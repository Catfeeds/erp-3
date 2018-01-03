<?php

namespace Order\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;

class ExportController extends AdminbaseController {

    protected $order, $order_shipping;

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->order_shipping = D('Order/OrderShipping');
    }

    /**
     * 根据域名建立时间，导出订单信息
     */
    public function domain_order_data(){
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $start_time = isset($_GET['start_time']) && $_GET['start_time']?$_GET['start_time']:'2017-02-01 00:00:00';
        $end_time = isset($_GET['end_time']) && $_GET['end_time']?$_GET['end_time']:'2017-04-03 00:00:00';
        $do_where['created_at'] = array(
            array('EGT', $start_time),
            array('LT', $end_time)
        );
        $domain = D('Domain/Domain')->where($do_where)->select();
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true,36000)->select();
        $advertiser = array_column($advertiser,'name','id');
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $effective_status = OrderStatus::get_effective_status();

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();
        $columns = array(
            '建站日', '广告员', '部门', '域名', '产品名','产品ID', '13天有效单', '小于5单的15天后订单','大于5单的15天后订单'
        );
        $j = 65;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $idx = 2;
        foreach($domain as $key=>$do){
            $id_domain     = $do['id_domain'];
            $created_at_13 = date('Y-m-d H:i:s',strtotime('+13 days',strtotime($do['created_at'])));
            $created_at_28 = date('Y-m-d H:i:s',strtotime('+15 days',strtotime($created_at_13)));
            $where_13 = array(
                //'created_at'=> array('LT',$created_at_13),
                'id_domain' => $id_domain,
                'id_order_status' => array('IN',$effective_status)
            );
            $count_13 = 'count(if(o.created_at<"'.$created_at_13.'",1,0)) as get_13';
            $count_28 = "count(if('".$created_at_13."' < o.created_at and o.created_at<'".$created_at_28."',1,0)) as get_28";
            $order_13 = D("Order/Order")->alias('o')->field($count_13.','.$count_28.',o.id_users,o.id_department
            ,o.id_domain,oi.sale_title,oi.id_product')
                ->join('__ORDER_ITEM__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->where($where_13)
                ->order('o.id_order desc')->group('o.id_order')
                ->find();

           /* $count_28_order = 0;
            if($order_13['get_count']<=5){
                $where_28 = array(
                    'created_at'=>array(array('EGT',$created_at_13), array('LT', $created_at_28)),
                    'id_domain' => $id_domain,
                    'id_order_status' => array('IN',$effective_status)
                );
                $order_28 = D("Order/Order")->alias('o')->field('count(o.id_order) as get_count,o.id_users,o.id_department
            ,o.id_domain,oi.sale_title')
                    ->join('__ORDER_ITEM__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                    ->where($where_28)->group('o.id_order')->find();
                $count_28_order = $order_28['get_count'];
            }*/
            $user_name = $advertiser[$order_13['id_users']];
            if($order_13['get_13']==0){
                $order_13 = D("Order/Order")->alias('o')->field($count_13.','.$count_28.',o.id_users,o.id_department
            ,o.id_domain,oi.sale_title,oi.id_product')
                    ->join('__ORDER_ITEM__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                    ->where('oi.sale_title is not null')
                    ->order('o.id_order desc')->group('o.id_order')
                    ->find();
                //$order_13['sale_title'] = $order_0['sale_title'];
                //$order_13['id_product'] = $order_0['id_product'];
                $order_13['get_13']     = 0;
            }
            $get_15 = $order_13['get_13']<=5?$order_13['get_28']:'';
            $get_28 = $order_13['get_13']>5?$order_13['get_28']:'';

            $excel_data = array(
                $do['created_at'],$user_name,$department[$order_13['id_department']],$do['name'],
                $order_13['sale_title'],$order_13['id_product'],$order_13['get_13'],$get_15,$get_28
            );
            $j = 65;
            foreach ($excel_data as $col) {
                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
        }


        $excel->getActiveSheet()->setTitle(date('Y-m-d') .'域名订单信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '域名订单信息表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
    /**
     * 根据域名建立时间，导出订单信息
     */
    public function domain_order_data2(){
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $start_time = isset($_GET['start_time']) && $_GET['start_time']?$_GET['start_time']:'2017-02-01 00:00:00';
        $end_time = isset($_GET['end_time']) && $_GET['end_time']?$_GET['end_time']:'2017-04-03 00:00:00';
        $do_where['created_at'] = array(
            array('EGT', $start_time),
            array('LT', $end_time)
        );
        $domain = D('Domain/Domain')->where($do_where)->select();
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true,36000)->select();
        $advertiser = array_column($advertiser,'name','id');
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $effective_status = OrderStatus::get_effective_status();

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();
        $columns = array(
            '建站日', '广告员', '部门', '域名', '产品名','产品ID','13天内小于5单的', '小于5单的15天后订单', '13天内有效单','28天内有效单'
        );
        $j = 65;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $idx = 2;
        foreach($domain as $key=>$do){
            $id_domain     = $do['id_domain'];
            $created_at_13 = date('Y-m-d H:i:s',strtotime('+13 days',strtotime($do['created_at'])));
            $created_at_28 = date('Y-m-d H:i:s',strtotime('+15 days',strtotime($created_at_13)));
            $where_13 = array(
                //'created_at'=> array('LT',$created_at_13),
                'id_domain' => $id_domain,
                'id_order_status' => array('IN',$effective_status)
            );
            $count_13 = 'SUM(if(o.created_at<"'.$created_at_13.'",1,0)) as get_13';
            $count_28 = "SUM(if('".$created_at_13."' < o.created_at and o.created_at<'".$created_at_28."',1,0)) as get_28";
            $order_13 = D("Order/Order")->alias('o')
                ->field($count_13.','.$count_28.',o.id_users,o.id_department,o.id_domain,o.id_order')
                ->where($where_13)
                ->order('o.id_order desc')
                ->cache(true,36000)->find();


            if($order_13['get_13']==0 && $order_13['get_13']==0){
                $order_13 = D("Order/Order")->alias('o')
                    ->field('o.id_users,o.id_department,o.id_domain,oi.sale_title,oi.id_product')
                    ->join('__ORDER_ITEM__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                    ->where('oi.sale_title is not null and o.id_domain='.$id_domain)
                    ->order('o.id_order desc')->group('o.id_order')
                    ->cache(true,36000)->find();
                $sale_title  = $order_13['sale_title'];
                $order_13['get_13']     = 0;
                $order_13['get_28']     = 0;
            }else{
                $order_item = D("Order/OrderItem")
                    ->where(array('id_order'=>$order_13['id_order']))
                    ->cache(true,36000)->find();
                $sale_title = $order_item['sale_title'];
                $order_13['id_product'] = $order_item['id_product'];
                $order_13['get_13']     = $order_13['get_13']?$order_13['get_13']:0;
            }
            $user_name = $advertiser[$order_13['id_users']];
            $get_13 = $order_13['get_13']<=5?$order_13['get_13']:'';
            $get_15 = $order_13['get_13']<=5?$order_13['get_28']:'';
            $get_28 = $order_13['get_13']+$order_13['get_28'];

            $excel_data = array(
                $do['created_at'],$user_name,$department[$do['id_department']],$do['name'],
                $sale_title,$order_13['id_product'],$get_13,$get_15,$order_13['get_13'],$get_28
            );
            $j = 65;
            foreach ($excel_data as $col) {
                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
        }


        $excel->getActiveSheet()->setTitle(date('Y-m-d') .'域名订单信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '域名订单信息表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
}
