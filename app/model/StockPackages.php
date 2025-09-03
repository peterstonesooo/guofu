<?php
// app/model/StockPackage.php
namespace app\model;

use think\Model;

class StockPackages extends Model
{

    // 状态常量
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;

    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段类型转换
    protected $type = [
        'id'               => 'integer',
        'lock_period'      => 'integer',
        'daily_sell_limit' => 'integer',
        'status'           => 'integer'
    ];

    // 套餐关联的股权项
    public function items()
    {
        return $this->hasMany(StockPackageItems::class, 'package_id');
    }

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? 0;
        $map = [
            self::STATUS_ENABLED  => '启用',
            self::STATUS_DISABLED => '禁用'
        ];
        return $map[$status] ?? '未知';
    }

    // 范围查询：启用的套餐
    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    // 范围查询：禁用的套餐
    public function scopeDisabled($query)
    {
        return $query->where('status', self::STATUS_DISABLED);
    }
}