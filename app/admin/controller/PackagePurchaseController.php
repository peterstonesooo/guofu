<?php

namespace app\admin\controller;

use app\model\PackagePurchases;
use app\model\StockPackages;
use app\model\User;
use think\facade\Request;
use think\facade\View;

class PackagePurchaseController extends AuthController
{
    // 股权方案购买记录
    public function index()
    {
        $req = Request::param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportPackagePurchaseList();
        }

        // 构建查询
        $query = PackagePurchases::alias('p')
            ->field('p.*, u.phone, u.realname, sp.name as package_name')
            ->join('user u', 'u.id = p.user_id')
            ->join('stock_packages sp', 'sp.id = p.package_id')
            ->order('p.id', 'desc');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $query->whereIn('p.user_id', $user_ids);
        }
        if (isset($req['package_id']) && $req['package_id'] !== '') {
            $query->where('p.package_id', $req['package_id']);
        }
        if (isset($req['pay_type']) && $req['pay_type'] !== '') {
            $query->where('p.pay_type', $req['pay_type']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $query->where('p.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $query->where('p.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $query->where('p.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据并处理支付方式文本
        $data = $query->paginate(['list_rows' => 15, 'query' => $req]);

        // 处理支付方式文本
        $data->each(function ($item) {
            $item->pay_type_text = $this->getPayTypeText($item->pay_type);
            return $item;
        });

        // 股权套餐下拉
        $packages = StockPackages::select();
        View::assign('packages', $packages);
        View::assign('req', $req);
        View::assign('data', $data);

        return View::fetch();
    }

    // 获取支付方式文本
    private function getPayTypeText($type)
    {
        $map = [
            1 => '可用余额',
            2 => '可提余额'
        ];
        return $map[$type] ?? '未知';
    }

    // 导出股权方案购买记录
    public function exportPackagePurchaseList()
    {
        $req = Request::param();

        $builder = PackagePurchases::alias('p')
            ->field('p.*, u.phone, u.realname, sp.name as package_name')
            ->join('user u', 'u.id = p.user_id')
            ->join('stock_packages sp', 'sp.id = p.package_id');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('p.user_id', $user_ids);
        }
        if (isset($req['package_id']) && $req['package_id'] !== '') {
            $builder->where('p.package_id', $req['package_id']);
        }
        if (isset($req['pay_type']) && $req['pay_type'] !== '') {
            $builder->where('p.pay_type', $req['pay_type']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('p.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('p.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('p.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $builder->order('p.id', 'desc')->select();

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $exportData[] = [
                'id'            => $item['id'],
                'phone'         => $item['phone'],
                'realname'      => $item['realname'],
                'package_name'  => $item['package_name'],
                'amount'        => $item['amount'],
                'pay_type_text' => $this->getPayTypeText($item['pay_type']),
                'status_text'   => $item['status'] == 1 ? '成功' : '失败',
                'created_at'    => $item['created_at'],
            ];
        }

        // 表头
        $header = [
            'id'            => 'ID',
            'phone'         => '用户手机号',
            'realname'      => '用户姓名',
            'package_name'  => '股权套餐',
            'amount'        => '支付金额',
            'pay_type_text' => '支付方式',
            'status_text'   => '状态',
            'created_at'    => '购买时间',
        ];

        // 导出Excel
        $filename = '股权方案购买记录-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}