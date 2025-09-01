<?php

namespace app\admin\controller;


use app\model\AdminOperations;
use app\model\StockTypes;
use app\model\UserStockWallets;
use think\facade\Cache;
use think\facade\Db;
use think\facade\View;

// 添加Redis支持

class StockController extends AuthController
{
    // 全局股价Redis键名
    const GLOBAL_STOCK_PRICE_KEY = 'global_stock_price';

    public function index()
    {
        if ($this->request->isAjax()) {
            $params = $this->request->only(['page', 'limit', 'keyword']);
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 15;
            $keyword = $params['keyword'] ?? '';

            $query = (new StockTypes());

            if (!empty($keyword)) {
                $query->where('name|code', 'like', "%{$keyword}%");
            }

            $list = $query->paginate([
                'list_rows' => $limit,
                'page'      => $page
            ]);

            // 从Redis获取全局股价
            $globalPrice = Cache::get(self::GLOBAL_STOCK_PRICE_KEY, 0);

            // 为每个股权添加全局价格
            $list->each(function ($item) use ($globalPrice) {
                $item->price = $globalPrice;
                return $item;
            });

            return json([
                'code'            => 0,
                'msg'             => '',
                'draw'            => $this->request->param('draw/d', 1),
                'recordsTotal'    => (int)$list->total(),
                'recordsFiltered' => (int)$list->total(),
                'data'            => $list->items()
            ]);
        }

        return View::fetch();
    }


    //实时股价调整
    public function adjustPrice()
    {
        if ($this->request->isPost()) {
            $price = $this->request->param('price/f', 0);

            try {
                Db::startTrans();

                // 获取调整前的价格
                $oldPrice = Cache::get(self::GLOBAL_STOCK_PRICE_KEY, 0);

                // 更新Redis中的全局股价
                Cache::set(self::GLOBAL_STOCK_PRICE_KEY, $price);

                // 记录操作日志
                AdminOperations::create([
                    'admin_id'    => $this->adminUser->id,
                    'type'        => 'adjust_global_stock_price',
                    'target_id'   => 0,
                    'target_type' => 'global',
                    'before_data' => json_encode(['price' => $oldPrice]),
                    'after_data'  => json_encode(['price' => $price]),
                    'ip'          => $this->request->ip(),
                    'remark'      => "调整全局股权价格",
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s')
                ]);

                Db::commit();
                return json(['code' => 1, 'msg' => '全局股价调整成功']);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '股价调整失败: ' . $e->getMessage()]);
            }
        }

        // 获取当前全局股价
        $currentPrice = Cache::get(self::GLOBAL_STOCK_PRICE_KEY, 0);

        return json([
            'code' => 1,
            'msg'  => '成功',
            'data' => [
                'price' => $currentPrice
            ]
        ]);
    }

    // 批量增加原始股权
    public function batchAdd()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(['stock_type_id', 'user_ids', 'quantity']);

            try {
                Db::startTrans();

                $stock = StockTypes::find($data['stock_type_id']);
                if (!$stock) {
                    throw new \Exception('股权不存在');
                }

                $userIds = explode(',', $data['user_ids']);
                $successCount = 0;

                foreach ($userIds as $userId) {
                    $userId = trim($userId);
                    if (empty($userId))
                        continue;

                    // 查找或创建用户股权钱包
                    $wallet = UserStockWallets::where([
                        'user_id'       => $userId,
                        'stock_type_id' => $data['stock_type_id']
                    ])->find();

                    if ($wallet) {
                        $beforeQuantity = $wallet->original_quantity;
                        $wallet->original_quantity += $data['quantity'];
                        $wallet->save();
                    } else {
                        $beforeQuantity = 0;
                        $wallet = UserStockWallets::create([
                            'user_id'              => $userId,
                            'stock_type_id'        => $data['stock_type_id'],
                            'original_quantity'    => $data['quantity'],
                            'circulating_quantity' => 0,
                            'purchased_quantity'   => 0,
                            'created_at'           => date('Y-m-d H:i:s'),
                            'updated_at'           => date('Y-m-d H:i:s')
                        ]);
                    }

                    // 记录操作日志
                    AdminOperations::create([
                        'admin_id'    => $this->adminUser->id,
                        'type'        => 'add_original_stock',
                        'target_id'   => $userId,
                        'target_type' => 'user_stock_wallets',
                        'before_data' => json_encode(['original_quantity' => $beforeQuantity]),
                        'after_data'  => json_encode(['original_quantity' => $wallet->original_quantity]),
                        'ip'          => $this->request->ip(),
                        'remark'      => "为用户[{$userId}]增加股权[{$stock->name}] {$data['quantity']}股",
                        'created_at'  => date('Y-m-d H:i:s'),
                        'updated_at'  => date('Y-m-d H:i:s')
                    ]);

                    $successCount++;
                }

                // 更新总发行数量
                $stock->total_shares += ($data['quantity'] * count($userIds));
                $stock->save();

                Db::commit();
                return json(['code' => 1, 'msg' => "成功为{$successCount}个用户增加原始股权"]);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()]);
            }
        }

        $id = $this->request->param('id/d', 0);
        $stock = StockTypes::find($id);
        if (!$stock) {
            $this->error('股权不存在');
        }

        View::assign('stock', $stock);
        return View::fetch();
    }

    // 调整流通股权数量
    public function adjustCirculating()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(['user_id', 'stock_type_id', 'quantity']);

            try {
                Db::startTrans();

                // 查找或创建用户股权钱包
                $wallet = UserStockWallets::where([
                    'user_id'       => $data['user_id'],
                    'stock_type_id' => $data['stock_type_id']
                ])->find();

                if (!$wallet) {
                    throw new \Exception('用户股权钱包不存在');
                }

                $beforeQuantity = $wallet->circulating_quantity;
                $wallet->circulating_quantity = $data['quantity'];
                $wallet->save();

                // 记录操作日志
                $stock = StockTypes::find($data['stock_type_id']);
                AdminOperations::create([
                    'admin_id'    => $this->adminUser->id,
                    'type'        => 'adjust_circulating_stock',
                    'target_id'   => $data['user_id'],
                    'target_type' => 'user_stock_wallets',
                    'before_data' => json_encode(['circulating_quantity' => $beforeQuantity]),
                    'after_data'  => json_encode(['circulating_quantity' => $data['quantity']]),
                    'ip'          => $this->request->ip(),
                    'remark'      => "调整用户[{$data['user_id']}]股权[{$stock->name}]流通数量",
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s')
                ]);

                Db::commit();
                return json(['code' => 1, 'msg' => '流通股权数量调整成功']);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()]);
            }
        }

        $id = $this->request->param('id/d', 0);
        $userId = $this->request->param('user_id/d', 0);

        $wallet = UserStockWallets::with(['stockType'])
            ->where('id', $id)
            ->find();

        if (!$wallet) {
            $this->error('记录不存在');
        }

        View::assign('wallet', $wallet);
        return View::fetch();
    }

    // 调整买入股权数量
    public function adjustPurchased()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(['user_id', 'stock_type_id', 'quantity']);

            try {
                Db::startTrans();

                // 查找或创建用户股权钱包
                $wallet = UserStockWallets::where([
                    'user_id'       => $data['user_id'],
                    'stock_type_id' => $data['stock_type_id']
                ])->find();

                if (!$wallet) {
                    throw new \Exception('用户股权钱包不存在');
                }

                $beforeQuantity = $wallet->purchased_quantity;
                $wallet->purchased_quantity = $data['quantity'];
                $wallet->save();

                // 记录操作日志
                $stock = StockTypes::find($data['stock_type_id']);
                AdminOperations::create([
                    'admin_id'    => $this->adminUser->id,
                    'type'        => 'adjust_purchased_stock',
                    'target_id'   => $data['user_id'],
                    'target_type' => 'user_stock_wallets',
                    'before_data' => json_encode(['purchased_quantity' => $beforeQuantity]),
                    'after_data'  => json_encode(['purchased_quantity' => $data['quantity']]),
                    'ip'          => $this->request->ip(),
                    'remark'      => "调整用户[{$data['user_id']}]股权[{$stock->name}]买入数量",
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s')
                ]);

                Db::commit();
                return json(['code' => 1, 'msg' => '买入股权数量调整成功']);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()]);
            }
        }

        $id = $this->request->param('id/d', 0);
        $userId = $this->request->param('user_id/d', 0);

        $wallet = UserStockWallets::with(['stockType'])
            ->where('id', $id)
            ->find();

        if (!$wallet) {
            $this->error('记录不存在');
        }

        View::assign('wallet', $wallet);
        return View::fetch();
    }

    // 用户股权钱包列表
    public function wallets()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->param('page/d', 1);
            $limit = $this->request->param('limit/d', 15);
            $keyword = $this->request->param('keyword/s', '');
            $stockTypeId = $this->request->param('stock_type_id/d', 0);

            $query = UserStockWallets::with(['stockType', 'user']);

            if (!empty($keyword)) {
                $query->hasWhere('user', function ($q) use ($keyword) {
                    $q->where('username|mobile', 'like', "%{$keyword}%");
                });
            }

            if ($stockTypeId > 0) {
                $query->where('stock_type_id', $stockTypeId);
            }

            $list = $query->paginate([
                'list_rows' => $limit,
                'page'      => $page
            ]);

            // 从Redis获取全局股价
            $globalPrice = Cache::get(self::GLOBAL_STOCK_PRICE_KEY, 0);

            $data = [];
            foreach ($list->items() as $item) {
                $data[] = [
                    'id'                   => $item->id,
                    'user_id'              => $item->user_id,
                    'username'             => $item->user->username ?? '',
                    'mobile'               => $item->user->mobile ?? '',
                    'stock_name'           => $item->stockType->name,
                    'stock_code'           => $item->stockType->code,
                    'original_quantity'    => $item->original_quantity,
                    'circulating_quantity' => $item->circulating_quantity,
                    'purchased_quantity'   => $item->purchased_quantity,
                    'price'                => $globalPrice, // 使用全局价格
                    'total_value'          => bcmul($item->original_quantity, $globalPrice, 2),
                    'created_at'           => $item->created_at,
                    'updated_at'           => $item->updated_at
                ];
            }

            return json([
                'code'  => 0,
                'msg'   => '',
                'count' => $list->total(),
                'data'  => $data
            ]);
        }

        $stockTypes = StockTypes::select();
        View::assign('stockTypes', $stockTypes);
        return View::fetch();
    }

    public function setStatus()
    {
        $id = $this->request->param('id/d', 0);
        $status = $this->request->param('status/d', 1);

        $stock = StockTypes::find($id);
        if (!$stock) {
            return json(['code' => 0, 'msg' => '股权不存在']);
        }

        try {
            $beforeStatus = $stock->status;
            $stock->status = $status;
            $stock->save();

            // 记录操作日志
            AdminOperations::create([
                'admin_id'    => $this->adminUser->id,
                'type'        => 'change_stock_status',
                'target_id'   => $id,
                'target_type' => 'stock_types',
                'before_data' => json_encode(['status' => $beforeStatus]),
                'after_data'  => json_encode(['status' => $status]),
                'ip'          => $this->request->ip(),
                'remark'      => "修改股权[{$stock->name}]状态",
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s')
            ]);

            return json(['code' => 1, 'msg' => '状态修改成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()]);
        }
    }

// 删除股权
    public function delete()
    {
        $id = $this->request->param('id/d', 0);

        $stock = StockTypes::find($id);
        if (!$stock) {
            return json(['code' => 0, 'msg' => '股权不存在']);
        }

        try {
            Db::startTrans();

            // 检查是否有关联的用户股权钱包
            $count = UserStockWallets::where('stock_type_id', $id)->count();
            if ($count > 0) {
                throw new \Exception('该股权已被用户持有，无法删除');
            }

            // 删除股价记录
            Db::name('stock_prices')->where('stock_type_id', $id)->delete();

            // 删除股权
            $stock->delete();

            // 记录操作日志
            AdminOperations::create([
                'admin_id'    => $this->adminUser->id,
                'type'        => 'delete_stock',
                'target_id'   => $id,
                'target_type' => 'stock_types',
                'before_data' => json_encode($stock),
                'after_data'  => json_encode([]),
                'ip'          => $this->request->ip(),
                'remark'      => "删除股权[{$stock->name}]",
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

    public function getStock()
    {
        $id = $this->request->param('id/d', 0);
        $stock = StockTypes::find($id);

        if (!$stock) {
            return json(['code' => 0, 'msg' => '股权不存在']);
        }

        return json(['code' => 1, 'data' => $stock]);
    }

    // 编辑股权（更新总数量）
    public function edit()
    {
        $id = $this->request->param('id/d', 0);
        $stock = StockTypes::find($id);

        if (!$stock) {
            $this->error('股权不存在');
        }

        if ($this->request->isPost()) {
            $data = $this->request->only(['name', 'total_shares']);

            try {
                $stock->name = $data['name'];
                $stock->total_shares = $data['total_shares'];
                $stock->save();

                return json(['code' => 1, 'msg' => '股权更新成功']);
            } catch (\Exception $e) {
                return json(['code' => 0, 'msg' => '更新失败: ' . $e->getMessage()]);
            }
        }

        View::assign('stock', $stock);
        return View::fetch();
    }
}