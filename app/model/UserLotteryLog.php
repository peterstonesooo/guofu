<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class UserLotteryLog extends Model
{
    //1.抽奖次数 2.加速使用 3.加速账变
    public static $LogType = [
        1 => '用户抽奖',
        2 => '邀请会员实名',
        3 => '购买产品',
        4 => '使用加速',
        5 => '加速账户改变',
    ];
}
