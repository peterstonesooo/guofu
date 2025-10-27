<?php

namespace app\api\service;

use app\model\subsidy_butie\DeclareRecord;
use app\model\subsidy_butie\DeclareRecordFund;
use app\model\subsidy_butie\DeclareSubsidyConfig;
use app\model\User;
use think\Exception;
use think\facade\Db;

class DeclareSubsidyService
{
    /**
     * 购买申报补贴配置
     * @param int $user_id 用户ID
     * @param int $subsidy_id 补贴配置ID
     * @param int $pay_type 支付方式 (1=充值余额, 2=团队奖金余额)
     * @return bool
     * @throws \Exception
     */
    public static function buyConfig($user_id, $subsidy_id, $pay_type = 1)
    {
        Db::startTrans();
        try {
            // 1. 获取补贴配置信息
            $config = DeclareSubsidyConfig::with('subsidyFunds')->find($subsidy_id);
            if (!$config) {
                throw new Exception('补贴配置不存在');
            }
            if ($config->status != 1) {
                throw new Exception('补贴配置已禁用');
            }

            $declare_amount = $config->declare_amount;
            $balanceField = ($pay_type == 1) ? 'topup_balance' : 'team_bonus_balance';

            // 2. 扣减用户余额
            User::changeInc(
                $user_id,
                -$declare_amount,
                $balanceField,
                101, // 日志类型：购买申报补贴
                0,
                1,
                "购买申报补贴:{$config->name}"
            );

            // 3. 创建申报记录
            $record = DeclareRecord::create([
                'user_id'        => $user_id,
                'subsidy_id'     => $subsidy_id,
                'declare_amount' => $declare_amount,
                'declare_cycle'  => $config->declare_cycle,
                'status'         => 1, // 直接设置为成功（根据业务需求可改为需要审核）
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ]);

            // 4. 创建资金明细记录
            if ($config->subsidyFunds && !$config->subsidyFunds->isEmpty()) {
                $fundData = [];
                foreach ($config->subsidyFunds as $fund) {
                    $fundData[] = [
                        'declare_id'   => $record->id,
                        'fund_type_id' => $fund->fund_type_id,
                        'fund_amount'  => $fund->fund_amount,
                        'created_at'   => date('Y-m-d H:i:s')
                    ];
                }

                if (!empty($fundData)) {
                    $declareRecordFund = new DeclareRecordFund();
                    $declareRecordFund->saveAll($fundData);
                }
            }

            Db::commit();
            return true;

        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 获取用户申报记录
     * @param int $user_id 用户ID
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array
     */
    public static function getUserRecords($user_id, $page = 1, $limit = 10)
    {
        $query = DeclareRecord::with(['subsidyConfig'])
            ->where('user_id', $user_id)
            ->order('id', 'desc');

        $total = $query->count();
        $list = $query->page($page, $limit)->select();

        return [
            'list'         => $list,
            'total'        => $total,
            'current_page' => $page,
            'total_page'   => ceil($total / $limit)
        ];
    }

    /**
     * 获取补贴配置详情
     * @param int $config_id 配置ID
     * @return array
     */
    public static function getConfigDetail($config_id)
    {
        $config = DeclareSubsidyConfig::with(['subsidyType', 'subsidyFunds' => function ($q) {
            $q->with('fundType');
        }])->find($config_id);

        if (!$config) {
            return [];
        }

        $detail = $config->toArray();

        // 处理资金配置
        if (isset($detail['subsidy_funds'])) {
            $detail['funds'] = $detail['subsidy_funds'];
            unset($detail['subsidy_funds']);
        }

        return $detail;
    }
}