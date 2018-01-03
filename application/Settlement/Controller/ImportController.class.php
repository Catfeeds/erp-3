<?php
/**
 * 结算模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Warehouse\Controller
 */
namespace Settlement\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use SystemRecord\Model\SystemRecordModel;

class ImportController extends AdminbaseController {
    protected $Warehouse, $orderModel;
    protected $paymenttype=['1'=>'理赔','2'=>'结算','3'=>'运费'];
    public function _initialize() {
        parent::_initialize();
        $this->Warehouse = D("Common/Warehouse");
        $this->orderModel = D("Order/Order");
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row']?$_SESSION['set_page_row']:20;
    }
    /**
     * 更新拒收价格
     */
    public function update_rejected(){
        if (IS_POST) {
            /* @var $ordShip \Common\Model\OrderShippingModel */
            $ordObj = D("Order/Order");
            $ordShipObj = D("Order/OrderShipping");
            $ordSetObj = D("Order/OrderSettlement");
            $orderShippingFor = D("Order/OrderShippingFormalities");
            $data = I('post.data');
            if(I('post.rejected_time')){
                $rejected_time=I('post.rejected_time');
            }else{
                $rejected_time=date("Y-m-d H:i:s");
            }

            //导入记录到文件
            $path = write_file('settlement', 'update_rejected', $data);

            $count = 1;
            $total = 0;
            $data = $this->getDataRow($data);
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 3);
                if (count($row) != 3 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
                $track_number = trim($row[0], '\'" ');
                $price = (float)$row[1];
                $refund_fee = (float)$row[2];
                $date_settlement = date("Y-m-d H:i:s");
                $getOrderShip = $ordShipObj->where("track_number='".$track_number."'")->find();
                $getRefundFee = $orderShippingFor->where("track_number='".$track_number."'")->field("refund_fee")->find();
                if($getOrderShip['id_order']){
                   $order_data = $ordObj->find($getOrderShip['id_order']);
                    $Department = M("Department")->field("title")->find($order_data['id_department']);
                    $findSet = $ordSetObj->field('amount_total,amount_settlement,status,remark')->where('id_order='.$getOrderShip['id_order'])->find();
                    if($findSet){
                        if(empty($findSet['remark'])){
                            //部分结款和已结款都可以退货
                            if ($findSet['status'] == 2 || $findSet['status'] == 1) {
                             if ($findSet['amount_settlement'] == 0) //导入的时候不减已经金额  退款金额作为单独字段存入数据库  --Lily 2017-10-30
                                    $update_data = array(
                                         'status' => 2, 'remark' => '退货导入', 'rejected_amount'=>$price
                                    );
                                else
                                    $update_data = array(
                                        'status' => 2, 'remark' => '退货导入', 'rejected_amount'=>$price
                                    );
                                if(!$getRefundFee){ // 导入退款手续费    --Lily  2017-12-04
                                $add_dataa = array(
                                        'track_number'  => $track_number,
                                        'id_order'=>$getOrderShip['id_order'],
                                        'surcharge'  => '',
                                        'back_fee'  => '',
                                        'collection_fee' => '',
                                        'refund_fee' => $refund_fee,
                                        'forward_fee' => '',
                                        'slotting_fee' => '',
                                        'operation_fee' => '',
                                        'other_fee' => '',
                                        'total_fee' => $refund_fee,
                                        'date_settlement' => $rejected_time
                                    );
                                    $ordShip_update_data = array('formalities_fee'=>$refund_fee);
                                    $orderShippingFor->add($add_dataa);
                                    $ordShipObj->where("id_order =".$getOrderShip['id_order'])->save($ordShip_update_data);
                                    $update_data['rejected_time']=$rejected_time;
                                    $ordSetObj->where('id_order=' . $getOrderShip['id_order'])->save($update_data);
                                    $ordObj->where(array('id_order' => $getOrderShip['id_order']))->save(array('id_order_status' => OrderStatus::RETURNED));
                                    D("Order/OrderRecord")->addHistory($getOrderShip['id_order'], OrderStatus::RETURNED, 4, '导入拒收,退货金额为：' . $price);
                                    $infor['success'][] = sprintf('第%s行: 订单号:%s 运单号:%s 部门: %s 退货金额: %s 退货手续费: %s 更新成功', $count, $order_data['id_increment'], $track_number, $Department['title'],$price,$refund_fee);
                                 } else {
                                    if($getRefundFee['refund_fee']>0){
                                       $infor['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 部门：%s 该运单号退货手续费已经导入，不能进行导入', $count, $order_data['id_increment'], $track_number,$Department['title']);
                                    }else{
                                      $refund_fee_data = array(
                                        'refund_fee'=>$refund_fee
                                        );
                                      $formalities_fee = array('formalities_fee'=>$refund_fee+$getOrderShip['formalities_fee']);
                                      $ordShipObj->where("id_order='".$getOrderShip['id_order']."'")->save($formalities_fee);
                                      $orderShippingFor->where("track_number='".$track_number."'")->save($refund_fee_data);
                                       $update_data['rejected_time']=$rejected_time;
                                        $ordSetObj->where('id_order=' . $getOrderShip['id_order'])->save($update_data);
                                        $ordObj->where(array('id_order' => $getOrderShip['id_order']))->save(array('id_order_status' => OrderStatus::RETURNED));
                                        D("Order/OrderRecord")->addHistory($getOrderShip['id_order'], OrderStatus::RETURNED, 4, '导入拒收,退货金额为：' . $price);
                                        $infor['success'][] = sprintf('第%s行: 订单号:%s 运单号:%s 部门: %s 退货金额: %s 退货手续费: %s 更新成功', $count, $order_data['id_increment'], $track_number, $Department['title'],$price,$refund_fee);
                                    }
                             }
                              } else {
                                $infor['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 部门：%s 该运单号还没有结款，不能进行退款导入', $count, $order_data['id_increment'], $track_number,$Department['title']);
                            }
                          } else {
                            $infor['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 部门：%s 该运单号已经进行了退货导入或者其他导入，不能重复进行导入', $count, $order_data['id_increment'], $track_number,$Department['title']);
                        }
                    }else{
                        $infor['error'][] = sprintf('第%s行:没有结款记录', $count);
                    }

                }else{
                    $infor['error'][] = sprintf('第%s行:找不到订单ID', $count);
                }
                $count++;
                //D("Common/OrderStatusHistory")->addHistory($order_id, $orderObj['status_id'], '更新物流'.$row[0]);
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 2, '导入拒收：更新结算金额为0',$path);
        }
        $this->assign('infor', $infor);
        $this->assign('data',I('post.data'));
        $this->display();
    }
    public function export_match_name(){
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();
        $ordShipping = D('Order/OrderShipping');
        if (IS_POST) {
            $data = I('post.data');

            //导入记录到文件
            $path = write_file('settlement', 'export_match_name', $data);

            $data = $this->getDataRow($data);
            //$count = 1;
            //$user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $total = 0;
            $column = array('订单号','运单号','部门','姓名','电话','结算状态', '结算金额', '产品名称','下单时间','发货时间');
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
                $row = explode("\t", trim($row), 2);
                $row = array_filter($row);
                $name = trim($row[0]);
                $select = D('Order/Order')->field('id_order,first_name,tel,created_at,date_delivery,id_department')
                    ->where(array('first_name'=>$name,'price_total'=>trim($row[1])))
                    ->select();
                foreach($select as $item){
                    $trackNumber = D('Order/OrderShipping')
                        ->where(array('id_order'=>$item['id_order']))
                        ->getField('track_number',true);
                    $trackNumber = $trackNumber?implode(',',$trackNumber):'';
                    $departName = D('Common/Department')->where(array('id_department'=>$item['id_department']))
                            ->getField('title',true);
                    $departName = $departName?implode(',', $departName):'';
                    $productName = D('Order/OrderItem')
                        ->where(array('id_order'=>$item['id_order']))
                        ->getField('product_title',true);
                    $productName = $productName?implode('  ,  ',$productName):'';
                    $settlement  = D('Order/OrderSettlement')->where('id_order='.$item['id_order'])->find();
                    $status = '未结';
                    if($settlement){
                        switch($settlement['status']){
                            case 0:
                                $status = '未结';
                                break;
                            case 1:
                                $status = '结款中';
                                break;
                            case 2:
                                $status = '已结';
                                break;
                        }
                    }
                    $rowData = array($item['id_order'],' '.$trackNumber,' '.$departName,$item['first_name'],$item['tel'],
                        $status,$row[1],$productName,$item['created_at'],$item['date_delivery']);
                    $j = 65;
                    foreach ($rowData as $col) {
                        $excel->getActiveSheet()->setCellValue(chr($j).$idx, $col);
                        ++$j;
                    }
                    ++$idx;
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 2, '批量导出用户名，金额',$path);

            $excel->getActiveSheet()->setTitle(date('Y-m-d').'分组.xlsx');
            $excel->setActiveSheetIndex(0);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . date('Y-m-d').'分组.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $writer->save('php://output');exit();
        }
        $this->display();
    }
    public function amount(){
        $infor = array(
            'error'   => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('settlement', 'amount', $data);

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
//                if (strpos($row[0], '-') && strpos($row[0], '-1/') === false) {
//                    //跳过一单多产品的其余订单
//                    $infor['warning'][] = sprintf('第%s行: 订单号:%s 跳过多单号更新.', $count++, $row[0]);
//                    continue;
//                }
                $track_number = trim($row[0], '\'" ');
                $price = (float)$row[1];
//                $freight_price = (float)$row[2];
//                if ($price < 0.0) {
//                    $infor['error'][] = sprintf('第%s行: 运单号:%s 金额:%s 收款金额不能是负数', $count++, $track_number, $price);
//                    continue;
//                }
                //$price = abs($price);
                //OrderShipping表里每一行代表一个运单号, 如果订单有多个运单号, 那么就是多行数据
                $shipping_info = D('Order/OrderShipping')->where(array(
                    'track_number' => $track_number
                ))->find();

                if (!$shipping_info) {
                    $infor['error'][] = sprintf('第%s行: 运单号:%s 不存在.', $count++, $track_number);
                    continue;
                }
//                if($shipping_info) {
//                    if((float)$shipping_info['freight'] != $freight_price) {
//                        $infor['error'][] = sprintf('第%s行: 运单号:%s 运费不一致.', $count++, $track_number);
//                        continue;
//                    }
//                }
                $settle = D("Order/OrderSettlement")
                    ->where('id_order='.$shipping_info['id_order'])
                    ->find();

                $order_payment = D('Order/Order')->alias('o')->field('o.payment_method,o.id_increment,title')
                                              ->join('__DEPARTMENT__ d on d.id_department = o.id_department')
                                              ->where('id_order='.$shipping_info['id_order'])->find();

                if($order_payment['payment_method'] !== '0') {
                    $infor['warning'][] = sprintf('第%s行: 运单号:%s 订单号:%s 业务组:%s  TF订单不能进行结款', $count++, $track_number, $order_payment['id_increment'],$order_payment['title']);
                    continue;
                }
                $group_data = '';
                if ($settle) {
                    $select = D('Order/Order')->alias('o')->field('o.id_increment,title,o.id_department,o.created_at')
                                              ->join('__DEPARTMENT__ d on d.id_department = o.id_department')
                                              ->where('id_order='.$settle['id_order'])->find();
                    if ($settle['status'] == 2) {
                        //已经结款的不能再更新
                        $infor['warning'][] = sprintf('第%s行: 运单号:%s 订单号:%s 业务组:%s  已经结款不能再结款', $count++, $track_number, $select['id_increment'],$select['title']);
                        continue;
                    }
                    $amount = $settle['amount_settlement'] + $price;
                    //TODO: 结款状态: 0未结款, 1部分结款, 2已结款
                    $status = $amount == $settle['amount_total'] ? 2 : 1;
                    $data = array(
                        'id_users' => $user_id,
                        'amount_settlement' => $amount,
                        'id_order_shipping' => $shipping_info['id_order_shipping'],
                        'date_settlement' => $_POST['settle_date'].' '.date('H:i:s'),
                        'status' => $status,
                        'updated_at' => date('Y-m-d H:i:s')
                    );
                    if ($status == 2) {
                        $shipping_data['is_settlemented'] = 1;
                    }
                    D("Order/OrderSettlement")->where('id_order_settlement=' . $settle['id_order_settlement'])->save($data);
                    M('Order')->where('id_order='.$settle['id_order'])->save(array('id_order_status'=>'9'));

                    //zhujie
                    $shipping_data['status_label'] = "順利送達";
                    $shipping_data['summary_status_label'] = "順利送達";
                    D('Order/OrderShipping')->where('id_order='.$settle['id_order'])->save($shipping_data);

                    $orderId = $shipping_info['id_order'];
                    $remark  = '更新结算:'.$amount;
                    if($select['id_department']==3){
                        $group_data = strtotime($select['created_at'])>strtotime('2017-03-01 00:00:00')?' <span style="color:red">袁昭明组</span>':' <span style="color:red">王园林组</span>';
                    }
                } else {
                    $amount = $price;
                    $order = D('Order/Order')->where(array(
                        'id_order' => $shipping_info['id_order']
                    ))->find();

                    // 结款状态: 0未结款, 1部分结款, 2已结款
                    $status = $amount == $order['price_total'] ? 2 : 1;
                    $data = array(
                        'id_order' => $order['id_order'],
                        'id_users' => $user_id,
                        'amount_total' => $order['price_total'],
                        'amount_settlement' => $amount,
                        'status' => $status,
                        'date_settlement' => $_POST['settle_date'].' '.date('H:i:s'),
                        'id_order_shipping' => $shipping_info['id_order_shipping'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    );
                    if ($status == 2) {
                        $shipping_data['is_settlemented'] = 1;
                    }
                    D("Order/OrderSettlement")->add($data);
                    M('Order')->where('id_order='.$order['id_order'])->save(array('id_order_status'=>'9'));

                    //zhujie 
                    $shipping_data['status_label'] = "順利送達";
                    $shipping_data['summary_status_label'] = "順利送達";
                    D('Order/OrderShipping')->where('id_order=' . $order['id_order'])->save($shipping_data);
                    $orderId = $order['id_order'];
                    $remark  = '添加结算:'.$amount;
                    if($order['id_department']==3){
                        $group_data = strtotime($order['created_at'])>strtotime('2017-03-01 00:00:00')?' <span style="color:red">袁昭明组</span>':' <span style="color:red">王园林组</span>';
                    }
                }
                $orderObj = D('Order/Order')->alias('o')->field('o.id_increment,title,id_order_status')
                    ->join('__DEPARTMENT__ d on d.id_department = o.id_department')
                    ->where('id_order='.$settle['id_order'])->find($orderId);
//                $orderObj = D("Order/Order")->find($orderId);
                if($orderObj){
                    D("Order/OrderRecord")->addHistory($orderId,$orderObj['id_order_status'],4,$remark);
                }
                $success = '';
                if ($status == 2) {
                    $success = '结款完成';
                } else if ($status === 1) {
                    $success = '部分结款';
                }

                $infor['success'][] = sprintf('第%s行: 运单号:%s 订单号:%s 业务组:%s 金额:%s %s %s', $count++, $track_number,$orderObj['id_increment'],$orderObj['title'], $price, $success,$group_data);
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 2, '导入结算：更新订单结款',$path);
        }

        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /*
     * 导入运费
     */
    public function export_freight() {
        $infor = array(
            'error'   => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('settlement', 'export_freight', $data);

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
                $track_number = trim($row[0], '\'" ');//运单号
                $freight_price = (float)$row[1];//运费

                $shipping_info = M('OrderShipping')->where(array(
                    'track_number' => $track_number
                ))->find();

                if (!$shipping_info) {
                    $infor['error'][] = sprintf('第%s行: 运单号:%s 不存在.', $count++, $track_number);
                    continue;
                } else {
                    if($shipping_info['freight']>0){
                        $infor['error'][] = sprintf('第%s行: 运单号:%s 运费已经导入，不能重复导入.', $count++, $track_number);
                        continue;
                     }else{
                      $select = M('Order')->alias('o')->field('o.id_increment,title')
                            ->join('__DEPARTMENT__ d on d.id_department = o.id_department')
                            ->where('id_order='.$shipping_info['id_order'])->find();

                    $data = array(
                        'freight'=>$freight_price
                    );
                    D('Order/OrderShipping')->where('id_order='.$shipping_info['id_order'])->save($data);
                    $infor['success'][] = sprintf('第%s行: 运单号:%s 订单号:%s 业务组:%s 运费:%s', $count++, $track_number,$select['id_increment'],$select['title'], $freight_price);   
                    }
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '结款管理导入运费',$path);
        }
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /*
     * 导入退货手续费
     */
    public function export_hand_fee() {
        $infor = array(
            'error'   => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('settlement', 'export_hand_fee', $data);

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
                $track_number = trim($row[0], '\'" ');//运单号
                $hand_price = (float)$row[1];//手续费

                $shipping_info = M('OrderShipping')->where(array(
                    'track_number' => $track_number
                ))->find();

                if (!$shipping_info) {
                    $infor['error'][] = sprintf('第%s行: 运单号:%s 不存在.', $count++, $track_number);
                    continue;
                } else {
                    $select = M('Order')->alias('o')->field('o.id_increment,title')
                            ->join('__DEPARTMENT__ d on d.id_department = o.id_department')
                            ->where('id_order='.$shipping_info['id_order'])->find();

                    $data = array(
                        'formalities_fee'=>$hand_price
                    );
                    D('Order/OrderShipping')->where('id_order='.$shipping_info['id_order'])->save($data);
                    $infor['success'][] = sprintf('第%s行: 运单号:%s 订单号:%s 业务组:%s 手续费:%s', $count++, $track_number,$select['id_increment'],$select['title'], $hand_price);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '结款管理导入退货手续费',$path);
        }
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    public function update_claims(){
        if (IS_POST) {
            $ordObj = D("Order/Order");
            $ordShipObj = D("Order/OrderShipping");
            $ordSetObj = D("Order/OrderSettlement");
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('settlement', 'update_claims', $data);
            $count = 1;
            $total = 0;
            $data = $this->getDataRow($data);
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 3);
                if (count($row) != 3 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
                $track_number = trim($row[0], '\'" ');
                $price = (float)trim($row[1], '\'" ');
                $comment = $row[2];
                $getOrderShip = $ordShipObj->where("track_number='".$track_number."'")->find();
                if($getOrderShip['id_order']){
                    $order_data = $ordObj->find($getOrderShip['id_order']);
                    $findSet = $ordSetObj->field('amount_total,amount_settlement,status')->where('id_order = '.$getOrderShip['id_order'],'status'.'!=2')->find();
                    if($findSet){
                            $update_data = array(
                                'amount_settlement'=>$price ,'status'=>2,'remark'=>$comment
                            );
                        $update_data['date_settlement'] = date('Y-m-d H:i:s');
                        //$update_data['id_users'] = $_SESSION['ADMIN_ID'];
                        $ordSetObj->where('id_order='.$getOrderShip['id_order'])->save($update_data);
                        $ordObj->where('id_order='.$getOrderShip['id_order'])->save(array('id_order_status'=>'19'));
                        D("Order/OrderRecord")->addHistory($getOrderShip['id_order'], 19, 4,'理赔导入：更新结算为：'.$update_data['amount_settlement'].'理赔金额为：'.$price);
                        $infor['success'][] = sprintf('第%s行: 运单号:%s  理赔金额：%s  备注：%s 更新成功', $count, $track_number,$price,$comment);
                    }else{
                        $order = $ordObj->where(array('id_order'=>$getOrderShip['id_order']))->find();
                        $add_data['id_users'] = $_SESSION['ADMIN_ID'];
                        $add_data['id_order_shipping'] = $getOrderShip['id_order_shipping'];
                        $add_data['id_order'] = $getOrderShip['id_order'];
                        $add_data['amount_total'] = $order['price_total'];
                        $add_data['amount_settlement'] = $price;
                        $add_data['date_settlement'] = date('Y-m-d H:i:s');
                        $add_data['created_at'] = date('Y-m-d H:i:s');
                        $add_data['remark'] = $comment;
                        $add_data['status'] = 2;
                        $ordSetObj->add($add_data);
                        $ordObj->where('id_order='.$getOrderShip['id_order'])->save(array('id_order_status'=>'19'));
                        D("Order/OrderRecord")->addHistory($getOrderShip['id_order'],19, 4,'理赔导入：更新结算为：'.$price.'理赔金额为：'.$price.json_encode($findSet));
                        $infor['success'][] = sprintf('第%s行: 运单号:%s  理赔金额：%s  备注：%s 更新成功', $count, $track_number,$price,$comment);
                    }
                }else{
                    $infor['error'][] = sprintf('第%s行:找不到订单ID', $count);
                }
                $count++;
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 2, '理赔导入',$path);
        }
        $this->assign('infor', $infor);
        $this->assign('data',I('post.data'));
        $this->display();
    }
   /*
   * 匹配信用卡通道订单产品
   */
    public function matching_credit(){
        $infor = array(
            'error'   => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        $orders = array();
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'matching_credit', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                if ($row) {
                    $find = M('Order')
                        ->where(array('id_increment'=>$row))
                        ->find();
                    $orders[] = $find;
                    if($find){
                        $infor['success'][] = sprintf('第%s行: 订单号：%s匹配成功', $count++,$row);
                    }else{
                        $infor['error'][] = sprintf('第%s行: 订单号：%s匹配失败', $count++,$row);
                    }
                } else {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }

        }
        if($orders){
            //导出excle
            try{
                set_time_limit(0);
                vendor("PHPExcel.PHPExcel");
                vendor("PHPExcel.PHPExcel.IOFactory");
                vendor("PHPExcel.PHPExcel.Writer.CSV");
                $excel = new \PHPExcel();
                $column = array(
                    '地区', '物流','业务组', '域名','广告专员', '订单号', '姓名', '电话号码',
                    '产品名和价格', '属性',
                    '留言备注', '下单时间', '发货日期', '订单状态', '运单号', '物流状态',
                    '结算状态', '已结算金额','运费','手续费', '总价（NTS）', '签收日期'
                );
                $j = 65;
                foreach ($column as $col) {
                    $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
                    ++$j;
                }
                $result = D('Order/OrderStatus')->select();
                $status = array();
                foreach ($result as $statu) {
                    $status[(int) $statu['id_order_status']] = $statu;
                }
                $order_item = D('Order/OrderItem');
                $idx = 2;
                $shipping = D('Common/Shipping')->cache(true,36000)->select();
                $shipping_data = array_column($shipping,'title','id_shipping');
                $order_record = D("Order/OrderRecord");
//                dump($orders);die;
                foreach ($orders as $o) {
                    if($o){
                        $product_name = '';
                        $attrs = '';
                        $products = $order_item->get_item_list($o['id_order']);
//                        $group = M("DepartmentGroup")->alias('dg')
//                            ->join('__GROUP_USERS__ as gu on gu.id_department = dg.id_department')
//                            ->where(array('gu.id_users'=>$o['id_users']))->getField('title');
                        $group = M("Department")->alias('d')
                            ->join('__DEPARTMENT_USERS__ as du on du.id_department = d.id_department')
                            ->where(array('du.id_users'=>$o['id_users']))->getField('title');
                        foreach ($products as $p) {
                            $inner_name = M('Product')->where(array('id_product'=>$p['id_product']))->getField('inner_name');
                            $product_name .= $inner_name . "  ";
                            if(!empty($p['sku_title'])) {
                                $attrs .= ',' . $p['sku_title'] . ' x ' . $p['quantity'] . "  ";
                            } else {
                                $attrs .= ',' . $p['product_title'] . ' x ' . $p['quantity'] . "  ";
                            }
                        }
                        $attrs = trim($attrs, ',');
                        $province = M('Zone')->where(array('id_zone'=>$o['id_zone']))->getField('title');
                        $name = !empty($o['id_users']) ? M('Users')->where('id='.$o['id_users'])->getField('user_nicename') : '未知';
                        $domain_name = !empty($o['id_domain']) ? M('Domain')->where(array('id_domain'=>$o['id_domain']))->getField('name') : '未知';
                        $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
                        $getShipObj = D("Order/OrderShipping")->field('track_number,status_label,date_signed,freight,formalities_fee')->where('id_order=' . $o['id_order'])->select();
                        $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
                        $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
                        $signedForDate = $getShipObj ? implode(',', array_column($getShipObj, 'date_signed')) : '';
                        $freight = $getShipObj ? implode(',', array_column($getShipObj, 'freight')) : '';
                        $formalities_fee = $getShipObj ? implode(',', array_column($getShipObj, 'formalities_fee')) : '';
                        switch ($o['status']) {
                            case 0:
                                $setStatus = ($o['status']===null)?'':'未结款';
                                $o['amount_settlement'] = $o['amount_settlement']==0?'':$o['amount_settlement'];
                                break;
                            case 1: $setStatus = '结款中';
                                break;
                            case 2: $setStatus = '已结款';
                                break;
                        }
                        $data = array(
                            $province, $shipping[$o['id_shipping']],$group,$domain_name, $name, ' '.$o['id_increment'], $o['first_name'], $o['tel'],
                            $product_name, $attrs,
                            $o['remark'], $o['created_at'],
                            $o['date_delivery'], $status_name, $trackNumber, $trackStatusLabel,
                            $setStatus, $o['amount_settlement'],$freight,$formalities_fee,$o['price_total'], $signedForDate
                        );
                        $j = 65;
                        foreach ($data as $key=>$col) {
                            if($key != 16 && $key != 17){
                                $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                            } else {
                                $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                            }
                            ++$j;
                        }
                        ++$idx;
                    }
                }

                add_system_record(sp_get_current_admin_id(), 6, 4, '导出匹配信用通道订单的产品');
                $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
                $excel->setActiveSheetIndex(0);
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
                header('Cache-Control: max-age=0');
                $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
                $writer->save('php://output');
                exit();
            }catch (\Exception $e){
                print_r($e->getMessage());
            }
        }

        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }



    /**
     * 导入列表
     */
    public function payment(){
        $getData = I('get.', "htmlspecialchars");
        if(!$getData['paymenttype']){
            $this->error('缺少必要参数！');
        }
        $cur_page = $getData['p']? : 1; //默认页数
        $findCond = [];
        if (!empty($getData['docno'])) {
            $findCond['docno'] = array('like', "%{$getData['docno']}%");
        }
        if (!empty($getData['status'])) {
            $findCond['status'] = $getData['status'];
        }
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }
        //默认开始，结束时间
        $start_time=$getData['start_time']?$getData['start_time']:date('Y-m-d',  strtotime('-7days'));
        $end_time=$getData['end_time']?strtotime($getData['end_time']):time();
        $findCond['billdate']=array('between',array($start_time,date('Y-m-d 23:59:59',$end_time)));
        $findCond['paymenttype']=$getData['paymenttype'];
        //获取盘点用户名称数组
        $userNames = [];
        $ownerIds = M('payment_import')->distinct(true)->where($findCond)->getField('ownerid', true);
        $statuserIds = M('payment_import')->distinct(true)->where($findCond)->getField('statuserid', true);
        $userIds = array_unique(array_merge($ownerIds, $statuserIds));
        $condUser = array();
        if ($userIds) {
            $condUser['id'] = array('in', implode(',', $userIds));
            $userNames = M('users')->where($condUser)->getField('id,user_login', true);
        }
        $claimsList = M('payment_import')->page("$cur_page,$this->page")->where($findCond)->select();
        $total = M('payment_import')->where($findCond)->count();
        $page = $this->page($total, $this->page);
        $this->assign("page", $page->show('Admin'));
        $this->assign('pmtype', $getData['paymenttype']);
        $this->assign('pmtypestr', $this->paymenttype[$getData['paymenttype']]);
        $this->assign('cur_data', date('Y-m-d'));
        $this->assign('getData', $getData);
        $this->assign('userNames', $userNames);
        $this->assign('claimsList', $claimsList);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', date('Y-m-d',$end_time));
        $this->display();
    }


/**
     * 导入列表
     */
    public function payment2(){
        $getData = I('get.', "htmlspecialchars");
        if(!$getData['paymenttype']){
            $this->error('缺少必要参数！');
        }
        $cur_page = $getData['p']? : 1; //默认页数
        $findCond = [];
        if (!empty($getData['docno'])) {
            $findCond['docno'] = array('like', "%{$getData['docno']}%");
        }
        if (!empty($getData['status'])) {
            $findCond['status'] = $getData['status'];
        }
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }
        //默认开始，结束时间
        $start_time=$getData['start_time']?$getData['start_time']:date('Y-m-d',  strtotime('-7days'));
        $end_time=$getData['end_time']?strtotime($getData['end_time']):time();
        $findCond['billdate']=array('between',array($start_time,date('Y-m-d 23:59:59',$end_time)));
        $findCond['paymenttype']=$getData['paymenttype'];
        //获取盘点用户名称数组
        $userNames = [];
        $ownerIds = M('payment_import')->distinct(true)->where($findCond)->getField('ownerid', true);
        $statuserIds = M('payment_import')->distinct(true)->where($findCond)->getField('statuserid', true);
        $userIds = array_unique(array_merge($ownerIds, $statuserIds));
        $condUser = array();
        if ($userIds) {
            $condUser['id'] = array('in', implode(',', $userIds));
            $userNames = M('users')->where($condUser)->getField('id,user_login', true);
        }
        $claimsList = M('payment_import')->page("$cur_page,$this->page")->where($findCond)->select();
        $total = M('payment_import')->where($findCond)->count();
        $page = $this->page($total, $this->page);
        $this->assign("page", $page->show('Admin'));
        $this->assign('pmtype', $getData['paymenttype']);
        $this->assign('pmtypestr', $this->paymenttype[$getData['paymenttype']]);
        $this->assign('cur_data', date('Y-m-d'));
        $this->assign('getData', $getData);
        $this->assign('userNames', $userNames);
        $this->assign('claimsList', $claimsList);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', date('Y-m-d',$end_time));
        $this->display('payment');
    }

/**
     * 导入列表
     */
    public function payment3(){
        $getData = I('get.', "htmlspecialchars");
        if(!$getData['paymenttype']){
            $this->error('缺少必要参数！');
        }
        $cur_page = $getData['p']? : 1; //默认页数
        $findCond = [];
        if (!empty($getData['docno'])) {
            $findCond['docno'] = array('like', "%{$getData['docno']}%");
        }
        if (!empty($getData['status'])) {
            $findCond['status'] = $getData['status'];
        }
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }
        //默认开始，结束时间
        $start_time=$getData['start_time']?$getData['start_time']:date('Y-m-d',  strtotime('-7days'));
        $end_time=$getData['end_time']?strtotime($getData['end_time']):time();
        $findCond['billdate']=array('between',array($start_time,date('Y-m-d 23:59:59',$end_time)));
        $findCond['paymenttype']=$getData['paymenttype'];
        //获取盘点用户名称数组
        $userNames = [];
        $ownerIds = M('payment_import')->distinct(true)->where($findCond)->getField('ownerid', true);
        $statuserIds = M('payment_import')->distinct(true)->where($findCond)->getField('statuserid', true);
        $userIds = array_unique(array_merge($ownerIds, $statuserIds));
        $condUser = array();
        if ($userIds) {
            $condUser['id'] = array('in', implode(',', $userIds));
            $userNames = M('users')->where($condUser)->getField('id,user_login', true);
        }
        $claimsList = M('payment_import')->page("$cur_page,$this->page")->where($findCond)->select();
        $total = M('payment_import')->where($findCond)->count();
        $page = $this->page($total, $this->page);
        $this->assign("page", $page->show('Admin'));
        $this->assign('pmtype', $getData['paymenttype']);
        $this->assign('pmtypestr', $this->paymenttype[$getData['paymenttype']]);
        $this->assign('cur_data', date('Y-m-d'));
        $this->assign('getData', $getData);
        $this->assign('userNames', $userNames);
        $this->assign('claimsList', $claimsList);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', date('Y-m-d',$end_time));
        $this->display('payment');
    }
    /**
     * 提交理赔导入单据
     */
    public function submitPayment(){
        if(IS_AJAX){
            $return = array('status' => 0, 'message' => '更新数据失败！');
            $claimsIds = is_array($_POST['claimsIds']) ? $_POST['claimsIds'] : array($_POST['claimsIds']);
            $msg = '提交导入';
            $updData = array('status'=>2, 'statuserid' => $_SESSION['ADMIN_ID'], 'statusdate' => date('Y-m-d H:i:s', time()));
            $updCond=[];
            $updCond['id']  = array('in',implode(',',$claimsIds));
            $updRes=M('payment_import')->where($updCond)->save($updData);
            if($updRes===FALSE){
                echo json_encode($return);
                exit();
            }
            foreach ($claimsIds as  $id){
                $itemInfo=M('payment_import')->where(array('id'=>$id))->field('billdate,paymenttype')->find();
                switch ($itemInfo['paymenttype']){
                    case 1:
                        $this->updateOrderClaims($id);
                        break;
                    case 2:
                        $this->updateOrderAmount($id, $itemInfo['billdate']);
                        break;
                    case 3:
                        $this->updateOrderFreight($id);
                        break;
                    default :;
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => 1, 'message' => 'suc!');
            echo json_encode($return);
            exit();
        }
    }

    /**
     * 删除导入单据
     */
    public function deletePayment(){
        if(IS_AJAX){
            $return = array('status' => 0, 'message' => '删除数据失败！');
            $pmtypestr=$this->paymenttype[$_POST['pmtype']];
            $paymentIds = is_array($_POST['paymentIds']) ? $_POST['paymentIds'] : array($_POST['paymentIds']);
            $msg = "删除{$pmtypestr}导入";
            foreach ($paymentIds as $id) {
                $delres=M('payment_import')->where(array('id'=>$id))->delete();
                $delitemres=M('payment_import_item')->where(array('pi_id'=>$id))->delete();
            }
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, $msg);
            $return = array('status' => 1, 'message' => 'suc!');
            echo json_encode($return);
            exit();
        }
    }

    public function payment_import(){
        if(IS_AJAX){
            $postData=I('post.');
            $pmtypestr=$this->paymenttype[$postData['pmtype']];
            $msg="新增{$pmtypestr}导入";
            $return = array('status' => 0, 'message' => '删除数据失败！');
            $claimsdata=$postData['claimsdata'];
            //验证快递单号和是否填写金额
            $checkRes=$this->checkPayment($claimsdata, $postData['pmtype']);
            if($checkRes['status']==0){
                $return['message']=$checkRes['message'];
                echo json_encode($return);
                exit();
            }
            $path = write_file('settlement', 'payment_import', $postData);
            //新增claims主表格数据
            $clsData=array('billdate'=>$postData['billdate'],'ownerid'=>$_SESSION['ADMIN_ID'],'creationdate'=>date('Y-m-d H:i:s',time()),'description'=>$postData['description'],'paymenttype'=>$postData['pmtype']);
            $clsData['docno']=$this->createDocno('payment_import', 'pm');
            if(!$clsData['docno']){
                $return['message']='生成单据编码失败！';
                echo json_encode($return);
                exit();
            }
            $clsId=M('payment_import')->add($clsData);
            if(!$clsId){
                $return['message']='payment_import主表添加数据失败！';
                echo json_encode($return);
                exit();
            }
            foreach ($claimsdata as $claim){
                $item=[];
                $item['pi_id']=$clsId;
                $item['amount']=$claim['amount'];
                $item['remark']=$claim['remark'];
                $item['track_number']=$claim['track_number'];
                $id_order=M('OrderShipping')->where(array('track_number'=>$claim['track_number']))->getField('id_order');
                $orderInfo=M('Order')->where(array('id_order'=>$id_order))->field('id_order,id_increment')->find();
                $item=  array_merge($item,$orderInfo);
                $additemRes=M('payment_import_item')->add($item);
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 2, $msg,$path);
            $return = array('status' => 1, 'message' => 'suc!','csid'=>$clsId);
            echo json_encode($return);
            exit();
        }  else{
            $getData = I('get.', "htmlspecialchars");
            if(!$getData['paymenttype']){
                $this->error('缺少必要参数！');
            }
        }
        $this->assign('pmtype', $getData['paymenttype']);
        $this->assign('pmtypestr', $this->paymenttype[$getData['paymenttype']]);
        $this->assign('cur_data', date('Y-m-d'));
        $this->display();
    }

    /**
     * 导入信息明细
     */
    public function  payment_detail(){
        $getData = I('get.', "htmlspecialchars");
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }
        $cur_page = $getData['p']? : 1; //默认页数
        $cond=[];
        if($getData['track_number']){
            $getData['track_number']=  trim($getData['track_number']);
            $cond['track_number']= array('like', "%{$getData['track_number']}%");
        }
        $csId=$getData['csid'];
        $claimsMaster=M('payment_import')->where(array('id'=>$csId))->field('docno,billdate,status,description,paymenttype')->find();
        $cond['pi_id']=$csId;
        $detailList=M('payment_import_item')->page("$cur_page,$this->page")->where($cond)->select();
        $total = M('payment_import_item')->where($cond)->count();
        $page = $this->page($total, $this->page);
        $this->assign('pmtype', $claimsMaster['paymenttype']);
        $this->assign('pmtypestr', $this->paymenttype[$claimsMaster['paymenttype']]);
        $this->assign("page", $page->show('Admin'));
        $this->assign("getData", $getData);
        $this->assign('claimsMaster', $claimsMaster);
        $this->assign('detailList', $detailList);
        $this->assign('csid', $csId);
        $this->display();
    }

    public function  deleteItem(){
        if (IS_AJAX) {
            $return = array('status' => 0, 'message' => 'suc!');
            $postData=I('post.');
            $itemids = is_array($postData['itemids']) ? $postData['itemids'] : array($postData['itemids']);
            foreach ($itemids as $itemid) {
                $delitemres=M('payment_import_item')->where(array('id'=>$itemid))->delete();
                if($delitemres===FALSE){
                    $return['message']='删除失败！';
                    echo json_encode($return);
                    exit();
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => 1, 'message' =>'成功删除');
            echo json_encode($return);
            exit();
        }
    }

    /**
     * 明细中 导入快递单和信息
     */
    public function updatePayment(){
        if(IS_AJAX){
            $postData=I('post.');
            $pmtypestr=$this->paymenttype[$postData['pmtype']];
            $msg="{$pmtypestr}导入";
            $return = array('status' => 0, 'message' => '删除数据失败！');
            $claimsdata=$postData['claimsdata'];
            $clsId=$postData['csid'];
            //验证快递单号和是否填写理赔金额
            $checkRes=$this->checkPayment($claimsdata, $postData['pmtype']);
            if($checkRes['status']==0){
                $return['message']=$checkRes['message'];
                echo json_encode($return);
                exit();
            }
            if($clsId){
                $clscnt=M('payment_import')->where(array('id'=>$clsId))->count();
                if($clscnt==0){
                    $return['message']="{$pmtypestr}数据有误！";
                    echo json_encode($return);
                    exit();
                }
                foreach ($claimsdata as $claim){
                    $item=[];
                    $item['pi_id']=$clsId;
                    $item['amount']=$claim['amount'];
                    $item['remark']=$claim['remark'];
                    $item['track_number']=$claim['track_number'];
                    $exitCond=array('pi_id'=>$clsId,'track_number'=>$claim['track_number']);
                    $isExitId=M('payment_import_item')->where($exitCond)->getField('id');
                    if($isExitId){
                        M('payment_import_item')->where(array('id'=>$isExitId))->save($item);
                    }else{
                        $id_order=M('OrderShipping')->where(array('track_number'=>$claim['track_number']))->getField('id_order');
                        $orderInfo=M('Order')->where(array('id_order'=>$id_order))->field('id_order,id_increment')->find();
                        $item=  array_merge($item,$orderInfo);
                        $additemRes=M('payment_import_item')->add($item);
                    }
                }
                add_system_record($_SESSION['ADMIN_ID'], 1, 3, $msg);
                $return = array('status' => 1, 'message' => 'suc!','csid'=>$clsId);
            }else{
                $return['message'] = "{$pmtypestr}数据有误";
            }
            echo json_encode($return);
            exit();
        }
    }


    /**
     * 通过id修改  金额
     */
    public function updateById(){
        if(IS_AJAX){
            $return = array('status' => 0, 'message' => 'fail');
            $postData = I('post.');
            if(empty($postData['detaildata'])){
                $return['message']='数据为空！';
                echo json_encode($return); exit();
            }
            $pmtype=$postData['pmtype'];
            $detaildata=$postData['detaildata'];
            $groupdata=[];
            foreach ($detaildata as $val){
                list($id,$field)=  explode('%', $val['name']);
                $groupdata[$id][$field]=$val['value'];
            }
            $checkRes=$this->checkPayment($groupdata, $pmtype);
            if($checkRes['status']==0){
                $return['message']=$checkRes['message'];
                echo json_encode($return); exit();
            }
            foreach($groupdata as $key=>$detail){
                $updres = M('payment_import_item')->where(array('id'=>$key))->save($detail);
                if($updRes===FALSE){
                    $return['message']='更新数据失败！';
                    echo json_encode($return); exit();
                }
            }
        $return = array('status' => 1, 'message' => '修改数据成功!');
        echo json_encode($return); exit();
        }
    }


    /**
     * 提交运费信息  修改订单的运费信息
     * @param type $claimId
     * @return boolean
     */
    public function updateOrderFreight($claimId){
        $itemlList=M('payment_import_item')->where(array('pi_id'=>$claimId))->field('id_order,id_increment,track_number,amount,remark')->select();
        $user_id=$_SESSION['ADMIN_ID'];
        foreach ($itemlList as $item){
            D('Order/OrderShipping')->where('id_order='.$item['id_order'])->save(array('freight'=>$item['amount']));
        }
        add_system_record($_SESSION['ADMIN_ID'], 5, 3, '结款管理导入运费');
        return TRUE;
    }

    /**
     * 检查快递单状态及金额限制
     * @param type $pmData
     * @param type $pmtype
     * @return string
     */
    protected function  checkPayment($pmData,$pmtype){
        $return=array('status'=>0,'message'=>'数据缺失！');
        if(empty($pmData)||empty($pmtype)){
            return $return;
        }
        $pmtypestr=$this->paymenttype[$pmtype];
        foreach($pmData as  $key=> $claim){
            $findOrderId=M('OrderShipping')->where(array('track_number'=>$claim['track_number']))->getField('id_order');
            if(!$findOrderId){
                $return['message']=$claim['track_number'].'快递单号，无法查询到记录！';
                return $return;
            }
            if($claim['amount']===''||is_null($claim['amount'])){
                $return['message']=$claim['track_number']."快递单号，请填写{$pmtypestr}金额！";
                return $return;
            }
            if($pmtype==2){//导入结算款  验证是是否已经结算 和 TF订单
                //导入结算   判断金额
                $order_payment = D('Order/Order')->where(array('id_order'=>$findOrderId))->Field('payment_method,price_total')->find();
                if($order_payment['payment_method']!== '0'){
                    $return['message']=$claim['track_number'].'快递单号, TF订单不能进行结款！';
                    return $return;
                }
                $orderstatus = D("Order/OrderSettlement")->where(array('id_order'=>$findOrderId))->Field('status,amount_settlement')->find();
                if($orderstatus['status']==2){
                    $return['message']=$claim['track_number'].'快递单号,已经结算！';
                    return $return;
                }
                $orderstatus['amount_settlement']=$orderstatus['amount_settlement']?$orderstatus['amount_settlement']:0;
                if($claim['amount']+$orderstatus['amount_settlement']>$order_payment['price_total']){
                    $return['message']=$claim['track_number'].'导入的结算金额累计已经超出快递单总计！';
                    return $return;
                }
            }
        }
        return array('status'=>1,'message'=>'suc！');
    }

    /**
     * 提交结算信息  修改订单的结算信息
     * @param type $claimId
     * @return boolean
     */
    protected function  updateOrderAmount($claimId,$billdate){
        $ordObj = D("Order/Order");
        $ordShipObj = D("Order/OrderShipping");
        $ordSetObj = D("Order/OrderSettlement");
        $itemlList=M('payment_import_item')->where(array('pi_id'=>$claimId))->field('id_order,id_increment,track_number,amount,remark')->select();
        $user_id=$_SESSION['ADMIN_ID'];
        foreach ($itemlList as $item){
            $orderId=$item['id_order'];
            $settle = $ordSetObj->where(array('id_order'=>$orderId))->find();
            $shipping_info = $ordShipObj->where(array('track_number' => $item['track_number'] ))->find();
            if($settle){
                $amount = $settle['amount_settlement'] + $item['amount'];
                //TODO: 结款状态: 0未结款, 1部分结款, 2已结款
                $status = $amount >= $settle['amount_total'] ? 2 : 1;
                $data = array(
                    'id_users' => $user_id,
                    'amount_settlement' => $amount,
                    'id_order_shipping' => $shipping_info['id_order_shipping'],
                    'date_settlement' => $billdate,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                );
                $ordSetObj->where('id_order_settlement=' . $settle['id_order_settlement'])->save($data);
                $remark  = '更新结算:'.$amount;
            }else{
                $amount = $item['amount'];
                $order = D('Order/Order')->where(array('id_order' => $orderId))->find();
                // 结款状态: 0未结款, 1部分结款, 2已结款
                $status = $amount >= $order['price_total'] ? 2 : 1;
                $data = array(
                    'id_order' => $order['id_order'],
                    'id_users' => $user_id,
                    'amount_total' => $order['price_total'],
                    'amount_settlement' => $amount,
                    'status' => $status,
                    'date_settlement' => $billdate,
                    'id_order_shipping' => $shipping_info['id_order_shipping'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                );
                $ordSetObj->add($data);
                $remark  = '添加结算:'.$amount;
            }
            if($status == 2){
                $shipping_data['is_settlemented'] = 1;
                $ordShipObj->where('id_order='.$orderId)->save($shipping_data);
            }
            $ordObj->where('id_order='.$item['id_order'])->save(array('id_order_status'=>'9'));
            D("Order/OrderRecord")->addHistory($orderId,9,4,$remark);
        }
        add_system_record($user_id, 5, 2, '导入结算：更新订单结款');
        return TRUE;
    }

    /**
     * 提交理赔信息  修改订单的理赔信息
     * @param type $claimId
     * @return boolean
     */
    protected function updateOrderClaims($claimId){
        $ordObj = D("Order/Order");
        $ordShipObj = D("Order/OrderShipping");
        $ordSetObj = D("Order/OrderSettlement");
        $itemlList=M('payment_import_item')->where(array('pi_id'=>$claimId))->field('id_order,id_increment,track_number,amount,remark')->select();
        foreach ($itemlList as $item){
            $getOrderShip = $ordShipObj->where("track_number='".$item['track_number']."'")->find();
            $findSet = $ordSetObj->field('amount_total,amount_settlement,status')->where('id_order = '.$item['id_order'],'status'.'!=2')->find();
            if($findSet){
                    $update_data = array(
                        'amount_settlement'=>$item['amount'] ,'status'=>2,'remark'=>$item['remark']
                    );
                $update_data['date_settlement'] = date('Y-m-d H:i:s');
                $ordSetObj->where('id_order='.$item['id_order'])->save($update_data);
                $ordObj->where('id_order='.$item['id_order'])->save(array('id_order_status'=>'19'));
                D("Order/OrderRecord")->addHistory($item['id_order'], 19, 4,'理赔导入：更新结算为：'.$item['amount'].'理赔金额为：'.$item['amount']);
            }else{
                $order = $ordObj->where(array('id_order'=>$item['id_order']))->find();
                $add_data['id_users'] = $_SESSION['ADMIN_ID'];
                $add_data['id_order_shipping'] = $getOrderShip['id_order_shipping'];
                $add_data['id_order'] = $item['id_order'];
                $add_data['amount_total'] = $order['price_total'];
                $add_data['amount_settlement'] = $item['amount'];
                $add_data['date_settlement'] = date('Y-m-d H:i:s');
                $add_data['created_at'] = date('Y-m-d H:i:s');
                $add_data['remark'] = $item['remark'];
                $add_data['status'] = 2;
                $ordSetObj->add($add_data);
                $ordObj->where('id_order='.$item['id_order'])->save(array('id_order_status'=>'19'));
                D("Order/OrderRecord")->addHistory($getOrderShip['id_order'],19, 4,'理赔导入：更新结算为：'.$item['amount'].'理赔金额为：'.$item['amount']);
            }
        }

        return TRUE;
    }



        /**
     * 产生新的单据编码
     */
    protected function createDocno($tableName,$prefix) {
        if(empty($tableName)||empty($prefix)){
            return FALSE;
        }
        $prefix = $prefix. date('ymd', time());
        $cond['billdate'] = array('like', '%' . date('Y-m-d', time()) . '%');
        $lastDocno = M($tableName)->where($cond)->order('id desc')->field('docno')->find();
        $lastNum = 0;
        if ($lastDocno['docno']) {
            $lastNum = substr($lastDocno['docno'], strlen($prefix));
        }
        $cur_num = $lastNum + 1;
        return $prefix . str_pad($cur_num, 7, '0', STR_PAD_LEFT);
    }

    /**
     * 结算管理-》
     * 导入手续费    liuruibin   20171025
     */
    public function shipping_formalities(){
        $infor = array(
            'error'   => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if(IS_POST){
            $ordShipFormObj = M('OrderShippingFormalities');
            $ordShipObj = D("Order/OrderShipping");
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('settlement','shipping_formalities',$data);
            $count = 1;
            $data = $this->getDataRow($data);
            //循环表格的数据
            foreach($data as $row){
                $row = trim($row);
                if(empty($row))
                    continue;
                ++$total;
                $row = explode("\t",trim($row),8);
                if(count($row) != 8 || !$row[0]){
                    $infor['error'][] = sprintf('第%s行：格式不正确',$count++);
                    continue;
                }
                $track_number = trim($row[0],'\'"');
                $surcharge = (float)trim($row[1],'\'"');
                $back_fee = (float)trim($row[2],'\'"');
                $collection_fee = (float)trim($row[3],'\'"');
                $forward_fee = (float)trim($row[4],'\'"');
                $slotting_fee = (float)trim($row[5],'\'"');
                $operation_fee = (float)trim($row[6],'\'"');
                $other_fee = (float)trim($row[7],'\'"');
                $date_settlement = $_POST['settle_date'].' '.date('H:i:s');
                $getOrderShip = $ordShipObj->where("track_number='".$track_number."'")->find();
                if($getOrderShip['id_order']){
                    $getOrdShipForm = $ordShipFormObj->where("track_number = '".$track_number."'")->find();
                    //存在该运单的费用，直接更新，以第一次导入为标准，后面导入的不能覆盖，在页面提示
                    if($getOrdShipForm){
                        $errorInfo = null;
                        if($getOrdShipForm['surcharge']>0){
                            $surcharge = $getOrdShipForm['surcharge'];
                            $errorInfo .= "[附加费]已存在 - ";
                        }
                        if($getOrdShipForm['back_fee']>0){
                            $back_fee = $getOrdShipForm['back_fee'];
                            $errorInfo .= "[返款手续费]已存在 - ";
                        }
                        if($getOrdShipForm['collection_fee']>0){
                            $collection_fee = $getOrdShipForm['collection_fee'];
                            $errorInfo .= "[代收手续费]已存在 - ";
                        }
                        if($getOrdShipForm['forward_fee']>0){
                            $forward_fee = $getOrdShipForm['forward_fee'];
                            $errorInfo .= "[转寄费]已存在 - ";
                        }
                        if($getOrdShipForm['slotting_fee']>0){
                            $slotting_fee = $getOrdShipForm['slotting_fee'];
                            $errorInfo .= "[上架费]已存在 - ";
                        }
                        if($getOrdShipForm['operation_fee']>0){
                            $operation_fee = $getOrdShipForm['operation_fee'];
                            $errorInfo .= "[作业费]已存在 - ";
                        }
                        if($getOrdShipForm['other_fee']>0){
                            $other_fee = $getOrdShipForm['other_fee'];
                            $errorInfo .= "[其他费用]已存在 - ";
                        }
                        if($errorInfo){
                            $infor['error'][] = sprintf('第%s行:运单号-[%s]出现重复：%s', $count,$track_number,$errorInfo);
                        }
                        $total_fee = $surcharge + $back_fee + $collection_fee + $forward_fee + $slotting_fee + $operation_fee + $other_fee;
                        $update_data = array(
                            'surcharge'  => $surcharge,
                            'back_fee'  => $back_fee,
                            'collection_fee' => $collection_fee,
                            'forward_fee' => $forward_fee,
                            'slotting_fee' => $slotting_fee,
                            'operation_fee' => $operation_fee,
                            'other_fee' => $other_fee,
                            'total_fee' => $total_fee,
                            'date_settlement' => $date_settlement
                        );
                        $ordShip_update_data = array('formalities_fee' => $total_fee+$getOrderShip['formalities_fee']);
                        $ordShipFormObj->where("track_number ='".$track_number."'")->save($update_data);
                        $ordShipObj->where("id_order =".$getOrderShip['id_order'])->save($ordShip_update_data);//更新订单运单表的总手续费
                        $infor['success'][] = sprintf('第%s行: 运单号:%s  手续费用 更新成功', $count, $track_number);
                    }else{
                        //统计导入的总价格
                        $total_fee = $surcharge + $back_fee + $collection_fee + $forward_fee + $slotting_fee + $operation_fee + $other_fee;
                        $add_data = array(
                            'track_number'  => $track_number,
                            'id_order'=>$getOrderShip['id_order'],
                            'surcharge'  => $surcharge,
                            'back_fee'  => $back_fee,
                            'collection_fee' => $collection_fee,
                            'refund_fee' => 0,
                            'forward_fee' => $forward_fee,
                            'slotting_fee' => $slotting_fee,
                            'operation_fee' => $operation_fee,
                            'other_fee' => $other_fee,
                            'total_fee' => $total_fee,
                            'date_settlement' => $date_settlement
                        );
                        $ordShip_update_data = array('formalities_fee' => $total_fee);
                        $ordShipFormObj->add($add_data);
                        $ordShipObj->where("id_order =".$getOrderShip['id_order'])->save($ordShip_update_data);
                        $infor['success'][] = sprintf('第%s行: 运单号:%s  手续费用 导入成功', $count, $track_number);
//                    D("Order/OrderRecord")->addHistory($getOrderShip['id_order'])
                    }
                }else{
                    $infor['error'][] = sprintf('第%s行:找不到运单号[%s]', $count,$track_number);
                }
                $count++;
            }
            add_system_record($_SESSION['ADMIN_ID'],5,2,'导入手续费',$path);
        }
        $this->assign('infor',$infor);
        $this->assign('data',I('post.data'));
        $this->display();
    }

    //充值运费,结算款,手续费 只开放给研发部分人员 jiangqinqing 20171115
    function unsetSettlement(){
        $type = empty($_REQUEST['type'])?1:intval($_REQUEST['type']);

        if (IS_POST) {
            $data = I('post.data');
            $data = $this->getDataRow($data);
            $count = 1;

            if($_POST['type'] == 1){
                //重置结算款
                foreach($data as $row){
                    $shipping_info = M('OrderShipping')->where(array('track_number' => $row))->find();

                    if (!$shipping_info) {
                        $infor['error'][] = sprintf('第%s行: 运单号:%s 不存在.', $count++, $row);
                        continue;
                    }else{
                        $amount_data = array(
                            'amount_settlement' => 0,
                            'date_settlement' => '',
                            'status' => 0,
                            'updated_at' => ''
                        );
                        D("Order/OrderSettlement")->where(' id_order =' . $shipping_info['id_order'])->save($amount_data);
                        $infor['success'][] = sprintf('第%s行: 运单号:%s 订单号:%s 业务组:%s 运费:%s', $count++, $row);
                    }
                }
            }else if($_POST['type'] == 2){
                //重置运费
                foreach($data as $row){
                    $shipping_data = array(
                        'freight'=>0
                    );
                    $shipping_info = M('OrderShipping')->where(array('track_number' => $row))->find();
                    if (!$shipping_info) {
                        $infor['error'][] = sprintf('第%s行: 运单号:%s 不存在.', $count++, $row);
                        continue;
                    }else{
                        D('Order/OrderShipping')->where('id_order='.$shipping_info['id_order'])->save($shipping_data);
                        $infor['success'][] = sprintf('第%s行: 运单号:%s 订单号:%s 业务组:%s 运费:%s', $count++, $track_number,$select['id_increment'],$select['title'], $freight_price);
                    }
                }
            }else if($_POST['type'] == 3){
                //重置手续费
                foreach($data as $row){
                    $shipping_info = M()->table("erp_order_shipping_formalities")->where(array('track_number' => $row))->delete();

                    $infor['success'][] = sprintf('第%s行: 运单号:'.$row.' 手续费重置成功', $count++, $row);
                }
            }

        }

        $this->assign('infor',$infor);
        $this->assign('type',$type);
        $this->display();
    }
}
