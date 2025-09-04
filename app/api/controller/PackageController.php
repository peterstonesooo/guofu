<?php

namespace app\api\controller;

use app\api\service\PackageService;
use app\model\StockPackages;

class PackageController extends AuthController
{
    /**
     * 获取所有套餐列表
     */
    public function packageList()
    {
        $user = $this->user; // 从鉴权中获取用户
        $userId = $user ? $user['id'] : 0;
        try {

            // 获取所有启用的套餐并关联套餐项及股票类型
            $packages = StockPackages::where('status', 1)
                ->with(['items' => function ($query) {
                    $query->with(['stockType' => function ($q) {
                        $q->field('id,name,code');
                    }]);
                }])
                ->select()
                ->toArray();

            if (empty($packages)) {
                return out([], 200, '暂无套餐');
            }

            // 获取用户未过期套餐ID
            $lockedPackageIds = [];
            if ($userId) {
                $expireTime = date('Y-m-d H:i:s', strtotime("-30 days")); // 锁定期计算
                $lockedPackageIds = \app\model\PackagePurchases::where('user_id', $userId)
                    ->where('status', 1)
                    ->where('created_at', '>', $expireTime)
                    ->column('package_id');
            }

            // 格式化套餐数据
            $result = [];
            foreach ($packages as $package) {
                $items = [];
                foreach ($package['items'] as $item) {
                    // 确保返回套餐项所有核心字段
                    $items[] = [
                        'id'            => $item['id'], // 套餐项ID
                        'package_id'    => $item['package_id'], // 关联套餐ID
                        'stock_type_id' => $item['stock_type_id'],
                        'stock_code'    => $item['stock_code'],
                        'quantity'      => $item['quantity'],
                        'stock_name'    => $item['stockType']['name'] ?? '未知类型', // 股票类型名称
                        'created_at'    => $item['created_at'], // 创建时间
                        'updated_at'    => $item['updated_at'], // 更新时间
                    ];
                }

                // 返回套餐所有字段（排除敏感字段）
                $result[] = [
                    'id'               => $package['id'],
                    'name'             => $package['name'],
                    'price'            => $package['price'],
                    'lock_period'      => $package['lock_period'],
                    'daily_sell_limit' => $package['daily_sell_limit'],
                    'description'      => $package['description'] ?? '', // 套餐描述
                    'status'           => $package['status'],
                    'is_locked'        => !in_array($package['id'], $lockedPackageIds), // 套餐是否锁定（已购买且未过期）
                    'created_at'       => $package['created_at'],
                    'updated_at'       => $package['updated_at'],
                    'items'            => $items // 嵌套的套餐项数据
                ];
            }

            return out($result, 200, 'success');
        } catch (\Exception $e) {
            return out([], 10001, '获取失败: ' . $e->getMessage());
        }
    }

    /**
     * 购买套餐
     * @param integer package_id 套餐ID
     * @param string pay_password 支付密码
     * @param integer pay_type 支付方式 (1=现金，2=股权)
     */
    public function buyPackage()
    {
        $user = $this->user;
        $packageId = $this->request->param('package_id/d', 0);
        $payPassword = $this->request->param('pay_password', '');
        $payType = $this->request->param('pay_type/d', 0);

        // 参数验证
        if ($packageId <= 0 || !in_array($payType, [1, 2])) {
            return out(null, 10001, '参数错误');
        }

        // 支付密码验证
        if (empty($user['pay_password'])) {
            return out(null, 10010, '请先设置支付密码');
        }
        if (sha1(md5($payPassword)) !== $user['pay_password']) {
            return out(null, 10011, '支付密码错误');
        }

        try {
            // 调用服务层购买套餐
            $result = PackageService::buyPackage($user['id'], $packageId, $payType);
            if ($result) {
                return out(null, 200, '购买成功');
            }
            return out(null, 10002, '购买失败');
        } catch (\Exception $e) {
            return out(null, 10003, $e->getMessage());
        }
    }
}