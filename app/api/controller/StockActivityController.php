<?php

namespace app\api\controller;

use app\model\StockActivityClaims;

class StockActivityController extends AuthController
{
    /**
     * 获取活动领取记录
     */
    public function getClaimRecord()
    {
        $user = $this->user;

        try {
            $record = StockActivityClaims::where('user_id', $user['id'])
                ->where('activity_name', '国庆新人福利')
                ->find();

            if ($record) {
                return out([
                    'claimed'    => true,
                    'claim_date' => $record->claim_date,
                    'status'     => $record->status
                ], 200, '已领取');
            }

            return out(['claimed' => false], 200, '未领取');
        } catch (\Exception $e) {
            return out([], 10001, '查询失败: ' . $e->getMessage());
        }
    }
}