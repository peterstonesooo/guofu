<?php

namespace app\api\service;

use app\model\PackagePurchases;
use app\model\StockPackageItems;
use app\model\StockPackages;
use app\model\StockTransactions;
use app\model\User;
use app\model\UserStockDetails;
use app\model\UserStockWallets;
use think\facade\Db;

class PackageService
{
    // 日志类型常量
    const LOG_STOCK_PACKAGE_BUY = 93; // 购买股权方案

    /**
     * 购买股权方案
     * @param int $userId 用户ID
     * @param int $packageId 股权方案ID
     * @param int $payType 支付方式 (1=充值余额, 2=团队奖金余额)
     * @return bool
     * @throws \Exception
     */
    public static function buyPackage($userId, $packageId, $payType)
    {
        Db::startTrans();
        try {
            // 获取股权方案信息
            $package = StockPackages::find($packageId);
            if (!$package || $package->status != 1) {
                throw new \Exception('股权方案不存在或已下架');
            }

            // 获取股权方案的股权配置项
            $items = StockPackageItems::where('package_id', $packageId)->select();
            if ($items->isEmpty()) {
                throw new \Exception('股权方案内容为空');
            }

            // 计算股权方案总价
            $totalAmount = $package->price;
            $remark = "购买股权方案:{$package->name}";

            // 根据支付方式扣款
            if ($payType == 1) {
                // 充值余额支付
                User::changeInc(
                    $userId,
                    -$totalAmount,
                    'topup_balance',
                    self::LOG_STOCK_PACKAGE_BUY,
                    0,
                    1,
                    $remark
                );
            } else if ($payType == 2) {
                // 团队奖金余额支付
                User::changeInc(
                    $userId,
                    -$totalAmount,
                    'team_bonus_balance',
                    self::LOG_STOCK_PACKAGE_BUY,
                    0,
                    1,
                    $remark
                );
            } else {
                throw new \Exception('不支持的支付方式');
            }

            // 记录购买
            $purchase = PackagePurchases::create([
                'user_id'    => $userId,
                'package_id' => $packageId,
                'amount'     => $totalAmount,
                'pay_type'   => $payType,
                'status'     => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 分配股权到用户钱包
            $purchaseDate = date('Y-m-d H:i:s');
            $expireAt = $package->lock_period > 0
                ? date('Y-m-d H:i:s', strtotime("+{$package->lock_period} days"))
                : null;

            foreach ($items as $item) {
                // 查找用户是否已有该类型的股权钱包
                $wallet = UserStockWallets::where('user_id', $userId)
                    ->where('stock_type_id', $item->stock_type_id)
                    ->where('source', $packageId)  // 关键修改：匹配相同来源
                    ->find();

                // 计算每股实际价格
                $stockPrice = StockService::getCurrentPrice();
                $itemAmount = $stockPrice * $item->quantity;

                // 不存在则创建新钱包
                if (!$wallet) {
                    $wallet = new UserStockWallets();
                    $wallet->user_id = $userId;
                    $wallet->stock_type_id = $item->stock_type_id;
                    $wallet->quantity = $item->quantity;
                    $wallet->frozen_quantity = 0;
                    $wallet->source = $packageId;
                    $wallet->save();
                } else {
                    // 存在则累加数量并更新解锁时间
                    $wallet->quantity += $item->quantity;
                    $wallet->save();
                }

                // 记录明细
                UserStockDetails::insert([
                    'user_id'             => $userId,
                    'stock_type_id'       => $item->stock_type_id,
                    'package_purchase_id' => $purchase->id,
                    'quantity'            => $item->quantity,
                    'remaining_quantity'  => $item->quantity,
                    'lock_period'         => $package->lock_period,
                    'available_at'        => $purchaseDate,
                    'expired_at'          => $expireAt,
                    'status'              => 1, // 1=有效
                    'created_at'          => $purchaseDate,
                    'updated_at'          => $purchaseDate
                ]);

                // 记录交易
                $stockPrice = StockService::getCurrentPrice();
                $itemAmount = $stockPrice * $item->quantity;


                // 记录股权交易（关键修改：记录实际价格和金额）
                StockTransactions::insert([
                    'user_id'       => $userId,
                    'stock_type_id' => $item->stock_type_id,
                    'type'          => 1,
                    'source'        => $packageId,
                    'quantity'      => $item->quantity,
                    'price'         => round($stockPrice, 2), // 保留2位小数
                    'amount'        => round($itemAmount, 2), // 保留2位小数
                    'status'        => 1,
                    'remark'        => $remark,
                    'created_at'    => $purchaseDate,
                    'updated_at'    => $purchaseDate
                ]);
                StockService::incrementPurchaseCount($userId, $item->stock_type_id);
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}