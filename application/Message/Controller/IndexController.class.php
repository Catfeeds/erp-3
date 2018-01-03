<?php
namespace Message\Controller;
use Common\Controller\AdminbaseController;

/**
 * 部门模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Message\Controller
 */
class IndexController extends AdminbaseController{
	protected $Message;
	public function _initialize() {
		parent::_initialize();
		$this->Message=D("Common/Message");
	}
	public function index(){
		$list_count = $this->Message->count();
		$page = $this->page($list_count,20);
        $list = $this->Message->limit($page->firstRow,$page->listRows)->select();
        $this->assign("list",$list);
		$this->assign('page',$page->show('Admin'));
		$this->display();
	}
}