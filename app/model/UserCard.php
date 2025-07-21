<?php

namespace app\model;

use think\Model;
use think\facade\Db;
use Exception;

class UserCard extends Model
{
    /**
     * 修改用户卡片金额
     * @param int $user_id 用户ID
     * @param float $amount 变更金额（正数为增加，负数为减少）
     * @param int $type 类型
     * @param int $log_type 日志类型
     * @param int $relation_id 关联ID
     * @param string $remark 备注
     * @param int $admin_user_id 管理员ID
     * @param int $status 状态
     * @param string $sn_prefix 订单号前缀
     * @return bool
     * @throws Exception
     */
    public static function changeCardMoney($user_id, $amount, $type = 15, $log_type = 1, $relation_id = 0, $remark = '财务部入金', $admin_user_id = 0, $status = 1, $sn_prefix = 'MC')
    {
        Db::startTrans();
        try {
            // 查找或创建用户卡片记录
            $userCard = self::where('user_id', $user_id)->find();
            
            if (!$userCard) {
                // 创建新记录
/*                 $userCard = self::create([
                    'user_id' => $user_id,
                    'no' => '',
                    'money' => 0,
                    'fees' => 0,
                    'yesterday_interest' => 0,
                    'status' => 0
                ]); */
                throw new Exception('用户卡片未激活');
            }
            
            // 检查余额是否足够（扣款时）
            if ($amount < 0 && $userCard['money'] < abs($amount)) {
                throw new Exception('卡片余额不足');
            }
            
            $before_balance = $userCard['money'];
            $after_balance = $before_balance + $amount;
            
            // 更新卡片金额
            self::where('user_id', $user_id)->update([
                'money' => Db::raw('money + ' . $amount)
            ]);
            
            // 生成订单号
            $sn = build_order_sn($user_id, $sn_prefix);
            
            // 记录余额变更日志
            UserBalanceLog::create([
                'user_id' => $user_id,
                'type' => $type,
                'log_type' => $log_type,
                'relation_id' => $relation_id,
                'before_balance' => $before_balance,
                'change_balance' => $amount,
                'after_balance' => $after_balance,
                'remark' => $remark,
                'admin_user_id' => $admin_user_id,
                'status' => $status,
                'order_sn' => $sn,
            ]);
            
            Db::commit();
            return true;
            
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}
