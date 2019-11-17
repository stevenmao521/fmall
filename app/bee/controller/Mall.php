<?php

/*
 * Copyright (C) 2017 All rights reserved.
 *   
 * @File UserTest.php
 * @Brief 
 * @Author 毛子
 * @Version 1.0
 * @Date 2017-12-26
 * @Remark 个人中心
 */

namespace app\bee\controller;

use think\Config;
use think\Db;
use app\bee\service\HelperDao;
use clt\WeixinPay;

class Mall extends Common {

    protected $helper;
    protected $mem_model;
    protected $product_model;
    protected $cart_model;
    protected $order_model;
    protected $order_detail_model;
    protected $address_model;
    protected $flow_model;
    protected $levellog_model;
    protected $productcate_model;
    protected $config;#微信支付配置
    protected $config_pay;

    public function _initialize() {
        parent::_initialize();
        $this->assign("title", "商品详情");
        $this->helper = new HelperDao();
        $this->mem_model = model("Members");
        $this->product_model = model("Product");
        $this->cart_model = model("Cart");
        $this->order_model = model("Order");
        $this->order_detail_model = model("Orderdetail");
        $this->address_model = model("Address");
        $this->flow_model = model("Memberflow");
        $this->levellog_model = model("Levellog");
        $this->productcate_model = model("Productcate");
        $this->config = Config::get('wechat');
        $this->config_pay = Config::get('wxpay');
    }
    
    #列表
    public function lists() {
        $uid = session("userid");
        $cid = input("cid") ? input("cid") : 1;
        
        $where = " cid = '{$cid}'";
        
        $catelist = $this->productcate_model->where("istrash=0")->order("listorder asc")->select();
        $product_list = $this->product_model
            ->where("isnew=2 and istrash=0")
            ->where($where)
            ->limit(20)->order("listorder asc")
            ->select();
        
        if ($product_list) {
            foreach ($product_list as $k=>$v) {
                $product_list[$k]['tag_name'] = mz_gettag($v['tag']);
                $product_list[$k]['pic'] = mz_pic($v['pics']);
            }
        }
        
        $this->assign("catelist", $catelist);
        $this->assign("list", $product_list);
        $this->assign("cid", $cid);
        return $this->fetch();
    }

    public function detail() {
        $id = input("id");
        $uid = session("userid");
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();

        $product = $this->product_model->where("id='{$id}'")->find();
        $product['pic'] = mz_pic($product['pics']);

        $this->assign("title", "商品详情");
        $this->assign("product", $product);
        $this->assign("info", $mem_info);
        return $this->fetch();
    }

    #加入购物车
    public function addcart() {
        $id = input("id");
        $uid = session("userid");
        $this->checklogin();
        

        $product = $this->product_model->where("id='{$id}'")->find();
        $member = $this->mem_model->where("id='{$uid}'")->find();

        $cart = $this->cart_model->where("uid='{$uid}' and product_id='{$product['id']}'")->find();
        if (!$cart) {
            $ins_data = array();
            $ins_data['product_id'] = $product['id'];
            $ins_data['product_name'] = $product['name'];
            $ins_data['isrebate'] = $product['id'];
            if ($member['level'] == 1) {
                $ins_data['price'] = $product['price'];
            } else {
                $ins_data['price'] = $product['reprice'];
            }
            $ins_data['uid'] = $uid;
            $ins_data['createtime'] = time();
            $ins_data['nums'] = 1;
            $r = $this->cart_model->insert($ins_data);
        } else {
            $updata = array();
            $updata['nums'] = $cart['nums'] + 1;
            $updata['updatetime'] = time();
            $r = $this->cart_model->where(["id" => $cart['id']])->update($updata);
        }
        if ($r) {
            return mz_apisuc("添加成功");
        } else {
            return mz_apierror("添加失败");
        }
    }

    #购物车列表
    public function cart() {
        $uid = session("userid");
        $this->checklogin();

        $cart_list = $this->cart_model
            ->where("uid='{$uid}'")->order("createtime desc")->select();

        if ($cart_list) {
            foreach ($cart_list as $k => $v) {
                #总价
                $cart_list[$k]['total_price'] = $v['price'] * $v['nums'];
                $product = $this->product_model->where("id='{$v['product_id']}'")->field("pics")->find();
                $cart_list[$k]['pic'] = mz_pic($product['pics']);
            }
        }

        $this->assign("title", "购物车");
        $this->assign('cartlist', $cart_list);
        return $this->fetch();
    }

    #创建订单
    public function addorder() {
        $uid = session("userid");
        $this->checklogin();
        
        $data = input("data");
        $member = $this->mem_model->where("id='{$uid}'")->find();


        if (!$data) {
            return mz_apierror("订单创建失败");
        } else {
            $data = substr($data, 0, -1);

            $address = $this->address_model->where("uid='{$uid}' and isdef")->find();
            if (!$address) {
                $address = $this->address_model->where("uid='{$uid}'")->order("id desc")->find();
            }

            Db::startTrans();
            try {
                if (strpos($data, ",")) {
                    #插入订单
                    $order_data = array();
                    $order_data['orderid'] = mz_get_order_sn();
                    if ($address) {
                        $order_data['addressid'] = $address['id'];
                        $order_data['addrname'] = $address['uname'];
                        $order_data['addrmobile'] = $address['mobile'];
                        $order_data['addrdetail'] = $address['pro_city_reg'] . $address['detail'];
                    } else {
                        #return mz_apierror("请先添加收获地址");
                    }
                    $order_data['uid'] = $uid;
                    $order_data['createtime'] = time();
                    $res = Db::name('Order')->insert($order_data, false, true);

                    #更新之前未付款的订单未废弃订单 status=2
                    $res_up_order = Db::name('Order')->where("id!='{$res}' and uid='{$uid}' and haspay=0")->update(array("status" => 2));


                    $total_price = 0;
                    $total_nums = 0;
                    $data_arr = explode(",", $data);
                    foreach ($data_arr as $k => $v) {
                        $data_exp = explode("_", $v);
                        $cart_info = Db::name('Cart')->where("id='{$data_exp[0]}'")->find();
                        $product_info = Db::name('Product')->where("id='{$cart_info['product_id']}'")->find();
                        if ($product_info) {
                            if ($member['level'] == 1) {
                                $total_price += $product_info['price'] * $data_exp[1];
                            } else {
                                $total_price += $product_info['reprice'] * $data_exp[1];
                            }
                            $total_nums += $data_exp[1];
                        }

                        $detail_data = array();
                        $detail_data['oid'] = $res;
                        $detail_data['product_id'] = $product_info['id'];
                        $detail_data['product_name'] = $product_info['name'];
                        $detail_data['nums'] = $data_exp[1];
                        if ($member['level'] == 1) {
                            $detail_data['price'] = $product_info['price'];
                        } else {
                            $detail_data['price'] = $product_info['reprice'];
                        }
                        $detail_data['isrebate'] = $product_info['isrebate'];
                        $detail_data['createtime'] = time();
                        $detail_data['bottles'] = $data_exp[1] * $product_info['bottles'];
                        $detail_data['isnew'] = $product_info['isnew'];
                        
                        $res_1 = Db::name('Orderdetail')->insert($detail_data);

                        #删除购物车相应产品
                        $res_2 = Db::name('Cart')->where("product_id='{$product_info['id']}' and uid='{$uid}'")->delete();
                    }
                    Db::name('Order')->where("id='{$res}'")->update(array("total_price" => $total_price, "total_nums" => $total_nums));

                    if ($res && $res_1 && $res_2) {
                        Db::commit();
                        return mz_apisuc("订单创建成功");
                    } else {
                        return mz_apierror("订单创建失败");
                    }
                } else {
                    #插入订单
                    $order_data = array();
                    $order_data['orderid'] = mz_get_order_sn();
                    if ($address) {
                        $order_data['addressid'] = $address['id'];
                        $order_data['addrname'] = $address['uname'];
                        $order_data['addrmobile'] = $address['mobile'];
                        $order_data['addrdetail'] = $address['pro_city_reg'] . $address['detail'];
                    } else {
                        #return mz_apierror("请先添加收获地址");
                    }
                    $order_data['uid'] = $uid;
                    $order_data['createtime'] = time();
                    $res = Db::name('Order')->insert($order_data, false, true);

                    #更新之前未付款的订单未废弃订单 status=2
                    $res_up_order = Db::name('Order')->where("id!='{$res}' and uid='{$uid}' and haspay=0")->update(array("status" => 2));

                    $total_price = 0;
                    $total_nums = 0;

                    $data_exp = explode("_", $data);
                    $cart_info = Db::name('Cart')->where("id='{$data_exp[0]}'")->find();
                    $product_info = Db::name('Product')->where("id='{$cart_info['product_id']}'")->find();

                    if ($product_info) {
                        if ($member['level'] == 1) {
                            $total_price = $product_info['price'] * $data_exp[1];
                        } else {
                            $total_price = $product_info['reprice'] * $data_exp[1];
                        }
                        $total_nums = $data_exp[1];
                    }

                    $detail_data = array();
                    $detail_data['oid'] = $res;
                    $detail_data['product_id'] = $product_info['id'];
                    $detail_data['product_name'] = $product_info['name'];
                    $detail_data['nums'] = $data_exp[1];
                    if ($member['level'] == 1) {
                        $detail_data['price'] = $product_info['price'];
                    } else {
                        $detail_data['price'] = $product_info['reprice'];
                    }
                    $detail_data['isrebate'] = $product_info['isrebate'];
                    $detail_data['bottles'] = $data_exp[1] * $product_info['bottles'];
                    $detail_data['isnew'] = $product_info['isnew'];
                    $detail_data['createtime'] = time();
                    $res_1 = Db::name('Orderdetail')->insert($detail_data);

                    #删除购物车相应产品
                    $res_2 = Db::name('Cart')->where("product_id='{$product_info['id']}' and uid='{$uid}'")->delete();
                    Db::name('Order')->where("id='{$res}'")->update(array("total_price" => $total_price, "total_nums" => $total_nums));
                    if ($res && $res_1 && $res_2) {
                        Db::commit();
                        return mz_apisuc("订单创建成功");
                    } else {
                        return mz_apierror("订单创建失败");
                    }
                }
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return mz_apierror("订单创建失败");
            }
        }
    }

    #立即购买
    public function addordernow() {
        $uid = session("userid");
        $this->checklogin();
        
        $member = $this->mem_model->where("id='{$uid}'")->find();
        $product_id = input("id");

        $address = $this->address_model->where("uid='{$uid}' and isdef")->find();
        if (!$address) {
            $address = $this->address_model->where("uid='{$uid}'")->order("id desc")->find();
        }

        Db::startTrans();
        try {

            #插入订单
            $order_data = array();
            $order_data['orderid'] = mz_get_order_sn();
            if ($address) {
                $order_data['addressid'] = $address['id'];
                $order_data['addrname'] = $address['uname'];
                $order_data['addrmobile'] = $address['mobile'];
                $order_data['addrdetail'] = $address['pro_city_reg'] . $address['detail'];
            } else {
                #return mz_apierror("请先添加收获地址");
            }
            $order_data['uid'] = $uid;
            $order_data['createtime'] = time();
            $res = Db::name('Order')->insert($order_data, false, true);

            #更新之前未付款的订单未废弃订单 status=2
            $res_up_order = Db::name('Order')->where("id!='{$res}' and uid='{$uid}' and haspay=0")->update(array("status" => 2));

            $total_price = 0;
            $total_nums = 0;

            $product_info = Db::name('Product')->where("id='{$product_id}'")->find();
            if ($product_info) {
                if ($member['level'] == 1) {
                    $total_price = $product_info['price'];
                } else {
                    $total_price = $product_info['reprice'];
                }
                $total_nums = 1;
            }

            $detail_data = array();
            $detail_data['oid'] = $res;
            $detail_data['product_id'] = $product_info['id'];
            $detail_data['product_name'] = $product_info['name'];
            $detail_data['nums'] = 1;
            if ($member['level'] == 1) {
                $detail_data['price'] = $product_info['price'];
            } else {
                $detail_data['price'] = $product_info['reprice'];
            }
            $detail_data['isrebate'] = $product_info['isrebate'];
            $detail_data['bottles'] = $product_info['bottles'];
            $detail_data['isnew'] = $product_info['isnew'];
            $detail_data['createtime'] = time();
            $res_1 = Db::name('Orderdetail')->insert($detail_data);

            Db::name('Order')->where("id='{$res}'")->update(array("total_price" => $total_price, "total_nums" => $total_nums));
            if ($res && $res_1) {
                Db::commit();
                return mz_apisuc("订单创建成功");
            } else {
                return mz_apierror("订单创建失败");
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return mz_apierror("订单创建失败");
        }
    }

    #订单详情页
    public function orderdetail() {
        $uid = session("userid");
        $this->checklogin();
        
        $order_info = $this->order_model->where("uid='{$uid}' and status=0")->order("createtime desc")->find();
        #订单商品
        $product_detail = $this->order_detail_model->where("oid='{$order_info['id']}'")->find();
        $product_info = $this->product_model->where("id='{$product_detail['product_id']}'")->find();
        $product_detail['pic'] = mz_pic($product_info['pics']);
        
        $address_info = $this->address_model->where("uid='{$uid}'")->find();
        
        $tuser = $this->mem_model->where("id='{$uid}'")->find();
        if (!$tuser['parent_id']) {
            $this->assign("needpid",1);
        } else {
            $this->assign("needpid",0);
            $this->assign("serviceid",$tuser['serviceid']);
        }

        $this->assign("addressinfo", $address_info);
        $this->assign("order", $order_info);
        $this->assign("product_detail", $product_detail);
        $this->assign("title", "订单");
        return $this->fetch();
    }

    #订单测试已付款
    public function orderpay() {
        exit;
        $uid = session("userid");
        $this->checklogin();
        
        $id = input("id");
        $res = $this->order_model->where("id='{$id}'")->update(array('haspay' => 1, 'paytime' => time(), "status" => 1));
        if ($res) {
            #更新流水记录
            $order_info = $this->order_model->where("id='{$id}'")->find();
            $member_info = $this->mem_model->where("id='{$uid}'")->find();
            mz_flow($uid, $id, 4, "-".$order_info['total_price'], "购买蜂蜜支出", $member_info['balance']);
            
            #增加商品销量
            $order_detail = $this->order_detail_model->where("oid='{$order_info['id']}'")->select();
            foreach ($order_detail as $k=>$v) {
                $this->product_model->where("id='{$v['product_id']}'")->setInc("selnums", $v['nums']);
            }
            
            #更新用户等级
            if ($member_info['level'] == 1) {
                #进行升级，并记录日志
                $ins_data = array();
                $ins_data['uid'] = $uid;
                $ins_data['direct_nums'] = 0;
                $ins_data['indirect_nums'] = 0;
                $ins_data['des'] = "达到 业务员 等级进行升级";
                $ins_data['createtime'] = time();
                $this->levellog_model->insert($ins_data);
                $this->mem_model->where("id='{$uid}'")->update(array("level"=>2));
            }
            
            return mz_apisuc("支付成功");
        } else {
            return mz_apierror("支付失败");
        }
    }
    
    #微信支付接口
    public function orderpaywx() {
        $uid = session("userid");
        #$this->checklogin();
        $id = input("id");
        $serviceid = input("serviceid");
        
        $order = $this->order_model->where("id='{$id}'")->find();
        
        #检查是否需要服务商id
        $tuser = $this->mem_model->where("id='{$uid}'")->find();
        if (!$tuser['parent_id']) {
            if (!$serviceid) {
                return mz_apierror("请填写服务商id");
            } else {
                $parent = $this->mem_model->where("serviceid='{$serviceid}'")->find();
                if (!$parent) {
                    return mz_apierror("服务商不存在");
                } else {
                    #更新
                    $this->mem_model->where("id='{$uid}'")->update(
                        array(
                            "parent_service"=>$parent['serviceid'],
                            "parent_id"=>$parent['id']
                        )
                    );
                    #添加ship
                    $has_ship = db("membership")->where("uid='{$uid}'")->find();
                    if ($has_ship) {
                        db("membership")->where("uid='{$uid}'")->update(array("parentid"=>$parent['id']));
                    } else {
                        db("membership")->insert(array(
                            "uid"=>$uid,
                            "parentid"=>$parent['id'],
                            "createtime"=>time()
                        ));
                    }
                }
            }
        }
        
        
        
        #是否添加收获地址
        if (!$order['addressid']) {
            return mz_apierror("请添加收获地址");
        }
        
        $mem = $this->mem_model->where("id='{$uid}'")->find();
        $fee = $order['total_price'];
        
        if ($uid == 10055) {
            $fee = '0.01';
        }
        
        $weixinpay = new WeixinPay(
            $this->config['wx_appid'], 
            $mem['openid'], 
            $this->config_pay['mch_id'], 
            $this->config_pay['api_sec'], 
            $order['orderid'],
            '蜜蜂商城', 
            $fee * 100, 
            $this->config_pay['notify_url']
        );
        $return = $weixinpay->pay();
        return mz_apisuc("成功", $return);
    }
    
    #wx回调
    public function paysuc() {
        $id = input("orderid");
        
        $order = $this->order_model->where("id='{$id}'")->find();
        if ($order['haspay'] == 1) {
            return mz_apierror("已支付");
        }
        
        $res = $this->order_model->where("id='{$id}'")->update(array('haspay' => 1, 'paytime' => time(), "status" => 1));
        if ($res) {
            #更新流水记录
            $order_info = $this->order_model->where("id='{$id}'")->find();
            $uid = $order_info['uid'];
            $member_info = $this->mem_model->where("id='{$uid}'")->find();
            mz_flow($uid, $id, 4, "-".$order_info['total_price'], "购买蜂蜜支出", $member_info['balance']);
            
            #增加商品销量
            $order_detail = $this->order_detail_model->where("oid='{$order_info['id']}'")->select();
            foreach ($order_detail as $k=>$v) {
                $this->product_model->where("id='{$v['product_id']}'")->setInc("selnums", $v['nums']);
            }
            #更新用户等级
            
            if ($member_info['level'] == 1) {
                #进行升级，并记录日志
                $ins_data = array();
                $ins_data['uid'] = $uid;
                $ins_data['direct_nums'] = 1;
                $ins_data['indirect_nums'] = 0;
                $ins_data['des'] = "达到 业务员 等级进行升级";
                $ins_data['createtime'] = time();
                $this->levellog_model->insert($ins_data);
                $this->mem_model->where("id='{$uid}'")->update(array("level"=>2));
            }
            
            #处理提成  处理业绩
            foreach ($order_detail as $k => $v) {
                $bottles = $v['bottles'];
                $this->doachieve($uid, $v['isrebate'], $v);
                $this->doresult($uid, $bottles);
            }
            
            return mz_apisuc("支付成功");
        } else {
            return mz_apierror("支付失败");
        }
    }
    
    
    
    #wx回调
    public function paysuctest() {
        exit;
        $id = input("orderid");
        
        $order = $this->order_model->where("id='{$id}'")->find();
        if ($order['haspay'] == 1) {
            return mz_apierror("已支付");
        }
        
        $res = $this->order_model->where("id='{$id}'")->update(array('haspay' => 1, 'paytime' => time(), "status" => 1));
        if ($res) {
            #更新流水记录
            $order_info = $this->order_model->where("id='{$id}'")->find();
            $uid = $order_info['uid'];
            $member_info = $this->mem_model->where("id='{$uid}'")->find();
            mz_flow($uid, $id, 4, "-".$order_info['total_price'], "购买蜂蜜支出", $member_info['balance']);
            
            #增加商品销量
            $order_detail = $this->order_detail_model->where("oid='{$order_info['id']}'")->select();
            foreach ($order_detail as $k=>$v) {
                $this->product_model->where("id='{$v['product_id']}'")->setInc("selnums", $v['nums']);
            }
            #更新用户等级
            
            if ($member_info['level'] == 1) {
                #进行升级，并记录日志
                $ins_data = array();
                $ins_data['uid'] = $uid;
                $ins_data['direct_nums'] = 1;
                $ins_data['indirect_nums'] = 0;
                $ins_data['des'] = "达到 业务员 等级进行升级";
                $ins_data['createtime'] = time();
                $this->levellog_model->insert($ins_data);
                $this->mem_model->where("id='{$uid}'")->update(array("level"=>2));
            }
            
            #处理提成  处理业绩
            if ($uid == 10055) {
                foreach ($order_detail as $k => $v) {
                    $bottles = $v['bottles'];

                    $this->doachieve($uid, $v['isrebate'], $v);

                    $this->doresult($uid, $bottles);
                }
            } else {
                foreach ($order_detail as $k => $v) {
                    $bottles = $v['bottles'];

                    

                    $this->doresult($uid, $bottles);
                }
            }
            return mz_apisuc("支付成功");
        } else {
            return mz_apierror("支付失败");
        }
    }
    
    
    
    
    
    
    
    
    #添加地址
    public function addressadd() {
        $uid = session("userid");
        $ispost = input("ispost");
        $uname = input("uname");
        $mobile = input("mobile");
        $pro_city_reg = input("pro_city_reg");
        $detail = input("detail");
        $isdef = input("isdef");
        $id = input("id");
        
        if ($ispost) {
            $ins_data = array();
            $ins_data = [
                "uid"=>$uid,
                "uname"=>$uname,
                "mobile"=>$mobile,
                "pro_city_reg"=>$pro_city_reg,
                "detail"=>$detail,
                "isdef"=>$isdef,
                "createtime"=>time()
            ];
            if ($isdef == 1) {
                $this->address_model->where("uid='{$uid}'")->update(["isdef"=>0]);
            }
            $res = $this->address_model->insertGetId($ins_data);
            if ($res) {
                $address = $this->address_model->where("id='{$res}'")->find();
                
                #为此订单赋值
                $order_data = array();
                $order_data['addressid'] = $address['id'];
                $order_data['addrname'] = $address['uname'];
                $order_data['addrmobile'] = $address['mobile'];
                $order_data['addrdetail'] = $address['pro_city_reg'] . $address['detail'];
                $this->order_model->where("id='{$id}'")->update($order_data);
                return mz_apisuc("添加成功");
            } else {
                return mz_apierror("添加失败");
            }
        }
        
        $this->assign("id", $id);
        $this->assign("title", "添加收货地址");
        return $this->fetch();
    }
    
    #我的收获地址
    public function addresslist() {
        $uid = session("userid");
        $id = input("id");
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        $address_list = $this->address_model->where("uid='{$uid}'")->select();
        
        $this->assign("id", $id);
        $this->assign("list", $address_list);
        $this->assign("info", $mem_info);
        $this->assign("title", "我的收获地址");
        return $this->fetch();
    }
    
    public function chooseaddr() {
        $uid = session("userid");
        $id = input("id");
        $oid = input("oid");
        
        
        $address = $this->address_model->where("id='{$id}'")->find();
        #为此订单赋值
        $order_data = array();
        $order_data['addressid'] = $address['id'];
        $order_data['addrname'] = $address['uname'];
        $order_data['addrmobile'] = $address['mobile'];
        $order_data['addrdetail'] = $address['pro_city_reg'] . $address['detail'];
        $r = $this->order_model->where("id='{$oid}'")->update($order_data);
        return mz_apisuc("添加成功");
    }
    
    
    #处理提成
    #isrebate 2：参与分佣
    public function doachieve($uid, $isrebate, $orderdetail) {
        
        $this->sysconfig_model = model("Sysconfig");
        $this->mem_model = model("Members");
        
        $sysconfig = $this->sysconfig_model->select();
        if (!$sysconfig) {
            $sysconfig = array(
                "0"=>["level"=>4,"direct_price"=>170,"second_price"=>120,"third_price"=>20],
                "1"=>["level"=>3,"direct_price"=>150,"second_price"=>100,"third_price"=>0],
                "2"=>["level"=>2,"direct_price"=>50,"second_price"=>0,"third_price"=>0],
            );
        }

        #是否参佣
        if ($isrebate == 2) {
            #新用户购买 还是 复购
            if ($orderdetail['isnew'] == 1) {
                #瓶数
                $bottels = $orderdetail['bottles'];
                
                #分佣
                $result = $this->recurrence_achieve($uid);
                
                if ($result) {
                    #只有业务员
                    if ($result['level2'] && !$result['level3'] && !$result['level4']) {
                        foreach ($sysconfig as $k=>$v) {
                            if ($v['level'] == 2) {
                                $re_uid = $result['level2'];
                                $money = $v['direct_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "业务员直推提成", $balance[0]);
                            }
                        }
                    }
                    
                    #只有主管
                    if ($result['level3'] && !$result['level2'] && !$result['level4']) {
                        foreach ($sysconfig as $k=>$v) {
                            if ($v['level'] == 3) {
                                $re_uid = $result['level3'];
                                $money = $v['direct_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "销售主管直推提成", $balance[0]);
                            }
                        }
                    }
                    
                    #只有总监
                    if ($result['level4'] && !$result['level2'] && !$result['level3']) {
                        foreach ($sysconfig as $k=>$v) {
                            if ($v['level'] == 4) {
                                $re_uid = $result['level4'];
                                $money = $v['direct_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "销售总监直推提成", $balance[0]);
                            }
                        }
                    }
                    
                    #业务员+主管
                    if ($result['level2'] && $result['level3'] && !$result['level4']) {
                        foreach ($sysconfig as $k=>$v) {
                            if ($v['level'] == 2) {
                                $re_uid = $result['level2'];
                                $money = $v['direct_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "业务员直推提成", $balance[0]);
                            }
                            if ($v['level'] == 3) {
                                $re_uid = $result['level3'];
                                $money = $v['second_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "下级业务员推销提成", $balance[0]);
                            }
                        }
                    }
                    
                    #业务员+总监
                    if ($result['level2'] && $result['level4'] && !$result['level3']) {
                        foreach ($sysconfig as $k=>$v) {
                            if ($v['level'] == 2) {
                                $re_uid = $result['level2'];
                                $money = $v['direct_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "业务员直推提成", $balance[0]);
                            }
                            if ($v['level'] == 4) {
                                $re_uid = $result['level4'];
                                $money = $v['second_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "下级业务员推销提成", $balance[0]);
                            }
                        }
                    }
                    
                    #主管+总监
                    if ($result['level3'] && $result['level4'] && !$result['level2']) {
                        foreach ($sysconfig as $k=>$v) {
                            if ($v['level'] == 3) {
                                $re_uid = $result['level3'];
                                $money = $v['direct_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "主管直推提成", $balance[0]);
                            }
                            if ($v['level'] == 4) {
                                $re_uid = $result['level4'];
                                $money = $v['third_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "下级主管推销提成", $balance[0]);
                            }
                        }
                    }
                    
                    #业务员+主管+总监
                    if ($result['level3'] && $result['level4'] && $result['level2']) {
                        foreach ($sysconfig as $k=>$v) {
                            if ($v['level'] == 2) {
                                $re_uid = $result['level2'];
                                $money = $v['direct_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "业务员直推提成", $balance[0]);
                            }
                            if ($v['level'] == 3) {
                                $re_uid = $result['level3'];
                                $money = $v['second_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "下级业务员推销提成", $balance[0]);
                            }
                            if ($v['level'] == 4) {
                                $re_uid = $result['level4'];
                                $money = $v['third_price'] * $bottels;
                                
                                $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                                $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                                $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                                mz_flow($re_uid, $orderdetail['oid'], 1, "+".$money, "下级主管推销提成", $balance[0]);
                            }
                        }
                    }
                }
            } else {
                #上面3位老师分别奖励10元
                $uids = $this->recurrence_3($uid);
                #print_r($uids);exit;
                if ($uids) {
                    foreach ($uids as $k=>$v) {
                        if ($k<=2) {
                            $re_uid = $v;
                            #瓶数
                            $bottels = $orderdetail['bottles'];
                            $money = 10 * $bottels;
                            $this->mem_model->where("id='{$re_uid}'")->setInc("balance", $money);
                            $this->mem_model->where("id='{$re_uid}'")->setInc("total_balance", $money);
                            $balance = $this->mem_model->where("id='{$re_uid}'")->column("balance");
                            mz_flow($re_uid, $orderdetail['oid'], 3, "+".$money, "复购奖励", $balance[0]);
                        }
                    }
                }
            }
        }
    }
    
    
    
    
    #递归处理业绩
    public function doresult($uid, $bottles) {
        $this->memberresult_model = model("Memberresult");
        
        
        #当前年份
        $year = date('Y', time());
        #当前月份
        $month = date('m', time());
        $uids_link = array();
        #递归整个上级链
        $uids_link[] = $uid; 
        $uids = $this->recurrence($uid, $uids_link);
        if ($uids) {
            foreach ($uids as $k=>$v) {
                $has_result = $this->memberresult_model->where("uid='{$v}' and year='{$year}' and month='{$month}'")->find();
                if ($has_result) {
                    if ($k == 0 ) {
                        $this->memberresult_model->where("id='{$has_result['id']}'")->setInc("direct_nums",$bottles);
                    } elseif ($k == 1) {
                        $this->memberresult_model->where("id='{$has_result['id']}'")->setInc("direct_nums",$bottles);
                    } else {
                        $this->memberresult_model->where("id='{$has_result['id']}'")->setInc("redirect_nums",$bottles);
                    }
                    
                    #升级判断处理
                    $level = $this->dolevel($v);
                    
                    $this->memberresult_model->where("id='{$has_result['id']}'")->update(array('level'=>$level));
                    
                } else {
                    if ($k == 0 ) {
                        #直接上级 增加直销量
                        $ins_data = array();
                        $ins['uid'] = $v;
                        $ins['year'] = $year;
                        $ins['month'] = $month;
                        $ins['direct_nums'] = $bottles;
                        $ins['redirect_nums'] = 0;
                    } elseif ($k == 1) {
                        #直接上级 增加直销量
                        $ins_data = array();
                        $ins['uid'] = $v;
                        $ins['year'] = $year;
                        $ins['month'] = $month;
                        $ins['direct_nums'] = $bottles;
                        $ins['redirect_nums'] = 0;
                    }else {
                        #直接上级 增加直销量
                        $ins_data = array();
                        $ins['uid'] = $v;
                        $ins['year'] = $year;
                        $ins['month'] = $month;
                        $ins['direct_nums'] = 0;
                        $ins['redirect_nums'] = $bottles;
                    }
                    $id = $this->memberresult_model->insertGetId($ins);
                    
                    #升级判断处理
                    $level = $this->dolevel($v);
                    $this->memberresult_model->where("id='{$id}'")->update(array('level'=>$level));
                }
            }
        }
    }
    
    #升级判断处理
    public function dolevel($uid) {
        
        $this->sysconfig_model = model("Sysconfig");
        $this->mem_model = model("Members");
        $this->memberresult_model = model("Memberresult");
        $this->levellog_model = model("Levellog");
        
        #系统配置参数
        $sysconfig = $this->sysconfig_model->order("level desc")->select();
        
        if (!$sysconfig) {
            $sysconfig = array(
                "0"=>["level"=>4,"directnums"=>30,"indirect_nums"=>100],
                "1"=>["level"=>3,"directnums"=>15,"indirect_nums"=>50],
                "2"=>["level"=>2,"directnums"=>0,"indirect_nums"=>0]
            );
        }

        $member_info = $this->mem_model->where("id='{$uid}'")->find();
        #已是最高等级
        if ($member_info['level'] == 4) {
            return false;
        }
        
        #当前用户推销战绩
        #直销战绩
        $level_1 = 2;
        $level_2 = 2;
        
        #直销最高等级
        $direct_nums = $this->memberresult_model->where("uid='{$uid}'")->sum("direct_nums");
        foreach ($sysconfig as $k=>$v) {
            if ($direct_nums >= $v['directnums']) {
                $level_1 = $v['level'];
                break;
            }
        }
        
        #分销最高等级
        $redirect_nums = $this->memberresult_model->where("uid='{$uid}'")->sum("redirect_nums");
        foreach ($sysconfig as $k=>$v) {
            if ($redirect_nums >= $v['indirect_nums']) {
                $level_2 = $v['level'];
                break;
            }
        }
        
        $level = max($level_1, $level_2);
        switch ($level) {
            case 2:
                $level_name = "业务员";
                break;
            case 3:
                $level_name = "销售主管";
                break;
            case 4:
                $level_name = "销售总监";
                break;
        }
        
//        echo $level;
//        echo $member_info['level'];
//        exit;
//        
        
        if ($level > $member_info['level']) {
           
            #进行升级，并记录日志
            $ins_data = array();
            $ins_data['uid'] = $uid;
            $ins_data['direct_nums'] = $direct_nums;
            $ins_data['indirect_nums'] = $redirect_nums;
            $ins_data['des'] = "达到 {$level_name} 等级进行升级";
            $ins_data['createtime'] = time();
            
            $this->levellog_model->insert($ins_data);
            #更新用户等级
            $this->mem_model->where("id='{$uid}'")->update(array("level"=>$level));
            return $level;
        } 
        return $member_info['level'];
    }
    
    #递归求链
    public function recurrence($uid, &$result=array()) {
        
        $this->membership_model = model("Membership");
        
        $uinfo = $this->membership_model->where("uid='{$uid}'")->find();
        #本人
        if ($uinfo['parentid']) {
            $result[] = $uinfo['parentid'];
            $this->recurrence($uinfo['parentid'],$result);
        }
        return $result;
    }
    
    #递归求链
    public function recurrence_2($uid, &$result=array()) {
        $this->membership_model = model("Membership");
        $this->mem_model = model("Members");
        $uinfo = $this->membership_model->where("uid='{$uid}'")->find();
        if ($uinfo['parentid']) {
            $tmp = array();
            $level = $this->mem_model->where("id='{$uinfo['parentid']}'")->column("level");
            $tmp['uid'] = $uinfo['parentid'];
            $tmp['level'] = $level[0];
            $result[] = $tmp;
            $this->recurrence_2($uinfo['parentid'],$result);
        }
        return $result;
    }
    
    #递归求链
    public function recurrence_3($uid, &$result=array()) {
        $this->membership_model = model("Membership");
        $uinfo = $this->membership_model->where("uid='{$uid}'")->find();
        if ($uinfo['parentid']) {
            $result[] = $uinfo['parentid'];
            $this->recurrence_3($uinfo['parentid'],$result);
        }
        return $result;
    }
    
    #递归分佣链
    public function recurrence_achieve($uid) {
        $this->membership_model = model("Membership");
        $this->mem_model = model("Members");
        
        $result = array();
        $uinfo = $this->membership_model->where("uid='{$uid}'")->find();
        #链条
        $uids = $this->recurrence_2($uid);
        if ($uinfo['parentid']) {
            $lv = $this->mem_model->where("id='{$uinfo['parentid']}'")->column("level");
            $level = $lv[0];
            
            if ($level == 4) {
                #直接返回
                $result['level4'] = $uinfo['parentid'];
            } elseif ($level == 3) {
                $result['level3'] = $uinfo['parentid'];
                if ($uids) {
                    unset($uids[0]);
                    if (count($uids) > 0) {
                        foreach ($uids as $k=>$v) {
                            if ($v['level'] == 4) {
                                $result['level4'] = $v['uid'];
                                break;
                            }
                        }
                    }
                }
            } elseif ($level == 2) {
                $result['level2'] = $uinfo['parentid'];
                if ($uids) {
                    unset($uids[0]);
                    if (count($uids) > 0) {
                        foreach ($uids as $k=>$v) {
                            if ($v['level'] == 4) {
                                $result['level4'] = $v['uid'];
                                break;
                            } elseif ($v['level'] == 3) {
                                $result['level3'] = $v['uid'];
                                #继续找
                                foreach ($uids as $k1=>$v1) {
                                    if ($v1['level'] == 4) {
                                        $result['level4'] = $v1['uid'];
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                    }
                }
            }
            return $result;
        }
    }

}
