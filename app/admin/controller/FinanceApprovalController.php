<?php

namespace app\admin\controller;

use app\model\FinanceApprovalApply;
use app\model\FinanceApprovalConfig;
use app\model\User;
use think\facade\View;

class FinanceApprovalController extends AuthController
{
    // 审批申请列表
    public function applyList()
    {
        $req = request()->param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportApplyList();
        }
        $builder = FinanceApprovalApply::alias('a')
            ->field('a.*, u.phone, u.realname, c.name as config_name')
            ->join('user u', 'u.id = a.user_id')
            ->join('finance_approval_config c', 'c.id = a.config_id');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('a.user_id', $user_ids);
        }
        if (isset($req['config_id']) && $req['config_id'] !== '') {
            $builder->where('a.config_id', $req['config_id']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('a.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('a.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('a.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 分页和排序
        $data = $builder->order('a.id', 'desc')->paginate(['list_rows' => 15, 'query' => $req]);

        // 获取审批配置下拉选项
        $configOptions = FinanceApprovalConfig::where('status', 1)->select();

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('configOptions', $configOptions);
        View::assign('statusMap', [
            1 => '审批中',
            2 => '已拨款',
            3 => '审批完成'
        ]);

        return View::fetch();
    }

    // 审批完成操作
    public function completeApply()
    {
        $req = $this->validate(request(), [
            'id'     => 'require|number',
            'remark' => 'max:255',
        ]);

        $apply = FinanceApprovalApply::find($req['id']);
        if (!$apply) {
            return out(null, 10001, '申请记录不存在');
        }

        // 状态验证：只能完成状态为"审批中"的审批
        if ($apply->status != 1) {
            return out(null, 10002, '该申请无法完成审批');
        }

        // 更新状态为审批完成
        $apply->status = 3;
        $apply->remark = $req['remark'] ?? '';
        $apply->save();

        return out();
    }

    // 拨款操作
    public function fundApply()
    {
        $req = $this->validate(request(), [
            'id'     => 'require|number',
            'remark' => 'max:255',
        ]);

        $apply = FinanceApprovalApply::find($req['id']);
        if (!$apply) {
            return out(null, 10001, '申请记录不存在');
        }

        // 状态验证：只能拨款状态为"审批完成"的记录
        if ($apply->status != 3) {
            return out(null, 10003, '请先完成审批操作');
        }

        // 更新状态为已拨款
        $apply->status = 2;
        $apply->remark = $req['remark'] ?? '';
        $apply->save();

        return out();
    }

    // 批量完成审批操作
    public function batchCompleteApply()
    {
        $req = $this->validate(request(), [
            'ids'    => 'require|array',
            'remark' => 'max:255',
        ]);

        $successCount = 0;
        $errorMessages = [];

        foreach ($req['ids'] as $id) {
            $apply = FinanceApprovalApply::find($id);
            if (!$apply) {
                $errorMessages[] = "ID {$id}: 申请记录不存在";
                continue;
            }

            if ($apply->status != 1) {
                $errorMessages[] = "ID {$id}: 该申请无法完成审批";
                continue;
            }

            $apply->status = 3;
            $apply->remark = $req['remark'] ?? $apply->remark;
            if ($apply->save()) {
                $successCount++;
            } else {
                $errorMessages[] = "ID {$id}: 审批完成失败";
            }
        }

        if (!empty($errorMessages)) {
            return out(['success_count' => $successCount], 10004, implode('; ', $errorMessages));
        }

        return out(['success_count' => $successCount]);
    }

    // 批量拨款操作
    public function batchFundApply()
    {
        $req = $this->validate(request(), [
            'ids'    => 'require|array',
            'remark' => 'max:255',
        ]);

        $successCount = 0;
        $errorMessages = [];

        foreach ($req['ids'] as $id) {
            $apply = FinanceApprovalApply::find($id);
            if (!$apply) {
                $errorMessages[] = "ID {$id}: 申请记录不存在";
                continue;
            }

            if ($apply->status != 3) {
                $errorMessages[] = "ID {$id}: 请先完成审批操作";
                continue;
            }

            $apply->status = 2;
            $apply->remark = $req['remark'] ?? $apply->remark;
            if ($apply->save()) {
                $successCount++;
            } else {
                $errorMessages[] = "ID {$id}: 拨款失败";
            }
        }

        if (!empty($errorMessages)) {
            return out(['success_count' => $successCount], 10005, implode('; ', $errorMessages));
        }

        return out(['success_count' => $successCount]);
    }


    // 导出审批申请列表
    public function exportApplyList()
    {
        $req = request()->param();
        $builder = FinanceApprovalApply::alias('a')
            ->field('a.*, u.phone, u.realname, c.name as config_name')
            ->join('user u', 'u.id = a.user_id')
            ->join('finance_approval_config c', 'c.id = a.config_id');

        // 搜索条件（与applyList方法保持一致）
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('a.user_id', $user_ids);
        }
        if (isset($req['config_id']) && $req['config_id'] !== '') {
            $builder->where('a.config_id', $req['config_id']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('a.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('a.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('a.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $builder->order('a.id', 'desc')->select();

        // 状态映射
        $statusMap = [
            1 => '审批中',
            2 => '已拨款',
            3 => '审批完成',
        ];

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $exportData[] = [
                'id'              => $item->id,
                'phone'           => $item->phone,
                'realname'        => $item->realname,
                'config_name'     => $item->config_name,
                'withdraw_amount' => $item->withdraw_amount,
                'approval_fee'    => $item->approval_fee,
                'status'          => $statusMap[$item->status] ?? '未知状态',
                'remark'          => $item->remark,
                'created_at'      => $item->created_at,
            ];
        }

        // 表头
        $header = [
            'id'              => 'ID',
            'phone'           => '用户手机号',
            'realname'        => '用户姓名',
            'config_name'     => '审批档次',
            'withdraw_amount' => '提现金额',
            'approval_fee'    => '审批费',
            'status'          => '状态',
            'remark'          => '备注',
            'created_at'      => '创建时间',
        ];

        // 导出Excel
        $filename = '审批申请列表-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}