<?php

namespace app\model;

use think\Model;

class Notarization extends Model
{
    
    // 设置字段信息
    
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
            1 => '公证中',
            2 => '公证完成',
            3 => '提现',
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
