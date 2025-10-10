<?php

namespace app\api\controller;

use app\api\service\StockProductService;
use think\facade\Cache;
use think\facade\Db;

class StockProductController extends AuthController
{
    // 共用Redis缓存键名
    const PRODUCT_LIST_KEY = 'stock_product_list';
    // 缓存时间（10分钟）
    const CACHE_TIME = 600;

    /**
     * 获取产品信息列表
     * @param integer page 页码 (可选)
     * @param integer limit 每页条数 (可选)
     */
    public function productList()
    {
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        try {
            // 生成缓存键，包含分页参数
            $cacheKey = self::PRODUCT_LIST_KEY . ":{$page}:{$limit}";

            // 尝试从缓存获取数据
            $cachedData = Cache::get($cacheKey);

            if ($cachedData) {
                // 如果缓存存在，直接返回缓存数据
                return out(unserialize($cachedData));
            }

            // 构建查询条件
            $where = [['status', '=', 1]];

            // 查询产品列表
            $query = Db::table('mp_stock_product')
                ->where($where)
                ->order('sort', 'desc')
                ->order('id', 'desc');

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->page($page, $limit)
                ->select()
                ->each(function ($item) {
                    // 添加完整图片URL
                    $item['img_url'] = env('app.img_host') . '/storage/' . $item['imgurl'];
                    return $item;
                });

            $result = [
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ];

            // 将数据存入缓存
            Cache::set($cacheKey, serialize($result), self::CACHE_TIME);

            return out($result);

        } catch (\Exception $e) {
            return out(null, 10001, '获取产品列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 购买产品
     * @param integer product_id 产品ID
     * @param integer quantity 购买数量
     * @param integer pay_type 支付方式 (1=充值余额, 2=团队奖金余额)
     * @param string pay_password 支付密码
     */
    public function buyProduct()
    {
        $user = $this->user;
        $product_id = $this->request->param('product_id/d', 0);
        $quantity = $this->request->param('quantity/d', 1);
        $pay_type = $this->request->param('pay_type/d', 1);
        $pay_password = $this->request->param('pay_password', '');

        // 参数验证
        if ($product_id <= 0 || $quantity <= 0 || !in_array($pay_type, [1, 2])) {
            return out(null, 10001, '参数错误');
        }

        // 支付密码验证
        if (empty($user['pay_password'])) {
            return out(null, 10010, '请先设置支付密码');
        }
        if (sha1(md5($pay_password)) !== $user['pay_password']) {
            return out(null, 10011, '支付密码错误');
        }

        try {
            $result = StockProductService::buyProduct($user['id'], $product_id, $quantity, $pay_type);
            if ($result) {
                return out(null, 200, '购买成功');
            }
            return out(null, 10002, '购买失败');
        } catch (\Exception $e) {
            return out(null, 10003, $e->getMessage());
        }
    }

    /**
     * 获取购买产品记录
     * @param integer page 页码 (可选)
     * @param integer limit 每页条数 (可选)
     */
    public function purchaseList()
    {
        $user_id = $this->user['id'];
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        try {
            // 查询购买记录
            $query = Db::table('mp_stock_product_purchases')
                ->alias('p')
                ->join('mp_stock_product pr', 'p.product_id = pr.id')
                ->where('p.user_id', $user_id)
                ->field('p.*, pr.title as product_name, pr.imgurl')
                ->order('p.id', 'desc');

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->page($page, $limit)
                ->select()
                ->each(function ($item) {
                    // 添加完整图片URL
                    if (!empty($item['imgurl'])) {
                        $item['img_url'] = env('app.img_host') . '/storage/' . $item['imgurl'];
                    }

                    // 支付方式文本
                    $payTypeMap = [
                        1 => '充值余额',
                        2 => '团队奖金余额'
                    ];
                    $item['pay_type_text'] = $payTypeMap[$item['pay_type']] ?? '未知';

                    return $item;
                });

            return out([
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ]);

        } catch (\Exception $e) {
            return out(null, 10004, '获取购买记录失败: ' . $e->getMessage());
        }
    }
}