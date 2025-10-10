<?php

namespace app\api\service;

use app\model\User;
use think\Exception;
use think\facade\Db;

class StockProductService
{
    /**
     * 购买产品
     * @param int $user_id 用户ID
     * @param int $product_id 产品ID
     * @param int $quantity 购买数量
     * @param int $pay_type 支付方式 (1=充值余额, 2=团队奖金余额)
     * @return bool
     * @throws \Exception
     */
    public static function buyProduct($user_id, $product_id, $quantity, $pay_type)
    {
        Db::startTrans();
        try {
            // 获取产品信息
            $product = Db::table('mp_stock_product')
                ->where('id', $product_id)
                ->where('status', 1)
                ->find();

            if (!$product) {
                throw new Exception('产品不存在或已下架');
            }

            // 计算总金额
            $amount = bcmul($product['price'], $quantity, 2);

            // 根据支付类型选择余额字段
            $balanceField = ($pay_type == 1) ? 'topup_balance' : 'team_bonus_balance';

            // 记录购买记录
            $purchaseId = Db::table('mp_stock_product_purchases')->insertGetId([
                'user_id'    => $user_id,
                'product_id' => $product_id,
                'quantity'   => $quantity,
                'price'      => $product['price'],
                'amount'     => $amount,
                'pay_type'   => $pay_type,
                'status'     => 1,
                'remark'     => "购买{$product['title']} x{$quantity}",
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if (!$purchaseId) {
                throw new Exception('记录购买记录失败');
            }

            // 扣减余额
            User::changeInc($user_id, -$amount, $balanceField, 98, $purchaseId, 1, "购买产品:{$product['title']} x{$quantity}");


            Db::commit();
            return true;

        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}