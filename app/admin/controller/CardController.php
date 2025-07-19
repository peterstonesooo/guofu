<?php

namespace app\admin\controller;

use app\model\UserCard;
use app\model\User;
use think\facade\Db;

class CardController extends AuthController
{
    public function index()
    {
        $req = request()->param();
        
        $builder = UserCard::alias('c')
            ->leftJoin('mp_user u', 'c.user_id = u.id')
            ->field('c.*, u.phone, u.realname')
            ->order('c.id', 'desc');
        
        // 按用户手机号或用户ID搜索
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('c.user_id', $user_ids);
        }
        
        // 按状态筛选
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('c.status', $req['status']);
        }
        
        // 按日期范围搜索
        if (!empty($req['start_date'])) {
            $builder->where('c.created_at', '>=', $req['start_date']);
        }
        if (!empty($req['end_date'])) {
            $builder->where('c.created_at', '<=', $req['end_date'] . ' 23:59:59');
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
                'yesterday_interest' => '昨日利息',
                'status' => ['激活状态', function($v) {
                    return $v == 1 ? '已激活' : '未激活';
                }],
                'created_at' => '创建时间',
            ], '银行卡列表');
        }
        
        $data = $builder->paginate(['query' => $req]);
        
        $statistics = [
            'total_count' => $builder1->count(),
            'total_money' => $builder1->sum('c.money'),
            'total_fees' => $builder1->sum('c.fees'),
            'total_interest' => $builder1->sum('c.yesterday_interest'),
        ];
        
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('statistics', $statistics);
        
        return $this->fetch();
    }
    
}