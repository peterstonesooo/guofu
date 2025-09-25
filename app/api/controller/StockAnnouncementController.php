<?php

namespace app\api\controller;

use app\model\StockAnnouncements;
use think\facade\Cache;

class StockAnnouncementController extends AuthController
{
    // Redis缓存键名
    const REDIS_KEY = 'stock_announcements';

    /**
     * 获取有效的股权公告列表
     */
    public function getAnnouncements()
    {
        try {
            // 尝试从缓存获取数据
            $cachedData = Cache::get(self::REDIS_KEY);
            $currentTime = date('Y-m-d H:i:s');

            if ($cachedData) {
                // 如果缓存存在，反序列化数据并筛选有效公告
                $allAnnouncements = unserialize($cachedData);

                // 筛选状态为1且在有效期内的公告
                $announcements = array_filter($allAnnouncements, function ($item) use ($currentTime) {
                    return $item['status'] == 1 &&
                        $item['start_time'] <= $currentTime &&
                        $item['end_time'] >= $currentTime;
                });

                // 按创建时间倒序排序
                usort($announcements, function ($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
            } else {
                // 如果缓存不存在
                $announcements = [];
            }

            return out(['announcements' => $announcements]);
        } catch (\Exception $e) {
            return out(null, 500, '获取公告失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取单个股权公告详情
     */
    public function getAnnouncementDetail()
    {
        try {
            $req = $this->validate(request(), [
                'id' => 'require|number'
            ]);

            $announcement = StockAnnouncements::where('id', $req['id'])
                ->where('status', 1)
                ->field('id, title, content, start_time, end_time, created_at')
                ->find();

            if (!$announcement) {
                return out(null, 10001, '公告不存在或已禁用');
            }

            return out(['announcement' => $announcement]);
        } catch (\Exception $e) {
            return out(null, 500, '获取公告详情失败: ' . $e->getMessage());
        }
    }
}