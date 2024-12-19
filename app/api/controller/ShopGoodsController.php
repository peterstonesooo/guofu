<?php

namespace app\api\controller;

use think\facade\Db;
use app\model\User;
use think\facade\Env;

class ShopGoodsController extends AuthController
{
    /**
     * 获取商品列表
     * 
     * @api {get} /ShopGoods/goodsList 获取商品列表
     * 
     * @apiDescription 获取商店商品列表，支持分类筛选和推荐商品筛选
     * 
     * @apiParam {Number} [cate_id=0] 分类ID，0表示全部分类
     * @apiParam {Number} [is_tuijian=0] 是否推荐，0表示全部，1表示推荐商品
     * 
     * @apiSuccess {Number} id 商品ID
     * @apiSuccess {String} title 商品标题
     * @apiSuccess {Number} price 商品价格
     * @apiSuccess {Number} integral 商品积分
     * @apiSuccess {String} sku 商品SKU
     * @apiSuccess {String} img_url 商品图片完整URL
     * @apiSuccess {Number} is_tuijian 是否推荐商品
     * 
     * @apiSuccessExample {json} 成功返回示例:
     * {
     *     "code": 1,
     *     "msg": "success",
     *     "data": {
     *         "current_page": 1,
     *         "data": [{
     *             "id": 1,
     *             "title": "商品名称",
     *             "price": 99.00,
     *             "integral": 100,
     *             "sku": "SP001",
     *             "img_url": "http://example.com/image.jpg",
     *             "is_tuijian": 1
     *         }]
     *     }
     * }
     */
    public function goodsList(){
        $req = $this->validate(request(), [
            'cate_id' => 'require|number',
            'is_tuijian' => 'require|number',
        ]);
        $query = Db::table('shop_goods')->where('status',1);
        if($req['cate_id'] != 0) {
            $query->where('cate_id',$req['cate_id']);
        }
        if($req['is_tuijian'] != 0) {
            $query->where('is_tuijian',$req['is_tuijian']);
        }

        $list = $query->field('id,title,price,integral,sku,imgurl,is_tuijian')->order('sort desc,id desc')->paginate(['query' => request()->param()])->each(function($item){
            $item['img_url'] = Db::table('shop_picture')->where('id',$item['imgurl'])->value('imgurl');
            $item['img_url'] = get_img_api($item['img_url']);
            return $item;
        });

        return out($list);
    }

    /**
     * 获取商品详情
     * 
     * @api {get} /ShopGoods/goodsDetail 获取商品详情
     * 
     * @apiDescription 获取单个商品的详细信息
     * 
     * @apiParam {Number} id 商品ID
     * 
     * @apiSuccess {Number} id 商品ID
     * @apiSuccess {String} title 商品标题 
     * @apiSuccess {Number} price 商品价格
     * @apiSuccess {Number} integral 商品积分
     * @apiSuccess {String} sku 商品SKU
     * @apiSuccess {String} img_url 商品图片完整URL
     * @apiSuccess {String} infos 商品详情
     * @apiSuccess {Number} num 库存数量
     * @apiSuccess {Number} sale_num 销量
     * @apiSuccess {Number} is_tuijian 是否推荐商品
     * @apiSuccess {Number} status 商品状态
     * 
     * @apiSuccessExample {json} 成功返回示例:
     * {
     *     "code": 1,
     *     "msg": "success",
     *     "data": {
     *         "id": 1,
     *         "title": "商品名称",
     *         "price": 99.00,
     *         "integral": 100,
     *         "sku": "SP001",
     *         "img_url": "http://example.com/image.jpg",
     *         "infos": "商品详情...",
     *         "num": 100,
     *         "sale_num": 10,
     *         "is_tuijian": 1,
     *         "status": 1
     *     }
     * }
     * 
     * @apiError {Number} 10001 商品不存在
     */
    public function goodsDetail() {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]); 
        $detail = Db::table('shop_goods')->where('id',$req['id'])->find();
        if(!$detail) {
            return out('商品不存在', 10001);
        }
        $detail['img_url'] = Db::table('shop_picture')->where('id',$detail['imgurl'])->value('imgurl');
        $detail['img_url'] = get_img_api($detail['img_url']);
        $imgs = Db::table('shop_picture')->where('id','in',$detail['imgs'])->field('id,imgurl')->column('imgurl');
        foreach($imgs as $k => $v) {
            $imgs[$k] = get_img_api($v);
        }
        $detail['imgs_list'] = $imgs;
        return out($detail);
    }

    public function cateList(){
        $cate = Db::table('shop_cate')->field('id,title')->order('sort desc,id desc')->select();
        return out($cate);
    }

}