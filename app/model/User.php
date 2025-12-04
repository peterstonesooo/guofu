<?php

namespace app\model;

use Exception;
use think\Model;
use think\facade\Db;

use app\model\UserLottery;
use app\model\UserLotteryLog;
class User extends Model
{
    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.user')['status_map'];
        return $map[$data['status']];
    }

    public function getLevelTextAttr($value, $data)
    {
        $map = config('map.user')['level_map'];
        return $map[$data['level']];
    }

    public function getIsActiveTextAttr($value, $data)
    {
        $map = config('map.user')['is_active_map'];
        return $map[$data['is_active']];
    }

    public function getActiveDateAttr($value, $data)
    {
        if (!empty($data['active_time'])) {
            return date('Y-m-d H:i:s', $data['active_time']);
        }
        return '';
    }



    public function upUser()
    {
        return $this->belongsTo(User::class, 'up_user_id');
    }

    // 持有股权
    public function getEquityAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(EquityYuanRecord::where('user_id', $data['id'])->where('status', 2)->where('type', 1)->sum('num'), 2);
        }
        return 0;
    }

    //推荐奖励总计
    public function getInviteBonus($value,$data){
        if (!empty($data['id'])) {
           // return round(UserBalanceLog::where('user_id', $data['id'])->where('type', 9)->sum('change_balance'), 2);
           return $data['invite_bonus'];
        }
        return 0;
    }

    // Removed unused wallet-related getters

    // 团队人数
    public function getTeamUserNumAttr($value, $data)
    {
        return UserRelation::where('user_id', $data['id'])->count();
    }

    // 团队业绩
    public function getTeamPerformanceAttr($value, $data)
    {
    	$sub_user_ids = UserRelation::where('user_id', $data['id'])->column('sub_user_id');

        if (!empty($sub_user_ids)) {
            //$sql = "select sum(invest_amount) as invest_amount from mp_user where  id in (".join(',', $sub_user_ids).")";
            $sql = "select sum(a.invest_amount)as invest_amount from mp_user as a inner join mp_user_relation as b on a.id=b.sub_user_id where b.user_id=".$data['id'];
            $users = Db::query($sql);
            return round($users[0]['invest_amount'], 2);
        }
        return 0;
    }
// 团队业绩
//    public function getTeamPerformanceAttr($value, $data)
//    {
//        $sub_user_ids = UserRelation::where('user_id', $data['id'])->column('sub_user_id');

//        if (!empty($sub_user_ids)) {
//            $sql = "select sum(a.invest_amount)as invest_amount from mp_user as a inner join mp_user_relation as b on a.id=b.sub_user_id where b.user_id=".$data['id'];
//            $users = Db::query($sql);
//            return round($users[0]['invest_amount'], 2);
//        }
//        return 0;
//	}
    // 用户最大连续签到天数
    public function getMaxContinuitySigninNumAttr($value, $data)
    {
        $signin_dates = UserSignin::where('user_id', $data['id'])->order('id', 'asc')->column('signin_date');
        if (!empty($signin_dates)) {
            return continue_days($signin_dates);
        }
        return 0;
    }

    // 用户充值总金额
    public function getTotalTopupAmountAttr($value, $data)
    {
        return round(Capital::where('user_id', $data['id'])->where('status', 2)->where('type', 1)->sum('amount'), 2);
    }

    // 用户总入金金额
    public function getTotalDepositAmountAttr($value, $data)
    {
        return round(UserBalanceLog::where('user_id', $data['id'])->where('log_type', 1)->whereIn('type', [1,14])->sum('change_balance'), 2);
    }

    // 用户支付总金额 包括后台入金
    public function getTotalPaymentAmountAttr($value, $data)
    {
        $amount1 = Payment::where('user_id', $data['id'])->where('status', 2)->sum('pay_amount');
        $amount2 = UserBalanceLog::where('user_id', $data['id'])->where('log_type', 1)->where('type', 14)->sum('change_balance');
        return round($amount1 + $amount2, 2);
    }

    // 用户可提现余额
    public function getCanWithdrawBalanceAttr($value, $data)
    {
        return round($data['balance'] - $data['topup_balance'], 2);
    }

    // 获取直属下级已实名的人数
    public function getRealSubUserNumAttr($value, $data)
    {
        return User::where('up_user_id', $data['id'])->where('ic_number', '<>', '')->count();
    }

    //通过token获取用户信息
    public static function getUserByToken($is_exit = true)
    {
        if ($token = request()->header('token')) {
            $arr = aes_decrypt($token);
            if (!empty($arr['id'])) {
                $user = User::where('id', $arr['id'])->find();
                if (empty($user)){
                    exit_out(null, 601, '账号不存在');
                }
                if ($user['status'] == 0){
                    exit_out(null, 602, '账号已被冻结');
                }

                return $user;
            }
        }

        if ($is_exit) {
            exit_out(null, 401, '认证失效，请重新登录');
        }

        return [];
    }

    // 改变用户余额或积分,加$change_balance为正数 ，减$change_balance为负数
    public static function changeBalance($user_id, $change_balance, $type, $relation_id = 0, $log_type = 1, $remark = '', $admin_user_id = 0)
    {
        if (!empty($change_balance) && $change_balance != 0) {
            $user = User::where('id', $user_id)->find();

            //$field = $log_type == 1 ? 'balance' : 'integral';
            if ($log_type == 1){
                if($type == 3){
                    //购买商品
                    $field = 'topup_balance';
                }else{
                    $field = 'balance';
                }
                
            }elseif($log_type == 2){
                $field = 'integral';
            }elseif($log_type == 3){
                $field = 'team_bonus_balance';
            }elseif($log_type == 14){
                $field = 'meeting_wallet';
            }else{
                $field = 'balance';
            }
            if($type == 3 && $log_type != 3){
                $before_balance = $user['topup_balance'] + $user['balance'];
                $after_balance = $before_balance + $change_balance;
                if ($after_balance < 0) {
                    $after_balance = 0;
                    $change_balance = 0 - $before_balance;
                }
            }else{
                $before_balance = $user[$field];
                $after_balance = $user[$field] + $change_balance;
                if ($after_balance < 0) {
                    $after_balance = 0;
                    $change_balance = 0 - $user[$field];
                }
            }

            // 如果是充值 就要添加充值金额
            if ($log_type == 1 && in_array($type, [1, 14])) {
                //User::where('id', $user_id)->inc($field, $change_balance)->inc('topup_balance', $change_balance)->update();
                User::where('id', $user_id)->inc('topup_balance', $change_balance)->update();
            }
            // 如果是购买项目，优先扣充值余额里面的钱
            elseif ($log_type == 1 && $type == 3) {
                $real_balance = 0 - $change_balance;
                $change_topup_balance = $user['topup_balance'] > $real_balance ? $real_balance : $user['topup_balance'];
                $change_topup_balance = 0 - $change_topup_balance;
                $sub_balance = $user['topup_balance'] < $real_balance ? ($change_balance + $user['topup_balance']) : 0;
                User::where('id', $user_id)->inc('balance', $sub_balance)->inc('topup_balance', $change_topup_balance)->update();
            }
            // 手动出金 先扣可提现金额，再扣充值金额
            elseif ($log_type == 1 && $type == 16) {
                $real_balance = 0 - $change_balance;
                if ($real_balance > $user['can_withdraw_balance']) {
                    $real_topup_balance = $real_balance - $user['can_withdraw_balance'];
                    $real_topup_balance = 0 - $real_topup_balance;
                    User::where('id', $user_id)->inc($field, $change_balance)->inc('topup_balance', $real_topup_balance)->update();
                }
                else {
                    User::where('id', $user_id)->inc($field, $change_balance)->update();
                }
            }
            else {
                User::where('id', $user_id)->inc($field, $change_balance)->update();
            }

            $status = $type == 2 ? 1 : 2;
            if ($type == 13) {
                $status = 3;
            }
            UserBalanceLog::create([
                'user_id' => $user_id,
                'type' => $type,
                'log_type' => $log_type,
                'relation_id' => $relation_id,
                'before_balance' => $before_balance,
                'change_balance' => $change_balance,
                'after_balance' => $after_balance,
                'remark' => $remark,
                'admin_user_id' => $admin_user_id,
                'status' => $status,
            ]);

            // 判断用户升级逻辑
           //shanchu
        }

        return true;
    }

    public static function changeInc($user_id,$amount,$field,$type,$relation_id = 0,$log_type = 1, $remark = '',$admin_user_id=0,$status=1,$sn_prefix='MR'){
        $user = User::where('id', $user_id)->find();
        $after_balance = $user[$field]+$amount;
        if($amount<0 && $user[$field]<abs($amount)){
            throw new Exception('余额不足');
        }

        
        Db::startTrans();
        try {
            $ret = User::where('id',$user_id)->inc($field,$amount)->update();
            $sn = build_order_sn($user_id,$sn_prefix);
            if($user[$field] >0) {
                UserBalanceLog::create([
                    'user_id' => $user_id,
                    'type' => $type,
                    'log_type' => $log_type,
                    'relation_id' => $relation_id,
                    'before_balance' => $user[$field],
                    'change_balance' => $amount,
                    'after_balance' => $after_balance,
                    'remark' => $remark,
                    'admin_user_id' =>$admin_user_id,
                    'status' => $status,
                    'order_sn'=>$sn,
                ]);
            } else {
                UserBalanceLog::create([
                    'user_id' => $user_id,
                    'type' => $type,
                    'log_type' => $log_type,
                    'relation_id' => $relation_id,
                    'change_balance' => $amount,
                    'after_balance' => $after_balance,
                    'remark' => $remark,
                    'admin_user_id' =>$admin_user_id,
                    'status' => $status,
                    'order_sn'=>$sn,
                ]);
            }

            Db::commit();
            return 'success';
        }catch(\Exception $e){
            Db::rollback();
            //return $e->getMessage();
            throw $e;
        }
    }

    public static function changelottery($user_id,$amount,$log_type = 1,$admin_id=0){
        $user = UserLottery::where('user_id', $user_id)->find();
        $after_balance = $user['lottery_num']+$amount;
        if($amount<0 && $user['lottery_num']<abs($amount)){
            throw new Exception('余额不足');
        }

        
        Db::startTrans();
        try {
            $ret = UserLottery::where('user_id',$user_id)->inc('lottery_num',$amount)->update();
            if($user['lottery_num'] >0) {
                UserLotteryLog::create([
                    'user_id' => $user_id,
                    'type' => 4,
                    'log_type' => $log_type,
                    'relation_id' => 0,
                    'amount_before' => $user['lottery_num'],
                    'amount' => $amount,
                    'amount_after' => $after_balance,
                    'admin_id' => $admin_id,
                ]);
            } else {
                UserLotteryLog::create([
                    'user_id' => $user_id,
                    'type' => 4,
                    'log_type' => $log_type,
                    'relation_id' => 0,
                    'amount_before' => $user['lottery_num'],
                    'amount' => $amount,
                    'amount_after' => $after_balance,
                    'admin_id' => $admin_id,

                ]);
            }

            Db::commit();
            return 'success';
        }catch(\Exception $e){
            Db::rollback();
            //return $e->getMessage();
            throw $e;
        }
    }

    // 获取用户的上3级的用户id
    public static function getThreeUpUserId($user_id)
    {
        $level = [];
        $user = User::field('id,up_user_id')->where('id', $user_id)->find();
        if (!empty($user['up_user_id'])) {
            $upUser1 = User::field('id,up_user_id')->where('id', $user['up_user_id'])->find();
            if (!empty($upUser1)) {
                $level[1] = $upUser1['id'];
                if (!empty($upUser1['up_user_id'])) {
                    $upUser2 = User::field('id,up_user_id')->where('id', $upUser1['up_user_id'])->find();
                    if (!empty($upUser2)) {
                        $level[2] = $upUser2['id'];
                        if (!empty($upUser2['up_user_id'])) {
                            $level[3] = $upUser2['up_user_id'];
                        }
                    }
                }
            }
        }

        return $level;
    }

    public function teamBonus($user_id,$amount,$paymentId){
        //总充值金额
        //if ($order['pay_method'] != 5) {
        //} 
        $user = User::where('id',$user_id)->field('id,up_user_id,invest_amount,level')->find();
        if(empty($user)){
            throw new Exception('用户不存在');
        }

        $upUser = User::where('id',$user['up_user_id'])->field('level')->find();
        if(empty($upUser)){
            return false;
        }
               // 如果不是积分兑换才算直推奖励和团队奖励
               //if ($order['pay_method'] != 5 && !empty($up_user_id)) {
                // 给直属上级推荐奖
                //$levelConfig = LevelConfig::where('level', $upUser['level'])->find();
/*                 if (!empty($levelConfig['direct_recommend_reward_ratio'])) {
                    $reward = round($levelConfig['direct_recommend_reward_ratio']/100*$amount, 2);
                    if($reward > 0){
                        //User::changeBalance($up_user_id, $reward, 9, $order_id);
                        User::changeInc($user['up_user_id'],$reward,'team_bonus_balance',9,$paymentId,2,'推荐奖励',0,2);
                        //User::changeInc($up_user_id,$reward,'balance',9,$order_id,1,'推荐奖励',0,2);
                    }
                } */
                // 给上3级团队奖
                $relation = UserRelation::where('sub_user_id', $user_id)->select();
                $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
                //$map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', ];
                foreach ($relation as $v) {
                    $reward = round(dbconfig($map[$v['level']])/100*$amount, 2);
                    if($reward > 0){
                        //User::changeBalance($v['user_id'], $reward, 8, $order_id);
                        //User::changeInc($up_user_id,$reward,'invite_bonus',8,$order_id,3,'推荐奖励');
                        User::changeInc($v['user_id'],$reward,'team_bonus_balance',8,$paymentId,3,'团队奖励',0,2,'TD');
                        //User::changeInc($v['user_id'],$reward,'balance',8,$order_id,1,'团队奖励',0,2);
                    }
                }
            //}
    
            // 检测用户升级
            //if (in_array($order['pay_method'], [1,2,3,4,6])) {
            //if (in_array($order['pay_method'], [2, 3, 4, 6])) {
                //$user = User::where('id', $order['user_id'])->find();

            //}
    }

    public static function upLevel($user_id){
                // 检测用户升级
            //User::where('id', $user_id)->inc('invest_amount', $amount)->update();
/*             $orderSum = Order::where('user_id',$user_id)->where('status','>=',2)->sum('single_amount');
            $assetOrderSum = UserBalanceLog::where('user_id',$user_id)->where('type',25)->sum('change_balance');
 */         $user = User::where('id',$user_id)->field('invest_amount')->find();

            $new_level = LevelConfig::where('min_topup_amount', '<=', intval($user['invest_amount']))->order('min_topup_amount', 'desc')->value('level');
            $user = User::where('id',$user_id)->field('level')->find();
            if ($user['level'] < $new_level) {
                User::where('id',$user_id)->update(['level' => $new_level]);
            }
    }

    public static function isThreeStage($userId){
        $order1 = Order::where('user_id',$userId)->where('status','>=',2)->where('project_group_id',1)->find();
        if(!$order1){
            return 0;
        }
        $order2 = Order::where('user_id',$userId)->where('status','>=',2)->where('project_group_id',2)->find();
        if(!$order2){
            return 0;
        }
        $order3 = Order::where('user_id',$userId)->where('status','>=',2)->where('project_group_id',3)->find();
        if(!$order3){
            return 0;
        }
        return 1;
    }

    public static function myHouse($userId){
        $apply = Apply::where('user_id',$userId)->where('type',2)->find();
        if(!$apply){
            return ['msg'=>'请先预约看房'];
        }
        $order = Order::where('user_id',$userId)->where('project_group_id',3)->where('status','>=',2)->select();
        $priceMax = [];
        foreach($order as $item){
            if($priceMax==[]){
                $priceMax = $item;
            }
            if($priceMax && $item['single_amount']>$priceMax['single_amount']){
                $priceMax = $item;
            }
        }
        if(empty($priceMax)){
            return ['msg'=>'请先购买房产'];
        }
        return ['msg'=>'','house'=>$priceMax];
    }

    public static function isCardOrder($userId){
        $order = Order::where('user_id',$userId)->where('project_group_id',5)->where('status','>=',2)->find();
        if(!$order){
            return 0;
        }
        return 1;
    }

    public static function cardWithdrawSum($userId){
        $user = User::where('id',$userId)->find();
        $alreayCount = Capital::where('user_id',$userId)->where('type',2)->whereIn('log_type',[3,6])->sum('amount');
        $sum = abs($alreayCount)+$user['digital_yuan_amount']+$user['income_balance'];
        return $sum;
    }

    public static function cardRecommend($sum){
        $conf = config('map.project.project_card');
        foreach($conf as $k=>$v){
            if($sum>=$v['min'] && $sum<$v['max']){
                return $k;
            }
        }
    }

    public static function walletSn($userId){
        $sn = '';
        $wallet = Db::table('mp_wallet')->where('user_id',$userId)->find();
        if(!$wallet){
            $sn = self::generateRandomNumber($userId);
            Db::table('mp_wallet')->insert(['user_id'=>$userId,'sn'=>$sn]);
        }
        $sn = $wallet['sn'];
        $arr = explode(' ',$sn);
        //$arr[1] = $arr[2] = "****";
        $sn1 = implode(' ',$arr);
        return $sn1;

    }

    public static function generateRandomNumber($userId) {
        $part1 = '0011';
        $part2 = substr(strrev((string)$userId), 0, 4);
        //$part2 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $part3 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $part4 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
        $randomNumber = $part1 . ' ' . $part2 . ' ' . $part3 . ' ' . $part4;
    
        return $randomNumber;
    }

    public static function fomartDayInHour($hourNum){
        if($hourNum==0){
            return '0小时';
        }
        // 将小时数转换为天数和小时数
        $days = floor($hourNum / 24);  // 获取天数
        $hours = $hourNum % 24;        // 获取剩余小时数

        // 格式化输出
        $timeDisplay = '';
        if ($days > 0) {
            $timeDisplay .= $days . '天';
        }
        if ($hours > 0 || $days == 0) {
            $timeDisplay .= $hours . '小时';
        }

        return $timeDisplay;
    }

    
}
