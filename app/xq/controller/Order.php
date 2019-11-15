<?php
namespace app\xq\controller;
use think\Db;
use think\Request;
use think\Controller;
use clt\Form;//表单
use app\xq\controller\Helper as Helper;//工具类

class Order extends Common{
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
        
        $this->controller = "order";
        $this->modname = "订单";
        
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
    
	public function index()
    {
        if (request()->isPost()) {
            #筛选字段
            $post = input("post.");
            $sel_map = $this->helper->getMap($post, $this->moduleid);

            #列表
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

			$haspay = $post['haspay'];
			if (isset($haspay) && $haspay == 0) {
				$whereother = " haspay=0 ";
			} else {
				$whereother = " haspay=1 ";
			}
			
			
            $list = $this->dao
                    ->where($sel_map)
                    ->where("istrash=0")
					->where($whereother)
                    ->order('id desc')
                    ->paginate(array('list_rows' => $pageSize, 'page' => $page))
                    ->toArray();

            #时间转换
            $lfields = $this->lfields;
            if ($lfields) {
                foreach ($lfields as $k => $v) {
                    if ($v['type'] == 'datetime') {
                        $list['data'] = mz_formattime($list['data'], $v['field'], 1);
                    }
                }
            }

            #统计项 获取统计字段
            $count = $this->helper->getCountField($this->moduleid);
            if ($count['fields']) {
                $sum = array();
                foreach ($count['fields'] as $k => $v) {
                    $sum_total = $this->dao
                            ->where($sel_map)
                            ->sum($v['field']);
                    $sum[$v['field']] = $sum_total;
                }
            }
            return $result = ['code' => 0, 'msg' => '获取成功!', 'data' => $list['data'], 'count' => $list['total'], 'rel' => 1, 'sum' => $sum];
        }
        #列表字段
        $list_str = $this->helper->getlistField($this->moduleid);
        #筛选html
        $sel_html = $this->helper->getSelField($this->moduleid);
        #获取统计字段
        $count = $this->helper->getCountField($this->moduleid);

        #模版渲染
        return $this->fetch('', [
            'js_str' => $list_str['js_str'],
            'js_tmp' => $list_str['js_tmp'],
            'html_str' => $sel_html['html_str'],
            'js_val' => $sel_html['js_val'],
            'js_where' => $sel_html['js_where'],
            'js_date' => $sel_html['js_date'],
            'count_html1' => $count['html_1'],
            'count_html2' => $count['html_2'],
            'count_js' => $count['js'],
            'js_ewhere' => $sel_html['js_ewhere']
        ]);
    }
	
    #领用列表
    public function orderdetail(){
        #筛选字段
        $post = input("request.");
        $parentid = $post['id'];
        $ispost = input("ispost");
        $ispost2 = input("ispost2");
        
        #列表
        $page =input('page')?input('page'):1;
        $pageSize =input('limit')?input('limit'):config('pageSize');
        $list = db("orderdetail")
            ->where("istrash=0 and oid='{$parentid}'")
            ->order('id desc')
            ->paginate(array('list_rows'=>$pageSize,'page'=>$page))
            ->toArray();
            
        foreach ($list['data'] as $k=>$v) {
            $product = db("product")->where("id='{$v['product_id']}'")->find();
            $list['data'][$k]['pic'] = mz_pic($product['pics']);
        }
        
        #时间转换
        $lfields = $this->lfields;
        if ($lfields) {
            foreach ($lfields as $k=>$v) {
                if ($v['type'] == 'datetime') {
                    $list['data'] = mz_formattime($list['data'], $v['field'], 2);
                    
                }
            }
        }
        
        if ($ispost) {
            $update = array();
            $update['addrname'] = input("addrname");
            $update['addrmobile'] = input("addrmobile");
            $update['addrdetail'] = input("addrdetail");
            $update['express'] = input("express");
            $update['expresscode'] = input("expresscode");
            $res = db("order")->where("id='{$parentid}'")->update($update);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apisuc("修改失败");
            }
        }
        
        if ($ispost2) {
            $update = array();
            $update['remark'] = input("remark");
            $res = db("order")->where("id='{$parentid}'")->update($update);
            if ($res) {
                return mz_apisuc("修改成功");
            } else {
                return mz_apisuc("修改失败");
            }
        }
        
        $orderinfo = db("order")->where("id='{$parentid}'")->find();
        $orderinfo['status'] = mz_getstatus($orderinfo['status']);
        $orderinfo['createdate'] = date("Y-m-d H:i", $orderinfo['createtime']);
        $orderinfo['paydate'] = $orderinfo['paytime'] ? date("Y-m-d H:i", $orderinfo['paytime']) : '';
        $orderinfo['senddate'] = $orderinfo['sendtime'] ? date("Y-m-d H:i", $orderinfo['sendtime']) : '';
        $orderinfo['finishdate'] = $orderinfo['finishtime'] ? date("Y-m-d H:i", $orderinfo['finishtime']) : '';
        
        $member = db("members")->where("id='{$orderinfo['uid']}'")->find();
        
        $this->assign("member", $member);
        $this->assign("order", $orderinfo);
        $this->assign("id", $parentid);
        $this->assign("list", $list);
        return $this->fetch();
    }
    
    #发货
    public function isSend() {
        $map['id'] = input('post.id');
        #判断当前状态情况
        $info = $this->dao->where($map)->find();
        
        if ($info['haspay'] != 1) {
            return ['code'=>0,'msg'=>'此订单还未付款，不能发货！'];
        } else {
            if (!$info['express'] || !$info['expresscode']) {
                return ['code'=>0,'msg'=>'请再详情里编辑快递信息再发货'];
            }
        }
        
        if ($info['status'] != 4) {
            $data['issend'] = 2;
            $data['status'] = 3;
            $data['sendtime'] = time();
            #自动收货时间
            $data['autotime'] = time() + 3600*24*5;
            
            $r = $this->dao->where($map)->setField($data);
            if ($r) {
                #日志
                $this->helper->insLog($this->moduleid, 'issend', session('aid'), session('username'), $map['id']);
                return ['code'=>1,'msg'=>'成功！'];
            } else {
                return ['code'=>0,'msg'=>'失败！'];
            }
        } else {
            return ['code'=>0,'msg'=>'此订单已完成'];
        }
    }
    
    #订单失效
    public function isLose() {
        $map['id'] = input('post.id');
        #判断当前状态情况
        $info = $this->dao->where($map)->find();
        
        if ($info['status'] != 4) {
            $data['status'] = 2;
            $r = $this->dao->where($map)->setField($data);
            if ($r) {
                #日志
                $this->helper->insLog($this->moduleid, 'lose', session('aid'), session('username'), $map['id']);
                return ['code'=>1,'msg'=>'成功！'];
            } else {
                return ['code'=>0,'msg'=>'失败！'];
            }
        } else {
            return ['code'=>0,'msg'=>'此订单已完成'];
        }
    }
    
    #确认收货
    public function isFinish() {
        $map['id'] = input('post.id');
        $info = $this->dao->where($map)->find();
        
        $url = "http://fmall.yuntim.cn/bee-Api-doneapi";
        $params['uid'] = $info['uid'];
        $params['id'] = $info['id'];
        $res = mz_http_send($url, $params);
        $r_arr = json_decode($res,1);
        if ($r_arr['code'] == 1) {
            return mz_apisuc($r_arr['msg']);
        } else {
            return mz_apierror($r_arr['msg']);
        }
    }
    
    #打印发货单
    public function dayin() {
        $id = input("id");
        $uid = session('aid');
        $orderinfo = db("order")->where("id='{$id}'")->find();
        $admininfo = db("admin")->where("admin_id='{$uid}'")->find();
        
        $addressinfo = db("address")->where("id='{$orderinfo['addressid']}'")->find();
        $addr = explode(" ", $addressinfo['pro_city_reg']);
        $addressinfo['pro'] = $addr[0];
        $addressinfo['city'] = $addr[1];
        $addressinfo['area'] = $addr[2];
        
        $orderdetail = db("orderdetail")->where("oid='{$orderinfo['id']}'")->find();
        $product_name = db("product")->where("id='{$orderdetail['product_id']}'")->column("name");
        $product_name = $product_name[0];
        
        $this->assign("name", $product_name);
        $this->assign("orderdetail", $orderdetail);
        $this->assign("addr", $addressinfo);
        $this->assign("admin", $admininfo);
        $this->assign("order", $orderinfo);
        return $this->fetch();
    }
    
}