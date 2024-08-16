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

class CheckShopBonus extends Command
{
    protected function configure()
    {
        $this->setName('checkShopBonus')->setDescription('流转商城，每五分钟执行');
    }

    public function execute(Input $input, Output $output)
    {

        $data = ShopOrder::where('status',2)->where('next_bonus_time', '<=', time())
            ->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus_shop($item);
            }
        });
    }


    public function bonus_shop($order)
    {
        Db::startTrans();
        try{
            
            // if($order['gain_bonus'] > 0) {
            //     User::changeInc($order['user_id'],$order['shop_profit'],'digit_balance',32,$order['id'],6);
            // } else {
                
            // }
            User::changeInc($order['user_id'],$order['shop_profit'],'digit_balance',32,$order['id'],6);
            User::changeInc($order['user_id'],$order['flow_amount'],'digit_balance',31,$order['id'],6);
            
            // $nextMonthTenth = strtotime('+1 month', strtotime(date("Y-m").'-10'));
            $gain_bonus = $order['gain_bonus'] + $order['shop_profit'];
            ShopOrder::where('id',$order['id'])->update(['status'=>4, 'gain_bonus' => $gain_bonus]);
            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('商城流转异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }
}
