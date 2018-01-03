<?php

namespace Order\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use Order\Model\UpdateStatusModel;
use Order\Repository\WlRepositoryController;
header("COntent-Type:text/html;charset=utf-8;");
/**
 * 订单管理模块
 *
 * @Author  morrowind
 * @qq      752979972
 * Class IndexController
 * @package Order\Controller
 */
class IndexController extends AdminbaseController
{

    protected $order, $page;

    public function _initialize ()
    {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->page = $_SESSION['set_page_row'] ? (int)$_SESSION['set_page_row'] : 20;
    }

    /**
     * 订单列表
     */
    public function index ()
    {


        /** @var \Order\Model\OrderModel $order_model */
        $order_model = $this->order;
        /*创建日期初始化*/
        $created_at_array = array();
        if ( $_GET['isajax'] == 1 ) {//ajax结算统计信息
            $ajaxdata = [];
            if ( $_GET['data'] ) {
                foreach ($_GET['data'] as $dataval) {
                    $ajaxdata[$dataval['name']] = $dataval['value'];
                }
                $_GET = array_merge($_GET, $ajaxdata);
            }
        }
        if(isset($_POST['symbol']) && $_POST['symbol'] == 'reviews'){
            $idOrder = $_POST['id_order'];
            $data['aftermarket_reviews'] = $_POST['review'];
            $ret = $this->order->where("id_order=".$idOrder)->save($data);
            // echo $this->order->getLastSql();die;
            if($ret){
              $return = array('status'=>1,'msg'=>'编辑成功！');
            }else{
                $return = array('status'=>0,'msg'=>'编辑失败！');
            }
            echo json_encode($return);
            exit;
        }

        if ( $_GET['start_time'] or $_GET['end_time'] ) {
            if ( $_GET['start_time'] ) {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ( $_GET['end_time'] ) {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
            $set_where['created_at'] = $created_at_array;
        }
        $where = $order_model->form_where($_GET, 'o.');
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        //默认只显示当前有权限的第一个部门 jiangqinqing 20171116
        if($_GET['id_department'] == 1000){
            $where['o.id_department'] = array('IN', $department_id);
        }else{
            $where['o.id_department'] = $_GET['id_department'] ? array('EQ', $_GET['id_department']) : array('EQ', $department_id[0]);
        }
        
        //获取当前用户拥有的部门信息  用于减少后面联表查询 jiangqinqing 20171204
        $department_id = $_SESSION['department_id'];
        $department = D('Department/Department')->where('type=1')->cache(true, 3600)->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
 
    /*
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id' => $_SESSION['ADMIN_ID'], 'role_id' => 32))->find();
        if ( $role_user ) {
            if ( $_REQUEST['zone_id'] ) {
                $where['o.id_zone'] = array('EQ', $_REQUEST['zone_id']);
            }
            $belong_zone_id = isset($_SESSION['belong_zone_id']) ? $_SESSION['belong_zone_id'] : array(0);
            if ( !isset($where['o.id_zone']) ) {
                $where['o.id_zone'] = array('IN', $belong_zone_id);
            }
        } */
        //根据筛选的地区进行过滤,没选择则默认所有区域 jiangqinqing  
        if(!empty($_REQUEST['zone_id']) ){
           $where['o.id_zone'] = array('EQ', $_REQUEST['zone_id']); 
        }

        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();
        $form_data['domain_address'] = $domain_model->get_all_real_address();
        if ( isset($_GET['status_id']) && $_GET['status_id'] ) {
            if ( $_GET['status_id'] == 11 ) {
                $where['o.order_repeat'] = array("GT", 1);
            } else {
                $where['o.id_order_status'] = $_GET['status_id'];
            }
            if (I('get.status_id') == '5,7,18') {
                $where['o.id_order_status'] =['IN', I('get.status_id')];
            }elseif(I('get.status_id') == 100){ //有效订单 zx 11/15
                $where['o.id_order_status'] =['IN', ('4,5,6,7,8,9,10,16,17,18,19,21,22,23,24,25,26,27')];
            }elseif(I('get.status_id') == 101){ //无效订单 zx 11/15
                $where['o.id_order_status'] =['IN',('11,12,13,14,15,28,29,30')];
            }
        }

        //$formData['product_type'] = $baseSql->getFieldGroupData('product_type');
        $form_data['track_status'] = D('Order/OrderShipping')->field('status_label as track_status')
            ->where("status_label is not null or status_label !='' ")
            ->group('status_label')->cache(true, 12000)->select();

        $today_where = $where;
        $today_where['o.created_at'] = array('EGT', $today_date);
        //今日统计订单 条件
        if ( $_GET['isajax'] == 1 ) {
            $return = array('status' => 0, 'msg' => '');
            $domainTable = $domain_model->getTableName();
            $all_domain_total = $order_model->alias('o')->join("{$domainTable} d on d.id_domain=o.id_domain")->field('d.name,count(o.id_domain) as total')->where($today_where)
                ->order('total desc')->group('o.id_domain')->select();
            $return = array('status' => 1, 'msg' => $all_domain_total);
            echo json_encode($return);
            exit();
        }

        //筛选ip
        if ( isset($_GET['ip']) && !empty($_GET['ip']) ) {
            $find_order = D('Order/OrderInfo')->field('id_order')->where(array('ip' => trim($_GET['ip'])))->select();
            if ( !empty($find_order) ) {
                $where['o.id_order'] = array('IN', array_column($find_order, 'id_order'));
            } else {
                $where['o.id_order'] = 0;
            }
        }
        //筛选物流
        if ( isset($_GET['id_shipping']) && $_GET['id_shipping'] ) {
            $where['o.id_shipping'] = $_GET['id_shipping'];
        }
        if ( isset($_GET['action']) && $_GET['action'] == 'repeat' ) {
            $id_order = $_GET['id_order'];
            $countOrder = D("order/order")->where("id_order=" . $id_order)->field("tel,first_name")->find();
            $where['_string'] = "(o.tel=" . $countOrder['tel'] . " OR o.first_name='" . $countOrder['first_name'] . "')";
        } else {
            $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
        }
        //添加"发货时间"筛选条件,可选范围     liuruibin   20171023
        if ( $_GET['startdate_delivery'] or $_GET['enddate_delivery'] ) {
            if ( $_GET['startdate_delivery'] ) {
                $date_delivery_array[] = array('EGT', $_GET['startdate_delivery']);
            }
            if ( $_GET['enddate_delivery'] ) {
                $date_delivery_array[] = array('LT', $_GET['enddate_delivery']);
            }
            $where['o.date_delivery'] = $date_delivery_array;
        }
        if(isset($_GET['sku']) && $_GET['sku']){
            $where['oit.sku'] = array("EQ",addslashes(trim($_GET['sku'])));
        }
        if(isset($_GET['inner_name']) && $_GET['inner_name']){
            $where['pp.inner_name'] = array("LIKE",'%'.addslashes(trim($_GET['inner_name'])).'%');
        }
        //修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
        if ( isset($_GET['status_label']) && $_GET['status_label'] ) {
            $where['s.status_label'] = strip_tags(trim($_GET['status_label']));
            $count = $order_model->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->join("__ORDER_ITEM__ oit On oit.id_order=o.id_order","LEFT")//增加sku 和 内部名的搜索  --Lily 2017-11-09
                ->join("__PRODUCT__ pp ON oit.id_product=pp.id_product","LEFT")
                ->where($where)->count("DISTINCT o.id_order");
            $today_total = $order_model->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->where($today_where)->count("DISTINCT o.id_order");
            $page = $this->page($count, $this->page);
            $order_list = $order_model->alias('o')->field('DISTINCT o.*,s.date_signed,oi.ip as ip,s.date_online')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->join('__ORDER_INFO__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                //->join('__DEPARTMENT__ dt ON (o.id_department = dt.id_department)', 'LEFT')
                ->join("__ORDER_ITEM__ oit On oit.id_order=o.id_order","LEFT") //增加sku 和 内部名的搜索  --Lily 2017-11-09
                ->join("__PRODUCT__ pp ON oit.id_product=pp.id_product","LEFT")
                ->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        } else {
            $count = $order_model->alias('o')
                ->join("__ORDER_ITEM__ oit On oit.id_order=o.id_order","LEFT")
                ->join("__PRODUCT__ pp ON oit.id_product=pp.id_product","LEFT")
                ->where($where)->count("DISTINCT o.id_order");//增加sku 和 内部名的搜索  --Lily 2017-11-09
            $today_total = $order_model->alias('o')
                ->join("__ORDER_ITEM__ oit On oit.id_order=o.id_order","LEFT") //增加sku 和 内部名的搜索  --Lily 2017-11-09
                ->join("__PRODUCT__ pp ON oit.id_product=pp.id_product","LEFT")
                ->where($today_where)->count("DISTINCT o.id_order");
            $page = $this->page($count, $this->page);
            $order_list = $order_model->alias('o')->field('DISTINCT o.*,oi.ip as ip,s.date_online')
                ->join('__ORDER_INFO__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                //->join('__DEPARTMENT__ dt ON (o.id_department = dt.id_department)', 'LEFT')
                ->join("__ORDER_ITEM__ oit On oit.id_order=o.id_order","LEFT") //增加sku 和 内部名的搜索  --Lily 2017-11-09
                ->join("__PRODUCT__ pp ON oit.id_product=pp.id_product","LEFT")
                ->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');

        //性能优化,减少循环中嵌套查询,根据订单ID获取产品
        $id_orders = array_column($order_list,"id_order");
        $order_product = $order_item->get_item_list($id_orders);
        $shipping = M('Shipping')->field('id_shipping,title')->where('status=1')->select();
        $ordershipping = array_column($shipping,'title','id_shipping');   

        foreach ($order_list as $key => $o) {
            $order_list[$key] = D("Order/OrderBlacklist")->black_list_and_ip_address($o);
           // $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['products'] = $order_product[$o['id_order']];
            $order_list[$key]['dt_title'] = $department[$o['id_department']];  
            $order_list[$key]['shipping_name'] = $ordershipping[$o['id_shipping']];
            $order_list[$key]['date_delivery'] = empty($o['date_delivery']) ? '' : date("Y-m-d",strtotime($o['date_delivery']));                        
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'], $o['currency_code']);
            $order_list[$key]['http_referer'] = !empty($o['http_referer']) ? $o['http_referer'] : '--';
        }
 
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true, 36000)->select();
        $advertiser = array_column($advertiser, 'name', 'id');
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();

        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        add_system_record($_SESSION['ADMIN_ID'], 4, 4, '查看DF订单列表');
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id' => $_SESSION['ADMIN_ID'], 'role_id' => 32))->find();
        if ( $role_user ) {
            $belong_zone_id = isset($_SESSION['belong_zone_id']) ? $_SESSION['belong_zone_id'] : array(0);
            if ( !empty($belong_zone_id) ) {
                $all_zone = $zone_model->field('`title`,id_zone')->where(['id_zone' => array('IN', $belong_zone_id)])->order('`title` ASC')->select();
                $all_zone = $all_zone ? array_column($all_zone, 'title', 'id_zone') : '';
            }

        } else {
            $all_zone = $zone_model->all_zone();
        }
         
        $this->assign("all_zone", $all_zone);
        $this->assign("shipping", $shipping);

        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("selectdepartment", empty($_GET['id_department'])?$department_id[0]:intval($_GET['id_department']));
        $this->assign("advertiser", $advertiser);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("page", $page->show('Admin'));
        $this->assign("today_total", $today_total);
        $this->assign("order_total", $count);
        $this->assign("all_domain_total", $all_domain_total);
        $this->assign("warehouse", $warehouse);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        $this->assign('status_list', $status_model->get_status_label());
        $this->assign("order_list", $order_list);
        $this->display();
    }
    /**
     * 订单列表(电话邮箱)
     */
    public function index_two ()
    {
        /** @var \Order\Model\OrderModel $order_model */
        $order_model = $this->order;
        /*创建日期初始化*/
        $created_at_array = array();
        if ( $_GET['start_time'] or $_GET['end_time'] ) {
            if ( $_GET['start_time'] ) {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ( $_GET['end_time'] ) {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
            $set_where['created_at'] = $created_at_array;
        }
        $where = $order_model->form_where($_GET, 'o.');
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);

        //默认只显示当前有权限的第一个部门 jiangqinqing 20171116
        if($_GET['id_department'] == 1000){
            $where['o.id_department'] = array('IN', $department_id);
        }else{
            $where['o.id_department'] = $_GET['id_department'] ? array('EQ', $_GET['id_department']) : array('EQ', $department_id[0]);
        }

        $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();
        $form_data['domain_address'] = $domain_model->get_all_real_address();

        //$formData['product_type'] = $baseSql->getFieldGroupData('product_type');
        $form_data['track_status'] = D('Order/OrderShipping')->field('status_label as track_status')
            ->where("status_label is not null or status_label !='' ")
            ->group('status_label')->cache(true, 12000)->select();


        //今日统计订单 条件
        $today_where = $where;
        $today_where['o.created_at'] = array('EGT', $today_date);
        $all_domain_total = $order_model->alias('o')->field('count(`id_domain`) as total,id_domain')->where($today_where)
            ->order('total desc')->group('id_domain')->select();

        //修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
        if ( isset($_GET['status_label']) && $_GET['status_label'] ) {
            $where['s.status_label'] = strip_tags(trim($_GET['status_label']));
            $count = $order_model->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->where($where)->count();
            $today_total = $order_model->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->where($today_where)->count();
            $page = $this->page($count, $this->page);
            $order_list = $order_model->alias('o')->field('o.*,s.date_signed,oi.ip as ip')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->join('__ORDER_INFO__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        } else {
            $count = $order_model->alias('o')->where($where)->count();
            $today_total = $order_model->alias('o')->where($today_where)->count();
            $page = $this->page($count, $this->page);
            $order_list = $order_model->alias('o')->field('o.*,oi.ip as ip')->join('__ORDER_INFO__ oi ON (o.id_order = oi.id_order)', 'LEFT')->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key] = D("Order/OrderBlacklist")->black_list_and_ip_address($o);
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'], $o['currency_code']);
            $order_list[$key]['http_referer'] = !empty($o['http_referer']) ? $o['http_referer'] : '--';
        }
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true, 36000)->select();
        $advertiser = array_column($advertiser, 'name', 'id');
        $department_id = $_SESSION['department_id'];
        $department = D('Department/Department')->where('type=1')->cache(true, 3600)->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        add_system_record($_SESSION['ADMIN_ID'], 4, 4, '查看DF订单列表');
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("selectdepartment", empty($_GET['id_department'])?$department_id[0]:intval($_GET['id_department']));
        $this->assign("advertiser", $advertiser);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("page", $page->show('Admin'));
        $this->assign("today_total", $today_total);
        $this->assign("order_total", $count);
        $this->assign("all_domain_total", $all_domain_total);
        $this->assign("warehouse", $warehouse);

        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        $this->assign('status_list', $status_model->get_status_label());
        $this->assign("order_list", $order_list);
        $this->display();
    }

    /**
     * 导出订单列表
     */
    public function export_search ()
    {
        set_time_limit(0);
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        /*$column = array(
            '地区','部门', '广告专员', '域名', '订单号', '姓名','email', '产品名和价格', '内部名', '总价（NTS）', '属性', 'SKU',
            '电话-送货地址', '购买产品数量', '留言信息','备注信息', '下单时间', '订单状态',
            '发货日期', '物流名称', '运单号', '物流状态'
        );*/

        //DF订单管理->未处理订单，导出邮编的显示     liuruibin   20171030
        $column = array(
            '地区', '部门', '广告专员', '域名', '订单号', '姓名', '电话', '邮箱', '产品名和价格', '内部名', '总价（NTS）', '属性', 'SKU',
            '电话-送货地址', '邮编', '购买产品数量', '留言信息', '售前备注', '售后备注','下单时间', '订单状态',
            '发货日期','物流上线日期','物流名称', '运单号', '物流状态'
        );

        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        /** 没有sku 以及内部名搜索 导出，注释掉之前的导出方法，现重新构造导出 zx 12/04 start **/
        /*$where = $this->order->form_where($_GET);
        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";//货到付款订单，过滤已经支付的
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        //默认只显示当前有权限的第一个部门 jiangqinqing 20171116
        if($_GET['id_department'] == 1000){
            $where['id_department'] = array('IN', $department_id);
        }else{
            $where['id_department'] = $_GET['id_department'] ? array('EQ', $_GET['id_department']) : array('EQ', $department_id[0]);
        }

        //筛选ip
        if ( isset($_GET['ip']) && !empty($_GET['ip']) ) {
            $find_order = D('Order/OrderInfo')->field('id_order')->where(array('ip' => trim($_GET['ip'])))->select();
            if ( !empty($find_order) ) {
                $where['id_order'] = array('IN', array_column($find_order, 'id_order'));
            } else {
                $where['id_order'] = 0;
            }
        }

        //筛选发货时间 导出 zx 11/20 startdate_delivery
        if( isset($_GET['startdate_delivery']) && !empty($_GET['startdate_delivery']) ) {
            $date_delivery[] = array('EGT', $_GET['startdate_delivery']);
            if( isset($_GET['enddate_delivery']) && !empty($_GET['enddate_delivery']) ) {
                $date_delivery[] = array('LT', $_GET['enddate_delivery']);
            }
            $where[] = array('date_delivery' => $date_delivery);
        }


        //筛选订单状态 增加有效订单 无效订单 导出 zx 11/17
        if(I('get.status_id') == 100){
                $where['id_order_status'] =['IN', ('4,5,6,7,8,9,10,16,17,18,19,21,22,23,24,25,26,27')];
        }elseif(I('get.status_id') == 101){
                $where['id_order_status'] =['IN',('11,12,13,14,15,28,29,30')];
        }elseif(I('get.status_id') == 0){
                unset($where['id_order_status']);
        }else{
                $where['id_order_status'] =['EQ',I('get.status_id')];
        }

        $role_user = M('RoleUser')->field('role_id')->where(array('user_id' => $_SESSION['ADMIN_ID'], 'role_id' => 32))->find();
        if ( $role_user ) {
            $belong_zone_id = isset($_SESSION['belong_zone_id']) ? $_SESSION['belong_zone_id'] : array(0);
            if ( !isset($where['id_zone']) ) {
                $where['id_zone'] = array('IN', $belong_zone_id);
            }
        }
                       
        $orders = $this->order
            ->where($where)
            ->order("id_order ASC")
            ->limit(20000)->select();
        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int)$statu['id_order_status']] = $statu;
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        /*$order_item = D('Order/OrderItem');
        $idx = 2;
        /** @var \Common\Model\ZoneModel $zone_model */
        /*$zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        foreach ($orders as $o) {
            $product_name = '';
            $inner_name = array();
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            $sku = [];
            foreach ($products as $p) {

                $product_name[] = $p['product_title'] . "\n";
                $inner_name[] = $p['inner_name'] . "\n";
                $sku[] = $p['sku'];
                if ( $p['sku_title'] ) {
                    $attrs .= $p['sku_title'] . ' x ' . $p['quantity'] . ",";
                } else {
                    $attrs .= $p['product_title'] . ' x ' . $p['quantity'] . ",";
                }
                $product_count += $p['quantity'];
            }
            $attrs = trim($attrs, ',');
            $sku = array_unique($sku);
            $sku = implode(',', $sku);
            $product_name = trim(implode(',', array_unique($product_name)), ',');

            $inner_name = trim(implode(',', array_unique($inner_name)), ',');

            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $user_name = M('Users')->where(array('id' => $o['id_users']))->getField('user_nicename');
            $department_title = M('department')->where(array('id_department' => $o['id_department']))->getField('title');
            $domain_title = D('Domain/Domain')->where(array('id_domain' => $o['id_domain']))->getField('name');
            $shipping_name = M('Shipping')->where(array('id_shipping' => $o['id_shipping']))->getField('title');
            $shipping_date_online = D("Order/OrderShipping")->where(array('id_shipping' => $o['id_shipping'],'id_order'=>$o['id_order']))->getField('date_online');
            $getShipObj = D("Order/OrderShipping")->field('track_number,status_label')->where('id_order=' . $o['id_order'])->select();
            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            /*$data = array(
                $all_zone[$o['id_zone']],$department_title, $user_name, $domain_title, ' ' . $o['id_increment'], $o['first_name'] . ' ' . $o['last_name'], $product_name, $inner_name, $o['price_total'], $attrs, $sku, $o['tel'].'-'.$o['province'] . $o['city'] . $o['area'] . $o['address'], $product_count, $o['remark'],$o['comment'], $o['created_at'], $status_name, $o['date_delivery'], $shipping_name, ' ' . $trackNumber, $trackStatusLabel
            );*/
            //if ( $_GET['istel_email'] == 1 ) {
            //DF订单管理->订单列表，导出邮编的显示$o['zipcode']     liuruibin   20171030
            /*$data = array(

                $all_zone[$o['id_zone']], $department_title, $user_name, $domain_title, ' ' . $o['id_increment'], $o['first_name'] . ' ' . $o['last_name'], $o['tel'], $o['email'], $product_name, $inner_name, $o['price_total'], $attrs, $sku,

                $o['tel'] . '-' . $o['province'] . $o['city'] . $o['area'] . $o['address'], $o['zipcode'], $product_count, $o['remark'], $o['comment'], $o['aftermarket_reviews'],$o['created_at'], $status_name,
                $o['date_delivery'],$shipping_date_online, $shipping_name, $trackNumber, $trackStatusLabel
            );
            //}
            $j = 65;
            foreach ($data as $key => $col) {
                if ( $key != 8 && $key != 11 ) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                } else {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;
        }*/
        /** 没有sku 以及内部名搜索 导出，现重新构造导出 zx 12/04 end **/
        
        /*** 重构 订单列表-导出start zx 12/04 ***/
        $order_model = $this->order;
        /*创建日期初始化*/
        $created_at_array = array();

        if ( $_GET['start_time'] or $_GET['end_time'] ) {
            if ( $_GET['start_time'] ) {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ( $_GET['end_time'] ) {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
            $set_where['created_at'] = $created_at_array;
        }
        $where = $order_model->form_where($_GET, 'o.');
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        //默认只显示当前有权限的第一个部门 jiangqinqing 20171116
        if($_GET['id_department'] == 1000){
            $where['o.id_department'] = array('IN', $department_id);
        }else{
            $where['o.id_department'] = $_GET['id_department'] ? array('EQ', $_GET['id_department']) : array('EQ', $department_id[0]);
        }
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id' => $_SESSION['ADMIN_ID'], 'role_id' => 32))->find();
        if ( $role_user ) {
            if ( $_REQUEST['zone_id'] ) {
                $where['o.id_zone'] = array('EQ', $_REQUEST['zone_id']);
            }
            $belong_zone_id = isset($_SESSION['belong_zone_id']) ? $_SESSION['belong_zone_id'] : array(0);
            if ( !isset($where['o.id_zone']) ) {
                $where['o.id_zone'] = array('IN', $belong_zone_id);
            }
        }

        $today_date = date('Y-m-d 00:00:00');
        
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();
        $form_data['domain_address'] = $domain_model->get_all_real_address();
        if ( isset($_GET['status_id']) && $_GET['status_id'] ) {
            if ( $_GET['status_id'] == 11 ) {
                $where['o.order_repeat'] = array("GT", 1);
            } else {
                $where['o.id_order_status'] = $_GET['status_id'];
            }
            if (I('get.status_id') == '5,7,18') {
                $where['o.id_order_status'] =['IN', I('get.status_id')];
            }elseif(I('get.status_id') == 100){ //有效订单 zx 11/15
                $where['o.id_order_status'] =['IN', ('4,5,6,7,8,9,10,16,17,18,19,21,22,23,24,25,26,27')];
            }elseif(I('get.status_id') == 101){ //无效订单 zx 11/15
                $where['o.id_order_status'] =['IN',('11,12,13,14,15,28,29,30')];
            }
        }

        //筛选ip
        if ( isset($_GET['ip']) && !empty($_GET['ip']) ) {
            $find_order = D('Order/OrderInfo')->field('id_order')->where(array('ip' => trim($_GET['ip'])))->select();
            if ( !empty($find_order) ) {
                $where['o.id_order'] = array('IN', array_column($find_order, 'id_order'));
            } else {
                $where['o.id_order'] = 0;
            }
        }
        //筛选物流
        if ( isset($_GET['id_shipping']) && $_GET['id_shipping'] ) {
            $where['o.id_shipping'] = $_GET['id_shipping'];
        }
        if ( isset($_GET['action']) && $_GET['action'] == 'repeat' ) {
            $id_order = $_GET['id_order'];
            $countOrder = D("order/order")->where("id_order=" . $id_order)->field("tel,first_name")->find();
            $where['_string'] = "(o.tel=" . $countOrder['tel'] . " OR o.first_name='" . $countOrder['first_name'] . "')";
        } else {
            $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
        }
        //添加"发货时间"筛选条件,可选范围     liuruibin   20171023
        if ( $_GET['startdate_delivery'] or $_GET['enddate_delivery'] ) {
            if ( $_GET['startdate_delivery'] ) {
                $date_delivery_array[] = array('EGT', $_GET['startdate_delivery']);
            }
            if ( $_GET['enddate_delivery'] ) {
                $date_delivery_array[] = array('LT', $_GET['enddate_delivery']);
            }
            $where['o.date_delivery'] = $date_delivery_array;
        }
        if(isset($_GET['sku']) && $_GET['sku']){
            $where['oit.sku'] = array("EQ",addslashes(trim($_GET['sku'])));
        }
        if(isset($_GET['inner_name']) && $_GET['inner_name']){
            $where['pp.inner_name'] = array("LIKE",'%'.addslashes(trim($_GET['inner_name'])).'%');
        }
        
        $field = "DISTINCT o.*";
        $order_list = $order_model->alias('o')->field($field)
            ->join("__ORDER_ITEM__ oit On oit.id_order=o.id_order","LEFT")
            ->join("__PRODUCT__ pp ON oit.id_product=pp.id_product","LEFT")
            ->where($where)->order("id_order ASC")->limit(20000)->select();

        $order_item = D('Order/OrderItem');
        if(!empty($order_list)){
            //取出所有的 id_domain
            $id_domains = array_column($order_list,'id_domain');
            $where_domain['id_domain'] = array("IN",implode(',', $id_domains));
            $domains_all = M("Domain")->field('id_domain,name')->where($where_domain)->select();

            //取出所有的 id_order
            $id_orders = array_column($order_list,'id_order');
            $where_order['id_order'] = array("IN",implode(',', $id_orders));
            $orders_all = M("OrderShipping")->field('id_order,date_online,track_number,status_label')->where($where_order)->select();

            //链表查询获取产品信息-订单详情
            $order_item_prodcut = M("OrderItem")->alias('oi')
                    ->join("__PRODUCT__ pp ON oi.id_product=pp.id_product",'LEFT')
                    ->field('oi.*,pp.title,pp.inner_name,pp.foreign_title')
                    ->where($where_order)
                    ->order('oi.id_order ASC')
                    ->select();

            foreach($order_item_prodcut as $key=>$row){
                    $order_item_prodcut_all[$row['id_order']][$row['id_product_sku']] = $row;
                    $order_item_prodcut_all[$row['id_order']][$row['id_product_sku']]['total_quantity'] = $row['quantity'];
                }
            $export_data = array();
            foreach($order_item_prodcut_all as $oik=>$oiv){
                $sku = array();
                $product_name = array();
                $inner_name = array();
                $attrs = '';
                $product_count = '';
                foreach($oiv as $kik=>$viv){
                    $product_name[$kik] = $viv['product_title'] ;
                    $sku[$kik] = $viv['sku'];
                    $inner_name[$kik] = $viv['inner_name'] ;
                    if ( $viv['sku_title'] ) {
                        $attrs .= $viv['sku_title'] . ' x ' . $viv['quantity'] . ",";
                    } else {
                        $attrs .= $viv['product_title'] . ' x ' . $viv['quantity'] . ",";
                    } 
                    $product_count += $viv['quantity'];
                }
                $attrs = trim($attrs, ',');
                $sku = implode(',', array_unique($sku));
                $product_name = trim(implode(',', array_unique($product_name)), ',');
                $inner_name = trim(implode(',', array_unique($inner_name)), ',');
                $export_data[$oik]['product_name'] = $product_name;
                $export_data[$oik]['inner_name'] = $inner_name;
                $export_data[$oik]['sku'] = $sku;
                $export_data[$oik]['attrs'] = $attrs; 
                $export_data[$oik]['product_count'] = $product_count;
            }
        }
             
        $result = D('Order/OrderStatus')->select();
        $users = M("Users")->where("user_status=1")->getField('id,user_nicename');
        $departments = M("Department")->where('type=1')->getField('id_department,title');
        $shippings = M("Shipping")->where('status=1')->getField('id_shipping,title');
        $statuss = M("OrderStatus")->getField('id_order_status,title');
        $zones = M("Zone")->getField('id_zone,title');
        
        $idx = 2;
        
        foreach ($order_list as $ko=>$o) {
            $shipping_date_online = ''; 
            $trackNumber = '';
            $trackStatusLabel = '';
            $product_name = '';
            $inner_name = '';
            $attrs = '';
            $sku = '';
            $product_count = 0;
            
            //拼接域名
            foreach($domains_all as $dk=>$dv){
                if($o['id_domain'] == $dv['id_domain']){
                   $domain_title = $dv['name']; 
                   break;
                }
            }
            //拼接物流状态
            foreach($orders_all as $oak=>$oav){
                if($o['id_order'] == $oav['id_order']){
                   $shipping_date_online = $oav['date_online']; //在黑猫系统上线时间
                   $trackNumber = $oav['track_number'];  //快递单号
                   $trackStatusLabel = $oav['status_label']; //状态标签
                   break;
                }
            }
            //拼接订单详情
            foreach($export_data as $ek=>$ev){
                if($o['id_order']  == $ek){
                    $product_name = $ev['product_name'];
                    $sku = $ev['sku'];
                    $attrs = $ev['attrs'];
                    $product_count = $ev['product_count'];
                    $inner_name = $ev['inner_name'];
                    break;
                }
            }
            
            //DF订单管理->订单列表，导出邮编的显示$o['zipcode']     liuruibin   20171030
            $data = array(
                $zones[$o['id_zone']], $departments[$o['id_department']], $users[$o['id_users']], $domain_title, ' ' . $o['id_increment'], $o['first_name'] . ' ' . $o['last_name'], $o['tel'], $o['email'], $product_name, $inner_name, $o['price_total'], $attrs, $sku,$o['tel'] . '-' . $o['province'] . $o['city'] . $o['area'] . $o['address'], $o['zipcode'], $product_count, $o['remark'], $o['comment'], $o['aftermarket_reviews'],$o['created_at'], $statuss[$o['id_order_status']],$o['date_delivery'],$shipping_date_online, $shippings[$o['id_shipping']], $trackNumber, $trackStatusLabel
            );

            $j = 65;
            foreach ($data as $key => $col) {
                if ( $key != 8 && $key != 11 ) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                } else {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;
        }
      
        /*** 重构 订单列表-导出end zx 12/04 ***/
        
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出DF订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    /**
     * 导出订单列表(电话邮箱)
     */
    public function export_search_two ()
    {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $column = array(
            '地区', '广告专员', '域名', '订单号', '姓名', '电话', '邮箱', '产品名和价格', '内部名', '总价（NTS）', '属性',
            '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
            '发货日期', '物流名称', '运单号', '物流状态'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $where = $this->order->form_where($_GET);
        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";//货到付款订单，过滤已经支付的
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != '' ? array('EQ', $_GET['id_department']) : array('IN', $department_id);
        if ( isset($_GET['id_department']) && $_GET['id_department'] ) {
            $where['id_department'] = $_GET['id_department'];
        }

        //筛选ip
        if ( isset($_GET['ip']) && !empty($_GET['ip']) ) {
            $find_order = D('Order/OrderInfo')->field('id_order')->where(array('ip' => trim($_GET['ip'])))->select();
            if ( !empty($find_order) ) {
                $where['id_order'] = array('IN', array_column($find_order, 'id_order'));
            } else {
                $where['id_order'] = 0;
            }
        }

        $role_user = M('RoleUser')->field('role_id')->where(array('user_id' => $_SESSION['ADMIN_ID'], 'role_id' => 32))->find();
        if ( $role_user ) {
            $belong_zone_id = isset($_SESSION['belong_zone_id']) ? $_SESSION['belong_zone_id'] : array(0);
            if ( !isset($where['id_zone']) ) {
                $where['id_zone'] = array('IN', $belong_zone_id);
            }
        }
        $orders = $this->order
            ->where($where)
            ->order("id_order ASC")
            ->limit(20000)->select();
        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int)$statu['id_order_status']] = $statu;
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        foreach ($orders as $o) {
            $product_name = '';
            $inner_name = array();

            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            foreach ($products as $p) {
                $product_name .= $p['product_title'] . "\n";

                $inner_name[] = $p['inner_name'] . "\n";
                if ( $p['sku_title'] ) {
                    $attrs .= $p['sku_title'] . ' x ' . $p['quantity'] . ",";
                } else {
                    $attrs .= $p['product_title'] . ' x ' . $p['quantity'] . ",";
                }
                $product_count += $p['quantity'];
            }
            $attrs = trim($attrs, ',');

            $inner_name = trim(implode(',', array_unique($inner_name)), ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $user_name = M('Users')->where(array('id' => $o['id_users']))->getField('user_nicename');
            $domain_title = D('Domain/Domain')->where(array('id_domain' => $o['id_domain']))->getField('name');
            $shipping_name = M('Shipping')->where(array('id_shipping' => $o['id_shipping']))->getField('title');
            $getShipObj = D("Order/OrderShipping")->field('track_number,status_label')->where('id_order=' . $o['id_order'])->select();
            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $data = array(
                $all_zone[$o['id_zone']], $user_name, $domain_title, $o['id_increment'], $o['first_name'] . ' ' . $o['last_name'], $o['tel'], $o['email'], $product_name,
                $inner_name, $o['price_total'], $attrs,
                $o['province'] . $o['city'] . $o['area'] . $o['address'], $product_count, $o['remark'], $o['created_at'], $status_name,
                $o['date_delivery'], $shipping_name, $trackNumber, $trackStatusLabel
            );
            $j = 65;
            foreach ($data as $key => $col) {
                if ( $key != 8 && $key != 11 ) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                } else {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出DF订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    /**
     * 订单详情
     */
    public function info ()
    {
        $order_id = I('get.id');
        $order = D("Order/Order")->find($order_id);
        $web_infos = !empty($order['web_info']) ? unserialize(htmlspecialchars_decode($order['web_info'])) : '';
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $orderHistory = D("Order/OrderRecord")
            ->field('*')
            ->join('__USERS__ u ON (__ORDER_RECORD__.id_users = u.id)', 'LEFT')
            ->where(array('id_order' => $order_id))
            ->order('created_at desc, id_order_status = 4 desc, id_order_status = 25 desc, id_order_status asc')->select();
        $shipping = D('Common/Shipping')
            ->where(array('id_shipping' => (int)$order['id_shipping']))->cache(true, 3600)
            ->find();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $all_domain = $domain_model->get_all_domain();
        $order['id_domain'] = $all_domain[$order['id_domain']];
        $order['id_order_status'] = $statusLabel[$order['id_order_status']];
        $products = D('Order/OrderItem')->get_item_list($order['id_order']);
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看DF订单详情');
        $this->assign("order", $order);
        $this->assign("products", $products);
        $this->assign("history", $orderHistory);
        $this->assign("label", $statusLabel);
        $this->assign('shipping_name', $shipping['title']);
        $this->assign('shopping_url', $shipping['track_url']);
        $this->assign('web_infos', $web_infos);
        $this->display();
    }

    /**
    ** 问题件订单列表  --Lily 2017-11-21
    **/
    public function question_order(){
        $domain_list = M("Domain")->getField("id_domain,name");
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->select();
        $advertiser = array_column($advertiser, 'name', 'id');
        $department_id = $_SESSION['department_id'];
        $department = D('Department/Department')->where('type=1')->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        // $shipping = M('OrderShipping')->where('status=0')->field('id_shipping,shipping_name,track_number,status_label,id_order')->select();dump($shipping);
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $zone_model = D('Common/Zone');
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id' => $_SESSION['ADMIN_ID'], 'role_id' => 32))->find();
        if ( $role_user ) {
            $belong_zone_id = isset($_SESSION['belong_zone_id']) ? $_SESSION['belong_zone_id'] : array(0);

            if ( !empty($belong_zone_id) ) {
                $all_zone = $zone_model->field('`title`,id_zone')->where(['id_zone' => array('IN', $belong_zone_id)])->order('`title` ASC')->select();
                $all_zone = $all_zone ? array_column($all_zone, 'title', 'id_zone') : '';
            }

        } else {
            $all_zone = $zone_model->all_zone();
        }
        if(isset($_GET['id_department']) && $_GET['id_department']){
            if($_GET['id_department'] != '1000'){
                $where['id_department'] = $_GET['id_department'];
            }
        }else{
            $where['id_department'] = $_SESSION['department_id'][0];
        }
        $create_time = array();
        if(isset($_GET['start_time']) && $_GET['start_time']){
            $create_time[] = array("EGT",$_GET['start_time']);
             if(isset($_GET['end_time']) && $_GET['end_time']){
            $create_time[] = array("ELT",$_GET['end_time']);
        }
        }
        if(!empty($create_time)){
           $where['create_time'] = $create_time;
        }
        $count = M("OrderQuestion")->where($where)->count();
        $page = $this->page($count, $this->page);
        $order_data = M("OrderQuestion")->where($where)->limit($page->firstRow . ',' . $page->listRows)->order("create_time DESC")->select();
        foreach ($order_data as $key => $val) {
            $bef_ord = json_decode($val['question_order_before'],true)[0];
            $val['question_order_before'] = json_decode($val['question_order_before'],true);
            $order_list[$key] = $bef_ord;
            $order_list[$key]['question_order_after'] = json_decode($val['question_order_after'],true);
            $order_list[$key]['products'] = $val['question_order_before']['product'];
            $order_list[$key]['question_id'] = $val['question_order_id'];
            $order_list[$key]['create_time'] = $val['create_time'];

        }
        $this->assign("all_zone", $all_zone);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("selectdepartment", empty($_GET['id_department'])?$department_id[0]:intval($_GET['id_department']));
        $this->assign("advertiser", $advertiser);
        $this->assign("domain_list", $domain_list);
        $this->assign("get", $_GET);
        $this->assign("warehouse", $warehouse);
        $this->assign("order_list",$order_list);
        $this->assign("page", $page->show('Admin'));
        $status_model = D('Order/OrderStatus');
        $this->assign('status_list', $status_model->get_status_label());
        $this->display();
    }

    /**
    **  问题件修改前后信息对比  --Lily 2017-11-22
    **/
    public function compareQuestion(){
        $question_order_id = $_POST['question_order_id'];
        // print_r($question_order_id);die;
        $order = M("OrderQuestion")->find($question_order_id);
        // dump($order);die;
        $this->assign("order",$order);
        $this->display();
        exit;
     }
     /**
     ** 导出问题件信息
     **/
     public function export_question(){
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        //表头
        $colum = array(
            '地区','部门','广告专员','域名','订单号','姓名','电话','邮箱','产品名和价格','内部名','总价（NTS）','属性','SKU','电话-送货地址','邮编','购买产品数量','留言信息','备注信息','下单时间','订单状态','发货日期','物流名称','运单号','物流状态','问题件时间'
            );
        //dump($colum);
         $j = 65;
        $idx = 2;
        foreach($colum as $k=>$cols){
            $excel->getActiveSheet()->setCellValue(chr($j).'1',$cols);
            ++$j;
        }
        $domain_list = M("Domain")->getField("id_domain,name");
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true, 36000)->select();
        $advertiser = array_column($advertiser, 'name', 'id');
        $department_id = $_SESSION['department_id'];
        $department = D('Department/Department')->where('type=1')->cache(true, 3600)->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        // $shipping1 = M('Shipping')->where('status=1')->getField('id_shipping,title',true);
        // $shipping2 = M('OrderShipping')->where('status=0')->getField('id_shipping,track_number',true);
        // $shipping3 = M('OrderShipping')->where('status=0')->getField('id_shipping,status_label');
       // dump($shipping3); // echo M('OrderShipping')->getLastSql();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $status_model = D('Order/OrderStatus');
        $id_order_status = $status_model->get_status_label();
        $zone_model = D('Common/Zone');
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id' => $_SESSION['ADMIN_ID'], 'role_id' => 32))->find();
        if ( $role_user ) {
            $belong_zone_id = isset($_SESSION['belong_zone_id']) ? $_SESSION['belong_zone_id'] : array(0);

            if ( !empty($belong_zone_id) ) {
                $all_zone = $zone_model->field('`title`,id_zone')->where(['id_zone' => array('IN', $belong_zone_id)])->order('`title` ASC')->select();
                $all_zone = $all_zone ? array_column($all_zone, 'title', 'id_zone') : '';
            }

        } else {
            $all_zone = $zone_model->all_zone();
        }
        if(isset($_GET['id_department']) && $_GET['id_department']){
            if($_GET['id_department'] != '1000'){
                $where['id_department'] = $_GET['id_department'];
            }
        }else{
            $where['id_department'] = $_SESSION['department_id'][0];
        }
        $create_time = array();
        if(isset($_GET['start_time']) && $_GET['start_time']){
            $create_time[] = array("EGT",$_GET['start_time']);
             if(isset($_GET['end_time']) && $_GET['end_time']){
            $create_time[] = array("ELT",$_GET['end_time']);
        }
        }
        if(!empty($create_time)){
           $where['create_time'] = $create_time;
        }
        $count = M("OrderQuestion")->where($where)->count();
        $page = $this->page($count, $this->page);
        $order_data = M("OrderQuestion")->where($where)->limit($page->firstRow . ',' . $page->listRows)->order("create_time DESC")->select();
         foreach ($order_data as $key => $val) {
            $bef_ord = json_decode($val['question_order_before'],true);
            $aft_ord = json_decode($val['question_order_after'],true);
            $order_list[$key]['question_order_after'] = $aft_ord;
            if($aft_ord['action'] && $aft_ord['action']=='ajax'){
                $order_list[$key]['products'] = D("Order/OrderItem")->get_item_list($aft_ord['id_order']);
                $order_list[$key]['first_name'] = $aft_ord['first_name'];
                $order_list[$key]['last_name'] = $aft_ord['last_name'];
                $order_list[$key]['total_price'] = $aft_ord['price_total'];
                $order_list[$key]['zipcode'] = $aft_ord['zipcode'];
                $order_list[$key]['province'] = $aft_ord['province'];
                $order_list[$key]['city'] = $aft_ord['city'];
                $order_list[$key]['area'] = $aft_ord['area'];
                $order_list[$key]['address'] = $aft_ord['address'];
                $order_list[$key]['created_at'] = $aft_ord['created_at'];
                $order_list[$key]['comment'] = $aft_ord['comment'];
                $order_list[$key]['tel'] = $aft_ord['tel'];
                $order_list[$key]['email'] = $aft_ord['email'];
                $order_list[$key]['total_qty_ordered'] = $aft_ord['total_qty_ordered'];
            }else{
                $order_list[$key]['products'] = $aft_ord['product'];
                $order_list[$key]['first_name'] = $aft_ord[0]['first_name'];
                $order_list[$key]['last_name'] = $aft_ord[0]['last_name'];
                $order_list[$key]['total_price'] = $aft_ord[0]['price_total'];
                $order_list[$key]['zipcode'] = $aft_ord[0]['zipcode'];
                $order_list[$key]['province'] = $aft_ord[0]['province'];
                $order_list[$key]['city'] = $aft_ord[0]['city'];
                $order_list[$key]['area'] = $aft_ord[0]['area'];
                $order_list[$key]['address'] = $aft_ord[0]['address'];
                $order_list[$key]['created_at'] = $aft_ord[0]['created_at'];
                $order_list[$key]['comment'] = $aft_ord[0]['comment'];
                $order_list[$key]['tel'] = $aft_ord[0]['tel'];
                $order_list[$key]['email'] = $aft_ord[0]['email'];
                $order_list[$key]['total_qty_ordered'] = $aft_ord[0]['total_qty_ordered'];
            }
                $order_list[$key]['id_increment'] = $bef_ord[0]['id_increment'];
                $order_list[$key]['id_domain'] = $domain_list[$bef_ord[0]['id_domain']];
                $order_list[$key]['id_users'] = $advertiser[$bef_ord[0]['id_users']];
                $order_list[$key]['id_zone'] = $all_zone[$bef_ord[0]['id_zone']];
                $order_list[$key]['id_warehouse'] = $warehouse[$bef_ord[0]['id_warehouse']];
                $order_list[$key]['id_order_status'] = $id_order_status[$bef_ord[0]['id_order_status']];
                $order_list[$key]['dt_title'] = $department[$bef_ord[0]['id_department']];
                $order_list[$key]['remark'] = $bef_ord[0]['remark'];
                $order_list[$key]['date_delivery'] = $bef_ord[0]['date_delivery'];
                $order_list[$key]['id_shipping'] = M('Shipping')->where('status=1 AND id_shipping='.$bef_ord[0]['id_shipping'])->getField('title',true);
               $order_list[$key]['track_number'] = M("OrderShipping")->where(array('id_shipping'=>$bef_ord[0]['id_shipping'],'id_order'=>$bef_ord['id_order']))->getField("track_number");
                $order_list[$key]['status_label'] = M("OrderShipping")->where(array('id_shipping'=>$bef_ord[0]['id_shipping'],'id_order'=>$bef_ord['id_order']))->getField("status_label");
                $order_list[$key]['order_repeat'] = $bef_ord[0]['order_repeat'];
                $order_list[$key]['create_time'] = $val['create_time'];
                $order_list[$key]['question_id'] = $val['question_order_id'];

        }
        
        foreach ($order_list as  $k => $val) {
             // dump($val);die;
                    $data = array(
                    $val['id_zone'],$val['dt_title'],$val['id_users'],$val['id_domain'],$val['id_increment'],$val['first_name'].$val['last_name'],$val['tel'],$val['email'],implode(",",array_column($val['products'],'product_title')),implode(",",array_column($val['products'],'inner_name')),implode(",",array_column($val['products'],'total')),implode(",",array_column($val['products'],'sku_title')),implode(",",array_column($val['products'],'sku')),$val['tel'].'-'.$val['address'],$val['zipcode'],$val['total_qty_ordered'],$val['remark'],$val['comment'],$val['created_at'],$val['id_order_status'],$val['date_delivery'],$val['id_shipping'],$val['track_number'],$val['status_label'],$val['create_time']
                    );
             $j = 65;
            foreach ($data as  $col) {
                 $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;

        }
        add_system_record(sp_get_current_admin_id(), 4, 4, '导出DF订单问题件列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '导出DF订单问题件列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '导出DF订单问题件列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
     }
    /**
     * 编辑订单 from表单
     */
    public function edit_order ()
    {
        $getId = I('get.id/i');
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where = array('id_order' => array('EQ', $getId));
        $where['id_department'] = array('IN', $department_id);
        $order = $this->order->where($where)->find();
        $web_infos = !empty($order['web_info']) ? unserialize(htmlspecialchars_decode($order['web_info'])) : '';
        if ( !$order ) {
            $this->error("你没有权限操作此订单！");
        }
        /** @var \Common\Model\ProductModel $product */
        $product = D('Common/Product');
        $order_item = D('Order/OrderItem');
        $products = $order_item->where(array(
            'id_order' => $order['id_order']
        ))->order('id_product desc')->select();
        /** @var  $options \Product\Model\ProductOptionModel */
        $options = D('Product/ProductOption');
        $all_attr = array();
        $arr_html = array();

        if ( $products ) {
            foreach ($products as $key => $product) {
                $product_id = $product['id_product'];
                if ( !empty($product_id) ) {
                    $sku_id = $product['id_product_sku'];
                    $sku_id_tmp = $sku_id + rand(1, 1000);
                    $arr_html[$product_id]['id_product'] = $product_id;
                    $arr_html[$product_id]['product_title'] = $product['product_title'];
                    $arr_html[$product_id]['id_order_item'] = $product['id_order_item'];
                    $arr_html[$product_id]['id_order_items'][$sku_id_tmp] = $product['id_order_item'];
                    $arr_html[$product_id]['quantity'][$sku_id_tmp] = $product['quantity'];
                    $arr_html[$product_id]['attrs'][$sku_id_tmp] = unserialize($product['attrs']);
                    $arr_html[$product_id]['attr_option_value'][$sku_id_tmp] = $options->get_attr_list_by_id($product_id);
                    $arr_html[$product_id]['attr_option_value_data'] = $options->get_attr_list_by_id($product_id);
                    $arr_html[$product_id]['sku_id'] = $product['id_product_sku'];
                    $arr_html[$product_id]['sku_id_tmp'] = $sku_id_tmp;
                    $products[$key]['attr_option_value'] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                    $all_attr[$product_id][$sku_id] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                }
            }
        }

        $this->assign("products", $arr_html);
        $this->assign("all_attr", $all_attr);
        $this->assign("order", $order);
        $this->assign('web_infos', $web_infos);
        $this->display();
    }

    //编辑订单添加产品属性
    public function get_attr_html ()
    {
        if ( IS_AJAX ) {
            $pro_id = I('post.id');
            $order_id = I('post.order_id');

            $order_item = D('Order/OrderItem');
            $products = $order_item->where(array(
                'id_order' => $order_id
            ))->order('id_product desc')->select();
            $options = D('Product/ProductOption');
            $all_attr = array();
            $select_attr = array();
            $select_num = array();

            if ( $products ) {
                foreach ($products as $key => $product) {
                    $product_id = $product['id_product'];
                    if ( !empty($product_id) ) {
                        $sku_id = $product['id_product_sku'];
                        $select_attr[$product_id]['attrs'] = unserialize($product['attrs']);
                        $select_attr[$product_id]['quantity'] = $product['quantity'];
                        $all_attr[$product_id] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                    }
                }
            }

//            dump($select_attr);

            $html = '<tr class="productAttrRow' . $pro_id . '">';
            $html .= '<td>';
            foreach ($all_attr[$pro_id] as $k => $val) {
                $html .= $val['title'] . '&nbsp';
                $html .= '<select name="option_id[' . $pro_id . '][' . $val["id_product_option"] . '][]">';
                foreach ($val["option_values"] as $kk => $vv) {
                    $selected = in_array($vv['id_product_option_value'], $select_attr[$pro_id]['attrs']) ? 'selected' : '';
                    $html .= '<option value ="' . $vv['id_product_option_value'] . '" ' . $selected . '>' . $vv['title'] . '</option>';
                }
                $html .= '</select>&nbsp;&nbsp;&nbsp;';
            }
            $html .= '<input name="number[' . $pro_id . '][]" value="' . $select_attr[$pro_id]['quantity'][0] . '" type="text">&nbsp;&nbsp;';
            $html .= '<a href="javascript:void(1);" class="deleteOrderAttr" pro_id="' . $pro_id . '">删除</a>';
            $html .= '</td>';
            $html .= '</tr>';
            echo $html;
            die();
        }
    }

    public function edit_order_post1 ()
    {
        if ( isset($_POST['action']) && $_POST['action'] == 'delete_attr' ) {
            //因为要添加权限，所以先写到这个控制器了。
            $orderId = I('get.id');
            $itemId = I('post.order_attr_id');
            if ( $orderId && $itemId ) {
                $order = D("Order/Order")->find($orderId);
                $deleteData = D("Order/OrderItem")->find($itemId);
                $comment = '删除产品属性：' . json_encode($deleteData);
//                D("Common/OrderItemOption")->where('order_item_id=' . $itemId)->delete();
                D("Order/OrderItem")->where('id_order_item=' . $itemId)->delete();
                D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'], 3, $comment);
            }
            exit();
        }
        if ( IS_POST ) {
            $data = I('post.');
            $fp = fopen('d:a.txt', 'a+b');
            fwrite($fp, print_r($data, true));
            fclose($fp);
            $order_info = $data;
            $order_info['id'] = $data['order_id'];
            $orderId = $data['order_id'];
            unset($order_info['product_ids'], $order_info['option_id'], $order_info['number'], $order_info['attach_id'], $order_info['number_attach'], $order_info['user_remark'], $order_info['action'], $order_info['order_id']);

            $result = D('Common/Order')->save($order_info);

            $product_ids = $data['product_ids'];
            $option_id = $data['option_id'];
            $number = $data['number'];
            //TODO:每一个属性组合都是一个订单产品
            //如果添加了3个属性组合, 那么就是3个订单的产品
            //要在order_item里添加3个产品
            //并且修改订单信息, 价格等

            foreach ($product_ids as $p_id) {//循环产品
                if ( !isset($option_id[$p_id]) || !isset($number[$p_id]) ) {
                    if ( isset($data['qty'][$p_id]) ) {
                        D('Common/OrderItem')->where('order_id=' . $orderId . ' and product_id=' . $p_id)->save(array('qty' => $data['qty'][$p_id]));
                    }
                    continue;
                }
                //1.找到订单里产品原有的信息
                //2.删除原有的属性 in order_item_option
                //3.重新写入替代的产品属性
                /* $order_item = D('Common/OrderItem')
                  ->where(array(
                  'order_id' => $data['order_id'],
                  'product_id' => $p_id
                  ))
                  ->find(); */

                $order_option = array();
                $numbers = implode('_', array_keys($option_id[$p_id]));
                $numbers = $number[$p_id][$numbers];
                foreach ($option_id[$p_id] as $op_id => $op) {//属性集
                    //print_r($op);
                    foreach ($op as $op_key => $op_value) {
                        //$order_option里每一个数组都是一个产品属性组合
                        //$op_key相当于order_item_option里的group_id
                        /* if ($numbers[$op_key] <= 0) {
                          continue;
                          } */ //下错型号了，需要把某个型号数量修改为0 ，另外型号修改为购买数量， 所以这里需要去掉
                        $order_option[$op_key][$op_id] = array(
                            'value_id' => $op_value,
                            'number' => $numbers[$op_key]
                        );
                    }
                }

                /* D('Common/OrderItemOption')
                  ->where(array(
                  'order_item_id' => $order_item['id'],
                  'product_id' => $order_item['product_id'],
                  ))
                  ->delete(); */
                //写入新属性
                //$op_key2组编号group_id
                $buyTotalQty = 0;
                foreach ($order_option as $op_key2 => $op2) {
                    $values = array_column($op2, 'value_id');
                    sort($values);
                    $valueString = "'" . implode(',', $values) . "'";
                    $productId = $p_id;
                    $productBundle = D('Common/ProductBundle')->where('product_id=' . $productId)->cache(true, 86400)->find();
                    $parentProductId = $productBundle ? $productBundle['parent_product_id'] : 0;

                    $getSkuSelect = D('Common/ProductSku')->where('product_id=' . $productId . ' and option_value=' . $valueString)
                        ->cache(true, 86400)->find();
                    $orItWhere = array('order_id' => $orderId, 'product_id' => $productId, 'sku_id' => $getSkuSelect['id']);
                    $getOrderItem = D('Common/OrderItem')->where($orItWhere)->find();
                    foreach ($op2 as $op3) {
                        $qty = $op3['number'];
                        break;
                    }
                    if ( $getOrderItem ) {
                        if ( $qty == 0 ) {
                            //下错型号了，需要把某个型号数量修改为 0 ,清除此产品 否则更新
                            D('Common/OrderItemOption')->where('order_item_id=' . $getOrderItem['id'])->delete();
                            D('Common/OrderItem')->where('id=' . $getOrderItem['id'])->delete();
                        } else {//更新购买的库存
                            $selOrdIteOpt = D('Common/OrderItemOption')->where('order_item_id=' . $getOrderItem['id'])->select();
                            if ( !$selOrdIteOpt && $values ) {
                                //当没提交订单过滤，没有添加属性的时候，添加属性
                                $attrs = serialize($values);
                                D('Common/OrderItem')->where('id=' . $getOrderItem['id'] . ' and order_id=' . $orderId)
                                    ->save(array('qty' => $qty, 'attrs' => $attrs, 'sku_id' => $getSkuSelect['id']));
                                foreach ($values as $optId) {
                                    $valueLabel = D('Common/ProductOptionValue')->find($optId);
                                    $addOptData = array('order_item_id' => $getOrderItem['id'],
                                        'product_id' => $productId,
                                        'option_id' => $optId,
                                        'value_label' => $valueLabel['title'],
                                        'number' => $qty);
                                    D('Common/OrderItemOption')->data($addOptData)->add();
                                }
                            } else {
                                D('Common/OrderItem')->where('id=' . $getOrderItem['id'] . ' and order_id=' . $orderId)->save(array('qty' => $qty));
                            }
                            //D('Common/OrderItem')->where('id='.$getOrderItem['id'].' and order_id='.$orderId)->save(array('qty'=>$qty));
                            $buyTotalQty += $qty;
                        }
                        $updateLog[] = '更新 (sku_id:' . $getOrderItem['sku_id'] . ') qty(' . $getOrderItem['qty'] . ') 到' . $qty;
                    } else if ( $qty > 0 && !$getOrderItem ) {
                        //没有找到此记录，往订单产品表添加产品。
                        $loadProduct = D('Common/Product')->cache(true, 86400)->find($productId);
                        $total = $loadProduct['special_price'] * $qty;
                        $attrs = serialize($values);
                        $itemData = array('order_id' => $orderId, 'product_id' => $productId,
                            'product_title' => $loadProduct['title'],
                            'sku_id' => $getSkuSelect['id'], 'attrs' => $attrs, 'qty' => $qty,
                            'price' => $loadProduct['special_price'], 'total' => $total);
                        $itemData['parent_prodct_id'] = $parentProductId;
                        $getItemId = D('Common/OrderItem')->data($itemData)->add();
                        foreach ($values as $valId) {
                            $getOptVal = D('Common/ProductOptionValue')->cache(true, 86400)->find($valId);
                            $itemVal = array('order_item_id' => $getItemId, 'product_id' => $productId,
                                'option_id' => $valId, 'value_label' => $getOptVal['title']);
                            D('Common/OrderItemOption')->data($itemVal)->add();
                        }
                        $buyTotalQty += $qty;
                        $updateLog[] = '新添加 (sku_id:' . $getSkuSelect['id'] . ') qty (' . $qty['qty'] . ') 到订单';
                    }
                }
            }
            $remark = $updateLog ? implode(',', $updateLog) : '';
            D("Common/OrderStatusHistory")->addHistory($orderId, 1, $remark);
            if ( isset($data['attach_id']) ) {
                $attachId = $data['attach_id'];
                $numberAttach = $data['number_attach'];
                foreach ($attachId as $key => $atId) {
                    if ( $numberAttach[$key] > 0 ) {
                        $qty = $numberAttach[$key];
                        $loadProduct = D('Common/Product')->cache(true, 86400)->find($atId);
                        $total = $loadProduct['special_price'] * $qty;
                        $itemData = array('order_id' => $orderId, 'product_id' => $atId,
                            'product_title' => $loadProduct['title'],
                            'sku_id' => '', 'attrs' => '', 'qty' => $qty,
                            'price' => $loadProduct['special_price'], 'total' => $total);
                        $getItemId = D('Common/OrderItem')->data($itemData)->add();
                        $buyTotalQty += $qty;
                    }
                }
            }
            $updateData = array(
                'id' => $orderId,
            );
            Hook::run('event:order:status:update', $updateData, 'editOrder');
            //D('Common/Order')->where('id='.$orderId)->save(array('total_qty_ordered'=>$buyTotalQty));
            $this->success("保存完成！", U('Order/editorder', array('id' => $orderId)));
        } else {
            $this->error("参数不正确！");
        }
    }

    //add a record
    public function add_product_record ($id_users, $des, $bef_data, $data, $id_order)
    {
        $data = json_encode($data);
        $bef_data = json_encode($bef_data);
        $adddata['id_users'] = $id_users;
        $adddata['data'] = $data;
        $adddata['des'] = $des;
        $adddata['bef_data'] = $bef_data;
        $adddata['id_order'] = $id_order;
        $adddata['created_at'] = date('Y-m-d H:i:s');
        $record = M('orderProductRecord');
        $result = $record->data($adddata)->add();
        if ( $result ) {
            return true;
        } else {
            return fasle;
        }
    }

    public function list_product_record ()
    {
        $where = array();
        if ( isset($_GET['id_users']) && $_GET['id_users'] ) {
            $where['pr.id_users'] = trim($_GET['id_users']);
        }
        if ( isset($_GET['id_order']) && $_GET['id_order'] ) {
            $where['pr.id_order'] = trim($_GET['id_order']);
        }
        if ( isset($_GET['des']) && $_GET['des'] ) {
            $where['pr.des'] = array('LIKE', '%' . trim($_GET['des']) . '%');
        }
        if ( isset($_GET['id_increment']) && $_GET['id_increment'] ) {
            $where['o.id_increment'] = array('LIKE', '%' . trim($_GET['id_increment']) . '%');
        }

        if ( !empty($_GET['start_time']) || !empty($_GET['end_time']) ) {
            $created_at_array = array();
            if ( $_GET['start_time'] )
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ( $_GET['end_time'] )
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['pr.created_at'] = $created_at_array;
        }
        $users = M('Users')->field('id,user_nicename')->where(array('user_status' => 1))->select();
        $users = array_column($users, 'user_nicename', 'id');
        $record = M('orderProductRecord');
        $count = $record->alias('pr')->join('__ORDER__ AS o ON o.id_order=pr.id_order')->where($where)->count();
        $page = $this->page($count, 20);
        $list = $record->alias('pr')->field('pr.*,o.id_increment')->join('__ORDER__ AS o ON o.id_order=pr.id_order')->where($where)->limit($page->firstRow, $page->listRows)->select();
        if ( !empty($list) ) {
            foreach ($list as $k => $v) {
                $list[$k]['data'] = json_decode($v['data'], true);
                $list[$k]['bef_data'] = json_decode($v['bef_data'], true);
            }
        }

        $this->assign('users', $users);
        $this->assign('list', $list);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign("getData", $_GET);
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看订单产品记录跟踪列表');
        $this->display();

    }

    /**
     * 处理编辑订单
     */
    public function edit_order_post ()
    {
        $orderId = I('get.id');
        $order = D("Order/Order")->find($orderId);
        F('order_item_by_order_id_cache' . $orderId, null);
        if ( isset($_POST['action']) && $_POST['action'] == 'delete_attr' ) {
            //因为要添加权限，所以先写到这个控制器了。
            $itemId = I('post.order_attr_id');
            if ( $orderId && $itemId ) {
                $deleteData = D("Order/OrderItem")->find($itemId);
                $comment = '删除产品属性：' . json_encode($deleteData);
                D("Order/OrderItem")->where('id_order_item=' . $itemId)->delete();
//                D('Order/Order')->where('id_order='.$orderId)->save(array('price_total'=>$order['price_total']-$deleteData['total']));
                D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'], 3, $comment);
            }
            F('order_item_by_order_id_cache' . $orderId, null);
            exit();
        }
        if ( IS_POST ) {
        //如果勾选了问题件 储存问题件修改的前后信息  --Lily 2017-11-21
        if(isset($_POST['is_question_order']) && $_POST['is_question_order']==1){
            $before = M("Order")->where("id_order=".$orderId)
                    ->select();
            $before['product'] = D('Order/OrderItem')->get_item_list($orderId);
            $dataP['id_department'] = $before[0]['id_department'];
            $dataP['question_order_before'] = json_encode($before);//获取修改前的信息
            $dataP['question_order_after'] = json_encode($_POST);//修改后的信息
            $dataP['id_user'] = $_SESSION['ADMIN_ID'];
            $dataP['create_time'] = date("Y-m-d H:i:s");
            M("OrderQuestion")->add($dataP,array(),true); //增加修改前后信息记录到表里
        }
            $data = I('post.');
            if ( isset($data['id_order']) ) {
                $orderRecordData = $order;
                $orderRecordData['orderitem'] = D("Order/OrderItem")->where(['id_order' => $orderId])->select();
                $this->add_product_record(sp_get_current_admin_id(), $des = '编辑订单产品', $orderRecordData, $data, $data['id_order']);
            }

            D('Order/Order')->save($data);
            $product_id = array();
            $set_product_qty = '';
            foreach ($data['option_id'] as $key => $val) {
                $product_id[] = $key;
                $temp = array();

                foreach ($val as $k => $v) {
                    foreach ($v as $kk => $vv) {
                        $temp[$kk][] = $vv;
                    }
                }
                $product = D('Product/Product')->field('title,inner_name,sale_price')->where('id_product=' . $key)->find();
                foreach ($temp as $psk => $psv) {

                    $option_value = asort($psv);
//                    $sku_option_value = M('ProductOptionValue')->where(array('id_product_option_value'=>array('IN',$psv)))->getField('title',true);
                    $option_value = implode(',', $psv);
                    $product_sku = D('Product/ProductSku')->where("status=1 and option_value='$option_value' and id_product=$key")->order('id_product_sku desc')->find();
                    if ( $data['number'][$key][$psk] ) {
                        $data['number'][$key][$psk] = str_replace(
                            array('１', '２', '３', '４', '５', '６', '７', '８', '９', '０'),
                            array(1, 2, 3, 4, 5, 6, 7, 8, 9, 0),
                            $data['number'][$key][$psk]
                        );
                        $set_product_qty .= ' 产品：' . $key . ' 数量： ' . $data['number'][$key][$psk];
                    }
                    $item_result = D('Order/OrderItem')->where('id_product=' . $key . ' and id_product_sku=' . $product_sku['id_product_sku'] . ' and id_order=' . $data['id_order'])->find();
                    $item_data['id_order'] = $data['id_order'];
                    $item_data['id_product'] = $key;
                    $item_data['id_product_sku'] = $product_sku['id_product_sku'];
                    $item_data['sku'] = $product_sku['sku'];
                    $item_data['sku_title'] = $product_sku['title'];
                    $item_result2 = D('Order/OrderItem')->where('id_product=' . $key . ' and id_order=' . $data['id_order'])->find();
                    $item_data['sale_title'] = $item_result2['sale_title'];
                    $item_data['product_title'] = $product['title'];
                    $item_data['quantity'] = $data['number'][$key][$psk];
                    $item_data['price'] = $product['sale_price'];
                    $item_data['total'] = $product['sale_price'] * $data['number'][$key][$psk];
                    $item_data['attrs'] = serialize($psv);
//                    $item_data['attrs_title'] = serialize($sku_option_value);
                    if ( array_keys($data['order_item_id'][$key])[$psk] == $psk ) {
                        D('Order/OrderItem')->where('id_order_item=' . $data['order_item_id'][$key][$psk])->data($item_data)->save();
                    } else {
                        if ( $product_sku['id_product_sku'] == $item_result['id_product_sku'] || empty($data['number'][$key][$psk]) ) continue;
                        D('Order/OrderItem')->data($item_data)->add();
                    }
                    if ( $data['number'][$key][$psk] == 0 ) {
                        D('Order/OrderItem')->where('id_order_item=' . $data['order_item_id'][$key][$psk])->delete();
                    }
                }
            }
            $lastval = 0;
            $the_key = 0;
            foreach ($data['pro_id'] as $pro_key => $pro_val) {
                if ( $pro_val == $lastval ) {
                    $the_key++;
                } else {
                    $lastval = $pro_val;
                    $the_key = 0;
                }
                if ( $data['qty' . $pro_val] ) {
                    $data['qty' . $pro_val] = str_replace(
                        array('１', '２', '３', '４', '５', '６', '７', '８', '９', '０'),
                        array(1, 2, 3, 4, 5, 6, 7, 8, 9, 0),
                        $data['qty' . $pro_val]
                    );
                    $set_product_qty .= ' 产品：' . $pro_val . ' 数量： ' . $data['number'][$pro_val][$the_key];
                }
                if ( !in_array($pro_val, $product_id) ) {
                    $other_product = D('Product/Product')->field('title,inner_name,sale_price')->where('id_product=' . $pro_val)->find();
                    $item_id = $data['order_item_id'][$pro_val][$the_key];
                    $other_item_data['total'] = $other_product['sale_price'] * $data['number'][$pro_val][$the_key];
                    $other_item_data['quantity'] = $data['number'][$pro_val][$the_key];
                    D('Order/OrderItem')->where('id_order_item=' . $data['order_item_id'][$pro_val][$the_key])->data($other_item_data)->save();
                }

                if ( $data['number'][$pro_val][$the_key] == 0 ) {
                    D('Order/OrderItem')->where('id_order_item=' . $data['order_item_id'][$pro_val][$the_key])->delete();
                }
            }
            D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'], 2, '编辑订单属性（' . $set_product_qty . '） 订单总价格:' . $data['price_total']);
            add_system_record(sp_get_current_admin_id(), 2, 4, '编辑DF订单属性');
            F('order_item_by_order_id_cache' . $orderId, null);
            if ( $order['id_order_status'] == OrderStatus::OUT_STOCK ) {
                if ( $order['id_zone'] != 9 )  //id_zone 为9是越南地区订单
                {
                    //先进行转寄仓匹配
                    $result_one = UpdateStatusModel::match_forward_order($orderId);

                    if ( $result_one['flag'] ) {
                        UpdateStatusModel::into_forward_order($orderId, $result_one['data']);
                        $this->success("订单属性保存成功;匹配转寄仓数据成功！", U('Index/edit_order', array('id' => $orderId)));
                        die;
                    }
                    //匹配该缺货订单
                    $res_two = UpdateStatusModel::check_stock($order['id_order']); //进行有效库存匹配
                    if ( $res_two['flag'] ) {
                        $update_data ['id_warehouse'] = $res_two['id_warehouse'];
                        $update_data ['id_order_status'] = OrderStatus::UNPICKING; //未配货
                        $res_three = D("Order/Order")->where(array('id_order' => $orderId))->save($update_data);
                        if ( $res_three ) {
                            //更新状态成功进行加在单处理
                            UpdateStatusModel::add_warehouse_product_preout($order['id_order']);
                            D("Order/OrderRecord")->addHistory($orderId, OrderStatus::UNPICKING, 3, '订单列表-缺货订单修改属性，匹配有效库存成功！');
                            //$this->success($message, U('Purchase/order/edit_order', array('id' => $orderId)),3);
                            $this->success("订单数据保存成功;属性修改成功;匹配有效库存成功，更新订单状态成功！", U('Index/edit_order', array('id' => $orderId)));
                            die;
                        } else {
                            D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'], 2, '订单列表-编辑属性，匹配库存成功，修改订单状态失败！');
                            //$this->success($message, U('Purchase/order/edit_order', array('id' => $orderId)),3);
                            $this->success("订单数据保存成功;属性修改成功;匹配有效库存成功，更新订单状态失败,请稍后重试！", U('Index/edit_order', array('id' => $orderId)));
                            die;
                        }
                    } else {
                        D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'], 2, '订单列表-编辑属性，匹配有效库存失败！');
                        // $this->success($message, U('Purchase/order/edit_order', array('id' => $orderId)),3);
                        $this->success("订单数据保存成功;属性修改成功;匹配有效库存失败！", U('Index/edit_order', array('id' => $orderId)));
                        die;
                    }
                } else {
                    $res_two = UpdateStatusModel::check_stock($order['id_order']); //进行有效库存匹配
                    if ( $res_two['flag'] ) {
                        $update_data ['id_warehouse'] = $res_two['id_warehouse'];
                        $update_data ['id_order_status'] = OrderStatus::UNPICKING; //未配货
                        $res_three = D("Order/Order")->where(array('id_order' => $orderId))->save($update_data);
                        if ( $res_three ) {
                            //更新状态成功进行加在单处理
                            UpdateStatusModel::add_warehouse_product_preout($order['id_order']);
                            D("Order/OrderRecord")->addHistory($orderId, OrderStatus::UNPICKING, 3, '订单列表-缺货订单修改属性，匹配有效库存成功！');
                            //$this->success($message, U('Purchase/order/edit_order', array('id' => $orderId)),3);
                            $this->success("订单数据保存成功;属性修改成功;匹配有效库存成功，更新订单状态成功！", U('Index/edit_order', array('id' => $orderId)));
                            die;
                        } else {
                            D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'], 2, '订单列表-编辑属性，匹配库存成功，修改订单状态失败！');
                            //$this->success($message, U('Purchase/order/edit_order', array('id' => $orderId)),3);
                            $this->success("订单数据保存成功;属性修改成功;匹配有效库存成功，更新订单状态失败,请稍后重试！", U('Index/edit_order', array('id' => $orderId)));
                            die;
                        }
                    } else {
                        D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'], 2, '订单列表-编辑属性，匹配有效库存失败！');
                        // $this->success($message, U('Purchase/order/edit_order', array('id' => $orderId)),3);
                        $this->success("订单数据保存成功;属性修改成功;匹配有效库存失败！", U('Index/edit_order', array('id' => $orderId)));
                        die;
                    }
                }
            } else {
                D('Order/OrderStatus')->check_order_statistics(3);//审核统计
                $this->success("保存完成！", U('Index/edit_order', array('id' => $orderId)));
            }


        } else {
            $this->error("参数不正确！");
        }
    }

    /**
     * 单个填写物流跟踪号发货，修改订单状态为发货
     */
    public function delivery ()
    {
        $orderId = (int)$_POST['order_id'];
        if ( $_POST['track_number'] && $orderId ) {
            $getTrackNumber = explode(',', str_replace('，', ',', $_POST['track_number']));
            $trackNumber = D("Order/OrderShipping")->field('track_number')->where(array('track_number' => array('IN', $getTrackNumber)))->find();
            if ( !$trackNumber ) {
                D("Order/OrderRecord")->addHistory($orderId, 8, 4, '导入运单号 ' . $track_number);
                $return = D("Order/OrderShipping")->updateShipping($orderId, $getTrackNumber, $_POST['order_remark']);
            } else {
                $implodeTraNu = $trackNumber ? implode(',', $trackNumber) : '';
                $return = array('status' => 0, 'message' => $implodeTraNu . '此跟踪号已经使用。');
            }
        } else {
            $return = array('status' => 0, 'message' => '订单号或订单ID不能为空。');
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '填写物流跟踪号发货，修改DF订单状态为发货');

        echo json_encode($return);
        exit();
    }

    /**
     * 要求取消订单
     */
    public function cancelOrder ()
    {
        try {
            $orderId = I('post.order_id');
            D("Order/Order")->where('id_order=' . $orderId)->save(array('id_order_status' => 14));
            D("Order/OrderRecord")->addHistory($orderId, 14, 3, '【仓储管理取消】 ' . $_POST['comment']);
            $status = 1;
            $message = '';
        } catch (\Exception $e) {
            $status = 1;
            $message = $e->getMessage();
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '要求取消DF订单');
        echo json_encode(array('status' => $status, 'message' => $message));
    }

    public function export_excel ()
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();

        $column = array(
            '地区', '域名', '订单号', '姓名', '电话号码', '邮箱',
            '产品名和价格', '属性', 'SKU', '总价（NTS）', '产品数量',
            '送货地址', '订单数量', '留言备注', '下单时间', '订单状态', '订单数',
            '发货日期'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        //默认结束时间是当天
        //如果不默认的话会把当天以后发货日期的订单也导出来
        $where = array();
        $time_start = I('get.start_time');
        $time_end = I('get.end_time');
        $_GET['start_time'] = $time_start;
        $_GET['end_time'] = $time_end;
        $status_id = I('get.status_id');

        if ( isset($_GET['user_province']) && $_GET['user_province'] ) {
            $where['province'] = array('EQ', $_GET['user_province']);
        }
        if ( $status_id > 0 ) {
            $where[] = "`id_order_status` = '$status_id'";
        } else {
            $where[] = "`id_order_status` IN (8)";
        }
        if ( $time_start )
            $where[] = "`created_at` >= '$time_start'"; //create_at
        if ( $time_end )
            $where[] = "`created_at` < '$time_end'";
        if ( isset($_GET['id_shipping']) && $_GET['id_shipping'] > 0 ) {
            $where[] = "`id_shipping` = $_GET[id_shipping]";
        }

        $M = new \Think\Model;
        $ordTable = D("Order/Order")->getTableName();
        $ordIteTable = D("Order/OrderItem")->getTableName();

        $model = D('Order/Order');

        //$orderList = $model->field('*')->where($where)->order("delivery_date asc,user_tel DESC,user_name desc,user_email desc")->select();
        $orderList = $M->table($ordTable . ' AS o LEFT JOIN ' . $ordIteTable . ' AS oi ON o.id_order=oi.id_order')->field('o.*')
            ->where($where)->order('oi.id_product,oi.id_product_sku desc')->group('oi.id_order')
//                ->fetchSql(true)
            ->limit(10000)->select();

        $order_item = D('Order/OrderItem');
        foreach ($orderList as $key => $o) {
            $orderList[$key]['products'] = $order_item->get_item_list($o['id_order']);

            /*if (in_array($o['id_order_status'], array(4, 6))) {//修改订单为配货中，并且写入导出记录
                $model->where('id_order=' . $o['id_order'])->save(array('id_order_status' => 5)); // 5 配货中
                D("Order/OrderRecord")->addHistory($o['id'], 5,5, '订单列表导出');
            }*/
        }
        $orders = $orderList;

        $result = D('Order/OrderStatus')->cache(true, 3600)->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int)$statu['id_order_status']] = $statu;
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        $data = array();
        $productSku = D('Common/ProductSku');
        foreach ($orders as $o) {
            $product_name = '';
            $attrs = '';
            $skuString = '';
            $products = $order_item->get_item_list($o['id_order']);
            $web = D('Common/Domain')->field('name')->where(array('id_domain' => $o['id_domain']))->find();
            $qty = 0;
            foreach ($products as $p) {
                $product_name .= $p['product_title'] . "\n";
                $qty += $p['quantity'];
                if ( $p['id_product_sku'] ) {
                    $getSkuObj = $productSku->cache(true, 3600)->find($p['id_product_sku']);
                    $skuString .= $getSkuObj['model'] . '   ';
                } else {
                    $skuString .= '';
                }

                if ( isset($p['order_attrs']) )
                    foreach ($p['order_attrs'] as $a) {
                        unset($a['number']);
                        foreach ($a as $av) {
                            $attrs .= $av['title'] . ' x ';
                        }
                        $attrs .= $p['qty'] . "\n";
                    }
            }
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $address = $o['city'] . ' ' . $o['area'] . ' ' . $o['address'];
            $dataKey = md5($skuString);
            $data[][$dataKey] = array(
                $o['province'], $web['name'], $o['id_increment'], $o['first_name'], $o['tel'], $o['email'],
                $product_name, $attrs, $skuString, $o['price_total'], $qty,
                $address, $o['order_count'], $o['remark'], $o['created_at'], $status_name, $o['order_repeat'],
                ''
            );
            /* $j = 65;
              foreach ($data as $col) {
              $excel->getActiveSheet()->setCellValue(chr($j).$idx, $col);
              ++$j;
              }
              ++$idx; */

            if ( in_array($o['id_order_status'], array(4, 6)) ) {//修改订单为配货中，并且写入导出记录
                //$model->where('id=' . $o['id'])->save(array('status_id' => 5)); // 18 配货中   6为缺货怎么变成未配货
                D("Order/OrderRecord")->addHistory($o['id'], $o['id_order_status'], 5, '导出未配货订单');
            }
        }
        if ( $data ) {
            foreach ($data as $items) {
                if ( is_array($items) ) {
                    foreach ($items as $item) {
                        $j = 65;
                        foreach ($item as $col) {
                            $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                            ++$j;
                        }
                        ++$idx;
                    }
                }
            }
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '导出出货信息表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '出货信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '出货信息表.xlsx');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
    }

    //导出未配货订单
    public function exportsearch ()
    {
        set_time_limit(0);
        $M = new \Think\Model;
        /** @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        $allStatus = $ordModel->getStatusLabel();
        $getFormWhere = $ordModel->form_where($_GET);
        $getFormWhere['status_id'] = array('IN', array(4, 5, 6));
        //$getFormWhere['shipping_id'] = array('NEQ','');
        if ( $_GET['product_id'] ) {
            $M = new \Think\Model;
            $ordName = $ordModel->getTableName();
            $ordIteName = D("Order/OrderItem")->getTableName();
            $findOrder = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where(array('oi.id_product' => $_GET['product_id'], 'o.id_order_status' => array('IN', array(4, 5, 6))))
                ->group('oi.id_order')->limit(5000)->select();
            $allId = array_column($findOrder, 'id_order');
            $getFormWhere['id'] = $allId ? array('IN', $allId) : array('IN', array(0));
        }
        $orderList = $ordModel->where($getFormWhere)
            ->where(array(
                'id' => '10086682'
            ))
            ->order("date_delivery asc, created_at ASC")
            //->fetchSql(true)
            ->select();


        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");//vendor("PHPExcel.PHPExcel.Writer.CSV");
        $objPHPExcel = new \PHPExcel();
        $getField = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC');
        $setRowName = array('日期', '订单号', '状态', '物流公司', '网络渠道', '类型', '订单重量', '运单号', '物流账号', '收件人'
        , '收件人电话', '物品名称', '件数', 'SKU', '属性', '物品数量', '代收款', '地址', '备注');
        foreach ($setRowName as $r => $v) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$r] . '1', $v);
        }
        /* @var $orderItem \Common\Model\OrderItemModel */
        $orderItem = D('Order/OrderItem');
        $ordShiTable = D('Common/OrderShipping')->getTableName();
        $shiTable = D('Common/Shipping')->getTableName();
        $num = 2;
        foreach ($orderList as $k => $v) {
            $orderId = $v['id_order'];
            $products = $orderItem->get_item_list($v['id_order']);

            $address = $v['province'] . ' ' . $v['city'] . ' ' . $v['area'] . ' ' . $v['address'];
            $getShipping = $M->table($ordShiTable . ' AS os left join  ' . $shiTable . ' AS s on os.id_shipping=s.id_shipping ')
                ->field('s.id_shipping,s.title,s.channels,s.account,os.track_number')
                ->where("os.id_order=" . $orderId)->select();
            $shippingName = $getShipping ? $getShipping[0]['title'] : '';
            $channels = $getShipping ? $getShipping[0]['channels'] : '';
            $account = $getShipping ? $getShipping[0]['account'] : '';
            $trackNumber = $getShipping ? array_column($getShipping, 'track_number') : '';
            $trackNumber = $trackNumber ? implode(',', $trackNumber) : '';
            $countPro = 1;
            $getProTotal = count($products);
            foreach ($products as $product) {
                $proTitle = $product['title'] ? $product['title'] : $product['product_title'];
                $proId = $product['id_product'];
                $attrArr = array();
                //if($v['id']=='10080188'){
                //print_r($product);exit();
                //}
                if ( $product['order_attrs'] ) {
                    foreach ($product['order_attrs'] as $pa) {
                        $number = $product['qty']; //$number=$pa['number'];
                        unset($pa['number']);
                        $tempAttrArray = array();
                        foreach ($pa as $pa2) {
                            $tempAttrArray[] = $pa2['value_label'];
                        }
                        $attrArr[] = $tempAttrArray ? implode(' * ', $tempAttrArray) : '';
                    }
                }
                $attr = $attrArr ? implode(' ', $attrArr) : '';
                //$setRowName = array('日期','订单号','状态','物流公司','网络渠道','类型','订单重量','运单号','物流账号','收件人'
                //,'收件人电话','物品名称','件数','SKU','属性','物品数量','代收款','地址','备注');
                $weight = '';
                $sku = isset($product['id_product_sku']) && $product['id_product_sku'] ? D('Common/ProductSku')->field('model')->getField('model') : '';
                $tempOrderId = $getProTotal > 1 ? $v['id_order'] . '-' . $countPro . '/' . $getProTotal : $v['id_order'];
                $bodyRow = array(
                    date('Y-m-d', strtotime($v['created_at'])),
                    $tempOrderId,
                    $allStatus[$v['id_order_status']],
                    $shippingName, $channels,
                    $v['province'], $weight, $trackNumber, $account,
                    $v['name'], $v['tel'],//'\''.
                    $proTitle, $v['order_count'], $sku, $attr, $product['quantity'],
                    $v['price_total'],
                    $address, $v['remark']
                );

                foreach ($bodyRow as $row => $value) {
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$row] . $num, $value);
                }
                $num++;
                $countPro++;
            }
            //if(count($products)==0){$num++;}//没有产品，输出空行
            if ( in_array($v['id_order_status'], array(4, 6)) ) {//修改订单为配货中，并且写入导出记录
                //$ordModel->where('id=' . $orderId)->save(array('status_id' => 5));// 18 配货中
                $comment = '导出未配货订单';
                D("Order/OrderRecord")->addHistory($orderId, $v['id_order_status'], 5, $comment);
            }
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '导出未配货订单');
        $objPHPExcel->getActiveSheet()->setTitle('order');
        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('YmdHis') . '.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    /**
     * 域名订单列表，统计昨天，今天的订单数据
     */
    public function domian_order ()
    {
        $today = date('Y-m-d 00:00:00');//今天
        $yesday = date('Y-m-d 00:00:00', strtotime('-1 day'));//昨天
        $where = array();
        $dep = $_SESSION['department_id'];

        //选择域名
        if ( isset($_GET['domain_id']) && $_GET['domain_id'] ) {
            $where['id_domain'] = $_GET['domain_id'];
        }

        //选择部门
        if ( isset($_GET['department_id']) && $_GET['department_id'] ) {
            $where['id_department'] = $_GET['department_id'];
        } else {
            $where['id_department'] = array('IN', $dep);
        }

        //排序
        $sort = isset($_GET['sort']) && $_GET['sort'] == 'asc' ? 'asc' : 'desc';
        $order_by = 'today ' . $sort;
        if ( isset($_GET['order_by']) ) {
            switch ($_GET['order_by']) {
                case 'yesday':
                    $order_by = 'yesday ' . $sort;
                    break;
                case 'order_count':
                    $order_by = 'order_count ' . $sort;
                    break;
                case 'today':
                    $order_by = 'today ' . $sort;
            }
        }
        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";//货到付款订单，过滤已经支付的

        $field = "count(*) as order_count,SUM(IF(created_at >= '" . $today . "',1,0)) as today,SUM(IF(created_at >= '" . $yesday . "' and created_at < '" . $today . "',1,0)) as yesday,id_users,id_domain";
        $order_count = M('Order')->field($field)->where($where)->group('id_domain')->select();
        $page = $this->page(count($order_count), 20);
        $order = M('Order')->field($field)->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->group('id_domain')->order($order_by)->select();

        foreach ($order as $k => $v) {
            $order[$k]['domian'] = M('Domain')->where(array('id_domain' => $v['id_domain']))->getField('name');
            $users = M('Order')->where(array('id_domain' => $v['id_domain'], 'created_at' => array('EGT', $today)))->getField('id_users', true);
            $userids = array_unique($users);
            $order[$k]['name'] = M('Users')->where(array('id' => $userids[0]))->getField('user_nicename');
        }

        $order_today_count = M('Order')->where(array('created_at' => array('EGT', $today)))->where($where)->count();
        $daytime = array();
        $daytime[] = array('EGT', $yesday);
        $daytime[] = array('LT', $today);
        $yhwhere[] = array('created_at' => $daytime);
        $order_yes_count = M('Order')->where($yhwhere)->where($where)->count();
        $department = M('Department')->field('id_department,title')->where(array('id_department' => array('IN', $dep), 'type' => 1))->select();
        $domain = M('Domain')->field('id_domain,name')->where(array('id_department' => array('IN', $dep)))->order('name ASC')->select();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看域名订单列表');
        $this->assign('order', $order);
        $this->assign("page", $page->show('Admin'));
        $this->assign('department', $department);
        $this->assign('domain', $domain);
        $this->assign('order_count', $order_today_count);
        $this->assign('order_yes_count', $order_yes_count);
        $this->display();
    }


    public function product_report ()
    {
        $getData = I('get.', "trim");
        $orderitemT = M('orderItem')->getTableName();
        $productT = M('product')->getTableName();
        $domainT = M('domain')->getTableName();
        $where = [];
        $where['o.id_order_status'] = array('in', OrderStatus::get_effective_status());
        if ( $getData['id_department'] ) {
            $where['o.id_department'] = $getData['id_department'];
        }
        if ( $getData['start_time'] ) {
            $start_time = date('Y-m-d', strtotime($getData['start_time']));
        } else {
            $start_time = date('Y-m-d');
            $getData['start_time'] = $start_time;
        }
        $start_time_arr[] = array('EGT', $start_time);
        $start_time_arr[] = array('LT', ($start_time . ' 23:59:59'));
        $where['o.created_at'] = $start_time_arr;
        if ( $getData['keyword'] ) {
            if ( $getData['keywordtype'] == "p.inner_name" ) {
                $getData['typeval'] = 1;
            }
            if ( $getData['keywordtype'] == "p.id_product" ) {
                $getData['typeval'] = 2;
            }
            if ( $getData['keywordtype'] == "d.name" ) {
                $getData['typeval'] = 3;
            }

            $getData['keyword'] = trim($getData['keyword']);
            if ( $getData['ismore'] ) {
                $where[$getData['keywordtype']] = array('like', "%{$getData['keyword']}%");
            } else {
                $where[$getData['keywordtype']] = $getData['keyword'];
            }
        }
        $list = M('order o')->join("{$orderitemT} oi on oi.id_order=o.id_order", 'left')
            ->join("{$productT} p on p.id_product =oi.id_product ", 'left')
            ->join("{$domainT} d on d.id_domain =o.id_domain ", 'left')
            ->field("oi.id_product,count(DISTINCT(o.id_order)) as ordercnt,o.created_at,d.name as domain,o.id_users,o.id_department,p.inner_name,o.id_zone")
            ->where($where)->group("oi.id_product")->having('ordercnt>=20')->select();
        $userlist = M('users')->where(array('user_status' => 1))->getField('id,user_nicename');
        $departmentList = M('department')->where(array('type' => 1))->order('title')->getField('id_department,title');
        if ( $getData['isexport'] == 1 ) {
            $column = "日期,域名,内部名,产品ID,部门,出单量\n";
            foreach ($list as $item) {
                $create_at = date('Y-m-d', strtotime($item['created_at']));
                $column .= $create_at . "," .
                    $item['domain'] . "," .
                    $item['inner_name'] . "," .
                    $item['id_product'] . "," .
                    $departmentList[$item['id_department']] . "," .
                    $item['ordercnt'] . "\n";
            }
            $filename = date('Ymd') . '.csv'; //设置文件名
            $this->export_csv($filename, iconv("UTF-8", "GBK//IGNORE", $column)); //导出
            exit;

        }
        $this->assign('getData', $getData);
        $this->assign('repList', $list);
        $this->assign('departmentList', $departmentList);
        $this->assign('userlist', $userlist);
        $this->display();
    }


    protected function export_csv ($filename, $data)
    {
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=" . $filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }

    //临时导出订单信息
    public function collect_order_list ()
    {
        if ( IS_POST ) {
            $domain = I("post.domain");
            if ( !$domain ) $this->error('域名不能为空');
            $start_time = I("post.start_time");
            $end_time = I("post.end_time");
            $country = I("post.country");
            $where = [];
            if ( $start_time && $end_time ) {
                $where['lastdate'] = ['BETWEEN', [$start_time . ' 00:00:00', $end_time . ' 23:59:59']];
            }
            $domain_list = preg_split("~[\r\n]~", $domain, -1, PREG_SPLIT_NO_EMPTY);
            $where['domain'] = ['IN', implode(",", $domain_list)];
            $wl = new WlRepositoryController();
            $wl->lists($where, $country);
        }
        $this->display();
    }

    //7天问题单
    public function day7question ()
    {
        //7天内的缺货单和未发货单

        if($_GET['type'] == 10){
            $end_time = date('Y-m-d 00:00:00', strtotime('-10 days'));
        }elseif($_GET['type'] == 5){
            $end_time = date('Y-m-d 00:00:00', strtotime('-5 days'));
        } elseif($_GET['type'] == 7) {
            $end_time = date('Y-m-d 00:00:00', strtotime('-7 days'));
        }
        if(!$_GET['type']) {
            $end_time = date('Y-m-d 00:00:00', strtotime('-5 days'));
            $_GET['type'] = 5;
        }

        $where = [
            'o.created_at' => ['lt', $end_time],
            'o.id_order_status' => ['IN',['6','5','7','18']]
        ];
        $where['o.id_department'] = array("IN",$_SESSION['department_id']);
        $order_list = $this->order->alias('o')->field('o.id_department,dt.title as dt_title, count( if(o.id_order_status = 6,1,null)) as qty,count( if(o.id_order_status = 5 OR o.id_order_status = 7 OR o.id_order_status = 18,1,null)) as nosend')
            ->join('__DEPARTMENT__ dt ON (o.id_department = dt.id_department)', 'LEFT')
            ->where($where)
            ->order("qty DESC")
            ->group('o.id_department')
            ->select();
//        echo $this->order->_sql();exit;

        /*$order_item = D('Order/OrderItem');
         * foreach ($order_list as $key => $o) {
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
        }*/

        foreach ($order_list as $key => $o) {
            $stockout_sum +=    $o['qty'];
            $unshipped_sum +=   $o['nosend'];
        }
        $this->assign('stockout_sum', $stockout_sum);
        $this->assign('unshipped_sum', $unshipped_sum);
        $this->assign('order_list', $order_list);
        $this->assign('end_time', $end_time);
        $this->assign('type', $_GET['type']);
        $this->display();

    }
}
