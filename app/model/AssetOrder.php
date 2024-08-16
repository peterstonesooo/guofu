<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class AssetOrder extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRewardStatusTextAttr($value, $data)
    {
        return $data['reward_status'] == 1 ? '已结算' : '等待结算';
    }

    public function getStatusTextAttr($value, $data)
    {
        return $data['status'] == 2 ? '等待回退' : '已回退';
    }

    public function getEnsureTextAttr($value, $data)
    {
        $a = explode(',', $data['ensure']);
        $text = '';
        foreach ($a as $key => $value) {
            if($value) {
                $text .= config('map.ensure')[$value]['name'].',';
            }
        }
        $text = rtrim($text, ',');
        return $text;
    }

    public function getRichTextAttr($value, $data)
    {
        return $data['rich'] == 1 ? '先富' : '后富';
    }
}
