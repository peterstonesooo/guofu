<?php

namespace app\admin\controller;

use app\model\StockActivityClaims;
use app\model\User;
use think\facade\Request;
use think\facade\View;

class StockActivityClaimController extends AuthController
{
    // 活动领取记录列表
    public function index()
    {
        $req = Request::param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportStockActivityClaimList();
        }

        // 构建查询
        $query = StockActivityClaims::alias('ac')
            ->field('ac.*, u.phone, u.realname, ltg.name as ltg_stock_name, ltg.code as ltg_stock_code, ysg.name as ysg_stock_name, ysg.code as ysg_stock_code')
            ->leftJoin('user u', 'u.id = ac.user_id')
            ->leftJoin('stock_types ltg', 'ltg.id = ac.stock_type_id_ltg')
            ->leftJoin('stock_types ysg', 'ysg.id = ac.stock_type_id_ysg')
            ->order('ac.id', 'desc');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $query->whereIn('ac.user_id', $user_ids);
        }
        if (isset($req['activity_name']) && $req['activity_name'] !== '') {
            $query->where('ac.activity_name', 'like', "%{$req['activity_name']}%");
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $query->where('ac.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $query->where('ac.claim_date', '>=', $req['start_date']);
        }
        if (!empty($req['end_date'])) {
            $query->where('ac.claim_date', '<=', $req['end_date']);
        }
        if (!empty($req['claim_start_date'])) {
            $query->where('ac.created_at', '>=', $req['claim_start_date'] . ' 00:00:00');
        }
        if (!empty($req['claim_end_date'])) {
            $query->where('ac.created_at', '<=', $req['claim_end_date'] . ' 23:59:59');
        }

        // 获取数据并处理状态文本
        $data = $query->paginate(['list_rows' => 15, 'query' => $req]);

        // 处理状态文本
        $data->each(function ($item) {
            $item->status_text = $this->getStatusText($item->status);
            return $item;
        });

        View::assign('req', $req);
        View::assign('data', $data);

        return View::fetch();
    }

    // 获取状态文本
    private function getStatusText($status)
    {
        $map = [
            1 => '已发放',
            0 => '未发放'
        ];
        return $map[$status] ?? '未知';
    }

    // 导出活动领取记录
    public function exportStockActivityClaimList()
    {
        $req = Request::param();

        $builder = StockActivityClaims::alias('ac')
            ->field('ac.*, u.phone, u.realname, ltg.name as ltg_stock_name, ltg.code as ltg_stock_code, ysg.name as ysg_stock_name, ysg.code as ysg_stock_code')
            ->leftJoin('user u', 'u.id = ac.user_id')
            ->leftJoin('stock_types ltg', 'ltg.id = ac.stock_type_id_ltg')
            ->leftJoin('stock_types ysg', 'ysg.id = ac.stock_type_id_ysg');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('ac.user_id', $user_ids);
        }
        if (isset($req['activity_name']) && $req['activity_name'] !== '') {
            $builder->where('ac.activity_name', 'like', "%{$req['activity_name']}%");
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('ac.status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('ac.claim_date', '>=', $req['start_date']);
        }
        if (!empty($req['end_date'])) {
            $builder->where('ac.claim_date', '<=', $req['end_date']);
        }
        if (!empty($req['claim_start_date'])) {
            $builder->where('ac.created_at', '>=', $req['claim_start_date'] . ' 00:00:00');
        }
        if (!empty($req['claim_end_date'])) {
            $builder->where('ac.created_at', '<=', $req['claim_end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $builder->order('ac.id', 'desc')->select();

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $exportData[] = [
                'id'            => $item['id'],
                'phone'         => $item['phone'],
                'realname'      => $item['realname'],
                'activity_name' => $item['activity_name'],
                'ltg_stock'     => $item['ltg_stock_name'] . '(' . $item['ltg_stock_code'] . ')',
                'ltg_quantity'  => $item['ltg_quantity'],
                'ysg_stock'     => $item['ysg_stock_name'] . '(' . $item['ysg_stock_code'] . ')',
                'ysg_quantity'  => $item['ysg_quantity'],
                'status_text'   => $this->getStatusText($item['status']),
                'claim_date'    => $item['claim_date'],
                'created_at'    => $item['created_at'],
            ];
        }

        // 表头
        $header = [
            'id'            => 'ID',
            'phone'         => '用户手机号',
            'realname'      => '用户姓名',
            'activity_name' => '活动名称',
            'ltg_stock'     => '流通股权类型',
            'ltg_quantity'  => '流通股权数量',
            'ysg_stock'     => '原始股权类型',
            'ysg_quantity'  => '原始股权数量',
            'status_text'   => '状态',
            'claim_date'    => '领取日期',
            'created_at'    => '创建时间',
        ];

        // 导出Excel
        $filename = '活动领取记录-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}