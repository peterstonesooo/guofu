<?php

namespace app\model\subsidy_butie;

use app\model\User;
use think\Model;

class DeclareRecord extends Model
{

    protected $autoWriteTimestamp = false;

    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段映射
    protected $type = [
        'id'             => 'integer',
        'user_id'        => 'integer',
        'subsidy_id'     => 'integer',
        'declare_amount' => 'float',
        'declare_cycle'  => 'integer',
        'status'         => 'integer',
        'audit_remark'   => 'string',
        'audit_time'     => 'datetime',
        'audit_user_id'  => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    // 状态常量
    const STATUS_PENDING = 0;   // 待审核
    const STATUS_APPROVED = 1; // 审核通过
    const STATUS_REJECTED = 2; // 审核不通过

    // 关联补贴配置
    public function subsidyConfig()
    {
        return $this->belongsTo(DeclareSubsidyConfig::class, 'subsidy_id');
    }

    // 关联补贴类型
    public function subsidyType()
    {
        return $this->hasOneThrough(
            DeclareSubsidyType::class,
            DeclareSubsidyConfig::class,
            'id', // 补贴配置表主键
            'id', // 补贴类型表主键
            'subsidy_id', // 申报记录表的补贴配置ID
            'type_id' // 补贴配置表的类型ID
        );
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 关联审核人
    public function auditUser()
    {
        return $this->belongsTo(User::class, 'audit_user_id');
    }

    // 关联资金明细
    public function recordFunds()
    {
        return $this->hasMany(DeclareRecordFund::class, 'declare_id');
    }

    /**
     * 获取申报记录列表
     */
    public static function getList($params = [])
    {
        $query = self::with(['subsidyConfig', 'subsidyType', 'user'])
            ->order('created_at', 'desc')
            ->order('id', 'desc');

        if (!empty($params['subsidy_name'])) {
            $query->whereHas('subsidyConfig', function ($q) use ($params) {
                $q->where('name', 'like', '%' . trim($params['subsidy_name']) . '%');
            });
        }

        if (!empty($params['user_name'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('username', 'like', '%' . trim($params['user_name']) . '%');
            });
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        return $query->paginate(['query' => $params]);
    }

    /**
     * 获取申报记录详情
     */
    public static function getDetail($id)
    {
        return self::with([
            'subsidyConfig',
            'subsidyType',
            'user',
            'auditUser',
            'recordFunds' => function ($query) {
                $query->field('id,declare_id,fund_type_id,fund_amount')
                    ->withAttr('fund_type_name', function ($value, $data) {
                        return \think\facade\Db::name('declare_fund_type')
                            ->where('id', $data['fund_type_id'])
                            ->value('name');
                    });
            }
        ])->find($id);
    }
}