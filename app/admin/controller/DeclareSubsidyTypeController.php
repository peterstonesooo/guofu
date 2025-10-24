<?php

namespace app\admin\controller;


use app\model\subsidy_butie\DeclareSubsidyType;
use think\facade\Cache;

class DeclareSubsidyTypeController extends AuthController
{
    // 缓存键名
    const CACHE_KEY = 'declare_subsidy_type_list';

    /**
     * 补贴类型列表
     */
    public function index()
    {
        $req = request()->param();

        try {
            $cacheKey = self::CACHE_KEY . ':admin:' . md5(serialize($req));
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                $data = unserialize($cachedData);
            } else {
                $data = DeclareSubsidyType::getList($req)->each(function ($item) {
                    $item->img_url = $item->img_url;
                    return $item;
                });
                $redis->setex($cacheKey, 600, serialize($data));
            }

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();

        } catch (\Exception $e) {
            $data = DeclareSubsidyType::getList($req)->each(function ($item) {
                $item->img_url = $item->img_url;
                return $item;
            });

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();
        }
    }

    /**
     * 显示添加/编辑页面
     */
    public function show()
    {
        $req = request()->param();
        $data = [];

        if (!empty($req['id'])) {
            $data = DeclareSubsidyType::find($req['id']);
            if ($data) {
                $data->img_url = $data->img_url;
            }
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 添加补贴类型
     */
    public function add()
    {
        $req = $this->validate(request(), [
            'name|类型名称'        => 'require',
            'code|类型编码'        => 'require|alphaDash|unique:declare_subsidy_type',
            'imgurl|图片'          => 'require',
            'description|类型描述' => 'require',
            'sort|排序'            => 'require|integer',
            'status|状态'          => 'require|number',
        ]);

        try {
            $type = new DeclareSubsidyType();
            $type->save($req);

            $this->clearCache();
            return out();

        } catch (\Exception $e) {
            return out('添加失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 编辑补贴类型
     */
    public function edit()
    {
        $req = $this->validate(request(), [
            'id'                   => 'require|number',
            'name|类型名称'        => 'require',
            'code|类型编码'        => 'require|alphaDash|unique:declare_subsidy_type,code,' . request()->param('id'),
            'imgurl|图片'          => 'require',
            'description|类型描述' => 'require',
            'sort|排序'            => 'require|integer',
            'status|状态'          => 'require|number',
        ]);

        try {
            $type = DeclareSubsidyType::find($req['id']);
            if (!$type) {
                return out('类型不存在', 400);
            }

            $type->save($req);
            $this->clearCache();
            return out();

        } catch (\Exception $e) {
            return out('编辑失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 删除补贴类型
     */
    public function delete()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        try {
//            // 检查是否被使用
//            if (DeclareSubsidyType::checkUsed($req['id'])) {
//                return out('该类型下存在补贴配置，无法删除', 400);
//            }

            $type = DeclareSubsidyType::find($req['id']);
            if (!$type) {
                return out('类型不存在', 400);
            }

            $type->delete();
            $this->clearCache();
            return out();

        } catch (\Exception $e) {
            return out('删除失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 清除缓存
     */
    private function clearCache()
    {
        try {
            $redis = Cache::store('redis')->handler();
            $pattern = self::CACHE_KEY . '*';

            $iterator = null;
            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if ($keys !== false && !empty($keys)) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);

        } catch (\Exception $e) {
            \think\facade\Log::error('清除补贴类型缓存失败: ' . $e->getMessage());
        }
    }
}