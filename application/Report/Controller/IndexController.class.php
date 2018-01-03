<?php
namespace Report\Controller;
use Common\Controller\AdminbaseController;

/**
 * 部门模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Report\Controller
 */
class IndexController extends AdminbaseController{
	protected $Report;
	public function _initialize() {
		parent::_initialize();
		$this->Report=D("Common/Report");
	}
	public function index(){
        $list = $this->Report->select();
        $this->assign("list",$list);
		$this->display();
	}
}