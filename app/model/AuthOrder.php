<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class AuthOrder extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
