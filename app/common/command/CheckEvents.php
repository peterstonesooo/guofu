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
        $this->SettleAccount();
    }


    /**
     * 结算奖励
     */
    public function SettleAccount(){
        $Rewards = [
            '1'=> 88, //福
            '2'=> 108, //禄
            '3'=> 188, //寿
            '4'=> 288, //喜
            '5'=> 288, //财

        ];
        // 福：88元   禄：108元  寿：188元   喜：288元   财：588元

        Db::startTrans();
        try{
        $lists = UserPrize::where('status',0)->select()->toArray();
       

                if(empty($lists)){
                    throw new Exception('无结算记录！');
                }
                $userTO = [];
                // 处理完更新
                foreach ($lists as $k => $val) {
                    if(isset($Rewards[$val['lottery_id']]) && $Rewards[$val['lottery_id']]>0){
                        User::changeInc($val['user_id'],$Rewards[$val['lottery_id']],'team_bonus_balance',8,$val['id'],3,'抽奖奖励');
                        // UserPrize::where('id',$val['id'])->update(['status'=>1]);
                    }
                    if(isset($userTO[$val['user_id']][$val['lottery_id']])){
                        $userTO[$val['user_id']][$val['lottery_id']] +=1;
                    }else{
                        $userTO[$val['user_id']][$val['lottery_id']] =1;
                    }

                }
               

                // 结算集合的
                foreach ($userTO as $k => $v) {
                    $max = 0;
                    if(isset($v['1']) && isset($v['2'])  && isset($v['3']) && isset($v['4']) && isset($v['5'])){
                        foreach ($v as $num) {
                            if($max !=0 && $max > $num){
                                $max = $num;
                            }
                        }
                    }
                    // 发送奖励
                    if($max==0){
                        // 跳出当次循环
                        continue;
                    }

                    User::changeInc($k,5888*$max,'team_bonus_balance',8,$val['id'],3,'抽奖');
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
}
