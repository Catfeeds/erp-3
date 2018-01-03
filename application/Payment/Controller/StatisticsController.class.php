<?php
namespace Payment\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;

/**
 * 订单统计
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */
class StatisticsController extends AdminbaseController{
	protected $order,$page;
	public function _initialize() {
		parent::_initialize();
		$this->order=D("Order/Order");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
	}
    public function every_day(){
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        $where['payment_method'] = array('NOT IN','0');
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time']) $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
            $where['created_at']= $create_at;
        }else{
            $where['id_order_status'] = array('GT',0);
        }
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }

        /*创建日期初始化*/
        $created_at_array = array();
        if ($_GET['start_time'] or $_GET['end_time'])
        {
            if ($_GET['start_time'])
            {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ($_GET['end_time'])
            {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
        }
        else
        {
            if (!$_GET['start_time'] && !$_GET['end_time'])
            {
                $get_data['start_time'] = $_GET['start_time'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time'] = $_GET['end_time'] = date('Y-m-d H:i',time());
                $created_at_array[] = array('EGT', $get_data['start_time']);
                $created_at_array[] = array('LT', $get_data['end_time']);
            }
        }
        $where['created_at'] = $created_at_array;
        $this->assign("start_time",$get_data['start_time']);
        $this->assign("end_time",$get_data['end_time']);

        $effective_status = OrderStatus::get_effective_status();
        //每日统计将待审核作为有效单
        array_push($effective_status, OrderStatus::VERIFICATION);

        $field = "SUBSTRING(created_at,1,10) AS set_date,SUM(IF(`id_order_status` IN(".implode(',', $effective_status)."),1,0)) as effective,
        count(id_order) as total,
        SUM(IF(`id_order_status` IN(10,11,12,13,14,15),1,0)) as invalid,
        SUM(IF(`id_order_status`=1,1,0)) AS status1,SUM(IF(`id_order_status`=2,1,0)) AS status2,
        SUM(IF(`id_order_status`=3,1,0)) AS status3,SUM(IF(`id_order_status`=4,1,0)) AS status4,
        SUM(IF(`id_order_status`=5,1,0)) AS status5,SUM(IF(`id_order_status`=6,1,0)) AS status6,
        SUM(IF(`id_order_status`=7,1,0)) AS status7,SUM(IF(`id_order_status`=8,1,0)) AS status8,
        SUM(IF(`id_order_status`=9,1,0)) AS status9,
        SUM(IF(`id_order_status`=10,1,0)) AS status10,SUM(IF(`id_order_status`=11,1,0)) AS status11,
        SUM(IF(`id_order_status`=12,1,0)) AS status12,SUM(IF(`id_order_status`=13,1,0)) AS status13,
        SUM(IF(`id_order_status`=14,1,0)) AS status14,SUM(IF(`id_order_status`=15,1,0)) AS status15
        ";
        $count = $ordModel->field($field)->where($where)
            ->order('set_date desc')
            ->group('set_date')->select();
        $page = $this->page(count($count), 20);
        $selectOrder = $ordModel->field($field)->where($where)->order('set_date desc')
            ->group('set_date')->limit($page->firstRow . ',' . $page->listRows)->select();

        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看TF订单统计');
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("list",$selectOrder);
        //$this->assign("shipping",$shipping);
        $this->assign("page",$page->show('Admin'));
        $this->display();
    }
}
