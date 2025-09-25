<?php

namespace app\model;

use think\Model;

class StockAnnouncements extends Model
{

    // 设置主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段类型转换
    protected $type = [
        'id'         => 'integer',
        'status'     => 'integer',
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    // 状态获取器
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? 0;
        return $status == 1 ? '启用' : '禁用';
    }

    // 状态获取器（用于API）
    public function getStatusApiAttr($value, $data)
    {
        $status = $data['status'] ?? 0;
        return $status == 1 ? true : false;
    }
}