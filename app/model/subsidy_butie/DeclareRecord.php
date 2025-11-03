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
     * 获取补贴类型名称 - 通过补贴配置间接获取
     */
    public function getSubsidyTypeNameAttr($value, $data)
    {
        // 如果已经有关联数据，直接返回
        if (isset($this->subsidyConfig) && $this->subsidyConfig->subsidyType) {
            return $this->subsidyConfig->subsidyType->name;
        }

        // 否则查询数据库
        $config = DeclareSubsidyConfig::with('subsidyType')
            ->where('id', $data['subsidy_id'])
            ->find();

        return $config->subsidyType->name ?? '';
    }

    /**
     * 关联资金明细
     */
    public function funds()
    {
        return $this->hasMany(DeclareRecordFund::class, 'declare_id', 'id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? $value;
        $map = [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED  => '失败'
        ];
        return $map[$status] ?? '未知';
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
}