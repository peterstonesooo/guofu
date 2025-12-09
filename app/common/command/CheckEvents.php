<?php

namespace app\common\command;

use app\model\UserPrize;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\User;
use think\facade\Db;

use Exception;
use think\facade\Log;

class CheckEvents extends Command
{
    protected function configure()
    {
        $this->setName('checkEvents')->setDescription('活动奖励运行一次');
    }

    public function execute(Input $input, Output $output)
    {
        $this->settleAccount3();
    }

    public function settleAccount3(){
        $Rewards = [
            1=>5,
            2=>10,
            3=>20,
            4=>200,
            5=>1000,
        ];
        $date = date('Y-m-d 00:00:00');
        echo $date;
        $data = UserPrize::where('status',0)->where('created_at','<',$date)->chunk(100,function($lists) use($Rewards){
            foreach($lists as $item){
                if(isset($Rewards[$item['lottery_id']]) && $Rewards[$item['lottery_id']]>0){
                    Db::startTrans();
                    try{
                        User::changeInc($item['user_id'],$Rewards[$item['lottery_id']],'team_bonus_balance',8,$item['id'],3,'抽奖奖励');
                        UserPrize::where('id',$item['id'])->update(['status'=>1]);
                        Db::commit();
                    }catch(Exception $e){
                        Db::rollback();
                        Log::error('结算异常 id:'.$item['id'].' '.$e->getMessage(),['e'=>$e]);
                        //print_r($e->getMessage());die;
                    }
                }
            }

        });


    }


    /**
     * 结算奖励a
     * 
     */
    public function SettleAccount(){
        $Rewards = [
            '1'=> 88, //福
            '2'=> 108, //禄
            '3'=> 188, //寿
            '4'=> 288, //喜
            '5'=> 588, //财

        ];
        // 福：88元   禄：108元  寿：188元   喜：288元   财：588元

        Db::startTrans();
        try{
        $lists = UserPrize::where('status',0)->select()->toArray();
       

                if(empty($lists)){
                    //throw new Exception('无结算记录！');
                }
                $userTO = [];
                // 处理完更新
                foreach ($lists as $k => $val) {
 /*                    if(isset($Rewards[$val['lottery_id']]) && $Rewards[$val['lottery_id']]>0){
                        User::changeInc($val['user_id'],$Rewards[$val['lottery_id']],'team_bonus_balance',8,$val['id'],3,'抽奖奖励');
                        // UserPrize::where('id',$val['id'])->update(['status'=>1]);
                    } */
                    if(isset($userTO[$val['user_id']][$val['lottery_id']])){
                        $userTO[$val['user_id']][$val['lottery_id']] +=1;
                    }else{
                        $userTO[$val['user_id']][$val['lottery_id']] =1;
                    }
                    $userTo[$val['user_id']]['record'][] = $val['id'];


                }
               

                // 结算集合的
                foreach ($userTO as $k => $v) {
                    $min_count = 0;
                    if(isset($v['1']) && isset($v['2']) && isset($v['3']) && isset($v['4']) && isset($v['5'])) {
                        // 使用 PHP 内置函数找出最小值
                        $min_count = min($v['1'], $v['2'], $v['3'], $v['4'], $v['5']);
                        
                        // 如果有效的最小值存在，发放奖励
                        if($min_count > 0) {
                            //User::changeInc($k, 5888 * $min_count, 'team_bonus_balance', 8, $val['id'], 3, '集齐五福奖励');
                        }
                    }
                }
                // 修改状态
                UserPrize::where('status',0)->update(['status'=>1]);
                
            Db::Commit();
            print_r('结算成功！');die;
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('结1算异常'.$e->getMessage(),$e);
            print_r($e->getMessage());die;
            // throw $e;
        }

    }

    public function SettleAccount2(){
        $Rewards = [
            '1'=> 88, //福
            '2'=> 108, //禄
            '3'=> 188, //寿
            '4'=> 288, //喜
            '5'=> 588, //财

        ];

        $RewardsName = [
            '1'=> '福', //福
            '2'=> '禄', //禄
            '3'=> '寿', //寿
            '4'=> '喜', //喜
            '5'=> '财', //财

        ];



        Db::execute('TRUNCATE mp_user_prize_count;');

        $sql = "INSERT INTO mp_user_prize_count 
                    (user_id, lottery_1, lottery_2, lottery_3, lottery_4, lottery_5, status)
                    SELECT 
                        user_id,
                        IFNULL(MAX(CASE WHEN lottery_id = 1 THEN ct END), 0) as lottery_1,
                        IFNULL(MAX(CASE WHEN lottery_id = 2 THEN ct END), 0) as lottery_2,
                        IFNULL(MAX(CASE WHEN lottery_id = 3 THEN ct END), 0) as lottery_3,
                        IFNULL(MAX(CASE WHEN lottery_id = 4 THEN ct END), 0) as lottery_4,
                        IFNULL(MAX(CASE WHEN lottery_id = 5 THEN ct END), 0) as lottery_5,
                        0 as status
                    FROM (
                        SELECT 
                            user_id,
                            lottery_id,
                            COUNT(*) as ct
                        FROM mp_user_prize 
                        WHERE status = 0
                        GROUP BY user_id, lottery_id
                    ) t
                    GROUP BY user_id		";
        Db::execute($sql);
        Db::startTrans();
        try{
            $lists = Db::name('user_prize_count')->where('status', 0)->where('lottery_1', '>', 0)->where('lottery_2', '>', 0)->where('lottery_3', '>', 0)->where('lottery_4', '>', 0)->where('lottery_5', '>', 0)->select();
            if(empty($lists)){
                //throw new Exception('无结算记录！');
            }
            foreach($lists as $item){
                $minCount = 0;
                $minCount = min($item['lottery_1'], $item['lottery_2'], $item['lottery_3'], $item['lottery_4'], $item['lottery_5']);
                if($minCount > 0){
                    // User::changeInc($item['user_id'], 5888 * $minCount, 'team_bonus_balance', 28, 0, 3, '集齐五福奖励');
                }
                Db::name('user_prize_count')->where('user_id', $item['user_id'])->dec('lottery_1', $minCount)->dec('lottery_2', $minCount)->dec('lottery_3', $minCount)->dec('lottery_4', $minCount)->dec('lottery_5', $minCount)->update();
            }

            $lists = Db::name('user_prize_count')->where('status', 0)->select();
            foreach($lists as $item){
                foreach($item as $k=>$v){
                    if($k == 'id' || $k == 'user_id' || $k == 'status'){
                        continue;
                    }
                    if($v > 0){
                        $key = str_replace('lottery_', '', $k);
                        $name = $RewardsName[$key];
                        // User::changeInc($item['user_id'], $Rewards[$key] * $v, 'team_bonus_balance', 28, $item['id'], 3, '抽奖奖励'.$name.'*'.$v);
                    }
                }
                Db::name('user_prize_count')->where('id', $item['id'])->update(['status'=>1]);
            }
            Db::name('user_prize')->where('status', 0)->update(['status'=>1]);
            Db::commit();
        }catch(Exception $e){
            Db::rollback();
            Log::error('结算异常'.$e->getMessage(),['e'=>$e]);
            print_r($e->getMessage());die;
        // throw $e;
        }
    }
}
