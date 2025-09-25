<?php

namespace app\admin\controller;

use app\model\StockAnnouncements;
use think\facade\Cache;

class StockAnnouncementsController extends AuthController
{
    // Redis缓存键名
    const REDIS_KEY = 'stock_announcements';

    // 清除缓存方法
    private function clearCache()
    {
        Cache::delete(self::REDIS_KEY);
    }

    // 获取缓存数据
    private function getCachedData()
    {
        return Cache::remember(self::REDIS_KEY, function () {
            $data = StockAnnouncements::order('created_at desc')->select()->toArray();
            return serialize($data);
        });
    }

    // 股权公告列表
    public function announcementList()
    {
        $req = request()->param();

        // 尝试从缓存获取数据
        $cachedData = $this->getCachedData();
        $allData = unserialize($cachedData);

        // 如果没有缓存数据，从数据库获取
        if (empty($allData)) {
            $builder = StockAnnouncements::order('created_at desc');

            if (isset($req['announcement_id']) && $req['announcement_id'] !== '') {
                $builder->where('id', $req['announcement_id']);
            }

            if (isset($req['title']) && $req['title'] !== '') {
                $builder->where('title', 'like', '%' . $req['title'] . '%');
            }

            if (isset($req['status']) && $req['status'] !== '') {
                $builder->where('status', $req['status']);
            }

            $data = $builder->paginate(['query' => $req]);
        } else {
            // 处理缓存数据的分页和筛选
            $filteredData = $allData;

            // 应用筛选条件
            if (isset($req['announcement_id']) && $req['announcement_id'] !== '') {
                $filteredData = array_filter($filteredData, function ($item) use ($req) {
                    return $item['id'] == $req['announcement_id'];
                });
            }

            if (isset($req['title']) && $req['title'] !== '') {
                $filteredData = array_filter($filteredData, function ($item) use ($req) {
                    return strpos($item['title'], $req['title']) !== false;
                });
            }

            if (isset($req['status']) && $req['status'] !== '') {
                $filteredData = array_filter($filteredData, function ($item) use ($req) {
                    return $item['status'] == $req['status'];
                });
            }

            // 手动分页
            $page = isset($req['page']) ? $req['page'] : 1;
            $pageSize = 10; // 默认每页10条
            $total = count($filteredData);
            $items = array_slice($filteredData, ($page - 1) * $pageSize, $pageSize);

            // 创建分页对象
            $data = new \think\paginator\driver\Bootstrap($items, $pageSize, $page, $total, false, [
                'query' => $req
            ]);
        }

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    // 显示添加/编辑页面
    public function showAnnouncement()
    {
        $req = request()->param();
        $data = [];

        if (!empty($req['id'])) {
            // 尝试从缓存获取
            $cachedData = $this->getCachedData();
            $allData = unserialize($cachedData);

            if ($allData) {
                foreach ($allData as $item) {
                    if ($item['id'] == $req['id']) {
                        $data = $item;
                        break;
                    }
                }
            }

            // 如果缓存中没有，从数据库获取
            if (empty($data)) {
                $data = StockAnnouncements::where('id', $req['id'])->find();
                if ($data) {
                    $data = $data->toArray();
                }
            }
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    // 添加股权公告
    public function addAnnouncement()
    {
        $req = $this->validate(request(), [
            'title|公告标题'      => 'require|max:200',
            'content|公告内容'    => 'require',
            'start_time|开始时间' => 'require|date',
            'end_time|结束时间'   => 'require|date',
        ]);

        // 获取当前管理员信息
        $adminUser = $this->adminUser;
        $req['created_by'] = $adminUser['username'];
        $req['updated_by'] = $adminUser['username'];
        $req['status'] = request()->param('status', 1);

        StockAnnouncements::create($req);

        // 清除缓存
        $this->clearCache();

        return out(null, 200, '添加成功');
    }

    // 编辑股权公告
    public function editAnnouncement()
    {
        $req = $this->validate(request(), [
            'id'                  => 'require|number',
            'title|公告标题'      => 'require|max:200',
            'content|公告内容'    => 'require',
            'start_time|开始时间' => 'require|date',
            'end_time|结束时间'   => 'require|date',
        ]);

        // 获取当前管理员信息
        $adminUser = $this->adminUser;
        $req['updated_by'] = $adminUser['username'];
        $req['status'] = request()->param('status', 1);

        StockAnnouncements::where('id', $req['id'])->update($req);

        // 清除缓存
        $this->clearCache();

        return out(null, 200, '编辑成功');
    }

    // 更改状态
    public function changeAnnouncement()
    {
        $req = $this->validate(request(), [
            'id'    => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        StockAnnouncements::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        // 清除缓存
        $this->clearCache();

        return out();
    }

    // 删除股权公告
    public function delAnnouncement()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        StockAnnouncements::where('id', $req['id'])->delete();

        // 清除缓存
        $this->clearCache();

        return out(null, 200, '删除成功');
    }
}