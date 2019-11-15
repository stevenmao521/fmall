<?php
 /* 
  * Copyright (C) 2017 All rights reserved.
 *   
 * @File UserTest.php
 * @Brief 
 * @Author 毛子
 * @Version 1.0
 * @Date 2017-12-26
 * @Remark 首页
 */
namespace app\bee\controller;
use think\Config;
use think\Db;

class Index extends Common {
    protected $ads_model;
    protected $product_model;
    protected $mem_model;


    public function _initialize() {
        parent::_initialize();
        
        $this->ads_model = model("Ads");
        $this->product_model = model("Product");
        $this->mem_model = model("Members");
    }
    
    public function index() {
        $uid = session("userid");
        
        if ($uid) {
            $user = $this->mem_model->where("id='{$uid}'")->find();
        } else {
            $user = array();
        }
        
        #首页幻灯
        $ads_lamp = $this->ads_model->where("type=1 and istrash=0")->select();
        $ads_news = $this->ads_model->where("type=2 and istrash=0")->select();
        
        #首购商品
        $first_product = $this->product_model->where("isnew=1 and istrash=0")->order("listorder asc")->find();
        $first_product['tag_name'] = mz_gettag($first_product['tag']);
        $first_product['pic'] = mz_pic($first_product['pics']);
        
        #复购商品4件
        $second_product = $this->product_model->where("isnew=2 and istrash=0")->limit(4)->order("listorder asc")->select();
        if ($second_product) {
            foreach ($second_product as $k=>$v) {
                $second_product[$k]['tag_name'] = mz_gettag($v['tag']);
                $second_product[$k]['pic'] = mz_pic($v['pics']);
            }
        }
        
        $this->assign("user", $user);
        $this->assign("first", $first_product);
        $this->assign("slist", $second_product);
        $this->assign("ads_lamp", $ads_lamp);
        $this->assign("ads_news", $ads_news);
        $this->assign("title", "流浪山谷");
        return $this->fetch('index');
    }
}