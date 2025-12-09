<?php

namespace app\model\subsidy_butie;

use think\Model;

/**
 * DeclareSubsidyType 模型类
 * 用于处理申报补贴类型相关的数据操作
 */
class DeclareSubsidyType extends Model
{
    // 类型常量定义
    const TYPE_SUBSIDY = 1; // 申报补贴类型
    const TYPE_GUARD = 2;   // 守护类型

    // 禁用自动时间戳
    protected $autoWriteTimestamp = false;

    // 定义创建时间和更新时间字段
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段映射
    protected $type = [
        'id'          => 'integer',
        'name'        => 'string',
        'code'        => 'string',
        'type'        => 'integer', // 新增字段
        'imgurl'      => 'string',
        'description' => 'string',
        'sort'        => 'integer',
        'status'      => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // 获取器 - 图片完整URL
    public function getImgUrlAttr($value, $data)
    {
        if (!empty($data['imgurl'])) {
            return env('app.img_host') . '/' . $data['imgurl'];
        }
        return '';
    }

    /**
     * 获取类型列表
     */
    public static function getList($params = [])
    {
        $query = self::order('sort', 'desc')->order('id', 'desc');

        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . trim($params['name']) . '%');
        }

        if (isset($params['type']) && $params['type'] !== '') {
            $query->where('type', $params['type']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        return $query->paginate(['query' => $params]);
    }

    /**
     * 根据类型获取列表
     */
    public static function getListByType($type)
    {
        return self::where('type', $type)
            ->where('status', 1)
            ->order('sort', 'desc')
            ->select();
    }

    /**
     * 检查类型是否被使用
     */
    public static function checkUsed($typeId)
    {
        return \think\facade\Db::name('declare_subsidy_config')
            ->where('type_id', $typeId)
            ->find();
    }

    /**
     * 获取类型文本
     */
    public function getTypeTextAttr($value, $data)
    {
        $type = $data['type'] ?? $value;
        $map = [
            self::TYPE_SUBSIDY => '申报补贴',
            self::TYPE_GUARD   => '守护'
        ];
        return $map[$type] ?? '未知';
    }
}