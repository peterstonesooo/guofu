<?php

namespace app\admin\controller;

use app\model\FinanceApprovalConfig;
use think\facade\Cache;

class FinanceApprovalConfigController extends AuthController
{
    public function approvalConfigList()
    {
        $req = request()->param();

        $builder = FinanceApprovalConfig::order('level asc');
        if (isset($req['level']) && $req['level'] !== '') {
            $builder->where('level', $req['level']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showApprovalConfig()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = FinanceApprovalConfig::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addApprovalConfig()
    {
        $req = $this->validate(request(), [
            'level|审批档次'           => 'require|number|unique:finance_approval_config',
            'name|档次名称'            => 'require|max:50',
            'withdraw_amount|提款金额' => 'require|float',
            'approval_fee|审批费'      => 'require|float',
        ]);

        FinanceApprovalConfig::create($req);

        return out();
    }

    public function editApprovalConfig()
    {
        $req = $this->validate(request(), [
            'id'                       => 'require|number',
            'name|档次名称'            => 'require|max:50',
            'withdraw_amount|提款金额' => 'require|float',
            'approval_fee|审批费'      => 'require|float',
        ]);

        FinanceApprovalConfig::where('id', $req['id'])->update($req);
        // 清除缓存
        Cache::delete('finance_approval_configs');
        return out();
    }

    public function changeApprovalConfig()
    {
        $req = $this->validate(request(), [
            'id'    => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        FinanceApprovalConfig::where('id', $req['id'])->update([$req['field'] => $req['value']]);
        // 清除缓存
        Cache::delete('finance_approval_configs');
        return out();
    }
}