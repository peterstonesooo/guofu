<?php
namespace app\admin\controller;

use app\model\Category as CategoryModel;
use think\facade\View;

class CategoryController extends AuthController
{
    /**
     * 分类列表
     */
    public function index()
    {
        $req = request()->param();
        
        $builder = CategoryModel::order('sort', 'desc')->order('id', 'desc');
        
        // 搜索条件
        if (!empty($req['id'])) {
            $builder->where('id', $req['id']);
        }
        if (!empty($req['name'])) {
            $builder->where('name', 'like', '%' . $req['name'] . '%');
        }
        
        $list = $builder->select();
        $teamplateType = config('map.project.teamplate_type');

        foreach($list as $key => &$value) {
            $list[$key]['type_text'] = $teamplateType[$value['type']];
        }
        View::assign('teamplateType', $teamplateType);
        View::assign('req', $req);
        View::assign('list', $list);
        
        return View::fetch();
    }
    
    /**
     * 添加分类页面
     */
    public function add()
    {
        $teamplateType = config('map.project.teamplate_type');
        View::assign('teamplateType', $teamplateType);

        return View::fetch('edit');
    }
    
    /**
     * 编辑分类页面
     */
    public function edit()
    {
        $id = request()->param('id/d');
        $data = CategoryModel::where('id', $id)->find();
        
        if (empty($data)) {
            return $this->error('分类不存在');
        }
        $teamplateType = config('map.project.teamplate_type');
        View::assign('teamplateType', $teamplateType);

        View::assign('data', $data);
        return View::fetch();
    }
    
    /**
     * 保存新增分类
     */
    public function save()
    {
        $data = $this->validate(request(), [
            'name|名称' => 'require|max:50',
            'type|类型' => 'require|in:0,1,2,3,4,5',
            'sort|排序' => 'number',
            'is_selected|是否选中' => 'in:0,1',
            'is_show|是否显示' => 'in:0,1'
        ]);
        
        // 默认值处理
        $data['is_selected'] = isset($data['is_selected']) ? intval($data['is_selected']) : 0;
        $data['is_show'] = isset($data['is_show']) ? intval($data['is_show']) : 1;
        $data['sort'] = isset($data['sort']) ? intval($data['sort']) : 0;
        
        $result = CategoryModel::create($data);
        
        if ($result) {
            return json(['code' => 200, 'msg' => '添加成功']);
        } else {
            return json(['code' => 0, 'msg' => '添加失败']);
        }
    }
    
    /**
     * 更新分类
     */
    public function update()
    {
        $data = $this->validate(request(), [
            'id|ID' => 'require|number',
            'name|名称' => 'require|max:50',
            'type|类型' => 'require|in:0,1,2,3,4,5',
            'sort|排序' => 'number',
            'is_selected|是否选中' => 'in:0,1',
            'is_show|是否显示' => 'in:0,1'
        ]);
        
        // 默认值处理
        $data['is_selected'] = isset($data['is_selected']) ? intval($data['is_selected']) : 0;
        $data['is_show'] = isset($data['is_show']) ? intval($data['is_show']) : 0;
        $data['sort'] = isset($data['sort']) ? intval($data['sort']) : 0;
        
        $category = CategoryModel::find($data['id']);
        if (!$category) {
            return json(['code' => 0, 'msg' => '分类不存在']);
        }
        
        $result = $category->save($data);
        
        if ($result !== false) {
            return json(['code' => 200, 'msg' => '更新成功']);
        } else {
            return json(['code' => 0, 'msg' => '更新失败或无变化']);
        }
    }
    
    /**
     * 更新状态（是否选中或是否显示）
     */
    public function updateStatus()
    {
        $id = request()->param('id/d');
        $field = request()->param('field/s', '');
        $value = request()->param('value');
        
        if (empty($id) || !in_array($field, ['is_selected', 'is_show', 'sort'])) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        $category = CategoryModel::find($id);
        if (!$category) {
            return json(['code' => 0, 'msg' => '分类不存在']);
        }
        
        // 对排序字段进行特殊处理
        if ($field === 'sort') {
            $value = intval($value);
        }
        
        $category->$field = $value;
        $result = $category->save();
        
        if ($result !== false) {
            return json(['code' => 200, 'msg' => '操作成功']);
        } else {
            return json(['code' => 0, 'msg' => '操作失败']);
        }
    }
}
