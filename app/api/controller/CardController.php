<?php

namespace app\api\controller;


use app\model\Notarization;
use app\model\User;
use app\model\UserCard;
use app\model\UserDelivery;
use app\model\UserRelation;
use Normalizer;
use think\facade\Db;
use app\model\TaxOrder;

class CardController extends AuthController
{
    public static $statusText = [
        1 => '未激活',
        2 => '已激活',
    ];

    public static $feesMoney = [
        ['min'=>1,'max'=>49999,'fees'=>300],
        ['min'=>50000,'max'=>99999,'fees'=>600],
        ['min'=>100000,'max'=>499999,'fees'=>1000],
        ['min'=>500000,'max'=>99999999,'fees'=>2000],      
    ];


    public function cardInfo(){
        $user = $this->user;
        $card = UserCard::where('user_id', $user['id'])->find();
        $data = [
            'money' => 0,
            'fees' => 0,
            'status' => 0,
            'yesterday_interest'=>0,
        ];
        if(!$card){
            $money = Notarization::where('user_id', $user['id'])->where('type',0)->where('status',2)->sum('money');
            $fees = $this->getFeesMoney($money);
            $data['money'] = $money;
            $data['fees'] = $fees;
            
        }else{
            $data['money'] = $card['money'];
            $data['status'] = $card['status'];
            $data['fees'] = $card['fees'];
            $data['yesterday_interest'] = $card['yesterday_interest'];

        }

            
        return out($data);
    }

    public function active(){
        $user = $this->user;
        $req = $this->validate(request(), [
            'pay_password|支付密码' => 'require|length:6,25',
        ]);
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $card = UserCard::where('user_id', $user['id'])->find();
        if ($card) {
            return out(null, 10001, '银行卡已激活');
        }

        $money = Notarization::where('user_id', $user['id'])->where('type',0)->where('status',2)->sum('money');
        $fees = $this->getFeesMoney($money);
        if($user['topup_balance'] < $fees){
            return out(null, 10001,'余额不足，请充值');
        }

        $data = [
            'user_id' => $user['id'],
            'money' => $money,
            'fees' => $fees,
            'status' => 1,
            'yesterday_interest' => 0,
        ];

        Db::startTrans();
        try{
            $card = UserCard::create($data);
            User::changeInc($user['id'], -$fees,'topup_balance',38,$card['id'],1,'激活银行卡' );
            $sn = build_order_sn($user['id'],'MC');

            // 记录余额变更日志
            \app\model\UserBalanceLog::create([
                'user_id' => $user['id'],
                'type' => 38,
                'log_type' => 13,
                'relation_id' => $card['id'],
                'before_balance' => 0,
                'change_balance' => $money,
                'after_balance' => $money,
                'remark' => '补助资金转入',
                'order_sn' => $sn,
            ]);
            
            Db::commit();
            return out($card);
        }catch (\Exception $e){
            Db::rollback();
            return out(null, 10001,'激活失败');
        }   

    }

    private function getFeesMoney($money){
        $fees = 0;
        foreach(self::$feesMoney as $item){
            if($money >= $item['min'] && $money <= $item['max']){
                $fees = $item['fees'];
                break;
            }
        }
        return $fees;
    }

}
