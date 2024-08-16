<?php

namespace app\middleware;
use app\model\Setting;

class ResWarp
{
    
    protected $noArr = [
        'common/uploadfile',
        'common/paynotify',
        'common/payNotify',
        'common/paynotify2',
        'common/payNotify2',
        'common/paynotify3',
        'common/payNotify3',
        'common/paynotify4',
        'common/payNotify4',
        'common/paynotify5',
        'common/payNotify5',
        'common/paynotify6',
        'common/payNotify6',
        'common/paynotify7',
        'common/payNotify7',
        'common/paynotify8',
        'common/payNotify8',
        'common/paynotify9',
        'common/payNotify9',
        'common/paynotify10',
        'common/payNotify10',
        'common/paynotify11',
        'common/payNotify11',
        'common/paynotify12',
        'common/payNotify12',
        'common/paynotify13',
        'common/payNotify13',
        'common/payNotify_daxiang',
        'common/paynotify_daxiang',
        'common/paynotify_huitong',
        'common/paynotify_sifang',
        'common/paynotify_999',
        'common/paynotify_xxpay',
        'common/paynotify_startlink',
        'common/checkDownload',
        //'common/captchaimg',
    ];

    public function handle($request, \Closure $next)
    {
        
		$response = $next($request);
        $pathInfo = $request->pathinfo();
        if(in_array(strtolower($pathInfo),$this->noArr)){
            return $response;
        }
        $setting=Setting::getSetting();
        if($setting['is_req_encypt']==1){
            // 添加中间件执行代码
            $key=config('config.req_aes_key');
            $iv=config('config.req_aes_iv');

            $json = $response->getContent();
            //$jsonData = encryptAES($json,$key,$iv);
            $cryptData = encryptAES($json,$key,$iv);
            $data ='{"c":"'.$cryptData.'"}';
            //$data=json_encode(['c'=>$cryptData]);
            $response->content($data);
        }
        return $response;
    }
}