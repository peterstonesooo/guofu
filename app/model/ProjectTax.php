<?php

namespace app\model;

use think\Model;

class ProjectTax extends Model
{
    public function getReceiveCardTextAttr($value, $data)
    {
        $a = ProjectCard::where('id', $data['receive_card'])->find();
        return $a['name'] ?? '共富卡';
        
    }
}
