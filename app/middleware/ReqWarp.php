<?php
declare (strict_types = 1);

namespace app\middleware;

use app\model\Setting;

class ReqWarp
{
    protected $noArr = [
        'common/uploadfile',
        'common/paynotify_hongya',
        'common/paynotify_haizei',
        'common/paynotify_start',
        'common/paynotify_xiangjiao',
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
