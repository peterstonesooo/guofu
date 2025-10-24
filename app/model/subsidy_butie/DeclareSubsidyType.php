<?php

namespace app\model\subsidy_butie;

use think\Model;

class DeclareSubsidyType extends Model
{

    protected $autoWriteTimestamp = false;

    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 字段映射
    protected $type = [
        'id'          => 'integer',
        'name'        => 'string',
        'code'        => 'string',
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
            return env('app.img_host') . '/storage/' . $data['imgurl'];
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

        return $query->paginate(['query' => $params]);
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
}