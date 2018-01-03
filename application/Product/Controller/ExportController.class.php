<?php

namespace Product\Controller;

use Common\Controller\AdminbaseController;

class ExportController extends AdminbaseController {

    protected $order, $order_shipping;

    public function _initialize() {
        parent::_initialize();
    }

    /**
     * 导入SKU，导出产品信息和开启产品
     */
    public function select_product_info(){
        if (IS_POST) {
            /** @var \Product\Model\ProductModel $product */
            $product = D('Product/Product');

            $data     = I('post.data');
            $data     = $this->getDataRow($data);
            $count    = 1;
            $user_id  = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $total    = 0;
            $sku_model  = D("Common/ProductSku");

            set_time_limit(0);
            ini_set('memory_limit', '-1');

            vendor("PHPExcel.PHPExcel");
            vendor("PHPExcel.PHPExcel.IOFactory");
            $excel = new \PHPExcel();
            $column = array('产品ID','内部名','SKU标题','新SKU','条形码','旧SKU',  'SKU状态');
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
                $row      = explode("\t", trim($row), 3);
                $sku      = trim($row[0]);
                $sku_temp = substr($sku,3);
                $find_sku = $sku_model->where(array('sku'=>$sku))->find();
                $inner_name = '';
                if($find_sku){
                    $_sku       = $find_sku['sku'];
                    $product_id = $find_sku['id_product'];
                    $sku_title  = $find_sku['title'];
                    $sku_status = $find_sku['status'];
                    $barcode    = $find_sku['barcode'];
                    $product->on_off_status($product_id,1);//开启产品
                    $load_pro   =  $product->find($product_id);
                    $inner_name = $load_pro['inner_name'];
                }else{
                    //去除前面4位数查找
                    $find_sku   = $sku_model->where(array('sku'=>array('LIKE','%'.$sku_temp)))->select();
                    if(count($find_sku)==1){
                        $_sku       = $find_sku[0]['sku'];
                        $product_id = $find_sku[0]['id_product'];
                        $sku_title  = $find_sku[0]['title'];
                        $sku_status = $find_sku[0]['status'];
                        $barcode    = $find_sku[0]['barcode'];
                        $product->on_off_status($product_id,1);//开启产品
                        $load_pro   =  $product->find($product_id);
                        $inner_name = $load_pro['inner_name'];
                    }elseif(count($find_sku)>1){
                        //查找多个情况下
                        $product_id = array_column($find_sku,'id_product','id_product');
                        if(count($product_id)==1){
                            //同一产品时候
                            $_sku       = $find_sku[0]['sku'];
                            $product_id = $find_sku[0]['id_product'];
                            $sku_title  = $find_sku[0]['title'];
                            $sku_status = $find_sku[0]['status'];
                            $barcode    = $find_sku[0]['barcode'];
                            foreach($product_id as $pro_id){
                                $product->on_off_status($product_id,1);//开启产品
                                $load_pro   =  $product->find($product_id);
                                $inner_name = $load_pro['inner_name'];
                            }
                        }else{
                            $product_id = implode(',',$product_id);
                            $_sku       = implode(',',array_column($find_sku,'sku'));
                            $sku_title  = implode(',',array_column($find_sku,'title'));
                            $sku_status = implode(',',array_column($find_sku,'status'));
                        }
                    }else{
                        //这个SKU没有找到
                        $_sku       = '';
                        $product_id = '';
                        $sku_title  = '';
                        $sku_status = '';
                        $barcode    = '';
                    }
                }
                if($sku==$_sku){
                    $sku = '';
                }
                $rowData = array($product_id,$inner_name,$sku_title,$_sku,$barcode,$sku,$sku_status);
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
            $excel->getActiveSheet()->setTitle(date('Y-m-d') . '导出产品信息.xlsx');
            $excel->setActiveSheetIndex(0);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '导出产品信息.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $writer->save('php://output');exit();
        }
        $this->display();
    }
}
