<?php

namespace app\model\butie;

use think\Model;
class StockButie extends Model
{

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 状态常量
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? $value;
        $map = [
            self::STATUS_ENABLED => '启用',
            self::STATUS_DISABLED => '禁用'
        ];
        return $map[$status] ?? '未知';
    }
}