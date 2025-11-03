<?php

return [
    '主页'     =>
        [
            'icon' => 'fa-home',
            'url'  => 'admin/Home/index',
        ],
    '会员中心' =>
        [
            'icon' => 'fa-user',
            'url'  =>
                [
                    '会员管理'             =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/User/userList',
                        ],
                    '实名认证'             =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Realname/realnameList',
                        ],
                    '会员资金明细'         =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/UserBalanceLog/userBalanceLogList',
                        ],
                    '会员积分记录'         =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/UserBalanceLog/userIntegralLogList',
                        ],
                    '抽奖次数明细'         =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/UserLotteryLog/userLotteryLogList',
                        ],
                    '收货地址'             =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/UserDelivery/userDeliveryList',
                        ],
                    '邀请现金红包配置'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/InviteCashConfig/index',
                        ],
                    '邀请现金红包发放记录' =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/InviteCashLog/index',
                        ],
                ],
        ],
    '交易中心' =>
        [
            'icon' => 'fa-cubes',
            'url'  =>
                [
                    '项目管理一期' =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Project/projectList',
                        ],
                    '项目分类'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Category/index',
                        ],
                    /*'税务抵用券' =>
                       array(
                           'icon' => 'fa-circle-o',
                           'url' => 'admin/Tax/projectList',
                       ),
                   '共富专属卡' =>
                       array(
                           'icon' => 'fa-circle-o',
                           'url' => 'admin/Card/projectList',
                       ), */
                    '交易订单'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Order/orderList',
                        ],
                    /*                     '修改分红天数'=>array(
                                            'icon' => 'fa-circle-o',
                                            'url' => 'admin/Order/addTime',
                                        ), */
                    '充值记录'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Capital/topupList',
                        ],
                    '提现记录'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Capital/withdrawList?log_type=0',
                        ],
                    '纳税管理'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/TaxOrder/taxList',
                        ],
                    '公证管理'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Notarization/index',
                        ],
                    '保证金管理'   =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Bail/index',
                        ],
                    '银行卡管理'   =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Card/index',
                        ],
                    /*  'ecny提现' =>
                     array(
                         'icon' => 'fa-circle-o',
                         'url' => 'admin/Capital/withdrawList?log_type=7',
                     ),
                     '资产交接订单' =>
                         array(
                             'icon' => 'fa-circle-o',
                             'url' => 'admin/Order/assetOrderList',
                         ), */
                    /*                	    '流程审核' =>
                                            array(
                                                'icon' => 'fa-circle-o',
                                                'url' => 'admin/Process/processList',
                                            ), */
                    /*                	    'eny存银行测试' =>
                                            array(
                                                'icon' => 'fa-circle-o',
                                                'url' => 'admin/Capital/testEcnyDetail',
                                            ), */
                ],
        ],
    '设置中心' =>
        [
            'icon' => 'fa-gears',
            'url'  =>
                [
                    '支付渠道配置' =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/PaymentConfig/paymentConfigList',
                        ],
                    /*                     '股权K线图' =>
                                            array(
                                                'icon' => 'fa-circle-o',
                                                'url' => 'admin/KlineChart/klineChart',
                                            ), */
                    /*                     '会员等级管理' =>
                                            array(
                                                'icon' => 'fa-circle-o',
                                                'url' => 'admin/LevelConfig/levelConfigList',
                                            ), */
                    /*                     '轮播图设置' =>
                                            array(
                                                'icon' => 'fa-circle-o',
                                                'url' => 'admin/Banner/bannerList',
                                            ), */
                    /*                     '公司动态' =>
                                            array(
                                                'icon' => 'fa-circle-o',
                                                'url' => 'admin/SystemInfo/companyInfoList',
                                            ), */
                    '系统信息设置' =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/SystemInfo/systemInfoList',
                        ],
                    '常规配置'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Setting/settingList',
                        ],
                    '站内信'       =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Notice/noticeList',
                        ],
                    '排行设置'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/ActiveRank/index',
                        ],
                    '抽奖设置'     =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/LotteryConfig/index',
                        ],
                ],
        ],
    '商城管理' =>
        [
            'icon' => 'fa-gears',
            'url'  =>
                [
                    '商品分类' =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/Cate/cateList',
                        ],
                    '商品管理' =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/ShopGoods/goodsList',
                        ],
                    '订单管理' =>
                        [
                            'icon' => 'fa-circle-o',
                            'url'  => 'admin/ShopOrder/orderList',
                        ],
                ],

        ],
    '股权管理' => [
        'icon' => 'fa-pie-chart',  // 使用图表图标表示股权数据
        'url'  => [
            '股权配置'         => [
                'icon' => 'fa-cog',  // 使用齿轮图标表示配置
                'url'  => 'admin/Stock/index',
            ],
            '股权方案'         => [
                'icon' => 'fa-line-chart',
                'url'  => 'admin/StockPackage/index',
            ],
            '股权购买记录'     => [
                'icon' => 'fa-exchange',  // 使用交换图标表示交易
                'url'  => 'admin/StockTransaction/index',
            ],
            '股权方案购买记录' => [
                'icon' => 'fa-exchange',  // 使用交换图标表示交易
                'url'  => 'admin/PackagePurchase/index',
            ],
            '股权公告管理'     => [
                'icon' => 'fa-bullhorn',  // 使用喇叭图标表示公告
                'url'  => 'admin/StockAnnouncements/announcementList',
            ],
            '活动领取记录'     => [
                'icon' => 'fa-gift',  // 使用礼物图标表示活动领取
                'url'  => 'admin/StockActivityClaim/index',
            ],
            '产品管理'         => [
                'icon' => 'fa-cube',  // 使用立方体图标表示产品
                'url'  => 'admin/StockProduct/productList',
            ],
            '产品购买记录'     => [
                'icon' => 'fa-shopping-cart',  // 使用购物车图标表示购买记录
                'url'  => 'admin/StockProductPurchase/index',
            ],
        ],
    ],
    '财政管理' => [
        'icon' => 'fa-pie-chart',
        'url'  => [
            '财政调整'         => [
                'icon' => 'fa-pie-chart',
                'url'  => 'admin/FinanceApprovalConfig/approvalConfigList',
            ],
            '财政审核'         => [
                'icon' => 'fa-line-chart',
                'url'  => 'admin/FinanceApproval/applyList',
            ],
            '优先方案管理'     => [
                'icon' => 'fa-leaf',
                'url'  => 'admin/GreenConfig/greenConfigList',
            ],
            '优先通道购买记录' => [
                'icon' => 'fa-shopping-cart',
                'url'  => 'admin/GreenChannelOrder/greenChannelList',
            ],
        ],
    ],
    '补贴管理' => [
        'icon' => 'fa-money',
        'url'  => [
            '补贴列表'             => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/Butie/butieList',
            ],
            '补贴领取记录'         => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/ButieRecord/index',
            ],
            '申报补贴类型管理'     => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/DeclareSubsidyType/index',
            ],
            '申报补贴资金类型管理' => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/DeclareFundType/index',
            ],
            '申报补贴配置管理'     => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/DeclareSubsidyConfig/index',
            ],
            '申报补贴记录管理'     => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/DeclareRecord/index',
            ],
            '守护类型管理'         => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/GuardType/index',
            ],
            '守护配置管理'         => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/GuardConfig/index',
            ],
            '守护记录管理'         => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/GuardRecord/index',
            ],
        ],
    ],
    '会议管理' => [
        'icon' => 'fa-calendar',
        'url'  => [
            '会议列表'     => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/Meeting/meetingList',
            ],
            '会议签到配置' => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/MeetingSignConfig/index',
            ],
            '会议签到记录' => [
                'icon' => 'fa-circle-o',
                'url'  => 'admin/MeetingSignConfig/meetingSignRecord',
            ],
        ],
    ],


    '后台账号管理' =>
        [
            'icon' => 'fa-users',
            'url'  => 'admin/AdminUser/adminUserList',
        ],
];
