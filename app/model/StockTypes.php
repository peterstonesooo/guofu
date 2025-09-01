<?php

namespace app\model;

use think\Model;

class StockTypes extends Model
{


    // 定义用户钱包关联
    public function userWallets()
    {
        return $this->hasMany(UserStockWallets::class, 'stock_type_id');
    }

    // 定义流通额度关联
    public function circulatingQuotas()
    {
        return $this->hasMany(CirculatingStockQuotas::class, 'stock_type_id');
    }

    // 定义交易记录关联
    public function transactions()
    {
        return $this->hasMany(StockTransactions::class, 'stock_type_id');
    }
}