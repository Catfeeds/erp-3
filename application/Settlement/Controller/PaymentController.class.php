<?php
namespace Settlement\Controller;
use Common\Controller\AdminbaseController;

class PaymentController extends AdminbaseController {

    protected $order,$page;

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->page = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /*
     * 订单列表
     */

    public function index() {
        /** @var \Order\Model\OrderModel $order_model */
        $order_model = $this->order;
        $where = $order_model->form_where($_GET);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }
        $where['payment_method'] = array('NOT IN','0');
        $where['id_order_status'] = array('NOT IN',array(1,2,3,11,12,13,14,15));//去除无效单
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();

        //$formData['product_type'] = $baseSql->getFieldGroupData('product_type');
        $form_data['track_status'] = D('Order/OrderShipping')->field('status_label as track_status')
                        ->where("status_label is not null or status_label !='' ")
                        ->group('status_label')->cache(true, 12000)->select();


        //今日统计订单 条件
        $today_where = $where;
        $today_where['created_at'] = array('EGT', $today_date);
        $all_domain_total = $order_model->field('count(`id_domain`) as total,id_domain')->where($today_where)
                        ->order('total desc')->group('id_domain')->select();

        //修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
        if (isset($where['status_label']) && $where['status_label']) {
            $count = $order_model->alias('o')
                            ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.order_id)', 'LEFT')
                            ->where($where)->count();
            $today_total = $order_model->alias('o')
                            ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.order_id)', 'LEFT')
                            ->where($today_where)->count();
            $page = $this->page($count, $this->page);
            $order_list = $order_model->alias('o')->field('o.*,s.signed_for_date')
                            ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.order_id)', 'LEFT')
                            ->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        } else {
            $count = $order_model->where($where)->count();
            $today_total = $order_model->where($today_where)->count();
            $page = $this->page($count, $this->page);
            $order_list = $order_model->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
        }
//        dump($form_data['domain']);die;
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true,36000)->select();
        $advertiser = array_column($advertiser,'name','id');
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        add_system_record($_SESSION['ADMIN_ID'], 4, 4,'查看结款管理TF订单列表');
        $this->assign("department", $department);
        $this->assign("advertiser", $advertiser);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("page", $page->show('Admin'));
        $this->assign("today_total", $today_total);
        $this->assign("order_total", $count);
        $this->assign("all_domain_total", $all_domain_total);

        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("order_list", $order_list);
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }

    /**
     * 导出订单列表
     */
    public function export_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $column = array(
            '地区', '域名', '订单号', '姓名', '电话号码', '邮箱',
            '产品名和价格', '总价（NTS）', '属性',
            '送货地址', '购买数量', '留言备注', '下单时间', '订单状态',
            '发货日期',  '运单号', '物流状态'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $where = $this->order->form_where($_GET);
        $where['payment_method'] = array('NOT IN','0');
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }

        $domain_model = D('Domain/Domain')->get_all_domain();
        $orders = $this->order
                ->where($where)
                ->order("id_order ASC")
                ->select();

        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        foreach ($orders as $o) {
            $product_name = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            foreach ($products as $p) {
                $product_name .= $p['product_title'] . "\n";
                if($p['sku_title']) {
                    $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                } else {
                    $attrs .= $p['product_title']. ' x ' . $p['quantity'] . ",";
                }
            }
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $getShipObj = D("Order/OrderShipping")->field('track_number,status_label')->where('id_order=' . $o['id_order'])->select();
            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $data = array(
                $o['province'], $domain_model[$o['id_domain']], $o['id_increment'], $o['first_name'], $o['tel'], $o['email'],
                $product_name, \Common\Lib\Currency::format($o['price_total'],$o['currency_code']), $attrs,
                $o['address'], $o['order_count'], $o['remark'], $o['created_at'], $status_name, 
                $o['date_delivery'], ' ' . $trackNumber, $trackStatusLabel
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                if($key != 7 && $key != 10) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                } else {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出TF订单信息');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
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
            ->order('created_at desc')->select();
        $shipping = D('Common/Shipping')
            ->where(array('id_shipping'=>(int)$order['shipping_id']))
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
        $this->display();
    }
}
