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
                'source'        => 2, // 来源:买入股权
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

    // 卖出股权
    public static function sellStock($user_id, $stock_code, $quantity, $pay_type = 1)
    {
        $price = self::getCurrentPrice();
        $amount = bcmul($quantity, $price, 2);

        // 根据支付类型选择余额字段
        $balanceField = ($pay_type == 1) ? 'topup_balance' : 'team_bonus_balance';

        Db::startTrans();
        try {
            // 根据code获取股权类型
            $stockType = Db::name('stock_types')
                ->where('code', $stock_code)
                ->find();

            if (!$stockType) {
                throw new Exception('股权类型不存在');
            }
            $stock_type_id = $stockType['id'];

            // 1. 检查可用股数
            $wallet = UserStockWallets::where('user_id', $user_id)
                ->where('stock_type_id', $stock_type_id)
                ->lock(true)
                ->find();

            if (!$wallet || $wallet->quantity < $quantity) {
                throw new Exception('可用股权不足');
            }

            // 2. 流通股检查每日额度
            if ($stockType['code'] === 'LTG001') {
                $today = date('Y-m-d');

                // 固定每日额度为10股
                $dailyQuota = 10;

                // 查询今日已卖出数量
                $todaySold = Db::name('stock_transactions')
                    ->where('user_id', $user_id)
                    ->where('stock_type_id', $stock_type_id)
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

            // 3. 更新股权钱包
            $wallet->quantity -= $quantity;
            $wallet->save();

            // 4. 增加用户余额
            User::changeInc(
                $user_id,
                $amount,
                $balanceField,
                92, // 新日志类型:股权卖出
                0,
                1,
                "股权卖出:{$quantity}股"
            );

            // 5. 创建交易记录
            $sn = build_order_sn($user_id, 'ST');
            StockTransactions::create([
                'user_id'       => $user_id,
                'stock_type_id' => $stock_type_id,
                'type'          => StockTransactions::TYPE_SELL, // 卖出
                'source'        => $stockType['code'] === 'LTG001' ? 1 : 2, // 来源
                'quantity'      => $quantity,
                'price'         => $price,
                'amount'        => $amount,
                'status'        => 1, // 成功
                'remark'        => "卖出{$quantity}股 @ {$price}元",
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
}