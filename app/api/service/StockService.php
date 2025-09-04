<?php

namespace app\api\service;

use app\model\StockTransactions;
use app\model\User;
use app\model\UserStockWallets;
use Exception;
use think\facade\Cache;
use think\facade\Db;

class StockService
{
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
            $stockType = Db::name('stock_types')
                ->where('code', $stock_code)
                ->find();

            if (!$stockType) {
                throw new Exception('股权类型不存在');
            }
            $stock_type_id = $stockType['id'];

            // 1. 扣减用户余额
            User::changeInc($user_id, -$amount, $balanceField, 91, 0, 1, "股权买入:{$quantity}股");

            // 2. 更新股权钱包
            $wallet = UserStockWallets::where('user_id', $user_id)
                ->where('stock_type_id', $stock_type_id)
                ->findOrEmpty();

            if ($wallet->isEmpty()) {
                $wallet = UserStockWallets::create([
                    'user_id'       => $user_id,
                    'stock_type_id' => $stock_type_id,
                    'quantity'      => $quantity
                ]);
            } else {
                $wallet->quantity += $quantity;
                $wallet->save();
            }

            // 3. 创建交易记录
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
    public static function sellStock($user_id, $stock_code, $quantity, $pay_type = 1, $source = 0)
    {
        $price = self::getCurrentPrice();
        $amount = bcmul($quantity, $price, 2);
        $balanceField = ($pay_type == 1) ? 'topup_balance' : 'team_bonus_balance';

        Db::startTrans();
        try {
            // 根据code获取股权类型
            $stockType = Db::name('stock_types')->where('code', $stock_code)->find();
            if (!$stockType) {
                throw new \Exception('股权类型不存在');
            }
            $stock_type_id = $stockType['id'];

            // 1. 获取指定来源的钱包记录（唯一记录）
            $wallet = UserStockWallets::where('user_id', $user_id)
                ->where('stock_type_id', $stock_type_id)
                ->where('source', $source)
                ->lock(true)
                ->find();

            if (!$wallet || $wallet->quantity < $quantity) {
                throw new Exception('可用股权不足');
            }
            // 如果 source != 0，表示来自套餐购买，需要检查套餐的每日出售限制
            if ($source != 0) {
                $package = Db::name('stock_packages')->where('id', $source)->find();
                if (!$package) {
                    throw new \Exception('关联的股权套餐不存在');
                }
                // 检查解锁状态
                $currentTime = time();
                $isUnlocked = empty($wallet->unlock_date) || $currentTime < strtotime($wallet->unlock_date);
                if (!$isUnlocked) {
                    throw new \Exception('股权仍在锁定期，无法卖出');
                }

                $today = date('Y-m-d');
                // 查询该用户今日卖出该套餐的股权总数
                $todaySold = StockTransactions::where('user_id', $user_id)
                    ->where('stock_type_id', $stock_type_id)
                    ->where('source', $source)
                    ->where('type', 2) // 卖出类型
                    ->where('DATE(created_at)', $today)
                    ->sum('quantity') ?: 0;

                $remainingQuotaToday = $package['daily_sell_limit'] - $todaySold;
                if ($quantity > $remainingQuotaToday) {
                    throw new \Exception("今日该套餐股权可卖出额度不足，还可卖出 {$remainingQuotaToday} 股");
                }
            } else {
                // 流通股检查每日额度
                if ($stockType['code'] === 'LTG001') {
                    $today = date('Y-m-d');

                    // 固定每日额度为10股
                    $dailyQuota = 10;

                    // 查询今日已卖出数量
                    $todaySold = StockTransactions::where('user_id', $user_id)
                        ->where('stock_type_id', $stock_type_id)
                        ->where('source', $source)
                        ->where('type', 2) // 卖出类型
                        ->where('DATE(created_at)', $today) // 今日的交易
                        ->sum('quantity') ?: 0;

                    $todaySold = $todaySold ?: 0; // 如果为空则设为0

                    // 计算今日剩余可卖额度
                    $remainingQuotaToday = $dailyQuota - $todaySold;

                    // 检查今日是否还可卖
                    if ($quantity > $remainingQuotaToday) {
                        throw new Exception("今日流通股可卖出额度不足，还可卖出 {$remainingQuotaToday} 股");
                    }
                }
            }

            // 5. 更新钱包数量
            $wallet->quantity -= $quantity;
            $wallet->save();

            // 6. 记录股权交易
            $sn = build_order_sn($user_id, 'ST');
            StockTransactions::create([
                'user_id'       => $user_id,
                'stock_type_id' => $stock_type_id,
                'type'          => StockTransactions::TYPE_SELL,
                'source'        => $source,
                'quantity'      => $quantity,
                'price'         => $price,
                'amount'        => $amount,
                'status'        => 1,
                'remark'        => "卖出{$quantity}股 @ {$price}元",
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ]);

            // 7. 增加用户余额
            User::changeInc(
                $user_id,
                $amount,
                $balanceField,
                92,
                0,
                1,
                "股权卖出:{$quantity}股"
            );

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}