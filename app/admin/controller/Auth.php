<?php
namespace app\admin\controller;
use think\Db;
use clt\Leftnav;
use app\admin\model\Admin;
use app\admin\model\AuthGroup;
use app\admin\model\authRule;
use think\Validate;
class Auth extends Common
{
    //管理员列表
    public function adminList(){
        if(request()->isPost()){
            $val=input('val');
            $url['val'] = $val;
            $this->assign('testval',$val);
            $map='';
            if($val){
                $map['username|email|tel']= array('like',"%".$val."%");
            }
            /*
            if (session('aid')!=1){
                $map['admin_id']=session('aid');
            }
            */
            if (session('aid')!=1) {
                $list=Db::table(config('database.prefix').'admin')->alias('a')
                ->join(config('database.prefix').'auth_group ag','a.group_id = ag.group_id','left')
                ->field('a.*,ag.title')
                ->where($map)
                ->where("admin_id not in(1)")
                ->select();
            } else {
                $list=Db::table(config('database.prefix').'admin')->alias('a')
                ->join(config('database.prefix').'auth_group ag','a.group_id = ag.group_id','left')
                ->field('a.*,ag.title')
                ->where($map)
                ->select();
            }
            return $result = ['code'=>0,'msg'=>'获取成功!','data'=>$list,'rel'=>1];
        }
        return view();
    }

    public function adminAdd(){
        if(request()->isPost()){
            $data = input('post.');
            $check_user = Admin::get(['username'=>$data['username']]);
            if ($check_user) {
                return $result = ['code'=>0,'msg'=>'用户已存在，请重新输入用户名!'];
            }
            $data['pwd'] = input('post.pwd', '', 'md5');
            $data['add_time'] = time();
            $data['ip'] = request()->ip();
            //验证
            $msg = $this->validate($data,'Admin');
            if($msg!='true'){
                return $result = ['code'=>0,'msg'=>$msg];
            }
            //单独验证密码
            $checkPwd = Validate::is(input('post.pwd'),'require');
            if (false === $checkPwd) {
                return $result = ['code'=>0,'msg'=>'密码不能为空！'];
            }
            //添加
            if (Admin::create($data)) {
                return ['code'=>1,'msg'=>'管理员添加成功!','url'=>url('adminList')];
            } else {
                return ['code'=>0,'msg'=>'管理员添加失败!'];
            }
        }else{
            //$auth_group=db('auth_group')->select();
            $auth_group=db('auth_group')->where("group_id not in(1)")->select();
            $this->assign('authGroup',json_encode($auth_group,true));
            $this->assign('title',lang('add').lang('admin'));
            $this->assign('info','null');
            $this->assign('selected', 'null');
            return view('adminForm');
        }
    }
    //删除管理员
    public function adminDel(){
        $admin_id=input('post.admin_id');
        if (session('aid')==1 or session('aid')==9){
            Admin::destroy(['admin_id'=>$admin_id]);
            return $result = ['code'=>1,'msg'=>'删除成功!'];
        }else{
            return $result = ['code'=>0,'msg'=>'您没有删除管理员的权限!'];
        }
    }
    //修改管理员状态
    public function adminState(){
        $id=input('post.id');
        if (empty($id)){
            $result['status'] = 0;
            $result['info'] = '用户ID不存在!';
            $result['url'] = url('adminList');
            exit;
        }
        $status=db('admin')->where('admin_id='.$id)->value('is_open');//判断当前状态情况
        if($status==1){
            $data['is_open'] = 0;
            db('admin')->where('admin_id='.$id)->update($data);
            $result['status'] = 1;
            $result['open'] = 0;
        }else{
            $data['is_open'] = 1;
            db('admin')->where('admin_id='.$id)->update($data);
            $result['status'] = 1;
            $result['open'] = 1;
        }
        return $result;
    }
    //更新管理员信息
    public function adminEdit(){
        if(request()->isPost()){
            $data = input('post.');
            $pwd=input('post.pwd');

            $map['admin_id'] = array('neq',input('post.admin_id'));
            $where['admin_id'] = input('post.admin_id');
            if(input('post.username')){
                $map['username'] = input('post.username');
                $check_user = Admin::get($map);
                if ($check_user) {
                    return $result = ['code'=>0,'msg'=>'用户已存在，请重新输入用户名!'];
                }
            }
            if ($pwd){
                $data['pwd']=input('post.pwd','','md5');
            }else{
                unset($data['pwd']);
            }
            $msg = $this->validate($data,'Admin');
            if($msg!='true'){
                return $result = ['code'=>0,'msg'=>$msg];
            }
            Admin::update($data);
            return $result = ['code'=>1,'msg'=>'管理员修改成功!','url'=>url('adminList')];
        }else{
            //$auth_group = AuthGroup::all();
            $auth_group=db('auth_group')->where("group_id not in(1)")->select();
            $info = Admin::get(['admin_id'=>input('admin_id')]);
            $selected = db('auth_group')->where('group_id',$info['group_id'])->find();
            $this->assign('selected',json_encode($selected,true));
            $this->assign('info', $info->toJson());
            $this->assign('authGroup',json_encode($auth_group,true));
            $this->assign('title',lang('edit').lang('admin'));
            return view('adminForm');
        }
    }
    /*-----------------------用户组管理----------------------*/
    //用户组管理
    public function adminGroup(){
        if(request()->isPost()){
            if (session('aid') != 1) {
                $list = db('auth_group')->where("group_id not in(1)")->select();
                $list = mz_formattime($list, 'addtime', 1);
            } else {
                $list = AuthGroup::all();
            }
            return $result = ['code'=>0,'msg'=>'获取成功!','data'=>$list,'rel'=>1];
        }
        return view();
    }
    //删除管理员分组
    public function groupDel(){
        AuthGroup::destroy(['group_id'=>input('id')]);
        return $result = ['code'=>1,'msg'=>'删除成功!'];
    }
    //添加分组
    public function groupAdd(){
        if(request()->isPost()){
            $data=input('post.');
            $data['addtime']=time();
            AuthGroup::create($data);
            $result['msg'] = '用户组添加成功!';
            $result['url'] = url('adminGroup');
            $result['code'] = 1;
            return $result;
        }else{
            $this->assign('title','添加用户组');
            $this->assign('info','null');
            return $this->fetch('groupForm');
        }
    }
    //修改分组
    public function groupEdit(){
        if(request()->isPost()) {
            $data=input('post.');
            AuthGroup::update($data);
            $result = ['code'=>1,'msg'=>'用户组修改成功!','url'=>url('adminGroup')];
            return $result;
        }else{
            $id = input('id');
            $info = AuthGroup::get(['group_id'=>$id]);
            $this->assign('info', json_encode($info,true));
            $this->assign('title','编辑用户组');
            return $this->fetch('groupForm');
        }
    }
    //分组配置规则
    public function groupAccess(){
        $nav = new Leftnav();
        $rules = db('auth_group')->where('group_id',input('id'))->value('rules');
        $rules_oa = db('auth_group')->where('group_id',4)->value('rules');
        $in_str = substr($rules_oa,0,-1);
        $admin_id = session("aid");
        if ($admin_id != 1) {
            $admin_rule=db('auth_rule')->where(" id IN ({$in_str}) ")->field('id,pid,title')->order('sort asc')->select();
        } else {
            $admin_rule=db('auth_rule')->field('id,pid,title')->order('sort asc')->select();
        }
        
        $arr = $nav->auth($admin_rule,$pid=0,$rules);
        $arr[] = array(
            "id"=>0,
            "pid"=>0,
            "title"=>"全部",
            "open"=>true
        );
        $this->assign('data',json_encode($arr,true));
        return $this->fetch();
    }
    public function groupSetaccess(){
        $rules = input('post.rules');
        if(empty($rules)){
            return array('msg'=>'请选择权限!','code'=>0);
        }
        $data = input('post.');
        if(AuthGroup::update($data)){
            return array('msg'=>'权限配置成功!','url'=>url('adminGroup'),'code'=>1);
        }else{
            return array('msg'=>'保存错误','code'=>0);
        }
    }

    /********************************权限管理*******************************/
    public function adminRule(){
        if(request()->isPost()){
            $nav = new Leftnav();
            $arr = cache('authRuleList');
            if(!$arr){
                $authRule = authRule::all(function($query){
                    $query->order('sort', 'asc');
                });
                $arr = $nav->menu($authRule);
                cache('authRuleList', $arr, 3600);
            }
            return $result = ['code'=>0,'msg'=>'获取成功!','data'=>$arr,'rel'=>1];
        }
        return view();
    }
    public function ruleAdd(){
        if(request()->isPost()){
            $data = input('post.');
            $data['addtime'] = time();
            authRule::create($data);
            cache('authRule', NULL);
            cache('authRuleList', NULL);
            return $result = ['code'=>1,'msg'=>'权限添加成功!','url'=>url('adminRule')];
        }else{
            $nav = new Leftnav();
            $arr = cache('authRuleList');
            if(!$arr){
                $authRule = authRule::all(function($query){
                    $query->order('sort', 'asc');
                });
                $arr = $nav->menu($authRule);
                cache('authRuleList', $arr, 3600);
            }
            $this->assign('admin_rule',$arr);//权限列表
            return $this->fetch();
        }
    }
    public function ruleOrder(){
        $auth_rule=db('auth_rule');
        $data = input('post.');
        if($auth_rule->update($data)!==false){
            cache('authRuleList', NULL);
            cache('authRule', NULL);
            return $result = ['code'=>1,'msg'=>'排序更新成功!','url'=>url('adminRule')];
        }else{
            return $result = ['code'=>0,'msg'=>'排序更新失败!'];
        }
    }
    public function ruleState(){
        $id=input('post.id');
        $statusone=db('auth_rule')->where(array('id'=>$id))->value('menustatus');//判断当前状态情况
        cache('authRule', NULL);
        cache('authRuleList', NULL);
        if($statusone==1){
            $statedata = array('menustatus'=>0);
            db('auth_rule')->where(array('id'=>$id))->setField($statedata);
            $result['status'] = 1;
            $result['menustatus'] = 0;
        }else{
            $statedata = array('menustatus'=>1);
            db('auth_rule')->where(array('id'=>$id))->setField($statedata);
            $result['status'] = 1;
            $result['menustatus'] = 1;
        }
        return $result;
    }
    public function ruleTz(){
        $id=input('post.id');
        $statusone=db('auth_rule')->where(array('id'=>$id))->value('authopen');//判断当前状态情况
        cache('authRule', NULL);
        cache('authRuleList', NULL);
        if($statusone==1){
            $statedata = array('authopen'=>0);
            db('auth_rule')->where(array('id'=>$id))->setField($statedata);
            $result['status'] = 1;
            $result['authopen'] = 0;
        }else{
            $statedata = array('authopen'=>1);
            db('auth_rule')->where(array('id'=>$id))->setField($statedata);
            $result['status'] = 1;
            $result['authopen'] = 1;
        }
        return $result;
    }

    public function ruleDel(){
        //authRule::destroy(['id'=>input('param.id')]);
        #递归删除
        $this->ruleDelRec(input('param.id'));
        cache('authRule', NULL);
        cache('authRuleList', NULL);
        return $result = ['code'=>1,'msg'=>'删除成功!'];
    }
    
    public function ruleDelRec($id){
        $rule_list = db('auth_rule')->where("pid='{$id}'")->select();
        if ($rule_list) {
            authRule::destroy(['id'=>$id]);
            foreach ($rule_list as $k=>$v) {
                $this->ruleDelRec($v['id']);
            }
        } else {
            authRule::destroy(['id'=>$id]);
        }
    }
    
    public function ruleAct() {
        $id=input('post.id');
        $mod = db("auth_rule");
        $info = $mod->where("id='{$id}'")->value('href');
        
        $arr = explode("/",$info);
        $key = $arr[1];
        
        $sql = "INSERT INTO `clt_auth_rule` (`href`, `title`, `type`, `status`, `authopen`, `icon`, `condition`, `pid`, `sort`, `addtime`, `zt`, `menustatus`) VALUES
        ('xq/{$key}/index', '列表', 1, 1, 0, '', '', {$id}, 0, 1519622406, NULL, 1),
        ('xq/{$key}/add', '添加', 1, 1, 0, '', '', {$id}, 1, 1519622406, NULL, 1),
        ('xq/{$key}/edit', '修改', 1, 1, 0, '', '', {$id}, 2, 1519622406, NULL, 1),
        ('xq/{$key}/listDel', '删除', 1, 1, 0, '', '', {$id}, 3, 1519622406, NULL, 1),
        ('xq/{$key}/listBack', '还原', 1, 1, 0, '', '', {$id}, 4, 1519622406, NULL, 1),
        ('xq/{$key}/listRush', '彻底删除', 1, 1, 0, '', '', {$id}, 5, 1519622406, NULL, 1),
        ('xq/{$key}/delAll', '全部删除', 1, 1, 0, '', '', {$id}, 6, 1519622406, NULL, 1),
        ('xq/{$key}/backAll', '全部还原', 1, 1, 0, '', '', {$id}, 7, 1519622406, NULL, 1),
        ('xq/{$key}/rushAll', '全部彻底删除', 1, 1, 0, '', '', {$id}, 8, 1519622406, NULL, 1),
        ('xq/{$key}/trash', '回收站', 1, 1, 0, '', '', {$id}, 9, 1519622406, NULL, 1),
        ('xq/{$key}/log', '操作日志', 1, 1, 0, '', '', {$id}, 10, 1519622406, NULL, 1);";
        
        Db::execute($sql);
        return $result = ['code'=>1,'msg'=>'生成成功!'];
    }
    

    public function ruleEdit(){
        if(request()->isPost()) {
            $datas = input('post.');
            if(authRule::update($datas)) {
                cache('authRule', NULL);
                cache('authRuleList', NULL);
                return json(['code' => 1, 'msg' => '保存成功!', 'url' => url('adminRule')]);
            } else {
                return json(['code' => 0, 'msg' =>'保存失败！']);
            }
        }else{
            $admin_rule = authRule::get(function($query){
                $query->where(['id'=>input('id')])->field('id,href,title,icon,sort,menustatus');
            });
            $this->assign('rule',$admin_rule);
            return $this->fetch();
        }
    }
}