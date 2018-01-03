<?php

namespace Warehouse\Controller;

use Common\Controller\AdminbaseController;

/**
 * 仓库模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */
class PrintController extends AdminbaseController {
    protected $order, $page;
    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->page = $_SESSION['set_page_row'] ? (int) $_SESSION['set_page_row'] : 20;
    }
    public function barcode(){
        $this->display();
    }
}
