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
            2 => '审批完成',
            3 => '已拨款'
        ]);

        return View::fetch();
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

        // 状态验证：只能拨款状态为"审批中"或"审批完成"的记录
        if ($apply->status == 3) {
            return out(null, 10002, '该申请已拨款');
        }

        // 更新状态为已拨款
        $apply->status = 3;
        $apply->remark = $req['remark'] ?? '';
        $apply->save();

        return out();
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

        // 状态验证：只能完成状态为"已拨款"的审批
        if ($apply->status != 3) {
            return out(null, 10003, '请先完成拨款操作');
        }

        // 更新状态为审批完成
        $apply->status = 2;
        $apply->remark = $req['remark'] ?? '';
        $apply->save();

        return out();
    }
}