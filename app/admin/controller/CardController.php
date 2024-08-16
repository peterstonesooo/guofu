<?php

namespace app\admin\controller;

use app\model\ProjectCard;

class CardController extends AuthController
{
    public function projectList()
    {
        $req = request()->param();

        $builder = ProjectCard::order(['sort' => 'asc', 'id' => 'desc']);
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
            $data = ProjectCard::where('id', $req['id'])->find();
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
            'limit_tax|限制金额' => 'require|number',
            'limit_direction|限制方向' => 'require',
            'underline_price|划线价' => 'float',
        ]);

        $req['cover_img'] = upload_file('cover_img');
        ProjectCard::create($req);

        return out();
    }

    public function editProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'name|项目名称' => 'require|max:100',
            'single_amount|单份金额' => 'require|float',
            'sort|排序号' => 'integer',
            'limit_tax|限制金额' => 'require|number',
            'limit_direction|限制方向' => 'require',
            'underline_price|划线价' => 'float',
        ]);

        if ($img = upload_file('cover_img', false)) {
            $req['cover_img'] = $img;
        }
        ProjectCard::where('id', $req['id'])->update($req);

        return out();
    }

    public function changeProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        ProjectCard::where('id', $req['id'])->update([$req['field'] => $req['value']]);

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
