<?php

namespace app\admin\controller;

use app\model\StockTransactions;
use app\model\StockTypes;
use app\model\User;
use think\facade\Request;
use think\facade\View;

class StockTransactionController extends AuthController
{
    // 股权交易记录列表
    public function index()
    {
        $req = Request::param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportStockTransactionList();
        }

        // 构建查询
        $query = StockTransactions::alias('t')
            ->field('t.*, u.phone, u.realname, st.name as stock_name, st.code as stock_code')
            ->join('user u', 'u.id = t.user_id')
            ->join('stock_types st', 'st.id = t.stock_type_id')
            ->order('t.id', 'desc');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $query->whereIn('t.user_id', $user_ids);
        }
        if (isset($req['stock_type_id']) && $req['stock_type_id'] !== '') {
            $query->where('t.stock_type_id', $req['stock_type_id']);
        }
        if (isset($req['type']) && $req['type'] !== '') {
            $query->where('t.type', $req['type']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $query->where('t.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $query->where('t.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $query->where('t.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据并处理类型和状态文本
        $data = $query->paginate(['list_rows' => 15, 'query' => $req]);

        // 处理类型和状态文本
        $data->each(function ($item) {
            $item->type_text = $this->getTypeText($item->type);
            $item->status_text = $this->getStatusText($item->status);
            $item->source_text = $this->getSourceText($item->source);
            return $item;
        });

        // 股权类型下拉
        $stockTypes = StockTypes::select();
        View::assign('stockTypes', $stockTypes);
        View::assign('req', $req);
        View::assign('data', $data);

        return View::fetch();
    }

    // 获取交易类型文本
    private function getTypeText($type)
    {
        $map = [
            1 => '买入',
            2 => '卖出',
            3 => '活动'
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

    // 获取来源文本
    private function getSourceText($source)
    {
        $map = [
            0 => '直接购买',
            1 => '股权方案购买所得',
        ];
        return $map[$source] ?? '股权方案购买';
    }

    // 导出股权交易记录
    public function exportStockTransactionList()
    {
        $req = Request::param();

        $builder = StockTransactions::alias('t')
            ->field('t.*, u.phone, u.realname, st.name as stock_name, st.code as stock_code')
            ->join('user u', 'u.id = t.user_id')
            ->join('stock_types st', 'st.id = t.stock_type_id');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('t.user_id', $user_ids);
        }
        if (isset($req['stock_type_id']) && $req['stock_type_id'] !== '') {
            $builder->where('t.stock_type_id', $req['stock_type_id']);
        }
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('t.type', $req['type']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('t.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('t.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('t.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $builder->order('t.id', 'desc')->select();

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $exportData[] = [
                'id'          => $item['id'],
                'phone'       => $item['phone'],
                'realname'    => $item['realname'],
                'stock_name'  => $item['stock_name'] . '(' . $item['stock_code'] . ')',
                'type_text'   => $this->getTypeText($item['type']),
                'quantity'    => $item['quantity'],
                'price'       => $item['price'],
                'amount'      => $item['amount'],
                'source_text' => $this->getSourceText($item['source']),
                'status_text' => $this->getStatusText($item['status']),
                'remark'      => $item['remark'],
                'created_at'  => $item['created_at'],
            ];
        }

        // 表头
        $header = [
            'id'          => 'ID',
            'phone'       => '用户手机号',
            'realname'    => '用户姓名',
            'stock_name'  => '股权类型',
            'type_text'   => '交易类型',
            'quantity'    => '交易数量',
            'price'       => '交易价格',
            'amount'      => '交易金额',
            'source_text' => '来源',
            'status_text' => '状态',
            'remark'      => '备注',
            'created_at'  => '交易时间',
        ];

        // 导出Excel
        $filename = '股权交易记录-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}