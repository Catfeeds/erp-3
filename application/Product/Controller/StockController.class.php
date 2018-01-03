<?php

namespace Product\Controller;

use Common\Controller\AdminbaseController;

class StockController extends AdminbaseController{
    
    public function index() {
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['p.id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['p.id_department']= $_GET['id_department'];
        }
        
        $M = new \Think\Model();
        $pro_tab = M('Product')->getTableName();
        $ware_pro_tab = M('WarehouseProduct')->getTableName();

        $ware_list = $M->table($ware_pro_tab.' as wp')->join('LEFT JOIN '.$pro_tab.' as p ON wp.id_product=p.id_product')
                ->field('wp.id_warehouse,SUM(wp.quantity) as quantity,SUM(wp.road_num) as road_num,p.id_department')
                ->where($where)->group('wp.id_warehouse,p.id_department')->select();

        foreach ($ware_list as $k=>$v) {
            $ware_list[$k]['count_qty'] = $v['quantity'];//总库存：仓库的库存加上路上的库存
            $ware_list[$k]['name'] = M('Warehouse')->where(array('id_warehouse'=>$v['id_warehouse']))->getField('title');
            $ware_list[$k]['department'] = M('Department')->where(array('id_department'=>$v['id_department']))->getField('title');
        }
                
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 1, '查看部门仓库库存');
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("list",$ware_list);
        $this->display();
    }
    
    public function check_view() {                
        $where = array(); 
        
        $wid = $_GET['id_warehouse'];
        $id_department = $_GET['department_id'];
        
        if(isset($_GET['department_id']) && $_GET['department_id']){
            $where['p.id_department']= $_GET['department_id'];
        }        
        
        if(isset($_GET['sku_title']) && $_GET['sku_title']) {
            $where['ps.sku'] = array('LIKE', '%' . $_GET['sku_title'] . '%');
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {            
            $where['p.title'] = array('LIKE', '%' . $_GET['pro_title'] . '%');
        }
        if(isset($_GET['pro_inner_title']) && $_GET['pro_inner_title']) {
            $where['p.inner_name'] = array('LIKE', '%' . $_GET['pro_inner_title'] . '%');
        }      
        
        $department_name = M('Department')->where(array('id_department'=>$id_department))->getField('title');
        $warehouse = M('Warehouse')->where('id_warehouse='.$wid)->find();
        $warehouse_product = M('WarehouseProduct')->where('id_warehouse='.$wid)->group('id_product')->select();
        $pro_id = array_column($warehouse_product, 'id_product');
        $pro_id = implode(',', $pro_id);
        $where['ps.id_product'] = array('IN',$pro_id);

        $M = new \Think\Model;
        $pro_sku_name = M("ProductSku")->getTableName();
        $pro_name = M("Product")->getTableName();
        $where['ps.status'] = 1;// 使用的SKU状态

        $count = $M->table($pro_name . ' AS p LEFT JOIN ' . $pro_sku_name . ' AS ps ON p.id_product=ps.id_product')
                    ->field('p.id_department,p.title,p.inner_name,ps.*')
                    ->where($where)->count();
        $page = $this->page($count, 40);
        
        $product_all = $M->table($pro_name . ' AS p LEFT JOIN ' . $pro_sku_name . ' AS ps ON p.id_product=ps.id_product')
                        ->field('p.id_department,p.inner_name,p.title as product_title,p.thumbs,ps.*')
                        ->where($where)
                        ->order("ps.sku ASC")
                        ->limit($page->firstRow . ',' . $page->listRows)->select();

        $product_arr = array();
        foreach ($product_all as $k=>$v) {
            $pro_names = M('Product')->where('id_product='.$v['id_product'])->getField('inner_name');
            $product_arr[$pro_names][] = $v;
        }
        foreach ($product_arr as $key=>$val) {               
            foreach ($val as $k=>$v) {
                $wpNum = M('WarehouseProduct')->where('id_product='.$v['id_product'].' and id_product_sku='.$v['id_product_sku'].' and id_warehouse='.$wid)->Field('quantity,qty_preout,road_num')->find();
                $img = json_decode($v['thumbs'],true);         
                $product_arr[$key][$k]['quantity'] = isset($wpNum['quantity']) ? $wpNum['quantity'] : '';
                $product_arr[$key][$k]['qty_preout'] = isset($wpNum['qty_preout']) ? $wpNum['qty_preout'] : '';
                $product_arr[$key][$k]['road_num'] = isset($wpNum['road_num']) ? $wpNum['road_num'] : '';
                $product_arr[$key][$k]['img'] = $img['photo'][0]['url'];
            }
        }
        
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 1, '查看部门库存页面');
        $this->assign("getData", $_GET);
        $this->assign('data',$warehouse);
        $this->assign('product_arr',$product_arr);
        $this->assign("page", $page->show('Admin'));
        $this->assign('department_name',$department_name);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign('count_product_sku',$count);
        $this->display();
    }
    
    /*
     * 导出sku的库存
     */

    public function stock_import() {
        $id = I('get.id_warehouse');
        $where = array();
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['p.id_department'] = array('EQ',$_GET['department_id']);
        }
        if(isset($_GET['department_id']) && $_GET['department_id']){
            $where['p.id_department']= $_GET['department_id'];
        }

        if(isset($_GET['sku_title']) && $_GET['sku_title']) {
            $where['ps.sku'] = array('LIKE', '%' . $_GET['sku_title'] . '%');
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
            $where['p.title'] = array('LIKE', '%' . $_GET['pro_title'] . '%');
        }
        if(isset($_GET['pro_inner_title']) && $_GET['pro_inner_title']) {
            $where['p.inner_name'] = array('LIKE', '%' . $_GET['pro_inner_title'] . '%');
        }
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $M = new \Think\Model;
        $pro_tab = D('Product/Product')->getTableName();
        $pro_sku_tab = D('Product/ProductSku')->getTableName();
        
        $warehouse = M('Warehouse')->where('id_warehouse='.$id)->find();
        $warehouse_product = M('WarehouseProduct')->where('id_warehouse='.$id)->group('id_product')->select();
        $pro_id = array_column($warehouse_product, 'id_product');
        $pro_id = implode(',', $pro_id);
        $where['ps.id_product'] = array('IN',$pro_id);
        
        $where['ps.status'] = 1;// 使用的SKU状态
        $warehouse_result = $M->table($pro_tab . ' AS p LEFT JOIN ' . $pro_sku_tab . ' AS ps ON p.id_product=ps.id_product')
                        ->field('p.id_department,p.inner_name,p.title as product_title,ps.*')
                        ->where($where)
                        ->order("p.id_product asc")
                        ->select();
//                dump($where);die;
        $columns = array(
            '仓库','部门', '产品名称', '内部名称', 'SKU', '属性','可配库存', '现有库存数量','在单数量','在途数量', '采购单价'
        );
        $j = 65;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $idx = 2;
        if ($warehouse_result) {
            foreach ($warehouse_result as $key => $val) {
                $warehouse = M('Warehouse')->where(array('id_warehouse'=>$id))->getField('title');
                $department = M('Department')->where(array('id_department'=>$val['id_department']))->getField('title');

                $wpNum = M('WarehouseProduct')->where(array('id_product'=>$val['id_product'],'id_product_sku'=>$val['id_product_sku'],'id_warehouse'=>$id))->Field('quantity,qty_preout,road_num')->find();
                $quantity = isset($wpNum['quantity']) ? $wpNum['quantity'] : '';
                $qty_preout = isset($wpNum['qty_preout']) ? $wpNum['qty_preout'] : '';
                $road_num = isset($wpNum['road_num']) ? $wpNum['road_num'] : '';
                $quantity2=$quantity-$qty_preout;

                //$qty = M('WarehouseProduct')->where(array('id_product'=>$val['id_product'],'id_product_sku'=>$val['id_product_sku'],'id_warehouse'=>$id))->getField('quantity');
                $data = array(
                    $warehouse,$department,$val['product_title'], $val['inner_name'], $val['sku'], $val['title'],$quantity2 ,$quantity, $qty_preout, $road_num,$val['purchase_price']
                );
                $j = 65;
                foreach ($data as $key => $col) {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                    ++$j;
                }
                ++$idx;
            }
        } else {
            $this->error("没有数据");
        }

        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '导出仓库库存表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '导出仓库库存表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
}
