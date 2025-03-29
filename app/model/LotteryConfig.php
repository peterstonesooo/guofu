<?php
declare (strict_types = 1);

namespace app\model;

use BitWasp\Bitcoin\Locktime;
use think\Model;
use think\facade\Db;

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
            $conf = $this->where('id', $result['id'])->lock(true)->find();
            if($conf['num'] > 0){
                $this->where('id', $result['id'])->dec('num')->inc('active_num')->update();
                $result = $conf;
            }else{
                $result = $configs[0];
                $this->where('id', $result['id'])->inc('active_num')->update();
            }


        return $result;
    }
}
