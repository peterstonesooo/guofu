<?php

namespace app\model;

use think\Model;

class PackagePurchases extends Model
{

    // 定义表的主键
    protected $pk = 'id';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 支付方式常量定义
    const PAY_TYPE_TOPUP_BALANCE = 1; // 可用余额支付
    const PAY_TYPE_TEAM_BALANCE = 2; // 可提余额支付

    // 状态常量定义
    const STATUS_SUCCESS = 1; // 支付成功
    const STATUS_FAILED = 0;  // 支付失败

    /**
     * 获取支付方式文本
     * @param int $payType 支付方式代码
     * @return string
     */
    public static function getPayTypeText($payType)
    {
        $map = [
            self::PAY_TYPE_TOPUP_BALANCE => '可用余额支付',
            self::PAY_TYPE_TEAM_BALANCE  => '可提余额支付'
        ];
        return isset($map[$payType]) ? $map[$payType] : '未知';
    }

    public function getPayTypeTextAttr()
    {
        return self::getPayTypeText($this->pay_type);
    }

    /**
     * 获取状态文本
     * @param int $status 状态代码
     * @return string
     */
    public static function getStatusText($status)
    {
        $map = [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED  => '失败'
        ];
        return $map[$status] ?? '未知';
    }

    /**
     * 关联用户表
     */
    public function user()
    {
        return $this->belongsTo('app\model\User', 'user_id');
    }

    /**
     * 关联套餐表
     */
    public function package()
    {
        return $this->belongsTo('app\model\StockPackages', 'package_id');
    }

    /**
     * 获取购买记录列表（带分页）
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public static function getPurchaseList($where = [], $page = 1, $limit = 15)
    {
        $query = self::with(['user' => function ($query) {
            $query->field('id,username,nickname');
        }])
            ->with(['package' => function ($query) {
                $query->field('id,name,price');
            }])
            ->where($where)
            ->order('id', 'desc');

        $list = $query->paginate([
            'list_rows' => $limit,
            'page'      => $page
        ]);

        return $list;
    }
}