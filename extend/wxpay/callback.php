<?php
/** Error reporting */
#微信回调
error_reporting(E_ALL & ~E_NOTICE);
error_reporting(0);

header('Content-Type: text/html; charset=utf-8');
include_once('mysql.php');

$db = include_once(__DIR__.'/../../app/database.php');
$db_helper = new mysql($db['hostname'], $db['username'], $db['password'], $db['database'], $conn, 'utf8');

//$res = $db_helper->query("select * from clt_parkplace");
//$arr = $db_helper->fetch_array($res);

$receipt = $_REQUEST;
if ($receipt == null) {
    $receipt = file_get_contents("php://input");
    
}
#$db_helper->query("insert into clt_test (test) values('{$receipt}')");
if ($receipt == null) {
    $receipt = $GLOBALS['HTTP_RAW_POST_DATA'];
}

$post_data = mz_xmlToArray($receipt);

$json = json_encode($post_data,1);
$db_helper->query("insert into clt_test (test) values('{$json}')");

$postSign = $post_data['sign'];

#返回信息
$ordernumber = $post_data['out_trade_no'];
$total_fee = $post_data['total_fee'];  
$open_id = $post_data['openid'];  
$time = $post_data['time_end'];
$addtime = time();

#订单id
#$ordernumber = '2018062010210110';
$res = $db_helper->query("select * from clt_order where orderid='{$ordernumber}'");
$order = $db_helper->fetch_assoc($res);
$orderid = $order['id'];

if ($post_data['return_code'] == 'SUCCESS' && $postSign) {
    $columnName = "";
    $value = "";
    #$db_helper->query("insert into clt_wxback (order_sn,total_fee,open_id,time,addtime) values ('{$ordernumber}',{$total_fee},'{$open_id}','{$time}','{$addtime}')");
    $url = "http://{$_SERVER['HTTP_HOST']}/bee/Mall/paysuc";
    $postData = array("orderid"=>$orderid);
    
    $return = mz_http_send($url, $postData, 'POST');
    echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
} else {
    // 写个日志记录  
    file_put_contents('wxpayerrorlog.txt', $post_data['return_code'] . PHP_EOL, FILE_APPEND);
    echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
}


function mz_xmlToArray($xml) {
    libxml_disable_entity_loader(true);
    $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    $val = json_decode(json_encode($xmlstring), true);
    return $val;
}

function mz_http_send($url, $params, $method = 'GET', $header = array(), $multi = false) {
    $opts = array(
        CURLOPT_TIMEOUT => 30,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $header
    );
    /* 根据请求类型设置特定参数 */
    switch (strtoupper($method)) {
        case 'GET':
            $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
            break;
        case 'POST':
            //判断是否传输文件 
            $params = $multi ? $params : http_build_query($params);
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_POST] = 1;
            $opts[CURLOPT_POSTFIELDS] = $params;
            break;
        default:
            throw new Exception('不支持的请求方式！');
    }
    /* 初始化并执行curl请求 */
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $data = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error)
        throw new Exception('请求发生错误：' . $error);
    return $data;
}
?>