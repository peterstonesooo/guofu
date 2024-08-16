<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\AuthOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\ShopOrder;
use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class CheckAuthBonus extends Command
{
    protected function configure()
    {
        $this->setName('checkAuthBonus')->setDescription('认证返还');
    }

    public function execute(Input $input, Output $output)
    {
        $cur_time = date('Y-m-d 00:00:00');
        $data = AuthOrder::where('id', 43848)->where('status',2)->where('created_at', '<=', $cur_time)
         ->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus($item);
            }
        });
    }

    public function bonus($order){
        Db::startTrans();
        try{
            //User::changeInc($order['user_id'],$order['sum_amount'],'digital_yuan_amount',6,$order['id'],3);
            User::changeInc($order['user_id'],$order['single_amount'],'gf_purse',41,$order['id'],9);
            //User::changeInc($order['user_id'],$order['single_gift_digital_yuan'],'digital_yuan_amount',5,$order['id'],3);
            AuthOrder::where('id',$order->id)->update(['status'=>4]);
            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('分红收益异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }

    
}
