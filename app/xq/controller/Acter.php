<?php
/*
 * 公共事件类
 * 此事件类为某些字段单独触发的事件
 * 来源于字段管理的绑定事件
 */
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\FormSel;    #表单

class Acter extends Common{
    
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
    
    #根据id获取材料信息
    /*
    public function getMateInfo($info) {
        $field = $info['field']; #当前字段
        $post_url = url("oa/Acter/getMateInfoData");
        $parseStr = "";
        $parseStr .= "<script> 
        layui.use('form', function () {
            var form = layui.form;
            form.on('select({$field})', function(data){
                var loading = layer.load(1, {shade: [0.1, '#fff']});
                var pid = data.value;
                $.get(\"{$post_url}?pid=\"+pid+\"\", function (data) {
                    layer.close(loading);
                    if (data) {
                        $('.materialname').val(data.name);
                        $('.unit').val(data.unit);
                        $('.price').val(data.price);
                        $('.format').val(data.format);
                    }
                });
            });
        });
        </script>";
        return $parseStr;
    }
    #根据id获取材料信息
    public function getMateInfoData() {
        $params = input("request.");
        $pid = $params['pid'];
        $mod = "material";
        $m_info = db($mod)->where("id='{$pid}'")->field("name,unit,price,format")->find();
        return $m_info;
    }
    */
}