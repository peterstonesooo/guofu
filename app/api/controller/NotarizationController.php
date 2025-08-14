<?php

namespace app\api\controller;


use app\model\Notarization;
use app\model\User;
use think\facade\Db;
use app\model\TaxOrder;
use app\model\Capital;
use app\model\PayAccount;

class NotarizationController extends AuthController
{
    public static $statusText = [
        1 => '公证中',
        2 => '公证完成',
        3 => '提现',
    ];

    public static $statusTextBail = [
        1 => '登记中',
        2 => '已完成',
    ];
    public function list(){
        $user = $this->user;
        $list = Notarization::where('user_id',$user['id'])->where('type',0)->select();
        $data = [
            'list' => $list,
            'can_withdraw' => $user['notarization_balance'], 
            'taxes' => 0,
        ];
/*         foreach($list as $key=>$item){
            if($item['status'] == 2){
                $data['can_withdraw'] = bcadd($data['can_withdraw'], $item['money'], 2);
            }
        }
 */        
        $list2 = TaxOrder::where('user_id',$user['id'])->where('status',3)->select();
        $taxes = 0;
        foreach($list2 as $key=>$item){
                $taxes = bcadd($taxes, $item['money'], 2);
                $taxes = bcadd($taxes, $item['taxes_money'], 2); 
        }
        $already = Notarization::where('user_id',$user['id'])->where('type',0)->sum('money');
        $canMoney = bcsub($taxes, $already, 2);
        $data['taxes'] = $canMoney;

        return out($data);
    }



    public function order(){
        return out(null, 10001, '暂未开放');

        $user = $this->user;
        $req = $this->validate(request(),[
            'money|公证金额' => 'require|float|between:20000,9999999',
            'pay_password|支付密码' => 'require|length:6,25',
        ]);
        if($user['status'] != 1){
            return out(null, 10001, '用户已冻结');
        }
        if($user['is_realname'] != 1){
            return out(null, 10001, '请先实名认证');    
        }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $list = TaxOrder::where('user_id',$user['id'])->where('status',3)->select();
        $taxes = 0;
        foreach($list as $key=>$item){
                $taxes = bcadd($taxes, $item['money'], 2);
                $taxes = bcadd($taxes, $item['taxes_money'], 2); 
        }
        $alreay = Notarization::where('user_id',$user['id'])->where('type',0)->sum('money');
        $canMoney = bcsub($taxes, $alreay, 2);
        if($canMoney<0 || $canMoney < $req['money']){
            return out(null, 10001,'申报金额不能超过已退税金额 '.$canMoney);
        }   
        $fee = bcmul($req['money'],0.01,2);
        if($fee > $user['topup_balance']){
            return out(null, 10001,'余额不足，请充值');
        }
            
        $endTime = date('Y-m-d',strtotime(date('Y-m-d', strtotime('+7 days'))));
        $data = [
            'user_id' => $user['id'],
            'money' => $req['money'],
            'fees' => $fee,
            'status' => 1,  
            'type' => 0, // 0 公证 1 保证金
            'end_time' => $endTime,
        ];
        Db::startTrans();
        try{
            $notarization = Notarization::create($data);
            User::changeInc($user['id'], -$fee,'topup_balance',35,$notarization['id'],3,'公证缴费' );
            Db::commit();
            return out($notarization);
        }catch (\Exception $e){
            Db::rollback();
            throw $e;
            return out(null, 10001,'公证缴费失败');
        }
    }

    public function certificate(){
        $user = $this->user;
        $list = Notarization::where('user_id',$user['id'])->where('type',0)->where('status','>=',2)->select();
        $data = [
            'list' => $list,
            'realname' => $user['realname'],
            'ic_number' => $user['ic_number'],
        ];
        foreach($list as $key=>$item){
            $item['end_time'] = date('Y-m-d ', strtotime($item['end_time']));
        }
        return out($data);
    }

    public function bailList(){
        $data = Notarization::where('user_id',$this->user['id'])->where('type',1)->select();
        $list = [];
        $list['list'] = $data;
        $list['can_withdraw'] = $this->user['bail_balance'];

        return out($list);
    }

    public function bailInfo(){
        $user = $this->user;
        $data['notarization_money'] = $user['notarization_balance'];
        $already = Notarization::where('user_id', $user['id'])->where('type', 1)->sum('money');
        $data['bail_money'] = $already;
        $data['no_bail_money'] = bcsub($data['notarization_money'], $already, 2);

        return out($data);
    }

    public function bailOrder(){
        return out(null, 10001, '暂未开放');
        $user = $this->user;
        $req = $this->validate(request(),[
            'money|监管金额' => 'require|float|between:5000,9999999',
            'pay_password|支付密码' => 'require|length:6,25',
        ]);
        if($user['status'] != 1){
            return out(null, 10001, '用户已冻结');
        }
        if($user['is_realname'] != 1){
            return out(null, 10001, '请先实名认证');    
        }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $already = Notarization::where('user_id', $user['id'])->where('type', 1)->sum('money');
        $canMoney = bcsub($user['notarization_balance'], $already, 2);
        if($canMoney<0 || $canMoney < $req['money']){
            return out(null, 10001,'监管金额不能超过未监管金额');
        }

        $fees = bcmul($req['money'], 0.05, 2);

        if($fees > $user['topup_balance']){
            return out(null, 10001,'余额不足，请充值');
        }
        $data = [
            'user_id' => $user['id'],
            'money' => $req['money'],
            'fees' => $fees,
            'status' => 1,  
            'type' => 1, // 0 公证 1 保证金
            'end_time' => date('Y-m-d',strtotime(date('Y-m-d', strtotime('+3 days')))),
        ];

        Db::startTrans();
        try{
            $notarization = Notarization::create($data);
            User::changeInc($user['id'], -$fees,'topup_balance',35,$notarization['id'],3,'监管金额缴费' );
            //User::changeInc($user['id'], $req['money'],'bail_balance',2,$notarization['id'],11,'保证金' );
            Db::commit();
            return out($notarization);
        }catch (\Exception $e){
            Db::rollback();
            throw $e;
            return out(null, 10001,'监管金额缴费失败');   
        }

    }

    public function withdraw(){
        $user = $this->user;
        $req = $this->validate(request(),[
            'amount|提现金额' => 'require|float',
            'pay_password|支付密码' => 'require|length:6,25',
            'bank_id|银行卡'=>'require|number',
            'pay_channel|收款渠道' => 'require|number',

        ]);
        if($user['status'] != 1){
            return out(null, 10001, '用户已冻结');
        }
        if($user['is_realname'] != 1){
            return out(null, 10001, '请先实名认证');    
        }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $payAccount = PayAccount::where('user_id', $user['id'])->where('id',$req['bank_id'])->find();
        if (empty($payAccount)) {
            return out(null, 802, '请先设置此收款方式');
        }
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        if ($payAccount['pay_type'] == 3 && dbconfig('bank_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启银行卡提现');
        }
        if ($payAccount['pay_type'] == 2 && dbconfig('alipay_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启支付宝提现');
        }
        if ($payAccount['pay_type'] == 1) {
            return out(null, 10001, '暂未开启微信提现');
        }

        $timeNum = (int)date('Hi');
        if ($timeNum < 1000 || $timeNum > 1700) {
            return out(null, 10001, '提现时间为早上10:00到晚上17:00');
        }

        
        $sum = $user['bail_balance'];
        if($sum <= 0){
            return out(null, 10001, '没有可提现的完成监管金额 '.$sum);
        }

        if($req['amount'] <= 0){
            return out(null, 10001, '提现金额必须大于0');
        }
        if($req['amount'] > $sum){
            return out(null, 10001, '提现金额不能大于完成监管金额 '.$sum);   
        }

        //$ids = Notarization::where('user_id',$user['id'])->where('status',2)->column('id');
        
        $num = Capital::where('user_id', $user['id'])->where('type', 2)->whereIn('log_type',[0,1])->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
        if ($num >= dbconfig('per_day_withdraw_max_num')) {
            return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
        }
        $field = 'large_subsidy';
        $log_type =3;
        
        Db::startTrans();
        try {

            $capital_sn = build_order_sn($user['id']);

            $payMethod = $req['pay_channel'] == 4 ? 1 : $req['pay_channel'];
            // 保存提现记录
            $capital = Capital::create([
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => -$req['amount'],
                'withdraw_amount' => $req['amount'],
                'withdraw_fee' => 0,
                'realname' => $payAccount['name'],
                'phone' => $payAccount['phone'],
                'collect_qr_img' => $payAccount['qr_img'],
                'account' => $payAccount['account'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_branch'],
            ]);

            User::changeInc($user['id'],-$req['amount'],$field,2,$capital['id'],$log_type,'',0,1,'WD');
            User::changeInc($user['id'],-$req['amount'],'bail_balance',2,$capital['id'],11,'',0,1,'WD');

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();

    }
}
