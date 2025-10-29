<?php

namespace app\api\controller;

use app\model\invite_present\InviteCashConfig;
use app\model\invite_present\InviteCashLog;
use app\model\User;

class InviteCash extends AuthController
{

    /**
     * 获取邀请现金红包奖励记录
     */
    public function inviteCashLog()
    {
        $user = $this->user;

        $list = InviteCashLog::where('user_id', $user['id'])
            ->order('created_at', 'desc')
            ->paginate(10)
            ->each(function ($item) {
                $item->status_text = $item->status == 1 ? '发放成功' : '发放失败';
                return $item;
            });

        return out($list);
    }

    /**
     * 获取邀请现金红包统计信息
     */
    public function inviteCashStats()
    {
        $user = $this->user;

        // 总获得现金红包金额
        $totalAmount = InviteCashLog::where('user_id', $user['id'])
            ->where('status', 1)
            ->sum('cash_amount');

        // 总获得红包次数
        $totalCount = InviteCashLog::where('user_id', $user['id'])
            ->where('status', 1)
            ->count();

        // 当前已实名邀请人数
        $realInviteCount = User::where('up_user_id', $user['id'])
            ->where('is_realname', 1)
            ->count();

        // 可领取的下一级红包信息
        $nextConfig = InviteCashConfig::where('status', 1)
            ->where('invite_num', '>', $realInviteCount)
            ->order('invite_num', 'asc')
            ->find();

        $data = [
            'total_amount'      => $totalAmount ?: '0.00',
            'total_count'       => $totalCount,
            'real_invite_count' => $realInviteCount,
            'next_reward'       => $nextConfig ? [
                'invite_num'  => $nextConfig['invite_num'],
                'cash_amount' => $nextConfig['cash_amount'],
                'need_invite' => $nextConfig['invite_num'] - $realInviteCount
            ] : null
        ];

        return out($data);
    }

}