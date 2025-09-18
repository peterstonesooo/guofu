<?php

namespace app\admin\controller;

use app\model\GreenConfig;

class GreenConfigController extends AuthController
{
    // 绿色方案列表
    public function greenConfigList()
    {
        $req = request()->param();

        $builder = GreenConfig::order('sort asc, id desc');
        if (isset($req['green_config_id']) && $req['green_config_id'] !== '') {
            $builder->where('id', $req['green_config_id']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }

//        trace('查询SQL: ' . $builder->fetchSql());
        $data = $builder->paginate(['query' => $req]);
        trace('查询结果: ' . json_encode($data->items()));

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    // 显示添加/编辑页面
    public function showGreenConfig()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = GreenConfig::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    // 添加绿色方案
    public function addGreenConfig()
    {
        $req = $this->validate(request(), [
            'name|方案名称'           => 'require|max:100',
            'priority_queue|优先队列' => 'require|number',
            'channel_fee|通道费'      => 'require|float',
            'sort|排序'               => 'number',
        ]);

        GreenConfig::create($req);

        return out(null, 200, '添加成功');
    }

    // 编辑绿色方案
    public function editGreenConfig()
    {
        $req = $this->validate(request(), [
            'id'                      => 'require|number',
            'name|方案名称'           => 'require|max:100',
            'priority_queue|优先队列' => 'require|number',
            'channel_fee|通道费'      => 'require|float',
            'sort|排序'               => 'number',
        ]);

        GreenConfig::where('id', $req['id'])->update($req);

        return out(null, 200, '编辑成功');
    }

    // 更改状态
    public function changeGreenConfig()
    {
        $req = $this->validate(request(), [
            'id'    => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        GreenConfig::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    // 删除绿色方案
    public function delGreenConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        GreenConfig::where('id', $req['id'])->delete();

        return out(null, 200, '删除成功');
    }
}