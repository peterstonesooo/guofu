<?php

namespace app\model;

use think\Model;

class AdminOperations extends Model
{

    protected $schema = [
        'id'          => 'int',
        'admin_id'    => 'int',
        'type'        => 'string',
        'target_id'   => 'int',
        'target_type' => 'string',
        'before_data' => 'string',
        'after_data'  => 'string',
        'ip'          => 'string',
        'remark'      => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // 定义管理员关联
    public function admin()
    {
        return $this->belongsTo(AdminUser::class, 'admin_id');
    }
}