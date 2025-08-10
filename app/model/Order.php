<?php

namespace app\model;

use think\Model;
use think\facade\Db;

use Exception;

class Order extends Model
{
    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.order')['status_map'];
        return $map[$data['status']] ?? '';
    }

    public function getPayMethodTextAttr($value, $data)
    {
        $map = config('map.order')['pay_method_map'];
        return $map[$data['pay_method']] ?? '';
    }

    public function getEquityStatusTextAttr($value, $data)
    {
        $map = config('map.order')['equity_status_map'];
        return $map[$data['equity_status']] ?? '';
    }

    public function getDigitalYuanStatusTextAttr($value, $data)
    {
        $map = config('map.order')['digital_yuan_status_map'];
        return $map[$data['digital_yuan_status']] ?? '';
    }

    public function getPayDateAttr($value, $data)
    {
        if (!empty($data['pay_time'])) {
            return date('Y-m-d H:i:s', $data['pay_time']);
        }
        return '';
    }

    public function getSaleDateAttr($value, $data)
    {
        if (!empty($data['sale_time'])) {
            return date('Y-m-d H:i:s', $data['sale_time']);
        }
        return '';
    }

    public function getEndDateAttr($value, $data)
    {
        if (!empty($data['end_time'])) {
            return date('Y-m-d H:i:s', $data['end_time']);
        }
        return '';
    }

    public function getExchangeEquityDateAttr($value, $data)
    {
        if (!empty($data['exchange_equity_time'])) {
            return date('Y-m-d H:i:s', $data['exchange_equity_time']);
        }
        return '';
    }

    public function getExchangeYuanDateAttr($value, $data)
    {
        if (!empty($data['exchange_yuan_time'])) {
            return date('Y-m-d H:i:s', $data['exchange_yuan_time']);
        }
        return '';
    }

    public function getPayStatusTextAttr($value, $data)
    {
        if ($data['status'] > 1) {
            if ($data['is_admin_confirm'] == 1) {
                return '成功,未通知';
            }
            return '成功,已通知';
        }
        return '未支付';
    }

    public function getEquityExchangePriceAttr($value)
    {
        $module = app('http')->getName();
        if ($module !== 'admin' && $value == 0) {
            $chart = KlineChart::where('date', date('Y-m-d'))->find();
            if (empty($chart)) {
                return 0;
            }
            $chart_data = json_decode($chart['chart_data'], true);
            $chart_data = array_column($chart_data, 'value', 'time');
            $time = (int)date('Hi');
            if (($time >= 930 && $time <= 1130) || ($time >= 1300 && $time <= 1500)) {
                $minute = date('i');
                $hour = date('H');
                $arr = str_split($minute);
                $start = $hour . ':' . $arr[0] . '0';
                if ($arr[0] == 5) {
                    $str1 = $hour + 1;
                    $str1 = sprintf("%02d", $str1);
                    $end = $str1 . ':00';
                } else {
                    $str1 = $arr[0] + 1;
                    $end = $hour . ':' . $str1 . '0';
                }

                $diff = $chart_data[$start] - $chart_data[$end];
                return round($chart_data[$start] + $diff / 10 * ($arr[1]), 4);
            } else {
                if ($time < 930) {
                    $ychart = KlineChart::where('date', date('Y-m-d', strtotime('-1 day')))->find();
                    if (empty($ychart)) {
                        return 0;
                    }
                    $ychart_data = json_decode($ychart['chart_data'], true);
                    $ychart_data = array_column($ychart_data, 'value', 'time');
                    return $ychart_data['15:00'];
                } elseif ($time > 1130 && $time < 1300) {
                    return $chart_data["11:30"];
                } else {
                    return $chart_data["15:00"];
                }
            }
        }

        return $value;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function getDailyBonusAttr($value, $data)
    {
        if (!empty($data['daily_bonus_ratio']) && !empty($data['buy_num'])) {
            return round($data['daily_bonus_ratio'] * $data['buy_num'], 2);
        }

        return 0;
    }

    public function getTotalBonusAttr($value, $data)
    {
        if (!empty($data['daily_bonus_ratio']) && !empty($data['buy_num'])) {
            return round($data['daily_bonus_ratio'] * $data['buy_num'] * $data['period'], 2);
        }

        return 0;
    }

    public function getBuyAmountAttr($value, $data)
    {
        if ($data['pay_method'] != 5) {
            return round($data['single_amount'] * $data['buy_num'], 2);
        }

        return 0;
    }

    public function getBuyIntegralAttr($value, $data)
    {
        if ($data['pay_method'] == 5) {
            return round($data['gift_integral'] * $data['buy_num'], 2);
        }

        return 0;
    }

    public function getEquityAttr($value, $data)
    {
        return $data['single_gift_equity'] * $data['buy_num'];
    }

    // public function getDigitalYuanAttr($value, $data)
    // {
    //     return $data['single_gift_digital_yuan']*$data['buy_num'];
    // }
    public function getDigitalYuanAttr($value, $data)
    {
        return $data['single_gift_digital_yuan'];
    }

    public function getWaitReceivePassiveIncomeAttr($value, $data)
    {
        // $s = PassiveIncomeRecord::where('order_id', $data['id'])->select();
        // $result = 0;
        // if(!empty($s)){
        //     foreach($s as $v){
        //       $result += $v['amount'];
        //     }
        // }
        // return round($result,2);
        // $num = Order::where('id',$data['id'])->value('buy_num');
        // $WaitReceivePassiveIncome = PassiveIncomeRecord::where('order_id', $data['id'])->where('status', 2)->value('amount');

        // return round($num*$WaitReceivePassiveIncome, 2);
        return round(PassiveIncomeRecord::where('order_id', $data['id'])->where('status', 2)->value('amount') * $data['buy_num'], 2);
    }

    // public function getTotalPassiveIncomeAttr($value, $data)
    // {
    //     return round(PassiveIncomeRecord::where('order_id', $data['id'])->value('amount'), 2);
    // }

    public function getTotalPassiveIncomeAttr($value, $data)
    {
        return round(PassiveIncomeRecord::where('order_id', $data['id'])->value('amount') * $data['buy_num'], 2);
    }

    public static function orderPayComplete($order_id, $project, $user_id,$is_gift =0)
    {
        $order = Order::where('id', $order_id)->find();
        $is_subsidy = 0;
        if($order['project_group_id']==10){
            $user = User::where('id', $order['user_id'])->field('id,income_balance')->find();
            $text = $order['project_name'].'-';
            if($user['income_balance']>=$order['sum_amount']){
                User::changeInc($order['user_id'], -$order['sum_amount'],'income_balance',29,$order['id'],4,'释放民生养老金');
                User::changeInc($order['user_id'],$order['sum_amount'],'team_bonus_balance',6,$order['id'],3,$text.'释放民生养老金');
                $is_subsidy = 1;
            }else if($user['income_balance']>0){
                User::changeInc($order['user_id'], -$user['income_balance'],'income_balance',29,$order['id'],4,'释放民生养老金');
                User::changeInc($order['user_id'],$user['income_balance'],'team_bonus_balance',6,$order['id'],3,$text.'释放民生养老金');
                $is_subsidy = 1;
            }
        }

        if($order['project_group_id']==12){
            $text = $order['project_name'].'-';
            if($order['daily_bonus_ratio']>0){
                User::changeInc($order['user_id'],$order['daily_bonus_ratio'],'team_bonus_balance',6,$order['id'],3,$text.'补助资金');
            }
            if($order['allow_withdraw_money']>0){
                User::changeInc($order['user_id'],$order['allow_withdraw_money'],'allow_withdraw_money',6,$order['id'],8,$text.'提现额度');
            }
        }
        if(in_array($order['project_group_id'],[13,16,24])){
            $text = $order['project_name'].'-';
            if($order['sum_amount']>0){
                User::changeInc($order['user_id'],$order['sum_amount'],'large_subsidy',6,$order['id'],7,$text.'民生补助资金');
            }
        }

        if($order['project_group_id'] == 26){
            $text = $order['project_name'].'-';
            if($order['sum_amount']>0){
                $user = User::where('id', $order['user_id'])->field('id,integral')->find();
                $integral = $user['integral'];
                if($user['integral']>$order['sum_amount']){
                    $integral = $order['sum_amount'];
                }
                User::changeInc($order['user_id'], -$integral, 'integral', 6, $order['id'], 2, $text.'');
                User::changeInc($order['user_id'],$integral,'team_bonus_balance',6,$order['id'],3,$text.'');
            }
            $user::changeInc($order['user_id'],$order['single_amount'],'team_bonus_balance',6,$order['id'],3,$text.'申报费用返还');
            
        }

         if($order['project_group_id']==32){
            $text = $order['project_name'].'-';
            if($order['daily_bonus_ratio']>0){
                User::changeInc($order['user_id'],$order['daily_bonus_ratio'],'team_bonus_balance',6,$order['id'],3,$text.'立返申报费');
            }
         }


        //if ($project['project_group_id'] == 1) {
            $period = $project['period']+1;
            $end_time = strtotime("+{$period} day", strtotime(date('Y-m-d')));
            //$next_bonus_time = $project['review_period'] * 3600;
            $next_bonus_time = strtotime("+1 day", strtotime(date('Y-m-d')));
            if($order['project_group_id'] == 19){
                $nextDate = date('Y-m-d', strtotime($order['created_at']));
                $next_bonus_time = strtotime($nextDate . ' +1 week');
            }
            Order::where('id', $order['id'])->update([
                'status' => 2,
                'pay_time' => time(),
                'end_time' => $end_time,
                'gain_bonus' => 0,
                'next_bonus_time' => $next_bonus_time, //$next_bonus_time + $order['period']*24*3600,
                'is_subsidy' => $is_subsidy,
                //'equity_status' => 2,
                //'digital_yuan_status' => 2
            ]);
        //}
        if($project['lottery_num']>0){
            //Db::table('mp_user_lottery')->where('user_id',$user_id)->inc('lottery_num',$project['lottery_num']);
            UserLottery::lotteryInc($user_id, $project['lottery_num'],3,0,$order['id']);
        }



        if($is_gift == 0){
            //购买产品和恢复资产用户激活
            if ($order['user']['is_active'] == 0 && $project['single_amount']>0) {
                User::where('id', $order['user_id'])->update(['is_active' => 1, 'active_time' => time()]);
                // 下级用户激活
                UserRelation::where('sub_user_id', $order['user_id'])->update(['is_active' => 1]);
                //UserLottery::lotteryInc($order['user']['up_user_id'], 3,3,0,$order['id']);
            }

            User::where('id', $user_id)->inc('invest_amount', $order['single_amount'])->update();

            $userModel = new User();
            $userModel->teamBonus($user_id, $project['price'],$order_id);
        }
        //User::upLevel($user_id);
        return !0;


        // 更新订单
        //$dividend_cycle = explode(' ',$order['dividend_cycle']);
        //$next_bonus_time = strtotime(date('Y-m-d 00:00:00', strtotime('+1 day')));
        //$end_time = strtotime(date('Y-m-d 00:00:00', strtotime('+'.($dividend_cycle[0] * $order['period']).' '.$dividend_cycle[1])));
        //$end_time = strtotime(date('Y-m-d 00:00:00', strtotime('+'.$order['period'].' day')));


        // 股权和数字人民
        // if ($order['single_gift_equity'] > 0) {
        //     EquityYuanRecord::create([
        //         'user_id' => $order['user_id'],
        //         'type' => 1,
        //         'status' => 2,
        //         'title' => $order['project_name'],
        //         'relation_type' => 1,
        //         'relation_id' => $order_id,
        //         'num' => round($order['equity']),
        //         'give_time' => time(),
        //         'equity_certificate_no' => 'ZX'.mt_rand(1000000000, 9999999999),
        //     ]);
        //     User::where('id', $order['user_id'])->inc('equity', $order['equity'])->inc('equity_amount', $order['equity'])->update();
        // }
        // if ($order['single_gift_digital_yuan'] > 0) {
        //     EquityYuanRecord::create([
        //         'user_id' => $order['user_id'],
        //         'type' => 2,
        //         'status' => 2,
        //         'title' => $order['project_name'],
        //         'relation_type' => 1,
        //         'relation_id' => $order_id,
        //         'num' => round($order['digital_yuan']),
        //         'give_time' => time(),
        //     ]);
        // }
        // 添加被动|补贴收益记录
        //$amount = bcmul($project['daily_bonus_ratio'],config('config.passive_income_days_conf')[$project['period']]/100,2);
        //   $amount = $order['single_gift_digital_yuan'];
        //   Db::startTrans();
        //   try {
        //       PassiveIncomeRecord::create([
        //               'user_id' => $order['user_id'],
        //               'order_id' => $order['id'],
        //               'execute_day' => date('Ymd'),
        //               'amount'=>$amount,
        //               'days'=>1,
        //               'is_finish'=>1,
        //               'status'=>3,
        //           ]); 
        //       $gain_bonus = bcadd($order['gain_bonus'],$amount,2);
        //       Order::where('id', $order['id'])->update(['gain_bonus'=>$gain_bonus]);
        //       User::changeInc($order['user_id'],$amount,'digital_yuan_amount',5,$order['id'],3);
        //       Db::commit();
        //   } catch (Exception $e) {
        //       Db::rollback();
        //       throw $e;
        //   }

        // PassiveIncomeRecord::create([
        //     'user_id' => $order['user_id'],
        //     'order_id' => $order['id'],
        //     'execute_day' => date('Ymd'),
        //     'amount'=>$amount,
        //     'days'=>$project['period'],
        //     'is_finish'=>1,
        //     'status'=>3,
        // ]); 
        //增加投资金额  注意积分兑换的不算投资金额
        /*         if ($order['pay_method'] != 5) {
            User::where('id', $order['user_id'])->inc('invest_amount', $order['buy_amount'])->update();
        } */
        // 判断激活
        /*         $up_user_id = User::where('id', $order['user_id'])->value('up_user_id');
        if (!empty($up_user_id)) {
            $upUser = User::where('id', $up_user_id)->find();
            $now_level = $upUser['level'];
        } */


        // 如果不是积分兑换才算直推奖励和团队奖励
        // if ($order['pay_method'] != 5 && !empty($up_user_id)) {
        //     // 给直属上级推荐奖
        //     $levelConfig = LevelConfig::where('level', $now_level)->find();
        //     if (!empty($levelConfig['direct_recommend_reward_ratio'])) {
        //         $reward = round($levelConfig['direct_recommend_reward_ratio']/100*$order['buy_amount'], 2);
        //         if($reward > 0){
        //             //User::changeBalance($up_user_id, $reward, 9, $order_id);
        //             User::changeInc($up_user_id,$reward,'team_bonus_balance',9,$order_id,4,'推荐奖励',0,2);
        //             //User::changeInc($up_user_id,$reward,'balance',9,$order_id,1,'推荐奖励',0,2);
        //         }
        //     }
        //     // 给上3级团队奖
        //     $relation = UserRelation::where('sub_user_id', $order['user_id'])->select();
        //     $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
        //     //$map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', ];
        //     foreach ($relation as $v) {
        //         $reward = round(dbconfig($map[$v['level']])/100*$order['buy_amount'], 2);
        //         if($reward > 0){
        //             //User::changeBalance($v['user_id'], $reward, 8, $order_id);
        //             //User::changeInc($up_user_id,$reward,'invite_bonus',8,$order_id,3,'推荐奖励');
        //             User::changeInc($v['user_id'],$reward,'team_bonus_balance',8,$order_id,4,'团队奖励',0,2);
        //             //User::changeInc($v['user_id'],$reward,'balance',8,$order_id,1,'团队奖励',0,2);
        //         }
        //     }
        // }

        // // 检测用户升级
        // if (in_array($order['pay_method'], [1,2,3,4,6])) {
        // //if (in_array($order['pay_method'], [2, 3, 4, 6])) {
        //     $user = User::where('id', $order['user_id'])->find();
        //     $new_level = LevelConfig::where('min_topup_amount', '<=', $user['invest_amount'])->order('min_topup_amount', 'desc')->value('level');

        //     if ($user['level'] < $new_level) {
        //         User::where('id', $user['id'])->update(['level' => $new_level]);
        //     }
        // }

        //赠送项目
        // if(!empty($project['give'])){
        //     $give = json_decode($project['give'],true);
        //     for ($i=0; $i < $order['buy_num']; $i++) { 
        //         foreach($give as $k => $v){
        //             $pro = Project::where('id',$k)->find();
        //             $zs = 'ZS'.mt_rand(1000000000, 9999999999);
        //             $data['up_user_id'] = $up_user_id;
        //             $data['user_id'] = $order['user_id'];
        //             $data['order_sn'] = 'ZS_'.$order['order_sn'];
        //             $data['status'] = 2;
        //             $data['buy_num'] = $v;
        //             $data['project_id'] = $k;
        //             $data['project_name'] = '赠送:'.$pro['name'];
        //             $data['single_amount'] = $pro['single_amount'];
        //             $data['gift_integral'] = $pro['gift_integral'];
        //             $data['cover_img'] = $pro['cover_img'];
        //             $data['total_num'] = $pro['total_num'];
        //             $data['daily_bonus_ratio'] = $pro['daily_bonus_ratio'] * $v;
        //             $data['sum_amount'] = $pro['sum_amount'] * $v;
        //             $data['period'] = $pro['period'];
        //             $data['single_gift_equity'] = $pro['single_gift_equity'] * $v;
        //             $data['single_gift_digital_yuan'] = $pro['single_gift_digital_yuan'] * $v;
        //             $data['pay_method'] = 6;
        //             $data['pay_time'] = time();
        //             //$data['end_time'] = $next_bonus_time + $pro['period']*24*3600;
        //             //$data['next_bonus_time'] = $next_bonus_time;
        //             $data['equity_status'] = 2;
        //             $data['digital_yuan_status'] = 2;
        //             $data['equity_certificate_no'] = $zs;
        //             $data['is_admin_confirm'] = 0;
        //             $data['is_gift'] = 1;
        //             $oid = Order::create($data)->getLastInsID();
        //             if($pro['class'] == 1){
        //                PassiveIncomeRecord::create([
        //                     'user_id' => $order['user_id'],
        //                     'order_id' => $oid,
        //                     'execute_day' => date('Ymd'),
        //                 ]); 
        //             }
        //             if($pro['class'] == 2){
        //                 SubsidyIncomeRecord::create([
        //                     'user_id' => $order['user_id'],
        //                     'order_id' => $oid,
        //                     'execute_day' => date('Ymd'),
        //                 ]); 
        //             }
        //             // 股权和数字人民
        //             if ($pro['single_gift_equity'] > 0) {
        //                 EquityYuanRecord::create([
        //                     'user_id' => $order['user_id'],
        //                     'type' => 1,
        //                     'status' => 2,
        //                     'title' => '赠送:'.$pro['name'],
        //                     'relation_type' => 4,
        //                     'relation_id' => $oid,
        //                     'num' => round($pro['single_gift_equity']) * $v,
        //                     'give_time' => time(),
        //                     'equity_certificate_no' => $zs,
        //                 ]);
        //                 User::where('id', $order['user_id'])->inc('equity', $order['equity'])->update();

        //             }
        //             if ($pro['single_gift_digital_yuan'] > 0) {
        //                 EquityYuanRecord::create([
        //                     'user_id' => $order['user_id'],
        //                     'type' => 2,
        //                     'status' => 2,
        //                     'title' => '赠送:'.$pro['name'],
        //                     'relation_type' => 4,
        //                     'relation_id' => $oid,
        //                     'num' => round($pro['single_gift_digital_yuan']) * $v,
        //                     'give_time' => time(),
        //                 ]);
        //             }
        //         }
        //     }

        // }

        return true;
    }

    public static function warpOrderComplete($order_id)
    {
        try {
            $order = Order::where('id', $order_id)->find();
            $project = Project::where('id', $order['project_id'])->find();
            self::orderPayComplete($order['id'], $project, $order['user_id']);
        } catch (Exception $e) {
            \think\facade\Log::error('warpOrderComplete:' . $e->getMessage() . $e->getLine() . $e->getFile());
            throw $e;
        }
    }

    public static function cancelOrder($order_id)
    {
        $order = Order::where('id', $order_id)->find();
        if (!$order || $order['status'] != 2) {
            return ['code' => 10003,'order_id'=>$order_id, 'msg' => '该记录状态异常'];
        }
        $logs = UserBalanceLog::where('relation_id', $order_id)->where('user_id', $order['user_id'])->where('type', 3)->order('id', 'asc')->select();
        if (count($logs) <= 0) {
            return ['code' => 10003,'order_id'=>$order_id, 'msg' => '找不到购买订单日志记录'];

        }
        $fields = ['topup_balance', 'team_bonus_balance'];
        $cnFields = ['topup_balance' => '可用余额',  'team_bonus_balance' => '可提余额'];
        $amouts = ['topup_balance' => 0, 'team_bonus_balance' => 0];
        foreach ($logs as $key => $log) {
            //$field = $fields[$key];
            if (strpos($log['remark'], '可用余额-') !== false) {
                $field = 'topup_balance';
            } elseif (strpos($log['remark'], '可提余额-') !== false) {
                $field = 'team_bonus_balance';
            }

            $amouts[$field] =  abs($log['change_balance']);
        }
        Db::startTrans();
        try {
            Db::table('mp_order_copy1')->insert($order->toArray());
            Db::table('mp_order')->where('id', $order_id)->delete();
            foreach ($amouts as $field => $amout) {
                if ($amout > 0) {
                    User::changeInc($order['user_id'], $amout, $field, 90, $order_id, 1, '撤销订单返还' . $cnFields[$field], 0, 1, 'ZS');
                }
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            //throw $e;
            return ['code' => 10003,'order_id'=>$order_id, 'msg' => $e->getMessage()];
        }
        return ['code' => 0, 'order_id'=>$order_id,'msg' => '撤销订单成功'];
    }
}
