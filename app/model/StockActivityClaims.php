<?php

namespace app\model;

use think\Model;

class StockActivityClaims extends Model
{

    // 设置主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段类型转换
    protected $type = [
        'id'                => 'integer',
        'user_id'           => 'integer',
        'stock_type_id_ltg' => 'integer',
        'stock_type_id_ysg' => 'integer',
        'ltg_quantity'      => 'integer',
        'ysg_quantity'      => 'integer',
        'status'            => 'integer',
    ];

    /**
     * 获取用户信息
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 获取流通股权类型信息
     */
    public function ltgStockType()
    {
        return $this->belongsTo(StockTypes::class, 'stock_type_id_ltg', 'id');
    }

    /**
     * 获取原始股权类型信息
     */
    public function ysgStockType()
    {
        return $this->belongsTo(StockTypes::class, 'stock_type_id_ysg', 'id');
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? 0;
        $statusMap = [
            0 => '未发放',
            1 => '已发放'
        ];
        return $statusMap[$status] ?? '未知状态';
    }
}