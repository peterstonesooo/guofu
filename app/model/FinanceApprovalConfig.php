<?php

namespace app\model;

use think\Model;

class FinanceApprovalConfig extends Model
{

    // 状态常量
    const STATUS_ENABLED = 1; // 启用
    const STATUS_DISABLED = 0; // 禁用

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_ENABLED  => '启用',
            self::STATUS_DISABLED => '禁用'
        ];
        return $status[$data['status']] ?? '未知状态';
    }
}