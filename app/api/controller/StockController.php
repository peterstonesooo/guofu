<?php

namespace app\api\controller;

use app\model\StockTypes;
use app\model\UserStockWallets;
use think\facade\Cache;
use think\facade\Db;

// 新增用户股权钱包模型

class StockController extends AuthController
{
    /**
     * 获取用户股权信息
     */
    public function userStockInfo()
    {
        $user = $this->user;
        $userId = $user['id'];

        // 从缓存获取全局股价
        $globalPrice = Cache::get('global_stock_price', 0);

        // 获取股权类型配置
        $stockTypes = StockTypes::whereIn('code', ['YSG001', 'LTG001', 'MRG001'])->select();
        if ($stockTypes->isEmpty()) {
            return out(null, 10001, '股权类型未配置');
        }

        $stocks = [];
        foreach ($stockTypes as $type) {
            // 直接从用户股权钱包表获取持有数量
            $wallet = UserStockWallets::where('user_id', $userId)
                ->where('stock_type_id', $type->id)
                ->find();

            $holdQuantity = $wallet ? $wallet->quantity : 0;

            // 计算当前价值
            $currentValue = $holdQuantity * $globalPrice;

            // 获取用户购买该股权的总成本（从交易记录）
            $totalCost = Db::name('stock_transactions')
                ->where('user_id', $userId)
                ->where('stock_type_id', $type->id)
                ->where('type', 1) // 假设1为买入类型
                ->where('status', 1)
                ->sum('amount');

            // 计算收益和收益率
            $profit = $currentValue - $totalCost;
            $profitRate = $totalCost > 0 ? ($profit / $totalCost) * 100 : 0;

            // 使用 stock_code 作为键
            $stocks[$type->code] = [
                'stock_name'    => $type->name,
                'stock_code'    => $type->code,
                'hold_quantity' => $holdQuantity,
                'current_value' => round($currentValue, 2),
                'global_price'  => $globalPrice,
                'total_cost'    => round($totalCost, 2),
                'profit'        => round($profit, 2),
                'profit_rate'   => round($profitRate, 2)
            ];
        }

        return out([
            'global_price' => $globalPrice,
            'stocks'       => $stocks
        ]);
    }
}