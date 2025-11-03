<?php

namespace app\admin\controller;

use app\model\subsidy_butie\DeclareFundType;
use app\model\subsidy_butie\DeclareSubsidyConfig;
use app\model\subsidy_butie\DeclareSubsidyFund;
use app\model\subsidy_butie\DeclareSubsidyType;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class GuardConfigController extends AuthController
{
    // 缓存键名
    const CACHE_KEY = 'guard_config_list';

    // 守护类型值
    const GUARD_TYPE = 2;

    /**
     * 守护配置列表
     */
    public function index()
    {
        $req = request()->param();

        try {
            $cacheKey = self::CACHE_KEY . ':admin:' . md5(serialize($req));
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                $data = unserialize($cachedData);
            } else {
                // 只查询守护类型的配置
                $req['guard_type'] = self::GUARD_TYPE;
                $data = $this->getGuardConfigList($req);
                $redis->setex($cacheKey, 600, serialize($data));
            }

            // 获取守护类型列表
            $typeList = DeclareSubsidyType::getListByType(self::GUARD_TYPE);

            $this->assign('req', $req);
            $this->assign('data', $data);
            $this->assign('typeList', $typeList);

            return $this->fetch();

        } catch (\Exception $e) {
            // 如果缓存出错，直接查询数据库
            $req['guard_type'] = self::GUARD_TYPE;
            $data = $this->getGuardConfigList($req);
            $typeList = DeclareSubsidyType::getListByType(self::GUARD_TYPE);

            $this->assign('req', $req);
            $this->assign('data', $data);
            $this->assign('typeList', $typeList);

            return $this->fetch();
        }
    }

    /**
     * 获取守护配置列表
     */
    private function getGuardConfigList($params)
    {
        // 先尝试不使用关联查询，直接使用join
        $query = DeclareSubsidyConfig::alias('c')
            ->join('mp_declare_subsidy_type t', 'c.type_id = t.id')
            ->where('t.type', self::GUARD_TYPE)
            ->order('c.sort', 'desc')
            ->order('c.id', 'desc');

        if (!empty($params['name'])) {
            $query->where('c.name', 'like', '%' . trim($params['name']) . '%');
        }

        if (!empty($params['type_id'])) {
            $query->where('c.type_id', $params['type_id']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('c.status', $params['status']);
        }

        // 选择需要的字段
        $query->field('c.*, t.name as type_name');

        return $query->paginate(['query' => $params]);
    }

    /**
     * 显示添加/编辑页面
     */
    public function show()
    {
        $req = request()->param();
        $data = [];

        if (!empty($req['id'])) {
            // 使用join替代关联查询
            $data = DeclareSubsidyConfig::alias('c')
                ->join('mp_declare_subsidy_type t', 'c.type_id = t.id')
                ->where('t.type', self::GUARD_TYPE)
                ->where('c.id', $req['id'])
                ->field('c.*, t.name as type_name')
                ->find();

            if ($data) {
                $data = $data->toArray();
                // 获取资金配置
                $funds = DeclareSubsidyFund::where('subsidy_id', $req['id'])->select();
                $data['funds'] = $funds ? $funds->toArray() : [];
            }
        }

        // 获取守护类型列表
        $typeList = DeclareSubsidyType::getListByType(self::GUARD_TYPE)->toArray();

        // 获取资金类型列表
        $fundTypeList = DeclareFundType::where('status', 1)->order('id', 'desc')->select()->toArray();

        $this->assign('data', $data);
        $this->assign('typeList', $typeList);
        $this->assign('fundTypeList', $fundTypeList);

        return $this->fetch();
    }

    /**
     * 添加守护配置
     */
    public function add()
    {
        $req = $this->validate(request(), [
            'type_id|守护类型'        => 'require|number',
            'name|守护名称'           => 'require',
            'declare_amount|申报金额' => 'require|float',
            'declare_cycle|申报周期'  => 'require|integer',
            'description|守护描述'    => 'require',
            'sort|排序'               => 'require|integer',
            'status|状态'             => 'require|number',
            'funds'                   => 'array'
        ]);

        // 验证类型是否为守护类型
        $guardType = DeclareSubsidyType::where('type', self::GUARD_TYPE)
            ->where('id', $req['type_id'])
            ->find();

        if (!$guardType) {
            return out('请选择正确的守护类型', 400);
        }

        Db::startTrans();
        try {
            // 创建守护配置
            $config = DeclareSubsidyConfig::create([
                'type_id'        => $req['type_id'],
                'name'           => $req['name'],
                'declare_amount' => $req['declare_amount'],
                'declare_cycle'  => $req['declare_cycle'],
                'description'    => $req['description'],
                'sort'           => $req['sort'],
                'status'         => $req['status'],
            ]);

            // 保存资金配置
            if (!empty($req['funds']) && is_array($req['funds'])) {
                $fundData = [];
                foreach ($req['funds'] as $fund) {
                    if (!empty($fund['fund_type_id']) && !empty($fund['fund_amount'])) {
                        $fundData[] = [
                            'subsidy_id'   => $config->id,
                            'fund_type_id' => $fund['fund_type_id'],
                            'fund_amount'  => $fund['fund_amount'],
                            'created_at'   => date('Y-m-d H:i:s'),
                            'updated_at'   => date('Y-m-d H:i:s')
                        ];
                    }
                }
                if (!empty($fundData)) {
                    (new DeclareSubsidyFund())->saveAll($fundData);
                }
            }

            Db::commit();
            $this->clearCache();
            return out('添加成功');

        } catch (\Exception $e) {
            Db::rollback();
            return out('操作失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 编辑守护配置
     */
    public function edit()
    {
        $req = $this->validate(request(), [
            'id'                      => 'require|number',
            'type_id|守护类型'        => 'require|number',
            'name|守护名称'           => 'require',
            'declare_amount|申报金额' => 'require|float',
            'declare_cycle|申报周期'  => 'require|integer',
            'description|守护描述'    => 'require',
            'sort|排序'               => 'require|integer',
            'status|状态'             => 'require|number',
            'funds'                   => 'array'
        ]);

        // 验证类型是否为守护类型
        $guardType = DeclareSubsidyType::where('type', self::GUARD_TYPE)
            ->where('id', $req['type_id'])
            ->find();

        if (!$guardType) {
            return out('请选择正确的守护类型', 400);
        }

        Db::startTrans();
        try {
            // 使用join替代关联查询
            $config = DeclareSubsidyConfig::alias('c')
                ->join('mp_declare_subsidy_type t', 'c.type_id = t.id')
                ->where('t.type', self::GUARD_TYPE)
                ->where('c.id', $req['id'])
                ->field('c.*')
                ->find();

            if (!$config) {
                return out('守护配置不存在', 400);
            }

            // 更新守护配置
            $config->save([
                'type_id'        => $req['type_id'],
                'name'           => $req['name'],
                'declare_amount' => $req['declare_amount'],
                'declare_cycle'  => $req['declare_cycle'],
                'description'    => $req['description'],
                'sort'           => $req['sort'],
                'status'         => $req['status'],
            ]);

            // 删除原有资金配置，重新添加
            DeclareSubsidyFund::where('subsidy_id', $req['id'])->delete();

            // 保存新的资金配置
            if (!empty($req['funds']) && is_array($req['funds'])) {
                $fundData = [];
                foreach ($req['funds'] as $fund) {
                    if (!empty($fund['fund_type_id']) && !empty($fund['fund_amount'])) {
                        $fundData[] = [
                            'subsidy_id'   => $req['id'],
                            'fund_type_id' => $fund['fund_type_id'],
                            'fund_amount'  => $fund['fund_amount'],
                            'created_at'   => date('Y-m-d H:i:s'),
                            'updated_at'   => date('Y-m-d H:i:s')
                        ];
                    }
                }
                if (!empty($fundData)) {
                    (new DeclareSubsidyFund())->saveAll($fundData);
                }
            }

            Db::commit();
            $this->clearCache();
            return out('编辑成功');

        } catch (\Exception $e) {
            Db::rollback();
            return out('操作失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 删除守护配置
     */
    public function delete()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        try {
            // 使用join替代关联查询
            $config = DeclareSubsidyConfig::alias('c')
                ->join('mp_declare_subsidy_type t', 'c.type_id = t.id')
                ->where('t.type', self::GUARD_TYPE)
                ->where('c.id', $req['id'])
                ->field('c.*')
                ->find();

            if (!$config) {
                return out('守护配置不存在', 400);
            }

            // 检查是否有申报记录
            if (DeclareSubsidyConfig::checkUsed($req['id'])) {
                return out('该守护配置下存在申报记录，无法删除', 400);
            }

            Db::startTrans();
            try {
                // 删除资金配置
                DeclareSubsidyFund::where('subsidy_id', $req['id'])->delete();
                // 删除守护配置
                $config->delete();

                Db::commit();
                $this->clearCache();
                return out('删除成功');

            } catch (\Exception $e) {
                Db::rollback();
                return out('删除失败：' . $e->getMessage(), 400);
            }

        } catch (\Exception $e) {
            return out('删除失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 清除缓存
     */
    private function clearCache()
    {
        try {
            $redis = Cache::store('redis')->handler();
            $pattern = self::CACHE_KEY . '*';

            $iterator = null;
            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if ($keys !== false && !empty($keys)) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);

        } catch (\Exception $e) {
            Log::error('清除守护配置缓存失败: ' . $e->getMessage());
        }
    }
}