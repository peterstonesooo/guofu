<?php

namespace app\api\service;

use app\model\User;
use think\Exception;
use think\facade\Db;

class ButieService
{
    /**
     * 领取补贴
     * @param int $user_id 用户ID
     * @param int $butie_id 补贴ID
     * @return bool
     * @throws \Exception
     */
    public static function receiveButie($user_id, $butie_id)
    {
        Db::startTrans();
        try {
            // 获取补贴信息
            $butie = Db::name('stock_butie')
                ->where('id', $butie_id)
                ->where('status', 1)
                ->find();

            if (!$butie) {
                throw new Exception('补贴不存在或已下架');
            }

            // 检查用户是否已经领取过该补贴
            $exists = Db::name('stock_butie_records')
                ->where('user_id', $user_id)
                ->where('butie_id', $butie_id)
                ->find();

            if ($exists) {
                throw new Exception('您已经领取过该补贴');
            }

            // 记录领取记录
            $recordId = Db::name('stock_butie_records')->insertGetId([
                'user_id'    => $user_id,
                'butie_id'   => $butie_id,
                'quantity'   => 1,
                'price'      => $butie['price'],
                'amount'     => $butie['price'],
                'type'       => 1, // 补贴类型(1=活动)
                'status'     => 1,
                'remark'     => "领取{$butie['title']}",
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if (!$recordId) {
                throw new Exception('记录领取记录失败');
            }

            // 增加用户的国补钱包余额
            User::changeInc($user_id, $butie['price'], 'national_subsidy_balance', 99, $recordId, 14, "领取补贴:{$butie['title']}");

            Db::commit();
            return true;

        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}