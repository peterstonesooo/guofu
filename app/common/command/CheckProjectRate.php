<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\Project;
use app\model\ProjectTax;
use app\model\ShopOrder;
use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class CheckProjectRate extends Command
{
    protected function configure()
    {
        $this->setName('checkProjectRate')->setDescription('商城实时进度记录');
    }

    public function execute(Input $input, Output $output)
    {

        $data = Project::whereIn('project_group_id',[7])->where('status', 1)
            ->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus_shop($item);
            }
        });

        // $aaa = ProjectTax::where('status', 1)
        //     ->chunk(100, function($list) {
        //     foreach ($list as $item) {
        //         $this->bonus_tax($item);
        //     }
        // });
    }


    public function bonus_shop($item)
    {
        Db::startTrans();
        try{
            if($item['rate_time']) {
                // $timestampStart = $item['rate_time'];
                // $timestampEnd = time();
                // 计算两个日期之间相隔的天数
                // $daysDiff = floor(($timestampEnd - $timestampStart) / (60 * 60) + 1);
                $rate = floatval(round(($item['virtually_progress'] + $item['realtime_rate']), 2));
                Project::where('id', $item['id'])->update(['realtime_rate' => $rate]);
            } else {
                $timestampStart = strtotime($item['created_at']);
                $timestampEnd = time();
                // 计算两个日期之间相隔的天数
                $daysDiff = floor(($timestampEnd - $timestampStart) / (60 * 60) + 1);
                $rate = floatval(round($daysDiff * $item['virtually_progress'], 2));
                Project::where('id', $item['id'])->update(['realtime_rate' => $rate]);
            }
            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('项目修改实时进度异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }

    public function bonus_tax($item)
    {
        Db::startTrans();
        try{
            if($item['rate_time']) {
                // $timestampStart = $item['rate_time'];
                // $timestampEnd = time();
                // 计算两个日期之间相隔的天数
                // $daysDiff = floor(($timestampEnd - $timestampStart) / (60 * 60) + 1);
                $rate = floatval(round(($item['virtually_progress'] + $item['realtime_rate']), 2));
                ProjectTax::where('id', $item['id'])->update(['realtime_rate' => $rate]);
            } else {
                $timestampStart = strtotime($item['created_at']);
                $timestampEnd = time();
                // 计算两个日期之间相隔的天数
                $daysDiff = floor(($timestampEnd - $timestampStart) / (60 * 60) + 1);
                $rate = floatval(round($daysDiff * $item['virtually_progress'], 2));
                ProjectTax::where('id', $item['id'])->update(['realtime_rate' => $rate]);
            }
            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('项目修改实时进度异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }
}
