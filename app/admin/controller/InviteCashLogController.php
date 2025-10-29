<?php

namespace app\admin\controller;

use app\model\invite_present\InviteCashLog;
use app\model\User;
use think\facade\Request;
use think\facade\View;

class InviteCashLogController extends AuthController
{
    // 现金红包发放记录列表
    public function index()
    {
        $req = Request::param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportInviteCashLog();
        }

        // 构建查询
        $query = InviteCashLog::with(['user'])
            ->order('id', 'desc');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $query->whereIn('user_id', $user_ids);
        }
        if (isset($req['invite_num']) && $req['invite_num'] !== '') {
            $query->where('invite_num', $req['invite_num']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $query->where('status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $query->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $query->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $query->paginate(['list_rows' => 15, 'query' => $req]);

        View::assign('req', $req);
        View::assign('data', $data);

        return View::fetch();
    }

    // 导出发放记录
    public function exportInviteCashLog()
    {
        $req = Request::param();

        $query = InviteCashLog::with(['user']);

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $query->whereIn('user_id', $user_ids);
        }
        if (isset($req['invite_num']) && $req['invite_num'] !== '') {
            $query->where('invite_num', $req['invite_num']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $query->where('status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $query->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $query->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $query->order('id', 'desc')->select();

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $exportData[] = [
                'id'          => $item['id'],
                'phone'       => $item->user->phone ?? '',
                'realname'    => $item->user->realname ?? '',
                'invite_num'  => $item['invite_num'],
                'cash_amount' => $item['cash_amount'],
                'status_text' => $item->status_text,
                'remark'      => $item['remark'],
                'created_at'  => $item['created_at'],
            ];
        }

        // 表头
        $header = [
            'id'          => 'ID',
            'phone'       => '用户手机号',
            'realname'    => '用户姓名',
            'invite_num'  => '邀请人数',
            'cash_amount' => '现金金额',
            'status_text' => '状态',
            'remark'      => '备注',
            'created_at'  => '发放时间',
        ];

        // 导出Excel
        $filename = '邀请现金红包发放记录-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}