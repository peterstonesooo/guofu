<?php

namespace app\model\subsidy_butie;

use think\facade\Db;
use think\Model;

class DeclareFundType extends Model
{

    protected $autoWriteTimestamp = false;

    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段映射
    protected $type = [
        'id'         => 'integer',
        'name'       => 'string',
        'status'     => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取资金类型列表
     */
    public static function getList($params = [])
    {
        $query = self::order('id', 'desc');

        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . trim($params['name']) . '%');
        }

        return $query->paginate(['query' => $params]);
    }

    /**
     * 检查资金类型是否被使用
     */
    public static function checkUsed($fundTypeId)
    {
        return Db::name('declare_subsidy_fund')
            ->where('fund_type_id', $fundTypeId)
            ->find();
    }
}