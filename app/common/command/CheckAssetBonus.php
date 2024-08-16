<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class CheckAssetBonus extends Command
{
    protected function configure()
    {
        $this->setName('checkAssetBonus')->setDescription('资产72小时恢复，每五分钟执行');
    }

    public function execute(Input $input, Output $output)
    {

       $data = AssetOrder::where('reward_status',0)->where('next_reward_time', '<=', time())
       ->chunk(100, function($list) {
          foreach ($list as $item) {
              $this->bonus_asset_reward($item);
          }
      });
    }


    public function bonus_asset_reward($order)
    {
        Db::startTrans();
        try{
            $user = User::where('id', $order['user_id'])->find();
            if($order['times'] == 2 || $order['times'] == 3) {
                if($order['created_at'] > '2024-01-09 23:26:41'){
                    if($user['digital_yuan_amount'] >= $order['last_time_amount']) {
                        User::where('id', $order['user_id'])->inc('digital_yuan_amount',-$order['last_time_amount'])->update();
                    }
                }
            } 
            //User::changeInc($order['user_id'],$order['balance'],'digital_yuan_amount',27,$order['id'],3);
            User::changeInc($order['user_id'],$order['digital_yuan_amount'],'digital_yuan_amount',27,$order['id'],3,'',0,1,'JJ');
            
            if($user['level'] < $order['level']) {
                User::where('id', $order['user_id'])->update(['level' => $order['level']]);
            }
            //User::changeInc($order['user_id'],$order['poverty_subsidy_amount'],'poverty_subsidy_amount',27,$order['id'],3);
            AssetOrder::where('id', $order->id)->update(['reward_status'=>1]);

            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('资产恢复异常：'.$e->getMessage(),['e'=>json_encode($e)]);
            throw $e;
        }
    }
}
