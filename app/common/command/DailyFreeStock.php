<?php

namespace app\common\command;

use app\api\service\StockActivityService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class DailyFreeStock extends Command
{
    protected function configure()
    {
        //php think dailyFreeStock --date=2023-10-05
        $this->setName('dailyFreeStock')
            ->setDescription('每日发放自由股权')
            ->addOption('date', 'd', Option::VALUE_OPTIONAL, '指定发放日期，格式为YYYY-MM-DD', null);
    }

    protected function execute(Input $input, Output $output)
    {
        $date = $input->getOption('date');

        $output->writeln('[' . date('Y-m-d H:i:s') . '] 开始发放自由股权...');
        if ($date) {
            $output->writeln('[' . date('Y-m-d H:i:s') . '] 指定发放日期: ' . $date);

            // 验证日期格式
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $output->writeln('[' . date('Y-m-d H:i:s') . '] 错误: 日期格式不正确，应为YYYY-MM-DD格式');
                return;
            }
        }

        try {
            $result = StockActivityService::dailyFreeStockDistribution($date);

            if ($result) {
                $output->writeln('[' . date('Y-m-d H:i:s') . '] 自由股权发放完成');
            } else {
                $output->writeln('[' . date('Y-m-d H:i:s') . '] 没有需要发放的用户');
            }
        } catch (\Exception $e) {
            $output->writeln('[' . date('Y-m-d H:i:s') . '] 发放失败: ' . $e->getMessage());
        }
    }
}