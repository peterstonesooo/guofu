<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class UserPath extends Model
{

    public function updatePath($parent,$userId){
        $data = [];
        $parentPath = UserPath::where('user_id',$parent['id'])->find();
        $data['path'] = $parentPath['path'] . '/' . $parent['id'];
        $data['depth'] = $parentPath['depth'] + 1;
        $data['user_id'] = $userId;
        $data['id'] = $userId;
        $this->insert($data);
        $this->updateCount($data['path'],'team_count');
    }

    public function updateCount($path,$field,$num=1){
        $pids = explode('/',$path);
        $this->whereIn('user_id',$pids)->update([$field=>Db::raw($field.'+'.$num)]);
    }

}
