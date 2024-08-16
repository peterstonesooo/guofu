<?php

namespace app\common\command;

use app\model\AssetOrder;
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

class CheckBonusReview extends Command
{
    protected function configure()
    {
        $this->setName('checkBonusReview')->setDescription('项目收益');
    }

    public function execute(Input $input, Output $output)
    {

        $data = Order::whereIn('project_group_id',[1])->where('review_status',2)->where('next_bonus_time', '<=', time())
        ->chunk(100, function($list) {
           foreach ($list as $item) {
               $this->bonus($item);
           }
       });
    }

    public function bonus($order){
        Db::startTrans();
        try{
            User::changeInc($order['user_id'],$order['sum_amount'],'digital_yuan_amount',6,$order['id'],3);
            Order::where('id',$order->id)->update(['review_status'=>4]);
            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('分红收益异常11：'.$e->getMessage(),$e);
            throw $e;
        }
    }

}
