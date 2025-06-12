<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use app\model\Order;
use app\model\project;
use app\model\PassiveIncomeRecord;
use app\model\ShopOrder;
use app\model\User;
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
        /*         $data = Order::whereIn('project_group_id',[1,2])->where('status',2)->where('end_time', '<=', $cur_time)
         ->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus($item);
            }
        }); */
        //养老二期
        $data = Order::whereIn('project_group_id', [5])->where('status', 2)->where('next_bonus_time', '<=', $cur_time)->chunk(100, function ($list) {
            foreach ($list as $item) {
                $this->bonus_group_2($item);
            }
        });

        /*         $data = Order::whereIn('project_group_id',[6])->where('status',2)->where('end_time', '<=', $cur_time)->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus_group_6($item);
            }
        });

        $data = Order::whereIn('project_group_id',[7])->where('status',2)->where('end_time', '<=', $cur_time)->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus_group_7($item);
            }
        });
        $data = Order::whereIn('project_group_id',[8])->where('status',2)->where('end_time', '<=', $cur_time)->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus_group_8($item);
            }
        });
         $data = Order::whereIn('project_group_id',[9])->where('status',2)->where('end_time', '<=', $cur_time)->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus_group_9($item);
            }
        }); */

/*         $data = Order::whereIn('project_group_id', [10])->where('status', 2)->where('end_time', '<=', $cur_time)->chunk(100, function ($list) {
            foreach ($list as $item) {
                $this->bonus_group_10($item);
            }
        });

        $data = Order::whereIn('project_group_id', [11])->where('status', 2)->where('end_time', '<=', $cur_time)->chunk(100, function ($list) {
            foreach ($list as $item) {
                $this->bonus_group_11($item);
            }
        });

        $data = Order::whereIn('project_group_id', [12])->where('status', 2)->where('end_time', '<=', $cur_time)->chunk(100, function ($list) {
            foreach ($list as $item) {
                $this->bonus_group_12($item);
            }
        });
 */
        $data = Order::whereIn('project_group_id', [13])->where('status', 2)->where('next_bonus_time', '<=', $cur_time)->chunk(100, function ($list) {
            foreach ($list as $item) {
                $this->bonus_group_13($item);
            }
        });

/*         $data = Order::whereIn('project_group_id', [14])->where('status', 2)->where('end_time', '<=', $cur_time)->chunk(100, function ($list) {
            foreach ($list as $item) {
                $this->bonus_group_14($item);
            }
        });
 */
        $data = Order::whereIn('project_group_id', [19])->where('status',2)->where('next_bonus_time', '<=', $cur_time)->chunk(100, function ($list) {
            echo count($list)."\n";
            foreach ($list as $item) {
                $this->bonus_group_19($item);
            }
        });
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
                    User::changeInc($order['user_id'], $income, 'team_bonus_balance', 6, $order['id'], 3, $text . '每周粮油补贴');
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
                    

                    Order::where('id',$order->id)->update(['status'=>4]);
                    
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
            // 当前周期已经分红
            return false;
        }

        // 获取最近一次分红记录
        $passiveIncome = PassiveIncomeRecord::where('order_id', $order['id'])
            ->where('user_id', $order['user_id'])
            ->order('execute_day', 'desc')
            ->find();

        $periodCount = 0;
        if ($passiveIncome) {
/*             if ($passiveIncome['days'] >= $order['period']) {
                // 已经分红完毕
                return false;
            } */
            $periodCount = $passiveIncome['days'];
        }

        // 增加分红周期数
        $periodCount += 1;

        // 创建新的分红记录
        PassiveIncomeRecord::create([
            'project_group_id' => $order['project_group_id'],
            'user_id' => $order['user_id'],
            'order_id' => $order['id'],
            'execute_day' => $currentDate,
            'amount' => $income,
            'days' => $periodCount,
            'is_finish' => 1,
            'status' => 3,
            'type' => 1,
        ]);

        // 更新订单的下一次分红时间和累计分红金额
        $nextBonusTime = $this->getNextBonusTime($order['next_bonus_time'], $interval);
        //$gainBonus = bcadd($order['gain_bonus'], $income, 2);
        $gainBonus = $order['gain_bonus']+$income;
        Order::where('id', $order['id'])->update([
            'next_bonus_time' => $nextBonusTime,
            'gain_bonus' => $gainBonus,
        ]);

        return true;
    }

    /**
     * 根据间隔类型获取当前周期的标识
     * @param string $interval 间隔类型：daily, weekly, monthly
     * @return string
     */
    private function getCurrentPeriodKey($interval)
    {
        switch ($interval) {
            case 'weekly':
                // 返回当前周的年份+周数，如：202324（2023年第24周）
                return date('oW');
            case 'monthly':
                // 返回当前月份，如：202306（2023年6月）
                return date('Ym');
            case 'daily':
            default:
                // 返回当前日期，如：20230615
                return date('Ymd');
        }
    }

    /**
     * 根据间隔类型计算下次分红时间
     * @param int $currentBonusTime 当前分红时间戳
     * @param string $interval 间隔类型：daily, weekly, monthly
     * @return int
     */
    private function getNextBonusTime($currentBonusTime, $interval)
    {
        $currentDate = date('Y-m-d H:i:s', $currentBonusTime);
        
        switch ($interval) {
            case 'weekly':
                // 下周同一天
                return strtotime('+1 week', strtotime($currentDate));
            case 'monthly':
                // 下月同一天
                return strtotime('+1 month', strtotime($currentDate));
            case 'daily':
            default:
                // 明天
                return strtotime('+1 day', strtotime($currentDate));
        }
    }

    public function bonus_shop($order)
    {
        Db::startTrans();
        try {

            if ($order['gain_bonus'] > 0) {
                User::changeInc($order['user_id'], $order['shop_profit'], 'digit_balance', 32, $order['id'], 6);
            } else {
                User::changeInc($order['user_id'], $order['shop_profit'], 'digit_balance', 32, $order['id'], 6);
                User::changeInc($order['user_id'], $order['flow_amount'], 'digit_balance', 31, $order['id'], 6);
                User::changeInc($order['user_id'], -$order['flow_amount'], 'flow_amount', 33, $order['id'], 7);
            }

            $nextMonthTenth = strtotime('+1 month', strtotime(date("Y-m") . '-10'));
            $gain_bonus = $order['gain_bonus'] + $order['shop_profit'];
            ShopOrder::where('id', $order['id'])->update(['next_bonus_time' => $nextMonthTenth, 'gain_bonus' => $gain_bonus]);
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('商城流转异常：' . $e->getMessage(), $e);
            throw $e;
        }
    }

    // public function execute(Input $input, Output $output)
    // {   


    //     $cur_time = strtotime(date('Y-m-d 00:00:00'));
    //      $data2 = Order::whereIn('project_group_id',[1,2,3])->where('status',2)->where('next_bonus_time', '<=', $cur_time)
    //     ->chunk(100, function($list) {
    //         foreach ($list as $item) {
    //             $this->digiYuan($item);
    //         }
    //     }); 

    //     // 分红收益
    //     $data = Order::whereIn('project_group_id',[1,2,3])->where('status',2)->where('end_time', '<=', $cur_time)
    //     ->chunk(100, function($list) {
    //         foreach ($list as $item) {
    //             $this->bonus($item);
    //         }
    //     });

    //     //456期项目
    //     $data = Order::whereIn('project_group_id',[4,6])->where('status',2)->where('end_time', '<=', $cur_time)
    //     ->chunk(100, function($list) {
    //         foreach ($list as $item) {
    //             $this->bonus4($item);
    //         }
    //     });
    //     //二期新项目结束之后每月分红
    //     $this->secondBonus();
    //     //$this->widthdrawAudit();
    //     return true;
    // }

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

    public function widthdrawAudit()
    {
        Capital::where('status', 1)->where('type', 2)->whereIn('log_type', [3, 6])->where('end_time', '<=', time())->update(['status' => 2]);
    }

    public function secondBonus()
    {
        $yesterday = date("Y-m-d", strtotime("-1 day"));
        $day = date("d", strtotime($yesterday));
        $month = date("m", strtotime($yesterday));
        Order::where('status', 4)->where('project_group_id', 2)->whereRaw("DAYOFMONTH(created_at)=$day")->chunk(100, function ($list) use ($month) {
            $time = time();
            $nowMonth = intval(date("m", $time));

            foreach ($list as $item) {
                $endMonth = intval(date("m", $item['end_time']));
                if ($nowMonth > $endMonth) {
                    $passiveIncome = PassiveIncomeRecord::where('order_id', $item['id'])->where('user_id', $item['user_id'])->where('execute_day', date('Ymd'))->where('type', 2)->find();
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
                            'execute_day' => date('Ymd'),
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
            }
        });
    }

    public function bonus($order)
    {
        Db::startTrans();
        try {
            //User::changeInc($order['user_id'],$order['sum_amount'],'digital_yuan_amount',6,$order['id'],3);
            $text = "{$order['project_name']}收益";
            $income = $order['single_amount'] + $order['sum_amount'];
            User::changeInc($order['user_id'], $income, 'team_bonus_balance', 6, $order['id'], 3, $text);
            if ($order['gift_integral'] > 0) {
                User::changeInc($order['user_id'], $order['gift_integral'], 'integral', 6, $order['id'], 2, $text);
            }
            $subsidyAmount = $order['single_amount'] * $order['bonus_multiple'];
            User::changeInc($order['user_id'], $subsidyAmount, 'poverty_subsidy_amount', 6, $order['id'], 5, $text);
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

    public function bonus_asset_return($order)
    {
        Db::startTrans();
        try {
            $amount = config('map.asset_recovery')[$order['type']]['amount'];
            User::changeInc($order['user_id'], $amount, 'digital_yuan_amount', 12, $order['id'], 3);
            AssetOrder::where('id', $order->id)->update(['status' => 4]);
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('资产恢复退回保证金：' . $e->getMessage(), $e);
            throw $e;
        }
    }

    public function bonus_asset_reward($order)
    {
        Db::startTrans();
        try {
            // User::changeInc($order['user_id'],$order['balance'],'digital_yuan_amount',27,$order['id'],3);
            User::changeInc($order['user_id'], $order['digital_yuan_amount'], 'digital_yuan_amount', 27, $order['id'], 3);
            // User::changeInc($order['user_id'],$order['poverty_subsidy_amount'],'poverty_subsidy_amount',27,$order['id'],3);
            AssetOrder::where('id', $order->id)->update(['reward_status' => 1]);
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('资产恢复异常：' . $e->getMessage(), $e);
            throw $e;
        }
    }

    public function bonus_ensure_return($order)
    {
        Db::startTrans();
        try {
            User::changeInc($order['user_id'], $order['amount'], 'digital_yuan_amount', 12, $order['id'], 3);
            EnsureOrder::where('id', $order->id)->update(['status' => 4]);
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('共富保障退回保证金：' . $e->getMessage(), $e);
            throw $e;
        }
    }

    public function bonus_ensure_reward($order)
    {
        Db::startTrans();
        try {
            User::changeInc($order['user_id'], $order['receive_amount'], 'digital_yuan_amount', 28, $order['id'], 3);
            EnsureOrder::where('id', $order->id)->update(['reward_status' => 1]);
            Db::Commit();
        } catch (Exception $e) {
            Db::rollback();

            Log::error('共富保障异常：' . $e->getMessage(), $e);
            throw $e;
        }
    }

    //     public function bonus($order){
    //         Db::startTrans();
    //         try{
    //             User::changeInc($order['user_id'],$order['sum_amount'],'income_balance',6,$order['id'],6);
    //             //User::changeInc($order['user_id'],$order['single_gift_digital_yuan'],'digital_yuan_amount',5,$order['id'],3);
    //             Order::where('id',$order->id)->update(['status'=>4]);
    // /*             if($order['project_group_id']==2){

    //             } */
    //             Db::Commit();
    //         }catch(Exception $e){
    //             Db::rollback();

    //             Log::error('分红收益异常：'.$e->getMessage(),$e);
    //             throw $e;
    //         }
    //     }

    public function bonus4($order)
    {
        Db::startTrans();
        try {
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

    protected function digiYuan($order)
    {
        $cur_time = strtotime(date('Y-m-d 00:00:00'));
        $user = User::where('id', $order->user_id)->where('status', 1)->find();
        if (is_null($user)) {
            //用户不存在,禁用
            return;
        }

        /*         if($order->end_time < $cur_time){
            //结束分红
            Order::where('id',$order->id)->update(['status'=>4]);
            return;
        } */
        $day = 0;
        $passiveIncome = PassiveIncomeRecord::where('order_id', $order['id'])->where('user_id', $order['user_id'])->where('execute_day', date('Ymd'))->find();
        if (!empty($passiveIncome)) {
            //已经分红

            return;
        }
        $passiveIncome = PassiveIncomeRecord::where('order_id', $order['id'])->where('user_id', $order['user_id'])->order('execute_day', 'desc')->find();
        if (!$passiveIncome) {
            $day = 0;
        } else if ($passiveIncome['days'] >= $order['period']) {
            //已经分红完毕
            return;
        } else {
            $day = $passiveIncome['days'];
        }
        $day += 1;
        $amount = $order['single_gift_digital_yuan'];
        Db::startTrans();
        try {
            PassiveIncomeRecord::create([
                'project_group_id' => $order['project_group_id'],
                'user_id' => $order['user_id'],
                'order_id' => $order['id'],
                'execute_day' => date('Ymd'),
                'amount' => $amount,
                'days' => $day,
                'is_finish' => 1,
                'status' => 3,
                'type' => 1,
            ]);
            $next_bonus_time = strtotime('+1 day', strtotime(date('Y-m-d H:i:s', $order['next_bonus_time'])));
            $gain_bonus = bcadd($order['gain_bonus'], $amount, 2);
            Order::where('id', $order['id'])->update(['next_bonus_time' => $next_bonus_time, 'gain_bonus' => $gain_bonus]);
            User::changeInc($order['user_id'], $amount, 'digital_yuan_amount', 5, $order['id'], 3, '每日国务院津贴');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return true;
    }

    protected function fixedMill($order)
    {
        $cur_time = strtotime(date('Y-m-d 00:00:00'));
        $user = User::where('id', $order->user_id)->where('status', 1)->find();
        if (is_null($user)) {
            //用户不存在,禁用
            return;
        }

        if ($order->end_time < $cur_time) {
            //结束分红
            Order::where('id', $order->id)->update(['status' => 4]);
            return;
        }
        $max_day = PassiveIncomeRecord::where('order_id', $order['id'])->max('days');
        if ($max_day >= 0) {
            $max_day = $max_day + 1;
        } else {
            $max_day = 1;
        }
        $amount = bcmul($order['single_amount'], $order['daily_bonus_ratio'] / 100, 2);
        $amount = bcmul($amount, $order['buy_num'], 2);
        Db::startTrans();
        try {
            PassiveIncomeRecord::create([
                'user_id' => $order['user_id'],
                'order_id' => $order['id'],
                'execute_day' => date('Ymd'),
                'amount' => $amount,
                'days' => $max_day,
                'is_finish' => 1,
                'status' => 3,
            ]);
            if (empty($order['dividend_cycle'])) {
                $dividend_cycle = '1 day';
            } else {
                $dividend_cycle = $order['dividend_cycle'];
            }
            if (empty($order['next_bonus_time']) || $order['next_bonus_time'] == 0) {
                $order['next_bonus_time'] = $cur_time;
            }
            $next_bonus_time = strtotime('+' . $dividend_cycle, strtotime($order['next_bonus_time']));
            $gain_bonus = bcadd($order['gain_bonus'], $amount, 2);
            Order::where('id', $order['id'])->update(['next_bonus_time' => $next_bonus_time, 'gain_bonus' => $gain_bonus]);
            if ($order->period <= $max_day) {
                //结束分红
                Order::where('id', $order->id)->update(['status' => 4]);
            }
            if ($order['settlement_method'] == 1)
                User::changeBalance($order['user_id'], $amount, 6, $order['id'], 3);
            else
                User::changeBalance($order['user_id'], $amount, 6, $order['id']);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return true;
    }
}
