<?php

namespace app\model;

use think\Model;

class FinanceApprovalApply extends Model
{

    // 状态常量
    const STATUS_PENDING = 1; // 审批中
    const STATUS_APPROVED = 2; // 审批完成
    const STATUS_COMPLETED = 3; // 已拨款

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_PENDING   => '审批中',
            self::STATUS_APPROVED  => '审批完成',
            self::STATUS_COMPLETED => '已拨款'
        ];
        return $status[$data['status']] ?? '未知状态';
    }

    // 关联配置模型
    public function config()
    {
        return $this->belongsTo(FinanceApprovalConfig::class, 'config_id');
    }

    // 关联用户模型
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}