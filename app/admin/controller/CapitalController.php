<?php

namespace app\admin\controller;

use app\common\CustomBootstrap;
use app\model\Capital;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\User;
use app\model\UserBalanceLog;
use Exception;
use GuzzleHttp\Client;
use think\facade\Db;
use think\facade\Cache;
class CapitalController extends AuthController
{
    public function topupList()
    {
        $req = request()->param();
        $req['type'] = 1;
        $data = $this->capitalList($req);
        $paymentConfig = PaymentConfig::select();
        $pconfig = [];
        foreach ($paymentConfig as $v) {
            $pconfig[$v['id']] = $v;
        }
        foreach ($data as $k => &$v) {
            $v['chanel_text'] = '';
            if (isset($v['payment'])) {
                if(isset($pconfig[$v->payment->payment_config_id])){
                    $payConfig = $pconfig[$v->payment->payment_config_id];
                    $chanel_name = config('map.payment_config.channel_map')[$payConfig['channel']];
                    $v['chanel_text'] = $chanel_name . '-' . $payConfig['mark'];
                    $v['pay_type'] = config('map.payment_config.type_map')[$payConfig['type']];
                }else{
                    $v['chanel_text'] = '未知';
                    $v['pay_type'] = '未知';
                }
            }
        }
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('auth_check', $this->adminUser['authGroup'][0]['title']);
        $this->assign('count',$data->total());

        return $this->fetch();
    }

    public function withdrawList()
    {
        $req = request()->param();
        $req['type'] = 2;
        if (!empty($req['export']) && $req['export'] == '支付宝导出' && $req['pay_channel'] != 3) {
            $this->error('请筛选支付宝的支付渠道');
        }

        $data = $this->capitalList($req);

        $logTypeList = [0=>'团队奖励'];
        $this->assign('logTypeList', $logTypeList);
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('auth_check', $this->adminUser['authGroup'][0]['title']);

        return $this->fetch();
    }

    private function capitalList($req)
    {
        $builder = Capital::alias('c')->field('c.*');
        $force = 'created_at_type_status';
        if ($req['type'] == 1) {
            //$builder->join('payment p', 'p.capital_id = c.id');
        }
        if (isset($req['pay_channel']) && $req['pay_channel'] !== '') {
           $builder->where('c.pay_channel', $req['pay_channel']);
           $force = 'index_0';
        }
        if($req['type']==2){
            $force ='';
            if(isset($req['log_type']) && $req['log_type']!=''){
                $builder->where('c.log_type', $req['log_type']);
            }
        }
        if (isset($req['capital_id']) && $req['capital_id'] !== '') {
            $force ='';
            $builder->where('c.id', $req['capital_id']);
        }
        if (isset($req['user']) && $req['user'] !== '') {
            $force='';
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('c.user_id', $user_ids);
        }
        if (isset($req['realname']) && $req['realname'] !== '') {
            $force='';
            $user_ids = User::where('realname', $req['realname'])->column('id');
            $user_ids[] = $req['realname'];
            $builder->whereIn('c.user_id', $user_ids);
        }
        if (isset($req['capital_sn']) && $req['capital_sn'] !== '') {
            $force='';
            $builder->where('c.capital_sn', $req['capital_sn']);
        }
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('c.type', $req['type']);
        }


/*         if (!empty($req['channel'])) {
            $builder->where('p.channel', $req['channel']);
        } */
        if (!empty($req['mark'])) {
            $builder->where('p.mark', $req['mark']);
        }
        
        if (!empty($req['start_date'])) {
            $builder->where('c.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }else{
            $builder->where('c.created_at', '>=', date('Y-m-d 00:00:00'));
        }
        if (!empty($req['end_date'])) {
            $builder->where('c.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
/*         if(isset($req['log_type']) && $req['log_type'] !== ''){
            $builder->where('c.log_type', $req['log_type']);
        } */
        if($force!=''){
            $builder->force($force);
        }
        $builder = $builder->order('c.id', 'desc');
        $builder1 = clone $builder;
        $builder2 = clone $builder;
        $builder3 = clone $builder;

        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('c.status', $req['status']);
        }

        if($req['type']==1){
            $total_amount = round($builder1->where('status',2)->sum('amount'), 2);
            
            $total_amount_fail = round($builder3->whereIn('status',[1,3])->sum('amount'), 2);
            $this->assign('total_amount', $total_amount);

            $this->assign('total_amount_fail', $total_amount_fail);

        }
        if ($req['type'] == 2) {
            $total_withdraw_amount = round($builder2->where('status',2)->sum('withdraw_amount'), 2);
            $this->assign('total_withdraw_amount', $total_withdraw_amount);
        }

        //1充值 2提现
        if (!empty($req['export'])) {
            $list = $builder->select();
            if ($req['type'] == 1) {
                foreach ($list as $v) {
                    $v->account_type = $v['user']['phone'] ?? '';
                    $v->realname=$v['user']['realname'] ?? '';
                }
                create_excel($list, [
                    'id' => '序号',
                    'account_type' => '用户',
                    'realname'=>'姓名',  
                    'capital_sn' => '单号',
                    'topup_status_text' => '充值状态',
                    'topup_pay_status_text' => '支付状态',
                    'pay_channel_text' => '支付渠道',
                    'amount' => '充值金额',
                    'audit_date' => '支付时间',
                    'created_at' => '创建时间'
                ], '充值记录-' . date('YmdHis'));
            } elseif ($req['type'] == 2) {
                foreach ($list as &$v) {
                    $v->account_type = $v['user']['phone'] ?? '';
                    $v->amountCapital = round(0 - $v['amount'], 2);
                    //if ($v->pay_channel == 4) {
                    if($v->bank_name!=''){
/*                         $v->payMethod = '银行：' . $v['bank_name'] ?? '';
                        $v->payMethod .= "\n" . '卡号：' . $v['account'] ?? '';
                        $v->payMethod .= "\n" . '分行：' . $v['bank_branch'] ?? ''; */
                        $v['payType'] = '银行卡';
                    } else {
                        //$v->payMethod = $v['account'];
                        $v['payType'] = '支付宝';
                    }
                    $v->shenheUser = $v['adminUser']['nickname'] ?? '';
                }
                create_excel($list, [
                    'id' => '序号',
                    // 'account_type' => '用户',
                    // 'capital_sn' => '单号',
                    // 'withdraw_status_text' => '状态',
                    // 'pay_channel_text' => '支付渠道',
                    'amountCapital' => '提现金额',
                    'withdraw_amount' => '到账金额',
                    'realname' => '收款人实名',
                    'account' => '收款账号',
                    'payType' => '收款方式',
                    'bank_name' => '银行名称',
                    // 'shenheUser' => '审核用户',
                    // 'audit_remark' => '拒绝理由',
                    // 'audit_date' => '审核时间',
                    'created_at' => '创建时间'
                ], '提现记录-' . date('YmdHis'));
            }
        }
        $listRows = $req['type'] == 1 ? 15 : 20;
        if(request()->get('per_page')){
            $listRows = request()->get('per_page');
        }
        $data = $builder->paginate(['list_rows'=>$listRows,'query' => $req]);
        return $data;
    }

    public function auditWithdraw()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'status' => 'require|in:2,3,4',
            'audit_remark' => 'max:200',
        ]);
        $adminUser = $this->adminUser;


        Db::startTrans();
        try {
            $withdraw_sn = Capital::auditWithdraw($req['id'], $req['status'], $adminUser['id'], $req['audit_remark'] ?? '');

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        $withdraw_sn = dbconfig('automatic_withdrawal_switch') == 1 ? $withdraw_sn : '';
        return out(['withdraw_sn' => $withdraw_sn ?? '']);
    }

    public function auditTopup()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'status' => 'require|in:2',
        ]);
        $adminUser = $this->adminUser;


        $topup = Cache::get('topup_' . $req['id'], '');
        if ($topup == '1') {
            return out(null, 10001, '重复操作');
        }
        Cache::set('topup_' . $req['id'], 1, 5);

        $capital = Capital::where('id', $req['id'])->find();
        if ($capital['status'] != 1) {
            return out(null, 10001, '该记录状态异常');
        }
        if ($capital['type'] != 1) {
            return out(null, 10002, '审核记录异常');
        }

        Db::startTrans();
        try {
            Payment::where('capital_id', $capital['id'])->update(['payment_time' => time(), 'status' => 2]);

            Capital::where('id', $capital['id'])->update(['is_admin_confirm' => 1]);
            //$userModel = new User();
            //$userModel->teamBonus($capital['user_id'], $capital['amount'], $capital['id']);

            Capital::topupPayComplete($capital['id'], $adminUser['id']);

            // 判断通道是否超过最大限额，超过了就关闭通道
            $payment = Payment::where('capital_id', $capital['id'])->find();
            PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function queryWithdrawResult()
    {
        $req = $this->validate(request(), [
            'withdraw_sn' => 'require',
        ]);

        $capital = Capital::where('withdraw_sn', $req['withdraw_sn'])->find();
        if (empty($capital)) {
            return out(null, 10001, '提现记录不存在');
        }
        if ($capital['status'] == 2) {
            return out(['is_success' => 1]);
        }
        if ($capital['status'] != 4) {
            return out(null, 10001, '提现失败');
        }

        return out(['is_success' => 0]);
    }

    public function batchAuditCapital()
    {
        $req = $this->validate(request(), [
            'ids' => 'require|array',
            'status' => 'require|in:2,3,4',
        ]);
        $adminUser = $this->adminUser;

        Db::startTrans();
        try {
            foreach ($req['ids'] as $v) {
                Capital::auditWithdraw($v, $req['status'], $adminUser['id'], '', true);
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function testEcnyDetail(){
            $req = ['startDate'=>request()->get('startDate','2024-02-08 00:00:00'),
                    'todayDate'=>request()->get('todayDate','2024-02-09 00:00:00'),];
                    

            $process = \app\model\ProcessReview::field('name,number,audit_status')->where('type',1)->order('sort','asc')->select();




/*             $stage = Capital::findCurrentStage($req['startDate'],$process,'2024-02-09 00:00:00','2024-02-17 23:59:59',$req['todayDate']);
            if($stage <=3){
                $stext = '审核中';
            }else{ */
                $stext = '未通过';
            //}
            $arr = [];
            foreach($process as $k=>$v){
/*                 if($k<$stage){
                    $statusText = $v['audit_status'] ==1 ? '已通过':'未通过';
                    $process[$k]['status']=$statusText;
                    //$arr = ['name'=>$v['name'],'status'=>'已完成'];
                }else if($stage==$k){
                    if(isset($process[$k-1]['status'])) 
                        $process[$k]['status']= $process[$k-1]['status'] == '未通过' ? '待审核' :'审核中';
                    else {
                        $process[$k]['status']= '审核中';
                    }
                    
                }else{
                    $process[$k]['status']='待审核';
                } */
                if($k<3){
                    $process[$k]['status'] = '已通过';
                }
                if($k==3){
                    $process[$k]['status'] = '未通过';
                }
                if($k==4){
                    $process[$k]['status'] = '待审核';
                }
                unset($process[$k]['number']);
            } 
/*             if($stage>=3){
                $process[3]['status'] = '审核中';
                $process[4]['status'] = '待审核';
            }   */ 
            $this->assign('process',$process);
            $this->assign('req',$req);
            $this->assign('stext',$stext);
        return $this->fetch();

    }
}
