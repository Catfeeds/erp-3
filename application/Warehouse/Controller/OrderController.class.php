<?php
/**
 * 仓库模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Warehouse\Controller
 */
namespace Warehouse\Controller;
use Common\Controller\AdminbaseController;
use Common\Lib\Currency;
use Order\Lib\OrderStatus;
use SystemRecord\Model\SystemRecordModel;
use Order\Model\UpdateStatusModel;
class OrderController extends AdminbaseController {
    protected $Warehouse, $orderModel, $orderoutModel;
    public function _initialize() {
        parent::_initialize();
        $this->Warehouse = D("Common/Warehouse");
        $this->orderModel = D("Order/Order");
        $this->orderoutModel = D("Order/Orderout");
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row']?$_SESSION['set_page_row']:20;
    }
    
    /**
     * 订单列表页
     */
    public function order_list() {
        $M = new \Think\Model;
        $order_model = $this->orderModel;
        $where = $order_model->form_where($_GET,'o.');
//        if(!isset($_GET['start_time']) && !isset($_GET['end_time'])){
//            $_GET['start_time']=date('Y-m-d 00:00',  strtotime('-1months'));
//            $_GET['end_time']=date('Y-m-d 23:59',  time());
//            $created_at_array[] = array('EGT', $_GET['start_time']);
//            $created_at_array[] = array('LT', $_GET['end_time']);
//            $where['o.created_at'] = $created_at_array;
//       }
        //所属仓库只能看到所属仓库的订单
        $belong_ware_id = $_SESSION['belong_ware_id'];
        if(isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where['id_warehouse'] = array('EQ',$_GET['id_warehouse']);
        } else {
            if (count($belong_ware_id) != 1 || (count($belong_ware_id) == 1 && $belong_ware_id[0] != 1)) {
                $hwhere['id_warehouse'] = array('IN', $belong_ware_id);
                $where['id_warehouse'] = array('IN', $belong_ware_id);
            }
        }

        /*下单时间初始化*/
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
            $get_data['start_time']=$_GET['start_time'];
            $get_data['end_time']=$_GET['end_time'];            
        }
        else
        {
            if (!$_GET['start_time'] && !$_GET['end_time'])
            {
                $get_data['start_time'] = date('Y-m-d',  strtotime('-7days'));
                $get_data['end_time'] = date('Y-m-d 23:59:59',time());
                $created_at_array[] = array('EGT', $get_data['start_time']);
                $created_at_array[] = array('LT', $get_data['end_time']);
            }
        }
        $where['o.created_at'] = $created_at_array;

        $id_order_status = I('get.status_id/i');
        if ($id_order_status <= 0) {
            $id_order_status = \Order\Lib\OrderStatus::get_effective_status();
            $id_order_status = array_merge($id_order_status,array(14));
            $where['o.id_order_status'] = array('IN', $id_order_status);
        }
        if(isset($_GET['id_classify']) && $_GET['id_classify']) {
            $ordIteName = M("OrderItem")->getTableName();
            $ordName = M("Order")->getTableName();
            $proName = M('Product')->getTableName();

            $product_ids = M('Product')->field('id_product')->where(array('id_classify' => $_GET['id_classify']))->select();
            $pro_where=$where;
            $product_id = array_column($product_ids, 'id_product');
            $product_id ? $pro_where['oi.id_product'] = array('IN', $product_id) : $pro_where['oi.id_product'] = array('IN', array(0));
            $pro_where['o.id_order_status'] = array('IN', array_merge(\Order\Lib\OrderStatus::get_effective_status(),array(14)));

            $class_order_id = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where($pro_where)->group('id_order')->select();

            if ($_GET['id_classify'] == 3) {
                if($class_order_id) {
                    $orderIds = array();
                    foreach ($class_order_id as $val) {
                        $order_item = $M->table($ordIteName . ' AS oi LEFT JOIN ' . $proName . ' AS p ON oi.id_product=p.id_product')
                            ->where(array('id_order' => $val['id_order']))
                            ->getField('id_classify', true);
                        if (!in_array('1', $order_item) && !in_array('2', $order_item)) {
                            $orderIds[] = $val['id_order'];
                        }
                    }
                    $where['o.id_order'] = array('IN', $orderIds);
                } else {
                    $where['o.id_order'] = array('IN', array(0));
                }
            } else {
                $order_id = array_column($class_order_id, 'id_order');
                if($order_id) {
                    $where['o.id_order'] = array('IN', $order_id);
                } else {
                    $where['o.id_order'] = array('IN', array(0));
                }
            }
        }
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = $_GET['zone_id'];
        }
        if(isset($_GET['sku']) && $_GET['sku']) {
            $where['oi.sku'] = I('get.sku');//array('LIKE',I('get.sku').'%');//I('get.sku');
            //$where['oi.id_order'] = array('NEQ','');
        }
        if ($_GET['shipping_start_time'] or $_GET['shipping_end_time']) {

            $date_delivery_array = array();
            if ($_GET['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_GET['shipping_start_time']);
            if ($_GET['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_GET['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');

        //$formData['product_type'] = $baseSql->getFieldGroupData('product_type');
        $form_data['track_status'] = D('Order/OrderShipping')->field('summary_status_label as track_status')
                        ->group('summary_status_label')->cache(true, 12000)->select();
        foreach ($form_data['track_status'] as $k=>$v) {
            if($v['track_status'] == '') {
                $form_data['track_status'][$k]['track_status'] = '空';
            }
        }
        /*if(empty($_GET['status_id'])){
            $where['id_order_status'] = array('IN',array(4, 5, 6, 7, 8, 9, 10, 14,17));
        }*/
        $form_data['shipping'] = D('Common/Shipping')->field('id_shipping,title')
            ->where("status=1")
            ->cache(true, 12000)->select();
        $form_data['domain'] = $domain_model->get_all_domain();


        //今日统计订单 条件
        $today_where = $where;
        $today_where['o.created_at'] = array('EGT', $today_date);
        //$all_domain_total = $order_model->alias('o')->field('count(`id_domain`) as total,id_domain')->where($today_where)->order('total desc')->group('id_domain')->select();
        if (isset($_GET['status_label']) && $_GET['status_label']) {//修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
            if(strip_tags(trim($_GET['status_label'])) == '空') {
                $where['_string'] = "(s.summary_status_label='' or s.summary_status_label is null)";
            } else {
                $where['s.summary_status_label'] = strip_tags(trim($_GET['status_label']));
            }

            if($_GET['sku']){
                $count = $order_model->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)
                    ->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;
                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')
                    ->field('o.*,s.date_signed')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->group('oi.id_order')
                    ->limit($page->firstRow . ',' . $page->listRows)->select();

             } else if((isset($_GET['status']) && $_GET['status']) || $_GET['status'] == '0') {   
                $where['ost.status'] = $_GET['status'];
                $order_result = $this->get_sellt_param($order_model,$where,$today_where);      
                $count = $order_result['count'];
                $page = $order_result['page'];
                $today_total = $order_result['today_total'];
                $order_list = $order_result['order_list'];
            }else if(isset($_GET['inner_name']) && $_GET['inner_name']){
                 // 内部名搜索  ---Lily 2017-11-24
                    $where['p.inner_name'] =array("LIKE",'%'.$_GET['inner_name'].'%');
                    $count = $order_model->alias('o')->field('o.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->join("__PRODUCT__ AS p ON p.id_product=oi.id_product","LEFT")
                    ->where($where)->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                     ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join("__PRODUCT__ AS p ON p.id_product=oi.id_product","LEFT")
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;

                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')->field('o.*')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                     ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join("__PRODUCT__ AS p ON p.id_product=oi.id_product","LEFT")
                    ->where($where)->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
                   
            }else{
                $count = $order_model->alias('o')->field('o.id_order')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;

                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')->field('o.*,s.date_signed')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
            }

        } else {
            if($_GET['sku']){
                $count = $order_model->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->group('oi.id_order')
                    ->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($today_where)
                    ->group('oi.id_order')
                    ->count();
                $today_total = $today_total?count($today_total):0;
                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')->field('o.*,os.date_signed')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->group('oi.id_order')
                    ->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)
                    ->select();

            }else if((isset($_GET['status']) && $_GET['status']) || $_GET['status'] == '0') {   
                $where['ost.status'] = $_GET['status'];
                $order_result = $this->get_sellt_param($order_model,$where,$today_where);      
                $count = $order_result['count'];
                $page = $order_result['page'];
                $today_total = $order_result['today_total'];
                $order_list = $order_result['order_list'];
            }else if(isset($_GET['inner_name']) && $_GET['inner_name']){ 
                // 内部名搜索  ---Lily 2017-11-24
                    $where['p.inner_name'] =array("LIKE",'%'.$_GET['inner_name'].'%');
                    $count = $order_model->alias('o')->field('o.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join("__PRODUCT__ AS p ON p.id_product=oi.id_product","LEFT")
                    ->where($where)->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join("__PRODUCT__ AS p ON p.id_product=oi.id_product","LEFT")
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;

                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')->field('o.*')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join("__PRODUCT__ AS p ON p.id_product=oi.id_product","LEFT")
                    ->where($where)->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
             }else{
                $count = $order_model->alias('o')->where($where)->count();
                $today_total = $order_model->alias('o')->where($today_where)->count();
                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')
                    ->where($where)
                    ->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
            }
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $select = array();
        $qty_count = 0;
        foreach ($order_list as $key => $o) {
            
            $order_list[$key]['weight'] = M('OrderShipping')->where(array('id_order'=>$o['id_order']))->getField('weight');
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
//            var_dump($order_list[$key]['products']);die();
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $order_list[$key]['shipping_name'] = D('Common/Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
            $wave_number = M('OrderWave')->where(array('id_order'=>$o['id_order']))->getField('wave_number');
            $order_list[$key]['wave_number'] = $wave_number;
            $order_list[$key]['print_waybill_num'] = M('OrderWavePrint')->where(array('wave_number'=>$wave_number))->getField('print_waybill_num');
            $quantity = $order_item->get_product_count($o['id_order']);
            $qty_count = $qty_count+=$quantity;
            $order_settl = M('OrderSettlement')->field('status')->where(array('id_order'=>$o['id_order']))->find();
            switch ($order_settl['status']) {
                case 0:
                    $order_list[$key]['status_name'] = ($order_settl['status']===null)?'':'未结款';
                    break;
                case 1: $order_list[$key]['status_name'] = '结款中';
                    break;
                case 2: $order_list[$key]['status_name'] = '已结款';
                    break;
            }
        }
        $hwhere['forward'] = 0;
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where()->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department = M('Department')->where(array('type'=>1))->order('title ASC')->select();
        $zone = M('Zone')->select();
        $classify = M('ProductClassify')->cache(true, 86400)->select();
        $this->assign('zone',$zone);
        $this->assign('department',$department);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("today_total", $today_total);
        $this->assign("warehouse", $warehouse);
        $this->assign('start_time',$get_data['start_time']);
        $this->assign('end_time',$get_data['end_time']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus')->field('id_order_status,title')->where(array('status'=>1,'id_order_status'=>array('NOT IN',array(25,26))))->select();
        $status_model = array_column($status_model,'title','id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看仓库订单列表');
        $this->assign('status_list',$status_model);
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->assign("order_total", $count);
        $this->assign("order_list", $order_list);
        $this->assign("page", $page->show('Admin'));
        $this->assign('qty_count',$qty_count);
        $this->assign('classify',$classify);
        $this->display();
    }

    /**
     * 出库订单列表页
     */
    public function order_out_list()
    {
        /** @var \Order\Model\OrderoutModel $orderout_model */
        $order_model = $this->orderoutModel;
        $M = new \Think\Model;
        $where = $order_model->get_data($_GET,'o.');

        /*下单时间初始化*/
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
                $get_data['start_time'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time'] = date('Y-m-d H:i',time());
                $created_at_array[] = array('EGT', $get_data['start_time']);
                $created_at_array[] = array('LT', $get_data['end_time']);
            }
        }
        $where['o.created_at'] = $created_at_array;
        $id_order_status = I('get.status_id/i');
        if ($id_order_status <= 0)
        {
            $id_order_status = \Order\Lib\OrderStatus::get_effective_status();
            $id_order_status = array_merge($id_order_status,array(14));
            $where['o.id_order_status'] = array('IN', $id_order_status);
        }
        if (isset($_GET['zone_id']) && $_GET['zone_id'])
        {
            $where['o.id_zone'] = $_GET['zone_id'];
        }
        if(isset($_GET['sku']) && $_GET['sku'])
        {
            $where['oi.sku'] = I('get.sku');
        }
        if ($_GET['shipping_start_time'] or $_GET['shipping_end_time'])
        {
            $date_delivery_array = array();
            if ($_GET['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_GET['shipping_start_time']);
            if ($_GET['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_GET['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');

        $form_data['track_status'] = D('Order/OrderShipping')->field('summary_status_label as track_status')
            ->group('summary_status_label')->cache(true, 12000)->select();
        foreach ($form_data['track_status'] as $k=>$v) {
            if($v['track_status'] == '') {
                $form_data['track_status'][$k]['track_status'] = '空';
            }
        }

        /*if(empty($_GET['status_id'])){
            $where['id_order_status'] = array('IN',array(4, 5, 6, 7, 8, 9, 10, 14,17));
        }*/
        $form_data['shipping'] = D('Common/Shipping')->field('id_shipping,title')
            ->where("status=1")
            ->cache(true, 12000)->select();
        $form_data['domain'] = $domain_model->get_all_domain();


        //今日统计订单 条件
        $today_where = $where;
        $today_where['o.created_at'] = array('EGT', $today_date);

        if (isset($_GET['status_label']) && $_GET['status_label'])
        {
            if(strip_tags(trim($_GET['status_label'])) == '空')
            {
                $where['_string'] = "(s.status_label='' or s.status_label is null)";
            }
            else
            {
                $where['s.status_label'] = strip_tags(trim($_GET['status_label']));
            }

            if($_GET['sku'])
            {
                $count = $order_model->alias('o')->field('oi.id_orderout,o.id_order')
                    ->join('__ORDER_OUTITEM__ oi on o.id_orderout = oi.id_orderout','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)
                    ->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')->field('oi.id_orderout,o.id_order')
                    ->join('__ORDER_OUTITEM__ oi on o.id_orderout = oi.id_orderout','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;
                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')
                    ->field('o.*,s.date_signed')
                    ->join('__ORDER_OUTITEM__ oi on o.id_orderout = oi.id_orderout','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->group('o.id_order')
                    ->limit($page->firstRow . ',' . $page->listRows)->select();

            }
            else if((isset($_GET['status']) && $_GET['status']) || $_GET['status'] == '0')
            {
                $where['ost.status'] = $_GET['status'];
                $order_result = $this->get_sellt_param_out($order_model,$where,$today_where);
                $count = $order_result['count'];
                $page = $order_result['page'];
                $today_total = $order_result['today_total'];
                $order_list = $order_result['order_list'];
            }
            else
            {
                $count = $order_model->alias('o')->field('o.id_orderout,o.id_order')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;

                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')->field('o.*,s.date_signed')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
            }

        }
        else
        {
            if($_GET['sku'])
            {
                $count = $order_model->alias('o')->field('oi.id_orderout,o.id_order')
                    ->join('__ORDER_OUTITEM__ oi on o.id_orderout = oi.id_orderout','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->group('o.id_order')
                    ->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')->field('oi.id_orderout,o.id_order')
                    ->join('__ORDER_OUTITEM__ oi on o.id_orderout = oi.id_orderout','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($today_where)
                    ->group('o.id_order')
                    ->count();
                $today_total = $today_total?count($today_total):0;
                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')->field('o.*,os.date_signed')
                    ->join('__ORDER_OUTITEM__ oi on o.id_orderout = oi.id_orderout','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->group('oi.id_orderout')
                    ->order("o.id_orderout DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)
                    ->select();
            }
            else if((isset($_GET['status']) && $_GET['status']) || $_GET['status'] == '0')
            {
                $where['ost.status'] = $_GET['status'];
                $order_result = $this->get_sellt_param_out($order_model,$where,$today_where);
                $count = $order_result['count'];
                $page = $order_result['page'];
                $today_total = $order_result['today_total'];
                $order_list = $order_result['order_list'];
            }
            else
            {
                $count = $order_model->alias('o')->where($where)->count();
                $today_total = $order_model->alias('o')->where($today_where)->count();
                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')
                    ->where($where)
                    ->order("o.id_orderout DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
            }
        }
        /** @var \Order\Model\OrderOutitemModel $order_item */
        $order_item = D('Order/OrderOutitem');
        $qty_count = 0;
        foreach ($order_list as $key => $o)
        {
            $order_list[$key]['weight'] = M('OrderShipping')->where(array('id_order'=>$o['id_order']))->getField('weight');
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_orderout']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $order_list[$key]['shipping_name'] = D('Common/Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
            $wave_number = M('OrderWave')->where(array('id_order'=>$o['id_order']))->getField('wave_number');
            $order_list[$key]['wave_number'] = $wave_number;
            $order_list[$key]['print_waybill_num'] = M('OrderWavePrint')->where(array('wave_number'=>$wave_number))->getField('print_waybill_num');
            $quantity = $order_item->get_product_count($o['id_order']);
            $qty_count = $qty_count+=$quantity;
            $order_settl = M('OrderSettlement')->field('status')->where(array('id_order'=>$o['id_order']))->find();
            switch ($order_settl['status'])
            {
                case 0:
                    $order_list[$key]['status_name'] = ($order_settl['status']===null)?'':'未结款';
                    break;
                case 1: $order_list[$key]['status_name'] = '结款中';
                    break;
                case 2: $order_list[$key]['status_name'] = '已结款';
                    break;
            }
        }

        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department = M('Department')->where('type=1')->select();
        $zone = M('Zone')->select();

        /*下单时间设置*/
        if (isset($get_data['start_time']) && isset($get_data['start_time']))
        {
            $this->assign('start_time',$get_data['start_time']);
            $this->assign('end_time',$get_data['end_time']);
        }
        $this->assign('zone',$zone);
        $this->assign('department',$department);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("today_total", $today_total);
        $this->assign("warehouse", $warehouse);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus')->field('id_order_status,title')->where('status=1')->select();
        $status_model = array_column($status_model,'title','id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看仓库订单列表');
        $this->assign('status_list',$status_model);
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->assign("order_total", $count);
        $this->assign("order_list", $order_list);
        $this->assign("page", $page->show('Admin'));
        $this->assign('qty_count',$qty_count);
        $this->display();
    }

    /**
     * 问题件订单列表页
     */
    public function problem_order_list() {
        /** @var \Order\Model\OrderModel $order_model */
        $order_model = $this->orderModel;
        $M = new \Think\Model;
        $where = $order_model->form_where($_GET,'o.');        
        $where['o.id_order_status'] = \Order\Lib\OrderStatus::PROBLEM;
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = $_GET['zone_id'];
        }
        if(isset($_GET['sku']) && $_GET['sku']) {
            $where['oi.sku'] = I('get.sku');
        }
        if ($_GET['shipping_start_time'] or $_GET['shipping_end_time']) {
            $date_delivery_array = array();
            if ($_GET['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_GET['shipping_start_time']);
            if ($_GET['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_GET['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }
        if(isset($_GET['pstatus_name']) && $_GET['pstatus_name']) {
            if(strip_tags(trim($_GET['pstatus_name'])) == '空') {
                $where['_string'] = "(o.problem_name='' or o.problem_name is null)";
            } else {
                $where['o.problem_name'] = strip_tags(trim($_GET['pstatus_name']));
            }
        }
        
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();

        $form_data['track_status'] = D('Order/OrderShipping')->field('summary_status_label as track_status')->group('summary_status_label')->cache(true, 12000)->select();
        foreach ($form_data['track_status'] as $k=>$v) {
            if($v['track_status'] == '') {
                $form_data['track_status'][$k]['track_status'] = '空';
            }
        }

        $form_data['shipping'] = D('Common/Shipping')->field('id_shipping,title')->where("status=1")->cache(true, 12000)->select();

        //今日统计订单 条件
        $today_where = $where;
        $today_where['o.created_at'] = array('EGT', $today_date);

        if (isset($_GET['status_label']) && $_GET['status_label']) {//修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
            if(strip_tags(trim($_GET['status_label'])) == '空') {
                $where['_string'] = "(s.summary_status_label='' or s.summary_status_label is null)";
            } else {
                $where['s.summary_status_label'] = strip_tags(trim($_GET['status_label']));
            }
            if($_GET['sku']){
                $count = $order_model->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)
                    ->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;
                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')
                    ->field('o.*,s.date_signed')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->group('oi.id_order')
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
             } else if((isset($_GET['status']) && $_GET['status']) || $_GET['status'] == '0') {   
                $where['ost.status'] = $_GET['status'];
                $order_result = $this->get_sellt_param($order_model,$where,$today_where);      
                $count = $order_result['count'];
                $page = $order_result['page'];
                $today_total = $order_result['today_total'];
                $order_list = $order_result['order_list'];
            } else{
                $count = $order_model->alias('o')->field('o.id_order')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;

                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')->field('o.*,s.date_signed')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
            }

        } else {
            if($_GET['sku']){
                $count = $order_model->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->group('oi.id_order')
                    ->select();
                $count = $count?count($count):0;
                $today_total = $order_model->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($today_where)
                    ->group('oi.id_order')
                    ->count();
                $today_total = $today_total?count($today_total):0;
                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')->field('o.*,os.date_signed')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->group('oi.id_order')
                    ->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)
                    ->select();

            }else if((isset($_GET['status']) && $_GET['status']) || $_GET['status'] == '0') {   
                $where['ost.status'] = $_GET['status'];
                $order_result = $this->get_sellt_param($order_model,$where,$today_where);      
                $count = $order_result['count'];
                $page = $order_result['page'];
                $today_total = $order_result['today_total'];
                $order_list = $order_result['order_list'];
            }else{
                $count = $order_model->alias('o')->where($where)->count();
                $today_total = $order_model->alias('o')->where($today_where)->count();
                $page = $this->page($count, $this->page);
                $order_list = $order_model->alias('o')
                    ->where($where)
                    ->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
            }
        }

        $problem_name = M('Order')->where(array('id_order_status'=>OrderStatus::PROBLEM))->group('problem_name')->getField('problem_name',true);
        foreach($problem_name as $key=>$val) {
            if($val == '') {
                $problem_name[$key] = '空';
            }
        }

        $order_item = D('Order/OrderItem');
//        $select = array();
        $qty_count = 0;
        foreach ($order_list as $key => $o) {
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $order_list[$key]['shipping_name'] = D('Common/Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
            $wave_number = M('OrderWave')->where(array('id_order'=>$o['id_order']))->getField('wave_number');
            $order_list[$key]['wave_number'] = $wave_number;
            $order_list[$key]['print_waybill_num'] = M('OrderWavePrint')->where(array('wave_number'=>$wave_number))->getField('print_waybill_num');
            $quantity = $order_item->get_product_count($o['id_order']);
            $qty_count = $qty_count+=$quantity;
            $order_settl = M('OrderSettlement')->field('status')->where(array('id_order'=>$o['id_order']))->find();
            switch ($order_settl['status']) {
                case 0:
                    $order_list[$key]['status_name'] = ($order_settl['status']===null)?'':'未结款';
                    break;
                case 1: $order_list[$key]['status_name'] = '结款中';
                    break;
                case 2: $order_list[$key]['status_name'] = '已结款';
                    break;
            }
        }
        
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department = M('Department')->where('type=1')->select();
        $zone = M('Zone')->select();
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();       
        $status_model = D('Order/OrderStatus')->field('id_order_status,title')->where('status=1')->select();
        $status_model = array_column($status_model,'title','id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看仓库问题订单列表');
        $this->assign('zone',$zone);
        $this->assign('department',$department);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("today_total", $today_total);
        $this->assign("warehouse", $warehouse);    
        $this->assign("all_zone", $all_zone);
        $this->assign("order_total", $count);
        $this->assign("order_list", $order_list);
        $this->assign("page", $page->show('Admin'));
        $this->assign('qty_count',$qty_count);
        $this->assign('problem_name',$problem_name);
        $this->assign('status_list',$status_model);
        $this->display();
    }
    /**
     * 转寄仓订单列表
     */
    public function forward_order_list() {
        $order_model = $this->orderModel;
        $M = new \Think\Model;
        $where = $order_model->form_where($_GET,'o.');
        $id_order_status = I('get.status_id/i');
        if ($id_order_status <= 0) {
            $where['o.id_order_status'] = array('IN',array(\Order\Lib\OrderStatus::MATCH_FORWARDING,\Order\Lib\OrderStatus::MATCH_FORWARDED,OrderStatus::MATCH_FINISH,OrderStatus::DELIVERING));
        }
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = $_GET['zone_id'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            unset($where['o.id_warehouse']);
            $new_order_id = M('OrderForward')->where(array('warehouse_id'=>$_GET['id_warehouse']))->getField('new_order_id',true);
            $where['o.id_order'] = $new_order_id ? array('IN',$new_order_id) : array(0);
        }
        if ($_GET['shipping_start_time'] or $_GET['shipping_end_time']) {
            $date_delivery_array = array();
            if ($_GET['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_GET['shipping_start_time']);
            if ($_GET['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_GET['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }

        $form_data = array();

        $form_data['track_status'] = D('Order/OrderShipping')->field('summary_status_label as track_status')->group('summary_status_label')->cache(true, 12000)->select();
        foreach ($form_data['track_status'] as $k=>$v) {
            if($v['track_status'] == '') {
                $form_data['track_status'][$k]['track_status'] = '空';
            }
        }

        $form_data['shipping'] = D('Common/Shipping')->field('id_shipping,title')->where("status=1")->cache(true, 12000)->select();

        //匹配转寄中订单 条件
        $today_where = $where;
        $today_where['o.id_order_status'] = array('EQ', \Order\Lib\OrderStatus::MATCH_FORWARDING);

        //已匹配转寄订单 条件
        $todaym_where = $where;
        $todaym_where['o.id_order_status'] = array('EQ', \Order\Lib\OrderStatus::MATCH_FORWARDED);

        //已转寄完成订单
        $todayf_where = $where;
        $todayf_where['o.id_order_status'] = array('EQ', \Order\Lib\OrderStatus::MATCH_FINISH);

        $count = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')->where($where)->count();
        $today_total = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')->where($today_where)->count();
        $todaym_total = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')->where($todaym_where)->count();
        $todayf_total = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')->where($todayf_where)->count();
        $page = $this->page($count, $this->page);
        $order_list = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')
            ->where($where)
            ->order("o.id_order DESC")
            ->limit($page->firstRow . ',' . $page->listRows)->select();

        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $order_list[$key]['shipping_name'] = D('Common/Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
        }

        $warehouse = M('Warehouse')->field('id_warehouse,title')->where(array('status'=>1,'forward'=>1))->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department = M('Department')->where('type=1')->select();
        $zone = M('Zone')->select();
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $status_model = D('Order/OrderStatus')->field('id_order_status,title')
            ->where(array('status'=>1,'id_order_status'=>array('IN',array(\Order\Lib\OrderStatus::MATCH_FORWARDING,\Order\Lib\OrderStatus::MATCH_FORWARDED,OrderStatus::MATCH_FINISH,OrderStatus::DELIVERING))))->select();
        $status_model = array_column($status_model,'title','id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看转寄仓订单列表');
        $this->assign('zone',$zone);
        $this->assign('department',$department);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("today_total", $today_total);
        $this->assign("todaym_total", $todaym_total);
        $this->assign("todayf_total", $todayf_total);
        $this->assign("warehouse", $warehouse);
        $this->assign("all_zone", $all_zone);
        $this->assign("order_total", $count);
        $this->assign("order_list", $order_list);
        $this->assign("page", $page->show('Admin'));
        $this->assign('status_list',$status_model);
        $this->display();
    }
    /**
     * 转寄中订单
     */
    public function forwarding_order_list() {
        $order_model = $this->orderModel;
        $where = $order_model->form_where($_GET,'o.');
        $id_order_status = I('get.status_id/i');
        if ($id_order_status <= 0) {
            $where['o.id_order_status'] = array('IN',array(\Order\Lib\OrderStatus::MATCH_FORWARDING));
        }
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = $_GET['zone_id'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            unset($where['o.id_warehouse']);
            $new_order_id = M('OrderForward')->where(array('warehouse_id'=>$_GET['id_warehouse']))->getField('new_order_id',true);
            $where['o.id_order'] = $new_order_id ? array('IN',$new_order_id) : array(0);
        }
        if ($_GET['shipping_start_time'] or $_GET['shipping_end_time']) {
            $date_delivery_array = array();
            if ($_GET['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_GET['shipping_start_time']);
            if ($_GET['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_GET['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }

        $form_data = array();

        $form_data['track_status'] = D('Order/OrderShipping')->field('summary_status_label as track_status')->group('summary_status_label')->cache(true, 12000)->select();
        foreach ($form_data['track_status'] as $k=>$v) {
            if($v['track_status'] == '') {
                $form_data['track_status'][$k]['track_status'] = '空';
            }
        }

        $form_data['shipping'] = D('Common/Shipping')->field('id_shipping,title')->where("status=1")->cache(true, 12000)->select();

        //匹配转寄中订单 条件
        $today_where = $where;
        $today_where['o.id_order_status'] = array('EQ', \Order\Lib\OrderStatus::MATCH_FORWARDING);

        $count = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')->where($where)->count();
        $today_total = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')->where($today_where)->count();
        $page = $this->page($count, $this->page);
        $order_list = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')
            ->where($where)
            ->order("o.id_order DESC")
            ->limit($page->firstRow . ',' . $page->listRows)->select();

        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $order_list[$key]['shipping_name'] = D('Common/Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
        }

        $warehouse = M('Warehouse')->field('id_warehouse,title')->where(array('status'=>1,'forward'=>1))->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department = M('Department')->where('type=1')->select();
        $zone = M('Zone')->select();
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $status_model = D('Order/OrderStatus')->field('id_order_status,title')
            ->where(array('status'=>1,'id_order_status'=>array('IN',array(\Order\Lib\OrderStatus::MATCH_FORWARDING,\Order\Lib\OrderStatus::MATCH_FORWARDED))))->select();
        $status_model = array_column($status_model,'title','id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看转寄仓订单列表');
        $this->assign('zone',$zone);
        $this->assign('department',$department);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("today_total", $today_total);
        $this->assign("warehouse", $warehouse);
        $this->assign("all_zone", $all_zone);
        $this->assign("order_total", $count);
        $this->assign("order_list", $order_list);
        $this->assign("page", $page->show('Admin'));
        $this->assign('status_list',$status_model);
        $this->display();
    }
    /**
     * 获取结款状态逻辑
     * @param type $order_model
     * @param type $where
     * @param type $today_where
     * @return type
     */
    protected function get_sellt_param($order_model,$where,$today_where) {
        $count = $order_model->alias('o')->field('o.id_order')
                ->join('__ORDER_SETTLEMENT__ ost on o.id_order = ost.id_order','INNER')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'INNER')
                ->where($where)
                ->select();
        $count = $count?count($count):0;
        $today_total = $order_model->alias('o')->field('o.id_order')
            ->join('__ORDER_SETTLEMENT__ ost on o.id_order = ost.id_order','INNER')
            ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'INNER')
            ->where($today_where)->select();
        $today_total = $today_total?count($today_total):0;
        $page = $this->page($count, $this->page);
        $order_list = $order_model->alias('o')
            ->field('o.*,s.date_signed')
            ->join('__ORDER_SETTLEMENT__ ost on o.id_order = ost.id_order','INNER')
            ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'INNER')
            ->where($where)->order("o.id_order DESC")
            ->limit($page->firstRow . ',' . $page->listRows)->select();
        
        return array(
            'count'=>$count,
            'today_total'=>$today_total,
            'page'=>$page,
            'order_list'=>$order_list
        );
    }

    /**
     * 获取出库订单列表页结款状态逻辑
     * @param type $order_model
     * @param type $where
     * @param type $today_where
     * @return type
     */
    protected function get_sellt_param_out($order_model,$where,$today_where) {
        $count = $order_model->alias('o')->field('o.id_orderout')
            ->join('__ORDER_SETTLEMENT__ ost on o.id_orderout = ost.id_order','INNER')
            ->join('__ORDER_SHIPPING__ s ON (o.id_orderout = s.id_order)', 'INNER')
            ->where($where)
            ->select();
        $count = $count?count($count):0;
        $today_total = $order_model->alias('o')->field('o.id_orderout')
            ->join('__ORDER_SETTLEMENT__ ost on o.id_orderout = ost.id_order','INNER')
            ->join('__ORDER_SHIPPING__ s ON (o.id_orderout = s.id_order)', 'INNER')
            ->where($today_where)->select();
        $today_total = $today_total?count($today_total):0;
        $page = $this->page($count, $this->page);
        $order_list = $order_model->alias('o')
            ->field('o.*,s.date_signed')
            ->join('__ORDER_SETTLEMENT__ ost on o.id_orderout = ost.id_order','INNER')
            ->join('__ORDER_SHIPPING__ s ON (o.id_orderout = s.id_order)', 'INNER')
            ->where($where)->order("o.id_orderout DESC")
            ->limit($page->firstRow . ',' . $page->listRows)->select();

        return array(
            'count'=>$count,
            'today_total'=>$today_total,
            'page'=>$page,
            'order_list'=>$order_list
        );
    }

    /**
     * 已打包订单列表页
     */
    public function order_package_list() {
        $order_model = $this->orderModel;
        $where = $order_model->form_where($_GET);
        $where['id_order_status'] = array('EQ', 18);
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['id_zone'] = $_GET['zone_id'];
        }
        if ($_GET['shipping_start_time'] or $_GET['shipping_end_time']) {

            $date_delivery_array = array();
            if ($_GET['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_GET['shipping_start_time']);
            if ($_GET['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_GET['shipping_end_time']);
            $where['date_delivery'] = $date_delivery_array;
        }
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();
        $form_data['domain'] = $domain_model->get_all_domain();
        $form_data['shipping'] = D('Common/Shipping')->field('id_shipping,title')
            ->where("status=1 ")
            ->cache(true, 12000)->select();
        //今日统计订单 条件
        $today_where = $where;
        $today_where['created_at'] = array('EGT', $today_date);
        $all_domain_total = $order_model->field('count(`id_domain`) as total,id_domain')->where($today_where)
            ->order('total desc')->group('id_domain')->select();

        $count = $order_model->where($where)->count();
        $today_total = $order_model->where($today_where)->count();
        $page = $this->page($count, $this->page);
        $order_list = $order_model->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        foreach($order_list as $k=>$v)
        {
            $order_list[$k]['wave_number'] = M('OrderWave')->where(array('id_order'=>$v['id_order']))->getField('wave_number');

        }

        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $order_list[$key]['shipping_name'] = D('Common/Shipping')->where('id_shipping='.$o['id_shipping'])->getField('title');
        }

        $department = M('Department')->where('type=1')->select();
        $zone = M('Zone')->select();
        $this->assign('zone',$zone);
        $this->assign('department',$department);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("page", $page->show('Admin'));
        $this->assign("today_total", $today_total);
        $this->assign("order_total", $count);
        $this->assign("all_domain_total", $all_domain_total);

        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus')->field('id_order_status,title')->where('status=1')->select();
        $status_model = array_column($status_model,'title','id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看已打包订单列表');
        $this->assign('status_list',$status_model);
        $this->assign("order_list", $order_list);
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
     * 未配货订单
     */
    public function no_distribution_order_list($status_id){
        $status_id = $_GET['status_id'];
        $data =  D('Order/Order')->order_list_by_status($status_id);

        $this->assign('zone',$data['zone']);
        $this->assign('department',$data['department']);
        $this->assign("get", $data['get']);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page", $data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data[order_total]);
        $this->assign("all_domain_total", $data['all_domain_total']);
        $this->assign("order_list", $data['order_list']);
        $this->assign("status_list", $data['status_list']);
        $this->assign("warehouse", $data['warehouse']);
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看未配货订单');
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
     * 配货中订单
     */
    public function in_distribution_order_list($status_id){
        $status_id = $_GET['status_id'];
        $data =  D('Order/Order')->order_list_by_status($status_id);
        $this->assign('zone',$data['zone']);
        $this->assign('department',$data['department']);
        $this->assign("get", $data['get']);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page", $data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data[order_total]);
        $this->assign("all_domain_total", $data['all_domain_total']);
        $this->assign("order_list", $data['order_list']);
        $this->assign("status_list", $data['status_list']);
        $this->assign("warehouse", $data['warehouse']);
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看配货中订单');
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
     * 缺货订单
     */
    public function stockout_order_list($status_id){
        $status_id = $_GET['status_id'];
        $data =  D('Order/Order')->order_list_by_status($status_id);
        $this->assign('zone',$data['zone']);
        $this->assign('department',$data['department']);
        $this->assign("get", $data['get']);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page", $data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data['order_total']);
        $this->assign("all_domain_total", $data['all_domain_total']);
        $this->assign("order_list", $data['order_list']);
        $this->assign("status_list", $data['status_list']);
        $this->assign("warehouse", $data['warehouse']);
        /** @var \Common\Model\ZoneModel $zone_model */
//        dump($data);
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看缺货订单');
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
     * 已配货订单
     */
    public function fulfilled_order_list($status_id){
        $status_id = $_GET['status_id'];
        $data =  D('Order/Order')->order_list_by_status($status_id);
        $this->assign('zone',$data['zone']);
        $this->assign('department',$data['department']);
        $this->assign("get", $data['get']);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page", $data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data[order_total]);
        $this->assign("all_domain_total", $data['all_domain_total']);
        $this->assign("order_list", $data['order_list']);
        $this->assign("status_list", $data['status_list']);
        $this->assign("warehouse", $data['warehouse']);
        /** @var \Common\Model\ZoneModel $zone_model */
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看已配货订单');
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
        * 配送中订单
        */
    public function in_shipping_order_list($status_id){
        $status_id = $_GET['status_id'];
        $data =  D('Order/Order')->order_list_by_status($status_id);
        $this->assign('zone',$data['zone']);
        $this->assign('department',$data['department']);
        $this->assign("get", $data['get']);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page", $data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data[order_total]);
        $this->assign("all_domain_total", $data['all_domain_total']);
        $this->assign("order_list", $data['order_list']);
        $this->assign("status_list", $data['status_list']);
        $this->assign("warehouse", $data['warehouse']);
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看配货中订单');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
       * 已签收订单
       */
    public function received_order_list($status_id){
          $status_id = $_GET['status_id'];
        $data =  D('Order/Order')->order_list_by_status($status_id);
        $this->assign('zone',$data['zone']);
        $this->assign('department',$data['department']);
        $this->assign("get", $data['get']);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page", $data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data[order_total]);
        $this->assign("all_domain_total", $data['all_domain_total']);
        $this->assign("order_list", $data['order_list']);
        $this->assign("status_list", $data['status_list']);
        $this->assign("warehouse", $data['warehouse']);
        /** @var \Common\Model\ZoneModel $zone_model */
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看已签收订单');
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
      * 已退货订单
      */
    public function return_order_list($status_id){
        $status_id = $_GET['status_id'];
        $data =  D('Order/Order')->order_list_by_status($status_id);
        $this->assign('zone',$data['zone']);
        $this->assign('department',$data['department']);
        $this->assign("get", $data['get']);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page", $data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data[order_total]);
        $this->assign("all_domain_total", $data['all_domain_total']);
        $this->assign("order_list", $data['order_list']);
        $this->assign("status_list", $data['status_list']);
        $this->assign("warehouse", $data['warehouse']);
        /** @var \Common\Model\ZoneModel $zone_model */
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看已退货订单');
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
     * 客户取消订单
      */
    public function cancel_order_list($status_id){
        $status_id = $_GET['status_id'];
        $data =  D('Order/Order')->order_list_by_status($status_id);
        $this->assign('zone',$data['zone']);
        $this->assign('department',$data['department']);
        $this->assign("get", $data['get']);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page", $data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data[order_total]);
        $this->assign("all_domain_total", $data['all_domain_total']);
        $this->assign("order_list", $data['order_list']);
        $this->assign("status_list", $data['status_list']);
        $this->assign("warehouse", $data['warehouse']);
        /** @var \Common\Model\ZoneModel $zone_model */
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看客户取消订单');
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
     * 部分缺货订单
     */
    public function partial_order_list($status_id){
        $status_id = $_GET['status_id'];
        $data =  D('Order/Order')->order_list_by_status($status_id);
        $this->assign('zone',$data['zone']);
        $this->assign('department',$data['department']);
        $this->assign("get", $data['get']);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page", $data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data[order_total]);
        $this->assign("all_domain_total", $data['all_domain_total']);
        $this->assign("order_list", $data['order_list']);
        $this->assign("status_list", $data['status_list']);
        $this->assign("warehouse", $data['warehouse']);
        /** @var \Common\Model\ZoneModel $zone_model */
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看部分缺货订单');
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    /**
     * 订单详情
     */
    public function info(){
        $order_id = I('get.id');
        $order = D("Order/Order")->find($order_id);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $orderHistory = D("Order/OrderRecord")
            ->field('*')
            ->join('__USERS__ u ON (__ORDER_RECORD__.id_users = u.id)', 'LEFT')
            ->where(array('id_order'=>$order_id))
            ->order('created_at desc, id_order_status = 4 desc, id_order_status = 25 desc, id_order_status asc')->select();
        $shipping = D('Common/Shipping')
            ->where(array('id_shipping'=>(int)$order['id_shipping']))
            ->find();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $all_domain = $domain_model->get_all_domain();
        $order['id_domain'] = $all_domain[$order['id_domain']];
        $order['id_order_status'] = $statusLabel[$order['id_order_status']];
        $products = D('Order/OrderItem')->get_item_list($order['id_order']);
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看仓库订单详情');
        $this->assign("order", $order);
        $this->assign("products", $products);
        $this->assign("history", $orderHistory);
        $this->assign("label", $statusLabel);
        $this->assign('shipping_name', $shipping['title']);
        $this->assign('shopping_url', $shipping['track_url']);
        $this->display();
    }

    /**
     * 出库订单详情
     */
    public function detail()
    {
        $order_id = I('get.id');
        $order = D("Order/Orderout")->find($order_id);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $orderHistory = D("Order/OrderRecord")
            ->field('*')
            ->join('__USERS__ u ON (__ORDER_RECORD__.id_users = u.id)', 'LEFT')
            ->where(array('id_order'=>$order['id_order']))
            ->order('created_at desc')->select();
        $shipping = D('Common/Shipping')
            ->where(array('id_shipping'=>(int)$order['id_shipping']))
            ->find();

        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $all_domain = $domain_model->get_all_domain();
        $order['id_domain'] = $all_domain[$order['id_domain']];
        $order['id_order_status'] = $statusLabel[$order['id_order_status']];
        $products = D('Order/OrderOutitem')->get_item_list($order['id_orderout']);
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看仓库订单详情');

        $this->assign("order", $order);
        $this->assign("products", $products);
        $this->assign("history", $orderHistory);
        $this->assign("label", $statusLabel);
        $this->assign('shipping_name', $shipping['title']);
        $this->assign('shopping_url', $shipping['track_url']);
        $this->display();
    }

    /**
     * 编辑订单 from表单
     */
    public function edit_order() {
        $getId = I('get.id/i');
        $where = array('id_order' => array('EQ', $getId));
        $order = $this->orderModel->where($where)->find();
        //当该订单状态为未处理，待处理和待审核状态下才能进行订单修改
        $id_order_status_arr = array(OrderStatus::UNPROCESS,OrderStatus::PROCESSING,OrderStatus::VERIFICATION);
        if (!in_array($order['id_order_status'],$id_order_status_arr))
        {
            $this->error("该订单状态不为未处理，待处理和待审核状态，不允许编辑！");
        }
        if(!$order){
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
        if ($products) {
            foreach ($products as $key => $product) {
                $product_id = $product['id_product'];
                if(!empty($product_id)) {
                    $sku_id = $product['id_product_sku'];
                    $sku_id_tmp=$sku_id+rand(1,1000);
                    $arr_html[$product_id]['id_product'] = $product_id;
                    $arr_html[$product_id]['product_title'] = $product['product_title'];
                    $arr_html[$product_id]['id_order_item'] = $product['id_order_item'];
                    $arr_html[$product_id]['id_order_items'][$sku_id_tmp] = $product['id_order_item'];
                    $arr_html[$product_id]['quantity'][$sku_id_tmp] = $product['quantity'];
                    $arr_html[$product_id]['attrs'][$sku_id_tmp] = unserialize($product['attrs']);
                    $arr_html[$product_id]['attr_option_value'][$sku_id_tmp] = $product_id ? $options->get_attr_list_by_id($product_id) : '';
                    $arr_html[$product_id]['attr_option_value_data'] = $product_id ? $options->get_attr_list_by_id($product_id) : '';
                    $arr_html[$product_id]['sku_id'] = $product['id_product_sku'];
                    $arr_html[$product_id]['sku_id_tmp'] = $sku_id_tmp;
                    $products[$key]['attr_option_value'] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                    $all_attr[$product_id] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                }
            }
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '编辑订单');
        $this->assign("products", $arr_html);
        $this->assign("all_attr", $all_attr);
        $this->assign("order", $order);
        $this->display();
    }

    /**
     * 编辑出库订单 from表单
     */
    public function edit_order_out()
    {
        $getId = I('get.id/i');
        $where = array('id_orderout' => array('EQ', $getId));
        $order = $this->orderoutModel->where($where)->find();
        if (!$order)
        {
            $this->error("你没有权限操作此订单！");
        }
        /** @var \Common\Model\ProductModel $product */
        $order_item = D('Order/OrderOutitem');
        $products = $order_item->where(array(
            'id_orderout' => $order['id_orderout']
        ))->order('id_product desc')->select();
        /** @var  $options \Product\Model\ProductOptionModel */
        $options = D('Product/ProductOption');
        $all_attr = array();
        $arr_html = array();

        if ($products)
        {
            foreach ($products as $key => $product)
            {
                $product_id = $product['id_product'];
                if (!empty($product_id))
                {
                    $sku_id = $product['id_product_sku'];
                    $arr_html[$product_id]['id_product'] = $product_id;
                    $arr_html[$product_id]['product_title'] = $product['product_title'];
                    $arr_html[$product_id]['id_order_item'] = $product['id_order_outitem'];
                    $arr_html[$product_id]['id_order_items'][$sku_id] = $product['id_order_outitem'];
                    $arr_html[$product_id]['quantity'][$sku_id] = $product['quantity'];
                    $arr_html[$product_id]['attrs'][$sku_id] = unserialize($product['attrs']);
                    $arr_html[$product_id]['attr_option_value'][$sku_id] = $product_id ? $options->get_attr_list_by_id($product_id) : '';
                    $arr_html[$product_id]['attr_option_value_data'] = $product_id ? $options->get_attr_list_by_id($product_id) : '';
                    $arr_html[$product_id]['sku_id'] = $product['id_product_sku'];
                    $products[$key]['attr_option_value'] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                    $all_attr[$product_id] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                }
            }
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '编辑出库订单');
        $this->assign("products", $arr_html);
        $this->assign("all_attr", $all_attr);
        $this->assign("order", $order);
        $this->display();
    }

    /**
     * 编辑订单添加产品属性
     */
    public function get_attr_html() {
        if(IS_AJAX) {
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
            
            if ($products) {
                foreach ($products as $key => $product) {                
                    $product_id = $product['id_product'];
                    if(!empty($product_id)) {
                        $sku_id = $product['id_product_sku'];
                        $select_attr[$product_id]['attrs'] = unserialize($product['attrs']);
                        $select_attr[$product_id]['quantity'] = $product['quantity'];
                        $all_attr[$product_id] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                    }
                }
            }
            
//            dump($select_attr);
            
            $html = '<tr class="productAttrRow'.$pro_id.'">';
            $html .= '<td>';
            foreach ($all_attr[$pro_id] as $k=>$val) {
                $html .= $val['title'].'&nbsp';
                $html .= '<select name="option_id['.$pro_id.']['.$val["id_product_option"].'][]">';
                foreach($val["option_values"] as $kk => $vv) {
                    $selected = in_array($vv['id_product_option_value'],$select_attr[$pro_id]['attrs']) ? 'selected' : '';  
                    $html .= '<option value ="'.$vv['id_product_option_value'].'" '.$selected.'>'.$vv['title'].'</option>';
                }
                $html .= '</select>&nbsp;&nbsp;&nbsp;';                    
            }
            $html .= '<input name="number['.$pro_id.'][]" value="'.$select_attr[$pro_id]['quantity'][0].'" type="text">&nbsp;&nbsp;';
            $html .= '<a href="javascript:void(1);" class="deleteOrderAttr" pro_id="'.$pro_id.'">删除</a>';
            $html .= '</td>';
            $html .= '</tr>';
            echo $html;die();
        }
    }
    /**
     * 处理编辑订单
     */
    public function edit_order_post() {
        $orderId = I('get.id');
        $order = D("Order/Order")->find($orderId);
        F('order_item_by_order_id_cache'.$orderId,null);
        if (isset($_POST['action']) && $_POST['action'] == 'delete_attr') {
            //因为要添加权限，所以先写到这个控制器了。            
            $itemId = I('post.order_attr_id');
            if ($orderId && $itemId) {
                $deleteData = D("Order/OrderItem")->find($itemId);
                $comment = '删除产品属性：' . json_encode($deleteData);
                D("Order/OrderItem")->where('id_order_item=' . $itemId)->delete();
//                D('Order/Order')->where('id_order='.$orderId)->save(array('price_total'=>$order['price_total']-$deleteData['total']));
                D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],3, $comment);
            }
            exit();
        }
        if(IS_POST) {
            $data = I('post.'); 
//            dump($data['number']);die;
            D('Order/Order')->save($data);
            
            $product_id = array();
            foreach($data['option_id'] as $key=>$val){
                $product_id[] = $key;
                $temp = array();

                foreach($val as $k=>$v) {
                    foreach($v as $kk=>$vv){
                        $temp[$kk][] = $vv;                        
                    }                    
                }
                $product = D('Product/Product')->field('title,inner_name,sale_price')->where('id_product='.$key)->find();
                foreach($temp as $psk =>$psv)  {
                    $option_value = asort($psv);
//                    $sku_option_value = M('ProductOptionValue')->where(array('id_product_option_value'=>array('IN',$psv)))->getField('title',true);
                    $option_value = implode(',', $psv);
                    $product_sku = D('Product/ProductSku')->where("status=1  and option_value='$option_value' and id_product=$key")->order('id_product_sku desc')->find();
                    $item_result = D('Order/OrderItem')->where('id_product='.$key.' and id_product_sku='.$product_sku['id_product_sku'].' and id_order='.$data['id_order'])->find();                                      
                    $item_data['id_order'] = $data['id_order'];
                    $item_data['id_product'] = $key;
                    $item_data['id_product_sku'] = $product_sku['id_product_sku']; 
                    $item_data['sku'] = $product_sku['sku'];
                    $item_data['sku_title'] = $product_sku['title'];
                    $item_result2 = D('Order/OrderItem')->where('id_product='.$key.' and id_order='.$data['id_order'])->find();
                    $item_data['sale_title'] = $item_result2['sale_title'];
                    $item_data['product_title'] = $product['title'];
                    $item_data['quantity'] = $data['number'][$key][$psk];
                    $item_data['price'] = $product['sale_price'];
                    $item_data['total'] = $product['sale_price']*$data['number'][$key][$psk];
                    $item_data['attrs'] = serialize($psv);
//                    $item_data['attrs_title'] = serialize($sku_option_value);
                    if(array_keys($data['order_item_id'][$key])[$psk] == $psk) {
                        D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$key][$psk])->data($item_data)->save();
                    } else {
                        if($product_sku['id_product_sku'] == $item_result['id_product_sku'] || empty($data['number'][$key][$psk])) continue;
                        D('Order/OrderItem')->data($item_data)->add();
                    }                    
                    if($data['number'][$key][$psk] == 0) {
                        D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$key][$psk])->delete();
                    }
                }
            } 
            $lastval=0;
            $the_key=0;
            foreach ($data['pro_id'] as $pro_key=>$pro_val) {
                if($pro_val==$lastval){
                    $the_key++;
                }else{
                    $lastval=$pro_val;
                    $the_key=0;
                }                
                if(!in_array($pro_val,$product_id)) {
                    $other_product = D('Product/Product')->field('title,inner_name,sale_price')->where('id_product='.$pro_val)->find();
                    $item_id = $data['order_item_id'][$pro_val][$the_key];
                    $other_item_data['total'] = $other_product['sale_price']*$data['number'][$pro_val][$the_key];
                    $other_item_data['quantity'] =$data['number'][$pro_val][$the_key];
                    D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$pro_val][$the_key])->data($other_item_data)->save();
                }

                if($data['number'][$pro_val][$the_key] == 0) {
                    D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$pro_val][$the_key])->delete();
                }
            }      
            D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],2, '仓库编辑订单属性');
            add_system_record(sp_get_current_admin_id(), 2, 1, '仓库编辑订单属性');
            $this->success("保存完成！", U('order/edit_order', array('id' => $orderId)));
        } else {
            $this->error("参数不正确！");
        }
    }    
    /**
     * 要求取消订单
     */
    public function cancel_order(){
        try{
            $order_id = I('post.order_id');
            $status_id = 14;  //取消订单
            $comment = '【仓储管理取消】 ';
            //D("Order/Order")->where(array('id_order'=>$order_id))->save(array('id_order_status'=>14));
            //D("Order/OrderRecord")->addHistory($order_id,$status_id,3,$comment.$_POST['comment']);
            $status = 1; $message = '';
            $update_data = array(
                'id'=>$order_id,
                'status_id'=>$status_id,
                'comment'=>$comment
            );
            UpdateStatusModel::cancel($update_data);
        }catch (\Exception $e){
            $status = 1; $message = $e->getMessage();
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '仓库取消订单');
        echo json_encode(array('status'=>$status,'message'=>$message));
    }    
    /**
     * 导出订单列表
     */
    public function export_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $export_limit = 15000;

        $column = array(
            '地区', '订单号', '姓名',
            '产品名和价格', '外文名','内部名','SKU','总价（NTS）', '属性',
            '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
            '发货日期','配货日期', '运单号', '物流状态','物流名称','邮编','仓库','结款状态'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

        $where = $this->orderModel->form_where($_POST,'o.');
//        if(empty($_GET['id_warehouse'])){
//            $where['id_warehouse'] = array('EQ',$_GET['id_warehouse']);
//        }
        if(empty($_POST['status_id'])){
            $id_order_status = \Order\Lib\OrderStatus::get_effective_status();
            $id_order_status = array_merge($id_order_status,array(14));
            $where['o.id_order_status'] = array('IN', $id_order_status);
        }
        if ($_POST['shipping_start_time'] or $_POST['shipping_end_time']) {

            $date_delivery_array = array();
            if ($_POST['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_POST['shipping_start_time']);
            if ($_POST['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_POST['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }
        if(isset($_POST['sku']) && $_POST['sku']) {
            $where['oi.sku'] = I('post.sku');//array('LIKE',I('get.sku').'%');//I('get.sku');
            //$where['oi.id_order'] = array('NEQ','');
        }
        if(strip_tags(trim($_POST['status_label'])) == '空') {
            $where['_string'] = "(os.summary_status_label='' or os.summary_status_label is null)";
        } elseif(trim($_POST['status_label'])) {
            $where['os.summary_status_label'] = strip_tags(trim($_POST['status_label']));
        }
        if((isset($_POST['status']) && $_POST['status']) || $_POST['status'] == '0') {
            $where['ost.status'] = $_POST['status'];
        }

        if($_POST['sku']){
            $orders = $this->orderModel->alias('o')->field('o.*,os.date_signed')
                ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                ->where($where)
                ->group('oi.id_order')
                ->order("o.id_order DESC")
                ->limit($export_limit)
                ->select();
        } else if((isset($_POST['status']) && $_POST['status']) || $_POST['status'] == '0') {   
                $orders = $this->orderModel->alias('o')
                        ->field('o.*')
                        ->join('__ORDER_SETTLEMENT__ ost on o.id_order = ost.id_order','INNER')
                        ->join('__ORDER_SHIPPING__ os ON o.id_order = os.id_order', 'INNER')
                        ->where($where)->order("o.id_order DESC")
                        ->limit($export_limit)->select();
        }else{
            if(isset($_POST['status_label']) && $_POST['status_label']){                
                $orders = $this->orderModel->alias('o')->field('o.*,os.date_signed')
                    //->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
//                    ->group('os.id_order')
                    ->order("o.id_order DESC")
                    ->limit($export_limit)
                    ->select();
            }else{
                $orders = $this->orderModel->alias('o')
                    ->where($where)
                    ->order("id_order DESC")
                    ->limit($export_limit)->select();
            }
        }

        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }
//        dump($status);die;
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        $shipping = D('Common/Shipping')->cache(true,36000)->select();
        $shipping_data = array_column($shipping,'title','id_shipping');
        /** @var \Order\Model\OrderRecordModel  $order_record */
        $order_record = D("Order/OrderRecord");
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        foreach ($orders as $o) {
            $inner_name = [];
            $product_name = [];
            $sale_titles=[];
            $sku = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            foreach ($products as $p) {
                $sku .=$p['sku']."   ";
                $product_name[]= $p['product_title'];
                $inner_name[]= $p['inner_name'];
                $sale_titles[]=$p['sale_title'];
                $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                $product_count += $p['quantity'];
            }
            $product_name=  implode(';', array_unique($product_name));
            $inner_name=  implode(';', array_unique($inner_name));
            $sale_titles=  implode(';', array_unique($sale_titles));            
//            dump($sku);
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $getShipObj = D("Order/OrderShipping")->alias('os')->field('track_number,status_label')//,s.title as shipping_name
                                                 //->join('__SHIPPING__ as s on s.id_shipping = os.id_shipping','left')
                                                 ->where(array('id_order'=>$o['id_order']))->select();

            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $shipping_name = $shipping_data[$o['id_shipping']];
            $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
            $warehouse = array_column($warehouse,'title','id_warehouse');
            //增加配货日期
            $m_where = array();
            $m_where['oi.id_order_status'] = array('EQ',OrderStatus::PICKING);
            $m_where['oi.id_order'] = array('EQ',$o['id_order']);
            //最晚的缺货时间
            $last_stockout=M('OrderRecord')->where(array('id_order_status'=>OrderStatus::UNPICKING,'id_order'=>$o['id_order']))
                    ->getField('max(created_at)');
            if($last_stockout){
                $m_where['oi.created_at'] = array('GT',$last_stockout);
            }
            $match_time = M('Order')->alias('o')->join('__ORDER_RECORD__ oi ON o.id_order=oi.id_order','LEFT')->field('min(oi.created_at) as match_time')
                          ->where($m_where)->find();
            if ($match_time && in_array($o['id_order_status'],array(5,7,8,9,10,16,18,21)))
            {
                $o['date_match'] = $match_time['match_time'];
            }
            else
            {
                $o['date_match'] = '--';
            }
            //台湾地区的地址不需要加上省份,但是其他的地区需要带上
            if ($o['id_zone'] == 2) {
                $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
            } else {
                $address = trim(sprintf('%s%s%s%s',$o['province'], $o['city'], $o['area'], $o['address']));
            }
            $order_settl = M('OrderSettlement')->field('status')->where(array('id_order'=>$o['id_order']))->find();
            switch ($order_settl['status']) {
                case 0:
                    $settl_name = ($order_settl['status']===null)?'':'未结款';
                    break;
                case 1: $settl_name = '结款中';
                    break;
                case 2: $settl_name = '已结款';
                    break;
//                default:$setStatus = '';
            }
            $data = array(
                $all_zone[$o['id_zone']], $o['id_increment'], $o['first_name'].' '.$o['last_name'],
                trim($product_name,';'),trim($sale_titles,';'),trim($inner_name,';'),$sku,$o['price_total'], $attrs,
                $address, $product_count, $o['remark'], $o['created_at'], $status_name,
                $o['date_delivery'],$o['date_match'], $trackNumber, $trackStatusLabel, $shipping_name,$o['zipcode'],$warehouse[$o['id_warehouse']],$settl_name
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                if($key != 7 && $key != 10){
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                }else{
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
//                $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
            $order_record->addHistory($o['id_order'],$o['id_order_status'],4, '仓库管理 订单列表 导出订单');
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出仓库订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    /**
     * 导出出库订单列表
     */
    public function export_search_out() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $column = array(
            '地区', '订单号', '姓名',
            '产品名和价格', 'SKU','总价（NTS）', '属性',
            '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
            '发货日期', '运单号', '物流状态','物流名称','邮编','仓库','结款状态'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

        $where = $this->orderoutModel->get_data($_POST,'o.');
        if(empty($_POST['status_id']))
        {
            $effective_status = \Order\Lib\OrderStatus::get_effective_status();
            $where['id_order_status'] = array('IN',$effective_status);
        }
        if ($_POST['shipping_start_time'] or $_POST['shipping_end_time'])
        {
            $date_delivery_array = array();
            if ($_POST['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_POST['shipping_start_time']);
            if ($_POST['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_POST['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }
        if (isset($_POST['sku']) && $_POST['sku'])
        {
            $where['oi.sku'] = I('post.sku');
        }
        if (strip_tags(trim($_POST['status_label'])) == '空')
        {
            $where['_string'] = "(os.status_label='' or os.status_label is null)";
        }
        elseif (trim($_POST['status_label']))
        {
            $where['os.status_label'] = strip_tags(trim($_POST['status_label']));
        }
        if ((isset($_POST['status']) && $_POST['status']) || $_POST['status'] == '0')
        {
            $where['ost.status'] = $_POST['status'];
        }

        if ($_POST['sku'])
        {
            $orders = $this->orderoutModel->alias('o')->field('o.*,os.date_signed')
                ->join('__ORDER_OUTITEM__ oi on o.id_orderout = oi.id_orderout','LEFT')
                ->join('__ORDER_SHIPPING__ os on o.id_orderout = os.id_order','LEFT')
                ->where($where)
                ->group('oi.id_orderout')
                ->order("o.id_orderout DESC")
                ->limit(5000)
                ->select();
        }
        else if ((isset($_POST['status']) && $_POST['status']) || $_POST['status'] == '0')
        {
            $orders = $this->orderoutModel->alias('o')
                ->field('o.*')
                ->join('__ORDER_SETTLEMENT__ ost on o.id_orderout = ost.id_order','INNER')
                ->join('__ORDER_SHIPPING__ os ON o.id_orderout = os.id_order', 'INNER')
                ->where($where)->order("o.id_orderout DESC")
                ->limit(5000)->select();
        }
        else
        {
            if (isset($_POST['status_label']) && $_POST['status_label'])
            {
                $orders = $this->orderModel->alias('o')->field('o.*,os.date_signed')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->order("o.id_orderout DESC")
                    ->limit(5000)
                    ->select();
            }
            else
            {
                $orders = $this->orderoutModel->alias('o')
                    ->where($where)
                    ->order("id_orderout ASC")
                    ->limit(5000)->select();
            }
        }

        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu)
        {
            $status[(int) $statu['id_order_status']] = $statu;
        }

        /** @var \Order\Model\OrderOutitemModel $order_item */
        $order_item = D('Order/OrderOutitem');
        $idx = 2;
        $shipping = D('Common/Shipping')->cache(true,36000)->select();
        $shipping_data = array_column($shipping,'title','id_shipping');
        /** @var \Order\Model\OrderRecordModel  $order_record */
        $order_record = D("Order/OrderRecord");
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        foreach ($orders as $o)
        {
            $product_name = '';
            $sku = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_orderout']);
            $product_count = 0;
            foreach ($products as $p)
            {
                $sku .=$p['sku']."   ";
                $product_name .= $p['product_title'] . "\n";
                $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                $product_count += $p['quantity'];
            }

            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $getShipObj = D("Order/OrderShipping")->alias('os')->field('track_number,status_label')
                ->where(array('id_order'=>$o['id_orderout']))->select();

            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $shipping_name = $shipping_data[$o['id_shipping']];
            $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
            $warehouse = array_column($warehouse,'title','id_warehouse');
            //台湾地区的地址不需要加上省份,但是其他的地区需要带上
            if ($o['id_zone'] == 2)
            {
                $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
            }
            else
            {
                $address = trim(sprintf('%s%s%s%s',$o['province'], $o['city'], $o['area'], $o['address']));
            }
            $order_settl = M('OrderSettlement')->field('status')->where(array('id_order'=>$o['id_orderout']))->find();
            switch ($order_settl['status'])
            {
                case 0:
                    $settl_name = ($order_settl['status']===null)?'':'未结款';
                    break;
                case 1: $settl_name = '结款中';
                    break;
                case 2: $settl_name = '已结款';
                    break;
            }
            $data = array(
                $all_zone[$o['id_zone']], $o['id_increment'], $o['first_name'].' '.$o['last_name'],
                $product_name,$sku,$o['price_total'], $attrs,
                $address, $product_count, $o['remark'], $o['created_at'], $status_name,
                $o['date_delivery'], $trackNumber, $trackStatusLabel, $shipping_name,$o['zipcode'],$warehouse[$o['id_warehouse']],$settl_name
            );
            $j = 65;
            foreach ($data as $key=>$col)
            {
                if($key != 7 && $key != 10)
                {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                }
                else
                {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;
            $order_record->addHistory($o['id_orderout'],$o['id_order_status'],4, '仓库管理 订单列表 导出订单');
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出仓库订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    /**
     * 分状态导出订单列表
     */
    public function export_search_status() {
        try{
            set_time_limit(0);
            vendor("PHPExcel.PHPExcel");
            vendor("PHPExcel.PHPExcel.IOFactory");
            vendor("PHPExcel.PHPExcel.Writer.CSV");
            $excel = new \PHPExcel();
            $column = array(
                '地区', '订单号', '姓名',
                '产品名和价格','SKU', '总价（NTS）', '属性',
                '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
                '发货日期', '运单号', '物流状态','物流名称','邮编','仓库'
            );
            $j = 65;
            foreach ($column as $col) {
                $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
                ++$j;
            }
            $where = $this->orderModel->form_where($_POST,'o.');

//            $where['id_order_status'] = array('EQ',$_POST['status_id']);
            if(isset($_POST['sku']) && $_POST['sku']) {
                $where['oi.sku'] = I('post.sku');//array('LIKE',I('get.sku').'%');//I('get.sku');
                //$where['oi.id_order'] = array('NEQ','');
            }
            if($_POST['sku']){
                $orders = $this->orderModel->alias('o')->field('o.*,os.date_signed')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->group('oi.id_order')
                    ->order("o.id_order DESC")
                    ->limit(5000)
                    ->select();
            }else{
                $orders = $this->orderModel->alias('o')
                    ->where($where)
                    ->order("id_order ASC")
                    ->limit(5000)->select();

            }
            $result = D('Order/OrderStatus')->select();
            $status = array();
            foreach ($result as $statu) {
                $status[(int) $statu['id_order_status']] = $statu;
            }
//        dump($status);die;
            /** @var \Order\Model\OrderItemModel $order_item */
            $order_item = D('Order/OrderItem');
            $idx = 2;
            $shipping = D('Common/Shipping')->cache(true,36000)->select();
            $shipping_data = array_column($shipping,'title','id_shipping');
            /** @var \Order\Model\OrderRecordModel  $order_record */
            $order_record = D("Order/OrderRecord");
            foreach ($orders as $o) {
                $product_name = '';
                $sku = '';
                $attrs = '';
                $products = $order_item->get_item_list($o['id_order']);
                $product_count = 0;
                foreach ($products as $p) {
                    $sku .= $p['sku'] . "     ";
                    $product_name .= $p['product_title'] . "\n";
                    $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                    $product_count += $p['quantity'];
                }
                $attrs = trim($attrs, ',');
                $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
                $getShipObj = D("Order/OrderShipping")->alias('os')->field('track_number,status_label')//,s.title as shipping_name
                    //->join('__SHIPPING__ as s on s.id_shipping = os.id_shipping','left')
                    ->where(array('id_order'=>$o['id_order']))->select();

                $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
                $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
                $shipping_name = $shipping_data[$o['id_shipping']];
                $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
                $warehouse = array_column($warehouse,'title','id_warehouse');
                $data = array(
                    $o['province'], $o['id_increment'], $o['first_name'].' '.$o['last_name'], 
                    $product_name,$sku, $o['price_total'], $attrs,
                    $o['address'], $product_count, $o['remark'], $o['created_at'], $status_name,
                    $o['date_delivery'], $trackNumber, $trackStatusLabel, $shipping_name,$o['zipcode'],$warehouse[$o['id_warehouse']]
                );
                $j = 65;
                foreach ($data as $key=>$col) {
                    if($key != 6 && $key != 9){
                        $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                    }else{
                        $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                    }
//                $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                    ++$j;
                }
                ++$idx;
                $order_record->addHistory($o['id_order'],$o['id_order_status'],4, '仓库管理 订单列表 导出订单');
            }
            add_system_record(sp_get_current_admin_id(), 6, 4, '导出仓库订单列表');
            $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
            $excel->setActiveSheetIndex(0);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $writer->save('php://output');exit();
        }catch (\Exception $e){
            print_r($e->getMessage());
        }
    }
    /**
     * 导出缺货的原因
     */
    public function export_out_stock_reason(){
//        dump($_POST);die;
        set_time_limit(0);
        $where = $this->orderModel->form_where($_POST,'o.');
        $M = new \Think\Model;
        if (isset($_POST['zone_id']) && $_POST['zone_id']) {
            $where['o.id_zone'] = $_POST['zone_id'];
        }
        if(isset($_POST['sku']) && $_POST['sku']) {
            $where['oi.sku'] = I('post.sku');//array('LIKE',I('get.sku').'%');//I('get.sku');
            //$where['oi.id_order'] = array('NEQ','');
        }
        $where['o.id_order_status'] = array('EQ',6);

        $Warehouse = D('Common/Warehouse')->where(array('status'=>1))->select();
        $warehouse_data = array_column($Warehouse,'title','id_warehouse');

        $select_field = 'o.id_order,o.id_increment,o.id_department,p.inner_name,p.title as product_title,oi.product_title as sale_title,oi.id_product_sku,oi.id_product,oi.sku,oi.sku_title,oi.quantity as buy_qty';
        $order_list = $this->orderModel->alias('o')
            ->field($select_field)
            ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
            ->join('__PRODUCT__ p on oi.id_product = p.id_product','LEFT')
            ->where($where)
            ->select();
        $ware_product = D('Common/WarehouseProduct');
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $column = array(
            'ID', '订单号', '部门', '内部名', '产品名',
            '销售名','SKU ID','产品ID','SKU', 'SKU 标题', '购买件数',
            '深圳福永仓库', '台湾台中仓库', '台湾桃园仓库','越南仓库','香港仓库','日本仓库', 'SKU是否可用', '距最近下单时间天数'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $idx = 2;
        foreach($order_list as $order){
            $id_product        = $order['id_product'];
            $id_product_sku    = $order['id_product_sku'];
            foreach($warehouse_data as $ware_id=>$ware_title){
                $ware_where = array(
                    'id_warehouse' => $ware_id,
                    'id_product' => $id_product,
                    'id_product_sku' => $id_product_sku,
                );
                $select_ware_product = $ware_product->where($ware_where)->find();
                $order['stock_qty'.$ware_id]  = $select_ware_product['quantity'];
            }
            $product_sku = M('ProductSku')->field('status')->where(array('id_product_sku'=>$id_product_sku))->find();
            $order_time = M('Order')->alias('o')->field('o.created_at')->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')->where(array('oi.id_product'=>$id_product))->order('o.created_at DESC')->find();
            if($product_sku['status'] == '1') {
                $order['is_status'] = '可用';
            } else {
                $order['is_status'] = '不可用';
            }
            $order['days'] = round((time()-strtotime($order_time['created_at']))/3600/24).'天前';

            $j = 65;
            foreach ($order as $key=>$col) {
                if($key != 'id_department'){
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                }else{
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            $idx++;
        }
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '缺货订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '缺货订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
    /**
     * 导出问题件订单
     */
    public function export_problem_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $column = array(
            '地区', '订单号', '姓名',
            '产品名和价格', 'SKU','总价', '属性',
            '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
            '发货日期', '运单号', '物流状态','物流名称','邮编','仓库','结款状态','问题类型'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

        $where = $this->orderModel->form_where($_POST,'o.');        
        $where['id_order_status'] = \Order\Lib\OrderStatus::PROBLEM;
        if ($_POST['shipping_start_time'] or $_POST['shipping_end_time']) {
            $date_delivery_array = array();
            if ($_POST['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_POST['shipping_start_time']);
            if ($_POST['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_POST['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }
        if(isset($_POST['sku']) && $_POST['sku']) {
            $where['oi.sku'] = I('post.sku');
        }
        if(strip_tags(trim($_POST['status_label'])) == '空') {
            $where['_string'] = "(os.status_label='' or os.status_label is null)";
        } elseif(trim($_POST['status_label'])) {
            $where['os.status_label'] = strip_tags(trim($_POST['status_label']));
        }
        if((isset($_POST['status']) && $_POST['status']) || $_POST['status'] == '0') {
            $where['ost.status'] = $_POST['status'];
        }
        if(isset($_POST['pstatus_name']) && $_POST['pstatus_name']) {
            if(strip_tags(trim($_POST['pstatus_name'])) == '空') {
                $where['_string'] = "(o.problem_name='' or o.problem_name is null)";
            } else {
                $where['o.problem_name'] = strip_tags(trim($_POST['pstatus_name']));
            }
        }

        if($_POST['sku']){
            $orders = $this->orderModel->alias('o')->field('o.*,os.date_signed')
                ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                ->where($where)
                ->group('oi.id_order')
                ->order("o.id_order DESC")
                ->limit(5000)
                ->select();
        } else if((isset($_POST['status']) && $_POST['status']) || $_POST['status'] == '0') {   
            $orders = $this->orderModel->alias('o')
                ->field('o.*')
                ->join('__ORDER_SETTLEMENT__ ost on o.id_order = ost.id_order','INNER')
                ->join('__ORDER_SHIPPING__ os ON o.id_order = os.id_order', 'INNER')
                ->where($where)->order("o.id_order DESC")
                ->limit(5000)->select();
        }else{
            if(isset($_POST['status_label']) && $_POST['status_label']){                
                $orders = $this->orderModel->alias('o')->field('o.*,os.date_signed')
                    //->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
//                    ->group('os.id_order')
                    ->order("o.id_order DESC")
                    ->limit(5000)
                    ->select();
            }else{
                $orders = $this->orderModel->alias('o')
                    ->where($where)
                    ->order("id_order ASC")
                    ->limit(5000)->select();
            }
        }

        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }

        $order_item = D('Order/OrderItem');
        $idx = 2;
        $shipping = D('Common/Shipping')->cache(true,36000)->select();
        $shipping_data = array_column($shipping,'title','id_shipping');
        $order_record = D("Order/OrderRecord");
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        foreach ($orders as $o) {
            $product_name = '';
            $sku = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            foreach ($products as $p) {
                $sku .=$p['sku']."   ";
                $product_name .= $p['product_title'] . "\n";
                $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                $product_count += $p['quantity'];
            }

            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $getShipObj = D("Order/OrderShipping")->alias('os')->field('track_number,status_label')->where(array('id_order'=>$o['id_order']))->select();//,s.title as shipping_name
                                                 //->join('__SHIPPING__ as s on s.id_shipping = os.id_shipping','left')
            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $shipping_name = $shipping_data[$o['id_shipping']];
            $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
            $warehouse = array_column($warehouse,'title','id_warehouse');
            //台湾地区的地址不需要加上省份,但是其他的地区需要带上
            if ($o['id_zone'] == 2) {
                $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
            } else {
                $address = trim(sprintf('%s%s%s%s',$o['province'], $o['city'], $o['area'], $o['address']));
            }
            $order_settl = M('OrderSettlement')->field('status')->where(array('id_order'=>$o['id_order']))->find();
            switch ($order_settl['status']) {
                case 0:
                    $settl_name = ($order_settl['status']===null)?'':'未结款';
                    break;
                case 1: $settl_name = '结款中';
                    break;
                case 2: $settl_name = '已结款';
                    break;
//                default:$setStatus = '';
            }
            $data = array(
                $all_zone[$o['id_zone']], $o['id_increment'], $o['first_name'].' '.$o['last_name'], 
                $product_name,$sku,$o['price_total'], $attrs,
                $address, $product_count, $o['remark'], $o['created_at'], $status_name,
                $o['date_delivery'], $trackNumber, $trackStatusLabel, $shipping_name,$o['zipcode'],$warehouse[$o['id_warehouse']],$settl_name,$o['problem_name']
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                if($key != 7 && $key != 10){
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                }else{
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;
            $order_record->addHistory($o['id_order'],$o['id_order_status'],4, '仓库管理 导出问题订单');
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出仓库问题订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }
    /**
     * 导出转寄中订单
     */
    public function export_forwarding_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $column = array(
            '地区', '订单号', '姓名', '电话号码', 
//            '地区', '订单号', '姓名',
            '产品名和价格', 'SKU','总价', '属性',
            '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
            '发货日期', '运单号', '快递单号','物流名称','邮编','仓库',
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

        $order_model = $this->orderModel;
        $where = $order_model->form_where($_GET,'o.');
        $id_order_status = I('get.status_id/i');
        if ($id_order_status <= 0) {
            $where['o.id_order_status'] = array('IN',array(\Order\Lib\OrderStatus::MATCH_FORWARDING));
        }
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = $_GET['zone_id'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            unset($where['o.id_warehouse']);
            $new_order_id = M('OrderForward')->where(array('warehouse_id'=>$_GET['id_warehouse']))->getField('new_order_id',true);
            $where['o.id_order'] = array('IN',$new_order_id);
        }
        if ($_GET['shipping_start_time'] or $_GET['shipping_end_time']) {
            $date_delivery_array = array();
            if ($_GET['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_GET['shipping_start_time']);
            if ($_GET['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_GET['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }

        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }

        $order_list = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')
            ->where($where)
            ->order("o.id_order DESC")
            ->select();

        $order_item = D('Order/OrderItem');
        $idx = 2;
        $shipping = D('Common/Shipping')->cache(true,36000)->select();
        $shipping_data = array_column($shipping,'title','id_shipping');
        $order_record = D("Order/OrderRecord");
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        foreach ($order_list as $o) {
            $product_name = '';
            $sku = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            foreach ($products as $p) {
                $sku .=$p['sku']."   ";
                $product_name .= $p['inner_name'] . "\n".'+'.$p['sku_title']. ' x ' . $p['quantity'] . ",";
                $attrs .= '';
                $product_count += $p['quantity'];
            }
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $getShipObj = D("Order/OrderShipping")->alias('os')->field('track_number,status_label')->where(array('id_order'=>$o['old_order_id']))->select();
            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $getShipObj_new = D("Order/OrderShipping")->alias('os')->field('track_number,status_label')->where(array('id_order'=>$o['new_order_id']))->select();
            $trackNumber_new = $getShipObj ? implode(',', array_column($getShipObj_new, 'track_number')) : '';
            $trackStatusLabel_new = $getShipObj ? implode(',', array_column($getShipObj_new, 'status_label')) : '';
            $shipping_name = $shipping_data[$o['id_shipping']];
            $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
            $warehouse = array_column($warehouse,'title','id_warehouse');
            //台湾地区的地址不需要加上省份,但是其他的地区需要带上
            if ($o['id_zone'] == 2) {
                $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
            } else {
                $address = trim(sprintf('%s%s%s%s',$o['province'], $o['city'], $o['area'], $o['address']));
            }
            $data = array(
                $all_zone[$o['id_zone']], $o['id_increment'], $o['first_name'].' '.$o['last_name'], $o['tel'],
//                $all_zone[$o['id_zone']], $o['id_increment'], $o['first_name'].' '.$o['last_name'],
                $product_name,$sku,$o['price_total'], $attrs,
                $address, $product_count, $o['remark'], $o['created_at'], $status_name,
                $o['date_delivery'], $trackNumber, $trackNumber_new,$shipping_name,$o['zipcode'],$warehouse[$o['warehouse_id']]
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                if($key != 7 && $key != 10){
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                }else{
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;

//            $order_record->addHistory($o['id_order'],$o['id_order_status'],4, '仓库管理 导出转寄仓订单');
            $history = array('id_order'=>$o['id_order'],'new_status_id'=>OrderStatus::MATCH_FORWARDED,'comment'=>'仓库管理 导出转寄中订单');
            D("Order/OrderStatus")->update_status_add_history($history);
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出转寄中订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();

    }

    /**
     * 导出转寄订单
     */
    public function export_forward_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $column = array(
            '地区', '订单号', '姓名', '电话号码', 
//            '地区', '订单号', '姓名',
            '产品名和价格', 'SKU','总价', '属性',
            '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
            '发货日期', '运单号', '快递单号','物流名称','邮编','仓库',
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

        $order_model = $this->orderModel;
        $where = $order_model->form_where($_GET,'o.');
        $id_order_status = I('get.status_id/i');
        if ($id_order_status <= 0) {
            $where['o.id_order_status'] = array('IN',array(\Order\Lib\OrderStatus::MATCH_FORWARDING,\Order\Lib\OrderStatus::MATCH_FORWARDED,OrderStatus::MATCH_FINISH,OrderStatus::DELIVERING));
        }
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = $_GET['zone_id'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            unset($where['o.id_warehouse']);
            $new_order_id = M('OrderForward')->where(array('warehouse_id'=>$_GET['id_warehouse']))->getField('new_order_id',true);
            $where['o.id_order'] = array('IN',$new_order_id);
        }
        if ($_GET['shipping_start_time'] or $_GET['shipping_end_time']) {
            $date_delivery_array = array();
            if ($_GET['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_GET['shipping_start_time']);
            if ($_GET['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_GET['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }

        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }

        $order_list = $order_model->alias('o')->join('__ORDER_FORWARD__ f ON (o.id_order=f.new_order_id)','LEFT')
            ->where($where)
            ->order("o.id_order DESC")
            ->select();

        $order_item = D('Order/OrderItem');
        $idx = 2;
        $shipping = D('Common/Shipping')->cache(true,36000)->select();
        $shipping_data = array_column($shipping,'title','id_shipping');
        $order_record = D("Order/OrderRecord");
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        foreach ($order_list as $o) {
            $product_name = '';
            $sku = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            foreach ($products as $p) {
                $sku .=$p['sku']."   ";
                $product_name .= $p['inner_name'] . "\n".'+'.$p['sku_title']. ' x ' . $p['quantity'] . ",";
                $attrs .= '';
                $product_count += $p['quantity'];
            }
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $getShipObj = D("Order/OrderShipping")->alias('os')->field('track_number,status_label')->where(array('id_order'=>$o['old_order_id']))->select();
            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $getShipObj_new = D("Order/OrderShipping")->alias('os')->field('track_number,status_label')->where(array('id_order'=>$o['new_order_id']))->select();
            $trackNumber_new = $getShipObj ? implode(',', array_column($getShipObj_new, 'track_number')) : '';
            $trackStatusLabel_new = $getShipObj ? implode(',', array_column($getShipObj_new, 'status_label')) : '';
            $shipping_name = $shipping_data[$o['id_shipping']];
            $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
            $warehouse = array_column($warehouse,'title','id_warehouse');
            //台湾地区的地址不需要加上省份,但是其他的地区需要带上
            if ($o['id_zone'] == 2) {
                $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
            } else {
                $address = trim(sprintf('%s%s%s%s',$o['province'], $o['city'], $o['area'], $o['address']));
            }
            $data = array(
                $all_zone[$o['id_zone']], $o['id_increment'], $o['first_name'].' '.$o['last_name'], $o['tel'], 
//                $all_zone[$o['id_zone']], $o['id_increment'], $o['first_name'].' '.$o['last_name'],
                $product_name,$sku,$o['price_total'], $attrs,
                $address, $product_count, $o['remark'], $o['created_at'], $status_name,
                $o['date_delivery'], $trackNumber, $trackNumber_new,$shipping_name,$o['zipcode'],$warehouse[$o['warehouse_id']]
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                if($key != 7 && $key != 10){
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                }else{
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;

            $order_record->addHistory($o['id_order'],$o['id_order_status'],4, '仓库管理 导出转寄仓订单');
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出转寄仓订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();

    }

    public function export_mar_html() {

        $count=5;

        $this->assign('count',$count);
        $this->display();
    }
    /**
     * 导出3月份的有效单
     */
    public function export_mar_order() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        ini_set("memory_limit", "1024M"); // 不够继续加大
        set_time_limit(0);
        $column = array(
            '地区', '订单号', '姓名', '电话号码', '邮箱',
            '产品名和价格', 'SKU','总价（NTS）', '属性',
            '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
            '发货日期', '运单号', '物流状态','物流名称','邮编','仓库','结款状态'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $_POST["department_id"]="0";
        $_POST["zone_id"]="0";
        $_POST["status_id"]="0";
        $_POST["status_label"]="0";
        $_POST["status"]="";
        $_POST["id_shipping"]="0";
        $_POST["id_warehouse"]="0";
        $_POST["keyword"]="";
        $_POST["start_time"]="2017-03-01 00:00";
        $_POST["end_time"]="2017-04-01 00:00";
        $_POST["shipping_start_time"]="";
        $_POST["shipping_end_time"]="";
        $_POST["sku"]="";

        $where = $this->orderModel->form_where($_POST,'o.');

        $effective_status = \Order\Lib\OrderStatus::get_effective_status();
        $where['id_order_status'] = array('IN',$effective_status);

        if ($_POST['shipping_start_time'] or $_POST['shipping_end_time']) {
            $date_delivery_array = array();
            if ($_POST['shipping_start_time'])
                $date_delivery_array[] = array('EGT', $_POST['shipping_start_time']);
            if ($_POST['shipping_end_time'])
                $date_delivery_array[] = array('LT', $_POST['shipping_end_time']);
            $where['o.date_delivery'] = $date_delivery_array;
        }


        if(strip_tags(trim($_POST['status_label'])) == '空') {
            $where['_string'] = "(os.status_label='' or os.status_label is null)";
        } elseif(trim($_POST['status_label'])) {
            $where['os.status_label'] = strip_tags(trim($_POST['status_label']));
        }
        if((isset($_POST['status']) && $_POST['status']) || $_POST['status'] == '0') {
            $where['ost.status'] = $_POST['status'];
        }

        if($_GET['part']==1){
            $limit="0,20000";
        }elseif ($_GET['part']==2){
            $limit="20000,20000";
        }elseif($_GET['part']==3){
            $limit="40000,20000";
        }elseif($_GET['part']==4){
            $limit="60000,20000";
        }elseif($_GET['part']==5){
            $limit="80000,20000";
        }elseif($_GET['part']==6){
            $limit="100000,20000";
        }elseif($_GET['part']==7){
            $limit="120000,20000";
        }
        $orders = $this->orderModel->alias('o')
                ->field('o.*')
                ->join('__ORDER_SETTLEMENT__ ost on o.id_order = ost.id_order','INNER')
                ->join('__ORDER_SHIPPING__ os ON o.id_order = os.id_order', 'INNER')
                ->where($where)->order("o.id_order DESC")
                ->limit($limit)->select();

        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }
//        dump($status);die;
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        $shipping = D('Common/Shipping')->cache(true,36000)->select();
        $shipping_data = array_column($shipping,'title','id_shipping');
        /** @var \Order\Model\OrderRecordModel  $order_record */
        $order_record = D("Order/OrderRecord");
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        foreach ($orders as $o) {
            $product_name = '';
            $sku = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            foreach ($products as $p) {
                $sku .=$p['sku']."   ";
                $product_name .= $p['product_title'] . "\n";
                $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                $product_count += $p['quantity'];
            }
//            dump($sku);
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $getShipObj = D("Order/OrderShipping")->alias('os')->field('track_number,status_label')//,s.title as shipping_name
            //->join('__SHIPPING__ as s on s.id_shipping = os.id_shipping','left')
            ->where(array('id_order'=>$o['id_order']))->select();

            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $shipping_name = $shipping_data[$o['id_shipping']];
            $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
            $warehouse = array_column($warehouse,'title','id_warehouse');
            //台湾地区的地址不需要加上省份,但是其他的地区需要带上
            if ($o['id_zone'] == 2) {
                $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
            } else {
                $address = trim(sprintf('%s%s%s%s',$o['province'], $o['city'], $o['area'], $o['address']));
            }
            $order_settl = M('OrderSettlement')->field('status')->where(array('id_order'=>$o['id_order']))->find();
            switch ($order_settl['status']) {
                case 0:
                    $settl_name = ($order_settl['status']===null)?'':'未结款';
                    break;
                case 1: $settl_name = '结款中';
                    break;
                case 2: $settl_name = '已结款';
                    break;
//                default:$setStatus = '';
            }
            $data = array(
                $all_zone[$o['id_zone']], $o['id_increment'], $o['first_name'].' '.$o['last_name'], $o['tel'], $o['email'],
                $product_name,$sku,$o['price_total'], $attrs,
                $address, $product_count, $o['remark'], $o['created_at'], $status_name,
                $o['date_delivery'], $trackNumber, $trackStatusLabel, $shipping_name,$o['zipcode'],$warehouse[$o['id_warehouse']],$settl_name
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                if($key != 7 && $key != 10){
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                }else{
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
//                $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
            $order_record->addHistory($o['id_order'],$o['id_order_status'],4, '仓库管理 订单列表 导出订单');
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '3月有效单订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '3月有效单订单信息'.$_GET['part'].'.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '3月有效单订单信息'.$_GET['part'].'.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');


        exit();
    }

    /**
     * 处理编辑订单
     */
    public function save_edit_order_out() {
        $id_orderout = I('get.id');
        if(IS_POST)
        {
            $data = I('post.');
            $update_data = array();
            $update_data['first_name'] = $data['first_name'];
            $update_data['last_name'] = $data['last_name'];
            $update_data['tel'] = $data['tel'];
            $update_data['email'] = $data['email'];
            $update_data['address'] = $data['address'];
            $update_data['price_total'] = $data['price_total'];
            $update_data['comment'] = $data['comment'];
            $res = D('Order/Orderout')->where(array('id_orderout' => $id_orderout))->save($update_data);
            if ($res)
            {
                $this->success("修改成功！");
            }
        }
        else
        {
            $this->error("修改失败！", U('order/edit_order_out', array('id' => $id_orderout)));
        }
    }
    
    /**
     * 批量取消订单
     */
    public function  more_cancel(){
        $info = array( 'error' => array(),'warning' => array(),'success' => array() );
        $update_data=array('status_id'=>14,'comment'=>'【仓储管理取消】');
        $total=0;
        $statusarr=M('orderStatus')->where(array('status'=>1))->getField('id_order_status,title');
        if($_POST){
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'more_cancel', $data);
            $data = $this->getDataRow($data);
            $total=count($data);
            foreach ($data as $key=>$id_increment){
                $id_increment=  trim($id_increment);
                $orderinfo=M('order')->where(array('id_increment'=>$id_increment))->field('*')->find();
                if(empty($orderinfo)){
                    $info['warning'][]=sprintf('第%s行  :%s 无法匹配该订单号信息！', ($key+1), $id_increment);
                }else if(!in_array($orderinfo['id_order_status'],array(1,3,22,4,5,7,6))){
                $info['error'][]=sprintf('第%s行  :%s 该订单状态为：%s, 不能进行取消处理！', ($key+1), $id_increment,$statusarr[$orderinfo['id_order_status']]);
                }else{
                    $update_data['id']=$orderinfo['id_order'];
                    $updRes=UpdateStatusModel::cancel($update_data);
                    $info['success'][]=sprintf('第%s行  :%s 取消订单成功！', ($key+1), $id_increment);
                }
            }
            add_system_record(sp_get_current_admin_id(), 2, 4, '仓库批量取消订单');
        }
        $this->assign('infor', $info);
        $this->assign('data', I('post.data'));
        $this->assign('total',$total );          
        $this->display();
    }
    

}
