<?php

namespace app\common\command;

use app\model\AuthOrder;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\User;
use app\model\UserRelation;
use app\model\UserSignin;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\Capital;
use app\model\PovertySubsidy;
use app\model\Realname;
use app\model\UserBalanceLog;
use app\model\UserPath;
use Exception;
use think\facade\Log;

class CheckSubsidy extends Command
{
    protected function configure()
    {
        $this->setName('checkSubsidy')->setDescription('二期项目数字建设补贴，每天的0点2分执行');
    }

    protected function execute(Input $input, Output $output)
    {
        //$this->settle();
        //$this->rank();
        //$this->fixDigitalYuan();
        //$this->all();
        //$this->fixSecondBonus();
        //$this->realname();
        //$this->povertySubsidy();
        //$this->ecnyReject();
        //$this->fixRank0424();
        //$this->order6();
        //$this->returnOrder();
        //$this->fixRecharge0720_2();
        //$this->fixBonus0116();
        //$this->fixBonus0203();
        //$this->fixbonus0404();
        //$this->fixWithdraw();
        //$this->withDrawTolargeSubsidy();
        //$this->settle0425();
        //$this->autoRealname();
        //$this->translateInsurance();
        $this->translate0625();
        return true;
    }

    public function translate0625(){
        $data = User::where('large_subsidy','>',0)->chunk(1000, function($list) {
            foreach($list as $item){
                Db::startTrans();
                try {
                    User::changeInc($item['id'], -$item['large_subsidy'], 'large_subsidy', 18, 0, 7, '转入团队奖励');
                    User::changeInc($item['id'], $item['large_subsidy'], 'team_bonus_balance', 19, 0, 3, '转入团队奖励');
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    throw $e;
                }
            }
        });

        $data = User::where('insurance_balance','>',0)->chunk(1000, function($list) {
            foreach($list as $item){
                Db::startTrans();
                try {
                    User::changeInc($item['id'], -$item['insurance_balance'], 'insurance_balance', 18, 0, 5, '转入团队奖励');
                    User::changeInc($item['id'], $item['insurance_balance'], 'team_bonus_balance', 19, 0, 3, '转入团队奖励');
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    throw $e;
                }
            }
        });

         $data = User::where('ph_wallet','>',0)->chunk(1000, function($list) {
            foreach($list as $item){
                Db::startTrans();
                try {
                    User::changeInc($item['id'], -$item['ph_wallet'], 'ph_wallet', 18, 0, 9, '转入团队奖励');
                    User::changeInc($item['id'], $item['ph_wallet'], 'team_bonus_balance', 19, 0, 3, '转入团队奖励');
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    throw $e;
                }
            }
        });       
        
    }
    
    public function translateInsurance(){
        $already = Db::table('mp_insurance_apply')->where('type',0)->column('user_id');
        $count = 0;
        $sql = "SELECT user_id,sum(daily_bonus_ratio) as suma FROM mp_order WHERE  project_group_id = 18  AND status = 2  AND user_id NOT IN (select user_id from mp_insurance_apply where type =0) GROUP BY user_id  ";
        $orders = Db::query($sql);
                $year = date('Y');
                $month = date('m');
                foreach ($orders as $item) {
                    try{
                        $data = [
                            'user_id' => $item['user_id'],
                            'year' => $year,
                            'month' => $month,
                            'mmoney' => $item['suma'],
                        ];
                        $id = Db::table('mp_insurance_apply')->insertGetId($data);
                        User::changeInc($item['user_id'], $item['suma'], 'insurance_balance',31,$id,5,'领取基本保险金');
                        Db::commit();
                    }catch(\Exception $e){
                        Db::rollback();
                        return json(['code' => 10001, 'msg' => $e->getMessage(), 'data' => []]);
                    }
                    $count++;
                    echo "已处理{$count}条记录\n";
                }
                echo "已处理{$count}条记录\n";
    }

    public function translateHouseBalance(){
        $count = 0;
        $data = User::where('poverty_subsidy_amount','>',0)->chunk(1000,function($list) use(&$count){
            foreach($list as $item){
                Db::startTrans();
                try {
                    User::changeInc($item['id'], -$item['poverty_subsidy_amount'], 'poverty_subsidy_amount', 18, 0, 5, '房屋保障金转民生补助金');
                    User::changeInc($item['id'], $item['poverty_subsidy_amount'], 'large_subsidy', 18, 0, 7, '房屋保障金转民生补助金');
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    throw $e;
                }
                $count++;
            }
            echo "已处理{$count}条记录\n";
        });
    }

    public function transtalteUpUser(){

        $phoneArr = ['13986230048','18627020117','15327179818','13264871999','15971995999','13177004681','19888379559','19092770021','13538898472','18947270789','13507226270','18328331286','13972195252','13972636016','18372869657','19871864514','18672898189','13807229098','18942904299','18971077155','18162764943','15972295057','15971995999','18162708676','18972937294','13971637835','13100733903','13687262316','17347668265','13368111086','18523941999','18523314999','15520021736','16602392897','13983989837','13173339262','13368443068','13786184628','13707194406','15328865223','13899313844','18062677627','15072558332','13046128785','13807229098','19888378537','13117258365','13477430013','13677284271','15826937066','13407226664','13477483546','15586304580','18477881232','18872611057','13035341247','18064103040','13843396467'];
        $idArr = [];
        $userArr = [];
        $targetId = 2143996;
        $targetPath = UserPath::where('user_id',$targetId)->find();
        
        User::where('phone','in',$phoneArr)->chunk(100,function($list) use(&$idArr, &$userArr, $targetId, $targetPath){
            foreach($list as $item){
                $idArr[] = $item['id'];
                $userArr[$item['id']] = ['id'=>$item['id'],'phone'=>$item['phone'],'realname'=>$item['realname'],'up_user_id'=>$item['up_user_id']];
                $tPath = $targetPath['path'].'/'.$targetId;
                $deep = $targetPath['depth'] + 1;
                UserPath::where('user_id',$item['id'])->update(['path'=>$tPath,'depth'=>$deep]); 
            }
        });

        UserRelation::where('sub_user_id','in',$idArr)->where('level',1)->update(['user_id'=>2143996]);
        UserRelation::where('sub_user_id','in',$idArr)->where('level',2)->update(['user_id'=>2114047]);
        UserRelation::where('sub_user_id','in',$idArr)->where('level',3)->update(['user_id'=>2114036]);
        User::where('id','in',$idArr)->update(['up_user_id'=>2143996]);

    }

    public function autoRealname(){
        $phoneArr = ['19952100200','19952100300','19952100400','19952100500','19952100600','19952100700','19952100800','19952100900','19952100101','19952100110','19952100120','19952100130','19952100140','19952100150','19952100160','19952100170','19952100180','19952100190','19952100201'];
        $count = 0;
        $generatedData = [];

        $surnames = ['赵', '钱', '孙', '李', '周', '吴', '郑', '王', '冯', '陈', '褚', '卫', '蒋', '沈', '韩', '杨', '张',]; // 常见姓氏
        $givenNameChars = ['伟', '芳', '娜', '秀', '英', '敏', '静', '丽', '强', '磊', '军', '洋', '勇', '艳', '杰', '娟', '涛', '明', '超', '兰', '霞', '平', '刚', '桂', '红', '波', '云', '龙']; // 常用名选字

        foreach ($phoneArr as $phone) {
            // 生成随机姓名
            $surname = $surnames[array_rand($surnames)];
            $givenNameLength = rand(1, 2); // 名字长度1或2个字
            $givenName = '';
            for ($i = 0; $i < $givenNameLength; $i++) {
                $givenName .= $givenNameChars[array_rand($givenNameChars)];
            }
            $name = $surname . $givenName;

            // 生成随机身份证号 (18位)
            // 1. 地址码 (前6位) - 随机生成一个大致范围的数字
            $addressCode = str_pad(rand(110000, 659999), 6, '0', STR_PAD_LEFT);
            
            // 2. 出生日期 (中间8位 YYYYMMDD)
            $year = rand(1950, 2005);
            $month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
            // 为了简化，日期范围1-28
            $day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            $birthDate = $year . $month . $day;

            // 3. 顺序码 (接下来的3位)
            $sequenceCode = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);

            // 4. 校验码 (最后1位) - 随机数字或X
            $checksumOptions = array_merge(range(0, 9), ['X']);
            $checksum = $checksumOptions[array_rand($checksumOptions)];
            
            $idNumber = $addressCode . $birthDate . $sequenceCode . $checksum;

            $generatedData[] = [
                'phone' => $phone,
                'name' => $name,
                'id_number' => $idNumber,
            
            ];
            echo "手机号: $phone, 姓名: $name, 身份证号: $idNumber\n";
            $user = User::where('phone',$phone)->find();
            $data = [
                'user_id'=>$user['id'],
                'realname'=>$name,
                'ic_number'=>$idNumber,
                'img1'=>'',
                'img2'=>'',
                'img3'=>'',
                'status'=>1,
                'phone'=>$phone,
            ];
            Realname::create($data);
            User::where('id',$user['id'])->update(['realname'=>$name,'ic_number'=>$idNumber]);
            $count++;
        }

    }

    public function siginIntegral2Integral(){
        $count =0;
        $data = User::where('signin_integral','>',0)->chunk(1000,function($list) use(&$count){
            foreach($list as $item){
                Db::startTrans();
                try {
                    User::changeInc($item['id'], -$item['signin_integral'], 'signin_integral', 6, 0, 6, '签到积分转积分');
                    User::changeInc($item['id'], $item['signin_integral'], 'integral', 6, 0, 2, '签到积分转积分');
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    throw $e;
                }
            }
            $count+=1000;
            echo "已处理{$count}条记录\n";
        });
    }

    public function settle0425(){
        $phones = [
            '15365495999','13737443447','13901168875','18904982940','17719020408','13019999299','17741916352','15908590201','18241061107','13698981717','18726742525','13211111111','18022843564','13692407650',
        ];
        $rechargeArr = [];
        foreach($phones as $phone){
            $user = User::where('phone',$phone)->field('id,phone,realname')->find();
            if($user){
                $userPath = UserPath::where('user_id',$user['id'])->find();
                $path = $userPath['path'].'/'.$user['id'];
                $sql = "select sum(amount) recharge_sum  from mp_capital where type=1 and status = 2 and user_id in (select id from mp_user_path where path like'{$path}%')";
                $query = Db::query($sql);
                $recharge_sum = $query[0]['recharge_sum'];
                $sql2 = "select sum(amount) withdraw_sum  from mp_capital where type=2 and status = 2 and user_id in (select id from mp_user_path where path like'{$path}%')";
                $query = Db::query($sql2);
                $withdraw_sum = $query[0]['withdraw_sum'];


                $rechargeArr[] = [
                    'user_id'=>$user['id'],
                    'phone'=>$user['phone'],
                    'realname'=>$user['realname'],
                    'recharge_sum'=>$recharge_sum,
                    'withdraw_sum'=>$withdraw_sum,
                ];
            }else{
                echo "用户{$phone} 不存在\n";
            }
        }

        create_excel_file($rechargeArr, [
            'user_id' => '序号',
            'phone' => '电话',
            'realname' => '姓名',
            'recharge_sum' => '充值总金额',
            'withdraw_sum' => '提现总金额',
        ], '充值统计-' . date('YmdHis'));
    }

    public function withDrawTolargeSubsidy(){
        $count=0;
        $data = user::where('team_bonus_balance','>',0)->chunk(1000,function($list) use(&$count){
            foreach($list as $item){
                Db::startTrans();
                try {
                    User::changeInc($item['id'], -$item['team_bonus_balance'], 'team_bonus_balance', 6, 0, 3, '转入民生补助金');
                    User::changeInc($item['id'], $item['team_bonus_balance'], 'large_subsidy', 6, 0, 7, '转入民生补助金');
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    throw $e;
                }
                $count++;
            }
            echo "已处理{$count}条记录\n";
        });
                
    }

    public function  fixWithdraw(){
        $data = Capital::where('type',2)->where('status',1)->chunk(500, function($list) {
            foreach($list as $item){
                Db::startTrans();
                try {
                    Capital::auditWithdraw($item['id'], 3, 0, '申请驳回-请详细阅读反洗钱专项的公告');      
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    throw $e;
                }
                //break;
             }
             //return false;
        });
    }

/*     public function fixbonus0404(){
        $data = Order::whereIn('project_group_id',[10])->where('is_subsidy',0)->chunk(100, function($list) {
            foreach ($list as $item) {
                $text = "{$item['project_name']}";
                $user = User::where('id', $item['user_id'])->find();
                if($user['income_balance'] >= $item['sum_amount']){
                    User::changeInc($item['user_id'], -$item['sum_amount'],'income_balance',29,$item['id'],4,$text.'释放民生养老金');
                    User::changeInc($item['user_id'],$item['sum_amount'],'team_bonus_balance',6,$item['id'],3,$text.'释放民生养老金');
                    Order::where('id',$item['id'])->update(['is_subsidy'=>1]);
                }else if($user['income_balance'] > 0){
                    User::changeInc($item['user_id'], -$user['income_balance'],'income_balance',29,$item['id'],4,'释放民生养老金');
                    User::changeInc($item['user_id'],$user['income_balance'],'team_bonus_balance',6,$item['id'],3,$text.'释放民生养老金');
                }
            }
        });
    } */

    public function fixBonus0325()
    {
        $sql = "select * from  mp_user_balance_log where type = 8 and  id < 6388456 and id > 6365826 and relation_id in (
                    select  relation_id  from  mp_user_balance_log where type = 3 and  remark like'%-赠送%' and  id < 6388456 and id > 6365826
                )";

        $data = Db::query($sql);
        foreach ($data as $item) {
            Db::startTrans();
            try {
                $user = User::where('id', $item['user_id'])->find();
                $money = abs($item['change_balance']);
                $yikou = $money;
                $yikou2 = 0;
                if ($user['team_bonus_balance'] > 0) {
                    if ($user['team_bonus_balance'] < $money) {
                        $yikou = $user['team_bonus_balance'];
                    }
                } else {
                    $yikou = 0;
                }
                if ($yikou) {
                    User::changeInc($item['user_id'], -$yikou, 'team_bonus_balance', 8, $item['id'], 3, '团队奖励扣除', 0, 1, 'FX');
                }
                $money = $money - $yikou;
                if ($money > 0) {
                    $yikou2 = $money;
                    if ($user['balance'] > 0) {
                        if ($user['balance'] < $money) {
                            $yikou2 = $user['balance'];
                        }
                    } else {
                        $yikou2 = 0;
                    }
                    if ($yikou2 > 0) {
                        User::changeInc($item['user_id'], -$yikou2, 'balance', 8, $item['id'], 1, '团队奖励扣除', 0, 1, 'FX');
                    }
                    $money = $money - $yikou2;
                }
                $status =  $money == 0 ? 1 : 0;
                $insert = [
                    'log_id' => $item['id'],
                    'user_id' => $item['user_id'],
                    'relation_id' => $item['relation_id'],
                    'money' => $item['change_balance'],
                    'money_balance' => $yikou,
                    'money_team_balance' => $yikou2,
                    'money_remaining' => $money,
                    'status' => $status,
                ];
                Db::table('fix_0325')->insert($insert);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                Log::error('0325异常：' . $e->getMessage(), ['e' => $e]);
                throw $e;
            }
        }
    }

    public function fixBonus0308()
    {
        $orders = Order::where('project_group_id', 5)->where('status', 2)->where('updated_at', '<', '2025-03-08 00:00:00')->chunk(100, function ($list) {
            foreach ($list as $order) {
                Db::startTrans();
                try {
                    $next_bonus_time = strtotime(date('Y-m-d 00:00:00', strtotime('+ 1day')));

                    $cur_time = strtotime(date('Y-m-d 00:00:00'));

                    $text = "{$order['project_name']}";
                    $income = $order['daily_bonus_ratio'];
                    // 分红钱
                    if ($income > 0) {
                        User::changeInc($order['user_id'], $income, 'team_bonus_balance', 6, $order['id'], 3, $text . '补助资金');
                    }
                    // 分红积分
                    if ($order['gift_integral'] > 0) {
                        User::changeInc($order['user_id'], $order['gift_integral'], 'integral', 6, $order['id'], 2, $text . '普惠积分');
                    }
                    $gain_bonus = bcadd($order['gain_bonus'], $income, 2);
                    Order::where('id', $order->id)->update(['next_bonus_time' => $next_bonus_time, 'gain_bonus' => $gain_bonus]);

                    // 到期需要返还申报费用
                    if ($order['end_time'] <= $cur_time) {
                        // 返还前
                        $amount = $order['single_amount'];
                        if ($amount > 0) {
                            User::changeInc($order['user_id'], $amount, 'team_bonus_balance', 6, $order['id'], 3, $text . '返还申报费用');
                        }
                        // 结束项目分红
                        Order::where('id', $order->id)->update(['status' => 4]);
                    }

                    Db::Commit();
                } catch (Exception $e) {
                    Db::rollback();

                    Log::error('分红收益异常：' . $e->getMessage(), ['e' => $e]);
                    throw $e;
                }
            }
        });
    }

    public function fixBonus0205()
    {
        $prizes = Db::table('mp_user_prize')->where('status', 1)->where('lottery_id', 5)->chunk(100, function ($list) {
            foreach ($list as $item) {
                User::changeInc($item['user_id'], 300, 'team_bonus_balance', 28, $item['id'], 3, '抽奖奖励 财补发');
            }
        });
    }

    public function fixBonus0213()
    {
        $orders = Order::where('project_group_id', 4)->where('status', 4)->chunk(100, function ($list) {
            foreach ($list as $order) {
                $text = "{$order['project_name']}";
                if ($order['single_amount'] > 0) {
                    User::changeInc($order['user_id'], $order['single_amount'], 'large_subsidy', 6, $order['id'], 7, $text . '申报费用返还');
                }
            }
        });
    }

    public function fixBonus0116()
    {
        // 养老二期
        $cur_time = strtotime(date('Y-m-d 00:05:00') . ' +1 day');
        $cc = 0;
        $dd = 0;
        $data = Order::whereIn('project_group_id', [5])->where('status', 2)->where('next_bonus_time', '<=', $cur_time)->chunk(100, function ($list) use (&$cc, &$dd) {

            foreach ($list as $item) {
                $count = UserBalanceLog::where('user_id', $item['user_id'])
                    ->where('type', 6)
                    ->where('log_type', 3)
                    ->where('id', '>=', 1967703)
                    ->where('id', '<=', 2017077)
                    ->where('relation_id', $item['id'])
                    ->count();

                $text = "{$item['project_name']}";
                if ($count > 1) {
                    $cc++;

                    $income = $item['daily_bonus_ratio'];
                    //echo "订单{$item['id']} 重复分红\n";
                    if ($income > 0) {
                        try {
                            User::changeInc($item['user_id'], -$income, 'team_bonus_balance', 6, $item['id'], 3, $text . '扣减重复补助资金');
                        } catch (Exception $e) {
                            Log::debug('订单' . $item['id'] . '重复分红异常：' . $e->getMessage(), ['e' => $e]);
                        }
                    }
                }

                $count2 = UserBalanceLog::where('user_id', $item['user_id'])
                    ->where('type', 6)
                    ->where('log_type', 2)
                    ->where('id', '>=', 1967703)
                    ->where('id', '<=', 2017077)
                    ->where('relation_id', $item['id'])
                    ->count();
                if ($count2 > 1) {
                    $dd++;
                    //echo "订单{$item['id']} 重复积分\n";
                    if ($item['gift_integral'] > 0) {
                        try {
                            User::changeInc($item['user_id'], -$item['gift_integral'], 'integral', 6, $item['id'], 2, $text . '扣减重复普惠积分');
                        } catch (Exception $e) {
                            Log::debug('订单' . $item['id'] . '重复积分异常：' . $e->getMessage(), ['e' => $e]);
                        }
                    }
                }
            }
            echo "已处理{$cc}条记录 {$dd}\n";
        });
    }

    public function  fixOrder1105()
    {
        $orders = Order::where('status', 2)->select();
        foreach ($orders as $order) {
            $endtimeOld = $order['end_time'];
            $endtimeNew = $endtimeOld - 86400;
            Order::where('id', $order['id'])->update(['end_time' => $endtimeNew]);
        }
    }

    public function fixRecharge0720_2()
    {
        $data = Db::table('fix_recharge_0720')->where('status', 0)->select();
        foreach ($data as $item) {
            $user = User::where('id', $item['user_id'])->find();
            echo "-- id {$item['id']} 用户{$item['user_id']} 金额{$item['return_amount']} \n";
            Log::debug("-- id {$item['id']} 用户{$item['user_id']} 金额{$item['return_amount']} \n");
            $log1 = UserBalanceLog::where('user_id', $item['user_id'])->where('type', 92)->find();
            $log2 = UserBalanceLog::where('user_id', $item['user_id'])->where('type', 3)->where('created_at', '>=', $log1['created_at'])->limit(3)->order('id', 'asc')->select();
            if (count($log2) > 0) {
                $log3 = $log2[0];
                $order = Order::where('id', $log3['relation_id'])->find();
                if (!$order) {
                    echo "用户{$item['user_id']} 订单{$log3['relation_id']} 不存在\n";
                    Log::debug("用户{$item['user_id']} 订单{$log3['relation_id']} 不存在\n");
                    continue;
                }
                $logs4 = UserBalanceLog::where('user_id', $item['user_id'])->where('type', 3)->where('relation_id', $log3['relation_id'])->order('id', 'asc')->select();
                $fields = ['topup_balance', 'signin_balance', 'team_bonus_balance'];
                $cnFields = ['topup_balance' => '充值余额', 'signin_balance' => '签到金额', 'team_bonus_balance' => '团队奖励'];
                $amouts = ['topup_balance' => 0, 'signin_balance' => 0, 'team_bonus_balance' => 0];

                foreach ($logs4 as $key => $log) {
                    $field = $fields[$key];
                    $amouts[$field] =  abs($log['change_balance']);
                    //$logIds[] = $log['id'];
                }

                if ($amouts['topup_balance'] < $item['return_amount']) {
                    echo "用户{$item['user_id']} 余额{$amouts['topup_balance']} 金额{$item['return_amount']} 不够扣除 \n";
                    Log::debug("用户{$item['user_id']} 余额{$amouts['topup_balance']} 金额{$item['return_amount']} 不够扣除 \n");
                    continue;
                }
                Db::table('mp_order_copy1')->insert($order->toArray());
                Order::where('id', $order['id'])->delete();
                foreach ($amouts as $key => $amount) {
                    if ($amount > 0) {
                        User::changeInc($item['user_id'], $amount, $key, 89, $order['id'], 1, '撤销订单返还' . $cnFields[$key], 0, 1, 'cz');
                        echo "用户{$item['user_id']} 订单{$item['id']} 金额{$order['single_amount']}  撤销订单返还 {$key} {$amount}\n";
                        Log::debug("用户{$item['user_id']} 订单{$item['id']} 金额{$order['single_amount']}  撤销订单返还 {$cnFields[$key]} {$amount}\n");
                    }
                }
                echo "充值重复扣除 topup_balance -{$item['return_amount']} \n";
                Log::debug("充值重复扣除 topup_balance -{$item['return_amount']} \n");
                User::changeInc($item['user_id'], -$item['return_amount'], 'topup_balance', 90, $item['id'], 1, '充值重复扣除', 0, 1, 'CZ');


                $amouts2 = ['topup_balance' => $item['topup_balance'], 'signin_balance' => $item['signin_balance'], 'team_bonus_balance' => $item['team_bonus_balance']];
                $returnAmount = $item['return_amount'];
                foreach ($amouts2 as $key => $amount) {
                    if ($returnAmount > 0) {
                        if ($returnAmount >= $amount) {
                            echo "用户{$item['user_id']} 撤销订单返还 {$key} {$amount} \n";
                            Log::debug("用户{$item['user_id']} 撤销订单返还 {$key} {$amount} \n");
                            User::changeInc($item['user_id'], $amount, $key, 89, $item['id'], 1, '撤销订单返还' . $cnFields[$key], 0, 1, 'cz');
                            $returnAmount = $returnAmount - $amount;
                        } else {
                            echo "用户{$item['user_id']} 撤销订单返还 {$key} {$returnAmount} \n";
                            User::changeInc($item['user_id'], $returnAmount, $key, 89, $item['id'], 1, '撤销订单返还' . $cnFields[$key], 0, 1, 'cz');
                            Log::debug("用户{$item['user_id']} 撤销订单返还 {$key} {$amount} \n");
                            $returnAmount = 0;
                            break;
                        }
                    }
                }
                Db::table('fix_recharge_0720')->where('id', $item['id'])->update(['success' => 1]);

                echo "用户{$item['user_id']} 可以扣除\n";
            } else {
                Log::debug("用户{$item['user_id']} 不能扣除 \n");
            }
        }
    }

    public function fixRecharge0720_1()
    {
        $data = Db::table('fix_recharge_0720')->where('status', 3)->select();
        foreach ($data as $item) {
            $user = User::where('id', $item['user_id'])->find();
            if ($user['topup_balance'] < $item['return_amount']) {
                echo "用户{$item['user_id']} 余额{$user['topup_balance']} 金额{$item['return_amount']} 扣除失败 \n";
                Log::debug("用户{$item['user_id']} 余额{$user['topup_balance']} 金额{$item['return_amount']} 扣除失败 \n");
                continue;
            }
            if ($user['topup_balance'] >= $item['return_amount']) {
                User::changeInc($item['user_id'], -$item['return_amount'], 'topup_balance', 90, $item['id'], 1, '充值重复扣除', 0, 1, 'cz');
                $returnAmount = $item['return_amount'];
                if ($returnAmount >= $item['topup_balance']) {
                    if ($item['topup_balance'] > 0) {
                        $user::changeInc($item['user_id'], $item['topup_balance'], 'topup_balance', 89, $item['id'], 1, '撤销订单返还充值余额', 0, 1, 'cz');
                        $returnAmount = $returnAmount - $item['topup_balance'];
                    }
                    if ($returnAmount > 0 && $returnAmount <= $item['signin_balance']) {
                        $user::changeInc($item['user_id'], $returnAmount, 'signin_balance', 89, $item['id'], 1, '撤销订单返还签到金额', 0, 1, 'cz');
                        if ($returnAmount > 0 && $returnAmount <= $item['team_bonus_balance']) {
                            $user::changeInc($item['user_id'], $returnAmount, 'team_bonus_balance', 89, $item['id'], 1, '撤销订单返还团队奖励', 0, 1, 'cz');
                        }
                    }
                }
                Db::table('fix_recharge_0720')->where('id', $item['id'])->update(['success' => 1]);
            }
        }
    }


    public function returnOrder()
    {
        $return = UserBalanceLog::where('type', 92)->select();
        $count = 0;
        foreach ($return as $item) {
            $count++;
            $user = User::where('id', $item['user_id'])->find();
            if ($user['topup_balance'] < $item['change_balance']) {
                echo "$count 用户{$item['user_id']} 余额{$user['topup_balance']} 金额{$item['change_balance']} \n";
            }

            $order = Db::table('mp_order_copy1')->where('user_id', $item['user_id'])->order('id', 'desc')->find();
            $logs = UserBalanceLog::where('user_id', $item['user_id'])->where('type', 3)->where('relation_id', $order['id'])->select();
            $fields = ['topup_balance', 'signin_balance', 'team_bonus_balance'];
            $amouts = ['topup_balance' => 0, 'signin_balance' => 0, 'team_bonus_balance' => 0];
            if (count($logs) == 1) {
                echo "用户{$item['user_id']} 订单{$order['id']} 金额{$order['single_amount']} 不用处理\n";
                //continue;
            }
            if ($amouts['topup_balance'] >= $item['change_balance']) {
                echo "用户{$item['user_id']} 订单{$order['id']} 金额{$order['single_amount']}  购买使用充值余额 {$amouts['topup_balance']}不用处理\n";
                //continue;
            }
            $logIds = [];
            foreach ($logs as $key => $log) {
                $field = $fields[$key];
                $amouts[$field] =  abs($log['change_balance']);
                $logIds[] = $log['id'];
            }
            print_r($amouts);
            $data = [
                'user_id' => $item['user_id'],
                'return_amount' => $item['change_balance'],
                'order_id' => $order['id'],
                'order_amount' => $order['single_amount'],
                'user_topup_balance' => $user['topup_balance'],
                'topup_balance' => $amouts['topup_balance'],
                'signin_balance' => $amouts['signin_balance'],
                'team_bonus_balance' => $amouts['team_bonus_balance'],
                'log_id' => implode(',', $logIds),
            ];
            Db::table('fix_recharge_0720')->insert($data);
            echo "---\n";
        }
    }

    public function fixRe0720()
    {
        $data = Db::table('fix_recharge_0719')->where('no_sub', '>', 0)->order('no_sub', 'desc')->select();
        foreach ($data as $item) {
            $user = User::where('id', $item['user_id'])->find();
            $balanceLogs = UserBalanceLog::where('user_id', $item['user_id'])->where('relation_id', $item['relation_id'])->where('type', 1)->where('log_type', 1)->where('change_balance', $item['change_balance'])->order('id', 'asc')->select();
            $log = $balanceLogs[0];
            $orders = Order::where('user_id', $item['user_id'])->where('status', 2)->where('created_at', '>=', $log['created_at'])->order('id', 'asc')->select();
            Log::debug("{$item['id']} 用户{$item['user_id']} 交易{$item['relation_id']}  未扣除{$item['no_sub']} 充值余额{$user['topup_balance']} 团队奖励{$user['team_bonus_balance']}");
            $transfers = UserBalanceLog::where('user_id', $item['id'])->where('type', 18)->where('created_at', '>=', $log['created_at'])->order('id', 'asc')->select();
            foreach ($transfers as $transfer) {
                $logs = UserBalanceLog::where('user_id', $transfer['relation_id'])->where('type', 19)->select();
            }
        }
    }

    public function fixRecharge()
    {
        $sql = "select user_id,relation_id,change_balance,count(*) ct  from mp_user_balance_log where type=1 and created_at BETWEEN '2024-07-19 00:00:00' and '2024-07-19 23:59:59'  group by  user_id,relation_id,change_balance having ct>1 order by ct desc limit 1000;";
        $data = Db::query($sql);
        $noSubArr = [];
        foreach ($data as $item) {
            //var_dump($item);
            $user = User::where('id', $item['user_id'])->find();
            $balanceLogs = UserBalanceLog::where('user_id', $item['user_id'])->where('relation_id', $item['relation_id'])->where('type', 1)->where('log_type', 1)->where('change_balance', $item['change_balance'])->order('id', 'asc')->select();
            $log = $balanceLogs[0];
            $amount = abs($log['change_balance']);
            $sum = ($item['ct'] - 1) * $amount;
            $item['sum'] = $sum;
            $item['no_sub'] = 0;
            $item['return'] = 0;
            echo "用户{$item['user_id']} 交易{$item['relation_id']} 金额{$amount} 次数{$item['ct']} 总金额{$sum}  用户金额{$user['topup_balance']}\n ";
            Log::debug("用户{$item['user_id']} 交易{$item['relation_id']} 金额{$amount} 次数{$item['ct']} 总金额{$sum}  用户金额{$user['topup_balance']}");
            if ($user['topup_balance'] > 0) {
                if ($user['topup_balance'] >= $sum) {
                    //$user['topup_balance'] = $user['topup_balance'] - $sum;
                    //User::where('id',$item['user_id'])->update(['topup_balance'=>$user['topup_balance']]);
                    User::changeInc($item['user_id'], -$sum, 'topup_balance', 91, $item['relation_id'], 1, '充值重复扣除', 0, 1, 'CZ');
                } else {
                    $sum = $sum - $user['topup_balance'];
                    User::changeInc($item['user_id'], -$user['topup_balance'], 'topup_balance', 91, $item['relation_id'], 1, '充值重复扣除', 0, 1, 'CZ');
                    echo "用户{$item['user_id']} 扣除{$user['topup_balance']} 余额{$sum}\n";
                    Log::debug("用户{$item['user_id']} 扣除{$user['topup_balance']} 余额{$sum}");
                }
            }
            $orders = Order::where('user_id', $item['user_id'])->where('status', 2)->where('created_at', '>=', $log['created_at'])->order('id', 'asc')->select();
            foreach ($orders as $order) {

                $sum = $sum - $order['single_amount'];
                try {
                    Db::table('mp_order_copy1')->insert($order->toArray());
                } catch (Exception $e) {
                    echo "订单{$order['id']} 复制失败\n";
                    Log::debug("订单{$order['id']} 复制失败");
                    dump($e->getMessage());
                    continue;
                    // throw $e;
                }
                Order::where('id', $order['id'])->delete();
                echo " 删除订单{$order['id']} 金额{$order['single_amount']} 剩余{$sum}\n";
                Log::debug(" 删除订单{$order['id']} 金额{$order['single_amount']} 剩余{$sum}");
                //$order['id']
                if ($sum <= 0) {
                    if ($sum < 0) {
                        echo "sum = $sum\n";
                        $return = abs($sum);
                        $item['return'] = $return;
                        echo " 用户{$item['user_id']}  返还{$return} \n";
                        User::changeInc($item['user_id'], $return, 'topup_balance', 92, $item['relation_id'], 1, '充值重复撤销订单返还', 0, 1, 'CZ');
                    }
                    break;
                }
            }
            if ($sum > 0) {
                echo "--**未扣除{$sum}";
                $item['no_sub'] = $sum;
                Log::debug("--**未扣除{$sum}");
            }
            $noSubArr[] = $item;

            echo "--\n";
            //break;
        }
        foreach ($noSubArr as $item) {
            Db::table('fix_recharge_0719')->insert($item);
        }
    }

    public function order6()
    {
        $data = Order::where('project_group_id', 6)->where('status', 2)->chunk(1000, function ($list) {
            foreach ($list as $item) {
                //$this->bonus($item);
                echo "正在处理订单{$item['id']}\n";
                User::changeInc($item['user_id'], $item['single_amount'], 'gf_purse', 42, $item['id'], 9, '项目收益申报返还', 0, 1, 'FX');
            }
        });
    }

    public function returnAuthOrder()
    {
        $data = AuthOrder::where('created_at', '<=', '2024-06-11 14:31:00')->where('created_at', '>=', '2024-06-11 00:00:00')->select();
        $fields = [0 => 'gf_purse', 1 => 'team_bonus_balance', 2 => 'topup_balance'];
        $texts = [0 => '共富钱包', 1 => '团队奖励', 2 => '充值余额'];
        foreach ($data as $item) {
            $logs = UserBalanceLog::where('relation_id', $item['id'])->where('type', 40)->order('id', 'desc')->select();
            if (count($logs) >= 3) {
                Db::startTrans();
                try {
                    foreach ($logs as $k => $log) {

                        $amount = abs($log['change_balance']);
                        if ($amount == 0) {
                            continue;
                        }
                        $field = $fields[$k];
                        $text = $texts[$k];
                        echo "正在处理订单{$item['id']} 认领资产 $field $text {$amount}\n";
                        User::changeInc($item['user_id'], $amount, $field, 98, $item['id'], 1, "认领资产{$text}退还");
                    }
                    AuthOrder::where('id', $item['id'])->where('user_id', $item['user_id'])->delete();
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    Log::error('认领资产' . $item['id'] . '退还异常：' . $e->getMessage(), ['e' => $e]);
                    throw $e;
                }
            } else {
                echo "订单{$item['id']} 不用退还\n";
            }
        }
    }
    public function fixRank0424()
    {
        $data = UserRelation::rankList('yesterday');
        foreach ($data as $item) {
            $balanceLog = UserBalanceLog::where('user_id', $item['user_id'])->where('type', 29)->where('log_type', 2)->where('created_at', '>=', '2024-04-24 00:00:00')->find();
            if ($balanceLog) {
                continue;
            }
            Db::startTrans();
            try {
                User::changeInc($item['user_id'], $item['reward'], 'team_bonus_balance', 29, 0, 2, '共富功臣奖励');
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                Log::error('团队排名奖励异常：' . $e->getMessage(), $e);
                throw $e;
            }
        }
    }

    public function ecnyReject()
    {
        $count = 0;
        Capital::where('type', 2)->where('log_type', 7)->where('status', 1)->chunk(1000, function ($list) use (&$count) {
            foreach ($list as $item) {
                Db::startTrans();
                try {
                    Capital::where('id', $item['id'])->update(['status' => 3]);
                    User::changeInc($item['user_id'], $item['withdraw_amount'], 'digit_balance', 13, $item['id'], 7, '驳回金额', 0, 1, 'TX');
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    Log::error('提现驳回异常：' . $e->getMessage(), ['E' => $e]);
                    throw $e;
                }
            }
            $count += 1000;
            echo "--已处理{$count}条记录\n";
        });
    }

    public function povertySubsidy()
    {
        $data = PovertySubsidy::alias('s')->join('user u', 's.user_id=u.id')->field('s.id,s.user_id,s.amount,u.invest_amount')->where('month', 3)->where('amount', '<', 50000)->where('invest_amount', '>=', 1500)->whereBetweenTime('s.created_at', '2024-03-15 00:00:00', '2024-03-15 08:26:00')->order('s.id asc')->select();
        //->chunk(100, function($list) {
        foreach ($data as $item) {
            //$this->povertySubsidyBonus($item);
            $subsidyAmount = 50000 - $item['amount'];
            if ($subsidyAmount > 0) {
                echo "{$item['id']} 用户{$item['user_id']} 需要补发{$subsidyAmount} \n";
                Db::startTrans();
                try {
                    User::where('id', $item['user_id'])->inc('poverty_subsidy_amount', $subsidyAmount)->update();
                    User::changeInc($item['user_id'], $subsidyAmount, 'digital_yuan_amount', 30, $item['id'], 3, '补发生活补助', 0, 1, 'BZ');
                    PovertySubsidy::where('id', $item['id'])->update(['amount' => 50000]);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    Log::error('生活补助异常：' . $e->getMessage(), ['e' => $e]);
                    throw $e;
                }
            }
        }
        // });
    }


    public function realname()
    {
        $data = User::where('digital_yuan_amount', '<', '1000000')->where('realname', '<>', '')->select();
        foreach ($data as $item) {
            $log = \app\model\UserBalanceLog::where('user_id', $item['id'])->where('type', 24)->where('log_type', 3)->where('change_balance', 1000000)->find();
            if ($log) {
                continue;
            }
            Db::startTrans();
            try {
                User::changeInc($item['id'], 1000000, 'digital_yuan_amount', 24, 0, 3, '注册赠送数字人民币', 0, 1, 'SM');
                User::where('id', $item['id'])->update(['is_realname' => 1]);
                echo "用户{$item['id']} {$item['realname']} \n";
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                Log::error('实名认证异常：' . $e->getMessage());
                throw $e;
            }
        }
    }

    public function settle()
    {
        $cur_time = strtotime(date('Y-m-d 00:00:00'));
        $time = strtotime(date('Y-m-d 00:00:00'));
        $data = Order::whereIn('project_group_id', [1])->where('status', 2)
            //->where('end_time', '<=', $cur_time)
            ->chunk(100, function ($list) {
                foreach ($list as $item) {
                    $this->bonus($item);
                }
            });
    }

    public function fixDigitalYuan()
    {
        $sql = "select user_id,count(*) ct from mp_user_balance_log where type=24 group by user_id HAVING ct>1 order by ct desc";
        $ids = Db::query($sql);
        $i = 0;
        foreach ($ids as $v) {
            $i++;
            $user = User::where('id', $v['user_id'])->find();
            if ($user['digital_yuan_amount'] == $v['ct'] * 1000000) {
                echo "$i {$v['user_id']} {$user['digital_yuan_amount']}\n";
                $ct = $v['ct'] - 1;
                $amount = $ct * 1000000;
                //User::where('id',$v['user_id'])->inc('digital_yuan_amount',-$amount)->updat入e();
                User::changeInc($v['user_id'], -$amount, 'digital_yuan_amount', 5, 0, 3, '系统扣除错误金额', 0, 1, 'CZ');
            }
        }
    }

    public function bonus($order)
    {
        Db::startTrans();
        try {
            User::changeInc($order['user_id'], $order['sum_amount'], 'digital_yuan_amount', 6, $order['id'], 3);
            User::changeInc($order['user_id'], $order['single_amount'], 'digital_yuan_amount', 12, $order['id'], 3);
            //User::changeInc($order['user_id'],$order['single_gift_digital_yuan'],'digital_yuan_amount',5,$order['id'],3);
            Order::where('id', $order->id)->update(['status' => 4]);
            /*             if($order['project_group_id']==2){
                
            } */
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage(), $e);
            throw $e;
        }
    }

    public function rank()
    {
        $data = UserRelation::rankList('yesterday');
        foreach ($data as $item) {
            Db::startTrans();
            try {
                User::changeInc($item['user_id'], $item['reward'], 'team_bonus_balance', 29, 0, 2, '共富功臣奖励');
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                Log::error('团队排名奖励异常：' . $e->getMessage(), $e);
                throw $e;
            }
        }
    }


    public function fixSecondBonus()
    {
        $yesterday = date("Y-m-d", strtotime("-1 day"));
        $day = date("d", strtotime($yesterday));
        $month = date("m", strtotime($yesterday));
        $sql = "select *  from mp_order where project_group_id = 2 and status=4 and created_at BETWEEN '2023-11-1 00:00:00' and '2023-11-09 :23:59:59' and id not in(
            select relation_id from mp_user_balance_log where remark='二期项目每月分红'
            )";
        $data = Db::query($sql);
        foreach ($data as $item) {

            echo "正在处理订单{$item['id']}\n";
            $time = time();
            $nowMonth = intval(date("m", $time));
            $endMonth = intval(date("m", $item['end_time']));
            $executeDay = date('Ym') . date("d", strtotime($item['created_at']));
            if ($nowMonth > $endMonth) {
                $passiveIncome = PassiveIncomeRecord::where('order_id', $item['id'])->where('user_id', $item['user_id'])->where('execute_day', $executeDay)->where('type', 2)->find();
                if (!empty($passiveIncome)) {
                    //已经分红
                    return;
                }
                $passiveIncome = PassiveIncomeRecord::where('order_id', $item['id'])->where('user_id', $item['user_id'])->order('execute_day', 'desc')->where('type', 2)->find();
                if (!$passiveIncome) {
                    $day = 0;
                } else {
                    $day = $passiveIncome['days'];
                }
                $day += 1;
                Db::startTrans();
                try {
                    $amount = $item['sum_amount'];
                    PassiveIncomeRecord::create([
                        'project_group_id' => $item['project_group_id'],
                        'user_id' => $item['user_id'],
                        'order_id' => $item['id'],
                        'execute_day' => $executeDay,
                        'amount' => $amount,
                        'days' => $day,
                        'is_finish' => 1,
                        'status' => 3,
                        'type' => 2,
                    ]);
                    $gain_bonus = bcadd($item['gain_bonus'], $amount, 2);
                    Order::where('id', $item['id'])->update(['gain_bonus' => $gain_bonus]);
                    User::changeInc($item['user_id'], $amount, 'income_balance', 6, $item['id'], 6, '二期项目每月分红');
                    Db::commit();
                } catch (Exception $e) {
                    Log::error('二期项目每月分红异常：' . $e->getMessage(), $e);
                    Db::rollback();
                    throw $e;
                }
                //return true;
            }
            // break;
        }
    }


    protected function all()
    {
        //$this->widthdrawAudit();
        $arr = [
            71267,
            71268,
            71269,
            71270,
            71271,
            71272,
            71273,
            71274,
            71275,
            71276,
        ];
        $data = Order::where('status', 2)->whereIn('id', $arr)
            ->chunk(100, function ($list) {
                foreach ($list as $item) {
                    $this->bonus4($item);
                }
            });
        //echo Order::getLastSql()."\n";
    }

    public function widthdrawAudit()
    {
        $ret = Capital::where('status', 1)->where('type', 2)->whereIn('log_type', [3, 6])->where('created_at', '<=', '2023-12-10 23:59:59')->update(['status' => 2]);
        //echo Capital::getLastSql()."\n";
        echo "updated {$ret} \n";
    }

    public function bonus4($order)
    {
        Db::startTrans();
        try {
            echo "正在处理订单{$order['id']}\n";
            //$digitalYuan = bcmul($order['single_gift_digital_yuan'],$order['period'],2);
            $digitalYuan = $order['single_gift_digital_yuan'];
            User::changeInc($order['user_id'], $order['sum_amount'], 'income_balance', 6, $order['id'], 6);
            User::changeInc($order['user_id'], $digitalYuan, 'digital_yuan_amount', 5, $order['id'], 3, '国务院津贴');

            //User::changeInc($order['user_id'],$order['single_gift_digital_yuan'],'digital_yuan_amount',5,$order['id'],3);
            Order::where('id', $order->id)->update(['status' => 4]);

            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage(), $e);
            throw $e;
        }
    }

    /*     protected function bonus($order){
        Db::startTrans();
        try{
            echo "正在处理订单{$order['id']}\n";
            $alreadyAmount = PassiveIncomeRecord::where('order_id',$order['id'])->sum('amount');
            $digitalYuan = bcmul($order['single_gift_digital_yuan'],$order['period']) - $alreadyAmount;
            User::changeInc($order['user_id'],$order['sum_amount'],'income_balance',6,$order['id'],6);
            $gainBonus = bcadd($order['gain_bonus'],$digitalYuan,2);
           
            if($digitalYuan>0){
                Order::where('id',$order->id)->update(['status'=>4,'gain_bonus'=>$gainBonus]);
                User::changeInc($order['user_id'],$digitalYuan,'digital_yuan_amount',5,$order['id'],3,'结算');
                PassiveIncomeRecord::create([
                    'project_group_id'=>$order['project_group_id'],
                    'user_id' => $order['user_id'],
                    'order_id' => $order['id'],
                    'execute_day' => date('Ymd'),
                    'amount'=>$digitalYuan,
                    'days'=>0,
                    'is_finish'=>1,
                    'status'=>3,
                    'type'=>1,
                ]); 
            }else{
                Order::where('id',$order->id)->update(['status'=>4]);
            }
            Db::Commit();
        }catch(\Exception $e){
            Db::rollback();
            Log::error('分红收益异常：'.$order['id'].' '.$e->getMessage(),$e);
            throw $e;
        }
     }*/

    protected function test()
    {
        $data = User::field('id,realname,phone')->whereIn('invite_code', ['4421900', '4263164', '7318805', '3631948', '8762543', '6526978'])->select();
        $countData = [];
        foreach ($data as $user) {
            $countData[$user['realname']]['have'] = 0;
            $countData[$user['realname']]['no'] = 0;
            $countData[$user['realname']]['id'] = $user['id'];
            $countData[$user['realname']]['phone'] = $user['phone'];
            $sub = UserRelation::where('user_id', $user['id'])->where('level', 1)->select();
            foreach ($sub as $item) {
                $count = UserRelation::where('user_id', $item['sub_user_id'])->count();
                if ($count <= 0) {
                    $countData[$user['realname']]['no']++;
                    echo $user['realname'] . "的下级" . $item['sub_user_id'] . "没有下级了\n";
                } else {
                    $countData[$user['realname']]['have']++;
                }
            }
        }
        //print_r($countData);
        foreach ($countData as $k => $v) {
            echo "{$v['id']} {$v['phone']} $k {$v['no']} {$v['have']}\n";
        }
    }

    public function test2()
    {
        $data = [
            '5' => 0,
            '10' => 0,
        ];
        $num = 0;
        User::whereRaw(' id  not in (select user_id from mp_user_relation) ')->chunk(1000, function ($list) use (&$data, &$num) {
            foreach ($list as $key => $item) {
                $num++;
                echo "正在处理第" . ($num) . " 个用户{$key}\n";
                $days =  $this->lianxuSignIn($item);

                if ($days >= 10) {
                    $data['10']++;
                } else if ($days >= 5) {
                    $data['5']++;
                }
            }
        });
        print_r($data);
    }

    public function lianxuSignIn($item)
    {
        $signIns = UserSignin::where('user_id', $item['id'])->order('signin_date asc')->select();
        $date1 = "";
        $signMax = 0;
        $signInDays = 0;
        foreach ($signIns as $signIn) {
            if ($signInDays >= 10) {
                $signMax = $signInDays;
                break;
            }
            if ($date1 != "") {
                $targetDate = date('Y-m-d', strtotime("+1 day", strtotime($date1)));
                if ($targetDate == $signIn['signin_date']) {
                    $signInDays++;
                } else {
                    if ($signInDays > $signMax) {
                        $signMax = $signInDays;
                    }
                    $signInDays = 0;
                }
                $date1 = $signIn['signin_date'];
            } else {
                $date1 = $signIn['signin_date'];
            }
        }
        if ($signInDays > $signMax) {
            $signMax = $signInDays;
        }
        return $signMax + 1;
    }
}
