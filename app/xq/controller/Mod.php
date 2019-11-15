<?php
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\Form;//表单
use app\xq\controller\Helper as Helper;//工具类

#自列表类
class Mod extends Common{
    protected $model;#当前模型
    protected $pid;#当前模型列表父id
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
        #当前自列表模型
        $this->model = input("mod");
        $this->assign("model", $this->model);
        $this->assign("pid", $this->pid);
        
        #模型信息
        $mod_info = db("module")->where("name='{$this->model}'")->find();
        
        $this->controller = 'mod';
        $this->modname = $mod_info['title'];
        $this->moduleid = $this->mod[$this->model]; #模型id
        $this->logid = 5;
        
        $this->dao = db($this->model); #当前模型
        $this->log_mod = db('logs');
        $this->form = new Form();
        $this->helper = new Helper();
        
        #初始化模版赋值
        $this->fields = $this->helper->getEditField($this->moduleid);#编辑字段
        $this->lfields = $this->helper->getLfield($this->moduleid);#列表字段
        
        $this->assign ('fields',$this->fields);#新增编辑字段
        $this->assign('modname', $this->modname);
    }
    
    
    #会员列表
    public function index(){
        if(request()->isPost()){
            $sysfield = array('catid','userid','username','title','thumb','keywords','description','posid','status','createtime','url','template','hits');
            $list = db('field')->where("moduleid=".input('param.id'))->order('listorder asc,id asc')->select();
            $list_arr = array();
            foreach ($list as $k=>$v){
                if(!in_array($v['field'],$sysfield)){
                    $list_arr[] = $v;
                }
            }
            $this->assign('list', $list_arr);
            return $result = ['code'=>0,'msg'=>'获取成功!','data'=>$list_arr,'rel'=>1];
        }else{
            return $this->fetch();
        }
    }
    #是否列表
    public function listStatus(){
        $map['id']=input('post.id');
        //判断当前状态情况
        $field = db('field');
        $status=$field->where($map)->value('islist');
        if($status==1){
            $data['islist'] = 0;
        }else{
            $data['islist'] = 1;
        }
        $field->where($map)->setField($data);
        return $data;
    }
    
    #是否筛选
    public function selStatus(){
        $map['id']=input('post.id');
        //判断当前状态情况
        $field = db('field');
        $status=$field->where($map)->value('issel');
        if($status==1){
            $data['issel'] = 0;
        }else{
            $data['issel'] = 1;
        }
        $field->where($map)->setField($data);
        return $data;
    }
    
    #是否排序
    public function sortStatus(){
        $map['id']=input('post.id');
        //判断当前状态情况
        $field = db('field');
        $status=$field->where($map)->value('issort');
        if($status==1){
            $data['issort'] = 0;
        }else{
            $data['issort'] = 1;
        }
        $field->where($map)->setField($data);
        return $data;
    }
    #是否统计
    public function countStatus(){
        $map['id']=input('post.id');
        //判断当前状态情况
        $field = db('field');
        $status=$field->where($map)->value('iscount');
        if($status==1){
            $data['iscount'] = 0;
        }else{
            $data['iscount'] = 1;
        }
        $field->where($map)->setField($data);
        return $data;
    }
    //字段排序
    public function listOrder(){
        $model =db('field');
        $data = input('post.');
        if($model->update($data)!==false){
            return $result = ['msg' => '操作成功！','url'=>url('field',array('id'=>input('post.moduleid'))), 'code' => 1];
        }else{
            return $result = ['code'=>0,'msg'=>'操作失败！'];
        }
    }
    #列表宽度
    public function editWidth(){
        $model =db('field');
        $data = input('post.');
        if($model->update($data)!==false){
            return $result = ['msg' => '操作成功！','url'=>url('field',array('id'=>input('post.moduleid'))), 'code' => 1];
        }else{
            return $result = ['code'=>0,'msg'=>'操作失败！'];
        }
    }
}