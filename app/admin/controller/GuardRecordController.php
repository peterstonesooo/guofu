<?php

namespace app\admin\controller;

use app\model\subsidy_butie\DeclareRecord;

class GuardRecordController extends AuthController
{
    // 守护类型值
    const GUARD_TYPE = 2;

    /**
     * 守护记录列表
     */
    public function index()
    {
        $req = request()->param();

        try {
            $data = $this->getGuardRecordList($req);

            // 状态列表
            $statusList = [
                1 => '成功',
                0 => '失败'
            ];

            $this->assign('req', $req);
            $this->assign('data', $data);
            $this->assign('statusList', $statusList);

            return $this->fetch();

        } catch (\Exception $e) {
            $data = [];
            $statusList = [
                1 => '成功',
                0 => '失败'
            ];

            $this->assign('req', $req);
            $this->assign('data', $data);
            $this->assign('statusList', $statusList);

            return $this->fetch();
        }
    }

    /**
     * 获取守护记录列表
     */
    private function getGuardRecordList($params)
    {
        $query = DeclareRecord::with(['user', 'subsidyConfig', 'subsidyType'])
            ->whereHas('subsidyConfig.subsidyType', function ($q) {
                $q->where('type', self::GUARD_TYPE);
            })
            ->order('created_at', 'desc');

        // 搜索条件
        if (!empty($params['subsidy_name'])) {
            $query->whereHas('subsidyConfig', function ($q) use ($params) {
                $q->where('name', 'like', "%{$params['subsidy_name']}%");
            });
        }

        if (!empty($params['user_name'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('username', 'like', "%{$params['user_name']}%");
            });
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        return $query->paginate(['list_rows' => 15, 'query' => $params]);
    }

    /**
     * 查看守护记录详情
     */
    public function detail()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        try {
            $record = DeclareRecord::with([
                'user'          => function ($query) {
                    $query->field('id,username as user_name');
                },
                'subsidyConfig' => function ($query) {
                    $query->field('id,name as subsidy_name,description');
                },
                'subsidyType'   => function ($query) {
                    $query->field('id,name as type_name');
                },
                'funds'         => function ($query) {
                    $query->with(['fundType' => function ($q) {
                        $q->field('id,name as fund_type_name');
                    }]);
                }
            ])->where('id', $req['id'])
                ->whereHas('subsidyConfig.subsidyType', function ($q) {
                    $q->where('type', self::GUARD_TYPE);
                })->find();

            if (!$record) {
                return out('记录不存在或不属于守护类型', 400);
            }

            $this->assign('data', $record->toArray());
            return $this->fetch();

        } catch (\Exception $e) {
            return out('获取详情失败：' . $e->getMessage(), 400);
        }
    }

    /**
     * 导出守护记录
     */
    public function export()
    {
        $req = request()->param();

        try {
            $data = $this->getGuardRecordList($req);

            // 导出Excel逻辑（参考原有申报记录的导出功能）
            // 这里简单示例，实际应根据原有导出功能进行修改

            $filename = '守护记录导出_' . date('YmdHis');
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
            header('Cache-Control: max-age=0');

            $excelData = "ID\t用户名称\t守护名称\t守护类型\t申报金额\t申报周期\t状态\t申报时间\n";

            foreach ($data as $item) {
                $statusText = $item->status == 1 ? '成功' : '失败';
                $excelData .= "{$item->id}\t{$item->user->username}\t{$item->subsidy_config->name}\t{$item->subsidy_type->name}\t{$item->declare_amount}\t{$item->declare_cycle}\t{$statusText}\t{$item->created_at}\n";
            }

            echo iconv('UTF-8', 'GBK', $excelData);
            exit;

        } catch (\Exception $e) {
            return out('导出失败：' . $e->getMessage(), 400);
        }
    }

}