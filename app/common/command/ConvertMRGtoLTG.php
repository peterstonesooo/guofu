<?php

namespace app\common\command;

use app\model\User;
use Exception;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class ConvertMRGtoLTG extends Command
{
    // 配置常量
    const BATCH_SIZE = 500; // 每批处理数量

    protected function configure()
    {
        $this->setName('convertMRGtoLTG')
            ->setDescription('将用户MRG001股权转换为LTG001股权')
            ->addOption('user', 'u', Option::VALUE_OPTIONAL, '指定用户手机号（多个用逗号分隔）', '');
    }

    protected function execute(Input $input, Output $output)
    {
        $startTime = microtime(true);
        $output->writeln("[" . date('Y-m-d H:i:s') . "] 开始处理MRG001股权转换...");

        try {
            // 获取股权类型ID
            $mrgType = Db::name('stock_types')->where('code', 'MRG001')->find();
            $ltgType = Db::name('stock_types')->where('code', 'LTG001')->find();

            if (!$mrgType || !$ltgType) {
                throw new Exception('股权类型未配置，请确认MRG001和LTG001已存在');
            }

            $mrgTypeId = $mrgType['id'];
            $ltgTypeId = $ltgType['id'];

            $output->writeln("MRG001 ID: {$mrgTypeId}, LTG001 ID: {$ltgTypeId}");

            // 获取用户选项
            $userOption = $input->getOption('user');
            $specificUsers = [];

            if (!empty($userOption)) {
                $specificUsers = array_map('trim', explode(',', $userOption));
                $output->writeln("指定用户: " . implode(', ', $specificUsers));
            }

            // 处理逻辑分支
            if (!empty($specificUsers)) {
                $processedCount = $this->processSpecificUsers($specificUsers, $mrgTypeId, $ltgTypeId, $output);
            } else {
                $processedCount = $this->processAllUsers($mrgTypeId, $ltgTypeId, $output);
            }

            $duration = round(microtime(true) - $startTime, 2);
            $output->writeln("转换完成，共处理: {$processedCount} 个用户，耗时: {$duration}秒");

        } catch (Exception $e) {
            $output->writeln("处理失败: " . $e->getMessage());
        }
    }

    /**
     * 处理所有用户
     */
    protected function processAllUsers($mrgTypeId, $ltgTypeId, Output $output): int
    {
        $processedCount = 0;
        $lastId = 0;

        // 获取需要处理的用户总数
        $totalUsers = Db::name('user_stock_wallets')
            ->where('stock_type_id', $mrgTypeId)
            ->group('user_id')
            ->count();

        $output->writeln("需要处理的用户总数: {$totalUsers}");

        if ($totalUsers == 0) {
            return 0;
        }

        do {
            // 获取需要处理的用户ID
            $userIds = Db::name('user_stock_wallets')
                ->where('stock_type_id', $mrgTypeId)
                ->where('user_id', '>', $lastId)
                ->group('user_id')
                ->order('user_id', 'asc')
                ->limit(self::BATCH_SIZE)
                ->column('user_id');

            if (empty($userIds)) {
                break;
            }

            foreach ($userIds as $userId) {
                $success = $this->processSingleUser($userId, $mrgTypeId, $ltgTypeId, $output);
                if ($success) {
                    $processedCount++;
                }
            }

            // 更新最后处理的用户ID
            $lastId = max($userIds);
            $output->writeln("已处理用户: {$processedCount}/{$totalUsers} (最后用户ID: {$lastId})");

        } while (true);

        return $processedCount;
    }

    /**
     * 处理指定用户
     */
    protected function processSpecificUsers(array $phones, $mrgTypeId, $ltgTypeId, Output $output): int
    {
        $processedCount = 0;

        // 获取用户ID
        $users = User::whereIn('phone', $phones)
            ->field('id, phone, team_bonus_balance, insurance_balance')
            ->select();

        if ($users->isEmpty()) {
            $output->writeln("未找到指定用户");
            return 0;
        }

        foreach ($users as $user) {
            // 检查用户是否有MRG001股权
            $hasMRG = Db::name('user_stock_wallets')
                ->where('user_id', $user['id'])
                ->where('stock_type_id', $mrgTypeId)
                ->count();

            if (!$hasMRG) {
                $output->writeln("用户 {$user['phone']} 没有MRG001股权，跳过");
                continue;
            }

            $success = $this->processSingleUser($user['id'], $mrgTypeId, $ltgTypeId, $output);
            if ($success) {
                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * 处理单个用户的股权转换
     */
    protected function processSingleUser($userId, $mrgTypeId, $ltgTypeId, Output $output): bool
    {
        Db::startTrans();
        try {
            $userInfo = Db::name('user')->where('id', $userId)->field('phone')->find();
            $phone = $userInfo['phone'] ?? $userId;

            $output->writeln("开始处理用户 {$phone} 的股权转换...");

            // 1. 处理股权钱包表
            $wallets = Db::name('user_stock_wallets')
                ->where('user_id', $userId)
                ->where('stock_type_id', $mrgTypeId)
                ->select();

            foreach ($wallets as $wallet) {
                // 检查是否已存在相同来源的LTG001记录
                $existingLtgWallet = Db::name('user_stock_wallets')
                    ->where('user_id', $userId)
                    ->where('stock_type_id', $ltgTypeId)
                    ->where('source', $wallet['source'])
                    ->find();

                if ($existingLtgWallet) {
                    // 合并数量到现有记录
                    Db::name('user_stock_wallets')
                        ->where('id', $existingLtgWallet['id'])
                        ->update([
                            'quantity'        => Db::raw("quantity + {$wallet['quantity']}"),
                            'frozen_quantity' => Db::raw("frozen_quantity + {$wallet['frozen_quantity']}"),
                            'updated_at'      => date('Y-m-d H:i:s')
                        ]);

                    // 删除原MRG001记录
                    Db::name('user_stock_wallets')->where('id', $wallet['id'])->delete();

                    $output->writeln("用户 {$phone} 来源 {$wallet['source']}: 合并到现有LTG001钱包");
                } else {
                    // 直接更新股权类型
                    Db::name('user_stock_wallets')
                        ->where('id', $wallet['id'])
                        ->update([
                            'stock_type_id' => $ltgTypeId,
                            'updated_at'    => date('Y-m-d H:i:s')
                        ]);

                    $output->writeln("用户 {$phone} 来源 {$wallet['source']}: 直接转换为LTG001");
                }
            }

            // 2. 处理股权明细表
            $detailCount = Db::name('user_stock_details')
                ->where('user_id', $userId)
                ->where('stock_type_id', $mrgTypeId)
                ->count();

            if ($detailCount > 0) {
                Db::name('user_stock_details')
                    ->where('user_id', $userId)
                    ->where('stock_type_id', $mrgTypeId)
                    ->update([
                        'stock_type_id' => $ltgTypeId,
                        'updated_at'    => date('Y-m-d H:i:s')
                    ]);

                $output->writeln("用户 {$phone}: 更新 {$detailCount} 条明细记录");
            }

            // 3. 处理交易记录表
            $transactionCount = Db::name('stock_transactions')
                ->where('user_id', $userId)
                ->where('stock_type_id', $mrgTypeId)
                ->count();

            if ($transactionCount > 0) {
                Db::name('stock_transactions')
                    ->where('user_id', $userId)
                    ->where('stock_type_id', $mrgTypeId)
                    ->update([
                        'stock_type_id' => $ltgTypeId,
                        'updated_at'    => date('Y-m-d H:i:s')
                    ]);

                $output->writeln("用户 {$phone}: 更新 {$transactionCount} 条交易记录");
            }

            Db::commit();
            $output->writeln("用户 {$phone} 处理成功");
            return true;

        } catch (Exception $e) {
            Db::rollback();
            $output->writeln("用户 {$phone} 处理失败: " . $e->getMessage());
            return false;
        }
    }
}