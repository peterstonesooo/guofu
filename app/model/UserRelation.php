<?php

namespace app\model;

use think\Model;
use think\facade\Db;
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

    public static function rankList($timeStr='today') {
        $timeStr = date('Y-m-d', strtotime($timeStr));
        $startTime = $timeStr . ' 00:00:00';
        $endTime = $timeStr . ' 23:59:59';
        $reward = config('map.rank_reward');


        $activeRank = Db::name('active_rank')->order('num','desc')->select();
        $data= [];

        foreach ($activeRank as $k => $v) {
            $user = User::where('phone', trim($v['phone']))->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            $data[] = [
                'phone'=>substr_replace($v['phone'], '****', 3, 4),
                'team_num'=>$v['num'],
                'realname'=>self::maskName($user['realname']),
                'sort'=>$k+1,
                'reward'=>$reward[$k+1]
            ];
        }
        // 需要排除的用户ID列表
        $excludeUsers = [
            2105152,2105228,2105220,2105224,2105232,2105231,2105234,2105218,2105226,2105229,2105227,2105221,2105225,2105223,2105230,2105222
        ];
    
        $relation = UserRelation::alias('r')
            ->field(['count(r.sub_user_id) as team_num', 'r.user_id'])
            ->join('mp_realname n', 'r.sub_user_id = n.user_id')
            ->join('mp_user u', 'u.id = r.user_id')
            ->whereBetween('n.audit_time', [$startTime, $endTime])
            ->whereNotIn('r.user_id', $excludeUsers)
            ->where('u.status', 1)
            ->where('u.is_realname', 1)
            ->group('r.user_id')
            ->order('team_num', 'desc')
            ->limit(5)
            ->select()
            ->toArray();
    
        foreach ($relation as $k => &$v) {

            
            $user = User::where('id', $v['user_id'])->find();


            $data[] = [
                'phone'=>substr_replace($user['phone'], '****', 3, 4),
                'team_num'=>$v['team_num'],
                'realname'=>self::maskName($user['realname']),
                'sort'=>$k+1+5,
                'reward'=>$reward[$k+1+5]
            ];
        }
    
        return $data;
    }

    public static function maskName($realname) {
        if (empty($realname)) {
            return '';
        }
        
        $len = mb_strlen($realname);
        if ($len <= 2) {
            // 两个字及以下的名字，只显示姓和星号
            return mb_substr($realname, 0, 1) . '*';
        }
        
        // 超过两个字的名字，中间用星号替代
        return mb_substr($realname, 0, 1) . '*' . mb_substr($realname, 2);
    }
}
