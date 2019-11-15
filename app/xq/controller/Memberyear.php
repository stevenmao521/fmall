<?php
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\Form;//表单
use app\xq\controller\Helper as Helper;//工具类
use think\Config;

class Memberyear extends Common{
    protected $modname; #模块名称
    protected $dao; #默认模型
    protected $fields; #字段
    protected $lfields;
    protected $controller; #控制器
    protected $log_mod; #日志模型
    protected $logid; #日志模型id
    protected $form;    #表单
    protected $helper;  #工具
    protected $neednums;

    #初始化
    public function _initialize() {
        
        parent::_initialize();
        
        $this->controller = "memberyear";
        $this->modname = "分销年终奖";
        
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
        
        $this->neednums = 1000;
        
        $this->assign('moduleid', $this->moduleid);
        $this->assign ('fields',$this->fields);#新增编辑字段
        $this->assign('modname', $this->modname);
    }
    
    #年终
    public function index(){
        $ispost = input("ispost");
        $host = Config::get('host');
        #$host = "www.fengmi.com";
        if ($ispost) {
            $start = input("start");
            $end = input("end");
            
            if (!$start || !$end) {
                return mz_apierror("请选择日期");
            }
            $url = "http://".$host."/bee/Year/done";
            $params = array("start"=>$start, "end"=>$end);
            $res_json = mz_http_send($url, $params, "POST");
            
            
            if ($res_json) {
                $res = json_decode($res_json,1);
                $return = array();
                
                $tj_bottles = 0;
                $tj_award = 0;
                
                foreach ($res as $k=>$v) {
                    $tmp = array();
                    $tmp['uid'] = $v['uid'];
                    $tmp['nickname'] = $v['nickname'];
                    $tmp['level'] = "销售总监";
                    $tmp['result'] = $v['bottles'];
                    if ($v['bottles'] >= $this->neednums) {
                        #奖励金额
                        $reward = $v['bottles'] * 20;
                        $tmp['status'] = "<font style='color:green;'>达到条件</font>";
                        
                        $tj_bottles += $v['bottles'];
                        $tj_award += $reward;
                        
                    } else {
                        $reward = 0;
                        $tmp['status'] = "<font style='color:red;'>未满足</font>";
                    }
                    
                    #上级是否销售总监
                    $mem_info = db("members")->where("id='{$v['uid']}'")->find();
                    if ($mem_info['parent_id']) {
                        $parent = db("members")->where("id='{$mem_info['parent_id']}'")->find();
                        
                        if ($parent['level'] == 4) {
                            $tmp['reward'] = ($reward/10) * 9;
                            $tmp['father'] = $reward/10;
                        } else {
                            $tmp['reward'] = $reward;
                            $tmp['father'] = 0;
                        }
                    } else {
                        $tmp['reward'] = $reward;
                        $tmp['father'] = 0;
                    }
                    $return[] = $tmp;
                }
                $tmp = array();
                $tmp['uid'] = '统计';
                $tmp['nickname'] = '奖励总瓶数：'.$tj_bottles;
                $tmp['level'] = '总奖金：'.$tj_award;
                $tmp['result'] = "";
                $tmp['status'] = "";
                $tmp['reward'] = "";
                $tmp['father'] = "";
                $return[] = $tmp;
                
                return mz_apisuc("成功", $return);
                
            } else {
                return mz_apierror("没有数据");
            }
        }
        return $this->fetch();
    }
    
    #生成年终奖发放单
    public function createorder() {
        
        $year_order_model = db("yearorder");
        $year_orderdetail_model = db("yearorderdetail");
        
        $ispost = input("ispost");
        $host = Config::get('host');
        #$host = "www.fengmi.com";
        if ($ispost) {
            $start = input("start");
            $end = input("end");
            
            if (!$start || !$end) {
                return mz_apierror("请选择日期");
            }
            
            $url = "http://".$host."/bee/Year/done";
            $params = array("start"=>$start, "end"=>$end);
            $res_json = mz_http_send($url, $params, "POST");
            $res = json_decode($res_json,1);
            if (count($res)<1) {
                return mz_apierror("没有数据，生成失败。");
            }
            
            $ins_data = array();
            $ins_data['name'] = $start."至".$end."年终奖发放单";
            $ins_data['start'] = $start;
            $ins_data['end'] = $end;
            $ins_data['createtime'] = time();
            $oid = $year_order_model->insertGetId($ins_data);
            
            if ($res_json) {
                $res = json_decode($res_json,1);
                $return = array();
                
                $tj_bottles = 0;
                $tj_award = 0;
                $tj_nums = 0;
                
                foreach ($res as $k=>$v) {
                    $tmp = array();
                    $tmp['uid'] = $v['uid'];
                    $tmp['nickname'] = $v['nickname'];
                    $tmp['level'] = "销售总监";
                    $tmp['result'] = $v['bottles'];
                    if ($v['bottles'] >= $this->neednums) {
                        #奖励金额
                        $reward = $v['bottles'] * 20;
                        $tmp['status'] = "<font style='color:green;'>达到条件</font>";
                        
                        $tj_bottles += $v['bottles'];
                        $tj_award += $reward;
                        $tj_nums++;
                        
                        #上级是否销售总监
                        $mem_info = db("members")->where("id='{$v['uid']}'")->find();
                        if ($mem_info['parent_id']) {
                            $parent = db("members")->where("id='{$mem_info['parent_id']}'")->find();

                            if ($parent['level'] == 4) {
                                $tmp['reward'] = ($reward/10) * 9;
                                $tmp['father'] = $reward/10;
                            } else {
                                $tmp['reward'] = $reward;
                                $tmp['father'] = 0;
                            }
                        } else {
                            $tmp['reward'] = $reward;
                            $tmp['father'] = 0;
                        }
                        $tmp['oid'] = $oid;
                        $year_orderdetail_model->insert($tmp);
                    }
                    
                }
                $year_order_model->where("id='{$oid}'")->update(array(
                    "allnums"=>$tj_bottles,
                    "allreward"=>$tj_award,
                    "allperson"=>$tj_nums,
                    "status"=>0
                ));
                return mz_apisuc("生成发放单成功", $return);
                
            } else {
                return mz_apierror("失败");
            }
        }
    }
    
    #年终列表
    public function orderlist() {
        $list = db("yearorder")->order("createtime desc")->select();
        if ($list) {
            foreach ($list as $k=>$v) {
                $list[$k]['date'] = date("Y-m-d H:i",$v['createtime']);
            }
        }
        $this->assign("list", $list);
        return $this->fetch();
    }
    
    
    #年终
    public function reward(){
        $id = input("id");
        
        $order = db("yearorder")->where("id='{$id}'")->find();
        if ($order['status'] == 1) {
            return mz_apierror("已经发放");
        }
        
        $res = db("yearorderdetail")->where("oid='{$id}'")->select();
        foreach ($res as $k=>$v) {
            if ($v['father'] > 0) {
                $parent = db("members")->where("id='{$v['uid']}'")->find();
                
                #增加金额
                db("members")->where("id='{$v['uid']}'")->setInc("balance", $v['reward']);
                db("members")->where("id='{$v['uid']}'")->setInc("total_balance", $v['reward']);

                db("members")->where("id='{$parent['parent_id']}'")->setInc("balance", $v['father']);
                db("members")->where("id='{$parent['parent_id']}'")->setInc("total_balance", $v['father']);

                $balance1 = db("members")->where("id='{$v['uid']}'")->column("balance");
                $balance2 = db("members")->where("id='{$parent['parent_id']}'")->column("balance");

                mz_flow($v['uid'], "", 6, "+" . $v['reward'], "年终奖", $balance1[0]);
                mz_flow($parent['parent_id'], "", 7, "+" . $v['father'], "下级年终奖感恩", $balance2[0]);
            } else {
                db("members")->where("id='{$v['uid']}'")->setInc("balance", $v['reward']);
                db("members")->where("id='{$v['uid']}'")->setInc("total_balance", $v['reward']);
                $balance1 = db("members")->where("id='{$v['uid']}'")->column("balance");
                mz_flow($v['uid'], "", 6, "+" . $v['reward'], "年终奖", $balance1[0]);
            }
        }
        db("yearorder")->where("id='{$id}'")->update(array("status"=>1));
        return mz_apisuc("发放成功");
    }
    
}