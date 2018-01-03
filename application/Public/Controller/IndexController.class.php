<?php
namespace Pulic\Controller;
use Common\Controller\AdminbaseController;

/**
 * 部门模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Pulic\Controller
 */
class IndexController extends AdminbaseController{
	public function _initialize() {
		parent::_initialize();
	}
	public function index(){
		$this->display();
	}
}