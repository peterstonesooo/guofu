<?php

namespace app\admin\controller;

use app\model\Capital;
use app\model\Order;
use app\model\User;

class HomeController extends AuthController
{
    public function index()
    {
        if(!session('is_admin')){
            $this->assign('data', []);
            return $this->fetch();
        }
        $data = $arr = [];

        $arr['title'] = '注册会员数';
        $arr['value'] = User::count();
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '激活会员数';
        $arr['value'] = User::where('is_active', 1)->count();
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '投资总金额';
        $arr['value'] = round(Order::where('status', '>', 1)->sum('single_amount*buy_num'), 2);
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '充值总金额';
        $arr['value'] = round(Capital::where('status', 2)->where('type', 1)->sum('amount'), 2);
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '提现总金额';
        $arr['value'] = round(0 - Capital::where('status', 2)->where('type', 2)->where('log_type',0)->sum('amount'), 2);
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '充值总次数';
        $arr['value'] = Capital::where('status', 2)->where('type', 1)->where('log_type',0)->count();
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '提现总次数';
        $arr['value'] = Capital::where('status', 2)->where('type', 2)->where('log_type',0)->count();
        $arr['url'] = '';
        $data[] = $arr;

        $this->assign('data', $data);

        return $this->fetch();
    }

    public function uploadSummernoteImg()
    {
        $img_url = upload_file2('img_url',true,false);

        return out(['img_url' => env('app.img_host').$img_url, 'filename' => md5(time()).'.jpg']);
    }
}
