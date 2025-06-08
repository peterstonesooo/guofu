<?php
namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use PhpOffice\PhpSpreadsheet\IOFactory;
use app\model\User;
use app\model\UserLottery;

class BatchRechargeProcess extends Command
{
    protected function configure()
    {
        $this->setName('batchRecharge')
            ->setDescription('处理批量入金任务');
    }

    protected function execute(Input $input, Output $output)
    {
        // 获取锁
        $lock = cache('batch_recharge_lock');
        if($lock) {
            $output->writeln("任务正在执行中...");
            return;
        }
        
        // 设置锁,10分钟超时
        cache('batch_recharge_lock', 1, 600);
        
        try {
            // 每次最多处理5个批次
            $batches = Db::name('batch_recharge')
                ->where('status', 0)
                ->limit(1)
                ->order('id asc')
                ->select();
                
            foreach($batches as $batch) {
                // 检查文件是否存在
                $url=public_path().$batch['url'];
                if(!file_exists($url)) {
                    Db::name('batch_recharge')
                        ->where('id', $batch['id'])
                        ->update([
                            'status' => 3,
                            'log' => json_encode(['error' => '文件不存在'])
                        ]);
                    continue;
                }
                
                // 更新为处理中
                Db::name('batch_recharge')
                    ->where('id', $batch['id'])
                    ->update(['status' => 1]);
                    
                try {
                    $spreadsheet = IOFactory::load($url);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();
                    
                    $total = count($rows) - 1;
                    $success = 0;
                    $fail = 0;
                    
                    // 更新总行数
                    Db::name('batch_recharge')
                        ->where('id', $batch['id'])
                        ->update(['rows' => $total]);
                        
                   
                    
                    try {
                        foreach($rows as $index => $row) {
                            if($index == 0) continue;
                            Db::startTrans();
                            try {
                                $phone = trim($row[0]);
                                $type = intval($row[1] ?? 1);
                                $amount = floatval($row[2]);
                                $remark = $row[3] ?? '';
                                
                                //检测$phone是否11位手机号
                                if (!preg_match('/^\d{11}$/', $phone)) {
                                    throw new \Exception('手机号格式错误');
                                }
                                //检测$type是否1,2,4
                                if (!in_array($type, [1, 2, 4,6,7,8,9,10,11,12])) {
                                    throw new \Exception('类型错误: ' . $type);
                                }
                                //检测$amount是否大于0
/*                                 if ($amount <= 0) {
                                    throw new \Exception('金额必须大于0');
                                } */



                                $user = User::where('phone', $phone)->find();
                                if(!$user) {
                                    throw new \Exception('用户不存在');
                                }
                                
                                if($user['status'] != 1) {
                                    throw new \Exception('用户状态已禁用');
                                }
/*                                 if($user['is_realname'] != 1) {
                                    throw new \Exception('用户未实名');
                                } */
                                if(in_array($type,[1,2,4,6,7,8]) == false){
                                    throw new \Exception('类型错误 '.$type);
                                }
                                if($type==6){
                                    
                                    //User::changelottery($user['id'],$amount,1);
                                    UserLottery::lotteryInc($user['id'],$amount,4,0,0,1,'lottery_num',$batch['admin_id']);

                                    $success++;
                                    
                                    // 记录成功日志
                                    Db::name('batch_log')->insert([
                                        'batch_id' => $batch['id'],
                                        'data' => json_encode($row),
                                        'status' => 1,
                                        'log' => json_encode(['status' => 'success'])
                                    ]);
                                    Db::commit();
                                }else{
                                    
                                    $field = $this->getField($type,$amount,$remark);
                                    // 处理入金
                                    User::changeInc(
                                        $user['id'],
                                        $amount,
                                        $field['filed'],
                                        $field['balance_type'],
                                        $batch['id'],
                                        $field['log_type'],
                                        $field['text'],
                                        $batch['admin_id'],
                                    );
                                    
                                    $success++;
                                    
                                    // 记录成功日志
                                    Db::name('batch_log')->insert([
                                        'batch_id' => $batch['id'],
                                        'data' => json_encode($row),
                                        'status' => 1,
                                        'log' => json_encode(['status' => 'success'])
                                    ]);
                                    //一百行更新一次batch_recharge success_rows
                                    if($success % 100 == 0){
                                        Db::name('batch_recharge')
                                            ->where('id', $batch['id'])
                                            ->update(['success_rows' => $success]);
                                    }
                                    Db::commit();
                                }
                            } catch(\Exception $e) {
                                $fail++;
                                Db::rollback();
                                // 记录失败日志
                                Db::name('batch_log')->insert([
                                    'batch_id' => $batch['id'],
                                    'status'=> 2,
                                    'data' => json_encode($row),
                                    'log' => json_encode([
                                        'status' => 'fail',
                                        'message' => $e->getMessage()
                                    ])
                                ]);
                            }
                        }
                        
                        
                        
                        // 更新完成状态
                        Db::name('batch_recharge')
                            ->where('id', $batch['id'])
                            ->update([
                                'status' => 2,
                                'success_rows' => $success,
                                'fail_rows' => $fail
                            ]);
                            
                    } catch(\Exception $e) {
                      
                        throw $e;
                    }
                    
                } catch(\Exception $e) {
                    // 更新失败状态
                    Db::name('batch_recharge')
                        ->where('id', $batch['id'])
                        ->update([
                            'status' => 3,
                            'log' => json_encode(['error' => $e->getMessage()])
                        ]);
                }
            }
            
        } catch(\Exception $e) {
            $output->writeln("Error: " . $e->getMessage());
        } finally {
            // 释放锁
            cache('batch_recharge_lock', null);
        }
        
        $output->writeln("处理完成");
    }
    
    private function getField($type,$amount,$remark='')
    {
        
        $filed = 'balance';
        $log_type = 0;
        $balance_type = 1;
        $text = '余额';
        switch($type) {
            case 1:
                $filed = 'topup_balance';
                $log_type = 1;
                $balance_type = 15;
                break;
            case 2:
                $filed = 'integral';
                $log_type = 4;
                $balance_type = 15;
                $text = '积分';
                break;

            case 4:
                $filed = 'team_bonus_balance';
                $log_type = 3;
                $balance_type = 8;
                $text = '团队奖励';
                break;
            case 7:
                $filed = 'income_balance';
                $log_type = 4;
                $balance_type = 15;
                $text = '民生养老金';
                break;
            case 8:
                $filed = 'large_subsidy';
                $log_type = 7;
                $balance_type = 15;
                $text = '民生补助金';
                break;

        }
        //User::changeBalance($req['user_id'], $req['money'], 15, 0, 1, $req['remark']??'', $adminUser['id']);
        if($amount > 0){
            $r_text = '财务部入金' . $text;
        } else {
            $r_text = '财务部扣款' . $text;
        }
        $text = empty($remark) ? $r_text : $remark;
        return ['filed'=>$filed,'log_type'=>$log_type,'balance_type'=>$balance_type,'text'=>$text];
    }

}