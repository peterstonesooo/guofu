<?php

namespace app\api\controller;

use app\model\CertificateFiles;
use think\facade\Cache;

class CertificateController extends AuthController
{
    // 共用Redis缓存键名
    const CERTIFICATE_LIST_KEY = 'certificate_files_list';
    // 缓存时间（10分钟）
    const CACHE_TIME = 600;

    /**
     * 获取凭证信息列表
     * @param integer page 页码 (可选)
     * @param integer limit 每页条数 (可选)
     */
    public function certificateList()
    {
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        try {
            // 生成缓存键，包含分页参数
            $cacheKey = self::CERTIFICATE_LIST_KEY . ":{$page}:{$limit}";

            // 使用Redis handler获取缓存
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                return out(unserialize($cachedData));
            }

            // 使用Model查询凭证列表
            $query = CertificateFiles::where('status', 1)
                ->order('sort', 'desc')
                ->order('id', 'desc');

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->page($page, $limit)
                ->select()
                ->each(function ($item) {
                    // 添加完整图片URL（模型已添加/storage/）
                    $item['image_url'] = env('app.img_host') . '/' . $item['image'];

                    return $item;
                });

            $result = [
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ];

            // 将数据存入缓存
            $redis->setex($cacheKey, self::CACHE_TIME, serialize($result));

            return out($result);

        } catch (\Exception $e) {
            return out(null, 10001, '获取凭证列表失败: ' . $e->getMessage());
        }
    }
}