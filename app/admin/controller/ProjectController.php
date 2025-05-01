<?php

namespace app\admin\controller;

use app\model\Category;
use app\model\Project;

class ProjectController extends AuthController
{
    public function projectList()
    {
        $req = request()->param();

        $builder = Project::order(['sort' => 'desc', 'id' => 'desc'])->where('class',1);
        if (isset($req['project_id']) && $req['project_id'] !== '') {
            $builder->where('id', $req['project_id']);
        }
        if (isset($req['project_group_id']) && $req['project_group_id'] !== '') {
            $builder->where('project_group_id', $req['project_group_id']);
        }
        if (isset($req['name']) && $req['name'] !== '') {
            $builder->where('name', $req['name']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }
        if (isset($req['is_recommend']) && $req['is_recommend'] !== '') {
            $builder->where('is_recommend', $req['is_recommend']);
        }

        $data = $builder->paginate(['query' => $req]);
        $groups = Category::getListKv();
        $this->assign('groups',$groups);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showProject()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = Project::where('id', $req['id'])->find();
        }
        //赠送项目
        $give = Project::select();
        if(!empty($data['give'])){
            $data['give'] = json_decode($data['give'],true);
        }
        $groups = Category::getListKv();
        $week = config('map.week');
        $this->assign('week',$week);
        $this->assign('groups',$groups);
        $this->assign('give',$give);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addProject()
    {
        $req = $this->validate(request(), [
            'project_group_id|项目分组ID' => 'require|integer',
            'name|项目名称' => 'require|max:100',
            'single_amount|单份金额' => 'require|float',
            'gift_integral|赠送积分' => 'integer',
            //'total_num|总份数' => 'require|integer',
 /*            'single_gift_digital_yuan|数字人民币' => 'integer',
            'single_gift_gf_purse|共富钱包' => 'integer', */
            'bonus_multiple|养老金倍数'=>'integer',
            'daily_bonus_ratio|单份日分红金额' => 'require|float',
            'period|周期' => 'require',
            //'review_period|周期' => 'requireIf:project_group_id,1',
            //'single_gift_equity|单份赠送股权' => 'integer',
            // 'single_gift_digital_yuan|单份赠送国家津贴' => 'integer',
            'is_recommend|是否推荐' => 'require|integer',
            //'give|赠送项目' => 'max:100',
            //'support_pay_methods|支付方式' => 'require|max:100',
            'sort|排序号' => 'integer',
            'sum_amount|总补贴金额' => 'requireIf:project_group_id,1|float',
            'lottery_num|抽奖次数' => 'integer',
            'allow_withdraw_money|可提现金额' => 'integer',
            'week'=>'require',
            'start_time'=>'require',
            'end_time'=>'require',
/*             'virtually_progress|虚拟进度' => 'float',
            'withdrawal_limit|赠送日提现额度' => 'integer',
            'digital_red_package|赠送数字红包' => 'integer',
            'total_quota|总名额' => 'max:32',
            'remaining_quota|剩余名额' => 'max:32',
            'min_flow_amount|最小流转金额' => 'integer',
            'max_flow_amount|最大流转金额' => 'integer',
            'shop_profit|商城盈利' => 'integer',
            'ensure|保障项目' => 'max:32',
            'flow_type|流转方式' => 'max:100',
            'allowed|流转名额' => 'integer',
 */           // 'underline_price|划线价' => 'float',

        ]);
        $req['intro'] = request()->param('intro', '');
        //$methods = explode(',', $req['support_pay_methods']);
        $methods =[1];
/*         if (in_array(5, $methods) && empty($req['gift_integral'])) {
            return out(null, 10001, '支付方式包含积分兑换，单份积分必填');
        } */
        $req['support_pay_methods'] = json_encode($methods);
/*         if(!empty(array_filter($req['give']))){
            $req['give'] = json_encode(array_filter($req['give']));
        }else{
            $req['give'] = 0;
        } */
        
        $req['cover_img'] = upload_file2('cover_img',false,false);
        $req['details_img'] = upload_file2('details_img',false,false);
        Project::create($req);

        return out();
    }

    public function editProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'project_group_id|项目分组ID' => 'require|integer',
            'name|项目名称' => 'require|max:100',
            'single_amount|单份金额' => 'require|float',
            'gift_integral|赠送积分' => 'integer',
            //'total_num|总份数' => 'require|integer',
 /*            'single_gift_digital_yuan|数字人民币' => 'integer',
            'single_gift_gf_purse|共富钱包' => 'integer', */
            'bonus_multiple|养老金倍数'=>'integer',

            'daily_bonus_ratio|单份日分红金额' => 'require|float',
            'period|周期' => 'require',
            //'review_period|周期' => 'requireIf:project_group_id,1',
            //'single_gift_equity|单份赠送股权' => 'integer',
            //'single_gift_digital_yuan|单份赠送国家津贴' => 'integer',
            'is_recommend|是否推荐' => 'require|integer',
            //'give|赠送项目' => 'max:100',
            //'support_pay_methods|支持的支付方式' => 'require|max:100',
            'sort|排序号' => 'integer',
            'sum_amount|总补贴金额' => 'requireIf:project_group_id,1|float',
            //'bonus_multiple|奖励倍数' => 'require|>=:0',
/*             'virtually_progress|虚拟进度' => 'float',
            'withdrawal_limit|赠送日提现额度' => 'integer',
            'digital_red_package|赠送数字红包' => 'integer',
            'total_quota|总名额' => 'max:32',
            'remaining_quota|剩余名额' => 'max:32',
            'min_flow_amount|最小流转金额' => 'integer',
            'max_flow_amount|最大流转金额' => 'integer',
            'shop_profit|商城盈利' => 'integer',
            'ensure|保障项目' => 'max:32',
            'flow_type|流转方式' => 'max:100',
            'allowed|流转名额' => 'integer', */
            //'underline_price|划线价' => 'float',
            'lottery_num|抽奖次数' => 'integer',
            'allow_withdraw_money|可提现金额' => 'integer',
            'is_limited|是否限购' => 'require|integer',
            'max_limited|限购数量' => 'require|integer',
            'min_limited|最小限购数量' => 'require|integer',
            'max_reduce|最大减少数量' => 'require|integer',
            'min_reduce|最小减少数量' => 'require|integer',
            'week'=>'require',
            'start_time'=>'require',
            'end_time'=>'require',

        ]);
        $req['intro'] = request()->param('intro', '');
        $methods =[1];
        //$methods = explode(',', $req['support_pay_methods']);
/*         if (in_array(5, $methods) && empty($req['gift_integral'])) {
            return out(null, 10001, '支付方式包含积分兑换，单份积分必填');
        } */
        $req['support_pay_methods'] = json_encode($methods);
       /*  if(!empty(array_filter($req['give']))){
            $req['give'] = json_encode(array_filter($req['give']));
        }else{
            $req['give'] = 0;
        } */
        if ($img = upload_file2('cover_img', false,false)) {
            $req['cover_img'] = $img;
        }
        if($img = upload_file2('details_img', false,false)){
            $req['details_img'] = $img;
        }
/*         if($req['project_group_id'] == 4 || $req['project_group_id'] == 1 || $req['project_group_id'] == 5 || $req['project_group_id'] == 7) {
            $p = Project::where('id', $req['id'])->find();
            if($p['virtually_progress'] != $req['virtually_progress']) {
                $req['rate_time'] = time();
            }
        } */
        $ret = Project::where('id', $req['id'])->update($req);

        return out();
    }

    public function changeProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        Project::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function delProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        //Project::destroy($req['id']);

        return out();
    }
}
