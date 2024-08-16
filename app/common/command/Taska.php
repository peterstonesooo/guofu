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

class Taska extends Command
{
    protected function configure()
    {
        $this->setName('ask')->setDescription('测试脚本');
    }

    public function execute(Input $input, Output $output)
    {
        ini_set ("memory_limit","-1");
        set_time_limit(0);
        $user = User::field('id')->whereIn('phone', ['17863959662','13659279996','17239352352','17574527534','18931175117','18800117801','13203249652','15106212761','17503064471','15020879719','13174927605','13408424849'])->order('id', 'desc')->select();
        foreach ($user as $key => $value) {
            $this->test($value['id']);
        }

        // $user = UserRelation::whereIn('user_id', [876729,876847,876725,876740,877990,898763,876722,672426,751102,754619,610942,950178])->select()->toArray();
        // var_dump($user[0]);
        // exit;
        // $a = 1;
        // foreach ($user as $key => $value) {
        //     echo 1;exit;
        //     $a ++;
        //     var_dump($a);
        //     exit;
        //     $aa = User::where('id', $value['sub_user_id'])->where('realname', '')->where('topup_balance', 0)->find();
        //     if($aa && $aa['status'] != 3) {
        //         var_dump($value['sub_user_id']);
        //         User::where('id', $value['sub_user_id'])->update(['phone' => '8'.$aa['phone'], 'status' => 3]);
        //     }
        // }

        
        
    }

    public function test($user_id)
    {
        $sub = UserRelation::where('user_id',$user_id)->select();
        foreach($sub as $item){
            $aa = User::where('id', $item['sub_user_id'])->where('realname', '')->where('topup_balance', 0)->find();
            if($aa && $aa['status'] != 3) {
                var_dump($item['sub_user_id']);
                User::where('id', $item['sub_user_id'])->update(['phone' => '8'.$aa['phone'], 'status' => 3]);
            }
        }
    }

    


}
