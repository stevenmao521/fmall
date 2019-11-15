<?php
namespace app\bee\service;
use think\Model;
use app\bee\model\Members;

class HelperDao{
    
    protected $sms;
    protected $members;


    #自定义初始化
    public function __construct()
    {
        $this->sms = db('smscode');
        $this->members = db('members');
    }
    
    #验证码检测
    public function checkCode($mobile, $code, $type)
    {
        $smscode = $this->sms->where("mobile='{$mobile}' and type='{$type}'")->find();
        
        if ($smscode['code'] != $code) {
            return false;
        } else {
            
            #是否过期
            $this->sms->where("id='{$smscode['id']}'")->delete();
            $diff = time() - $smscode['createtime'];
            if ($diff >= 300) {
                return false;
            } 
            return true;
        }
    }
    
    #token检测
    public function checkToken($token)
    {
        $member = $this->members->where("token='{$token}'")->find();
        if (!$member) {
            return false;
        } else {
            return $member;
        }
    }
    
    #二维码动态生成
    public function scerweima($data) {
        \think\Loader::import('phpqrcode.phpqrcode');
        // 纠错级别：L、M、Q、H 
        $level = 'L'; 
        // 点的大小：1到10,用于手机端4就可以了 
        $size = 7; 
        // 下面注释了把二维码图片保存到本地的代码,如果要保存图片,用$fileName替换第二个参数false 
        //$path = "images/"; 
        // 生成的文件名 
        //$fileName = $path.$size.'.png'; 
        \QRcode::png($data, false, $level, $size);
    }
    
    #角色
    public function getRole($list) {
        if ($list) {
            foreach ($list as $k=>$v) {
                switch ($v['role_id']) {
                    case 1:
                        $list[$k]['role'] = '商户';
                        break;
                    case 2:
                        $list[$k]['role'] = '员工';
                        break;
                    case 3:
                        $list[$k]['role'] = '连锁总部';
                        break;
                }
            }
        }
        return $list;
    }

}
