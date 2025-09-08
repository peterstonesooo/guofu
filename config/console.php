<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'curd'                => 'app\common\command\Curd',
        'rule'                => 'app\common\command\Rule',
        'checkBonus'          => 'app\common\command\CheckBonus',
        'activeRank'          => 'app\common\command\ActiveRank',
        'checkSubsidy'        => 'app\common\command\CheckSubsidy',
        'checkEvents'         => 'app\common\command\CheckEvents',
        // 'sendCashReward'  =>  'app\common\command\SendCashReward',
        // 'autoWithdrawAudit'  =>  'app\common\command\AutoWithdrawAudit',
        // 'genarateEthAdress' =>  'app\common\command\GenarateEthAdress',
        // 'checkAssetBonus'  =>  'app\common\command\CheckAssetBonus',
        // 'checkShopBonus'  =>  'app\common\command\CheckShopBonus',
        // 'task'  =>  'app\common\command\Task',
        // 'taska'  =>  'app\common\command\Taska',
        // 'checkProjectRate'  =>  'app\common\command\CheckProjectRate',
        // 'checkBonusReview'  =>  'app\common\command\CheckBonusReview',
        // 'checkAuthBonus'  =>  'app\common\command\CheckAuthBonus',
        'sysncUser'           => 'app\common\command\SysncUser',
        'batchRecharge'       => 'app\common\command\BatchRechargeProcess',
        'ProjectLimited'      => 'app\common\command\ProjectLimited',
        'mettingAudit'        => 'app\common\command\MettingAudit',
        'recoverUserBalances' => 'app\common\command\RecoverUserBalances',
    ],
];
