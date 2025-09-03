<?php
// app/model/StockPackageItem.php
namespace app\model;

use think\Model;

class StockPackageItems extends Model
{

    // 股权类型常量
    const TYPE_CIRCULATING = "LTG001"; // 流通股权
    const TYPE_ORIGINAL = "YSG001";    // 原始股权

    // 字段类型转换
    protected $type = [
        'id'            => 'integer',
        'package_id'    => 'integer',
        'stock_type_id' => 'integer',
        'type'          => 'integer',
        'quantity'      => 'integer'
    ];

    // 关联套餐
    public function package()
    {
        return $this->belongsTo(StockPackages::class, 'package_id');
    }

    // 关联股权类型
    public function stockType()
    {
        return $this->belongsTo(StockTypes::class, 'stock_type_id');
    }

    // 获取类型文本
    public function getTypeTextAttr($value, $data)
    {
        $type = $data['type'] ?? '';
        $map = [
            self::TYPE_CIRCULATING => '流通股权',
            self::TYPE_ORIGINAL    => '原始股权'
        ];
        return $map[$type] ?? '未知';
    }

    // 范围查询：流通股权
    public function scopeCirculating($query)
    {
        return $query->where('type', self::TYPE_CIRCULATING);
    }

    // 范围查询：原始股权
    public function scopeOriginal($query)
    {
        return $query->where('type', self::TYPE_ORIGINAL);
    }
}