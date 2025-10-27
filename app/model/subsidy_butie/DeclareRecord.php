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
     * 关联补贴类型
     */
    public function subsidyType()
    {
        return $this->belongsTo(DeclareSubsidyType::class, 'subsidy_config.type_id', 'id')
            ->through('subsidyConfig');
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
     * 获取列表数据
     */
    public static function getList($params)
    {
        $query = self::with(['user', 'subsidyConfig', 'subsidyType'])
            ->order('created_at', 'desc');

        // 搜索条件
        if (!empty($params['subsidy_name'])) {
            $query->whereHas('subsidyConfig', function ($q) use ($params) {
                $q->where('name', 'like', "%{$params['subsidy_name']}%");
            });
        }

        if (!empty($params['user_name'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('username', 'like', "%{$params['user_name']}%");
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
                $query->field('id,username as user_name');
            },
            'subsidyConfig' => function ($query) {
                $query->field('id,name as subsidy_name,description');
            },
            'subsidyType'   => function ($query) {
                $query->field('id,name as type_name');
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