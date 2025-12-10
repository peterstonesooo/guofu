<?php

namespace app\model;

use think\Model;

class UserIpLog extends Model
{
    protected $name = 'user_ip_log';
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'user_id'     => 'int',
        'login_ip'    => 'string',
        'register_ip' => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
    
    /**
     * 记录登录IP
     * @param int $userId 用户ID
     * @param string $ip IP地址
     * @return bool
     */
    public static function recordLoginIp($userId, $ip)
    {
        try {
            // 查找是否已有记录
            $log = self::where('user_id', $userId)->find();
            
            if ($log) {
                // 更新登录IP
                $log->login_ip = $ip;
                $log->save();
            } else {
                // 创建新记录
                self::create([
                    'user_id' => $userId,
                    'login_ip' => $ip,
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 记录注册IP
     * @param int $userId 用户ID
     * @param string $ip IP地址
     * @return bool
     */
    public static function recordRegisterIp($userId, $ip)
    {
        try {
            // 查找是否已有记录
            $log = self::where('user_id', $userId)->find();
            
            if ($log) {
                // 更新注册IP
                $log->register_ip = $ip;
                $log->save();
            } else {
                // 创建新记录
                self::create([
                    'user_id' => $userId,
                    'register_ip' => $ip,
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取用户的IP信息
     * @param int $userId 用户ID
     * @return array|null
     */
    public static function getUserIpInfo($userId)
    {
        return self::where('user_id', $userId)->find();
    }
}