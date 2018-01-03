<?php

namespace Settlement\Controller;

use Common\Controller\AdminbaseController;
use Order\Model\UpdateStatusModel;
use Purchase\Lib\PurchaseStatus;
use Think\Exception;
header("content-type:text/html;charset=utf-8;");
/**
 * 部门模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Settlement\Controller
 */
class IndexController extends AdminbaseController {
    private $lock_write_data = 'lock_check_data.lock';
    protected $order;
    protected $page;
    protected $orderSettlement;

    public function _initialize() {
        parent::_initialize();
        $this->orderModel = D("Order/Order");
        $this->orderSettlement = D("Order/OrderSettlement");
        $this->page = $_SESSION['set_page_row'] ?(int)$_SESSION['set_page_row'] : 20;
        $this->Purchase = D("Common/Purchase");
        $this->PurchaseProduct = D("Common/PurchaseProduct");
        $this->PurchaseIn = M("PurchaseIn");
        $this->PurchaseInitem = M("PurchaseInitem");
        $this->ordShipFormObj = M('OrderShippingFormalities');
        $this->Users = D("Common/Users");
        $this->time_start = I('get.start_time', date('Y-m-d 00:00', strtotime('-7 day')));
        $this->time_end = I('get.end_time', date('Y-m-d 00:00', strtotime('+1 day')));
    }

    /*
     * 结算列表
     * */

    public function index() {
        ini_set("memory_limit","-1");
        $t1 = microtime(true);
        //D("Common/OrderSettlement")->shippingTrace();
        $M = new \Think\Model;
        $ord_table = D("Order/Order")->getTableName();
        $ord_set_table = D("Order/OrderSettlement")->getTableName();
        $where = $this->get_filter_condition();

        $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的

        if(isset($_GET['summary_status_label']) && $_GET['summary_status_label']){
             $where['or_s.summary_status_label'] = trim($_GET['summary_status_label']);
            $find_count = $M->table($ord_set_table . ' AS os LEFT JOIN ' . $ord_table . ' AS o ON o.id_order=os.id_order')
                ->field('count(os.id_order_settlement) as count')
                ->join('__ORDER_SHIPPING__ or_s on or_s.id_order = os.id_order','LEFT')
                ->where($where)->count();
            $page = $this->page($find_count, 20);
            $order_list = $M->table($ord_set_table . ' AS os LEFT JOIN ' . $ord_table . ' AS o ON o.id_order=os.id_order')
                ->field('DISTINCT os.id_order,o.order_count,o.id_domain,o.first_name,o.id_shipping,o.id_increment,o.created_at as order_create_at,o.id_department,os.*,or_s.summary_status_label,o.date_delivery,o.id_department_master,o.id_order_status,or_s.weight') //添加重量显示-or_s.weight zx 11/27
                ->join('__ORDER_SHIPPING__ AS or_s on or_s.id_order = os.id_order','LEFT')
                ->where($where)->order('os.id_order desc')->group('os.id_order')
                ->limit($page->firstRow, $page->listRows)->select();
        }else{
            $find_count = $M->table($ord_set_table . ' AS os LEFT JOIN ' . $ord_table . ' AS o ON o.id_order=os.id_order')
                ->field('count(os.id_order_settlement) as count')
                 ->join('__ORDER_SHIPPING__ AS or_s on or_s.id_order = os.id_order','LEFT')
                ->where($where)->count();
            $page = $this->page($find_count, 20);

            $order_list = $M->table($ord_set_table . ' AS os LEFT JOIN ' . $ord_table . ' AS o ON o.id_order=os.id_order')
                ->field('DISTINCT os.id_order,o.order_count,o.id_domain,o.first_name,o.id_shipping,o.id_increment,o.created_at as order_create_at,o.id_department,os.*,o.date_delivery,o.id_department_master,o.id_order_status,or_s.weight') //添加重量显示-or_s.weight zx 11/27
                 ->join('__ORDER_SHIPPING__ AS or_s on or_s.id_order = os.id_order','LEFT')
                ->where($where)->order('os.id_order desc')->group('os.id_order')
                ->limit($page->firstRow, $page->listRows)->select();

         }

        $sql=$M->getlastSql();
        $t2 = microtime(true);
        /** @var \Common\Model\OrderItemModel $order_item */
        //统计和获取订单ID，添加关联订单运单号表，关联物流不同状态的订单  liuruibin   20171019
        $amount_settlement = $M->table($ord_set_table . ' AS os LEFT JOIN ' . $ord_table . ' AS o ON o.id_order=os.id_order')
                ->join('__ORDER_SHIPPING__ or_s on or_s.id_order = os.id_order','LEFT')
                ->where($where)->sum("amount_settlement");
        $order_id = $M->table($ord_set_table . ' AS os LEFT JOIN ' . $ord_table . ' AS o ON o.id_order=os.id_order')
                ->join('__ORDER_SHIPPING__ or_s on or_s.id_order = os.id_order','LEFT')
                ->where($where)->field("os.id_order")->select();



        $order_item = D("Order/OrderItem");
        $ord_shipping = D("Order/OrderShipping");
        if($order_id){
             $order_id = array_column($order_id, "id_order");
            $feight = $ord_shipping->field('freight')->where(['id_order'=>['IN',$order_id]])->sum("freight");
            $fee = $ord_shipping->field('formalities_fee')->where(['id_order'=>['IN',$order_id]])->sum("formalities_fee");
        }
        foreach ($order_list as $key => $o) {

            $order_ship   = $ord_shipping->where('id_order=' . $o['id_order'])->select();
            $status_array = $order_ship?array_column($order_ship,'status_label'):'';
            $summary_status_label = $order_ship?array_column($order_ship,'summary_status_label'):'';
            $date_signed = $ord_shipping->field('date_signed')->where('id_order=' . $o['id_order'])->find();
            $freight = $ord_shipping->field('freight')->where('id_order=' . $o['id_order'])->find();
            $formalities_fee = $ord_shipping->field('formalities_fee')->where('id_order=' . $o['id_order'])->find();
            $id_user = D('Order/Order')->where('id_order=' . $o['id_order'])->getField('id_users');
            $order_list[$key]['summary_status_label'] = $summary_status_label ? implode(',', $summary_status_label) : '';
            $order_list[$key]['track_label'] = $status_array ? implode(',', $status_array) : '';
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['date_signed'] = $date_signed['date_signed'];
            $order_list[$key]['return_time'] =M('OrderReturn')->where('id_order=' . $o['id_order'])->getField('date_return');
            $order_list[$key]['freight'] = $freight['freight'];
            $order_list[$key]['date_online'] = $order_ship[0]['date_online'];//物流信息第一次获取时间  zhujie 20171107
            $order_list[$key]['formalities_fee'] = $formalities_fee['formalities_fee'];
            $order_list[$key]['track_number'] = D('Order/OrderShipping')->where('id_order='.$o['id_order'])->getField('track_number');
            $order_list[$key]['name'] = D('Users')->where('id='.(int)$id_user)->getField('user_nicename');
            $order_list[$key]['depart'] = M('Department')->where(array('id_department'=>$o['id_department']))->getField('title');
            $order_list[$key]['order_status'] = M("OrderStatus")->where(array('id_order_status'=>$o['id_order_status']))->getField("title");

            //这里筛出手续费表对应的订单号的其它费用项-导入的手续费  liuruibin   20171025
            $ordShipFormObj = M('OrderShippingFormalities');
            $getOrdShipForm = $ordShipFormObj->where('id_order ='.$o['id_order'])->find();
            $order_list[$key]['surcharge'] = $getOrdShipForm['surcharge'];//附加费
            $order_list[$key]['back_fee'] = $getOrdShipForm['back_fee'];//返款手续费
            $order_list[$key]['collection_fee'] = $getOrdShipForm['collection_fee'];//代收手续费
            $order_list[$key]['refund_fee'] = $getOrdShipForm['refund_fee'];//退款手续费
            $order_list[$key]['forward_fee'] = $getOrdShipForm['forward_fee'];//转寄费
            $order_list[$key]['slotting_fee'] = $getOrdShipForm['slotting_fee'];//上架费
            $order_list[$key]['operation_fee'] = $getOrdShipForm['operation_fee'];//作业费
            $order_list[$key]['other_fee'] = $getOrdShipForm['other_fee'];//其他费用
            $order_list[$key]['stat_total_fee'] = $freight['freight']+$formalities_fee['formalities_fee'];//导入的手续费统计


     }
        $t3 = microtime(true);
        $shipping = D("Common/Shipping")->field('id_shipping,title')->where("status=1")->cache(true, 86400)->select();
        $ship_temp = $shipping ? array_column($shipping, 'title', 'id_shipping') : array();
        $summary_status_label = D('Order/OrderShipping')//->field('summary_status_label')
            ->where("summary_status_label is not null")
            ->group('summary_status_label')->cache(true, 12000)->getField('summary_status_label',true);
        $t4 = microtime(true);
        $this->assign('summary_status',$summary_status_label);
        /** @var \Common\Model\AdvertProductModel $advert */
        $zone = M('Zone')->select();
        $department = M('Department')->where('type=1')->cache(true, 86400)->select();
        // 获取 所有的订单状态   --Lily 2017-10-18
        $orderStatus = M("OrderStatus")->field('id_order_status,title')->select();
        $userList=M("users")->where(array('user_status'=>1))->getField('id,user_nicename');
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看结款结算列表');
        $this->assign('amount_settlement', $amount_settlement); //结款金额  Lily 2017-10-30
        $this->assign('feight', $feight); //运费金额  Lily 2017-10-30
        $this->assign('fee', $fee); //手续费金额  Lily 2017-10-30
        $this->assign('t1', $t1);
        $this->assign('t2', $t2);
        $this->assign('t3', $t3);
        $this->assign('t4', $t4);
        $this->assign('sql', $sql);
        $this->assign('department',$department);
        $this->assign('userList',$userList);
        $this->assign("orderList", $order_list);
        $this->assign('shipping', $ship_temp);
        $this->assign("page", $page->show('Admin'));
        $this->assign('zone',$zone);
        $this->assign('orderStatus',$orderStatus);
        $this->display();
    }

    /**
     * 订单列表
     */
    public function orderlist() {
        /** @var  $orderObj  \Order\Model\OrderModel */
        $orderObj = D("Order/Order");
        $zone = M('Zone')->select();
        $getFormWhere = $orderObj->form_where($_GET, 'o.');
        if(isset($_GET['summary_status_label']) && $_GET['summary_status_label']){
            $where['or_s.summary_status_label'] = trim($_GET['summary_status_label']);
        }
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $getFormWhere['id_zone'] = $_GET['zone_id'];
        }
//        if(isset($_GET['shipping_id']) && $_GET['shipping_id'] && $_GET['shipping_id'] != '-1'){
//            $getFormWhere['o.id_shipping'] = $_GET['shipping_id'];
//        }elseif($_GET['shipping_id'] === '0' || $_GET['shipping_id'] >= '0') {
//            $getFormWhere['o.id_shipping'] = 0;
//        }
        if(isset($_GET['shipping_id']) && $_GET['shipping_id'] && $_GET['shipping_id'] != '-1'){
            $getFormWhere['o.id_shipping'] = array('IN',$_GET['shipping_id']);
        }elseif($_GET['shipping_id'] === '0' || $_GET['shipping_id'] >= '0') {
            $getFormWhere['o.id_shipping'] = 0;
        }
        if(isset($_GET['status_id']) && $_GET['status_id']) {
            if($_GET['status_id']==16){
                $getFormWhere['o.refused_to_sign'] = 1;
            }else{
                $getFormWhere['o.id_order_status'] = $_GET['status_id'];
            }
        } else {
            $getFormWhere['o.id_order_status'] = array('NOT IN',array(1,2,3,11,12,13,14,15));//去除无效单
        }
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $getFormWhere['os.status'] = $_GET['status'];
        }
        $getFormWhere['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
        if (isset($_GET['action']) && $_GET['action'] == 'repeat' && $_GET['id']) {//查询重复的订单
            $order = $orderObj->find($_GET['id']);
            $orderPro = D("Order/OrderItem")->where(array('id_order' => $_GET['id']))->select();
            $proId = array();
            if ($orderPro) {
                foreach ($orderPro as $pro) {
                    $proId[] = $pro['id_product'];
                }
            }
            $M = new \Think\Model;
            $ordName = $orderObj->getTableName();
            $ordIteName = D("Order/OrderItem")->getTableName();
            $repWhere = array('o.id_shipping' => $order['id_shipping'], 'o.first_name' => $order['first_name'], 'o.tel' => $order['tel']);
            if ($proId) {
                $repWhere['oi.id_product'] = array('IN', $proId);
            }
            $findRepeat = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id=oi.order_id')
                ->field('o.id_order')->where($repWhere)->group('o.id_order')->select();
            if ($findRepeat) {
                foreach ($findRepeat as $repeat) {
                    $orderIn[] = $repeat['id_order'];
                }
                if ($orderIn) {
                    $getFormWhere['o.id_order'] = array('IN', $orderIn);
                }
            }
        }
        $todayDate = date('Y-m-d') . ' 00:00:00';
        $formData = array();

        $formData['shipping'] = M('Order')->field('id_shipping')
            ->group('id_shipping')->cache(true, 12000)->select();
//        dump($form_data['shipping']);die;
        $arr = array();
        foreach ($formData['shipping'] as $k=>$v) {
            $arr[$v['id_shipping']] = M('Shipping')->where(array('id_shipping'=>$v['id_shipping']))->getField('title');
            if($v['id_shipping'] == 0) {
                $arr[$v['id_shipping']] = '空';
            }
        }
        $formData['shipping'] = $arr;
        $formData['track_status'] = D('Order/OrderShipping')->field('summary_status_label')
            ->where("summary_status_label is not null or summary_status_label !='' ")
            ->group('summary_status_label')->cache(true, 36000)->select();

        $todayWebWhere = array('created_at' => array('EGT', $todayDate));
        //部门权限条件
        $getFormWhere['id_department'] = array('in',$_SESSION['department_id']);
        //部门权限条件
        $order_settlement = D('Order/OrderSettlement')->getTableName();
        $baseSql = $orderObj->alias('o')->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')->where($getFormWhere);
        //修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
        if (isset($_GET['summary_status_label']) && $_GET['summary_status_label']) {
            $getFormWhere['s.summary_status_label'] = strip_tags(trim($_GET['summary_status_label']));
            $count = M('order')->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')
                ->where($getFormWhere)->count();
            $todayTotal = M('order')->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')
                ->where($getFormWhere)
                ->where(array('o.created_at' => array('EGT', $todayDate)))->count();

            $page = $this->page($count, $this->page);
            $orderList = M('order')->alias('o')->field('o.*,s.date_signed,s.freight,s.formalities_fee,os.status')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')
                ->where($getFormWhere)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        } elseif (isset($_GET['status']) && $_GET['status'] !== '') {

           if($_GET['status']==0){
//               $getFormWhere['os.status'] = ['IN',[1,2]];
//               $orderIDList = M('order')->alias('o')->field('o.id_order')
//                   ->join('__ORDER_SETTLEMENT__ os ON (o.id_order = os.id_order)', 'LEFT')
//                   ->where($getFormWhere)->order("id_order DESC")->group('o.id_order')->select();
//               if ($orderIDList) {
//                   foreach ($orderIDList as $repeat2) {
//                       $orderIn2[] = $repeat2['id_order'];
//                   }
//                   if ($orderIn2) {
//                       $getFormWhere['o.id_order'] = array('NOTIN', $orderIn2);
//                   }
//               }
//               unset( $getFormWhere['os.status']);
               $getFormWhere['_string'] .= " and (os.status =0 or os.status IS NULL)";
           }else{
               $getFormWhere['os.status'] = $_GET['status'];
           }
            $count = M('order')->alias('o')
                    ->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')
                ->where($getFormWhere)->count();
            $todayTotal = M('order')->alias('o')
                    ->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')
                ->where($getFormWhere)
                ->where(array('o.created_at' => array('EGT', $todayDate)))->count();
            $page = $this->page($count, $this->page);
            $orderList = M('order')->alias('o')->field('o.*,os.status')
                ->where($getFormWhere)->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        } else {
            $count = $baseSql->count();
            $todayTotal = M('order')->alias('o')->where($getFormWhere)->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')
                ->where(array('o.created_at' => array('EGT', $todayDate)))->count();
            $page = $this->page($count, $this->page);
            $orderList = $baseSql->alias('o')->field('o.*,os.status')->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')->where($getFormWhere)
                ->order("o.id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        }
//        var_dump($baseSql->getLastSql());die();
//        echo $orderList;
        /** @var \Common\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        /** @var \Common\Model\OrderSettlementModel $order_settlement */


        foreach ($orderList as $key => $o) {
            $orderList[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $selectSet = D('Order/OrderSettlement')->field('date_settlement,status,amount_settlement,rejected_time')->where('id_order=' . $o['id_order'])->find();
            $date_signed = D('Order/OrderShipping')->where('id_order=' . $o['id_order'])->field('date_signed,freight,weight,formalities_fee')->find(); //增加重量显示-weight zx 11/27
            switch ($selectSet['status']) {
                case 0:
                    $setStatus = '未结款';
                    break;
                case 1:
                    $setStatus = '结款中';
                    break;
                case 2:
                    $setStatus = '已结款';
                    break;
                default:
                    $setStatus = '';
            }
            $orderList[$key]['set_status_label'] = $setStatus;
            $orderList[$key]['date_settlement'] = $selectSet['date_settlement'];
            $orderList[$key]['rejected_time'] = $selectSet['rejected_time'];
            $orderList[$key]['return_time'] =M('OrderReturn')->where('id_order=' . $o['id_order'])->getField('date_return');
            $orderList[$key]['settlement_amount'] = !empty($selectSet['amount_settlement']) ? \Common\Lib\Currency::format($selectSet['amount_settlement'],$o['currency_code']) : '';
            $orderList[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $orderList[$key]['signed_for_date'] = $date_signed['date_signed'];
            $orderList[$key]['freight'] = $date_signed['freight'];
            $orderList[$key]['formalities_fee'] = $date_signed['formalities_fee'];//手续费
            $orderList[$key]['shipping_weight'] = $date_signed['weight']; //增加重量显示-weight zx 11/27
            $id_user = M('Order')->where('id_order=' . $o['id_order'])->getField('id_users');
            $orderList[$key]['name'] = !empty($id_user) ? M('Users')->where('id='.$id_user)->getField('user_nicename') : '';
            $orderList[$key]['shipping_name'] = M('Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
            $orderList[$key]['zone'] = M('Zone')->where(array('id_zone'=>$o['id_zone']))->getField('title');
            $orderList[$key]['depart'] = M('Department')->where(array('id_department'=>$o['id_department']))->getField('title');
        }

        $shipping = D("Common/Shipping")->field('id_shipping,title')->cache(true, 86400)->select();
        $ship_temp = $shipping ? array_column($shipping, 'title', 'id_shipping') : array();

        //部门列表
         $department = M('department')->field('id_department,title')->where(array('type' => 1))->where(array('id_department'=>array('in',$_SESSION['department_id'])))->order('sort asc')->select();
        //该部门列表显示不全，已替换
        //$department = UpdateStatusModel::sort_department();
        $userList=M("users")->where(array('user_status'=>1))->getField('id,user_nicename');
        $warehouse = M('Warehouse')->cache(true, 86400)->select();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看结款订单列表');
        $this->assign('department',$department);
        $this->assign('userList',$userList);
        $this->assign('warehouse',$warehouse);
        $this->assign("getData", $_GET);
        $this->assign("form_data", $formData);
        $this->assign("page", $page->show('Admin'));
        $this->assign("todayTotal", $todayTotal);
        $this->assign("allOrder", $count);
        //$this->assign("todayWebData", $allWebTotal);
        $this->assign("order_list", $orderList);
        $this->assign('zone',$zone);
        $this->assign('shipping', $ship_temp);
        $this->display();

    }

    public function order_info() {
        $order_id = I('get.id');
        $order = D("Order/Order")->find($order_id);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $orderHistory = D("Order/OrderRecord")
            ->field('*')
            ->join('__USERS__ u ON (__ORDER_RECORD__.id_users = u.id)', 'LEFT')
            ->where(array('id_order'=>$order_id))
            ->order('created_at desc')->select();
        $shipping = D('Common/Shipping')
            ->where(array('id_shipping'=>(int)$order['id_shipping']))
            ->find();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $all_domain = $domain_model->get_all_domain();
        $order['id_domain'] = $all_domain[$order['id_domain']];
        $order['id_order_status'] = $statusLabel[$order['id_order_status']];
        $products = D('Order/OrderItem')->get_item_list($order['id_order']);
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看结款订单详情');
        $this->assign("order", $order);
        $this->assign("products", $products);
        $this->assign("history", $orderHistory);
        $this->assign("label", $statusLabel);
        $this->assign('shipping_name', $shipping['title']);
        $this->assign('shopping_url', $shipping['track_url']);
        $this->display();
    }

    /*
     * 结算分组
     * */

    public function grouping() {
        if (IS_POST) {
            $data = I('post.data');
            $data = $this->getDataRow($data);
            $count = 1;
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $total = 0;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 3);
                if (count($row) != 3 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
            }
        }
        $this->display();
    }

    public function groupingexcel(){
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();
        $ordShipping = D('Order/OrderShipping');
        if (IS_POST) {
            $data = I('post.data');

            //导出记录到文件
            $path = write_file('settlement', 'grouping', $data);

            $data = $this->getDataRow($data);
            //$count = 1;
            //$user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $total = 0;
            $column = array('组名','订单号','运单号', '运费', '手续费');
            $j = 65;
            foreach ($column as $col) {
                $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
                ++$j;
            }
            $idx = 2;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 3);
                $row = array_filter($row);
                $trackNumber = trim($row[0]);
                $groupName = '';
                $orderId = '';
                $id_increment = '';
                if($trackNumber){
                    $ordShipObj = $ordShipping->field('id_order')->where(array('track_number'=>$trackNumber))->find();
                    $orderId    = $ordShipObj['id_order'];
                    if($ordShipObj){
                        $order_list = M('Order')->field('id_department,id_increment,created_at')->where(array('id_order'=>$orderId))->find();
                        $id_increment = $order_list['id_increment'];
                        $id_department = $order_list['id_department'];
                        $groupName = M('department')->where(array('id_department'=>$id_department,'type'=>1))->cache(true,3600)->getField('title');
                        if($order_list['id_department']==3){
                            if(strtotime($order_list['created_at'])>strtotime('2017-08-01 00:00:00')){
                                $groupName .= ' 刘鑫组';
                            }else{
                                if(strtotime($order_list['created_at'])>strtotime('2017-03-01 00:00:00')){
                                    $groupName .= ' 袁昭明组';
                                }else{
                                    $groupName .= ' 王园林组';
                                }
                            }
                        }else if($order_list['id_department']==2){
                            $groupName .= strtotime($order_list['created_at'])>strtotime('2017-07-01 00:00:00')?' 艾聪组':' 黄绍伟组';
                        }else if($order_list['id_department']==7){
                            $groupName .= strtotime($order_list['created_at'])>strtotime('2017-08-01 00:00:00')?' 万繁平组':' 林晓忠组';
                        }else if($order_list['id_department']==30){
                            $groupName .= strtotime($order_list['created_at'])>strtotime('2017-08-01 00:00:00')?' 刘帅宏组':' 藏述正组';
                        }else if($order_list['id_department']==1){
                            $groupName .= strtotime($order_list['created_at'])>strtotime('2017-08-01 00:00:00')?' 吕鑫组':' 陈学建组';
                        }
                    }
                }
                $rowData = array($groupName,$id_increment,$row[0],$row[1],$row[2]);
                $j = 65;
                foreach ($rowData as $key=>$col) {
                    if($key != 3 && $key != 4){
                        $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                    }else{
                        $excel->getActiveSheet()->setCellValue(chr($j).$idx, $col);
                    }
                    ++$j;
                }
                ++$idx;
            }
        }else{
            $this->error("上传失败，文件格式错误！");
        }
        add_system_record(sp_get_current_admin_id(), 7, 3, '导出运单分组信息',$path);
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '运单分组信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '运单分组信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    /**
     * 获取过滤的条件
     * @return array
     */
    protected function get_filter_condition() {
        $where = array();

        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $where['os.status'] = $_GET['status'] ? $_GET['status'] : 0;
        }
        if (isset($_GET['keyword']) && $_GET['keyword']) {
            $return_or['id_increment'] = array('LIKE', '%' . $_GET['keyword'] . '%');
            $return_or['id_domain'] = array('LIKE', '%' . $_GET['keyword'] . '%');
            $return_or['first_name'] = array('LIKE', '%' . $_GET['keyword'] . '%');
            $return_or['tel'] = array('LIKE', '%' . $_GET['keyword'] . '%');
            // 邮箱   地址  备注  广告专员的查询  因为前台的广告专员是重组数组循环时从用户表里面查出来的，这里查询用户表获取用户ID 作为订单表的查询条件  --Lily 2017-11-10
            if($_GET['keywordtype']=='username'){
                $return_o['user_nicename'] = array("LIKE",'%'.$_GET['keyword'].'%');
                $user = M("Users")->where($return_o)->getField("id",true);
                 if($user){
                $return_or['o.id_users'] = array("eq",$user[0]);
            }
            }
            $return_or['o.email'] = array('LIKE', '%' . $_GET['keyword'] . '%');
            $return_or['o.address'] = array('LIKE', '%' . $_GET['keyword'] . '%');
            $return_or['o.remark'] = array('LIKE', '%' . $_GET['keyword'] . '%');
           $product_name = M('OrderItem')->field('id_order')->where(array('sale_title'=>array('LIKE', '%' . $get_data['keyword'] . '%')))->select();//查找产品名称
            if($product_name) {
                $order_ids = array_column($product_name, 'id_order');
                $key_where['id_order'] = array('IN', $order_ids);
            }
            $return_or['_logic'] = 'or';
            $where['_complex'] = $return_or;
        }
        if (empty($where)) {
            $where['os.status'] = array('IN', array(0, 1, 2));
        }
        if ($_GET['start_time'] or $_GET['end_time']) {//搜索物流运单号表的订单
            $create_at_array = array();
            if ($_GET['start_time'])
                $create_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $create_at_array[] = array('LT', $_GET['end_time']);
            // $get_order_id = D('Order/OrderShipping')
            //     ->where(array('date_signed' => $create_at_array, 'status_label' => '順利送達'))
            //     ->getField('id_order', true);
            // if ($get_order_id) {
            //     $where['o.id_order'] = array('IN', $get_order_id);
            // } else {
            //     $where['o.id_order'] = 0;
            // }
            $where['or_s.date_signed'] = $create_at_array;
        }
        if (isset($_GET['start_date_settlement'])&& ($_GET['start_date_settlement'] or $_GET['end_date_settlement'])) {
            $date_sett_where = array();
            if ($_GET['start_date_settlement']){
                $date_sett_where[] = array('EGT', $_GET['start_date_settlement']);
            }
            if ($_GET['start_date_settlement'] && $_GET['end_date_settlement']){
                $date_sett_where[] = array('LT', $_GET['end_date_settlement']);
            }
            $where['os.date_settlement'] = $date_sett_where;
        }

        if (isset($_GET['start_created_at'])&& ($_GET['start_created_at'] or $_GET['end_created_at'])) {
            $date_sett_where = array();
            if ($_GET['start_created_at']){
                $date_sett_where[] = array('EGT', $_GET['start_created_at']);
            }
            if ($_GET['end_created_at'] && $_GET['end_created_at']){
                $date_sett_where[] = array('LT', $_GET['end_created_at']);
            }
            $where['o.created_at'] = $date_sett_where;
        }

        if (isset($_GET['start_date_delivery'])&& ($_GET['start_date_delivery'] or $_GET['end_date_delivery'])) {
            $date_sett_where = array();
            if ($_GET['start_date_delivery']){
                $date_sett_where[] = array('EGT', $_GET['start_date_delivery']);
            }
            if ($_GET['end_date_delivery'] && $_GET['end_date_delivery']){
                $date_sett_where[] = array('LT', $_GET['end_date_delivery']);
            }
            $where['o.date_delivery'] = $date_sett_where;
        }

        if (isset($_GET['shipping_id']) && $_GET['shipping_id']) {
            $where['o.id_shipping'] = array('IN', explode(',', trim($_GET['shipping_id'])));
        }
        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where['o.id_department'] = array('IN', explode(',', trim($_GET['department_id'])));
        }
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = array('EQ', (int) $_GET['zone_id']);
        }
        if (isset($_GET['track_number']) && $_GET['track_number']) {
            // $get_order_id = D('Order/OrderShipping')->field('id_order')
            //     ->where(array('track_number' => trim($_GET['track_number'])))
            //     ->find();
            // if ($get_order_id) {
            //     $where['o.id_order'] = $get_order_id['id_order'];
            // }
            $where['or_s.track_number'] = array("LIKE",'%'.trim($_GET['track_number'].'%'));
        }
        if(isset($_GET['id_order_status']) && $_GET['id_order_status']){
            $where['o.id_order_status'] = $_GET['id_order_status'];
        }
        return $where;
    }

    //导出结算列表
    public function export_search(){
//        ini_set("memory_limit","-1");
        set_time_limit(0);
        ini_set("memory_limit","-1");
        $M = new \Think\Model;
        $ordTable = D("Order/Order")->getTableName();
        $ordSetTable = D("Order/OrderSettlement")->getTableName();
        $where  = $this->get_filter_condition();
        if(isset($_GET['summary_status_label']) && $_GET['summary_status_label']){
            $where['or_s.summary_status_label'] = trim($_GET['summary_status_label']);
        }
        // if(isset($_GET['shipping_id']) && $_GET['shipping_id']){
        //     if(stripos($_GET['shipping_id'],",")){
        //         $where['os.id_order_shipping'] = array("IN",$_GET['shipping_id']);
        //     }else{
        //         $where['os.id_order_shipping'] = $_GET['shipping_id'];
        //     }
        // }
        $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
        $proList = $M->table($ordSetTable . ' AS os LEFT JOIN ' . $ordTable . ' AS o ON o.id_order=os.id_order')
            //增加两个链表查询的 select 字段 11/7
            //增加重量or_s.weight 显示 zx 11/27
            ->field('o.id_increment,o.id_users as sales_name,o.id_shipping as delivery_shipping_id,o.province,o.first_name,o.tel,o.email,o.created_at as order_created_at,o.id_department,os.*,o.date_delivery,o.id_department_master,or_s.track_number,or_s.status_label,or_s.summary_status_label,or_s.date_signed,freight,or_s.formalities_fee,or_s.weight,o_s_F.surcharge,o_s_F.back_fee,o_s_F.collection_fee,o_s_F.refund_fee,o_s_F.forward_fee,o_s_F.slotting_fee,o_s_F.operation_fee,o_s_F.other_fee,o_s_F.total_fee,or_s.date_online,os.rejected_amount') //zhujie 20171205
            //->field('o.id_increment,o.id_shipping as delivery_shipping_id,o.province,o.first_name,o.tel,o.email,o.created_at as order_created_at,o.id_department,os.*,o.date_delivery,o.id_department_master')
            ->join('__ORDER_SHIPPING__ or_s on or_s.id_order = os.id_order','LEFT')
            //加多一个链表查询 ORDER_SHIPPING_FORMALITIES ，以免在循环里查询 11/7
            ->join('__ORDER_SHIPPING_FORMALITIES__ o_s_F on o_s_F.id_order = os.id_order','LEFT')
            ->where($where)->order("o.id_order DESC")->group('os.id_order')->limit(30000)->select();

        $getField = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG');
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $objPHPExcel = new \PHPExcel();
        $setRowName = array('地区','订单号','部门','部门主管','广告员','姓名','产品名','总金额','已结金额','未结金额','退款金额','运费','总手续费','附加费','返款手续费','代收手续费','退款手续费','转寄费','上架费','作业费','其他费用','费用金额统计','重量','物流','物流状态','物流归类状态','物流上线日期','签收日期','运单号','结算状态','结算日期','下单日期','发货日期');
        foreach($setRowName as $r=>$v){
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$r].'1',$v);
        }
        $shipping = D("Common/Shipping")->field('id_shipping,title')->cache(true, 86400)->select();
        $shipTemp = $shipping ? array_column($shipping, 'title', 'id_shipping') : array();

        $ordShipping = D("Order/OrderShipping");
        $ordItem     = D("Order/OrderItem");
        $userList=$this->Users->where(array('user_status'=>1))->getField('id,user_nicename');
        if($proList){
            $num  = 2;
            $ordShipFormObj = $this->ordShipFormObj;
            //所有业务部门 11/7
            $department = M('Department')->where(array('type'=>1))->getField('id_department,title');
            foreach($proList as $order){
//                $signed_for_date = $ordShipping->where('id_order=' . $o['id_order'])->getField('date_signed');
                $notSettlement = $order['amount_total']-$order['amount_settlement'];
                switch($order['status']){
                    case 0:
                        $status = '未结算';
                        $order['amount_settlement'] = $order['amount_settlement']==0?'':$order['amount_settlement'];
                        break;
                    case 2: $status = '已结算';   break;
                    case 1: $status = '部分结款'; break;
                    default:$status = '';
                }
                /** 避免在循环里查询，$proList 查询时已经链表查询 11/7
                $shippingInfo = $ordShipping
                    ->field('id_order,track_number,status_label,summary_status_label,date_signed,freight,formalities_fee')->where('id_order='.$order['id_order'])
                    ->select();
                $trackNumber = $shippingInfo?array_column($shippingInfo,'track_number'):array();
                $track_label = $shippingInfo?array_column($shippingInfo,'status_label'):array();
                $summary_status_label = $shippingInfo?array_column($shippingInfo,'summary_status_label'):array();
                $summary_status_label = array_filter($summary_status_label);
                $track_date_signed = $shippingInfo?array_column($shippingInfo,'date_signed'):array();
                $freight = $shippingInfo?array_column($shippingInfo,'freight'):array();
                $formalities_fee = $shippingInfo?array_column($shippingInfo,'formalities_fee'):array();
                //新增关联"运单手续费表"
                $getOrdShipForm = $ordShipFormObj->where('id_order ='.$order['id_order'])->select();
                //获取对应订单的手续费表具体费用项
                $surcharge = $getOrdShipForm?array_column($getOrdShipForm,'surcharge'):array();//附加费
                $back_fee = $getOrdShipForm?array_column($getOrdShipForm,'back_fee'):array();//返款手续费
                $collection_fee = $getOrdShipForm?array_column($getOrdShipForm,'collection_fee'):array();//代收手续费
                $refund_fee = $getOrdShipForm?array_column($getOrdShipForm,'refund_fee'):array();//退款手续费
                $forward_fee = $getOrdShipForm?array_column($getOrdShipForm,'forward_fee'):array();//转寄费
                $slotting_fee = $getOrdShipForm?array_column($getOrdShipForm,'slotting_fee'):array();//上架费
                $operation_fee = $getOrdShipForm?array_column($getOrdShipForm,'operation_fee'):array();//作业费
                $other_fee = $getOrdShipForm?array_column($getOrdShipForm,'other_fee'):array();//其他费用
                $total_fee = $getOrdShipForm?array_column($getOrdShipForm,'total_fee'):array();
                $freight = $freight?implode(',',$freight):'';
                $formalities_fee =$formalities_fee?implode(',',$formalities_fee):'';
                $stat_total_fee = (float)$freight+(float)$formalities_fee;//统计费用总金额
                 *
                 */
                $stat_total_fee = ((float)$order['freight']+(float)$order['formalities_fee']);//统计费用总金额
                $getProduct = $ordItem->field('product_title')
                    ->where('id_order='.$order['id_order'])->getField('product_title',true);
                $titleString = implode(',',$getProduct);
                $tempRow = array(
                    /*$order['province'],
                    $order['id_increment'],
                    //$department,
                    $department[$order['id_department']],
                    $userList[$order['id_department_master']],
                    $order['first_name'],
                    $titleString,
                    $order['amount_total'],
                    $order['amount_settlement'],
                    $freight,
                    $formalities_fee,
                    $surcharge?implode(',',$surcharge):'',
                    $back_fee?implode(',',$back_fee):'',
                    $collection_fee?implode(',',$collection_fee):'',
                    $refund_fee?implode(',',$refund_fee):'',
                    $forward_fee?implode(',',$forward_fee):'',
                    $slotting_fee?implode(',',$slotting_fee):'',
                    $operation_fee?implode(',',$operation_fee):'',
                    $other_fee?implode(',',$other_fee):'',
                    $stat_total_fee,
                    $notSettlement,
                    $shipTemp[$order['delivery_shipping_id']],
                    $track_label?implode(',',$track_label):'',
                    $summary_status_label?implode(',',$summary_status_label):'',
                    $track_date_signed?trim(implode(',',$track_date_signed),','):'',
                    $trackNumber?' '.implode(',',$trackNumber):'',
                    $status,
                    $order['date_settlement'],
                    $order['order_created_at'],
                    $order['date_delivery'],
                     *
                     */
                    $order['province'],
                    $order['id_increment'],
                    $department[$order['id_department']],
                    $userList[$order['id_department_master']],
                    $userList[$order['sales_name']],
                    $order['first_name'],
                    $titleString,
                    $order['amount_total'],
                    $order['amount_settlement'],
                    $notSettlement,
                    $order['rejected_amount'],
                    $order['freight'],
                    $order['formalities_fee'],
                    $order['surcharge'],
                    $order['back_fee'],
                    $order['collection_fee'],
                    $order['refund_fee'],
                    $order['forward_fee'],
                    $order['slotting_fee'],
                    $order['operation_fee'],
                    $order['other_fee'],
                    $stat_total_fee,
                    $order['weight'], //重量 zx 11/27
                    $shipTemp[$order['delivery_shipping_id']],
                    $order['status_label'],
                    $order['summary_status_label'],
                    $order['date_online'],
                    $order['date_signed'],
                    $order['track_number'],
                    $status,
                    $order['date_settlement'],
                    $order['order_created_at'],
                    $order['date_delivery']
                );
                foreach ($tempRow as $row => $value) {
                    if($row != 8 && $row != 9 && $row != 10 && $row != 11 && $row != 12) {
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit($getField[$row] . $num, $value);
                    } else {
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$row] . $num, $value);
                    }
                }
                $num++;
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 7, 4, '结款导出结算列表');
        $objPHPExcel->getActiveSheet()->setTitle('order');
        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.date('Y-m-d').'导出结算列表.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    /**
     * 更新运单号日期到结算表
     */
    public function update_signed_for_date(){
        set_time_limit(0);
        $where =  "( signed_for_date is null OR signed_for_date ='0000-00-00 00:00:00' OR signed_for_date='' )";
        $listId = D('Order/OrderSettlement')->where($where)->getField('order_id',true);

        foreach($listId as $id){
            $getShipping = D('Order/OrderShipping')->where(array('id_order'=>$id))->select();
            $statusLabel =  array();
            $statusDate  =  array();
            foreach($getShipping as $ship){
                $trackNumber   = $ship['track_number'];
                $getText = $this->getTrack('http://www.t-cat.com.tw/Inquire/Trace.aspx?no='.$trackNumber,$trackNumber);
                $statusLabel[] = $getText['status'];
                $statusDate[]  = $getText['date'];
                //更新到订单运单号表，结算 因为需要根据签收时间来过滤订单
                if($getText['date']){
                    D('Order/OrderShipping')->where(array('track_number'=>$trackNumber))->save(array('date_signed'=>$getText['date']));
                }
            }
            $statusLabel = $statusLabel?implode(',',$statusLabel):'';
            $statusDate  = $statusDate?implode(',',array_filter($statusDate)):'';
            D('Order/OrderSettlement')->where(array('id_order'=>$id))->save(array('track_label'=>$statusLabel,'signed_for_date'=>$statusDate));
        }
        echo 'OK';
    }

    protected  function getTrack($url, $track_number) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect: '));    //avoid continue100
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);

        //exec
        $curlRes = curl_exec($curl);

        if (!curl_errno($curl)) {
            $curl_info = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } else {
            $curl_info = curl_error($curl);
        }
        if ($curlRes === false) {
            return '请求超时';
        }
        $matchString2 = "</a></span></td>    <td class='style1'  title=''>        <span class='r2'><strong>(.+?)</strong></span>    </td>~i";
        if (preg_match("~".$track_number."</a></span></td>(.+?)</span>    </td>~i", $curlRes, $m)) {
            $result = $m[1];
            $result = preg_replace('~<.+?>~i', '', $result);
            $result = trim($result);
        } elseif(preg_match("~".$matchString2, $curlRes, $m)) {
            $result = $m[1];
            $result = trim($result);
        }else{
            $result = '无信息';
        }
        $date = '';
        $pregString = "~</font></strong></span>    </td>    <td class='style1'>        <div align='center'>            <span class='bl12'>(.+?)</div>~i";
        if(preg_match($pregString, $curlRes, $match)){
            $date = date('Y-m-d H:i:s',strtotime(str_replace('<br>','',$match[1])));
        }else{
            $pregString = "~</font></strong></span>    </td>    <td class='style1'>        <div align='center'>            <span class='bl12'>(.+?)<br>~i";
            preg_match($pregString, $curlRes, $match);
            $getTime = strtotime($match[1]);
            if($getTime){$date = date('Y-m-d H:i:s',$getTime);}
        }
        return array('status'=>$result,'date'=>$date);
    }

    /*
     * 编辑
     * */

    public function edit() {
        if ($_POST && $_POST['id']) {
            $update = $_POST;
            if ($update['status'] == 2) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            $res = D('Order/OrderSettlement')->where(array('id_order_settlement' => $_POST['id']))->save($update);
            if(!empty($_POST['order_status'])) {
                D('Order/Order')->where(array('id_order' => $_GET['id']))->save(array('id_order_status' => $update['order_status']));
                D("Order/OrderRecord")->addHistory($_GET['id'], $_POST['order_status'], 1, '财务编辑结算更新订单状态');
            }
            if ($res == false) {
                $this->error("保存失败！", U('Index/edit', array('id' => $_GET['id'])));
            }
            $this->success("保存完成", U('Index/edit', array('id' => $_GET['id'])));
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '编辑结算列表');
        }
        $order_settle = D('Order/OrderSettlement')->where('id_order=' . $_GET['id'])->find();
        $order = M('Order')->where(array('id_order'=>$_GET['id']))->find();
        $this->assign("order", $order_settle);
        $this->assign('order_res',$order);
        $this->display();
    }

    /**
     * 导入结算
     */
    public function import() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $data = I('post.data');
            //导入记录到文件
            $setPath = './' . C("UPLOADPATH") . 'settlement' . "/";
            if (!is_dir($setPath)) {
                mkdir($setPath, 0777, TRUE);
            }
            $logTxt = $_POST['settle_date'] . PHP_EOL . $data;
            $getPathFile = $setPath . $user_id . '_' . date('Y_m_d_H_i_s') . '.txt';
            file_put_contents($getPathFile, $logTxt, FILE_APPEND);

            $data = $this->getDataRow($data);
            $count = 1;

            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                if (count($row) != 2 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }

                $track_number = trim($row[0], '\'" ');
                $price = (float) $row[1];

                //OrderShipping表里每一行代表一个运单号, 如果订单有多个运单号, 那么就是多行数据
                $shipping_info = D('Order/OrderShipping')->where(array(
                    'track_number' => $track_number
                ))->find();
                if (!$shipping_info) {
                    $infor['error'][] = sprintf('第%s行: 运单号:%s 不存在.', $count++, $track_number);
                    continue;
                }
                $settle = D("Common/OrderSettlement")
                    ->where('id_order=' . $shipping_info['id_order'])
                    ->find();
                if ($settle) {
                    if ($settle['status'] == 2) {
                        //已经结款的不能再更新
                        $infor['warning'][] = sprintf('第%s行: 运单号:%s 订单号:%s 已经结款不能再结款', $count++, $track_number, $settle['order_id']);
                        continue;
                    }
                    $amount = $settle['amount_settlement'] + $price;
                    //TODO: 结款状态: 0未结款, 1部分结款, 2已结款
                    $status = $amount == $settle['amount_total'] ? 2 : 1;
                    $data = array(
                        'user_id' => $user_id,
                        'amount_settlement' => $amount,
//                        'remark' => '',
                        'status' => $status,
                        'id_order_shipping' => $_POST['shipping_id'],
                        'date_settlement' => $_POST['settle_date'],
                        'updated_at' => date('Y-m-d H:i:s')
                    );

                    D("Order/OrderSettlement")->where('id_order_settlement=' . $settle['id_order_settlement'])->save($data);
                    $orderId = $shipping_info['id_order'];
                    $remark = '更新结算:' . $amount;
                } else {
                    $amount = $price;
                    $order = D('Order/Order')->where(array(
                        'id_order' => $shipping_info['id_order']
                    ))->find();
                    // 结款状态: 0未结款, 1部分结款, 2已结款
                    $status = $amount == $order['price_total'] ? 2 : 1;
                    $data = array(
                        'id_order' => $order['id'],
                        'id_users' => $user_id,
                        'amount_total' => $order['price_total'],
                        'amount_settlement' => $amount,
//                        'track_label' => '结算添加',
//                        'remark' => '',
                        'status' => $status,
//                        'delivery_date' => $order['delivery_date'],
                        'id_order_shipping' => $_POST['shipping_id'],
                        'date_settlement' => $_POST['settle_date'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    );

                    D("Order/OrderSettlement")->add($data);
                    $orderId = $order['id_order'];
                    $remark = '添加结算:' . $amount;
                }
                $orderObj = D("Order/Order")->find($orderId);
                if ($orderObj) {
                    D("Order/OrderRecord")->addHistory($orderId, $orderObj['id_order_status'], 1, $remark);
                }
                $success = '';
                if ($status == 2) {
                    $success = '结款完成';
                } else if ($status === 1) {
                    $success = '部分结款';
                }

                $infor['success'][] = sprintf('第%s行: 运单号:%s 金额:%s %s', $count++, $track_number, $price, $success);
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '导入结算');
        }

        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 统计某日应结和实结 订单  金额
     */
    public function date_total() {
        $M = new \Think\Model;
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        /* @var $orderItem \Common\Model\OrderItemModel */
        $ordSett = D('Order/OrderSettlement');
        $ordName = $ordModel->getTableName();
        $ordSettName = $ordSett->getTableName();
        $fieldString = 'SUBSTRING(os.date_settlement,1,10) AS set_date,SUM(os.amount_total) AS total_amount,
    (SUM(IF(os.`status`=1,os.amount_total,0))+SUM(IF(os.`status`=0,amount_total,0))) AS no_sett,
    SUM(IF(os.`status`=2,amount_total,0)) AS sett,
    (SUM(CASE os.`status` WHEN 0 THEN 1 ELSE 0 END)+
    SUM(CASE os.`status` WHEN 1 THEN 1 ELSE 0 END)) AS no_order,COUNT(os.`status`) AS all_order
    ';
        $where = array();
        if (isset($_GET['shipping_id']) && $_GET['shipping_id']) {
            $where[] = array('o.id_shipping' => $_GET['shipping_id']);
        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
            $createAtArray = array();
            if ($_GET['start_time'])
                $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $createAtArray[] = array('LT', $_GET['end_time']);
            $where[] = array('os.date_settlement' => $createAtArray);
        }
        if (count($where) == 0) {
            $where = array('os.status' => array('in', array(0, 1, 2)));
        }
        $count = $M->table($ordSettName . ' AS os LEFT JOIN ' . $ordName . ' AS o ON os.id_order=o.id_order')
            ->field($fieldString)
            ->where($where)
            ->order('set_date desc')
            ->group('set_date')->select();
        $page = $this->page(count($count), 20);

        $selectOrder = $M->table($ordSettName . ' AS os LEFT JOIN ' . $ordName . ' AS o ON os.id_order=o.id_order')
            ->field($fieldString)
            ->where($where)
            ->order('set_date desc')
            ->group('set_date')
            ->limit($page->firstRow, $page->listRows)
            ->select();


        $shipping = D("Order/Shipping")->where('status=1')->cache(true, 6000)->select();


        $this->assign("list", $selectOrder);
        $this->assign("shipping", $shipping);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /**
     * 更新拒收价格
     */
    public function update_rejected_price() {
        if (IS_POST) {
            /* @var $ordShip \Common\Model\OrderShippingModel */
            $ordObj = D("Common/Order");
            $ordShipObj = D("Common/OrderShipping");
            $ordSetObj = D("Common/OrderSettlement");
            $data = I('post.data');
            $data = $this->getDataRow($data);
            //导入记录到文件
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $setPath = './' . C("UPLOADPATH") . 'settlement' . "/" . date('Ymd') . "/";
            if (!is_dir($setPath)) {
                mkdir($setPath, 0777, TRUE);
            }
            $logTxt = $_POST['settle_date'] . PHP_EOL . $data;
            $getPathFile = $setPath . $user_id . '_' . date('H_i_s') . 'update_rejected' . '.txt';
            file_put_contents($getPathFile, $logTxt, FILE_APPEND);


            $count = 1;
            $total = 0;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $getOrderShip = $ordShipObj->where("track_number='" . $row . "'")->find();
                if ($getOrderShip['order_id']) {
                    $order_data = $ordObj->find($getOrderShip['order_id']);
                    $findSet = $ordSetObj->field('total_amount,settlement_amount,status')->where('order_id=' . $getOrderShip['order_id'])->find();
                    if ($findSet) {
                        $update_data = array(
                            'settlement_amount' => 0, 'status' => 2
                        ); //'total_amount'=>0,
                        $ordSetObj->where('order_id=' . $getOrderShip['order_id'])->save($update_data);
                        D("Common/OrderStatusHistory")->addHistory($getOrderShip['order_id'], $order_data['status_id'], '导入拒收：更新结算为0' . json_encode($findSet));
                        $infor['success'][] = sprintf('第%s行: 订单号:%s 更新成功: %s', $count, $getOrderShip['order_id'], $row);
                    } else {
                        $infor['error'][] = sprintf('第%s行:没有结款记录', $count);
                    }
                } else {
                    $infor['error'][] = sprintf('第%s行:找不到订单ID', $count);
                }
                $count++;
                //D("Common/OrderStatusHistory")->addHistory($order_id, $orderObj['status_id'], '更新物流'.$row[0]);
            }
        }
        $this->assign('infor', $infor);
        $this->display();
    }

    public function match_name() {
        $this->display();
    }

    /**
     * 导出订单信息  zhujie  重写
     */
    public function export_order_list() {
        /** @var  $orderObj  \Common\Model\OrderModel */
        set_time_limit(0);
        ini_set("memory_limit","-1");
        /** @var \Common\Model\AdvertProductModel $advert */
//        $domainList = D('Domain/Domain')->get_all_domain();
//        $domain = array_column($domainList, 'name', 'id_domain');
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $column = array(
            '地区', '部门','部门主管','物流', '域名','广告专员', '订单号', '姓名','地址',
            '产品名','SKU', '属性',
            '留言备注', '下单时间', '发货日期', '结算时间','订单状态', '运单号', '物流状态', '物流归类状态',
            '结算状态', '重量', '已结算金额','运费','手续费', '总价（NTS）', '签收日期'
        );
        //导出超过26列的问题解决
        $key = ord("A");//A--65
        $key2 = ord("@");//@--64

        foreach ($column as $col) {

            if($key>ord("Z")){
                $key2 += 1;
                $key = ord("A");
                $colum = chr($key2).chr($key);//超过26个字母时才会启用
            }else{
                if($key2>=ord("A")){
                    $colum = chr($key2).chr($key);//超过26个字母时才会启用
                }else{
                    $colum = chr($key);
                }
            }
            $excel->getActiveSheet()->setCellValue($colum . '1', $col);
            $key += 1;
            ++$j;
        }

        $j = 65;
        $idx = 2;
        $orderObj = D("Order/Order");

        $getFormWhere = $orderObj->form_where($_GET, 'o.');

        if(isset($_GET['summary_status_label']) && $_GET['summary_status_label']){
            $where['or_s.summary_status_label'] = trim($_GET['summary_status_label']);
        }
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $getFormWhere['id_zone'] = $_GET['zone_id'];
        }
//        if(isset($_GET['shipping_id']) && $_GET['shipping_id'] && $_GET['shipping_id'] != '-1'){
//            $getFormWhere['o.id_shipping'] = $_GET['shipping_id'];
//        }elseif($_GET['shipping_id'] === '0' || $_GET['shipping_id'] >= '0') {
//            $getFormWhere['o.id_shipping'] = 0;
//        }
        if(isset($_GET['shipping_id']) && $_GET['shipping_id'] && $_GET['shipping_id'] != '-1'){
            $getFormWhere['o.id_shipping'] = array('IN',$_GET['shipping_id']);
        }elseif($_GET['shipping_id'] === '0' || $_GET['shipping_id'] >= '0') {
            $getFormWhere['o.id_shipping'] = 0;
        }
        if(isset($_GET['status_id']) && $_GET['status_id']) {
            if($_GET['status_id']==16){
                $getFormWhere['o.refused_to_sign'] = 1;
            }else{
                $getFormWhere['o.id_order_status'] = $_GET['status_id'];
            }
        } else {
            $getFormWhere['o.id_order_status'] = array('NOT IN',array(1,2,3,11,12,13,14,15));//去除无效单
        }
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $where['os.status'] = $_GET['status'];
        }
        $getFormWhere['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
        $todayDate = date('Y-m-d') . ' 00:00:00';
        $todayWebWhere = array('created_at' => array('EGT', $todayDate));
        //部门权限条件
        $getFormWhere['id_department'] = array('in',$_SESSION['department_id']);
        //部门权限条件
        $order_settlement = D('Order/OrderSettlement')->getTableName();
        //$baseSql = $orderObj->alias('o')->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')->where($getFormWhere);
        //修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
        if (isset($_GET['summary_status_label']) && $_GET['summary_status_label']) {
            $getFormWhere['s.summary_status_label'] = strip_tags(trim($_GET['summary_status_label']));
            $orderList = M('order')->alias('o')->field('o.*,s.date_signed,s.freight,s.formalities_fee,os.status,os.amount_settlement,os.date_settlement')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')
                ->where($getFormWhere)->order("id_order DESC")->select();
        } elseif (isset($_GET['status']) && $_GET['status'] !== '') {

            if($_GET['status']==0){
                $getFormWhere['_string'] .= " and (os.status =0 or os.status IS NULL)";
            }else{
                $getFormWhere['os.status'] = $_GET['status'];
            }
            $orderList = M('order')->alias('o')->field('o.*,s.date_signed,s.freight,s.formalities_fee,os.status,os.amount_settlement,os.date_settlement')
                ->where($getFormWhere)
                ->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')->order("id_order DESC")
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->select();
        } else {
            $orderList = M('order')->alias('o')->field('o.*,s.date_signed,s.freight,s.formalities_fee,os.status,os.amount_settlement,os.date_settlement')
                ->join("{$order_settlement} os ON (os.id_order = o.id_order)",'left')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->where($getFormWhere)
                ->order("o.id_order DESC")->select();
        }

        $order_item = D('Order/OrderItem');

        $shipping = D("Common/Shipping")->cache(true, 3600)->getField('id_shipping,title');
        $department = M('department')->where(array('type' => 1))->where(array('id_department'=>array('in',$_SESSION['department_id'])))->order('sort asc')->cache(true, 3600)->getField('id_department,title');
        //该部门列表显示不全，已替换
        $userList=M("users")->where(array('user_status'=>1))->cache(true, 3600)->getField('id,user_nicename');
        $zone = M('Zone')->cache(true, 3600)->getField('id_zone,title');
        $OrderStatus_arr = M('OrderStatus')->cache(true, 3600)->getField('id_order_status,title');
        $domains = M('Domain')->cache(true, 3600)->where(array('type' => 1))->getField('id_domain,name');
        $setStatus = [0 => '未结款', 1 => '结款中' ,2 => '已结款'];
        foreach ($orderList as $key => $o) {
            $orderList[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $date_signed = D('Order/OrderShipping')->where('id_order=' . $o['id_order'])->field('date_signed,freight,weight,track_number,status_label,summary_status_label')->find(); //增加重量显示-weight zx 11/27
            //$orderList[$key]['return_time'] =M('OrderReturn')->where('id_order=' . $o['id_order'])->getField('date_return');
            $orderList[$key]['settlement_amount'] = !empty($selectSet['amount_settlement']) ? \Common\Lib\Currency::format($selectSet['amount_settlement'],$o['currency_code']) : '';
            $orderList[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $orderList[$key]['signed_for_date'] = $date_signed['date_signed'];
            $orderList[$key]['freight'] = $date_signed['freight'];
            $orderList[$key]['shipping_weight'] = $date_signed['weight']; //增加重量显示-weight zx 11/27
            $orderList[$key]['track_number'] = $date_signed['track_number'];
            $orderList[$key]['status_label'] = $date_signed['status_label'];
            $orderList[$key]['summary_status_label'] = $date_signed['summary_status_label'];
            /*$id_user = M('Order')->where('id_order=' . $o['id_order'])->getField('id_users');
            $orderList[$key]['name'] = !empty($id_user) ? M('Users')->where('id='.$id_user)->getField('user_nicename') : '';
            $orderList[$key]['shipping_name'] = M('Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
            $orderList[$key]['zone'] = M('Zone')->where(array('id_zone'=>$o['id_zone']))->getField('title');
            $orderList[$key]['depart'] = M('Department')->where(array('id_department'=>$o['id_department']))->getField('title');*/
        }
        foreach ($orderList as $o) {
            $inner_name = '';
            $sku_str = '';
            $attrs = '';
            foreach ($o['products'] as $p) {
                $inner_name .= $p['inner_name'] . $p['sale_title'] . " ";
                $sku_str .= $p['sku'] . " ";
                $attrs .= $p['sku_title'] . " ";
            }
            $data = array(
                $zone[$o['id_zone']], $department[$o['id_department']], $userList[$o['id_department_master']],$shipping[$o['id_shipping']], $domains[$o['id_domain']], $userList[$o['id_users']], $o['id_increment'], $o['first_name'],$o['address'],
                $inner_name,$sku_str, $attrs,
                $o['remark'], $o['created_at'],
                $o['date_delivery'], $o['date_settlement'],$OrderStatus_arr[$o['id_order_status']], $o['track_number'], $o['status_label'],$o['summary_status_label'],
                $setStatus[$o['status']], $o['shipping_weight'], $o['amount_settlement'],$o['freight'],$o['formalities_fee'],$o['price_total'], $o['date_signed']
            );
            $j = 65;
            $key1 = ord("A");//A--65
            $key2 = ord("@");//@--64
            foreach ($data as $key=>$col) {

                if($key1>ord("Z")){
                    $key2 += 1;
                    $key1 = ord("A");
                    $colum = chr($key2).chr($key1);//超过26个字母时才会启用
                }else{
                    if($key2>=ord("A")){
                        $colum = chr($key2).chr($key1);//超过26个字母时才会启用
                    }else{
                        $colum = chr($key1);
                    }
                }

                if($key != 18 && $key != 19 && $key != 20){
                    $excel->getActiveSheet()->setCellValueExplicit($colum.$idx, $col);
                } else {
                    $excel->getActiveSheet()->setCellValue($colum . $idx, $col);
                }
                ++$j;
                $key1 += 1;
            }
            ++$idx;
        }


        add_system_record($_SESSION['ADMIN_ID'], 7, 4, '结款导出订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    /**
     * 订单多个产品，分多行显示
     */
    public function export_order_product(){
        set_time_limit(0);
        ini_set("memory_limit","-1");
        /** @var  $orderObj  \Common\Model\OrderModel*/
        $orderObj = D("Order/Order");
        $M = new \Think\Model;
        $ordName = D("Order/Order")->getTableName();
        $ordSetName = D("Order/OrderSettlement")->getTableName();
        /** @var \Common\Model\AdvertProductModel $advert */
//        $advert = D('Common/AdvertProduct');
//        $advertList = $advert->getCorrespondUser();
//        $domain     = array_column($advertList,'user_nicename','name');

        $shippingArray = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();
        $shipping = array_column($shippingArray,'title','id_shipping');

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $column = array(
            '地区','部门','部门主管','物流','广告专员', '订单号', '姓名',
            '产品名和价格','SKU','属性','产品数',
            '留言备注', '下单时间','发货日期','结算时间' ,'订单状态','运单号','签收状态','物流归类状态',
            '结算状态','已结算金额', '总价（NTS）','签收日期'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        $where = $orderObj->form_where($_GET,'o.');
//        $where['id_order_status'] = array('IN',array(3,4,5,6));
       if(isset($_GET['status_id']) && $_GET['status_id']) {
            if($_GET['status_id']==16){
                $where['o.refused_to_sign'] = 1;
            }else{
                $where['o.id_order_status'] = $_GET['status_id'];
            }
        } else {
            $where['o.id_order_status'] = array('NOT IN',array(1,2,3,11,12,13,14,15));//去除无效单
        }
        $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
        if(isset($_GET['summary_status_label']) && $_GET['summary_status_label']){
            $where['s.summary_status_label'] = $_GET['summary_status_label'];
            $orders = $orderObj->alias('o')->field('o.*,s.date_signed,os.status,os.amount_settlement,os.date_settlement')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->join($ordSetName.' as os ON o.id_order = os.id_order', 'LEFT')
                ->where($where)->order("id_order DESC")->group('o.id_order')->limit(30000)->select();
        }else{
            //$orders = $orderObj->where($where)->where($where)->order("id ASC")->select();
            $orders = $M->table($ordName . ' AS o LEFT JOIN ' . $ordSetName . ' AS os ON o.id_order=os.id_order')
                ->field('o.*,os.status,os.amount_settlement,os.date_settlement')->where($where)
                ->group('o.id_order')->limit(30000)->select();
        }
//        dump($orders);die;

        $result = D('Order/OrderStatus')->cache(true,86400)->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int)$statu['id_order_status']] = $statu;
        }
        /** @var \Common\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        $userList=M("users")->where(array('user_status'=>1))->getField('id,user_nicename');
        foreach ($orders as $o) {
            $product_name = '';
            $attrs = '';
            $name = !empty($o['id_users']) ? M('Users')->where('id='.$o['id_users'])->getField('user_nicename') : '';
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $getShipObj = D("Order/OrderShipping")->field('track_number,status_label,summary_status_label,date_signed')
                ->where('id_order='.$o['id_order'])->select();
            $trackNumber = $getShipObj?implode(',',array_column($getShipObj,'track_number')):'';
            $trackStatusLabel = $getShipObj?implode(',',array_column($getShipObj,'status_label')):'';
            $summary_status_label = $getShipObj?implode(',',array_column($getShipObj,'summary_status_label')):'';
//            $summary_status_label = array_filter($summary_status_label);
            $signedForDate = $getShipObj?implode(',',array_column($getShipObj,'date_signed')):'';
            $department = M('Department')->where(array('id_department'=>$o['id_department']))->getField('title');
            switch($o['status']){
                case 0:
                    $setStatus = '未结款';
                    $o['amount_settlement'] = $o['amount_settlement']==0?'':$o['amount_settlement'];
                    break;
                case 1: $setStatus = '结款中';break;
                case 2: $setStatus = '已结款';break;
                default:$setStatus = '';
            }
            $products = $order_item->get_item_list($o['id_order']);
            $countProduct = 0 ;
            foreach ($products as $p) {
                $inner_name = M('Product')->where(array('id_product'=>$p['id_product']))->getField('inner_name');
                $product_name .= $inner_name;
                if(!empty($p['sku_title'])) {
                    $attrs .= ',' . $p['sku_title'] . ' x ' . $p['quantity'] . "  ";
                    $attrs_name = $p['sku_title'] . ' x ' . $p['quantity'];
                } else {
                    $attrs .= ',' . $p['product_title'] . ' x ' . $p['quantity'] . "  ";
                    $attrs_name = $p['product_title'] . ' x ' . $p['quantity'];
                }
                $attrs = trim($attrs, ',');
                if($countProduct==0){
                    $data = array(
                        $o['province'],$department,$userList[$o['id_department_master']],$shipping[$o['id_shipping']],$name, $o['id_increment'], $o['first_name'],
                        $product_name,$p['sku'],$attrs,$p['quantity'],
                        $o['remark'], $o['created_at'],
                        $o['date_delivery'], $o['date_settlement'],$status_name,$trackNumber,$trackStatusLabel,$summary_status_label,
                        $setStatus,$o['amount_settlement'], $o['price_total'],$signedForDate
                    );
                }else{
                    $data = array(
                        '','','','', $o['id_increment'],'',
                        $inner_name,$p['sku'] ,$attrs_name,$p['quantity'],
                        '', '', '',
                        '', '',' ','','',
                        '','','',''
                    );
                }
                $j = 65;
                foreach ($data as $key=>$col) {
                    if($key != 9 && $key != 17 && $key != 18){
                        $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                    } else {
                        $excel->getActiveSheet()->setCellValue(chr($j).$idx, $col);
                    }
                    ++$j;
                }
                ++$idx;$countProduct++;
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 7, 4, '结款导出拆分产品列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d').'拆分产品订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '拆分产品订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    /**
     * 输入结算金额 ，ajax post
     */
    public function entry()
    {
        try {
            $getId = (int)$_POST['id'];
            $settle = D("Order/OrderSettlement")->find($getId);
            $postAmount = is_numeric($_POST['amount']) ? $_POST['amount'] : 0;
            $amount = $settle['amount_settlement'] + $postAmount;
            $notSettle = $settle['amount_total'] - $amount;
            if ($settle['amount_total'] >= $amount) {
                $userId = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
                $status = $amount < $settle['amount_total'] ? 1 : 2;
                $data = array('id_users' => $userId, 'amount_settlement' => $amount,
                    'status' => $status, 'updated_at' => date('Y-m-d H:i:s'));
                D("Order/OrderSettlement")->where('id=' . $getId)->save($data);
                $message = '';
                $status = 1;
            } else {
                $status = 0;
                $message = '输入金额加待结算金额大于总金额。';
            }

        } catch (\Exception $e) {
            $status = 0;
            $message = $e->getMessage();
        }
        echo json_encode(array('status' => $status, 'message' => $message, 'notsettle' => $notSettle, 'settlement' => $amount));
        exit();
    }

    public function export_statistics()
    {
        $ord_model = D("Order/Order");
        $M = new \Think\Model;
        $ordName = D("Order/Order")->getTableName();
        $ordSetName = D("Order/OrderSettlement")->getTableName();
        /** @var \Common\Model\AdvertProductModel $advert */
//        $advert = D('Common/AdvertProduct');
//        $advertList = $advert->getCorrespondUser();
//        $domain     = array_column($advertList,'user_nicename','name');


        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $excel->setActiveSheetIndex(0)
            ->setCellValue('A1', '部门')
            ->setCellValue('B1', '币种')
            ->setCellValue('C1', '有效单')
            ->setCellValue('D1', '有效单')
            ->setCellValue('E1', '营业额')
            ->setCellValue('F1', '已结款')
            ->setCellValue('G1', '已结款')
            ->setCellValue('H1', '未结款')
            ->setCellValue('I1', '未结款')
            ->setCellValue('J1', '营业成本')
            ->setCellValue('K1', '运费')
            ->setCellValue('L1', '广告费')
            ->setCellValue('M1', '预估利润')
            ->setCellValue('C2', '单数')
            ->setCellValue('D2', '总金额')
            ->setCellValue('F2', '单数')
            ->setCellValue('G2', '总金额')
            ->setCellValue('H2', '单数')
            ->setCellValue('I2', '总金额');

        $excel->getActiveSheet(0)->mergeCells('A1:A2');
        $excel->getActiveSheet(0)->mergeCells('B1:B2');
        $excel->getActiveSheet(0)->mergeCells('C1:D1');
        $excel->getActiveSheet(0)->mergeCells('E1:E2');
        $excel->getActiveSheet(0)->mergeCells('F1:G1');
        $excel->getActiveSheet(0)->mergeCells('H1:I1');
        $excel->getActiveSheet(0)->mergeCells('J1:J2');
        $excel->getActiveSheet(0)->mergeCells('K1:K2');
        $excel->getActiveSheet(0)->mergeCells('L1:L2');
        $excel->getActiveSheet(0)->mergeCells('M1:M2');
        // $data = array('','');



        $orderSettlement = D("Order/OrderSettlement");
        $department = D("Department/Department");

        $departments  = $department->where('type=1')->cache(true,3600)->select();
        /*****查询所有的货币符号********/
        $currency_symbols = $ord_model->field('distinct currency_code as currency_code')->cache(true,3600)->select();
        $currency_symbols= array_column($currency_symbols,'currency_code');
        $data = array();
        $idx =3;
        $c = 3;
        foreach ($departments as  $item) {
            if(isset($_GET['department_id'])&&$_GET['department_id']){
                if($_GET['department_id']==$item['id_department']){
                    foreach ($currency_symbols as $currency_symbol) {
                        if(isset($_GET['year'])&&$_GET['year']&&isset($_GET['month'])&&$_GET['month']){
                            $y_month = $_GET['year'].'-'.$_GET['month'];
                            $effect = $ord_model->alias('o')->field('currency_code,d.title,d.id_department,SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as effective,sum(price_total) as price_total')
                                ->join('__DEPARTMENT__ as d on d.id_department = o.id_department')
                                ->where(array('currency_code'=>$currency_symbol,'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'o.id_department'=>$_GET['department_id'],'SUBSTRING(o.created_at,1,7)'=>$y_month))
                                ->find();
                            $finish = $ord_model->alias('o')->field('SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as finish,sum(amount_total) as price_total')
                                ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order')
                                ->where(array('currency_code'=>$currency_symbol,'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'o.id_department'=>$_GET['department_id'],'status'=>'2','SUBSTRING(o.created_at,1,7)'=>$y_month))
                                ->find();
                            $unfinish = $ord_model->alias('o')->field('SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as unfinish,sum(price_total) as price_total')
                                ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order')
                                ->where(array('currency_code'=>$currency_symbol,'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'o.id_department'=>$_GET['department_id'],'status'=>'0','SUBSTRING(o.created_at,1,7)'=>$y_month))
                                ->find();
                            $data= array(
                                $item['title'],$currency_symbol,$effect['effective'],\Common\Lib\Currency::format($effect['price_total'],$currency_symbol),'',$finish['finish'],\Common\Lib\Currency::format($finish['price_total'],$currency_symbol),$unfinish['unfinish'],\Common\Lib\Currency::format($unfinish['price_total'],$currency_symbol),'','','',''
                            );
                            $j = 65;
                            foreach ($data as $col) {
                                $excel->getActiveSheet(0)->setCellValue(chr($j).$idx, $col);
                                ++$j;

                            }
                            $idx++;
                        }
                        elseif(isset($_GET['year'])&&$_GET['year'])
                        {
                            $effect = $ord_model->alias('o')->field('currency_code,d.title,d.id_department,SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as effective,sum(price_total) as price_total')
                                ->join('__DEPARTMENT__ as d on d.id_department = o.id_department')
                                ->where(array('currency_code'=>$currency_symbol,'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'o.id_department'=>$_GET['department_id'],'SUBSTRING(o.created_at,1,4)'=>$_GET['year']))
                                ->find();
                            $finish = $ord_model->alias('o')->field('SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as finish,sum(amount_total) as price_total')
                                ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order')
                                ->where(array('currency_code'=>$currency_symbol,'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'o.id_department'=>$_GET['department_id'],'status'=>'2','SUBSTRING(o.created_at,1,4)'=>$_GET['year']))
                                ->find();
                            $unfinish = $ord_model->alias('o')->field('SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as unfinish,sum(price_total) as price_total')
                                ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order')
                                ->where(array('currency_code'=>$currency_symbol,'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'o.id_department'=>$_GET['department_id'],'status'=>'0','SUBSTRING(o.created_at,1,4)'=>$_GET['year']))
                                ->find();
                            $data= array(
                                $item['title'],$currency_symbol,$effect['effective'],\Common\Lib\Currency::format($effect['price_total'],$currency_symbol),'',$finish['finish'],\Common\Lib\Currency::format($finish['price_total'],$currency_symbol),$unfinish['unfinish'],\Common\Lib\Currency::format($unfinish['price_total'],$currency_symbol),'','','',''
                            );
                            $j = 65;
                            foreach ($data as $col) {
                                $excel->getActiveSheet(0)->setCellValue(chr($j).$idx, $col);
                                ++$j;

                            }
                            $idx++;
                        }
                        else
                        {
                            $effect = $ord_model->alias('o')->field('currency_code,d.title,d.id_department,SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as effective,sum(price_total) as price_total')
                                ->join('__DEPARTMENT__ as d on d.id_department = o.id_department')
                                ->where(array('currency_code'=>$currency_symbol,'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'o.id_department'=>$_GET['department_id']))
                                ->find();
                            $finish = $ord_model->alias('o')->field('SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as finish,sum(amount_total) as price_total')
                                ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order')
                                ->where(array('currency_code'=>$currency_symbol,'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'o.id_department'=>$_GET['department_id'],'status'=>'2'))
                                ->find();
                            $unfinish = $ord_model->alias('o')->field('SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as unfinish,sum(price_total) as price_total')
                                ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order')
                                ->where(array('currency_code'=>$currency_symbol,'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'o.id_department'=>$_GET['department_id'],'status'=>'0'))
                                ->find();
                            $data= array(
                                $item['title'],$currency_symbol,$effect['effective'],\Common\Lib\Currency::format($effect['price_total'],$currency_symbol),'',$finish['finish'],\Common\Lib\Currency::format($finish['price_total'],$currency_symbol),$unfinish['unfinish'],\Common\Lib\Currency::format($unfinish['price_total'],$currency_symbol),'','','',''
                            );
                            $j = 65;
                            foreach ($data as $col) {
                                $excel->getActiveSheet(0)->setCellValue(chr($j).$idx, $col);
                                ++$j;

                            }
                            $idx++;
                        }
                    }
                }
            }
            else{
                foreach ($currency_symbols as $currency_symbol) {
                    $effect = $ord_model->alias('o')->field('currency_code,d.title,d.id_department,SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as effective,sum(price_total) as price_total')
                        ->join('__DEPARTMENT__ as d on d.id_department = o.id_department')
                        ->where(array('currency_code'=>$currency_symbol,'o.id_department'=>$item['id_department'],'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)')))
                        ->find();
                    $finish = $ord_model->alias('o')->field('SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as finish,sum(amount_total) as price_total')
                        ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order')
                        ->where(array('currency_code'=>$currency_symbol,'o.id_department'=>$item['id_department'],'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'status'=>'2'))
                        ->find();
                    $unfinish = $ord_model->alias('o')->field('SUM(IF(`id_order_status` IN(1,2,3,4,5,6,7,8,9),1,0)) as unfinish,sum(price_total) as price_total')
                        ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order')
                        ->where(array('currency_code'=>$currency_symbol,'o.id_department'=>$item['id_department'],'id_order_status'=>array('in','(1,2,3,4,5,6,7,8,9)'),'status'=>'0'))
                        ->find();
                    $data= array(
                        $item['title'],$currency_symbol,$effect['effective'],\Common\Lib\Currency::format($effect['price_total'],$currency_symbol),'',$finish['finish'],\Common\Lib\Currency::format($finish['price_total'],$currency_symbol),$unfinish['unfinish'],\Common\Lib\Currency::format($unfinish['price_total'],$currency_symbol),'','','',''
                    );
                    // var_dump($data);
                    $j = 65;
                    foreach ($data as $col) {
                        $excel->getActiveSheet(0)->setCellValue(chr($j).$idx, $col);
                        ++$j;
                    }
                    $idx++;

                }
            }
        }


        $excel->getActiveSheet()->setTitle(date('Y-m-d').'订单统计信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单统计信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    /*
     * 采购单待付款列表
     */
    public function waiting_pay(){
        if($_GET['isajax']==1){//ajax结算统计信息
            $ajaxdata=[];
            if($_GET['data']){
                foreach ($_GET['data'] as $dataval){
                    $ajaxdata[$dataval['name']]=$dataval['value'];
                }
                $_GET=  array_merge($_GET,$ajaxdata);
            }
        }
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $supplier = M('Supplier')->getField('id_supplier,title');
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where['pe.id_department'] = array('IN', $_GET['department_id']);
        }
        // dump($where);die;
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $purchase_no=  trim($_GET['purchase_no']);
            $where['pe.purchase_no'] = array('like', "%{$purchase_no}%");
        }
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['pe.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if (isset($_GET['inner_name']) && $_GET['inner_name']) {
            $where_pro['p.inner_name'] = $_GET['inner_name'];
        }
        if ($_GET['alibaba_no']) {
            $alibaba_no=  trim($_GET['alibaba_no']);
            $where['pe.alibaba_no'] = array('like', "%{$alibaba_no}%");
        }

        //增加采购员名字筛选
        if (isset($_GET['shop_id']) && $_GET['shop_id']) {
            //$id_users = M('Users')->where(array('user_nicename' => $_GET['shop_id']))->getField('id');
            $where['pe.id_users'] = array('EQ', $_GET['shop_id']);
        }
        /* 此功能已被 采购员名字筛选 替换
          if (isset($_GET['id_users']) && $_GET['id_users']) {

            $id_users = M('Users')->where(array('user_nicename' => $_GET['id_users']))->getField('id');
            $where['pe.id_users'] = $id_users;
        }*/

        if (isset($_GET['sku']) && $_GET['sku']) {
            $id_purchase = M('PurchaseProduct')->alias('pp')
                ->field('id_purchase')
                ->join('__PRODUCT_SKU__ ps on ps.id_product_sku = pp.id_product_sku')
                ->where(array('sku' => $_GET['sku']))
                ->getField('id_purchase', true);
            $new = '';
            if($id_purchase){
                foreach ($id_purchase as $k => $v) {
                    $new .= 'pe.id_purchase = ' . $v . ' OR ';
                }
                $where[] = substr($new, 0, -3);
            }else{
                //为空就找不到
                $where['pe.id_purchase'] =-1;
            }

        }

        $createAtArray = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray[] = array('EGT',$_GET['start_time']);
        }
        if($_GET['end_time']) {
            $createAtArray[] = array('LT',$_GET['end_time']);

        }
        if(!empty($createAtArray))
            $where['pe.created_at'] =$createAtArray;

        $purchaseTimeArray = array();
        if (isset($_GET['start_purchase_time']) && $_GET['start_purchase_time']) {
            $purchaseTimeArray[] = array('EGT',$_GET['start_purchase_time']);
        }
        if(isset($_GET['end_purchase_time']) && $_GET['end_purchase_time']) {
            $purchaseTimeArray[] = array('LT',$_GET['end_purchase_time']);

        }
        if(!empty($purchaseTimeArray)){
            $where['pe.inner_purchase_time'] =$purchaseTimeArray;
        }


        if (!empty($_GET['warehouse_id']) || !empty($_GET['warehouse_id'])) {
            $where['pe.id_warehouse'] = $_GET['warehouse_id'];
        }

        if(isset($_GET['status_id'])&&$_GET['status_id']==0){
            $where['pe.status'] = array('IN',[PurchaseStatus::FINISHCHECK,PurchaseStatus::PAYMENT,PurchaseStatus::REJECTPAYMENT]);
        }elseif(isset($_GET['status_id'])&&$_GET['status_id']==PurchaseStatus::PAYMENT){
            $where['pe.status'] = PurchaseStatus::PAYMENT;
        }elseif(isset($_GET['status_id'])&&$_GET['status_id']==PurchaseStatus::REJECTPAYMENT){
            $where['pe.status'] = PurchaseStatus::REJECTPAYMENT;
        }else{
            $where['pe.status'] = PurchaseStatus::FINISHCHECK;
            $_GET['status_id']= PurchaseStatus::FINISHCHECK;
        }
        $model = new \Think\Model();
        //统计信息
        if($_GET['isajax']==1){//ajax结算统计信息
            $ppTable = D("PurchaseProduct")->getTableName();
            $statisticsInfo=$model->table($this->Purchase->getTableName() . ' pe')
                    ->join("$ppTable as pp on pp.id_purchase=pe.id_purchase",'left')
                    ->field('count(DISTINCT(pe.id_purchase)) as totalcnt,sum(pp.quantity) as totalpp,truncate(sum(pp.quantity*pp.price)+pe.price_shipping,4) as totalprice')
                    ->group('pe.id_purchase')
                    ->where($where)->select();
            $statisticsInfo2['totalcnt']=0;
            $statisticsInfo2['totalpp']=0;
            $statisticsInfo2['totalprice']=0;
            foreach($statisticsInfo as $v){
                $statisticsInfo2['totalcnt']= $statisticsInfo2['totalcnt']+$v['totalcnt'];
                $statisticsInfo2['totalpp']= $statisticsInfo2['totalpp']+$v['totalpp'];
//                $statisticsInfo2['totalprice']= $statisticsInfo2['totalprice']+$v['totalprice'];
            }
            $statisticsInfo2['totalprice']=$model->table($this->Purchase->getTableName() . ' pe')->where($where)->getField('sum(pe.price) as totalprice');
            echo json_encode($statisticsInfo2);
            exit();
        }
        $count =  $this->Purchase->alias('pe')->where($where)->count();
        $page = $this->page($count, 20);
        $lists = $this->Purchase->alias('pe')->where($where)->order('created_at DESC') ->limit($page->firstRow, $page->listRows)->select();
        // dump($this->Purchase->getLastSql());die;
        //echo '<pre>';print_r($where);exit;
        foreach ($lists as $key => $list) {
            $purchase_channel = '';
            switch ($list['purchase_channel']) {
                case 1: $purchase_channel = '阿里巴巴 ';
                    break;
                case 2: $purchase_channel = '淘宝 ';
                    break;
                case 3: $purchase_channel = '线下 ';
                    break;
                default:$purchase_channel = '空 ';
            }
            $lists[$key]['purchase_channel'] = $purchase_channel;
            $where_pro['pp.id_purchase'] = $list['id_purchase'];
            $lists[$key]['purchase_product'] = $this->PurchaseProduct->alias('pp')
                ->field('pp.*,ps.*,p.thumbs,p.inner_name,p.id_users,pp.quantity*pp.price as totalitem')
                ->join('__PRODUCT_SKU__ as ps on ps.id_product_sku = pp.id_product_sku', 'LEFT')
                ->join('__PRODUCT__ as p on p.id_product = pp.id_product', 'LEFT')
                ->where($where_pro)->select();

                    //查询广告员名字
            foreach ($lists[$key]['purchase_product'] as $kk=>$vv){
                $sales_name = M('Users')->field('user_nicename')->where(array('id' => $vv['id_users']))->find();
                // echo M('Users')->getLastSql();
            }
            $lists[$key]['sales_name'] = $sales_name['user_nicename'];
            //查询广告员名字
            $lists[$key]['num_amo']=0;
            foreach($lists[$key]['purchase_product'] as $k=>$v){
                $lists[$key]['num_amo'] += $v['quantity'];
            }
            $lists[$key]['totalprice']=  array_sum(array_column($lists[$key]['purchase_product'],'totalitem'))+$list['price_shipping'];
            //$lists[$key]['user_nicename'] = M('Users')->where(array('id' => $list['id_users']))->getField('user_nicename');
        }
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看采购单待付款列表');
        $pur_status=[PurchaseStatus::FINISHCHECK =>'已审核',PurchaseStatus::PAYMENT=>'已付款',PurchaseStatus::REJECTPAYMENT=>'拒绝付款'];

        //查询所有采购部人员
        $shop_users = M()->query("SELECT a.id,a.user_nicename,b.* FROM erp_users AS a LEFT JOIN erp_department_users AS b ON a.id=b.id_users WHERE b.id_department=19");

        $this->assign('pur_status', $pur_status);

        $where2['type'] = 1;
        //部门筛选过滤,如不需过滤，直接删掉
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        if (I('get.id_department')){
            $where2['id_department'] = I('get.id_department');
        }else{
            $where2['id_department'] = array('IN',$department_id);
        }
        //筛选有效的用户
        $users = M('Users')->field('id,user_nicename')->where(array('id_user' => $user_id,'user_status'=>1))->select();
        $users = array_column($users, 'user_nicename', 'id');
        //部门筛选
        //dump($users);exit;
        $depart = M('Department')->where($where2)->cache(true, 3600)->order('sort ASC')->select();
        $this->assign('depart', $depart);
        $this->assign('warehouse', $warehouse);
        $this->assign('supplier', $supplier);
        //$this->assign('statisticsInfo', $statisticsInfo);
        $this->assign('lists', $lists);
        $this->assign('users', $users);
        $this->assign("Page", $page->show('Admin'));
        $this->assign('shop_users', $shop_users); //所有采购部人员
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }

    /*
     * 批量审核采购单
     */
    public function check_purchase() {
        $purchase_no = $_REQUEST['purchase_no'];
        $check = $_REQUEST['check'];
        if ($check == 'pass') {
            if (file_exists(CACHE_PATH.$this->lock_write_data)) {
                echo json_encode(array('flag' => '1', 'msg' => '请等待上一次审核完成再操作！'));
                exit;
            }
            try {
                $data['status'] = PurchaseStatus::PAYMENT;
                $data['updated_at'] = date('Y-m-d H:i:s');
                $purchase_no = explode(',',$purchase_no);
                foreach($purchase_no as $v){
                    $id_purchase = M('Purchase')->where(array('purchase_no'=>$v))->getField('id_purchase');
                    if($id_purchase){
                        M()->startTrans();
                        $purchase = $this->insert_purchase_in($id_purchase);
                        $add_road = $this->add_road($id_purchase);
                        $result2 = $this->Purchase->where(array('id_purchase' =>  $id_purchase))->save($data);
                        $res4 = D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::PAYMENT, '已付款');
                        if($purchase === false || $add_road === false || $result2 === false ||$res4 === false){
                            M()->rollback();
                        }else{
                            M()->commit();
                        }
                    }
                }
                $result = $this->Purchase->where(array('purchase_no' => array('IN', $purchase_no)))->save($data);
            }catch (Exception $e) {
                echo $e->getMessage();
            }
            finally {
                if (file_exists(CACHE_PATH.$this->lock_write_data)) {
                    unlink(CACHE_PATH.$this->lock_write_data);
                }
            }
        } elseif ($check == 'refuse') {
            $data['status'] = PurchaseStatus::REJECTPAYMENT;
            $data['remark'] = $_REQUEST['reason'];
            $data['updated_at'] = date('Y-m-d H:i:s');
            $purchase_no = explode(',',$purchase_no);
            foreach($purchase_no as $v){
                $id_purchase = M('Purchase')->where(array('purchase_no'=>$v))->getField('id_purchase');
                if($id_purchase)
                    $res = D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::REJECTPAYMENT, '拒绝付款'.',原因是'.$data['remark']);
            }
            $result = $this->Purchase->where(array('purchase_no' => array('IN', $purchase_no)))->save($data);
            echo json_encode(array('flag' => 1, 'msg' => '已经拒绝'));
            exit;
        }
        if (false!==$result) {
            $flag = 0;
            $msg = '审核完成';
        } else {
            $flag = 1;
            $msg = '审核失败';
        }
        echo json_encode(array('flag' => $flag, 'msg' => $msg));
        exit;
    }

    /*
     * 采购付完款，生成采购入库单
     */
    public function insert_purchase_in($id_purchase){
        //采购单信息
        $add_sttlement = array();
        $info = $this->Purchase->where(array('id_purchase'=>$id_purchase))->find();
        $add_sttlement['id_users'] = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
        $add_sttlement['id_erp_purchase'] = $info['id_purchase'];
        $add_sttlement['id_department'] = $info['id_department'];
        $add_sttlement['id_supplier'] = $info['id_supplier'];
        M()->startTrans();
        $this->PurchaseIn->where(array('id_erp_purchase'=>$info['id_purchase'],'status'=>1))->delete();
        $id_purchasein = $this->PurchaseIn->where(array('id_erp_purchase'=>$info['id_purchase'],'status'=>1))->getField('id_purchasein');
        $this->PurchaseInitem->where(array('id_purchasein'=>$id_purchasein))->delete();
        $purchase_in = $this->PurchaseIn->add(array_merge($info,array('billdate'=>date('Y-m-d H:i:s'),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),'billtype'=>1,'status'=>1,'total_received'=>$info['total'],'id_erp_purchase'=>$info['id_purchase'])));
        $purchse_product = $this->PurchaseProduct->where(array('id_purchase'=>$id_purchase))->select();
        foreach($purchse_product as $k=>$v){
            $this->PurchaseInitem->add(array_merge($v,array('id_purchasein'=>$purchase_in,'received'=>$v['quantity'])));
            $add_sttlement['id_product'] = $v['id_product'];
            $add_sttlement['id_product_sku'] = $v['id_product_sku'];
            $add_sttlement['qty'] = $v['quantity'];
            $add_sttlement['qtyin'] = 0;
            $add_sttlement['amount_total'] = $info['price'];
            $add_sttlement['amount_settlement'] = $v['quantity']*$v['price'];
            $add_sttlement['date_settlement'] = date('Y-m-d H:i:s');
            $add_sttlement['created_at'] = date('Y-m-d H:i:s');
            $add_sttlement['remark'] = $info['remark'];
            $dataList[] = $add_sttlement;
        }
        try{
            $res = M('PurchaseSettlement')->addAll($dataList,$options=array(),$replace=true);
            if($purchase_in === false || $res=== false){
                M()->rollback();
                return false;
            }else{
                return ture;
                M()->commit();
            }
        }catch (Exception $e) {
            return false;
        }
    }
    /*
     * 付完款后增加库存的在途
     */
    public function add_road($id_purchase){
        $warehouse_id = M('Purchase')->where(array('id_purchase'=>$id_purchase))->getField('id_warehouse');
        $product = M('PurchaseProduct')->where(array('id_purchase'=>$id_purchase))->select();

        if($product){
            foreach($product as $key=>$val){
                $warehouse_product = M('WarehouseProduct')->field('id_product,road_num,id_product_sku,id_warehouse,quantity')
                    ->where(array('id_warehouse' => $warehouse_id))
                    ->where(array('id_product_sku' => $val['id_product_sku']))
                    ->find();
                $datas = array(
                    'id_warehouse' => $warehouse_id,
                    'id_product' => $val['id_product'],
                    'id_product_sku' => $val['id_product_sku'],
                    'quantity' => !empty($warehouse_product)?$warehouse_product['quantity']:0,
                    'road_num' => $val['quantity']
                );

                if (!empty($warehouse_product)) {
                    $datas['road_num'] = $warehouse_product['road_num'] + $val['quantity'];
                    D("Common/WarehouseProduct")->where(array('id_product_sku' => $val['id_product_sku']))
                        ->where(array('id_warehouse' => $warehouse_id))
                        ->save($datas);
                } else {
                    D("Common/WarehouseProduct")->data($datas)->add();
                }
            }
        }
    }


    /*
 *临时提那家
 */
    public function temp() {
        $purchase_no = M('Purchase')->where(array('status'=>5))->getField('purchase_no',true);
//        dump($purchase_no);die;
        $newpurchase_no = $purchase_no;
//        M('Purchase')->where(array('status'=>5))->save(array('status'=>3));
        foreach($newpurchase_no as $v1){
            $_GET['purchase_no'] = $v1;
            $check = 'pass';
            if ($check == 'pass') {
                $data['status'] = PurchaseStatus::PAYMENT;
                $data['updated_at'] = date('Y-m-d H:i:s');
                $purchase_no = $v1;
                $id_purchase = M('Purchase')->where(array('purchase_no'=>$v1))->getField('id_purchase');
                $purchase = $this->insert_purchase_in($id_purchase);
                $add_road = $this->add_road($id_purchase);
                if($id_purchase)
                    $res = D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::PAYMENT, '已付款');
            }

        }
        echo 'ok ';
    }

    /*
     * 撤销付款     liuruibin   20171030
     * */
    public function revoke_payment(){
        $purchase_no = $_REQUEST['purchase_no'];
        $data['status'] = PurchaseStatus::UNSUBMIT;//撤销付款之后，待提交
        $data['updated_at'] = date('Y-m-d H:i:s');
        try{
            $id_purchase = M('Purchase')->where('purchase_no ='.$purchase_no)->getField('id_purchase');
            if($id_purchase){
                $purchase_del = $this->delete_purchase_in($id_purchase);//删除采购入库和明细
                if($purchase_del==false){
                    echo json_encode(array('flag' => '1', 'msg' => '没有未入库的单无法撤回付款'));
                    exit;
                }
                $reduce_road = $this->reduce_road($id_purchase);//减少在途量
                $res4  = D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::UNSUBMIT, '撤回付款：待提交');
            }
            $result = $this->Purchase->where('purchase_no='.$purchase_no)->save($data);
        }catch(Exception $e){
            echo $e->getMessage();
        }
        finally{
            if (file_exists(CACHE_PATH.$this->lock_write_data)) {
                unlink(CACHE_PATH.$this->lock_write_data);
            }
        }
        if(false !== $result) {
            $flag = 0;
            $msg = '撤销成功';
        }else{
            $flag = 1;
            $msg = '撤销失败';
        }
        echo json_encode(array('flag' => $flag,'msg' => $msg));
        exit;
    }

    /*
     * 撤销付款之后，减少库存的在途     liuruibin   20171030
     * */
    public function reduce_road($id_purchase){
        $warehouse_id = M('Purchase')->where(array('id_purchase'=>$id_purchase))->getField('id_warehouse');
        $product = M('PurchaseProduct')->where(array('id_purchase'=>$id_purchase))->select();

        if($product){
            foreach($product as $key => $val){
                $warehouse_product = M('WarehouseProduct')->field('id_product,road_num,id_product_sku,id_warehouse,quantity')
                    ->where(array('id_warehouse' => $warehouse_id))
                    ->where(array('id_product_sku' => $val['id_product_sku']))
                    ->find();
                if (!empty($warehouse_product)) {
                    $datas['road_num'] = $warehouse_product['road_num'] - $val['quantity'];//在途减去相应的数量
                    D("Common/WarehouseProduct")->where(array('id_product_sku' => $val['id_product_sku']))
                        ->where(array('id_warehouse' => $warehouse_id))
                        ->save($datas);
                }
            }
        }
    }

    /*
     * 删除采购入库单列表和入库明细     liuruibin   20171030
     * */
    public function delete_purchase_in($id_purchase){
        //采购入库单和明细的处理,存入临时表，只有未入库的才能被撤回
        $info = $this->Purchase->where(array('id_purchase'=>$id_purchase))->find();
        $purchaseIn_Info = $this->PurchaseIn->where(array('id_erp_purchase'=>$info['id_purchase'],'status'=>1))->find();
        $purchaseInItem_Info = $this->PurchaseInitem->where(array('id_purchasein'=>$purchaseIn_Info['id_purchasein'],'status'=>1))->find();
        if(!$purchaseIn_Info || !$purchaseInItem_Info){
            return false;
        }
        $add_interim = array('erp_purchase_in_DATA' => $purchaseIn_Info,'erp_purchase_initem_DATA' => $purchaseInItem_Info);
        $add_interim_json = json_encode($add_interim);
        $add_interim_info['data_json'] = $add_interim_json;
        $add_interim_info['remark'] = '临时存放[采购入库表][采购单明细表]';
        $add_interim_info['date_operation'] = date('Y-m-d H:i:s');
        $interimObj = M('PurchaseInterim');
        $interimObj->add($add_interim_info);
        //采购信息-删除
        $this->PurchaseIn->where(array('id_erp_purchase'=>$info['id_purchase'],'status'=>1))->delete();
        $this->PurchaseInitem->where(array('id_purchasein'=>$purchaseIn_Info['id_purchasein'],'status'=>1))->delete();
        return true;
    }

    //zhujie 20171116 导入采购渠道号 #148
    public function import_alibaba_no ()
    {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $data = I('post.data');
            //导入记录到文件
            $setPath = './' . C("UPLOADPATH") . 'settlement' . "/";
            if (!is_dir($setPath)) {
                mkdir($setPath, 0777, TRUE);
            }
            $logTxt = $_POST['settle_date'] . PHP_EOL . $data;
            $getPathFile = $setPath . $user_id . '_' . date('Y_m_d_H_i_s') . '.txt';
            file_put_contents($getPathFile, $logTxt, FILE_APPEND);

            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;

                $row = explode("\t", trim($row), 3);
                if (count($row) != 3 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
                try {
                    $data['status'] = PurchaseStatus::PAYMENT;
                    $data['updated_at'] = date('Y-m-d H:i:s');
                    $purchase_list = M('Purchase')->field("id_purchase,status,price,id_department")->where(array('alibaba_no'=>$row[0]))->find();
                    $department = M("Department")->field("title")->where("id_department=".$purchase_list['id_department']." AND type=1")->find();
                    if ($purchase_list['price'] != $row[1]) {

                        $infor['warning'][] = sprintf('第%s行: 渠道订单号:%s 金额与采购金额不符', $count++, $row[0]);
                        continue;
                    }
                    if($department['title'] != $row[2]){
                        $infor['warning'][] = sprintf('第%s行: 渠道订单号:%s 部门信息不符', $count++, $row[0]);
                        continue;
                    }
                    if ($purchase_list['status'] == PurchaseStatus::PAYMENT) {
                        $infor['warning'][] = sprintf('第%s行: 渠道订单号:%s 已经结款不能再结款', $count++, $row[0]);
                        continue;
                    }
                    if($purchase_list){
                        M()->startTrans();
                        $purchase = $this->insert_purchase_in($purchase_list['id_purchase']);
                        $add_road = $this->add_road($purchase_list['id_purchase']);
                        $result2 = $this->Purchase->where(array('id_purchase' =>  $purchase_list['id_purchase']))->save($data);
                        $res4 = D("Purchase/PurchaseStatus")->add_pur_history($purchase_list['id_purchase'], PurchaseStatus::PAYMENT, '已付款');
                        if($purchase === false || $add_road === false || $result2 === false ||$res4 === false){
                            M()->rollback();
                        }else{
                            M()->commit();
                        }
                    } else {
                        $infor['warning'][] = sprintf('第%s行: 渠道订单号:%s 不存在', $count++, $row[0]);
                        continue;
                    }
                    $result = $this->Purchase->where(array('purchase_no' => array('IN',$purchase_list['id_purchase'])))->save($data);
                }catch (Exception $e) {
                    echo $e->getMessage();
                }

                $infor['success'][] = sprintf('第%s行: 采购预付款-渠道号:%s  已付款', $count++, $row[0]);
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '导入采购预付款');
        }

        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);

        $this->display();
    }
}
