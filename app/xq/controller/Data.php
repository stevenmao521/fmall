<?php
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\Form;//表单
use app\xq\controller\Helper as Helper;//工具类

class Data extends Common{
    protected $modname; #模块名称
    protected $dao; #默认模型
    protected $fields; #字段
    protected $lfields;
    protected $controller; #控制器
    protected $log_mod; #日志模型
    protected $logid; #日志模型id
    protected $form;    #表单
    protected $helper;  #工具
    #初始化
    public function _initialize() {
        
        parent::_initialize();
        
        $this->controller = "data";
        $this->modname = "数据统计";
        
        $this->moduleid = $this->mod[MODULE_NAME]; #模型id
        $this->logid = 2;
        
        $this->dao = db(MODULE_NAME); #当前模型
        $this->log_mod = db('logs');
        $this->form = new Form();
        $this->helper = new Helper();
        
        #初始化模版赋值
        $this->fields = $this->helper->getEditField($this->moduleid);#编辑字段
        $this->lfields = $this->helper->getLfield($this->moduleid);#列表字段
        
        #是否有子列表
        $mod_info = db("module")->where("name='{$this->controller}'")->find();
        if ($mod_info['olist']) {
            $this->olist = db($mod_info['olist']);
            $this->assign('olist', $mod_info['olist']);
        }
        $this->assign('moduleid', $this->moduleid);
        $this->assign ('fields',$this->fields);#新增编辑字段
        $this->assign('modname', $this->modname);
    }
    
    public function index() {
        $info = array();
        $member_model = db("members");
        $flow_model = db("memberflow");
        $order_model = db("order");
        $orderdetail_model = db("orderdetail");
        
        $info['memnums'] = $member_model->where("istrash=0")->count();
        #销量
        $order = $order_model->where("status=4")->select();
        $bottels = 0;
        foreach($order as $k=>$v) {
            $bottels += $orderdetail_model->where("oid='{$v['id']}'")->sum("bottles");
        }
        $info['selnums'] = $bottels;
        #完成订单量
        $info['ordernums'] = count($order);
        #反佣
        $info['rebate'] = $flow_model->where("type=1")->sum("money");
        
        $this->assign("info", $info);
        return $this->fetch();
    }
    
}