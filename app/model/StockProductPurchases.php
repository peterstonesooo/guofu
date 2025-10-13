<?php

namespace app\model;

use think\Model;

class StockProductPurchases extends Model
{

    // 设置主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段类型转换
    protected $type = [
        'id'         => 'integer',
        'user_id'    => 'integer',
        'product_id' => 'integer',
        'quantity'   => 'integer',
        'price'      => 'float',
        'amount'     => 'float',
        'pay_type'   => 'integer',
        'status'     => 'integer',
    ];

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 关联产品
    public function product()
    {
        return $this->belongsTo(StockProduct::class, 'product_id');
    }
}