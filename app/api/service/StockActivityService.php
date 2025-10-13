<?php

namespace app\api\service;

use app\model\StockActivityClaims;
use app\model\StockTransactions;
use app\model\UserStockWallets;
use think\db\exception\DbException;
use think\facade\Db;
use think\facade\Log;

class StockActivityService
{
    /**
     * 领取活动股权
     */
    public static function claimActivityStock($userId, $phone, $activityName, $ltgQuantity, $ysgQuantity)
    {
        // 在事务开始前再次检查是否已领取
        $existingClaim = StockActivityClaims::where('user_id', $userId)
            ->where('activity_name', $activityName)
            ->find();

        if ($existingClaim) {
            throw new \Exception('您已领取过该活动股权');
        }

        Db::startTrans();
        try {
            // 获取股权类型ID
            $ltgType = Db::name('stock_types')->where('code', 'LTG001')->find();
            $ysgType = Db::name('stock_types')->where('code', 'YSG001')->find();

            if (!$ltgType || !$ysgType) {
                throw new \Exception('股权类型未配置');
            }

            $currentTime = date('Y-m-d H:i:s');
            $currentDate = date('Y-m-d');

            // 记录领取
            $claim = StockActivityClaims::create([
                'user_id'           => $userId,
                'phone'             => $phone,
                'activity_name'     => $activityName,
                'stock_type_id_ltg' => $ltgType['id'],
                'stock_type_id_ysg' => $ysgType['id'],
                'ltg_quantity'      => $ltgQuantity,
                'ysg_quantity'      => $ysgQuantity,
                'status'            => 1, // 已发放
                'claim_date'        => $currentDate,
                'created_at'        => $currentTime,
                'updated_at'        => $currentTime
            ]);

            // 更新用户股权钱包
            self::updateUserStockWallet($userId, $ltgType['id'], $ltgQuantity, 0, $currentTime);
            self::updateUserStockWallet($userId, $ysgType['id'], $ysgQuantity, 0, $currentTime);

            $currentPrice = StockService::getCurrentPrice();

            // 记录股权交易（活动类型）
            self::recordStockTransaction(
                $userId,
                $ltgType['id'],
                3, // 活动类型
                0, // 来源为活动
                $ltgQuantity,
                $currentPrice,
                $ltgQuantity * $currentPrice,
                '国庆新人福利赠送流通股权',
                $currentTime
            );

            self::recordStockTransaction(
                $userId,
                $ysgType['id'],
                3, // 活动类型
                0, // 来源为活动
                $ysgQuantity,
                $currentPrice,
                $ysgQuantity * $currentPrice,
                '国庆新人福利赠送原始股权',
                $currentTime
            );

            Db::commit();
            return true;
        } catch (DbException $e) {
            Db::rollback();
            // 如果是唯一约束违反，抛出特定异常
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new \Exception('您已领取过该活动股权');
            }
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 更新用户股权钱包
     */
    private static function updateUserStockWallet($userId, $stockTypeId, $quantity, $source, $timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = date('Y-m-d H:i:s');
        }

        $wallet = UserStockWallets::where('user_id', $userId)
            ->where('stock_type_id', $stockTypeId)
            ->where('source', $source)
            ->findOrEmpty();

        if ($wallet->isEmpty()) {
            $wallet = UserStockWallets::create([
                'user_id'         => $userId,
                'stock_type_id'   => $stockTypeId,
                'quantity'        => $quantity,
                'frozen_quantity' => 0,
                'source'          => $source,
                'created_at'      => $timestamp,
                'updated_at'      => $timestamp
            ]);
        } else {
            $wallet->quantity += $quantity;
            $wallet->updated_at = $timestamp;
            $wallet->save();
        }
    }

    /**
     * 记录股权交易
     */
    private static function recordStockTransaction($userId, $stockTypeId, $type, $source, $quantity, $price, $amount, $remark, $timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = date('Y-m-d H:i:s');
        }

        StockTransactions::create([
            'user_id'       => $userId,
            'stock_type_id' => $stockTypeId,
            'type'          => $type,
            'source'        => $source,
            'quantity'      => $quantity,
            'price'         => $price,
            'amount'        => $amount,
            'status'        => 1,
            'remark'        => $remark,
            'created_at'    => $timestamp,
            'updated_at'    => $timestamp
        ]);
    }

    /**
     * 每日发放自由股权
     * @param string|null $date 指定发放日期，格式为'Y-m-d'，默认为今天
     */
    public static function dailyFreeStockDistribution($date = null)
    {
        // 如果没有指定日期，默认使用今天作为发放日期
        if ($date === null) {
            $date = date('Y-m-d');
            $distributionTime = null;

        } else {
            // 为补发操作设置时间戳（指定日期的凌晨）
            $distributionTime = $date . ' 00:00:00';
//            $isToday = ($date === date('Y-m-d'));
//            if ($isToday) {
//                $distributionTime = date('Y-m-d H:i:s'); // 使用当前时间
//            } else {
//                $distributionTime = date('Y-m-d', strtotime($distributionTime . ' +1 day'));
//            }
        }

        // 验证日期格式
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \Exception('日期格式不正确，应为YYYY-MM-DD格式');
        }

        // 计算对应的领取日期（发放日期的前一天）
        $claimDate = date('Y-m-d', strtotime($date . ' -1 day'));

        // 获取前一天领取活动的所有用户
        $claims = StockActivityClaims::where('status', 1)
            ->select();

        if ($claims->isEmpty()) {
            Log::info("{$date} 发放自由股权：没有找到 {$claimDate} 的领取记录");
            return true;
        }

        // 获取自由股权类型
        $mrgType = Db::name('stock_types')->where('code', 'MRG001')->find();
        if (!$mrgType) {
            throw new \Exception('自由股权类型未配置');
        }

        $quantity = 1;
        $successCount = 0;

        foreach ($claims as $claim) {
            // 检查发放日期是否晚于或等于用户领取日期
            $claimDate = $claim->claim_date;
            if (strtotime($date) < strtotime($claimDate)) {
                Log::info("跳过用户 {$claim->user_id}：发放日期 {$date} 早于领取日期 {$claimDate}");
                continue;
            }

            Db::startTrans();
            try {
                $userId = $claim->user_id;

                // 检查该用户是否已经在指定发放日期发放过自由股权
                $existingFreeStock = StockTransactions::where('user_id', $userId)
                    ->where('stock_type_id', $mrgType['id'])
                    ->where('type', 3)
                    ->where('remark', 'like', '%新人福利赠送原始股权%')
                    ->where('created_at', '>=', $date . ' 00:00:00')
                    ->where('created_at', '<=', $date . ' 23:59:59')
                    ->find();

                if ($existingFreeStock) {
                    Log::info("用户 {$userId} 在 {$date} 已经领取过自由股权，跳过");
                    Db::commit();
                    continue;
                }

                // 更新用户股权钱包（使用指定日期的时间戳）
                self::updateUserStockWallet($userId, $mrgType['id'], $quantity, 0, $distributionTime);
                $currentPrice = StockService::getCurrentPrice();

                // 记录股权交易（使用指定日期的时间戳）
                self::recordStockTransaction(
                    $userId,
                    $mrgType['id'],
                    3, // 每日发放类型
                    0, // 来源为活动
                    $quantity,
                    $currentPrice,
                    $quantity * $currentPrice,
                    "国庆新人福利赠送原始股权",
                    $distributionTime
                );

                Db::commit();
                Log::info("成功为用户 {$userId} 发放 {$date} 的自由股权（基于 {$claimDate} 的领取）");
                $successCount++;

            } catch (\Exception $e) {
                Db::rollback();
                Log::error("为用户 {$claim->user_id} 发放 {$date} 自由股权失败: " . $e->getMessage());
            }
        }

        Log::info("{$date} 自由股权发放完成：成功 {$successCount} 个用户，基于 {$claimDate} 的领取记录");
        return true;
    }
}