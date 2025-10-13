<?php

namespace app\admin\controller;

use app\model\butie\StockButie;
use app\model\butie\StockButieRecords;
use app\model\User;
use think\facade\Request;
use think\facade\View;

class ButieRecordController extends AuthController
{
    // 补贴领取记录列表
    public function index()
    {
        $req = Request::param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportButieRecordList();
        }

        // 构建查询
        $query = StockButieRecords::alias('br')
            ->field('br.*, u.phone, u.realname, sb.title as butie_name')
            ->join('user u', 'u.id = br.user_id')
            ->join('stock_butie sb', 'sb.id = br.butie_id')
            ->order('br.id', 'desc');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $query->whereIn('br.user_id', $user_ids);
        }
        if (isset($req['butie_id']) && $req['butie_id'] !== '') {
            $query->where('br.butie_id', $req['butie_id']);
        }
        if (isset($req['type']) && $req['type'] !== '') {
            $query->where('br.type', $req['type']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $query->where('br.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $query->where('br.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $query->where('br.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据并处理类型和状态文本
        $data = $query->paginate(['list_rows' => 15, 'query' => $req]);

        // 处理类型和状态文本
        $data->each(function ($item) {
            $item->type_text = $this->getTypeText($item->type);
            $item->status_text = $this->getStatusText($item->status);
            return $item;
        });

        // 补贴下拉
        $buties = StockButie::where('status', 1)->select();
        View::assign('buties', $buties);
        View::assign('req', $req);
        View::assign('data', $data);

        return View::fetch();
    }

    // 获取补贴类型文本
    private function getTypeText($type)
    {
        $map = [
            1 => '活动补贴'
        ];
        return $map[$type] ?? '未知';
    }

    // 获取状态文本
    private function getStatusText($status)
    {
        $map = [
            1 => '成功',
            0 => '失败'
        ];
        return $map[$status] ?? '未知';
    }

    // 导出补贴领取记录
    public function exportButieRecordList()
    {
        $req = Request::param();

        $builder = StockButieRecords::alias('br')
            ->field('br.*, u.phone, u.realname, sb.title as butie_name')
            ->join('user u', 'u.id = br.user_id')
            ->join('stock_butie sb', 'sb.id = br.butie_id');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('br.user_id', $user_ids);
        }
        if (isset($req['butie_id']) && $req['butie_id'] !== '') {
            $builder->where('br.butie_id', $req['butie_id']);
        }
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('br.type', $req['type']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('br.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('br.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('br.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $builder->order('br.id', 'desc')->select();

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $exportData[] = [
                'id'           => $item['id'],
                'phone'        => $item['phone'],
                'realname'     => $item['realname'],
                'butie_name'   => $item['butie_name'],
                'quantity'     => $item['quantity'],
                'price'        => $item['price'],
                'amount'       => $item['amount'],
                'type_text'    => $this->getTypeText($item['type']),
                'status_text'  => $this->getStatusText($item['status']),
                'remark'       => $item['remark'],
                'created_at'   => $item['created_at'],
            ];
        }

        // 表头
        $header = [
            'id'           => 'ID',
            'phone'        => '用户手机号',
            'realname'     => '用户姓名',
            'butie_name'   => '补贴名称',
            'quantity'     => '领取数量',
            'price'        => '补贴单价',
            'amount'       => '领取金额',
            'type_text'    => '补贴类型',
            'status_text'  => '状态',
            'remark'       => '备注',
            'created_at'   => '领取时间',
        ];

        // 导出Excel
        $filename = '补贴领取记录-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}