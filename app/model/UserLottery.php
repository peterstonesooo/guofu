<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\facade\Db;
use think\facade\Log;

/**
 * @mixin \think\Model
 */
class UserLottery extends Model
{
    /**
     * type1:用户抽奖 2:邀请会员实名  3:邀请会员激活 
     */
    public static function lotteryInc($userId,$num,$type,$userLotteryId,$realationId,$logType = 1){
        $userLottery = UserLottery::where('user_id', $userId)->find();

        try{
/*             if(!$userLottery){
                throw new \Exception('用户抽奖次数记录不存在');
            } */
            Db::startTrans();
            if(!$userLottery){
                UserLottery::create(['user_id'=>$userId,'lottery_num'=>0]);
            }
            $lotteryNum = $userLottery['lottery_num'];
            //抽奖次数
            UserLottery::where('user_id', $userId)->update([
                'lottery_num' => Db::raw('lottery_num + ' . $num)
            ]);
            //抽奖次数记录
            $data = [
                'type' => $type,
                'user_id' => $userId,
                'user_lottery_id' => $userLotteryId,
                'relation_id' => $realationId,
                'amount' => 1,
                'amount_before' => $lotteryNum,
                'amount_after' => $lotteryNum + $num,
                'log_type' => $logType,
            ];
            UserLotteryLog::create($data);
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            Log::error('抽奖次数失败:'.$e->getMessage(),['file'=>$e->getFile(),'line'=>$e->getLine()]);
            return false;
        }
    }
}
