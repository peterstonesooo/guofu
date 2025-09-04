<?php

namespace app\admin\controller;

use app\model\StockTransactions;
use app\model\StockTypes;
use think\facade\View;

class StockTransactionController extends AuthController
{
    // 股权交易记录列表
    public function index()
    {
        if ($this->request->isAjax()) {
            $params = $this->request->only([
                'page', 'limit', 'draw', 'start', 'length',
                'user_id', 'stock_type_id',
                'type', 'status', 'date_range'
            ]);

            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 15;

            $query = StockTransactions::with(['user', 'stockType'])
                ->order('id', 'desc');

            // 搜索条件
            if (!empty($params['user_id'])) {
                $query->where('user_id', $params['user_id']);
            }

            if (!empty($params['stock_type_id'])) {
                $query->where('stock_type_id', $params['stock_type_id']);
            }

            if (!empty($params['type'])) {
                $query->where('type', $params['type']);
            }

            if (!empty($params['status'])) {
                $query->where('status', $params['status']);
            }

            if (!empty($params['date_range'])) {
                [$start, $end] = explode(' - ', $params['date_range']);
                $query->whereBetweenTime('created_at', $start, $end);
            }

            $page = ($params['start'] / $params['length']) + 1;
            $list = $query->paginate([
                'list_rows' => $params['length'],
                'page'      => $page
            ]);

            // 格式化数据
            $data = [];
            foreach ($list->items() as $item) {
                $data[] = [
                    'id'          => $item->id,
                    'username'    => $item->user->realname ?? '已删除',
                    'mobile'      => $item->user->phone ?? '',
                    'stock_name'  => $item->stockType->name ?? '已删除',
                    'type_text'   => $item->type == 1 ? '买入' : '卖出',
                    'quantity'    => (float)$item->quantity, // 转换为浮点数
                    'price'       => (float)$item->price,    // 转换为浮点数
                    'amount'      => (float)$item->amount,   // 转换为浮点数
                    'status_text' => $item->status == 1 ? '成功' : '失败',
                    'remark'      => $item->remark,
                    'created_at'  => $item->created_at,
                    'source_text' => $this->getSourceText($item->source)
                ];
            }

            return json([
                'draw'            => $params['draw'] ?? 1, // DataTables必需参数
                'recordsTotal'    => $list->total(),
                'recordsFiltered' => $list->total(),
                'data'            => $data
            ]);
        }

        // 股权类型下拉
        $stockTypes = StockTypes::select();
        View::assign('stockTypes', $stockTypes);

        return View::fetch();
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
}