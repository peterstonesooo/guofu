<?php

namespace app\common\command;

use app\model\Capital;
use app\model\User;
use app\model\UserBalanceLog;
use Exception;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class BatchRejectWithdrawals extends Command
{
    // 每批处理记录数
    const BATCH_SIZE = 1000;

    protected function configure()
    {

        //php think batch_reject_withdrawals -u "13800138000,13900139000"
        $this->setName('batch_reject_withdrawals')
            ->setDescription('批量拒绝所有待审核提现单并将金额退回可提余额')
            ->addOption('user', 'u', Option::VALUE_OPTIONAL, '指定用户手机号（多个用逗号分隔）', '');
    }

    protected function execute(Input $input, Output $output)
    {
        $startTime = microtime(true);
        $output->writeln("[" . date('Y-m-d H:i:s') . "] 开始处理提现拒绝操作...");

        // 获取用户选项
        $userOption = $input->getOption('user');
        $specificUsers = [];

        if (!empty($userOption)) {
            $specificUsers = array_map('trim', explode(',', $userOption));
            $output->writeln("指定用户: " . implode(', ', $specificUsers));
        }

        // 构建基础查询
        $baseQuery = Capital::alias('c')
            ->where('c.type', 2)  // 提现类型
            ->where('c.status', 1);  // 待审核状态

        // 处理指定用户
        if (!empty($specificUsers)) {
            $userIds = User::whereIn('phone', $specificUsers)->column('id');
            if (empty($userIds)) {
                $output->writeln("未找到指定用户");
                return;
            }
            $baseQuery->whereIn('c.user_id', $userIds);
        }

        $totalRecords = $baseQuery->count();
        $output->writeln("待处理提现单总数: {$totalRecords}");

        $processedCount = 0;
        $successCount = 0;
        $lastId = 0;

        do {
            // 分批获取待处理提现单
            $query = clone $baseQuery;
            $withdrawals = $query
                ->where('c.id', '>', $lastId)
                ->order('c.id', 'asc')
                ->limit(self::BATCH_SIZE)
                ->select();

            if ($withdrawals->isEmpty()) {
                break;
            }

            foreach ($withdrawals as $withdrawal) {
                $processedCount++;
                $lastId = $withdrawal->id;

                Db::startTrans();
                try {
                    // 1. 获取用户信息
                    $user = User::where('id', $withdrawal->user_id)->lock(true)->find();
                    if (!$user) {
                        throw new Exception("用户不存在");
                    }

                    // 2. 计算退回金额（取绝对值）
                    $amount = abs($withdrawal->amount);

                    // 3. 更新提现单状态为拒绝
                    $updateData = [
                        'status'        => 3,  // 审核拒绝
                        'audit_remark'  => '系统批量拒绝，金额退回可提余额',
                        'audit_time'    => time(),
                        'admin_user_id' => 0,  // 系统操作
                        'updated_at'    => date('Y-m-d H:i:s')
                    ];
                    Capital::where('id', $withdrawal->id)->update($updateData);

                    // 4. 更新用户可提余额
                    $newBalance = bcadd($user->team_bonus_balance, $amount, 2);
                    User::where('id', $user->id)->update([
                        'team_bonus_balance' => $newBalance,
                        'updated_at'         => date('Y-m-d H:i:s')
                    ]);

                    // 5. 记录资金日志
                    $this->createBalanceLog($user, $amount, $withdrawal->id);

                    Db::commit();
                    $successCount++;

                    $output->writeln("用户 {$user->phone} 提现单 {$withdrawal->id} 处理成功");
                } catch (Exception $e) {
                    Db::rollback();
                    $output->writeln("处理失败 [ID: {$withdrawal->id}]: " . $e->getMessage());
                }
            }

            $progress = round(($processedCount / $totalRecords) * 100, 2);
            $output->writeln("处理进度: {$processedCount}/{$totalRecords} ({$progress}%)");

            // 释放内存
            unset($withdrawals);
        } while (true);

        $duration = round(microtime(true) - $startTime, 2);
        $output->writeln("处理完成！成功: {$successCount}条, 失败: " . ($processedCount - $successCount) . "条, 耗时: {$duration}秒");
    }

    /**
     * 创建资金日志记录
     */
    private function createBalanceLog(User $user, $amount, $capitalId)
    {
        UserBalanceLog::create([
            'user_id'        => $user->id,
            'type'           => 13,  // 提现失败类型
            'log_type'       => 1,  // 余额日志
            'relation_id'    => $capitalId,
            'before_balance' => $user->team_bonus_balance,
            'change_balance' => $amount,
            'after_balance'  => bcadd($user->team_bonus_balance, $amount, 2),
            'remark'         => '需申请财政审批',
            'admin_user_id'  => 0,  // 系统操作
            'status'         => 2,  // 成功状态
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
            'order_sn'       => build_order_sn($user->id, 'TXBK')  // 使用系统函数生成订单号
        ]);
    }
}