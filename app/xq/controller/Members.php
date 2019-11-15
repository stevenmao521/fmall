<?php
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\Form;//表单
use app\xq\controller\Helper as Helper;//工具类

class Members extends Common{
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
        
        $this->controller = "members";
        $this->modname = "用户";
        
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
    
    #详细
    public function infos() {
        $id = input("request.id");
        $ispost = input("ispost");
        
        $mem_info = db("members")->where("id='{$id}'")->find();
        $mem_info['level'] = mz_gettype($mem_info['level']);
        
        if ($ispost) {
            $up = array();
            $serviceid = input("serviceid");;
            $service = db("members")->where("serviceid='{$serviceid}' and id != '{$id}'")->find();
            
            if (!$service) {
                return mz_apierror("服务商不存在");
            }
            
            if (($mem_info['parent_id'] && $mem_info['parent_id'] != $service['id']) || !$mem_info['parent_id']) {
                $up['parent_id'] = $service['id'];
                $up['parent_service'] = $service["serviceid"];
                
                #memship
                $ship = db("membership")->where("uid='{$id}'")->find();
                if ($ship) {
                    db("membership")->where("uid='{$id}'")->update(array("parentid"=>$service['id']));
                } else {
                    db("membership")->insert(array(
                        "uid"=>$id,
                        "parentid"=>$service['id'],
                        "rank"=>0,
                        "createtime"=>time(),
                    ));
                }
            }
            
            $up['nickname'] = input("nickname");
            $up['mobile'] = input("mobile");
            $up['realname'] = input("realname");
            $up['bankcode'] = input("bankcode");
            $up['bankname'] = input("bankname");
            $res = db("members")->where("id='{$id}'")->update($up);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apierror("修改失败");
            }
        }
        
        $this->assign("mem", $mem_info);
        $this->assign("id", $id);
        return $this->fetch();
    }
    
    public function child()
    {
        $parentid = input("parentid");
        if (request()->isPost()) {
            #筛选字段
            $post = input("post.");
            $sel_map = $this->helper->getMap($post, $this->moduleid);

            #列表
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

            $list = $this->dao
                    ->where($sel_map)
                    ->where("istrash=0 and parent_id='{$parentid}'")
                    ->order('id desc')
                    ->paginate(array('list_rows' => $pageSize, 'page' => $page))
                    ->toArray();

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
            'parentid'=>$parentid,
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
    
    #详细
    public function childinfo() {
        $id = input("request.id");
        $ispost = input("ispost");
        
        $mem_info = db("members")->where("id='{$id}'")->find();
        $mem_info['level'] = mz_gettype($mem_info['level']);
        
        if ($ispost) {
            $up = array();
            $serviceid = input("serviceid");;
            $service = db("members")->where("serviceid='{$serviceid}' and id != '{$id}'")->find();
            
            if (!$service) {
                return mz_apierror("服务商不存在");
            }
            
            if (($mem_info['parent_id'] && $mem_info['parent_id'] != $service['id']) || !$mem_info['parent_id']) {
                $up['parent_id'] = $service['id'];
                $up['parent_service'] = $service["serviceid"];
                
                #memship
                $ship = db("membership")->where("uid='{$id}'")->find();
                if ($ship) {
                    db("membership")->where("uid='{$id}'")->update(array("parentid"=>$service['id']));
                } else {
                    db("membership")->insert(array(
                        "uid"=>$id,
                        "parentid"=>$service['id'],
                        "rank"=>0,
                        "createtime"=>time(),
                    ));
                }
            }
            $up['nickname'] = input("nickname");
            $up['mobile'] = input("mobile");
            $up['realname'] = input("realname");
            $up['bankcode'] = input("bankcode");
            $up['bankname'] = input("bankname");
            $res = db("members")->where("id='{$id}'")->update($up);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apierror("修改失败");
            }
        }
        
        $this->assign("mem", $mem_info);
        $this->assign("id", $id);
        return $this->fetch();
    }
    
    #收支明细
    public function flow()
    {
        $id = input("id");
        $parentid = input("parentid");
        $this->dao = db('memberflow');
        $this->moduleid = 13;
        
        if (request()->isPost()) {
            #筛选字段
            $post = input("post.");
            $sel_map = $this->helper->getMap($post, $this->moduleid);

            #列表
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

            $list = $this->dao
                    ->where($sel_map)
                    ->where("istrash=0 and uid='{$id}'")
                    ->order('id desc')
                    ->paginate(array('list_rows' => $pageSize, 'page' => $page))
                    ->toArray();

            #时间转换
            $this->lfields = $this->helper->getLfield($this->moduleid);#列表字段
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
            'id'=>$id,
            'parentid'=>$parentid,
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
    
}