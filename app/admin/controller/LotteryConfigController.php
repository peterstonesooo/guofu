<?php
declare (strict_types = 1);

namespace app\admin\controller;
use think\facade\Db;
use think\Request;

class LotteryConfigController extends AuthController
{
    public function index()
    {
        $req = request()->param();
        
        // 改为查询抽奖配置表
        $data = Db::name('lottery_config')->select();

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
        if (empty($data['id']) || empty($data['name']) || !isset($data['lottery_ratio']) || !isset($data['num'])) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        // 组织保存数据
        $saveData = [
            'name' => $data['name'],
            'lottery_ratio' => intval($data['lottery_ratio']),
            'num' => intval($data['num']),
        ];

        try {
            Db::name('lottery_config')->where('id', $data['id'])->update($saveData);
            return json(['code' => 1, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '保存失败']);
        }
    }

    public function add()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'msg' => '请求方式错误']);
        }

        $data = $this->request->post();
        
        // 验证必填参数
        if (empty($data['name']) || !isset($data['lottery_ratio'])) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        // 组织保存数据
        $saveData = [
            'name' => $data['name'],
            'lottery_ratio' => intval($data['lottery_ratio']),
            'num' => intval($data['num'] ?? 0),
        ];

        try {
            $result = Db::name('lottery_config')->insert($saveData);
            if ($result) {
                return json(['code' => 1, 'msg' => '添加成功']);
            }
            return json(['code' => 0, 'msg' => '添加失败']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '系统错误']);
        }
    }
}
