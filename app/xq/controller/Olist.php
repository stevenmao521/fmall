<?php
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\Form;//表单
use app\xq\controller\Helper as Helper;//工具类

#自列表类

class Olist extends Common{
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
        
        #父id
        $this->pid = input("pid");
        #当前自列表模型
        $this->model = input("mod");
        $this->assign("model", $this->model);
        $this->assign("pid", $this->pid);
        
        #模型信息
        $mod_info = db("module")->where("name='{$this->model}'")->find();
        
        $this->controller = 'olist';
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
            #筛选字段
            $post = input("post.");
            $sel_map = $this->helper->getMap($post, $this->moduleid);
            
            #列表
            $page =input('page')?input('page'):1;
            $pageSize =input('limit')?input('limit'):config('pageSize');
            $list = $this->dao
                ->where($sel_map)
                ->where("istrash=0 and pid={$this->pid}")
                ->order('id desc')
                ->paginate(array('list_rows'=>$pageSize,'page'=>$page))
                ->toArray();
            
            #时间转换
            $lfields = $this->lfields;
            if ($lfields) {
                foreach ($lfields as $k=>$v) {
                    if ($v['type'] == 'datetime') {
                        $list['data'] = mz_formattime($list['data'], $v['field'], 1);
                    }
                }
            }
            
            #统计项 获取统计字段
            $count = $this->helper->getCountField($this->moduleid);
            if ($count['fields']) {
                $sum = array();
                foreach ($count['fields'] as $k=>$v) {
                    $sum_total = $this->dao
                        ->where($sel_map)
                        ->where("istrash=0")
                        ->sum($v['field']);
                    $sum[$v['field']] = $sum_total;
                }
            }
            return $result = ['code'=>0,'msg'=>'获取成功!','data'=>$list['data'],'count'=>$list['total'],'rel'=>1,'sum'=>$sum];
        }
        #列表字段
        $list_str = $this->helper->getlistField($this->moduleid);
        #筛选html
        $sel_html = $this->helper->getSelField($this->moduleid);
        #获取统计字段
        $count = $this->helper->getCountField($this->moduleid);
        
        #模版渲染
        return $this->fetch('',[
            'js_str' => $list_str['js_str'],
            'js_tmp' => $list_str['js_tmp'],
            'html_str' => $sel_html['html_str'],
            'js_val' => $sel_html['js_val'],
            'js_where' => $sel_html['js_where'],
            'js_date' => $sel_html['js_date'],
            'count_html' => $count['html'],
            'count_js' => $count['js']
        ]);
    }
    
    #回收站
    public function trash(){
        if(request()->isPost()){
            #筛选字段
            $post = input("post.");
            $sel_map = $this->helper->getMap($post, $this->moduleid);
            
            #列表
            $page =input('page')?input('page'):1;
            $pageSize =input('limit')?input('limit'):config('pageSize');
            $list = $this->dao
                ->where($sel_map)
                ->where('istrash=1 and pid={$this->pid}')
                ->order('createtime desc')
                ->paginate(array('list_rows'=>$pageSize,'page'=>$page))
                ->toArray();
            
            #时间转换
            $lfields = $this->lfields;
            if ($lfields) {
                foreach ($lfields as $k=>$v) {
                    if ($v['type'] == 'datetime') {
                        $list['data'] = mz_formattime($list['data'], $v['field'], 2);
                    }
                }
            }
            return $result = ['code'=>0,'msg'=>'获取成功!','data'=>$list['data'],'count'=>$list['total'],'rel'=>1];
        }
        #列表字段
        $list_str = $this->helper->getlistField($this->moduleid);
        #筛选html
        $sel_html = $this->helper->getSelField($this->moduleid);
        #模版渲染
        return $this->fetch('',[
            'js_str' => $list_str['js_str'],
            'js_tmp' => $list_str['js_tmp'],
            'html_str' => $sel_html['html_str'],
            'js_val' => $sel_html['js_val'],
            'js_where' => $sel_html['js_where'],
            'js_date' => $sel_html['js_date']
        ]);
    }
    
    #会员列表
    public function log(){
        if(request()->isPost()){
            #筛选字段
            $post = input("post.");
            $sel_map = $this->helper->getMap($post, $this->logid);
            #列表
            $page =input('page')?input('page'):1;
            $pageSize =input('limit')?input('limit'):config('pageSize');
            $list = $this->log_mod
                ->where($sel_map)
                ->where("moduleid='{$this->moduleid}'")
                ->where("istrash=0")
                ->order('createtime desc')
                ->paginate(array('list_rows'=>$pageSize,'page'=>$page))
                ->toArray();
            #时间转换
            $lfields = $this->helper->getLfield($this->logid);#列表字段
            if ($lfields) {
                foreach ($lfields as $k=>$v) {
                    if ($v['type'] == 'datetime') {
                        $list['data'] = mz_formattime($list['data'], $v['field'], 2);
                    }
                }
            }
            return $result = ['code'=>0,'msg'=>'获取成功!','data'=>$list['data'],'count'=>$list['total'],'rel'=>1];
        }
        #列表字段
        $list_str = $this->helper->getlistField($this->logid);
        #筛选html
        $sel_html = $this->helper->getSelField($this->logid);
        #模版渲染
        return $this->fetch('',[
            'js_str' => $list_str['js_str'],
            'js_tmp' => $list_str['js_tmp'],
            'html_str' => $sel_html['html_str'],
            'js_val' => $sel_html['js_val'],
            'js_where' => $sel_html['js_where'],
            'js_date' => $sel_html['js_date']
        ]);
    }
    
    #新增
    public function add(){
        $form = new Form();
        $this->assign('form', $form);
        $this->assign('title', '添加' );
        return $this->fetch("{$this->controller}/edit");
    }
    
    #插入操作
    public function insert() {
        $fields = $this->fields;
        #字段校验
        $data = $this->checkfield($fields,input('post.'));
        if(isset($data['code']) && $data['code']==0){
            return $data;
        }
        #创建时间
        if(empty($data['createtime']) ){
            $data['createtime'] = time();
        }
        #创建者
        $data['userid'] = session('aid');
        $data['username'] = session('username');
        $data['pid'] = $this->pid;
        
        #标题
        $title_style ='';
        if (isset($data['style_color'])) {
            $title_style .= 'color:' . $data['style_color'].';';
            unset($data['style_color']);
        }else{
            $title_style .= 'color:#222;';
        }
        if (isset($data['style_bold'])) {
            $title_style .= 'font-weight:' . $data['style_bold'].';';
            unset($data['style_bold']);
        }else{
            $title_style .= 'font-weight:normal;';
        }
        if($fields['title']['setup']['style']==1) {
            $data['title_style'] = $title_style;
        }
        unset($data['style_color']);
        unset($data['style_bold']);
        unset($data['pics_name']);
        //编辑多图和多文件
        foreach ($fields as $k=>$v){
            if($v['type']=='files' or $v['type']=='images'){
                if(!$data[$k]){
                    $data[$k]='';
                }
                $data[$v['field']] = $data['images'];
            }
        }
        $id= $this->dao->insertGetId($data);
        if ($id !==false) {
            #日志
            $this->helper->insLog($this->moduleid, 'add', session('aid'), session('username'), $id);
            $url = url("xj/".$this->controller."/index",array('mod'=>$this->model,'pid'=>$this->pid));
            #返回
            return mz_success('添加成功', $url);
        } else {
            return mz_success('添加失败');
        }
    }
    
    #编辑
    public function edit(){
        $id = input('id');
        $request = Request::instance();
        $controllerName = $request->controller();
        
        $info = $this->dao->where('id',$id)->find();
        $form = new Form($info);
        $this->assign ('info', $info );
        $this->assign ( 'form', $form );
        $this->assign ( 'title', '编辑' );
        return $this->fetch("{$this->controller}/edit");
    }
    
    #更新操作
    public function update() {
        $fields = $this->fields;
        $data = $this->checkfield($fields,input('post.'));
        if($data['code']=="0"){
            $result['msg'] = $data['msg'];
            $result['code'] = 0;
            return $result;
        }
        #更新人
        $data['updatetime'] = time();
        $data['userid'] = session('aid');
        
        #标题
        $title_style ='';
        if (isset($data['style_color'])) {
            $title_style .= 'color:' . $data['style_color'].';';
            unset($data['style_color']);
        }else{
            $title_style .= 'color:#222;';
        }
        if (isset($data['style_bold'])) {
            $title_style .= 'font-weight:' . $data['style_bold'].';';
            unset($data['style_bold']);
        }else{
            $title_style .= 'font-weight:normal;';
        }
        if($fields['title']['setup']['style']==1) {
            $data['title_style'] = $title_style;
        }
        
        //编辑多图和多文件
        foreach ($fields as $k=>$v){
            if($v['type']=='files' or $v['type']=='images'){
                if(!$data[$k]){
                    $data[$k]='';
                }
                $data[$v['field']] = $data['images'];
            }
        }
        $update = $this->dao->update($data);
        if (false !== $update) {
            #日志
            $this->helper->insLog($this->moduleid, 'edit', session('aid'), session('username'), input('post.id'));
            $url = url("xj/".$this->controller."/index",array('mod'=>$this->model,'pid'=>$this->pid));
            return mz_success("修改成功", $url);
        } else {
            return mz_error("修改失败", $url);
        }
    }
    
    #删除操作
    public function listDel() {
        $id = input('post.id');
        $this->dao->where(array('id'=>$id))->setField('istrash', 1);
        #日志
        $this->helper->insLog($this->moduleid, 'delete', session('aid'), session('username'), $id);
        return ['code'=>1,'msg'=>'删除成功！'];
    }
    
    #还原
    public function listBack() {
        $id = input('post.id');
        $this->dao->where(array('id'=>$id))->setField('istrash',0);
        #日志
        $this->helper->insLog($this->moduleid, 'back', session('aid'), session('username'), $id);
        return ['code'=>1,'msg'=>'还原成功！'];
    }
    
    #彻底删除
    public function listRush() {
        $id = input('post.id');
        $this->dao->where(array('id'=>$id))->delete();
        #日志
        $this->helper->insLog($this->moduleid, 'rush', session('aid'), session('username'), $id);
        return ['code'=>1,'msg'=>'彻底删除成功！'];
    }
    
    #全部删除
    public function delAll(){
        $map['id'] =array('in',input('param.ids/a'));
        $model = $this->dao;
        $this->dao->where($map)->setField('istrash', 1);
        $url = url("xj/".$this->controller."/index");
        #日志
        $ids = implode(',',input('param.ids/a'));
        $this->helper->insLog($this->moduleid, 'delete', session('aid'), session('username'), $ids);
        return mz_success('删除成功', $url);
    }
    
    #全部还原
    public function backAll(){
        $map['id'] =array('in',input('param.ids/a'));
        $model = $this->dao;
        $this->dao->where($map)->setField('istrash', 0);
        $url = url("xj/".$this->controller."/index");
        #日志
        $ids = implode(',',input('param.ids/a'));
        $this->helper->insLog($this->moduleid, 'back', session('aid'), session('username'), $ids);
        return mz_success('还原成功', $url);
    }
    
    #彻底删除
    public function rushAll(){
        $map['id'] =array('in',input('param.ids/a'));
        $model = $this->dao;
        $this->dao->where($map)->delete();
        $url = url("xj/".$this->controller."/index");
        #日志
        $ids = implode(',',input('param.ids/a'));
        $this->helper->insLog($this->moduleid, 'rush', session('aid'), session('username'), $ids);
        return mz_success('彻底删除成功', $url);
    }
    
    function checkfield($fields, $post) {
        foreach ( $post as $key => $val ) {
            if(isset($fields[$key])){
                
                $setup=$fields[$key]['setup'];
                #必填参数 
                if(!empty($fields[$key]['required']) && empty($post[$key])){
                    $result['msg'] = $fields[$key]['errormsg']?$fields[$key]['errormsg']:'缺少必要参数！';
                    $result['code'] = 0;
                    return $result;
                }
                if(isset($setup['multiple'])){
                    if(is_array($post[$key])){
                        $post[$key] = implode(',',$post[$key]);
                    }
                }
                if(isset($setup['inputtype'])){
                    if($setup['inputtype']=='checkbox'){
                        $post[$key] = implode(',',$post[$key]);
                    }
                }
                if(isset($setup['fieldtype'])){
                    if($fields[$key]['type']=='checkbox'){
                        $post[$key] = implode(',',$post[$key]);
                    }
                }
                if($fields[$key]['type']=='datetime'){
                    $post[$key] =strtotime($post[$key]);
                }elseif($fields[$key]['type']=='textarea'){
                    $post[$key]=addslashes($post[$key]);
                }elseif($fields[$key]['type']=='editor'){
                    if(isset($post['add_description']) && $post['description'] == '' && isset($post['content'])) {
                        $content = stripslashes($post['content']);
                        $description_length = intval($post['description_length']);
                        $post['description'] = str_cut(str_replace(array("\r\n","\t",'[page]','[/page]','&ldquo;','&rdquo;'), '', strip_tags($content)),$description_length);
                        $post['description'] = addslashes($post['description']);
                    }
                    if(isset($post['auto_thumb']) && $post['thumb'] == '' && isset($post['content'])) {
                        $content = $content ? $content : stripslashes($post['content']);
                        $auto_thumb_no = intval($post['auto_thumb_no']) * 3;
                        if(preg_match_all("/(src)=([\"|']?)([^ \"'>]+\.(gif|jpg|jpeg|bmp|png))\\2/i", $content, $matches)) {
                            $post['thumb'] = $matches[$auto_thumb_no][0];
                        }
                    }
                }
            }
        }
        return $post;
    }

}