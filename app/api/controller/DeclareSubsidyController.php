<?php

namespace app\api\controller;

use app\api\service\DeclareSubsidyService;
use app\model\subsidy_butie\DeclareRecord;
use app\model\subsidy_butie\DeclareSubsidyConfig;
use app\model\subsidy_butie\DeclareSubsidyType;
use think\facade\Cache;

class DeclareSubsidyController extends AuthController
{
    // 缓存键名（与后台共用）
    const CACHE_KEY_CONFIG_LIST = 'declare_subsidy_config_list';
    const CACHE_KEY_TYPE_LIST = 'declare_subsidy_type_list';
    const CACHE_KEY_GUARD_CONFIG_LIST = 'guard_config_list';
    const CACHE_KEY_GUARD_TYPE_LIST = 'guard_type_list';

    /**
     * 获取申报补贴类型列表（带缓存）
     * @param integer type 类型:1=申报补贴,2=守护
     */
    public function typeList()
    {
        $type = $this->request->param('type/d', 1); // 默认查询申报补贴类型

        try {
            // 根据类型选择缓存键名（与后台保持一致）
            $cacheKey = $type == 2 ? self::CACHE_KEY_GUARD_TYPE_LIST : self::CACHE_KEY_TYPE_LIST;
            $cacheKey .= ':api:' . md5(serialize(['type' => $type]));

            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if (!is_null($cachedData) && $cachedData !== false) {
                $typeList = unserialize($cachedData);
            } else {
                $query = DeclareSubsidyType::where('status', 1)
                    ->order('sort', 'desc')
                    ->order('id', 'desc');

                // 根据类型筛选
                if ($type > 0) {
                    $query->where('type', $type);
                }

                $typeList = $query->select()->toArray();

                // 缓存10分钟
                if (!empty($typeList)) {
                    $redis->setex($cacheKey, 600, serialize($typeList));
                } else {
                    $redis->setex($cacheKey, 600, serialize([]));
                }
            }

            return out([
                'list' => $typeList
            ]);

        } catch (\Exception $e) {
            // 缓存异常时直接查询数据库
            $query = DeclareSubsidyType::where('status', 1)
                ->order('sort', 'desc')
                ->order('id', 'desc');

            if ($type > 0) {
                $query->where('type', $type);
            }

            $typeList = $query->select()->toArray();

            return out([
                'list' => $typeList
            ]);
        }
    }

    /**
     * 获取申报补贴配置列表（带缓存）
     * @param integer type_id 补贴类型ID（可选）
     * @param integer type 类型:1=申报补贴,2=守护（可选）
     */
    public function configList()
    {
        $type_id = $this->request->param('type_id/d', 0);
        $type = $this->request->param('type/d', 0);

        try {
            // 根据参数确定缓存键名
            $cacheParams = ['type_id' => $type_id, 'type' => $type];
            $cacheKey = '';

            // 如果同时传递了type_id和type，优先使用type_id对应的类型
            if ($type_id > 0) {
                // 获取type_id对应的类型
                $subsidyType = DeclareSubsidyType::where('id', $type_id)
                    ->where('status', 1)
                    ->find();

                if ($subsidyType) {
                    $actualType = $subsidyType->type;
                    $cacheKey = $actualType == 2 ? self::CACHE_KEY_GUARD_CONFIG_LIST : self::CACHE_KEY_CONFIG_LIST;
                } else {
                    // 如果type_id不存在，则根据type参数
                    $actualType = $type;
                    $cacheKey = $type == 2 ? self::CACHE_KEY_GUARD_CONFIG_LIST : self::CACHE_KEY_CONFIG_LIST;
                }
            } else {
                // 只根据type参数
                $actualType = $type;
                $cacheKey = $type == 2 ? self::CACHE_KEY_GUARD_CONFIG_LIST : self::CACHE_KEY_CONFIG_LIST;
            }

            $cacheKey .= ':api:' . md5(serialize($cacheParams));

            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                $configList = unserialize($cachedData);
            } else {
                $query = DeclareSubsidyConfig::with(['subsidyType', 'subsidyFunds' => function ($q) {
                    $q->with('fundType');
                }])
                    ->where('status', 1)
                    ->order('sort', 'desc')
                    ->order('id', 'desc');

                // 处理查询条件
                if ($type_id > 0) {
                    // 如果传了type_id，优先使用type_id
                    $query->where('type_id', $type_id);

                    // 如果同时传了type，验证type_id和type是否匹配
                    if ($type > 0) {
                        $subsidyType = DeclareSubsidyType::where('id', $type_id)
                            ->where('type', $type)
                            ->where('status', 1)
                            ->find();

                        if (!$subsidyType) {
                            // 如果不匹配，返回空数据
                            $configList = [];
                            $redis->setex($cacheKey, 600, serialize($configList));
                            return out(['list' => $configList]);
                        }
                    }
                } // 如果只传了type，没有传type_id
                elseif ($type > 0) {
                    // 获取指定类型的type_id列表
                    $typeIds = DeclareSubsidyType::where('type', $type)
                        ->where('status', 1)
                        ->column('id');

                    if (!empty($typeIds)) {
                        $query->whereIn('type_id', $typeIds);
                    } else {
                        $query->where('type_id', 0); // 确保没有数据返回
                    }
                } // 如果既没有传type_id也没有传type，返回所有启用的配置
                else {
                    // 不添加额外的type_id筛选条件，返回所有status=1的配置
                }

                $configList = $query->select()->toArray();

                // 处理资金配置数据
                foreach ($configList as &$config) {
                    if (isset($config['subsidy_funds'])) {
                        $config['funds'] = $config['subsidy_funds'];
                        unset($config['subsidy_funds']);
                    }

                    // 添加类型信息，便于前端区分
                    $config['actual_type'] = $config['subsidy_type']['type'] ?? 0;
                }

                // 缓存10分钟
                if (!empty($configList)) {
                    $redis->setex($cacheKey, 600, serialize($configList));
                } else {
                    $redis->setex($cacheKey, 600, serialize([]));
                }
            }

            return out([
                'list' => $configList
            ]);

        } catch (\Exception $e) {
            // 缓存异常时直接查询数据库
            $query = DeclareSubsidyConfig::with(['subsidyType', 'subsidyFunds' => function ($q) {
                $q->with('fundType');
            }])
                ->where('status', 1)
                ->order('sort', 'desc')
                ->order('id', 'desc');

            // 处理查询条件（与缓存逻辑保持一致）
            if ($type_id > 0) {
                $query->where('type_id', $type_id);

                if ($type > 0) {
                    $subsidyType = DeclareSubsidyType::where('id', $type_id)
                        ->where('type', $type)
                        ->where('status', 1)
                        ->find();

                    if (!$subsidyType) {
                        return out(['list' => []]);
                    }
                }
            } elseif ($type > 0) {
                $typeIds = DeclareSubsidyType::where('type', $type)
                    ->where('status', 1)
                    ->column('id');

                if (!empty($typeIds)) {
                    $query->whereIn('type_id', $typeIds);
                }
            }

            $configList = $query->select()->toArray();

            // 处理资金配置数据
            foreach ($configList as &$config) {
                if (isset($config['subsidy_funds'])) {
                    $config['funds'] = $config['subsidy_funds'];
                    unset($config['subsidy_funds']);
                }

                $config['actual_type'] = $config['subsidy_type']['type'] ?? 0;
            }

            return out([
                'list' => $configList
            ]);
        }
    }

    /**
     * 购买申报补贴配置
     * @param integer subsidy_id 补贴配置ID
     * @param integer pay_type 支付方式 (1=充值余额, 2=团队奖金余额)
     * @param string pay_password 支付密码
     */
    public function buyConfig()
    {
        $user = $this->user;
        $subsidy_id = $this->request->param('subsidy_id/d', 0);
        $pay_type = $this->request->param('pay_type/d', 0);
        $pay_password = $this->request->param('pay_password', '');

        // 参数验证
        if ($subsidy_id <= 0 || !in_array($pay_type, [1, 2])) {
            return out(null, 10001, '参数错误');
        }

        // 支付密码验证
        if (empty($user['pay_password'])) {
            return out(null, 10010, '请先设置支付密码');
        }
        if (sha1(md5($pay_password)) !== $user['pay_password']) {
            return out(null, 10011, '支付密码错误');
        }

        try {
            $result = DeclareSubsidyService::buyConfig($user['id'], $subsidy_id, $pay_type);
            if ($result) {
                // 清除相关缓存（包括后台和API的缓存）
                $this->clearCache();
                return out(null, 200, '购买成功');
            }
            return out(null, 10003, '购买失败');
        } catch (\Exception $e) {
            return out(null, 10004, $e->getMessage());
        }
    }

    /**
     * 获取申报补贴购买记录
     * @param integer type 类型:1=申报补贴,2=守护（可选）
     * @param integer page 页码
     * @param integer limit 每页条数
     */
    public function purchaseList()
    {
        $user_id = $this->user['id'];
        $type = $this->request->param('type/d', 0);
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        try {
            // 使用更简单的查询方式，避免复杂的关联查询
            $query = DeclareRecord::alias('r')
                ->leftJoin('mp_declare_subsidy_config c', 'r.subsidy_id = c.id')
                ->leftJoin('mp_declare_subsidy_type t', 'c.type_id = t.id')
                ->field('r.*, c.name as subsidy_name, c.declare_amount, c.declare_cycle, t.type')
                ->where('r.user_id', $user_id)
                ->order('r.id', 'desc');

            // 根据类型筛选
            if ($type > 0) {
                $query->where('t.type', $type);
            }

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->page($page, $limit)->select()->toArray();

            // 处理数据格式
            foreach ($list as &$item) {
                // 添加类型文本
                $typeMap = [
                    1 => '申报补贴',
                    2 => '守护'
                ];
                $item['type_text'] = $typeMap[$item['type']] ?? '未知类型';

                // 添加状态文本
                $statusMap = [
                    0 => '审核失败',
                    1 => '审核成功'
                ];
                $item['status_text'] = $statusMap[$item['status']] ?? '未知状态';
            }

            return out([
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ]);

        } catch (\Exception $e) {
            // 如果上面的查询方式有问题，尝试更简单的查询
            try {
                $query = DeclareRecord::with(['subsidyConfig'])
                    ->where('user_id', $user_id)
                    ->order('id', 'desc');

                // 根据类型筛选 - 使用子查询方式
                if ($type > 0) {
                    $typeIds = DeclareSubsidyType::where('type', $type)
                        ->where('status', 1)
                        ->column('id');

                    if (!empty($typeIds)) {
                        $subsidyIds = DeclareSubsidyConfig::whereIn('type_id', $typeIds)
                            ->column('id');

                        if (!empty($subsidyIds)) {
                            $query->whereIn('subsidy_id', $subsidyIds);
                        } else {
                            $query->where('subsidy_id', 0); // 确保没有数据
                        }
                    } else {
                        $query->where('subsidy_id', 0); // 确保没有数据
                    }
                }

                // 获取总数
                $total = $query->count();

                // 获取分页数据
                $list = $query->page($page, $limit)->select()->toArray();

                // 处理数据格式
                foreach ($list as &$item) {
                    $item['subsidy_name'] = $item['subsidy_config']['name'] ?? '';
                    $item['declare_amount'] = $item['subsidy_config']['declare_amount'] ?? 0;
                    $item['declare_cycle'] = $item['subsidy_config']['declare_cycle'] ?? 0;

                    // 获取类型信息
                    if (!empty($item['subsidy_config']['type_id'])) {
                        $subsidyType = DeclareSubsidyType::where('id', $item['subsidy_config']['type_id'])
                            ->find();
                        $item['type'] = $subsidyType ? $subsidyType->type : 0;
                    } else {
                        $item['type'] = 0;
                    }

                    $typeMap = [
                        1 => '申报补贴',
                        2 => '守护'
                    ];
                    $item['type_text'] = $typeMap[$item['type']] ?? '未知类型';

                    unset($item['subsidy_config']);

                    // 添加状态文本
                    $statusMap = [
                        0 => '审核失败',
                        1 => '审核成功'
                    ];
                    $item['status_text'] = $statusMap[$item['status']] ?? '未知状态';
                }

                return out([
                    'list'         => $list,
                    'total'        => $total,
                    'current_page' => $page,
                    'total_page'   => ceil($total / $limit)
                ]);

            } catch (\Exception $e2) {
                return out(null, 10005, '查询失败: ' . $e2->getMessage());
            }
        }
    }

    /**
     * 清除缓存（与后台共用，确保数据一致性）
     */
    private function clearCache()
    {
        try {
            $redis = Cache::store('redis')->handler();

            // 清除类型列表缓存（申报补贴和守护）
            $typePatterns = [
                self::CACHE_KEY_TYPE_LIST . '*',
                self::CACHE_KEY_GUARD_TYPE_LIST . '*'
            ];

            foreach ($typePatterns as $pattern) {
                $this->clearCacheByPattern($redis, $pattern);
            }

            // 清除配置列表缓存（申报补贴和守护）
            $configPatterns = [
                self::CACHE_KEY_CONFIG_LIST . '*',
                self::CACHE_KEY_GUARD_CONFIG_LIST . '*'
            ];

            foreach ($configPatterns as $pattern) {
                $this->clearCacheByPattern($redis, $pattern);
            }

        } catch (\Exception $e) {
            \think\facade\Log::error('清除申报补贴缓存失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据模式清除缓存
     */
    private function clearCacheByPattern($redis, $pattern)
    {
        $iterator = null;

        do {
            $keys = $redis->scan($iterator, $pattern, 100);
            if ($keys !== false && !empty($keys)) {
                $redis->del($keys);
            }
        } while ($iterator > 0);
    }
}