<?php

namespace app\api\service;

use app\model\meeting\MeetingSignConfig;
use app\model\meeting\MeetingSignRecords;
use app\model\User;
use think\Exception;
use think\facade\Db;

class MeetingService
{
    /**
     * 日常签到（每日一次）
     * @param int $user_id 用户ID
     * @return bool
     * @throws \Exception
     */
    public static function signMeeting($user_id)
    {
        Db::startTrans();
        try {
            // 获取签到配置
            $config = MeetingSignConfig::order('id', 'desc')->find();
            if (!$config || $config['sign_status'] != 1) {
                throw new Exception('签到功能暂未开启');
            }

            // 检查签到金额
            if ($config['sign_bonus'] <= 0) {
                throw new Exception('签到奖励暂未设置');
            }

            // 检查用户今天是否已经签到
            $today = date('Y-m-d');
            $exists = MeetingSignRecords::where('user_id', $user_id)
                ->where('sign_date', $today)
                ->find();

            if ($exists) {
                throw new Exception('您今天已经签到过了');
            }

            // 记录签到记录（meeting_id设为0表示日常签到）
            $recordData = [
                'user_id'      => $user_id,
                'meeting_id'   => 0, // 0表示日常签到
                'bonus_amount' => $config['sign_bonus'],
                'sign_date'    => $today,
                'status'       => 1,
                'remark'       => "会议日常签到",
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s')
            ];

            $record = new MeetingSignRecords();
            $result = $record->save($recordData);

            if (!$result) {
                throw new Exception('记录签到记录失败');
            }

            // 增加用户的团队奖励余额（team_bonus_balance）
            User::changeInc($user_id, $config['sign_bonus'], 'team_bonus_balance', 99, $record->id, 3, "日常签到");

            Db::commit();
            return true;

        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}