<?php

namespace app\common\command;

use app\model\User;
use app\model\UserBalanceLog;
use Exception;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class TransferTeamBonusToInsurance extends Command
{
    // 性能配置
    const BATCH_SIZE = 500; // 每批处理用户数
    const LOG_TYPE = 1; // 余额日志类型
    const TRANSACTION_TYPE = 97;

    protected function configure()
    {
//        php think transferTeamBonusToInsurance -u "13800138000,13900139000"
        $this->setName('transferTeamBonusToInsurance')
            ->setDescription('将用户可提余额余额转移到未审批金额')
            ->addOption('user', 'u', Option::VALUE_OPTIONAL, '指定用户手机号（多个用逗号分隔）', '');
    }

    protected function execute(Input $input, Output $output)
    {
        $startTime = microtime(true);
        $output->writeln("[" . date('Y-m-d H:i:s') . "] 开始处理可提余额余额转移...");

        // 获取用户选项
        $userOption = $input->getOption('user');
        $specificUsers = [];

        if (!empty($userOption)) {
            $specificUsers = array_map('trim', explode(',', $userOption));
            $output->writeln("指定用户: " . implode(', ', $specificUsers));
        }

        $processedCount = 0;

        // 处理逻辑分支
        if (!empty($specificUsers)) {
            $processedCount = $this->processSpecificUsers($specificUsers, $output);
        } else {
            $processedCount = $this->processAllUsers($output);
        }

        $duration = round(microtime(true) - $startTime, 2);
        $output->writeln("可提余额余额转移完成，共处理 {$processedCount} 个用户，耗时: {$duration}秒");
    }

    /**
     * 处理所有符合条件的用户
     */
    protected function processAllUsers(Output $output): int
    {
        $processedCount = 0;
        $lastId = 0;

        // 获取符合条件的用户总数
        $totalUsers = User::where('team_bonus_balance', '>', 0)->count();
        $output->writeln("需要处理的用户总数: {$totalUsers}");

        if ($totalUsers == 0) {
            return 0;
        }

        do {
            // 使用游标方式确保所有用户都被处理
            $users = User::where('team_bonus_balance', '>', 0)
                ->where('id', '>', $lastId)
                ->field('id, phone, team_bonus_balance, insurance_balance')
                ->order('id', 'asc')
                ->limit(self::BATCH_SIZE)
                ->select();

            if ($users->isEmpty()) {
                break;
            }

            $countInBatch = $this->processUserBatch($users, $output);
            $processedCount += $countInBatch;

            // 更新最后处理的ID
            $lastId = $users->max('id');

            $output->writeln("已处理: {$processedCount}/{$totalUsers} 用户 (最后ID: {$lastId})");

            // 释放内存
            unset($users);

        } while (true);

        return $processedCount;
    }

    /**
     * 处理指定用户
     */
    protected function processSpecificUsers(array $phones, Output $output): int
    {
        $users = User::whereIn('phone', $phones)
            ->field('id, phone, team_bonus_balance, insurance_balance')
            ->select();

        if ($users->isEmpty()) {
            $output->writeln("未找到指定用户");
            return 0;
        }

        return $this->processUserBatch($users, $output);
    }

    /**
     * 批量处理用户
     */
    protected function processUserBatch($users, Output $output): int
    {
        $processedCount = 0;

        foreach ($users as $user) {
            $currentTime = date('Y-m-d H:i:s');

            // 跳过没有可提余额余额的用户
            if ($user->team_bonus_balance <= 0) {
                $output->writeln("用户 {$user->phone} 没有可提余额余额，跳过");
                continue;
            }

            Db::startTrans();
            try {
                $transferAmount = $user->team_bonus_balance;

                // 记录可提余额余额减少日志
                UserBalanceLog::create([
                    'user_id'        => $user->id,
                    'type'           => self::TRANSACTION_TYPE,
                    'log_type'       => self::LOG_TYPE,
                    'relation_id'    => 0,
                    'before_balance' => $user->team_bonus_balance,
                    'change_balance' => -$transferAmount,
                    'after_balance'  => 0,
                    'remark'         => "可提余额余额转移到未审批金额: {$transferAmount}",
                    'admin_user_id'  => 0,
                    'status'         => 2,
                    'created_at'     => $currentTime,
                    'updated_at'     => $currentTime,
                    'order_sn'       => build_order_sn($user->id, 'TTBI'),
                ]);

                // 记录未审批金额增加日志
                UserBalanceLog::create([
                    'user_id'        => $user->id,
                    'type'           => self::TRANSACTION_TYPE,
                    'log_type'       => self::LOG_TYPE,
                    'relation_id'    => 0,
                    'before_balance' => $user->insurance_balance,
                    'change_balance' => $transferAmount,
                    'after_balance'  => bcadd($user->insurance_balance, $transferAmount, 2),
                    'remark'         => "可提余额余额转入: {$transferAmount}",
                    'admin_user_id'  => 0,
                    'status'         => 2,
                    'created_at'     => $currentTime,
                    'updated_at'     => $currentTime,
                    'order_sn'       => build_order_sn($user->id, 'TTBI'),
                ]);

                // 更新用户数据
                User::where('id', $user->id)->update([
                    'team_bonus_balance' => 0,
                    'insurance_balance'  => Db::raw("insurance_balance + {$transferAmount}"),
                    'updated_at'         => $currentTime
                ]);

                Db::commit();
                $output->writeln("用户 {$user->phone} 处理成功: 转移可提余额余额 {$transferAmount} 到未审批金额");
                $processedCount++;
            } catch (Exception $e) {
                Db::rollback();
                $output->writeln("用户 {$user->phone} 处理失败: " . $e->getMessage());
            }
        }

        return $processedCount;
    }
}