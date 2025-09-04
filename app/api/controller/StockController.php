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
            // 1. 普通购买的股权信息
            $normalHold = UserStockWallets::where('user_id', $userId)
                ->where('stock_type_id', $type->id)
                ->where('source', 0) // 普通购买
                ->sum('quantity');

            // 普通购买的成本
            $normalCost = Db::name('stock_transactions')
                ->where('user_id', $userId)
                ->where('stock_type_id', $type->id)
                ->where('source', 0) // 普通购买
                ->where('type', 1) // 买入
                ->where('status', 1)
                ->sum('amount');

            // 2. 套餐获得的股权信息
            $packageHold = UserStockWallets::where('user_id', $userId)
                ->where('stock_type_id', $type->id)
                ->where('source', '>', 0) // 套餐购买
                ->sum('quantity');

            // 套餐购买的成本（按比例分摊）
            $packageCost = Db::name('package_purchases')
                ->alias('pp')
                ->join('stock_package_items spi', 'spi.package_id = pp.package_id')
                ->where('pp.user_id', $userId)
                ->where('spi.stock_type_id', $type->id)
                ->sum('pp.amount');

            // 分别计算收益
            $normalValue = $normalHold * $globalPrice;
            $normalProfit = $normalValue - $normalCost;
            $normalProfitRate = $normalCost > 0 ? ($normalProfit / $normalCost) * 100 : 0;

            $packageValue = $packageHold * $globalPrice;
            $packageProfit = $packageValue - $packageCost;
            $packageProfitRate = $packageCost > 0 ? ($packageProfit / $packageCost) * 100 : 0;

            $stocks[$type->code] = [
                'stock_name' => $type->name,
                'stock_code' => $type->code,
                'normal'     => [ // 普通购买
                    'hold_quantity' => $normalHold,
                    'total_cost'    => round($normalCost, 2),
                    'current_value' => round($normalValue, 2),
                    'profit'        => round($normalProfit, 2),
                    'profit_rate'   => round($normalProfitRate, 2)
                ],
                'package'    => [ // 套餐获得
                    'hold_quantity' => $packageHold,
                    'total_cost'    => round($packageCost, 2),
                    'current_value' => round($packageValue, 2),
                    'profit'        => round($packageProfit, 2),
                    'profit_rate'   => round($packageProfitRate, 2)
                ],
                'total'      => [ // 汇总信息（可选）
                    'hold_quantity' => $normalHold + $packageHold,
                    'current_value' => round($normalValue + $packageValue, 2)
                ]
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
        $source = $this->request->param('source/d', 0);

        // 参数验证
        if (empty($stock_code) || $quantity <= 0 || !in_array($pay_type, [1, 2])) {
            return out(null, 10001, '参数错误');
        }
        if ($source < 0) { // source应为非负整数
            return out(null, 10001, '来源参数错误');
        }

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