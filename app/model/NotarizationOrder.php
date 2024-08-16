<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class NotarizationOrder extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
