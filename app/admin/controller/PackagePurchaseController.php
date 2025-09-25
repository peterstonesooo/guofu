<?php

namespace app\admin\controller;

use app\model\PackagePurchases;
use app\model\StockPackages;
use think\facade\View;

class PackagePurchaseController extends AuthController
{
    // 股权方案购买记录
    public function index()
    {
        if ($this->request->isAjax()) {
            $params = $this->request->only([
                'page', 'limit', 'draw', 'start', 'length',
                'user_id', 'package_id',
                'pay_type', 'status', 'date_range'
            ]);

            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 15;

            $query = PackagePurchases::with(['user', 'package'])
                ->order('id', 'desc');

            // 搜索条件
            if (!empty($params['user_id'])) {
                $query->where('user_id', $params['user_id']);
            }

            if (!empty($params['package_id'])) {
                $query->where('package_id', $params['package_id']);
            }

            if (!empty($params['pay_type'])) {
                $query->where('pay_type', $params['pay_type']);
            }

            if (!empty($params['status'])) {
                $query->where('status', $params['status']);
            }

            if (!empty($params['date_range'])) {
                [$start, $end] = explode(' - ', $params['date_range']);
                $query->whereBetweenTime('created_at', $start, $end);
            }

            $list = $query->paginate([
                'list_rows' => $params['length'],
                'page'      => $page
            ]);

            // 格式化数据
            $data = [];
            foreach ($list->items() as $item) {
                $data[] = [
                    'id'            => $item->id,
                    'username'      => $item->user->realname ?? '已删除',
                    'mobile'        => $item->user->phone ?? '',
                    'package_name'  => $item->package->name ?? '已删除',
                    'amount'        => (float)$item->amount, // 转换为浮点数
                    'pay_type_text' => $this->getPayTypeText($item->pay_type),
                    'status_text'   => $item->status == 1 ? '成功' : '失败',
                    'created_at'    => $item->created_at
                ];
            }

            return json([
                'draw'            => $params['draw'] ?? 1, // DataTables必需参数
                'recordsTotal'    => $list->total(),
                'recordsFiltered' => $list->total(),
                'data'            => $data
            ]);
        }

        // 股权套餐下拉
        $packages = StockPackages::select();
        View::assign('packages', $packages);

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
}