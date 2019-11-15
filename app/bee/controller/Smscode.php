<?php
 /* 
  * Copyright (C) 2017 All rights reserved.
 *   
 * @File UserTest.php
 * @Brief 
 * @Author 毛子
 * @Version 1.0
 * @Date 2017-12-26
 * @Remark 服务端接口 发送短信
 */
namespace app\bee\controller;
use think\Config;
use clt\SmsSingleSender;


class Smscode extends Common{
    
    protected $mem_model;
    protected $sms_model;

    public function _initialize() {
        parent::_initialize();
        $this->mem_model = model('members');
        $this->sms_model = model('smscode');
    }
    
    #type 1:绑定
    public function send() {
        $mobile = mz_checkfield('phone', true, '缺少手机号');
        $type = mz_checkfield('type', true, '缺少type');
        
        $hasMobile = $this->mem_model
                ->where(["mobile"=>$mobile])
                ->find();
        
        switch ($type) {
            case 1:
                if ($hasMobile) {
                    return mz_apierror('该手机已绑定过');
                }
                break;
        }
        $quick = $this->check_code($mobile, $type);
        
        if (!$quick) {
            return mz_apierror("发送过快");
        }
        $has_send = $this->send_code($mobile, $type);
        if ($has_send) {
            #暂时屏蔽短信发送 返回验证码
            #return mz_apisuc("短信发送成功 验证码为：".$has_send);
            return mz_apisuc("短信发送成功");
            #return mz_apisuc("短信发送成功");
        } else {
            return mz_apierror("短信发送失败");
        }
    }
    
    #发送过快
    public function check_code($mobile, $type) {
        $info = $this->sms_model->where("mobile='{$mobile}' and type='{$type}'")->find();
        if ($info) {
            $diff = time() - $info['createtime'];
            #echo $diff;exit;
            if ($diff <= 60) {
                return false;
            } else {
                $this->sms_model->where("id='{$info['id']}'")->delete();
            }
        }
        return true;
    } 
    
    public function send_code($mobile, $type) {
        #暂时屏蔽短信接口
        $mobile_code = mz_random(4,1);
        
        #短信接口
        $appid = 1400254722;
        $appkey = "723b9d61d89abc4a7e9f596116a08fc3";
        $singleSender = new SmsSingleSender($appid, $appkey);

        #普通单发
        $result = $singleSender->send(0, "86", $mobile, "您的验证码是{$mobile_code}，请于1分钟内填写。如非本人操作，请忽略本短信。", "", "");
        
        $res = json_decode($result,1);
        if ($res['result'] == 0) {
            $data = array(
                'type'=>$type,
                'mobile'=>$mobile,
                'createtime'=>time(),
                'code'=>$mobile_code
            );
            $this->sms_model->insert($data);
            return $mobile_code;
        } else {
            return false;
        }
    }
    
}