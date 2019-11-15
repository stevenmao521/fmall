<?php
namespace app\api\controller;
use think\Input;
use think\Db;
use clt\Leftnav;
use think\Request;
use think\Controller;

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
    }
    
    public function _empty(){
        return $this->error('空操作，返回上次访问页面中...');
    }
}