<?php
 /* 
  * Copyright (C) 2017 All rights reserved.
 *   
 * @File UserTest.php
 * @Brief 
 * @Author 毛子
 * @Version 1.0
 * @Date 2017-12-26
 * @Remark 账户
 */
namespace app\bee\controller;
use think\Config;
use think\Db;
use app\bee\service\HelperDao;

class News extends Common {
    
    protected $helper;
    protected $ads_model;


    public function _initialize() {
        parent::_initialize();
        $this->helper = new HelperDao();
        $this->ads_model = model("Ads");
    }
    
    public function detail() {
        $id = input("id");
        $info = $this->ads_model
                ->where("id='{$id}'")
                ->find();
        
        $this->assign("info", $info);
        $this->assign("title", "详情");
        return $this->fetch();
    }
    
}