<?php

namespace app\admin\controller;

use app\model\subsidy_butie\DeclareFundType;
use app\model\subsidy_butie\DeclareSubsidyConfig;
use app\model\subsidy_butie\DeclareSubsidyFund;
use app\model\subsidy_butie\DeclareSubsidyType;
use think\facade\Cache;
use think\facade\Db;

class DeclareSubsidyConfigController extends AuthController
{
    // 缓存键名
    const CACHE_KEY = 'declare_subsidy_config_list';

    /**
     * 补贴配置列表
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
                $data = DeclareSubsidyConfig::getList($req);
                $redis->setex($cacheKey, 600, serialize($data));
            }

            // 获取补贴类型列表
            $typeList = DeclareSubsidyType::where('status', 1)->select();

            $this->assign('req', $req);
            $this->assign('data', $data);
            $this->assign('typeList', $typeList);

            return $this->fetch();

        } catch (\Exception $e) {
            $data = DeclareSubsidyConfig::getList($req);
            $typeList = DeclareSubsidyType::where('status', 1)->select();

            $this->assign('req', $req);
            $this->assign('data', $data);
            $this->assign('typeList', $typeList);

            return $this->fetch();
        }
    }

    /**
     * 显示添加/编辑页面
     */
    public function show()
    {
        $req = request()->param();
        $data = [];

        if (!empty($req['id'])) {
            $data = DeclareSubsidyConfig::getDetail($req['id']);
        }

        // 获取补贴类型列表
        $typeList = DeclareSubsidyType::where('status', 1)->select();
        // 获取资金类型列表
        $fundTypeList = DeclareFundType::where('status', 1)->select();

        $this->assign('data', $data);
        $this->assign('typeList', $typeList);
        $this->assign('fundTypeList', $fundTypeList);

        return $this->fetch();
    }

    /**
     * 添加补贴配置
     */
    public function add()
    {
        $req = $this->validate(request(), [
            'type_id|补贴类型'        => 'require|number',
            'name|补贴名称'           => 'require',
            'declare_amount|申报金额' => 'require|float',
            'declare_cycle|申报周期'  => 'require|integer',
            'description|补贴描述'    => 'require',
            'sort|排序'               => 'require|integer',
            'status|状态'             => 'require|number',
            'funds'                   => 'array'
        ]);

        Db::startTrans();
        try {
            // 创建补贴配置
            $config = new DeclareSubsidyConfig();
            $config->save([
                'type_id'        => $req['type_id'],
                'name'           => $req['name'],
                'declare_amount' => $req['declare_amount'],
                'declare_cycle'  => $req['declare_cycle'],
                'description'    => $req['description'],
                'sort'           => $req['sort'],
                'status'         => $req['status'],
            ]);

            // 保存资金配置
            if (!empty($req['funds'])) {
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
            return out();

        } catch (\Exception $e) {
            Db::rollback();
            return out('操作失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 编辑补贴配置
     */
    public function edit()
    {
        $req = $this->validate(request(), [
            'id'                      => 'require|number',
            'type_id|补贴类型'        => 'require|number',
            'name|补贴名称'           => 'require',
            'declare_amount|申报金额' => 'require|float',
            'declare_cycle|申报周期'  => 'require|integer',
            'description|补贴描述'    => 'require',
            'sort|排序'               => 'require|integer',
            'status|状态'             => 'require|number',
            'funds'                   => 'array'
        ]);

        Db::startTrans();
        try {
            $config = DeclareSubsidyConfig::find($req['id']);
            if (!$config) {
                return out('补贴配置不存在', 400);
            }

            // 更新补贴配置
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
            if (!empty($req['funds'])) {
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
            return out();

        } catch (\Exception $e) {
            Db::rollback();
            return out('操作失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 删除补贴配置
     */
    public function delete()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        try {
            // 检查是否有申报记录
            if (DeclareSubsidyConfig::checkUsed($req['id'])) {
                return out('该补贴配置下存在申报记录，无法删除', 400);
            }

            $config = DeclareSubsidyConfig::find($req['id']);
            if (!$config) {
                return out('补贴配置不存在', 400);
            }

            Db::startTrans();
            try {
                // 删除资金配置
                DeclareSubsidyFund::where('subsidy_id', $req['id'])->delete();
                // 删除补贴配置
                $config->delete();

                Db::commit();
                $this->clearCache();
                return out();

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
            \think\facade\Log::error('清除补贴配置缓存失败: ' . $e->getMessage());
        }
    }
}