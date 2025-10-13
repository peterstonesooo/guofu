<?php

namespace app\model;

use think\Model;

class StockProduct extends Model
{

    // 设置主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段类型转换
    protected $type = [
        'id'     => 'integer',
        'price'  => 'float',
        'sort'   => 'integer',
        'status' => 'integer',
    ];

    // 获取启用状态的产品列表
    public static function getEnabledProducts()
    {
        return self::where('status', 1)->select();
    }
}