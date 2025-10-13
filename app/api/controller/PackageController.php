<?php

namespace app\api\controller;

use app\api\service\PackageService;
use app\api\service\StockActivityService;
use app\model\PackagePurchases;
use app\model\StockActivityClaims;
use app\model\StockPackages;
use app\model\User;
use think\facade\Cache;
use think\facade\Log;

class PackageController extends AuthController
{
    /**
     * 获取所有股权方案列表
     */
    public function packageList()
    {
        $user = $this->user; // 从鉴权中获取用户
        $userId = $user ? $user['id'] : 0;
        try {

            // 获取所有启用的股权方案并关联股权方案项及股票类型
            $packages = StockPackages::where('status', 1)
                ->with(['items' => function ($query) {
                    $query->with(['stockType' => function ($q) {
                        $q->field('id,name,code');
                    }]);
                }])
                ->select()
                ->toArray();

            if (empty($packages)) {
                return out([], 200, '暂无股权方案');
            }

            // 获取用户未过期股权方案ID
            $lockedPackageIds = [];
            if ($userId) {
                $expireTime = date('Y-m-d H:i:s', strtotime("-30 days")); // 锁定期计算
                $lockedPackageIds = \app\model\PackagePurchases::where('user_id', $userId)
                    ->where('status', 1)
                    ->where('created_at', '>', $expireTime)
                    ->column('package_id');
            }

            // 格式化股权方案数据
            $result = [];
            foreach ($packages as $package) {
                $items = [];
                foreach ($package['items'] as $item) {
                    // 确保返回股权方案项所有核心字段
                    $items[] = [
                        'id'            => $item['id'], // 股权方案项ID
                        'package_id'    => $item['package_id'], // 关联股权方案ID
                        'stock_type_id' => $item['stock_type_id'],
                        'stock_code'    => $item['stock_code'],
                        'quantity'      => $item['quantity'],
                        'stock_name'    => $item['stockType']['name'] ?? '未知类型', // 股票类型名称
                        'created_at'    => $item['created_at'], // 创建时间
                        'updated_at'    => $item['updated_at'], // 更新时间
                    ];
                }

                // 返回股权方案所有字段（排除敏感字段）
                $result[] = [
                    'id'               => $package['id'],
                    'name'             => $package['name'],
                    'price'            => $package['price'],
                    'lock_period'      => $package['lock_period'],
                    'daily_sell_limit' => $package['daily_sell_limit'],
                    'description'      => $package['description'] ?? '', // 股权方案描述
                    'status'           => $package['status'],
                    'is_locked'        => !in_array($package['id'], $lockedPackageIds), // 股权方案是否锁定（已购买且未过期）
                    'created_at'       => $package['created_at'],
                    'updated_at'       => $package['updated_at'],
                    'items'            => $items // 嵌套的股权方案项数据
                ];
            }

            return out($result, 200, 'success');
        } catch (\Exception $e) {
            return out([], 10001, '获取失败: ' . $e->getMessage());
        }
    }

    /**
     * 购买股权方案
     * @param integer package_id 股权方案ID
     * @param string pay_password 支付密码
     * @param integer pay_type 支付方式 (1=余额，2=可提现余额)
     */
    public function buyPackage()
    {
        $user = $this->user;
        $packageId = $this->request->param('package_id/d', 0);
        $payPassword = $this->request->param('pay_password', '');
        $payType = $this->request->param('pay_type/d', 0);
        // 参数验证
        if (empty($packageId) || !in_array($payType, [1, 2])) {
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
            // 调用服务层购买股权方案
            $result = PackageService::buyPackage($user['id'], $packageId, $payType);
            if ($result) {
                // 购买成功后处理团队奖励
                $this->processTeamBonus($user['id'], $packageId);

                return out(null, 200, '购买成功');
            }
            return out(null, 10002, '购买失败');
        } catch (\Exception $e) {
            return out(null, 10003, $e->getMessage());
        }
    }

    /**
     * 处理团队奖励
     * @param int $userId 用户ID
     * @param int $packageId 股权方案ID
     */
    private function processTeamBonus($userId, $packageId)
    {
        try {
            // 获取购买记录
            $purchase = PackagePurchases::where('user_id', $userId)
                ->where('package_id', $packageId)
                ->order('id', 'desc')
                ->find();

            if (!$purchase) {
                return;
            }

            // 获取股权方案信息
            $package = StockPackages::find($packageId);
            if (!$package) {
                return;
            }

            // 调用User模型的teamBonus方法处理团队奖励
            $userModel = new User();
            $userModel->teamBonus($userId, $package->price, $purchase->id);

        } catch (\Exception $e) {
            // 记录错误日志，但不影响主流程
            Log::error('处理团队奖励失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取用户已购买的股权方案记录（分页+日期搜索）
     * @param integer page 当前页码（默认1）
     * @param integer limit 每页数量（默认10）
     * @param string start_date 开始日期（格式Y-m-d）
     * @param string end_date 结束日期（格式Y-m-d）
     */
    public function purchasedRecords()
    {
        $user = $this->user;
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);
        $startDate = $this->request->param('start_date/s', '');
        $endDate = $this->request->param('end_date/s', '');

        try {
            // 构建基础查询
            $query = PackagePurchases::where('user_id', $user['id'])
                ->with(['package' => function ($q) {
                    $q->field('id,name,price');
                }])
                ->append(['pay_type_text'])
                ->order('created_at', 'desc');

            // 添加日期筛选
            if (!empty($startDate)) {
                $query->where('created_at', '>=', $startDate . ' 00:00:00');
            }
            if (!empty($endDate)) {
                $query->where('created_at', '<=', $endDate . ' 23:59:59');
            }

            // 执行分页查询
            $list = $query->paginate([
                'page'      => $page,
                'list_rows' => $limit,
                'path'      => 'javascript:;' // 防止生成URL
            ]);

            if ($list->isEmpty()) {
                return out([], 200, '暂无购买记录');
            }

            // 格式化数据
            $data = [];
            foreach ($list as $record) {
                $data[] = [
                    'id'            => $record->id,
                    'package_id'    => $record->package_id,
                    'package_name'  => $record->package->name ?? '已删除方案',
                    'amount'        => $record->amount,
                    'pay_type'      => $record->pay_type,
                    'pay_type_text' => $record->pay_type_text,
                    'status'        => $record->status,
                    'created_at'    => $record->created_at
                ];
            }

            return out([
                'list'         => $data,
                'total'        => $list->total(),
                'current_page' => $list->currentPage(),
                'last_page'    => $list->lastPage()
            ], 200, 'success');

        } catch (\Exception $e) {
            return out([], 10001, '查询失败: ' . $e->getMessage());
        }
    }

    /**
     * 领取活动股权
     */
    public function getNewUserPrize()
    {
        $user = $this->user;
        $lockKey = 'stock_activity:' . $user['id'];

        // 获取锁
        $lockIdentifier = $this->acquireLock($lockKey);
        if (!$lockIdentifier) {
            return out(null, 10007, '系统繁忙，请稍后再试');
        }

        try {
            // 检查用户是否已实名认证
            if (empty($user['realname'])) {
                return out(null, 10002, '请先完成实名认证');
            }

            // 检查活动时间（10月1日-10月8日）
            $currentDate = date('Y-m-d');
            $startDate = '2025-10-01';
            $endDate = '2025-10-08';

            if ($currentDate < $startDate || $currentDate > $endDate) {
                return out(null, 10003, '活动未开始或已结束');
            }

            // 检查用户注册时间，只有活动期间注册的用户才能领取
            $userCreateDate = date('Y-m-d', strtotime($user['created_at']));
            if ($userCreateDate < $startDate || $userCreateDate > $endDate) {
                return out(null, 10008, '只有活动期间注册的新用户才能领取');
            }

            // 检查是否已领取（双重检查，确保在锁内再次验证）
            $existingClaim = StockActivityClaims::where('user_id', $user['id'])
                ->where('activity_name', '国庆新人福利')
                ->find();

            if ($existingClaim) {
                return out(null, 10004, '您已领取过该活动股权');
            }

            // 调用服务层处理领取逻辑
            $result = StockActivityService::claimActivityStock(
                $user['id'],
                $user['phone'],
                '国庆新人福利',
                1800, // 流通股权数量
                3000  // 原始股权数量
            );

            if ($result) {
                return out(null, 200, '领取成功');
            }

            return out(null, 10005, '领取失败');
        } catch (DbException $e) {
            // 捕获数据库异常，特别是唯一约束违反异常
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return out(null, 10004, '您已领取过该活动股权');
            }
            return out(null, 10006, '系统错误，请稍后再试');
        } catch (\Exception $e) {
            return out(null, 10006, $e->getMessage());
        } finally {
            // 释放锁
            $this->releaseLock($lockKey, $lockIdentifier);
        }
    }


    /**
     * 自定义Redis锁实现
     */
    private function acquireLock($key, $timeout = 10)
    {
        $redis = Cache::store('redis')->handler();
        $lockKey = "lock:{$key}";
        $identifier = uniqid();

        $endTime = time() + $timeout;
        while (time() < $endTime) {
            // 尝试获取锁
            if ($redis->setnx($lockKey, $identifier)) {
                // 设置锁过期时间，防止死锁
                $redis->expire($lockKey, $timeout);
                return $identifier;
            }

            // 检查锁是否已过期但没有被释放
            $ttl = $redis->ttl($lockKey);
            if ($ttl < 0) {
                $redis->del($lockKey);
            }

            usleep(100000); // 等待100毫秒后重试
        }

        return false;
    }

    /**
     * 释放锁
     */
    private function releaseLock($key, $identifier)
    {
        $redis = Cache::store('redis')->handler();
        $lockKey = "lock:{$key}";

        // 只有锁的持有者才能释放锁
        if ($redis->get($lockKey) == $identifier) {
            $redis->del($lockKey);
            return true;
        }

        return false;
    }
}