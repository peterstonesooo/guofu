<?php

namespace app\model;

use think\Model;

class GreenChannelOrder extends Model
{

    // 状态映射
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED = 0;

    public function getStatusTextAttr($value, $data)
    {
        $map = [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED  => '失败'
        ];
        return $map[$data['status']] ?? '未知';
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 关联配置
    public function config()
    {
        return $this->belongsTo(GreenConfig::class, 'config_id');
    }
}