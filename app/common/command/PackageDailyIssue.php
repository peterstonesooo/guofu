<?php

namespace app\common\command;

use app\api\service\StockService;
use app\model\PackagePurchases;
use app\model\StockPackageItems;
use app\model\StockTransactions;
use app\model\StockTypes;
use app\model\User;
use app\model\UserStockDetails;
use app\model\UserStockWallets;
use Exception;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class PackageDailyIssue extends Command
{
    // 配置常量
    const BATCH_SIZE = 500; // 每批处理购买记录数
    const STOCK_CODE = 'MRG001'; // 自由股权代码
    const CIRCULATION_STOCK_CODE = 'LTG001'; // 流通股权代码

    protected function configure()
    {
        //php think packageDailyIssue -u "13800138000,13900139000"
        $this->setName('packageDailyIssue')
            ->setDescription('每日发放自由股权')
            ->addOption('user', 'u', Option::VALUE_OPTIONAL, '指定用户手机号（多个用逗号分隔）', '');
    }

    protected function execute(Input $input, Output $output)
    {
        $startTime = microtime(true);
        $output->writeln("[" . date('Y-m-d H:i:s') . "] 开始处理股权每日发放...");

        // 获取用户选项
        $userOption = $input->getOption('user');
        $specificUsers = [];

        if (!empty($userOption)) {
            $specificUsers = array_map('trim', explode(',', $userOption));
            $output->writeln("指定用户: " . implode(', ', $specificUsers));
        }

        // 获取MRG001股权类型ID
        $stockType = StockTypes::where('code', self::STOCK_CODE)->find();
        if (!$stockType) {
            $output->writeln("错误: 未找到股权代码 " . self::STOCK_CODE);
            return;
        }
        $stockTypeId = $stockType->id;

        $processedCount = 0;

        // 处理逻辑分支
        if (!empty($specificUsers)) {
            $processedCount = $this->processSpecificUsers($specificUsers, $stockTypeId, $output);
        } else {
            $processedCount = $this->processAllPurchases($stockTypeId, $output);
        }

        $duration = round(microtime(true) - $startTime, 2);
        $output->writeln("股权每日发放完成，共处理 {$processedCount} 条购买记录，耗时: {$duration}秒");
    }

    /**
     * 处理所有购买记录
     */
    protected function processAllPurchases($stockTypeId, Output $output): int
    {
        $processedCount = 0;
        $lastId = 0;

        do {
            // 获取需要处理的购买记录
            $purchases = PackagePurchases::where('id', '>', $lastId)
                ->where('status', 1) // 只处理成功购买的记录
                ->with(['package'])
                ->order('id', 'asc')
                ->limit(self::BATCH_SIZE)
                ->select();

            if ($purchases->isEmpty()) {
                break;
            }

            foreach ($purchases as $purchase) {
                // 检查套餐是否包含流通股权(LTG001)
                if (!$this->hasCirculationStock($purchase->package_id)) {
                    $output->writeln("购买记录 {$purchase->id} 的套餐不包含流通股权(LTG001)，跳过");
                    $lastId = $purchase->id;
                    continue;
                }

                $result = $this->processSinglePurchase($purchase, $stockTypeId, $output);
                if ($result) {
                    $processedCount++;
                }
                $lastId = $purchase->id;
            }

            $output->writeln("已处理: {$processedCount} 条购买记录 (最后ID: {$lastId})");

        } while (true);

        return $processedCount;
    }

    /**
     * 处理指定用户的购买记录
     */
    protected function processSpecificUsers(array $phones, $stockTypeId, Output $output): int
    {
        $processedCount = 0;

        // 先获取用户ID
        $userIds = User::whereIn('phone', $phones)->column('id');
        if (empty($userIds)) {
            $output->writeln("未找到指定用户");
            return 0;
        }

        // 获取指定用户的购买记录
        $purchases = PackagePurchases::whereIn('user_id', $userIds)
            ->where('status', 1)
            ->with(['package'])
            ->select();

        if ($purchases->isEmpty()) {
            $output->writeln("未找到指定用户的购买记录");
            return 0;
        }

        foreach ($purchases as $purchase) {
            // 检查套餐是否包含流通股权(LTG001)
            if (!$this->hasCirculationStock($purchase->package_id)) {
                $output->writeln("购买记录 {$purchase->id} 的套餐不包含流通股权(LTG001)，跳过");
                continue;
            }

            $result = $this->processSinglePurchase($purchase, $stockTypeId, $output);
            if ($result) {
                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * 检查套餐是否包含流通股权(LTG001)
     */
    protected function hasCirculationStock($packageId): bool
    {
        return StockPackageItems::where('package_id', $packageId)
                ->where('stock_code', self::CIRCULATION_STOCK_CODE)
                ->count() > 0;
    }

    /**
     * 处理单条购买记录
     */
    protected function processSinglePurchase($purchase, $stockTypeId, Output $output): bool
    {
        // 检查购买记录是否有有效的套餐信息
        if (!$purchase->package) {
            $output->writeln("购买记录 {$purchase->id} 没有找到套餐信息，跳过");
            return false;
        }

        $package = $purchase->package;
        $userId = $purchase->user_id;

        // 计算锁定期结束日期
        $purchaseDate = strtotime($purchase->created_at);
        $lockEndDate = strtotime("+{$package->lock_period} days", $purchaseDate);

        // 如果锁定期已结束，跳过处理
        if (time() > $lockEndDate) {
            return false;
        }

        // 检查今天是否已经发放过
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));

        $alreadyIssuedToday = StockTransactions::where('user_id', $userId)
            ->where('stock_type_id', $stockTypeId)
            ->where('source', $purchase->package_id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', $todayStart))
            ->where('created_at', '<=', date('Y-m-d H:i:s', $todayEnd))
            ->where('remark', 'like', '%套餐每日发放%')
            ->count();

        if ($alreadyIssuedToday > 0) {
            $output->writeln("用户 {$userId} 套餐 {$package->name} 今天已发放过，跳过");
            return false;
        }

        // 计算应发放的数量 = 每日可售量 × 购买次数
        $purchaseCount = PackagePurchases::where('user_id', $userId)
            ->where('package_id', $package->id)
            ->where('status', 1)
            ->count();

        $dailyQuantity = $package->daily_sell_limit * $purchaseCount;

        Db::startTrans();
        try {
            // 查找或创建用户股权钱包
            $wallet = UserStockWallets::where('user_id', $userId)
                ->where('stock_type_id', $stockTypeId)
                ->where('source', 0) // 来源为购买股权
                ->find();

            if (!$wallet) {
                $wallet = new UserStockWallets();
                $wallet->user_id = $userId;
                $wallet->stock_type_id = $stockTypeId;
                $wallet->quantity = $dailyQuantity;
                $wallet->frozen_quantity = 0;
                $wallet->source = 0;
                $wallet->created_at = date('Y-m-d H:i:s');
                $wallet->updated_at = date('Y-m-d H:i:s');
                $wallet->save();
            } else {
                $wallet->quantity += $dailyQuantity;
                $wallet->updated_at = date('Y-m-d H:i:s');
                $wallet->save();
            }

            // 记录股权明细
            $detail = new UserStockDetails();
            $detail->user_id = $userId;
            $detail->stock_type_id = $stockTypeId;
            $detail->package_purchase_id = $purchase->id;
            $detail->quantity = $dailyQuantity;
            $detail->remaining_quantity = $dailyQuantity;
            $detail->lock_period = 0; // 自由股权无锁定期
            $detail->available_at = date('Y-m-d H:i:s');
            $detail->expired_at = null;
            $detail->status = 1; // 有效状态
            $detail->created_at = date('Y-m-d H:i:s');
            $detail->updated_at = date('Y-m-d H:i:s');
            $detail->save();

            // 记录股权交易
            $currentPrice = StockService::getCurrentPrice();
            $transaction = new StockTransactions();
            $transaction->user_id = $userId;
            $transaction->stock_type_id = $stockTypeId;
            $transaction->type = 1; // 买入
            $transaction->source = $purchase->package_id;
            $transaction->quantity = $dailyQuantity;
            $transaction->price = $currentPrice;
            $transaction->amount = $dailyQuantity * $currentPrice;
            $transaction->status = 1; // 成功
            $transaction->remark = "套餐每日发放: {$package->name} × {$purchaseCount}";
            $transaction->created_at = date('Y-m-d H:i:s');
            $transaction->updated_at = date('Y-m-d H:i:s');
            $transaction->save();

            Db::commit();
            $output->writeln("用户 {$userId} 套餐 {$package->name} 今日发放成功: {$dailyQuantity} 股");
            return true;
        } catch (Exception $e) {
            Db::rollback();
            $output->writeln("用户 {$userId} 套餐 {$package->name} 发放失败: " . $e->getMessage());
            return false;
        }
    }
}