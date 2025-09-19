<?php

namespace app\admin\controller;

use app\model\GreenConfig;
use think\facade\Cache;

class GreenConfigController extends AuthController
{
    // Redis 缓存键名
    const REDIS_KEY = 'green_configs';

    // 清除缓存方法
    private function clearCache()
    {
        Cache::delete(self::REDIS_KEY);
    }

    // 获取缓存数据
    private function getCachedData()
    {
        return Cache::remember(self::REDIS_KEY, function () {
            $data = GreenConfig::order('sort asc, id desc')->select()->toArray();
            return serialize($data);
        });
    }

    // 格式化通道费文本
    private function formatChannelFeeText(&$data)
    {
        if (is_array($data)) {
            foreach ($data as &$item) {
                $item['channel_fee_text'] = '￥' . number_format($item['channel_fee'], 2);
            }
        } else if (is_object($data)) {
            $data->channel_fee_text = '￥' . number_format($data->channel_fee, 2);
        }
    }

    // 绿色方案列表
    public function greenConfigList()
    {
        $req = request()->param();

        // 尝试从缓存获取数据
        $cachedData = $this->getCachedData();
        $allData = unserialize($cachedData);

        // 如果没有缓存数据，从数据库获取
        if (empty($allData)) {
            $builder = GreenConfig::order('sort asc, id desc');
            if (isset($req['green_config_id']) && $req['green_config_id'] !== '') {
                $builder->where('id', $req['green_config_id']);
            }
            if (isset($req['status']) && $req['status'] !== '') {
                $builder->where('status', $req['status']);
            }

            $data = $builder->paginate(['query' => $req]);

            // 格式化通道费文本
            $data->each(function ($item) {
                $this->formatChannelFeeText($item);
                return $item;
            });
        } else {
            // 处理缓存数据的分页和筛选
            $filteredData = $allData;

            // 应用筛选条件
            if (isset($req['green_config_id']) && $req['green_config_id'] !== '') {
                $filteredData = array_filter($filteredData, function ($item) use ($req) {
                    return $item['id'] == $req['green_config_id'];
                });
            }

            if (isset($req['status']) && $req['status'] !== '') {
                $filteredData = array_filter($filteredData, function ($item) use ($req) {
                    return $item['status'] == $req['status'];
                });
            }

            // 格式化通道费文本
            $this->formatChannelFeeText($filteredData);

            // 手动分页
            $page = isset($req['page']) ? $req['page'] : 1;
            $pageSize = 10; // 默认每页10条
            $total = count($filteredData);
            $items = array_slice($filteredData, ($page - 1) * $pageSize, $pageSize);

            // 创建分页对象
            $data = new \think\paginator\driver\Bootstrap($items, $pageSize, $page, $total, false, [
                'query' => $req
            ]);
        }

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    // 显示添加/编辑页面
    public function showGreenConfig()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            // 尝试从缓存获取
            $cachedData = $this->getCachedData();
            $allData = unserialize($cachedData);

            if ($allData) {
                foreach ($allData as $item) {
                    if ($item['id'] == $req['id']) {
                        $data = $item;
                        break;
                    }
                }
            }

            // 如果缓存中没有，从数据库获取
            if (empty($data)) {
                $data = GreenConfig::where('id', $req['id'])->find();
                if ($data) {
                    $data = $data->toArray();
                }
            }

            // 格式化通道费文本
            if (!empty($data)) {
                $data['channel_fee_text'] = '￥' . number_format($data['channel_fee'], 2);
            }
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    // 添加绿色方案
    public function addGreenConfig()
    {
        $req = $this->validate(request(), [
            'name|方案名称'           => 'require|max:100',
            'priority_queue|优先队列' => 'require|number',
            'channel_fee|通道费'      => 'require|float',
            'sort|排序'               => 'number',
        ]);

        GreenConfig::create($req);

        // 清除缓存
        $this->clearCache();

        return out(null, 200, '添加成功');
    }

    // 编辑绿色方案
    public function editGreenConfig()
    {
        $req = $this->validate(request(), [
            'id'                      => 'require|number',
            'name|方案名称'           => 'require|max:100',
            'priority_queue|优先队列' => 'require|number',
            'channel_fee|通道费'      => 'require|float',
            'sort|排序'               => 'number',
        ]);

        GreenConfig::where('id', $req['id'])->update($req);

        // 清除缓存
        $this->clearCache();

        return out(null, 200, '编辑成功');
    }

    // 更改状态
    public function changeGreenConfig()
    {
        $req = $this->validate(request(), [
            'id'    => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        GreenConfig::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        // 清除缓存
        $this->clearCache();

        return out();
    }

    // 删除绿色方案
    public function delGreenConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        GreenConfig::where('id', $req['id'])->delete();

        // 清除缓存
        $this->clearCache();

        return out(null, 200, '删除成功');
    }
}