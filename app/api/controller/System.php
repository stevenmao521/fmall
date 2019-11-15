<?php
 /* 
  * Copyright (C) 2017 All rights reserved.
 *   
 * @File UserTest.php
 * @Brief 
 * @Author 毛子
 * @Version 1.0
 * @Date 2017-12-26
 * @Remark 分销处理
 */
namespace app\api\controller;
use think\Config;
use think\Db;


class System extends Common {
    
    
    protected $mem_model;
    protected $addr_model;
    protected $order_model;
    protected $orderdetail_model;
    protected $product_model;
    protected $memberresult_model;
    protected $membership_model;
    protected $sysconfig_model;
    protected $levellog_model;

    public function _initialize() {
        parent::_initialize();
        $this->assign("title", "个人中心");
        
        $this->mem_model = model("Members");
        $this->addr_model = model("Address");
        $this->order_model = model("Order");
        $this->orderdetail_model = model("Orderdetail");
        $this->product_model = model("Product");
        $this->memberresult_model = model("Memberresult");
        $this->membership_model = model("Membership");
        $this->sysconfig_model = model("Sysconfig");
        $this->levellog_model = model("Levellog");
    }
    
    
    public function doneapi() {
        $uid = input("uid");
        $id = input("id");
       
        #检查订单状态
        $order_info = $this->order_model->where("id='{$id}'")->find();
        if ($order_info['status'] == 4) {
            return mz_apisuc("此订单已完成");
        }
        if ($order_info['uid'] != $uid) {
            return mz_apisuc("参数错误");
        }
        
        #更新订单状态
        $this->order_model
            ->where("id='{$id}'")
            ->update(array("status"=>4,"finishtime"=>time()));
            
        $order_info = $this->order_model->where("id='{$id}'")->find();
        $order_detail = $this->orderdetail_model->where("oid='{$id}'")->select();
        
        #业绩加成
        if ($order_detail) {
            foreach ($order_detail as $k=>$v) {
                #瓶数
                $bottles = $v['bottles'];
                
                #处理提成
                $this->doachieve($uid, $v['isrebate'], $v);
                
                #处理业绩
                $this->doresult($uid, $bottles);
            }
        }
        return mz_apisuc("订单完成成功");
    }
    
    
    #处理提成
    #isrebate 2：参与分佣
    public function doachieve($uid, $isrebate, $orderdetail) {
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
        $uinfo = $this->membership_model->where("uid='{$uid}'")->find();
        #本人
        if ($uinfo['parentid']) {
            $result[] = $uinfo['parentid'];
            $this->recurrence($uinfo['parentid'],$result);
        }
        return $result;
    }
    
    #递归求链
    public function recurrence_3($uid, &$result=array()) {
        $uinfo = $this->membership_model->where("uid='{$uid}'")->find();
        if ($uinfo['parentid']) {
            $result[] = $uinfo['parentid'];
            $this->recurrence_3($uinfo['parentid'],$result);
        }
        return $result;
    }
    
    #递归求链
    public function recurrence_2($uid, &$result=array()) {
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
    
    
    #递归分佣链
    public function recurrence_achieve($uid) {
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
    
    
    #测试清空
    public function test() {
        $uid = session("userid");
        $flow = model("memberflow");
        $flow->where("uid>0")->delete();
        #余额清0 等级业务员
        $this->mem_model->where("id>0")->update(["balance"=>0,"total_balance"=>0,"level"=>2]);
        $this->memberresult_model->where("uid>0")->delete();
        
        #当前用户等级1
        $this->mem_model->where("id='{$uid}'")->update(["level"=>1]);
        
        #清理订单
        $this->order_model->where("id>0")->delete();
        $this->orderdetail_model->where("id>0")->delete();
        
        #年终奖单
        db("yearorder")->where("id>0")->delete();
        db("yearorderdetail")->where("id>0")->delete();
        db("cashorder")->where("id>0")->delete();
        
        $this->mem_model->where("id='10000'")->update(["level"=>4]);
        
        #升级日志
        $levellog = $this->levellog_model->where("uid>0")->delete();
        return mz_apierror("清空测试成功");
    }
    
}