<?php

namespace app\admin\controller;

use app\model\TaxOrder;
use app\model\User;
use think\facade\Db;
use think\facade\Log;
use Exception;

class TaxOrderController extends AuthController
{
    public function taxList()
    {
        $req = request()->param();
        
        $builder = TaxOrder::alias('t')
            ->leftJoin('mp_user u', 't.user_id = u.id')
            ->field('t.*, u.phone, u.realname')
            ->order('t.id', 'desc');
        
        // 按用户手机号或用户ID搜索
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('t.user_id', $user_ids);
        }
        
        // 按状态筛选
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('t.status', $req['status']);
        }
        
        // 按日期范围搜索
        if (!empty($req['start_date'])) {
            $builder->where('t.created_at', '>=', $req['start_date']);
        }
        if (!empty($req['end_date'])) {
            $builder->where('t.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        
        $builder1 = clone $builder;
        $builder2 = clone $builder;
        
        // 导出功能
        if (!empty($req['export'])) {
            $list = $builder2->select();
            create_excel($list, [
                'id' => '序号',
                'phone' => '用户手机',
                'realname' => '用户姓名',
                'money' => '金额',
                'taxes_money' => '税金',
                'status_text' => '状态',
                'created_at' => '创建时间',
                'end_time' => '结束时间'
            ], '纳税记录-' . date('YmdHis'));
        }
        
        // 统计数据
        $total_money = round($builder1->sum('t.money'), 2);
        $total_taxes = round($builder1->sum('t.taxes_money'), 2);
        
        $this->assign('total_money', $total_money);
        $this->assign('total_taxes', $total_taxes);
        
        // 分页 - 固定每页15条
        $data = $builder->paginate(15, false, ['query' => $req]);
        
        $this->assign('req', $req);
        $this->assign('data', $data);
        
        return $this->fetch();
    }
    
    /**
     * 审核通过税单（退税成功）
     */
    public function approve()
    {
        $id = input('post.id');
        
        if (!$id) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        $taxOrder = TaxOrder::find($id);
        if (!$taxOrder) {
            return json(['code' => 0, 'msg' => '税单记录不存在']);
        }
        
        if ($taxOrder->status != 2) {
            return json(['code' => 0, 'msg' => '该记录不是退税申请中状态']);
        }
        
        Db::startTrans();
        try {
            // 返还税金到团队奖励余额
            User::changeInc($taxOrder['user_id'], $taxOrder['taxes_money'], 'team_bonus_balance', 3, $taxOrder['id'], 36, '缴纳税费返还');
            
            // 更新状态为退税成功
            TaxOrder::where('id', $taxOrder['id'])->update([
                'status' => 3,
                'end_time' => date('Y-m-d H:i:s')
            ]);
            
            Db::commit();
            
            // 记录操作日志
            admin_handle_log('税单审核', '审核通过税单ID：' . $id);
            
            return json(['code' => 1, 'msg' => '审核通过成功']);
        } catch (Exception $e) {
            Db::rollback();
            Log::error('税费订单审核异常：' . $e->getMessage(), $e);
            return json(['code' => 0, 'msg' => '操作失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 批量审核通过
     */
    public function batchApprove()
    {
        $ids = input('post.ids', []);
        
        if (empty($ids)) {
            return json(['code' => 0, 'msg' => '请选择要审核的记录']);
        }
        
        // 查询所有待审核的记录
        $taxOrders = TaxOrder::whereIn('id', $ids)->where('status', 2)->select();
        
        if ($taxOrders->isEmpty()) {
            return json(['code' => 0, 'msg' => '没有可审核的记录']);
        }
        
        Db::startTrans();
        try {
            $success_count = 0;
            $fail_count = 0;
            $fail_messages = [];
            
            foreach ($taxOrders as $item) {
                try {
                    // 返还税金到团队奖励余额
                    User::changeInc($item['user_id'], $item['taxes_money'], 'team_bonus_balance', 3, $item['id'], 36, '缴纳税费返还');
                    
                    // 更新状态为退税成功
                    TaxOrder::where('id', $item['id'])->update([
                        'status' => 3,
                        'end_time' => date('Y-m-d H:i:s')
                    ]);
                    
                    $success_count++;
                } catch (Exception $e) {
                    $fail_count++;
                    $fail_messages[] = "税单ID {$item['id']} 处理失败: " . $e->getMessage();
                    Log::error('批量审核税单异常：ID=' . $item['id'] . ', ' . $e->getMessage(), $e);
                }
            }
            
            Db::commit();
            
            // 记录操作日志
            admin_handle_log('批量税单审核', '批量审核通过税单，成功：' . $success_count . '条，失败：' . $fail_count . '条');
            
            $msg = "批量审核完成，成功 {$success_count} 条";
            if ($fail_count > 0) {
                $msg .= "，失败 {$fail_count} 条";
                if (!empty($fail_messages)) {
                    $msg .= "。失败详情：" . implode('；', array_slice($fail_messages, 0, 3));
                    if (count($fail_messages) > 3) {
                        $msg .= "...";
                    }
                }
            }
            
            return json(['code' => 1, 'msg' => $msg]);
        } catch (Exception $e) {
            Db::rollback();
            Log::error('批量税单审核异常：' . $e->getMessage(), $e);
            return json(['code' => 0, 'msg' => '操作失败：' . $e->getMessage()]);
        }
    }
}