<?php

namespace app\admin\controller;

use app\model\meeting\Meeting;
use app\model\meeting\MeetingSignConfig;
use app\model\meeting\MeetingSignRecords;
use think\facade\Log;
use think\facade\Request;
use think\facade\View;
use think\facade\Cache;
use app\model\User;

class MeetingSignConfigController extends AuthController
{
    // 签到配置管理
    public function index()
    {
        // 获取签到配置（只有一条记录）
        $config = MeetingSignConfig::order('id', 'desc')->find();
        View::assign('config', $config);
        return View::fetch();
    }

    /**
     * 编辑签到奖金配置
     */
    public function editSignConfig()
    {
        $req = $this->validate(Request::param(), [
            'sign_bonus|签到奖金' => 'require|float|min:0',
            'sign_status|签到开关' => 'require|in:0,1',
        ]);

        // 获取并更新唯一的配置记录
        $config = MeetingSignConfig::order('id', 'desc')->find();

        if (!$config) {
            return out(null, 400, '签到配置不存在');
        }

        $config->sign_bonus = $req['sign_bonus'];
        $config->sign_status = $req['sign_status'];
        $config->updated_at = date('Y-m-d H:i:s');
        $config->save();

        // 清除会议相关的缓存
        $this->clearMeetingCache();

        return out(null, 200, '签到配置更新成功');
    }

    /**
     * 清除会议相关缓存
     */
    private function clearMeetingCache()
    {
        try {
            $redis = Cache::store('redis')->handler();
            $patterns = ['meeting_list*', 'meeting_sign_config*'];

            foreach ($patterns as $pattern) {
                $iterator = null;
                do {
                    $keys = $redis->scan($iterator, $pattern, 100);
                    if ($keys !== false && !empty($keys)) {
                        $redis->del($keys);
                    }
                } while ($iterator > 0);
            }
        } catch (\Exception $e) {
            Log::error('清除会议缓存失败: ' . $e->getMessage());
        }
    }

    public function meetingSignRecord()
    {
        $req = Request::param();

        // 检查是否是导出请求
        if (!empty($req['export'])) {
            return $this->exportSignRecordList();
        }

        // 构建查询
        $query = MeetingSignRecords::with(['user', 'meeting'])
            ->alias('sr')
            ->field('sr.*, u.phone, u.realname, m.title as meeting_name')
            ->join('mp_user u', 'u.id = sr.user_id')
            ->join('mp_meeting m', 'm.id = sr.meeting_id', 'LEFT')
            ->order('sr.id', 'desc');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            if (!empty($user_ids)) {
                $query->whereIn('sr.user_id', $user_ids);
            } else {
                $query->where('sr.user_id', 0); // 确保没有结果
            }
        }

        if (isset($req['meeting_id']) && $req['meeting_id'] !== '') {
            $query->where('sr.meeting_id', $req['meeting_id']);
        }

        if (isset($req['status']) && $req['status'] !== '') {
            $query->where('sr.status', $req['status']);
        }

        if (!empty($req['start_date'])) {
            $query->where('sr.sign_date', '>=', $req['start_date']);
        }

        if (!empty($req['end_date'])) {
            $query->where('sr.sign_date', '<=', $req['end_date']);
        }

        // 获取数据并处理状态文本
        $data = $query->paginate(['list_rows' => 15, 'query' => $req]);

        // 处理状态文本和会议名称
        $data->each(function ($item) {
            $item->status_text = $item->status_text;
            // 如果没有关联会议，显示独立签到
            if (empty($item->meeting_name)) {
                $item->meeting_name = '日常签到';
            }
            return $item;
        });

        // 会议下拉
        $meetings = Meeting::where('status', 1)->select();
        View::assign('meetings', $meetings);
        View::assign('req', $req);
        View::assign('data', $data);

        return View::fetch();
    }

    /**
     * 导出签到记录
     */
    public function exportSignRecordList()
    {
        $req = Request::param();

        $builder = MeetingSignRecords::alias('sr')
            ->field('sr.*, u.phone, u.realname, m.title as meeting_name')
            ->join('mp_user u', 'u.id = sr.user_id')
            ->join('mp_meeting m', 'm.id = sr.meeting_id', 'LEFT');

        // 搜索条件
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone|realname', 'like', "%{$req['user']}%")->column('id');
            if (!empty($user_ids)) {
                $builder->whereIn('sr.user_id', $user_ids);
            } else {
                $builder->where('sr.user_id', 0); // 确保没有结果
            }
        }

        if (isset($req['meeting_id']) && $req['meeting_id'] !== '') {
            $builder->where('sr.meeting_id', $req['meeting_id']);
        }

        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('sr.status', $req['status']);
        }

        if (!empty($req['start_date'])) {
            $builder->where('sr.sign_date', '>=', $req['start_date']);
        }

        if (!empty($req['end_date'])) {
            $builder->where('sr.sign_date', '<=', $req['end_date']);
        }

        // 获取数据
        $data = $builder->order('sr.id', 'desc')->select();

        // 处理数据
        $exportData = [];
        foreach ($data as $item) {
            $meetingName = $item->meeting_name ?: '日常签到';
            $statusText = $item->status == 1 ? '成功' : '失败';

            $exportData[] = [
                'id' => $item->id,
                'phone' => $item->phone,
                'realname' => $item->realname,
                'meeting_name' => $meetingName,
                'bonus_amount' => $item->bonus_amount,
                'sign_date' => $item->sign_date,
                'status_text' => $statusText,
                'remark' => $item->remark,
                'created_at' => $item->created_at,
            ];
        }

        // 表头
        $header = [
            'id' => 'ID',
            'phone' => '用户手机号',
            'realname' => '用户姓名',
            'meeting_name' => '会议名称',
            'bonus_amount' => '奖励金额',
            'sign_date' => '签到日期',
            'status_text' => '状态',
            'remark' => '备注',
            'created_at' => '创建时间',
        ];

        // 导出Excel
        $filename = '会议签到记录-' . date('YmdHis');
        create_excel($exportData, $header, $filename);
    }
}