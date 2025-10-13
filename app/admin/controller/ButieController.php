<?php

namespace app\admin\controller;

use think\facade\Cache;
use think\facade\Db;
use think\facade\Request;

class ButieController extends AuthController
{
    // 共用Redis缓存键名（与API模块相同）
    const BUTIE_LIST_KEY = 'stock_butie_list';

    public function butieList()
    {
        $req = request()->param();

        try {
            // 生成缓存键，包含查询参数
            $cacheKey = self::BUTIE_LIST_KEY . ':admin:' . md5(serialize($req));

            // 使用Redis handler获取缓存
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                // 如果缓存存在，直接使用缓存数据
                $data = unserialize($cachedData);
            } else {
                // 缓存不存在，从数据库查询
                $builder = Db::name('stock_butie')->order('sort', 'desc')->order('id', 'desc');

                if (isset($req['title']) && trim($req['title']) != '') {
                    $builder->where('title', 'like', '%' . trim($req['title']) . '%');
                }

                $data = $builder->paginate(['query' => $req])->each(function ($item) {
                    $item['img_url'] = env('app.img_host') . '/storage/' . $item['imgurl'];
                    return $item;
                });

                // 将数据存入缓存
                $redis->setex($cacheKey, 600, serialize($data)); // 10分钟缓存
            }

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();

        } catch (\Exception $e) {
            // 异常时仍返回数据，但不使用缓存
            $builder = Db::name('stock_butie')->order('sort', 'desc')->order('id', 'desc');

            if (isset($req['title']) && trim($req['title']) != '') {
                $builder->where('title', 'like', '%' . trim($req['title']) . '%');
            }

            $data = $builder->paginate(['query' => $req])->each(function ($item) {
                $item['img_url'] = env('app.img_host') . '/storage/' . $item['imgurl'];
                return $item;
            });

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();
        }
    }

    public function showButie()
    {
        $req = request()->param();
        $data = [];

        if (!empty($req['id'])) {
            $data = Db::name('stock_butie')->where('id', $req['id'])->find();
            if ($data) {
                $data['img_url'] = env('app.img_host') . '/storage/' . $data['imgurl'];
            }
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 清除所有补贴列表缓存（API和Admin共用）
     */
    private function clearAllButieListCache()
    {
        try {
            $redis = Cache::store('redis')->handler();
            // 使用SCAN迭代器安全删除，避免阻塞
            $iterator = null;
            $pattern = self::BUTIE_LIST_KEY . '*';

            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if ($keys !== false && !empty($keys)) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);

        } catch (\Exception $e) {
            // 记录日志，但不影响主流程
            \think\facade\Log::error('清除补贴缓存失败: ' . $e->getMessage());
        }
    }

    public function addButie()
    {
        $req = $this->validate(request(), [
            'title|补贴名称' => 'require',
            'imgurl|图片'    => 'require',
            'price|价格'     => 'require|float',
            'infos|补贴详情' => 'require',
            'sort|排序'      => 'require|integer',
            'status|状态'    => 'require|number',
        ]);

        $req['created_at'] = date('Y-m-d H:i:s');
        $req['updated_at'] = date('Y-m-d H:i:s');

        Db::name('stock_butie')->insert($req);

        // 添加成功后清除所有缓存（包括API和Admin）
        $this->clearAllButieListCache();

        return out();
    }

    public function editButie()
    {
        $req = $this->validate(request(), [
            'id'             => 'require|number',
            'title|补贴名称' => 'require',
            'imgurl|图片'    => 'require',
            'price|价格'     => 'require|float',
            'infos|补贴详情' => 'require',
            'sort|排序'      => 'require|integer',
            'status|状态'    => 'require|number',
        ]);

        $req['updated_at'] = date('Y-m-d H:i:s');

        Db::name('stock_butie')->where('id', $req['id'])->update($req);

        // 编辑成功后清除所有缓存（包括API和Admin）
        $this->clearAllButieListCache();

        return out();
    }

    public function deleteButie()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        Db::name('stock_butie')->where('id', $req['id'])->delete();

        // 删除成功后清除所有缓存（包括API和Admin）
        $this->clearAllButieListCache();

        return out();
    }
}