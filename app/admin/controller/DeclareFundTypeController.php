<?php

namespace app\admin\controller;

use app\model\subsidy_butie\DeclareFundType;
use think\facade\Cache;

class DeclareFundTypeController extends AuthController
{
    // 缓存键名
    const CACHE_KEY = 'declare_fund_type_list';

    /**
     * 资金类型列表
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
                $data = DeclareFundType::getList($req);
                $redis->setex($cacheKey, 600, serialize($data));
            }

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();

        } catch (\Exception $e) {
            $data = DeclareFundType::getList($req);

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
            $data = DeclareFundType::find($req['id']);
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 添加资金类型
     */
    public function add()
    {
        $req = $this->validate(request(), [
            'name|资金类型名称'    => 'require',
            'description|类型描述' => 'require',
            'sort|排序'            => 'require|integer',
            'status|状态'          => 'require|number',
        ]);

        try {
            $fundType = new DeclareFundType();
            $fundType->save($req);

            $this->clearCache();
            return out();

        } catch (\Exception $e) {
            return out('添加失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 编辑资金类型
     */
    public function edit()
    {
        $req = $this->validate(request(), [
            'id'                   => 'require|number',
            'name|资金类型名称'    => 'require',
            'description|类型描述' => 'require',
            'sort|排序'            => 'require|integer',
            'status|状态'          => 'require|number',
        ]);

        try {
            $fundType = DeclareFundType::find($req['id']);
            if (!$fundType) {
                return out('资金类型不存在', 400);
            }

            $fundType->save($req);
            $this->clearCache();
            return out();

        } catch (\Exception $e) {
            return out('编辑失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 删除资金类型
     */
    public function delete()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        try {
            // 检查是否被使用
//            if (DeclareFundType::checkUsed($req['id'])) {
//                return out('该资金类型已被使用，无法删除', 400);
//            }

            $fundType = DeclareFundType::find($req['id']);
            if (!$fundType) {
                return out('资金类型不存在', 400);
            }

            $fundType->delete();
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
            \think\facade\Log::error('清除资金类型缓存失败: ' . $e->getMessage());
        }
    }
}