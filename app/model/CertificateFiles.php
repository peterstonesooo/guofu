<?php

namespace app\model;

use think\Model;

class CertificateFiles extends Model
{
    // 状态常量
    const STATUS_DISABLED = 0; // 禁用
    const STATUS_ENABLED = 1;  // 启用

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 时间戳字段
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 获取图片URL完整路径
     */
    public function getImageUrlAttr($value, $data)
    {
        if (!empty($data['image'])) {
            return env('app.img_host') . '/storage/' . $data['image'];
        }
        return '';
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return $data['status'] == self::STATUS_ENABLED ? '启用' : '禁用';
    }
}