<?php

namespace app\model\invite_present;

use think\Model;

class InviteCashConfig extends Model
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
            self::STATUS_ENABLED  => '启用',
            self::STATUS_DISABLED => '禁用'
        ];
        return $map[$status] ?? '未知';
    }

    /**
     * 根据邀请人数获取配置
     */
    public static function getByInviteNum($inviteNum)
    {
        return self::where('invite_num', $inviteNum)
            ->where('status', self::STATUS_ENABLED)
            ->find();
    }

    /**
     * 获取所有启用状态的配置
     */
    public static function getEnabledConfigs()
    {
        return self::where('status', self::STATUS_ENABLED)
            ->order('invite_num', 'asc')
            ->select();
    }
}