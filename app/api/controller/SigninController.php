<?php

namespace app\api\controller;

use app\model\AssetOrder;
use app\model\ContinuousSignin;
use app\model\Order;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserReceive;
use app\model\UserSignin;
use Exception;
use think\facade\Db;
use think\model\relation\OneToOne;
use think\facade\Request;
use app\model\PovertySubsidy;
use app\model\ShopOrder;

class SigninController extends AuthController
{
    public function userSignin()
    {
        
        //return out(null, 10001, '暂未开放');
        
        
        // 每天签到时间为8：00-20：00 早上8点到晚上21点
/*         $timeNum = (int)date('Hi');
        if ($timeNum < 800 || $timeNum > 2100) {
            return out(null, 10001, '签到时间为早上8:00到晚上21:00');
        } */
        // $arr =config('map.noDomainArr');
        // $host = request()->host();
        // if(in_array($host,$arr)){
        //     return out(null, 10001, '请联系客服下载最新app进行签到');
        // }
        // if(!domainCheck()){
        //     return out(null, 10001, '请联系客服下载最新app进行签到');
        // }
        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        $signin_date = date('Y-m-d');
        $yesterday = date('Y-m-d',strtotime('-1 day'));
/*         if($user['level'] == 0){
            return out(null, 10001, '共富等级一级才有奖励');
        }
        $level_config = \app\model\LevelConfig::where('level', $user['level'])->find();
        if(!$level_config){
            return out(null, 10001, '您的等级有误');
        }
 */
        Db::startTrans();
        try {
            if (UserSignin::where('user_id', $user['id'])->where('signin_date', $signin_date)->lock(true)->count()) {
                return out(null, 10001, '您今天已经签到了');
            }


            // $oneDate = date('Y-m')."-01";
            // $signDates = UserSignin::where('user_id',$user['id'])->where('signin_date','>',$oneDate)->order('signin_date','desc')->select();
            // $signNum=1;
            // $dates = [];
            // foreach($signDates as $date){
            //     $dates[$date['signin_date']] = $date; 
            // }

            // $yesterday = date("Y-m-d");
            // foreach($signDates as $date){
            //    $yesterday = date('Y-m-d',strtotime("-1 day",strtotime($yesterday)));
            //    if(isset($dates[$yesterday])){
            //          $signNum++;
            //    }else{
            //             break;
            //    }   
            // }

            $signin = UserSignin::create([
                'user_id' => $user['id'],
                'signin_date' => $signin_date,
            ]);
            // 添加签到奖励积分
            //User::changeBalance($user['id'], dbconfig('signin_integral'), 17, $signin['id'], 6,'积分-每日签到奖励');

           User::changeInc($user['id'], dbconfig('signin_integral'),'signin_integral',17,$signin['id'],6,'每日签到奖励',0,1,'QD');
           // User::changeInc($user['id'],$level_config['cash_reward_amount'],'signin_balance',17,$signin['id'],3,'等级签到奖励',0,1,'QD');
           
            $signinYesterDay = UserSignin::where('user_id', $user['id'])->where('signin_date', $yesterday)->find();
            if(!$signinYesterDay){
                ContinuousSignin::create([
                    'user_id'=>$user['id'],
                    'start'=>$signin_date,
                    'end'=>$signin_date,
                    'day'=>1,
                ]);
            }else{
                $updata = [
                    'day'=>Db::raw('day+1'),
                    'end'=>$signin_date,
                ];
                $ret = ContinuousSignin::where('user_id',$user['id'])->where('end',$yesterday)->update($updata);
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    /**
     * 首先看恢复资产用户选择的先富后富
     * 没有恢复资产的用户，看是否充值了1500
     */
    public function dayReceive(){
        $user = $this->user;
        $req = $this->validate(request(), [
            'rich|领取类别' => 'number',
        ]);
        $user = User::where('id', $user['id'])->find();
        $signin_date = date('Y-m-d');
        if($user['level'] == 0){
            return out(null, 10001, '共富等级一级才有奖励');
        }
        if(!isset($req['rich'])){
            $req['rich'] =0;
        };

        $is_rich=0;
        $text = '后富每日奖励';
        $assetOrder = AssetOrder::where('user_id',$user['id'])->where('status','>=',2)->find();
        if($assetOrder){
            $is_rich = $assetOrder['rich'] ==1 ? 1 : 0;
        }
        //如果是后富，看是否充值了1500
        if($is_rich==0){
            $orderSum = Order::where('user_id',$user['id'])->where('status','>=',2)->sum('single_amount');
            $assetSum = UserBalanceLog::where('user_id',$user['id'])->where('type',25)->sum('change_balance');
            $shopSum = ShopOrder::where('user_id',$user['id'])->where('status','>=',2)->sum('single_amount');
            $invest_amount = $orderSum + abs($assetSum) + $shopSum;

            if($invest_amount>=1500 || $user['invest_amount']>=1500){
                $is_rich =1;            
            }
        }
        //先富可以领取后富
        if($req['rich']==0 && $is_rich==1){
           // return out(null, 10001, '请领取14元先富奖励');
        }

        if($req['rich']==1 && $is_rich==0){
            return out(null, 10001, '仅限先富领取');
        }
        
        
/*         if($req['rich']==1){
            $orderSum = Order::where('user_id',$user['id'])->where('status','>=',2)->sum('single_amount');
            $assetSum = UserBalanceLog::where('user_id',$user['id'])->where('type',25)->sum('change_balance');
            $invest_amount = $orderSum + abs($assetSum);
            if($invest_amount<1000){
                return out(null, 10001, '投资1000元才能领取先富奖励');            
            }
            $text = '先富每日奖励';
        } */

        if($req['rich']==1){
            $text = '先富每日奖励';
        }

        $field = $req['rich']==1 ? 'first_rich' : 'after_rich';  
        $relationId = 0;
        $amount = $req['rich'] == 1 ? 14 : 1;
        Db::startTrans();
        try {
            $receive = UserReceive::where('user_id', $user['id'])->where('signin_date', $signin_date)->lock(true)->find();
            if($receive){
                if($is_rich==1){
                    if($receive['first_rich']==1 && $receive['after_rich']==1){
                        return out(null, 10001, '您今天已经领取了');
                    }
                    if($receive['first_rich']==1 && $req['rich']==1){
                        return out(null, 10001, '您今天已经领取了');
                    }
                    if($receive['after_rich']==1 && $req['rich']==0){
                        return out(null, 10001, '您今天已经领取了');
                    }
                }
                if($is_rich==0 && $receive['after_rich']==1){
                    return out(null, 10001, '您今天已经领取了');
                }
                UserReceive::where('id',$receive['id'])->update([$field=>1,'amout'=>$receive['amout']+$amount]);
                $relationId = $receive['id'];
            }else{
                 $signin = UserReceive::create([
                    'user_id' => $user['id'],
                    'signin_date' => $signin_date,
                    'amout' => $amount,
                    $field => 1,
                ]);
                $relationId = $signin['id'];
            }
            
            User::changeInc($user['id'],$amount,'signin_balance',17,$relationId,3,$text,0,1,'QD');
            Db::commit();

           
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
            return out(null,200,$e->getMessage());
        }
        return out();
    }

    public function povertySubsidy(){
        $user = $this->user;
        $month = date("m");
        $day = date("d");
        if($day<15 || $day>20){
            return out(null,10001,'请在指定时间内领取');
        }
        if($user['level'] == 0){
            return out(null, 10001, '共富等级一级才有奖励');
        }
        $subsidy = povertySubsidy::where('user_id',$user['id'])->where('month',$month)->find();
        $amountData = [
            0=>0,
            1=>5000,
            2=>10000,
            3=>20000,
            4=>30000,
            5=>50000,
        ];
        if($subsidy){
            return out(null,10001,'您已经领取过了');
        }
/*             $orderSum = Order::where('user_id',$user['id'])->where('status','>=',2)->sum('single_amount');
            $assetSum = UserBalanceLog::where('user_id',$user['id'])->where('type',25)->sum('change_balance');
            $shopSum = ShopOrder::where('user_id',$user['id'])->where('status','>=',2)->sum('single_amount');
 */            

            $invest_amount = $user['invest_amount'];
            
            $level = $user['level'];
            if($invest_amount>=1500){
                $level=5;            
            }

            $data =[
                'user_id'=>$user['id'],
                'month'=>$month,
                'amount'=>$amountData[$level],
                'signin_date'=>date("Y-m-d"),
                'created_at'=>date("Y-m-d H:i:s"),
            ];
            Db::startTrans();
            try{
                $new = povertySubsidy::create($data);
                User::where('id',$user['id'])->inc('poverty_subsidy_amount',$amountData[$level])->update();
                User::changeInc($user['id'],$amountData[$level],'digital_yuan_amount',30,$new['id'],3,'生活补助',0,1,'BZ');
                //User::changeInc($user['id'],$amountData[$level],'digital_yuan_amount',30,$new['id'],3,'生活补助',0,1,'BZ');
                Db::commit();
            }catch(Exception $e){
                Db::rollback();
                //throw $e;
                return out(null,200,$e->getMessage());
            }
        
        return out();

    }

    public function signinRecord()
    {
        $user = $this->user;
        $time = date("Y-m")."-01";

        $list = UserSignin::where('user_id', $user['id'])->where("signin_date",'>=',$time)->order('id', 'desc')->select()->toArray();
        $yesterday = date("Y-m-d",strtotime('-1 day'));
        $continuous = ContinuousSignin::where('user_id',$user['id'])->where('end','>=',$yesterday)->find();
        $continuous_num = !$continuous ? 0 : $continuous['day'];

        foreach ($list as &$item) {
            $item['day'] = date('d', strtotime($item['signin_date']));
        }
        return out([
            'total_signin_num' => count($list),
            'list' => $list,
            'continuous_num'=>$continuous_num,
        ]);
    }
}
