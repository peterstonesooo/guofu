<?php

namespace app\admin\controller;

use think\facade\Cache;
use think\facade\Db;

class StockProductController extends AuthController
{
    // 共用Redis缓存键名（与API模块相同）
    const PRODUCT_LIST_KEY = 'stock_product_list';

    public function productList()
    {
        $req = request()->param();

        try {
            // 生成缓存键，包含查询参数
            $cacheKey = self::PRODUCT_LIST_KEY . ':admin:' . md5(serialize($req));

            // 尝试从缓存获取数据
            $cachedData = Cache::get($cacheKey);

            if ($cachedData) {
                // 如果缓存存在，直接使用缓存数据
                $data = unserialize($cachedData);
            } else {
                // 缓存不存在，从数据库查询
                $builder = Db::table('mp_stock_product')->order('sort', 'desc')->order('id', 'desc');

                if (isset($req['title']) && trim($req['title']) != '') {
                    $builder->where('title', 'like', '%' . trim($req['title']) . '%');
                }

                $data = $builder->paginate(['query' => $req])->each(function ($item) {
                    $item['img_url'] = env('app.img_host') . '/storage/' . $item['imgurl'];
                    return $item;
                });

                // 将数据存入缓存
                Cache::set($cacheKey, serialize($data), 600); // 10分钟缓存
            }

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();

        } catch (\Exception $e) {
            // 异常时仍返回数据，但不使用缓存
            $builder = Db::table('mp_stock_product')->order('sort', 'desc')->order('id', 'desc');

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

    public function showProduct()
    {
        $req = request()->param();
        $data = [];

        if (!empty($req['id'])) {
            $data = Db::table('mp_stock_product')->where('id', $req['id'])->find();
            if ($data) {
                $data['img_url'] = env('app.img_host') . '/storage/' . $data['imgurl'];
            }
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 清除所有产品列表缓存（API和Admin共用）
     */
    private function clearAllProductListCache()
    {
        // 获取缓存实例
        $cache = Cache::instance();

        // 使用通配符删除所有相关的产品列表缓存
        $keys = $cache->keys(self::PRODUCT_LIST_KEY . '*');
        foreach ($keys as $key) {
            $cache->delete($key);
        }
    }

    public function addProduct()
    {
        $req = $this->validate(request(), [
            'title|产品名称' => 'require',
            'imgurl|图片'    => 'require',
            'price|价格'     => 'require|float',
            'infos|产品详情' => 'require',
            'sort|排序'      => 'require|integer',
            'status|状态'    => 'require|number',
        ]);

        $req['created_at'] = date('Y-m-d H:i:s');
        $req['updated_at'] = date('Y-m-d H:i:s');

        Db::table('mp_stock_product')->insert($req);

        // 添加成功后清除所有缓存（包括API和Admin）
        $this->clearAllProductListCache();

        return out();
    }

    public function editProduct()
    {
        $req = $this->validate(request(), [
            'id'             => 'require|number',
            'title|产品名称' => 'require',
            'imgurl|图片'    => 'require',
            'price|价格'     => 'require|float',
            'infos|产品详情' => 'require',
            'sort|排序'      => 'require|integer',
            'status|状态'    => 'require|number',
        ]);

        $req['updated_at'] = date('Y-m-d H:i:s');

        Db::table('mp_stock_product')->where('id', $req['id'])->update($req);

        // 编辑成功后清除所有缓存（包括API和Admin）
        $this->clearAllProductListCache();

        return out();
    }

    public function deleteProduct()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        Db::table('mp_stock_product')->where('id', $req['id'])->delete();

        // 删除成功后清除所有缓存（包括API和Admin）
        $this->clearAllProductListCache();

        return out();
    }
}