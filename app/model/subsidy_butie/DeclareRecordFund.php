<?php

namespace app\model\subsidy_butie;

use think\Model;

class DeclareRecordFund extends Model
{

    protected $autoWriteTimestamp = false;

    protected $createTime = 'created_at';

    // 字段映射
    protected $type = [
        'id'           => 'integer',
        'declare_id'   => 'integer',
        'fund_type_id' => 'integer',
        'fund_amount'  => 'float',
        'created_at'   => 'datetime',
    ];

    // 关联资金类型
    public function fundType()
    {
        return $this->belongsTo(DeclareFundType::class, 'fund_type_id');
    }
}