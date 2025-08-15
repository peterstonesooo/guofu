<?php

namespace app\model;

use Exception;
use GuzzleHttp\Client;
use think\facade\Db;
use think\Model;

class Capital extends Model
{
    public function getTypeTextAttr($value, $data)
    {
        $map = config('map.capital')['type_map'];
        return $map[$data['type']];
    }

    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.capital')['status_map'];
        return $map[$data['status']];
    }

    public function getTopupStatusTextAttr($value, $data)
    {
        $map = config('map.capital')['topup_status_map'];
        return $map[$data['status']];
    }

    public function getWithdrawStatusTextAttr($value, $data)
    {
        $map = config('map.capital')['withdraw_status_map'];
        return $map[$data['status']];
    }

    public function getPayChannelTextAttr($value, $data)
    {
        $map = config('map.capital')['pay_channel_map'];
        return $map[$data['pay_channel']];
    }

    public function getTopupPayStatusTextAttr($value, $data)
    {
        if ($data['status'] > 1) {
            if ($data['is_admin_confirm'] == 1) {
                return '成功,未通知';
            }
            return '成功,已通知';
        }
        return '未支付';
    }

    public function getAuditDateAttr($value, $data)
    {
        if (!empty($data['audit_time'])) {
            return date('Y-m-d H:i:s', $data['audit_time']);
        }

        return '';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public static function topupPayComplete($capital_id, $admin_user_id = 0)
    {
        $capital = Capital::where('id', $capital_id)->find();
        // 充值增加余额
        //User::changeBalance($capital['user_id'], $capital['amount'], 1, $capital_id, 1, '', $admin_user_id);
        User::changeInc($capital['user_id'],$capital['amount'],'topup_balance',1,$capital_id,1,'',$admin_user_id,1,'CZ');
        // 改变充值单状态
        Capital::where('id', $capital_id)->update(['status' => 2, 'audit_time' => time()]);
        // 添加充值奖励
        //$user = User::where('id', $capital['user_id'])->find();
        //$topup_reward_ratio = LevelConfig::where('level', $user['level'])->value('topup_reward_ratio');
        //$change_balance = round($topup_reward_ratio/100*$capital['amount'], 2);
        //User::changeBalance($capital['user_id'], $change_balance, 4, $capital_id, 2, '', $admin_user_id);
        //User::changeInc($capital['user_id'], $change_balance,'topup_balance',4, $capital_id, 2, '', $admin_user_id);

        return true;
    }


    public static function auditWithdraw($id, $status, $admin_user_id, $audit_remark = '', $is_batch = false)
    {
        $capital = Capital::where('id', $id)->lock(true)->find();
        if (!in_array($capital['status'], [1,4])) {
            exit_out(null, 10001, '该记录已经审核了');
        }
        if ($capital['type'] != 2) {
            exit_out(null, 10002, '审核记录异常');
        }
/*         if(in_array($capital['log_type'],[3,6])){
            exit_out(null, 10002, '国务院津贴和收益提现不需要审核');
        } */
/*         if ($is_batch && $status == 4 && $capital['pay_channel'] == 1) {
            $status = 2;
        } */
        // 当是手动出金的审核，并且审核通过了 要判断余额是否充足
/*         if ($capital['pay_channel'] == 1 && $status == 2) {
            $user = User::where('id', $capital['user_id'])->find();
            $change_amount = 0 - $capital['amount'];
            if ($user['balance'] < $change_amount) {
                exit_out(null, 10001, '用户余额不足，不能审核通过');
            }
        } */
        $update = [
            'status' => $status,
            'audit_time' => time(),
            'admin_user_id' => $admin_user_id,
        ];
        if (!empty($audit_remark)) {
            $update['audit_remark'] = $audit_remark;
        }
        if ($status == 4 && $capital['pay_channel'] == 4) {
            $withdraw_sn = build_order_sn($capital['user_id']);
            $update['withdraw_sn'] = $withdraw_sn;
        }
        Capital::where('id', $id)->update($update);

        if ($status != 4) {
            /*
            if ($capital['pay_channel'] == 1) {
                // 审核通过就扣余额
                if ($status == 2) {
                    //User::changeBalance($capital['user_id'],  $capital['amount'], 16, $capital['id'], 1, $audit_remark??'', $admin_user_id);
                }
            }
            else {*/
                // 审核拒绝把余额加回去
                if ($status == 3) {
                    $change = 0 - $capital['amount'];
                    //User::changeBalance($capital['user_id'], $change, 13, $id, 1, $audit_remark ?? '', $admin_user_id);
                    if($capital['log_type'] == 1){
                        User::changeInc($capital['user_id'], $change,'gf_purse', 13, $id, 2, $audit_remark ?? '', $admin_user_id,1,'TX');
                    } else {
                        //User::changeInc($capital['user_id'], $change,'team_bonus_balance', 13, $id, 3, $audit_remark ?? '', $admin_user_id,1,'TX');
                        User::changeInc($capital['user_id'], $change,'large_subsidy', 13, $id, 3, $audit_remark ?? '', $admin_user_id,1,'TX');
                        //User::changeInc($capital['user_id'], $change,'bail_balance', 13, $id, 3, $audit_remark ?? '', $admin_user_id,1,'TX');

                    }
                    
                }
                else {
                    // 审核通过把资金日志的提现记录变为已完成
                    UserBalanceLog::where('user_id', $capital['user_id'])->where('type', 2)->where('relation_id', $id)->where('log_type', 1)->where('status', 1)->update(['status' => 2]);
                }
            //}
        }
        else {
            // 如果是银联提现，就调用接口自动转账
            if ($capital['pay_channel'] == 4 && dbconfig('automatic_withdrawal_switch') == 1) {
                $client = new Client();
                $payoutsReq = [
                    'platform_id' => 'PF0200',
                    'service_id' => 'SVC0004',
                    'payout_cl_id' => $withdraw_sn ?? '',
                    'amount' => round((0 - $capital['amount'])*100),
                    'notify_url' => env('app.host').'/common/withdrawNotify',
                    'name' => $capital['realname'],
                    'number' => $capital['account'],
                    'request_time' => time(),
                ];
                $payoutsReq['sign'] = withdraw_builder_sign($payoutsReq);
                $rsp = $client->post('https://hbj168.club/gateway/api/v2/payouts', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'content-type' => 'application/json;charset=utf-8',
                    ],
                    'json' => $payoutsReq,
                ]);
                $json = $rsp->getBody()->getContents();
                $ret = json_decode($json, true);
                if (!isset($ret['error_code']) || $ret['error_code'] != '0000') {
                    exit_out(null, 10001, $ret['error_msg']??'请求第三方错误', $json);
                }
            }
        }

        return $withdraw_sn ?? '';
    }

    public static function findCurrentStage($startDate, $dateArr, $holidayStart, $holidayEnd,$todayDate=0) {
        $holidayStart = new \DateTime($holidayStart);
        $holidayEnd = new \DateTime($holidayEnd);
        if($todayDate==0){
            $today = new \DateTime();
        }else{
            $today = new \DateTime($todayDate);
        }
        $date = new \DateTime($startDate);

        foreach ($dateArr as $key=>$stage) {
            // 如果新的日期在假期期间，将开始日期设置为假期结束后的日期,加上假期开始日期-开始日期的天数
            $itemDateStr = $date->format('Y-m-d');
            // 将日期增加到阶段的天数
            $date->modify('+' . $stage['number'] . ' days');
    
            if ($date > $holidayStart && $date < $holidayEnd) {
                $itemDate = new \DateTime($itemDateStr);
                $interval = $itemDate->diff($holidayStart)->days;
                $remain = $stage['number'] - $interval;
                //echo $interval."\n";
                //echo $itemDateStr."\n";
                $date = clone $holidayEnd;
                $date->modify("+$remain day");
            }
    
            // 检查今天是否在当前阶段
            //echo "$key ".$date->format('Y-m-d H:i:s')."\n";
            if ($today < $date) {
                //$stage['status'] = '审核中';
                return $key;
            }/* else{
                $stage['status'] = '已完成';
            } */
        }
    
        return count($dateArr) - 1;
    }
}
