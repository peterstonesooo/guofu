<?php

namespace app\model;

use think\Model;
use think\facade\Db;
use \Exception;

class Realname extends Model
{

    public static function audit($id,$status, $admin_user_id, $audit_remark = ''){
        $realname = Realname::where('id',$id)->find();
        if (in_array($realname['status'], [1,2])) {
            exit_out(null, 10001, '该记录已经审核了');
        }

        $user = User::where('id',$realname['user_id'])->field('id,update_realname,up_user_id')->find();
        Db::startTrans();

        try {
            Realname::where('id',$id)->update(['status'=>$status,'audit_admin_id'=>$admin_user_id,'audit_time'=>Date('Y-m-d H:i:s'),'mark'=>$audit_remark]);
            if($status == 1 && $user['update_realname'] == 0){
                //$user = User::where('id',$realname['user_id'])->find();
                User::where('id',$realname['user_id'])->update(['is_realname'=>1,'realname'=>$realname['realname'],'ic_number'=>$realname['ic_number']]);
                //UserLottery::lotteryInc($user['up_user_id'],1,2,0,$realname['id']);
            }
            if($status == 1 && $user['update_realname'] == 1){
                User::where('id',$realname['user_id'])->update(['update_realname'=>0,'is_realname'=>1,'realname'=>$realname['realname'],'ic_number'=>$realname['ic_number']]);
            }
            Db::commit();
            if($status == 1 && $user['update_realname'] == 0){
                $userPathModel = new UserPath();
                $parentPath = $userPathModel->where('user_id',$realname['user_id'])->value('path');
                $userPathModel->updateCount($parentPath,'team_real_count');
            }            

        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}
