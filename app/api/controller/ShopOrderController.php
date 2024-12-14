<?php

namespace app\api\controller;

use think\facade\Db;
use app\model\User;

class ShopOrderController extends AuthController
{

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
        $req['sku'] = isset($req['sku']) ? $req['sku'] : '';



        Db::transaction(function () use ($user, $req) {
            try {
                $goods = Db::table('shop_goods')->lock(true)->where('id', $req['goods_id'])->find();
                if (!$goods) {
                    exit_out2(null,10001,'商品不存在');
                }
                if ($goods['num'] <= 0) {
                    exit_out2(null,10001,'商品已售罄');
                }
                if ($goods['status'] != 1) {
                    exit_out2(null,10001,'商品已下架');
                }
                $allPrice = $goods['price'] * $req['num'];
                $allIntegral = $goods['integral'] * $req['num'];
                $user = User::where('id', $user['id'])->lock(true)->find();
                if ($user['topup_balance'] <= $allPrice) {
                    exit_out2(null,10001,'余额不足');
                    
                }
                if ($user['integral'] < $allIntegral) {
                    exit_out2(null,10001,'积分不足');

                }
                if($user['pay_password'] == ''){
                    exit_out2(null,10001,'请先设置支付密码');
                }
                if ($req['pay_password']== '' || $user['pay_password'] != sha1(md5($req['pay_password']))) {
                    exit_out2(null,10001,'支付密码错误');
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
                User::changeInc($user['id'], -$orderData['all_price'], 'topup_balance', 27, $id, 1, '余额' . '-' . $goods['title'], 0, 1, 'GO');
                User::changeInc($user['id'], -$orderData['all_integral'], 'integral', 27, $id, 1, '积分' . '-' . $goods['title'], 0, 1, 'GO');
                return out(['order_id' => $id],200,'购买成功');
                exit;
            } catch (\Exception $e) {
                exit_out(null,10001,$e->getMessage());
            }
        });
        //return out(null,10001,'购买失败');

    }

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
        $list = $query->order('id', 'DESC')->paginate(['query' => request()->param()]);
        foreach ($list as &$v) {
            $v['status_text'] = config('map.shop_order_status')[$v['status']];
            $v['img_url'] = Db::table('shop_picture')->where('id', $v['imgurl'])->value('imgurl');
        }
        return out($list);
    }
}
