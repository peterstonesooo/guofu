<?php

return array(
    'user' =>
    array(
        'status_map' =>
        array(
            0 => '禁用',
            1 => '启用',
        ),
        'level_map' =>
        array(
            0 => 'VIP0',
            1 => 'VIP1',
            2 => 'VIP2',
            3 => 'VIP3',
            4 => 'VIP4',
            5 => 'VIP5',
            6 => 'VIP6',
        ),
        'is_active_map' =>
        array(
            0 => '否',
            1 => '是',
        ),
    ),
    'user_relation' =>
    array(
        'level_map' =>
        array(
            1 => 'LV1',
            2 => 'LV2',
            3 => 'LV3',
        ),
        'is_active_map' =>
        array(
            0 => '否',
            1 => '是',
        ),
    ),
    'system_info' =>
    array(
        'type_map' =>
        array(
            1 => '公告',
            2 => '公司动态',
            3 => '民生回顾',
            4 =>'新闻政策'
        ),
        'status_map' =>
        array(
            0 => '禁用',
            1 => '启用',
        ),
        'setting_key' => ['apk_download_url', 'version_apk', 'video_url', 'video_img_url', 'kefu_url', 'register_domain', 'is_req_encypt', 'microcore_group', 'qq_group', 'chat_url1', 'chat_url2','withdraw_fee_ratio','single_withdraw_min_amount','is_card_show','card_schedule','card_string'],
    ),
    'project' =>
    array(
        'status_map' =>
        array(
            0 => '禁用',
            1 => '启用',
        ),
        'is_recommend_map' =>
        array(
            0 => '否',
            1 => '是',
        ),
        'group' => [
            1 => '民生帮扶计划',
            2 => '就业补助一期',
            3 => '就业补助二期',
            4 => '养老补助一期',
            5=>'养老补助二期',
            6=>'教育补助一期',
            7=>'延迟退休补助',
            8=>'教育补助二期',
            9=>'以旧换新补助'
            // 6 => '驰援甘肃',
        ],
        'groupName' => [
            //['id'=>2,'name'=>'就业补助一期','type'=>0,],
            //['id'=>3,'name'=>'就业补助二期','type'=>0,],
            //['id'=>5,'name'=>'养老补助二期','type'=>0,'is_selected'=>1],
            //['id'=>1,'name'=>'民生帮扶计划','type'=>0,],
           // ['id'=>6,'name'=>'教育补助一期','type'=>0,'is_selected'=>1],
            ['id'=>9,'name'=>'以旧换新补助','type'=>2,'is_selected'=>1],
            ['id'=>8,'name'=>'教育补助二期','type'=>0,'is_selected'=>0],
            //['id'=>7,'name'=>'延迟退休补助','type'=>1,'is_selected'=>0],

        ],
        'teamplate_type'=>[
            0 => '教育补助二期',
            1 => '特殊项目1',
            2 => '以旧换新补助',
            3 => '体重管理',
        ],
        'project_house'=>[
            45=>38,
            46=>90,
            47=>90,
            48=>110,
            49=>127,
            50=>130,
            51=>150,
            52=>150,
        ],
        'project_card'=>[
            61=>['min'=>0,'max'=>100000],
            62=>['min'=>100000,'max'=>300000],
            63=>['min'=>300000,'max'=>500000],
            64=>['min'=>500000,'max'=>1000000],
            65=>['min'=>1000000,'max'=>10000000],
        ]
    ),
    'order' =>
    array(
        'status_map' =>
        array(
            1 => '待支付',
            2 => '收益中',
            3 => '待出售',
            4 => '已完成',
        ),
        'pay_method_map' =>
        array(
            0 => '未知',
            1 => '余额',
            2 => '微信',
            3 => '支付宝',
            4 => '线上银联',
            5 => '推荐奖励',
            6 => '线下银联',
        ),
        'equity_status_map' =>
        array(
            1 => '不能兑换',
            2 => '可以兑换',
            3 => '已兑换',
        ),
        'digital_yuan_status_map' =>
        array(
            1 => '不能兑换',
            2 => '可以兑换',
            3 => '已兑换',
        ),
    ),
    'user_balance_log' =>
    array(
        'type_map' =>
        array(
            1 => '充值',
            2 => '提现',
            3 => '购买项目',
            4 => '充值奖励',
            5 => '数字人民币',
            6 => '项目分红',
            7 => '额外奖励',
            8 => '团队奖励',
            9 => '直属推荐额外奖励',
            10 => '股权兑换',
            11 => '期权兑换',
            12 => '返还本金',
            13 => '提现失败',
            14 => '被动收益',
            15 => '手动入金',
            16 => '手动出金',
            17 => '签到',
            18 => '转账',
            19 => '收款',
            20 => '提现手续费',
            21 => '房屋维修基金',
            22 => '日提现额度',
            23 => '数字人民币红包',
            24 => '注册赠送数字人民币',
            25 => '激活数字人民币账单',
            26 => '积分兑换',
            27 => '购买商品',
            28 => '抽奖奖励',


        ),
        'balance_type_map' =>
        array(
            1 => '充值',
            2 => '提现',
            3 => '购买项目',
            4 => '充值奖励',
            5 => '数字人民币',
            6 => '项目分红',
            7 => '额外奖励',
            8 => '推荐奖励',
            9 => '直属推荐额外奖励',
            10 => '股权兑换',
            11 => '期权兑换',
            12 => '返还本金',
            13 => '提现失败',
            14 => '被动收益',
            15 => '手动入金',
            16 => '手动出金',
            17 => '签到',
            18 => '转账',
            19 => '收款',
            20 => '提现手续费',
            21 => '房屋维修基金',
            22 => '日提现额度',
            23 => '数字人民币红包',
            24 => '注册赠送',
            25 => '激活数字人民币账单',
            26 => '积分兑换',
            27 => '购买商品',
            28 => '抽奖奖励',

        ),
        'integral_type_map' =>
        array(
            3 => '购买项目',
            15 => '手动入金',
            16 => '手动出金',
            17 => '签到',
        ),
        'log_type_map' =>
        array(
            1 => '可用余额日志',
            2 => '积分日志',
            3 => '可提余额日志',
            4 => '民生养老金日志',
            5 => '房屋保障金日志',
            //6 => '民生奖日志',
            7 => '大额补助金',
        ),
        'status_map' =>
        array(
            1 => '待确认',
            2 => '成功',
            3 => '失败',
        ),
    ),
    'passive_income_record' =>
    array(
        'status_map' =>
        array(
            1 => '未开始',
            2 => '未领取',
            3 => '已领取',
        ),
        'is_finish_map' =>
        array(
            0 => '否',
            1 => '是',
        ),
    ),
    'banner' =>
    array(
        'status_map' =>
        array(
            0 => '禁用',
            1 => '启用',
        ),
    ),
    'level_config' =>
    array(
        'level_map' =>
        array(
            0 => 'VIP0',
            1 => 'VIP1',
            2 => 'VIP2',
            3 => 'VIP3',
            4 => 'VIP4',
            5 => 'VIP5',
            6 => 'VIP6',
        ),
    ),
    'payment' =>
    array(
        'product_type_map' =>
        array(
            1 => '投资项目',
            2 => '充值',
        ),
        'status_map' =>
        array(
            1 => '未支付',
            2 => '支付成功',
            3 => '支付失败',
        ),
    ),
    'capital' =>
    array(
        'type_map' =>
        array(
            1 => '充值',
            2 => '提现',
        ),
        'status_map' =>
        array(
            1 => '待审核-待支付',
            2 => '审核通过-支付成功',
            3 => '审核拒绝-支付失败',
            4 => '待打款',
        ),
        'topup_status_map' =>
        array(
            1 => '待支付',
            2 => '支付成功',
            3 => '支付失败',
        ),
        'withdraw_status_map' =>
        array(
            1 => '待审核',
            2 => '已提现',
            3 => '审核拒绝',
            4 => '打款中',
        ),
        'pay_channel_map' =>
        array(
            0 => '线下',
            1 => '宏亚',
            2 => '海贼',
            3 => '星辰',
            4 => '银行卡',
            8 => '香蕉',
            200=>'后台手动',
        ),
    ),
    'pay_account' =>
    array(
        'pay_type_map' =>
        array(
            1 => '微信',
            2 => '支付宝',
            3 => '线上银联',
            4 => '银行卡',
            5 => '云闪付',
            8 => '快捷支付',
            200=>'后台手动',
        ),
    ),
    'payment_config' =>
    array(
        'type_map' =>
        array(
            1 => '微信',
            2 => '支付宝',
            3 => '线上银联',
            4 => '银行卡',
            5 => '云闪付',
            8 => '快捷支付',
            200=>'后台手动',

        ),
        'status_map' =>
        array(
            0 => '禁用',
            1 => '启用',
        ),
        'channel_map' =>
        array(
            0 => '线下',
            1 => '宏亚',
            2 => '海贼',
            3 => '星辰',
            4 => '银行卡',
            8 => '香蕉',
            200=>'后台手动',
        ),
    ),
    'rank_reward' => [
        1=>3000,
        2=>1500,
        3=>1000,
        4=>500,
        5=>300,
        6=>100,
        7=>80,
        8=>60,
        9=>40,
        10=>20,
        11=>0,
    ],
    'noDomainArr'=>[
            // 'api.nhxij.com',
            // 'api.ojokl.com',
            // 'api.zcxjh.com',
            // 'api.actzv.com',  
            // 'api.fkbya.com',
            // 'api.hjtojoh.com',
            // 'api.aojmjfe.com', 
            // 'api.lht2586.com',
            // 'api.hprkv.com',
            // 'api.f3sfu.com',
            // 'api.smnrg.com',
            // 'api.gbudew.com',
            // 'api.spcdew.com',
            // 'api.smnrg.com',
            // 'api.gbudew.com',
            // 'api.spcdew.com',
            // 'api.fengyansh.cn',
            // 'api.yjvade.com',
            // 'api.nolrew.com',
    ],
    'asset_recovery' => [
        1 => [
            'type' => 1,
            'amount' => 100,
            'min_asset' => 1,
            'max_asset' => 100,
            'max_level' => 3,
            'rich' => 2
        ],
        2 => [
            'type' => 2,
            'amount' => 200,
            'min_asset' => 101,
            'max_asset' => 300,
            'max_level' => 3,
            'rich' => 2
        ],
        3 => [
            'type' => 3,
            'amount' => 500,
            'min_asset' => 301,
            'max_asset' => 600,
            'max_level' => 3,
            'rich' => 2
        ],
        4 => [
            'type' => 4,
            'amount' => 1000,
            'min_asset' => 601,
            'max_asset' => 1000,
            'max_level' => 3,
            'rich' => 2
        ],
        5 => [
            'type' => 5,
            'amount' => 2000,
            'min_asset' => 1001,
            'max_asset' => 3000,
            'max_level' => 5,
            'rich' => 1
        ],
        6 => [
            'type' => 6,
            'amount' => 3000,
            'min_asset' => 3001,
            'max_asset' => 5000,
            'max_level' => 5,
            'rich' => 1
        ],
        7 => [
            'type' => 7,
            'amount' => 6000,
            'min_asset' => 5001,
            'max_asset' => 20000,
            'max_level' => 5,
            'rich' => 1
        ],
    ],
    'ensure' => [
        1 => [
            'id' => 1,
            'name' => '住房保障',
            'img' => env('app.host').'/zhufang.jpg',
            'intro_img' => env('app.host').'/intro_zhufang.jpg',
            'receive' => false,
            'amount' => 20000,
            'receive_amount' => 4200000,
            'process_time' => 25,
            'verify_time' => 45,
            'remain_count' => 10000,
            'notarization_status' => 0,
        ],
        2 => [
            'id' => 2,
            'name' => '出行保障',
            'img' => env('app.host').'/chuxing.jpg',
            'intro_img' => env('app.host').'/intro_chuxing.jpg',
            'receive' => false,
            'amount' => 4500,
            'receive_amount' => 567000,
            'process_time' => 25,
            'verify_time' => 45,
            'remain_count' => 0,
            'notarization_status' => 0,
        ],
        3 => [
            'id' => 3,
            'name' => '养老保障',
            'img' => env('app.host').'/yanglao.jpg',
            'intro_img' => env('app.host').'/intro_yanglao.jpg',
            'receive' => false,
            'amount' => 10000,
            'receive_amount' => 1470000,
            'process_time' => 25,
            'verify_time' => 45,
            'remain_count' => 0,
            'notarization_status' => 0,
        ],
        4 => [
            'id' => 4,
            'name' => '通讯保障',
            'img' => env('app.host').'/tongxin.jpg',
            'intro_img' => env('app.host').'/intro_tongxin.jpg',
            'receive' => false,
            'amount' => 1500,
            'receive_amount' => 157500,
            'process_time' => 25,
            'verify_time' => 45,
            'remain_count' => 0,
            'notarization_status' => 0,
        ],
        8 => [
            'id' => 8,
            'name' => '通讯保障',
            'img' => env('app.host').'/tongxin1.jpg',
            'intro_img' => env('app.host').'/intro_tongxin1.png',
            'receive' => false,
            'amount' => 1500,
            'receive_amount' => 157500,
            'process_time' => 25,
            'verify_time' => 45,
            'remain_count' => 0,
            'notarization_status' => 0,
        ],
        7 => [
            'id' => 7,
            'name' => '养老保障',
            'img' => env('app.host').'/yanglao1.jpg',
            'intro_img' => env('app.host').'/intro_yanglao1.png',
            'receive' => false,
            'amount' => 10000,
            'receive_amount' => 1470000,
            'process_time' => 25,
            'verify_time' => 45,
            'remain_count' => 0,
            'notarization_status' => 0,
        ],
        6 => [
            'id' => 6,
            'name' => '出行保障',
            'img' => env('app.host').'/chuxing1.jpg',
            'intro_img' => env('app.host').'/intro_chuxing1.png',
            'receive' => false,
            'amount' => 4500,
            'receive_amount' => 567000,
            'process_time' => 25,
            'verify_time' => 45,
            'remain_count' => 0,
            'notarization_status' => 0,
        ],
        5 => [
            'id' => 5,
            'name' => '住房保障',
            'img' => env('app.host').'/zhufang1.jpg',
            'intro_img' => env('app.host').'/intro_zhufang1.png',
            'receive' => false,
            'amount' => 20000,
            'receive_amount' => 4200000,
            'process_time' => 25,
            'verify_time' => 45,
            'remain_count' => 10000,
            'notarization_status' => 0,
        ],
        // 5 => [
        //     'id' => 5,
        //     'name' => '共富商城',
        //     'img' => env('app.host').'/shangcheng.png',
        //     'intro_img' => env('app.host').'/intro_shangcheng.jpg',
        //     'receive' => false,
        //     'amount' => 0,
        //     'receive_amount' => 0,
        //     'process_time' => 0,
        //     'verify_time' => 0,
        //     'remain_count' => 0
        // ],
    ],
    'zhufang' => [
        1 => [
            'id' => 1,
            'name' => '保障房40平米',
            'img' => env('app.host').'/zhufang_40.png',
            'intro_img' => env('app.host').'/intro_zhufang_40.png',
            'receive' => false,
        ],
        2 => [
            'id' => 2,
            'name' => '保障房65平米',
            'img' => env('app.host').'/zhufang_65.png',
            'intro_img' => env('app.host').'/intro_zhufang_65.png',
            'receive' => false,
        ],
        3 => [
            'id' => 3,
            'name' => '保障房88平米',
            'img' => env('app.host').'/zhufang_88.png',
            'intro_img' => env('app.host').'/intro_zhufang_88.png',
            'receive' => false,
        ],
        4 => [
            'id' => 4,
            'name' => '保障房125平米',
            'img' => env('app.host').'/zhufang_125.png',
            'intro_img' => env('app.host').'/intro_zhufang_125.png',
            'receive' => false,
        ],
    ],
    'notice' =>
    array(
        'status_map' =>
        array(
            0 => '未读',
            1 => '已读',
        ),
    ),
    'realname_status'=>[
        0=>'未认证',
        1=>'已认证',
        2=>'已拒绝',
    ],
    'shop_order_status'=>[
        1 => '未支付',
        2 => '已支付',
        5 => '待收货',
        6 => '已取消',
        7 => '已完成',
       // 8 => '已退款',
    ],
    // 'tax_cert' => [
    //     1 => [
    //         'id' => 1,
    //         'name' => '税务抵用券',
    //         'img' => env('app.host').'/shuiwu1.png',
    //         'amount' => 1000,
    //         'limit_asset' => 3000000,
    //         'receive_card' => 1,  //1黄金 2铂金 3钻石
    //         'limit_direction' => 'down',
    //     ],
    //     2 => [
    //         'id' => 2,
    //         'name' => '税务抵用券',
    //         'img' => env('app.host').'/shuiwu1.png',
    //         'amount' => 3000,
    //         'limit_asset' => 10000000,
    //         'receive_card' => 1,  //1黄金 2铂金 3钻石
    //         'limit_direction' => 'down',
    //     ],
    //     3 => [
    //         'id' => 3,
    //         'name' => '税务抵用券',
    //         'img' => env('app.host').'/shuiwu1.png',
    //         'amount' => 5000,
    //         'limit_asset' => 20000000,
    //         'receive_card' => 2,  //1黄金 2铂金 3钻石
    //         'limit_direction' => 'down',
    //     ],
    //     4 => [
    //         'id' => 4,
    //         'name' => '税务抵用券',
    //         'img' => env('app.host').'/shuiwu1.png',
    //         'amount' => 10000,
    //         'limit_asset' => 50000000,
    //         'receive_card' => 2,  //1黄金 2铂金 3钻石
    //         'limit_direction' => 'down',
    //     ],
    //     5 => [
    //         'id' => 5,
    //         'name' => '税务抵用券',
    //         'img' => env('app.host').'/shuiwu1.png',
    //         'amount' => 20000,
    //         'limit_asset' => 100000000,
    //         'receive_card' => 3,  //1黄金 2铂金 3钻石
    //         'limit_direction' => 'down',
    //     ],
    //     6 => [
    //         'id' => 6,
    //         'name' => '税务抵用券',
    //         'img' => env('app.host').'/shuiwu1.png',
    //         'amount' => 30000,
    //         'limit_asset' => 100000000,
    //         'receive_card' => 3,  //1黄金 2铂金 3钻石
    //         'limit_direction' => 'up',
    //     ],
    // ],
/*     'gongfu_card' => [
        1 => [
            'id' => 1,
            'name' => '共富工程专属黄金卡',
            'img' => env('app.host').'/huangjin.png',
            'amount' => 300,
            'limit_tax' => 10000000,
            'limit_direction' => 'down',
        ],
        2 => [
            'id' => 2,
            'name' => '共富工程专属铂金卡',
            'img' => env('app.host').'/bojin.png',
            'amount' => 500,
            'limit_tax' => 50000000,
            'limit_direction' => 'down',
        ],
        3 => [
            'id' => 3,
            'name' => '共富工程专属钻石卡',
            'img' => env('app.host').'/zuanshi.png',
            'amount' => 1000,
            'limit_tax' => 50000000,
            'limit_direction' => 'up',
        ],
    ], */
);
