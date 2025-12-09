<?php

namespace app\api\controller;

use app\model\invite_present\InviteCashConfig;
use app\model\invite_present\InviteCashLog;
use app\model\User;
use think\facade\Db;
use think\facade\Log;

class InviteCashController extends AuthController
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

    /**
     * 领取邀请现金红包奖励
     */
    public function claimInviteReward()
    {
        $user = $this->user;
        
        // 获取用户已实名邀请人数
        $realInviteCount = User::where('up_user_id', $user['id'])
            ->where('is_realname', 1)
            ->count();

        // 获取用户可领取的奖励配置
        $availableConfigs = InviteCashConfig::where('status', 1)
            ->where('invite_num', '<=', $realInviteCount)
            ->order('invite_num', 'asc')
            ->select();

        if ($availableConfigs->isEmpty()) {
            return out(null, 10001, '暂无可领取的奖励');
        }

        $claimedRewards = [];
        Db::startTrans();
        try {
            foreach ($availableConfigs as $config) {
                // 检查是否已经领取过该级别的奖励
                $existsLog = InviteCashLog::where('user_id', $user['id'])
                    ->where('invite_num', $config['invite_num'])
                    ->find();

                if (!$existsLog) {
                    // 记录发放日志
                    $logData = [
                        'user_id'     => $user['id'],
                        'invite_num'  => $config['invite_num'],
                        'cash_amount' => $config['cash_amount'],
                        'status'      => 1,
                        'remark'      => "邀请{$config['invite_num']}人实名认证奖励"
                    ];

                    $cashLog = InviteCashLog::create($logData);

                    // 给用户账户增加现金余额到 team_bonus_balance
                    User::changeInc(
                        $user['id'],
                        $config['cash_amount'],
                        'team_bonus_balance',
                        102,
                        $cashLog['id'],
                        3,
                        "邀请{$config['invite_num']}人实名认证现金红包",
                        0,
                        2,
                        'ICR'
                    );

                    $claimedRewards[] = [
                        'invite_num' => $config['invite_num'],
                        'cash_amount' => $config['cash_amount']
                    ];
                }
            }

            Db::commit();

            return out([
                'claimed_rewards' => $claimedRewards,
                'message' => '成功领取 ' . count($claimedRewards) . ' 个奖励'
            ]);

        } catch (\Exception $e) {
            Db::rollback();

            // 记录失败的发放日志
            foreach ($availableConfigs as $config) {
                $existsLog = InviteCashLog::where('user_id', $user['id'])
                    ->where('invite_num', $config['invite_num'])
                    ->find();

                if (!$existsLog) {
                    InviteCashLog::create([
                        'user_id'     => $user['id'],
                        'invite_num'  => $config['invite_num'],
                        'cash_amount' => $config['cash_amount'],
                        'status'      => 2,
                        'remark'      => "发放失败: " . $e->getMessage()
                    ]);
                }
            }

            Log::error('邀请现金红包领取失败: ' . $e->getMessage(), [
                'user_id' => $user['id']
            ]);

            return out(null, 10002, '领取失败，请稍后重试');
        }
    }

}