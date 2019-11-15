<?php
 /* 
  * Copyright (C) 2017 All rights reserved.
 *   
 * @File UserTest.php
 * @Brief 
 * @Author 毛子
 * @Version 1.0
 * @Date 2017-12-26
 * @Remark 微信
 */
namespace app\bee\controller;
use think\Config;

class Sign extends Common{
    
    public function _initialize() {
        parent::_initialize();
        #刷新微信access
        $this->access();
    }
    
    public function index() {
        $sign = $this->getSign();
        print_r($sign);
        return $this->fetch('', array(
            'sign'=>$sign,
        ));
    }
    
    public function getSign() {
        $url = "http://".$_SERVER['HTTP_HOST']."/wx/index";
        $appinfo = db("wxconfig")->find();
        $jsapiTicket = db('wxjsticket')->value("ticket");
        $timestamp = time();
        $nonceStr = $this->createNonceStr();
        
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);
        $signPackage = array(
            "appId" => $appinfo['appid'],
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "signature" => $signature,
        );
        return $signPackage;
    }
	
	public function getSignApi2() {
        $url = "http://fmall.yuntim.cn/bee-Userinfo-myshare.html";
        $appinfo = db("wxconfig")->find();
        $jsapiTicket = db('wxjsticket')->value("ticket");
        $timestamp = time();
        $nonceStr = $this->createNonceStr();
        
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);
        $signPackage = array(
            "appId" => $appinfo['appid'],
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "signature" => $signature,
        );
        return mz_apisuc("成功", $signPackage);
    }
    
    public function getSignApi() {
        $url = "http://fmall.yuntim.cn/bee-Mall-orderdetail.html";
        $appinfo = db("wxconfig")->find();
        $jsapiTicket = db('wxjsticket')->value("ticket");
        $timestamp = time();
        $nonceStr = $this->createNonceStr();
        
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);
        $signPackage = array(
            "appId" => $appinfo['appid'],
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "signature" => $signature,
        );
        return mz_apisuc("成功", $signPackage);
    }
    
    public function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    
    #微信刷新token
    public function access() { //微信access_token刷新
        $app_info = db('wxconfig')->find();
        $acc_info = db('wxaccess')->find();
        $res = array();
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$app_info['appid']}&secret={$app_info['appsec']}";
        
        if ($app_info) {
            if ($acc_info) {
                $diff_time = time() - $acc_info['addtime'];
                if ($diff_time >= 7000) {
                    $return = json_decode(mz_http_send($url, array(), 'POST'), 1);
                    if ($return['access_token']) {
                        $res['access_token'] = $return['access_token'];
                        $ins['access_token'] = $return['access_token'];
                        $ins['addtime'] = time();
                        $ins['adddate'] = date("Y-m-d H:i:s", time());
                        db('wxaccess')->where("id='{$acc_info['id']}'")->update($ins);
                    }
                } else {
                    $res['access_token'] = $acc_info['access_token'];
                }
            } else {
                $return = json_decode(mz_http_send($url, array(), 'POST'), 1);
                if ($return['access_token']) {
                    $res['access_token'] = $return['access_token'];
                    $ins['access_token'] = $return['access_token'];
                    $ins['addtime'] = time();
                    $ins['adddate'] = date("Y-m-d H:i:s", time());
                    db('wxaccess')->insert($ins);
                }
            }

            //jsapi_ticket
            $url2 = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=" . $res['access_token'] . "&type=jsapi";
            $info2 = db('wxjsticket')->find();
            if ($info2) {
                $diff_time = time() - $info2['addtime'];
                if ($diff_time >= 7000) {
                    $return = json_decode(mz_http_send($url2, array(), 'POST'), 1);
                    if ($return['errcode'] === 0) {
                        $ins['ticket'] = $return['ticket'];
                        $ins['addtime'] = time();
                        $ins['adddate'] = date("Y-m-d H:i:s", time());
                        db('wxjsticket')->where("id='{$info2['id']}'")->update($ins);
                    }
                }
            } else {
                $return = json_decode(mz_http_send($url2, array(), 'POST'), 1);
                if ($return['errcode'] === 0) {
                    $ins['ticket'] = $return['ticket'];
                    $ins['addtime'] = time();
                    $ins['adddate'] = date("Y-m-d H:i:s", time());
                    db('wxjsticket')->insert($ins);
                }
            }
        }
    }
    
}