<?php

namespace app\common\command;

use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\Realname;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\console\input\Argument;
use Exception;
use think\facade\Log;
use app\model\Capital;
use app\model\MettingLog;

class MettingAudit extends Command
{
    protected function configure()
    {
        $this->setName('meeting_audit')
            ->setDescription('会议审核');
    }

    protected function execute(Input $input, Output $output)
    { 
        $this->realnameAutoAudit();
        MettingLog::where('status',0)->where('end_time','<=',time())->chunk(100, function ($list) {
            foreach($list as $item){
                Db::startTrans();
                try{
                    MettingLog::where('id',$item['id'])->update(['status'=>1]);
                    // User::changeInc($item['user_id'], 100, 'ph_wallet', 30, $item['id'], 9, '会议奖励');
                    Db::commit();
                }catch (Exception $e) {
                    Db::rollback();
                    Log::error('会议自动审核失败:'.$e->getMessage());
                }
            }
        });
        echo ' run success,updated ';
    }

    protected function realnameAutoAudit(): void{
        Realname::where('status', 0)
            ->where('created_at', '>', '2025-06-24')
            ->chunk(100, function ($list) {
                foreach ($list as $item) {
                    Realname::audit($item['id'], 1,0,'');
                }
            });
    }

}