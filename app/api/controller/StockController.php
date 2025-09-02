<?php

namespace app\api\controller;

use app\api\service\StockService;
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


    /**
     * 买入股权
     * @param string stock_code 股权代码 (如LTG001, MRG001)
     * @param integer quantity 购买数量
     */
    public function buyStock()
    {
        $user_id = $this->user['id'];
        $stock_code = $this->request->param('stock_code', ''); // 改为字符串参数
        $quantity = $this->request->param('quantity/d', 0);

        // 参数验证
        if (empty($stock_code) || $quantity <= 0) {
            return out(null, 10001, '参数错误');
        }

        try {
            $result = StockService::buyStock($user_id, $stock_code, $quantity);
            if ($result) {
                return out(null, 200, '买入成功');
            } else {
                return out(null, 10002, '买入失败');
            }
        } catch (\Exception $e) {
            return out(null, 10003, $e->getMessage());
        }
    }

    /**
     * 卖出股权
     * @param string stock_code 股权代码
     * @param integer quantity 卖出数量
     */
    public function sellStock()
    {
        $user_id = $this->user['id'];
        $stock_code = $this->request->param('stock_code', ''); // 改为字符串参数
        $quantity = $this->request->param('quantity/d', 0);

        // 参数验证
        if (empty($stock_code) || $quantity <= 0) {
            return out(null, 10001, '参数错误');
        }

        try {
            $result = StockService::sellStock($user_id, $stock_code, $quantity);
            if ($result) {
                return out(null, 200, '卖出成功');
            } else {
                return out(null, 10004, '卖出失败');
            }
        } catch (\Exception $e) {
            return out(null, 10005, $e->getMessage());
        }
    }


    /**
     * 获取股权交易记录
     * @param integer page 页码 (可选)
     * @param integer limit 每页条数 (可选)
     * @param integer type 交易类型 (可选，1买入 2卖出)
     */
    public function transactionList()
    {
        $user_id = $this->user['id'];
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);
        $type = $this->request->param('type/d', 0);

        $where = [['user_id', '=', $user_id]];
        if (in_array($type, [1, 2])) {
            $where[] = ['type', '=', $type];
        }

        try {
            $list = Db::name('stock_transactions')
                ->where($where)
                ->page($page, $limit)
                ->order('id', 'desc')
                ->select();
            $total = Db::name('stock_transactions')->where($where)->count();

            return out([
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page
            ], 200, 'success');
        } catch (\Exception $e) {
            return out(null, 10007, $e->getMessage());
        }
    }
}