<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\AuthOrder;
use app\model\Capital;
use app\model\CardOrder;
use app\model\EnsureOrder;
use app\model\NotarizationOrder;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\ShopOrder;
use app\model\TaxOrder;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class Task extends Command
{
    protected function configure()
    {
        $this->setName('task')->setDescription('测试脚本');
    }

    public function execute(Input $input, Output $output)
    {
        ini_set ("memory_limit","-1");
        set_time_limit(0);

        $data = UserBalanceLog::where('type', 93)->select();
        Db::startTrans();
        try{
            foreach ($data as $key => $value) {
                if($value['id'] != 198883553) {
                    $a = UserBalanceLog::where('user_id', $value['user_id'])->where('type', 37)->where('log_type', 6)->where('relation_id', $value['relation_id'])->find();
                    if($a) {
                        User::changeInc($value['user_id'],-$a['change_balance'],'assessment_amount',93,$value['relation_id'],1, '共富专属卡已纳税金额退还');
                        UserBalanceLog::where('id', $value['id'])->delete();
                    }
                }
            }
            Db::commit();
        }catch(Exception $e){
            Db::rollback();
            throw $e;
        }
        exit;
        // $data = ShopOrder::where('pay_time','<=',1718186424)->where('pay_time','>=',1718035200)->select();
        // $fields = [0=>'gf_purse',1=>'team_bonus_balance',2=>'signin_balance',3=>'topup_balance'];
        // $texts = [0=>'共富钱包',1=>'团队奖励',2=>'签到余额',3=>'充值余额'];
        // foreach($data as $item){
        //     $logs = UserBalanceLog::where('relation_id',$item['id'])->whereIn('type',[3,35])->whereLike('remark', "%商城%")->order('id','desc')->select();
        //     if(count($logs)>=4){
        //         Db::startTrans();
        //         try{
        //             foreach ($logs as $k=>$log){

        //                 $amount = abs($log['change_balance']);
        //                 if($amount==0){
        //                     continue;
        //                 }
        //                 $field = $fields[$k];
        //                 $text = $texts[$k];
        //                 echo "正在处理订单{$item['id']} 商城 $field $text {$amount}\n";
        //                User::changeInc($item['user_id'],$amount,$field,97,$item['id'],1,"商城{$text}退还");
        //             }
        //             ShopOrder::where('id',$item['id'])->where('user_id',$item['user_id'])->delete();
        //             User::changeInc($item['user_id'],$item['flow_amount'],'flow_amount',96,$item['id'],7, '商城订单退还流转金额');
        //             Db::commit();
        //         }catch(Exception $e){
        //             Db::rollback();
        //             Log::error('商城'.$item['id'].'退还异常：'.$e->getMessage(),['e'=>$e]);
        //             throw $e;
        //         }
        //     }else{
        //         echo "订单{$item['id']} 不用退还\n";
        //     }
        // }
        // exit;

        // $data = TaxOrder::where('id','<=',247723)->where('id','>=',244067)->select();
        // $fields = [0=>'gf_purse',1=>'team_bonus_balance',2=>'signin_balance',3=>'topup_balance'];
        // $texts = [0=>'共富钱包',1=>'团队奖励',2=>'签到余额',3=>'充值余额'];
        // foreach($data as $item){
        //     $logs = UserBalanceLog::where('relation_id',$item['id'])->whereIn('type',[3,35])->where('remark', "税务抵用劵")->order('id','desc')->select();
        //     if(count($logs)>=4){
        //         Db::startTrans();
        //         try{
        //             foreach ($logs as $k=>$log){

        //                 $amount = abs($log['change_balance']);
        //                 if($amount==0){
        //                     continue;
        //                 }
        //                 $field = $fields[$k];
        //                 $text = $texts[$k];
        //                 echo "正在处理订单{$item['id']} 税务抵用券 $field $text {$amount}\n";
        //                User::changeInc($item['user_id'],$amount,$field,96,$item['id'],1,"税务抵用券{$text}退还");
        //             }
        //             TaxOrder::where('id',$item['id'])->where('user_id',$item['user_id'])->delete();
        //             $cc = UserBalanceLog::where('user_id', $item['user_id'])->where('relation_id',$item['id'])->whereIn('type',36)->where('log_type', 6)->find();
        //             $aa = abs($cc['change_balance']);
        //             if($aa != 0) {
        //                 User::changeInc($item['user_id'],$aa,'digit_balance',95,$item['id'],6);
        //                 User::changeInc($item['user_id'],-$aa,'assessment_amount',95,$item['id'],6);
        //                 User::where('id', $item['user_id'])->update(['assessment_time' => 0]);
        //             }
        //             Db::commit();
        //         }catch(Exception $e){
        //             Db::rollback();
        //             Log::error('商城'.$item['id'].'退还异常：'.$e->getMessage(),['e'=>$e]);
        //             throw $e;
        //         }
        //     }else{
        //         echo "订单{$item['id']} 不用退还\n";
        //     }
        // }
        // exit;

        // $data = CardOrder::where('created_at','<=','2024-06-12 18:31:00')->where('created_at','>=','2024-06-11 00:00:00')->select();
        // $fields = [0=>'gf_purse',1=>'team_bonus_balance',2=>'signin_balance',3=>'topup_balance',4=>'gf_purse',5=>'team_bonus_balance',6=>'signin_balance',7=>'topup_balance',8=>'gf_purse',9=>'team_bonus_balance',10=>'signin_balance',11=>'topup_balance'];
        // $texts = [0=>'共富钱包',1=>'团队奖励',2=>'签到余额',3=>'充值余额',4=>'共富钱包',5=>'团队奖励',6=>'签到余额',7=>'充值余额',8=>'共富钱包',9=>'团队奖励',10=>'签到余额',11=>'充值余额'];
        // foreach($data as $item){
        //     $logs = UserBalanceLog::where('relation_id',$item['id'])->where('type',37)->where('log_type',1)->order('id','desc')->select();
        //     if(count($logs)==4 || count($logs)==8){
        //         Db::startTrans();
        //         try{
        //             foreach ($logs as $k=>$log){

        //                 $amount = abs($log['change_balance']);
        //                 if($amount==0){
        //                     continue;
        //                 }
        //                 $field = $fields[$k];
        //                 $text = $texts[$k];
        //                 echo "正在处理订单{$item['id']} 共富专属卡 $field $text {$amount}\n";
        //                 User::changeInc($item['user_id'],$amount,$field,94,$item['id'],1,"共富专属卡{$text}退还");
        //             }
        //            CardOrder::where('id',$item['id'])->where('user_id',$item['user_id'])->delete();
        //            User::changeInc($item['user_id'],-$item['single_amount'],'assessment_amount',93,$item['id'],1, '共富专属卡已纳税金额退还');
        //            Db::commit();
        //         }catch(Exception $e){
        //             Db::rollback();
        //             Log::error('商城'.$item['id'].'退还异常：'.$e->getMessage(),['e'=>$e]);
        //             throw $e;
        //         }
        //     }else{
        //         $bb = count($logs);
        //         echo "订单{$item['id']} 不用退还{$bb}\n";
        //     }
        // }
        // exit;
        // $data = Order::field('user_id')->where('created_at', '<', '2024-06-06')->where('project_group_id', 5)->where('updated_at', '>', '2024-06-06 08:54:00')
        //     ->where('updated_at', '<', '2024-06-06 09:06:00')->group('user_id')->select();
        // var_dump(count($data));
        // foreach ($data as $key => $value) {
        //     $a = UserBalanceLog::where('user_id', $value['user_id'])->where('type', 18)->where('created_at', '>', '2024-06-06 08:00:00')->whereLike('remark', '%共富工程钱包余额%')->find();
        //     if($a) {
        //         file_put_contents('负数.txt', $value['user_id'].PHP_EOL, FILE_APPEND);
        //     }
            
        // }
        // $data = Order::where('project_group_id', 5)->where('created_at', '>', '2024-06-06 08:00:42')->select();
        // foreach ($data as $key => $value) {
        //     $a = UserBalanceLog::where('user_id', $value['user_id'])->where('type', 3)->where('relation_id', $value['id'])->find();
        //     if(!$a) {
        //         file_put_contents('lou.txt', $value['id'].PHP_EOL, FILE_APPEND);
        //     }
            
        // }
        // exit;
        // $data = Order::whereIn('project_group_id',[5])->where('status',4)->select();
        // foreach ($data as $key => $value) {
        //     //User::changeInc($value['user_id'],-$value['sum_amount'],'gf_purse',39,$order['id'],9,$order['project_name'].'持有到期收益');
        //     UserBalanceLog::where('user_id', $value['user_id'])->where('type', 39)->where('log_type', 9)->where('change_balance', $value['sum_amount'])->where('remark', $value['project_name'].'持有到期收益')->delete();
        //     Order::where('id',$value['id'])->update(['status'=>2, 'end_time' => 1718035200]);
        //     User::where('id',$value['user_id'])->inc('gf_purse',-$value['sum_amount'])->update();
        // }


        // exit;

        // $data = UserRelation::rankList('yesterday');
        // Db::startTrans();
        
            
        //     try{
        //         foreach($data as $item){
        //             file_put_contents('3.txt', $item['phone'].'--'.$item['realname'].PHP_EOL, FILE_APPEND);
        //         }
        //         Db::commit();
        //     }catch(Exception $e){
        //         Db::rollback();
        //         Log::error('团队排名奖励异常：'.$e->getMessage(),$e);
        //         throw $e;
        //     }
        


        $count = AuthOrder::field('user_id')->group('user_id')->select();
        var_dump(count($count));
       exit;

        //$a = UserBalanceLog::where('type', 6)->where('log_type', 3)->where('created_at', '>=', '2024-04-02 12:58:54')->select();
        
       // $a = Order::whereIn('project_group_id',[1])->where('pay_time', '>', 1711900800)->select();

        //$order = Order::whereIn('project_group_id',[1])->select();
        //$a = User::where('status', 1)->where('digital_yuan_amount', '<', 0)->select();
       // foreach ($a as $key => $value) {
            // Db::startTrans();
            // try{
            //     $b = UserBalanceLog::where('user_id', $value['user_id'])->where('type', 6)->where('log_type', 3)->where('relation_id', $value['id'])->find();
            //     if(!$b) {
            //         Order::where('id', $value['id'])->update(['review_status' => 2, 'next_bonus_time'=>$value['pay_time'] + 86400]);
            //     }
                // if($order['user_id'] != 747969) {
                //     User::changeInc($order['user_id'],$order['sum_amount'],'digital_yuan_amount',6,$order['id'],3);
                // }
                // // $end_time = strtotime("+{$value['period']} day", strtotime(date('Y-m-d', $value['pay_time'])));
                // // Order::where('id', $value['id'])->update([
                // //     'end_time' => $end_time,    //$next_bonus_time + $order['period']*24*3600,
                // //     'next_bonus_time' => $end_time,
                // // ]);
                // $count = UserBalanceLog::where('user_id', $value['user_id'])->where('relation_id', $value['id'])->where('type', 6)->where('log_type', 3)->count();
                // UserBalanceLog::where('user_id', $value['user_id'])->where('relation_id', $value['id'])->where('type', 6)->where('log_type', 3)->delete();
                // $amount = $value['sum_amount'] * $count;
                //User::where('id', $value['user_id'])->inc('digital_yuan_amount',-$value['change_balance'])->update();
               // $b = UserBalanceLog::where('user_id', $value['id'])->where('log_type', 3)->where('remark', '每日签到奖励')->order('id', 'desc')->find();
                // if($b) {
                //     if($value['digital_yuan_amount'] < $b['after_balance']) {
                //         User::where('id', $value['id'])->update(['digital_yuan_amount' => $b['after_balance']]);
                //     }
                // }
               // if($value['digital_yuan_amount'] != $b['after_balance']) {

              //  }
                // $log = UserBalanceLog::where('user_id', $value['user_id'])->where('log_type', 3)->where('remark', '每日签到奖励')->order('id', 'desc')->find();
                // if($log) {
                //     $b = User::where('id', $value['user_id'])->find();
                //     if($b['digital_yuan_amount'] != $log['after_balance']) {
                //         if($value['change_balance'] > $b['digital_yuan_amount']) {
                //             User::where('id', $value['user_id'])->inc('digital_yuan_amount',-$value['change_balance'])->update();
                //         }
                        
                //     }
                //     UserBalanceLog::where('id', $value['id'])->delete();
                // }

                
                
            //     Db::Commit();
            // }catch(Exception $e){
            //     Db::rollback();
                
            //     Log::error('分红收益异常：'.$e->getMessage(),$e);
            //     throw $e;
            // }

            
        // }
        // $order = User::where('status', 1)->select();
        
        // foreach ($order as $key => $value) {
        //     User::upLevel($value['id']);
        //     var_dump($value['id']);
        //     // Db::startTrans();
        //     // try {
        //     //     // $count = ShopOrder::where('user_id', $value['user_id'])->count();
        //     //     // if($count > 1) {
        //     //     //     $shop = ShopOrder::where('user_id', $value['user_id'])->order('id', 'desc')->find();
        //     //     //     User::changeInc($shop['user_id'],$shop['single_amount'],'digital_yuan_amount',34,$value['id'],3);
        //     //     //     ShopOrder::where('id', $shop['id'])->delete();
        //     //     // } else {
        //     //     //     User::changeInc($value['user_id'],-$value['flow_amount'],'flow_amount',33,$value['id'],7);
        //     //     // }
                
        //     //     Db::Commit();
        //     // } catch (\Exception $e) {
        //     //     Db::rollback();
        //     //     throw $e;
        //     // }
            
        // }

       // $res = Db::query("select user_id,count(user_id) ct from mp_user_relation group by user_id HAVING ct >=100");
        // $res = User::where('status', 1)->where('is_active', 1)->select();
        // foreach ($res as $key => $value) {
        //     //$user = User::where('id', $value['user_id'])->find();
        //     $name = $value['realname'] ? $value['realname'] : '未实名';
        //     file_put_contents('1.txt', $name.'-'.$value['phone'].PHP_EOL, FILE_APPEND);
        // }

        // $list = UserRelation::where('user_id', 2090673)->select();
        // $data = [];
        // foreach ($list as $key => $value) {
        //     // $cap = Capital::where('user_id', $value['sub_user_id'])->where('type', 2)->where('status', 2)->order('id','desc')->select();
        //     // foreach ($cap as $k => $v) {
        //     //     $v->account_type = $v['user']['phone'] ?? '';
        //     //     $v->amountCapital = round(0 - $v['amount'], 2);
        //     //     if ($v->pay_channel == 4) {
        //     //         $v->payMethod = '银行：' . $v['bank_name'] ?? '';
        //     //         $v->payMethod .= "\n" . '卡号：' . $v['account'] ?? '';
        //     //         $v->payMethod .= "\n" . '分行：' . $v['bank_branch'] ?? '';
        //     //     } else {
        //     //         $v->payMethod = $v['account'];
        //     //     }
        //     //     $v->shenheUser = $v['adminUser']['nickname'] ?? '';
        //     //     $data[] = $v;
        //     // }
        //     $data[] = $value['sub_user_id'];
        //     User::where('id', $value['sub_user_id'])->update(['status' => 0]);


        // }
        //$data = [0 => 2090673];
        // $data = [0 => 2093926];
        // $zz = $this->aaa(2093926, $data);
        // //$zz = $this->aaa(2090673, $data);
        // $list = array_unique($zz);

        // $ss = [];
        // foreach ($list as $key => $value) {
        //     $pp = User::where('id', $value)->find();
        //     $ss[] = ['id' => $value, 'phone' => $pp['phone']];
        // }

       // var_dump($zz);
        // 提取需要去重的字段
        // $uniqueIds = array_column(array_unique($zz, SORT_REGULAR), 'id');
        
        // // 使用array_filter重新组装数组
        // $list = array_intersect_key($zz, array_flip($uniqueIds));
        // var_dump($list);

        // $list = Capital::whereIn('user_id',$zz)->where('type', 2)->where('status', 2)->order('id','desc')->select();
        // foreach ($list as $v) {
        //     $v->account_type = $v['user']['phone'] ?? '';
        //     $v->amountCapital = round(0 - $v['amount'], 2);
        //     if ($v->pay_channel == 4) {
        //         $v->payMethod = '银行：' . $v['bank_name'] ?? '';
        //         $v->payMethod .= "\n" . '卡号：' . $v['account'] ?? '';
        //         $v->payMethod .= "\n" . '分行：' . $v['bank_branch'] ?? '';
        //     } else {
        //         $v->payMethod = $v['account'];
        //     }
        //     $v->shenheUser = $v['adminUser']['nickname'] ?? '';
        // }
        // create_excel_file($ss, [
        //     'id' => '序号',
        //     'phone' => '手机号',
        //     // // 'capital_sn' => '单号',
        //     // // 'withdraw_status_text' => '状态',
        //     // // 'pay_channel_text' => '支付渠道',
        //     // 'amountCapital' => '提现金额',
        //     // 'withdraw_amount' => '到账金额',
        //     // 'realname' => '收款人实名',
        //     // 'payMethod' => '收款账号',
        //     // // 'shenheUser' => '审核用户',
        //     // // 'audit_remark' => '拒绝理由',
        //     // // 'audit_date' => '审核时间',
        //     // 'created_at' => '创建时间'
        // ], '记录-' . date('YmdHis'));

        

        
        
    }

    // public function aaa($user_id, &$data) 
    // {
    //     $list = UserRelation::where('user_id', $user_id)->select();
    //     foreach ($list as $key => $value) {
    //        // $pp = User::where('id', $value['sub_user_id'])->find();
    //         $data[] = $value['sub_user_id'];
    //         User::where('id', $value['sub_user_id'])->update(['status' => 0]);
    //         $c = UserRelation::where('user_id', $value['sub_user_id'])->select();
    //         if($c) {
    //             //file_put_contents('1.txt', $value['sub_user_id'].PHP_EOL, FILE_APPEND);
    //             $this->aaa($value['sub_user_id'], $data);
    //         }
    //     }
    //     return $data;
    // }

    


}
