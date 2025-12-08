<?php

namespace app\api\controller;

use app\api\service\MeetingService;
use app\model\meeting\Meeting;
use app\model\meeting\MeetingSignRecords;
use think\facade\Cache;

class MeetingController extends AuthController
{
    // 共用Redis缓存键名
    const MEETING_LIST_KEY = 'meeting_list';
    // 缓存时间（10分钟）
    const CACHE_TIME = 600;
    // 签到锁前缀
    const SIGN_LOCK_KEY = 'meeting_sign_lock:';

    /**
     * 获取会议信息列表
     * @param integer page 页码 (可选)
     * @param integer limit 每页条数 (可选)
     */
    public function meetingList()
    {
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        try {
            // 生成缓存键，包含分页参数
            $cacheKey = self::MEETING_LIST_KEY . ":{$page}:{$limit}";

            // 使用Redis handler获取缓存
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                $result = unserialize($cachedData);
                // 即使有缓存，也需要重新检查签到状态，因为签到状态会实时变化
                $user_id = $this->user['id'];
                $is_signed = MeetingService::checkUserTodaySigned($user_id) ? 1 : 0;

                // 更新列表中的签到状态
                foreach ($result['list'] as &$item) {
                    $item['is_signed'] = $is_signed;
                }

                return out($result);
            }

            // 检查用户今天是否已签到（每日签到，与特定会议无关）
            $user_id = $this->user['id'];
            $is_signed = MeetingService::checkUserTodaySigned($user_id) ? 1 : 0;

            // 使用Model查询会议列表
            $query = Meeting::where('status', 1)
                ->order('sort', 'desc')
                ->order('id', 'desc');

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->page($page, $limit)
                ->select()
                ->each(function ($item) use ($is_signed) {
                    // 添加完整图片URL
                    $item['cover_url'] = env('app.img_host') . '/' . $item['cover_img'];

                    // 密码使用支付密码加密方式返回
                    if (!empty($item['password'])) {
                        $item['password'] = $this->passwordEncrypt($item['password']);
                    }

                    // 设置签到状态
                    $item['is_signed'] = $is_signed;

                    return $item;
                });

            $result = [
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ];

            // 注意：不缓存包含签到状态的数据，因为签到状态会实时变化
            // 可以考虑缓存基础数据，然后动态添加签到状态

            return out($result);

        } catch (\Exception $e) {
            return out(null, 10001, '获取会议列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 支付密码加密方式（与StockController一致）
     */
    private function passwordEncrypt($password)
    {
        return sha1(md5($password));
    }

    /**
     * 会议签到（带并发控制）
     */
    public function signMeeting()
    {
        $user = $this->user;

        // 生成用户签到锁键名
        $lockKey = self::SIGN_LOCK_KEY . $user['id'];
        $redis = Cache::store('redis')->handler();

        // 尝试获取分布式锁（有效期5秒）
        $lockAcquired = false;
        try {
            $lockAcquired = $redis->set($lockKey, 1, ['nx', 'ex' => 5]);

            if (!$lockAcquired) {
                return out(null, 10005, '操作过于频繁，请稍后再试');
            }

            $result = MeetingService::signMeeting($user['id']);
            if ($result) {
                return out(null, 200, '签到成功');
            }
            return out(null, 10002, '签到失败');
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
     * 获取会议签到记录
     * @param integer page 页码 (可选)
     * @param integer limit 每页条数 (可选)
     */
    public function signRecordList()
    {
        $user_id = $this->user['id'];
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        try {
            // 使用Model查询签到记录
            $query = MeetingSignRecords::where('user_id', $user_id)
                ->order('id', 'desc');

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->page($page, $limit)
                ->select();

            return out([
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ]);

        } catch (\Exception $e) {
            return out(null, 10004, '获取签到记录失败: ' . $e->getMessage());
        }
    }
}