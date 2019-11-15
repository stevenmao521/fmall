<?php
 /* 
  * Copyright (C) 2017 All rights reserved.
 *   
 * @File UserTest.php
 * @Brief 
 * @Author 毛子
 * @Version 1.0
 * @Date 2017-12-26
 * @Remark 账户
 */
namespace app\bee\controller;
use think\Config;
use think\Db;
use app\bee\service\HelperDao;

class Passport extends Common {
    
    protected $helper;
    protected $mem_model;


    public function _initialize() {
        parent::_initialize();
        
        $this->helper = new HelperDao();
        $this->mem_model = model("Members");
    }
    
    #来自分享
    public function share() {
        $id = input("id");
        $uid = session("userid");
        
		
		#查看是否有缓存的父亲id
		if (!$id) {
			$id = session("parentid");
		}
		
		$parent = $this->mem_model->where("serviceid='{$id}'")->find();
        if (!$parent['serviceid']) {
            $this->redirect("bee/Index/index");
        }
        
        if ($uid) {
            $user = $this->mem_model->where("id='{$uid}'")->find();
            if (!$user) {
                session("userid",null);
            } else {
                $this->redirect("bee/Index/index");
            }
        }
        
        $serviceid = $parent['serviceid'];
        #存储
        session("parentid", $serviceid);
        
        $wx_info = db("wx_user")->find();
        $appid = $wx_info['appid'];
        //微信网页授权
        #回调页面 绑定页面
        $host = Config::get('host');
        #未绑定手机跳转手机绑定页面
        #$jump = $_SERVER['REQUEST_URI'];
        $bind = "bee-Passport-index";
        $redirect_uri = urlencode('http://'.$host."/".$bind);

        $url ="https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_userinfo&state={$serviceid}&connect_redirect=1#wechat_redirect";
        header("Location:".$url);
        exit;
    }
    
    
    #授权页面
    public function index() {
        $code = $_GET["code"];
        $state = $_GET["state"];
        
        $wx_info = db("wx_user")->find();
        $appid = $wx_info['appid'];
        $secret = $wx_info['appsecret'];
        
        if ($state == 1) {
            if (session("parentid")){
                $state = session("parentid");
            }
        }
        
        #echo $code;
        #db("smscode")->insert(array('type'=>2,'mobile'=>13452415831,'code'=>$code));
        #exit;
        
        //第一步:取得openid
        $oauth2Url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code";
        $oauth2 = $this->getJson($oauth2Url);
        
        
        //第二步:根据全局access_token和openid查询用户信息
        $access_token = $oauth2["access_token"];
        $openid = $oauth2['openid'];
        $get_user_info_url = "https://api.weixin.qq.com/sns/userinfo?access_token=$access_token&openid=$openid&lang=zh_CN";
        $userinfo = $this->getJson($get_user_info_url);
        
        if($userinfo['openid']){
            
            $openid = $userinfo['openid'];
            $nickname = $userinfo['nickname'];
            $headimg = $userinfo['headimgurl'];
            
            #用户是否存在
            $hasuser = $this->mem_model->where("openid='{$userinfo['openid']}'")->find();
            
            if ($hasuser) {
                
                #更新用户信息
                $this->mem_model
                    ->where(array('id' => $hasuser['id']))
                    ->update(
                        array(
                        'nickname' => $this->filterEmoji($nickname),
                        'avatarurl' => $headimg,
                        'lasttime' => time()
                        )
                    );
                session("userid", $hasuser['id']);
                #是否绑定手机
                if (!$hasuser['mobile']) {
                    #$this->redirect("bee/Passport/bindPhone",["state"=>$state]);
					$this->redirect("bee/Index/index");
                } else {
                    $this->redirect("bee/Index/index");
                }
            }else{
                
                if ($state != 1) {
                    $parent = $this->mem_model->where("serviceid='{$state}'")->find();
                    if (!$parent) {
                        $parent['id'] = 0;
                        $parent['serviceid'] = "";
                    }
                } else {
                    $parent['id'] = 0;
                    $parent['serviceid'] = "";
                }
                $ins_data = array(
                    'nickname' => $this->filterEmoji($nickname), 
                    'openid' => $openid, 
                    'avatarurl' => $headimg, 
                    'createtime' => time(),
                    'parent_id' => $parent['id'],
                    'parent_service' => $parent['serviceid']
                );
                
                $res = $this->mem_model->insertGetId($ins_data);
                if ($res) {
                    if ($parent['id']) {
                        db('membership')->insert(array('uid'=>$res, 'parentid'=>$parent['id'], 'createtime'=>time()));
                    } 
                    $this->mem_model->where("id='{$res}'")->update(array("serviceid"=>"FM".$res));
                    session("userid", $res);
                    #$this->redirect("bee/Passport/bindPhone",["state"=>$state]);
					
					$this->redirect("bee/Index/index");
                } else {
                    $this->redirect("bee/Index/index");
                }
            }
        } else {
            $this->redirect("bee/Index/index");
        }
    }
    
    #绑定手机号
    public function bindPhone() {
        $ispost = input("ispost");
        $phone = input("phone");
        $code = input("code");
        $serviceid = input("serviceid");
        
        $uid = session("userid");
        if (!$uid) {
            $this->checklogin();
        }
        
        #用户信息
        $meminfo = $this->mem_model->where("id='{$uid}'")->find();
        
        if ($ispost) {
            $checkCode = $this->helper->checkCode($phone, $code, 1);
            if ($checkCode) {
                #检查服务商ID
                if (!$meminfo['parent_id']) {
                    if (!$serviceid) {
                        return mz_apierror("缺少服务商号码");
                    }
                    $parent = $this->mem_model->where("serviceid='{$serviceid}'")->find();
                    if (!$parent) {
                        return mz_apierror("服务商不存在");
                    }
                    $this->mem_model->where("id='{$meminfo['id']}'")->update(array(
                        'parent_id'=>$parent['id'],
                        'parent_service'=>$parent['serviceid']
                    ));
                    
                    #membership
                    $ship = db('membership')->where("uid='{$uid}'")->find();
                    if (!$ship) {
                        db('membership')->insert(array('uid'=>$uid, 'parentid'=>$parent['id'], 'createtime'=>time()));
                    } else {
                        db('membership')->where("id='{$ship['id']}'")->update(array('parentid'=>$parent['id'], 'createtime'=>time()));
                    }
                }
                
                #绑定成功
                $mem_info = $this->mem_model->where("id='{$uid}'")->find();
                if ($mem_info['mobile']) {
                    return mz_apierror("已经绑定过手机");
                } else {
                    $res = $this->mem_model->where("id='{$uid}'")->update(["mobile"=>$phone]);
                    if ($res) {
                        return mz_apisuc("绑定成功");
                    } else {
                        return mz_apierror("绑定失败");
                    }
                }
            } else {
                return mz_apierror("验证码错误");
            }
        }
        
        $this->assign("meminfo", $meminfo);
        $this->assign("title", "微信登录-绑定手机号");
        return $this->fetch();
    }
    
    #退出登录
    public function logout() {
        session(null);
        $this->redirect('Index/index');
    }
	
	
	function filterEmoji($str)
	{
	  $str = preg_replace_callback( '/./u',
		  function (array $match) {
			return strlen($match[0]) >= 4 ? '' : $match[0];
		  },
		  $str);
	   return $str;
	}
    
    public function getJson($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output, true);
    }

}