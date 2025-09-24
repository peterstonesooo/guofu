<?php

namespace app\model;

use think\Model;

class GreenConfig extends Model
{
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 字段类型转换
    protected $type = [
        'priority_queue' => 'integer',
        'channel_fee'    => 'float',
        'status'         => 'integer',
        'sort'           => 'integer',
    ];

    // 获取器 - 状态文本
    public function getStatusTextAttr($value, $data)
    {
        $status = [0 => '禁用', 1 => '启用'];
        return $status[$data['status']];
    }

    // 获取器 - 格式化通道费
    public function getChannelFeeTextAttr($value, $data)
    {
        return number_format($data['channel_fee'], 2) . '元';
    }
}