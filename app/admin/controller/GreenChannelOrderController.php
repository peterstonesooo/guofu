<?php

namespace app\admin\controller;

use app\model\GreenChannelOrder;
use app\model\GreenConfig;
use app\model\User;
use think\facade\View;

class GreenChannelOrderController
{

    // 绿色通道购买记录列表
    public function greenChannelList()
    {
        $req = request()->param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportGreenChannelList();
        }

        $builder = GreenChannelOrder::alias('o')
            ->field('o.*, u.phone, u.realname, g.name as config_name')
            ->join('user u', 'u.id = o.user_id')
            ->join('green_config g', 'g.id = o.config_id')
            ->where('o.status', 1); // 只显示成功的订单

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('o.user_id', $user_ids);
        }
        if (isset($req['config_id']) && $req['config_id'] !== '') {
            $builder->where('o.config_id', $req['config_id']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('o.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('o.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 分页和排序
        $data = $builder->order('o.id', 'desc')->paginate(['list_rows' => 15, 'query' => $req]);

        // 获取绿色通道配置下拉选项
        $configOptions = GreenConfig::where('status', 1)->select();

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('configOptions', $configOptions);

        return View::fetch('green_channel_list');
    }

// 导出绿色通道购买记录
    public function exportGreenChannelList()
    {
        $req = request()->param();

        $builder = GreenChannelOrder::alias('o')
            ->field('o.*, u.phone, u.realname, g.name as config_name')
            ->join('user u', 'u.id = o.user_id')
            ->join('green_config g', 'g.id = o.config_id')
            ->where('o.status', 1);

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            $builder->whereIn('o.user_id', $user_ids);
        }
        if (isset($req['config_id']) && $req['config_id'] !== '') {
            $builder->where('o.config_id', $req['config_id']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('o.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('o.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        // 获取数据
        $data = $builder->order('o.id', 'desc')->select();

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $exportData[] = [
                'id'                => $item['id'],
                'phone'             => $item['phone'],
                'realname'          => $item['realname'],
                'config_name'       => $item['config_name'],
                'order_sn'          => $item['order_sn'],
                'channel_fee'       => $item['channel_fee'],
                'priority_queue'    => $item['priority_queue'],
                'before_queue_code' => $item['before_queue_code'] ?? '无',
                'after_queue_code'  => $item['after_queue_code'] ?? '无',
                'remark'            => $item['remark'],
                'created_at'        => $item['created_at'],
            ];
        }

        // 表头
        $header = [
            'id'                => 'ID',
            'phone'             => '用户手机号',
            'realname'          => '用户姓名',
            'config_name'       => '绿色方案',
            'order_sn'          => '订单编号',
            'channel_fee'       => '通道费',
            'priority_queue'    => '优先队列值',
            'before_queue_code' => '购买前排队号',
            'after_queue_code'  => '购买后排队号',
            'remark'            => '备注',
            'created_at'        => '购买时间',
        ];

        // 导出Excel
        $filename = '绿色通道购买记录-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}