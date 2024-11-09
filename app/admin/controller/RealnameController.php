<?php

namespace app\admin\controller;

use app\model\AdminUser;
use app\model\Realname;
use app\model\User;
use think\facade\Db;
use \Exception;

class RealnameController extends AuthController
{
    public function realnameList()
    {
        $req = request()->param();
        $builder = Db::table('mp_realname')->order('created_at','desc');

        if (isset($req['realname']) && $req['realname'] !== '') {
            $builder->where('realname', $req['realname']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }

        if (isset($req['user']) && $req['user'] !== '') {
            if(strlen($req['user'])==11){
                $builder->where('phone',$req['user']);
            }else{
                $builder->whereIn('user_id', $req['user']);
            }
        }

        if(isset($req['start_date']) && $req['start_date']) {
            $builder->where('created_at', '>=', $req['start_date']);
        }
        if(isset($req['end_date']) && $req['end_date']) {
            $builder->where('created_at', '<=', $req['end_date']);
        }
        $statusArr = config('map.realname_status');
        $conf = config('filesystem.disks.qiniu');
        $domain = $conf['domain'];
        $data = $builder->paginate(['query' => $req])->each(function($item, $key) use ($statusArr,$domain){
            $item['status_text'] = $statusArr[$item['status']];
            $item['admin_name'] = AdminUser::where('id', $item['audit_admin_id'])->value('account');
            for($i=1;$i<=3;$i++){
                $item['img'.$i] = $this->replaceDomain($item['img'.$i],$domain);
            }
            return $item;
        });



        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function audit()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'status' => 'require|in:0,1,2',
            'audit_remark' => 'max:200',
        ]);
        $adminUser = $this->adminUser;
        Realname::audit($req['id'],$req['status'],$adminUser['id'],$req['audit_remark']);


        return out();
    }

    public function batchauditRealname(){
        $req = $this->validate(request(), [
            'ids' => 'require|array',
            'status' => 'require|in:0,1,2',
        ]);
        $adminUser = $this->adminUser;

        Db::startTrans();
        try {
            foreach ($req['ids'] as $v) {
                Realname::audit($v, $req['status'], $adminUser['id'], '');
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function replaceDomain($url,$domain){
        // 接收url, domain参数
        // 搜索realname表中的url字段不包含domain, 把url中的domain替换成新的domain
        // 使用正则替换
        if (strpos($url, $domain)) {
            return $url;
        }

        $pattern = '/^(https?:\/\/)?([^\/]+)/i';
        $replacement = '$1' . $domain;
        $newUrl = preg_replace($pattern, $replacement, $url);
        return $newUrl;

    }

    
}
