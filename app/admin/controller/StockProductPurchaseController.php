<?php

namespace app\admin\controller;

use app\model\StockProduct;
use app\model\StockProductPurchases;
use app\model\User;
use think\facade\Request;
use think\facade\View;

class StockProductPurchaseController extends AuthController
{
    // 产品购买记录列表
    public function index()
    {
        $req = Request::param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportProductPurchaseList();
        }

        // 构建查询
        $query = StockProductPurchases::alias('pp')
            ->field('pp.*, u.phone, u.realname, sp.title as product_name')
            ->join('user u', 'u.id = pp.user_id')
            ->join('stock_product sp', 'sp.id = pp.product_id')
            ->order('pp.id', 'desc');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $query->whereIn('pp.user_id', $user_ids);
        }
        if (isset($req['product_id']) && $req['product_id'] !== '') {
            $query->where('pp.product_id', $req['product_id']);
        }
        if (isset($req['pay_type']) && $req['pay_type'] !== '') {
            $query->where('pp.pay_type', $req['pay_type']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $query->where('pp.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $query->where('pp.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $query->where('pp.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据并处理类型和状态文本
        $data = $query->paginate(['list_rows' => 15, 'query' => $req]);

        // 处理类型和状态文本
        $data->each(function ($item) {
            $item->pay_type_text = $this->getPayTypeText($item->pay_type);
            $item->status_text = $this->getStatusText($item->status);
            return $item;
        });

        // 产品下拉
        $products = StockProduct::where('status', 1)->select();
        View::assign('products', $products);
        View::assign('req', $req);
        View::assign('data', $data);

        return View::fetch();
    }

    // 获取支付方式文本
    private function getPayTypeText($pay_type)
    {
        $map = [
            1 => '充值余额',
            2 => '团队奖金余额'
        ];
        return $map[$pay_type] ?? '未知';
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

    // 导出产品购买记录
    public function exportProductPurchaseList()
    {
        $req = Request::param();

        $builder = StockProductPurchases::alias('pp')
            ->field('pp.*, u.phone, u.realname, sp.title as product_name')
            ->join('user u', 'u.id = pp.user_id')
            ->join('stock_product sp', 'sp.id = pp.product_id');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('pp.user_id', $user_ids);
        }
        if (isset($req['product_id']) && $req['product_id'] !== '') {
            $builder->where('pp.product_id', $req['product_id']);
        }
        if (isset($req['pay_type']) && $req['pay_type'] !== '') {
            $builder->where('pp.pay_type', $req['pay_type']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('pp.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('pp.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('pp.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $builder->order('pp.id', 'desc')->select();

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $exportData[] = [
                'id'            => $item['id'],
                'phone'         => $item['phone'],
                'realname'      => $item['realname'],
                'product_name'  => $item['product_name'],
                'quantity'      => $item['quantity'],
                'price'         => $item['price'],
                'amount'        => $item['amount'],
                'pay_type_text' => $this->getPayTypeText($item['pay_type']),
                'status_text'   => $this->getStatusText($item['status']),
                'remark'        => $item['remark'],
                'created_at'    => $item['created_at'],
            ];
        }

        // 表头
        $header = [
            'id'            => 'ID',
            'phone'         => '用户手机号',
            'realname'      => '用户姓名',
            'product_name'  => '产品名称',
            'quantity'      => '购买数量',
            'price'         => '产品单价',
            'amount'        => '支付金额',
            'pay_type_text' => '支付方式',
            'status_text'   => '状态',
            'remark'        => '备注',
            'created_at'    => '购买时间',
        ];

        // 导出Excel
        $filename = '产品购买记录-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}