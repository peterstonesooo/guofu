<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class Notice extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getIsReadTextAttr($value, $data)
    {
        return $data['is_read'] == 1 ? '已读' : '未读';
    }
}
