<?php

 function findCurrentStage($startDate, $dateArr, $holidayStart, $holidayEnd,$todayDate=0) {
        $holidayStart = new \DateTime($holidayStart);
        $holidayEnd = new \DateTime($holidayEnd);
        if($todayDate==0){
            $today = new \DateTime();
        }else{
            $today = new \DateTime($todayDate);
        }       
        $date = new \DateTime($startDate);

        foreach ($dateArr as $key=>$stage) {
            // 如果新的日期在假期期间，将开始日期设置为假期结束后的日期,加上假期开始日期-开始日期的天数
            $itemDateStr = $date->format('Y-m-d');

            // 将日期增加到阶段的天数
            $date->modify('+' . $stage['number'] . ' days');
    
            if ($date > $holidayStart && $date < $holidayEnd) {
                $itemDate = new \DateTime($itemDateStr);
                $interval = $itemDate->diff($holidayStart)->days;
                $remain = $stage['number'] - $interval;
                echo $interval."\n";
                echo $itemDateStr."\n";
                $date = clone $holidayEnd;
                $date->modify("+$remain day");
            }
    
            // 检查今天是否在当前阶段
            echo "$key ".$date->format('Y-m-d H:i:s')."\n";
            if ($today < $date) {
                //$stage['status'] = '审核中';
                return $key;
            }/* else{
                $stage['status'] = '已完成';
            } */
        }
    
        return count($dateArr) - 1;
    }

    $process = [
        ['name' => '采集身份信息', 'number' => 1, 'audit_status' => 1],
        ['name' => '被动收益结算', 'number' => 2, 'audit_status' => 1],
        ['name' => '财务部审核', 'number' => 3, 'audit_status' => 1],
        ['name' => '扶贫办审核', 'number' => 7, 'audit_status' => 0],
        ['name' => '财务部打款', 'number' => 1, 'audit_status' => 0],
    ];

    $stage = findCurrentStage('2024-02-12 00:00:00',$process,'2024-02-09 00:00:00','2024-02-17 23:59:59','2024-02-30 00:00:00');
    echo "stage=".$stage."\n";
    foreach($process as $k=>$v){
        if($k<$stage){
            $statusText = $v['audit_status'] ==1 ? '已通过':'未通过';
            $process[$k]['status']=$statusText;
            //$arr = ['name'=>$v['name'],'status'=>'已完成'];
        }else if($stage==$k){
            if(isset($process[$k-1]['status'])) 
                $process[$k]['status']= $process[$k-1]['status'] == '未通过' ? '待审核' :'审核中';
             else {
                $process[$k]['status']= '审核中';
            }
            
        }else{
            $process[$k]['status']='待审核';
        }
        if($stage>=3){
            $process[3]['status'] = '审核中';
            $process[4]['status'] = '待审核';
        }
        //unset($process[$k]['number']);
    }
    print_r($process);