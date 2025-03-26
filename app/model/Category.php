<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class Category extends Model
{
    public static function getListKv()
    {
        $list = self::select();
        $data = [];
        foreach ($list as $v) {
            $data[$v['id']] = $v['name'];
        }
        return $data;
    }

    public static function getList(){
        $list = self::field('id,name,type,is_selected')->where('is_show',1)->order('sort desc')->order('id desc')->select();
        return $list;
    }
}
