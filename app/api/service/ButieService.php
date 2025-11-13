<?php

namespace app\api\service;

use app\model\butie\StockButie;
use app\model\butie\StockButieRecords;
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
            // 使用Model获取补贴信息
            $butie = StockButie::where('id', $butie_id)
                ->where('status', StockButie::STATUS_ENABLED)
                ->find();

            if (!$butie) {
                throw new Exception('补贴不存在或已下架');
            }

            // 检查用户是否已经领取过该补贴
            $exists = StockButieRecords::where('user_id', $user_id)
                ->where('butie_id', $butie_id)
                ->find();

            if ($exists) {
                throw new Exception('您已经领取过该补贴');
            }

            // 使用Model创建领取记录
            $record = new StockButieRecords();
            $recordData = [
                'user_id'  => $user_id,
                'butie_id' => $butie_id,
                'quantity' => 1,
                'price'    => $butie->price,
                'amount'   => $butie->price,
                'type'     => 1, // 补贴类型(1=活动)
                'status'   => StockButieRecords::STATUS_SUCCESS,
                'remark'   => "领取{$butie->title}",
            ];

            if (!$record->save($recordData)) {
                throw new Exception('记录领取记录失败');
            }

            // 增加用户的国补钱包余额
            User::changeInc($user_id, $butie->price, 'integral', 99, $record->id, 14, "领取补贴:{$butie->title}");

            Db::commit();
            return true;

        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}