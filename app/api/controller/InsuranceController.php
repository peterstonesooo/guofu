<?php

namespace app\api\controller;

use app\model\Notice;
use app\model\User;
use think\facade\Db;

class InsuranceController extends AuthController
{

    public function  insurance(){
        $user = $this->user;
        
        $orders = \app\model\Order::where('user_id', $this->user['id'])
            ->where('status', 2)
            ->where('project_group_id', 18)
            ->field('id,project_id,project_group_id,daily_bonus_ratio,created_at')
            ->order('id', 'desc')
            ->select();
        
        $baseInsurance = 0;
        foreach ($orders as $order) {
            $baseInsurance += $order['daily_bonus_ratio'];
        }

        $data = [
            'base_insurance' => $baseInsurance,
            'orders' => $orders,
            'is_apply' => 0, // 是否领取当月保险
        ];
        return json(['code' => 200, 'msg' => '', 'data' => $data]);
        
    }

    public function apply(){
        return json(['code' => 200, 'msg' => '未到领取时间，基本保险金每月1日可领取', 'data' => []]);
    }

}