<?php
namespace Payment\Controller;
use Common\Controller\AdminbaseController;

class IndexController extends AdminbaseController {

    protected $order,$page;

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order")->alias('o');
        $this->page = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /*
     * 订单列表
     */

    public function index() {
        /** @var \Order\Model\OrderModel $order_model */
        $order_model = $this->order;

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
        $where = $order_model->form_where($_GET);
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }
        //筛选ip
        if(isset($_GET['ip']) && !empty($_GET['ip'])){
            $find_order = D('Order/OrderInfo')->field('id_order')->where(array('ip'=> trim($_GET['ip'])))->select();
            if(!empty($find_order)){
                $where['id_order'] = array('IN', array_column($find_order, 'id_order'));
            }else{
                $where['id_order'] = 0;
            }
        }
        $where['payment_method'] = array('NOT IN','0');
        if(isset($_GET['payment_method']) && $_GET['payment_method']){
            $where['payment_method']= $_GET['payment_method'];
        }

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
            $order_list[$key]['http_referer'] = !empty($o['http_referer']) ? $o['http_referer'] : '--';
        }
//        dump($form_data['domain']);die;
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true,36000)->select();
        $advertiser = array_column($advertiser,'name','id');
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        add_system_record($_SESSION['ADMIN_ID'], 4, 4,'查看TF订单列表');
        $payment_method = D('Order/Order')->field('payment_method')
            ->where('payment_method!=0 or payment_method!=""')
            ->group('payment_method')->cache(true,36000)->getField('payment_method',true);
        $this->assign("payment_method", $payment_method);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
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
            '地区', '域名', '订单号', '姓名',
            '产品名和价格', '总价（NTS）', '属性',
            '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
            '发货日期',  '物流名称', '运单号', '物流状态'
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
        if(isset($_GET['payment_method']) && $_GET['payment_method']){
            $where['payment_method']= $_GET['payment_method'];
        }

        //ip筛选
        if(isset($_GET['ip']) && !empty($_GET['ip'])){
            $find_order = D('Order/OrderInfo')->field('id_order')->where(array('ip'=> trim($_GET['ip'])))->select();
            if(!empty($find_order)){
                $where['id_order'] = array('IN', array_column($find_order, 'id_order'));
            }else{
                $where['id_order'] = 0;
            }
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
            $product_count = 0;
            foreach ($products as $p) {
                $product_name .= $p['product_title'] . "\n";
                if($p['sku_title']) {
                    $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                } else {
                    $attrs .= $p['product_title']. ' x ' . $p['quantity'] . ",";
                }
                $product_count +=$p['quantity'];
            }
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $getShipObj = D("Order/OrderShipping")->field('track_number,status_label')->where('id_order=' . $o['id_order'])->select();
            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $shipping_name = M('Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
            $data = array(
                $o['province'], $domain_model[$o['id_domain']], $o['id_increment'], $o['first_name'].' '.$o['last_name'],
                $product_name, \Common\Lib\Currency::format($o['price_total'],$o['currency_code']), $attrs,
                $o['address'], $product_count, $o['remark'], $o['created_at'], $status_name, 
                $o['date_delivery'], $shipping_name, $trackNumber, $trackStatusLabel
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
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看DF订单详情');
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
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where = array('id_order' => array('EQ', $getId));
        $where['id_department'] = array('IN', $department_id);
        $order = $this->order->where($where)->find();
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
                    $arr_html[$product_id]['id_product'] = $product_id;
                    $arr_html[$product_id]['product_title'] = $product['product_title'];
                    $arr_html[$product_id]['id_order_item'] = $product['id_order_item'];
                    $arr_html[$product_id]['id_order_items'][$sku_id] = $product['id_order_item'];
                    $arr_html[$product_id]['quantity'][$sku_id] = $product['quantity'];
                    $arr_html[$product_id]['attrs'][$sku_id] = unserialize($product['attrs']);
                    $arr_html[$product_id]['attr_option_value'][$sku_id] = $options->get_attr_list_by_id($product_id);
                    $arr_html[$product_id]['attr_option_value_data'] = $options->get_attr_list_by_id($product_id);
                    $arr_html[$product_id]['sku_id'] = $product['id_product_sku'];
                    $products[$key]['attr_option_value'] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                    $all_attr[$product_id] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                }
            }
        }

        $this->assign("products", $arr_html);
        $this->assign("all_attr", $all_attr);
        $this->assign("order", $order);
        $this->display();
    }
    
    //编辑订单添加产品属性
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
        if (isset($_POST['action']) && $_POST['action'] == 'delete_attr') {
            //因为要添加权限，所以先写到这个控制器了。            
            $itemId = I('post.order_attr_id');
            if ($orderId && $itemId) {
                $deleteData = D("Order/OrderItem")->find($itemId);
                $comment = '删除产品属性：' . json_encode($deleteData);
                D("Order/OrderItem")->where('id_order_item=' . $itemId)->delete();
                D('Order/Order')->where('id_order='.$orderId)->save(array('price_total'=>$order['price_total']-$deleteData['total']));
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
                    $option_value = implode(',', $psv);
                    $product_sku = D('Product/ProductSku')->where("option_value='$option_value' and id_product=$key")->find();
                    $item_result = D('Order/OrderItem')->where('id_product='.$key.' and id_product_sku='.$product_sku['id_product_sku'].' and id_order='.$data['id_order'])->find();                                      
                    $item_data['id_order'] = $data['id_order'];
                    $item_data['id_product'] = $key;
                    $item_data['id_product_sku'] = $product_sku['id_product_sku']; 
                    $item_data['sku'] = $product_sku['sku'];
                    $item_data['sku_title'] = $product_sku['title'];
                    $item_data['sale_title'] = $product['title'];
                    $item_data['product_title'] = $product['title'];
                    $item_data['quantity'] = $data['number'][$key][$psk];
                    $item_data['price'] = $product['sale_price'];
                    $item_data['total'] = $product['sale_price']*$data['number'][$key][$psk];
                    $item_data['attrs'] = serialize($psv);
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

            foreach ($data['pro_id'] as $pro_key=>$pro_val) {
                if(!in_array($pro_val,$product_id)) {
                    $other_product = D('Product/Product')->field('title,inner_name,sale_price')->where('id_product='.$pro_val)->find();
                    $item_id = $data['order_item_id'][$pro_val][0];
                    $other_item_data['total'] = $other_product['sale_price']*$data['qty'.$pro_val];
                    $other_item_data['quantity'] = $data['qty'.$pro_val];
                    D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$pro_val][0])->data($other_item_data)->save();
                }

                if(isset($data['qty'.$pro_val]) && $data['qty'.$pro_val] == 0) {
                    D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$pro_val][0])->delete();
                }
            }      
            D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],2, '编辑订单属性');
            add_system_record(sp_get_current_admin_id(), 2, 4, '编辑TF订单属性');
            $this->success("保存完成！", U('Index/edit_order', array('id' => $orderId)));
        } else {
            $this->error("参数不正确！");
        }
    }
    
    /**
     * 单个填写物流跟踪号发货，修改订单状态为发货
     */
    public function delivery() {
        $orderId = (int) $_POST['order_id'];
        if ($_POST['track_number'] && $orderId) {
            $getTrackNumber = explode(',', str_replace('，', ',', $_POST['track_number']));
            $trackNumber = D("Order/OrderShipping")->field('track_number')->where(array('track_number' => array('IN', $getTrackNumber)))->find();
            if (!$trackNumber) {
                D("Order/OrderRecord")->addHistory($orderId, 8, 4,'导入运单号 '.$track_number);
                $return = D("Order/OrderShipping")->updateShipping($orderId, $getTrackNumber, $_POST['order_remark']);
            } else {
                $implodeTraNu = $trackNumber ? implode(',', $trackNumber) : '';
                $return = array('status' => 0, 'message' => $implodeTraNu . '此跟踪号已经使用。');
            }
        } else {
            $return = array('status' => 0, 'message' => '订单号或订单ID不能为空。');
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '填写物流跟踪号发货，修改TF订单状态为发货');

        echo json_encode($return);
        exit();
    }
    
    /**
     * 要求取消订单
     */
    public function cancelOrder(){
        try{
            $orderId = I('post.order_id');
            D("Order/Order")->where('id_order='.$orderId)->save(array('id_order_status'=>14));
            D("Order/OrderRecord")->addHistory($orderId,14,3,'【仓储管理取消】 '.$_POST['comment']);
            $status = 1; $message = '';
        }catch (\Exception $e){
            $status = 1; $message = $e->getMessage();
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '要求取消TF订单');
        echo json_encode(array('status'=>$status,'message'=>$message));
    }
    
    public function export_excel() {
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

        if (isset($_GET['user_province']) && $_GET['user_province']) {
            $where['province'] = array('EQ', $_GET['user_province']);
        }
        if ($status_id > 0) {
            $where[] = "`id_order_status` = '$status_id'";
        } else {
            $where[] = "`id_order_status` IN (8)";
        }
        if ($time_start)
            $where[] = "`created_at` >= '$time_start'"; //create_at
        if ($time_end)
            $where[] = "`created_at` < '$time_end'";
        if (isset($_GET['id_shipping']) && $_GET['id_shipping'] > 0) {
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
                ->select();

        $order_item = D('Order/OrderItem');
        foreach ($orderList as $key => $o) {
            $orderList[$key]['products'] = $order_item->get_item_list($o['id_order']);

            if (in_array($o['id_order_status'], array(4, 6))) {//修改订单为配货中，并且写入导出记录
                //$model->where('id_order=' . $o['id_order'])->save(array('id_order_status' => 5)); // 5 配货中
                D("Order/OrderRecord")->addHistory($o['id'],$o['id_order_status'],5, '导出未配货订单');
            }
        }
        $orders = $orderList;
        
        $result = D('Order/OrderStatus')->cache(true, 3600)->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }
        /** @var \Common\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        $data = array();
        $productSku = D('Common/ProductSku');
        foreach ($orders as $o) {
            $product_name = '';
            $attrs = '';
            $skuString = '';
            $products = $order_item->get_item_list($o['id_order']);
            $web = D('Common/Domain')->field('name')->where(array('id_domain'=>$o['id_domain']))->find();
            $qty = 0;
            foreach ($products as $p) {
                $product_name .= $p['product_title'] . "\n";
                $qty += $p['quantity'];
                if ($p['id_product_sku']) {
                    $getSkuObj = $productSku->cache(true, 3600)->find($p['id_product_sku']);
                    $skuString .= $getSkuObj['model'] . '   ';
                } else {
                    $skuString .= '';
                }

                if (isset($p['order_attrs']))
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

            if (in_array($o['id_order_status'], array(4, 6))) {//修改订单为配货中，并且写入导出记录
                $model->where('id=' . $o['id'])->save(array('status_id' => 5)); // 18 配货中
                D("Order/OrderRecord")->addHistory($o['id'], 5,5, '导出未配货订单');
            }
        }
        if ($data) {
            foreach ($data as $items) {
                if (is_array($items)) {
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
}
