<?php
namespace app\common\command;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\User;

class SysncUser extends Command
{
    protected function configure()
    {
        $this->setName('sysncUser')->setDescription('同步用户关系数据');
    }

    protected function execute(Input $input, Output $output)
    {   
        //$this->sysncUser();
        $this->updateTeamCount();
        $output->writeln('同步完成');
    }

    public function sysncUser(){
        // 清空用户路径表
        Db::execute('TRUNCATE TABLE `mp_user_path`');
        
        // 获取总用户数
        $total = User::count();
        $processed = 0;
        
        User::chunk(1000, function($users) use (&$processed, $total) {
            foreach($users as $user) {
                $processed++;
                echo "\r正在同步用户数据... {$processed}/{$total}";
                
                $path = '';
                $depth = 0;
                $current_user = $user;
                $parent_ids = [];
                
                // 构建用户路径
                while($current_user && $current_user['up_user_id'] > 0) {
                    $parent_ids[] = $current_user['up_user_id'];
                    $current_user = User::find($current_user['up_user_id']);
                    $depth++;
                }
                
                if(!empty($parent_ids)) {
                    $path = implode('/', array_reverse($parent_ids));
                }
                
                // 插入用户路径记录
                Db::name('user_path')->insert([
                    'id' => $user['id'],
                    'user_id' => $user['id'],
                    'path' => $path,
                    'depth' => $depth,
                    'team_count' => 0,
                    'team_real_count' => 0
                ]);
            }
        });
        
        echo "\n同步用户数据完成，共处理 {$processed} 个用户\n";
    }
    
    protected function updateTeamCount()
    {
        // 获取所有用户
        $users = Db::name('user_path')->select();
        $total = count($users);
        $processed = 0;

        foreach($users as $user) {
            $processed++;
            echo "\r正在统计团队人数... {$processed}/{$total}";
            
            // 构建完整的path查询条件
            $full_path = $user['path'] ? $user['path'].'/'.$user['user_id'] : $user['user_id'];
            
            // 统计所有下级总人数
            $team_count = Db::name('user_path')
                ->where('path', 'like', $full_path.'%')
                ->count();

            // 统计实名下级人数
            $team_real_count = Db::name('user_path')
                ->alias('p')
                ->join('mp_user u', 'p.user_id = u.id')
                ->where('p.path', 'like', $full_path.'%')
                ->where('u.is_realname', 1)
                ->count();

            // 更新团队人数
            Db::name('user_path')
                ->where('user_id', $user['user_id'])
                ->update([
                    'team_count' => $team_count,
                    'team_real_count' => $team_real_count
                ]);
        }
        echo "\n团队人数统计完成\n";
    }
}