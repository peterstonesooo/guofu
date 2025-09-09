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

class RecoverUserBalances extends Command
{
    // 性能配置
    const BATCH_SIZE = 500; // 每批处理用户数
    const LOG_TYPE = 1; // 余额日志类型
    const TRANSACTION_TYPE = 94; // 回收操作类型

    protected function configure()
    {
        // php think recoverUserBalances -u "13800138000,13900139000"
        $this->setName('recoverUserBalances')
            ->setDescription('回收用户团队奖励余额和大额补助')
            ->addOption('user', 'u', Option::VALUE_OPTIONAL, '指定用户手机号（多个用逗号分隔）', '');
    }

    protected function execute(Input $input, Output $output)
    {
        $startTime = microtime(true);
        $output->writeln("[" . date('Y-m-d H:i:s') . "] 开始处理用户余额回收...");

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
        $output->writeln("用户余额回收完成，共处理 {$processedCount} 个用户，耗时: {$duration}秒");
    }

    /**
     * 处理所有符合条件的用户
     */
    protected function processAllUsers(Output $output): int
    {
        $processedCount = 0;
        $lastId = 0;
        $totalProcessed = 0;

        // 获取符合条件的用户总数
        $totalUsers = User::where('team_bonus_balance', '>', 0)
            ->whereOr('large_subsidy', '>', 0)
            ->count();

        $output->writeln("需要处理的用户总数: {$totalUsers}");

        do {
            // 使用游标方式确保所有用户都被处理
            $users = User::where('team_bonus_balance', '>', 0)
                ->whereOr('large_subsidy', '>', 0)
                ->where('id', '>', $lastId)
                ->field('id, phone, team_bonus_balance, large_subsidy')
                ->order('id', 'asc')
                ->limit(self::BATCH_SIZE)
                ->select();

            if ($users->isEmpty()) {
                break;
            }

            $countInBatch = $this->processUserBatch($users, $output);
            $processedCount += $countInBatch;
            $batchSize = count($users);
            $totalProcessed += $batchSize;

            // 更新最后处理的ID - 计算最大ID
            $lastId = 0;
            foreach ($users as $user) {
                if ($user->id > $lastId) {
                    $lastId = $user->id;
                }
            }

            $output->writeln("处理进度: {$totalProcessed}/{$totalUsers} 用户 (最后ID: {$lastId})");
        } while (true);

        return $processedCount;
    }

    /**
     * 处理指定用户
     */
    protected function processSpecificUsers(array $phones, Output $output): int
    {
        $users = User::whereIn('phone', $phones)
            ->field('id, phone, team_bonus_balance, large_subsidy')
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
            $teamAmount = 0;
            $largeAmount = 0;
            $hasChanges = false;

            Db::startTrans();
            try {
                // 处理团队奖励余额
                if ($user->team_bonus_balance > 0) {
                    $teamAmount = $user->team_bonus_balance;
                    $hasChanges = true;

                    // 创建团队奖励余额回收日志
                    UserBalanceLog::insert([
                        'user_id'        => $user->id,
                        'type'           => self::TRANSACTION_TYPE,
                        'log_type'       => self::LOG_TYPE,
                        'relation_id'    => 0,
                        'before_balance' => $user->team_bonus_balance,
                        'change_balance' => -$teamAmount,
                        'after_balance'  => 0,
                        'remark'         => "回收可提现余额: {$teamAmount}",
                        'admin_user_id'  => 0,
                        'status'         => 2,
                        'created_at'     => $currentTime,
                        'updated_at'     => $currentTime,
                        'order_sn'       => build_order_sn($user['id'], 'HGTB'),
                    ]);
                }

                // 处理大额补助
                if ($user->large_subsidy > 0) {
                    $largeAmount = $user->large_subsidy;
                    $hasChanges = true;

                    // 创建大额补助回收日志
                    UserBalanceLog::insert([
                        'user_id'        => $user->id,
                        'type'           => self::TRANSACTION_TYPE,
                        'log_type'       => self::LOG_TYPE,
                        'relation_id'    => 0,
                        'before_balance' => 0,
                        'change_balance' => -$largeAmount,
                        'after_balance'  => 0,
                        'remark'         => "回收待核准余额: {$largeAmount}",
                        'admin_user_id'  => 0,
                        'status'         => 2,
                        'created_at'     => $currentTime,
                        'updated_at'     => $currentTime,
                        'order_sn'       => build_order_sn($user['id'], 'HGHZ'), // 确保与团队奖励日志不同
                    ]);
                }

                if (!$hasChanges) {
                    $output->writeln("用户 {$user->phone} 没有可回收的余额，跳过");
                    Db::rollback();
                    continue;
                }

                // 更新用户数据
                $updateData = ['updated_at' => $currentTime];

                if ($teamAmount > 0) {
                    $updateData['team_bonus_balance'] = 0;
                }

                if ($largeAmount > 0) {
                    $updateData['large_subsidy'] = 0;
                }

                User::where('id', $user->id)->update($updateData);
                Db::commit();

                // 记录处理成功信息
                $messages = [];
                if ($teamAmount > 0)
                    $messages[] = "可提现余额: {$teamAmount}";
                if ($largeAmount > 0)
                    $messages[] = "待核准余额: {$largeAmount}";
                $output->writeln("用户 {$user->phone} 处理成功: " . implode(', ', $messages));

                $processedCount++;
            } catch (Exception $e) {
                Db::rollback();
                $output->writeln("用户 {$user->phone} 处理失败: " . $e->getMessage());
            }
        }

        return $processedCount;
    }
}