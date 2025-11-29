<?php

namespace app\api\service;

use app\model\CertificateFiles;
use think\Exception;
use think\facade\Db;

class CertificateService
{
    /**
     * 获取凭证列表
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array
     * @throws \Exception
     */
    public static function getCertificateList($page = 1, $limit = 10)
    {
        try {
            $query = CertificateFiles::where('status', CertificateFiles::STATUS_ENABLED)
                ->order('sort', 'desc')
                ->order('id', 'desc');

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->page($page, $limit)
                ->select()
                ->each(function ($item) {
                    // 添加完整图片URL
                    $item['image_url'] = env('app.img_host') . '/storage/' . $item['image'];
                    return $item;
                });

            return [
                'list'         => $list,
                'total'        => $total,
                'current_page' => $page,
                'total_page'   => ceil($total / $limit)
            ];

        } catch (Exception $e) {
            throw new Exception('获取凭证列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据ID获取凭证信息
     * @param int $id 凭证ID
     * @return array|null
     * @throws \Exception
     */
    public static function getCertificateById($id)
    {
        try {
            $certificate = CertificateFiles::where('id', $id)
                ->where('status', CertificateFiles::STATUS_ENABLED)
                ->find();

            if ($certificate) {
                $certificate['image_url'] = env('app.img_host') . '/storage/' . $certificate['image'];
                return $certificate;
            }

            return null;

        } catch (Exception $e) {
            throw new Exception('获取凭证信息失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取所有启用的凭证
     * @return array
     * @throws \Exception
     */
    public static function getAllEnabledCertificates()
    {
        try {
            return CertificateFiles::where('status', CertificateFiles::STATUS_ENABLED)
                ->order('sort', 'desc')
                ->order('id', 'desc')
                ->select()
                ->each(function ($item) {
                    $item['image_url'] = env('app.img_host') . '/storage/' . $item['image'];
                    return $item;
                });

        } catch (Exception $e) {
            throw new Exception('获取凭证列表失败: ' . $e->getMessage());
        }
    }
}