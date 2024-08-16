<?php

return [
    // 默认磁盘
    'default' => env('filesystem.driver', 'public'),
    // 磁盘列表
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
        ],
        'public' => [
            // 磁盘类型
            'type'       => 'local',
            // 磁盘路径
            'root'       => app()->getRootPath() . 'public/storage',
            // 磁盘路径对应的外部URL路径
            'url'        => '/storage',
            // 可见性
            'visibility' => 'public',
        ],
        
        // 更多的磁盘配置信息
        'qiniu' =>[									//完全可以自定义的名称
            'type'=>'qiniu',						//可以自定义,实际上是类名小写
            'accessKey' =>'jpDClka0AFROaejYyw-MJccYgZnkdbPZKo-CqN6W',		//七牛云的配置,accessKey
            'secretKey'=>'Zq4jd7lAorIuEVEMXkpqXfEvgCEE2c_bJqPCYYFG',//七牛云的配置,secretKey
            'bucket'=>'store142',					//七牛云的配置,bucket空间名
            //'domain'=>'s2dgpwe6t.hn-bkt.clouddn.com'					//七牛云的配置,domain,域名
            'domain'=>'gf.yjtno.com',				//七牛云的配置,domain,域名
        ],
    ],
];
