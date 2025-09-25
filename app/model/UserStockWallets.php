<?php

namespace app\model;

use think\Model;

class UserStockWallets extends Model
{

    // 定义用户关联
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 定义股权类型关联
    public function stockType()
    {
        return $this->belongsTo(StockTypes::class, 'stock_type_id');
    }

    // 定义流通额度关联
    public function circulatingQuota()
    {
        return $this->hasOne(CirculatingStockQuotas::class, 'user_id', 'user_id')
            ->where('stock_type_id', $this->stock_type_id);
    }
}