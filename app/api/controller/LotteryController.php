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
    public function lotteryConfigList()
    {
        $data = LotteryConfig::field('id,name')->select();
        return json(['code' => 200, 'msg' => '', 'data' => $data]);
    }


    /**
     * 用户抽奖次数
     */
    public function lotteryNum(){
        $user = $this->user;
        $userLottery = UserLottery::where('user_id', $user['id'])->find();
        $count = [];
        if ($userLottery) {
            $lotteryNum = $userLottery['lottery_num'];
            $speedUpBalance = $userLottery['speed_up_balance'];
            $count = UserPrize::where('user_id', $user['id'])->field('lottery_id,name,count(lottery_id) ct')->group('lottery_id,name')->select();

        }else{
            $lotteryNum = 0;
            $speedUpBalance = 0;
        }
        return json(['code' => 200, 'msg' => '', 'data' => ['lottery_num'=>$lotteryNum,'speed_up_balance'=>$speedUpBalance,'count'=>$count]]);
    }

    /**
     * 用户抽奖次数明细
     * 1.抽奖次数 2.加速使用 3.加速账变
     */
    public function lotteryLog(){
        $logType = input('log_type',1,'intval');
        $user = $this->user;
        $data = UserLotteryLog::where('user_id', $user['id'])->where('log_type',$logType)->order('id','desc')->paginate(15)->each(function($item, $key){
            $item['type_text'] = UserLotteryLog::$LogType[$item['type']];
            return $item;
        });
        return json(['code' => 200, 'msg' => '', 'data' => $data]);
        
    }

    /**
     * 用户奖品
     */
    public function prizeList(){
        $user = $this->user;
        //$data = UserPrize::where('user_id', $user['id'])->order('id','desc')->paginate(15);
        $userPrize = UserPrize::where('user_id', $user['id'])->where('status',0)->field('lottery_id,name,count(lottery_id) ct')->group('lottery_id,name')->select();

        return json(['code' => 200, 'msg' => '', 'data' => $userPrize]);
    }


    /**
     * 用户抽奖.
     *
     * @return \think\Response
     */
    public function lottery()
    {
        //return json(['code' => 10001, 'msg' => '抽奖功能正在维护']);

        $user = $this->user;

        $lotteryNum = 0;
        $userLottery = UserLottery::where('user_id', $user['id'])->find();
        if ($userLottery) {
            $lotteryNum = $userLottery['lottery_num'];
        }
  
        if($lotteryNum <= 0){
            return json(['code' => 10001, 'msg' => '抽奖次数不足,购买产品获取抽奖次数']);
        }

        $today = date('Y-m-d');

        $lotteryConfig = new LotteryConfig();
        
        try{
            Db::startTrans();

            $result = $lotteryConfig->lottery();

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
                'speed_up_balance' => $userLottery['speed_up_balance'],
            ];

            Db::commit();
            $userPrize = UserPrize::where('user_id', $user['id'])->field('lottery_id,name,count(lottery_id) ct')->group('lottery_id,name')->select();
            $returnData['count'] = $userPrize;

            return json(['code' => 200, 'msg' => '抽奖成功', 'data' => $returnData]);

        }catch(\Exception $e){
            Db::rollback();
            Log::error('抽奖失败:'.$e->getMessage(), ['file' =>__FILE__, 'line' => $e->getLine()]);
            return json(['code' => 10002, 'msg' => '抽奖失败']);
        }
        

    }

    /**
     * 用户加速
     */
/*     public function speedUp(){
        $req = $this->validate(request(), [
            'day_num' => 'require|number',
            'order_id' => 'require|number',
        ]);
        $user = $this->user;
        $lottery = UserLottery::where('user_id', $user['id'])->find();
        $speedUpBalance = 0;
        if($lottery){
            $speedUpBalance = $lottery['speed_up_balance'];
        }
        if($speedUpBalance <= 24){
            return json(['code' => 10001, 'msg' => '加速时间不足一天']);
        }
        $order = Order::where('user_id', $user['id'])->where('status',2)->where('project_group_id',2)->where('id',$req['order_id'])->find();
        if(!$order){
            return json(['code' => 10001, 'msg' => '请购买就业补助一期后再加速']);
        }
        $today = date('Y-m-d');

        $time = $req['day_num']*60*60*24;
        $hour = $req['day_num']*24;
        if($hour > $speedUpBalance){
            return json(['code' => 10001, 'msg' => '加速时间不足']);
        }
        Order::where('user_id',$user['id'])->where('id',$order['id'])->update([
            'end_time'=>Db::raw('end_time-'.$time),
            'is_speed_up'=>1,
        ]);
        UserLottery::lotteryInc($user['id'],-$hour,4,0,$order['id'],3,'speed_up_balance');
        return json(['code' => 200, 'msg' => '加速成功', 'data' => ['speed_up_balance'=>$speedUpBalance - $hour,'order_id'=>$order['id'],'end_time'=>date('Y-m-d H:i:s',$order['end_time'] - $time)]]);
    } */


}
