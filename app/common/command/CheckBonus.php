<?php

// This command has been disabled as it uses multiple deprecated fund types
// which have been removed from the system according to requirements

/*
namespace app\common\command;

use app\model\AssetOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use app\model\Notarization;
use app\model\Order;
use app\model\TaxOrder;
use app\model\project;
use app\model\PassiveIncomeRecord;
use app\model\ShopOrder;
use app\model\User;
use app\model\UserCard;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class CheckBonus extends Command
{

    protected function configure()
    {
        $this->setName('checkBonus')->setDescription('项目分红收益和被动收益，每天的0点1分执行');
    }

    public function execute(Input $input, Output $output)
    {

        $cur_time = strtotime(date('Y-m-d 00:00:00'));
        $time = strtotime(date('Y-m-d 00:00:00'));
        
        // All functionality related to deprecated fund types has been removed
        // Original code commented out as it uses deprecated types
        /*
        $data = Order::whereIn('project_group_id',[1,2])->where('status',2)->where('end_time', '<=', $cur_time)
         ->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus($item);
            }
        }); */
        //养老二期
        /*
        $data = Order::whereIn('project_group_id', [5])->where('status', 2)->where('next_bonus_time', '<=', $cur_time)->chunk(100, function ($list) {
            foreach ($list as $item) {
                $this->bonus_group_2($item);
            }
        }); */

        /*
        $data = Order::whereIn('project_group_id', [13,24])->where('status', 2)->where('next_bonus_time', '<=', $cur_time)->chunk(100, function ($list) {
            foreach ($list as $item) {
                $this->bonus_group_13($item);
            }
        }); */

        /*
        $data = Order::whereIn('project_group_id', [19])->where('status',2)->where('next_bonus_time', '<=', $cur_time)->chunk(100, function ($list) {
            echo count($list)."\n";
            foreach ($list as $item) {
                $this->bonus_group_19($item);
            }
        }); */

        //以旧换新
        /*
        $data = Order::whereIn('project_group_id', [25])->where('status',2)->where('end_time', '<=', $cur_time)->chunk(100, function ($list) {
            //echo count($list)."\n";
            foreach ($list as $item) {
                $this->bonus_group_25($item);
            }
        }); */

        $today = date('Y-m-d');
        /*
        $data = TaxOrder::where('status',2)->where('end_time', '<=', $today)->chunk(100, function ($list) {
            foreach ($list as $item) {
                Db::startTrans();
                try{
                    User::changeInc($item['user_id'], $item['taxes_money'],'large_subsidy', 3, $item['id'], 36, '缴纳税费返还');
                    TaxOrder::where('id',$item['id'])->update(['status'=>3]);
                    Db::commit();
                }catch (Exception $e) {
                    Db::rollback();
                    Log::error('税费订单异常：' . $e->getMessage(), $e);
                    throw $e;
                }
            }
        }); */

        /*
        $data = Notarization::where('status',1)->where('type',0)->where('end_time', '<=', $today)->chunk(100, function ($list) {
            foreach ($list as $item) {
                Db::startTrans();
                try{
                    User::changeInc($item['user_id'], $item['money'],'notarization_balance', 15, $item['id'], 11, '公证资金');
                    Notarization::where('id',$item['id'])->update(['status'=>2]);
                    $card = UserCard::where('user_id', $item['user_id'])->where('status',1)->find();
                    if($card){
                        UserCard::changeCardMoney($item['user_id'],$item['money'],38,13,$item['id'],'公正资金转入' );
                    }
                    Db::commit();
                }catch (Exception $e) {
                    Db::rollback();
                    Log::error('公证资金异常' . $e->getMessage(), []);
                    throw $e;
                }
            }
        }); */
        /*
        $data = Notarization::where('status',1)->where('type',1)->where('end_time', '<=', $today)->chunk(100, function ($list) {
            foreach ($list as $item) {
                Db::startTrans();
                try{
                    User::changeInc($item['user_id'], $item['money'],'bail_balance', 15, $item['id'], 12, '监管金额');
                    Notarization::where('id',$item['id'])->update(['status'=>2]);
                    Db::commit();
                }catch (Exception $e) {
                    Db::rollback();
                    Log::error('监管金额' . $e->getMessage(), []);
                    throw $e;
                }
            }
        }); */

        /*
        $data = UserCard::where('status',1)->chunk(100, function ($list) {
            foreach ($list as $item) {
                Db::startTrans();
                try {
                    $interest = bcdiv(bcmul($item['money'],0.15,2),100,2); 
                    UserCard::where('id', $item['id'])->update(['yesterday_interest' => $interest,'money'=>Db::raw('money + '.$interest)]);
                    $sn = build_order_sn($item['user_id'],'CI');
                    \app\model\UserBalanceLog::create([
                        'user_id' => $item['user_id'],
                        'type' => 39,
                        'log_type' => 13,
                        'relation_id' => $item['id'],
                        'before_balance' => $item['money'],
                        'change_balance' => $interest,
                        'after_balance' => $item['money'] + $interest,
                        'remark' => '银行卡资金利息',
                        'admin_user_id' => 0,
                        'status' => 1,
                        'order_sn'=>$sn,
                    ]);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    Log::error('银行卡资金异常：' . $e->getMessage(), []);
                    throw $e;
                }
            }
        }); */
    }


    // All bonus_group methods have been commented out as they use deprecated fund types
    /*
    public function bonus_group_25($order)
    {
        Db::startTrans();
        try {
            $cur_time = strtotime(date('Y-m-d 00:00:00'));
            $text = "{$order['project_name']}";
            if ($order['end_time'] <= $cur_time) {
                User::changeInc($order['user_id'], $order['sum_amount'], 'team_bonus_balance', 6, $order['id'], 3, $text . '');
                User::changeInc($order['user_id'],$order['single_amount'],'team_bonus_balance',6,$order['id'],3,$text.'申报费用返还');

                Order::where('id', $order->id)->update(['status' => 4]);
                // 结束项目分红
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage());
            throw $e;
        }

    }


    //提振消费
    public function bonus_group_21($order)
    {
        Db::startTrans();
        try {
            $cur_time = strtotime(date('Y-m-d 00:00:00'));
            $text = "{$order['project_name']}";
            // 到期需要返还申33报费用
            if ($order['end_time'] <= $cur_time) {
                User::changeInc($order['user_id'], $order['sum_amount'], 'team_bonus_balance', 6, $order['id'], 3, $text . '');
                Order::where('id', $order->id)->update(['status' => 4]);
                // 结束项目分红
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage());
            throw $e;
        }
    }

    public function bonus_group_19($order)
    {
        Db::startTrans();
        try {
            $cur_time = strtotime(date('Y-m-d 00:00:00'));
            $text = "{$order['project_name']}";
            $income = $order['daily_bonus_ratio'];
            if ($income > 0) {
                // 检查当天是否已经分红
                $isBonusToday = $this->processPassiveIncomeInterval($order, $income,'weekly');
                if($isBonusToday){
                    User::changeInc($order['user_id'], $income, 'large_subsidy', 6, $order['id'], 3, $text . '每周粮油补贴');
                }
            }
            // 到期需要返还申报费用
            //if($order['end_time'] <= $cur_time) {
            /*                     if($order['sum_amount'] > 0){
                        User::changeInc($order['user_id'],$order['sum_amount'],'team_bonus_balance',6,$order['id'],3,$text.'补助资金');
                    } */
            //        User::changeInc($order['user_id'],$order['single_amount'],'team_bonus_balance',6,$order['id'],3,$text.'申报费用返还');
                   

            //        Order::where('id',$order->id)->update(['status'=>4]);
                   
            // } 
            Db::commit();

        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage());
            throw $e;
        }
    }


    //普惠活动
    public function bonus_group_14($order)
    {
        Db::startTrans();
        try {
            $cur_time = strtotime(date('Y-m-d 00:00:00'));
            $text = "{$order['project_name']}";
            // 到期需要返还申报费用
            if ($order['end_time'] <= $cur_time) {
                User::changeInc($order['user_id'], $order['sum_amount'], 'team_bonus_balance', 6, $order['id'], 3, $text . '');
                Order::where('id', $order->id)->update(['status' => 4]);
                // 结束项目分红
            }
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage());
            throw $e;
        }
    }


    //反制裁强国补贴
    public function bonus_group_13($order)
    {
        Db::startTrans();
        try {
            $cur_time = strtotime(date('Y-m-d 00:00:00'));
            $text = "{$order['project_name']}";
            $income = $order['daily_bonus_ratio'];
            if ($income > 0) {
                // 检查当天是否已经分红
                $isBonusToday = $this->processPassiveIncome($order, $income);
                if($isBonusToday){
                    User::changeInc($order['user_id'], $income, 'team_bonus_balance', 6, $order['id'], 3, $text . '每日补助资金');
                }
            }
            // 到期需要返还申报费用
            if($order['end_time'] <= $cur_time) {
            /*                     if($order['sum_amount'] > 0){
                        User::changeInc($order['user_id'],$order['sum_amount'],'team_bonus_balance',6,$order['id'],3,$text.'补助资金');
                    } */
                    User::changeInc($order['user_id'],$order['single_amount'],'team_bonus_balance',6,$order['id'],3,$text.'申报费用返还');
                   

                    Order::where('id',$order->id')->update(['status'=>4]);
                   
             } 
            Db::commit();

        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage());
            throw $e;
        }
    }

    //反洗钱专项
    public function bonus_group_12($order)
    {
        Db::startTrans();
        try {
            $cur_time = strtotime(date('Y-m-d 00:00:00'));
            $text = "{$order['project_name']}";
            // 到期需要返还申报费用
            if ($order['end_time'] <= $cur_time) {
                User::changeInc($order['user_id'], $order['single_amount'], 'large_subsidy', 6, $order['id'], 7, $text . '申报费用返还');

                if ($order['gift_integral'] > 0) {
                    User::changeInc($order['user_id'], $order['gift_integral'], 'integral', 6, $order['id'], 2, $text . '普惠积分');
                }
                Order::where('id', $order->id())->update(['status' => 4]);
                // 结束项目分红
            }
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage());
            throw $e;
        }
    }


    //教育补助三期
    public function bonus_group_11($order)
    {
        Db::startTrans();
        try {
            $cur_time = strtotime(date('Y-m-d 00:00:00'));
            $text = "{$order['project_name']}";
            // 到期需要返还申报费用
            if ($order['end_time'] <= $cur_time) {
                if ($order['sum_amount'] > 0) {
                    User::changeInc($order['user_id'], $order['sum_amount'], 'large_subsidy', 6, $order['id'], 7, $text . '补助资金');
                }
                User::changeInc($order['user_id'], $order['single_amount'], 'large_subsidy', 6, $order['id'], 7, $text . '申报费用返还');

                if ($order['gift_integral'] > 0) {
                    User::changeInc($order['user_id'], $order['gift_integral'], 'integral', 6, $order['id'], 2, $text . '普惠积分');
                }
                Order::where('id', $order->id())->update(['status' => 4]);


                // 结束项目分红
            }
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage());
            throw $e;
        }
    }

    //体重管理
    public function bonus_group_10($order)
    {
        Db::startTrans();
        try {
            $cur_time = strtotime(date('Y-m-d 00:00:00'));
            $text = "{$order['project_name']}";
            // 到期需要返还申报费用
            if ($order['end_time'] <= $cur_time) {
                if ($order['daily_bonus_ratio'] > 0) {
                    User::changeInc($order['user_id'], $order['daily_bonus_ratio'], 'large_subsidy', 6, $order['id'], 7, $text . '补助资金');
                }

                User::changeInc($order['user_id'], $order['single_amount'], 'large_subsidy', 6, $order['id'], 7, $text . '申报费用返还');

                if ($order['gift_integral'] > 0) {
                    User::changeInc($order['user_id'], $order['gift_integral'], 'integral', 6, $order['id'], 2, $text . '普惠积分');
                }
                Order::where('id', $order->id())->update(['status' => 4]);


                // 结束项目分红
            }
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('分红收益异常：' . $e->getMessage());
            throw $e;
        }
    }



    /**
     * 养老二期
     */
    public function bonus_group_2($order)
    {
        Db::startTrans();
        try {
            $next_bonus_time = strtotime(date('Y-m-d 00:00:00', strtotime('+ 1day')));

            $cur_time = strtotime(date('Y-m-d 00:00:00'));

            $text = "{$order['project_name']}";
            $income = $order['daily_bonus_ratio'];
            // 分红钱
            if ($income > 0) {
                User::changeInc($order['user_id'], $income, 'large_subsidy', 6, $order['id'], 7, $text . '补助资金');
            }
            // 分红积分
            if ($order['gift_integral'] > 0) {
                User::changeInc($order['user_id'], $order['gift_integral'], 'integral', 6, $order['id'], 2, $text . '普惠积分');
            }
            //$gain_bonus = bcadd($order['gain_bonus'], $income, 2);
            $gain_bonus = $order['gain_bonus']+$income;
            Order::where('id', $order->id)->update(['next_bonus_time' => $next_bonus_time, 'gain_bonus' => $gain_bonus]);

            // 到期需要返还申报费用
            if ($order['end_time'] <= $cur_time) {
                // 返还前
                $amount = $order['single_amount'];
                if ($amount > 0) {
                    User::changeInc($order['user_id'], $amount, 'large_subsidy', 6, $order['id'], 7, $text . '返还申报费用');
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



    protected function processPassiveIncome($order, $income)
    {

        // 检查当天是否已经分红
        $currentDay = date('Ymd');
        $passiveIncome = PassiveIncomeRecord::where('order_id', $order['id'])
            ->where('user_id', $order['user_id'])
            ->where('execute_day', $currentDay)
            ->find();
        if (!empty($passiveIncome)) {
            // 当天已经分红
            return false;
        }

        // 获取最近一次分红记录
        $passiveIncome = PassiveIncomeRecord::where('order_id', $order['id'])
            ->where('user_id', $order['user_id'])
            ->order('execute_day', 'desc')
            ->find();

        $day = 0;
        if ($passiveIncome) {
            if ($passiveIncome['days'] >= $order['period']) {
                // 已经分红完毕
                return false;
            }
            $day = $passiveIncome['days'];
        }

        // 增加分红天数
        $day += 1;

        // 创建新的分红记录
        PassiveIncomeRecord::create([
            'project_group_id' => $order['project_group_id'],
            'user_id' => $order['user_id'],
            'order_id' => $order['id'],
            'execute_day' => $currentDay,
            'amount' => $income,
            'days' => $day,
            'is_finish' => 1,
            'status' => 3,
            'type' => 1,
        ]);

        // 更新订单的下一次分红时间和累计分红金额
        $nextBonusTime = strtotime('+1 day', strtotime(date('Y-m-d H:i:s', $order['next_bonus_time'])));
        $gainBonus = bcadd($order['gain_bonus'], $income, 2);
        Order::where('id', $order['id'])->update([
            'next_bonus_time' => $nextBonusTime,
            'gain_bonus' => $gainBonus,
        ]);


        return true;
    }


    protected function processPassiveIncomeInterval($order, $income, $interval = 'daily')
    {
        // 根据间隔类型确定检查日期和下次分红时间
        $currentDate = $this->getCurrentPeriodKey($interval);
        
        // 检查当前周期是否已经分红
        $passiveIncome = PassiveIncomeRecord::where('order_id', $order['id'])
            ->where('user_id', $order['user_id'])
            ->where('execute_day', $currentDate)
            ->find();
            
        if (!empty($passiveIncome)) {
            // 当天已经分红
            return false;
        }

        // 获取最近一次分红记录
        $passiveIncome = PassiveIncomeRecord::where('order_id', $order['id'])
            ->where('user_id', $order['user_id'])
            ->order('execute_day', 'desc')
            ->find();

        $day = 0;
        if ($passiveIncome) {
            if ($passiveIncome['days'] >= $order['period']) {
                // 已经分红完毕
                return false;
            }
            $day = $passiveIncome['days'];
        }

        // 增加分红天数
        $day += 1;

        // 创建新的分红记录
        PassiveIncomeRecord::create([
            'project_group_id' => $order['project_group_id'],
            'user_id' => $order['user_id'],
            'order_id' => $order['id'],
            'execute_day' => $currentDate,
            'amount' => $income,
            'days' => $day,
            'is_finish' => 1,
            'status' => 3,
            'type' => 1,
        ]);

        // 更新订单的下一次分红时间和累计分红金额
        $nextBonusTime = $this->getNextBonusTime($interval, strtotime(date('Y-m-d H:i:s', $order['next_bonus_time'])));
        $gainBonus = bcadd($order['gain_bonus'], $income, 2);
        Order::where('id', $order['id'])->update([
            'next_bonus_time' => $nextBonusTime,
            'gain_bonus' => $gainBonus,
        ]);


        return true;
    }
    */
}
