<?php

namespace app\api\controller;

use think\facade\Db;
use app\model\User;
use think\facade\Env;

class ShopGoodsController extends AuthController
{
    public function goodsList(){
        $req = $this->validate(request(), [
            'cate_id' => 'number',
            'is_tuijian' => 'number',
        ]);
        $query = Db::table('shop_goods')->where('staus',1);
        if($req['cate_id'] != 0) {
            $query->where('cate_id',$req['cate_id']);
        }
        if($req['is_tuijian'] != 0) {
            $query->where('is_tuijian',$req['is_tuijian']);
        }

        $list = $query->field('id,title,price,integral,sku,imgurl,is_tuijian')->paginate(['query' => request()->param()])->each(function($item){
            $item['img_url'] = Db::table('shop_picture')->where('id',$item['imgurl'])->value('imgurl');
            $item['img_url'] = get_img_api($item['img_url']);
            return $item;
        });


        return out($list);
    }

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
        return out($detail);
    }

}