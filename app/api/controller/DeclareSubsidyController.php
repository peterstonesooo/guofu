<?php

namespace app\api\controller;

use app\api\service\DeclareSubsidyService;
use app\model\subsidy_butie\DeclareRecord;
use app\model\subsidy_butie\DeclareSubsidyConfig;
use app\model\subsidy_butie\DeclareSubsidyType;
use think\facade\Cache;

class DeclareSubsidyController extends AuthController
{
    // 缓存键名（与后台共用）
    const CACHE_KEY_CONFIG_LIST = 'declare_subsidy_config_list';
    const CACHE_KEY_TYPE_LIST = 'declare_subsidy_type_list';

    /**
     * 获取申报补贴类型列表（带缓存）
     */
    public function typeList()
    {
        try {
            $cacheKey = self::CACHE_KEY_TYPE_LIST . ':api';
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if (!is_null($cachedData) && $cachedData !== false) {
                $typeList = unserialize($cachedData);
            } else {
                $typeList = DeclareSubsidyType::where('status', 1)
                    ->order('sort', 'desc')
                    ->order('id', 'desc')
                    ->select()
                    ->toArray();

                // 缓存10分钟
                $redis->setex($cacheKey, 600, serialize($typeList));
            }

            return out([
                'list' => $typeList
            ]);

        } catch (\Exception $e) {
            return out(null, 10001, '获取失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取申报补贴配置列表（带缓存）
     * @param integer type_id 补贴类型ID（可选）
     */
    public function configList()
    {
        $type_id = $this->request->param('type_id/d', 0);

        try {
            $cacheKey = self::CACHE_KEY_CONFIG_LIST . ':api:' . $type_id;
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                $configList = unserialize($cachedData);
            } else {
                $query = DeclareSubsidyConfig::with(['subsidyType', 'subsidyFunds' => function ($q) {
                    $q->with('fundType');
                }])
                    ->where('status', 1)
                    ->order('sort', 'desc')
                    ->order('id', 'desc');

                if ($type_id > 0) {
                    $query->where('type_id', $type_id);
                }

                $configList = $query->select()->toArray();

                // 处理资金配置数据
                foreach ($configList as &$config) {
                    if (isset($config['subsidy_funds'])) {
                        $config['funds'] = $config['subsidy_funds'];
                        unset($config['subsidy_funds']);
                    }
                }

                // 缓存10分钟
                if (!empty($configList)) {
                    $redis->setex($cacheKey, 600, serialize($configList));
                }

            }

            return out([
                'list' => $configList
            ]);

        } catch (\Exception $e) {
            return out(null, 10002, '获取失败: ' . $e->getMessage());
        }
    }

    /**
     * 购买申报补贴配置
     * @param integer subsidy_id 补贴配置ID
     * @param integer pay_type 支付方式 (1=充值余额, 2=团队奖金余额)
     * @param string pay_password 支付密码
     */
    public function buyConfig()
    {
        $user = $this->user;
        $subsidy_id = $this->request->param('subsidy_id/d', 0);
        $pay_type = $this->request->param('pay_type/d', 0);
        $pay_password = $this->request->param('pay_password', '');

        // 参数验证
        if ($subsidy_id <= 0 || !in_array($pay_type, [1, 2])) {
            return out(null, 10001, '参数错误');
        }

        // 支付密码验证
        if (empty($user['pay_password'])) {
            return out(null, 10010, '请先设置支付密码');
        }
        if (sha1(md5($pay_password)) !== $user['pay_password']) {
            return out(null, 10011, '支付密码错误');
        }

        try {
            $result = DeclareSubsidyService::buyConfig($user['id'], $subsidy_id, $pay_type);
            if ($result) {
                // 清除相关缓存
                $this->clearCache();
                return out(null, 200, '购买成功');
            }
            return out(null, 10003, '购买失败');
        } catch (\Exception $e) {
            return out(null, 10004, $e->getMessage());
        }
    }

    /**
     * 获取申报补贴购买记录
     * @param integer page 页码
     * @param integer limit 每页条数
     */
    public function purchaseList()
    {
        $user_id = $this->user['id'];
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        try {
            // 查询购买记录
            $query = DeclareRecord::with(['subsidyConfig' => function ($q) {
                $q->field('id,name,declare_amount,declare_cycle');
            }])
                ->where('user_id', $user_id)
                ->order('id', 'desc');

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->page($page, $limit)->select()->toArray();

            // 处理数据格式
            foreach ($list as &$item) {
                $item['subsidy_name'] = $item['subsidy_config']['name'] ?? '';
                $item['declare_amount'] = $item['subsidy_config']['declare_amount'] ?? 0;
                $item['declare_cycle'] = $item['subsidy_config']['declare_cycle'] ?? 0;
                unset($item['subsidy_config']);

                // 添加状态文本
                $statusMap = [
                    0 => '审核失败',
                    1 => '审核成功'
                ];
                $item['status_text'] = $statusMap[$item['status']] ?? '未知状态';
            }

            return out([
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ]);

        } catch (\Exception $e) {
            return out(null, 10005, '查询失败: ' . $e->getMessage());
        }
    }

    /**
     * 清除缓存（供后台调用）
     */
    private function clearCache()
    {
        try {
            $redis = Cache::store('redis')->handler();

            // 清除类型列表缓存
            $typePattern = self::CACHE_KEY_TYPE_LIST . '*';
            $this->clearCacheByPattern($redis, $typePattern);

            // 清除配置列表缓存
            $configPattern = self::CACHE_KEY_CONFIG_LIST . '*';
            $this->clearCacheByPattern($redis, $configPattern);

        } catch (\Exception $e) {
            \think\facade\Log::error('清除申报补贴缓存失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据模式清除缓存
     */
    private function clearCacheByPattern($redis, $pattern)
    {
        $iterator = null;

        do {
            $keys = $redis->scan($iterator, $pattern, 100);
            if ($keys !== false && !empty($keys)) {
                $redis->del($keys);
            }
        } while ($iterator > 0);
    }
}