<?php

namespace app\admin\controller;

use app\model\Notarization;
use app\model\User;
use think\facade\Db;

class BailController extends AuthController
{
    public function index()
    {
        $req = request()->param();
        
        $builder = Notarization::alias('n')
            ->leftJoin('mp_user u', 'n.user_id = u.id')
            ->field('n.*, u.phone, u.realname')
            ->where('n.type', 1) // 1 保证金
            ->order('n.id', 'desc');
        
        // 按用户手机号或用户ID搜索
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('n.user_id', $user_ids);
        }
        
        // 按状态筛选
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('n.status', $req['status']);
        }
        
        // 按日期范围搜索
        if (!empty($req['start_date'])) {
            $builder->where('n.created_at', '>=', $req['start_date']);
        }
        if (!empty($req['end_date'])) {
            $builder->where('n.created_at', '<=', $req['end_date'] . ' 23:59:59');
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
                'fees' => '手续费',
                'status_text' => '状态',
                'created_at' => '创建时间',
                'end_time' => '完成时间'
            ], '保证金记录-' . date('YmdHis'));
        }
        
        // 统计数据
        $total_money = round($builder1->sum('n.money'), 2);
        $total_fees = round($builder1->sum('n.fees'), 2);
        
        $this->assign('total_money', $total_money);
        $this->assign('total_fees', $total_fees);
        
        // 分页 - 固定每页15条
        $data = $builder->paginate(15, false, ['query' => $req]);
        
        $this->assign('req', $req);
        $this->assign('data', $data);
        
        return $this->fetch();
    }
    
    /**
     * 审核通过保证金
     */
    public function approve()
    {
        $id = input('post.id');
        
        if (!$id) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        $bail = Notarization::where('id', $id)->where('type', 1)->find();
        if (!$bail) {
            return json(['code' => 0, 'msg' => '保证金记录不存在']);
        }
        
        if ($bail->status != 1) {
            return json(['code' => 0, 'msg' => '该记录不是登记中状态']);
        }
        
        Db::startTrans();
        try {
            // 审核通过
            User::changeInc($bail['user_id'], $bail['money'], 'bail_balance', 15, $bail['id'], 12, '完成保证金');
            Notarization::where('id', $bail['id'])->update(['status' => 2, 'end_time' => date('Y-m-d H:i:s')]);
            
            Db::commit();
            return json(['code' => 1, 'msg' => '审核通过成功']);
        } catch (\Exception $e) {
            Db::rollback();
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
        
        $bails = Notarization::whereIn('id', $ids)->where('type', 1)->where('status', 1)->select();
        
        if ($bails->isEmpty()) {
            return json(['code' => 0, 'msg' => '没有可审核的记录']);
        }
        
        Db::startTrans();
        try {
            $success_count = 0;
            foreach ($bails as $item) {
                // 审核通过
                User::changeInc($item['user_id'], $item['money'], 'bail_balance', 15, $item['id'], 12, '完成保证金');
                Notarization::where('id', $item['id'])->update(['status' => 2, 'end_time' => date('Y-m-d H:i:s')]);
                $success_count++;
            }
            
            Db::commit();
            return json(['code' => 1, 'msg' => "批量审核成功，共审核通过 {$success_count} 条记录"]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '操作失败：' . $e->getMessage()]);
        }
    }
}