<?php

namespace app\model\subsidy_butie;

use think\facade\Db;
use think\Model;

class DeclareSubsidyConfig extends Model
{

    protected $autoWriteTimestamp = false;

    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段映射
    protected $type = [
        'id'             => 'integer',
        'type_id'        => 'integer',
        'name'           => 'string',
        'declare_amount' => 'float',
        'declare_cycle'  => 'integer',
        'description'    => 'string',
        'sort'           => 'integer',
        'status'         => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    // 关联补贴类型
    public function subsidyType()
    {
        return $this->belongsTo(DeclareSubsidyType::class, 'type_id');
    }

    // 关联资金配置
    public function subsidyFunds()
    {
        return $this->hasMany(DeclareSubsidyFund::class, 'subsidy_id');
    }

    /**
     * 获取配置列表
     */
    public static function getList($params = [])
    {
        $query = self::with(['subsidyType'])
            ->order('sort', 'desc')
            ->order('id', 'desc');

        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . trim($params['name']) . '%');
        }

        if (!empty($params['type_id'])) {
            $query->where('type_id', $params['type_id']);
        }

        return $query->paginate(['query' => $params]);
    }

    /**
     * 获取配置详情（包含资金配置）
     */
    public static function getDetail($id)
    {
        return self::with(['subsidyFunds' => function ($query) {
            $query->field('id,subsidy_id,fund_type_id,fund_amount')
                ->withAttr('fund_type_name', function ($value, $data) {
                    return Db::name('declare_fund_type')
                        ->where('id', $data['fund_type_id'])
                        ->value('name');
                });
        }])->find($id);
    }

    /**
     * 检查配置是否被使用
     */
    public static function checkUsed($configId)
    {
        return Db::name('declare_record')
            ->where('subsidy_id', $configId)
            ->find();
    }
}