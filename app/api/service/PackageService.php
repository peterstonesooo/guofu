<?php

namespace app\api\service;

use app\model\PackagePurchases;
use app\model\StockPackageItems;
use app\model\StockPackages;
use app\model\UserStockWallets;
use think\facade\Db;

class PackageService
{
    /**
     * 购买套餐
     * @param int $userId 用户ID
     * @param int $packageId 套餐ID
     * @param int $payType 支付方式 (1=现金, 2=股权)
     * @return bool
     * @throws \Exception
     */
    public static function buyPackage($userId, $packageId, $payType)
    {
        Db::startTrans();
        try {
            // 获取套餐信息
            $package = StockPackages::find($packageId);
            if (!$package || $package->status != 1) {
                throw new \Exception('套餐不存在或已下架');
            }

            // 获取套餐的股权配置项
            $items = StockPackageItems::where('package_id', $packageId)->select();
            if ($items->isEmpty()) {
                throw new \Exception('套餐内容为空');
            }

            // 计算套餐总价
            $totalAmount = $package->price;

            // 根据支付方式扣款
            if ($payType == 1) {
                // 现金支付：扣除用户现金
                $user = Db::name('user')->where('id', $userId)->lock(true)->find();
                if ($user['balance'] < $totalAmount) {
                    throw new \Exception('现金余额不足');
                }
                Db::name('user')->where('id', $userId)->dec('balance', $totalAmount)->update();
            } else if ($payType == 2) {
                // 股权支付：扣除用户股权
                // 这里需要根据业务逻辑实现股权扣款
                // 例如：扣除用户持有的特定股权类型
                // 由于没有具体股权类型，这里使用伪代码表示
                $deducted = self::deductStock($userId, $totalAmount);
                if (!$deducted) {
                    throw new \Exception('股权数量不足');
                }
            }

            // 记录套餐购买
            $purchase = new PackagePurchases();
            $purchase->user_id = $userId;
            $purchase->package_id = $packageId;
            $purchase->amount = $totalAmount;
            $purchase->pay_type = $payType;
            $purchase->status = 1;
            $purchase->created_at = date('Y-m-d H:i:s');
            $purchase->updated_at = date('Y-m-d H:i:s');
            $purchase->save();

            // 分配套餐中的股权到用户钱包
            $purchaseDate = date('Y-m-d H:i:s');
            $unlockDate = $package->lock_period > 0 ?
                date('Y-m-d H:i:s', strtotime("+{$package->lock_period} days")) :
                null;

            foreach ($items as $item) {
                // 查找用户是否已有该类型的股权钱包
                $wallet = UserStockWallets::where('user_id', $userId)
                    ->where('stock_type_id', $item->stock_type_id)
                    ->find();

                if (!$wallet) {
                    $wallet = new UserStockWallets();
                    $wallet->user_id = $userId;
                    $wallet->stock_type_id = $item->stock_type_id;
                    $wallet->quantity = 0;
                    $wallet->source = 2; // 来源为购买套餐
                    $wallet->purchase_date = $purchaseDate;
                    $wallet->lock_period = $package->lock_period;
                    $wallet->unlock_date = $unlockDate;
                    $wallet->created_at = $purchaseDate;
                }

                $wallet->quantity += $item->quantity;
                $wallet->save();

                // 记录股权交易 (来源为购买产品所得)
                Db::name('stock_transactions')->insert([
                    'user_id'       => $userId,
                    'stock_type_id' => $item->stock_type_id,
                    'type'          => 1, // 买入
                    'source'        => 2, // 购买产品所得
                    'quantity'      => $item->quantity,
                    'price'         => 0, // 套餐内的股权没有单独价格
                    'amount'        => 0,
                    'status'        => 1,
                    'remark'        => "购买套餐: {$package->name}",
                    'created_at'    => $purchaseDate,
                    'updated_at'    => $purchaseDate
                ]);
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 扣除用户股权 (示例方法)
     * @param int $userId 用户ID
     * @param float $amount 扣除金额
     * @return bool
     */
    private static function deductStock($userId, $amount)
    {
        // 实际实现需要根据业务逻辑扣除用户持有的股权
        // 这里只是一个示例
        return true;
    }
}