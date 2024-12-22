<?php

namespace app\admin\controller;
use app\admin\controller\AuthController;

use think\facade\Db;

class CateController extends AuthController
{
    public function cateList()
    {
        $req = request()->param();

        $builder = Db::table('shop_cate')->order('id', 'asc');


        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showCate()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data =  Db::table('shop_cate')->where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function editCate()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'title|分类名称' => 'require',
            'sort|排序' => 'require|integer',
        ]);

        Db::table('shop_cate')->where('id', $req['id'])->update($req);

        return out();
    }

    public function addCate()
    {
        $req = $this->validate(request(), [
            'title|分类名称' => 'require',
            'sort|排序' => 'require|integer',
        ]);

        Db::table('shop_cate')->insert($req);

        return out();
    }

}