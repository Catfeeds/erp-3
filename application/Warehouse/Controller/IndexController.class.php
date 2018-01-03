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
use Order\Lib\OrderStatus;
use Order\Model\UpdateStatusModel;
use Product\Controller\PdfController;

class IndexController extends AdminbaseController {
    protected $Warehouse, $orderModel;
    public function _initialize() {
        parent::_initialize();
        $this->Warehouse = D("Common/Warehouse");
        $this->orderModel = D("Order/Order");
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row']?$_SESSION['set_page_row']:20;
    }

    /**
     * 仓库列表
     */
    public function index() {
        $list = $this->Warehouse->order('id_warehouse desc')->select();
        foreach ($list as $k=>$v) {
            $list[$k]['zone'] = M('Zone')->where('id_zone='.$v['id_zone'])->getField('title');
        }
        add_system_record(sp_get_current_admin_id(), 4, 1, '查看仓库列表');
        $this->assign("list", $list);
        $this->display();
    }
    
    /**
     * 库存查询 对于erp_warehouse_product的展示
     */
    public function stock_search(){
        $getData = I('get.', "htmlspecialchars");
        $cond_stock= []; 
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        } 
        $cur_page = $getData['p']? : 1; //默认页数            
        $pro_table = D("Common/Product")->getTableName();
        $pro_s_table = D("Common/ProductSku")->getTableName();
        $wp_table = D("Common/WarehouseProduct")->getTableName(); 
        
        
        $where='pt.id_product=pst.id_product and pst.id_product_sku=wpt.id_product_sku and pst.id_product=wpt.id_product and wpt.id_product=pt.id_product';//多表连接条件
        if(!empty($getData['id_warehouse'])){
            $where.=" and wpt.id_warehouse=".$getData['id_warehouse'];
        }
        if(!empty($getData['department'])){
            $where.=" and pt.id_department=".$getData['department'];
        }        
        if(!empty($getData['sku'])){
            $where.=" and pst.sku like '%".trim($getData['sku'])."%'";
        }       
        if(!empty($getData['inner_name'])){
            $where.=" and pt.inner_name like '%".trim($getData['inner_name'])."%'";
        }     
        $where.='  and pst.status=1';
        $fields='pt.id_department,wpt.id_warehouse,pst.barcode,pt.title as ptitle,pt.inner_name,pst.sku,pst.title as attr, wpt.quantity,wpt.road_num,wpt.qty_preout,wpt.quantity-wpt.qty_preout canuse';
        $count= M()->table(array(  $pro_table=> 'pt',$pro_s_table => 'pst',$wp_table=>'wpt'))->field($fields)->where($where)->count();
        $stocklist=M()->table(array(  $pro_table=> 'pt',$pro_s_table => 'pst',$wp_table=>'wpt'))->field($fields)->where($where)->page("$cur_page,$this->page")->select();
        $page = $this->page($count, $this->page);
        $warehouseList=$this->Warehouse ->where(array('status' => 1))->getField('id_warehouse,title', true);
        $departmentList=M('department')->getField('id_department,title', true);
        $this->assign("warehouseList", $warehouseList);
        $this->assign("departmentList", $departmentList);
        $this->assign("stocklist", $stocklist);
        $this->assign("page", $page->show('Admin'));
        $this->assign("getdata",$getData);
        $this->display();
    }
    
    
    /**
     * 导出库存
     */
    public function stockImport(){
        $getData = I('get.', "htmlspecialchars");
        $pro_table = D("Common/Product")->getTableName();
        $pro_s_table = D("Common/ProductSku")->getTableName();
        $wp_table = D("Common/WarehouseProduct")->getTableName();
        $where='pt.id_product=pst.id_product and pst.id_product_sku=wpt.id_product_sku and pst.id_product=wpt.id_product and wpt.id_product=pt.id_product';//多表连接条件
        if(!empty($getData['id_warehouse'])){
            $where.=" and wpt.id_warehouse=".$getData['id_warehouse'];
        }
        if(!empty($getData['department'])){
            $where.=" and pt.id_department=".$getData['department'];
        }          
        if(!empty($getData['sku'])){
            $where.=" and pst.sku like '%".trim($getData['sku'])."%'";
        }       
        if(!empty($getData['inner_name'])){
            $where.=" and pt.inner_name like '%".trim($getData['inner_name'])."%'";
        }     
        $where.='  and pst.status=1';
        $fields='pt.id_department,wpt.id_warehouse,pst.barcode,pt.title as ptitle,pt.inner_name,pst.sku,pst.title as attr, wpt.quantity,wpt.road_num,wpt.qty_preout,wpt.quantity-wpt.qty_preout canuse';
        $stocklist=M()->table(array(  $pro_table=> 'pt',$pro_s_table => 'pst',$wp_table=>'wpt'))->field($fields)->where($where)->select();        
        $warehouseList=$this->Warehouse ->where(array('status' => 1))->getField('id_warehouse,title', true);
        $departmentList=M('department')->getField('id_department,title', true); 
        /*$str = "序号,仓库名称,部门名称,产品名称,内部名,sku,属性,可配库存,库存量,在途量,在单量（锁定库存）\n";    
        foreach($stocklist as $k => $item){
            $str.=($k+1).','.
                $warehouseList[$item['id_warehouse']].','.
                $departmentList[$item['id_department']].','.
                $item['ptitle'].','.
                $item['inner_name'].','.
                trim($item['sku'],',').','.   //去除首位空格，以免导出格式错乱
                trim($item['attr'],',').','.  //去除首位空格，以免导出格式错乱
                $item['canuse'].','.
                $item['quantity'].','.
                $item['road_num'].','.
                $item['qty_preout']."\n";
        }        
        $filename = date('Ymd').'.csv'; //设置文件名
        $this->export_csv($filename,$str); //导出
        exit;  */
        
        //重构导出格式xlsx zx 11/23
        $getField = array('A','B','C','D','E','F','G','H','I','J','K');
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $objPHPExcel = new \PHPExcel();
        $setRowName = array('序号','仓库名称','部门名称','产品名称','内部名','sku','属性','可配库存','库存量','在途量','在单量（锁定库存)');
        $num  = 2;
        foreach($setRowName as $r=>$v){
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$r].'1',$v);
            $j++;
        }
            
         foreach($stocklist as $k =>$order){
                $tempRow = array(
                    ($k+1),
                    $warehouseList[$order['id_warehouse']],
                    $departmentList[$order['id_department']],
                    $order['ptitle'],
                    $order['inner_name'],
                    $order['sku'],
                    $order['attr'],
                    $order['canuse'],
                    $order['quantity'],
                    $order['road_num'],
                    $order['qty_preout']
                        
                );
                foreach ($tempRow as $row => $value) {
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$row] . $num, $value);
                }
                $num++;
            }
        
        add_system_record($_SESSION['ADMIN_ID'], 7, 4, '库存查询');
        $objPHPExcel->getActiveSheet()->setTitle('order');
        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.date('Y-m-d').'库存查询.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
        
    }    
    
    /*
     * 批量添加产品到指定仓库
     */
    public function product_batch_add() {

        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $where['title'] = array('LIKE', '%' . $_GET['keyword'] . '%');
            $where['inner_name'] = array('LIKE', '%' . $_GET['keyword'] . '%');
            $where['model'] = array('EQ', $_GET['keyword']);
            $where['_logic'] = 'or';
        }
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['id_department'] = array('EQ', $_GET['department_id']);
        }

        $count = M('Product')->where($where)->count();
        $page = $this->page($count, 40);
        $product = M('Product')->where($where)->order("id_product DESC")->limit($page->firstRow , $page->listRows)->select();

        foreach ($product as $k=>$v) {
            $product[$k]['img'] = json_decode($v['thumbs'],true);
        }
        add_system_record(sp_get_current_admin_id(), 1, 1, '批量添加产品到指定仓库');
        $department = M('Department')->where('type=1')->cache(true, 86400)->select();
        $warehouse = M('Warehouse')->cache(true, 86400)->select();
        $this->assign('product',$product);
        $this->assign('warehouse',$warehouse);
        $this->assign('department',$department);
        $this->assign("getData", $_GET);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    //添加新增的商品sku库存
    public function batch_add_post() {
        try{
            $warehouse_id = I('get.id');
            $product_id = I('get.pro_id');
            $warehouse = M("Warehouse")->find($warehouse_id);
            if($warehouse_id && $warehouse) {
                if($product_id) {
                    $product = M('Product')->where(array('id_product'=>$product_id))->select();
                } else {
                    $product = M('Product')->select();
                }
                if($product && is_array($product)){
                    foreach($product as $k=>$val){
                        $id_product_sku = M('ProductSku')->field('id_product_sku')->where('id_product='.$val['id_product'])->select();
                        if(!$id_product_sku) continue;                    
                        foreach ($id_product_sku as $sku) {                        
                            $warehouse_pro_sku = M('WarehouseProduct')->where(array('id_product_sku'=>$sku['id_product_sku'],'id_warehouse'=>$warehouse_id))->getField('id_product_sku');
                            if($warehouse_pro_sku) continue;
                            $data_list = array(
                                'id_warehouse'=>$warehouse_id,
                                'id_product'=>$val['id_product'],
                                'id_product_sku'=>$sku['id_product_sku'],
                                'quantity' => 0,
                                'road_num' => 0
                            );
                            D('Common/WarehouseProduct')->add($data_list);
                        }                    
                    }                
                }
                $status= 1; $message ='成功';
            } else {
                $status= 0; $message = '参数不正确';
            }
        }catch (\Exception $e){
           $status= 0; $message = $e->getMessage();
        }
        $return = array('status'=>$status,'message'=>$message);
        echo json_encode($return);exit();
    }
    
    //添加深圳仓库的库存到任意仓库
    public function batch_add_other_post() {
        try{
            $warehouse_id = I('get.id');
            $warehouse = M("Warehouse")->find($warehouse_id);
            if($warehouse_id && $warehouse) {
                $warehouse_product = M('WarehouseProduct')->where(array('id_warehouse'=>1))->select();
                if($warehouse_product&& is_array($warehouse_product)){
                    foreach($warehouse_product as $k=>$val){
                        $result = M('WarehouseProduct')->where(array('id_product'=>$val['id_product'],'id_product_sku'=>$val['id_product_sku'],'id_warehouse'=>$warehouse_id))->find();
                        if(!$result) {
                            $data_list = array(
                                'id_warehouse'=>$warehouse_id,
                                'id_product'=>$val['id_product'],
                                'id_product_sku'=>$val['id_product_sku'],
                                'quantity' => 0,
                                'road_num' => 0
                            );
                            D('Common/WarehouseProduct')->add($data_list);      
                        }
                    }                
                }
                $status= 1; $message ='成功';
            } else {
                $status= 0; $message = '参数不正确';
            }
        }catch (\Exception $e){
           $status= 0; $message = $e->getMessage();
        }
        $return = array('status'=>$status,'message'=>$message);
        echo json_encode($return);exit();
    }

    /**
     *库存页面 
     */
    public function stock_index() {
        $warehouse = M('Warehouse')->select();
        $ware_list = M('WarehouseProduct')->alias('wp')->join('__PRODUCT_SKU__ AS ps ON ps.id_product_sku=wp.id_product_sku')
                ->field('wp.id_warehouse,SUM(wp.quantity) as quantity,SUM(wp.road_num) as road_num')->where(array('ps.status'=>1))->group('wp.id_warehouse')->select();
        foreach ($ware_list as $k=>$v) {
            $ware_list[$k]['count_qty'] = $v['quantity'];//总库存：仓库的库存加上路上的库存
            $ware_list[$k]['name'] = M('Warehouse')->where('id_warehouse='.$v['id_warehouse'])->getField('title');
        }
        add_system_record(sp_get_current_admin_id(), 4, 1, '查看仓库库存列表');
        $this->assign('list',$ware_list);
        
        $this->display();
    }
    /**
     * 部门库存
     */
    public function dep_stock(){
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = array_column($department,'title','id_department');
        $warehouse = M('Warehouse')->select();
        $warehouse  = array_column($warehouse,'title','id_warehouse');
        foreach($department as $key=>$value){
            $ware_list = M('WarehouseProduct')->alias('wp')->field('SUM(wp.quantity) as quantity,SUM(wp.road_num) as road_num,id_warehouse')
                ->join('__PRODUCT_SKU__ AS ps ON ps.id_product_sku=wp.id_product_sku')
                ->join('__PRODUCT__ AS p ON p.id_product=wp.id_product')
                ->where(array('p.id_department'=>array('EQ',$key),'ps.status'=>1))
                ->group('wp.id_warehouse')->select();
            foreach ($ware_list as $k=>$v) {
                $list[$key][$v['id_warehouse']]['count_qty']= $v['quantity'];
                $list[$key][$v['id_warehouse']]['department']= $department[$key];
          }
        }

//        dump($list);
        add_system_record(sp_get_current_admin_id(), 4, 1, '查看部门库存列表');
        $this->assign('list',$list);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /*
     *  货位库存
     */
    public function allocation_stock(){
        $id_warehouse = I('request.id');
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $where = array();

        $where['wga.id_warehouse'] = array('EQ', $id_warehouse);

        if(isset($_GET['id_warehouse'])&&$_GET['id_warehouse']){
            $where['wga.id_warehouse'] = $_GET['id_warehouse'];
            if(isset($_GET['area_title'])&&$_GET['area_title']){
                $w['title'] = $_GET['area_title'];
                $w['id_warehouse'] = $_GET['id_warehouse'];
                $id_goods_area = M('WarehouseGoodsArea')->field('id_goods_area')->where($w)->find();
                $id_goods_area = implode('',$id_goods_area);
                $where['wga.id_goods_area'] = $id_goods_area;
            }
        }
        if(isset($_GET['id_department'])&&$_GET['id_department']){
            $where['p.id_department'] = trim($_GET['id_department']);
        }
        if(isset($_GET['goods_name'])&&$_GET['goods_name']){
            $where['wga.goods_name'] = array('LIKE',trim($_GET['goods_name']));
        }
        if(isset($_GET['sku'])&&$_GET['sku']){
            $key_where['pk.sku'] = array('LIKE', '%' .trim($_GET['sku']) . '%');
            $key_where['pk.barcode'] = array('LIKE', '%' . trim($_GET['sku']) . '%');
            $key_where['_logic'] = 'or';
            $where['_complex'] = $key_where;
        }
        if(isset($_GET['title'])&&$_GET['title']){
            $where['p.title'] = trim($_GET['title']);
        }
        if(isset($_GET['inner_name'])&&$_GET['inner_name']) {
            $where['p.inner_name'] = array('LIKE', '%' .trim($_GET['inner_name']) . '%');
        }
        $where['was.quantity']=array('GT',0);
        $count = M('WarehouseGoodsAllocation')->alias('wga')
            ->field('wga.id_warehouse,p.id_department,wga.goods_name,pk.sku,p.title,was.quantity,wga.id_warehouse_allocation')
            ->join('__WAREHOUSE_ALLOCATION_STOCK__  was on was.id_warehouse_allocation = wga.id_warehouse_allocation','LEFT')
            ->join('__PRODUCT_SKU__ as pk on was.id_product_sku = pk.id_product_sku','LEFT')
            ->join('__PRODUCT__ as p on was.id_product = p.id_product','LEFT')
            ->where($where)->count();
        $page = $this->page($count, 20);
        $area_list = M('WarehouseGoodsArea')->field('id_goods_area,title')->select();
        $area_list = array_column($area_list,'title','id_goods_area');


        if(I('request.display') == 'export'){
            $list = M('WarehouseGoodsAllocation')->alias('wga')
                ->field('wga.id_warehouse,was.id,p.id_department,wga.goods_name,pk.sku,pk.barcode,p.inner_name,p.title,
            was.quantity,wga.id_warehouse_allocation,was.id_product_sku,pk.title as attr')
                ->join('__WAREHOUSE_ALLOCATION_STOCK__  was on was.id_warehouse_allocation = wga.id_warehouse_allocation','LEFT')
                ->join('__PRODUCT_SKU__ as pk on was.id_product_sku = pk.id_product_sku','LEFT')
                ->join('__PRODUCT__ as p on was.id_product = p.id_product','LEFT')
                ->where($where)->select();
        }else{
            $list = M('WarehouseGoodsAllocation')->alias('wga')
                ->field('wga.id_warehouse,was.id,p.id_department,wga.goods_name,pk.sku,pk.barcode,p.inner_name,p.title,
            was.quantity,wga.id_warehouse_allocation,was.id_product_sku,pk.title as attr')
                ->join('__WAREHOUSE_ALLOCATION_STOCK__  was on was.id_warehouse_allocation = wga.id_warehouse_allocation','LEFT')
                ->join('__PRODUCT_SKU__ as pk on was.id_product_sku = pk.id_product_sku','LEFT')
                ->join('__PRODUCT__ as p on was.id_product = p.id_product','LEFT')
                ->where($where)->limit($page->firstRow, $page->listRows)->select();
        }
        $status_approved = OrderStatus::APPROVED;
        $status_unpicking = OrderStatus::UNPICKING;
        $status_picked = OrderStatus::PICKED;

        foreach($list as &$row){
            $tmp_quantity = M('Order')->alias('o')
                ->field("SUM(ABS(wr.num)) as tmp_quantity,
                    SUM(IF(o.id_order_status={$status_approved}, ABS(wr.num), 0)) AS approved_quantity,
                    SUM(IF(o.id_order_status={$status_unpicking}, ABS(wr.num), 0)) AS unpicking_quantity,
                    SUM(IF(o.id_order_status={$status_picked}, ABS(wr.num), 0)) AS picked_quantity
                ")
                ->join('__WAREHOUSE_RECORD__ as wr ON wr.id_order = o.id_order', 'LEFT')
                ->where(array(
                    'o.id_order_status'=>array('IN', array($status_approved, $status_unpicking, $status_picked))
                ))   //未配货、配货中、已审核订单    即扣了库存但是实际产品还在仓库中的订单
                ->where(array(
                    'wr.id_product_sku' => $row['id_product_sku'],
                    'wr.id_warehouse_allocation' => $row['id_warehouse_allocation'],
                    'wr.type' => 3,
                ))
                ->find();
            if(empty($tmp_quantity['tmp_quantity'])){
                $tmp_quantity =$approved_quantity = $unpicking_quantity = $picked_quantity = 0;
            }else{
                $approved_quantity = $tmp_quantity['approved_quantity'];
                $unpicking_quantity = $tmp_quantity['unpicking_quantity'];
                $picked_quantity = $tmp_quantity['picked_quantity'];
                $tmp_quantity = $tmp_quantity['tmp_quantity'];
            }
            $row['actual_quantity'] = $tmp_quantity + $row['quantity'];
            $row['approved_quantity'] = $approved_quantity;
            $row['unpicking_quantity'] = $unpicking_quantity;
            $row['picked_quantity'] = $picked_quantity;
            $row['department_name'] = $department[$row['id_department']];
        }

        if(I('request.display') == 'export'){
            add_system_record($_SESSION['ADMIN_ID'], 4, 1,'导出货位库存');
            $row_map = array(
                array('name'=>'内部名', 'key'=> 'inner_name'),
                array('name'=>'部门', 'key'=> 'department_name'),
                array('name'=>'货位名称', 'key'=> 'goods_name'),
                array('name'=>'SKU', 'key'=> 'sku'),
                array('name'=>'条形码', 'key'=> 'barcode'),
                array('name'=>'产品名', 'key'=> 'title'),
                array('name'=>'属性', 'key'=> 'attr'),
                array('name'=>'可用库存', 'key'=> 'quantity'),
                array('name'=>'实际库存', 'key'=> 'actual_quantity'),
                array('name'=>'已审核库存', 'key'=> 'approved_quantity'),
                array('name'=>'未配货库存', 'key'=> 'unpicking_quantity'),
                array('name'=>'已配货库存', 'key'=> 'picked_quantity')
            );
            vendor('PHPExcel.ExcelManage');
            $excel = new \ExcelManage();
            $excel->export($list, $row_map, date("Y-m-d") . '货位库存');
        }else{
            $warehouse_name = M('Warehouse')->where(array('id_warehouse'=>$id_warehouse))->getField('title');

            $this->assign('warehouse_name',$warehouse_name);
            $this->assign('list',$list);
            $this->assign('department',$department);
            $this->assign('area_list',$area_list);
            $this->assign("Page", $page->show('Admin'));
            add_system_record($_SESSION['ADMIN_ID'], 4, 1,'查看货位库存');
            $this->display();
        }
    }
    
    public function stock_post() {
        if(IS_AJAX) {
            $data = I('post.');
            $model = new \Think\Model;
            $order_table_name = D('Order/Order')->getTableName();
            $order_item_table_name = D('Order/OrderItem')->getTableName();
            
            $pro_sku = M('ProductSku')->where(array('id_product_sku'=>$data['id_product_sku']))->getField('sku');
            $result = M('WarehouseProduct')->where('id_product='.$data['id_product'].' and id_product_sku='.$data['id_product_sku'].' and id_warehouse='.$data['id_warehouse'])->find();
            if($result) {
                $status = 2;
                $flag = D("Common/WarehouseProduct")->where('id_product='.$data['id_product'].' and id_product_sku='.$data['id_product_sku'].' and id_warehouse='.$data['id_warehouse'])->save($data);
            } else {
                $data['road_num'] = 0;
                $status = 1;
                $flag = D("Common/WarehouseProduct")->data($data)->add();
            }

            if($data['quantity']>0) {
                $where = 'oi.id_product_sku ='.$data['id_product_sku'].' and o.id_order_status=6';
                $order_data = $model->table($order_table_name . ' as o LEFT JOIN ' . $order_item_table_name . ' as oi ON o.id_order=oi.id_order')
                    ->field('oi.id_order,o.id_zone,o.id_department,o.id_order_status,o.payment_method')
                                ->where($where)
                                ->order('oi.sorting desc,o.date_purchase asc')
                                ->select();

                //仓库收货后对缺货产品进行未配货的状态更新，并减去仓库库存
                if($order_data && $data['quantity']>0) {
                    /** @var \Order\Model\OrderRecordModel  $order_record */
                    $order_record = D("Order/OrderRecord");
                    foreach ($order_data as $key=>$val) {
                        //if(in_array($val['id_department'],array(4,5,7))){//4,5,

                            //香港地区DF订单减库存后状态改为已审核
                            if($val['id_zone'] == 3 && empty($val['payment_method'])){
                                $default_id_order_status = \Order\Lib\OrderStatus::APPROVED;
                            }else{
                                $default_id_order_status = \Order\Lib\OrderStatus::UNPICKING;
                            }

                            $results = \Order\Model\UpdateStatusModel::lessInventory($val['id_order'],$val);
                            if($results['status']) {
                                $update_order = array();
                                $update_order['id_order_status'] = $default_id_order_status;
                                $update_order['id_warehouse'] = isset($results['id_warehouse'])?end($results['id_warehouse']):1;
                                D('Order/Order')->where('id_order='.$val['id_order'])->save($update_order);
                                $parameter  = array(
                                    'id_order' => $val['id_order'],
                                    'id_order_status' => $default_id_order_status,
                                    'type' => 1,
                                    //'user_id' => 1,
                                    'comment' => '更改仓库库存对缺货状态进行更新,更新为：'.$data['quantity'],
                                );
                                $order_record->addOrderHistory($parameter);
                            }
                        //}
                    }
                }
                
            }

            if($flag) {
                $sta = 1;
                $msg = '保存成功';                
            } else {
                $sta = 0;
                $msg = '保存失败';                
            }
            add_system_record(sp_get_current_admin_id(), 2, 1, '保存仓库库存，仓库ID：'.$data['id_warehouse'].'，SKU：'.$pro_sku.'，原有库存：'.$result['quantity'].'，修改为：'.$data['quantity']);
            echo json_encode(array('sta'=>$sta,'msg'=>$msg));exit();
        }
    }

    /**
     * 编辑表单
     */
    public function edit() {
        $id = I('get.id');
        $data = array();
        if ($id) {
            $data = $this->Warehouse->find($id);
        }
        $zone = M('Zone')->select();
        //dump($zone);die;
        $this->assign("data", $data);
        $this->assign('zone',$zone);
        $this->display();
    }

    /**
     * 保存仓库信息
     */
    public function save_post() {
        if (IS_POST) {
            $data = $_POST;
            $data['updated_at'] = date('Y-m-d H:i:s');
            if (isset($data['id']) && $data['id']) {
                $result = $this->Warehouse->where('id_warehouse=' . $data['id'])->save($data);
                $msg = $result ? '仓库'.$data['id'].'修改成功' : '仓库'.$data['id'].'修改失败';
                $status = 2;
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $result = $this->Warehouse->data($data)->add();
                $msg = $result ? '仓库添加成功' : '仓库添加失败';
                $status = 1;
            }
            F('getAllWarehouseList',false);
            if ($result) {
                add_system_record(sp_get_current_admin_id(), $status, 1, $msg);
                $this->success($msg, U("index/index"));
            } else {
                add_system_record(sp_get_current_admin_id(), $status, 1, $msg);
                $this->error($msg, U("index/index"));
            }
        } else {
            $this->error("保存失败");
        }
    }

    /**
     * 删除仓库信息
     */
    public function delete() {
        $id = intval(I('get.id'));
        $warehouse=$this->Warehouse->where(array("id_warehouse" => $id))->find();
        if (!empty($warehouse) && $warehouse['status']==1) {
            $this->error("该仓库正处于开启状态，请先关闭");
        }
        if (!empty($warehouse) && $warehouse['forward']==1) {
            $count=D('Forward')->where(array("id_warehouse" => $id))->count();
        }else{
            $count=D('WarehouseProduct')->where(array("id_warehouse" => $id))->count();
        }

        if ($count > 0) {
            $this->error("该仓库已经被引用，请先清除库存再删除仓库！");
        }
        $status = $this->Warehouse->delete($id);
        if ($status) {
            add_system_record(sp_get_current_admin_id(), 3, 1, '删除仓库' . $id . '成功');
            $this->success("删除成功！", U('index/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 3, 1, '删除仓库' . $id . '失败');
            $this->error("删除失败！");
        }
    }

    /**
     * 所有派送中（配送中）的订单列表
     */
    public function delivery() {
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = $this->orderModel;
        $getFormWhere = $ordModel->form_where($_GET);
        if (isset($getFormWhere['create_at'])) {
            $getFormWhere['date_delivery'] = $getFormWhere['create_at'];
            unset($getFormWhere['create_at']);
        }
        $department = M('Department')->where('type=1')->select();
        $warehouse = M('Warehouse')->select();
        
        $setStatusId = 8;
        $getFormWhere['id_order_status'] = $setStatusId;
//        $_SESSION['department_id'] ? $getFormWhere['id_department'] = array('IN',$_SESSION['department_id']) : '';
        //$getFormWhere['shipping_id'] = array('NEQ','');
        if ($_GET['product_id']) {
            $M = new \Think\Model;
            $ordName = D("Common/Order")->getTableName();
            $ordIteName = D("Common/OrderItem")->getTableName();
            $findOrder = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                            ->where(array('oi.id_product' => $_GET['product_id'], 'o.id_order_status' => $setStatusId))
                            ->group('oi.id_order')->select();
            $allId = array_column($findOrder, 'id_order');
            $allId = implode(',', $allId);
            $getFormWhere['id_order'] = $allId ? array('IN', $allId) : array('EQ', 0);
        }

        $baseSql = $this->orderModel->where($getFormWhere);
        $count = $baseSql->count();
        $todayDate = date('Y-m-d');
        $todayTotal = $this->orderModel->where($getFormWhere)->where(array('date_delivery' => array('like', $todayDate . '%')))->count();
        $formData = array();
        $formData['web_url'] = D('Common/Domain')
                ->field('`name` web_url')
                ->order('`name` ASC')
                ->cache(true, 3600)
                ->select();

        $page = $this->page($count, $this->page);
        $orderList = $baseSql->where($getFormWhere)->order("date_delivery asc,tel DESC,first_name desc,email desc")
                        ->limit($page->firstRow . ',' . $page->listRows)->select();
        
        $order_item = D('Order/OrderItem');
        foreach ($orderList as $key => $o) {
            $getProducts = $order_item->get_item_list($o['id_order']);
            $orderList[$key]['is_transfer'] = $ordModel->matchOrder($getProducts);
            $orderList[$key]['products'] = $getProducts;
        }

        $shipping = D("Common/Shipping")->field('id_shipping,title')->cache(true, 600)->select();
        $shipTemp = array();
        if (is_array($shipping)) {
            foreach ($shipping as $ship) {
                $shipTemp[$ship['id_shipping']] = $ship['title'];
            }
        }

        //echo $baseSql->where($getFormWhere)->fetchSql(true)->select();
        $TProWhere = array('id_order_status' => $setStatusId, 'delivery_date' => array('EGT', date('Y-m-d 00:00:00')));
        $productCount = D("Common/order")->field('SUM(`order_count`) as total')->where($TProWhere)->select();

        $allProduct = D('Common/Product')->field('id_product,title')->order('id_product desc')->cache(true, 86400)->select();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看所有配送中的订单列表');
        $this->assign("getData", $_GET);
        $this->assign("form_data", $formData);
        $this->assign("page", $page->show('Admin'));
        $this->assign("todayTotal", $todayTotal);
        $this->assign("orderTotal", $count);
        $this->assign("order_list", $orderList);
        $this->assign("shipping", $shipTemp);
        $this->assign('product', D("Common/product")->getAllProduct());
        $this->assign("todayProduct", $productCount); //今天待发产品统计
        $this->assign("allProduct", $allProduct);
        $this->assign('department',$department);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /**
     * 缺货订单
     */
    public function unstock() {
        $status_where = 6; //array('IN',array(2,18,19));
        /* @var $order \Common\Model\OrderModel */
        $order = D("Order/Order");

        $department = M('Department')->where('type=1')->select();
        $warehouse = M('Warehouse')->select();
        $result = $order->getWarehouseOrder($status_where);
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看缺货订单');
        $this->assign("getData", $_GET);
        $this->assign("form_data", $result['form_data']);
        $this->assign("page", $result['page']);
        $this->assign("todayTotal", $result['todayTotal']);
        $this->assign("orderTotal", $result['orderTotal']);
        $this->assign("todayWebData", $result['todayWebData']);
        $this->assign("order_list", $result['order_list']);
        $this->assign("shipping", $result['shipping']);
        $this->assign('product', $result['product']);
        $this->assign("todayProduct", $result['todayProduct']); //今天待发产品统计
        $this->assign("allProduct", $result['allProduct']);
        $this->assign('department',$department);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /**
     * 无运单号订单
     */
    public function untracknumber() {
        $status = array('IN', array(4, 5, 6, 7));
        $department = M('Department')->where('type=1')->select();
        $warehouse = M('Warehouse')->select();
        $result = $this->getUntracknumberOrders($status);
        $allProduct = D('Common/Product')
                ->field('id_product,title')
                ->order('id_product desc')
                ->cache(true, 86400)
                ->select();
        $shipping = D("Common/Shipping")->field('id_shipping,title')->cache(true, 86400)->select();
        $shipping = $shipping ? array_column($shipping, 'title', 'id_shipping') : array();
        $domains = D('Common/Domain')
                ->order('`name` ASC')
                ->cache(true, 3600)
                ->select();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看无运单号的订单列表');
        $this->assign("getData", $_GET);
        $this->assign("page", $result['page']);
        $this->assign("orderTotal", $result['orderTotal']);
        $this->assign("order_list", $result['order_list']);
        $this->assign("shipping", $shipping);
        $this->assign("allProduct", $allProduct);
        $this->assign('domains', $domains);
        $this->assign('department',$department);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /**
     * 统计指定时间内的指定产品的销售数据
     */
    public function statistics() {
        //默认显示
        //昨天订单
        //状态[未配货][配货中]
        $where = array();
        //所属仓库只能看到所属仓库的订单
        $belong_ware_id = $_SESSION['belong_ware_id'];
        if (count($belong_ware_id) != 1 || (count($belong_ware_id) == 1 && $belong_ware_id[0] != 1)) {
            $hwhere['id_warehouse'] = array('IN', $belong_ware_id);
            $where['id_warehouse'] = array('IN', $belong_ware_id);
        }
        $time_start = I('post.time_start', date('Y-m-d 00:00', strtotime('-1 day')));
        $time_end = I('post.time_end', date('Y-m-d 00:00'));
        $_POST['time_start'] = $time_start;
        $_POST['time_end'] = $time_end;
        $status_id = I('post.status_id');
        $shippingId = I('post.shipping_id');
        $department_id = I('post.department_id');
        $warehouse_id = I('post.warehouse_id');
        $zone_id = I('zone_id');
        if ($shippingId) {
            $where[] = "`id_shipping` = '$shippingId'";
        }
        if($department_id) {
            $where[] = "`id_department` = '$department_id'";
        }
        if($zone_id) {
            $where[] = "`id_zone` = '$zone_id'";
        }
        if($warehouse_id) {
            $where['id_warehouse'] = $warehouse_id;
        }
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();
        if ($status_id > 0) {
            $where[] = "`id_order_status` = '$status_id'";
        } else {
            $where[] = "`id_order_status` IN (".implode(',', $effective_status).")"; //只需要有效订单
        }
        if ($time_start)
            $where[] = "`created_at` >= '$time_start'";
        if ($time_end)
            $where[] = "`created_at` < '$time_end'";

        $department = M('Department')->where(array('type=1'))->select();
        $warehouse = M('Warehouse')->where($hwhere)->select();
        $pro_result = D('Common/Product')->select();
        $products = array();
        foreach ($pro_result as $product) {
            $products[(int) $product['id_product']] = $product;
        }
        $result = D('Common/Shipping')->select();
        $shippings = array();
        foreach ($result as $shipping) {
            $shippings[$shipping['id_shipping']] = $shipping;
        }

        $order_model = D('Order/Order');
        $order_table = $order_model->getTableName();
        $orders = $order_model
                ->field($order_table . '.id_order AS order_id, id_order_status,id_shipping, i.id_product,i.id_product_sku, i.quantity,
             i.product_title,i.sku_title, i.id_order_item order_item_id,i.sku')
                ->join("__ORDER_ITEM__ i ON (__ORDER__.id_order = i.id_order)", 'LEFT')
                ->where($where)
                ->order('i.sku ASC')
                ->select();

//        $count = 0;
//        $stat = array();
//        $stat_shipping = array();
//        $stat_product = array();
        $order_count = array(); //订单总数
        $product_count = 0; // 产品总数
//        $tempProModel = array();

        foreach ($orders as $key=>$val){
            $order_count[] = $val['order_id'];
            $product_count += $val['quantity'];
            $product_name = $products[(int) $val['id_product']]['inner_name'];
            if (empty($product_name)) $product_name = $products[(int) $val['id_product']]['title'];
            $img = json_decode($products[(int) $val['id_product']]['thumbs'], true);
            $orders[$key]['pro_name'] = $product_name;
            $orders[$key]['img'] = $img['photo'][0]['url'];
        }

        $results = $this->get_arr($orders);

//        foreach ($orders as $o) {
//            $order_count[] = $o['order_id'];
//            if (isset($shippings[$o['id_shipping']]))
//                $shipping_name = $shippings[$o['id_shipping']]['title'];
//            else
//                $shipping_name = '无物流';
//
////            if ((int)$o['parent_product_id'] > 0)
////                $product_name = $products[(int)$o['parent_product_id']]['inner_name'];
////            else
////                $product_name = $products[(int)$o['product_id']]['inner_name'];
////            if (empty($product_name)) {
////                if ((int)$o['parent_product_id'] > 0)
////                    $product_name = $products[(int)$o['parent_product_id']]['title'];
////                else
////                    $product_name = $products[(int)$o['product_id']]['title'];
////            }
//            $img = json_decode($products[(int) $o['id_product']]['thumbs'],true);
//            //直接使用产品的内部名称
//            $product_name = $products[(int) $o['id_product']]['inner_name'];
//            if (empty($product_name))
//                $product_name = $products[(int) $o['id_product']]['title'];
//
//            if (!isset($stat[$shipping_name][$product_name])){
//                $stat[$shipping_name][$product_name] = array();
//            }
//
//            $attrIdMd5 = '';
//            if (!isset($stat[$shipping_name][$product_name][$o['sku_title']])) {
//                $stat[$shipping_name][$product_name][$o['sku_title']]['qty'] = (int) $o['quantity'];
//            } else {
//                $stat[$shipping_name][$product_name][$o['sku_title']]['qty'] += (int) $o['quantity'];
//            }
//            $stat[$shipping_name][$product_name][$o['sku_title']]['img'] = $img['photo'][0]['url'];
//            $stat[$shipping_name][$product_name][$o['sku_title']]['sku'] = $o['sku'];
//
//            $attrIdMd5 = md5($product_name . $o['sku_title']);
//
//            if ($o['id_product_sku']) {
//                $getSkuModel = D("Common/ProductSku")->cache(true, 3600)->find($o['id_product_sku']);
//                $tempProModel[$attrIdMd5] = $getSkuModel['sku'];
//            } else {
//                $getSkuModel = D("Common/ProductSku")->cache(true, 3600)->find($o['id_product']);
//                $tempProModel[$attrIdMd5] = $getSkuModel['sku'];
//            }
//
//            $product_count += (int) $o['quantity'];
//        }
//
//        foreach ($stat as $sp_name => $p_s) {
//            ksort($stat[$sp_name]);
//        }
//        //计算物流与产品数
//        foreach ($stat as $sp_name => $p_s) {
//            if (!isset($stat_shipping[$sp_name]))
//                $stat_shipping[$sp_name] = 0;
//            foreach ($p_s as $p_name => $p_pro) {
//                if (!isset($stat_product[$sp_name . $p_name]))
//                    $stat_product[$sp_name . $p_name] = 0;
//                $stat_product[$sp_name . $p_name] += count($p_pro);
//                $stat_shipping[$sp_name] += count($p_pro);
//            }
//        }

        $order_count = array_unique($order_count);
        $order_count = count($order_count);

        $status_model = D('Order/OrderStatus')->field('id_order_status,title')->where('status=1 and id_order_status IN (4,5,6,7,8,9,10)')->getField('id_order_status,title',true);
        $zone = M('Zone')->cache(true,84600)->getField('id_zone,title');
        add_system_record(sp_get_current_admin_id(), 4, 1, '查看仓库货品统计列表');
        $this->assign('shippings', $shippings);
        $this->assign('status_list', $status_model);
        $this->assign('statistics', $results);
        $this->assign('zone',$zone);
//        $this->assign('stat_shipping', $stat_shipping);
//        $this->assign('stat_product', $stat_product);
        $this->assign('product_count', $product_count);
        $this->assign('order_count', $order_count);
        $this->assign('post', $_POST);
//        $this->assign('attr_sku', $tempProModel);
        $this->assign('department',$department);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /**
     * 处理数组相加
     */
    protected function get_arr($array) {
        $item=array();
        foreach($array as $res_k=>$res_v){
            if(!isset($item[$res_v['id_product_sku']])){
                $item[$res_v['id_product_sku']]=$res_v;
            }else{
                $item[$res_v['id_product_sku']]['quantity']+=$res_v['quantity'];
            }
        }
        return $item;
    }

    /**
     * 仓储部: 统计订单<p>
     * 统计指定物流, 时间段, 订单状态的数量
     * </p>
     */
    public function statistics_order() {
        //默认显示
        //所有物流下面每个订单状态的数量
        $where = array();
        $time_start = I('post.time_start', date('Y-m-d 00:00', strtotime('-1 day')));
        $time_end = I('post.time_end', date('Y-m-d 00:00'));
        $_POST['time_start'] = $time_start;
        $_POST['time_end'] = $time_end;
        $status_id = I('post.status_id');
        $shipping_id = I('post.shipping_id');
        $department_id = I('post.department_id');
        $warehouse_id = I('post.warehouse_id');
        if ($shipping_id) {
            $where[] = "`id_shipping` = '$shipping_id'";
        }
        if ($status_id > 0) {
            $where[] = "`id_order_status` = '$status_id'";
        } else {
            $where[] = "`id_order_status` IN (4, 5, 6, 7)";
        }
        if($department_id) {
            $where[] = "`id_department` = '$department_id'";
        }
        if($warehouse_id) {
            $where[] = "`id_warehouse` = '$warehouse_id'";
        }

        $where[] = "`created_at` >= '$time_start'";
        $where[] = "`created_at` < '$time_end'";

        $department = M('Department')->where('type=1')->select();
        $warehouse = M('Warehouse')->select();
        $result = D('Common/Shipping')->select();
        $shippings = array();
        foreach ($result as $shipping) {
            $shippings[$shipping['id_shipping']] = $shipping;
        }

        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }

        $order_model = D('Order/Order');
        $order_table = $order_model->getTableName();
        $orders = $order_model
                ->field($order_table . '.id_order AS order_id, id_shipping, id_order_status, order_count')
                //->join("__ORDER_ITEM__ i ON (__ORDER__.id = i.order_id)", 'INNER')
                //->join("__ORDER_ITEM_OPTION__ opt ON (i.id = opt.order_item_id)", 'LEFT')
                //->join("__PRODUCT_OPTION_VALUE__ ov ON (opt.option_id = ov.id)")
                ->where($where)
                //->fetchSql(true)
                ->select();
//        dump($orders);die;
        $stat_shipping = array(); //统计物流下的各订单数据
        $stat_status = array(); //统计所有物流下各订单数据
        $order_total = 0; //总订单数

        foreach ($orders as $o) {
            $shipping_name = isset($shippings[$o['id_shipping']]) ? $shippings[$o['id_shipping']]['title'] : '无物流';
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '无状态';
            if (!isset($stat_shipping[$shipping_name])) {
                $stat_shipping[$shipping_name] = array();
            }
            
            if (!isset($stat_shipping[$shipping_name][$status_name])) {
                $stat_shipping[$shipping_name][$status_name] = 1;
            } else {
                $stat_shipping[$shipping_name][$status_name] += 1;
            }
            if (!isset($stat_status[$status_name])) {
                $stat_status[$status_name] = 1;
            } else {
                $stat_status[$status_name] += 1;
            }
            $order_total++;
        }
        
        add_system_record(sp_get_current_admin_id(), 4, 1, '查看仓库订单统计列表');
        $this->assign('order_total', $order_total);
        $this->assign('shippings', $shippings);
        $this->assign('stat_shipping', $stat_shipping);
        $this->assign('stat_status', $stat_status);
        $this->assign('post', $_POST);
        $this->assign('department',$department);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }
    
    public function status_update(){
        try{
            $orderIds = is_array($_POST['order_id'])?$_POST['order_id']:array($_POST['order_id']);
            $action  = $_POST['order_action'];
            switch($action){
                case 1: $statusId = 4;$comment = '批量操作《未配货》'; break;
                case 2: $statusId = 5;$comment = '批量操作《配货中》'; break;
                case 3: $statusId = 7;$comment = '批量操作《已配货》'; break;
                case 4: $statusId = 6;$comment = '批量操作《缺货》'; break;
            }
            if($orderIds&& is_array($orderIds)){
                foreach($orderIds as $id){
                    D("Order/Order")->where('id_order='.$id)->save(array('id_order_status'=>$statusId));
                    D("Order/OrderRecord")->addHistory($id,$statusId,4,$comment);
                }
            }
            $status= 1; $message ='';
        }catch (\Exception $e){
           $status= 0; $message = $e->getMessage();
        }
        $return = array('status'=>$status,'message'=>$message);
        echo json_encode($return);exit();
    }

    /**
     * 更新状态,一行一个, tab分割列
     */
    public function update_status() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $status = array(
            7 => '已配货',
            6 => '缺货'
        );
        $total = 0;
        $ordShip = D('Common/OrderShipping');
        if (IS_POST) {
            $data = I('post.data');
            $data = $this->getDataRow($data);
            //导入记录到文件
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $setPath = './'.C("UPLOADPATH").'warehouse'."/";
            if(!is_dir($setPath)){
                mkdir($setPath,0777,TRUE);
            }
            $logTxt = $_POST['settle_date'].PHP_EOL.$data;
            $getPathFile = $setPath.$user_id.'_'.date('Y_m_d_H_i_s').'.txt';
            file_put_contents($getPathFile,$logTxt,FILE_APPEND);

            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 1);
                $selectShip = $ordShip->where('track_number='.$row[0])->find();

                if($selectShip  && $selectShip['id_order']){
                    $order_id = $selectShip['id_order'];
                    $today = date('Y-m-d H:i:s');
                    D("Order/Order")->where('id_order='.$order_id)->save(array('id_order_status' => 8,'date_delivery'=>$today));
                    D("Order/OrderRecord")->addHistory($order_id, 8,4,'批量导入配送中');
                    $infor['success'][] = sprintf('第%s行: 订单号:%s 更新状态: %s', $count++, $order_id, $row[0]);
                }else{
                    $infor['error'][] = sprintf('第%s行: 格式不正确,没有找到订单', $count++);
                }
            }
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '更新状态');
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    public function track_number_update() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Common/OrderShipping');
        if (IS_POST) {
            $data = I('post.data');
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                if ($row[0]) {
                    $actionID = (int) $_POST['order_action'];
                    $selectShip = $ordShip->where('track_number=' . $row[0])->find();
                    if ($selectShip && $actionID && $selectShip['id_order']) {
                        $order_id = $selectShip['id_order'];
                        $today = date('Y-m-d H:i:s');
                        $updateData = array('id_order_status' => $actionID, 'date_delivery' => $today);
                        D("Order/Order")->where('id_order=' . $order_id)->save($updateData);
                        D("Order/OrderRecord")->addHistory($order_id, $actionID,4,'根据运单号更新状态'.$row[0]);
                        $info['success'][] = sprintf('第%s行: 订单号:%s 更新状态: %s', $count++, $order_id, $row[0]);
                    } else {
                        $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，没有找到订单', $count++, $row[0]);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '根据运单更新订单状态');
        $this->assign('infor', $info);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display('track_number_update');
    }

    public function update_shipping() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordObj = D("Order/Order");
        if (IS_POST) {
            $data = I('post.data');
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $order_id = (int) $row[0];
                if ($order_id) {
                    $orderObj = $ordObj->find($order_id);
                    if ($orderObj) {
                        $ordObj->where('id_order=' . $order_id)->save(array('id_shipping' => $_POST['shipping_id']));
                        D("Order/OrderRecord")->addHistory($order_id, $orderObj['id_order_status'], '更新物流'.$row[0]);
                        $info['success'][] = sprintf('第%s行: 订单号:%s 更新状态: %s', $count++, $order_id, $row[0]);
                    } else {
                        $info['error'][] = sprintf('第%s行: 没有找到订单', $count++);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 没有找到订单', $count++);
                }
            }
        }
        add_system_record(sp_get_current_admin_id(), 2, 3, '更新物流');
        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
        $this->assign('shipping', $shipping);
        $this->assign('infor', $info);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    
    public function untracknumber_excel()
    {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();
        
        $column = array(
            '地区', '域名', '订单号', '姓名', '电话号码', '邮箱',
            '产品名和价格', '属性', '总价（NTS）', '产品数量',
            '送货地址', '订单数量', '留言备注', '下单时间', '订单状态', '订单数',
            '发货日期'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        
        $status = array('IN',array(4, 5, 6, 7));
        $result = $this->getUntracknumberOrders($status, true);
        $orders = $result['order_list'];
        
        $result = D('Order/OrderStatus')->cache(true, 3600)->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int)$statu['id_order_status']] = $statu;
        }
        /** @var \Common\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        foreach ($orders as $o) {
            $product_name = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $web = D('Common/Domain')->field('name')->where(array('id_domain'=>$o['id_domain']))->find();
            $qty = 0;
            foreach ($products as $p) {
                $product_name .= $p['product_title']."\n";
                $qty += $p['quantity'];
                if (isset($p['order_attrs'])) {
                    //有属性的产品
                    foreach ($p['order_attrs'] as $a) {
                        unset($a['number']);
                        foreach ($a as $av) {
                            $attrs .= $av['title'] . ' x ';
                        }
                        $attrs .= $p['qty'] . "\n";
                    }
                } else {
                    //无属性的产品, 解决智能手环问题
                    $attrs .= '+'.$p['title'] . ' x ' . $p['quantity'] . "\n";
                }
            }
            $attrs = trim($attrs, '+');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
            $data = array(
                $o['province'], $web['name'], $o['id_order'], $o['first_name'], $o['tel'], $o['email'],
                $product_name, $attrs, $skuString, $o['price_total'], $qty,
                $address, $o['order_count'], $o['remark'], $o['created_at'], $status_name, $o['order_repeat'],
                ''
            );
            $j = 65;
            foreach ($data as $col) {
                $excel->getActiveSheet()->setCellValue(chr($j).$idx, $col);
                ++$j;
            }
            ++$idx;
        }
        
        $excel->getActiveSheet()->setTitle(date('Y-m-d').'无运单号的订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '无运单号的订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
    }

    /**
     * 更新运单号.格式:订单号,
     */
    public function update_track() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
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
                if (strpos($row[0], '-') && strpos($row[0], '-1/') === false) {
                    //跳过一单多产品的其余订单
                    $infor['warning'][] = sprintf('第%s行: 订单号:%s 跳过多单号更新.', $count++, $row[0]);
                    continue;
                }
                list($order_id, ) = explode('-', $row[0]);
                //$name = trim($row[2]);
                $track_number = str_replace("'", '', $row[1]);
                $track_number = str_replace('"', '', $track_number);
                $track_number = trim($track_number);
                //查找全局是否有重复运单号
                $finded = D('Common/OrderShipping')
                        ->field('id_order, track_number')
                        ->where(array(
                            'track_number' => $track_number
                        ))
                        ->find();
                if ($finded) {
                    $infor['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 运单号已经存在. 订单号:%s', $count++, $order_id, $track_number, $finded['order_id']);
                    continue;
                }
                //TODO: 可以从OrderShpping复制一条记录
                $order = D('Order/Order')
                        ->field('id_order, first_name, tel, id_shipping, date_delivery, id_order_status')
                        ->where('id_order=' . (int) $order_id)
                        ->find();
                if (!$order) {
                    $infor['error'][] = sprintf('第%s行: 订单号:%s 不存在.', $count++, $order_id);
                    continue;
                }
                //TODO: 没有运单号时添加一条记录, 但是在分配物流时已经加了一条记录.冗余代码
                //TODO: 如果一个订单有多个运单号时, 必须在这里添加一条新记录
                $shipping_info = D('Common/OrderShipping')
                        ->field('id_order_shipping, track_number')
                        ->where(array(
                            'id_order' => $order['id']
                        ))
                        ->select();
                //TODO: 修改OrderShipping的逻辑, 只有在更新运单号时直接写入运单号信息即可,不用在分配物流时写入
                $updated = false;
                foreach ($shipping_info as $ship) {
                    if (empty($ship['track_number'])) {
                        //更新一个后退出
                        D('Common/OrderShipping')
                                ->save(array(
                                    'id_order_shipping' => $ship['id'],
                                    'track_number' => $track_number,
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'id_shipping' => $_POST['shipping_id'],
                        ));
                        $updated = true;
                        //TODO: 导入运单号后更新订单状态为已配货(20)
                        D('Order/Order')->save(array(
                            'id_order' => $order['id'],
                            'id_shipping' => $_POST['shipping_id'],
                            'id_order_status' => 7
                        ));
                        D("Order/OrderRecord")
                            ->addHistory($order['id'], 7, 4,'导入运单号 '.$track_number);
                        break;
                    }
                }
                if (!$updated) {
                    //新的运单号
                    D('Common/OrderShipping')
                            ->add(array(
                                'id_order' => $order_id,
                                'id_shipping' => $_POST['shipping_id'],
                                'shipping_name' => '', //TODO: 加入物流名称
                                'track_number' => $track_number,
                                'is_email' => 0,
                                'status_label' => '',
                                'date_delivery' => $order['delivery_date'],
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                    ));
                    $updated = true;
                    //TODO: 导入运单号后更新订单状态为配送中(3)
                    D('Order/Order')->save(array(
                        'id_order' => $order['id'],
                        'id_shipping' => $_POST['shipping_id'],
                        'id_order_status' => 8
                    ));
                    D("Order/OrderRecord")
                        ->addHistory($order['id'], 8, 4,'导入运单号 '.$track_number);
                }

                $infor['success'][] = sprintf('第%s行: 订单号:%s 更新运单号: %s', $count++, $order_id, $track_number);
            }
        }
        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
        add_system_record(sp_get_current_admin_id(), 2, 3, '更新运单号');
        $this->assign('shipping', $shipping);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    private function getUntracknumberOrders($status = false, $show_all = false) {
        $getFormWhere = array();
        $getFormWhere['id_order_status'] = $status;
        $web_url = I('get.web_url');
        $shipping_id = I('get.shipping_id');
        $keyword = I('get.keyword');
        $department_id = I('get.department_id');
        $warehouse_id = I('get.warehouse_id');
        $id_increment = I('get.id_increment');

        if ($web_url)
            $getFormWhere['web_url'] = $web_url;
        if ($shipping_id > 0)
            $getFormWhere['o.id_shipping'] = $shipping_id;
        if($department_id > 0)
            $getFormWhere['o.id_department'] = $department_id;
        if($warehouse_id > 0)
            $getFormWhere['o.id_warehouse'] = $warehouse_id;
        $deliveryDate = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $start_time = I('get.start_time', date('Y-m-d 00:00', strtotime('-1 day')));
            $deliveryDate[] = array('EGT', $start_time);
        }
        if(!empty($id_increment)) {
            $getFormWhere['o.id_increment'] = $id_increment;
        }

        if (isset($_GET['end_time']) && $_GET['end_time']) {
            $end_time = I('get.end_time');
            $deliveryDate[] = array('LT', $end_time);
        }
        if ($deliveryDate) {
            $getFormWhere['date_delivery'] = $deliveryDate;
        }
//        $_SESSION['department_id'] ? $getFormWhere['id_department'] = array('IN',$_SESSION['department_id']) : '';
        $model = D('Order/Order');
        if ($keyword) {
            $getFormWhere['o.id_order'] = array('LIKE', "%$keyword%");
            $getFormWhere['first_name'] = array('LIKE', "%$keyword%");
            $getFormWhere['tel'] = array('LIKE', "%$keyword%");
            $getFormWhere['address'] = array('LIKE', "%$keyword%");
            $getFormWhere['track_number'] = array('LIKE', "%$keyword%");
            $getFormWhere['remark'] = array('LIKE', "%$keyword%");
            $getFormWhere['email'] = array('LIKE', "%$keyword%");
            $getFormWhere['o.id_increment'] = array('LIKE', "%$keyword%");
        }
        $getFormWhere[] = "(s.track_number IS NULL OR s.track_number='')";
        $count = $model->alias('o')
                        ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                        ->where($getFormWhere)->count();

        $page = $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
        $page = $this->page($count, $page);
        $orderList = $model
                ->field('o.*, s.track_number')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->where($getFormWhere)
                ->order("date_delivery asc,tel DESC,first_name desc,email desc");
        if (!$show_all) {
            $orderList = $orderList->limit($page->firstRow, $page->listRows);
        }
        $orderList = $orderList->select(array(
            'alias' => 'o'
        ));
        $order_item = D('Order/OrderItem');
        foreach ($orderList as $key => $o) {
            $orderList[$key]['products'] = $order_item->get_item_list($o['id_order']);
        }

        $returnArr = array(
            'page' => $page->show('Admin'),
            'orderTotal' => $count,
            'order_list' => $orderList,
            'product' => D("Common/product")->getAllProduct(),
        );
        return $returnArr;
    }
    
    public function  waitdelivery_telphone(){
        $this->waitdelivery(1,$_GET);
    }

    /**
     * 未配货订单，发货人员根据物流发货
     * @see OrdercheckController:waitdelivery2
     */
    public function waitdelivery($telphone=0,$tel_get=array()) {
        /* @var $ord_model \Common\Model\OrderModel */
        $M = new \Think\Model;
        $ord_model = $this->orderModel;
        $default_order_status = array(4);
        $id_order_status = I('get.id_order_status');
        if ($id_order_status > 0) { 
            $default_order_status = array((int)$id_order_status);
        }
        if($telphone==1){
            $_GET=$tel_get;
        }
        $where = $ord_model->form_where($_GET);

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

        if(isset($_GET['sku_keyword']) && $_GET['sku_keyword']) {
            $ordName = D("Order/Order")->getTableName();
            $ordIteName = D("Order/OrderItem")->getTableName();
            $order_ids = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where(array('oi.sku'=>array('LIKE', '%' . $_GET['sku_keyword'] . '%'),array('o.id_order_status'=>array('IN', $default_order_status))))
                ->group('oi.id_order')->select();
            $order_ids = array_column($order_ids, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);  
            } else {
                $where['id_order'] = array('IN', array(0)); 
            }
        }
        $where['id_order_status'] = array('IN', $default_order_status);
        if(isset($_GET['payment_method']) && $_GET['payment_method']){
            switch($_GET['payment_method']){
                case '1':case 1:
                $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
                break;
                case 2:
                case '2':
                    $where['_string'] = "o.payment_method !='0'";
                    break;
            }
        }
        if(isset($_GET['price']) && $_GET['price']){
//            $where['o.price_total'] = $_GET['price']==2?array('LT', 1):array('GT', 0);
            if($_GET['price']==1){$where['o.price_total']=array('GT', 0);}
            if($_GET['price']==2){$where['o.price_total']=array('LT', 1);}
            if($_GET['price']==1381){$where['o.price_total']=array('GT', 1380);}
            if($_GET['price']==1379){$where['o.price_total']=array('elt', 1380);}
            
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
            $owhere['o.id_order_status'] = array('IN',$default_order_status);
            if (isset($order_ids) && !empty($order_ids))
            {
                $owhere['o.id_order'] = array('IN',$order_ids);
            }
            else if(isset($order_ids) && empty($order_ids))
            {
                $owhere['o.id_order'] = array('IN',array(0));
            }
            $order_ids = M('Order')->alias('o')->join('__ORDER_ITEM__ oi ON o.id_order=oi.id_order','LEFT')->field('o.id_order')->where($owhere)->group('oi.id_order')->having($having)->select();
            $order_ids = array_column($order_ids, 'id_order');
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
            $pro_where['o.id_order_status'] = array('IN', $default_order_status);
            if (isset($order_ids) && !empty($order_ids))
            {
                $pro_where['o.id_order'] = array('IN',$order_ids);
            }
            else if(isset($order_ids) && empty($order_ids))
            {
                $pro_where['o.id_order'] = array('IN',array(0));
            }

            $order_ids = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where($pro_where)->group('id_order')->select();
            $order_ids = array_column($order_ids, 'id_order');
            if($order_ids) {
                $where['id_order'] = array('IN', $order_ids);
            } else {
                $where['id_order'] = array('IN', array(0));
            }
        }

        if ($_GET['product_id']) {
            $ordName = D("Order/Order")->getTableName();
            $ordIteName = D("Order/OrderItem")->getTableName();
            if (isset($order_ids) && !empty($order_ids))
            {
                $order_item_where['o.id_order'] = array('IN',$order_ids);
            }
            else if(isset($order_ids) && empty($order_ids))
            {
                $order_item_where['o.id_order'] = array('IN',array(0));
            }
            $order_item_where['oi.id_product'] = array('EQ',$_GET['product_id']);
            $order_item_where['o.id_order_status'] = array('IN',$default_order_status);
            $order_ids = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where($order_item_where)
                ->group('oi.id_order')->select();
            $order_ids = array_column($order_ids, 'id_order');
            $where['id_order'] = $order_ids ? array('IN', $order_ids) : array('EQ', 0);
        }

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

        $baseSql = $this->orderModel->alias('o')->where($where);
        $count = $this->orderModel->alias('o')->where($where)->count();
        $todayDate = date('Y-m-d');
        $todayTotal = $this->orderModel->alias('o')->where($where)->where(array('created_at' => array('like', $todayDate . '%')))->count();
        $formData = array();
        $formData['web_url'] = D('Domain/Domain')
            ->field('`name` web_url')
            ->order('`name` ASC')
            ->cache(true, 3600)
            ->select();
        $page = $this->page($count, 100);
        $order_list = $this->orderModel->alias('o')->where($where)->order("created_at asc,tel DESC,first_name desc,email desc")
            ->limit($page->firstRow, $page->listRows)->select();
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $currency_data = UpdateStatusModel::get_currency();
        foreach ($order_list as $key => $o) {
            if ($currency_data[$o['currency_code']]['left'])
            {
                $order_list[$key]['price_total'] = $currency_data[$o['currency_code']]['currency_code'].$order_list[$key]['price_total'];
            }
            else
            {
                $order_list[$key]['price_total'] = $order_list[$key]['price_total'].$currency_data[$o['currency_code']]['currency_code'];
            }
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $sku = M('OrderItem')->field('sku')->where(array('id_order'=>$o['id_order']))->order('sku ASC')->select();            
            $sku = array_column($sku, 'sku');
            $order_list[$key]['sku'] =  $sku?implode('<br>', $sku):'';
            $pro_ids = M('OrderItem')->field('id_product')->where(array('id_order'=>$o['id_order']))->group('id_product')->order('sku ASC')->select();
            $pro_id = array_column($pro_ids, 'id_product');
            if($pro_id){
                $pro_result = M('Product')->field('foreign_title')->where(array('id_product'=>array('IN',$pro_id)))->select();
                $pro_foreign_title = array_column($pro_result, 'foreign_title');
                $order_list[$key]['foreign'] =  $pro_foreign_title?implode('<br>', $pro_foreign_title):'';
            }

        }

        $shipping = D("Common/Shipping")->field('id_shipping,title')->cache(true, 600)->select();
        $shipTemp = array();
        if (is_array($shipping)) {
            foreach ($shipping as $ship) {
                $shipTemp[$ship['id_shipping']] = $ship['title'];
            }
        }

        $order_count_text = '';
        $department = M('Department')->where('type=1')->order('title ASC')->select();
        $department_id = array_column($department,'id_department');
        $department_ids = M('Department')->where('type=1')->order('department_code ASC')->getField('id_department',true);
        unset($where['id_department']);
        $all_depart_count = M('Order')->alias('o')->field('id_department,count(id_order) as order_count')
            ->where($where)->where(array('id_department'=>array('IN',$department_ids)))->group('id_department')->order("field(id_department,'".implode("','",$department_ids)."')")->select();
        foreach($all_depart_count as $all) {
            $department_name = M('Department')->where(array('id_department'=>$all['id_department']))->order('department_code ASC')->Field('department_code,title')->find();
            $department_str=$department_name['title'].'-'.$department_name['department_code'];
            $order_count_text .= $department_str.':<span style="color:red">'.$all['order_count'].'</span>&nbsp&nbsp';
        }

        $hwhere['status'] = 1;
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where($hwhere)->select();
//        var_dump(M('Warehouse')->getLastSql());die();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $classify = M('ProductClassify')->cache(true, 86400)->select();
        $zones = M('Zone')->cache(true, 86400)->select();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看仓库未配送订单');
        $this->assign('warehouse',$warehouse);
        $this->assign('department',$department);
        $this->assign('zones', $zones);
        $this->assign("getData", $_GET);
        $this->assign("form_data", $formData);
        $this->assign("page", $page->show('Admin'));
        $this->assign("todayTotal", $todayTotal);
        $this->assign("orderTotal", $count);
        //$this->assign("todayWebData", $allWebTotal);
        $this->assign("order_list", $order_list);
        $this->assign("shipping", $shipTemp);
        $this->assign('classify',$classify);
        $this->assign('order_count_text',$order_count_text);
        //$this->assign("allProduct", $allProduct);
        
        $this->display();
    }

    /**
     * 未配货列表导出配送订单
     */
    public function export_order_list2(){
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        /* @var $orderItem \Common\Model\OrderItemModel */
        $orderItem = D('Order/OrderItem');
        $ordShiTable = D('Order/OrderShipping')->getTableName();
        $shiTable = D('Common/Shipping')->getTableName();
        $ordTable = D("Order/Order")->getTableName();
        $M = new \Think\Model;

        $where = array();
        $default_order_status = array(4,5);
        $id_order_status = I('get.id_order_status');

        if ($id_order_status > 0) {
            $default_order_status = array((int)$id_order_status);
        }

        $ordShiTable = D("Order/OrderShipping")->getTableName();
        $selOrder = $M->table($ordTable . ' AS o LEFT JOIN ' . $ordShiTable . ' AS os ON o.id_order=os.id_order')->field('o.id_order')
            ->where("(os.track_number = '' OR  os.track_number IS NULL)")
            ->where(array('o.id_order_status' => array('IN', $default_order_status)))
            ->group('os.id_order')->cache(true,3600)->select();

        if($selOrder){
            $getAllId = $selOrder ? array_column($selOrder, 'id_order') : '';
            $getOrderId = implode(',',$getAllId);
            //$where[] = "o.id_order in ($getOrderId)";
        }else{
            $this->error("没有数据");
        }
        if(isset($_GET['start_time']) && $_GET['start_time']){
            $time_start = I('get.start_time', date('Y-m-d 00:00:00', strtotime('-1 day')));
            $time_end = I('get.end_time', date('Y-m-d 00:00:00'));
            $_GET['start_time'] = $time_start;
            $_GET['end_time'] = $time_end;
            $where['delivery_date'] = $time_start;//create_at
            $where['delivery_date'] = $time_end;
        }

        if (isset($_GET['id_shipping']) && $_GET['id_shipping'] > 0) {
            $getShippingId = (int)$_GET['id_shipping'];
            $where['id_shipping'] = $getShippingId;
        }
        $field = 'o.*,oi.id_product,oi.id_product_sku,oi.sku,oi.sku_title,oi.sale_title,oi.product_title,oi.quantity';
        $field .= ',s.title as shipping_name,s.channels,s.account';
        $select_all = $ordModel->alias('o')->field($field)
            ->join($orderItem->getTableName().' AS oi ON (o.id_order = oi.id_order)')
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
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");//vendor("PHPExcel.PHPExcel.Writer.CSV");
        $columns = array(
            '发货日期', '订单号', '状态', '物流公司', '网络渠道', '类型', '订单重量', '运单号', '物流账号',
            '收件人', '收件人电话', '物品名称', '代收款',  '属性', '物品数量', '留言','备注', 'SKU', '地址'
        );
        $excel = new \PHPExcel();
        $j = 65;$col_number = 1;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }

        if($order_list){
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
                    $product_name[] =  $product['product_title'];
                    $attr_value[]   = $product['sku_title'].' x '.$product['quantity'];
                    $total_qty +=$product['quantity'];
                    $sku[] =  $product['sku'];
                }
                $product_title = $product_name?implode(' ; ', $product_name):'';
                $attr_value = $attr_value?implode(' ; ', $attr_value):'';
                $sku  = $sku?implode(' ; ', $sku):'';
                $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
                $data = array(
                    date('Y-m-d', strtotime($o['delivery_date'])),
                    "'".$o['id_increment'], '配货中', $o['shipping_name'], $o['channels'],
                    $o['province'].'件', '', '', $o['account'],
                    $o['first_name'].' '.$o['last_name'], $o['tel'], $product_title, $o['price_total'],$attr_value,
                    $total_qty, $o['remark'], $o['comment'], $sku, $address
                );
                $j = 65;
                foreach ($data as $col) {
                    $excel->getActiveSheet()->setCellValue(chr($j).$col_number, $col);
                    ++$j;
                }
            }
        }

        $excel->getActiveSheet()->setTitle(date('Y-m-d').'无运单号的订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '无运单号的订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit;

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
        $ord_model = $this->orderModel;
        $where = array();
        $where = $ord_model->form_where($_GET,'o.');
        $time_start = I('get.start_time');
        $time_end = I('get.end_time');
        $_GET['start_time'] = $time_start;
        $_GET['end_time'] = $time_end;
        $id_zone = I('get.zone_id');
        $id_department = I('get.department_id');
        $id_warehouse = I('get.id_warehouse');
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
//        if (trim($_GET['keyword'])) {
//            $filter = array();
//            $keyword = $_GET['keyword'];
//            $filter['o.id_increment'] = array('LIKE', '%' . $keyword . '%');
//            $filter['o.id_domain'] = array('LIKE', '%' . $keyword . '%');
//            $filter['o.first_name'] = array('LIKE', '%' . $keyword . '%');
//            $filter['o.tel'] = array('LIKE', '%' . $keyword . '%');
//            $filter['o.address'] = array('LIKE', '%' . $keyword . '%');
//            $filter['o.email'] = array('LIKE', '%' . $keyword . '%');
//            $filter['o.remark'] = array('LIKE', '%' . $keyword . '%');
//            $filter['_logic'] = 'or';
//            $where['_complex'] = $filter;
//        }
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
        if(isset($_GET['id_classify']) && $_GET['id_classify']) {
            $ordIteName = M("OrderItem")->getTableName();
            $ordName = M("Order")->getTableName();

            $product_ids = M('Product')->field('id_product')->where(array('id_classify' => array('IN', $_GET['id_classify'])))->select();
            $product_id = array_column($product_ids, 'id_product');
            $product_id ? $pro_where['oi.id_product'] = array('IN', $product_id) : $pro_where['oi.id_product'] = array('IN', array(0));
            $pro_where['o.id_order_status'] = array('IN', $default_order_status);
            if (isset($order_ids) && !empty($order_ids))
            {
                $pro_where['o.id_order'] = array('IN',$order_ids);
            }
            else if(isset($order_ids) && empty($order_ids))
            {
                $pro_where['o.id_order'] = array('IN',array(0));
            }

            $order_ids = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where($pro_where)->group('id_order')->select();
            $order_ids = array_column($order_ids, 'id_order');
            if($order_ids) {
                $where['o.id_order'] = array('IN', $order_ids);
            } else {
                $where['o.id_order'] = array('IN', array(0));
            }
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
            elseif(isset($order_ids) && empty($order_ids))
            {
                $owhere['o.id_order'] = array('IN',array(0));
            }
            $owhere['o.id_order_status'] = array('IN',$default_order_status);
            $orderids = M('Order')->alias('o')->join('__ORDER_ITEM__ oi ON o.id_order=oi.id_order','LEFT')->field('o.id_order')->where($owhere)->group('oi.id_order')->having($having)->select();
            $order_ids = array_column($orderids, 'id_order');
            if($order_ids) {
                $where['o.id_order'] = array('IN', $order_ids);
            } else {
                $where['o.id_order'] = array('IN', array(0));
            }
        }
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
                $where['o.id_order'] = array('IN', $order_ids);
            } else {
                $where['o.id_order'] = array('IN', array(0));
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
                $where['o.id_order'] = array('IN', $order_ids);
            } else {
                $where['o.id_order'] = array('IN', array(0));
            }
        }
        if(isset($_GET['price']) && $_GET['price']){
//            $where['o.price_total'] = $_GET['price']==2?array('LT', 1):array('GT', 0);
            if($_GET['price']==1){$where['o.price_total']=array('GT', 0);}
            if($_GET['price']==2){$where['o.price_total']=array('LT', 1);}
            if($_GET['price']==1381){$where['o.price_total']=array('GT', 1380);}
            if($_GET['price']==1379){$where['o.price_total']=array('elt', 1380);}            
        }
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        /* @var $orderItem \Common\Model\OrderItemModel */
        $orderItem = D('Order/OrderItem');
        $shiTable = D('Common/Shipping')->getTableName();
        $product_name = D('Product/Product')->getTableName();

        $field = 'o.*,oi.id_product,oi.id_product_sku,oi.sku,oi.sku_title,oi.sale_title,oi.quantity';//,oi.product_title
        $field .= ',s.title as shipping_name,s.channels,s.account,p.inner_name as product_title,p.foreign_title';
        $select_all = $ordModel->alias('o')->field($field)
            ->join($orderItem->getTableName().' AS oi ON (o.id_order = oi.id_order)', 'LEFT')
            ->join($product_name.' p ON (oi.id_product=p.id_product)', 'LEFT')
            ->join($shiTable.' s ON (o.id_shipping=s.id_shipping)', 'LEFT')
            ->where($where)->order('oi.id_product desc,oi.id_product_sku desc')->limit(5000)->select();

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
                'quantity'=>$item['quantity'],
                'foreign_title'=>$item['attrs_title']
            );
        }
        $columns=array(
        '地区', '物流', '订单号', '运单号', '姓名',
        '产品名', '外文产品名','属性','SKU', '总价（NTS）', '产品数量',
        '送货地址', '留言备注', '下单时间', '订单状态',
        '发货日期','后台备注', '付款方式', '付款状态','邮编','仓库','省份'
        );
        if($_GET['istelphone']==1){
            $columns=array(
            '地区', '物流', '订单号', '运单号', '姓名', '电话号码',
            '产品名', '外文产品名','属性','SKU', '总价（NTS）', '产品数量',
            '送货地址', '留言备注', '下单时间', '订单状态',
            '发货日期','后台备注', '付款方式', '付款状态','邮编','仓库','省份'
            );
        }
        
        $j = 65;$col_number = 1;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }

        if($order_list){
            $provincearray = D('Warehouse')->getAllProvinceENDesc();
            $all_domain= D('Common/Domain')->field('`name`,id_domain')->order('`name` ASC')->cache(true, 3600)->select();
            $all_domain = $all_domain?array_column($all_domain,'name','id_domain'):'';
            $all_zone = D('Common/Zone')->field('`title`,id_zone')->order('`title` ASC')->cache(true, 3600)->select();
            $all_zone = $all_zone?array_column($all_zone,'title','id_zone'):'';
            /** @var \Order\Model\OrderStatusModel $status_model */
            $status_model = D("Order/OrderStatus");
            $all_status = $status_model->get_status_label();
            $currency_data = UpdateStatusModel::get_currency();

            foreach($order_list as $o){
                $col_number++;
                $id_order = $o['id_order'];
                $products = $temp_product[$id_order];
                $total_qty = 0;
                $product_name = array();
                $foreigns_title = array();
                $attr_value = array();
                $sku = array();
                $qty = 0;
                foreach($products as $product){
                    $attr_title = unserialize($product['foreign_title']);
                    if($product['sku_title']){
                        $attrs_title = !empty($attr_title)?implode('-',$attr_title):$product['sku_title'];
                        $product_name[] =  $product['product_title'].' + '.$product['sku_title'].' x '.$product['quantity'];
                        $foreigns_title[] = $product['sale_title'].' + '.$attrs_title.' x '.$product['quantity'];
                    }else{
                        $product_name[] =  $product['product_title'].' x '.$product['quantity'];
                        $foreigns_title[] = $product['sale_title'].' x '.$product['quantity'];
                    }
                    $total_qty +=$product['quantity'];
                    $sku[] =  $product['sku'];
                }
                $getShipObj = D("Order/OrderShipping")->field('track_number,status_label,shipping_name')//
                            ->where(array('id_order'=>$o['id_order']))->select();
                $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
                $shipping_name = D('Common/Shipping')->where(array('id_shipping'=>$o['id_shipping']))->getField('title');
                $product_title = $product_name&& is_array($product_name)?implode(' ; ', $product_name):'';
                $foreign_name = $foreigns_title&& is_array($foreigns_title)?implode(' ; ', $foreigns_title):'';
                $attr_value = $attr_value && is_array($attr_value)?implode(' ; ', $attr_value):'';
                $sku  = $sku?implode(' ; ', $sku):'';
                $user_name = $o['first_name'].' '.$o['last_name'];
                $payment_method = $o['payment_method']?:'货到付款';
                $payment_status = $o['payment_status']?:'未付款';
                $payment_id = trim($o['payment_id']);
//                if ($currency_data[$o['currency_code']]['left'])
//                {
//                    $o['price_total'] = $currency_data[$o['currency_code']]['currency_code'].$o['price_total'];
//                }
//                else
//                {
//                    $o['price_total'] = $o['price_total'].$currency_data[$o['currency_code']]['currency_code'];
//                }
                if ($payment_id) {
                    //TODO: 只要是信用卡支付, 然后客服从通道那里确认后把订单状态改成"未配货"认为已经付款完成
                    $payment_method = '信用卡支付';
                    $payment_status = '已付款';
                }
                $product_title_attr = trim($attr_value)?$product_title.'   '.$attr_value:$product_title;
                //台湾地区的地址不需要加上省份,但是其他的地区需要带上
                if ($o['id_zone'] == 2) {
                    $address = trim(sprintf('%s%s%s', $o['city'], $o['area'], $o['address']));
                } else {
                    $address = trim(sprintf('%s%s%s%s',$o['province'], $o['city'], $o['area'], $o['address']));
                }
                $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
                $warehouse = array_column($warehouse,'title','id_warehouse');
                $provincestr='';
                if($o['id_zone'] == 11){
                    $provincestr=$provincearray[$o['zipcode']];
                }

                $data = array(
                    $all_zone[$o['id_zone']],$shipping_name,
                    $o['id_increment'],$trackNumber,$user_name, $product_title_attr, $foreign_name,'', $sku,
                    $o['price_total'],$total_qty, $address, $o['remark'],$o['created_at'],
                    $all_status[$o['id_order_status']], $o['date_delivery'], $o['comment'],
                    $payment_method, $payment_status,$o['zipcode'],$warehouse[$o['id_warehouse']],$provincestr
                ); 
                if($_GET['istelphone']==1){
                    $data = array(
                        $all_zone[$o['id_zone']],$shipping_name,
                        $o['id_increment'],$trackNumber,$user_name ,$o['tel'], $product_title_attr, $foreign_name,'', $sku,
                        $o['price_total'],$total_qty, $address, $o['remark'],$o['created_at'],
                        $all_status[$o['id_order_status']], $o['date_delivery'], $o['comment'],
                        $payment_method, $payment_status,$o['zipcode'],$warehouse[$o['id_warehouse']],$provincestr
                    );                     
                }
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
                $history = array('id_order'=>$o['id_order'],'new_status_id'=>5,'comment'=>'导出未配货订单');
                //$status_model->update_status_add_history($history);
            }
            add_system_record($_SESSION['ADMIN_ID'], 6, 1, '仓库导出未配送订单');
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
//storage_ftp_list?warehouse_id=&start_time=2017-05-04+00%3A00&end_time=2017-05-24+00%3A00&user=&docno=2017002&title=&sku=
    //仓库日进销存  
    public function storage_ftp_list(){ 
    // dump($_SESSION['department_id'][0]); die;//dump(123);exit;
        $department_id = $_SESSION['department_id'];
        $where = array();
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            // $created_at_array = array();
            if ($_GET['start_time'])
                  $created_at_array[] = array('EGT', $_GET['start_time'].' 00:00:00');
             if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time'].' 23:59:59');
            }else{
                $created_at_array[] = array('EGT', date("Y-m-d", strtotime("-1 weeks")).' 00:00:00');
                 $created_at_array[] = array('LT', date("Y-m-d").' 23:59:59');
             }
        $where['billdate'] = $created_at_array;
        if (!empty($_GET['title'])) {
            $where['title'] =array("like",'%'.$_GET['title'].'%');
        }
        if (!empty($_GET['inner_name'])) {
            $where['inner_name'] =array("like",'%'.$_GET['inner_name'].'%');
        }
        if (!empty($_GET['sku'])) {
            $where['sku'] =array("EQ",$_GET['sku']);
        }
         if(isset($_GET['id_department']) && $_GET['id_department']!=="0") {
            $where['id_department'] = $_GET['id_department'];
        }else if(isset($_GET['id_department']) && $_GET['id_department']=="0"){
            $where['id_department'] = array("IN",$_SESSION['department_id']);
        }else{
            $where['id_department'] = array("EQ",$_SESSION['department_id'][0]);
        }
        // dump($where);die;
        $billtype = M('StorageFtp')->field('billtype')->group('billtype')->select();
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $list = M("StorageView")->field("id,docno,billtype,billdate,qtychange,amtchange,qty_alloc,wtitle,sku,sku_title,title,inner_name,user_nicename,id_department,dt_title")->where($where)->order("billdate DESC,id DESC")->select();
            $datas = [];//按时间 SKU为索引 重组数组
            $department = D('Common/Department')->where($whereD)->cache(true,6000)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        foreach ($list as $key => $value) {
            $d = substr($value['billdate'],0,10);
            $datas[$d][$value['sku']]['dt_title'] = $value['dt_title'] ;
            $datas[$d][$value['sku']]['id'] = $value['id'] ;
            $datas[$d][$value['sku']]['title'] = $value['title'] ;
            $datas[$d][$value['sku']]['inner_name'] = $value['inner_name'] ;
            $datas[$d][$value['sku']]['sku_title'] = $value['sku_title'] ;
            $datas[$d][$value['sku']]['dt_title'] = $department[$value['id_department']];
            $datas[$d][$value['sku']]['qty_alloc'] = ($datas[$d][$value['sku']]['qty_alloc']+$value['qty_alloc'] );
             // $datas[$d][$value['sku']]['total_order_quantity'] = $datas[$d][$value['sku']]['total_order_quantity']+$value['quantity'];
             if(stripos($value['billtype'],"出库")!==false){ 
                $datas[$d][$value['sku']]['out'] += abs($value['qtychange']);
             }
              if(stripos($value['billtype'],"入库")!==false){
                $datas[$d][$value['sku']]['in'] += abs($value['qtychange']);
             }
        }
        // dump($datas);die;
        $whereD['id_department'] = array("IN",$department_id);
        $whereD['type'] = 1;
        
        $this->assign('department',$department);
        $this->assign('list',$datas);
        $billtype  = $billtype?array_column($billtype,'billtype'):array();
        $this->assign('billtype',$billtype);
        // $this->assign("Page", $page->show('Admin'));
        $this->assign('warehouse',$warehouse);
        $this->assign("pn",$page->Current_page);
        $this->display();
    }
     //仓库日进销存详细  -- Lily 2017-11-09
    public function storage_ftp_detail(){
        if(isset($_GET['action'])){
            if($_GET['id']){
              $wherep['id']=intval($_GET['id']);  
            }else{
                $this->error("ID不能为空");
            }
            $data = M("StorageFtp")->where($wherep)->field("billdate,id_product_sku")->find();
            if($data){
              $where['sf.id_product_sku'] =$data['id_product_sku'];   
            }else{
                $this->error("不存该在日进销存记录");
            }
           //dump($data['billdate']);
            if(isset($_GET['billtype_de']) && $_GET['billtype_de']) {
            $where['sf.billtype'] = $_GET['billtype_de'];
            if(stripos($_GET['billtype_de'],"入库")!==false){ //判断当前页面是显示入库 还是显示出库  --Lily 2017-11-09
                $_GET['action'] = "wa_in";
            }else if(stripos($_GET['billtype_de'],"出库")!==false){
                 $_GET['action'] = "wa_out";
            }
            }
            $department_id = trim($_GET['department_id']);//dump($department_id);die;
            if($department_id=="全部"){ // 是否显示全部部门  --Lily 2017-11-09
                $dt_title['id_department'] = 0;
            }else{
                $dt_title = M("Department")->where("title='".$department_id."'")->field("id_department")->find(); 
            }
           
            $sku = M("ProductSku")->where("id_product_sku=".$data['id_product_sku'])->field("sku")->find();
            //是否存在从日进销存页面进到详细页面的标识  --Lily 2017-11-09
            if($_GET['detail']){
                 if($_GET['action']=='wa_in'){
                        $where['sf.billtype'] = ['LIKE','%'.'入库'.'%'];
                    }else if($_GET['action']=='wa_out'){
                        $where['sf.billtype'] = ['LIKE','%'.'出库'.'%'];
                    }
            }
          }
         $billtype = M('StorageFtp')->field('billtype')->group('billtype')->select();
        $pager = isset($_SESSION['set_page_row']) ? $_SESSION['set_page_row'] : 20;
        $list =  M('StorageFtp')->alias('sf')
            ->field("sf.*,user.user_nicename,pk.sku,pk.title as sku_title,p.title,p.inner_name,w.title as wtitle,dt.title as dt_title")
            ->join('__WAREHOUSE__ as w on w.id_warehouse = sf.id_warehose','LEFT')
            ->join('__PRODUCT_SKU__ as pk on sf.id_product_sku = pk.id_product_sku','LEFT')
            ->join('__PRODUCT__ as p on sf.id_product = p.id_product','LEFT')
            ->join('__USERS__ as user on user.id = sf.id_users','LEFT')
            ->join('__DEPARTMENT__ as dt on dt.id_department = pk.id_department','LEFT')
            ->order('billdate DESC')
            ->where($where)->select();
            // 取出数组中同一时间的记录 并做上标识  前台页面有该标识的则显示  没有则不显示  --Lily 2017-11-09
          foreach ($list as $key => $value) {  
            if(substr($value['billdate'], 0,10)==substr($data['billdate'],0,10)){
                    $list[$key]['is_hide']=1;
                 }
         } 

        $p = $_GET['p']; //接收前台传过来的p值  
        $p==""?'0':$p;  //判断p值是否存在
        $start = $p==0?$p*20:($p-1)*20; //数组截取的开始位置
        $listda = array_slice($list,$start,20,true);//数组截取值 分页
        $num = count($list);//dump($num);exit;//总条数
        $per_num = 20; //每一页的条数
        $page = $this->page($num, $per_num );
        $this->assign('department',$department);
        $this->assign('list',$listda); //dump($listda);exit;
        $billtype  = $billtype?array_column($billtype,'billtype'):array();
        $this->assign('billtype',$billtype);
        $this->assign("Page", $page->show('Admin'));
        $this->assign('warehouse',$warehouse);
        $this->assign('sku',$sku);
        $this->assign('dt_id',$dt_title);
        $this->display();
    }
    
    /**
     * 仓库日进销存导出	  
     */
    public function  export_storageFtp(){
        //判断是否有action 有为细节导出  没有为总体导出  --Lily 2017-11-09
        if(isset($_GET['action'])){
        $wherep['id']=$_GET['id'];
        $data = M("StorageFtp")->where($wherep)->field("billdate,id_product_sku")->find();
        $where['sf.id_product_sku'] =$data['id_product_sku'];//dump($data['billdate']);
        if(isset($_GET['billtype']) && $_GET['billtype']){
            $where['sf.billtype'] = $_GET['billtype'];
        }
        $billtype = M('StorageFtp')->field('billtype')->group('billtype')->select();
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $list =  M('StorageFtp')->alias('sf')
            ->field("sf.*,user.user_nicename,pk.sku,pk.title as sku_title,p.title,p.inner_name,w.title as wtitle,dt.title as dt_title")
            ->join('__WAREHOUSE__ as w on w.id_warehouse = sf.id_warehose','LEFT')
            ->join('__PRODUCT_SKU__ as pk on sf.id_product_sku = pk.id_product_sku','LEFT')
            ->join('__PRODUCT__ as p on sf.id_product = p.id_product','LEFT')
            ->join('__USERS__ as user on user.id = sf.id_users','LEFT')
            ->join('__DEPARTMENT__ as dt on dt.id_department = pk.id_department','LEFT')
            ->order('billdate DESC')
            ->where($where)->limit($page->firstRow, $page->listRows)->select();
            // print_r($list);exit;
          foreach ($list as $key => $value) {
            //判断是否事同一天  如是则添加标识  没有该标识的则在导出的时候跳出  --Lily 2017-11-09
            if(substr($value['billdate'], 0,10)==substr($data['billdate'],0,10)){
                    $list[$key]['is_hide']=1;
                    $id['id'][$k] = $v['id'];

                }
         } 
            if($_GET['action']=="wa_in"){
                $stock_num = '入库数';
                $amount_nu ='入库金额';
                $get_num = ',上架数量'; 
            }else if($_GET['action']=="wa_out"){
                $stock_num = '出库数';
                $amount_nu ='出库金额';
                $get_num = ''; 
            }
        $str = "ID,仓库,产品名,内部名,出入库日期,单据编号, sku,单据类型, 出入库人,".$stock_num.','.$amount_nu.$get_num."\n";
        
        foreach($list as $k => $val){
            if(empty($val['is_hide']))continue;
            if(empty($val['qtychange'])){
                $val['qtychange']==0;
            }
            if(empty($val['amtchange'])){
                $val['amtchange']==0;
            }
            if(empty($val['qty_alloc'])){
                $val['qty_alloc']==0;
            }
            if($_GET['action']=='wa_in'){
                $str.=$val['id'].','.
                $val['wtitle'].','.
                $val['title']."\t,".
                $val['inner_name'].','.
                $val['billdate'].','.
                $val['docno']."\t,".
                $val['sku'].','.
                $val['billtype'].','.
                $val['user_nicename']."\t,".
                $val['qtychange'].','.
                $val['amtchange'].','.
                $val['qty_alloc']."\n";
            }else if($_GET['action']=='wa_out'){
                $str.=$val['id'].','.
                $val['wtitle'].','.
                $val['title']."\t,".
                $val['inner_name'].','.
                $val['billdate'].','.
                $val['docno']."\t,".
                trim($val['sku']).','.
                $val['billtype'].','.
                $val['user_nicename']."\t,".
                abs($val['qtychange']).','.
                $val['amtchange']."\n";
            }

        }
        
        $filename = date('Ymd').'日进销存详细.csv'; //设置文件名
        $this->export_csv($filename,iconv("UTF-8","GBK//IGNORE",$str)); //导出
        exit;
        }else{  //全部导出  --Lily 2017-11-09
            $department_id = $_SESSION['department_id'];
            $where = array();
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            // $created_at_array = array();
            if ($_GET['start_time'])
                  $created_at_array[] = array('EGT', $_GET['start_time'].' 00:00:00');
             if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time'].' 23:59:59');
            }else{
                $created_at_array[] = array('EGT', date("Y-m-d", strtotime("-1 weeks")).' 00:00:00');
                 $created_at_array[] = array('LT', date("Y-m-d").' 23:59:59');
             }
        $where['billdate'] = $created_at_array;
        if (!empty($_GET['title'])) {
            $where['title'] =array("like",'%'.$_GET['title'].'%');
        }
        if (!empty($_GET['inner_name'])) {
            $where['inner_name'] =array("like",'%'.$_GET['inner_name'].'%');
        }
        if (!empty($_GET['sku'])) {
            $where['sku'] =array("like",'%'.$_GET['sku'].'%');
        }
         if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = $_GET['id_department'];
        }
        if(isset($_GET['id_department']) && $_GET['id_department']!=="0") {
            $where['id_department'] = $_GET['id_department'];
        }else if(isset($_GET['id_department']) && $_GET['id_department']=="0"){
            $where['id_department'] = array("IN",$_SESSION['department_id']);
        }else{
            $where['id_department'] = array("EQ",$_SESSION['department_id'][0]);
        }
        // $whereD['id_department'] = array("IN",$department_id);
        // $whereD['type'] = 1;
        // $department = D('Common/Department')->where($whereD)->cache(true,6000)->select();
        // $department  = $department?array_column($department,'title','id_department'):array();
        $billtype = M('StorageFtp')->field('billtype')->group('billtype')->select();
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $list = M("StorageView")->field("id,docno,billtype,billdate,qtychange,amtchange,qty_alloc,wtitle,sku,sku_title,title,inner_name,user_nicename,id_department,dt_title")->where($where)->order("billdate DESC,id DESC")->select();
        // echo M("StorageView")->getLastSql();exit;
        // dump($list);die;
            $datas = [];
        foreach ($list as $key => $value) {
            $d = substr($value['billdate'],0,10);
            $datas[$d][$value['sku']]['dt_title'] = $value['dt_title'] ;
            $datas[$d][$value['sku']]['id'] = $value['id'] ;
            $datas[$d][$value['sku']]['title'] = $value['title'] ;
            $datas[$d][$value['sku']]['inner_name'] = $value['inner_name'] ;
            $datas[$d][$value['sku']]['sku_title'] = $value['sku_title'] ;
            $datas[$d][$value['sku']]['qty_alloc'] = ($datas[$d][$value['sku']]['qty_alloc']+$value['qty_alloc'] );
             // $datas[$d][$value['sku']]['total_order_quantity'] = $datas[$d][$value['sku']]['total_order_quantity']+$value['quantity'];
             if(stripos($value['billtype'],"出库")!==false){ 
                $datas[$d][$value['sku']]['out'] += abs($value['qtychange']);
             }
              if(stripos($value['billtype'],"入库")!==false){
                $datas[$d][$value['sku']]['in'] += abs($value['qtychange']);
             }
        }
        // dump($datas);die;
        $str = "日期,部门, 产品名称, 内部名, sku,属性, 入库量,出库量,上架数\n";
        foreach($datas as $k => $val){
            foreach($val as $kk=>$vv){
                if($vv['in']==""){
                $vv['in']=0;
            }
            if($vv['out']==""){
                $vv['out']=0;
            }else{
                $vv['out'] = abs($vv['out']);
            }
            $str.=$k.','.
                $vv['dt_title'].','.
                $vv['title'].','.
                $vv['inner_name'].','.
                $kk."\t,".
                str_replace(',','',$vv['sku_title']).','.
                $vv['in'].','.
                $vv['out'].','.
                $vv['qty_alloc']."\n";

            }
        }
         // dump($str);die;
        $filename = date('Ymd').'日进销存.csv'; //设置文件名
        $this->export_csv($filename,iconv("UTF-8","GBK//IGNORE",$str)); //导出
        exit;
        }
     }

    //月结列表封帐
    public function period_fz(){
        if(IS_AJAX) {
            try {
                $msg="月结列表封帐";
                $ivIds = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                $upd_data=array('isendaccount'=>'Y');
                if ($ivIds && is_array($ivIds)) {
                    foreach ($ivIds as $ivid) {
                        $ivstatus = M('Period')->field('isendaccount')->where(array('id'=>$ivid))->find();
                        if($ivstatus['isendaccount']=='N') {//  防止多次提交
                            $ret=M('Period')->where(array('id'=>$ivid))->save($upd_data);
                        }
                    }
                    $status = 1;
                    $message = $msg.'成功';
                }
            } catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }

            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }
    //月结列表月结
    public function period_yj(){
        if(IS_AJAX) {
            try {
                $msg="月结列表月结";
                $ivIds = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                $upd_data=array('ismonthsum'=>'Y');
                if ($ivIds && is_array($ivIds)) {
                    foreach ($ivIds as $ivid) {
                        $ivstatus = M('Period')->field('ismonthsum')->where(array('id'=>$ivid))->find();
                        if($ivstatus['ismonthsum']=='N') {//  防止多次提交
                            $ret=M('Period')->where(array('id'=>$ivid))->save($upd_data);
                        }
                    }
                    $status = 1;
                    $message = $msg.'成功';
                }
            } catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }

            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }
    //仓库月结
    public function period_list(){
        $where = array();
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['p.creationdate'] = $created_at_array;
        }

        if (!empty($_GET['user'])) {
            $where['user.user_nicename'] =array("like",'%'.$_GET['user'].'%');
        }
        if(isset($_GET['warehouse_id']) && $_GET['warehouse_id']) {
            $where['p.id_warehouse'] = $_GET['warehouse_id'];
        }
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $pager = isset($_SESSION['set_page_row']) ? $_SESSION['set_page_row'] : 20;
        //$where['wga.id_warehouse'] = array('EQ', $id_warehouse);
        $count = M('Period')->alias('p')
            ->field("p.*,user.user_nicename,w.title")
            ->join('__WAREHOUSE__ as w on w.id_warehouse = p.id_warehouse','LEFT')
            ->join('__USERS__ as user on user.id = p.ownerid','LEFT')
            ->where($where)->count();
        $page = $this->page($count, $pager );

        $list =   M('period')->alias('p')
            ->field("p.*,w.title as wtitle")
            ->join('__WAREHOUSE__ as w on w.id_warehouse = p.id_warehouse','LEFT')
            ->join('__USERS__ as user on user.id = p.ownerid','LEFT')
            ->order('id DESC')
            ->where($where)->limit($page->firstRow, $page->listRows)->select();
        $user = M('Users')->getField('id,user_nicename',true);
        $this->assign('user',$user);
        $this->assign('list',$list);
        $this->assign("Page", $page->show('Admin'));
        $this->assign('warehouse',$warehouse);
        $this->display();
    }
    //仓库月结导出
    public function period_export(){
        $where = array();
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['p.creationdate'] = $created_at_array;
        }

        if (!empty($_GET['user'])) {
            $where['user.user_nicename'] =array("like",'%'.$_GET['user'].'%');
        }
        if(isset($_GET['warehouse_id']) && $_GET['warehouse_id']) {
            $where['p.id_warehouse'] = $_GET['warehouse_id'];
        }
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $list =   M('period')->alias('p')
            ->field("p.*,w.title as wtitle")
            ->join('__WAREHOUSE__ as w on w.id_warehouse = p.id_warehouse','LEFT')
            ->join('__USERS__ as user on user.id = p.ownerid','LEFT')
            ->where($where)->select();
        $user = M('Users')->getField('id,user_nicename',true);
        $str = "ID,仓库编号,月结年月,财务开始日期,财务结束日期,是否封账,是否月结,制单人,提交人,制单时间提交时间\n";
        foreach($list as $k => $vlist){
            $str.=$vlist['id'].','.
                $vlist['wtitle'].','.
                $vlist['yearmonth'].','.
                $vlist['datebegin'].','.
                $vlist['dateend'].','.
                $vlist['isendaccount'].','.
                $vlist['ismonthsum'].','.
                $user[$vlist['ownerid']].','.
                $user[$vlist['statusid']].','.
                $vlist['creationdate'].','.
                $vlist['statustime']."\n";
        }
        $filename = date('Ymd').'.csv'; //设置文件名
        $this->export_csv($filename,$str); //导出
        exit;
    }
    function export_csv($filename,$data)
    {
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=".$filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }
    //仓库月结明细
    public function period_detail(){
        if (IS_AJAX) {
            $message = array();
            try
            {
                $id	= I('post.id');
                $save_data = array();
                $save_data['datebegin']=I('post.datebegin');
                $save_data['dateend']=I('post.dateend');
                $ret=M('period')->where(array('id'=>array('EQ',$id)))->save($save_data);
                $message 	= '保存成功';
                $status 	= 1;
            } catch (\Exception $e) {
                $status 	= 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ("出库管理导入保存"));
            $return = array('status' => $status, 'id' => $id, 'message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
        $id=$_GET['id'];
        $where['p.id']=$id;
        $list =   M('period')->alias('p')
            ->field("p.*,w.title as wtitle")
            ->join('__WAREHOUSE__ as w on w.id_warehouse = p.id_warehouse','LEFT')
            ->join('__USERS__ as user on user.id = p.ownerid','LEFT')
            ->where($where)->find();
        $user = M('Users')->getField('id,user_nicename',true);
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $this->assign('user',$user);
        $this->assign('list',$list);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /*
     * 货架条形码
     * */
    public function storage_rack_bar_code(){
        $this->display();
    }
    
    /*
     * 仓库进销存查询 zx 11/20 
     */
    function inventory() {
        //参数筛选
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();//有效状态订单
        //产品内部名
        $flag = 0; //默认不显示数据
        if ($_POST['sku'] && $_POST['innername']) {
            $where['pt.inner_name'] = array('like', '%' . $_POST['innername'] . '%');
            $where['oi.sku'] = array('EQ', $_POST['sku']);
            $flag = 3;
        } elseif (isset($_POST['innername']) && $_POST['innername']) {
            $where['pt.inner_name'] = array('like', '%' . $_POST['innername'] . '%');
            $flag = 2;
        } elseif (isset($_POST['sku']) && $_POST['sku']) {
            $where['oi.sku'] = array('EQ', $_POST['sku']);
            $flag = 1;
        }

        if ($flag != 0) {
            $orderitem_table = D('Order/orderItem')->getTableName();
            $product_table = D('product')->getTableName();
            $order = D('order')->getTableName();
            //获取已发货状态订单 8:配送中 9:已签收 10:已退货 16:拒收 19:理赔 21:退货入库
            $deliver_status = \Order\Lib\OrderStatus::get_delivered_status();
            //$fields = "SUM(IF(`id_order_status` IN(".implode(',', $deliver_status)."),1,0)) as deliver_number,o.id_order_status,oi.id_product_sku,oi.sku,oi.sku_title,oi.id_product,pt.inner_name,pt.title";
            $where['o.id_order_status'] = array("IN",$deliver_status);
            $fields = "COUNT(DISTINCT oi.id_order) as deliver_number,o.id_order,o.id_order_status,oi.id_product_sku,oi.sku,oi.sku_title,oi.id_product,pt.inner_name,pt.title";
            $staticsinfo = M('order o')
                            ->join("{$orderitem_table} oi on o.id_order=oi.id_order", 'left')
                            ->join("{$product_table} pt on pt.id_product=oi.id_product", 'left')
                            ->where($where)
                            ->field($fields)
                            ->group('oi.id_product_sku')
                            ->select();

            //取出所有的 id_product_sku
            $sku_arr = array_column($staticsinfo, 'id_product_sku');
            $where_sku['id_product_sku'] = array("IN", implode(',', $sku_arr));
            $purchase_products = M("PurchaseProduct")->field('id_product_sku,price,quantity')->where($where_sku)->select();

            $model = new \Think\Model();
            //总产品数 orderItem       
            $where_total_product['oi.id_product_sku'] = array("IN", implode(',', $sku_arr));
            $where_total_product['o.id_order_status'] = array("IN",$effective_status);
            $total_product = $model->table('erp_order o')
                            ->join("__ORDER_ITEM__ oi ON o.id_order=oi.id_order", "left")
                            ->field('oi.quantity,oi.id_product_sku')
                            ->where($where_total_product)->select();

            foreach ($total_product as $pkey => $pval) {
                $product_stNumber[$pval['id_product_sku']]['id_product_sku'] = $pval['id_product_sku'];
                $product_stNumber[$pval['id_product_sku']]['total'] += $pval['quantity'];
            }
            $product_stNumber = array_merge($product_stNumber);

            //总采购数 PurchaseInitem PurchaseIn 
            $where_total_purchase['pi.id_product_sku'] = array("IN", implode(',', $sku_arr));
            $where_total_purchase['po.status'] = array("EQ", 5); //已付款
            $total_purchase = $model->table('erp_purchase_in p')
                            ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein", 'left')
                            ->join("__PURCHASE__ as po on p.id_erp_purchase=po.id_purchase", 'left')
                            ->field('pi.id_product_sku,sum(pi.quantity) as total')
                            ->group('pi.id_product_sku')
                            ->where($where_total_purchase)->select();

            foreach ($total_purchase as $tkey => $tval) {
                $purchase_stNumber[$tval['id_product_sku']]['id_product_sku'] = $tval['id_product_sku'];
                $purchase_stNumber[$tval['id_product_sku']]['total'] += $tval['total'];
            }
            $purchase_stNumber = array_merge($purchase_stNumber);

            //总入库数 PurchaseInitem PurchaseIn
            $where_warehouse['pi.id_product_sku'] = array("IN", implode(',', $sku_arr));
            $where_warehouse['p.status'] = array("IN", [2, 3]); //已入库+部分入库 received 
            $total_warehouse = $model->table('erp_purchase_in p')
                            ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein", 'left')
                            ->field('pi.id_product_sku,sum(pi.received) as received')
                            ->group('pi.id_product_sku')
                            ->where($where_warehouse)->select();
            foreach ($total_warehouse as $wkey => $wval) {
                $purchase_swarehouse[$wval['id_product_sku']]['id_product_sku'] = $wval['id_product_sku'];
                $purchase_swarehouse[$wval['id_product_sku']]['received'] += $wval['received'];
            }
            $purchase_swarehouse = array_merge($purchase_swarehouse);

            //总未入库数
            $where_no_warehouse['pi.id_product_sku'] = array("IN", implode(',', $sku_arr));
            $where_no_warehouse['p.status'] = array("EQ", 1); //未入库 received 
            $total_no_warehouse = $model->table('erp_purchase_in p')
                            ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein", 'left')
                            ->field('pi.id_product_sku,sum(pi.received) as received')
                            ->group('pi.id_product_sku')
                            ->where($where_no_warehouse)->select();

            foreach ($total_no_warehouse as $nkey => $nval) {

                $purchase_no_swarehouse[$nval['id_product_sku']]['id_product_sku'] = $nval['id_product_sku'];
                $purchase_no_swarehouse[$nval['id_product_sku']]['received'] += $nval['received'];
            }
            $purchase_no_swarehouse = array_merge($purchase_no_swarehouse);

            foreach ($staticsinfo as $key => $itme) {
                // 拼接总产品数 zx 11/20 
                foreach ($product_stNumber as $pkey => $pval) {
                    if ($itme['id_product_sku'] == $pval['id_product_sku']) {
                        $staticsinfo[$key]['total_product'] = $pval['total'];
                        break;
                    }
                }

                // 拼接总采购数 zx 11/20 
                foreach ($purchase_stNumber as $tkey => $tval) {
                    if ($itme['id_product_sku'] == $tval['id_product_sku']) {
                        $staticsinfo[$key]['total_purchase'] = $tval['total'];
                        break;
                    }
                }
                // 拼接总入库数 zx 11/18 
                foreach ($purchase_swarehouse as $wkey => $wval) {
                    if ($itme['id_product_sku'] == $wval['id_product_sku']) {
                        $staticsinfo[$key]['total_warehouse'] = $wval['received'];
                        break;
                    }
                }
                // 拼接总未入库数 zx 11/18 
                foreach ($purchase_no_swarehouse as $nkey => $nval) {
                    if ($itme['id_product_sku'] == $nval['id_product_sku']) {
                        $staticsinfo[$key]['total_no_warehouse'] = $nval['received'];
                        break;
                    }
                }
            }
        }

        $this->assign('statistics', $staticsinfo);
        $this->assign('post', $_POST);
        $this->display();
    }
    
    /*
     * 仓库进销存导出
     */
    function export_inventory() {
        //参数筛选
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();//有效状态订单
        //产品内部名
        $flag = 0; //默认不显示数据
        if ($_POST['sku'] && $_POST['innername']) {
            $where['pt.inner_name'] = array('like', '%' . $_POST['innername'] . '%');
            $where['oi.sku'] = array('EQ', $_POST['sku']);
            $flag = 3;
        } elseif (isset($_POST['innername']) && $_POST['innername']) {
            $where['pt.inner_name'] = array('like', '%' . $_POST['innername'] . '%');
            $flag = 2;
        } elseif (isset($_POST['sku']) && $_POST['sku']) {
            $where['oi.sku'] = array('EQ', $_POST['sku']);
            $flag = 1;
        }

        if ($flag != 0) {
            $orderitem_table = D('Order/orderItem')->getTableName();
            $product_table = D('product')->getTableName();
            $order = D('order')->getTableName();
            //获取已发货状态订单 8:配送中 9:已签收 10:已退货 16:拒收 19:理赔 21:退货入库
            $deliver_status = \Order\Lib\OrderStatus::get_delivered_status();
            //$fields = "SUM(IF(`id_order_status` IN(".implode(',', $deliver_status)."),1,0)) as deliver_number,o.id_order_status,oi.id_product_sku,oi.sku,oi.sku_title,oi.id_product,pt.inner_name,pt.title";
            $where['o.id_order_status'] = array("IN",$deliver_status);
            $fields = "COUNT(DISTINCT oi.id_order) as deliver_number,o.id_order,o.id_order_status,oi.id_product_sku,oi.sku,oi.sku_title,oi.id_product,pt.inner_name,pt.title";
            $staticsinfo = M('order o')
                            ->join("{$orderitem_table} oi on o.id_order=oi.id_order", 'left')
                            ->join("{$product_table} pt on pt.id_product=oi.id_product", 'left')
                            ->where($where)
                            ->field($fields)
                            ->group('oi.id_product_sku')
                            ->select();

            //取出所有的 id_product_sku
            $sku_arr = array_column($staticsinfo, 'id_product_sku');
            $where_sku['id_product_sku'] = array("IN", implode(',', $sku_arr));
            $purchase_products = M("PurchaseProduct")->field('id_product_sku,price,quantity')->where($where_sku)->select();

            $model = new \Think\Model();
            //总产品数 orderItem       
            $where_total_product['oi.id_product_sku'] = array("IN", implode(',', $sku_arr));
            $where_total_product['o.id_order_status'] = array("IN",$effective_status);
            $total_product = $model->table('erp_order o')
                            ->join("__ORDER_ITEM__ oi ON o.id_order=oi.id_order", "left")
                            ->field('oi.quantity,oi.id_product_sku')
                            ->where($where_total_product)->select();

            foreach ($total_product as $pkey => $pval) {
                $product_stNumber[$pval['id_product_sku']]['id_product_sku'] = $pval['id_product_sku'];
                $product_stNumber[$pval['id_product_sku']]['total'] += $pval['quantity'];
            }
            $product_stNumber = array_merge($product_stNumber);

            //总采购数 PurchaseInitem PurchaseIn 
            $where_total_purchase['pi.id_product_sku'] = array("IN", implode(',', $sku_arr));
            $where_total_purchase['po.status'] = array("EQ", 5); //已付款
            $total_purchase = $model->table('erp_purchase_in p')
                            ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein", 'left')
                            ->join("__PURCHASE__ as po on p.id_erp_purchase=po.id_purchase", 'left')
                            ->field('pi.id_product_sku,sum(pi.quantity) as total')
                            ->group('pi.id_product_sku')
                            ->where($where_total_purchase)->select();

            foreach ($total_purchase as $tkey => $tval) {
                $purchase_stNumber[$tval['id_product_sku']]['id_product_sku'] = $tval['id_product_sku'];
                $purchase_stNumber[$tval['id_product_sku']]['total'] += $tval['total'];
            }
            $purchase_stNumber = array_merge($purchase_stNumber);

            //总入库数 PurchaseInitem PurchaseIn
            $where_warehouse['pi.id_product_sku'] = array("IN", implode(',', $sku_arr));
            $where_warehouse['p.status'] = array("IN", [2, 3]); //已入库+部分入库 received 
            $total_warehouse = $model->table('erp_purchase_in p')
                            ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein", 'left')
                            ->field('pi.id_product_sku,sum(pi.received) as received')
                            ->group('pi.id_product_sku')
                            ->where($where_warehouse)->select();
            foreach ($total_warehouse as $wkey => $wval) {
                $purchase_swarehouse[$wval['id_product_sku']]['id_product_sku'] = $wval['id_product_sku'];
                $purchase_swarehouse[$wval['id_product_sku']]['received'] += $wval['received'];
            }
            $purchase_swarehouse = array_merge($purchase_swarehouse);

            //总未入库数
            $where_no_warehouse['pi.id_product_sku'] = array("IN", implode(',', $sku_arr));
            $where_no_warehouse['p.status'] = array("EQ", 1); //未入库 received 
            $total_no_warehouse = $model->table('erp_purchase_in p')
                            ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein", 'left')
                            ->field('pi.id_product_sku,sum(pi.received) as received')
                            ->group('pi.id_product_sku')
                            ->where($where_no_warehouse)->select();

            foreach ($total_no_warehouse as $nkey => $nval) {

                $purchase_no_swarehouse[$nval['id_product_sku']]['id_product_sku'] = $nval['id_product_sku'];
                $purchase_no_swarehouse[$nval['id_product_sku']]['received'] += $nval['received'];
            }
            $purchase_no_swarehouse = array_merge($purchase_no_swarehouse);

            foreach ($staticsinfo as $key => $itme) {
                // 拼接总产品数 zx 11/20 
                foreach ($product_stNumber as $pkey => $pval) {
                    if ($itme['id_product_sku'] == $pval['id_product_sku']) {
                        $staticsinfo[$key]['total_product'] = $pval['total'];
                        break;
                    }
                }

                // 拼接总采购数 zx 11/20 
                foreach ($purchase_stNumber as $tkey => $tval) {
                    if ($itme['id_product_sku'] == $tval['id_product_sku']) {
                        $staticsinfo[$key]['total_purchase'] = $tval['total'];
                        break;
                    }
                }
                // 拼接总入库数 zx 11/18 
                foreach ($purchase_swarehouse as $wkey => $wval) {
                    if ($itme['id_product_sku'] == $wval['id_product_sku']) {
                        $staticsinfo[$key]['total_warehouse'] = $wval['received'];
                        break;
                    }
                }
                // 拼接总未入库数 zx 11/18 
                foreach ($purchase_no_swarehouse as $nkey => $nval) {
                    if ($itme['id_product_sku'] == $nval['id_product_sku']) {
                        $staticsinfo[$key]['total_no_warehouse'] = $nval['received'];
                        break;
                    }
                }
            }
        }

        //重构导出格式 zx 11/24
        $getField = array('A','B','C','D','E','F','G','H','I','J','K','L');
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $objPHPExcel = new \PHPExcel();
        $setRowName = array('产品','属性','SKU','总产品数','总采购数','总入库数','总未入库','已发货总量');
        $num  = 2;
        foreach($setRowName as $r=>$v){
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$r].'1',$v);
            $j++;
        }
            
         foreach($staticsinfo as $order){
              $pro_name=$order['inner_name']?$order['inner_name']:$order['title'];
                if(!$order['id_product']){
                    continue;
                }

                if($order['total_purchase'] >= $order['total_warehouse']){
                    $rel_warehouse = $order['total_warehouse'];
                }else{
                    $rel_warehouse = $order['total_purchase'];
                }
                
                
                $tempRow = array(
                    $pro_name,
                    trim($order['sku_title'],','),
                    $order['sku'],
                    $order['total_product'],
                    $order['total_purchase'],
                    $rel_warehouse,
                    //$order['total_warehouse'],
                    $order['total_no_warehouse'],
                    $order['deliver_number']
                );
                foreach ($tempRow as $row => $value) {
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$row] . $num, $value);
                }
                $num++;
            }
        
        add_system_record($_SESSION['ADMIN_ID'], 7, 4, '进销存导出');
        $objPHPExcel->getActiveSheet()->setTitle('order');
        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.date('Y-m-d').'进销存.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }
}
