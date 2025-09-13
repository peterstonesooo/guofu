<?php

namespace app\api\controller;

use app\api\service\StockService;
use app\model\StockTransactions;
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
        $today = date('Y-m-d'); // 获取当前日期

        // 从缓存获取全局股价
        $globalPrice = Cache::get('global_stock_price', 0);

        // 获取股权类型配置
        $stockTypes = StockTypes::whereIn('code', ['YSG001', 'LTG001', 'MRG001'])->select();
        if ($stockTypes->isEmpty()) {
            return out(null, 10001, '股权类型未配置');
        }

        // 获取用户所有套餐购买记录（成功支付的）
        $purchases = Db::name('package_purchases')
            ->where('user_id', $userId)
            ->where('status', 1) // 成功支付的
            ->select()
            ->toArray(); // 转换为数组

        // 提取套餐ID数组
        $packageIds = array_column($purchases, 'package_id');

        // 查询这些套餐的股权明细
        $packageItems = [];
        $packageTotalQuantities = [];
        if (!empty($packageIds)) {
            $packageItems = Db::name('stock_package_items')
                ->whereIn('package_id', $packageIds)
                ->select()
                ->toArray();

            // 计算每个套餐的总股权数量
            foreach ($packageItems as $item) {
                $packageId = $item['package_id'];
                if (!isset($packageTotalQuantities[$packageId])) {
                    $packageTotalQuantities[$packageId] = 0;
                }
                $packageTotalQuantities[$packageId] += $item['quantity'];
            }
        }

        $stocks = [];
        foreach ($stockTypes as $type) {
            $stockTypeId = $type['id'];

            // 获取该股权今日已卖出总量（type=2）
            $soldToday = Db::name('stock_transactions')
                ->where('user_id', $userId)
                ->where('stock_type_id', $stockTypeId)
                ->where('type', 2) // 卖出交易
                ->where('DATE(created_at)', $today) // 今日的交易
                ->sum('quantity') ?: 0;

            // 计算当日剩余可卖出数量
            $remainingToday = max(0, StockService::DAILY_SELL_LIMIT - $soldToday);

            // 获取普通购买（source=0）的股权信息
            $normalHold = UserStockWallets::where('user_id', $userId)
                ->where('stock_type_id', $stockTypeId)
                ->where('source', 0)
                ->sum('quantity') ?: 0;

            $normalCost = StockTransactions::where('user_id', $userId)
                ->where('stock_type_id', $stockTypeId)
                ->where('source', 0)
                ->where('type', 1) // 买入
                ->sum('amount') ?: 0;

            // 获取套餐购买（source>0）的股权信息
            $packageHold = UserStockWallets::where('user_id', $userId)
                ->where('stock_type_id', $stockTypeId)
                ->where('source', '>', 0)
                ->sum('quantity') ?: 0;

            // 重新计算套餐成本（按股权数量比例分摊）
            $packageCost = 0;
            if (!empty($purchases) && !empty($packageItems)) {
                foreach ($purchases as $purchase) {
                    $packageId = $purchase['package_id'];

                    // 跳过总数为0的套餐
                    if (!isset($packageTotalQuantities[$packageId]) || $packageTotalQuantities[$packageId] <= 0) {
                        continue;
                    }

                    // 查找该套餐中当前股权类型的数量
                    $quantityInPackage = 0;
                    foreach ($packageItems as $item) {
                        if ($item['package_id'] == $packageId && $item['stock_type_id'] == $stockTypeId) {
                            $quantityInPackage = $item['quantity'];
                            break;
                        }
                    }

                    if ($quantityInPackage > 0) {
                        // 分摊成本：该股权类型在该套餐中的数量 / 该套餐总股权数量 * 套餐购买金额
                        $cost = ($quantityInPackage / $packageTotalQuantities[$packageId]) * $purchase['amount'];
                        $packageCost += $cost;
                    }
                }
            }

            // 计算收益
            $normalValue = $normalHold * $globalPrice;
            $normalProfit = $normalValue - $normalCost;
            $normalProfitRate = $normalCost > 0 ? ($normalProfit / $normalCost) * 100 : 0;

            $packageValue = $packageHold * $globalPrice;
            $packageProfit = $packageValue - $packageCost;
            $packageProfitRate = $packageCost > 0 ? ($packageProfit / $packageCost) * 100 : 0;

            // 组装数据
            $stocks[$type['code']] = [
                'stock_name'               => $type['name'],
                'stock_code'               => $type['code'],
                'normal'                   => [
                    'hold_quantity' => $normalHold,
                    'total_cost'    => round($normalCost, 2),
                    'current_value' => round($normalValue, 2),
                    'profit'        => round($normalProfit, 2),
                    'profit_rate'   => round($normalProfitRate, 2)
                ],
                'package'                  => [ // 套餐获得
                    'hold_quantity' => $packageHold,
                    'total_cost'    => round($packageCost, 2),
                    'current_value' => round($packageValue, 2),
                    'profit'        => round($packageProfit, 2),
                    'profit_rate'   => round($packageProfitRate, 2)
                ],
                'total'                    => [ // 汇总信息
                    'hold_quantity' => $normalHold + $packageHold,
                    'current_value' => round($normalValue + $packageValue, 2)
                ],
                'daily_remaining_quantity' => $remainingToday // 当日剩余可卖出数量
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
        $user = $this->user;
        $stock_code = $this->request->param('stock_code', '');
        $quantity = $this->request->param('quantity/d', 0);
        $pay_password = $this->request->param('pay_password', '');
        $pay_type = $this->request->param('pay_type/d', 0);

        // 参数验证
        if (empty($stock_code) || $quantity <= 0 || !in_array($pay_type, [1, 2])) {
            return out(null, 10001, '参数错误');
        }

        // 支付密码验证（新增）
        if (empty($user['pay_password'])) {
            return out(null, 10010, '请先设置支付密码');
        }
        if (sha1(md5($pay_password)) !== $user['pay_password']) {
            return out(null, 10011, '支付密码错误');
        }

        try {
            $result = StockService::buyStock($user['id'], $stock_code, $quantity, $pay_type);
            if ($result) {
                return out(null, 200, '买入成功');
            }
            return out(null, 10002, '买入失败');
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
        $user = $this->user;
        $stock_code = $this->request->param('stock_code', '');
        $quantity = $this->request->param('quantity/d', 0);
        $pay_password = $this->request->param('pay_password', '');
        $pay_type = $this->request->param('pay_type/d', 0);
//        $source = $this->request->param('source/d', 0);
        $source = -1; // -1表示自动顺序

        // 参数验证
        if (empty($stock_code) || $quantity <= 0 || !in_array($pay_type, [1, 2])) {
            return out(null, 10001, '参数错误');
        }
//        if ($source < 0) { // source应为非负整数
//            return out(null, 10001, '来源参数错误');
//        }

        // 支付密码验证（新增）
        if (empty($user['pay_password'])) {
            return out(null, 10010, '请先设置支付密码');
        }
        if (sha1(md5($pay_password)) !== $user['pay_password']) {
            return out(null, 10011, '支付密码错误');
        }

        try {
            $result = StockService::sellStock($user['id'], $stock_code, $quantity, $pay_type, $source);
            if ($result) {
                return out(null, 200, '卖出成功');
            }
            return out(null, 10004, '卖出失败');
        } catch (\Exception $e) {
            return out(null, 10005, $e->getMessage());
        }
    }


    /**
     * 获取股权交易记录
     * @param string stock_code 股权代码 (可选，如 LTG001, MRG001)
     * @param integer type 交易类型 (可选，1=买入 2=卖出)
     * @param integer page 页码 (可选)
     * @param integer limit 每页条数 (可选)
     */
    public function transactionList()
    {
        $user_id = $this->user['id'];
        $stock_code = $this->request->param('stock_code', '');
        $type = $this->request->param('type/d', 0);
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        // 构建基础查询条件
        $where = [['user_id', '=', $user_id]];

        // 添加交易类型条件
        if (in_array($type, [1, 2])) {
            $where[] = ['type', '=', $type];
        }

        // 添加股权代码条件
        if (!empty($stock_code)) {
            // 根据股权代码查询对应的股权类型ID
            $stockType = Db::name('stock_types')
                ->where('code', $stock_code)
                ->find();

            if (!$stockType) {
                return out(null, 10008, '股权代码不存在');
            }

            $where[] = ['stock_type_id', '=', $stockType['id']];
        }

        try {
            // 查询交易记录
            $query = Db::name('stock_transactions')
                ->where($where)
                ->order('id', 'desc');

            // 获取总数
            $total = $query->count();

            // 获取分页数据 - 确保转换为数组
            $list = $query->page($page, $limit)->select()->toArray();

            // 如果查询结果不为空，补充股权代码信息
            if (!empty($list)) {
                // 获取所有涉及的股权类型ID
                $stockTypeIds = [];
                foreach ($list as $item) {
                    if (isset($item['stock_type_id'])) {
                        $stockTypeIds[] = $item['stock_type_id'];
                    }
                }
                $stockTypeIds = array_unique($stockTypeIds);

                if (!empty($stockTypeIds)) {
                    // 查询股权类型信息
                    $stockTypes = Db::name('stock_types')
                        ->whereIn('id', $stockTypeIds)
                        ->select()
                        ->toArray();

                    // 转换为以ID为键的数组
                    $stockTypeMap = [];
                    foreach ($stockTypes as $type) {
                        $stockTypeMap[$type['id']] = [
                            'code' => $type['code'],
                            'name' => $type['name']
                        ];
                    }

                    // 为每条记录添加股权代码和名称
                    foreach ($list as &$item) {
                        if (isset($item['stock_type_id']) && isset($stockTypeMap[$item['stock_type_id']])) {
                            $item['stock_code'] = $stockTypeMap[$item['stock_type_id']]['code'];
                            $item['stock_name'] = $stockTypeMap[$item['stock_type_id']]['name'];
                        } else {
                            $item['stock_code'] = '';
                            $item['stock_name'] = '未知股权';
                        }

                        // 添加交易类型文本
                        $item['type_text'] = $item['type'] == 1 ? '买入' : '卖出';
                    }
                }
            }

            return out([
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ], 200, 'success');

        } catch (\Exception $e) {
            return out(null, 10007, '查询失败: ' . $e->getMessage());
        }
    }
}