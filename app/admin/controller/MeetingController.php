<?php

namespace app\admin\controller;

use app\model\meeting\Meeting;
use think\facade\Cache;
use think\facade\Request;
use think\facade\View;

class MeetingController extends AuthController
{
    // 共用Redis缓存键名
    const MEETING_LIST_KEY = 'meeting_list';

    public function meetingList()
    {
        $req = Request::param();

        try {
            // 生成缓存键，包含查询参数
            $cacheKey = self::MEETING_LIST_KEY . ':admin:' . md5(serialize($req));

            // 使用Redis handler获取缓存
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                $data = unserialize($cachedData);
            } else {
                $query = Meeting::order('sort', 'desc')->order('id', 'desc');

                if (isset($req['title']) && trim($req['title']) != '') {
                    $query->where('title', 'like', '%' . trim($req['title']) . '%');
                }

                $data = $query->paginate(['query' => $req])->each(function ($item) {
                    $item->cover_url = $item->cover_url;
                    return $item;
                });

                // 将数据存入缓存
                $redis->setex($cacheKey, 600, serialize($data));
            }

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();

        } catch (\Exception $e) {
            // 异常时仍返回数据，但不使用缓存
            $query = Meeting::order('sort', 'desc')->order('id', 'desc');

            if (isset($req['title']) && trim($req['title']) != '') {
                $query->where('title', 'like', '%' . trim($req['title']) . '%');
            }

            $data = $query->paginate(['query' => $req])->each(function ($item) {
                $item->cover_url = $item->cover_url;
                return $item;
            });

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();
        }
    }

    public function showMeeting()
    {
        $req = Request::param();
        $data = [];

        if (!empty($req['id'])) {
            $data = Meeting::find($req['id']);
            if ($data) {
                $data->cover_url = $data->cover_url;
            }
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 清除所有会议列表缓存
     */
    private function clearAllMeetingListCache()
    {
        try {
            $redis = Cache::store('redis')->handler();
            $iterator = null;
            $pattern = self::MEETING_LIST_KEY . '*';

            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if ($keys !== false && !empty($keys)) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);

        } catch (\Exception $e) {
            \think\facade\Log::error('清除会议缓存失败: ' . $e->getMessage());
        }
    }

    public function addMeeting()
    {
        $req = $this->validate(Request::param(), [
            'title|会议标题' => 'require',
            'password|会议密码' => 'require',
            'meeting_url|会议链接' => 'require|url',
            'cover_img|封面图片' => 'require',
            'content|会议内容' => 'require',
            'sort|排序' => 'require|integer',
            'status|状态' => 'require|in:0,1',
        ]);

        $req['created_at'] = date('Y-m-d H:i:s');
        $req['updated_at'] = date('Y-m-d H:i:s');

        Meeting::create($req);

        // 添加成功后清除所有缓存
        $this->clearAllMeetingListCache();

        return out();
    }

    public function editMeeting()
    {
        $req = $this->validate(Request::param(), [
            'id' => 'require|number',
            'title|会议标题' => 'require',
            'password|会议密码' => 'require',
            'cover_img|封面图片' => 'require',
            'meeting_url|会议链接' => 'require|url',
            'content|会议内容' => 'require',
            'sort|排序' => 'require|integer',
            'status|状态' => 'require|in:0,1',
        ]);

        $req['updated_at'] = date('Y-m-d H:i:s');

        $meeting = Meeting::find($req['id']);
        if (!$meeting) {
            return out(null, 404, '会议不存在');
        }

        $meeting->save($req);

        // 编辑成功后清除所有缓存
        $this->clearAllMeetingListCache();

        return out();
    }

    public function deleteMeeting()
    {
        $req = $this->validate(Request::param(), [
            'id' => 'require|number'
        ]);

        $meeting = Meeting::find($req['id']);
        if (!$meeting) {
            return out(null, 404, '会议不存在');
        }

        $meeting->delete();

        // 删除成功后清除所有缓存
        $this->clearAllMeetingListCache();

        return out();
    }
}