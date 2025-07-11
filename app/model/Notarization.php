<?php

namespace app\model;

use think\Model;

class Notarization extends Model
{
    protected $pk = 'id';
    protected $table = 'mp_notarization';
    
    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'user_id' => 'int',
        'money' => 'decimal',
        'fees' => 'decimal',
        'status' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'end_time' => 'datetime',
    ];
    
    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 关联用户模型
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    // 状态文字
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            0 => '待公证',
            1 => '公证中',
            2 => '完成公证'
        ];
        return $status[$data['status']] ?? '未知状态';
    }
    
    // 状态颜色
    public function getStatusColorAttr($value, $data)
    {
        $colors = [
            0 => 'default',
            1 => 'warning',
            2 => 'success'
        ];
        return $colors[$data['status']] ?? 'default';
    }
}