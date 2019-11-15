<?php
namespace app\bee\controller;
use think\Input;
use think\Db;
use clt\Leftnav;
use think\Request;
use think\Controller;
use think\Config;

class Common extends Controller{
    protected $pagesize;
    public function _initialize(){
        $sys = F('System');
        $this->assign('sys',$sys);
        //获取控制方法
        $request = Request::instance();
        
        //print_r($request);
        $action = $request->action();
        $controller = $request->controller();
        $this->assign('action',($action));
        $this->assign('controller',strtolower($controller));
        define('MODULE_NAME',strtolower($controller));
        define('ACTION_NAME',strtolower($action));
        
        #用户登录检测
        #session("userid", 10010);
        #session("userid", null);
    }
    
    public function _empty(){
        return $this->error('空操作，返回上次访问页面中...');
    }
    
    #检查登陆
    public function checklogin() {
        $uid = session("userid");
        $wx_info = db("wx_user")->find();
        $appid = $wx_info['appid'];
        
        if (!$uid) {
            
            //微信网页授权
            #回调页面 绑定页面
            $host = Config::get('host');
            #未绑定手机跳转手机绑定页面
            #$jump = $_SERVER['REQUEST_URI'];
            $bind = "bee-Passport-index";
            $redirect_uri = urlencode('http://'.$host."/".$bind);
            $url ="https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_userinfo&state=1&connect_redirect=1#wechat_redirect";
            header("Location:".$url);
            exit;
        } else {
            
            $user = db('members')->where("id='{$uid}'")->find();
            
            if ($user) {
                if (!$user['mobile']) {
                    #$this->redirect("bee/Passport/bindPhone");
                }
            } else {
                session("userid",null);
                //微信网页授权
                #回调页面 绑定页面
                $host = Config::get('host');
                #未绑定手机跳转手机绑定页面
                #$jump = $_SERVER['REQUEST_URI'];
                $bind = "bee-Passport-index";
                $redirect_uri = urlencode('http://'.$host."/".$bind);
                
                $url ="https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_userinfo&state=1&connect_redirect=1#wechat_redirect";
                header("Location:".$url);
                exit;
            }
        }
    }
    
}