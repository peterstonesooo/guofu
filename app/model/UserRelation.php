<?php

namespace app\model;

use think\Model;

class UserRelation extends Model
{
    public function getLevelTextAttr($value, $data)
    {
        $map = config('map.user_relation')['level_map'];
        return $map[$data['level']];
    }

    public function getIsActiveTextAttr($value, $data)
    {
        $map = config('map.user_relation')['is_active_map'];
        return $map[$data['is_active']];
    }

    public static function saveUserRelation($user_id)
    {
        $upUserIds = User::getThreeUpUserId($user_id);
        foreach ($upUserIds as $k => $v) {
            UserRelation::create([
                'user_id' => $v,
                'sub_user_id' => $user_id,
                'level' => $k
            ]);
        }

        return true;
    }

    public function subUser()
    {
        return $this->belongsTo(User::class, 'sub_user_id')->field('id,realname,phone');
    }

    public static function  rankList($timeStr='today'){
        $timeStr =date('Y-m-d',strtotime($timeStr));
        //$times = strtotime($timeStr);
        //$timee = $times + 60*60*24;
        $reward = config('map.rank_reward');
/*         $relation = UserRelation::alias('r')
        ->field(['count(r.sub_user_id) as team_num', 'r.user_id'])
        ->join('user u', 'u.id = r.sub_user_id')
        ->whereBetween('u.auth_time',[$times,$timee])
        ->where('r.user_id', '<>', '468992')
        ->group('r.user_id')->order('team_num', 'desc')
        ->limit(100)->select()->toArray(); */

        //$relation= "select count(r.sub_user_id) team_count,r.user_id from mp_user_relation r INNER JOIN mp_order o on r.sub_user_id = o.user_id where o.created_at BETWEEN '2024-04-23 00:00:00' and '2024-04-23 23:59:59'  group by r.user_id order by team_count desc limit 100";
        $relation = UserRelation::alias('r')
        ->field(['count(r.sub_user_id) as team_num', 'r.user_id'])
        ->join('mp_order o', 'o.user_id = r.sub_user_id')
        ->whereBetween('o.created_at',["$timeStr 00:00:00","$timeStr 23:59:59"])
        ->where('o.status',2)
        ->where('o.project_group_id',8)
        ->where('r.user_id', '<>', '468992')
        ->group('r.user_id')->order('team_num', 'desc')
        ->limit(100)->select()->toArray(); 
      
        foreach ($relation as $k => &$v) {
            $user = User::where('id',$v['user_id'])->find();
            $v['phone'] = $user['phone'];
            $v['realname'] = $user['realname'];
            $v['phone'] = substr_replace($v['phone'],'****', 3, 4);
            $v['sort'] = $k+1;
            if($k<=10){
                $v['reward'] = $reward[$k+1];
            }else{
                $v['reward'] = 20;
            }
        }
        
        return $relation;

    }
}
