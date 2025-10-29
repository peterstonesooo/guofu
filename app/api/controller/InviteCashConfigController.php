<?php

namespace app\api\controller;

use app\model\invite_present\InviteCashConfig;
use think\facade\Cache;

class InviteCashConfigController extends AuthController
{
    // Redis缓存键名
    const INVITE_CASH_CONFIG_KEY = 'api_invite_cash_config_list';

    /**
     * 获取邀请现金红包配置列表
     */
    public function getConfigList()
    {
        try {
            // 生成缓存键
            $cacheKey = self::INVITE_CASH_CONFIG_KEY;
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                // 如果缓存存在，直接使用缓存数据
                $data = unserialize($cachedData);
            } else {
                // 从数据库查询启用状态的配置
                $data = InviteCashConfig::where('status', InviteCashConfig::STATUS_ENABLED)
                    ->order('invite_num', 'asc')
                    ->select()
                    ->toArray();

                // 将数据存入缓存（1小时缓存）
                $redis->setex($cacheKey, 3600, serialize($data));
            }

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            // 异常时从数据库查询，但不使用缓存
            $data = InviteCashConfig::where('status', InviteCashConfig::STATUS_ENABLED)
                ->order('invite_num', 'asc')
                ->select()
                ->toArray();

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $data
            ]);
        }
    }


    /**
     * 清除API缓存（供后台管理调用）
     */
    public function clearCache()
    {
        try {
            $redis = Cache::store('redis')->handler();
            $iterator = null;
            $pattern = self::INVITE_CASH_CONFIG_KEY . '*';

            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if ($keys !== false && !empty($keys)) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);

            return json([
                'code' => 200,
                'msg'  => '缓存清除成功'
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg'  => '缓存清除失败：' . $e->getMessage()
            ]);
        }
    }
}