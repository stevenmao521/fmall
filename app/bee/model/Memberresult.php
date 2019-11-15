<?php
namespace app\bee\model;
use think\Model;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
/**
 * Description of Members
 *
 * @author stevenmao521
 */
class Memberresult extends Model{
    protected $table = 'clt_memberresult';
    protected $pk = 'id';
    
    //自定义初始化
    protected function initialize()
    {
        parent::initialize();
    }
}
