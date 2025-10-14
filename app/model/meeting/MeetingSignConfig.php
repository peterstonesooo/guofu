<?php

namespace app\model\meeting;

use think\Model;

class MeetingSignConfig extends Model
{
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 状态常量
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 更新或创建配置
     */
    public static function updateOrCreate($data)
    {
        $config = self::getConfig();
        if ($config) {
            return $config->save($data);
        } else {
            return self::create($data);
        }
    }
}