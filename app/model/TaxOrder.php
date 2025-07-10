<?php

namespace app\model;

use think\Model;

class TaxOrder extends Model
{
    protected $pk = 'id';
    protected $table = 'mp_tax_order';
    
    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'user_id' => 'int',
        'money' => 'decimal',
        'taxes_money' => 'decimal',
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
            0 => '未缴费',
            1 => '已缴费',
            2 => '退税申请中',
            3 => '退税成功'
        ];
        return $status[$data['status']] ?? '未知状态';
    }
    
    // 状态颜色
    public function getStatusColorAttr($value, $data)
    {
        $colors = [
            0 => 'warning',
            1 => 'success',
            2 => 'info',
            3 => 'primary'
        ];
        return $colors[$data['status']] ?? 'default';
    }
}
