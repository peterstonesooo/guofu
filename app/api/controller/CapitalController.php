<?php

namespace app\api\controller;

use app\model\Capital;
use app\model\HouseFee;
use app\model\PayAccount;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\ProcessReview;
use app\model\User;
use app\model\UserBalanceLog;
use Exception;
use think\facade\Db;

class CapitalController extends AuthController
{
    public function topup()
    {
        $req = $this->validate(request(), [
            'amount|充值金额' => 'require|float',
            'pay_channel|支付渠道' => 'require|number',
            'payment_config_id' => 'require|number',
            'pay_voucher_img_url' => 'url',
            'uname|付款人'=>'min:2',
        ]);
        $user = $this->user;

        if ($req['pay_channel'] == 0 && empty($req['pay_voucher_img_url'])) {
            if ( empty($req['pay_voucher_img_url'])) {
                return out(null, 10001, '请上传支付凭证图片');
            }
        }
        if ($req['pay_channel'] == 0 && (!isset($req['uname']) || $req['uname']=='')){
            return out(null, 10001, '请填写付款人');
        }
        if($user['realname']=='' || $user['ic_number']==''){
            return out(null, 10001, '请先实名认证');
        }
        // if (in_array($req['pay_channel'], [2,3,4,5,6,8,9,10])) {
        //     $type = $req['pay_channel'] - 1;
        //     if ($req['pay_channel'] == 6) {
        //         $type = 4;
        //     }
        // }
        $type = $req['pay_channel'];
        $paymentConf = PaymentConfig::userCanPayChannel($req['payment_config_id'], $type, $req['amount']);

        Db::startTrans();
        try {
            $capital_sn = build_order_sn($user['id']);
            // 创建充值单
            $capital = Capital::create([
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 1,
                'pay_channel' => $req['pay_channel'],
                'amount' => $req['amount'],
                'realname'=>$req['uname']??'',

            ]);

            $card_info = json_encode($paymentConf['card_info']);
            if (empty($card_info)) {
                $card_info = '';
            }
            // 创建支付记录
            Payment::create([
                'user_id' => $user['id'],
                'trade_sn' => $capital_sn,
                'pay_amount' => $req['amount'],
                'product_type' => 2,
                'capital_id' => $capital['id'],
                'payment_config_id' => $paymentConf['id'],
                'channel' => $type,
                'mark' => $paymentConf['mark'],
                'type' => $paymentConf['type'],
                'card_info' => $card_info,
                'pay_voucher_img_url' => $req['pay_voucher_img_url'] ?? '',
            ]);
            // 发起支付
            if ($paymentConf['channel'] == 1) {
                $ret = Payment::requestPayment_hongya($capital_sn, $paymentConf['mark'], $req['amount']);
            }
            elseif ($paymentConf['channel'] == 2) {
                $ret = Payment::requestPayment_haizei($capital_sn, $paymentConf['mark'], $req['amount']);
            }
            elseif ($paymentConf['channel'] == 3) {
                $ret = Payment::requestPayment_start($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==8){
                $ret = Payment::requestPayment_xiangjiao($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==9){
                $ret = Payment::requestPayment5($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==10){
                $ret = Payment::requestPayment6($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==11){
                $ret = Payment::requestPayment7($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==12){
                $ret = Payment::requestPayment8($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==13){
                $ret = Payment::requestPayment9($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==14){
                $ret = Payment::requestPayment10($capital_sn, $paymentConf['mark'], $req['amount']);
            
            }else if($paymentConf['channel']==15){
                $ret = Payment::requestPayment11($capital_sn, $paymentConf['mark'], $req['amount']);
            
            }else if($paymentConf['channel']==16){
                $ret = Payment::requestPayment12($capital_sn, $paymentConf['mark'], $req['amount']);
            
            }else if($paymentConf['channel']==17){
                $ret = Payment::requestPayment13($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==18){
                $ret = Payment::requestPayment_daxiang($capital_sn, $paymentConf['mark'], $req['amount']);
            
            }else if($paymentConf['channel']==19){
                $ret = Payment::requestPayment_huitong($capital_sn, $paymentConf['mark'], $req['amount']);
            
            }else if($paymentConf['channel']==20){
                $ret = Payment::requestPayment_999($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==21){
                $ret = Payment::requestPayment_xxpay($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==22){
                $ret = Payment::requestPayment_sifang($capital_sn, $paymentConf['mark'], $req['amount']);
            }
            else if($paymentConf['channel']==23){
                $ret = Payment::requestPayment_startlink($capital_sn, $paymentConf['mark'], $req['amount']);
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['trade_sn' => $capital_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);
    }

    public function applyWithdraw()
    {
        //return out(null, 10001, '网络问题，请稍后再试');
        if(!domainCheck()){
            return out(null, 10001, '请联系客服下载最新app');
        }
        $req = $this->validate(request(), [
            'amount|提现金额' => 'require|float',
            'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            'bank_id|银行卡'=>'require|number',
        ]);
        $user = $this->user;

        if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }


        $pay_type = $req['pay_channel'] - 1;
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
/*         if ($req['pay_channel'] == 7 && dbconfig('digital_withdrawal_switch') == 0) {
            return out(null, 10001, '连续签到30天才可提现国务院津贴');
        } */

        // 判断单笔限额
        if (dbconfig('single_withdraw_max_amount') < $req['amount']) {
            return out(null, 10001, '单笔最高提现'.dbconfig('single_withdraw_max_amount').'元');
        }
        if (dbconfig('single_withdraw_min_amount') > $req['amount']) {
            return out(null, 10001, '单笔最低提现'.dbconfig('single_withdraw_min_amount').'元');
        }
        // 每天提现时间为8：00-20：00 早上8点到晚上20点
        $timeNum = (int)date('Hi');
        if ($timeNum < 1000 || $timeNum > 1700) {
            return out(null, 10001, '提现时间为早上10:00到晚上17:00');
        }

        // $a = UserBalanceLog::where('user_id', $user['id'])->where('type', 18)->where('created_at', '>', '2024-06-06 08:00:00')->where('created_at', '<', '2024-06-06 12:00:00')->find();
        // $b = UserBalanceLog::where('user_id', $user['id'])->where('type', 19)->where('created_at', '>', '2024-06-06 08:00:00')->where('created_at', '<', '2024-06-06 12:00:00')->find();
        // if($a || $b) {
        //     return out(null, 10001, '您的账号数据异常，请6月7日进行提现');
        // }
        $user = User::where('id', $user['id'])->lock(true)->find();
 

        
        Db::startTrans();
        try {

            $field = 'team_bonus_balance';
            $log_type =3;
            if ($user[$field] < $req['amount']) {
                return out(null, 10001, '可提现金额不足');
            }
   
            // 判断每天最大提现次数
            $num = Capital::where('user_id', $user['id'])->where('type', 2)->whereIn('log_type',[0,1])->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
            if ($num >= dbconfig('per_day_withdraw_max_num')) {
                return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
            }

            $capital_sn = build_order_sn($user['id']);
            $change_amount = 0 - $req['amount'];
            $withdraw_fee = round(dbconfig('withdraw_fee_ratio')/100*$req['amount'], 2);
            $withdraw_amount = round($req['amount'] - $withdraw_fee, 2);

            $payMethod = $req['pay_channel'] == 4 ? 1 : $req['pay_channel'];
            // 保存提现记录
            $capital = Capital::create([
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => $change_amount,
                'withdraw_amount' => $withdraw_amount,
                'withdraw_fee' => $withdraw_fee,
                'realname' => $payAccount['name'],
                'phone' => $payAccount['phone'],
                'collect_qr_img' => $payAccount['qr_img'],
                'account' => $payAccount['account'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_branch'],
            ]);
            // 扣减用户余额
            User::changeInc($user['id'],$change_amount,$field,2,$capital['id'],$log_type,'',0,1,'WD');
            //User::changeInc($user['id'],$change_amount,'invite_bonus',2,$capital['id'],1);
            //User::changeBalance($user['id'], $change_amount, 2, $capital['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    //积分兑换
    public function integralExchange(){
        return out(null, 10001, '积分兑换已关闭');
        $req = $this->validate(request(), [
            'amount|兑换积分' => 'require|integer',
            //'pay_password|支付密码' => 'require',
        ]);
        $user = $this->user;
        if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
/*         $user = User::where('id', $user['id'])->find();
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        } */
        if($user['integral']<$req['amount']){
            return out(null, 10001, '积分不足');
        }
        $integral = $req['amount'];
        $amount = bcmul($integral,0.01,2);
        Db::startTrans();
        try{
            User::changeInc($user['id'],-$integral,'integral',26,0,2,'积分兑换',0,1,'SE');
            User::changeInc($user['id'],-$integral,'income_balance',26,0,4,'积分兑换',0,1,'SE');
            User::changeInc($user['id'],$integral,'team_bonus_balance',26,0,3,'积分兑换',0,1,'SE');

            //User::changeInc($user['id'],$amount,'balance',22,0,1,'积分兑换');
            Db::commit();
        }catch(Exception $e){
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function applyWithdrawPurse()
    {
        return out(null, 10001, '请先办理共富工程项目收益申报');
        if(!domainCheck()){
            return out(null, 10001, '请联系客服下载最新app');
        }
        $req = $this->validate(request(), [
            'amount|提现金额' => 'require|float',
            'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            'bank_id|银行卡'=>'require|number',
        ]);
        $user = $this->user;

        if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }


        $pay_type = $req['pay_channel'] - 1;
        $payAccount = PayAccount::where('user_id', $user['id'])->where('id',$req['bank_id'])->find();
        if (empty($payAccount)) {
            return out(null, 802, '请先设置此收款方式');
        }
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        if ($req['pay_channel'] == 4 && dbconfig('bank_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启银行卡提现');
        }
        if ($req['pay_channel'] == 3 && dbconfig('alipay_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启支付宝提现');
        }
/*         if ($req['pay_channel'] == 7 && dbconfig('digital_withdrawal_switch') == 0) {
            return out(null, 10001, '连续签到30天才可提现国务院津贴');
        } */

        // 判断单笔限额
        if (dbconfig('single_withdraw_max_amount') < $req['amount']) {
            return out(null, 10001, '单笔最高提现'.dbconfig('single_withdraw_max_amount').'元');
        }
        if (dbconfig('single_withdraw_min_amount') > $req['amount']) {
            return out(null, 10001, '单笔最低提现'.dbconfig('single_withdraw_min_amount').'元');
        }
        // 每天提现时间为8：00-20：00 早上8点到晚上20点
        $timeNum = (int)date('Hi');
        if ($timeNum < 1000 || $timeNum > 1700) {
            return out(null, 10001, '提现时间为早上10:00到晚上17:00');
        }

        // $a = UserBalanceLog::where('user_id', $user['id'])->where('type', 18)->where('created_at', '>', '2024-06-06 08:00:00')->where('created_at', '<', '2024-06-06 12:00:00')->find();
        // $b = UserBalanceLog::where('user_id', $user['id'])->where('type', 19)->where('created_at', '>', '2024-06-06 08:00:00')->where('created_at', '<', '2024-06-06 12:00:00')->find();
        // if($a || $b) {
        //     return out(null, 10001, '您的账号数据异常，请6月7日进行提现');
        // }
       
        $user = User::where('id', $user['id'])->lock(true)->find();
 

        
        Db::startTrans();
        try {

            $field = 'gf_purse';
            $log_type =2;
            if ($user[$field] < 100) {
                return out(null, 10001, '提现金额需要高于100');
            }
            if ($user[$field] < $req['amount']) {
                return out(null, 10001, '可提现金额不足');
            }
   
            // 判断每天最大提现次数
            $num = Capital::where('user_id', $user['id'])->where('type', 2)->whereIn('log_type',[0,1])->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
            if ($num >= dbconfig('per_day_withdraw_max_num')) {
                return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
            }

            $capital_sn = build_order_sn($user['id']);
            $change_amount = 0 - $req['amount'];
            $withdraw_fee = round(dbconfig('withdraw_fee_ratio')/100*$req['amount'], 2);
            $withdraw_amount = round($req['amount'] - $withdraw_fee, 2);

            $payMethod = $req['pay_channel'] == 4 ? 1 : $req['pay_channel'];
            // 保存提现记录
            $capital = Capital::create([
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => $change_amount,
                'withdraw_amount' => $withdraw_amount,
                'withdraw_fee' => $withdraw_fee,
                'realname' => $payAccount['name'],
                'phone' => $payAccount['phone'],
                'collect_qr_img' => $payAccount['qr_img'],
                'account' => $payAccount['account'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_branch'],
                'log_type' => 1,
            ]);
            // 扣减用户余额
            User::changeInc($user['id'],$change_amount,$field,2,$capital['id'],$log_type,'',0,1,'TX');
            //User::changeInc($user['id'],$change_amount,'invite_bonus',2,$capital['id'],1);
            //User::changeBalance($user['id'], $change_amount, 2, $capital['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function applyWithdrawShop()
    {
        if(!domainCheck()){
            return out(null, 10001, '请联系客服下载最新app');
        }
        $req = $this->validate(request(), [
            //'amount|提现金额' => 'require|number',
            //'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            //'bank_id|银行卡'=>'require|number',
        ]);
        $user = $this->user;

        if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        $user = User::where('id', $user['id'])->find();
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        // 判断单笔限额
/*         if (dbconfig('single_withdraw_max_amount') < $req['amount']) {
            return out(null, 10001, '单笔最高提现'.dbconfig('single_withdraw_max_amount').'元');
        }
        if (dbconfig('single_withdraw_min_amount') > $req['amount']) {
            return out(null, 10001, '单笔最低提现'.dbconfig('single_withdraw_min_amount').'元');
        } */
        // 每天提现时间为8：00-20：00 早上8点到晚上20点
        $timeNum = (int)date('Hi');
        if ($timeNum < 800 || $timeNum > 1700) {
            return out(null, 10001, '提现时间为早上8:00到晚上17:00');
        }
/*         if($user['digital_yuan_amount'] < $req['amount']){
            return out(null, 10001, '可提现金额不足');
        } */

        Db::startTrans();
        try {
            $field = 'digital_yuan_amount';
            $text='数字人民币提现';
            $log_type =3;
            $change_amount =$user[$field];
            if($change_amount<=0){
                return out(null, 10001, '可提现金额不足');
            }
            // 扣减用户余额
            User::changeInc($user['id'],-$change_amount,$field,2,0,$log_type,$text);
            $field="flow_amount";
            User::changeInc($user['id'],$change_amount,$field,2,0,$log_type,$text);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function applyWithdrawEcny(){
        if(!domainCheck()){
            return out(null, 10001, '请联系客服下载最新app');
        }
        return out(null, 10001, '提现已关闭，请耐心等待');
        $req = $this->validate(request(), [
            'amount|提现金额' => 'require|float',
            'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            'bank_id|银行卡'=>'require|number',
        ]);
        $user = $this->user;

        if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }


        $pay_type = $req['pay_channel'] - 1;
        $payAccount = PayAccount::where('user_id', $user['id'])->where('id',$req['bank_id'])->find();
        if (empty($payAccount)) {
            return out(null, 802, '请先设置此收款方式');
        }
        if(preg_match('/^\d{11}$/', $payAccount['account']) === 1){
            return out(null, 10001, '只支持银行卡');
        }
        if(strpos($payAccount['account'],'@')!==false){
            return out(null, 10001, '只支持银行卡');
        }

        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        if ($req['pay_channel'] == 4 && dbconfig('bank_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启银行卡提现');
        }
        if ($req['pay_channel'] == 3 && dbconfig('alipay_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启支付宝提现');
        }
/*         if ($req['pay_channel'] == 7 && dbconfig('digital_withdrawal_switch') == 0) {
            return out(null, 10001, '连续签到30天才可提现国务院津贴');
        } */

        // 判断单笔限额
        if (999999999 < $req['amount']) {
            return out(null, 10001, '单笔最高提现999999999元');
        }
        if (100000 > $req['amount']) {
            return out(null, 10001, '单笔最低提现100000元');
        }
        // 每天提现时间为8：00-20：00 早上8点到晚上20点
        $timeNum = (int)date('Hi');
        if ($timeNum < 1000 || $timeNum > 1700) {
            return out(null, 10001, '存银行时间为早上10:00到晚上17:00');
        }
       
        $user = User::where('id', $user['id'])->lock(true)->find();
 

        
        Db::startTrans();
        try {

            $field = 'digit_balance';
            $log_type =7;
            if ($user[$field] < $req['amount']) {
                return out(null, 10001, '可提现金额不足');
            }
   
            // 判断每天最大提现次数
            $num = Capital::where('user_id', $user['id'])->where('type', 2)->where('log_type',7)->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
            if ($num >= 1) {
                return out(null, 10001, '每天最多存银行1次');
            }

            $capital_sn = build_order_sn($user['id']);
            $change_amount = 0 - $req['amount'];
            $withdraw_fee = 0;
            $withdraw_amount = round($req['amount'] - $withdraw_fee, 2);

            $payMethod = $req['pay_channel'] == 4 ? 1 : $req['pay_channel'];
            // 保存提现记录
            $capital = Capital::create([
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => $change_amount,
                'withdraw_amount' => $withdraw_amount,
                'withdraw_fee' => $withdraw_fee,
                'realname' => $payAccount['name'],
                'phone' => $payAccount['phone'],
                'collect_qr_img' => $payAccount['qr_img'],
                'account' => $payAccount['account'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_branch'],
                'log_type'=>$log_type,
            ]);
            // 扣减用户余额
            User::changeInc($user['id'],$change_amount,$field,2,$capital['id'],$log_type,'',0,1,'TX');
            //User::changeInc($user['id'],$change_amount,'invite_bonus',2,$capital['id'],1);
            //User::changeBalance($user['id'], $change_amount, 2, $capital['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function houseFee(){
        $user = $this->user;
        $data = User::myHouse($user['id']);
        if($data['msg']!=''){
            return out(null, 10001, $data['msg']);
        }
        $houseFee = HouseFee::where('user_id',$user['id'])->find();
        if($houseFee){
            return out(null, 10001, '已经缴纳过房屋基金');
        }
        $house = $data['house'];
        $feeConf = config('map.project.project_house');
        $size = $feeConf[$house['project_id']];
        $unitPrice = 62.5;
        $fee = bcmul($size,$unitPrice,2);
        $user = User::where('id', $user['id'])->find();
        if($user['balance']<$fee){
            return out(null, 10001, '钱包余额不足'.$fee);
        }
        Db::startTrans();
        try{
            User::changeInc($user['id'],-$fee,'balance',21,0,1,'房屋维修基金');
            HouseFee::create([
                'user_id'=>$user['id'],
                'order_id'=>$house['id'],
                'project_id'=>$house['project_id'],
                'unit_amount'=>$unitPrice,
                'fee_amount'=>$fee,
                'size'=>$size,
            ]);
            Db::commit();
        }catch(Exception $e){
            Db::rollback();
            return out(null, 10001, $e->getMessage(),$e);
            //throw $e;
        }

        return out();
    }

    public function payAccountList()
    {
        $user = $this->user;

        $bank_withdrawal_switch = dbconfig('bank_withdrawal_switch');
        $alipay_withdrawal_switch = dbconfig('alipay_withdrawal_switch');
        $digital_withdrawal_switch = dbconfig('digital_withdrawal_switch');
        $pay_type = [];
        if ($bank_withdrawal_switch == 1) {
            $pay_type[] = 3;
        }
        if ($alipay_withdrawal_switch == 1) {
            $pay_type[] = 2;
        }
        if ($digital_withdrawal_switch == 1) {
            $pay_type[] = 6;
        }
        $data = PayAccount::where('user_id', $user['id'])->whereIn('pay_type', $pay_type)->select()->toArray();
        foreach ($data as $k => &$v) {
            $v['realname'] = $v['name'];
        }

        return out($data);
    }

    public function payAccountDetail()
    {
        $req = $this->validate(request(), [
            'pay_account_id' => 'require|number',
        ]);
        $user = $this->user;

        $data = PayAccount::where('id', $req['pay_account_id'])->where('user_id', $user['id'])->append(['realname'])->find();
        return out($data);
    }

    public function savePayAccount()
    {
        $req = $this->validate(request(), [
            'pay_type' => 'require|number',
            'name' => 'require',
            'account' => 'requireIf:pay_type,3',
            'phone' => 'mobile',
            'qr_img' => 'url',
            'bank_name|银行名称' => 'requireIf:pay_type,3',
            //'bank_branch|银行支行' => 'requireIf:pay_type,3',
        ]);
        $user = $this->user;

        if (empty($user['ic_number']) || empty($user['realname'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        $req['name'] = $user['realname'];
/*         if ($user['realname'] != $req['name']) {
            return out(null, 10001, '只能绑定本人帐户');
        }
 */
        if ($req['pay_type'] == 3 && dbconfig('bank_withdrawal_switch') == 0) {
            return out(null, 10001, '银行卡提现通道暂未开启');
        }
        if ($req['pay_type'] == 2 && dbconfig('alipay_withdrawal_switch') == 0) {
            return out(null, 10001, '支付宝提现通道暂未开启');
        }
        if ($req['pay_type'] == 1 ) {
            return out(null, 10001, '微信提现通道暂未开启');
        }


        if (PayAccount::where('user_id', $user['id'])->where('pay_type', $req['pay_type'])->count()>2) {
            //PayAccount::where('user_id', $user['id'])->where('pay_type', $req['pay_type'])->update($req);
            return out(null, 10001, '银行卡数量超过限制');
        }
/*         if (PayAccount::where('user_id', $user['id'])->where('pay_type', $req['pay_type'])->count()>0) {
            //PayAccount::where('user_id', $user['id'])->where('pay_type', $req['pay_type'])->update($req);
            return out(null, 10001, '请联系客服修改');
        }
        else { */
            $req['user_id'] = $user['id'];
            PayAccount::create($req);
            $logData = [
                'user_id' => $user['id'],
                'url' => 'capital/savePayAccount',
                'req'=>json_encode($req),
                'ip'=>request()->ip(),
                'info'=>request()->header('user-agent'),
            ];
            \app\model\ApiHandleLog::create($logData);

        //}

        return out();
    }

    public function payAccountDel(){
        //return out(null, 10001, '请联系客服修改');

        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $ret = PayAccount::where('id',$req['id'])->delete();
        return out();

    }

    public function capitalRecord()
    {
        $req = $this->validate(request(), [
            'type' => 'number',
            'log_type'=>'number',
        ]);
        $user = $this->user;
        $builder = Capital::where('user_id', $user['id'])->order('id', 'desc');
        if(isset($req['type']) && $req['type'] != ''){
            $builder->where('type', $req['type']);
        }
        if(isset($req['log_type']) && $req['log_type'] != ''){
            $builder->where('log_type', $req['log_type']);
        }
        $data = $builder->append(['audit_date'])->paginate();
        $process = ProcessReview::field('name,number,audit_status')->where('type',1)->order('sort','asc')->select();

        foreach($data as &$item){
           if($item['type']==1){
                $item['stext'] = config('map.capital.topup_status_map')[$item['status']];
           }else{
                if($item['log_type']==7){
                    //$stage = Capital::findCurrentStage($item['created_at'],$process,'2024-02-09 00:00:00','2024-02-17 23:59:59');
                    //if($stage <=3){
                        $item['stext'] = '未通过';
/*                     }else{
                        $item['stext'] = '未通过';
                    } */
                }else{
                    $item['stext'] = config('map.capital.withdraw_status_map')[$item['status']];
                }
           }
        }

        return out($data);
    }
    
    public function capitalEcnyDetail(){
        $req = $this->validate(request(), [
            'id' => 'number',
        ]);

        $withdraw = Capital::where('type',2)->where('log_type',7)->where('id',$req['id'])->find();
        if(!$withdraw){
            return out(null,10010,'没有此记录');
        }
        $process = ProcessReview::field('name,number,audit_status')->where('type',1)->order('sort','asc')->select();
        //$stage = Capital::findCurrentStage($withdraw['created_at'],$process,'2024-02-09 00:00:00','2024-02-17 23:59:59');
        $arr = [];
        foreach($process as $k=>$v){
/*             if($k<$stage){
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
            }
 */         
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
/*         if($stage>=3){
            $process[3]['status'] = '审核中';
            $process[4]['status'] = '待审核';
        } */

        return out($process);
    }
}
