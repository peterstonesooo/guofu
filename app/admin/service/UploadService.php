<?php

namespace app\admin\service;

use think\facade\Filesystem;
use think\file\UploadedFile;

class UploadService
{
    public static function upload($file,$folder)
    {
        try {
            if (!$file instanceof UploadedFile) {
                throw new \Exception('请选择要上传的文件');
            }

            // 验证文件大小和类型
            $validate = [
                'size' => 1024 * 1024 * 2, // 2MB
                'ext'  => 'jpg,png,jpeg,gif'
            ];

            $savename = Filesystem::disk('public')->putFile($folder, $file);

            if ($savename) {
                $imgHost = env('IMG_HOST', 'http://api.admin.huipu.me');
                return [
                    'code' => 200,
                    'msg'  => '上传成功',
                    'data' => [
                        'id'  => $savename, // 这里实际应该返回图片ID，但为了简化，使用路径作为ID
                        'url' => ['/storage/' . $savename] // 编辑器需要数组格式，添加/storage前缀
                    ]
                ];
            } else {
                throw new \Exception('文件上传失败');
            }
        } catch (\Exception $e) {
            return [
                'code' => 500,
                'msg'  => $e->getMessage()
            ];
        }
    }
}