<?php
namespace Shipping\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;

class LableController extends AdminbaseController {

    protected $shipping;

    public function _initialize() {
        parent::_initialize();
        $this->shipping = D("Common/Shipping");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /*
     * 设置面单
     */
    public function index(){
//        var_dump($_POST);die;
        $temp = M('WaybillTemplate')->select();
        $temp = array_column($temp,'title','id');
        if(IS_POST){
            $res =M('WaybillTemplate')->save($_POST);
            if ($res == false) {
                add_system_record(sp_get_current_admin_id(), 1, 1, '添加面单失败');
                $this->error("添加面单失败！", U('Shipping/Lable/index'));
            }
            else{
                add_system_record(sp_get_current_admin_id(), 1, 1, '添加面单成功');
                $this->success("添加面单成功！", U('Shipping/Lable/index'));
            }
        }
        $this->assign('temp',$temp);
        $this->display();
    }
}
