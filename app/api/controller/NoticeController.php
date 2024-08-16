<?php

namespace app\api\controller;

use app\model\Notice;
use app\model\User;

class NoticeController extends AuthController
{

    public function noticeList()
    {
        $user = $this->user;
        $data = Notice::where('user_id', $user['id'])->order('is_read', 'asc')->order('id', 'desc')->paginate(10);
        return out($data);
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
        Notice::where('id', $req['id'])->update(['is_read' => 1]);
        return out();
    }

}
