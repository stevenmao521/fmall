<?php
    //获得接口认证
    $timestamp = $_GET['timestamp'];
    $nonce = $_GET['nonce'];
    $token = 'maozi521';
    $signature = $_GET['signature'];
    //将参数字典化排序
    $tmpArr = array($timestamp,$nonce,$token);
    sort($tmpArr);
    $judgeArr = implode('',$tmpArr);
    $judge = sha1($judgeArr);
    //判断是否符合
    if($judge == $signature)
    {
        echo $_GET['echostr'];
        exit;
    }
	
?>