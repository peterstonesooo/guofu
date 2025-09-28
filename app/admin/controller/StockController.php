<?php

namespace app\admin\controller;


use app\model\AdminOperations;
use app\model\StockTypes;
use app\model\User;
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


            if (!empty($keyword)) {
                $query = StockTypes::where('name|code', 'like', "%{$keyword}%");
            } else {
                $query = (new StockTypes());
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
    // 新增Excel批量导入功能
    public function batchImport()
    {
        if ($this->request->isPost()) {
            $file = $this->request->file('excel_file');
            $stockTypeId = $this->request->param('stock_type_id/d', 0);

            if (!$file) {
                return json(['code' => 0, 'msg' => '请上传文件']);
            }

            $stock = StockTypes::find($stockTypeId);
            if (!$stock) {
                return json(['code' => 0, 'msg' => '股权不存在']);
            }

            try {
                $extension = $file->getOriginalExtension();
                if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                    throw new \Exception('文件格式不支持');
                }

                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getPathname());
                $spreadsheet = $reader->load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                // 移除标题行
                array_shift($rows);

                // 限制1000条
                if (count($rows) > 1000) {
                    throw new \Exception('单次导入不能超过1000条记录');
                }

                Db::startTrans();

                $successCount = 0;
                $errorReasons = [];
                $globalPrice = Cache::get(self::GLOBAL_STOCK_PRICE_KEY, 0);

                foreach ($rows as $index => $row) {
                    $rowNumber = $index + 2; // 实际行号

                    if (empty($row[0])) {
                        $errorReasons[] = "第{$rowNumber}行：手机号码不能为空";
                        continue;
                    }

                    $phone = trim($row[0]);
                    $operation = trim($row[1] ?? '+');
                    $quantity = $row[2] ?? 0;

                    // 通过手机号查找用户
                    $user = User::where('phone', $phone)->find();
                    if (!$user) {
                        $errorReasons[] = "第{$rowNumber}行：手机号码 {$phone} 不存在";
                        continue;
                    }

                    $userId = $user->id;

                    // 验证数量必须是整数
                    if (!is_numeric($quantity) || $quantity != (int)$quantity || $quantity <= 0) {
                        $errorReasons[] = "第{$rowNumber}行：数量必须是大于0的整数";
                        continue;
                    }

                    $quantity = (int)$quantity; // 强制转换为整数

                    // 查找或创建钱包
                    $wallet = UserStockWallets::where([
                        'user_id'       => $userId,
                        'stock_type_id' => $stockTypeId
                    ])->find();

                    $beforeQuantity = 0;
                    if ($wallet) {
                        $beforeQuantity = $wallet->quantity;
                    } else {
                        $wallet = new UserStockWallets();
                        $wallet->user_id = $userId;
                        $wallet->stock_type_id = $stockTypeId;
                        $wallet->quantity = 0;
                    }

                    // 处理操作
                    if ($operation === '+') {
                        // 检查总发行量是否足够
                        if ($stock->total_shares < $quantity) {
                            $errorReasons[] = "第{$rowNumber}行：股权池数量不足（剩余 {$stock->total_shares} 股）";
                            continue;
                        }

                        // 从股权池中扣除
                        $stock->total_shares -= $quantity;

                        // 增加用户股权
                        $wallet->quantity += $quantity;

                        // 记录交易记录（增加）
                        Db::name('stock_transactions')->insert([
                            'user_id'       => $userId,
                            'stock_type_id' => $stockTypeId,
                            'type'          => 1, // 1=买入
                            'source'        => 0,
                            'quantity'      => $quantity,
                            'price'         => $globalPrice,
                            'amount'        => bcmul($quantity, $globalPrice, 2),
                            'status'        => 1, // 成功
                            'remark'        => "批量导入增加股权",
                            'created_at'    => date('Y-m-d H:i:s'),
                            'updated_at'    => date('Y-m-d H:i:s')
                        ]);
                    } else if ($operation === '-') {
                        // 检查用户钱包是否足够
                        if ($wallet->quantity < $quantity) {
                            $errorReasons[] = "第{$rowNumber}行：用户股权数量不足（当前持有 {$wallet->quantity} 股）";
                            continue;
                        }

                        // 减少用户股权
                        $wallet->quantity -= $quantity;

                        // 回收到股权池中
                        $stock->total_shares += $quantity;

                        // 记录交易记录（减少）
                        Db::name('stock_transactions')->insert([
                            'user_id'       => $userId,
                            'stock_type_id' => $stockTypeId,
                            'type'          => 2, // 2=卖出
                            'source'        => 0,
                            'quantity'      => $quantity,
                            'price'         => $globalPrice,
                            'amount'        => bcmul($quantity, $globalPrice, 2),
                            'status'        => 1, // 成功
                            'remark'        => "批量导入减少股权",
                            'created_at'    => date('Y-m-d H:i:s'),
                            'updated_at'    => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $errorReasons[] = "第{$rowNumber}行：操作类型无效（只能为+/-）";
                        continue;
                    }

                    // 保存钱包和股权池状态
                    $wallet->save();
                    $stock->save(); // 每次操作后立即更新股权池

                    $successCount++;

                    // 记录操作日志
                    AdminOperations::create([
                        'admin_id'    => $this->adminUser->id,
                        'type'        => 'batch_import_stock',
                        'target_id'   => $userId,
                        'target_type' => 'user_stock_wallets',
                        'before_data' => json_encode([
                            'wallet_quantity' => $beforeQuantity,
                            'total_shares'    => $beforeQuantity + ($operation === '+' ? $quantity : -$quantity)
                        ]),
                        'after_data'  => json_encode([
                            'wallet_quantity' => $wallet->quantity,
                            'total_shares'    => $stock->total_shares
                        ]),
                        'ip'          => $this->request->ip(),
                        'remark'      => "导入{$operation}用户[{$phone}]股权[{$stock->name}] {$quantity}股",
                        'created_at'  => date('Y-m-d H:i:s'),
                        'updated_at'  => date('Y-m-d H:i:s')
                    ]);
                }

                Db::commit();

                $msg = "导入完成：成功{$successCount}条";
                if (!empty($errorReasons)) {
                    $msg .= "，失败" . count($errorReasons) . "条";
                }

                return json([
                    'code'   => 1,
                    'msg'    => $msg,
                    'errors' => $errorReasons
                ]);

            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '导入失败: ' . $e->getMessage()]);
            }
        }

        $id = $this->request->param('id/d', 0);
        $stock = StockTypes::find($id);
        if (!$stock) {
            $this->error('股权不存在');
        }

        View::assign('stock', $stock);
        return View::fetch('batch_import');
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

    // 新增单个用户股权调整功能（带搜索）
    public function adjustSingle()
    {
        if ($this->request->isAjax()) {
            $keyword = $this->request->param('keyword/s', '');

            if ($keyword) {
                $users = User::where('realname|phone', 'like', "%{$keyword}%")
                    ->field('id,realname,phone')
                    ->limit(10)
                    ->select();
                return json(['code' => 1, 'data' => $users]);
            } else {
                $data = $this->request->only(['user_id', 'stock_type_id', 'quantity', 'operation']);

                try {
                    Db::startTrans();

                    $stock = StockTypes::find($data['stock_type_id']);
                    if (!$stock) {
                        throw new \Exception('股权不存在');
                    }

                    $userId = (int)$data['user_id'];
                    $quantity = $data['quantity'];
                    $operation = $data['operation']; // + 或 -
                    $globalPrice = Cache::get(self::GLOBAL_STOCK_PRICE_KEY, 0);

                    // 验证用户存在性
                    if (!User::where('id', $userId)->find()) {
                        throw new \Exception('用户不存在');
                    }

                    // 验证数量必须是整数
                    if (!is_numeric($quantity) || $quantity != (int)$quantity || $quantity <= 0) {
                        throw new \Exception('数量必须是大于0的整数');
                    }
                    $quantity = (int)$quantity; // 强制转换为整数

                    // 查找或创建钱包
                    $wallet = UserStockWallets::where([
                        'user_id'       => $userId,
                        'stock_type_id' => $data['stock_type_id']
                    ])->find();

                    $beforeQuantity = 0;
                    if ($wallet) {
                        $beforeQuantity = $wallet->quantity;
                    } else {
                        $wallet = new UserStockWallets();
                        $wallet->user_id = $userId;
                        $wallet->stock_type_id = $data['stock_type_id'];
                        $wallet->quantity = 0;
                    }

                    // 处理增减操作
                    if ($operation === '+') {
                        // 检查股权池是否足够
                        if ($stock->total_shares < $quantity) {
                            throw new \Exception("股权池数量不足（剩余 {$stock->total_shares} 股）");
                        }

                        // 从股权池中扣除
                        $stock->total_shares -= $quantity;

                        // 增加用户股权
                        $wallet->quantity += $quantity;

                        // 记录交易记录（增加）
                        Db::name('stock_transactions')->insert([
                            'user_id'       => $userId,
                            'stock_type_id' => $data['stock_type_id'],
                            'type'          => 1, // 1=买入
                            'source'        => 0,
                            'quantity'      => $quantity,
                            'price'         => $globalPrice,
                            'amount'        => bcmul($quantity, $globalPrice, 2),
                            'status'        => 1, // 成功
                            'remark'        => "系统增加股权",
                            'created_at'    => date('Y-m-d H:i:s'),
                            'updated_at'    => date('Y-m-d H:i:s')
                        ]);
                    } else if ($operation === '-') {
                        // 检查用户钱包是否足够
                        if ($wallet->quantity < $quantity) {
                            throw new \Exception("用户股权数量不足（当前持有 {$wallet->quantity} 股）");
                        }

                        // 减少用户股权
                        $wallet->quantity -= $quantity;

                        // 回收到股权池中
                        $stock->total_shares += $quantity;

                        // 记录交易记录（减少）
                        Db::name('stock_transactions')->insert([
                            'user_id'       => $userId,
                            'stock_type_id' => $data['stock_type_id'],
                            'type'          => 2, // 2=卖出
                            'source'        => 0,
                            'quantity'      => $quantity,
                            'price'         => $globalPrice,
                            'amount'        => bcmul($quantity, $globalPrice, 2),
                            'status'        => 1, // 成功
                            'remark'        => "系统减少股权",
                            'created_at'    => date('Y-m-d H:i:s'),
                            'updated_at'    => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        throw new \Exception('无效的操作类型');
                    }

                    // 保存钱包和股权池状态
                    $wallet->save();
                    $stock->save();

                    // 记录操作日志
                    AdminOperations::create([
                        'admin_id'    => $this->adminUser->id,
                        'type'        => 'adjust_single_stock',
                        'target_id'   => $userId,
                        'target_type' => 'user_stock_wallets',
                        'before_data' => json_encode([
                            'wallet_quantity' => $beforeQuantity,
                            'total_shares'    => $operation === '+'
                                ? $stock->total_shares + $quantity
                                : $stock->total_shares - $quantity
                        ]),
                        'after_data'  => json_encode([
                            'wallet_quantity' => $wallet->quantity,
                            'total_shares'    => $stock->total_shares
                        ]),
                        'ip'          => $this->request->ip(),
                        'remark'      => ($operation === '+' ? '增加' : '减少') . "用户[{$userId}]股权[{$stock->name}] {$quantity}股",
                        'created_at'  => date('Y-m-d H:i:s'),
                        'updated_at'  => date('Y-m-d H:i:s')
                    ]);

                    Db::commit();
                    return json(['code' => 1, 'msg' => '股权调整成功']);
                } catch (\Exception $e) {
                    Db::rollback();
                    return json(['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()]);
                }
            }
        }

        $id = $this->request->param('id/d', 0);
        $stock = StockTypes::find($id);
        if (!$stock) {
            $this->error('股权不存在');
        }

        View::assign('stock', $stock);
        return View::fetch('adjust_single');
    }

    public function downloadTemplate()
    {
        // 创建示例数据
        $data = [
            ['手机号码', '操作类型(+/-)', '数量'],
            ['13800138000', '+', 100],
            ['13900139000', '-', 50]
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($data);

        // 设置响应头
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="股权批量导入模板.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}