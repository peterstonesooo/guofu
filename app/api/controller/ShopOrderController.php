<?php

namespace app\api\controller;

use think\facade\Db;
use app\model\User;

class ShopOrderController extends AuthController
{
/**
 * 商品购买
 * 
 * @api {post} /ShopOrder/order 商品购买
 * 
 * @apiDescription 用户购买商品，支持余额和积分支付
 * 
 * @apiParam {Number} goods_id 商品ID
 * @apiParam {Number} num 购买数量
 * @apiParam {String} name 收货人姓名
 * @apiParam {String} phone 收货人手机号
 * @apiParam {String} address 收货地址
 * @apiParam {String} main_address 详细地址
 * @apiParam {String} sku 商品规格
 * 
 * @apiSuccess {Number} order_id 订单ID
 * 
 * @apiSuccessExample {json} 成功返回示例:
 * {
 *     "code": 200,
 *     "msg": "购买成功",
 *     "data": {
 *         "order_id": 1
 *     }
 * }
 * 
 * @apiError {Number} 10001 购买失败
 * @apiErrorExample {json} 错误返回示例:
 * {
 *     "code": 10001,
 *     "msg": "余额不足",
 *     "data": null
 * }
 */
    public function order()
    {
        $user = $this->user;
        //return out(null, 10001, '维护中，请稍后再试'); 
        $req = $this->validate(request(), [
            'goods_id' => 'require|number',
            //'pay_selected' => 'require|number', //首选 1余额支付 3团队奖励
            'pay_password|支付密码' => 'require',
            'num|数量' => 'require|number',
            'name|收货人' => 'require',
            'phone|手机号' => 'require|mobile',
            'address|详细地址' => 'require',
            'main_address|省市县' => 'require',

        ]);
        $req['sku'] =request()->param('sku');
        $userOrder = Db::table('shop_order')->where('user_id', $user['id'])->where('status','>=',2)->find();
        if($userOrder){
            return out(null, 10001, '每个用户只能购买一个商品');
        }


        Db::startTrans();
            try {
                $goods = Db::table('shop_goods')->lock(true)->where('id', $req['goods_id'])->find();
                if (!$goods) {
                    throw new \Exception('商品不存在');
                }
                if ($goods['num'] <= 0) {
                    throw new \Exception('商品已售罄');
                }
                if ($goods['status'] != 1) {
                    throw new \Exception('商品已下架');
                }
                $allPrice = $goods['price'] * $req['num'];
                $allIntegral = $goods['integral'] * $req['num'];
                $user = User::where('id', $user['id'])->lock(true)->find();
                if ($user['topup_balance']+$user['team_bonus_balance'] < $allPrice) {
                    throw new \Exception('余额不足');
                    
                }
                if ($user['integral'] < $allIntegral) {
                    throw new \Exception('积分不足');

                }
                if($user['pay_password'] == ''){
                    throw new \Exception('请先设置支付密码');
                }
                if ($req['pay_password']== '' || $user['pay_password'] != sha1(md5($req['pay_password']))) {
                    throw new \Exception('支付密码错误');
                }

                $orderData = [
                    'order_num' => 'SO' . build_order_sn($user['id']),
                    'user_id' => $user['id'],
                    'goods_id' => $goods['id'],
                    'goods_title' => $goods['title'],
                    'price' => $goods['price'],
                    'integral' => $goods['integral'],
                    'num' => $req['num'],
                    'all_price' => $allPrice,
                    'all_integral' => $allIntegral,
                    'name' => $req['name'],
                    'phone' => $req['phone'],
                    'address' => $req['address'],
                    'main_address' => $req['main_address'],
                    'sku' => $req['sku'],
                    'status' => 2,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'imgurl' => $goods['imgurl'],
                ];
                $id = Db::table('shop_order')->insertGetId($orderData);
                // 增加销量 sale_num 和减少库存
                Db::table('shop_goods')->where('id', $goods['id'])->inc('sale_num', $orderData['num'])->dec('num', $orderData['num'])->update();
                if($user['topup_balance'] >= $allPrice){
                    User::changeInc($user['id'], -$orderData['all_price'], 'topup_balance', 27, $id, 1, '余额' . '-' . $goods['title'], 0, 1, 'GO');
                }else{
                    $toupBalance = $user['topup_balance'];
                    User::changeInc($user['id'], -$user['topup_balance'], 'topup_balance', 27, $id, 1, '余额' . '-' . $goods['title'], 0, 1, 'GO');
                    User::changeInc($user['id'], $allPrice-$toupBalance, 'team_bonus_balance', 27, $id, 1, '团队奖励' . '-' . $goods['title'], 0, 1, 'GO');
                }
                User::changeInc($user['id'], -$orderData['all_integral'], 'integral', 27, $id, 2, '积分' . '-' . $goods['title'], 0, 1, 'GO');
                Db::commit();
                return out(['order_id' => $id],200,'购买成功');
            } catch (\Exception $e) {
                Db::rollback();
                exit_out(null,10001,$e->getMessage());
            }
        
        //return out(null,10001,'购买失败');

    }

/**
 * 获取订单列表
 * 
 * @api {get} /ShopOrder/orderList 获取订单列表
 * 
 * @apiDescription 获取当前用户的订单列表，支持按状态筛选
 * 
 * @apiParam {Number} status 订单状态( 1未支付  2 已支付  5 待收货 6已取消  7 已完成 8退款中 9已退款)
 * 
 * 
 */
    public function orderList()
    {
        $req = $this->validate(request(), [
            'status' => 'require|number',
        ]);
        $user = $this->user;

        $query = Db::table('shop_order')->where('user_id', $user['id']);
        if($req['status'] >0) {
            $query->where('status', $req['status']);
        }
        $list = $query->order('id', 'DESC')->paginate(['query' => request()->param()])->each(function($item){
            $item['status_text'] = config('map.shop_order_status')[$item['status']];
            $item['img_url'] = Db::table('shop_picture')->where('id', $item['imgurl'])->value('imgurl');
            $item['img_url'] = get_img_api($item['img_url']);
            return $item;
        });

        return out($list);
    }

    public function orderDetail(){
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $user = $this->user;
        $order = Db::table('shop_order')->where('id', $req['id'])->find();
        if (!$order) {
            return out(null, 10001, '订单不存在');
        }
        if ($order['user_id'] != $user['id']) {
            return out(null, 10001, '订单不存在');
        }
        $order['status_text'] = config('map.shop_order_status')[$order['status']];
        $order['img_url'] = Db::table('shop_picture')->where('id', $order['imgurl'])->value('imgurl');
        $order['img_url'] = get_img_api($order['img_url']);

        return out($order);
    }
}
