<?php
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\Form;//表单
use app\xq\controller\Helper as Helper;//工具类

class Product extends Common{
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
        
        $this->controller = "product";
        $this->modname = "商品";
        
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
    
    public function index()
    {
        if (request()->isPost()) {
            #筛选字段
            $post = input("post.");
            $sel_map = $this->helper->getMap($post, $this->moduleid);

            #列表
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

            $list = $this->dao
                    ->where($sel_map)
                    ->where("istrash=0")
                    ->order('id desc')
                    ->paginate(array('list_rows' => $pageSize, 'page' => $page))
                    ->toArray();
            
            foreach ($list['data'] as $k=>$v) {
                $list['data'][$k]['pics'] = mz_pic($v['pics']);
            }

            #时间转换
            $lfields = $this->lfields;
            if ($lfields) {
                foreach ($lfields as $k => $v) {
                    if ($v['type'] == 'datetime') {
                        $list['data'] = mz_formattime($list['data'], $v['field'], 1);
                    }
                }
            }

            #统计项 获取统计字段
            $count = $this->helper->getCountField($this->moduleid);
            if ($count['fields']) {
                $sum = array();
                foreach ($count['fields'] as $k => $v) {
                    $sum_total = $this->dao
                            ->where($sel_map)
                            ->sum($v['field']);
                    $sum[$v['field']] = $sum_total;
                }
            }
            return $result = ['code' => 0, 'msg' => '获取成功!', 'data' => $list['data'], 'count' => $list['total'], 'rel' => 1, 'sum' => $sum];
        }
        #列表字段
        $list_str = $this->helper->getlistField($this->moduleid);
        #筛选html
        $sel_html = $this->helper->getSelField($this->moduleid);
        #获取统计字段
        $count = $this->helper->getCountField($this->moduleid);

        #模版渲染
        return $this->fetch('', [
            'js_str' => $list_str['js_str'],
            'js_tmp' => $list_str['js_tmp'],
            'html_str' => $sel_html['html_str'],
            'js_val' => $sel_html['js_val'],
            'js_where' => $sel_html['js_where'],
            'js_date' => $sel_html['js_date'],
            'count_html1' => $count['html_1'],
            'count_html2' => $count['html_2'],
            'count_js' => $count['js'],
            'js_ewhere' => $sel_html['js_ewhere']
        ]);
    }
    
    //字段排序
    public function listOrder(){
        $model =db('productcate');
        $data = input('post.');
        if($model->update($data)!==false){
            return $result = ['msg' => '操作成功！','url'=>url('productcate/index'), 'code' => 1];
        }else{
            return $result = ['code'=>0,'msg'=>'操作失败！'];
        }
    }
    
}