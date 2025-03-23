<?php

namespace app\api\controller;

use app\model\Notice;
use app\model\User;
use think\facade\Db;

class NoticeController extends AuthController
{

    public function noticeList()
    {
        $user = $this->user;
        //$data = Notice::where('user_id', $user['id'])->order('id', 'desc')->paginate(10);
            // 第一部分查询：类型为1的通知
/*         $data = Db::table('mp_notice')->where('type', 1)->alias('n')->field('n*')
                        ->unionAll('select n.* from mp_notice n inner join mp_user_notice u on n.id = u.notice_id  where type = 2  and u.user_id = '.$user['id'])
                        ->paginate(10);
 */
        $sql = "select * from mp_notice n  where type = 1  
                UNION ALL
                select n.* from mp_notice n inner join mp_user_notice u on n.id = u.notice_id  where type = 2  and u.user_id ={$user['id']} order by id desc";
        $data = Db::query($sql);
        foreach($data as &$v) {
            //把换行符替换成<br>
            $v['content'] = htmlspecialchars ($v['content']);

            $v['content'] = nl2br($v['content']);  // 将换行符转换为<br>标签
            $v['content'] = str_replace(' ', '&nbsp;', $v['content']);  // 将空格转换为&nbsp;
        }
        return out($data);
    }

    public function readCount(){
        $user = $this->user;
        $sql1 = "select count(*) ct from mp_notice n  where n.type = 1 and not EXISTS (select id from mp_notice_read r where r.notice_id = n.id and r.user_id = {$user['id']})";
        $broadcastRead = Db::query($sql1);

        $sql2 = "select count(*) ct from mp_user_notice u where u.user_id = {$user['id']} and not EXISTS (select id from mp_notice_read r where r.notice_id = u.notice_id and r.user_id = u.user_id )";
        $userRead = Db::query($sql2);
        $count = $broadcastRead[0]['ct'] + $userRead[0]['ct'];

        return out(['un_read'=>$count]);
    }

    public function indexNotice()
    {
        $user = $this->user;
        $data = Notice::where('user_id', $user['id'])->where('is_read', 0)->order('id', 'asc')->find();
        if(!$data) {
            return out(null, 20001);
        }
        return out($data);
    }

    public function noticeRead()
    {
        $req = $this->validate(request(), [
            'id' => 'require',
        ]);
        //Notice::where('id', $req['id'])->update(['is_read' => 1]);
        $read = Db::table('mp_notice_read')->where('notice_id', $req['id'])->where('user_id', $this->user['id'])->find();
        if(!$read){
            Db::table('mp_notice_read')->insert(['notice_id' => $req['id'], 'user_id' => $this->user['id']]);
        }
        return out();
    }

}
