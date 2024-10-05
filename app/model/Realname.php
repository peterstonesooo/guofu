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
        Db::startTrans();

        try {
            Realname::where('id',$id)->update(['status'=>$status,'audit_admin_id'=>$admin_user_id,'audit_time'=>Date('Y-m-d H:i:s'),'mark'=>$audit_remark]);
            if($status == 1){
                $user = User::where('id',$realname['user_id'])->find();
                User::where('id',$realname['user_id'])->update(['is_realname'=>1,'realname'=>$realname['realname'],'ic_number'=>$realname['ic_number']]);
                User::changeInc($user['up_user_id'], 5,'integral',24,$user['id'],2,'直推实名赠送积分',0,4,'ZS');            }   
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}
