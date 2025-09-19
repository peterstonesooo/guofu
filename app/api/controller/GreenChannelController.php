<?php

namespace app\api\controller;

use app\model\FinanceApprovalApply;
use app\model\GreenChannelOrder;
use app\model\GreenConfig;
use app\model\User;
use think\facade\Db;

class GreenChannelController extends AuthController
{
    const MIN_QUEUE_CODE = 6000; // 最低排队编号

    /**
     * 获取可用的绿色通道方案
     */
    public function getConfigs()
    {
        try {
            $configs = GreenConfig::where('status', 1)
                ->field('id, name, priority_queue, channel_fee')
                ->order('sort', 'asc')
                ->select()
                ->toArray();

            return out(['configs' => $configs]);
        } catch (\Exception $e) {
            return out(null, 500, '获取配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 购买绿色通道
     */
    public function purchase()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'approve_id'   => 'require|number',
            'config_id'    => 'require|number',
            'pay_password' => 'require'
        ]);

        // 支付密码验证
        if (empty($user['pay_password'])) {
            return out(null, 10010, '请先设置支付密码');
        }
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10011, '支付密码错误');
        }

        // 获取申请记录
        $apply = FinanceApprovalApply::where('id', $req['approve_id'])
            ->where('user_id', $user['id'])
            ->find();

        if (!$apply) {
            return out(null, 10002, '申请记录不存在');
        }

        // 检查申请状态是否为待审核
        if ($apply->status == FinanceApprovalApply::STATUS_APPROVED) {
            return out(null, 10003, '只有待审核的申请可以购买绿色通道');
        }

        // 获取配置
        $config = GreenConfig::find($req['config_id']);
        if (!$config || $config['status'] != 1) {
            return out(null, 10001, '绿色通道方案不存在或已禁用');
        }

        // 检查余额是否足够支付通道费
        if ($user['topup_balance'] < $config['channel_fee']) {
            return out(null, 10012, '余额不足，请充值');
        }

        Db::startTrans();
        try {
            // 确定基准排队编号：如果有after_queue_code则使用它，否则使用初始queue_code
            $baseQueueCode = $apply->after_queue_code ?: $apply->queue_code;
            $priorityQueue = $config['priority_queue'];

            // 计算购买后的排队编号
            if ($baseQueueCode <= self::MIN_QUEUE_CODE) {
                $afterQueueCode = self::MIN_QUEUE_CODE;
            } else {
                $afterQueueCode = $baseQueueCode - $priorityQueue;
                if ($afterQueueCode < self::MIN_QUEUE_CODE) {
                    $afterQueueCode = self::MIN_QUEUE_CODE;
                }
            }

            // 扣除通道费
            User::changeInc(
                $user['id'],
                -$config['channel_fee'],
                'topup_balance',
                96, // 类型，购买优先通道
                $config['id'], // 关联ID，使用配置ID
                5, // 日志类型，对应topup_balance
                '绿色通道-支付通道费',
                0, // 管理员ID
                2, // 状态
                'GC' // 订单前缀
            );

            // 更新申请记录的排队编号
            $apply->after_queue_code = $afterQueueCode;
            $apply->save();

            // 生成订单号
            $orderSn = build_order_sn($user['id'], 'GC');

            // 创建绿色通道购买记录
            $orderData = [
                'user_id'           => $user['id'],
                'config_id'         => $config['id'],
                'order_sn'          => $orderSn,
                'channel_fee'       => $config['channel_fee'],
                'priority_queue'    => $priorityQueue,
                'before_queue_code' => $baseQueueCode,
                'after_queue_code'  => $afterQueueCode,
                'status'            => GreenChannelOrder::STATUS_SUCCESS,
                'remark'            => '购买绿色通道成功'
            ];

            $order = GreenChannelOrder::create($orderData);

            Db::commit();

            return out([
                'order_id'          => $order->id,
                'order_sn'          => $orderSn,
                'before_queue_code' => $baseQueueCode,
                'after_queue_code'  => $afterQueueCode,
                'channel_fee'       => $config['channel_fee']
            ], 200, '购买成功');

        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 500, '购买失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取绿色通道购买记录
     */
    public function records()
    {
        $user = $this->user;
        $page = input('page/d', 1);
        $limit = input('limit/d', 10);

        $list = GreenChannelOrder::with(['config' => function ($query) {
            $query->field('id, name');
        }])
            ->where('user_id', $user['id'])
            ->order('id', 'desc')
            ->paginate(['page' => $page, 'list_rows' => $limit])
            ->each(function ($item) {
                $item->status_text = $item->getStatusTextAttr(null, $item->toArray());
                return $item;
            })
            ->toArray();

        return out($list);
    }
}