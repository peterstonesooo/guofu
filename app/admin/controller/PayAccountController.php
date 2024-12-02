<?php

namespace app\admin\controller;

use app\model\PayAccount;

class PayAccountController extends AuthController
{
    public function payAccountList()
    {
        $req = request()->param();

        $builder = PayAccount::order('id', 'desc');
        if (isset($req['pay_account_id']) && $req['pay_account_id'] !== '') {
            $builder->where('id', $req['pay_account_id']);
        }
        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('user_id', $req['user_id']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showPayAccount()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = PayAccount::where('id', $req['id'])->find();
        }
        if(isset($req['user_id']) && $req['user_id'] !== ''){
            $data['realname'] = \app\model\User::where('id', $req['user_id'])->value('realname');
        }
        
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addPayAccount()
    {
        $req = $this->validate(request(), [
            'pay_type'=>'require|number',
            'id' => 'require|number',
            'account|账号' => 'require',
            'bank_name|银行名称' => 'max:100',
            'bank_branch|银行支行' => 'max:100',
        ]);


        if ($qr_img = upload_file('qr_img', false)) {
            $req['qr_img'] = $qr_img;
        }
        $user  = \app\model\User::where('id', $req['id'])->field('phone,realname')->find();
        $req['phone'] = $user['phone'];
        $req['name'] = $user['realname'];
        $req['user_id']=$req['id'];
        unset($req['id']);
        PayAccount::create($req);

        return out();
    }

    public function editPayAccount()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'pay_type'=>'require|number',
            'account|账号' => 'require',
            'phone|手机号' => 'mobile',
            'bank_name|银行名称' => 'max:100',
            'bank_branch|银行支行' => 'max:100',
        ]);

        $payAccount = PayAccount::where('id', $req['id'])->find();
        if ($payAccount['pay_type'] == 3) {
            if (empty($req['bank_name'])) {
                return out(null, 10001, '银行名称不能为空');
            }
            if (empty($req['bank_branch'])) {
                return out(null, 10001, '银行支行不能为空');
            }
        }
        else {
            unset($req['bank_name'], $req['bank_branch']);
        }
        if ($qr_img = upload_file('qr_img', false)) {
            $req['qr_img'] = $qr_img;
        }
        PayAccount::where('id', $req['id'])->update($req);

        return out();
    }

    public function changePayAccount()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        PayAccount::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function delPayAccount()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        PayAccount::destroy($req['id']);

        return out();
    }
}
