<?php
/*
 * 公共工具类
 */
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\FormSel;    #表单

class Helper extends Common{
    
    protected $field_mod;#字段模型
    protected $form;
    protected $log_mod;#日志模型
    protected $module_mod;#模型

    #初始化
    public function _initialize() {
        parent::_initialize();
        $this->field_mod = db('field'); #字段模型
        $this->log_mod = db('logs');
        $this->module_mod = db('module');
        $this->form = new FormSel(); #表单
    }
    
    #列表筛选
    #moduleid 模型id
    public function getMap($post, $moduleid) {
        $sel_map = array();
        $sel_map = " 1=1 ";
        
        #筛选字段
        $map = array();
        $map['moduleid'] = $moduleid;
        $map['issel'] = 1;
        $map['status'] = 1;
        $sel_field = $this->field_mod
            ->where($map)
            ->field("field,name,width,type,setup")
            ->order("listorder","asc")
            ->select();
        //print_r($sel_field);
        foreach ($sel_field as $k=>$v) {
            $value = $post[$v['field']];
            switch ($v['type']) {
                case 'text':
                    if ($value)
                        $sel_map .= " and {$v['field']} like '%{$value}%'";
                    break;
                case 'select':
                    if ($value)
                        $sel_map .= " and {$v['field']}='{$value}'";
                    break;
                case 'radio':
                    if ($value)
                        $sel_map .= " and {$v['field']}='{$value}'";
                    break;
                case 'datetime':
                    $start_str = $v['field']."_start";
                    $end_str = $v['field']."_end";
                    $start = $post[$start_str];
                    $end = $post[$end_str];
                    if ($start)
                        $sel_map .=  " and {$v['field']}>='".strtotime($start)."'";
                    if ($end)
                        $sel_map .=  " and {$v['field']}<='".strtotime($end)."'";
                case 'fkey':
                    if ($value)
                        $sel_map .= " and {$v['field']}='{$value}'";
                    break;
            }
        }
        return $sel_map;
    }
    
    #获取编辑新增字段
    public function getEditField($moduleid) {
        $fields_arr = array();
        #列表字段
        $map = array();
        $map['moduleid'] = $moduleid;
        $map['isedit'] = 1;
        $map['status'] = 1;
        $edit_field = $this->field_mod
            ->where($map)
            ->order("listorder","asc")
            ->select();
        foreach($edit_field as $key => $res){
            $res['setup'] = string2array($res['setup']);
            $k = $res['field'];
            $fields_arr[$k] = $res;
        }
        return $fields_arr;
    }
    
    #获取编辑新增字段
    public function getLfield($moduleid) {
        $fields_arr = array();
        #列表字段
        $map = array();
        $map['moduleid'] = $moduleid;
        $map['islist'] = 1;
        $map['status'] = 1;
        $edit_field = $this->field_mod
            ->where($map)
            ->order("listorder","asc")
            ->select();
        foreach($edit_field as $key => $res){
            $res['setup'] = string2array($res['setup']);
            $k = $res['field'];
            $fields_arr[$k] = $res;
        }
        return $fields_arr;
    }

    #获取列表JS显示字段
    public function getlistField($moduleid) {
        $return = array();
        $js_str = "";
        $tmp = "";#模版
        #列表字段
        $map = array();
        $map['moduleid'] = $moduleid;
        $map['islist'] = 1;
        $map['status'] = 1;
        $list_field = $this->field_mod
            ->where($map)
            ->field("field,name,width,type,setup,event,issort")
            ->order("listorder","asc")
            ->select();
        unset($map);
        $js_str .= "{field: 'id', title: '编号', width: 80, fixed: true, sort: true}, ";
        #js转化
        foreach ($list_field as $k=>$v) {
            #如果是下来或者单选项
            #附加属性
            $other = "";
            if ($v['type'] == 'select' || $v['type'] == 'radio') {
                #字段模版
                $v['setup']=is_array($v['setup']) ? $v['setup'] : string2array($v['setup']);
                $options    = $v['setup']['options'];
                $options = explode("\n",$v['setup']['options']);
                
                foreach($options as $r) {
                    $v1 = explode("|",$r);
                    $k1 = trim($v1[1]);
                    $optionsarr[$k1] = $v1[0];
                }
                $tmp .= "<script type='text/html' id='{$v['field']}_tmp'>";
                $tag = 1;
                
                foreach ($optionsarr as $k2=>$v2) {
                    if ($tag == 1) {
                        $tmp .= "{{# if(d.{$v['field']} == '{$k2}'){ }}";
                        $tmp .= $v2;
                    } else {
                        $tmp .= "{{# }else if(d.{$v['field']} == '{$k2}'){ }}";
                        $tmp .= $v2;
                    }
                    $tag ++;
                }
                $tmp .= "{{# } }}";
                $tmp .= "</script>";
                $other = ", templet: '#{$v['field']}_tmp'";
            }
            if ($v['event']) {
                $other .= ", toolbar: '#{$v['event']}_evt'";
            }
            if ($v['issort']) {
                $other .= ", sort: true";
            }
            #外键
            if ($v['type'] == 'fkey') {
                $v['setup']=is_array($v['setup']) ? $v['setup'] : string2array($v['setup']);
                $modname = $v['setup']['modname'];
                $keyname = $v['setup']['keyname'];
                $islink = $v['setup']['islink'];

                #字段
                if ($modname == 'admin') {
                    $fields = "admin_id,{$keyname}";
                } else {
                    $fields = "id,{$keyname}";
                }
                $list = db($modname)->field($fields)->select();
                if ($list) {
                $tmp .= "<script type='text/html' id='{$v['field']}_tmp'>";
                $tag = 1;
                    foreach ($list as $k2=>$v2) {
                        if ($tag == 1) {
                            if ($modname == 'admin') {
                                $tmp .= "{{# if(d.{$v['field']} == '{$v2['admin_id']}'){ }}";
                            } else {
                                $tmp .= "{{# if(d.{$v['field']} == '{$v2['id']}'){ }}";
                            }
                            if ($islink) {
                                $tmp .= "<a class='layui-table-link' lay-event='info' data='{$modname}'>".$v2[$keyname]."</a>";
                            } else {
                                $tmp .= $v2[$keyname];
                            }
                        } else {
                            if ($modname == 'admin') { 
                                $tmp .= "{{# }else if(d.{$v['field']} == '{$v2['admin_id']}'){ }}";
                            } else {
                                $tmp .= "{{# }else if(d.{$v['field']} == '{$v2['id']}'){ }}";
                            }
                            if ($islink) {
                                $tmp .= "<a class='layui-table-link' lay-event='info' data='{$modname}'>".$v2[$keyname]."</a>";
                            } else {
                                $tmp .= $v2[$keyname];
                            }
                        }
                        $tag ++;
                    }
                    $tmp .= "{{# } }}";
                    $tmp .= "</script>";
                    $other = ", templet: '#{$v['field']}_tmp'";
                }
            }
            
            #关联键
            if ($v['type'] == 'rekey') {
                $v['setup']=is_array($v['setup']) ? $v['setup'] : string2array($v['setup']);
                $modname = $v['setup']['modname'];
                $keyname = $v['setup']['keyname'];
                $fields = "id,{$keyname}";
                $list = db($modname)->field($fields)->select();
                if ($list) {
                $tmp .= "<script type='text/html' id='{$v['field']}_tmp'>";
                $tag = 1;
                    foreach ($list as $k2=>$v2) {
                        if ($tag == 1) {
                            $tmp .= "{{# if(d.{$v['field']} == '{$v2['id']}'){ }}";
                            $tmp .= "<a class='layui-table-link' lay-event='info' data='{$modname}'>".$v2[$keyname]."</a>";
                        } else {
                            $tmp .= "{{# }else if(d.{$v['field']} == '{$v2['id']}'){ }}";
                            $tmp .= "<a class='layui-table-link' lay-event='info' data='{$modname}'>".$v2[$keyname]."</a>";
                        }
                        $tag ++;
                    }
                    $tmp .= "{{# } }}";
                    $tmp .= "</script>";
                    $other = ", templet: '#{$v['field']}_tmp'";
                }
            }
            
            if ($v['type'] == 'images') {
                
                $tmp .= "<script type='text/html' id='{$v['field']}_tmp'>";
                $tmp .= "<img src='__PUBLIC__{{d.{$v['field']}}}' />";
                
                $tmp .= "</script>";
                $other = ", templet: '#{$v['field']}_tmp'";
                
            }
            #原始
            $js_str .= "{field: '{$v['field']}', title: '{$v['name']}', width: {$v['width']} {$other}},";
        }
        $return['js_str'] = $js_str;
        $return['js_tmp'] = $tmp;
        return $return;
    }
    
    #获取列表筛选字段
    #html_str 筛选html
    #js_val 搜索表单字段初始化
    #js_where 搜索表单字段值
    #$js_date 时间控件js初始化
    #js_ewhere 导出参数
    public function getSelField($moduleid) {
        $return = array();
        $html_str = "";
        $js_where = "where:{";
        $js_val = "";
        $js_date = "";
        
        $map = array();
        $map['moduleid'] = $moduleid;
        $map['issel'] = 1;
        $map['status'] = 1;
        $sel_field = $this->field_mod
            ->where($map)
            ->order("listorder","asc")
            ->select();
        unset($map);
        foreach ($sel_field as $k=>$v) {
            switch($v['type']) {
                #文本框
                case 'text':
                    $html_str .= "<div class='layui-inline'>";
                    $html_str .= "<label class='layui-form-label' style='width:auto;float:left;'>{$v['name']}</label>";
                    $html_str .= "<div class='layui-input-inline'>";
                    $html_str .= $this->form->text($v, $value);
                    $html_str .= "</div>";
                    $html_str .= "</div>";
                    #搜索传值js
                    $js_val .= "var {$v['field']} = $('#{$v['field']}').val();";
                    $js_where .= " {$v['field']}:{$v['field']},";
                    break;
                case 'number':
                    $html_str .= "<div class='layui-inline'>";
                    $html_str .= "<label class='layui-form-label' style='width:auto;float:left;'>{$v['name']}</label>";
                    $html_str .= "<div class='layui-input-inline'>";
                    $html_str .= $this->form->text($v, $value);
                    $html_str .= "</div>";
                    $html_str .= "</div>";
                    #搜索传值js
                    $js_val .= "var {$v['field']} = $('#{$v['field']}').val();";
                    $js_where .= " {$v['field']}:{$v['field']},";
                    break;
                #单选框
                case 'radio':
                    $html_str .=    "<div class='layui-inline'>";
                    $html_str .=    "<label class='layui-form-label' style='width:auto;float:left;'>{$v['name']}</label>";
                    $html_str .=    "<div class='layui-input-inline'>";
                    $sel = $this->form->select($v, $value);
                    $html_str .= $sel;
                    $html_str .=    "</div>";
                    $html_str .=    "</div>";
                    #搜索传值js
                    $js_val .= "var {$v['field']} = $('#{$v['field']}').val();";
                    $js_where .= " {$v['field']}:{$v['field']},";
                    
                break;
                #下拉列表
                case 'select':
                    $html_str .=    "<div class='layui-inline'>";
                    $html_str .=    "<label class='layui-form-label' style='width:auto;float:left;'>{$v['name']}</label>";
                    $html_str .=    "<div class='layui-input-inline'>";
                    $sel = $this->form->select($v, $value);
                    $html_str .= $sel;
                    $html_str .=    "</div>";
                    $html_str .=    "</div>";
                    #搜索传值js
                    $js_val .= "var {$v['field']} = $('#{$v['field']}').val();";
                    $js_where .= " {$v['field']}:{$v['field']},";
                    
                break;
                #时间
                case 'datetime':
                    $html_str .=    "<div class='layui-inline'>";
                    $html_str .=    "<label class='layui-form-label' style='width:auto;float:left;'>{$v['name']}</label>";
                    $html_str .=    "<div class='layui-input-inline'>";
                    $html_str .=    "<input type='text' class='layui-input' id='{$v['field']}_start' name='{$v['field']}_start' placeholder='起始'>";
                    $html_str .=    "</div>";
                    $html_str .=    "<div class='layui-input-inline' >";
                    $html_str .=    "<input type='text' class='layui-input' id='{$v['field']}_end' name='{$v['field']}_start' placeholder='结束'>";
                    $html_str .=    "</div>";
                    $html_str .=    "</div>";
                    #搜索传值js
                    $js_val .= "var {$v['field']}_start = $('#{$v['field']}_start').val();";
                    $js_val .= "var {$v['field']}_end = $('#{$v['field']}_end').val();";
                    $js_where .= " {$v['field']}_start:{$v['field']}_start,";
                    $js_where .= " {$v['field']}_end:{$v['field']}_end,";
                    #时间js
                    $js_date .= "laydate.render({";
                    $js_date .= "elem: '#{$v['field']}_start',";
                    $js_date .= "type: 'datetime'";
                    $js_date .="});";
                    $js_date .= "laydate.render({";
                    $js_date .= "elem: '#{$v['field']}_end',";
                    $js_date .= "type: 'datetime'";
                    $js_date .="});";
                break;
                #外键
                case 'fkey':
                    $html_str .=    "<div class='layui-inline'>";
                    $html_str .=    "<label class='layui-form-label' style='width:auto;float:left;'>{$v['name']}</label>";
                    $html_str .=    "<div class='layui-input-inline'>";
                    $sel = $this->form->fkey($v, $value);
                    $html_str .= $sel;
                    $html_str .=    "</div>";
                    $html_str .=    "</div>";
                    #搜索传值js
                    $js_val .= "var {$v['field']} = $('#{$v['field']}').val();";
                    $js_where .= " {$v['field']}:{$v['field']},";
                break;
            }
        }
        $js_where  = substr($js_where, 0, -1);
        $js_where .= "}";
        $js_ewhere = substr($js_where,7);
        $js_ewhere = substr($js_ewhere, 0,-1);
        
        $return['html_str'] = $html_str;
        $return['js_val'] = $js_val;
        $return['js_where'] = $js_where;
        $return['js_date'] = $js_date;
        $return['js_ewhere'] = $js_ewhere;
        return $return;
    }
    
    #获取统计字段
    public function getCountField($moduleid) {
        $return = array();
        $html = "";
        $js = "";
        $fields_arr = array();
        #列表字段
        $map = array();
        $map['moduleid'] = $moduleid;
        $map['iscount'] = 1;
        $map['status'] = 1;
        $count_field = $this->field_mod
            ->where($map)
            ->order("listorder","asc")
            ->select();
        foreach($count_field as $key => $res){
            $res['setup'] = string2array($res['setup']);
            $k = $res['field'];
            $fields_arr[$k] = $res;
        }
        if ($fields_arr) {
            foreach ($fields_arr as $k=>$v) {
                $html_1 .= "<th>{$v['name']}</th>";
                $html_2 .= "<td id='{$v['field']}_count'></td>";
                $js .= "$('#{$v['field']}_count').html(res.sum.{$v['field']});";
            }
        }
        $return['html_1'] = $html_1;
        $return['html_2'] = $html_2;
        $return['fields'] = $fields_arr;
        $return['js'] = $js;
        return $return;
    }
    
    #维护信息
    public function insLog($moduleid, $act, $userid, $username, $ids){
        $module = $this->module_mod->where("id='{$moduleid}'")->find();
        switch ($act) {
            case 'add':
                $act = '新增';
                break;
            case 'edit':
                $act = '编辑';
                break;
            case 'delete':
                $act = '删除';
                break;
            case 'back':
                $act = '还原';
                break;
            case 'rush':
                $act = '彻底删除';
                break;
            case 'issend':
                $act = '发货';
                break;
            case 'iscahs':
                $act = '审核打款';
                break;
            case 'lose':
                $act = '订单失效';
                break;
        }
        $remark = $act.$module['title'].'编号：'.$ids;
        $ins_data = array();
        $ins_data['moduleid'] = $moduleid;
        $ins_data['act'] = $act;
        $ins_data['userid'] = $userid;
        $ins_data['username'] = $username;
        $ins_data['remark'] = $remark;
        $ins_data['createtime'] = time();
        $ins_data['ids'] = $ids;
        $this->log_mod->insert($ins_data);
    }
    
    #联动获取
    public function getRelation() {
        $params = input("request.");
        $pid = $params['pid'];
        $modname = $params['modname'];
        $keyname = $params['keyname'];
        $rekey = $params['rekey'];
        if ($pid) {
            if ($modname == 'admin') {
                $fields = "admin_id,{$keyname}";
            } else {
                $fields = "id,{$keyname}";
            }
            $list = db($modname)->where("istrash=0 and {$rekey}={$pid}")->field($fields)->select();
            return $list;
        }
    }
}