<?php
namespace app\common\libs;
use think\App;
use think\Response;
class NullTrace
{
    public function output(App $app, Response $response, array $log = [])
    {
        return null;
    }
}