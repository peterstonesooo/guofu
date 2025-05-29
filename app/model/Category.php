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
        $list = self::field('id,name,type,is_selected,intro')->where('is_show',1)->order('sort desc')->order('id desc')->select();
        foreach($list as $key=>&$item){
            if(!empty($item['intro']) && trim($item['intro'])!=''){
                $item['intro'] = str_replace(" ", "&nbsp;", $item['intro']);

                // 将换行符替换为 HTML 换行标签
                $item['intro'] = nl2br($item['intro']);
                $item['intro'] = str_replace(["\r", "\n"], "", $item['intro']);

            }
        }
        return $list;
    }
}
