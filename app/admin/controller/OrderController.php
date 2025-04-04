<?php

namespace app\admin\controller;

use app\model\AssetOrder;
use app\model\Order;
use app\model\OrderLog;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\User;
use Exception;
use think\facade\Db;
use think\facade\Session;

use function PHPUnit\Framework\isNull;

class OrderController extends AuthController
{
    public function orderList()
    {
        
        $req = request()->param();

        if (!empty($req['channel'])||!empty($req['mark'])) {
            $builder = Order::alias('o')->leftJoin('payment p', 'p.order_id = o.id')->field('o.*')->order('o.id', 'desc');
        }else{
            $builder = Order::alias('o')->field('o.*')->order('o.id', 'desc');
        }
        // echo $builder->buildSql();
        // die;
        if (isset($req['order_id']) && $req['order_id'] !== '') {
            $builder->where('o.id', $req['order_id']);
        }
        if (isset($req['up_user_id']) && $req['up_user_id'] !== '') {
            $builder->where('o.up_user_id', $req['up_user_id']);
        }
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('o.user_id', $user_ids);
        }
        if (isset($req['order_sn']) && $req['order_sn'] !== '') {
            $builder->where('o.order_sn', $req['order_sn']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('o.status', $req['status']);
        }
        if (isset($req['project_id']) && $req['project_id'] !== '') {
            $builder->where('o.project_id', $req['project_id']);
        }
        if (isset($req['project_name']) && $req['project_name'] !== '') {
            $builder->whereLike('o.project_name', '%'.$req['project_name'].'%');
        }
        if (isset($req['pay_method']) && $req['pay_method'] !== '') {
            $builder->where('o.pay_method', $req['pay_method']);
        }
        if (isset($req['pay_time']) && $req['pay_time'] !== '') {
            $builder->where('o.pay_time', $req['pay_time']);
        }
        if (!empty($req['channel'])) {
            $builder->where('p.channel', $req['channel']);
        }
        if (!empty($req['mark'])) {
            $builder->where('p.mark', $req['mark']);
        }

        if (!empty($req['start_date'])) {
            $builder->where('o.created_at', '>=', $req['start_date']);
        }else{
            $builder->where('o.created_at', '>=', date('Y-m-d'));
        }
        if (!empty($req['end_date'])) {
            $builder->where('o.created_at', '<=', $req['end_date']);
        }

        if(!empty($req['project_id'])){
            $builder->where('o.project_id', $req['project_id']);
        }

        $builder1 = clone $builder;
        $builder2 = clone $builder;
        if (!empty($req['export'])) {
            $list = $builder2->select();
            foreach ($list as $v) {
                $v->phone = $v['user']['phone'] ?? '';
                $v->realname=$v['user']['realname'] ?? '';
            }
                create_excel($list, [
                    'id' => '序号',
                    'project_name' => '项目名称',
                    'phone' => '用户',
                    'realname'=>'姓名',  
                    'single_amount' => '金额',
                    'status_text' => '状态',
                    'period' => '周期',
                    'daily_bonus' => '日分红',
                    'sum_amount'=> '收益',
                    'created_at' => '创建时间'
                ], '订单记录-' . date('YmdHis'));
         } 
        


        $total_buy_amount = round($builder1->sum('o.buy_num*o.single_amount'), 2);
        $this->assign('total_buy_amount', $total_buy_amount);


        $projectList = \app\model\Project::field('id,name')->select();
        
        if (isset($req['num']) && $req['num'] !== '') {
            if($req['num']<0){
                $req['num']=$builder->count();
            }
        }else{
            $req['num']=15;
        }
        
        $data = $builder->paginate($req['num'],false,['query' => $req]);
        // echo $builder->buildSql();
        // var_dump($req);
        // die;
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('projectList', $projectList);

        return $this->fetch();
    }

    public function auditOrder()
    {
        $req = request()->post();
        $this->validate($req, [
            'id' => 'require|number',
            'status' => 'require|in:2',
        ]);

        $order = Order::where('id', $req['id'])->find();
        if ($order['status'] != 1) {
            return out(null, 10001, '该记录状态异常');
        }
        if (!in_array($order['pay_method'], [2,3,4,6])) {
            return out(null, 10002, '审核记录异常');
        }

        Db::startTrans();
        try {
            Payment::where('order_id', $order['id'])->update(['payment_time' => time(), 'status' => 2]);

            Order::where('id', $order['id'])->update(['is_admin_confirm' => 1]);
            Order::orderPayComplete($order['id']);
            // 判断通道是否超过最大限额，超过了就关闭通道
            $payment = Payment::where('order_id', $order['id'])->find();
            $userModel = new User();
            $userModel->teamBonus($order['user_id'],$payment['pay_amount'],$payment['id']);

            PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function addTime(){
        

            if(request()->isPost()){
                $req = request()->post();
                $this->validate($req, [
                    'project_id' => 'require|number',
                    'day_num' => 'require|number',
                ]);
                $updateData=[
                    'end_time'=>Db::raw('end_time+'.$req['day_num']*24*3600),
                    'period'=>Db::raw('period+'.$req['day_num']),
                    'period_change_day'=>$req['day_num'],
                ];

                $num = Order::where('project_id',$req['project_id'])->where('status',2)->update($updateData);

                return out(['msg'=>$num."个订单已增加".$req['day_num']."天"]);
            }else{
                $projectList = \app\model\Project::field('id,name')->where('status',1)->select();
                $this->assign('projectList', $projectList);
                return $this->fetch();
            }
    }

    public function assetOrderList()
    {
        $req = request()->param();

        $builder = AssetOrder::order('id', 'desc');
        if (isset($req['order_id']) && $req['order_id'] !== '') {
            $builder->where('id', $req['order_id']);
        }
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('user_id', $user_ids);
        }
        if (isset($req['order_sn']) && $req['order_sn'] !== '') {
            $builder->where('order_sn', $req['order_sn']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }

        if (isset($req['reward_status']) && $req['reward_status'] !== '') {
            $builder->where('reward_status', $req['reward_status']);
        }

        $data = $builder->paginate(['query' => $req]);
        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function batchCancelOrder(){
        $req = request()->param();
        $this->validate($req, [
            'ids' => 'require',
        ]);
        $rets = [];
        $ids = explode(',', $req['ids']);
        foreach($ids as $id){
           $ret = Order::cancelOrder($id);
           //if($ret['code'] != 0){
               $rets[] = $ret;
           //}
        }
        return out($rets);
    }
}
