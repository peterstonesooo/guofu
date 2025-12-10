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
            // 如果审核通过，需要检查身份证号是否已被其他用户使用
            if($status == 1) {
                // 检查是否有其他已审核通过的实名认证使用了相同的身份证号
                $existingRealname = Realname::where('ic_number', $realname['ic_number'])
                    ->where('status', 1)
                    ->where('user_id', '<>', $realname['user_id'])
                    ->find();
                
                if($existingRealname) {
                    exit_out(null, 10002, '该身份证号已被其他用户实名认证，不能重复使用');
                }
                
                // 检查用户表中是否有其他用户已使用该身份证号
                $existingUser = User::where('ic_number', $realname['ic_number'])
                    ->where('id', '<>', $realname['user_id'])
                    ->find();
                
                if($existingUser) {
                    exit_out(null, 10002, '该身份证号已被其他用户实名认证，不能重复使用');
                }
            }
            
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
