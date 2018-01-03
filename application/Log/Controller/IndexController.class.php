<?php
namespace Log\Controller;
use Common\Controller\AdminbaseController;

/**
 * 部门模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Log\Controller
 */
class IndexController extends AdminbaseController{
	protected $Log;
	public function _initialize() {
		parent::_initialize();
		$this->Log=D("Common/Log");
	}
	public function index(){
        $list = $this->Log->select();
        $this->assign("list",$list);
		$this->display();
	}
}