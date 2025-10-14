<?php

namespace app\model\meeting;

use think\Model;
use app\model\User;

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
     * 关联会议
     */
    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $map = [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED => '失败'
        ];
        return $map[$data['status']] ?? '未知';
    }

    /**
     * 获取会议名称（包含日常签到处理）
     */
    public function getMeetingNameAttr($value, $data)
    {
        if (!empty($data['meeting_id']) && $this->meeting) {
            return $this->meeting->title;
        }
        return '日常签到';
    }
}