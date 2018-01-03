<?php
namespace Shipping\Controller;
use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;

class ImportController extends AdminbaseController {

    protected $shipping;

    public function _initialize() {
        parent::_initialize();
        $this->shipping = D("Common/Shipping");
    }
    /*
     * 批量导入运单号
     */
    public function batch_import_track()
    {
        $shippings = $this->shipping->field('id_shipping,title')->where(array('status'=>1))->cache(true,7200)->select();
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('shipping', 'batch_import_track', $data);
            $id_shipping = $_POST['id_shipping'];
            $type = $_POST['type'];
            $data = $this->getDataRow($data);

            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $track_number = trim($row);
//                $weight = trim($row[2]);//重量
                //查找全局是否有重复运单号
                $finded = M('ShippingTrack')
                    ->field('track_number')
                    ->where(array(
                        'track_number' => $track_number,
                        'id_shipping'=>$id_shipping
                    ))
                    ->find();
                if ($finded) {
                    $infor['error'][] = sprintf('第%s行:  运单号:%s 运单号已存在.', $count++, $track_number);
                    continue;
                }else{

                    $add = array(
                        'id_shipping'=>$id_shipping,
                        'track_number'=>$track_number,
                        'type'=>$type,
                        'track_status'=> 0
                    );
                   $add =  M('ShippingTrack')->add($add);
                    if($add)$infor['success'][] = sprintf('第%s行: 运单号: %s添加成功 ', $count++, $track_number);
                    else $infor['error'][] = sprintf('第%s行: 运单号:%s 添加失败', $count++, $track_number);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '批量导入运单号',$path);
        }
        $this->assign('post',$_POST);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->assign('shippings',$shippings);
        $this->display();
    }

    public function interval_import_track()
    {

        $shippings = $this->shipping->field('id_shipping,title')->where(array('status'=>1))->cache(true,7200)->select();
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $id_shipping = $_POST['id_shipping'];
            $type = $_POST['type'];
            $start_track = I('post.start_track');
            $end_track = I('post.end_track');
            $data = array(
                'start_track'=>$start_track,
                'end_track'=>$end_track
            );
            //导入记录到文件
            $path = write_file('shipping', 'Import_import_track', $data);
            $count = 1;
            if($end_track-$start_track<0)
            {
                $infor['error'][] = sprintf('结束区间不能小于开始区间');
            }
            for($start_track;$end_track-$start_track>=0;$start_track++)
            {
                $finded = M('ShippingTrack')
                    ->field('track_number')
                    ->where(array(
                        'track_number' => $start_track,
                        'id_shipping'=>$id_shipping
                    ))
                    ->find();
                if ($finded) {
                    $infor['error'][] = sprintf('第%s行:  运单号:%s 运单号已存在.', $count++, $start_track);
                    continue;
                }else{
                    $add = array(
                        'id_shipping'=>$id_shipping,
                        'track_number'=>$start_track,
                        'type'=>$type,
                        'track_status'=> 0
                    );
                    $add =  M('ShippingTrack')->add($add);
                    if($add)$infor['success'][] = sprintf('第%s行: 运单号: %s ', $count++, $start_track);
                    else $infor['error'][] = sprintf('第%s行: 运单号:%s 添加失败', $count++, $start_track);
                }
                if($count>1000){
                    $infor['error'][] = sprintf('一次性导入不能超过1000条');
                    break;
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '区间导入运单号',$path);
        }
        $this->assign('post',$_POST);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->assign('shippings',$shippings);
        $this->display();
    }

    public function delete_shipping() {
        D('Order/OrderShipping')->where(array('id_order_shipping'=>1437822))->delete();
    }

    public function update_track() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        $shipping = M('Shipping')->getField('id_shipping,title',true);//物流名称
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_track', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $id_increment = $row[0];//订单号
                $track_number = $row[1];//运单号
                if ($track_number) {
                    $select_order = M('Order')->where(array('id_increment'=>trim($id_increment)))->find();//获取订单信息
                    if ($select_order) {
                        $id_order = $select_order['id_order'];
                        D('Order/OrderShipping')
                            ->add(array(
                                'id_order' => $id_order,
                                'id_shipping' => $_POST['shipping_id'],
                                'shipping_name' => $shipping[$_POST['shipping_id']], //TODO: 加入物流名称
                                'track_number' => $track_number,
                                'fetch_count' => 0,
                                'is_email' => 0,
                                'status_label' => '',
                                'date_delivery' => $select_order['date_delivery'],
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s'),
                            ));
                        $info['success'][] = sprintf('第%s行: 运单号:%s 订单号:%s 并存运单号成功',$count++, $track_number,$id_increment);
                    } else {
                        $info['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 没有找到订单', $count++, $id_increment,$track_number);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '并存运单号', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->assign('shipping',$shipping);
        $this->display();
    }
    
    /**
     * 匹配订单信息
     */
    public function  matching_order(){
        $infor = array( 'error'   => array(),'warning' => array(), 'success' => array() ); 
        $ordShip = D("Order/OrderShipping");
        $order_item = D('Order/OrderItem');
        $orders = array();
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('Shipping', 'matching_order', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            $fields='id_order,id_increment,id_department,tel,id_shipping,id_order_status,created_at,date_delivery,id_zone,address,remark,comment,payment_method,id_warehouse,first_name,last_name,zipcode';
            foreach ($data as $key=> $row) {
                $row = trim($row);
                $tipstr='';
                if (empty($row)){
                    continue;
                }                                   
                $find = $ordShip->where(array('track_number' => trim($row)))->find();      
                if(!preg_match("/^\d*$/",$row)&&!$find){
                    $infor['error'][] = sprintf('第%s行:  ：%s  无法找到匹配订单',($key+1),$row); 
                    continue;
                }                    
                $findOrder=M('order')->where(array('id_increment'=>trim($row)))->field($fields)->find();
                if($find){
                    $get_order = D("Order/Order")->field($fields)->where(array('id_order' => $find['id_order']))->find();
                    $track_number['track_number'] = $row;
                    $orders[] = array_merge($get_order,$track_number);                         
                }else if($findOrder){
                    $findOrder['track_number']=$ordShip->where(array('id_order' => trim($findOrder['id_order'])))->getField('track_number');
                    $orders[]=$findOrder;
                }else{                        
                    $infor['error'][] = sprintf('第%s行:  ：%s  无法找到匹配订单',($key+1),$row); 
                } 
            }
//            if($infor['error']){
//                $this->assign('infor', $infor);
//                $this->assign('data', I('post.data'));
//                $this->assign('total', count($data));              
//                $this->display();                
//                exit();          
//            }    
            if($orders){
                $column = "运单号,订单号, 产品名, 属性和数量, SKU,部门, 总数量,订单状态,物流,物流状态,下单时间,发货时间\n";
                $orderstatus=M('OrderStatus')->where(array('status'=>1))->getField('id_order_status,title');
                $shipping_data = D('Common/Shipping')->cache(true,36000)->getField('id_shipping,title');
                $department  = D('Department/Department')->where('type=1')->cache(true,3600)->getField('id_department,title');
                $zoneList=M('zone')->cache(true,36000)->getField('id_zone,title');
                $warehouseList=M('warehouse')->cache(true,36000)->where(array('status'=>1))->getField('id_warehouse,title');
                foreach ($orders as $o){
                    $product_name = [];
                    $all_attrs=[];
                    $sum_num=0;
                    $totalPrice=0;
                    $attrs='';
                    $skus =[];
                    $products = $order_item->get_item_list($o['id_order']);
                    foreach ($products as $p) {
                         $all_attrs[]=implode('+',unserialize($p['attrs_title'])).' x '. $p['quantity'] . "  ";
                         $skus[]=$p['sku'];
                         $product_name []= $p['inner_name'];
                         $sum_num +=(int) $p['quantity'];
                         $totalPrice+=$p['price'];
                         if(!empty($p['sku_title'])) {
                             $attrs .= ';' . $p['sku_title'] . ' x ' . $p['quantity'] . "  ";

                         } else {
                             $attrs .= ';' . $p['product_title'] . ' x ' . $p['quantity'] . "  ";
                         }
                    }
                    $sku = implode(';',$skus);
                    $attrs=trim($attrs,';');
                    $product_name=  implode(';', array_unique($product_name));
                    $product_name= str_replace(',', ' ', $product_name);
                    $order_shipping = M('OrderShipping')->field('status_label')->where(array('id_order'=>$o['id_order']))->select();
                    $trackStatusLabel = $order_shipping ? implode(',', array_column($order_shipping, 'status_label')) : ''; 
                    $trackStatusLabel=  str_replace(',', ' ', $trackStatusLabel);
                    $column.=$o['track_number']. "\t," .$o['id_increment']."\t,".$product_name . ',' .$attrs. ',' .trim($sku, ','). "\t," .$department[$o['id_department']] . ','. $sum_num . ',' .$orderstatus[$o['id_order_status']] . ',' . $shipping_data[$o['id_shipping']] . ',' .$trackStatusLabel. ',' .$o['created_at']. "\t,".$o['date_delivery']. "\t\n" ;
                }             
                add_system_record(sp_get_current_admin_id(), 7, 4, '导出匹配订单信息',$path);
                $filename = date('Ymd') . '订单信息.csv'; //设置文件名
                $this->export_csv($filename, iconv("UTF-8","GBK//IGNORE",$column)); //导出
                exit;                  
            }
        }
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();        
    }
    protected function export_csv($filename, $data) {    
        header("Content-type:text/csv;");
        header("Content-Disposition:attachment;filename=" . $filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }        
}
