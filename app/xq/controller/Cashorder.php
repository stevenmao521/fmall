<?php
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\Form;//表单
use app\xq\controller\Helper as Helper;//工具类

class Cashorder extends Common{
    protected $modname; #模块名称
    protected $dao; #默认模型
    protected $fields; #字段
    protected $lfields;
    protected $controller; #控制器
    protected $log_mod; #日志模型
    protected $logid; #日志模型id
    protected $form;    #表单
    protected $helper;  #工具
    #初始化
    public function _initialize() {
        
        parent::_initialize();
        
        $this->controller = "cashorder";
        $this->modname = "提现管理";
        
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
        $this->assign('moduleid', $this->moduleid);
        $this->assign ('fields',$this->fields);#新增编辑字段
        $this->assign('modname', $this->modname);
    }
    
    #打款
    public function isCash() {
        $this->mem_model = db("Members");
        $map['id'] = input('post.id');
        #判断当前状态情况
        $info = $this->dao->where($map)->find();
        
        if ($info['status'] == 2) {
            return ['code'=>0,'msg'=>'失败！'];
        }
        
        $data['status'] = 2;
        $data['dotime'] = time();
		$r = $this->dao->where($map)->setField($data);
		
		
        
        if ($r) {
            #10位老师提现1%
            $uid = $info['uid'];
            
			$this->mem_model->where("id='{$info['uid']}'")->setInc("getcash",$info['money']);
			
            #老师
            $parent_uid = $this->recurrence($uid);
            #取现金额10%
            $cash = $info['money'] / 10;
            
            if ($parent_uid) {
                #
                $cash_item = $cash / 10;
                foreach($parent_uid as $k=>$v) {
                    if ($k <= 9) {
                        $this->mem_model->where("id='{$v['uid']}'")->setInc("balance", $cash_item);
                        $this->mem_model->where("id='{$v['uid']}'")->setInc("total_balance", $cash_item);
                        $member = $this->mem_model->where("id='{$v['uid']}'")->find();
                        $after_money = $member['balance'];
                        
                        mz_flow($v['uid'], $info['id'], 5, "+".$cash_item, "提现奖励", $after_money);
                    }
                }
                $count_uid = count($parent_uid);
                if ($count_uid < 10) {
                    $diff = 10 - $count_uid;
                }
                $plat_money = $cash_item * $diff;
                $this->mem_model->where("id='10000'")->setInc("balance", $plat_money);
                $this->mem_model->where("id='10000'")->setInc("total_balance", $plat_money);
                $member_plat = $this->mem_model->where("id='10000'")->find();
                $after_money = $member_plat['balance'];
                mz_flow(10000, "", 5, "+".$plat_money, "提现平台收回 ".$diff."%", $after_money);
                
            } else {
                $diff = 10;
                $plat_money = $cash_item * $diff;
                #全部平台
                $this->mem_model->where("id='10000'")->setInc("balance", $plat_money);
                $this->mem_model->where("id='10000'")->setInc("total_balance", $plat_money);
                $member_plat = $this->mem_model->where("id='10000'")->find();
                $after_money = $member_plat['balance'];
                mz_flow(10000, "", 5, "+".$plat_money, "提现平台收回 ".$diff."%", $after_money);
            }
            
            #日志
            $this->helper->insLog($this->moduleid, 'iscash', session('aid'), session('username'), $map['id']);
            return ['code'=>1,'msg'=>'成功！'];
        } else {
            return ['code'=>0,'msg'=>'失败！'];
        }
        
    }
    
    #递归求链
    public function recurrence($uid, &$result=array()) {
        $this->membership_model = db("Membership");
        $this->mem_model = db("Members");
        $uinfo = $this->membership_model->where("uid='{$uid}'")->find();
        if ($uinfo['parentid']) {
            $tmp = array();
            $level = $this->mem_model->where("id='{$uinfo['parentid']}'")->column("level");
            $tmp['uid'] = $uinfo['parentid'];
            $tmp['level'] = $level[0];
            $result[] = $tmp;
            $this->recurrence($uinfo['parentid'],$result);
        }
        return $result;
    }
    
}