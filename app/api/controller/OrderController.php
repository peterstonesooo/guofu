<?php

namespace app\api\controller;

use app\model\AssetOrder;
use app\model\AssetOrderNew;
use app\model\AuthOrder;
use app\model\CardOrder;
use app\model\EnsureOrder;
use app\model\NotarizationOrder;
use app\model\Order;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\PassiveIncomeRecord;
use app\model\ProjectCard;
use app\model\ProjectTax;
use app\model\ShopOrder;
use app\model\TaxOrder;
use app\model\User;
use app\model\UserLotteryLog;
use app\model\UserRelation;
use app\model\UserSignin;
use app\model\ZhufangOrder;
use Exception;
use PHPUnit\Framework\Assert;
use think\facade\Db;

class OrderController extends AuthController
{


    public function placeOrder()
    {
        //return out(null, 10001, '维护中，请稍后再试'); 
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
            'pay_method' => 'require|number',
            'pay_selected'=>'require|number',//首选 1余额支付 3团队奖励
            'payment_config_id' => 'requireIf:pay_method,2|requireIf:pay_method,3|requireIf:pay_method,4|requireIf:pay_method,6|number',
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
            'pay_voucher_img_url|支付凭证' => 'requireIf:pay_method,6|url',
        ]);

/*         if($req['pay_selected']==3){
            return out(null, 10001, '暂不支持本项操作');
        } */

        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $project = Project::where('id', $req['project_id'])->where('status',1)->find();
        $projectIsLimit = $project['is_limited'] ?? 0;
        if(!$project){
            return out(null, 10001, '项目不存在');
        }
        if($project->is_limited &&  $project->max_limited<1){
            return out(null, 10001, '项目已领完');
        }
/*         if($req['project_id']==1 || $req['project_id']==2 || $req['project_id']==5 || $req['project_id']==6 ){
            $order = Order::where('user_id', $user['id'])->where('project_id', $req['project_id'])->whereIn('status', [1,2])->find();
            if($order){
                return out(null, 10001, '周期结束前不能重复购买');
            }
        }
        if($project['project_group_id']==2){
            $order = Order::where('user_id', $user['id'])->where('project_group_id', 2)->whereIn('status', [1,2])->find();
            if($order){
                return out(null, 10001, '周期结束前不可参与同系列其他财富方案');
            }
        } */

        if($project['project_group_id']==23){
            $order = Order::where('user_id', $user['id'])->where('project_id', $req['project_id'])->find();
            if($order){
                return out(null, 10001, '一个用户只能购买一份');
            }
        }
        if($project['project_group_id']==27){
            $order = Order::where('user_id', $user['id'])->where('project_group_id', 27)->find();
            if($order){
                return out(null, 10001, '一个用户只能购买一份');
            }
        }
/*         if($project['project_group_id']==7){
            return out(null, 10001, '暂时不能购买');
        } */
/*         if($project['project_group_id']==21){
            $order = Order::where('user_id', $user['id'])->where('project_group_id',21)->whereIn('status', [1,2])->find();
            if($order){
                return out(null, 10001, '每个用户只能购买一份');
            }
        } */

        if (!in_array($req['pay_method'], $project['support_pay_methods'])) {
            return out(null, 10001, '不支持该支付方式');
        }



        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::field('id project_id,name project_name,class,project_group_id,cover_img,single_amount,gift_integral,total_num,daily_bonus_ratio,sum_amount,dividend_cycle,period,single_gift_equity,single_gift_digital_yuan,sham_buy_num,progress_switch,bonus_multiple,settlement_method,withdrawal_limit,digital_red_package,review_period,single_gift_gf_purse,poverty_subsidy_amount,lottery_num,allow_withdraw_money,max_limited,is_limited,week,start_time,end_time,buy_gift_num')
                        ->where('id', $req['project_id'])
                        //->lock(true)
                        ->append(['all_total_buy_num'])
                        ->find()
                        ->toArray();

            $pay_amount = $project['single_amount'];
            $pay_integral = 0;

            //if ($req['pay_method'] == 1 && $pay_amount >  $user['topup_balance']) {
            if(in_array($project['project_group_id'],[14])){
                    //计算今天是周几
                    $weekConf = config('map.week');
                    $weekText = $weekConf[$project['week']];
                    $errText = "活动未开放，请在 {$weekText} {$project['start_time']} - {$project['end_time']} 报名";
                    if($project['week'] == 0){
                       return out(null, 10001, '$errText');
                    }
                    $week = date('w');
                    if($week == 0){
                        $week = 7;
                    }
                    $time1 = date('H:i:s');
                    if($week !=$project['week']){
                        return out(null, 10001, $errText);
                    }
                    if($time1 < $project['start_time'] || $time1 > $project['end_time']){
                        
                        return out(null, 10001, $errText);
                    }
                    if($pay_amount > $user['ph_wallet']){
                        return out(null, 10001, '余额不足');
                    }
                    
            }else{

                if ($req['pay_method'] == 1 && $pay_amount >  $user['topup_balance'] ) {
                    exit_out(null, 10090, '余额不足');
                }
            }
 
            //没有团队奖励支付方式先屏蔽
            // if ($req['pay_method'] == 5) {
            //     $pay_integral = $project['single_amount'];
            //     if ($pay_integral > $user['team_bonus_balance']) {
            //         exit_out(null, 10003, '团队奖励不足');
            //     }
            // }

/*             if (in_array($req['pay_method'], [2,3,4,6])) {
                $type = $req['pay_method'] - 1;
                if ($req['pay_method'] == 6) {
                    $type = 4;
                }
                $paymentConf = PaymentConfig::userCanPayChannel($req['payment_config_id'], $type, $pay_amount);
            } */

            if (isset(config('map.order')['pay_method_map'][$req['pay_method']]) === false) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            if (empty($req['pay_method'])) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            $order_sn = 'OD'.build_order_sn($user['id']);


            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 1;
            $project['pay_method'] = $req['pay_method'];
            $project['price'] = $pay_amount;
            unset($project['end_time']);
            $order = Order::create($project);
            $project['order_sn'] = 'OD'.build_order_sn($user['id']);



/*             if($project['project_group_id'] == 20){
                $order2 = Order::create($project);
            } */
 /*           $project['order_sn'] = 'OD'.build_order_sn($user['id']);
            $order3 = Order::create($project);
            $project['order_sn'] = 'OD'.build_order_sn($user['id']);
            $order4 = Order::create($project); */


            if ($req['pay_method']==1) {

/*                 if($project['project_group_id'] == 8) {//不使用签到金
                    // 扣余额
                    if($user['topup_balance'] >= $pay_amount) {
                        User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                    } else {
                        User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                        $topup_amount = bcsub($pay_amount, $user['topup_balance'],2);
                        User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                    }
                } else {
                    // 扣余额
                    if($user['topup_balance'] >= $pay_amount) {
                        User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                    } else {
                        User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                        $topup_amount = bcsub($pay_amount, $user['topup_balance'],2);
                        if($user['signin_balance'] >= $topup_amount) {
                            User::changeInc($user['id'],-$topup_amount,'signin_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                        } else {
                            User::changeInc($user['id'],-$user['signin_balance'],'signin_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                            $signin_amount = bcsub($topup_amount, $user['signin_balance'],2);
                            if($user['team_bonus_balance'] >= $signin_amount) {
                                User::changeInc($user['id'],-$signin_amount,'team_bonus_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                            } 
                        }
                    }*/
                    if(in_array($project['project_group_id'],[14])){

                        if($user['ph_wallet'] >= $pay_amount){
                            User::changeInc($user['id'],-$pay_amount,'ph_wallet',3,$order['id'],9,'普惠钱包'.'-'.$project['project_name'],0,1,'OD');

                        }else{
                            return out(null, 10001, '余额不足');
                        }
                    }else{

                        $txtArr = [1=>'可用余额',3=>'可提余额'];
                        //if($req['pay_selected']==1){
                            $field1 = 'topup_balance';
                            $field2 = 'team_bonus_balance';
                            $logType1 = 1;
                            $logType2 = 3;
/*                         }else{
                            $field1 = 'team_bonus_balance';
                            $field2 = 'topup_balance';
                            $logType1 = 3;
                            $logType2 = 1;
                        } */
                        
                        if($user[$field1] >= $pay_amount) {

                            User::changeInc($user['id'],-$pay_amount,$field1,3,$order['id'],$logType1,$txtArr[$logType1].'-'.$project['project_name'],0,1,'OD');
                        }else{
                                throw new Exception('余额不足');
                        }
                        
                        
/*                         else{
                            if($user[$field1]>0){
                                User::changeInc($user['id'],-$user[$field1],$field1,3,$order['id'],$logType1,$txtArr[$logType1].'-'.$project['project_name'],0,1,'OD');
                            } 
                            $topup_amount = bcsub($pay_amount, $user[$field1],2);
                            if($user[$field2] >= $topup_amount) {
                                User::changeInc($user['id'],-$topup_amount,$field2,3,$order['id'],$logType2,$txtArr[$logType2].'-'.$project['project_name'],0,1,'OD');
                            }else{
                                throw new Exception('余额不足');
                            }
                        } */
                    }
/*                 if($project['project_group_id'] == 20){
                    User::changeInc($user['id'],0,$field1,3,$order2['id'],$logType1,$txtArr[$logType1].'-'.$project['project_name'].'-赠送',0,1,'OD');
                } */
                    
                    //User::changeInc($user['id'],0,$field1,3,$order3['id'],$logType1,$txtArr[$logType1].'-'.$project['project_name'].'-赠送',0,1,'OD');
                    //User::changeInc($user['id'],0,$field1,3,$order4['id'],$logType1,$txtArr[$logType1].'-'.$project['project_name'].'-赠送',0,1,'OD');

                

                // 累计总收益和赠送数字人民币  到期结算
                // 订单支付完成
                Order::orderPayComplete($order['id'], $project, $user['id'],0);
                
                if(isset($project['buy_gift_num']) && $project['buy_gift_num'] > 0){
                    for($i = 0; $i < $project['buy_gift_num']; $i++){
                        // 创建赠送项目数据副本
                        $giftProject = $project;
                        $giftProject['order_sn'] = 'OD'.build_order_sn($user['id']);
                        $giftProject['is_gift'] = 1;
                        //$giftProject['price'] = 0; // 赠送订单价格为0
                        //$giftProject['pay_method'] = 0; // 赠送订单无需支付方式
                        
                        $giftOrder = Order::create($giftProject);
                        
                        // 记录赠送日志（金额为0）
                        User::changeInc($user['id'], 0, $field1, 3, $giftOrder['id'], $logType1, $txtArr[$logType1].'-'.$project['project_name'].'-赠送', 0, 1, 'OD');
                        
                        // 完成赠送订单
                        Order::orderPayComplete($giftOrder['id'], $giftProject, $user['id'], 1);
                    }
                }              
                
/*                 if($project['project_group_id'] == 20){
                    Order::orderPayComplete($order2['id'], $project, $user['id'],1);
                } */
                //Order::orderPayComplete($order3['id'], $project, $user['id'],1);
                //Order::orderPayComplete($order4['id'], $project, $user['id'],1);
                if($projectIsLimit){
                    Project::where('id', $req['project_id'])->dec('max_limited')->update();
                }
/*                 $project = Project::where('id', $req['project_id'])->find();
                if ($project !== null) { // 确保查询到了项目
                    $project->max_limited -= 1;

                    // 使用模型更新数据
                    $result = $project->save();
                } */
            } else {
                exit_out(null, 10005, '支付渠道不存在');
            }
            //没有团队奖励支付方式先屏蔽
            // else if($req['pay_method']==5){
            //     // 扣团队奖励
            //     User::changeInc($user['id'],-$pay_amount,'team_bonus_balance',3,$order['id'],2);
            //     // 累计总收益和赠送数字人民币  到期结算
            //     // 订单支付完成
            //     Order::orderPayComplete($order['id']);
            // }
            // 发起第三方支付
            // if (in_array($req['pay_method'], [2,3,4,6])) {
            //     $card_info = '';
            //     if (!empty($paymentConf['card_info'])) {
            //         $card_info = json_encode($paymentConf['card_info']);
            //         if (empty($card_info)) {
            //             $card_info = '';
            //         }
            //     }
            //     // 创建支付记录
            //     Payment::create([
            //         'user_id' => $user['id'],
            //         'trade_sn' => $order_sn,
            //         'pay_amount' => $pay_amount,
            //         'order_id' => $order['id'],
            //         'payment_config_id' => $paymentConf['id'],
            //         'channel' => $paymentConf['channel'],
            //         'mark' => $paymentConf['mark'],
            //         'type' => $paymentConf['type'],
            //         'card_info' => $card_info,
            //         'product_type'=>1,
            //         'pay_voucher_img_url'=>$req['pay_voucher_img_url'],
            //     ]);
            //     // 发起支付
            //     if ($paymentConf['channel'] == 1) {
            //         $ret = Payment::requestPayment($order_sn, $paymentConf['mark'], $pay_amount);
            //     }
            //     elseif ($paymentConf['channel'] == 2) {
            //         $ret = Payment::requestPayment2($order_sn, $paymentConf['mark'], $pay_amount);
            //     }
            //     elseif ($paymentConf['channel'] == 3) {
            //         $ret = Payment::requestPayment3($order_sn, $paymentConf['mark'], $pay_amount);
            //     }else if($paymentConf['channel']==8){
            //         $ret = Payment::requestPayment4($order_sn, $paymentConf['mark'], $pay_amount);
            //     }else if($paymentConf['channel']==9){
            //         $ret = Payment::requestPayment5($order_sn, $paymentConf['mark'], $pay_amount);
            //     }
            // }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
            //return out(null, 10001, $e->getMessage());
        }

        return out(['order_id' => $order['id'] ?? 0, 'trade_sn' => $trade_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);

    }

    public function shopPlaceOrder()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
            'pay_method' => 'require|number',
            'payment_config_id' => 'requireIf:pay_method,2|requireIf:pay_method,3|requireIf:pay_method,4|requireIf:pay_method,6|number',
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
            'pay_voucher_img_url|支付凭证' => 'requireIf:pay_method,6|url',
        ]);

        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $project = Project::where('id', $req['project_id'])->where('status',1)->find();
        if(!$project){
            return out(null, 10001, '项目不存在');
        }

        if($project['realtime_rate'] >= 100) {
            return out(null, 10001, '份额已满');
        }

        if (!in_array($req['pay_method'], $project['support_pay_methods'])) {
            return out(null, 10001, '不支持该支付方式');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::field('id project_id,name project_name,class,project_group_id,cover_img,single_amount,gift_integral,total_num,daily_bonus_ratio,sum_amount,dividend_cycle,period,single_gift_equity,single_gift_digital_yuan,sham_buy_num,progress_switch,bonus_multiple,settlement_method,withdrawal_limit,digital_red_package,created_at,shop_profit,min_flow_amount,max_flow_amount,ensure,flow_type')
                        ->where('id', $req['project_id'])
                        ->lock(true)
                        ->find()
                        ->toArray();

            $pay_amount = $project['single_amount'];

            if($user['flow_amount'] > $project['max_flow_amount'] || $user['flow_amount'] < $project['min_flow_amount']) {
                return out(null, 10001, '流转金额不在范围内');
            }

            if ($req['pay_method'] == 1 && $pay_amount >  ($user['topup_balance'] + $user['signin_balance'] + $user['team_bonus_balance'])) {
                exit_out(null, 10090, '余额不足');
            }

            if (in_array($req['pay_method'], [2,3,4,6])) {
                $type = $req['pay_method'] - 1;
                if ($req['pay_method'] == 6) {
                    $type = 4;
                }
                $paymentConf = PaymentConfig::userCanPayChannel($req['payment_config_id'], $type, $pay_amount);
            }

            if (isset(config('map.order')['pay_method_map'][$req['pay_method']]) === false) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            if (empty($req['pay_method'])) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            $order_sn = 'GF'.build_order_sn($user['id']);


            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 1;
            $project['pay_method'] = $req['pay_method'];
            $project['price'] = $pay_amount;
            $project['flow_amount'] = $user['flow_amount'];

            $order = ShopOrder::create($project);

            if ($req['pay_method']==1) {
                // 扣余额
                if($user['topup_balance'] >= $pay_amount) {
                    User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                } else {
                    User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                    $topup_amount = bcsub($pay_amount, $user['topup_balance'],2);
                    if($user['signin_balance'] >= $topup_amount) {
                        User::changeInc($user['id'],-$topup_amount,'signin_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                    } else {
                        User::changeInc($user['id'],-$user['signin_balance'],'signin_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                        $signin_amount = bcsub($topup_amount, $user['signin_balance'],2);
                        User::changeInc($user['id'],-$signin_amount,'team_bonus_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                        // if($user['team_bonus_balance'] >= $signin_amount) {
                        //     User::changeInc($user['id'],-$signin_amount,'team_bonus_balance',35,$order['id'],1,$project['project_name'],0,1,'GF');
                        // } else {
                        //     User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',3,$order['id'],1,$project['project_name'],0,1,'GF');
                        //     $team_amount = bcsub($signin_amount, $user['team_bonus_balance'],2);
                        //     User::changeInc($user['id'],-$team_amount,'gf_purse',3,$order['id'],1,$project['project_name'],0,1,'GF');
                        // }
                    }
                }

                $end_time = strtotime("+{$project['period']} day");
                ShopOrder::where('id', $order['id'])->update([
                    'status' => 2,
                    'pay_time' => time(),
                    'end_time' => $end_time,
                    'gain_bonus' => 0,
                    'next_bonus_time' => $end_time,
                ]);
                $userUpdate = ['invest_amount'=>Db::raw('invest_amount+'.$pay_amount),'3_1_invest_amount'=>Db::raw('3_1_invest_amount+'.$pay_amount)];
                User::where('id', $user['id'])->update($userUpdate);
                //User::upLevel($user['id']);
                User::changeInc($user['id'],-$user['flow_amount'],'flow_amount',33,$order['id'],7);
                UserRelation::where('sub_user_id',$user['id'])->update(['is_flow_buy'=>1,'flow_buy_time'=>time()]);

            } else {
                exit_out(null, 10005, '支付渠道不存在');
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['order_id' => $order['id'] ?? 0, 'trade_sn' => $trade_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);
    }

    public function shopOrderList()
    {
        $req = $this->validate(request(), [
            'status' => 'number',
        ]);
        $user = $this->user;

        $builder = ShopOrder::where('user_id', $user['id']);
        
        if (!empty($req['status'])) {
            $builder->where('status', $req['status']);
        }
        $data = $builder->order('id', 'desc')->paginate(10,false,['query'=>request()->param()]);

        foreach($data as $item) {
            $item['end_time'] = date('Y-m-d H:i:s', $item['end_time']);
        }

        return out($data);
    }

    public function assetPlaceOrder()
    {
        $req = $this->validate(request(), [
            'type' => 'require|number',
            'name|姓名'=> 'require',
            'phone|手机号' => 'require',
            'id_card|身份证号' => 'require',
            // 'balance|账户余额' => 'require|number',
            'digital_yuan_amount|数字人民币' => 'number',
            // 'poverty_subsidy_amount|生活补助' => 'require|number',
            'level|共富等级' => 'require|number',
            'ensure|共富保障' => 'max:100',
            'rich|共富方式' => 'require|number',
            'pay_password|支付密码' => 'require',
            'times' => 'require|number',
        ]);
        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $count = AssetOrder::where('user_id', $user['id'])->where('created_at', '<', '2024-03-24 00:00:00')->count();
        if($count) {
            return out(null, 10111, '您已申请过资产交接，不能再次申请');
        }

        $asorder = AssetOrder::where('user_id', $user['id'])->where('status', 2)->find();
        if(($asorder && $req['times'] == 1) || ($asorder && $req['times'] == 2 && $asorder['times'] == 2) || ($asorder && $asorder['times'] == 1 && $req['times'] == 1)) {
            return out(null, 10111, '您已提交过申请，请勿重复提交');
        }
        $max_level = config('map.asset_recovery')[$req['type']]['max_level'];
        if($req['level'] > $max_level) {
            return out(null, 10112, '共富等级超过限制，请重新选择');
        }


        $min_asset = config('map.asset_recovery')[$req['type']]['min_asset'];
        $max_asset = config('map.asset_recovery')[$req['type']]['max_asset'];

        if(!isset($req['digital_yuan_amount']) || !$req['digital_yuan_amount']) {
            if($max_asset == 'max') {
                $req['digital_yuan_amount'] = 200000000;
            } else {
                $req['digital_yuan_amount'] = (mt_rand($min_asset, $max_asset)) * 10000;
            }
            $req['forget_amount'] = 1;
            
        } else {
            if($max_asset == 'max' && $req['digital_yuan_amount'] > 50000000) {
                return out(null, 10110, '恢复资产超过限制');
            } elseif($max_asset != 'max') {
                $max_asset = $max_asset * 10000;
                if($req['digital_yuan_amount'] > $max_asset) {
                    return out(null, 10110, '恢复资产超过限制');
                }
            }
            
        }

        $amount = config('map.asset_recovery')[$req['type']]['amount'];

        if ($amount >  ($user['topup_balance'] + $user['signin_balance'] + $user['team_bonus_balance'])) {
            exit_out(null, 10090, '余额不足');
        }

        Db::startTrans();
        try {
            if($req['times'] == 2) {
                if($asorder['reward_status'] == 1) {
                    $req['last_time_amount'] = $asorder['digital_yuan_amount'];
                }
                AssetOrder::where('id', $asorder['id'])->delete();
                //EnsureOrder::where('user_id', $user['id'])->delete();
                // if($asorder['times'] == 2) {
                //     $req['times'] = 3;
                // }
            }
            $req['user_id'] = $user['id'];
            $req['order_sn'] = 'GF'.build_order_sn($user['id']);
            $req['status'] = 2;
            if(isset($req['ensure']) && !empty($req['ensure'])) {
                $req['ensure'] = implode(',', $req['ensure']);
            }
            $req['rich'] = config('map.asset_recovery')[$req['type']]['rich'];
            $req['next_return_time'] = strtotime("+25 day", strtotime(date('Y-m-d')));
            $req['next_reward_time'] = strtotime("+24 hours");
            $order = AssetOrder::create($req);

            
            //购买产品和资产恢复都要激活用户
            $userUpdate = ['can_open_digital' => 1,'invest_amount'=>Db::raw('invest_amount+'.$amount),'3_1_invest_amount'=>Db::raw('3_1_invest_amount+'.$amount)];
            if ($user['is_active'] == 0) {
                $userUpdate['is_active'] = 1;
                $userUpdate['active_time'] = time();
                // 下级用户激活
                \app\model\UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
            }
            //扣余额
            if($user['topup_balance'] >= $amount) {
                User::changeInc($user['id'],-$amount,'topup_balance',25,$order['id'],1,'资产交接',0,1,'JJ');
            } else {
                User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',25,$order['id'],1,'资产交接',0,1,'JJ');
                $topup_amount = bcsub($amount, $user['topup_balance'],2);
                if($user['signin_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'signin_balance',25,$order['id'],1,'资产交接',0,1,'JJ');
                } else {
                    User::changeInc($user['id'],-$user['signin_balance'],'signin_balance',35,$order['id'],1,'资产交接',0,1,'JJ');
                    $signin_amount = bcsub($topup_amount, $user['signin_balance'],2);
                    User::changeInc($user['id'],-$signin_amount,'team_bonus_balance',35,$order['id'],1,'资产交接',0,1,'JJ');
                    // if($user['team_bonus_balance'] >= $signin_amount) {
                    //     User::changeInc($user['id'],-$signin_amount,'team_bonus_balance',35,$order['id'],1,'资产交接',0,1,'JJ');
                    // } else {
                    //     User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',3,$order['id'],1,'资产交接',0,1,'JJ');
                    //     $team_amount = bcsub($signin_amount, $user['team_bonus_balance'],2);
                    //     User::changeInc($user['id'],-$team_amount,'gf_purse',3,$order['id'],1,'资产交接',0,1,'JJ');
                    // }
                }
            }
            //User::changeInc($user['id'],-$amount,'topup_balance',25,$order['id'],1, '资产交接',0,1,'JJ');
            User::where('id', $user['id'])->update($userUpdate);
            //下单保障项目
            if(isset($req['ensure']) && !empty($req['ensure'])) {
                $ensure = explode(',', $req['ensure']);
                foreach ($ensure as $key => $value) {
                    $data = config('map.ensure')[$value];
                    $insert['user_id'] = $user['id'];
                    $insert['order_sn'] = 'GF'.build_order_sn($user['id']);
                    $insert['status'] = 2;
                    $insert['ensure'] = $value;
                    $insert['amount'] = $data['amount'];
                    $insert['receive_amount'] = $data['receive_amount'];
                    $insert['process_time'] = $data['process_time'];
                    $insert['verify_time'] = $data['verify_time'];
                    $insert['next_reward_time'] = strtotime("+{$data['process_time']} day", strtotime(date('Y-m-d')));
                    $insert['next_return_time'] = strtotime("+{$data['verify_time']} day", strtotime(date('Y-m-d')));
                    $order = EnsureOrder::create($insert);
                }
            }

            //User::upLevel($user['id']);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out(['order_id' => $order['id'] ?? 0]);
    }

    public function assetDetail()
    {
        $user = $this->user;

        $data = AssetOrder::where('user_id', $user['id'])->find();

        if(!$data){
            return out(null, 10001, '没有交接资产');
        }

        if($data['forget_amount'] == 1) {
            $data['digital_yuan_amount'] = '';
        } else {
            $data['digital_yuan_amount'] = $data['digital_yuan_amount'].'元';
        }

        $data['next_reward_time'] = date('Y-m-d H:i:s', $data['next_reward_time']);

        return out($data);
    }

    public function zhufangList()
    {

        $data = config('map.zhufang');
        return out($data);
    }

    public function zhufangPlaceOrder()
    {
        //return out(null,10010,'名额已满');
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $user = $this->user;
        //exit_out(null, 10001, '住房名额已满');
        $amount = $user['3_1_invest_amount'];
        if($req['id'] == 1) {
            if($amount < 5000) {
                exit_out(null, 10001, '3月1日起消费金额满5000元可领取，现消费金额'.$amount.'元');
            }
        }
        if($req['id'] == 2) {
            if($amount < 10000) {
                exit_out(null, 10001, '3月1日起消费金额满10000元可领取，现消费金额'.$amount.'元');
            }
        }

        if($req['id'] == 3) {
            if($amount < 20000) {
                exit_out(null, 10001, '3月1日起消费金额满20000元可领取，现消费金额'.$amount.'元');
            }
        }

        if($req['id'] == 4) {
            if($amount < 30000) {
                exit_out(null, 10001, '3月1日起消费金额满30000元可领取，现消费金额'.$amount.'元');
            }
        }

        //$data = config('map.zhufang')[$req['id']];

        $repeat = ZhufangOrder::where('user_id', $user['id'])->find();
        if($repeat) {
            if($repeat['ensure'] >= $req['id']) {
                exit_out(null, 10001, '您已经领取了住房保障');
            }
            Db::startTrans();
            try {
                $insert['ensure'] = $req['id'];
                $order = ZhufangOrder::where('id', $repeat['id'])->update($insert);
                if ($insert['ensure'] == 1) {
                    $zhufang_text = '40㎡';
                } elseif ($insert['ensure'] == 2) {
                    $zhufang_text = '65㎡';
                } elseif ($insert['ensure'] == 3) {
                    $zhufang_text = '88㎡';
                } elseif ($insert['ensure'] == 4) {
                    $zhufang_text = '125㎡';
                }
                NotarizationOrder::where('user_id', $user['id'])->update(['zhufang_text' => $zhufang_text]);
               // User::changeInc($user['id'],-$data['amount'],'topup_balance',26,$order['id'],1,'',0,1,'BZ');
                //ShopOrder::where('id', $use['id'])->update(['used_ensure' => 1]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
            
        } else {
            Db::startTrans();
            try {
                $insert['user_id'] = $user['id'];
                $insert['order_sn'] = 'GF'.build_order_sn($user['id']);
                $insert['status'] = 2;
                $insert['ensure'] = $req['id'];
                $order = ZhufangOrder::create($insert);
               // User::changeInc($user['id'],-$data['amount'],'topup_balance',26,$order['id'],1,'',0,1,'BZ');
                //ShopOrder::where('id', $use['id'])->update(['used_ensure' => 1]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }


        return out(['order_id' => $order['id'] ?? 0]);
    }

    public function notarizationConfig()
    {
        $user = $this->user;
        $tongxun_old = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 4)->count();
        $tongxun_new = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 8)->count();
        $yanglao_old = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 3)->count();
        $yanglao_new = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 7)->count();
        $chuxing_old = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 2)->count();
        $chuxing_new = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 6)->count();
        $jintie_old = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->whereIn('ensure', 1)->count();
        $jintie_new = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->whereIn('ensure', 5)->count();
        $zhufang = ZhufangOrder::where('user_id', $user['id'])->where('notarization_status', 0)->find();
        if($tongxun_old) {
            $tongxun_old = 1;
        }
        if($yanglao_old) {
            $yanglao_old = 1;
        }
        if($chuxing_old) {
            $chuxing_old = 1;
        }
        if($jintie_old) {
            $jintie_old = 1;
        }
        if($tongxun_new) {
            $tongxun_new = 1;
        }
        if($yanglao_new) {
            $yanglao_new = 1;
        }
        if($chuxing_new) {
            $chuxing_new = 1;
        }
        if($jintie_new) {
            $jintie_new = 1;
        }
        $tongxun = $tongxun_old + $tongxun_new;
        $yanglao = $yanglao_old + $yanglao_new;
        $chuxing = $chuxing_old + $chuxing_new;
        $jintie = $jintie_old + $jintie_new;
        $tongxun_text = $tongxun.'部';
        $yanglao_text = ($yanglao*20).'万';
        $chuxing_text = $chuxing.'台';
        $jintie_text = $jintie.'份';
        if(!$zhufang) {
            $zhufang_text = '无';
        } elseif ($zhufang['ensure'] == 1) {
            $zhufang_text = '40㎡';
        } elseif ($zhufang['ensure'] == 2) {
            $zhufang_text = '65㎡';
        } elseif ($zhufang['ensure'] == 3) {
            $zhufang_text = '88㎡';
        } elseif ($zhufang['ensure'] == 4) {
            $zhufang_text = '125㎡';
        }

        if($user['assessment_amount'] >= 1 && $user['assessment_amount'] < 3000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0005);
        } elseif ($user['assessment_amount'] >= 3000000 && $user['assessment_amount'] < 20000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0004);
        } elseif ($user['assessment_amount'] >= 20000000 && $user['assessment_amount'] < 50000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0003);
        } elseif ($user['assessment_amount'] >= 50000000 && $user['assessment_amount'] < 100000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0002);
        } elseif ($user['assessment_amount'] >= 100000000 && $user['assessment_amount'] < 500000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.00015);
        } elseif ($user['assessment_amount'] >= 500000000 && $user['assessment_amount'] < 2000000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0001);
        } elseif ($user['assessment_amount'] < 1) {
            $notarization_amount = 0;
        }

        return out([
            'tongxun_text' => $tongxun_text,
            'yanglao_text' => $yanglao_text,
            'chuxing_text' => $chuxing_text,
            'zhufang_text' => $zhufang_text,
            'jintie_text' => $jintie_text,
            'notarization_amount' => $notarization_amount,
        ]);
    }

    public function notarizationPlaceOrder()
    {
        $req = $this->validate(request(), [
            'pay_password|支付密码' => 'require',
        ]);
        $user = $this->user;
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        if($user['assessment_amount'] >= 1 && $user['assessment_amount'] < 3000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0005);
        } elseif ($user['assessment_amount'] >= 3000000 && $user['assessment_amount'] < 20000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0004);
        } elseif ($user['assessment_amount'] >= 20000000 && $user['assessment_amount'] < 50000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0003);
        } elseif ($user['assessment_amount'] >= 50000000 && $user['assessment_amount'] < 100000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0002);
        } elseif ($user['assessment_amount'] >= 100000000 && $user['assessment_amount'] < 500000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.00015);
        } elseif ($user['assessment_amount'] >= 500000000 && $user['assessment_amount'] < 2000000000) {
            $notarization_amount = bcmul($user['assessment_amount'],0.0001);
        } elseif ($user['assessment_amount'] < 1) {
            $notarization_amount = 0;
        }

        if($notarization_amount == 0) {
            exit_out(null, 10001, '您的已纳税金额为0');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();


            $pay_amount = $notarization_amount;

            if ($pay_amount >  ($user['topup_balance'] + $user['signin_balance'] + $user['team_bonus_balance'])) {
                exit_out(null, 10090, '余额不足');
            }

            $tongxun_old = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 4)->count();
            $tongxun_new = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 8)->count();
            $yanglao_old = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 3)->count();
            $yanglao_new = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 7)->count();
            $chuxing_old = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 2)->count();
            $chuxing_new = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->where('ensure', 6)->count();
            $jintie_old = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->whereIn('ensure', 1)->count();
            $jintie_new = EnsureOrder::where('user_id', $user['id'])->where('notarization_status', 0)->whereIn('ensure', 5)->count();
            $zhufang = ZhufangOrder::where('user_id', $user['id'])->where('notarization_status', 0)->find();
            if($tongxun_old) {
                $tongxun_old = 1;
            }
            if($yanglao_old) {
                $yanglao_old = 1;
            }
            if($chuxing_old) {
                $chuxing_old = 1;
            }
            if($jintie_old) {
                $jintie_old = 1;
            }
            if($tongxun_new) {
                $tongxun_new = 1;
            }
            if($yanglao_new) {
                $yanglao_new = 1;
            }
            if($chuxing_new) {
                $chuxing_new = 1;
            }
            if($jintie_new) {
                $jintie_new = 1;
            }
            $tongxun = $tongxun_old + $tongxun_new;
            $yanglao = $yanglao_old + $yanglao_new;
            $chuxing = $chuxing_old + $chuxing_new;
            $jintie = $jintie_old + $jintie_new;
            $tongxun_text = $tongxun.'部';
            $yanglao_text = ($yanglao*20).'万';
            $chuxing_text = $chuxing.'台';
            $jintie_text = $jintie.'份';
            if(!$zhufang) {
                $zhufang_text = '无';
            } elseif ($zhufang['ensure'] == 1) {
                $zhufang_text = '40㎡';
            } elseif ($zhufang['ensure'] == 2) {
                $zhufang_text = '65㎡';
            } elseif ($zhufang['ensure'] == 3) {
                $zhufang_text = '88㎡';
            } elseif ($zhufang['ensure'] == 4) {
                $zhufang_text = '125㎡';
            }

            $order_sn = build_order_sn($user['id'], 'SW');
            $number = mt_rand(100000, 999999);
            $count = NotarizationOrder::where('number', $number)->count();
            while ($count) {
                $number = mt_rand(10000000, 99999999);
                $count = NotarizationOrder::where('number', $number)->count();
            }

            $project = [];
            $project['user_id'] = $user['id'];
            $project['order_sn'] = $order_sn;
            $project['number'] = $number;
            $project['amount'] = $pay_amount;
            $project['tongxun_text'] = $tongxun_text;
            $project['yanglao_text'] = $yanglao_text;
            $project['chuxing_text'] = $chuxing_text;
            $project['zhufang_text'] = $zhufang_text;
            $project['jintie_text'] = $jintie_text;
            $project['assessment_amount'] = $user['assessment_amount'];
            $order = NotarizationOrder::create($project);
            // 扣余额
            if($user['topup_balance'] >= $pay_amount) {
                User::changeInc($user['id'],-$pay_amount,'topup_balance',38,$order['id'],1,'资产公证',0,1,'GF');
            } else {
                User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',38,$order['id'],1,'资产公证',0,1,'GF');
                $topup_amount = bcsub($pay_amount, $user['topup_balance'],2);
                if($user['signin_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'signin_balance',38,$order['id'],1,'资产公证',0,1,'GF');
                } else {
                    User::changeInc($user['id'],-$user['signin_balance'],'signin_balance',38,$order['id'],1,'资产公证',0,1,'GF');
                    $signin_amount = bcsub($topup_amount, $user['signin_balance'],2);
                    User::changeInc($user['id'],-$signin_amount,'team_bonus_balance',38,$order['id'],1,'资产公证',0,1,'GF');
                    // if($user['team_bonus_balance'] >= $signin_amount) {
                    //     User::changeInc($user['id'],-$signin_amount,'team_bonus_balance',38,$order['id'],1,'资产公证',0,1,'GF');
                    // } else {
                    //     User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',38,$order['id'],1,'资产公证',0,1,'GF');
                    //     $team_amount = bcsub($signin_amount, $user['team_bonus_balance'],2);
                    //     User::changeInc($user['id'],-$team_amount,'gf_purse',38,$order['id'],1,'资产公证',0,1,'GF');
                    // }
                }

                if($user['signin_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'signin_balance',38,$order['id'],1,'资产公证',0,1,'GF');
                } else {
                    User::changeInc($user['id'],-$user['signin_balance'],'signin_balance',38,$order['id'],1,'资产公证',0,1,'GF');
                    $signin_amount = $topup_amount - $user['signin_balance'];
                    User::changeInc($user['id'],-$signin_amount,'team_bonus_balance',38,$order['id'],1,'资产公证',0,1,'GF');
                }
            }

            User::changeInc($user['id'],-$user['assessment_amount'],'assessment_amount',38,$order['id'],6);
            User::changeInc($user['id'],$user['assessment_amount'],'notarization_amount',38,$order['id'],6,'已公证资产');
            User::where('id',$user['id'])->update(['notarization_time'=>strtotime(date('Y-m-d H:i:s'))]);

            if ($user['is_active'] == 0 ) {
                User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                // 下级用户激活
                UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
            }
            User::where('id',$user['id'])->inc('3_1_invest_amount',$pay_amount)->update();
            User::where('id',$user['id'])->inc('invest_amount',$pay_amount)->update();
            EnsureOrder::where('user_id', $user['id'])->update(['notarization_status' => 1]);
            ZhufangOrder::where('user_id', $user['id'])->update(['notarization_status' => 1]);
            //User::upLevel($user['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out(['order_id' => $order['id'] ?? 0]);

    }

    public function notarizationOrder()
    {
        $user = $this->user;
        $data = NotarizationOrder::where('user_id', $user['id'])->paginate();
        foreach($data as $k => $v){
            $data[$k]['notarization_amount'] = $v['amount'];
        }
        return out($data);
    }

    public function assetOrderConfig()
    {
        return out(
            config('map.asset_recovery')
        );
    }

    public function assetOrderList()
    {

        $user = $this->user;

        $order = AssetOrder::where('user_id', $user['id'])->where('status', 2)->find();

        $data = config('map.ensure');

        if($order && $order['ensure']) {
            $ensure = explode(',', $order['ensure']);
            foreach ($ensure as $value) {
                $data[$value]['receive'] = !0;
            }
        }
        foreach ($data as $key => $value) {
            $e = EnsureOrder::where('user_id', $user['id'])->where('ensure', $value['id'])->find();
            if($e) {
                $data[$key]['receive'] = !0;
                $data[$key]['notarization_status'] = $e['notarization_status'];
            }
        }

        $zhufang = ZhufangOrder::where('user_id', $user['id'])->where('status', 2)->find();
        if($zhufang) {
            $zhufangdata = config('map.zhufang')[$zhufang['ensure']];
            $zhufangdata['receive'] = !0;
            $zhufangdata['notarization_status'] = $zhufang['notarization_status'];
            array_push($data, $zhufangdata);
        }
        $apply =\app\model\Apply::where('user_id', $user['id'])->where('type', 1)->find();
        if($apply){
            $data[]=[
                'id' => 99,
                'name' => '商品住房',
                'img' => env('app.host').'/shangpinfang.jpg',
                'intro_img' => env('app.host').'/shangpinfang.jpg',
                'receive' => true,
                'amount' => 0,
                'receive_amount' => 0,
                'process_time' => 0,
                'verify_time' => 0,
                'remain_count' => 0,
                'notarization_status' => 0,
            ];
        }

       // unset($data[1]);
        return out($data);
    }

    public function assetOrderIndexList()
    {

        $data = config('map.ensure');
        unset($data[1]);
        unset($data[2]);
        unset($data[3]);
        unset($data[4]);
        foreach ($data as $key => $value) {
            $data[$key]['receive'] = !0;
        }
        return out($data);
    }

    public function receivePlaceOrder()
    {
        //return out(null,10010,'名额已满');
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $user = $this->user;

        if($req['id'] == 5 || $req['id'] == 6 || $req['id'] == 7 || $req['id'] == 8) {
            exit_out(null, 10001, '份额已满');
        }

        $count = ShopOrder::where('user_id', $user['id'])
            ->count();
        if(!$count) {
            exit_out(null, 10001, '请先在流转商城中进行流转');
        }
        // if(!$use) {
        //     exit_out(null, 10001, '您暂时无法申请，请先申请流转');
        // }
        $data = config('map.ensure')[$req['id']];

        $repeat = EnsureOrder::where('user_id', $user['id'])->where('ensure', $req['id'])->count();
        if($repeat) {
            exit_out(null, 10001, '您已经领取'.$data['name'].',不能重复领取');
        }

        $amount = $user['invest_amount'];
        if($req['id'] == 8) {
            if($amount < 3000) {
                exit_out(null, 10001, '申请消费满3000可领取已消费'.$amount);
            }
        }
        if($req['id'] == 7) {
            if($amount < 6000) {
                exit_out(null, 10001, '申请消费满6000可领取已消费'.$amount);
            }
        }

        if($req['id'] == 6) {
            if($amount < 10000) {
                exit_out(null, 10001, '申请消费满10000可领取已消费'.$amount);
            }
        }

        if($req['id'] == 5) {
            if($amount < 20000) {
                exit_out(null, 10001, '申请消费满20000可领取已消费'.$amount);
            }
        }


        // if ($data['amount'] >  $user['topup_balance']) {
        //     exit_out(null, 10090, '余额不足');
        // }

        Db::startTrans();
        try {
            $insert['user_id'] = $user['id'];
            $insert['order_sn'] = 'GF'.build_order_sn($user['id']);
            $insert['status'] = 2;
            $insert['ensure'] = $req['id'];
            $insert['amount'] = $data['amount'];
            $insert['receive_amount'] = $data['receive_amount'];
            $insert['process_time'] = $data['process_time'];
            $insert['verify_time'] = $data['verify_time'];
            $insert['next_reward_time'] = strtotime("+{$data['process_time']} day", strtotime(date('Y-m-d')));
            $insert['next_return_time'] = strtotime("+{$data['verify_time']} day", strtotime(date('Y-m-d')));
            $order = EnsureOrder::create($insert);
           // User::changeInc($user['id'],-$data['amount'],'topup_balance',26,$order['id'],1,'',0,1,'BZ');
            //ShopOrder::where('id', $use['id'])->update(['used_ensure' => 1]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out(['order_id' => $order['id'] ?? 0]);
    }

    public function placeOrder_bak()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
            'buy_num' => 'require|number|>:0',
            'pay_method' => 'require|number',
            'payment_config_id' => 'requireIf:pay_method,2|requireIf:pay_method,3|requireIf:pay_method,4|requireIf:pay_method,6|number',
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
            'pay_voucher_img_url|支付凭证' => 'requireIf:pay_method,6|url',
        ]);
        $user = $this->user;

/*         if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        } */
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $project = Project::where('id', $req['project_id'])->where('status',1)->find();
        if(!$project){
            return out(null, 10001, '项目不存在');
        }
        if($project['project_group_id']==2){
            $order = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('project_group_id',1)->find();
            if(!$order){
                return out(null, 10001, '请先购买强国工匠项目');
            }
        }

        if($project['project_group_id']==3){
            $order = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('project_group_id',1)->find();
            $order2 = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('project_group_id',2)->find();
            if(!$order || !$order2){
                return out(null, 10001, '请先购买强国工匠和国富民强项目');
            }
        }
/*         if($req['pay_method']>1){
            $req['pay_method']+=1;
        } */
        if (!in_array($req['pay_method'], $project['support_pay_methods'])) {
            return out(null, 10001, '不支持该支付方式');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::field('id project_id,name project_name,class,project_group_id,cover_img,single_amount,gift_integral,total_num,daily_bonus_ratio,sum_amount,dividend_cycle,period,single_gift_equity,single_gift_digital_yuan,sham_buy_num,progress_switch,bonus_multiple,settlement_method')->where('id', $req['project_id'])->lock(true)->append(['all_total_buy_num'])->find()->toArray();

            $pay_amount = round($project['single_amount']*$req['buy_num'], 2);
            $pay_integral = 0;

            if ($req['pay_method'] == 1 && $pay_amount >  $user['balance']) {
                exit_out(null, 10002, '余额不足');
            }
            if ($req['pay_method'] == 5) {
                $pay_integral = $project['single_amount'] * $req['buy_num'];
                if ($pay_integral > $user['team_bonus_balance']) {
                    exit_out(null, 10003, '团队奖励不足');
                }
            }

            if (in_array($req['pay_method'], [2,3,4,6])) {
                $type = $req['pay_method'] - 1;
                if ($req['pay_method'] == 6) {
                    $type = 4;
                }
                $paymentConf = PaymentConfig::userCanPayChannel($req['payment_config_id'], $type, $pay_amount);
            }

/*             if ($project['progress_switch'] == 1 && ($req['buy_num'] + $project['all_total_buy_num'] > $project['total_num'])) {
                exit_out(null, 10004, '超过了项目最大所需份数');
            } */

            if (isset(config('map.order')['pay_method_map'][$req['pay_method']]) === false) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            if (empty($req['pay_method'])) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            $order_sn = build_order_sn($user['id']);

            // 创建订单
            //if($project['class']==1){
                $project['sum_amount'] = round($project['sum_amount']*$req['buy_num'], 2);
            //}

            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = $req['buy_num'];
            $project['pay_method'] = $req['pay_method'];
            //$project['equity_certificate_no'] = 'ZX'.mt_rand(1000000000, 9999999999);
            //$project['daily_bonus_ratio'] = round($project['daily_bonus_ratio']*$project['bonus_multiple'], 2);
            //$project['monthly_bonus_ratio'] = round($project['monthly_bonus_ratio']*$project['bonus_multiple'], 2);

            //$project['single_gift_equity'] = round($project['single_gift_equity']*$req['buy_num']*$project['bonus_multiple'], 2);
            $project['single_gift_digital_yuan'] = round($project['single_gift_digital_yuan']*$req['buy_num'], 2);
            $project['sum_amount'] = round($project['sum_amount']*$req['buy_num'], 2);
            $project['price'] = $pay_amount;

            $order = Order::create($project);

            if ($req['pay_method']==1) {
                // 扣余额
                User::changeInc($user['id'],-$pay_amount,'balance',3,$order['id'],1);
                // 累计总收益和赠送数字人民币  到期结算
                // 订单支付完成
                Order::orderPayComplete($order['id']);
            }else if($req['pay_method']==5){
                // 扣团队奖励
                User::changeInc($user['id'],-$pay_amount,'team_bonus_balance',3,$order['id'],2);
                // 累计总收益和赠送数字人民币  到期结算
                // 订单支付完成
                Order::orderPayComplete($order['id']);
            }
            // 发起第三方支付
            if (in_array($req['pay_method'], [2,3,4,6])) {
                $card_info = '';
                if (!empty($paymentConf['card_info'])) {
                    $card_info = json_encode($paymentConf['card_info']);
                    if (empty($card_info)) {
                        $card_info = '';
                    }
                }
                // 创建支付记录
                Payment::create([
                    'user_id' => $user['id'],
                    'trade_sn' => $order_sn,
                    'pay_amount' => $pay_amount,
                    'order_id' => $order['id'],
                    'payment_config_id' => $paymentConf['id'],
                    'channel' => $paymentConf['channel'],
                    'mark' => $paymentConf['mark'],
                    'type' => $paymentConf['type'],
                    'card_info' => $card_info,
                    'product_type'=>1,
                    'pay_voucher_img_url'=>$req['pay_voucher_img_url'],
                ]);
                // 发起支付
                if ($paymentConf['channel'] == 1) {
                    $ret = Payment::requestPayment($order_sn, $paymentConf['mark'], $pay_amount);
                }
                elseif ($paymentConf['channel'] == 2) {
                    $ret = Payment::requestPayment2($order_sn, $paymentConf['mark'], $pay_amount);
                }
                elseif ($paymentConf['channel'] == 3) {
                    $ret = Payment::requestPayment3($order_sn, $paymentConf['mark'], $pay_amount);
                }else if($paymentConf['channel']==8){
                    $ret = Payment::requestPayment4($order_sn, $paymentConf['mark'], $pay_amount);
                }else if($paymentConf['channel']==9){
                    $ret = Payment::requestPayment5($order_sn, $paymentConf['mark'], $pay_amount);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['order_id' => $order['id'] ?? 0, 'trade_sn' => $trade_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);
    }
    
    // public function ssss(){
    //     $data = Order::alias('o')->join('mp_project p','o.project_id = p.id')->field('o.*,p.sum_amount as psum,p.single_gift_equity as pequity,p.single_gift_digital_yuan as pyuan,p.daily_bonus_ratio as pratio')->where('o.buy_num','>',1)->where('p.class',2)->select()->toArray();
    //     $a = '';
    //     if(empty($data)){
    //         return '没有执行数据';
    //     }else{
    //         foreach($data as $v){
    //             $up = [];
    //             if(($v['buy_num']*$v['psum']) > $v['sum_amount']){
    //                 $up['sum_amount'] =  $v['buy_num']*$v['psum'];
    //             }
    //             if(($v['buy_num']*$v['pequity']) > $v['single_gift_equity']){
    //                 $up['single_gift_equity'] =  $v['buy_num']*$v['pequity'];
    //             }
    //             if(($v['buy_num']*$v['pyuan']) > $v['single_gift_digital_yuan']){
    //                 $up['single_gift_digital_yuan'] =  $v['buy_num']*$v['pyuan'];
    //             }
    //             if(($v['buy_num']*$v['pratio']) > $v['daily_bonus_ratio']){
    //                 $up['daily_bonus_ratio'] =  $v['buy_num']*$v['pratio'];
    //             }
    //             Order::where('id',$v['id'])->update($up);
    //             $a .= 'id:'.$v['id'].'<br>'.'总补贴:'.$v['buy_num']*$v['psum'].'<br>'.'赠送股权:'.$v['buy_num']*$v['pequity'].'<br>'.'赠送期权:'. $v['buy_num']*$v['pyuan'].'<br>'.'分红:'.$v['buy_num']*$v['pratio'].'_______________<br>';
    //         }
    //     }
    //     return $a;
    // }

    public function submitPayVoucher()
    {
        $req = $this->validate(request(), [
            'pay_voucher_img_url|支付凭证' => 'require|url',
            'order_id' => 'require|number'
        ]);
        $user = $this->user;

        if (!Payment::where('order_id', $req['order_id'])->where('user_id', $user['id'])->count()) {
            return out(null, 10001, '订单不存在');
        }
        $remark = null!=request()->param('remark')?request()->param('remark'):'';
        $upData = [
            'pay_voucher_img_url' => $req['pay_voucher_img_url'],
            'agent_name'=>$remark,
        ];
        Payment::where('order_id', $req['order_id'])->where('user_id', $user['id'])->update($upData);

        return out();
    }

    public function orderList()
    {
/*         $req = $this->validate(request(), [
            'status' => 'number',
            'project_group_id' => 'number',
        ]); */

        $validate =  \think\facade\Validate::rule([
            'status' => 'number', //2收益中 4已完成
            'is_speed_up' => 'number',
            'project_group_id' => 'number',
        ]);
        $req = request()->param();
        if (!$validate->check($req)) {
            return out(null, 10001, $validate->getError());
        }
        $user = $this->user;

        $builder = Order::where('user_id', $user['id'])->where('status', '>', 1);
        
        if (!empty($req['status'])) {
            $builder->where('status', $req['status']);
        }
        if (!empty($req['is_speed_up'])) {
            $builder->where('is_speed_up', $req['is_speed_up']);
        }

        if (!empty($req['project_group_id'])) {
            $builder->where('project_group_id', $req['project_group_id']);
        }


        $orders = $builder->order('id', 'desc')->paginate(30,false,['query'=>request()->param()]);
        $data = [];
        foreach($orders as $order){
            $item['id'] = $order['id'];
            $item['project_name'] = $order['project_name'];
            $item['single_amount'] = $order['single_amount'];
            $item['gift_integral'] = $order['gift_integral'];
            $item['gift_integral'] = $order['gift_integral'];
            $item['sum_amount'] = $order['sum_amount'];
            $item['period'] = $order['period'];
            $item['end_time'] = date('Y-m-d H:i:s',$order['end_time']);
            $item['status'] = $order['status'];
            $item['bonus_multiple'] = $order['bonus_multiple'];
            $item['is_speed_up'] = $order['is_speed_up'];
            $item['project_group_id'] = $order['project_group_id'];
            $item['is_can_speed_up'] = 0; //可以加速
            if($order['project_group_id']==2 && $order['status']==2){
                $item['is_can_speed_up'] = 1;
            }
            $item['prev_time'] = 0;
            if($order['is_speed_up']==1){
                $speedUpLog = UserLotteryLog::where('user_id',$user['id'])->where('relation_id',$order['id'])->where('type',4)->order('id','asc')->find();
                if($speedUpLog){
                    $item['prev_time'] = date('Y-m-d H:i:s',$speedUpLog['amount_before']);
                }
            }
            $data[] = $item;
        }

        return out($data);
    }

    public function orderList_bak()
    {
        $req = $this->validate(request(), [
            'status' => 'number',
            'search_type' => 'number',
        ]);
        $user = $this->user;

        $builder = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('sum_amount',0);
        if (!empty($req['status'])) {
            $builder->where('status', $req['status']);
        }
        if (!empty($req['search_type'])) {
            if ($req['search_type'] == 1) {
                $builder->where('single_gift_equity', '>', 0);
            }
            if ($req['search_type'] == 2) {
                $builder->where('single_gift_digital_yuan', '>', 0);
            }
        }
        $data = $builder->order('id', 'desc')->append(['buy_amount', 'total_bonus', 'equity', 'digital_yuan', 'wait_receive_passive_income', 'total_passive_income', 'pay_date', 'sale_date', 'end_date', 'exchange_equity_date', 'exchange_yuan_date'])->paginate(10,false,['query'=>request()->param()])->each(function($item, $key){
            $item['p_id'] = PassiveIncomeRecord::where('order_id',$item['id'])->order('id','desc')->value('id');
            $cre = intval((time()-strtotime($item['created_at'])) / 60 / 60 / 24);
            if($cre >= 77){
                $item['back_amount'] = 1;
            }else{
                $item['back_amount'] = 0;
            }
            return $item;
        });

        return out($data);
    }

    public function ordersList2()
    {
        $req = $this->validate(request(), [
            'status' => 'number',
            'search_type' => 'number',
        ]);
        $user = $this->user;

        $builder = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('sum_amount','<>',0);
        if (!empty($req['status'])) {
            $builder->where('status', $req['status']);
        }
        if (!empty($req['search_type'])) {
            if ($req['search_type'] == 1) {
                $builder->where('single_gift_equity', '>', 0);
            }
            if ($req['search_type'] == 2) {
                $builder->where('single_gift_digital_yuan', '>', 0);
            }
        }
        $data = $builder->order('id', 'desc')->append(['buy_amount', 'total_bonus', 'equity', 'digital_yuan', 'wait_receive_passive_income', 'total_passive_income', 'pay_date', 'sale_date', 'end_date', 'exchange_equity_date', 'exchange_yuan_date'])->paginate(10,false,['query'=>request()->param()])->each(function($item, $key){
            $item['p_id'] = PassiveIncomeRecord::where('order_id',$item['id'])->order('id','desc')->value('id');
            $cre = intval((time()-strtotime($item['created_at'])) / 60 / 60 / 24);
            if($cre >= 77){
                $item['back_amount'] = 1;//显示反还本金
            }else{
                $item['back_amount'] = 0;//不显示反还本金
            }
            return $item;
        });

        return out($data);
    }

    public function ordersList(){
        $user = $this->user;
        $userModel = new User();
        $data = [];
        $data['profiting_bonus'] = $userModel->getProfitingBonusAttr(0,$user);
        $list = Order::where('user_id', $user['id'])->where('status', '>', 1)->field('id,cover_img,single_amount,buy_num,project_name,sum_amount,sum_amount2,order_sn,daily_bonus_ratio,dividend_cycle,period,created_at')->order('created_at','desc')->paginate(5)->each(function($item,$key){
            if($item['sum_amount']==0 && $item['sum_amount2']>0){
                //$item['sum_amount'] = bcmul($item['daily_bonus_ratio']*config('config.passive_income_days_conf')[$item['period']]/100,2);
                $item['sum_amount'] = $item['sum_amount2'];
            }
            $daily_bonus = bcmul($item['single_amount'],$item['daily_bonus_ratio']/100,2);
           
            $daily_bonus = bcmul($daily_bonus,$item['buy_num'],2);
            $item['price'] = bcmul($item['single_amount'],$item['buy_num'],2);
            if($item['dividend_cycle'] == '1 month'){
                $day_remark = '每月';
            }else{
                $day_remark = '每日';
            }
            
            $item['text'] = "单笔认购{$item['price']}元，{$day_remark}收益{$daily_bonus}元，永久性收益！";

            return $item;
        });
        $data['list'] = $list;
        return out($data);
    }

    public function investmentList(){
        $user = $this->user;
        $list = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('is_gift',0)->field('id,project_id,single_amount,buy_num,project_name,sum_amount,sum_amount2,order_sn,daily_bonus_ratio,period,created_at')->order('created_at','desc')->paginate(20)->each(function($item,$key){
            $bonusMultiple = Project::where('id',$item['project_id'])->value('bonus_multiple');
            $item['bonus_multiple'] = $bonusMultiple;
            $item['price'] = bcmul($item['single_amount'],$item['buy_num'],2);
            $item['text'] = "{$item['price']}元投资{$bonusMultiple}倍{$item['project_name']}";
            

            return $item;
        });
        $data['list'] = $list;
        return out($data);
    }

    public function orderDetail()
    {
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
        ]);
        $user = $this->user;

        $data = Order::where('id', $req['order_id'])->where('user_id', $user['id'])->append(['buy_amount', 'total_bonus', 'equity', 'digital_yuan', 'wait_receive_passive_income', 'total_passive_income', 'pay_date', 'sale_date', 'end_date', 'exchange_equity_date', 'exchange_yuan_date'])->find();
        $data['card_info'] = null;
        if (!empty($data)) {
            $payment = Payment::field('card_info')->where('order_id', $req['order_id'])->find();
            $data['card_info'] = $payment['card_info'];
        }

        return out($data);
    }

    public function saleOrder()
    {
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
        ]);
        $user = $this->user;

        Db::startTrans();
        try {
            $order = Order::where('id', $req['order_id'])->where('user_id', $user['id'])->lock(true)->find();
            if (empty($order)) {
                exit_out(null, 10001, '订单不存在');
            }
            if ($order['status'] != 3) {
                exit_out(null, 10001, '订单状态异常，不能出售');
            }

            Order::where('id', $req['order_id'])->update(['status' => 4, 'sale_time' => time()]);

            User::changeBalance($user['id'], $order['gain_bonus'], 6, $req['order_id']);

            // 检查返回本金 签到累计满77天才会返还80%的本金，注意积分兑换的不返还本金
            if ($order['pay_method'] != 5) {
                $signin_num = UserSignin::where('user_id', $user['id'])->count();
                if ($signin_num >= 77) {
                //if ($signin_num >= 3) {
                    $change_amount = round($order['buy_amount']*0.8, 2);
                    User::changeBalance($user['id'], $change_amount, 12, $req['order_id']);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
    
    public function takeDividend(){
        $user = $this->user;
  
        $isStatus = Order::where('user_id', $user['id'])->where('project_name','2022年年底分红')->find();

        if($isStatus){
            return out(null, 10001, '您已领取2022年年底分红');
        }else{
            $arr = User::where('id', $user['id'])->append(['equity'])->find()->toArray();

            $order_sn = build_order_sn($user['id']);
            // 创建分红订单
            $project['project_name'] = '2022年年底分红';
            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 0;
            $project['pay_method'] = 0;//gain_bonus
            $project['gain_bonus'] = $arr['equity']*10;//
            $project['status'] = 2;//
            $project['equity_certificate_no'] = 'ZX'.mt_rand(1000000000, 9999999999);
            $project['daily_bonus_ratio'] = 0;
            $project['sum_amount'] = 2400;
            $project['single_gift_equity'] = 0;
            $project['single_gift_digital_yuan'] = 0;
            Order::create($project);
        }
        return out();
    }

    public function takeDividendstate(){
        $user = $this->user;
        $isStatus = Order::where('user_id', $user['id'])->where('project_name','2022年年底分红')->find();

        if($isStatus){
            $data['take_status']=0;
        }else{
            $arr = User::where('id', $user['id'])->append(['equity'])->find()->toArray();
            if($arr['equity']<1){
                $data['take_status']=0;
            }else{
                $data['take_status']=1;
            }
        }
        return out($data);
    }

    function convertNumber($number) {
        if ($number >= pow(10, 8)) {
            $result = round($number / pow(10, 8), 2);
            return $result . '亿';
        } elseif ($number >= pow(10, 4)) {
            $result = round($number / pow(10, 4), 2);
            return $result . '万';
        } else {
            return $number;
        }
    }
    
}
