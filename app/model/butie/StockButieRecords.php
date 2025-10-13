<?php

namespace app\model\butie;

use app\model\User;
use think\Model;

class StockButieRecords extends Model
{

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 状态常量
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED = 0;

    // 类型常量
    const TYPE_ACTIVITY = 1;

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 关联补贴
     */
    public function butie()
    {
        return $this->belongsTo(StockButie::class, 'butie_id', 'id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? $value;
        $map = [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED => '失败'
        ];
        return $map[$status] ?? '未知';
    }

    /**
     * 获取类型文本
     */
    public function getTypeTextAttr($value, $data)
    {
        $type = $data['type'] ?? $value;
        $map = [
            self::TYPE_ACTIVITY => '活动补贴'
        ];
        return $map[$type] ?? '未知';
    }
}