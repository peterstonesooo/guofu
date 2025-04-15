<?php

namespace app\common\command;

use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\Project;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\console\input\Argument;
use Exception;
use think\facade\Log;

class ProjectLimited extends Command
{
    protected function configure()
    {
        // 设置命令名称和描述
        $this->setName('ProjectLimited')
            ->setDescription('每分钟随机减少project表的max_limited字段');
    }

    protected function execute(Input $input, Output $output)
    {
        $timeNum = (int)date('Hi');
        if ($timeNum < 800 || $timeNum > 2030) {
            $output->writeln(date('Y-m-d H:i:s') . ' - 不在运行时间范围内（8:00-20:30），退出执行');
            return;
        }
        // 获取所有is_limited为1的数据
        $projects = Db::name('project')->where('is_limited', 1)->select();

        foreach ($projects as $project) {
            // 检查max_limited是否大于min_limited
            if ($project['max_limited'] > $project['min_limited']) {
                // 计算随机数范围
                $reduceBy = rand($project['min_reduce'], $project['max_reduce']);

                // 更新max_limited值，确保不会低于min_limited
                $newMaxLimited = max($project['min_limited'], $project['max_limited'] - $reduceBy);

                // 更新数据库
                Db::name('project')->where('id', $project['id'])->update(['max_limited' => $newMaxLimited]);
                $output->writeln(date('Y-m-d H:i:s') . ' - '.$project['name'].'减少至'.$newMaxLimited);
            }
        }

        $output->writeln(date('Y-m-d H:i:s') . ' - 项目限制更新成功');
    }
}