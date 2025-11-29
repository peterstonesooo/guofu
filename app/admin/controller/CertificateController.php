<?php

namespace app\admin\controller;

use app\model\CertificateFiles;
use think\facade\Cache;
use think\facade\Request;
use think\facade\View;

class CertificateController extends AuthController
{
    // 共用Redis缓存键名
    const CERTIFICATE_LIST_KEY = 'certificate_files_list';

    public function certificateList()
    {
        $req = Request::param();

        try {
            // 生成缓存键，包含查询参数
            $cacheKey = self::CERTIFICATE_LIST_KEY . ':admin:' . md5(serialize($req));

            // 使用Redis handler获取缓存
            $redis = Cache::store('redis')->handler();
            $cachedData = $redis->get($cacheKey);

            if ($cachedData !== false) {
                $data = unserialize($cachedData);
            } else {
                $query = CertificateFiles::order('sort', 'desc')->order('id', 'desc');

                if (isset($req['title']) && trim($req['title']) != '') {
                    $query->where('title', 'like', '%' . trim($req['title']) . '%');
                }

                $data = $query->paginate(['query' => $req])->each(function ($item) {
                    $item->image_url = $item->image_url;
                    return $item;
                });

                // 将数据存入缓存
                $redis->setex($cacheKey, 600, serialize($data));
            }

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();

        } catch (\Exception $e) {
            // 异常时仍返回数据，但不使用缓存
            $query = CertificateFiles::order('sort', 'desc')->order('id', 'desc');

            if (isset($req['title']) && trim($req['title']) != '') {
                $query->where('title', 'like', '%' . trim($req['title']) . '%');
            }

            $data = $query->paginate(['query' => $req])->each(function ($item) {
                $item->image_url = $item->image_url;
                return $item;
            });

            $this->assign('req', $req);
            $this->assign('data', $data);

            return $this->fetch();
        }
    }

    public function showCertificate()
    {
        $req = Request::param();
        $data = [];

        if (!empty($req['id'])) {
            $data = CertificateFiles::find($req['id']);
            if ($data) {
                $data->image_url = $data->image_url;
            }
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 清除所有凭证列表缓存
     */
    private function clearAllCertificateListCache()
    {
        try {
            $redis = Cache::store('redis')->handler();
            $iterator = null;
            $pattern = self::CERTIFICATE_LIST_KEY . '*';

            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if ($keys !== false && !empty($keys)) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);

        } catch (\Exception $e) {
            \think\facade\Log::error('清除凭证缓存失败: ' . $e->getMessage());
        }
    }

    public function addCertificate()
    {
        $req = $this->validate(Request::param(), [
            'title|凭证标题' => 'require',
            'image|凭证图片' => 'require',
            'sort|排序' => 'require|integer',
            'status|状态' => 'require|in:0,1',
        ]);

        $req['created_at'] = date('Y-m-d H:i:s');
        $req['updated_at'] = date('Y-m-d H:i:s');

        CertificateFiles::create($req);

        // 添加成功后清除所有缓存
        $this->clearAllCertificateListCache();

        return out();
    }

    public function editCertificate()
    {
        $req = $this->validate(Request::param(), [
            'id' => 'require|number',
            'title|凭证标题' => 'require',
            'image|凭证图片' => 'require',
            'sort|排序' => 'require|integer',
            'status|状态' => 'require|in:0,1',
        ]);

        $req['updated_at'] = date('Y-m-d H:i:s');

        $certificate = CertificateFiles::find($req['id']);
        if (!$certificate) {
            return out(null, 404, '凭证不存在');
        }

        $certificate->save($req);

        // 编辑成功后清除所有缓存
        $this->clearAllCertificateListCache();

        return out();
    }

    public function deleteCertificate()
    {
        $req = $this->validate(Request::param(), [
            'id' => 'require|number'
        ]);

        $certificate = CertificateFiles::find($req['id']);
        if (!$certificate) {
            return out(null, 404, '凭证不存在');
        }

        $certificate->delete();

        // 删除成功后清除所有缓存
        $this->clearAllCertificateListCache();

        return out();
    }
}