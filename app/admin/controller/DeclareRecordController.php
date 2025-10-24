<?php

namespace app\admin\controller;

use app\model\subsidy_butie\DeclareRecord;
use think\facade\Cache;

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

            $this->assign('req', $req);
            $this->assign('data', $data);
            $this->assign('statusList', [
                '0' => '待审核',
                '1' => '审核通过',
                '2' => '审核不通过'
            ]);

            return $this->fetch();

        } catch (\Exception $e) {
            $data = DeclareRecord::getList($req);

            $this->assign('req', $req);
            $this->assign('data', $data);
            $this->assign('statusList', [
                '0' => '待审核',
                '1' => '审核通过',
                '2' => '审核不通过'
            ]);

            return $this->fetch();
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

        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 审核申报记录
     */
    public function audit()
    {
        $req = $this->validate(request(), [
            'id'                    => 'require|number',
            'status|审核状态'       => 'require|in:1,2',
            'audit_remark|审核备注' => 'max:500'
        ]);

        try {
            $record = DeclareRecord::find($req['id']);
            if (!$record) {
                return out('申报记录不存在', 400);
            }

            if ($record->status != DeclareRecord::STATUS_PENDING) {
                return out('该记录已审核，不能重复审核', 400);
            }

            $record->save([
                'status'        => $req['status'],
                'audit_remark'  => $req['audit_remark'] ?? '',
                'audit_time'    => date('Y-m-d H:i:s'),
                'audit_user_id' => session('admin_id'),
            ]);

            return out();

        } catch (\Exception $e) {
            return out('审核失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 批量审核
     */
    public function batchAudit()
    {
        $req = $this->validate(request(), [
            'ids'                   => 'require|array',
            'status|审核状态'       => 'require|in:1,2',
            'audit_remark|审核备注' => 'max:500'
        ]);

        Db::startTrans();
        try {
            $successCount = 0;
            $failCount = 0;

            foreach ($req['ids'] as $id) {
                try {
                    $record = DeclareRecord::find($id);
                    if ($record && $record->status == DeclareRecord::STATUS_PENDING) {
                        $record->save([
                            'status'        => $req['status'],
                            'audit_remark'  => $req['audit_remark'] ?? '',
                            'audit_time'    => date('Y-m-d H:i:s'),
                            'audit_user_id' => session('admin_id'),
                        ]);
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                } catch (\Exception $e) {
                    $failCount++;
                }
            }

            Db::commit();

            $message = "审核完成：成功 {$successCount} 条，失败 {$failCount} 条";
            return out($message);

        } catch (\Exception $e) {
            Db::rollback();
            return out('批量审核失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 导出申报记录
     */
    public function export()
    {
        $req = request()->param();

        try {
            $data = DeclareRecord::with(['subsidyConfig', 'subsidyType', 'user'])
                ->order('created_at', 'desc')
                ->select();

            // 设置响应头
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="申报记录_' . date('YmdHis') . '.xls"');
            header('Cache-Control: max-age=0');

            // 输出Excel内容
            echo "<table border='1'>";
            echo "<tr>
                    <th>ID</th>
                    <th>用户名称</th>
                    <th>补贴名称</th>
                    <th>补贴类型</th>
                    <th>申报金额</th>
                    <th>申报周期</th>
                    <th>状态</th>
                    <th>申报时间</th>
                  </tr>";

            foreach ($data as $item) {
                $statusText = '';
                switch ($item->status) {
                    case DeclareRecord::STATUS_PENDING:
                        $statusText = '待审核';
                        break;
                    case DeclareRecord::STATUS_APPROVED:
                        $statusText = '审核通过';
                        break;
                    case DeclareRecord::STATUS_REJECTED:
                        $statusText = '审核不通过';
                        break;
                }

                echo "<tr>
                        <td>{$item->id}</td>
                        <td>{$item->user->username ?? ''}</td>
                        <td>{$item->subsidyConfig->name ?? ''}</td>
                        <td>{$item->subsidyType->name ?? ''}</td>
                        <td>{$item->declare_amount}</td>
                        <td>{$item->declare_cycle}天</td>
                        <td>{$statusText}</td>
                        <td>{$item->created_at}</td>
                      </tr>";
            }
            echo "</table>";
            exit;

        } catch (\Exception $e) {
            return out('导出失败：' . $e->getMessage(), 400);
        }
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