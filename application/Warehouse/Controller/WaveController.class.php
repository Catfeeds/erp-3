<?php

namespace Warehouse\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use Order\Model\UpdateStatusModel;
use Purchase\Lib\PurchaseStatus;

class WaveController extends AdminbaseController {

    //波次单列表
    public function Index() {
        $where = array();
        if (isset($_GET['status']) && $_GET['status']) {
            $where['ow.status'] = array('EQ', $_GET['status']);
        }
        if (isset($_GET['id_shipping']) && $_GET['id_shipping']) {
            $where['ow.id_shipping'] = array('EQ', $_GET['id_shipping']);
        }if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['ow.id_department'] = array('EQ', $_GET['id_department']);
        }
        if (isset($_GET['keyword']) && $_GET['keyword']) {
            $where['ow.wave_number'] = array('LIKE', '%' . $_GET['keyword'] . '%');
        }
        $where['ow.type']=1;
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
            $where[] = array('ow.created_at' => $createAtArray);
        }

        $M = new \Think\Model();
        $order_wave_tab = M('OrderWave')->getTableName();
        $order_item_tab = M('OrderItem')->getTableName();
        $order_tab = M('Order')->getTableName();

        $list_count = $M->table($order_wave_tab . ' as ow')->field('ow.wave_number,ow.id_order,count(*) as order_count,ow.id_shipping,ow.status,ow.created_at')->where($where)
                        ->group('ow.wave_number')
                        ->order('ow.created_at DESC')->select();

        $page = $this->page(count($list_count), 30);

        $list = $M->table($order_wave_tab . ' as ow')->field('ow.wave_number,ow.id_order,count(*) as order_count,ow.id_shipping,ow.status,ow.created_at,ow.id_department')->where($where)
                        ->group('ow.wave_number')
                        ->order('ow.created_at DESC')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($list as $k => $v) {
            $wave = M('OrderWave')->field('id_order')->where(array('wave_number' => $v['wave_number']))->select();
            $order_id = array_column($wave, 'id_order');
            $result = $M->table($order_tab . ' as o')->field('SUM(oi.quantity) as qty')->join('LEFT JOIN ' . $order_item_tab . ' as oi ON oi.id_order=o.id_order')
                            ->where(array('oi.id_order' => array('IN', $order_id)))->find();
            $list[$k]['quantity'] = $result['qty'];
            $list[$k]['shipping_name'] = M('shipping')->where(array('id_shipping' => $v['id_shipping']))->getField('title');
            $list[$k]['department'] = M('Department')->where(array('id_department'=>$v['id_department']))->getField('title');
            $list[$k]['print_picking_num'] = M('OrderWavePrint')->where(array('wave_number' => $v['wave_number']))->getField('print_picking_num'); //打印配货单次数
            $list[$k]['print_waybill_num'] = M('OrderWavePrint')->where(array('wave_number' => $v['wave_number']))->getField('print_waybill_num'); //打印运单次数
        }

        $template = M('WaybillTemplate')->field('id,title,id_shipping')->where(array('status'=>1))->order('id_shipping ASC')->select();
        $department = M('Department')->where(array('type'=>1))->order('department_num ASC')->getField('id_department,title',true);
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '仓库查看波次单列表');
        $shipping = M('Shipping')->where(array('status' => 1))->select();
        // dump($shipping);die;
        $this->assign('list', $list);
        $this->assign('shipping', $shipping);
        $this->assign("page", $page->show('Admin'));
        $this->assign('template',$template);
        $this->assign('department',$department);
        $this->display();
    }

    //波次单详情
    public function info_list() {
        $wave_num = I('get.num');
        
        $where = array();
        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $order = M('Order')->field('id_order')->where(array('id_increment'=>$_GET['keyword']))->find();
            $where['ow.id_order'] = array('EQ',$order['id_order']);
        }
        $where['ow.wave_number'] = array('EQ',$wave_num);
        $M = new \Think\Model();
        $order_tab = M('Order')->getTableName();
        $order_wave_tab = M('OrderWave')->getTableName();
        $order_item_tab = M('OrderItem')->getTableName();

        $list_count = $M->table($order_wave_tab . ' as ow')->field('DISTINCT ow.id,ow.wave_number,ow.id_shipping as wave_shipping,ow.track_number_id,o.*')
                        ->join('LEFT JOIN ' . $order_tab . ' as o ON o.id_order=ow.id_order')
                        ->join('LEFT JOIN '. $order_item_tab . ' as oi ON ow.id_order=oi.id_order')
                        ->where($where)->select();

        $page = $this->page(count($list_count), 30);

        $list = $M->table($order_wave_tab . ' as ow')->field('DISTINCT ow.id,ow.wave_number,ow.id_shipping as wave_shipping,ow.track_number_id,ow.attr_id,o.*,COUNT(oi.id_order) as oi_count')
                        ->join('LEFT JOIN ' . $order_tab . ' as o ON o.id_order=ow.id_order')
                        ->join('LEFT JOIN '. $order_item_tab . ' as oi ON ow.id_order=oi.id_order')
                        ->where($where)->group('ow.id_order')
                        ->order('oi.id_product ASC,oi_count ASC,oi.sku DESC,oi.quantity DESC')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($list as $key => $v) {
            $list[$key]['products'] = D('Order/OrderItem')->get_item_list($v['id_order']);
            $list[$key]['total_price'] = \Common\Lib\Currency::format($v['price_total'], $v['currency_code']);
            $list[$key]['shipping_name'] = M('Shipping')->where(array('id_shipping' => $v['wave_shipping']))->getField('title');
            $list[$key]['track_number'] = M('ShippingTrack')->where(array('id_shipping_track' => $v['track_number_id']))->getField('track_number');
            $list[$key]['zone'] = M('Zone')->where(array('id_zone'=>$v['id_zone']))->getField('title');
            $pro_ids = M('OrderItem')->field('id_product')->where(array('id_order'=>$v['id_order']))->group('id_product')->order('sku ASC')->select();
            $pro_id = array_column($pro_ids, 'id_product');
            if($pro_id) {
                $pro_result = M('Product')->field('foreign_title')->where(array('id_product' => array('IN', $pro_id)))->select();
                $pro_foreign_title = array_column($pro_result, 'foreign_title');
            }
            $list[$key]['foreign'] =  $pro_foreign_title?implode('<br>', $pro_foreign_title):'';            
        }

        $waybills = M('WaybillTemplate')->field('id,title')->where(array('id_shipping' => $list[0]['wave_shipping']))->select();
        $shipping = M('Shipping')->select();
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '仓库查看波次单详情列表');

        $wave_info['need_match_shipping'] = empty($list[0]['wave_shipping']) ? true : false;

        if(!$wave_info['need_match_shipping']){
            $shipping_info = M('Shipping')->where(array('id_shipping' => $list[0]['wave_shipping']))->find();
            $wave_info['id_shipping'] = $shipping_info['id_shipping'];
            $wave_info['need_send_order'] = $shipping_info['need_send_order'];
            $wave_info['shipping_tag'] = $shipping_info['tag'];
        }

        $this->assign('wave_info', $wave_info);
        $this->assign('shipping', $shipping);
        $this->assign('list', $list);
        $this->assign("page", $page->show('Admin'));
        $this->assign('status_list', D('Order/OrderStatus')->get_status_label());
        $this->assign('is_shipping_name', $list[0]['shipping_name']);
        $this->assign('is_shipping_id', $list[0]['wave_shipping']);
        $this->assign('attr_id',$list[0]['attr_id']);
        $this->assign('waybilled', $waybills);
        $this->assign('getData',$_GET);
        $this->display();
    }

    //预览配货单
    public function picking() {

        $wave_num = I('get.num');

        $M = new \Think\Model();
        $order_tab = M('Order')->getTableName();
        $order_item_tab = M('OrderItem')->getTableName();
        $product_sku_tab = M('ProductSku')->getTableName();
        $order_wave_tab = M('OrderWave')->getTableName();
        $warehouse_goods_allocation_tab = M('WarehouseGoodsAllocation')->getTableName();
        $warehouse_goods_sku_tab = M('WarehouseGoodsSku')->getTableName();
        $warehouse_allocation_stock_tab = M('WarehouseAllocationStock')->getTableName();

        $wave = M('OrderWave')->field('id_order,id_department,created_at,id_shipping,attr_id')->where(array('wave_number' => $wave_num))->select();
        $department_id = array_unique(array_column($wave, 'id_department'));
        $department = M('Department')->field("CONCAT(title,'-',department_code) as title")->where(array('id_department' => array('IN', $department_id)))->select();
        $department = array_column($department, 'title');
        $department_name = implode(',', $department);
        $id_order = array_column($wave, 'id_order');
//        $list = $M->table($order_tab . ' as o')->field('o.id_order,oi.id_product_sku,oi.sku,SUM(oi.quantity) as quantity,oi.sale_title,oi.sku_title,o.created_at,oi.id_product')
//                ->join('LEFT JOIN ' . $order_item_tab . ' as oi ON oi.id_order=o.id_order')
//                ->where(array('o.id_order' => array('IN', $id_order)))->group('oi.sku')->order('oi.id_product desc')->select();
        
        $list = $M->table($order_wave_tab . ' as ow')->field('ow.id_shipping,o.id_order,oi.id_product_sku,oi.sku,SUM(oi.quantity) as quantity,oi.sale_title,ps.title as sku_title,o.created_at,oi.id_product')
                        ->join('LEFT JOIN ' . $order_tab . ' as o ON o.id_order=ow.id_order')
                        ->join('LEFT JOIN '. $order_item_tab . ' as oi ON ow.id_order=oi.id_order')
                        ->join('LEFT JOIN '. $product_sku_tab . ' as ps ON ps.id_product_sku=oi.id_product_sku')
                        ->where(array('ow.wave_number' => $wave_num))
                        ->group('oi.sku')
                        ->order('oi.id_product ASC,oi.sku DESC,oi.quantity DESC')->select();

        $product_count = 0;
        $shipping_name = M('Shipping')->where(array('id_shipping'=>$wave[0]['id_shipping']))->getField('title');

        $attrs = array();
        foreach ($list as $key => $v) {
            $img = M('Product')->field('thumbs,inner_name,id_classify')->where(array('id_product' => $v['id_product']))->find();
//            $list[$key]['img'] = json_decode($img['thunmbs'], true);
            $list[$key]['inner_name'] = $img['inner_name'];

            $warehouse_allocation = M('OrderWaveLessstock')->field('desc')->where(array('id_order' => array('IN', $id_order),'id_product_sku'=>$v['id_product_sku'],'id_product'=>$v['id_product']))->select();
            $warehouse_allocation = array_column($warehouse_allocation, 'desc');
            $cc = implode(',', $warehouse_allocation);
            $dd = explode(',', $cc);
            if($warehouse_allocation) {
                $array = array();
                foreach ($dd as $k=>$val) {
                    $str = explode('捡货数量', $val);
                    if(!isset($array[$str[0]])){
                        $array[$str[0]] = (int)$str[1];
                    } else {
                        $array[$str[0]] += (int)$str[1];
                    }       
                }
                $m = '';
                foreach($array as $kk=>$vv) {
                    $m .= $kk.'捡货数量'.$vv.'<br>';
                }
            } else {
                $m = '';
            }
            $list[$key]['location'] = $m;
            $product_count += $v['quantity'];
            $attrs[] = $img['id_classify'];
        }

        if(count(array_unique($attrs))==1) {
            $attr_name = $attrs[0]==2?'特货':'普货';
        } else {
            $attr_name = '';
        }
//        $arr_list = $this->get_sort($list, 'location');

        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '仓库预览配货单');
        $this->assign('list', $list);
        $this->assign('wave', $wave);
        $this->assign('department_name', $department_name);
        $this->assign('product_count', $product_count);
        $this->assign('shipping_name',$shipping_name);
        $this->assign('attr_name',$attr_name);
        $this->display();
    }

    //搜索结果生成波次单
    public function search_generate() {
        $M = new \Think\Model();
        $where = D('Order/Order')->form_where($_GET);
        if (isset($_GET['sku_keyword']) && $_GET['sku_keyword']) {
            $ordName = D("Order/Order")->getTableName();
            $ordIteName = D("Order/OrderItem")->getTableName();
            $order_ids = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                            ->where(array('oi.sku' => array('LIKE', '%' . $_GET['sku_keyword'] . '%'), array('o.id_order_status' => array('EQ', 4))))
                            ->group('oi.id_order')->select();
            $order_ids = array_column($order_ids, 'id_order');
            if ($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }
        }
        if(isset($_GET['price']) && $_GET['price']){
            if($_GET['price']==1){$where['price_total']=array('GT', 0);}
            if($_GET['price']==2){$where['price_total']=array('LT', 1);}
            if($_GET['price']==1381){$where['price_total']=array('GT', 1380);}
            if($_GET['price']==1379){$where['price_total']=array('elt', 1380);}               
//            $where['price_total'] = $_GET['price']==2?array('LT', 1):array('GT', 0);
        }
        if(isset($_GET['payment_method']) && $_GET['payment_method']){
            switch($_GET['payment_method']){
                case '1':case 1:
                $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
                break;
                case 2:case '2':
                    $where['_string'] = "o.payment_method !='0'";
                    break;
            }
        }
        if(isset($_GET['pro_num']) && $_GET['pro_num']) {
            switch ($_GET['pro_num']) {
                case '1':
//                    $owhere['oi.quantity'] = array('GT',$_GET['pro_num']);
                    $having = 'count(oi.id_order)>1';
                break;
                case '2':
//                    $owhere['oi.quantity'] = 1;
                    $having = 'count(oi.id_order)=1';
                break;
            }
            if(isset($order_ids) && !empty($order_ids))
            {
                $owhere['o.id_order'] = array('IN',$order_ids);
            }
            else if(isset($order_ids) && !empty($order_ids))
            {
                $owhere['o.id_order'] = array('IN',array(0));
            }
            $owhere['o.id_order_status'] = array('EQ',4);
            $orderids = M('Order')->alias('o')->join('__ORDER_ITEM__ oi ON o.id_order=oi.id_order','LEFT')->field('o.id_order')->where($owhere)->group('oi.id_order')->having($having)->select();
            $order_ids = array_column($orderids, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }
        }
        if(isset($_GET['id_classify']) && $_GET['id_classify']) {
            $ordIteName = M("OrderItem")->getTableName();
            $ordName = M("Order")->getTableName();

            $product_ids = M('Product')->field('id_product')->where(array('id_classify' => array('IN', $_GET['id_classify'])))->select();
            $product_id = array_column($product_ids, 'id_product');
            $product_id ? $pro_where['oi.id_product'] = array('IN', $product_id) : $pro_where['oi.id_product'] = array('IN', array(0));
            $pro_where['o.id_order_status'] = array('EQ', 4);

            if(isset($order_ids) && !empty($order_ids))
            {
                $pro_where['o.id_order'] = array('IN',$order_ids);
            }
            else if(isset($order_ids) && !empty($order_ids))
            {
                $pro_where['o.id_order'] = array('IN',array(0));
            }
            $class_order_id = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where($pro_where)->group('id_order')->select();
            $order_ids = array_column($class_order_id, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }

        }
        if(isset($_GET['id_wave_shipping']) && $_GET['id_wave_shipping']) {
            $shipping_id = $_GET['id_wave_shipping'];//匹配的物流
            $swhere['id_shipping'] = $shipping_id;
        }
        if(isset($_GET['attr_id']) && $_GET['attr_id']) {
            $swhere['type'] = $_GET['attr_id'];
            $attr_id = $_GET['attr_id'];
        }
        $where['id_order_status'] = array('EQ', 4);
        $swhere['track_status'] = 0;

        if (isset($order_ids) && !empty($order_ids))
        {
            $order_where['id_order'] = array('IN',$order_ids);
        }
        else if(isset($order_ids) && empty($order_ids))
        {
            $order_where['id_order'] = array('IN',array(0));
        }
        $order_where['id_order_status'] = array('EQ',OrderStatus::UNPICKING);
        $order_id_arr = D('Order/Order')->field('id_order')->where($order_where)->select();
        $order_ids = array_column($order_id_arr, 'id_order');
        if (!$order_ids)
        {
            $order_ids = array(0);
        }
        if (isset($_GET['match_start_time']) && $_GET['match_start_time'])
        {
            $start_time = strtotime($_GET['match_start_time'])+43200;
            $m_where['o.id_order'] = array('IN',$order_ids);
            $m_where['oi.id_order_status'] = array('EQ',OrderStatus::UNPICKING);
            $order_id_arr_one = M('Order')->alias('o')->join('__ORDER_RECORD__ oi ON o.id_order=oi.id_order','LEFT')->field('oi.id_order,max(oi.created_at) as created_at')
                ->where($m_where)->group('oi.id_order')->having('unix_timestamp(max(oi.created_at)) >='.$start_time)->select();
            $order_ids = array_column($order_id_arr_one, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }
        }
        if (isset($_GET['match_end_time']) && $_GET['match_end_time'])
        {
            if (isset($order_ids) && !empty($order_ids))
            {
                $m_where['o.id_order'] = array('IN',$order_ids);
            }
            else if(isset($order_ids) && empty($order_ids))
            {
                $m_where['o.id_order'] = array('IN',array(0));
            }

            $end_time = strtotime($_GET['match_end_time'])+43200;
            $m_where['oi.id_order_status'] = array('EQ',OrderStatus::UNPICKING);
            $order_id_arr_two = M('Order')->alias('o')->join('__ORDER_RECORD__ oi ON o.id_order=oi.id_order','LEFT')->field('oi.id_order,max(oi.created_at) as created_at')
                ->where($m_where)->group('oi.id_order')->having( $end_time.'>unix_timestamp(max(oi.created_at))')->select();
            $order_ids = array_column($order_id_arr_two, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse'])
        {
            $where['id_warehouse'] =  array('EQ', $_GET['id_warehouse']);
        }
        $order_list = M('Order')->alias('o')->field('id_order,id_department,id_zone,id_warehouse')->where($where)->select();
        $department = array_column($order_list, 'id_department');
        $zone = array_column($order_list, 'id_zone');
        $warehouse = array_column($order_list, 'id_warehouse');
        $number = date('Ymds') .$order_list[0]['id_department'] . rand(0, 9999);
//        if (count($order_list) <= 100) {
        if(count(array_unique($warehouse)) == 1) {
            if(count(array_unique($department)) == 1) {
                if(count(array_unique($zone)) == 1) {
                    try {
                        if($shipping_id) {
                            //判断是否需要运单号
                            $shipping = M('Shipping')->where(array('id_shipping' => $shipping_id))->find();
                            if($shipping['need_track_num']) {
                                if ($shipping['need_shipping_type'] == 1) {
                                    $track_numbers = M('ShippingTrack')->field('id_shipping,id_shipping_track,track_number')->where(array('id_shipping' => $shipping_id, 'track_status' => 0, 'type' => $attr_id))->select();
                                } else {
                                    $track_numbers = M('ShippingTrack')->field('id_shipping,id_shipping_track,track_number')->where(array('id_shipping' => $shipping_id, 'track_status' => 0))->select();
                                }

                                if (empty($track_numbers) || count($order_list) > count($track_numbers)) {
                                    $this->error('该物流可用运单号不足');
                                }
                            }

                            $order_res = array();
                            foreach ($order_list as $k=>$val) {
                                if($shipping_id == 33 || $shipping_id == 39) {
                                    $order = M('Order')->field('id_department,id_increment, city, area, address')->where(array('id_order' => $val['id_order']))->find();
                                    $order_res[] = array(
                                        'order_id'=>$order['id_increment'],
                                        'address'=>trim($order['city'] . $order['area'] . $order['address'])
                                    );
                                }
                            }
                            if(!empty($order_res)) $res = $this->check_deliver_region($order_res);
                            $id_num = array();
                            if($shipping_id == 33 || $shipping_id == 39) {
                                foreach ($res['Data'] as $key => $val) {
                                    $order_id = $order_list[$key]['id_order'];
                                    if ($val['Status'] == 1) {
                                        $id_department = M('Order')->where(array('id_order' => $order_id))->getField('id_department');
                                        $data = array(
                                            'wave_number' => $number,
                                            'id_order' => $order_id,
                                            'created_at' => date('Y-m-d H:i:s'),
                                            'id_department' => $id_department,
                                            'id_shipping' => $shipping_id,
                                            'track_number_id' => isset($track_numbers) ? $track_numbers[$key]['id_shipping_track'] : null,
                                            'status' => 2,
                                            'update_at' => date('Y-m-d H:i:s'),
                                            'attr_id' => $attr_id
                                        );
                                        D('Common/OrderWave')->add($data);
                                        D('Order/Order')->where(array('id_order' => $order_id))->save(array('id_order_status' => OrderStatus::PICKING)); //订单状态改为配货中
                                        $this->add_wave_shipping(isset($track_numbers) ? $track_numbers[$key] : null, $order_id, $shipping_id);
                                    } else {
                                        $id_num[] = $val['order_id'];
                                        $msg = '不在配送区域，无法匹配物流';
                                        continue;
                                    }
                                }
                            } else {
                                foreach ($order_list as $key => $val) {
                                    $order_id = $val['id_order'];
                                    $id_department = M('Order')->where(array('id_order' => $order_id))->getField('id_department');
                                    $data = array(
                                        'wave_number' => $number,
                                        'id_order' => $order_id,
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'id_department' => $id_department,
                                        'id_shipping' => $shipping_id,
                                        'track_number_id' => isset($track_numbers) ? $track_numbers[$key]['id_shipping_track'] : null,
                                        'status' => 2,
                                        'update_at' => date('Y-m-d H:i:s'),
                                        'attr_id' => $attr_id
                                    );
                                    D('Common/OrderWave')->add($data);
                                    D('Order/Order')->where(array('id_order' => $order_id))->save(array('id_order_status' => OrderStatus::PICKING)); //订单状态改为配货中
                                    $this->add_wave_shipping(isset($track_numbers) ? $track_numbers[$key] : null, $order_id, $shipping_id);
                                }
                            }

                            if(!empty($id_num)){
                                $status = 0;
                                $message = '订单号：'.implode("\r\n",$id_num).$msg;
                            } else {
                                $status = 1;
                                $message = '生成成功';
                            }

                        }else {
                            foreach ($order_list as $val) {
                                $data = array(
                                    'wave_number' => $number,
                                    'id_order' => $val['id_order'],
                                    'status' => 0,
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'id_department' => $val['id_department']
                                );
                                D('Common/OrderWave')->add($data);
                                D('Order/Order')->where(array('id_order' => $val['id_order']))->save(array('id_order_status' => OrderStatus::PICKING));
                                D("Order/OrderRecord")->addHistory($val['id_order'], 5, 4, '生成波次单，把订单状态改为配货中');
                            }
                            $status = 1;
                            $message = '生成成功';
                        }
                    } catch (\Exception $e) {
                        $status=0;
                        $message = $e->getMessage();
                    }
                } else {
                    $status = 0;
                    $message = '请选择相同地区的订单进行生成';
                }
            } else {
                $status = 0;
                $message = '请选择相同部门的订单进行生成';
            }
        } else {
            $status = 0;
            $message = '请选择相同仓库的订单进行生成';
        }
        add_system_record($_SESSION['ADMIN_ID'], 1, 3, '仓库未配货列表搜索结果生成波次单');
        if($status == 0){
            $this->error($message);
        }else{
            $this->success($message);
        }
    }

    //页面生成波次单
    public function page_generate() {
        $M = new \Think\Model();
        $where = D('Order/Order')->form_where($_GET);
        if (isset($_GET['sku_keyword']) && $_GET['sku_keyword']) {
            $ordName = D("Order/Order")->getTableName();
            $ordIteName = D("Order/OrderItem")->getTableName();
            $order_ids = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                            ->where(array('oi.sku' => array('LIKE', '%' . $_GET['sku_keyword'] . '%'), array('o.id_order_status' => array('EQ', 4))))
                            ->group('oi.id_order')->select();
            $order_ids = array_column($order_ids, 'id_order');
            if ($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }
        }
        if(isset($_GET['price']) && $_GET['price']){
//            $where['price_total'] = $_GET['price']==2?array('LT', 1):array('GT', 0);
            if($_GET['price']==1){$where['price_total']=array('GT', 0);}
            if($_GET['price']==2){$where['price_total']=array('LT', 1);}
            if($_GET['price']==1381){$where['price_total']=array('GT', 1380);}
            if($_GET['price']==1379){$where['price_total']=array('elt', 1380);}              
        }
        if(isset($_GET['payment_method']) && $_GET['payment_method']){
            switch($_GET['payment_method']){
                case '1':case 1:
                $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
                break;
                case 2:case '2':
                    $where['_string'] = "o.payment_method !='0'";
                    break;
            }
        }
        if(isset($_GET['pro_num']) && $_GET['pro_num']) {
            switch ($_GET['pro_num']) {
                case '1':
//                    $owhere['oi.quantity'] = array('GT',$_GET['pro_num']);
                    $having = 'count(oi.id_order)>1';
                break;
                case '2':
//                    $owhere['oi.quantity'] = 1;
                    $having = 'count(oi.id_order)=1';
                break;
            }
            if (isset($order_ids) && !empty($order_ids))
            {
                $owhere['o.id_order'] = array('IN',$order_ids);
            }
            elseif (isset($order_ids) && empty($order_ids))
            {
                $owhere['o.id_order'] = array('IN',array(0));
            }
            $owhere['o.id_order_status'] = array('EQ',4);
            $orderids = M('Order')->alias('o')->join('__ORDER_ITEM__ oi ON o.id_order=oi.id_order','LEFT')->field('o.id_order')->where($owhere)->group('oi.id_order')->having($having)->select();
            $order_ids = array_column($orderids, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }
        }
        if(isset($_GET['id_classify']) && $_GET['id_classify']) {
            $ordIteName = M("OrderItem")->getTableName();
            $ordName = M("Order")->getTableName();

            $product_ids = M('Product')->field('id_product')->where(array('id_classify' => array('IN', $_GET['id_classify'])))->select();
            $product_id = array_column($product_ids, 'id_product');
            $product_id ? $pro_where['oi.id_product'] = array('IN', $product_id) : $pro_where['oi.id_product'] = array('IN', array(0));
            $pro_where['o.id_order_status'] = array('EQ', 4);

            if (isset($order_ids) && !empty($order_ids))
            {
                $pro_where['o.id_order'] = array('IN',$order_ids);
            }
            elseif (isset($order_ids) && empty($order_ids))
            {
                $pro_where['o.id_order'] = array('IN',array(0));
            }
            $class_order_id = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where($pro_where)->group('id_order')->select();
            $order_ids = array_column($class_order_id, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }

        }
        if(isset($_GET['number']) && $_GET['number']) {
            $pager = $_GET['number']*100;
        } else {
            $this->error('请填写页码');
        }
        if(isset($_GET['id_wave_shipping']) && $_GET['id_wave_shipping']) {
            $shipping_id = $_GET['id_wave_shipping'];//匹配的物流
            $swhere['id_shipping'] = $shipping_id;
        }
        if(isset($_GET['attr_id']) && $_GET['attr_id']) {
            $swhere['type'] = $_GET['attr_id'];
            $attr_id = $_GET['attr_id'];
        }
        $swhere['track_status'] = 0;
        if (isset($order_ids) && !empty($order_ids))
        {
            $order_where['id_order'] = array('IN',$order_ids);
        }
        else if(isset($order_ids) && empty($order_ids))
        {
            $order_where['id_order'] = array('IN',array(0));
        }
        $order_where['id_order_status'] = array('EQ',OrderStatus::UNPICKING);
        $order_id_arr = D('Order/Order')->field('id_order')->where($order_where)->select();
        $order_ids = array_column($order_id_arr, 'id_order');
        if (!$order_ids)
        {
            $order_ids = array(0);
        }
        if (isset($_GET['match_start_time']) && $_GET['match_start_time'])
        {
            $start_time = strtotime($_GET['match_start_time'])+43200;
            $m_where['o.id_order'] = array('IN',$order_ids);
            $m_where['oi.id_order_status'] = array('EQ',OrderStatus::UNPICKING);
            $order_id_arr_one = M('Order')->alias('o')->join('__ORDER_RECORD__ oi ON o.id_order=oi.id_order','LEFT')->field('oi.id_order,max(oi.created_at) as created_at')
                ->where($m_where)->group('oi.id_order')->having('unix_timestamp(max(oi.created_at)) >='.$start_time)->select();
            $order_ids = array_column($order_id_arr_one, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }
        }
        if (isset($_GET['match_end_time']) && $_GET['match_end_time'])
        {
            if (isset($order_ids) && !empty($order_ids))
            {
                $m_where['o.id_order'] = array('IN',$order_ids);
            }
            else if(isset($order_ids) && empty($order_ids))
            {
                $m_where['o.id_order'] = array('IN',array(0));
            }

            $end_time = strtotime($_GET['match_end_time'])+43200;
            $m_where['oi.id_order_status'] = array('EQ',OrderStatus::UNPICKING);
            $order_id_arr_two = M('Order')->alias('o')->join('__ORDER_RECORD__ oi ON o.id_order=oi.id_order','LEFT')->field('oi.id_order,max(oi.created_at) as created_at')
                ->where($m_where)->group('oi.id_order')->having( $end_time.'>unix_timestamp(max(oi.created_at))')->select();
            $order_ids = array_column($order_id_arr_two, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }
        }
        $where['id_order_status'] = array('EQ', 4);
        $order_list = M('Order')->field('id_order,id_department,id_zone')->where($where)->order("created_at ASC,tel DESC,first_name DESC,email DESC")->limit($pager)->select();
        $department = array_column($order_list, 'id_department');
        $zone = array_column($order_list, 'id_zone');
        $number = date('Ymds') .$order_list[0]['id_department'] . rand(0, 9999);

        if(!empty($order_list)) {
            if(count(array_unique($department)) == 1) {
                if(count(array_unique($zone)) == 1) {
                    try {
                        if($shipping_id) {
                            //判断是否需要运单号
                            $shipping = M('Shipping')->where(array('id_shipping' => $shipping_id))->find();
                            if($shipping['need_track_num']) {
                                if ($shipping['need_shipping_type'] == 1) {
                                    $track_numbers = M('ShippingTrack')->field('id_shipping,id_shipping_track,track_number')->where(array('id_shipping' => $shipping_id, 'track_status' => 0, 'type' => $attr_id))->select();
                                } else {
                                    $track_numbers = M('ShippingTrack')->field('id_shipping,id_shipping_track,track_number')->where(array('id_shipping' => $shipping_id, 'track_status' => 0))->select();
                                }

                                if (empty($track_numbers) || count($order_list) > count($track_numbers)) {
                                    $this->error('该物流可用运单号不足');
                                }
                            }

                            $order_res = array();
                            foreach ($order_list as $k=>$val) {
                                if($shipping_id == 33 || $shipping_id == 39) {
                                    $order = M('Order')->field('id_department,id_increment, city, area, address')->where(array('id_order' => $val['id_order']))->find();
                                    $order_res[] = array(
                                        'order_id'=>$order['id_increment'],
                                        'address'=>trim($order['city'] . $order['area'] . $order['address'])
                                    );
                                }
                            }
                            if(!empty($order_res)) $res = $this->check_deliver_region($order_res);
                            if($shipping_id == 33 || $shipping_id == 39) {
                                $id_num = array();
                                foreach ($res['Data'] as $key => $val) {
                                    $order_id = $order_list[$key]['id_order'];
                                    if ($val['Status'] == 1) {
                                        $id_department = M('Order')->where(array('id_order' => $order_id))->getField('id_department');
                                        $data = array(
                                            'wave_number' => $number,
                                            'id_order' => $order_id,
                                            'created_at' => date('Y-m-d H:i:s'),
                                            'id_department' => $id_department,
                                            'id_shipping' => $shipping_id,
                                            'track_number_id' => isset($track_numbers) ? $track_numbers[$key]['id_shipping_track'] : null,
                                            'status' => 2,
                                            'update_at' => date('Y-m-d H:i:s'),
                                            'attr_id' => $attr_id
                                        );
                                        D('Common/OrderWave')->add($data);
                                        D('Order/Order')->where(array('id_order' => $order_id))->save(array('id_order_status' => OrderStatus::PICKING)); //订单状态改为配货中
                                        $this->add_wave_shipping(isset($track_numbers) ? $track_numbers[$key] : null, $order_id, $shipping_id);
                                    } else {
                                        $id_num[] = $val['order_id'];
                                        $msg = '不在配送区域，无法匹配物流';
                                        continue;
                                    }
                                }
                            } else {
                                foreach ($order_list as $key => $val) {
                                    $order_id = $val['id_order'];
                                    $id_department = M('Order')->where(array('id_order' => $order_id))->getField('id_department');
                                    $data = array(
                                        'wave_number' => $number,
                                        'id_order' => $order_id,
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'id_department' => $id_department,
                                        'id_shipping' => $shipping_id,
                                        'track_number_id' => isset($track_numbers) ? $track_numbers[$key]['id_shipping_track'] : null,
                                        'status' => 2,
                                        'update_at' => date('Y-m-d H:i:s'),
                                        'attr_id' => $attr_id
                                    );
                                    D('Common/OrderWave')->add($data);
                                    D('Order/Order')->where(array('id_order' => $order_id))->save(array('id_order_status' => OrderStatus::PICKING)); //订单状态改为配货中
                                    $this->add_wave_shipping(isset($track_numbers) ? $track_numbers[$key] : null, $order_id, $shipping_id);
                                }
                            }
                            if(!empty($id_num)){
                                $status = 0;
                                $message = '订单号：'.implode("\r\n",$id_num).$msg;
                            } else {
                                $status = 1;
                                $message = '生成成功';
                            }
                        } else {
                                foreach ($order_list as $val) {
                                $data = array(
                                    'wave_number' => $number,
                                    'id_order' => $val['id_order'],
                                    'status' => 0,
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'id_department' => $val['id_department']
                                );
                                D('Common/OrderWave')->add($data);
                                D('Order/Order')->where(array('id_order' => $val['id_order']))->save(array('id_order_status' => OrderStatus::PICKING));
                                D("Order/OrderRecord")->addHistory($val['id_order'], 5, 4, '生成波次单，把订单状态改为配货中');
                            }
                            $status = 1;
                            $message = '生成成功';
                        }
                    } catch (\Exception $e) {
                        $status = 0;
                        $message = $e->getMessage();
                    }
                } else {
                    $status = 0;
                    $message = '请选择相同地区的订单进行生成';
                }
            } else {
                $status = 0;
                $message = '请选择相同部门的订单进行生成';
            }
        }else{
            $status = 0;
            $message = '没有可生成的订单';
        }

        add_system_record($_SESSION['ADMIN_ID'], 1, 3, '仓库未配货列表页面生成波次单');
        if($status == 0){
            $this->error($message);
        }else{
            $this->success($message);
        }
    }

    //匹配物流
    public function match_shipping() {
        if (IS_AJAX) {
            try {
                $shipping_id = I('post.shipping_id');
                $number = I('post.number');
                $attr_id = I('post.attr_id');
                $wave = M('OrderWave')->field('*')->where(array('wave_number' => $number, 'status' => 0))->select();
                $shipping = M('Shipping')->where(array('id_shipping' => $shipping_id))->find();
                $shipping_name = $shipping['title'];
                if ($shipping['need_track_num']) {
                    if ($shipping['need_shipping_type'] == 1) {
                        $track_numbers = M('ShippingTrack')->field('id_shipping,id_shipping_track,track_number')->where(array('id_shipping' => $shipping_id, 'track_status' => 0, 'type' => $attr_id))->select();
                    } else {
                        $track_numbers = M('ShippingTrack')->field('id_shipping,id_shipping_track,track_number')->where(array('id_shipping' => $shipping_id, 'track_status' => 0))->select();
                    }

                    if (empty($track_numbers) || count($wave) > count($track_numbers)) {
                        echo json_encode(array('status' => 0, 'message' => '该物流可用运单号不足'));exit;
                    }
                }

                $order_res = array();
                foreach ($wave as $k=>$val) {
                    if($shipping_id == 33 || $shipping_id == 39) {
                        $order = M('Order')->field('id_department,id_increment, city, area, address')->where(array('id_order' => $val['id_order']))->find();
                        $order_res[] = array(
                            'order_id'=>$order['id_increment'],
                            'address'=>trim($order['city'] . $order['area'] . $order['address'])
                        );
                    }
                }
                if(!empty($order_res)) $res = $this->check_deliver_region($order_res);
                $id_num = array();
                $flag = false;
                if($shipping_id == 33 || $shipping_id == 39) {
                    foreach ($res['Data'] as $key => $val) {
                        $order_id = $wave[$key]['id_order'];
                        if ($val['Status'] == 1) {
                            if (empty($wave[$key]['id_shipping']) && empty($wave[$key]['track_number_id'])) {
                                $track_number = isset($track_numbers) ? array_pop($track_numbers) : null;
                                $flag = true;
                                $data = array(
                                    'id' => $wave[$key]['id'],
                                    'id_shipping' => $shipping_id,
                                    'track_number_id' => !empty($track_number) ? $track_number['id_shipping_track'] : null,
                                    'status' => 2,
                                    'update_at' => date('Y-m-d H:i:s'),
                                    'attr_id' => $attr_id
                                );
                                D('Common/OrderWave')->save($data);

                                D('Common/ShippingTrack')->where(array('id_shipping_track' => $track_number['id_shipping_track']))->save(array('track_status' => 1));
                                D('Order/Order')->where(array('id_order' => $order_id))->save(array('id_shipping' => $shipping_id));
                                //匹配物流信息，物流信息同时也要加入出库表
                                D('Order/Orderout')->where(array('id_order' => $order_id))->save(array('id_shipping' => $shipping_id));
                                $order = D('Order/Order')->field('id_order, id_increment, id_shipping, date_delivery, id_order_status')->where(array('id_order' => $order_id))->find();
                                $shipping_info = D('Order/OrderShipping')->field('id_order_shipping, track_number')->where(array('id_order' => $order['id_order']))->select();
                                $updated = false;
                                if($shipping_info){
                                    $updated = true;
                                    foreach ($shipping_info as $ship) {
                                        //更新一个后退出
                                        D('Order/OrderShipping')->save(array(
                                            'id_order_shipping' => $ship['id_order_shipping'],
                                            'track_number' => !empty($track_number) ? $track_number['track_number'] : null,
                                            'updated_at' => date('Y-m-d H:i:s'),
                                            'id_shipping' => $shipping_id,
                                        ));
                                        D('Order/Order')->save(array(
                                            'id_order' => $order['id_order'],
                                            'id_shipping' => $shipping_id
                                        ));
                                        //加入出库表
                                        D('Order/Orderout')->save(array(
                                            'id_order' => $order['id_order'],
                                            'id_shipping' => $shipping_id
                                        ));
                                        break;
                                    }
                                }

                                if ($updated === false) {
                                    //新的运单号
                                    D('Order/OrderShipping')
                                        ->add(array(
                                            'id_order' => $order['id_order'],
                                            'id_shipping' => $shipping_id,
                                            'shipping_name' => $shipping_name, //TODO: 加入物流名称
                                            'track_number' => !empty($track_number) ? $track_number['track_number'] : null,
                                            'fetch_count' => 0,
                                            'is_email' => 0,
                                            'status_label' => '',
                                            'date_delivery' => $order['date_delivery'],
                                            'created_at' => date('Y-m-d H:i:s'),
                                            'updated_at' => date('Y-m-d H:i:s'),
                                        ));
                                    D('Order/Order')->save(array(
                                        'id_order' => $order['id_order'],
                                        'id_shipping' => $shipping_id
                                    ));
                                    //加入出库表
                                    D('Order/Orderout')->save(array(
                                        'id_order' => $order['id_order'],
                                        'id_shipping' => $shipping_id
                                    ));
                                }

                            }
                        } else {
                            $flag = false;
                            $id_num[] = $val['order_id'];
                            $msg = '不在配送区域，无法匹配物流';
                            continue;
                        }
                    }
                } else {
                    foreach ($wave as $k => $v) {
                        if (empty($v['id_shipping']) && empty($v['track_number_id'])) {
                            $track_number = isset($track_numbers) ? array_pop($track_numbers) : null;
                            $flag = true;
                            $data = array(
                                'id' => $v['id'],
                                'id_shipping' => $shipping_id,
                                'track_number_id' => !empty($track_number) ? $track_number['id_shipping_track'] : null,
                                'status' => 2,
                                'update_at' => date('Y-m-d H:i:s'),
                                'attr_id' => $attr_id
                            );
                            D('Common/OrderWave')->save($data);

                            D('Common/ShippingTrack')->where(array('id_shipping_track' => $track_number['id_shipping_track']))->save(array('track_status' => 1));
                            D('Order/Order')->where(array('id_order' => $v['id_order']))->save(array('id_shipping' => $shipping_id));
                            //匹配物流信息，物流信息同时也要加入出库表
                            D('Order/Orderout')->where(array('id_order' => $v['id_order']))->save(array('id_shipping' => $shipping_id));
                            $order = D('Order/Order')->field('id_order, id_increment, id_shipping, date_delivery, id_order_status')->where(array('id_order' => $v['id_order']))->find();
                            $shipping_info = D('Order/OrderShipping')->field('id_order_shipping, track_number')->where(array('id_order' => $order['id_order']))->select();
                            $updated = false;
                            if($shipping_info){
                                $updated = true;
                                foreach ($shipping_info as $ship) {
                                    //更新一个后退出
                                    D('Order/OrderShipping')->save(array(
                                        'id_order_shipping' => $ship['id_order_shipping'],
                                        'track_number' => !empty($track_number) ? $track_number['track_number'] : null,
                                        'updated_at' => date('Y-m-d H:i:s'),
                                        'id_shipping' => $shipping_id,
                                    ));
                                    D('Order/Order')->save(array(
                                        'id_order' => $order['id_order'],
                                        'id_shipping' => $shipping_id
                                    ));
                                    //加入出库表
                                    D('Order/Orderout')->save(array(
                                        'id_order' => $order['id_order'],
                                        'id_shipping' => $shipping_id
                                    ));
                                    break;
                                }
                            }
                            if ($updated === false) {
                                //新的运单号
                                D('Order/OrderShipping')
                                    ->add(array(
                                        'id_order' => $order['id_order'],
                                        'id_shipping' => $shipping_id,
                                        'shipping_name' => $shipping_name, //TODO: 加入物流名称
                                        'track_number' => !empty($track_number) ? $track_number['track_number'] : null,
                                        'fetch_count' => 0,
                                        'is_email' => 0,
                                        'status_label' => '',
                                        'date_delivery' => $order['date_delivery'],
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'updated_at' => date('Y-m-d H:i:s'),
                                    ));
                                D('Order/Order')->save(array(
                                    'id_order' => $order['id_order'],
                                    'id_shipping' => $shipping_id
                                ));
                                //加入出库表
                                D('Order/Orderout')->save(array(
                                    'id_order' => $order['id_order'],
                                    'id_shipping' => $shipping_id
                                ));
                            }
                        }
                    }
                }

                if ($flag == false) {
                    $status = 0;
                    $message = !empty($id_num)?'订单号：'.implode("\r\n",$id_num).$msg:'该波次单已匹配运单号！';
//                    $message = '该波次单已匹配运单号！';
                } else {
                    $status = 1;
                    $message = '匹配成功';
                }
            }
            catch
                (\Exception $e) {
                    $status = 0;
                    $message = $e->getMessage();
                }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '仓库匹配运单号');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);
            exit();
        }
    }

    //生成波次单
    public function generate() {
        if (IS_AJAX) {
            try {
                $orderIds = is_array($_POST['order_id']) ? $_POST['order_id'] : array($_POST['order_id']);
                $order_arr = is_array($_POST['order_arr']) ? $_POST['order_arr'] : array($_POST['order_arr']);
                $zone_arr = is_array($_POST['zone_arr']) ? $_POST['zone_arr'] : array($_POST['zone_arr']);
                if(isset($_POST['shipping_id'])) {
                    $shipping_id = $_POST['shipping_id'];//匹配的物流
                    $swhere['id_shipping'] = $shipping_id;
                }
                if(isset($_POST['attr_id'])) {
                    $swhere['type'] = $_POST['attr_id'];
                    $attr_id = $_POST['attr_id'];
                }
                $swhere['track_status'] = 0;
                $number = date('Ymds') .$order_arr[0]. rand(0, 9999);
                if (count(array_unique($order_arr)) == 1) {
                    if(count(array_unique($zone_arr)) == 1) {
                        if ($orderIds && is_array($orderIds)) {
                            if($shipping_id) {
                                //判断是否需要运单号
                                $shipping = M('Shipping')->where(array('id_shipping' => $shipping_id))->find();
                                if($shipping['need_track_num']) {
                                    if ($shipping['need_shipping_type'] == 1) {
                                        $track_numbers = M('ShippingTrack')->field('id_shipping,id_shipping_track,track_number')->where(array('id_shipping' => $shipping_id, 'track_status' => 0, 'type' => $attr_id))->select();
                                    } else {
                                        $track_numbers = M('ShippingTrack')->field('id_shipping,id_shipping_track,track_number')->where(array('id_shipping' => $shipping_id, 'track_status' => 0))->select();
                                    }

                                    if (empty($track_numbers) || count($orderIds) > count($track_numbers)) {
                                        echo json_encode(array('status'=>0, 'message'=>'该物流可用运单号不足'));exit;
                                    }
                                }

                                $order_res = array();
                                foreach ($orderIds as $k=>$val) {
                                    if($shipping_id == 33 || $shipping_id == 39) {
                                        $order = M('Order')->field('id_department,id_increment, city, area, address')->where(array('id_order' => $val))->find();
                                        $order_res[] = array(
                                            'order_id'=>$order['id_increment'],
                                            'address'=>trim($order['city'] . $order['area'] . $order['address'])
                                        );
                                    }
                                }
                                if(!empty($order_res)) $res = $this->check_deliver_region($order_res);
                                if($shipping_id == 33 || $shipping_id == 39) {
                                    $id_num = array();
                                    foreach ($res['Data'] as $key => $val) {
                                        $order_id = $orderIds[$key];
                                        if ($val['Status'] == 1) {
                                            $id_department = M('Order')->where(array('id_order' => $order_id))->getField('id_department');
                                            $data = array(
                                                'wave_number' => $number,
                                                'id_order' => $order_id,
                                                'created_at' => date('Y-m-d H:i:s'),
                                                'id_department' => $id_department,
                                                'id_shipping' => $shipping_id,
                                                'track_number_id' => isset($track_numbers) ? $track_numbers[$key]['id_shipping_track'] : null,
                                                'status' => 2,
                                                'update_at' => date('Y-m-d H:i:s'),
                                                'attr_id' => $attr_id
                                            );
                                            D('Common/OrderWave')->add($data);
                                            D('Order/Order')->where(array('id_order' => $order_id))->save(array('id_order_status' => OrderStatus::PICKING)); //订单状态改为配货中
                                            $this->add_wave_shipping(isset($track_numbers) ? $track_numbers[$key] : null, $order_id, $shipping_id);
                                        } else {
                                            $id_num[] = $val['order_id'];
                                            $msg = '不在配送区域，无法匹配物流';
                                            continue;
                                        }
                                    }
                                } else {
                                    foreach ($orderIds as $key => $val) {
                                        $order_id = $val;
                                        $id_department = M('Order')->where(array('id_order' => $order_id))->getField('id_department');
                                        $data = array(
                                            'wave_number' => $number,
                                            'id_order' => $order_id,
                                            'created_at' => date('Y-m-d H:i:s'),
                                            'id_department' => $id_department,
                                            'id_shipping' => $shipping_id,
                                            'track_number_id' => isset($track_numbers) ? $track_numbers[$key]['id_shipping_track'] : null,
                                            'status' => 2,  //波次单状态:1=完成 2=配货中
                                            'update_at' => date('Y-m-d H:i:s'),
                                            'attr_id' => $attr_id
                                        );
                                        D('Common/OrderWave')->add($data);
                                        D('Order/Order')->where(array('id_order' => $order_id))->save(array('id_order_status' => OrderStatus::PICKING)); //订单状态改为配货中
                                        $this->add_wave_shipping(isset($track_numbers) ? $track_numbers[$key] : null, $order_id, $shipping_id);
                                    }
                                }
                                if(!empty($id_num)){
                                    $status = 0;
                                    $message = '订单号：'.implode("\r\n",$id_num).$msg;
                                } else {
                                    $status = 1;
                                    $message = '生成成功';
                                }
                            } else {
                                foreach ($orderIds as $val) {
                                    $order = M('Order')->field('id_department')->where(array('id_order' => $val))->find();
                                    $data = array(
                                        'wave_number' => $number,
                                        'id_order' => $val,
                                        'status' => 0,
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'id_department' => $order['id_department']
                                    );
                                    D('Common/OrderWave')->add($data);
                                    D('Order/Order')->where(array('id_order' => $val))->save(array('id_order_status' => OrderStatus::PICKING)); //订单状态改为配货中
                                    D("Order/OrderRecord")->addHistory($val, 5, 4, '生成波次单，把订单状态改为配货中');
                                }
                                $status = 1;
                                $message = '生成成功';
                            }
                        }
                    } else {
                        $status = 0;
                        $message = '请选择相同地区的订单进行生成';
                    }
                } else {
                    $status = 0;
                    $message = '请选择相同部门的订单进行生成';
                }
            } catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 1, 3, '仓库未配货列表生成波次单');
            $return = array('status' => $status, 'message' => $message, 'num'=>$number);
            echo json_encode($return);
            exit();
        }
    }

    //移除
    public function removed() {
        if (IS_AJAX) {
            $id = I('post.id');
            $id_order = I('post.id_order');
            if ($id) {
                $wave = M('OrderWave')->field('track_number_id')->where(array('id' => $id))->find();
                $result = D('Common/OrderWave')->delete($id);
                if ($result) {
                    $flag = 1;
                    $msg = '移除成功';
                    if ($wave['track_number_id']) {
                        $shipping_track = M('ShippingTrack')->field('id_shipping,track_number')->where(array('id_shipping_track' => $wave['track_number_id']))->find();
//                        D('Common/ShippingTrack')->where(array('id_shipping_track' => $wave['track_number_id']))->save(array('track_status' => 0));
                        D('Order/OrderShipping')->where(array('id_order' => $id_order,'id_shipping'=>$shipping_track['id_shipping'],'track_number'=>$shipping_track['track_number']))->delete();
                    }
                    $order_data = D("Order/Order")->where(array('id_order' => $id_order))->find(); //获取订单信息
                    if (in_array($order_data['id_order_status'], OrderStatus::get_canceled_to_rollback_status())) //如果订单状态是已打包，配送中，已配送状态进行加在单，加库存
                    {
                        UpdateStatusModel::wave_delete_rollback_stock($id_order);
                    }
                    D('Order/Order')->where(array('id_order' => $id_order))->save(array('id_order_status' => 4,'id_shipping'=>0,'date_delivery'=>null));
                    D("Order/OrderRecord")->addHistory($id_order, 4, 4, '移除波次单里的订单，把订单状态改为未配货');
                } else {
                    $flag = 0;
                    $msg = '移除失败';
                }
                add_system_record($_SESSION['ADMIN_ID'], 3, 3, '仓库移除波次单里的订单');
                echo json_encode(array('flag' => $flag, 'msg' => $msg));
            }
        }
    }

    //批量移除
    public function batch_removed() {
        if(IS_AJAX) {
            try {
                //获取能进行库存回滚的订单状态
                $rollback_stock_order_status = OrderStatus::get_canceled_to_rollback_status();
                $orderIds = is_array($_POST['id_order']) ? $_POST['id_order'] : array($_POST['id_order']);
                $waveIds = is_array($_POST['id_wave']) ? $_POST['id_wave'] : array($_POST['id_wave']);
                if ($orderIds && $waveIds && is_array($orderIds))
                {
                    foreach ($orderIds as $key=>$val)
                    {
                        $wave = M('OrderWave')->field('track_number_id')->where(array('id' => $waveIds[$key]))->find();
                        $result = D('Common/OrderWave')->delete($waveIds[$key]);
                        if ($result)
                        {
                            //获取订单状态
                            $order_data = D('Order/Order')->where(array('id_order' => $val))->find();
                            if (in_array($order_data['id_order_status'],$rollback_stock_order_status))
                            {
                                UpdateStatusModel::wave_delete_rollback_stock($val);
                            }
                            if ($wave['track_number_id'])
                            {
                                $shipping_track = M('ShippingTrack')->field('id_shipping,track_number')->where(array('id_shipping_track' => $wave['track_number_id']))->find();
                                D('Order/OrderShipping')->where(array('id_order' => $val,'id_shipping'=>$shipping_track['id_shipping'],'track_number'=>$shipping_track['track_number']))->delete();
                            }
                            D('Order/Order')->where(array('id_order' => $val))->save(array('id_order_status' => 4,'id_shipping'=>0,'date_delivery'=>null));
                            D("Order/OrderRecord")->addHistory($val, 4, 4, '移除波次单里的订单，把订单状态改为未配货');
                        }
                    }
                    $status = 1;
                    $message = '移除成功';
                }
            }
            catch (\Exception $e)
            {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '仓库移除波次单里的订单');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }
    
    //删除波次单
    public function delete_wave() {
        if(IS_AJAX) {
            $wave_num = I('post.wave_num/i');
            $wave = M('OrderWave')->where(array('wave_number'=>$wave_num))->select();
            try{
                if(!empty($wave))
                {
                    $rollback_stock_order_status = OrderStatus::get_canceled_to_rollback_status();
                    foreach ($wave as $key=>$val)
                    {
                        //获取订单状态
                        $order_data = D('Order/Order')->where(array('id_order' => $val['id_order']))->find();
                        if (in_array($order_data['id_order_status'],$rollback_stock_order_status))
                        {
                            UpdateStatusModel::wave_delete_rollback_stock($val['id_order']);
                        }

                        if ($val['track_number_id'])
                        {
                            $shipping_track = M('ShippingTrack')->field('id_shipping,track_number')->where(array('id_shipping_track' => $val['track_number_id']))->find();
                            D('Order/OrderShipping')->where(array('id_order' => $val['id_order'],'id_shipping'=>$shipping_track['id_shipping'],'track_number'=>$shipping_track['track_number']))->delete();
                        }
                        D('Order/Order')->where(array('id_order' => $val['id_order']))->save(array('id_order_status' => 4,'id_shipping'=>0,'date_delivery'=>null));
                        D("Order/OrderRecord")->addHistory($val['id_order'], 4, 4, '移除波次单里的订单，把订单状态改为未配货');
                    }
                    D('Common/OrderWave')->where(array('wave_number'=>$wave_num))->delete();
                    $status = 1;
                    $message = '删除成功';
                }
                else
                {
                    throw new Exception("没有找到波次单");
                }
            }
            catch(\Exception $e)
            {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '仓库移除波次单');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    //打印次数
    public function print_number() {
        if (IS_AJAX) {
            $number = I('post.num');
            if ($number) {
                $wave = M('OrderWavePrint')->where(array('wave_number' => $number))->find();
                if (empty($wave)) {
                    $data = array(
                        'wave_number' => $number,
                        'print_picking_num' => 1,
                    );
                    D('Common/OrderWavePrint')->add($data);
                    $status = 1;
                } else {
                    $data = array(
                        'print_picking_num' => $wave['print_picking_num'] + 1,
                    );
                    D('Common/OrderWavePrint')->where(array('id' => $wave['id']))->save($data);
                    $status = 1;
                }
            } else {
                $status = 0;
            }
            add_system_record($_SESSION['ADMIN_ID'], 6, 3, '仓库打印配货单');
            echo json_encode(array('status' => $status));
            exit();
        }
    }

    //获取运单模板
    public function get_waybill() {
        if (IS_AJAX) {
            $shipping_id = I('post.shipping_id');
            $waybill = M('WaybillTemplate')->field('id,title')->where(array('id_shipping' => $shipping_id))->select();
            $waybill = array_column($waybill, 'title', 'id');
            $html = '<option value="0">请选择</option>';
            if ($waybill) {
                foreach ($waybill as $k => $v) {
                    $html .= '<option value="' . $k . '">' . $v . '</option>';
                }
            }
            echo $html;
        }
    }
    
    //导出波次单里的订单
    public function export_order_list()
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();

        //默认结束时间是当天
        //如果不默认的话会把当天以后发货日期的订单也导出来
        $M = new \Think\Model;
        $where = array();
        $wave_num = I('get.num');
//        $id_order = M('OrderWave')->field('id_order')->where(array('wave_number'=>$wave_num))->select();
//        $id_order = array_column($id_order, 'id_order');
//        $where['o.id_order'] = array('IN',$id_order);
////        $where['o.price_total'] = array('GT', 0);
//
//        /* @var $ordModel \Common\Model\OrderModel */
//        $ordModel = D("Order/Order");
//        /* @var $orderItem \Common\Model\OrderItemModel */
//        $orderItem = D('Order/OrderItem');
//        $shiTable = D('Common/Shipping')->getTableName();
//        $product_name = D('Product/Product')->getTableName();
//
//        $field = 'o.*,oi.id_product,oi.id_product_sku,oi.sku,oi.sku_title,oi.sale_title,oi.quantity';//,oi.product_title
//        $field .= ',s.title as shipping_name,s.channels,s.account,p.inner_name as product_title';
//        $select_all = $ordModel->alias('o')->field($field)
//            ->join($orderItem->getTableName().' AS oi ON (o.id_order = oi.id_order)')
//            ->join($product_name.' p ON (oi.id_product=p.id_product)', 'LEFT')
//            ->join($shiTable.' s ON (o.id_shipping=s.id_shipping)', 'LEFT')
//            ->where($where)->order('oi.id_product desc,oi.id_product_sku desc')->limit(5000)->select();

        //$shipping_model = D("Shipping/Shipping");
        //$all_shipping   = $shipping_model->all();
        $order_tab = M('Order')->getTableName();
        $ship_track_tab = M('ShippingTrack')->getTableName();
        $order_item_tab = M('OrderItem')->getTableName();
        $departmentList = D('Common/Department')->where('type=1')->getField("id_department,department_code");
        $select_all = D('OrderWave')->alias('ow')->field('o.*,st.track_number,ow.area_code,ow.zipcode,o.zipcode as order_zipcode,ow.station,ow.other_content,COUNT(oi.id_order) AS oi_count')
            ->join($order_tab.' as o ON o.id_order=ow.id_order','left')
            ->join($order_item_tab.' as oi ON o.id_order=oi.id_order','left')
            ->join($ship_track_tab.' as st ON ow.track_number_id=st.id_shipping_track','left')
            ->where(array('ow.wave_number'=>$wave_num))->group('oi.id_order')
            ->order('oi.id_product ASC,oi_count ASC,oi.sku DESC,oi.quantity DESC')->select();
        /** @var \Order\Model\OrderItemModel $ord_item_model */
        $ord_item_model = D('Order/OrderItem');

        
        $order_list = array();$i=0;$temp_product = array();
        foreach($select_all as $item){
            $order_id = $item['id_order'];
            $order_list[$order_id] = $item;
            $products = $ord_item_model->get_item_list($item['id_order']);
            if($products){
                foreach($products as $pro_key=>$product){
                    $temp_product[$order_id][] = array(
                        'id_product'=>$product['id_product'],
                        'id_product_sku'=>$product['id_product_sku'],
                        'sku'=>$product['sku'],
                        'sku_title'=>$product['sku_title'],
                        'sale_title'=>$product['sale_title'],
                        'product_title'=>$product['product_title'],
                        'product_inner_title'=>$product['inner_name'],
                        'quantity'=>$product['quantity'],
                        'foreign_title'=>$product['attrs_title']
                    );
                }
            }
        }

        $columns = array(
            '部门','地区', '物流', '订单号', '运单号', '姓名', '电话号码', '外部产品名',
            '内部产品名', '外文产品名','属性','SKU', '总价（NTS）', '产品数量',
            '送货地址', '留言备注', '下单时间', '订单状态',
            '发货日期','后台备注', '付款方式', '付款状态','邮编','仓库'
        );
        $j = 65;$col_number = 1;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        if($order_list){
            $all_domain= D('Common/Domain')->field('`name`,id_domain')->order('`name` ASC')->cache(true, 3600)->select();
            $all_domain = $all_domain?array_column($all_domain,'name','id_domain'):'';
            $all_zone = D('Common/Zone')->field('`title`,id_zone')->order('`title` ASC')->cache(true, 3600)->select();
            $all_zone = $all_zone?array_column($all_zone,'title','id_zone'):'';
            /** @var \Order\Model\OrderStatusModel $status_model */
            $status_model = D("Order/OrderStatus");
            $all_status = $status_model->get_status_label();

            foreach($order_list as $o){
                $col_number++;
                $id_order = $o['id_order'];
                $products = $temp_product[$id_order];
                $total_qty = 0;
                $product_title = array();
                $product_inner_name = array();
                $foreigns_title = array();
                $attr_value = array();
                $sku = array();
                $qty = 0;
                $temp_array = [];
                foreach($products as $product){
                    $attr_title = unserialize($product['foreign_title']);
                    if($product['sku_title']){
                        $attrs_title = !empty($attr_title)?implode('-',$attr_title):$product['sku_title'];
                        $product_title[] = $product['product_title'].' + '.$product['sku_title'].' x '.$product['quantity'];
                        if(!in_array($product['product_inner_title'],$temp_array)){
                            $product_inner_name[$product['product_inner_title']] =  $product['product_inner_title'].' , '.$product['sku_title'].' x '.$product['quantity'].";";
                            $temp_array[] = $product['product_inner_title'];
                        }else{
                            $product_inner_name[$product['product_inner_title']].= $product['sku_title'].' x '.$product['quantity'].";";
                        }
                        $foreigns_title[] = $product['sale_title'].' + '.$attrs_title.' x '.$product['quantity'];
                    }else{
                        $product_title[] =  $product['product_title'].' x '.$product['quantity'];
                        $product_inner_name[$product['product_inner_title']] =  $product['product_inner_title'].' x '.$product['quantity'];
                        $foreigns_title[] = $product['sale_title'].' x '.$product['quantity'];
                    }
                    $total_qty +=$product['quantity'];
                    $sku[] =  $product['sku'];
                }
                $getShipObj = D("Order/OrderShipping")->field('track_number,status_label,shipping_name')//
                            ->where(array('id_order'=>$o['id_order']))->select();
                $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
                $shipping_name = D('Common/Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
                $product_wtitle = $product_title&& is_array($product_title)?implode(' ; ', $product_title):'';
                $product_innertitle = $product_inner_name&& is_array($product_inner_name)?implode(' ; ', $product_inner_name):'';
                $foreign_name = $foreigns_title&& is_array($foreigns_title)?implode(' ; ', $foreigns_title):'';
                $sku  = $sku?implode(' ; ', $sku):'';
                $user_name = $o['first_name'].' '.$o['last_name'];
                $payment_method = $o['payment_method']?:'货到付款';
                $payment_status = $o['payment_status']?:'未付款';
                $payment_id = trim($o['payment_id']);

                if($o['id_shipping'] == 33 || $o['id_shipping'] == 39){
                    $zipcode = $o['zipcode'];
                }else{
                    $zipcode = $o['order_zipcode'];
                }
                if ($payment_id) {
                    //TODO: 只要是信用卡支付, 然后客服从通道那里确认后把订单状态改成"未配货"认为已经付款完成
                    $payment_method = '信用卡支付';
                    $payment_status = '已付款';
                }
//                $product_title_attr = trim($attr_value)?$product_title.'   '.$attr_value:$product_title;
                //台湾地区的地址不需要加上省份,但是其他的地区需要带上
                if ($o['id_zone'] == 2) {
                    $address = trim($o['address']);
                } else if($o['id_zone'] == 22){
                    $address = trim(sprintf('%s %s %s %s', $o['address'], $o['area'], $o['city'], $o['province']));
                } else {
                    $address = trim(sprintf('%s %s %s %s', $o['province'], $o['city'], $o['area'], $o['address']));
                }
                $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
                $warehouse = array_column($warehouse,'title','id_warehouse');
                $data = array(
                    $departmentList[$o['id_department']],$all_zone[$o['id_zone']],$shipping_name,
                    $o['id_increment'],$trackNumber,$user_name ,$o['tel'], $product_wtitle,$product_innertitle, $foreign_name,'', $sku,
                    $o['price_total'],$total_qty, $address, $o['remark'],$o['created_at'],
                    $all_status[$o['id_order_status']], $o['date_delivery'], $o['comment'],
                    $payment_method, $payment_status,$zipcode,$warehouse[$o['id_warehouse']]
                );
                $j = 65;
                foreach ($data as $key=>$col) {
                    if($key != 11 && $key != 12){
                        $excel->getActiveSheet()->setCellValueExplicit(chr($j).$col_number, $col);
                    }else{
                        $excel->getActiveSheet()->setCellValue(chr($j) . $col_number, $col);
                    }
                    //$excel->getActiveSheet()->getStyle(chr($j).$col_number)->getNumberFormat()->setFormatCode('@');
                    ++$j;
                }
                D('Order/OrderRecord')->addHistory(!empty($o['id_order'])?$o['id_order']:0,!empty($o['id_order_status'])?$o['id_order_status']:0,1,'仓库导出波次单订单');
            }
            add_system_record($_SESSION['ADMIN_ID'], 7, 1, '仓库导出波次单订单');
        }else{
            $this->error("没有数据");
        }

        $excel->getActiveSheet()->setTitle(date('Y-m-d').'波次单订单信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d').'波次单订单信息表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    /**
     * 检查嘉里订单是否在配送区域
     * @param $order_id 订单号
     * @param $address 地址
     * @return mixed|string
     */
    public function check_deliver_region($order_res) {
        $config = C('KERRYTJ_CHECK_AREA_API_CONFIG');
        $url = $config['REQUEST_URL'];//http://api.kerrytj.com/ked/api/ExistUnassignedArea.ashx;
        $key = $config['KEY'];//51fcc8539158487db4f4d374ae6f76cf
        $set_data = array(
            'Key' => $key,
            'Data' => $order_res
        );
        $result = send_curl_request($url,json_encode($set_data));
        write_file('warehouse','check_deliver',json_encode($set_data)."\r\n".$result);
        return $result?json_decode($result,true):'';
    }
    
    /**
     * 根据波次单修复订单的发货时间
     */
    public function update_order_date() {
        try {
            $wave = M('OrderWave')->field('id_order,update_at')->select();//波次单
            foreach ($wave as $key=>$val) {
                $order = M('Order')->field('id_order,date_delivery')->where(array('id_order'=>$val['id_order']))->find();
                if($order) {
                    if(empty($order['date_delivery']) && !empty($val['update_at'])) {
                        D('Order/Order')->where(array('id_order'=>$val['id_order']))->save(array('date_delivery'=>$val['update_at']));                        
                    }
                }
            }
            $status = 1;
            $message = 'success';
        } catch (\Exception $e) {
            $status = 0;
            $message = $e->getMessage();
        }
        $return = array('status' => $status, 'message' => $message);
        echo json_encode($return);exit();
    }
    
    /**
     * 修改订单状态
     */
    public function update_order_status() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        $order_status = M('OrderStatus')->field('id_order_status,title')->select();
        $order_status = array_column($order_status, 'title','id_order_status');
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'track_number_update', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $id_increment = $row[0];//订单号
                $order = M('Order')->where(array('id_increment'=>$id_increment))->find();
                $order_shipping = M('OrderShipping')->where(array('track_number'=>$id_increment))->find();
                if($order) {
                    D('Order/Order')->where(array('id_increment'=>$id_increment))->save(array('id_order_status'=>$_POST['status_id']));
                    $info['success'][] = sprintf('第%s行: 订单号%s 修改状态 %s 成功', $count++,$id_increment,$order_status[$_POST['status_id']]);
                } elseif($order_shipping) {
                    $id_order = $order_shipping['id_order'];
                    D('Order/Order')->where(array('id_order'=>$id_order))->save(array('id_order_status'=>$_POST['status_id']));
                    $info['success'][] = sprintf('第%s行: 运单号%s 修改状态 %s 成功', $count++,$id_increment,$order_status[$_POST['status_id']]);
                } else {
                    $info['error'][] = sprintf('第%s行: 找不到订单', $count++);
                }
            }
        }       
        
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->assign('order_status',$order_status);
        $this->display();
    }
    
    /**
     * 获取嘉里物流产品属性
     */
    public function get_shipping_attr() {
        if(IS_AJAX) {
            $shipping_id = I('request.shipping_id');
            $need_shipping_type = M('Shipping')->where(array('id_shipping'=>$shipping_id))->getField('need_shipping_type');
            if($need_shipping_type == 1){
                $html = '';
                $html .= "订单产品属性:";
                $html .= "<select class='selectAttr' name='attr_id' style='width:120px;margin-bottom: 0' >";
                $html.= "<option value='1'>特货</option>";
                $html.= "<option value='2'>普货</option>";
                $html .= '</select>';
                echo $html;
            }else{
                echo '';
            }
        }
    }
    
    /**
     * 匹配物流，生成订单物流信息，并添加订单记录
     */
    public function add_wave_shipping($shipping_track, $id_order, $id_shipping) {
        if(!empty($shipping_track)){
            D('Common/ShippingTrack')->where(array('id_shipping_track' => $shipping_track['id_shipping_track']))->save(array('track_status' => 1));
        }
//        D('Order/Order')->where(array('id_order' => $id_order))->save(array('id_shipping' => $id_shipping, 'date_delivery' => date('Y-m-d H:i:s')));
        D('Order/Order')->where(array('id_order' => $id_order))->save(array('id_order_status' => 5,'id_shipping' => $id_shipping));
        D("Order/OrderRecord")->addHistory($id_order, 5, 4, '生成波次单，把订单状态改为配货中');
        $shipping_name = M('Shipping')->where(array('id_shipping' => $id_shipping))->find();
        $order = D('Order/Order')->field('id_order, id_increment, id_shipping, date_delivery, id_order_status')->where(array('id_order' => $id_order))->find();
        $shipping_info = D('Order/OrderShipping')->field('id_order_shipping, track_number')->where(array('id_order' => $order['id_order']))->select();
        $updated = false;
        foreach ($shipping_info as $ship) {
            //更新一个后退出
            D('Order/OrderShipping')->save(array(
                'id_order_shipping' => $ship['id_order_shipping'],
                'track_number' => !empty($shipping_track) ? $shipping_track['track_number'] : null,
                'updated_at' => date('Y-m-d H:i:s'),
                'id_shipping' => $id_shipping,
            ));
            $updated = true;
            D('Order/Order')->save(array(
                'id_order' => $order['id_order'],
                'id_shipping' => $id_shipping
            ));
            break;
        }
        if (!$updated) {
            //新的运单号
            D('Order/OrderShipping')
                    ->add(array(
                        'id_order' => $order['id_order'],
                        'id_shipping' => $id_shipping,
                        'shipping_name' => $shipping_name['title'], //TODO: 加入物流名称
                        'track_number' => !empty($shipping_track) ? $shipping_track['track_number'] : null,
                        'fetch_count' => 0,
                        'is_email' => 0,
                        'status_label' => '',
                        'date_delivery' => $order['date_delivery'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
            ));
            D('Order/Order')->save(array(
                'id_order' => $order['id_order'],
                'id_shipping' => $id_shipping
            ));
        }
    }


    public function send_order(){
        $wave_number = I('request.number');
        $shipping_id = I('request.shipping_id');

        $shipping_info = M('Shipping')->where(array('id_shipping' => $shipping_id))->find();
        $shipping_name = $shipping_info['tag'];
        if($shipping_name == 'JL'){  //嘉里运单直接用send_order_jl方法发送
            $this->send_order_jl();
            exit;
        }

        $shipping_api_name = "\\Shipping\\Lib\\".$shipping_name . 'ShippingApi';
        if(!$shipping_name || !class_exists($shipping_api_name)){
            echo json_encode(array('status'=>0,'message'=>'没有选择物流或者不支持发送运单'));exit;
        }
        $shipping_api = new $shipping_api_name();
        $result = $shipping_api->send_order($wave_number);
        if($result && $shipping_info['need_track_num'] == 0){
            //将返回的运单号填充到orderShipping/shippingTrack表中
            foreach($result as $key=>$value){
                $id_shipping_track = M('ShippingTrack')->add(array(
                    'id_shipping' => $shipping_info['id_shipping'],
                    'track_number' => $value,
                    'track_status' => 1,
                    'type' => 2,
                ));
                $id_order = M('Order')->where(array('id_increment'=>$key))->getField('id_order');
                M('OrderShipping')->where(array('id_order'=>$id_order))->save(array(
                    'track_number'=>$value,
                    'has_sent'=>1,  //是否已发送
                ));
                M('OrderWave')->where(array('id_order'=>$id_order))->save(array(
                    'track_number_id'=>$id_shipping_track
                ));
            }
        }elseif($result && $shipping_info['need_track_num'] == 1){
            //根据运单号修改信息
            foreach($result as $key=>$value){
                $id_shipping_track = M('ShippingTrack')->add(array(
                    'id_shipping' => $shipping_info['id_shipping'],
                    'track_number' => $value,
                    'track_status' => 1,
                    'type' => 2,
                ));
                $id_order = M('OrderShipping')->where(array('track_number'=>$value))->getField('id_order');
                M('OrderShipping')->where(array('id_order'=>$id_order))->save(array(
                    'track_number'=>$value,
                    'has_sent'=>1,  //是否已发送
                ));
                M('OrderWave')->where(array('id_order'=>$id_order))->save(array(
                    'track_number_id'=>$id_shipping_track
                ));
           }
        }
        if(empty($shipping_api->get_error())){
            echo json_encode(array('status'=>1,'message'=>'订单('.implode(',', array_keys($result)).')发送成功'));exit;
        }else{
            echo json_encode(array('status'=>0,'message'=>$shipping_api->get_error()));exit;
        }
    }

    /**
     * 发送订单给嘉里
     * */
    public function send_order_jl(){
        $wave_num  = I('post.number');
        $M = new \Think\Model();
        $order_tab = M('Order')->getTableName();
        $order_wave_tab = M('OrderWave')->getTableName();
        $ship_track_tab = M('ShippingTrack')->getTableName();

        $list = $M->table($order_wave_tab . ' as ow')->field('DISTINCT ow.id,ow.wave_number,st.track_number,ow.attr_id,o.*')
            ->join('LEFT JOIN ' . $order_tab . ' as o ON o.id_order=ow.id_order')
            ->join('LEFT JOIN '. $ship_track_tab . ' as st ON ow.track_number_id=st.id_shipping_track')
            ->where(array('ow.wave_number' => $wave_num))
            ->order('ow.id ASC')->select();
        $wave_data = array();
        $order_data = array();
        foreach($list as $key=>$item){
            $is_special = $item['attr_id']==1?1:0;
            $address = trim($item['city'].$item['area'].$item['address']);
            $wave_data[$item['track_number']] = $item['id'];
            $order_data[] = array(
                "BLN" => $item['track_number'],
                "Consignee" => $item['last_name'].$item['first_name'],
                "ConsigneePost" => "",
                "ConsigneeAdd" => $address,
                "ConsigneePhone" => $item['tel'],
                "Piece" => $item['total_qty_ordered'],
                "Volume" => "",
                "Weight" => "",
                "AC" => $item['price_total'],
                "Remark" => $item['remark'],
                "SSNO" => "",
                "ETP" =>  "",
                "CON" =>  "",
                "BNO" =>  ""
            );
        }
        $wave_model = D('Common/OrderWave');
        $message = array();
        if($order_data){
            /** @var \Shipping\Model\ShippingModel $Shipping_model */
            $Shipping_model = D('Shipping/Shipping');
            $result         = $Shipping_model->kerrytj_send_order($order_data,$is_special);
            if($result['Status']==1){
                $get_Data = $result['Data'];
                foreach($get_Data as $key=>$ship_data){
                    $track_number = $ship_data['BLN'];
                    if($wave_data[$track_number]){
                        $update = array(
                            'area_code' => $ship_data['SSID'],
                            'zipcode' => $ship_data['SSNO'],
                            'station' => $ship_data['SSNA'].'>'.$ship_data['ESNA'],
                            'other_content' => json_encode($ship_data),
                        );
                        $wave_model->where(array('id'=>$wave_data[$track_number]))->save($update);
                    }else{
                        $message[] = '没有找到运单号（'.$track_number.')';
                    }
                }
            }else{
                $message[] = $result['Message'];
            }
        }
        $status = $message?0:1;
        $message = $message?implode(',',$message):'';
        echo json_encode(array('status'=>$status,'message'=>$message));
        exit();
    }
}
