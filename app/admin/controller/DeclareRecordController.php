<?php

namespace app\admin\controller;

use app\model\subsidy_butie\DeclareRecord;
use think\facade\Cache;
use think\facade\View;

class DeclareRecordController extends AuthController
{
    // 缓存键名
    const CACHE_KEY = 'declare_record_list';

    /**
     * 申报记录列表
     */
    public function index()
    {
        $req = request()->param();

        try {
            $cacheKey = self::CACHE_KEY . ':admin:' . md5(serialize($req));
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                $data = unserialize($cachedData);
            } else {
                $data = DeclareRecord::getList($req);
                $redis->setex($cacheKey, 600, serialize($data));
            }

            View::assign([
                'req'        => $req,
                'data'       => $data,
                'statusList' => $this->getStatusList()
            ]);

            return View::fetch();

        } catch (\Exception $e) {
            $data = DeclareRecord::getList($req);

            View::assign([
                'req'        => $req,
                'data'       => $data,
                'statusList' => $this->getStatusList()
            ]);

            return View::fetch();
        }
    }

    /**
     * 显示申报记录详情
     */
    public function show()
    {
        $req = request()->param();
        $data = [];

        if (!empty($req['id'])) {
            $data = DeclareRecord::getDetail($req['id']);
        }

        View::assign('data', $data);
        return View::fetch();
    }

    /**
     * 导出申报记录
     */
    public function export()
    {
        $req = request()->param();

        try {
            $data = DeclareRecord::with([
                'user' => function ($q) {
                    $q->field('id,realname'); // 使用 realname
                },
                'subsidyConfig',
                'subsidyType'
            ])->order('created_at', 'desc')
                ->select();

            $exportData = [];
            foreach ($data as $item) {
                $exportData[] = [
                    'id'             => $item->id,
                    'user_name'      => $item->user->realname ?? '', // 修改为 realname
                    'subsidy_name'   => $item->subsidyConfig->name ?? '',
                    'subsidy_type'   => $item->subsidyType->name ?? '', // 通过关联获取补贴类型
                    'declare_amount' => $item->declare_amount,
                    'declare_cycle'  => $item->declare_cycle . '天',
                    'status_text'    => $item->status_text,
                    'created_at'     => $item->created_at,
                ];
            }

            // 表头
            $header = [
                'id'             => 'ID',
                'user_name'      => '用户名称',
                'subsidy_name'   => '补贴名称',
                'subsidy_type'   => '补贴类型',
                'declare_amount' => '申报金额',
                'declare_cycle'  => '申报周期',
                'status_text'    => '状态',
                'created_at'     => '申报时间',
            ];

            $filename = '申报记录_' . date('YmdHis');
            create_excel($exportData, $header, $filename);

        } catch (\Exception $e) {
            return out('导出失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 获取状态列表
     */
    private function getStatusList()
    {
        return [
            DeclareRecord::STATUS_SUCCESS => '成功',
            DeclareRecord::STATUS_FAILED  => '失败'
        ];
    }

    /**
     * 清除缓存
     */
    private function clearCache()
    {
        try {
            $redis = Cache::store('redis')->handler();
            $pattern = self::CACHE_KEY . '*';

            $iterator = null;
            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if ($keys !== false && !empty($keys)) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);

        } catch (\Exception $e) {
            \think\facade\Log::error('清除申报记录缓存失败: ' . $e->getMessage());
        }
    }
}