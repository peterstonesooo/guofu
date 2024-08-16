<?php

namespace app\api\controller;

use app\model\Apply;
use app\model\User;
use app\model\UserDelivery;
use app\model\UserRelation;
use think\facade\Db;

class ApplyController extends AuthController
{
    public function applyHonorList(){
        $user = $this->user;
        $order = \app\model\Order::where('user_id', $user['id'])->where('status','>=',2)->where('project_group_id',7)->field('project_id,project_name,count(project_id) ct')->group('project_id,project_name')->select();
        foreach($order as $k=>$v){
            $apply = Apply::where('user_id', $user['id'])->where('type', 0)->where('project_id',$v['project_id'])->find();
            if($apply){
                $order[$k]['is_apply'] = 1;
            }else{
                $order[$k]['is_apply'] = 0;
            }
        }
        return out($order);
    }   


    public function applyHonor(){
        $user = $this->user;
        $req = $this->validate(request(),[
            'project_id'=>'require',
        ]);
        $order = \app\model\Order::where('user_id', $user['id'])->where('status', 2)->where('project_group_id',7)->where('project_id',$req['project_id'])->find();
        if(!$order){
            return out(null,'请先购买项目', 10001);
        }
        $apply =Apply::where('user_id', $user['id'])->where('type', 0)->where('project_id',$req['project_id'])->find();
        if($apply){
           // $address = UserDelivery::where('user_id', $user['id'])->field('phone,address')->find();
            return out(['project_name'=>$order['project_name']], 10001, '已成功预约');
        }

        //UserDelivery::updateAddress2($user,$req);
        $data = [
            'user_id' => $user['id'],
            'type' => 0,
            'ext'=>json_encode($req),
            'project_id'=>$order['project_id'],
            'project_name'=>$order['project_name'],// '强国建设项目
            'create_time' => time(),
        ];
        Apply::create($data);
        return out(['project_name'=>$order['project_name']], 200, '预约成功');
    }

    public function applyHouse(){
        $req=$this->validate(request(),[
            'city' => 'require',
        ]);
        $user = $this->user;
        $projectIdArr = \app\model\Project::where('project_group_id', 7)->field('id')->column('id');
        $orderArr = \app\model\Order::where('user_id', $user['id'])->where('status', 2)->where('project_group_id',7)->whereIn('project_id',$projectIdArr)->field('project_id')->group('project_id')->select();
        if(count($orderArr)<count($projectIdArr)){
            return out(null,'请先购买6个不同的强国建设项目', 10001);
        }
        $apply=Apply::where('user_id', $user['id'])->where('type', 1)->find();
        if($apply){
            return out(null, 10001, '已成功预约');
        }
        $data = [
            'user_id' => $user['id'],
            'type' => 1,
            'ext'=>json_encode($req),
            'create_time' => time(),
        ];
        Apply::create($data);
        return out(null, 200, '预约成功');
    }
}
