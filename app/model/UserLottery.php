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
     * type1:用户抽奖 2:邀请会员实名  3:购买产品增加抽奖次数 4.使用加速 5.加速账户改变
     */
    public static function lotteryInc($userId,$num,$type,$userLotteryId,$realationId,$logType = 1,$field='lottery_num'){

        try{
/*             if(!$userLottery){
                throw new \Exception('用户抽奖次数记录不存在');
            } */
            $val =0;
            Db::startTrans();
            $userLottery = UserLottery::where('user_id', $userId)->lock(true)->find();

            if(!$userLottery){
                UserLottery::create(['user_id'=>$userId,'lottery_num'=>0,'speed_up_balance'=>0]);
            }else{
                $val = $userLottery[$field];
            }
            //抽奖次数
            UserLottery::where('user_id', $userId)->update([
                $field => Db::raw($field.' + ' . $num)
            ]);
            //抽奖次数记录
            $data = [
                'type' => $type,
                'user_id' => $userId,
                'user_lottery_id' => $userLotteryId,
                'relation_id' => $realationId,
                'amount' => $num,
                'amount_before' => $val,
                'amount_after' => $val + $num,
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
