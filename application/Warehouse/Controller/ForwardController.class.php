<?php
namespace Warehouse\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use Order\Model\UpdateStatusModel;

class ForwardController extends AdminbaseController {
    protected $Warehouse, $orderModel;

    public function _initialize() {
        parent::_initialize();
        $this->Warehouse = D("Common/Warehouse");
        $this->orderModel = D("Order/Order");
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row']?$_SESSION['set_page_row']:20;
        $this->forward_status=array("0"=>'未匹配',"1"=>"已匹配");
    }
    /**
     * 导入
     */
    public function import() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $model = new \Think\Model;
        $order_table_name = D('Order/Order')->getTableName();
        $order_item_table_name = D('Order/OrderItem')->getTableName();
        $belong_ware_id = $_SESSION['belong_ware_id'];
//        dump($belong_ware_id);die;
        $where['status'] = 1;
        if(count($belong_ware_id) != 1 || (count($belong_ware_id) == 1 && $belong_ware_id[0] != 1)) {
            //$where['id_warehouse'] = array('IN',$belong_ware_id);
        }
        $where['forward'] = 1;
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where($where)->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'forward', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $track_number = str_replace("'", '', $row[0]);
                $track_number = str_replace(array('"',' ',' ','　'),'', $track_number);
                $track_number = trim($track_number);
                $finded = M('OrderShipping')->field('id_order, track_number')->where(array('track_number' => $track_number))->find();
                if($finded) {
                    $id_order = $finded['id_order'];//订单id
                    $order = M('Order')->field('id_order_status,id_increment,id_warehouse,id_department,id_order,id_zone')->where(array('id_order'=>$id_order))->find();
                    if($order['id_order_status']==OrderStatus::DELIVERING || $order['id_order_status']==OrderStatus::RETURNED || $order['id_order_status']==OrderStatus::REJECTION || $order['id_order_status']==OrderStatus::CLAIMS || $order['id_order_status']==OrderStatus::MATCH_FINISH) {
                        D('Order/Order')->where(array('id_order'=>$id_order))->save(array('id_order_status'=>OrderStatus::FORWARD));
                        D("Order/OrderRecord")->addHistory($id_order, OrderStatus::FORWARD, 4, '更新转寄状态，运单号' . $track_number.'，仓库' .$warehouse[$_POST['warehouse_id']]);
                        $ProductData=D("Common/OrderItem")->where(array('id_order'=>$id_order))->field('sku_title,sku,id_product,id_product_sku,product_title,quantity')->select();
                        if(!empty($ProductData)){
                            $Prodate['track_number']=$track_number;
                            $Prodate['id_increment']=$order['id_increment'];
                            $Prodate['created_at']=date('Y-m-d H:i:s');
                            $Prodate['updated_at']=date('Y-m-d H:i:s');
                            $Prodate['id_warehouse']= $_POST['warehouse_id'];

                            $Prodate['id_order']=$order['id_order'];

                            $id_product_sku_arr = array();
                            foreach($ProductData as $k=>$v){
                                $id_product_sku_arr[] = $v['id_product_sku'];
                                $Prodate['sku']=$v['sku'];
                                $Prodate['id_product']=$v['id_product'];
                                $Prodate['id_department']=M('Product')->where(["id_product"=>$Prodate['id_product']])->getField('id_department');
                                $Prodate['inner_name']=M('Product')->where(["id_product"=>$Prodate['id_product']])->getField('inner_name');
                                $Prodate['id_product_sku']=$v['id_product_sku'];
                                $Prodate['title']=$v['product_title'];
                                $Prodate['code']=M('ProductSku')->where(["id_product_sku"=>$Prodate['id_product_sku']])->getField('barcode');
                                $Prodate['total']=$v['quantity'];

                                //$Prodate['option_value']=$v['sku_title'];

                                M('Forward')->add($Prodate);
                            }

                            //获取当地仓库的缺货订单
                            $model = new \Think\Model;
                            $order_table_name = D('Order/Order')->getTableName();
                            $order_item_table_name = D('Order/OrderItem')->getTableName();
                            $id_product_sku_arr = implode(',',$id_product_sku_arr);
                            $where = 'oi.id_product_sku in ('.$id_product_sku_arr.') and o.id_order_status=6 and o.id_zone = '. $order['id_zone'];
                            $order_data_arr = $model->table($order_table_name . ' as o LEFT JOIN ' . $order_item_table_name . ' as oi ON o.id_order=oi.id_order')
                                ->field('oi.id_order')
                                ->where($where)
                                ->select();

                            foreach($order_data_arr as $val) {
                                $res_one = UpdateStatusModel::match_forward_order($val['id_order']);
                                if ($res_one['flag'])
                                {
                                    UpdateStatusModel::into_forward_order($val['id_order'],$res_one['data']);
                                }
                            }
                        }
                        $infor['success'][] = sprintf('第%s行: 订单号:%s 运单号:%s 仓库名称: %s', $count++, $order['id_increment'], $track_number, $warehouse[$_POST['warehouse_id']]);
                    } else {
                        $infor['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 该运单号不能进行更新转寄状态操作', $count++, $order['id_increment'], $track_number);
                    }
                } else {
                    $infor['error'][] = sprintf('第%s行: 运单号:%s 没有该运单号', $count++, $track_number);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, '更新转寄状态', $path);
        }
        $this->assign('post', $_POST);
        $this->assign('warehouse', $warehouse);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    public function export() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();
        $column = array(
            '运单号', '订单号', '部门', '产品名', '内部名',  'sku','数量', '所属仓库','状态'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        $idx = 2;
        if (!empty($_GET['department_id'])) {
            $where['id_department'] = array('EQ', $_GET['department_id']);
        }
        if (!empty($_GET['warehouse_id'])) {
            $where['id_warehouse'] = array('EQ', $_GET['warehouse_id']);
        }
        if ($_GET['status']!=666 && isset($_GET['status'])) {
            $where['status'] = array('EQ', $_GET['status']);
        }
        if(!empty($_GET['id_increment'])) {
            $where['id_increment'] = array('LIKE', '%' . $_GET['id_increment'] . '%');
        }
        if(!empty($_GET['title'])) {
            $where['title'] = array('LIKE', '%' . $_GET['title'] . '%');
        }
        if(!empty($_GET['inner_name'])) {
            $where['inner_name'] = array('LIKE', '%' . $_GET['inner_name'] . '%');
        }
        if(!empty($_GET['sku'])) {
            $where['_string'] = "sku='".$_GET['sku']."' or code='".$_GET['sku']."'";
            //$where['sku'] = $_GET['sku'];
        }
        if(!empty($_GET['track_number'])) {
            $where['track_number'] = array('LIKE', '%' . $_GET['track_number'] . '%');
        }
        $department = M('Department')->where('type=1')->select();
        $department = array_column($department, 'title', 'id_department');
        $warehouse = M('Warehouse')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $model = new \Think\Model;
        $track_numberArray = D('Forward')->field('track_number')->where($where)->group('track_number')->order("status ASC")->select();
        $trackArray=array();
        foreach($track_numberArray as $v){
            array_push($trackArray,$v['track_number']);
        }
        $data=array();
        $temp = [];
        $all_sku = array();
        if(!empty($trackArray)){
            $pro_list=D('Forward')->field('*')->where(['track_number'=>array('IN',$trackArray)])->order("status ASC")->select();
//            var_dump($pro_list);die;
            $trackArray2=array();
            foreach($pro_list as $k => $v){
                if(in_array($v['track_number'],$trackArray2)){
                    $temp[$v['track_number']]+=$v['total'];
                    if(in_array($all_sku,$v['sku'])){
                        $sum += $v['total'];
                    }
                    else{
                        array_push($all_sku,$v['sku']);
                        $sum = $v['total'];
                    }
                    $data[$v['track_number']]['skuarray'] .= $v['sku'].'  X  '.$v['total'].';';
                }else{
                    array_push($trackArray2,$v['track_number']);
                    $data[$v['track_number']]=$v;
                    $temp[$v['track_number']]=$v['total'];
                    $data[$v['track_number']]['titlearray']='';
                    $data[$v['track_number']]['innerarray']='';
//                    echo $v['sku'];die;
                    if(in_array($all_sku,$v['sku'])){
                        $sum += $v['total'];
                    }
                    else{
                        array_push($all_sku,$v['sku']);
                        $sum = $v['total'];
                    }
                    $data[$v['track_number']]['skuarray'] .= $v['sku'].' X '.$v['total'].';';
                }
//                $skuString=$v['sku'] .":".$v['total'];
//                $data[$v['track_number']]['skuarray'] .=$skuString."
//";
//                $data[$v['track_number']]['skuarray'] .=$v['sku'].'  X  '. $temp[$v['track_number']].";";
                $data[$v['track_number']]['skucnt'] .=$v['total']."";
                $titleString=$v['title'];
                if(strpos($data[$v['track_number']]['titlearray'],$titleString)===false){
                    $data[$v['track_number']]['titlearray'] .=$titleString."";
                }
                $innerString=$v['inner_name'];

                if(strpos($data[$v['track_number']]['innerarray'],$innerString)===false){
                    $data[$v['track_number']]['innerarray'] .=$innerString."";
                }


            }
        }
        $forward_status=$this->forward_status;
        if(!empty($data)){
            foreach($data as $k =>$item){
                $outdata[] = array(
                    $item['track_number'],$item['id_increment'],$department[$item['id_department']],$item['titlearray'],$item['innerarray'],$item['skuarray'],$temp[$item['track_number']],
                    $warehouse[$item['id_warehouse']],$forward_status[$item['status']]
                );

            }
        }
        if ($outdata) {
            foreach ($outdata as $items) {
                $j = 65;
                foreach ($items as  $key=> $col) {
                    if($key == 0||$key==1){
                        $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                    } else {
                        $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                    }                    
                    ++$j;
                }
                ++$idx;
            }
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出转寄仓库存列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '转寄仓库存列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '转寄仓库存列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
    public function index() {
        if (!empty($_GET['department_id'])) {
            $where['id_department'] = array('EQ', $_GET['department_id']);
        }
        if (!empty($_GET['warehouse_id'])) {
            $where['id_warehouse'] = array('EQ', $_GET['warehouse_id']);
        }
        if ($_GET['status']!=666 && isset($_GET['status'])) {
            $where['status'] = array('EQ', $_GET['status']);
        }
        if(!empty($_GET['id_increment'])) {
            $where['id_increment'] = array('LIKE', '%' . $_GET['id_increment'] . '%');
        }
        if(!empty($_GET['title'])) {
            $where['title'] = array('LIKE', '%' . $_GET['title'] . '%');
        }
        if(!empty($_GET['inner_name'])) {
            $where['inner_name'] = array('LIKE', '%' . $_GET['inner_name'] . '%');
        }
        if(!empty($_GET['sku'])) {
            $where['_string'] = "sku='".$_GET['sku']."' or code='".$_GET['sku']."'";
            //$where['sku'] = $_GET['sku'];
        }
        if(!empty($_GET['track_number'])) {
            $where['track_number'] = array('LIKE', '%' . $_GET['track_number'] . '%');
        }
        $department = M('Department')->where('type=1')->select();
        $department = array_column($department, 'title', 'id_department');
        $warehouse = M('Warehouse')->where('forward=1 and status=1')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $model = new \Think\Model;

        $count =D('Forward')->where($where)->field('track_number')->group('track_number')->select();
        //var_dump($count);die;
        $count=count($count);
        $page = $this->page($count,20);
        $track_numberArray = D('Forward')->field('track_number')->where($where)->group('track_number')->order("status ASC")->limit($page->firstRow . ',' . $page->listRows)->select();
        $trackArray=array();
        foreach($track_numberArray as $v){
            array_push($trackArray,$v['track_number']);
        }
        $data=array();
        if(!empty($trackArray)){
            $pro_list=D('Forward')->field('*')->where(['track_number'=>array('IN',$trackArray)])->order("status ASC")->select();

            $trackArray2=array();
            foreach($pro_list as $k => $v){
                if(in_array($v['track_number'],$trackArray2)){
                }else{
                    array_push($trackArray2,$v['track_number']);
                    $data[$v['track_number']]=$v;
                    $data[$v['track_number']]['skuarray']=array();
                    $data[$v['track_number']]['codearray']=array();
                    $data[$v['track_number']]['titlearray']=array();
                    $data[$v['track_number']]['innerarray']=array();
                    $data[$v['track_number']]['totalarray']=array();
                }
                $skuString=$v['sku'];
                array_push($data[$v['track_number']]['skuarray'],$skuString);
                $totalString=$v['total'];
                array_push($data[$v['track_number']]['totalarray'],$totalString);
                $codeString=$v['code'];
                array_push($data[$v['track_number']]['codearray'],$codeString);
                $titleString=$v['title'];
                array_push($data[$v['track_number']]['titlearray'],$titleString);
                $innerString=$v['inner_name'];
                array_push($data[$v['track_number']]['innerarray'],$innerString);
                $data[$v['track_number']]['titlearray']=array_unique($data[$v['track_number']]['titlearray']);
                $data[$v['track_number']]['innerarray']=array_unique($data[$v['track_number']]['innerarray']);

            }

        }


        $this->assign("getData", $_GET);
        $this->assign('department', $department);
        $this->assign('warehouse', $warehouse);
        $this->assign("pro_list", $data);
        $this->assign("forward_status", $this->forward_status);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }


}
