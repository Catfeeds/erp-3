<?php
/*
 *      _______ _     _       _     _____ __  __ ______
 *     |__   __| |   (_)     | |   / ____|  \/  |  ____|
 *        | |  | |__  _ _ __ | | _| |    | \  / | |__
 *        | |  | '_ \| | '_ \| |/ / |    | |\/| |  __|
 *        | |  | | | | | | | |   <| |____| |  | | |
 *        |_|  |_| |_|_|_| |_|_|\_\\_____|_|  |_|_|
 */
/*
 *     _________  ___  ___  ___  ________   ___  __    ________  _____ ______   ________
 *    |\___   ___\\  \|\  \|\  \|\   ___  \|\  \|\  \ |\   ____\|\   _ \  _   \|\  _____\
 *    \|___ \  \_\ \  \\\  \ \  \ \  \\ \  \ \  \/  /|\ \  \___|\ \  \\\__\ \  \ \  \__/
 *         \ \  \ \ \   __  \ \  \ \  \\ \  \ \   ___  \ \  \    \ \  \\|__| \  \ \   __\
 *          \ \  \ \ \  \ \  \ \  \ \  \\ \  \ \  \\ \  \ \  \____\ \  \    \ \  \ \  \_|
 *           \ \__\ \ \__\ \__\ \__\ \__\\ \__\ \__\\ \__\ \_______\ \__\    \ \__\ \__\
 *            \|__|  \|__|\|__|\|__|\|__| \|__|\|__| \|__|\|_______|\|__|     \|__|\|__|
 */
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace Portal\Controller;
use Common\Controller\HomebaseController;
use Common\Lib\Currency;

/**
 * 首页
 */
class ExportController extends HomebaseController {
    public function  get_csv_data($csvFile=false){
        $returnArray  = array();
        if(file_exists($csvFile)){
            $csvFile  = fopen($csvFile,'r');
            $i=0;$tempArray=array();
            while ($data = fgetcsv($csvFile)) {
                if($i==0){
                    $tempArray = $data;
                }else{
                    $itemArray = array();
                    if(is_array($data)){
                        foreach($data as $key=>$item){
                            $getKey = trim($tempArray[$key]);
                            $itemArray[$getKey]=$item;
                        }
                    }
                    $returnArray[] = $itemArray;
                }
                $i++;
            }
            fclose($csvFile);
        }
        return $returnArray;
    }
    public function return_data(){
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $file = './return_data.csv';
        $data = $this->get_csv_data($file);
        /** @var \Order\Model\OrderShippingModel $order_shipping */
        $order_shipping = D("Order/OrderShipping");
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();
        $col_number = 1;$col_number = 1;
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        /** @var \Order\Model\OrderModel $order_model */
        $order_model = D("Order/Order");
        $columns = array(
            '运单号', '订单号', '部门', '内部名称', '数量'
        );
        $j = 65;$col_number = 1;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }

        if($data){
            foreach($data as $key=>$item){
                $col_number++;
                $track_number = trim($item['track_number']);
                $get_shipping = $order_shipping->field('id_order')->where(array('track_number'=>$track_number))->find();
                if($get_shipping){
                    $order_d = $order_model->alias('o')->field('o.id_increment,d.title')
                        ->join('__DEPARTMENT__ d ON (d.id_department = o.id_department)', 'LEFT')
                        ->where(array('o.id_order'=>$get_shipping['id_order']))->find();

                    $pro_title = array();
                    $pro_quantity = 0;
                    $products = $order_item->get_item_list($get_shipping['id_order']);
                    foreach($products as $product){
                        $pro_title[] = $product['inner_name'].'  ('.$product['sku_title'].' x'.$product['quantity'].')';
                        $pro_quantity += $product['quantity'];
                    }
                    $product_title = $pro_title?implode(' ; ', $pro_title):$pro_title;
                    $export_data = array(
                        $track_number,$order_d['id_increment'],$order_d['title'],$product_title,$pro_quantity
                    );
                }else{
                    $export_data = array(
                        $track_number,'','','',''
                    );
                }
                $j = 65;
                foreach ($export_data as $key=>$col) {
                    if(in_array($key,array(0,1))){
                        $excel->getActiveSheet()->setCellValueExplicit(chr($j).$col_number, $col);
                    }else{
                        $excel->getActiveSheet()->setCellValue(chr($j) . $col_number, $col);
                    }
                    ++$j;
                }
            }
        }

        $excel->getActiveSheet()->setTitle(date('Y-m-d').'退货信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d').'退货信息表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();

    }
    public function contrast_returns(){
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $file = './contrast_returns.csv';
        $data = $this->get_csv_data($file);
        /** @var \Order\Model\OrderShippingModel $order_shipping */
        $order_shipping = D("Order/OrderShipping");

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();
        $col_number = 1;$col_number = 1;
        if($data){
            foreach($data as $key=>$item){
                $E = trim($item['E']);
                if($E){
                    $get_shipping = $order_shipping->field('status_label')->where(array('track_number'=>$E))->find();
                    array_push($item,$get_shipping['status_label']);
                }else{
                    array_push($item,'');
                }

                $columns = array_values($item);
                $j = 65;
                foreach ($columns as $col) {
                    $temp_j = $j>90?'A'.chr($j-26):chr($j);
                    if(in_array($j,array(69,76))){
                        $excel->getActiveSheet()->setCellValueExplicit($temp_j.$col_number,$col);
                    }else{
                        $excel->getActiveSheet()->setCellValue($temp_j . $col_number, $col);
                    }
                    ++$j;
                }
                $col_number++;
            }
        }

        $excel->getActiveSheet()->setTitle(date('Y-m-d').'退货信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d').'退货信息表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
    //查询过往15天超过10单的订单
    public function statistics_fifteen(){
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $order_shipping = D("Order/OrderShipping");
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();
        $col_number = 1;$col_number = 1;
        $order_item = D('Order/OrderItem');
        $order_model = D("Order/Order");
        $columns = array(
            '产品名', '内部名', '广告专员', '总订单'
        );
        $j = 65;$col_number = 1;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }

        $data=array();

        for($day=1;$day<16;$day++){
            $time1=date("Y-m-d",strtotime("-$day day"));
            $day2=$day-1;
            $time2=date("Y-m-d",strtotime("-$day2 day"));
            //id_product产品id id_users 用户id title产品名 inner_name内部名
            //把第一天的产品id数组作为基准
            if($day==1){
            //查询当天超过10单的产品订单
                    $data = $order_model->alias('o')->field('o.id_order,o.id_users,d.id_product')
                    ->where(array('o.date_purchase'=>array('BETWEEN',"$time1,$time2")))
                    ->join('__ORDER_ITEM__ d ON (d.id_order = o.id_order)', 'LEFT')
                    ->group('d.id_product')
                    ->having('count(d.id_product)>10')->select();
                    //新增一个数组存储产品id
                    $productIDData=array();
                    foreach($data as $k => $v){
                        array_push($productIDData,$v['id_product']);
                    }
                if(empty($productIDData)){
                    break;
                }
                $productIDData=array_unique($productIDData);

            }else{

                $data = $order_model->alias('o')->field('o.id_order,o.id_users,d.id_product')
                    ->where(array('o.date_purchase'=>array('BETWEEN',"$time1,$time2"),'d.id_product'=>array('IN',$productIDData)))
                    ->join('__ORDER_ITEM__ d ON (d.id_order = o.id_order)', 'LEFT')
                    ->group('d.id_product')
                    ->having('count(d.id_product)>10')->select();
                $productIDData=array();
                foreach($data as $k => $v){
                    array_push($productIDData,$v['id_product']);
                }
            }

        }

        if($data){
            foreach($data as $key=>$item){
                $col_number++;
                //查询用户名称
                $name = $order_model->alias('o')->field('d.user_nicename')
                        ->join('__USERS__ d ON (d.id = o.id_users)', 'LEFT')
                        ->where(array('o.id_order'=>$item['id_order']))->find();
                //查询产品信息
                $products = $order_item->alias('o')->field('d.inner_name,o.product_title,d.id_product')
                    ->join('__PRODUCT__ d ON (d.id_product = o.id_product)', 'LEFT')
                    ->where(array('o.id_order'=>$item['id_order']))->find();
                $time3=date("Y-m-d",strtotime("-15 day"));
                $time4=date("Y-m-d",strtotime("-1 day"));
                //查询这15天中的总订单
                $count = $order_model->alias('o')->field('count(o.id_order) as ordercount')
                    ->where(array('o.date_purchase'=>array('BETWEEN',"$time3,$time4"),'d.id_product'=>$products['id_product']))
                    ->join('__ORDER_ITEM__ d ON (d.id_order = o.id_order)', 'LEFT')->find();
                $export_data = array(
                    $products['product_title'],$products['inner_name'],$name['user_nicename'],$count['ordercount']
                );
                $j = 65;
                foreach ($export_data as $key=>$col) {
                    if(in_array($key,array(0,1))){
                        $excel->getActiveSheet()->setCellValueExplicit(chr($j).$col_number, $col);
                    }else{
                        $excel->getActiveSheet()->setCellValue(chr($j) . $col_number, $col);
                    }
                    ++$j;
                }
            }
        }

        $excel->getActiveSheet()->setTitle(date('Y-m-d').'过往15天超过10单的订单报表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d').'过往15天超过10单的订单报表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
}


