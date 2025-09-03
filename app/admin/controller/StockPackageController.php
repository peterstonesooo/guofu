<?php

namespace app\admin\controller;

use app\model\AdminOperations;
use app\model\StockPackageItems;
use app\model\StockPackages;
use app\model\StockTypes;
use think\facade\Db;
use think\facade\View;

class StockPackageController extends AuthController
{
    // 股权方案列表
    public function index()
    {
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 15);
        $keyword = $this->request->param('keyword/s', '');

        $query = StockPackages::order('id', 'desc');

        if (!empty($keyword)) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        $list = $query->paginate([
            'list_rows' => $limit,
            'page'      => $page
        ]);

        View::assign([
            'stock_list' => $list,
            'keyword'    => $keyword
        ]);

        return View::fetch();
    }

    // 添加股权方案
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(['name', 'lock_period', 'daily_sell_limit', 'status', 'stock_items']);

            // 验证数据
            if (empty($data['name'])) {
                return json(['code' => 0, 'msg' => '股权方案名称不能为空']);
            }
            if (!isset($data['price']) || $data['price'] < 0) {
                return json(['code' => 0, 'msg' => '股权方案价格不能为负数']);
            }
            if (empty($data['stock_items']) || !is_array($data['stock_items'])) {
                return json(['code' => 0, 'msg' => '请至少添加一个股权类型']);
            }

            try {
                Db::startTrans();

                $package = new StockPackages();
                $package->name = $data['name'];
                $package->price = $data['price'] ?? 0;
                $package->lock_period = $data['lock_period'] ?? 0;
                $package->daily_sell_limit = $data['daily_sell_limit'] ?? 0;
                $package->status = $data['status'] ?? 1;
                $package->save();

                // 添加股权方案项
                foreach ($data['stock_items'] as $item) {
                    $stockTypeId = $item['stock_type_id'] ?? 0;
                    $quantity = $item['quantity'] ?? 0;

                    if ($stockTypeId <= 0 || $quantity <= 0) {
                        throw new \Exception('股权配置信息不完整');
                    }
                    // 获取股权类型代码
                    $stockType = StockTypes::find($stockTypeId);
                    if (!$stockType) {
                        throw new \Exception('股权类型不存在');
                    }


                    $packageItem = new StockPackageItems();
                    $packageItem->package_id = $package->id;
                    $packageItem->stock_type_id = $stockTypeId;
                    $packageItem->stock_code = $stockType->code;
                    $packageItem->quantity = $quantity;
                    $packageItem->save();
                }

                // 记录操作日志
                AdminOperations::create([
                    'admin_id'    => $this->adminUser->id,
                    'type'        => 'add_stock_package',
                    'target_id'   => $package->id,
                    'target_type' => 'stock_package',
                    'before_data' => '',
                    'after_data'  => json_encode($package->toArray()),
                    'ip'          => $this->request->ip(),
                    'remark'      => "添加股权股权方案: {$data['name']}",
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s')
                ]);

                Db::commit();
                return json(['code' => 1, 'msg' => '添加成功']);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '添加失败: ' . $e->getMessage()]);
            }
        }

        // 获取所有股权类型
        $stockTypes = StockTypes::where('status', 1)->select()->toArray();
        View::assign('stockTypes', $stockTypes);
        return View::fetch();
    }

    // 编辑股权方案
    public function edit()
    {
        $id = $this->request->param('id/d', 0);
        $package = StockPackages::find($id);
        if (!$package) {
            return json(['code' => 0, 'msg' => '股权方案不存在']);
        }

        if ($this->request->isPost()) {
            $data = $this->request->only(['name', 'lock_period', 'daily_sell_limit', 'status', 'stock_items']);

            // 验证数据
            if (empty($data['name'])) {
                return json(['code' => 0, 'msg' => '股权方案名称不能为空']);
            }
            if (!isset($data['price']) || $data['price'] < 0) {
                return json(['code' => 0, 'msg' => '股权方案价格不能为负数']);
            }
            if (empty($data['stock_items']) || !is_array($data['stock_items'])) {
                return json(['code' => 0, 'msg' => '请至少添加一个股权类型']);
            }

            try {
                Db::startTrans();

                $beforeData = $package->toArray();

                $package->name = $data['name'];
                $package->price = $data['price'] ?? 0;
                $package->lock_period = $data['lock_period'] ?? 0;
                $package->daily_sell_limit = $data['daily_sell_limit'] ?? 0;
                $package->status = $data['status'] ?? 1;
                $package->save();

                // 删除原有关联
                StockPackageItems::where('package_id', $package->id)->delete();

                // 重新添加股权方案项
                foreach ($data['stock_items'] as $item) {
                    $stockTypeId = $item['stock_type_id'] ?? 0;
                    $type = $item['type'] ?? 0;
                    $quantity = $item['quantity'] ?? 0;

                    if ($stockTypeId <= 0 || $type <= 0 || $quantity <= 0) {
                        throw new \Exception('股权配置信息不完整');
                    }

                    // 获取股权类型代码
                    $stockType = StockTypes::find($stockTypeId);
                    if (!$stockType) {
                        throw new \Exception('股权类型不存在');
                    }

                    $packageItem = new StockPackageItems();
                    $packageItem->package_id = $package->id;
                    $packageItem->stock_type_id = $stockTypeId;
                    $packageItem->stock_code = $stockType->code;
                    $packageItem->quantity = $quantity;
                    $packageItem->save();
                }

                // 记录操作日志
                AdminOperations::create([
                    'admin_id'    => $this->adminUser->id,
                    'type'        => 'edit_stock_package',
                    'target_id'   => $package->id,
                    'target_type' => 'stock_package',
                    'before_data' => json_encode($beforeData),
                    'after_data'  => json_encode($package->toArray()),
                    'ip'          => $this->request->ip(),
                    'remark'      => "编辑股权股权方案: {$data['name']}",
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s')
                ]);

                Db::commit();
                return json(['code' => 1, 'msg' => '编辑成功']);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '编辑失败: ' . $e->getMessage()]);
            }
        }

        // 获取股权方案项
        $packageItems = StockPackageItems::where('package_id', $package->id)
            ->select()
            ->toArray();

        // 获取所有股权类型
        $stockTypes = StockTypes::where('status', 1)->select();
        View::assign([
            'package'      => $package->toArray(),
            'packageItems' => $packageItems,  // 直接传递数组
            'stockTypes'   => $stockTypes
        ]);
        return View::fetch();
    }

    // 删除股权方案
    public function delete()
    {
        $id = $this->request->param('id/d', 0);
        $package = StockPackages::find($id);
        if (!$package) {
            return json(['code' => 0, 'msg' => '股权方案不存在']);
        }

        try {
            Db::startTrans();

            // 删除股权方案项
            StockPackageItems::where('package_id', $package->id)->delete();

            // 删除股权方案
            $package->delete();

            // 记录操作日志
            AdminOperations::create([
                'admin_id'    => $this->adminUser->id,
                'type'        => 'delete_stock_package',
                'target_id'   => $id,
                'target_type' => 'stock_package',
                'before_data' => json_encode($package),
                'after_data'  => '',
                'ip'          => $this->request->ip(),
                'remark'      => "删除股权股权方案: {$package->name}",
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '删除失败: ' . $e->getMessage()]);
        }
    }

    // 设置状态
    public function setStatus()
    {
        $id = $this->request->param('id/d', 0);
        $status = $this->request->param('status/d', 1);

        $package = StockPackages::find($id);
        if (!$package) {
            return json(['code' => 0, 'msg' => '股权方案不存在']);
        }

        $beforeStatus = $package->status;
        $package->status = $status;
        $package->save();

        // 记录操作日志
        AdminOperations::create([
            'admin_id'    => $this->adminUser->id,
            'type'        => 'change_stock_package_status',
            'target_id'   => $id,
            'target_type' => 'stock_package',
            'before_data' => json_encode(['status' => $beforeStatus]),
            'after_data'  => json_encode(['status' => $status]),
            'ip'          => $this->request->ip(),
            'remark'      => "修改股权股权方案[{$package->name}]状态",
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 1, 'msg' => '状态修改成功']);
    }
}