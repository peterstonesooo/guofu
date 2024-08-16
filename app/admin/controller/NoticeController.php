<?php

namespace app\admin\controller;

use app\model\Notice;
use app\model\User;
use Exception;
use think\facade\Db;

class NoticeController extends AuthController
{
    public function noticeList()
    {
        $req = request()->param();

        $builder = Notice::order('id', 'desc');
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('user_id', $user_ids);
        }
        if (isset($req['start_date']) && $req['start_date'] !== ''){
            $builder->where('created_at', '>', $req['start_date']);
        }
        if (isset($req['end_date']) && $req['end_date'] !== ''){
            $builder->where('created_at', '<', $req['end_date']);
        }

        if (isset($req['is_read']) && $req['is_read'] !== '') {
            $builder->where('is_read', $req['is_read']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('data', $data);

        $this->assign('req', $req);

        return $this->fetch();
    }

    public function showNotice()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = Notice::where('id', $req['id'])->find();
            $user = User::where('id', $data['user_id'])->find();
            $data['phone'] = $user['phone'];
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addNotice()
    {
        $req = $this->validate(request(), [
            'phone|手机号' => 'require',
            'title|标题' => 'require|max:100',
            'content|内容' => 'require',
        ]);
        Db::startTrans();
        try {
            $phone = explode(',', $req['phone']);
            foreach ($phone as $key => $value) {
                $user = User::where('phone', $value)->find();
                if(!$user) {
                    throw new Exception($value.'手机号不存在');
                }
                $insert = [
                    'user_id' => $user['id'],
                    'title' => $req['title'],
                    'content' => $req['content'],
                ];
                Notice::create($insert);
            }
            
            Db::Commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function editNotice()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'phone|手机号' => 'require',
            'title|标题' => 'require|max:100',
            'content|内容' => 'require',
        ]);
        $user = User::where('phone', $req['phone'])->find();
        if(!$user) {
            return out(null, 0, '手机号不存在或者输入多个手机号');
        }
        Notice::where('id', $req['id'])->update($req);

        return out();
    }


    public function delNotice()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        Notice::destroy($req['id']);

        return out();
    }
}
