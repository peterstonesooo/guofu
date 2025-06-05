<?php

namespace app\api\controller;

use app\model\Notice;
use app\model\User;
use think\facade\Db;

class InsuranceController extends AuthController
{

    public function  insurance(){
        $user = $this->user;
        
        $insurance = $this->getBaseInsurance($user['id']);
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $apply = Db::table('mp_insurance_apply')->where('user_id', $user['id'])
            ->where('year', $year)
            ->where('month', $month)
            ->find();
        $is_apply = 0;
        if($apply){
            $is_apply = 1; // 已领取当月保险
        }
        $data = [
            'base_insurance' => $insurance['baseInsurance'], // 基本保险金
            'orders' => $insurance['orders'], // 订单列表
            'is_apply' => $is_apply, // 是否领取当月保险
        ];
        return json(['code' => 200, 'msg' => '', 'data' => $data]);
        
    }

    public function apply(){
        $user = $this->user;
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $apply = Db::table('mp_insurance_apply')->where('user_id', $user['id'])
            ->where('year', $year)
            ->where('month', $month)
            ->find();
        if($apply){

            return json(['code' => 10001, 'msg' => '您已领取过本月保险金，请勿重复领取', 'data' => []]);
        }

/*         if($day!=1){
            return json(['code' => 10001, 'msg' => '未到领取时间，基本保险金每月1日可领取', 'data' => []]);
        } */

        $insurance = $this->getBaseInsurance($user['id']);
        $baseInsurance = $insurance['baseInsurance'];
        if($baseInsurance <= 0){
            return json(['code' => 10001, 'msg' => '您本月没有基本保险金可领取', 'data' => []]);
        }
        Db::startTrans();

        try{
            $data = [
                'user_id' => $user['id'],
                'year' => $year,
                'month' => $month,
                'mmoney' => $baseInsurance,
            ];
            $id = Db::table('mp_insurance_apply')->insertGetId($data);
            User::changeInc($user['id'], $baseInsurance, 'insurance_balance',31,$id,5,'领取基本保险金');
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return json(['code' => 10001, 'msg' => $e->getMessage(), 'data' => []]);
        }
        return json(['code' => 200, 'msg' => '领取成功', 'data' => []]);
    }

    private function getBaseInsurance($userId)
    {
        // 获取用户的基本保险金
        $orders = \app\model\Order::where('user_id', $userId)
            ->where('status', 2)
            ->where('project_group_id', 18)
            ->field('id,project_id,project_name,project_group_id,daily_bonus_ratio,created_at')
            ->order('id', 'desc')
            ->select();
        
        $baseInsurance = 0;
        foreach ($orders as $order) {
            $baseInsurance += $order['daily_bonus_ratio'];
        }        
        return ['baseInsurance' =>$baseInsurance,'orders' => $orders];
    }

}