<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\model\LotteryConfig;
use app\model\Order;
use app\model\UserLottery;
use app\model\UserLotteryLog;
use app\model\UserPrize;
use think\Request;
use think\facade\Log;
use think\facade\Db;


class LotteryController extends AuthController
{
    /**
     * 奖项列表
     *
     * @return \think\Response
     */
    public function lotteryList()
    {
        $data = LotteryConfig::select();
        return json(['code' => 200, 'msg' => '', 'data' => $data]);
    }


    /**
     * 用户抽奖次数
     */
    public function lotteryNum(){
        $user = $this->user;
        $userLottery = UserLottery::where('user_id', $user['id'])->find();
        if ($userLottery) {
            $lotteryNum = $userLottery['lottery_num'];
        }else{
            $lotteryNum = 0;
        }
        return json(['code' => 200, 'msg' => '', 'data' => ['lottery_num'=>$lotteryNum]]);
    }

    /**
     * 用户抽奖次数明细
     */
    public function lotteryLog(){
        $user = $this->user;
        $data = UserLotteryLog::where('user_id', $user['id'])->order('id','desc')->pagenate(15)->each(function($item, $key){
            $item['type_text'] = UserLotteryLog::$LogType[$item['type']];
            return $item;
        });
        return json(['code' => 200, 'msg' => '', 'data' => $data]);
        
    }

    /**
     * 用户抽奖.
     *
     * @return \think\Response
     */
    public function lottery()
    {
        $user = $this->user;

        $lotteryNum = 0;
        $userLottery = UserLottery::where('user_id', $user['id'])->find();
        if ($userLottery) {
            $lotteryNum = $userLottery['lottery_num'];
        }
        if($lotteryNum <= 0){
            return json(['code' => 10001, 'msg' => '抽奖次数不足,请邀请用户获取抽奖次数']);
        }

        $order = Order::where('user_id', $user['id'])->where('status',2)->where('project_group_id',2)->find();
        if(!$order){
            return json(['code' => 10001, 'msg' => '请购买就业补助一期后再抽奖']);
        }

        $lotteryConfig = new LotteryConfig();
        
        try{
            $result = $lotteryConfig->lottery();
            Db::startTrans();
            //保存抽奖记录
            $id = UserPrize::insertGetId([
                'user_id' => $user['id'],
                'lottery_id' => $result['id'],
                'name' => $result['name'],
            ]);
            //减少抽奖次数
            UserLottery::lotteryInc($user['id'], -1, 1,$id,$result['id']);
            $returnData = [
                'prize' => $result['id'],
                'lottery_num' => $lotteryNum - 1,
            ];
            if($result['day_num'] > 0){
                //中奖
                //$returnData['prize_name'] = $result['name'];
                $time = $result['day_num']*60*60*24;
                Order::where('user_id',$user['id'])->where('id',$order['id'])->update([
                    'end_time'=>Db::raw('end_time-'.$time),
                    'is_speed_up'=>1,
                ]);
                $data = [
                    'type'=>4,
                    'user_id'=>$user['id'],
                    'user_lottery_id' => $id,
                    'relation_id' => $order['id'],
                    'amount' => $time,
                    'amount_before' => $order['end_time'],
                    'amount_after' => $order['end_time'] - $time,
                    'log_type' => 2,
                    
                ];
                UserLotteryLog::create($data);
            }
            Db::commit();
            return json(['code' => 200, 'msg' => '抽奖成功', 'data' => $returnData]);

        }catch(\Exception $e){
            Db::rollback();
            Log::error('抽奖失败:'.$e->getMessage(), ['file' =>__FILE__, 'line' => $e->getLine()]);
            return json(['code' => 10002, 'msg' => '抽奖失败']);
        }
        

    }


}
