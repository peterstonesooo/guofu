<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class CardOrder extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
