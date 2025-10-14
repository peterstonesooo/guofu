<?php

namespace app\model\meeting;

use app\model\User;
use think\Model;

class MeetingSignRecords extends Model
{
    protected $table = 'mp_meeting_sign_records';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 状态常量
    const STATUS_FAILED = 0;
    const STATUS_SUCCESS = 1;

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $map = [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED  => '失败'
        ];
        return $map[$data['status']] ?? '未知';
    }

    /**
     * 获取签到类型名称
     */
    public function getSignTypeAttr()
    {
        return '日常签到';
    }
}