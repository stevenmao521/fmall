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

class Userinfo extends Common {
    
    protected $helper;
    protected $mem_model;
    protected $addr_model;
    protected $order_model;
    protected $orderdetail_model;
    protected $product_model;
    protected $flow_model;
    protected $cash_order;
    protected $memresult_model;

    public function _initialize() {
        
        #检查登陆
        $this->checklogin();
        
        parent::_initialize();
        $this->assign("title", "个人中心");
        $this->helper = new HelperDao();
        $this->mem_model = model("Members");
        $this->addr_model = model("Address");
        $this->order_model = model("Order");
        $this->orderdetail_model = model("Orderdetail");
        $this->product_model = model("Product");
        $this->flow_model = model("Memberflow");
        $this->cash_order = model("Cashorder");
        $this->memresult_model = model("Memberresult");
    }
    
    public function index() {
        $uid = session("userid");
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        
        $this->assign("info", $mem_info);
        return $this->fetch();
    }
    
    #个人中心资料编辑
    public function info() {
        $uid = session("userid");
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        $mem_info['level_name'] = mz_gettype($mem_info['level']);
        
        $this->assign("info", $mem_info);
        $this->assign("title", "个人资料");
        return $this->fetch();
    }
    
    #修改昵称
    public function changename() {
        $uid = session("userid");
        $ispost = input("ispost");
        $nickname = input("nickname");
        
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        if ($ispost) {
            $res = $this->mem_model->where("id='{$uid}'")->update(['nickname'=>$nickname]);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apierror("修改失败");
            }
        }
        
        $this->assign("info", $mem_info);
        $this->assign("title", "修改昵称");
        return $this->fetch();
    }
    
    #修改昵称
    public function changebankname() {
        $uid = session("userid");
        $ispost = input("ispost");
        $nickname = input("nickname");
        
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        if ($ispost) {
            $res = $this->mem_model->where("id='{$uid}'")->update(['bankname'=>$nickname]);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apierror("修改失败");
            }
        }
        
        $this->assign("info", $mem_info);
        $this->assign("title", "修改开户行");
        return $this->fetch();
    }
    
    #修改昵称
    public function changerealname() {
        $uid = session("userid");
        $ispost = input("ispost");
        $nickname = input("nickname");
        
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        if ($ispost) {
            $res = $this->mem_model->where("id='{$uid}'")->update(['realname'=>$nickname]);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apierror("修改失败");
            }
        }
        
        $this->assign("info", $mem_info);
        $this->assign("title", "修改开户姓名");
        return $this->fetch();
    }
    
    #修改手机
    public function changemobile() {
        $uid = session("userid");
        $ispost = input("ispost");
        $nickname = input("nickname");
        
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        if ($ispost) {
            $res = $this->mem_model->where("id='{$uid}'")->update(['mobile'=>$nickname]);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apierror("修改失败");
            }
        }
        
        $this->assign("info", $mem_info);
        $this->assign("title", "修改手机");
        return $this->fetch();
    }
    
    #修改昵称
    public function changebankcode() {
        $uid = session("userid");
        $ispost = input("ispost");
        $nickname = input("nickname");
        
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        if ($ispost) {
            $res = $this->mem_model->where("id='{$uid}'")->update(['bankcode'=>$nickname]);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apierror("修改失败");
            }
        }
        
        $this->assign("info", $mem_info);
        $this->assign("title", "修改银行卡号");
        return $this->fetch();
    }
    
    #我的推广
    public function myshare() {
        $uid = session("userid");
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        
        $this->assign("info", $mem_info);
        $this->assign("title", "我的推广");
        return $this->fetch();
    }
    
    #我的二维码
    public function qrcode() {
        $uid = session("userid");
        $member = $this->mem_model->where("id='{$uid}'")->find();
        $serviceid = $member['serviceid'];
        $host = Config::get('host');
        $url = "http://".$host."/bee/Passport/share/id/".$serviceid;
        echo $this->helper->scerweima($url);
        exit;
    }
    
    #我的收获地址
    public function myaddress() {
        $uid = session("userid");
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        
        $address_list = $this->addr_model->where("uid='{$uid}'")->select();
        
        $this->assign("list", $address_list);
        $this->assign("info", $mem_info);
        $this->assign("title", "我的收获地址");
        return $this->fetch();
    }
    
    #添加新收获地址
    public function addaddress() {
        $uid = session("userid");
        $ispost = input("ispost");
        $uname = input("uname");
        $mobile = input("mobile");
        $pro_city_reg = input("pro_city_reg");
        $detail = input("detail");
        $isdef = input("isdef");
        
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
                $this->addr_model->where("uid='{$uid}'")->update(["isdef"=>0]);
            }
            $res = $this->addr_model->insert($ins_data);
            if ($res) {
                return mz_apisuc("添加成功");
            } else {
                return mz_apierror("添加失败");
            }
        }
        $this->assign("title", "添加收货地址");
        return $this->fetch();
    }
    
    #编辑收货地址
    public function editaddress() {
        $uid = session("userid");
        $id = input("id");
        $ispost = input("ispost");
        $uname = input("uname");
        $mobile = input("mobile");
        $pro_city_reg = input("pro_city_reg");
        $detail = input("detail");
        $isdef = input("isdef");
        
        $address = $this->addr_model->where("id='{$id}'")->find();
        
        if ($ispost) {
            $ins_data = array();
            $ins_data = [
                "uname"=>$uname,
                "mobile"=>$mobile,
                "pro_city_reg"=>$pro_city_reg,
                "detail"=>$detail,
                "isdef"=>$isdef,
                "createtime"=>time()
            ];
            if ($isdef == 1) {
                $this->addr_model->where("uid='{$uid}'")->update(["isdef"=>0]);
            }
            $res = $this->addr_model->where("id='{$id}'")->update($ins_data);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apierror("修改失败");
            }
        }
        
        $this->assign("info", $address);
        $this->assign("title", "编辑收货地址");
        return $this->fetch();
    }
    
    #删除收获地址
    public function deladdress() {
        $id = input("id");
        $res = $this->addr_model->where("id='{$id}'")->delete();
        if ($res) {
            return mz_apisuc("删除成功");
        } else {
            return mz_apierror("删除失败");
        }
    }
    
    #在线客服
    public function service() {
        $this->assign("title", "在线客服");
        return $this->fetch();
    }
    
    #关于我们
    public function aboutus() {
        $this->assign("title", "关于我们");
        return $this->fetch();
    }
    
    #关于我们
    public function myservice() {
        $uid = session("userid");
        $mem = $this->mem_model->where("id='{$uid}'")->find();
        if ($mem['parent_id']) {
            $parent = $this->mem_model->where("id='{$mem['parent_id']}'")->find();
            $this->assign("info", $parent);
        }
        $this->assign("title", "我的服务商");
        return $this->fetch();
    }
    
    #我的团队
    public function myteam() {
        $uid = session("userid");
        $mem_info = $this->mem_model->where("id='{$uid}'")->find();
        $mem_info['level_name'] = mz_gettype($mem_info['level']);
        
        #我的团队
        $child_list = $this->mem_model
                ->where("parent_id='{$mem_info['id']}'")
                ->order("createtime desc")->select();
                
        $child_level1 = [];
        $child_level2 = [];
        $child_level3 = [];
        $child_level4 = [];
                
        if ($child_list) {
            foreach ($child_list as $k=>$v) {
                switch ($v['level']) {
                    case 1:
                        $child_level1[] = $v;
                        break;
                    case 2:
                        $child_level2[] = $v;
                        break;
                    case 3:
                        $child_level3[] = $v;
                        break;
                    case 4:
                        $child_level4[] = $v;
                        break;
                }
            }
        }
        
        $dir = $this->memresult_model->where("uid='{$uid}'")->sum("direct_nums");
        $rdir = $this->memresult_model->where("uid='{$uid}'")->sum("redirect_nums");
        $all = $dir + $rdir;
        if (!$all) {
            $all = 0;
        }
        
        $this->assign("all", $all);
        $this->assign("level1", $child_level1);
        $this->assign("level2", $child_level2);
        $this->assign("level3", $child_level3);
        $this->assign("level4", $child_level4);
        $this->assign("info", $mem_info);
        $this->assign("title", "我的团队");
        return $this->fetch();
    }
    
    #我的订单
    public function myorder() {
        $uid = session("userid");
        $type = input("type");
        $page = input("page") ? input("page") : 1;
        $pagesize = 10;
        
        $start = ($page-1)*10;
        $end = $page*10;
        
        if (!$type) {
            $type = "all";
        }
        
        switch ($type) {
            case "all":
                $condition = " 1=1 and status!=0";
                break;
            case "send":
                $condition = " status=1 ";
                break;
            case "hassend":
                $condition = " status=3 ";
                break;
            case "finish":
                $condition = " status=4 ";
                break;
        }
                    
        $order_list = $this->order_model
            ->where("uid='{$uid}' and status!=2")
            ->where($condition)
            ->order("createtime","desc")
            ->limit($start,$end)
            ->select();
        
        if ($order_list) {
            foreach ($order_list as $k=>$v) {
                $order_detail = $this->orderdetail_model->where("oid='{$v['id']}'")->find();
                $product_info = $this->product_model->where("id='{$order_detail['product_id']}'")->find();
                $order_list[$k]['pic'] = mz_pic($product_info['pics']);
                $order_list[$k]['product_name'] = $product_info['name'];
                $order_list[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
                $order_list[$k]['status'] = mz_getstatus($v['status']);
            }
        }
        
        $this->assign("type", $type);
        $this->assign("order_list", $order_list);
        $this->assign("title", "我的订单");
        return $this->fetch();
    }
    
    #订单详情
    public function orderdetail() {
        $uid = session("uid");
        $id = input("id");
        if (!$id) {
            $this->error("参数错误");
        }
        
        $order_info = $this->order_model->where("id='{$id}'")->find();
        $status = $order_info['status'];
        $order_info['status'] = mz_getstatus($order_info['status']);
        $order_info['createtime'] = date("Y-m-d H:i:s", $order_info['createtime']);
        
        #自动收获哦时间
        $auto_time = $order_info['autotime'];
        #减去当前时间
        $left_time = $auto_time - time();
        #天时分
        $left_str = mz_time2string($left_time);
        
        $order_detail = $this->orderdetail_model->where("oid='{$order_info['id']}'")->select();
        foreach ($order_detail as $k=>$v) {
            $product = $this->product_model->where("id='{$v['product_id']}'")->find();
            $order_detail[$k]['pic'] = mz_pic($product['pics']);
            $order_detail[$k]['total_price'] = $v['price'] * $v['nums'];
        }
        
        $this->assign("left_time", $left_str);
        $this->assign("status", $status);
        $this->assign("order_info", $order_info);
        $this->assign("order_detail", $order_detail);
        return $this->fetch();
    }
    
    #我的钱包
    public function mywallet() {
        $uid = session("userid");
        $type = input("type") ? input("type") : 1;
        
        if ($type == 1) {
            $where .= " type IN(1,3,4) ";
        } elseif ($type == 2) {
            $where .= " type=2 ";
        }
        $userinfo = $this->mem_model->where("id='{$uid}'")->find();
        
        #收支明细
        $flow = $this->flow_model
            ->where("uid='{$uid}'")
            ->where($where)
            ->order("createtime desc")
            ->select();
        if ($flow) {
            foreach ($flow as $k=>$v) {
                $flow[$k]['date'] = date("Y.m.d H:i", $v['createtime']);
                if (substr($v['money'],0,1) == "-") {
                    $flow[$k]['money'] = substr($v['money'], 1);
                    $flow[$k]['moneytype'] = 1;
                } elseif(substr($v['money'],0,1) == "+") {
                    $flow[$k]['money'] = substr($v['money'], 1);
                    $flow[$k]['moneytype'] = 2;
                } else {
                    $flow[$k]['moneytype'] = 2;
                }
            }
        }
        $this->assign("type", $type);
        $this->assign("userinfo", $userinfo);
        $this->assign("flow", $flow);
        return $this->fetch();
    }
    
    #提现申请
    public function getcash() {
        $uid = session("userid");
        return $this->fetch();
    }
    
    #检测提现
    public function checkcash() {
        $uid = session("userid");
        $money = input("money");
        
        $uinfo = $this->mem_model->where("id='{$uid}'")->find();
        if (!$uinfo['realname'] || !$uinfo['bankcode'] || !$uinfo['bankname']) {
            $result = array();
            $result['code'] = 2;
            $result['msg'] = "请到个人中心完善银行卡信息";
            return json($result);
            exit;
        }
        if (!preg_match("/^[1-9][0-9]*$/", $money)) {
            return mz_apierror("金额输入不正确");
        }
        if ($money%50 != 0) {
            return mz_apierror("取现金额只能是100的整数");
        }
        #用户余额
        if ($uinfo['balance'] < $money) {
            return mz_apierror("余额不足");
        }
        
        #流水表
        $after_money = $uinfo['balance'] - $money;
        $this->mem_model->where("id='{$uid}'")->setDec("balance", $money);
        mz_flow($uid, "", 2, "-".$money, "申请提现", $after_money);
        
        #提现申请表
        $cash_data = array();
        $cash_data['uid'] = $uid;
        $cash_data['money'] = $money;
        $cash_data['docash'] = ($money/10)*9;
        $cash_data['bankname'] = $uinfo['bankname'];
        $cash_data['realname'] = $uinfo['realname'];
        $cash_data['bankcode'] = $uinfo['bankcode'];
        $cash_data['status'] = 1;
        $cash_data['orderid'] = mz_get_order_sn();
        $cash_data['createtime'] = time();
        $res = $this->cash_order->insert($cash_data);
        if ($res) {
            return mz_apisuc("提现申请成功");
        } else {
            return mz_apierror("提现申请失败");
        }
    }
    
}