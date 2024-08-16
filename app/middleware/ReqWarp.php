<?php
declare (strict_types = 1);

namespace app\middleware;

use app\model\Setting;

class ReqWarp
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
        'paynotify_startlink',
        'common/checkDownload',
    ];
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        $pathInfo = $request->pathinfo();
        if(in_array(strtolower($pathInfo),$this->noArr)){
            return $next($request);
        }
        $setting=Setting::getSetting();
        if($setting['is_req_encypt']==1){
       
            $key=config('config.req_aes_key');
            $iv=config('config.req_aes_iv');
            $postData =[];
            $getData=[];
            if($request->has('d','post') && $request->post('d')){
                $d = $request->post('d');
                $jsonData = decryptAES($d,$key,$iv);
                $postData = json_decode($jsonData,true);
            }
            if($request->has('d','get') && $request->get('d')){
                $d=$request->get('d');
                $jsonData = decryptAES($d,$key,$iv);
                $getData = json_decode($jsonData,true);
            }
            $request = $request->withPost($postData)
                                ->withGet($getData);
        }
        return $next($request);
        
    }



}
