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
use SystemRecord\Model\SystemRecordModel;

class ExportController extends AdminbaseController {

    protected $Warehouse, $orderModel;

    public function _initialize() {
        parent::_initialize();
        $this->Warehouse = D("Common/Warehouse");
        $this->orderModel = D("Order/Order");
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
    }
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
        $time_start = I('get.start_time');
        $time_end = I('get.end_time');
        $_GET['start_time'] = $time_start;
        $_GET['end_time'] = $time_end;
        $id_zone = I('get.zone_id');
        $id_department = I('get.department_id');
        $id_warehouse = I('get.warehouse_id');
        $id_shipping = I('get.id_shipping');
        $default_order_status = array(4);
        $id_order_status = I('get.status_id');
        if ($id_order_status > 0) {
            $default_order_status = array((int)$id_order_status);
        }
        $where['id_order_status'] = array('IN', $default_order_status);
        if($time_start || $time_end) {
            $created_at_array = array();
            if ($time_start)
                $created_at_array[] = array('EGT', $time_start);
            if ($time_end)
                $created_at_array[] = array('LT', $time_end);
            $where['o.created_at'] = $created_at_array;
        }

        if ($id_department) {
            $where['o.id_department'] = $id_department;
        }
        if ($id_zone) {
            $where['o.id_zone'] = $id_zone;
        }
        if ($id_warehouse) {
            $where['o.id_warehouse'] = $id_warehouse;
        }
        if ($id_shipping) {
            $where['o.id_shipping'] = $id_shipping;
        }
        if (trim($_GET['keyword'])) {
            $filter = array();
            $keyword = $_GET['keyword'];
            $filter['o.id_increment'] = array('LIKE', '%' . $keyword . '%');
            $filter['o.id_domain'] = array('LIKE', '%' . $keyword . '%');
            $filter['o.first_name'] = array('LIKE', '%' . $keyword . '%');
            $filter['o.tel'] = array('LIKE', '%' . $keyword . '%');
            $filter['o.address'] = array('LIKE', '%' . $keyword . '%');
            $filter['o.email'] = array('LIKE', '%' . $keyword . '%');
            $filter['o.remark'] = array('LIKE', '%' . $keyword . '%');
            $filter['_logic'] = 'or';
            $where['_complex'] = $filter;
        }
        if(trim($_GET['empty_status_label'])) {
            $ordName = D("Order/Order")->getTableName();
            $ordIteName = D("Order/OrderShipping")->getTableName();
            $order_ids = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where(array('oi.sku'=>array('LIKE', '%' . $_GET['sku_keyword'] . '%'),array('o.id_order_status'=>array('IN', $default_order_status))))
                ->group('oi.id_order')->select();
            $order_ids = array_column($order_ids, 'id_order');
            if($order_ids) {
                $where['o.id_order'] = array('IN', $order_ids);
            } else {
                $where['o.id_order'] = array('IN', array(0));
            }
        }

        if(trim($_GET['sku_keyword'])) {
            $ordName = D("Order/Order")->getTableName();
            $ordIteName = D("Order/OrderItem")->getTableName();
            $order_ids = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where(array('oi.sku'=>array('LIKE', '%' . $_GET['sku_keyword'] . '%'),array('o.id_order_status'=>array('IN', $default_order_status))))
                ->group('oi.id_order')->select();
            $order_ids = array_column($order_ids, 'id_order');
            if($order_ids) {
                $where['o.id_order'] = array('IN', $order_ids);
            } else {
                $where['o.id_order'] = array('IN', array(0));
            }
        }

        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        /* @var $orderItem \Common\Model\OrderItemModel */
        $orderItem = D('Order/OrderItem');
        $shiTable = D('Common/Shipping')->getTableName();
        $product_name = D('Product/Product')->getTableName();
        $order_shipping = D('Order/OrderShipping')->getTableName();
        $time_start = I('get.start_time')?I('get.start_time'):'2016-11-01 00:00:00';
        $time_end = I('get.end_time')?I('get.end_time'):'2016-12-16 00:00:00';

        $where = "o.id_order_status=8 and '".$time_start."'<=o.created_at AND o.created_at<'".$time_end."' AND  (os.status_label IS NULL OR os.status_label='')";
        $field = 'o.*,oi.id_product,oi.id_product_sku,oi.sku,oi.sku_title,oi.sale_title,oi.quantity';//,oi.product_title
        $field .= ',s.title as shipping_name,s.channels,s.account,p.inner_name as product_title';
        $select_all = $ordModel->alias('o')->field($field)
            ->join($orderItem->getTableName().' AS oi ON (o.id_order = oi.id_order)')
            ->join($order_shipping.' os ON (o.`id_order`=os.`id_order`)', 'LEFT')
            ->join($product_name.' p ON (oi.id_product=p.id_product)', 'LEFT')
            ->join($shiTable.' s ON (o.id_shipping=s.id_shipping)', 'LEFT')
            ->where($where)->order('oi.id_product desc,oi.id_product_sku desc')->select();

        $order_list = array();$i=0;$temp_product = array();
        foreach($select_all as $item){
            $order_id = $item['id_order'];
            $order_list[$order_id] = $item;
            $temp_product[$order_id][] = array(
                'id_product'=>$item['id_product'],
                'id_product_sku'=>$item['id_product_sku'],
                'sku'=>$item['sku'],
                'sku_title'=>$item['sku_title'],
                'sale_title'=>$item['sale_title'],
                'product_title'=>$item['product_title'],
                'quantity'=>$item['quantity']
            );
        }

        $columns = array(
            '地区', '物流', '订单号', '运单号', '姓名', '电话号码', '邮箱',
            '产品名', '属性','SKU', '总价（NTS）', '产品数量',
            '送货地址', '留言备注', '下单时间', '订单状态',
            '发货日期','后台备注', '付款方式', '付款状态','邮编'
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
                $product_name = array();
                $attr_value = array();
                $sku = array();
                $qty = 0;
                foreach($products as $product){
                    if($product['sku_title']){
                        $product_name[] =  $product['product_title'].' + '.$product['sku_title'].' x '.$product['quantity'];
                        //$product_name[] =  $product['product_title'];
                        //$sku_title = $product['sku_title']?$product['sku_title']:$product['product_title'];
                        //$attr_value[]   = $product['sku_title'].' x '.$product['quantity'];
                    }else{
                        $product_name[] =  $product['product_title'].' x '.$product['quantity'];
                    }
                    $total_qty +=$product['quantity'];
                    $sku[] =  $product['sku'];
                }
                $getShipObj = D("Order/OrderShipping")->field('track_number,status_label,shipping_name')//
                    ->where(array('id_order'=>$o['id_order']))->select();
                $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
                $shipping_name = D('Common/Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
                $product_title = $product_name&& is_array($product_name)?implode(' ; ', $product_name):'';
                $attr_value = $attr_value && is_array($attr_value)?implode(' ; ', $attr_value):'';
                $sku  = $sku?implode(' ; ', $sku):'';
                $user_name = $o['first_name'].' '.$o['last_name'];
                $payment_method = $o['payment_method']?:'货到付款';
                $payment_status = $o['payment_status']?:'未付款';
                $payment_id = trim($o['payment_id']);
                if ($payment_id) {
                    //TODO: 只要是信用卡支付, 然后客服从通道那里确认后把订单状态改成"未配货"认为已经付款完成
                    $payment_method = '信用卡支付';
                    $payment_status = '已付款';
                }
                $product_title_attr = trim($attr_value)?$product_title.'   '.$attr_value:$product_title;
                //台湾地区的地址不需要加上省份,但是其他的地区需要带上
                if ($o['id_zone'] == 2) {
                    $address = trim($o['address']);
                } else {
                    $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
                }
                $data = array(
                    $all_zone[$o['id_zone']],$shipping_name,
                    $o['id_increment'],$trackNumber,$user_name ,$o['tel'],
                    $o['email'], $product_title_attr,'', $sku,
                    $o['price_total'],$total_qty, $address, $o['remark'],$o['created_at'],
                    $all_status[$o['id_order_status']], $o['date_delivery'], $o['comment'],
                    $payment_method, $payment_status,$o['zipcode']
                );
                $j = 65;
                foreach ($data as $key=>$col) {
                    if(in_array($key,array(3,10,11))){
                        $excel->getActiveSheet()->setCellValueExplicit(chr($j).$col_number, $col);
                    }else{
                        $excel->getActiveSheet()->setCellValue(chr($j) . $col_number, $col);
                    }
                    //$excel->getActiveSheet()->getStyle(chr($j).$col_number)->getNumberFormat()->setFormatCode('@');
                    ++$j;
                }
                //$history = array('id_order'=>$o['id_order'],'new_status_id'=>5,'comment'=>'导出未配货订单');
                //$status_model->update_status_add_history($history);
            }
            //add_system_record($_SESSION['ADMIN_ID'], 6, 1, '仓库导出未配送订单');
        }else{
            $this->error("没有数据");
        }

        $excel->getActiveSheet()->setTitle(date('Y-m-d').'出货信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d').'出货信息表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

}
