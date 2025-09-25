<?php

namespace app\model;

use think\Model;

class StockTransactions extends Model
{

    // 交易类型常量
    const TYPE_BUY = 1;
    const TYPE_SELL = 2;

    // 来源常量
    const SOURCE_CIRCULATING = 1;
    const SOURCE_PURCHASED = 2;

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
}