<?php

namespace app\admin\controller;

use think\facade\Db;

class ShopGoodsController extends AuthController
{
    public function goodsList()
    {
        $req = request()->param();

        $builder = Db::table('shop_goods')->order('id', 'desc');
        if (isset($req['cate_id']) && $req['cate_id'] != '') {
            $builder->where('cate_id', $req['cate_id']);
        }
        if (isset($req['title']) && trim($req['title']) != '') {
            $builder->where('title', 'like', '%' . trim($req['title']) . '%');
        }

        $data = $builder->paginate(['query' => $req])->each(function ($item) {
            $item['cate_name'] = Db::table('shop_cate')->where('id', $item['cate_id'])->value('title');
            $item['img_url'] = Db::table('shop_picture')->where('id', $item['imgurl'])->value('imgurl');
            return $item;
        });


        $goodsCate = Db::table('shop_cate')->field('id,title')->order('sort desc')->select();

        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('goodsCate', $goodsCate);

        return $this->fetch();
    }

    public function showGoods()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = Db::table('shop_goods')->where('id', $req['id'])->find();
            $data['cate_name'] = Db::table('shop_cate')->where('id', $data['cate_id'])->value('title');
            $data['img_url'] = Db::table('shop_picture')->where('id', $data['imgurl'])->value('imgurl');
            $data['imgs_list'] = Db::table('shop_picture')->where('id', 'in', $data['imgs'])->field('id,imgurl')->select();

        }
        $cate = Db::table('shop_cate')->field('id,title')->order('sort desc')->select();
        $this->assign('data', $data);
        $this->assign('cate', $cate);
        return $this->fetch();
    }

    public function addGoods()
    {
        $req = $this->validate(request(), [
            'title|商品名称'  => 'require',
            'cate_id|分类'    => 'require|number',
            'imgurl|图片'     => 'require',
            'price|价格'      => 'require|float',
            //'stock|库存' => 'require|number',
            'sort|排序'       => 'require|integer',
            'imgs|轮播图'     => 'array',
            'old_price|原价'  => 'float',
            'infos|商品详情'  => 'require',
            'integral|积分'   => 'number',
            'num|库存'        => 'number',
            'sale_num|销量'   => 'number',
            'limitnum|限购'   => 'number',
            'sku|规格'        => 'require',
            'is_tuijian|推荐' => 'number',
            'status|状态'     => 'number',

        ]);
        if (is_array($req['imgs'])) {
            $req['imgs'] = implode(',', $req['imgs']);
        }
        Db::table('shop_goods')->insert($req);

        return out();
    }

    public function editGoods()
    {
        $req = $this->validate(request(), [
            'id'              => 'require|number',
            'title|商品名称'  => 'require',
            'cate_id|分类'    => 'require|number',
            'imgurl|图片'     => 'require',
            'price|价格'      => 'require|float',
            //'stock|库存' => 'require|number',
            'sort|排序'       => 'require|integer',
            'imgs|轮播图'     => 'array',
            'old_price|原价'  => 'float',
            'infos|商品详情'  => 'require',
            'integral|积分'   => 'number',
            'num|库存'        => 'number',
            'sale_num|销量'   => 'number',
            'limitnum|限购'   => 'number',
            'sku|规格'        => 'require',
            'is_tuijian|推荐' => 'number',
            'status|状态'     => 'number',
        ]);
        if (is_array($req['imgs'])) {
            $req['imgs'] = implode(',', $req['imgs']);
        }
        Db::table('shop_goods')->where('id', $req['id'])->update($req);

        return out();
    }


}
