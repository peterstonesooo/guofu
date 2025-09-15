<?php

namespace app\admin\controller;

use app\model\FinanceApprovalApply;
use app\model\FinanceApprovalConfig;
use app\model\FinanceApprovalCountDown;
use app\model\User;
use think\facade\Cache;
use think\facade\Db;
use think\facade\View;

class FinanceApprovalController extends AuthController
{
    // 全局起始排队编号
    const GLOBAL_QUEUE_START = 60000;

    // 审批申请列表
    public function applyList()
    {
        $req = request()->param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportApplyList();
        }

        // 获取当前倒计时编号
        $countDown = FinanceApprovalCountDown::find(1);
        View::assign('countDown', $countDown ? $countDown->current_queue_code : self::GLOBAL_QUEUE_START);
        View::assign('countDownLastUpdated', $countDown ? $countDown->updated_at : "");

        $builder = FinanceApprovalApply::alias('a')
            ->field('a.*, u.phone, u.realname, c.name as config_name')
            ->join('user u', 'u.id = a.user_id')
            ->join('finance_approval_config c', 'c.id = a.config_id');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('a.user_id', $user_ids);
        }
        if (isset($req['config_id']) && $req['config_id'] !== '') {
            $builder->where('a.config_id', $req['config_id']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('a.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('a.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('a.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 分页和排序
        $data = $builder->order('a.id', 'desc')->paginate(['list_rows' => 15, 'query' => $req]);

        // 获取审批配置下拉选项
        $configOptions = FinanceApprovalConfig::where('status', 1)->select();

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('configOptions', $configOptions);
        View::assign('statusMap', [
            1 => '审批中',
            2 => '已拨款',
            3 => '审批完成'
        ]);

        return View::fetch();
    }

    // 生成排队编号（严格递增）
    private function generateQueueCode()
    {
        // 检查当前是否已有排队编号
        $maxQueueCode = FinanceApprovalApply::where('queue_code', '>', 0)->max('queue_code');

        // 如果还没有排队编号，则使用起始编号60000
        if (!$maxQueueCode) {
            // 初始化序列控制记录
            Db::name('finance_approval_queue_seq')
                ->where('id', 1)
                ->update([
                    'last_queue_code'     => self::GLOBAL_QUEUE_START,
                    'current_range_start' => self::GLOBAL_QUEUE_START,
                    'current_range_end'   => self::GLOBAL_QUEUE_START + 10
                ]);

            return self::GLOBAL_QUEUE_START;
        }

        // 使用事务确保操作的原子性
        Db::startTrans();
        try {
            // 获取序列控制记录（加锁）
            $seq = Db::name('finance_approval_queue_seq')
                ->lock(true)
                ->find(1);

            if (!$seq) {
                throw new \Exception('序列控制记录不存在');
            }

            // 确定可用范围
            $minCode = $seq['last_queue_code'] + 1;
            $maxCode = $seq['current_range_end'];

            // 如果当前范围已用完，扩展到下一个区块
            if ($minCode > $maxCode) {
                $newRangeStart = $maxCode + 1;
                $newRangeEnd = $newRangeStart + 10;

                Db::name('finance_approval_queue_seq')
                    ->where('id', 1)
                    ->update([
                        'current_range_start' => $newRangeStart,
                        'current_range_end'   => $newRangeEnd,
                        'last_queue_code'     => $newRangeStart - 1 // 重置为新区块前一个值
                    ]);

                // 重新获取更新后的范围
                $seq = Db::name('finance_approval_queue_seq')
                    ->lock(true)
                    ->find(1);

                $minCode = $seq['last_queue_code'] + 1;
                $maxCode = $seq['current_range_end'];
            }

            // 在当前区块内随机生成大于上次编号的整数
            $queueCode = mt_rand($minCode, $maxCode);

            // 更新序列
            Db::name('finance_approval_queue_seq')
                ->where('id', 1)
                ->update([
                    'last_queue_code' => $queueCode
                ]);

            Db::commit();
            return $queueCode;

        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    // 更新倒计时编号（每分钟自动调用）
    private function updateCountDown()
    {
        // 获取当前倒计时编号
        $countDown = FinanceApprovalCountDown::find(1);
        if (!$countDown) {
            $countDown = new FinanceApprovalCountDown();
            $countDown->id = 1;
            $countDown->current_queue_code = self::GLOBAL_QUEUE_START;
            $countDown->updated_at = date('Y-m-d H:i:s');
            $countDown->save();
        }

        // 计算距离上次更新的分钟数
        $lastUpdated = strtotime($countDown->updated_at);
        $currentTime = time();
        $minutesPassed = max(1, round(($currentTime - $lastUpdated) / 60));

        // 每小时增加5-10个编号的逻辑
        $changeAmount = 0;

        // 每分钟有10%的概率触发变化
        if (mt_rand(1, 100) <= 10) {
            // 每小时的基础变化量 (5-10个)
            $hourlyChange = mt_rand(5, 10);

            // 计算当前分钟应增加的数量（按比例减少）
            $changeAmount = ceil($hourlyChange * ($minutesPassed / 60));

            // 确保最少变化1个
            $changeAmount = max(1, $changeAmount);

            // 增加变化量
            $countDown->current_queue_code += $changeAmount;

            // 更新最后变化时间
            $countDown->updated_at = date('Y-m-d H:i:s');
        }

        // 确保不超过当前最大排队编号
        $maxQueueCode = FinanceApprovalApply::where('queue_code', '>', 0)
            ->max('queue_code');

        if ($maxQueueCode && $countDown->current_queue_code > $maxQueueCode) {
            $countDown->current_queue_code = $maxQueueCode;
        }

        $countDown->save();

        // 更新Redis缓存
        $countDownCacheKey = 'finance_approval_count_down';
        Cache::set($countDownCacheKey, $countDown->current_queue_code, 3600 * 24 * 365);

        return [
            'count_down'    => $countDown->current_queue_code,
            'change_amount' => $changeAmount
        ];
    }

    // 审批完成操作
    public function completeApply()
    {
        $req = $this->validate(request(), [
            'id'     => 'require|number',
            'remark' => 'max:255',
        ]);

        $apply = FinanceApprovalApply::find($req['id']);
        if (!$apply) {
            return out(null, 10001, '申请记录不存在');
        }

        // 状态验证：只能完成状态为"审批中"的审批
        if ($apply->status != 1) {
            return out(null, 10002, '该申请无法完成审批');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 生成排队编号
            $queueCode = $this->generateQueueCode();

            // 更新状态为审批完成
            $apply->status = 3;
            $apply->queue_code = $queueCode;
            $apply->remark = $req['remark'] ?? '';
            $apply->save();

            // 提交事务
            Db::commit();

            return out(['queue_code' => $queueCode]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return out(null, 10006, '操作失败: ' . $e->getMessage());
        }
    }

    // 拨款操作
    public function fundApply()
    {
        $req = $this->validate(request(), [
            'id'     => 'require|number',
            'remark' => 'max:255',
        ]);

        $apply = FinanceApprovalApply::find($req['id']);
        if (!$apply) {
            return out(null, 10001, '申请记录不存在');
        }

        // 状态验证：只能拨款状态为"审批完成"的记录
        if ($apply->status != 3) {
            return out(null, 10003, '请先完成审批操作');
        }

        // 更新状态为已拨款
        $apply->status = 2;
        $apply->remark = $req['remark'] ?? '';
        $apply->save();

        return out();
    }

    // 批量完成审批操作
    public function batchCompleteApply()
    {
        $req = $this->validate(request(), [
            'ids'    => 'require|array',
            'remark' => 'max:255',
        ]);

        $successCount = 0;
        $errorMessages = [];

        // 开启事务
        Db::startTrans();
        try {
            foreach ($req['ids'] as $id) {
                $apply = FinanceApprovalApply::find($id);
                if (!$apply) {
                    $errorMessages[] = "ID {$id}: 申请记录不存在";
                    continue;
                }

                if ($apply->status != 1) {
                    $errorMessages[] = "ID {$id}: 该申请无法完成审批";
                    continue;
                }

                // 生成排队编号
                $queueCode = $this->generateQueueCode();

                $apply->status = 3;
                $apply->queue_code = $queueCode;
                $apply->remark = $req['remark'] ?? $apply->remark;
                if ($apply->save()) {
                    $successCount++;
                } else {
                    $errorMessages[] = "ID {$id}: 审批完成失败";
                }
            }

            // 提交事务
            Db::commit();

            if (!empty($errorMessages)) {
                return out(['success_count' => $successCount], 10004, implode('; ', $errorMessages));
            }

            return out(['success_count' => $successCount]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return out(['success_count' => $successCount], 10006, '操作失败: ' . $e->getMessage());
        }
    }

    // 批量拨款操作
    public function batchFundApply()
    {
        $req = $this->validate(request(), [
            'ids'    => 'require|array',
            'remark' => 'max:255',
        ]);

        $successCount = 0;
        $errorMessages = [];

        foreach ($req['ids'] as $id) {
            $apply = FinanceApprovalApply::find($id);
            if (!$apply) {
                $errorMessages[] = "ID {$id}: 申请记录不存在";
                continue;
            }

            if ($apply->status != 3) {
                $errorMessages[] = "ID {$id}: 请先完成审批操作";
                continue;
            }

            $apply->status = 2;
            $apply->remark = $req['remark'] ?? $apply->remark;
            if ($apply->save()) {
                $successCount++;
            } else {
                $errorMessages[] = "ID {$id}: 拨款失败";
            }
        }

        if (!empty($errorMessages)) {
            return out(['success_count' => $successCount], 10005, implode('; ', $errorMessages));
        }

        return out(['success_count' => $successCount]);
    }

    // 获取当前倒计时编号
    public function getCountDown()
    {
        $countDown = FinanceApprovalCountDown::find(1);
        if (!$countDown) {
            // 初始化倒计时编号
            $countDown = new FinanceApprovalCountDown();
            $countDown->id = 1;
            $countDown->current_queue_code = self::GLOBAL_QUEUE_START;
            $countDown->updated_at = date('Y-m-d H:i:s');
            $countDown->save();
        }

        return out([
            'count_down'   => $countDown->current_queue_code,
            'last_updated' => $countDown->updated_at
        ]);
    }

    // 更新倒计时编号（每分钟自动调用）
    public function updateCountDownApi()
    {
        try {
            $result = $this->updateCountDown();
            return out($result);
        } catch (\Exception $e) {
            return out(null, 10007, '更新倒计时编号失败: ' . $e->getMessage());
        }
    }

    // 导出审批申请列表
    public function exportApplyList()
    {
        $req = request()->param();
        $builder = FinanceApprovalApply::alias('a')
            ->field('a.*, u.phone, u.realname, c.name as config_name')
            ->join('user u', 'u.id = a.user_id')
            ->join('finance_approval_config c', 'c.id = a.config_id');

        // 搜索条件（与applyList方法保持一致）
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('a.user_id', $user_ids);
        }
        if (isset($req['config_id']) && $req['config_id'] !== '') {
            $builder->where('a.config_id', $req['config_id']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('a.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('a.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('a.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $builder->order('a.id', 'desc')->select();

        // 状态映射
        $statusMap = [
            1 => '审批中',
            2 => '已拨款',
            3 => '审批完成',
        ];

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $exportData[] = [
                'id'              => $item->id,
                'phone'           => $item->phone,
                'realname'        => $item->realname,
                'config_name'     => $item->config_name,
                'queue_code'      => $item->queue_code ?? '未分配',
                'withdraw_amount' => $item->withdraw_amount,
                'approval_fee'    => $item->approval_fee,
                'status'          => $statusMap[$item->status] ?? '未知状态',
                'remark'          => $item->remark,
                'created_at'      => $item->created_at,
            ];
        }

        // 表头
        $header = [
            'id'              => 'ID',
            'phone'           => '用户手机号',
            'realname'        => '用户姓名',
            'config_name'     => '审批档次',
            'queue_code'      => '排队编号',
            'withdraw_amount' => '提现金额',
            'approval_fee'    => '审批费',
            'status'          => '状态',
            'remark'          => '备注',
            'created_at'      => '创建时间',
        ];

        // 导出Excel
        $filename = '审批申请列表-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}