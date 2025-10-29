<?php

namespace app\admin\controller;

use app\model\invite_present\InviteCashConfig;
use think\facade\Cache;
use think\facade\Request;

class InviteCashConfigController extends AuthController
{
    // Redis缓存键名
    const INVITE_CASH_CONFIG_KEY = 'invite_cash_config_list';

    public function index()
    {
        $req = Request::param();

        try {
            // 生成缓存键
            $cacheKey = self::INVITE_CASH_CONFIG_KEY . ':admin:' . md5(serialize($req));
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                $data = unserialize($cachedData);
            } else {
                $query = InviteCashConfig::order('invite_num', 'asc');

                if (isset($req['invite_num']) && trim($req['invite_num']) != '') {
                    $query->where('invite_num', $req['invite_num']);
                }

                $data = $query->paginate(['query' => $req]);

                // 10分钟缓存
                $redis->setex($cacheKey, 600, serialize($data));
            }

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();

        } catch (\Exception $e) {
            // 异常处理
            $query = InviteCashConfig::order('invite_num', 'asc');

            if (isset($req['invite_num']) && trim($req['invite_num']) != '') {
                $query->where('invite_num', $req['invite_num']);
            }

            $data = $query->paginate(['query' => $req]);

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();
        }
    }

    public function showConfig()
    {
        $req = Request::param();
        $data = [];

        if (!empty($req['id'])) {
            $data = InviteCashConfig::find($req['id']);
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 清除配置缓存
     */
    private function clearConfigCache()
    {
        try {
            $redis = Cache::store('redis')->handler();
            $iterator = null;
            $pattern = self::INVITE_CASH_CONFIG_KEY . '*';

            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if ($keys !== false && !empty($keys)) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);

        } catch (\Exception $e) {
            \think\facade\Log::error('清除邀请现金配置缓存失败: ' . $e->getMessage());
        }
    }

    public function addConfig()
    {
        $req = $this->validate(Request::instance(), [
            'invite_num|邀请人数'  => 'require|integer|min:1',
            'cash_amount|现金金额' => 'require|float|min:0',
            'status|状态'          => 'require|in:0,1',
            'remark|备注'          => 'max:255',
        ]);

        // 检查邀请人数是否已存在
        $exists = InviteCashConfig::where('invite_num', $req['invite_num'])->find();

        if ($exists) {
            return out([], 400, '该邀请人数配置已存在');
        }

        $config = new InviteCashConfig();
        $config->save($req);

        $this->clearConfigCache();

        return out();
    }

    public function editConfig()
    {
        $req = $this->validate(Request::instance(), [
            'id'                   => 'require|integer',
            'invite_num|邀请人数'  => 'require|integer|min:1',
            'cash_amount|现金金额' => 'require|float|min:0',
            'status|状态'          => 'require|in:0,1',
            'remark|备注'          => 'max:255',
        ]);

        // 检查邀请人数是否已存在（排除当前记录）
        $exists = InviteCashConfig::where('invite_num', $req['invite_num'])
            ->where('id', '<>', $req['id'])
            ->find();

        if ($exists) {
            return out([], 400, '该邀请人数配置已存在');
        }

        $config = InviteCashConfig::find($req['id']);
        if (!$config) {
            return out([], 400, '配置不存在');
        }

        $config->save($req);

        $this->clearConfigCache();

        return out();
    }

    public function deleteConfig()
    {
        $req = $this->validate(Request::instance(), [
            'id' => 'require|integer'
        ]);

        $config = InviteCashConfig::find($req['id']);
        if (!$config) {
            return out([], 400, '配置不存在');
        }

        $config->delete();

        $this->clearConfigCache();

        return out();
    }
}