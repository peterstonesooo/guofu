<?php

namespace app\admin\controller;

use app\model\Capital;
use app\model\EquityYuanRecord;
use app\model\LevelConfig;
use app\model\Realname;
use app\model\User;
use app\model\UserLottery;
use app\model\UserRelation;
use GuzzleHttp\Psr7\Message;
use think\facade\Db;
class UserController extends AuthController
{
    public function userList()
    {
        $req = request()->param();

        $builder = User::order('id', 'desc');
        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('id', $req['user_id']);
        }
        if (isset($req['up_user']) && $req['up_user'] !== '') {
            $user_ids = User::where('phone', $req['up_user'])->column('id');
            $user_ids[] = $req['up_user'];
            $builder->whereIn('up_user_id', $user_ids);
        }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('phone', $req['phone']);
        }
        if (isset($req['ic_number']) && $req['ic_number'] !== '') {
            $builder->where('ic_number', $req['ic_number']);
        }
        if (isset($req['invite_code']) && $req['invite_code'] !== '') {
            $builder->where('invite_code', $req['invite_code']);
        }
        if (isset($req['realname']) && $req['realname'] !== '') {
            $builder->where('realname', $req['realname']);
        }
        if (isset($req['level']) && $req['level'] !== '') {
            $builder->where('level', $req['level']);
        }
        if (isset($req['is_active']) && $req['is_active'] !== '') {
            if ($req['is_active'] == 0) {
                $builder->where('is_active', 0);
            }
            else {
                $builder->where('is_active', 1);
            }
        }
        if (isset($req['is_realname']) && $req['is_realname'] !== '') {
            if ($req['is_realname'] == 0) {
                $builder->where('is_realname', 0);
            }
            else {
                $builder->where('is_realname', 1);
            }
        }
        $builder1 = clone $builder;
        $data = $builder->paginate(['query' => $req]);
        if(session('admin_user')['auth_group_id'] == 3){
            foreach($data as &$item){
                $item['invest_amount']=0;
            }
        }
        

        if (!empty($req['export'])) {
            $list = $builder1->select();
            create_excel($list, [
                'id' => '序号',
                // 'account_type' => '用户',
                // 'capital_sn' => '单号',
                // 'withdraw_status_text' => '状态',
                // 'pay_channel_text' => '支付渠道',
                'phone' => '电话',
                'real_sub_user_num' => '实名下级人数',

            ], '用户-' . date('YmdHis'));
        }

        foreach($data as &$item){
            $lotteryNum = UserLottery::where('user_id', $item['id'])->find();
            if($lotteryNum){
                $item['lottery_num'] = $lotteryNum['lottery_num'];
            }else{
                $item['lottery_num'] = 0;
            }
        }

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showUser()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = User::where('id', $req['id'])->find();
        }
        $levelConf = LevelConfig::select();
        $this->assign('data', $data);
        $this->assign('levelConf', $levelConf);

        return $this->fetch();
    }

    public function editUser()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'password|登录密码' => 'max:50',
            'pay_password|支付密码' => 'max:50',
            //'realname|实名认证姓名' => 'max:50',
            //'ic_number|身份证号' => 'max:50',
            'level|用户等级' => 'number',
        ]);

        if (empty($req['password'])) {
            unset($req['password']);
        }
        else {
            $req['password'] = sha1(md5($req['password']));
        }

        if (empty($req['pay_password'])) {
            unset($req['pay_password']);
        }
        else {
            $req['pay_password'] = sha1(md5($req['pay_password']));
        }
/*         if (empty($req['realname']) && !empty($req['ic_number'])) {
            return out(null, 10001, '实名和身份证号必须同时为空或同时不为空');
        }
        if (!empty($req['realname']) && empty($req['ic_number'])) {
            return out(null, 10001, '实名和身份证号必须同时为空或同时不为空');
        } */

        // 判断给直属上级额外奖励
/*         if (!empty($req['realname']) && !empty($req['ic_number'])) {
            if (User::where('ic_number', $req['ic_number'])->where('id', '<>', $req['id'])->count()) {
                return out(null, 10001, '该身份证号已经实名过了');
            }
             */
/*             $user = User::where('id', $req['id'])->find();
            if (!empty($user['up_user_id']) && empty($user['ic_number'])) {
                User::changeBalance($user['up_user_id'], dbconfig('direct_recommend_reward_amount'), 7, $user['id']);
            } */
            //$req['is_realname']=1;
        //}

        
        User::where('id', $req['id'])->update($req);

        // 把注册赠送的股权给用户
        //EquityYuanRecord::where('user_id', $req['id'])->where('type', 1)->where('status', 1)->where('relation_type', 2)->update(['status' => 2, 'give_time' => time()]);

        return out();
    }

    public function changeUser()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        User::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function editPhone(){
        if(request()->isPost()){
            $req = $this->validate(request(), [
                'user_id'=>'require',
                'phone|手机号' => 'require|mobile',
                'is_clear|清空实名信息'=>'number',
            ]);
            if(!isset($req['is_clear'])){
                $req['is_clear'] = 0;
            }
            $user = User::where('id',$req['user_id'])->find();
            $update = [];
            //如果传入手机号和原手机号不一样，判断新手机号是否已经存在
            if($user['phone']!=$req['phone']){
                $new = User::where('phone',$req['phone'])->find();
                if($new){
                    return out(null,10001,'已有的手机号');
                }
                $update = ['phone'=>$req['phone'],'prev_phone'=>$user['phone']];

            }

            if($req['is_clear']==1){
                $update['ic_number']='';
                $update['realname']='';
                $update['is_realname']=0;
                $update['update_realname']=1;

                Realname::where('user_id',$req['user_id'])->delete();
            }
            if(count($update)>0){
                $ret = User::where('id',$req['user_id'])->update($update);
            }

            return out();
        }else{
            $req = $this->validate(request(), [
                'user_id'=>'require',
            ]);
            $user = User::where('id',$req['user_id'])->find();
            $this->assign('data', $user);

            return $this->fetch();
        }
    }

    public function showChangeBalance()
    {
        $req = request()->get();
        $this->validate($req, [
            'user_id' => 'require|number',
            'type' => 'require|in:15,16',
        ]);

        $this->assign('req', $req);

        return $this->fetch();
    }

    public function batchShowBalance()
    {
        $req = request()->get();

        return $this->fetch();
    }

    public function addBalance()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'money' => 'require|float',
            'type'=>'require|number',
            'remark' => 'max:50',
        ]);
        $adminUser = $this->adminUser;
        $filed = 'topup_balance';
        $log_type = 0;
        $balance_type = 1;
        $text = '现金';
        switch($req['type']){
            case 1:
                $filed = 'topup_balance';
                $log_type = 1;
                $balance_type = 15;
                break;
            case 2:
                $filed = 'integral';
                $log_type = 2;
                $balance_type = 15;
                $text = '积分';
                break;

             case 4:
                $filed = 'team_bonus_balance';
                $log_type = 3;
                $balance_type = 8;
                $text = '团队奖励';
                break;
            case 5:
                Db::startTrans();
                try{
                    $capital_sn = build_order_sn($req['user_id']);
                    // 创建充值单
                    $capital = Capital::create([
                        'user_id' => $req['user_id'],
                        'capital_sn' => $capital_sn,
                        'type' => 1,
                        'pay_channel' => 200,
                        'amount' => $req['money'],
                        'admin_user_id' => $adminUser['id'],
                        //'realname'=>$req['uname']??'',
        
                    ]);
        
                    if (empty($card_info)) {
                        $card_info = '';
                    }
                    // 创建支付记录
                    \app\model\Payment::create([
                        'user_id' => $req['user_id'],
                        'trade_sn' => $capital_sn,
                        'pay_amount' => $req['money'],
                        'product_type' => 2,
                        'capital_id' => $capital['id'],
                        'payment_config_id' =>200,
                        'channel' => 200,
                        'mark' => '后台手动',
                        'type' => 200,
                        'pay_voucher_img_url' =>  '',
                    ]);
                    Db::commit();
                }catch(\Exception $e){
                    Db::rollback();
                    return out(null, 10001, $e->getMessage());
                }
                return out(null,200,'请去充值列表手动确认');
                break;
            case 6:
                $filed = 'lottery_num';
                $log_type = 3;
                $text = '抽奖次数';
                break;
            case 7:
                $filed = 'income_balance';
                $log_type = 4;
                $balance_type = 15;
                $text = '民生养老金';
                break;
            case 8:
                $filed = 'large_subsidy';
                $log_type = 7;
                $balance_type = 15;
                $text = '民生补助金';
                break;
            case 9:
                $filed = 'insurance_balance';
                $log_type = 5;
                $balance_type = 15;
                $text = '基本保险';
                break;
            case 10:
                $filed = 'fupin_balance';
                $log_type = 10;
                $balance_type = 15;
                $text = '扶贫补助金';
                break;
            default:
                return out(null, 10001, '类型错误');
        }
        if($req['money'] >0 ){
            $r_text = '财务部入金'.$text;
        } else {
            $r_text = '财务部扣款'.$text;
        }
        //User::changeBalance($req['user_id'], $req['money'], 15, 0, 1, $req['remark']??'', $adminUser['id']);
        $text = $req['remark']==''?$r_text:$req['remark'];
        if($req['type']==6){
                    
            //User::changelottery($req['user_id'],$req['money'],1,$adminUser['id']);
            UserLottery::lotteryInc($req['user_id'],$req['money'],4,0,0,1,'lottery_num',$adminUser['id']);
        }else{
            User::changeInc($req['user_id'],$req['money'],$filed,$balance_type,0,$log_type,$text,$adminUser['id']);
        }

        return out();
    }

    public function batchBalance()
    {
        if(request()->param('input_type')=='file') {

        
            // 使用 upload_file3 处理 Excel 文件上传
            $file_url = upload_file3('file', true, false, 'excel', 'xlsx,xls');

            if(empty($file_url)) {
                return out(null, 201, '请上传文件');
            }
            //取上传文件名
            $file = request()->file()['file'];
            $fileName = $file->getOriginalName().'_'.date('ymdHis');
            // 创建批次记录
            $batchId = Db::name('batch_recharge')->insertGetId([
                'name' => $fileName,
                'url' => $file_url,
                'status' => 0,
                'rows' => 0,
                'success_rows' => 0,
                'fail_rows' => 0
            ]);
            
            return out(['batch_id' => $batchId]);

        }

        // 原有的手动输入处理逻辑保持不变...
        $req = request()->post();
        $this->validate($req, [
            'users' => 'require',
            'money' => 'require|float',
            'type'=>'require|number',
            'remark' => 'max:50',
        ]);
        $phoneList = explode(PHP_EOL, $req['users']);
        if(count($phoneList)<=0){
            return out(null, 10001, '用户不能为空');
        }
        $adminUser = $this->adminUser;
        $filed = 'balance';
        $log_type = 0;
        $balance_type = 1;
        $text = '余额';
        switch($req['type']){
            case 1:
                $filed = 'topup_balance';
                $log_type = 1;
                $balance_type = 15;
                break;
            case 2:
                $filed = 'integral';
                $log_type = 2;
                $balance_type = 15;
                $text = '积分';
                break;

            case 4:
                $filed = 'team_bonus_balance';
                $log_type = 2;
                $balance_type = 8;
                $text = '团队奖励';
                break;
            case 6:
                $filed = 'lottery_num';
                $log_type = 3;
                $text = '抽奖次数';
                break;
            case 7:
                $filed = 'income_balance';
                $log_type = 4;
                $balance_type = 15;
                $text = '民生养老金';
                break;
            case 8:
                $filed = 'large_subsidy';
                $log_type = 7;
                $balance_type = 15;
                $text = '民生补助金';
                break;
            default:
                return out(null, 10001, '类型错误');
        }
        //User::changeBalance($req['user_id'], $req['money'], 15, 0, 1, $req['remark']??'', $adminUser['id']);
        if($req['money'] >0 ){
            $r_text = '财务部入金'.$text;
        } else {
            $r_text = '财务部扣款'.$text;
        }
        $text = isset($req['remark']) || $req['remark']==''?$r_text:$req['remark'];
        if(isset($req['remark']) && $req['remark']==''){
            $text = $r_text;
        }else{
            $text = $req['remark'];
        }
        foreach($phoneList as $key=>$phone){
            $phoneList[$key] = trim($phone);
        }
        $ids = User::whereIn('phone',$phoneList)->column('id');
       
        Db::startTrans();
        try{
            foreach($ids as $id){
                if($req['type']==6){
                    
                    //User::changelottery($id,$req['money'],1);
                    UserLottery::lotteryInc($id,$req['money'],4,0,0,1,'lottery_num',$adminUser['id']);

                }else{
                    User::changeInc($id,$req['money'],$filed,$balance_type,0,$log_type,$text,$adminUser['id']);
                }
            }
        }catch(\Exception $e){
            Db::rollback();
            return out(null, 10001, $e->getMessage());
        }
        Db::commit();
        return out();
    }

    // 批次列表
    public function getBatchList()
    {
        $list = Db::name('batch_recharge')
                ->order('id desc')
                ->limit(20)
                ->select()
                ->toArray();
        return out(['list' => $list]);
    }

    // 批次详情
    public function getBatchDetail($id)
    {
        $logs = Db::name('batch_log')
                ->where('batch_id', $id)
                ->where('status', 2)
                ->select();
        $is_logs = false;
        if($logs && count($logs) > 0) {
            $is_logs = true;
        }        
        return out(['logs' => $logs,'show_export' => $is_logs,'batch_id' => $id]);
    }

    public function deductBalance()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'money' => 'require|float',
            'remark' => 'max:50',
        ]);
        $adminUser = $this->adminUser;

        $user = User::where('id', $req['user_id'])->find();
        if ($user['balance'] < $req['money']) {
            return out(null, 10001, '用户余额不足');
        }

        if (Capital::where('user_id', $user['id'])->where('type', 2)->where('pay_channel', 1)->where('status', 1)->count()) {
            return out(null, 10001, '该用户有待审核的手动出金，请先去完成审核');
        }

        // 保存到资金记录表
        Capital::create([
            'user_id' => $user['id'],
            'capital_sn' => build_order_sn($user['id']),
            'type' => 2,
            'pay_channel' => 1,
            'amount' => 0 - $req['money'],
            'withdraw_amount' => $req['money'],
            'audit_remark' => $req['remark'] ?? '',
            'admin_user_id' => $adminUser['id'],
        ]);

        return out();
    }

    public function userTeamList()
    {
        $req = request()->get();
        if(!isset($req['user_id']) || $req['user_id']==''){
            return out(null, 10001, '参数错误');
        }
        if(strlen(trim($req['user_id']))==11){
            $user = User::where('phone',$req['user_id'])->find();
            $req['user_id'] = $user['id'];
        }else{
            $user = User::where('id', $req['user_id'])->find();
        }
        $path = Db::table('mp_user_path')->where('user_id', $req['user_id'])->find();
        $regCount = 0;
        $regRealCount = 0;
        if((isset($req['start_date']) && $req['start_date']!='') || (isset($req['end_date']) && $req['end_date']!='')){
            $query = User::alias('u')
                ->join('mp_user_path p', 'u.id = p.user_id')
                ->where('p.path', 'like', $path['path'] .'/'.$user['id']. '%');

            $queryReal = clone $query;
            $queryReal->join('mp_realname r','u.id = r.user_id')->where('u.is_realname', 1);

            if(isset($req['start_date']) && $req['start_date']!='') {
                $query->where('u.created_at','>=',$req['start_date'].' 00:00:00');  
                $queryReal->where('r.audit_time','>=',$req['start_date'].' 00:00:00');
            }

            if(isset($req['end_date']) && $req['end_date']!='') {
                $query->where('u.created_at','<=',$req['end_date'].' 23:59:59');
                $queryReal->where('r.audit_time','<=',$req['end_date'].' 23:59:59');
            }
            $regCount = $query->count();
            $regRealCount = $queryReal->count();
        }else{
            $regCount = $path['team_count'];
            $regRealCount = $path['team_real_count'];
        }


        $data = ['user_id' => $user['id'], 'phone' => $user['phone']];
        $data['reg_count'] = $regCount;
        $data['reg_real_count'] = $regRealCount;

        $data['total_num'] = UserRelation::where('user_id', $req['user_id'])->count();
        $data['active_num'] = UserRelation::where('user_id', $req['user_id'])->where('is_active', 1)->count();

        $data['total_num1'] = UserRelation::where('user_id', $req['user_id'])->where('level', 1)->count();
        $data['active_num1'] = UserRelation::where('user_id', $req['user_id'])->where('level', 1)->where('is_active', 1)->count();

        $data['total_num2'] = UserRelation::where('user_id', $req['user_id'])->where('level', 2)->count();
        $data['active_num2'] = UserRelation::where('user_id', $req['user_id'])->where('level', 2)->where('is_active', 1)->count();

        $data['total_num3'] = UserRelation::where('user_id', $req['user_id'])->where('level', 3)->count();
        $data['active_num3'] = UserRelation::where('user_id', $req['user_id'])->where('level', 3)->where('is_active', 1)->count();

        $subUsersIds = UserRelation::where('user_id', $req['user_id'])->column('sub_user_id');
        $data['total_invest'] = User::whereIn('id', $subUsersIds)->sum('invest_amount');
        
        if (!empty($req['export'])) {
            $list = UserRelation::alias('r')->join('mp_user u','r.sub_user_id = u.id')->field('u.id,u.phone,u.realname,r.level,r.is_active')->where('r.user_id', $req['user_id'])->select();
            create_excel($list, [
                'id' => '序号',
                // 'account_type' => '用户',
                // 'capital_sn' => '单号',
                // 'withdraw_status_text' => '状态',
                // 'pay_channel_text' => '支付渠道',
                'phone' => '电话',
                'realname' => '姓名',
                'level' => '第几级',
                'is_active' => '激活',
            ], '用户-' . date('YmdHis'));
        }

       
        $this->assign('data', $data);
        $this->assign('req', $req);

        return $this->fetch();
    }
    public function KKK(){
        $a = User::field('id,up_user_id,is_active')->limit(0,150000)->select()->toArray();
        // $a = User::field('id,up_user_id,is_active')->limit(150000,150000)->select()->toArray();
        // $a = User::field('id,up_user_id,is_active')->limit(300000,150000)->select()->toArray();
        // echo '<pre>';print_r($a);die;
        $re = $this->tree($a,4);
        echo count($re);
    }

    public function tree($data,$pid){
        static $arr = [];
        foreach($data as $k=>$v){
          if($v['up_user_id']==$pid && $v['is_active'] == 1){
            $arr[] = $v;
            unset($data[$k]);
            $this->tree($data,$v['id']);
          }
        }
        return $arr;
  }

    public function getSubUsers()
    {
        $userId = input('user_id', 0, 'intval');
        if (!$userId) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $subUsers = Db::table('mp_user_relation')
            ->alias('r')
            ->join('mp_user u', 'r.sub_user_id = u.id')
            ->where('r.user_id', $userId)
            ->field('u.id, u.phone, u.realname')
            ->select();

        return json(['code' => 0, 'data' => $subUsers]);
    }

    public function userTree()
    {
        $userId = input('user_id', 0, 'intval');
        if (!$userId) {
            $userId = 2105152;
        }

        // 获取根用户信息
        $user = User::where('id', $userId)->find();
        if (!$user) {
            return out(null, 10001, '用户不存在');
        }

        $this->assign('user_id', $userId);
        $this->assign('root_user', $user);
        
        return $this->fetch();
    }

    // 在UserController.php中添加导出方法
    public function exportErrorLog() 
    {
        $batchId = input('batch_id', 0, 'intval');
        $batch = Db::name('batch_recharge')->where('id', $batchId)->find();
        // 获取批次的错误日志
        $logs = Db::name('batch_log')
                ->where('batch_id', $batchId)
                ->where('status', 2) 
                ->select()
                ->toArray();

        if(empty($logs)) {
            return out(null, 10001, '没有错误日志数据');
        }
        $list=[];
        foreach($logs as $log) {
            $data = json_decode($log['data'], true);
            $info = json_decode($log['log'], true);
            $log1['phone'] = $data[0] ?? '';
            $log1['type'] = $data[1] ?? '';
            $log1['amount'] = $data[2] ?? '';
            $log1['remark'] = $data[3] ?? '';
            $log1['error_msg'] = $info['message'] ?? '';
            $list[] = $log1;
        }

        create_excel($list, [
            'phone' => '电话',
            'type' => '类别',
            'amount'=>'金额',  
            'remark' => '备注',
            'error_msg' => '错误',

        ],$batch['name'].'_错误日志'.date('YmdHis'));
    }


}
