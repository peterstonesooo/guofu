<?php

namespace app\api\controller;

use app\model\FinanceApprovalApply;
use app\model\FinanceApprovalConfig;

class FinanceApprovalController extends AuthController
{
    /**
     * 获取审批档次配置
     */
    public function getLevelConfig()
    {
        $configs = FinanceApprovalConfig::where('status', FinanceApprovalConfig::STATUS_ENABLED)
            ->field('id, level, name, withdraw_amount, approval_fee')
            ->select();

        return out($configs);
    }

    /**
     * 提交审批申请
     */
    public function apply()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'config_id'    => 'require|number',
            'pay_password' => 'require'
        ]);

        // 支付密码验证
        if (empty($user['pay_password'])) {
            return out(null, 10010, '请先设置支付密码');
        }
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10011, '支付密码错误');
        }

        // 获取配置
        $config = FinanceApprovalConfig::find($req['config_id']);
        if (!$config) {
            return out(null, 10001, '审批配置不存在');
        }

        // 使用配置中的固定金额和审批费
        $data = [
            'user_id'         => $user['id'],
            'config_id'       => $config['id'],
            'withdraw_amount' => $config['withdraw_amount'], // 直接使用配置的固定金额
            'approval_fee'    => $config['approval_fee'],       // 直接使用配置的固定审批费
            'status'          => FinanceApprovalApply::STATUS_PENDING
        ];

        try {
            $approval = FinanceApprovalApply::create($data);
            return out(['id' => $approval->id], 200, '申请提交成功');
        } catch (\Exception $e) {
            return out(null, 500, '申请提交失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取申请记录
     */
    public function records()
    {
        $user = $this->user;
        $page = input('page/d', 1);
        $limit = input('limit/d', 10);

        $list = FinanceApprovalApply::with(['config'])
            ->where('user_id', $user['id'])
            ->order('id', 'desc')
            ->paginate(['page' => $page, 'list_rows' => $limit])
            ->each(function ($item) {
                $item->status_text = $item->getStatusTextAttr(null, $item->toArray());
                return $item;
            })
            ->toArray();

        return out($list);
    }
}