<?php

namespace app\api\service;

use app\model\StockPackages;
use app\model\StockTransactions;
use app\model\StockTypes;
use app\model\User;
use app\model\UserStockDetails;
use app\model\UserStockWallets;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;

class StockService
{

    const DAILY_SELL_LIMIT = 10; // 全局每日卖出限额

    // 获取实时股价（从缓存获取）
    public static function getCurrentPrice()
    {
        return Cache::get('global_stock_price', 1.00);
    }

    // 买入股权
    public static function buyStock($user_id, $stock_code, $quantity, $pay_type = 1)
    {
        $price = self::getCurrentPrice();
        $amount = bcmul($quantity, $price, 2);
        // 根据支付类型选择余额字段
        $balanceField = ($pay_type == 1) ? 'topup_balance' : 'team_bonus_balance';

        Db::startTrans();
        try {
            // 根据code获取股权类型ID
            $stockType = StockTypes::where('code', $stock_code)
                ->find();

            if (!$stockType) {
                throw new Exception('股权类型不存在');
            }
            $stock_type_id = $stockType['id'];

            // 扣减余额
            User::changeInc($user_id, -$amount, $balanceField, 91, 0, 1, "股权买入:{$quantity}股");

            // 更新钱包
            $wallet = UserStockWallets::where('user_id', $user_id)
                ->where('stock_type_id', $stock_type_id)
                ->where('source', 0)
                ->findOrEmpty();

            if ($wallet->isEmpty()) {
                $wallet = UserStockWallets::create([
                    'user_id'         => $user_id,
                    'stock_type_id'   => $stock_type_id,
                    'quantity'        => $quantity,
                    'frozen_quantity' => 0,
                    'source'          => 0
                ]);
            } else {
                $wallet->quantity += $quantity;
                $wallet->save();
            }

            // 记录交易
            $sn = build_order_sn($user_id, 'ST');
            StockTransactions::create([
                'user_id'       => $user_id,
                'stock_type_id' => $stock_type_id,
                'type'          => StockTransactions::TYPE_BUY, // 买入
                'source'        => 0, // 来源:买入股权
                'quantity'      => $quantity,
                'price'         => $price,
                'amount'        => $amount,
                'status'        => 1, // 成功
                'remark'        => "买入{$quantity}股 @ {$price}元",
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 卖出股权
     * @param int $user_id 用户ID
     * @param string $stock_code 股权代码
     * @param int $quantity 卖出数量
     * @param int $pay_type 支付方式 (1=充值余额, 2=团队奖金余额)
     * @param int $source 来源 (0=直接购买, 其他=mp_stock_packages.id)
     * @return bool
     * @throws \Exception
     */
    public static function sellStock($user_id, $stock_code, $quantity, $pay_type = 1, $source = -1)
    {
        $price = self::getCurrentPrice();
        $amount = bcmul($quantity, $price, 2);
        $balanceField = ($pay_type == 1) ? 'topup_balance' : 'team_bonus_balance';

        Db::startTrans();
        try {
            // 1. 获取股权类型信息
            $stockType = StockTypes::where('code', $stock_code)->find();
            if (!$stockType) {
                throw new \Exception('股权类型不存在');
            }
            $stock_type_id = $stockType['id'];

            // 2. 执行卖出操作
            $soldQuantity = 0;

            if ($source == 0) {
                // 卖出普通股权
                $soldQuantity = self::sellNormalStock($user_id, $stock_type_id, $quantity, $price);
            } elseif ($source > 0) {
                // 卖出特定套餐股权
                $soldQuantity = self::sellSpecificPackageStock($user_id, $stock_type_id, $quantity, $price, $source);
            } else {
                // 自动顺序卖出（先普通后套餐，按FIFO）
                $soldQuantity = self::sellAutoSequence($user_id, $stock_type_id, $quantity, $price);
            }

            // 3. 检查是否完全卖出
            if ($soldQuantity < $quantity) {
                throw new \Exception('部分股权无法卖出 请改天再卖出 ，实际卖出: ' . $soldQuantity . '股');
            }

            // 4. 增加用户余额
            User::changeInc(
                $user_id,
                $amount,
                $balanceField,
                92, // 日志类型：卖出股权
                0,
                1,
                "股权卖出:{$soldQuantity}股 @ {$price}元"
            );

            Db::commit();
            return true;

        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 自动顺序卖出（先普通后股权方案，按FIFO原则）
     */
    private static function sellAutoSequence($user_id, $stock_type_id, $quantity, $price)
    {
        $remaining = $quantity;
        $soldTotal = 0;
        $currentTime = date('Y-m-d H:i:s');

        // 1. 先卖出普通股权（source=0）
        $normalWallet = UserStockWallets::where('user_id', $user_id)
            ->where('stock_type_id', $stock_type_id)
            ->where('source', 0)
            ->lock(true)
            ->find();

        if ($normalWallet && $normalWallet->quantity > 0) {
            // 检查普通股权的每日额度
            $normalQuota = self::getDailyRemainingQuota($user_id, $stock_type_id, 0);
            $sellNormal = min($normalWallet->quantity, $remaining, $normalQuota);

            if ($sellNormal > 0) {
                $normalWallet->quantity -= $sellNormal;
                $normalWallet->save();

                self::recordSellTransaction($user_id, $stock_type_id, $sellNormal, $price, 0);

                $soldTotal += $sellNormal;
                $remaining -= $sellNormal;
            }
        }

        // 2. 再卖出股权方案股权（按购买时间顺序，FIFO）
        if ($remaining > 0) {
            // 获取所有有效股权方案明细，按购买时间排序（FIFO）
            $details = UserStockDetails::where('user_id', $user_id)
                ->where('stock_type_id', $stock_type_id)
                ->where('remaining_quantity', '>', 0)
                ->where('available_at', '<=', $currentTime)
                ->where('status', 1) // 有效状态
                ->order('id', 'asc') // 按购买时间先进先出
                ->select();

            foreach ($details as $detail) {
                if ($remaining <= 0)
                    break;

                // 获取对应source的钱包
                $wallet = UserStockWallets::where('user_id', $user_id)
                    ->where('stock_type_id', $stock_type_id)
                    ->where('source', $detail->packagePurchase->package_id)
                    ->lock(true)
                    ->find();

                if (!$wallet || $wallet->quantity <= 0)
                    continue;

                // 检查该股权方案的每日额度
                $packageQuota = self::getDailyRemainingQuota($user_id, $stock_type_id, $wallet->source);
                if ($packageQuota <= 0)
                    continue;

                // 计算可卖出数量（取最小值）
                $sellQuantity = min(
                    $detail['remaining_quantity'], // 明细剩余数量
                    $wallet->quantity,             // 钱包可用数量
                    $remaining,                    // 还需要卖出的数量
                    $packageQuota                 // 每日剩余额度
                );

                if ($sellQuantity <= 0)
                    continue;

                // 更新明细
                UserStockDetails::where('id', $detail['id'])
                    ->dec('remaining_quantity', $sellQuantity)
                    ->update();

                // 更新钱包
                $wallet->quantity -= $sellQuantity;
                $wallet->save();

                // 记录交易
                self::recordSellTransaction($user_id, $stock_type_id, $sellQuantity, $price, $wallet->source);

                $soldTotal += $sellQuantity;
                $remaining -= $sellQuantity;
            }
        }

        return $soldTotal;
    }

    /**
     * 卖出普通股权（source=0）
     */
    private static function sellNormalStock($user_id, $stock_type_id, $quantity, $price)
    {
        // 1. 获取钱包并锁定
        $wallet = UserStockWallets::where('user_id', $user_id)
            ->where('stock_type_id', $stock_type_id)
            ->where('source', 0)
            ->lock(true)
            ->find();

        if (!$wallet || $wallet->quantity < $quantity) {
            throw new \Exception('普通股权数量不足');
        }

        // 2. 检查每日额度
        $remainingQuota = self::getDailyRemainingQuota($user_id, $stock_type_id, 0);
        if ($quantity > $remainingQuota) {
            throw new \Exception("普通股权今日可卖出额度不足，剩余: {$remainingQuota}股");
        }

        // 3. 执行卖出
        $wallet->quantity -= $quantity;
        $wallet->save();

        // 4. 记录交易和更新额度
        self::recordSellTransaction($user_id, $stock_type_id, $quantity, $price, 0);

        return $quantity;
    }

    /**
     * 卖出特定股权方案股权
     */
    private static function sellSpecificPackageStock($user_id, $stock_type_id, $quantity, $price, $source)
    {
        $currentTime = date('Y-m-d H:i:s');
        $sold = 0;
        $remaining = $quantity;

        // 1. 获取该股权方案的有效股权明细（按购买时间排序，FIFO）
        $details = UserStockDetails::where('user_id', $user_id)
            ->where('stock_type_id', $stock_type_id)
            ->where('package_id', $source)
            ->where('remaining_quantity', '>', 0)
            ->where('available_at', '<=', $currentTime)
            ->where('status', 1) // 有效状态
            ->order('id', 'asc') // 按购买时间先进先出
            ->select();

        foreach ($details as $detail) {
            if ($remaining <= 0)
                break;

            // 2. 获取对应钱包并锁定
            $wallet = UserStockWallets::where('user_id', $user_id)
                ->where('stock_type_id', $stock_type_id)
                ->where('source', $source)
                ->lock(true)
                ->find();

            if (!$wallet || $wallet->quantity <= 0)
                continue;

            // 3. 检查每日额度
            $packageQuota = self::getDailyRemainingQuota($user_id, $stock_type_id, $source);
            if ($packageQuota <= 0)
                continue;

            // 4. 计算可卖出数量（取最小值）
            $sellQuantity = min(
                $detail['remaining_quantity'], // 明细剩余数量
                $wallet->quantity,             // 钱包可用数量
                $remaining,                    // 还需要卖出的数量
                $packageQuota                  // 每日剩余额度
            );

            if ($sellQuantity <= 0)
                continue;

            // 5. 更新明细
            UserStockDetails::where('id', $detail['id'])
                ->dec('remaining_quantity', $sellQuantity)
                ->update();

            // 6. 更新钱包
            $wallet->quantity -= $sellQuantity;
            $wallet->save();

            // 7. 记录交易
            self::recordSellTransaction($user_id, $stock_type_id, $sellQuantity, $price, $source);

            $sold += $sellQuantity;
            $remaining -= $sellQuantity;
        }

        if ($sold < $quantity) {
            throw new \Exception('股权方案股权数量或额度不足，仅能卖出: ' . $sold . '股');
        }

        return $sold;
    }

    /**
     * 获取今日剩余可卖出额度
     */
    private static function getDailyRemainingQuota($user_id, $stock_type_id, $source)
    {
        // 检查是否为MRG001股权类型
        $stockType = StockTypes::find($stock_type_id);
        if ($stockType && $stockType->code == 'MRG001') {
            // 如果是MRG001，返回一个很大的数，表示无限制
            return 999999;
        }

        $today = date('Y-m-d');

        // 获取该来源今日已卖出量
        $soldToday = StockTransactions::where('user_id', $user_id)
            ->where('stock_type_id', $stock_type_id)
            ->where('source', $source)
            ->where('type', 2) // 卖出
            ->where('DATE(created_at)', $today)
            ->sum('quantity') ?: 0;

        // 确定每日限额
        $dailyLimit = self::DAILY_SELL_LIMIT;
        if ($source > 0) {
            // 如果是股权方案，检查是否有独立限额
            $package = StockPackages::find($source);
            if ($package && isset($package['daily_sell_limit'])) {
                $dailyLimit = $package['daily_sell_limit'];
            }
        }

        // 计算剩余额度
        $remaining = $dailyLimit - $soldToday;
        return max(0, $remaining);
    }

    /**
     * 记录卖出交易
     */
    private static function recordSellTransaction($user_id, $stock_type_id, $quantity, $price, $source)
    {
        $amount = bcmul($quantity, $price, 2);

        StockTransactions::create([
            'user_id'       => $user_id,
            'stock_type_id' => $stock_type_id,
            'type'          => 2, // 卖出
            'source'        => $source,
            'quantity'      => $quantity,
            'price'         => $price,
            'amount'        => $amount,
            'status'        => 1,
            'remark'        => "卖出{$quantity}股" . ($source > 0 ? "(股权方案来源)" : ""),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s')
        ]);
    }
}