<?php

namespace app\model\subsidy_butie;

use app\model\User;
use think\Model;

class DeclareRecord extends Model
{
    // 状态常量
    const STATUS_FAILED = 0;
    const STATUS_SUCCESS = 1;

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 关联补贴配置
     */
    public function subsidyConfig()
    {
        return $this->belongsTo(DeclareSubsidyConfig::class, 'subsidy_id', 'id');
    }

    /**
     * 关联资金明细
     */
    public function funds()
    {
        return $this->hasMany(DeclareRecordFund::class, 'declare_id', 'id');
    }

    /**
     * 获取列表数据 - 简化版本
     */
    public static function getList($params)
    {
        $query = self::with([
            'user'          => function ($q) {
                $q->field('id,realname');
            },
            'subsidyConfig' => function ($q) {
                $q->field('id,name,type_id')->with(['subsidyType' => function ($q2) {
                    $q2->field('id,name');
                }]);
            },
            'funds'         => function ($q) {
                $q->with(['fundType' => function ($q2) {
                    $q2->field('id,name');
                }]);
            }
        ])->order('created_at', 'desc');

        // 搜索条件
        if (!empty($params['subsidy_name'])) {
            $query->whereHas('subsidyConfig', function ($q) use ($params) {
                $q->where('name', 'like', "%{$params['subsidy_name']}%");
            });
        }

        if (!empty($params['user_name'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('realname', 'like', "%{$params['user_name']}%");
            });
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        return $query->paginate(['list_rows' => 15, 'query' => $params]);
    }

    /**
     * 获取详情
     */
    public static function getDetail($id)
    {
        $record = self::with([
            'user'          => function ($query) {
                $query->field('id,realname as user_name');
            },
            'subsidyConfig' => function ($query) {
                $query->field('id,name as subsidy_name,description,type_id')
                    ->with(['subsidyType' => function ($q) {
                        $q->field('id,name as type_name');
                    }]);
            },
            'funds'         => function ($query) {
                $query->with(['fundType' => function ($q) {
                    $q->field('id,name as fund_type_name');
                }]);
            }
        ])->find($id);

        if ($record) {
            return $record->toArray();
        }

        return [];
    }

    /**
     * 获取用户购买记录列表（API使用）- 修复对象访问问题
     */
    public static function getUserPurchaseList($user_id, $type = 0, $page = 1, $limit = 10)
    {
        // 使用简单的JOIN查询，避免复杂的关联查询
        $query = self::alias('r')
            ->leftJoin('mp_declare_subsidy_config c', 'r.subsidy_id = c.id')
            ->leftJoin('mp_declare_subsidy_type t', 'c.type_id = t.id')
            ->field('r.*, c.name as subsidy_name, c.declare_amount, c.declare_cycle, t.type, t.name as type_name')
            ->where('r.user_id', $user_id)
            ->order('r.id', 'desc');

        // 根据类型筛选
        if ($type > 0) {
            $query->where('t.type', $type);
        }

        // 获取总数
        $total = $query->count();

        // 获取分页数据
        $list = $query->page($page, $limit)->select();

        // 处理数据格式
        $result = [];
        if ($list) {
            $list = $list->toArray();

            foreach ($list as $item) {
                // 确保所有字段都有默认值
                $item['type'] = $item['type'] ?? 1;
                $item['subsidy_name'] = $item['subsidy_name'] ?? '';
                $item['declare_amount'] = $item['declare_amount'] ?? 0;
                $item['declare_cycle'] = $item['declare_cycle'] ?? 0;
                $item['type_name'] = $item['type_name'] ?? '';

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

                // 获取资金明细数据
                $item['subsidyFunds'] = self::getRecordFunds($item['id']);

                $result[] = $item;
            }
        }

        return [
            'list'         => $result,
            'total'        => $total,
            'current_page' => $page,
            'total_page'   => $limit > 0 ? ceil($total / $limit) : 1
        ];
    }

    /**
     * 获取记录的资金明细
     */
    private static function getRecordFunds($declare_id)
    {
        if (!$declare_id) {
            return [];
        }

        $funds = DeclareRecordFund::alias('rf')
            ->leftJoin('mp_declare_fund_type ft', 'rf.fund_type_id = ft.id')
            ->field('rf.*, ft.name as fund_type_name')
            ->where('rf.declare_id', $declare_id)
            ->select();

        $fundsData = [];
        if ($funds) {
            $funds = $funds->toArray();

            foreach ($funds as $fund) {
                $fundsData[] = [
                    'id'             => $fund['id'] ?? 0,
                    'fund_type_id'   => $fund['fund_type_id'] ?? 0,
                    'fund_amount'    => $fund['fund_amount'] ?? 0,
                    'fund_type_name' => $fund['fund_type_name'] ?? '',
                    'created_at'     => $fund['created_at'] ?? ''
                ];
            }
        }

        return $fundsData;
    }
}