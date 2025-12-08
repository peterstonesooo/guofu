<?php

namespace app\api\controller;

use app\api\service\ButieService;
use app\model\butie\StockButie;
use app\model\butie\StockButieRecords;
use think\facade\Cache;

class ButieController extends AuthController
{
    // 共用Redis缓存键名
    const BUTIE_LIST_KEY = 'stock_butie_list';
    // 缓存时间（10分钟）
    const CACHE_TIME = 600;
    // 领取补贴锁前缀
    const RECEIVE_LOCK_KEY = 'butie_receive_lock:';

    /**
     * 获取补贴信息列表
     * @param integer page 页码 (可选)
     * @param integer limit 每页条数 (可选)
     */
    public function butieList()
    {
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);
        $user_id = $this->user['id'];

        try {
            // 生成缓存键，包含分页参数
            $cacheKey = self::BUTIE_LIST_KEY . ":{$page}:{$limit}";

            // 使用Redis handler获取缓存
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                // 如果缓存存在，直接返回缓存数据
                $result = unserialize($cachedData);
            } else {
                // 使用 Model 查询
                $query = StockButie::where('status', StockButie::STATUS_ENABLED)
                    ->order('sort', 'desc')
                    ->order('id', 'desc');

                // 获取总数
                $total = $query->count();

                // 获取分页数据
                $list = $query->page($page, $limit)
                    ->select()
                    ->each(function ($item) {
                        // 添加完整图片URL
                        $item['img_url'] = env('app.img_host') . '/' . $item['imgurl'];
                        return $item;
                    });

                // 处理空数据情况
                if ($list->isEmpty()) {
                    $result = [
                        'list'         => [],
                        'total'        => 0,
                        'current_page' => $page,
                        'total_page'   => 0
                    ];
                } else {
                    $result = [
                        'list'         => $list->toArray(),
                        'total'        => $total,
                        'current_page' => $page,
                        'total_page'   => ceil($total / $limit)
                    ];
                }

                // 将数据存入缓存
                if (!empty($result)) {
                    $redis->setex($cacheKey, self::CACHE_TIME, serialize($result));
                }
            }

            // 获取用户今天已领取的补贴ID列表
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');
            $receivedButieIds = StockButieRecords::where('user_id', $user_id)
                ->where('status', StockButieRecords::STATUS_SUCCESS)
                ->where('created_at', '>=', $todayStart)
                ->where('created_at', '<=', $todayEnd)
                ->column('butie_id');

            // 为每个补贴项添加领取状态
            if (!empty($result['list'])) {
                foreach ($result['list'] as &$item) {
                    $item['is_received'] = in_array($item['id'], $receivedButieIds) ? 1 : 0;
                }
                unset($item);
            }

            return out($result);

        } catch (\Exception $e) {
            // 记录详细错误日志
            trace('获取补贴列表失败: ' . $e->getMessage(), 'error');
            return out(null, 10001, '获取补贴列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 领取补贴（带并发控制）
     * @param integer butie_id 补贴ID
     * @param string pay_password 支付密码
     */
    public function receiveButie()
    {
        $user = $this->user;
        $butie_id = $this->request->param('butie_id/d', 0);

        // 参数验证
        if ($butie_id <= 0) {
            return out(null, 10001, '参数错误');
        }

        // 生成用户领取锁键名
        $lockKey = self::RECEIVE_LOCK_KEY . $user['id'] . ':' . $butie_id;
        $redis = Cache::store('redis')->handler();

        // 尝试获取分布式锁（有效期5秒）
        $lockAcquired = false;
        try {
            // 使用SET命令原子性设置锁（NX表示不存在才设置，EX表示过期时间）
            $lockAcquired = $redis->set($lockKey, 1, ['nx', 'ex' => 5]);

            if (!$lockAcquired) {
                return out(null, 10005, '操作过于频繁，请稍后再试');
            }

            $result = ButieService::receiveButie($user['id'], $butie_id);
            if ($result) {
                return out(null, 200, '领取成功');
            }
            return out(null, 10002, '领取失败');
        } catch (\Exception $e) {
            return out(null, 10003, $e->getMessage());
        } finally {
            // 释放锁
            if ($lockAcquired) {
                $redis->del($lockKey);
            }
        }
    }

    /**
     * 获取领取补贴记录
     * @param integer page 页码 (可选)
     * @param integer limit 每页条数 (可选)
     */
    public function receiveRecordList()
    {
        $user_id = $this->user['id'];
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        try {
            // 使用Model查询领取记录
            $query = StockButieRecords::with(['butie'])
                ->where('user_id', $user_id)
                ->order('id', 'desc');

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->page($page, $limit)
                ->select()
                ->each(function ($item) {
                    // 添加完整图片URL
                    if ($item->butie && !empty($item->butie->imgurl)) {
                        $item['img_url'] = env('app.img_host') . '/' . $item->butie->imgurl;
                        $item['butie_name'] = $item->butie->title;
                    }

                    // 使用模型获取器获取类型文本
                    $item['type_text'] = $item->type_text;

                    return $item;
                });

            return out([
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ]);

        } catch (\Exception $e) {
            return out(null, 10004, '获取领取记录失败: ' . $e->getMessage());
        }
    }
}