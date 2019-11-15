<?php
namespace app\admin\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\Form;//表单
use app\admin\controller\Helper as Helper;//工具类

class Base extends Common{
    protected $modname; #模块名称
    protected $dao; #默认模型
    protected $fields; #字段
    protected $lfields;
    protected $field_mod;
    protected $controller; #控制器

    protected $form;    #表单
    protected $helper;  #工具
    #初始化
    public function _initialize() {
        parent::_initialize();
        $this->controller = "base";
        $this->modname = "员工信息";
        $this->moduleid = $this->mod[MODULE_NAME]; #模型id
        $this->dao = db(MODULE_NAME); #当前模型
        $this->field_mod = db('field'); #字段模型
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
                ->where("istrash=0")
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
                ->where('istrash=1')
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
            $url = url("admin/".$this->controller."/index");
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
            $url = url("admin/".$this->controller."/index");
            return mz_success("修改成功", $url);
        } else {
            return mz_error("修改失败", $url);
        }
    }
    
    #删除操作
    public function listDel() {
        $id = input('post.id');
        $this->dao->where(array('id'=>$id))->setField('istrash', 1);
        return ['code'=>1,'msg'=>'删除成功！'];
    }
    
    #还原
    public function listBack() {
        $id = input('post.id');
        $this->dao->where(array('id'=>$id))->setField('istrash',0);
        return ['code'=>1,'msg'=>'还原成功！'];
    }
    
    #彻底删除
    public function listRush() {
        $id = input('post.id');
        $this->dao->where(array('id'=>$id))->delete();
        return ['code'=>1,'msg'=>'彻底删除成功！'];
    }
    
    #全部删除
    public function delAll(){
        $map['id'] =array('in',input('param.ids/a'));
        $model = $this->dao;
        $this->dao->where($map)->setField('istrash', 1);
        $url = url("admin/".$this->controller."/index");
        return mz_success('删除成功', $url);
    }
    
    #全部还原
    public function backAll(){
        $map['id'] =array('in',input('param.ids/a'));
        $model = $this->dao;
        $this->dao->where($map)->setField('istrash', 0);
        $url = url("admin/".$this->controller."/index");
        return mz_success('还原成功', $url);
    }
    
    #彻底删除
    public function rushAll(){
        $map['id'] =array('in',input('param.ids/a'));
        $model = $this->dao;
        $this->dao->where($map)->delete();
        $url = url("admin/".$this->controller."/index");
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