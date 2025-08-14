<?php

namespace app\api\controller;


use app\model\User;
use app\model\UserDelivery;
use app\model\UserRelation;
use think\facade\Db;
use app\model\TaxOrder;

class TaxesController extends AuthController
{
    public static $statusText = [
        1 => '已缴费',
        2 => '退税申请中',
        3 => '退税成功',
    ];
    public function TaxesList(){
        $user = $this->user;
        $list = TaxOrder::where('user_id',$user['id'])->select();
        $data = [
            'list' => $list,
            'taxes' => 0, 
        ];
        foreach($list as $key=>$item){
            if($item['status'] == 3){
                $data['taxes'] = bcadd($data['taxes'], $item['money'], 2);
                $data['taxes'] = bcadd($data['taxes'], $item['taxes_money'], 2); 
            }
        }
        return out($data);
    }

    public function TaxesRefund(){
        $user = $this->user;
        $req = $this->validate(request(),[
            'id' => 'require|integer',
        ]);
        $taxes = TaxOrder::where('id', $req['id'])
            ->where('user_id', $user['id'])
            ->find();
        if(!$taxes){
            return out(null, 10001,'未找到该税费订单');
        }
        if($taxes['status'] >=2){
            return out(null, 10001,'该税费订单已处理');
        }
        TaxOrder::where('id', $req['id'])->where('user_id', $user['id'])->update(['status' => 2]);
        return out();
            
            
    }

    public function TaxesOrder(){
        //return out(null, 10001,'纳税已停止');
        $user = $this->user;
        $req = $this->validate(request(),[
            'money|申报金额' => 'require|float|between:20000,9999999',
            'pay_password|支付密码' => 'require|length:6,25',
        ]);
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }


        //$req['money'] = round($req['money'], 2, PHP_ROUND_DOWN);;
        $alreadyMoney = TaxOrder::where('user_id', $user['id'])
            ->sum('money');

        $alreadyNotarization = \app\model\Notarization::where('user_id', $user['id'])->where('status',2)->sum('money');
        $alreadyMoney = bcsub($alreadyMoney, $alreadyNotarization, 2);

        $remnantMoney = $user['large_subsidy'] - $alreadyMoney;
        if($remnantMoney < $req['money']){
           return out(null, 10001,'未纳税提现金额不足 '.$remnantMoney);
        }
        $taxesMoney = bcmul($req['money'], 0.015,2);
        if($user['topup_balance'] < $taxesMoney){
            return out(null, 10001,'余额不足，请充值');

        }
        $endTime = date('Y-m-d',strtotime(date('Y-m-d', strtotime('+7 days'))));
        $taxesData = [
            'user_id' => $user['id'],
            'money' => $req['money'],
            'taxes_money' => $taxesMoney,
            'status' => 1,  
            'end_time' => $endTime,
        ];
        Db::startTrans();
        try{
            $taxes = TaxOrder::create($taxesData);
            User::changeInc($user['id'], -$taxesMoney,'topup_balance',35,$taxes['id'],3,'缴纳税费' );
            Db::commit();
            return out($taxes);
        }catch (\Exception $e){
            Db::rollback();
            return out(null, 10001,'缴纳税费失败');
        }
    }


    public function hezhun(){
                $user = $this->user;
        $req = $this->validate(request(),[
            'money|核准金额' => 'require|float|between:30000,9999999',
            'pay_password|支付密码' => 'require|length:6,25',
            'pay_selected'=>'require|number',//首选 1余额支付 3团队奖励

        ]);
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        if($user['large_subsidy']< $req['money']){
            return out(null, 10001,'未纳税提现金额不足 '.$user['large_subsidy']);
        }

        $taxesMoney = bcmul($req['money'], 0.01,2);
/*         if($user['topup_balance'] < $taxesMoney){
            return out(null, 10001,'余额不足，请充值');

        } */
        $pay_amount = $taxesMoney;
        if ($pay_amount >  ($user['topup_balance'] + $user['team_bonus_balance'])) {
            exit_out(null, 10090, '余额不足');
        }


        $endTime = date('Y-m-d',strtotime(date('Y-m-d', strtotime('+7 days'))));
        $taxesData = [
            'user_id' => $user['id'],
            'money' => $req['money'],
            'taxes_money' => $taxesMoney,
            'status' => 1,  
            'end_time' => $endTime,
            'type'=>5, //核准
        ];
        
        Db::startTrans();
        try{

           $taxes = TaxOrder::create($taxesData);


            $txtArr = [1=>'可用余额',3=>'可提余额'];
            if($req['pay_selected']==1){
                $field1 = 'topup_balance';
                $field2 = 'team_bonus_balance';
                $logType1 = 1;
                $logType2 = 3;
            }else{
                $field1 = 'team_bonus_balance';
                $field2 = 'topup_balance';
                $logType1 = 3;
                $logType2 = 1;
            }
            
            if($user[$field1] >= $pay_amount) {

                User::changeInc($user['id'],-$pay_amount,$field1,40,$taxes['id'],$logType1,$txtArr[$logType1].'-资金核准费',0,1,'OD');
            }else{
                if($user[$field1]>0){
                    User::changeInc($user['id'],-$user[$field1],$field1,40,$taxes['id'],$logType1,$txtArr[$logType1].'-资金核准费',0,1,'OD');
                } 
                $topup_amount = bcsub($pay_amount, $user[$field1],2);
                if($user[$field2] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,$field2,40,$taxes['id'],$logType2,$txtArr[$logType2].'-资金核准费',0,1,'OD');
                }else{
                    throw new \Exception('余额不足');
                }
            }

            //User::changeInc($user['id'], -$taxesMoney,'topup_balance',40,$taxes['id'],1,'资金核准费' );
            //User::changeInc($user['id'], -$req['money'],'large_subsidy',40,$taxes['id'],7,'资金核准' );
            //User::changeInc($user['id'], $req['money'],'team_bonus_balance',40,$taxes['id'],3,'资金核准' );
            
            Db::commit();
            return out($taxes);
        }catch (\Exception $e){
            Db::rollback();
            throw $e;
            return out(null, 10001,'缴纳税费失败');
        }
    }
}
