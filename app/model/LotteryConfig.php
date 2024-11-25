<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class LotteryConfig extends Model
{
    //

    public function lottery(){
        $configs = $this->select();
            // 生成1-100的随机数
        $random = rand(1, 100);
        
        // 计算概率区间并判断中奖
        $currentRatio = 0;
        $result = -1;
        
        foreach ($configs as $config) {
            $currentRatio += $config['lottery_ratio'];
            if ($random <= $currentRatio) {
                $result = $config;
                break;
            }
        }
        return $result;

    }
}
