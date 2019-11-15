<?php
namespace clt;
use clt\Leftnav;
class FormSel{
    public $data = array();

    public function __construct($data=array()) {
        $this->data = $data;
    }
    
    public function text($info,$value){
        $info['setup']=is_array($info['setup']) ? $info['setup'] : string2array($info['setup']);
        $field = $info['field'];
        $name = $info['name'];

        $info['setup']['ispassword'] ? $inputtext = 'password' : $inputtext = 'text';
        $action = ACTION_NAME;
        if($action=='add'){
            $value = $value ? $value : $info['setup']['default'];
        }else{
            $value = $value ? $value : $this->data[$field];
        }
        $pattern='';
        if($info['pattern']!='defaul'){
            $pattern='|'.$info['pattern'];
        }
        $parseStr   = '<input type="'.$inputtext.'" data-required="'.$info['required'].'" min="'.$info['minlength'].'" max="'.$info['maxlength'].'" errormsg="'.$info['errormsg'].'" title="'.$name.'" placeholder="请输入'.$name.'" lay-verify="defaul'.$pattern.'" class="'.$info['class'].' layui-input" name="'.$field.'" id="'.$field.'" value="'.$value.'" /> ';
        return $parseStr;
    }

    public function select($info,$value){

        $info['setup']=is_array($info['setup']) ? $info['setup'] : string2array($info['setup']);
        $id = $field = $info['field'];
        $validate = getvalidate($info);
        $action = ACTION_NAME;
        if($action=='add'){
            $value = $value ? $value : $info['setup']['default'];
        }else{
            $value = $value ? $value : $this->data[$field];
        }
        if($value != '') $value = strpos($value, ',') ? explode(',', $value) : $value;
        if(is_array($info['options'])){
            $optionsarr = $info['options'];
        }else{
            $options    = $info['setup']['options'];
            $options = explode("\n",$info['setup']['options']);
            foreach($options as $r) {
                $v = explode("|",$r);
                $k = trim($v[1]);
                $optionsarr[$k] = $v[0];
            }
        }
        if(!empty($info['setup']['multiple'])) {
            $onchange = '';
            if(isset($info['setup']['onchange'])){
                $onchange = $info['setup']['onchange'];
            }
            $parseStr = '<select id="'.$id.'" name="'.$field.'"  onchange="'.$onchange.'" class="'.$info['class'].'"  '.$validate.' size="'.$info['setup']['size'].'" multiple="multiple" >';
        }else {
            $onchange = '';
            if(isset($info['setup']['onchange'])){
                $onchange = $info['setup']['onchange'];
            }
            $parseStr = '<select lay-search="" id="'.$id.'" name="'.$field.'" onchange="'.$onchange .'"  class="'.$info['class'].'" '.$validate.'>';
        }
        $parseStr   .= '<option value="">直接选择或搜索选择</option>';
        if(is_array($optionsarr)) {
            foreach($optionsarr as $key=>$val) {
                if(!empty($value)){
                    $selected='';
                    if(is_array($value)){
                        if(in_array($key,$value)){
                            $selected = ' selected="selected"';
                        }
                    }else{
                        if($value==$key){
                            $selected = ' selected="selected"';
                        }
                    }
                    $parseStr   .= '<option '.$selected.' value="'.$key.'">'.$val.'</option>';
                }else{
                    $parseStr   .= '<option value="'.$key.'">'.$val.'</option>';
                }
            }
        }
        $parseStr   .= '</select>';
        return $parseStr;
    }
    
    #外键
    public function fkey($info,$value){
        $validate = getvalidate($info);
        $action = ACTION_NAME;
        $id = $field = $info['field'];
        
        $info['setup'] = string2array($info['setup']);
        $value = $value ? $value : $this->data[$field];
        $modname = $info['setup']['modname'];
        $keyname = $info['setup']['keyname'];
        
        
        #字段
        if ($modname == 'admin') {
            $fields = "admin_id,{$keyname}";
        } else {
            $fields = "id,{$keyname}";
        }
        
        #如果模型为科室，则需要判断用户组
        #排除人员管理
        $module = MODULE_NAME;
        if ($modname == 'office' && $module != 'agency') {
            $admin_id = session("aid");
            $group = db('admin')->where("admin_id='{$admin_id}'")->value("group_id");
            $rule = db('auth_group')->where("group_id='{$group}'")->value("office");
            $rule = rtrim($rule,',');
            if ($rule) {
                $list = db($modname)->where("id in ({$rule})")->field($fields)->select();
            } else {
                $list = array();
            }
        } else {
            $list = db($modname)->field($fields)->select();
        }
        
        $parseStr = '<select lay-search="" id="'.$id.'" lay-verify="required" name="'.$field.'"  '.$validate.'>';
        $parseStr .= '<option value="">请选择'.$info['name'].'或输入</option>';
        if ($list) {
            foreach ($list as $k=>$v) {
                if ($value) {
                    if ($v['id'] == $value) {
                        $selected = ' selected="selected"';
                    }
                }
                if ($modname == 'admin') {
                    $parseStr .= '<option '.$selected.' value="'.$v['admin_id'].'">'.$v[$keyname].'</option>';
                } else {
                    $parseStr .= '<option '.$selected.' value="'.$v['id'].'">'.$v[$keyname].'</option>';
                }
            }
        }
        $parseStr .= '</select>';
        return $parseStr;
    }
}
?>