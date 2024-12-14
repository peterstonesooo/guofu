<?php

namespace app\admin\controller;
use app\admin\controller\AuthController;

use think\facade\Db;
use app\model\User;

class ShopOrderController extends AuthController
{
    public function orderList(){
        $req = request()->param();

        $builder = Db::table('shop_order')->order('id', 'asc');
        if(isset($req['order_num']) && trim($req['order_num'])!=''){
            $builder->where('order_num','like','%'.trim($req['order_num']).'%');
        }
        if(isset($req['status']) && $req['status']!=''){
            $builder->where('status',$req['status']);
        }
        if (isset($req['user']) && $req['user'] !== '') {
            $user_id = User::where('phone', $req['user'])->value('id');
            $user_ids = [];
            if($user_id){
            	$user_ids[0] = $user_id;
            }
            $user_ids[] = $req['user'];

            $builder->whereIn('user_id', $user_ids);
        }

        if (!empty($req['start_date'])) {
            $builder->where('add_time', '>=', $req['start_date']);
        }else{
            $builder->where('add_time', '>=', date('Y-m-d',strtotime('-7 day')));
        }
        if (!empty($req['end_date'])) {
            $builder->where('add_time', '<=', $req['end_date']);
        }

        if(isset($req['goods_title']) && trim($req['goods_title'])!=''){
            $builder->where('goods_title','like','%'.$req['goods_title'].'%');
        }


        $statusConfig = config('map.shop_order_status');
        $data = $builder->paginate(['query' => $req])->each(function($item) use ($statusConfig){
            $user = Db::table('mp_user')->where('id',$item['user_id'])->field('phone')->find();
            $item['phone'] = $user['phone'];
            $item['img_url'] = Db::table('shop_picture')->where('id',$item['imgurl'])->value('imgurl');
            $item['status_text'] = $statusConfig[$item['status']];
            return $item;
        });

        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('statusConfig', $statusConfig);

        return $this->fetch();
    }

    public function showDetail(){
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);

        $order = Db::table('shop_order')->where('id', $req['id'])->find();
        if(!$order) {
            exit_out('订单不存在');
        }
        $user = Db::table('mp_user')->where('id', $order['user_id'])->find();
        $order['phone'] = $user['phone'];
        $order['img_url'] = Db::table('shop_picture')->where('id',$order['imgurl'])->value('imgurl');
        $order['status_text'] = config('map.shop_order_status')[$order['status']];
        $this->assign('order', $order);
        return $this->fetch();
    }

    public function ship(){
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
            'express_name' => 'require',
            'express_num' => 'require'
        ]);
        $order = Db::table('shop_order')->where('id', $req['order_id'])->find();
        if(!$order) {
            exit_out('订单不存在');
        }
        if($order['status'] != 2) {
            exit_out('订单状态不正确');
        }
        $update_data = [
            'status' => 5,
            'send_type' => $req['express_name'],
            'send_num' => $req['express_num'],
            'fa_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('shop_order')->where('id', $req['order_id'])->update($update_data);
        exit_out('发货成功', 1);
    }

    public function changeStatus()
    {
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
            'status' => 'require|number'
        ]);

        $order = Db::table('shop_order')->where('id', $req['order_id'])->find();
        if(!$order) {
            return json(['code' => 0, 'msg' => '订单不存在']);
        }

        $update = [
            'status' => $req['status'],
            'update_time' => date('Y-m-d H:i:s')
        ];

        try {
            Db::table('shop_order')->where('id', $req['order_id'])->update($update);
            return json(['code' => 1, 'msg' => '修改成功']);
        } catch(\Exception $e) {
            return json(['code' => 0, 'msg' => '修改失败']);
        }
    }
}
