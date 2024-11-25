<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class UserLotteryLog extends Model
{
    //
    public static $LogType = [
        1 => '用户抽奖',
        2 => '邀请会员实名',
        3 => '邀请会员激活',
    ];
}
