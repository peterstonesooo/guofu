<?php

namespace app\model\invite_present;

use app\model\User;
use think\Model;

class InviteCashLog extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 状态常量
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED = 2;

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? $value;
        $map = [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED  => '失败'
        ];
        return $map[$status] ?? '未知';
    }

    /**
     * 检查用户是否已经领取过某个邀请人数的奖励
     */
    public static function hasReceived($userId, $inviteNum)
    {
        return self::where('user_id', $userId)
            ->where('invite_num', $inviteNum)
            ->where('status', self::STATUS_SUCCESS)
            ->find();
    }

    /**
     * 获取用户的发放记录
     */
    public static function getUserLogs($userId, $paginate = false)
    {
        $query = self::with(['user'])
            ->where('user_id', $userId)
            ->order('id', 'desc');

        return $paginate ? $query->paginate() : $query->select();
    }
}