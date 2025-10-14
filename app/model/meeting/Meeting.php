<?php

namespace app\model\meeting;

use think\Model;

class Meeting extends Model
{
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 状态常量
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 获取封面完整URL
     */
    public function getCoverUrlAttr($value, $data)
    {
        if (!empty($data['cover_img'])) {
            return env('app.img_host') . '/storage/' . $data['cover_img'];
        }
        return '';
    }

    /**
     * 根据状态获取列表
     */
    public static function getListByStatus($status = self::STATUS_ENABLED)
    {
        return self::where('status', $status)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->select();
    }
}