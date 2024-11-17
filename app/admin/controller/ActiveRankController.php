<?php

namespace app\admin\controller;

use app\common\command\ActiveRank;
use think\facade\Db;

class ActiveRankController extends AuthController
{
    public function index()
    {
        $req = request()->param();

        $data = Db::name('active_rank')->select();

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }


    public function save()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'msg' => '请求方式错误']);
        }

        $data = $this->request->post();
        
        // 验证必填参数
        if (empty($data['id']) || empty($data['phone'])) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        // 组织保存数据
        $saveData = [
            'phone' => $data['phone'],
            'day_max' => $data['day_max'],
            'day_min' => $data['day_min'],
            'max' => $data['max'],
            'min' => $data['min'],
            'update_time' => time()
        ];

        try {
            Db::name('active_rank')->where('id', $data['id'])->update($saveData);
            return json(['code' => 1, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '保存失败：' . $e->getMessage()]);
        }
    }

    public function changeStatus(){
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        Db::name('active_rank')->where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }
}
