<?php

namespace app\model;

use think\Model;

/**
 * 用户股权明细模型
 * 记录用户通过购买套餐获得的每一笔股权明细，用于管理锁定期、解锁、过期和冻结逻辑
 */
class UserStockDetails extends Model
{

    // 设置主键
    protected $pk = 'id';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 定义状态常量
    const STATUS_LOCKED = 0;     // 未解锁（锁定期内）
    const STATUS_ACTIVE = 1;     // 有效（可交易）
    const STATUS_FROZEN = 2;     // 已冻结（过期未卖出）
    const STATUS_EXPIRED = 3;    // 已失效（过期已处理）
    const STATUS_SOLD = 4;       // 已卖出

    /**
     * 获取状态说明
     * @param int $status
     * @return string
     */
    public static function getStatusText($status)
    {
        $statusMap = [
            self::STATUS_LOCKED  => '未解锁',
            self::STATUS_ACTIVE  => '有效',
            self::STATUS_FROZEN  => '已冻结',
            self::STATUS_EXPIRED => '已失效',
            self::STATUS_SOLD    => '已卖出'
        ];
        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 关联用户
     * @return \think\model\relation\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id');
    }

    /**
     * 关联股权类型
     * @return \think\model\relation\BelongsTo
     */
    public function stockType()
    {
        return $this->belongsTo('StockTypes', 'stock_type_id');
    }

    /**
     * 关联套餐购买记录
     * @return \think\model\relation\BelongsTo
     */
    public function packagePurchase()
    {
        return $this->belongsTo('PackagePurchases', 'package_purchase_id');
    }

    /**
     * 关联套餐
     * @return \think\model\relation\BelongsTo
     */
    public function package()
    {
        return $this->belongsTo('StockPackages', 'package_id');
    }

    /**
     * 获取可用的股权明细（未解锁+有效）
     * @param int $userId 用户ID
     * @param int $stockTypeId 股权类型ID
     * @return \think\Collection
     */
    public static function getAvailableDetails($userId, $stockTypeId)
    {
        return self::where('user_id', $userId)
            ->where('stock_type_id', $stockTypeId)
            ->where('status', 'in', [self::STATUS_LOCKED, self::STATUS_ACTIVE])
            ->where('remaining_quantity', '>', 0)
            ->order('id', 'asc') // 按购买时间排序（FIFO）
            ->select();
    }

    /**
     * 检查是否已解锁
     * @return bool
     */
    public function isUnlocked()
    {
        return $this->available_at && strtotime($this->available_at) <= time();
    }

    /**
     * 检查是否已过期
     * @return bool
     */
    public function isExpired()
    {
        return $this->expired_at && strtotime($this->expired_at) <= time();
    }
}