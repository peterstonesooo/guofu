<?php

namespace app\admin\controller;

use app\model\ProjectTax;

class TaxController extends AuthController
{
    public function projectList()
    {
        $req = request()->param();

        $builder = ProjectTax::order(['sort' => 'asc', 'id' => 'desc']);
        if (isset($req['name']) && $req['name'] !== '') {
            $builder->where('name', $req['name']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showProject()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = ProjectTax::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addProject()
    {
        $req = $this->validate(request(), [
            'name|项目名称' => 'require|max:100',
            'single_amount|单份金额' => 'require|float',
            'sort|排序号' => 'integer',
            'limit_asset|限制金额' => 'require|number',
            'virtually_progress|虚拟进度' => 'float',
            'limit_direction|限制方向' => 'require',
            'receive_card|共富专属卡' => 'require',
            'underline_price|划线价' => 'float',
        ]);

        $req['cover_img'] = upload_file('cover_img');
        ProjectTax::create($req);

        return out();
    }

    public function editProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'name|项目名称' => 'require|max:100',
            'single_amount|单份金额' => 'require|float',
            'sort|排序号' => 'integer',
            'limit_asset|限制金额' => 'require|number',
            'virtually_progress|虚拟进度' => 'float',
            'limit_direction|限制方向' => 'require',
            'receive_card|共富专属卡' => 'require',
            'underline_price|划线价' => 'float',
        ]);

        if ($img = upload_file('cover_img', false)) {
            $req['cover_img'] = $img;
        }
        $p = ProjectTax::where('id', $req['id'])->find();
        if($p['virtually_progress'] != $req['virtually_progress']) {
            $req['rate_time'] = time();
        }
        ProjectTax::where('id', $req['id'])->update($req);

        return out();
    }

    public function changeProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        ProjectTax::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }


    public function delProjects()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        ProjectTax::destroy($req['id']);

        return out();
    }
}
