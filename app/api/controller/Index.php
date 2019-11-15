<?php
 /* 
  * Copyright (C) 2017 All rights reserved.
 *   
 * @File UserTest.php
 * @Brief 
 * @Author 毛子
 * @Version 1.0
 * @Date 2017-12-26
 * @Remark 服务端接口
 */
namespace app\api\controller;
use think\Config;

class Index extends Common{
    
    public function _initialize() {
        parent::_initialize();
    }
    
    public function index() {
        echo "api";
    }
    
}