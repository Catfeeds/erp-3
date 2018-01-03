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
use Order\Lib\OrderStatus;
use Order\Model\UpdateStatusModel;

class ImportController extends AdminbaseController {

    protected $Warehouse, $orderModel;

    public function _initialize() {
        parent::_initialize();
        $this->Warehouse = D("Common/Warehouse");
        $this->orderModel = D("Order/Order");
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
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
        if(isset($_GET['sku_title'])&& $_GET['sku_title']){
            $key_where['ps.sku'] = array('LIKE', '%' . $_GET['sku_title'] . '%');
            $key_where['ps.barcode'] = array('LIKE', '%' . $_GET['sku_title'] . '%');
            $key_where['_logic'] = 'or';
            $where['_complex'] = $key_where;
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
            '仓库','部门', '产品名称', '内部名称', 'SKU','条码', '属性', '库存数量'
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
                $ware_pro = M('WarehouseProduct')->field('quantity,road_num')->where(array('id_product'=>$val['id_product'],'id_product_sku'=>$val['id_product_sku'],'id_warehouse'=>$id))->find();
                $data = array(
                    $warehouse,$department,$val['product_title'], $val['inner_name'], $val['sku'], $val['barcode'], $val['title'], $ware_pro['quantity']+$ware_pro['road_num']
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

    /*
     * 更新sku到仓库
     */

    public function update_warehouse_sku() {
        try {
            $all_sku = M("ProductSku")->where(array('status' => 1))->select(); //现在都在使用的sku
            foreach ($all_sku as $k => $sku) {
                //查找仓库相同的有的sku，有就跳过，没有就添加
                $warehouse_sku = M('WarehouseProduct')->where(array('id_product' => $sku['id_product'], 'id_product_sku' => $sku['id_product_sku'], 'id_warehouse' => 1))->find();
                if (!$warehouse_sku){
                    $data_list = array(
                        'id_warehouse' => 1,
                        'id_product' => $sku['id_product'],
                        'id_product_sku' => $sku['id_product_sku'],
                        'quantity' => 0,
                        'road_num' => 0
                    );
                    D('Common/WarehouseProduct')->add($data_list);
                }
            }
            $status = 1;
            $message = '成功';
        } catch (\Exception $e) {
            $status = 0;
            $message = $e->getMessage();
        }
        
        $return = array('status' => $status, 'message' => $message);
        echo json_encode($return);
        exit();
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

            //导入记录到文件
            $path = write_file('warehouse', 'update_track', $data);

            $data = $this->getDataRow($data);

            $count = 1;
           
            //验证本次导入是否有重复订单
            $isexport=1;
            $orderdata=[];
            foreach($data as $row){
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);  
                $orderdata[]=$row[0];
            }
            $unique_arr = array_unique ( $orderdata );
            // 获取重复数据的数组
            $repeat_arr = array_diff_assoc ( $orderdata, $unique_arr );
            if($repeat_arr){
                $isexport=0;
                $repeat_str= implode(',', $repeat_arr);
                $infor['error'][] = sprintf('本次导入有重复订单:%s ,清理后请重新导入', $repeat_str);
            }             
                
            if($isexport==1){    
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
                $order_id = trim($row[0]);
                //$name = trim($row[2]);
                $track_number = str_replace("'", '', $row[1]);
                $track_number = str_replace(array('"',' ',' ','　'),'', $track_number);
                $track_number = trim($track_number);
//                $weight = trim($row[2]);//重量
                //查找全局是否有重复运单号
                $finded = D('Order/OrderShipping')
                        ->field('id_order, track_number')
                        ->where(array(
                            'track_number' => $track_number
                        ))
                        ->find();
                if ($finded) {
                    $infor['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 运单号已经存在.', $count++, $order_id, $track_number);
                    continue;
                }
                //TODO: 可以从OrderShpping复制一条记录
                $order = D('Order/Order')
                        ->field('id_order, id_increment, first_name, tel, id_shipping, date_delivery, id_order_status')
                        ->where(array('id_increment' => $order_id))
                        ->find();
                if (!$order) {
                    $infor['error'][] = sprintf('第%s行: 订单号:%s 不存在.', $count++, $order_id);
                    continue;
                }
                if (in_array($order['id_order_status'], array(11, 12, 13, 14, 15))) {
                    $infor['error'][] = sprintf('第%s行: 订单号:%s 订单已经取消.', $count++, $order_id);
                    continue;
                }
                $shipping_name = D('Common/Shipping')
                        ->where(array(
                            'id_shipping' => $_POST['shipping_id']
                        ))
                        ->find();
                //运费
//                if($weight <= $shipping_name['first_weight']) {
//                    $freight = $shipping_name['first_weight_price'];
//                } else if($weight > $shipping_name['first_weight'] && ($weight - $shipping_name['first_weight']) < $shipping_name['continued_weight']) {
//                    $freight = $shipping_name['first_weight_price']+$shipping_name['continued_weight_price'];
//                } else if($weight > $shipping_name['first_weight'] && ($weight - $shipping_name['first_weight']) > $shipping_name['continued_weight']) {
//                    $freight = $shipping_name['first_weight_price']+($shipping_name['continued_weight_price']*($weight - $shipping_name['first_weight']));
//                }
                //TODO: 没有运单号时添加一条记录, 但是在分配物流时已经加了一条记录.冗余代码
                //TODO: 如果一个订单有多个运单号时, 必须在这里添加一条新记录
                $shipping_info = D('Order/OrderShipping')
                        ->field('id_order_shipping, track_number, id_order')
                        ->where(array(
                            'id_order' => $order['id_order']
                        ))
                        ->select();
                
                $shipping_track = M('ShippingTrack')->where(array('id_shipping'=>$_POST['shipping_id'],'track_number'=>$track_number))->find();
                //1.如果运单号库表存在，并且未使用，则更新为已使用
                //2.如果运单号库表不存在，则新增该运单号到库里，并标记为已使用
                if(!$shipping_track) {
                    $track_array = array(
                        'id_shipping'=>$_POST['shipping_id'],
                        'track_number'=>$track_number,
                        'track_status'=>1
                    );
                    $track_number_id = D('Common/ShippingTrack')->add($track_array);
                } else {
                    if($shipping_track['track_status'] == 0) {
                        D('Common/ShippingTrack')->where(array('id_shipping'=>$_POST['shipping_id'],'track_number'=>$track_number))->save(array('track_status'=>1));
                    }
                    $track_number_id = $shipping_track['id_shipping_track'];
                } 

                //TODO: 修改OrderShipping的逻辑, 只有在更新运单号时直接写入运单号信息即可,不用在分配物流时写入
                $updated = false;
                foreach ($shipping_info as $ship) {
//                    if (empty($ship['track_number']) or $ship['track_number']=='') {
                        //更新一个后退出
                        D('Order/OrderShipping')->where(array('id_order_shipping' => $ship['id_order_shipping']))
                                ->save(array(
                                    'track_number' => $track_number,
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'id_shipping' => $_POST['shipping_id'],
                        ));
                        M('OrderWave')->where(array('id_order' => $ship['id_order']))
                                ->save(array(
                                    'track_number_id' => $track_number_id,
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'id_shipping' => $_POST['shipping_id'],
                                ));
                        $updated = true;
                        //TODO: 导入运单号后更新订单状态为已配货(20)
                        //当订单状态为匹配转寄中和已匹配转寄状态时，不改变状态，只添加物流信息
                        if($order['id_order_status'] == OrderStatus::MATCH_FORWARDED) {
                            D('Order/Order')->where(array('id_order' => $order['id_order']))->save(array(
                                'id_shipping' => $_POST['shipping_id'],
                                'id_order_status' => OrderStatus::MATCH_FINISH
                            ));
                            D("Order/OrderRecord")->addHistory($order['id_order'], OrderStatus::MATCH_FINISH, 4, '更新运单号,转寄完成' . $track_number);
                        } else {
                            D('Order/Order')->where(array('id_order' => $order['id_order']))->save(array(
                                'id_shipping' => $_POST['shipping_id'],
                            ));
                            D("Order/OrderRecord")->addHistory($order['id_order'], $order['id_order_status'], 4, '更新运单号 ' . $track_number);
                        }
                        break;
//                    }
                }
                if (!$updated) {

                    //新的运单号
                    D('Order/OrderShipping')
                            ->add(array(
                                'id_order' => $order['id_order'],
                                'id_shipping' => $_POST['shipping_id'],
                                'shipping_name' => $shipping_name['title'], //TODO: 加入物流名称
                                'track_number' => $track_number,
                                'fetch_count' => 0,
                                'is_email' => 0,
                                'status_label' => '',
                                'date_delivery' => $order['date_delivery'],
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s'),
//                            'freight' => $freight
                    ));
                    $updated = true;
                    //TODO: 导入运单号后更新订单状态为已配货(3)
                    //当订单状态为匹配转寄中和已匹配转寄状态时，不改变状态，只添加物流信息
                    if($order['id_order_status'] == OrderStatus::MATCH_FORWARDED) {
                        D('Order/Order')->save(array(
                            'id_order' => $order['id_order'],
                            'id_shipping' => $_POST['shipping_id'],
                            'id_order_status' => OrderStatus::MATCH_FINISH
                        ));
                        D("Order/OrderRecord")
                            ->addHistory($order['id_order'], OrderStatus::MATCH_FINISH, 4, '更新运单号,转寄完成' . $track_number);
                    } else {
                        D('Order/Order')->save(array(
                            'id_order' => $order['id_order'],
                            'id_shipping' => $_POST['shipping_id'],
                        ));
                        D("Order/OrderRecord")
                            ->addHistory($order['id_order'], $order['id_order_status'], 4, '更新运单号 ' . $track_number);
                    }
                }
                $infor['success'][] = sprintf('第%s行: 订单号:%s 更新运单号: %s ', $count++, $order_id, $track_number);
            }
            
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新运单号', $path);
            }
        }
        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
        $this->assign('post', $_POST);
        $this->assign('shipping', $shipping);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 更新物流
     */
    public function update_shipping() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
        $shipping_selected = array();
        foreach ($shipping as $item) {
            if ((int) $_POST['shipping_id'] === (int) $item['id_shipping']) {
                $shipping_selected = $item;
            }
        }

        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordObj = D("Order/Order");
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_shipping', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $order_id = trim($row[0]);

                if ($order_id) {
                    $orderObj = $ordObj->where(array(
                                'id_increment' => $order_id
                            ))->find();
                    if ($orderObj) {
                        $ordObj->where('id_order=' . $orderObj['id_order'])->save(array('id_shipping' => $_POST['shipping_id']));
                        D('Order/OrderShipping')->where('id_order=' . $orderObj['id_order'])->save(array('id_shipping' => $_POST['shipping_id'], 'shipping_name' => $shipping_selected['title']));
                        D("Order/OrderRecord")->addHistory($order_id, $orderObj['id_order_status'], 4, '更新物流' . $row[0]);
                        $info['success'][] = sprintf('第%s行: 订单号:%s 物流名称: %s', $count++, $order_id, $shipping_selected['title']);
                    } else {
                        $info['error'][] = sprintf('第%s行: 没有找到订单', $count++);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 没有找到订单', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新物流', $path);
        }

        $this->assign('post', $_POST);
        $this->assign('shipping', $shipping);
        $this->assign('infor', $info);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 更新配送中  更新状态,一行一个, tab分割列
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
        //所属仓库只能看到所属仓库的订单
        $belong_ware_id = $_SESSION['belong_ware_id'];
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $warehouse = M('Warehouse')->getField('id_warehouse','title',true);
        $total = 0;
        $ordShip = D('Order/OrderShipping');
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_status', $data);
            $data = $this->getDataRow($data);
            //导入记录到文件
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $setPath = './' . C("UPLOADPATH") . 'warehouse' . "/";
            if (!is_dir($setPath)) {
                mkdir($setPath, 0777, TRUE);
            }
            $logTxt = $_POST['settle_date'] . PHP_EOL . $data;
            $getPathFile = $setPath . $user_id . '_' . date('Y_m_d_H_i_s') . '.txt';
            file_put_contents($getPathFile, $logTxt, FILE_APPEND);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 1);
                $selectShip = $ordShip->where(array('track_number' => trim($row[0])))->find();//获取运单信息
                $select_order = M('Order')->where(array('id_increment'=>trim($row[0])))->find();//获取订单信息  
                if (($selectShip && $selectShip['id_order'])||($select_order&&$select_order['id_order'])) {
                    $order_id = $selectShip['id_order']?$selectShip['id_order']:$select_order['id_order'];
                    $tipStr=$selectShip['id_order']?'运单号':'订单号';
                    $get_order = D("Order/Order")->field('id_order_status,id_warehouse')->where(array('id_order' => $order_id))->find();
                    if(in_array($get_order['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1)) {
                        if (!in_array($get_order['id_order_status'], array(OrderStatus::PACKAGED, OrderStatus::MATCH_FINISH))) {
                            $show_text = $statusLabel[$get_order['id_order_status']];
                            $infor['error'][] = sprintf('第%s行: '.$tipStr.':%s 订单状态已经是' . $show_text . '了,不能更新为配送中', $count++, $row[0]);
                        } else {
                            $today = date('Y-m-d H:i:s');
                            D("Order/Order")->where('id_order=' . $order_id)->save(array('id_order_status' => OrderStatus::DELIVERING, 'date_delivery' => $today));
                            //发货日期更新到出库订
                            D("Order/Orderout")->where('id_order=' . $order_id)->save(array('id_order_status' => OrderStatus::DELIVERING, 'date_delivery' => $today)); //配送中
                            $id_increment = D('Order/Order')->where('id_order=' . $order_id)->getField('id_increment');
                            $track_number = $ordShip->where('id_order=' . $order_id)->getField('track_number');
                            D("Order/OrderRecord")->addHistory($order_id, 8, 4, '批量导入配送中');
                            $infor['success'][] = sprintf('第%s行: %s:%s  更新状态: %s', $count++, $tipStr, trim($row[0]), '配送中');
                        }
                    } else {
                        $infor['error'][] = sprintf('第%s行: 更新状态失败，%s'.$tipStr.'属于%s仓库', $count++,$row[0],$warehouse[$get_order['id_warehouse']]);
                    }
                } else {
                    $infor['error'][] = sprintf('第%s行: 没有找到订单', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新配送中', $path);
        }
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    

    /**
     * 更新所选状态
     */
    public function track_number_update() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Order/OrderShipping');
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'track_number_update', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            $OUT_STOCK = \Order\Lib\OrderStatus::OUT_STOCK;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                if ($row[0]) {
                    $actionID = (int) $_POST['order_action'];
                    $status_name = D('Order/OrderStatus')->where(array('id_order_status' => $actionID))->getField('title');
                    $selectShip = $ordShip->where(array(
                                'track_number' => trim($row[0])
                            ))
                            ->find();
                    if ($selectShip && $actionID && $selectShip['id_order']) {
                        $order_id = $selectShip['id_order'];
                        $get_order = D('Order/Order')->where(array('id_order'=>$order_id))->find();
                        $today = date('Y-m-d H:i:s');
                        $updateData = array('id_order_status' => $actionID);
                        D("Order/Order")->where('id_order=' . $order_id)->save($updateData);
                        $update_qty_message = '';
                        //如果设置为缺货，库存回滚
                        if($actionID==$OUT_STOCK){
                            //$update_qty_message = UpdateStatusModel::inventory_rollback($order_id,$get_order);
                        }
                        $id_increment = $get_order['id_increment'];
                        D("Order/OrderRecord")->addHistory($order_id, $actionID, 4, $update_qty_message.' 根据运单号('. $row[0].')更新订单状态');
                        $info['success'][] = sprintf('第%s行: 订单号:%s 更新状态: %s', $count++, $id_increment, $status_name);
                    } else {
                        $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，没有找到订单', $count++, $row[0]);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新所选状态', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display('track_number_update');
    }

    /**
     * 更新缺货
     */
    public function update_out_stock() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Order/OrderShipping');
        if (IS_POST)
        {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_out_stock', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row)
            {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                if ($row[0])
                {
                    //订单状态可更新为缺货订单的订单状态
                    $less_stock_status = array(OrderStatus::UNPICKING, OrderStatus::PICKING, OrderStatus::PICKED ,OrderStatus::APPROVED,OrderStatus::MATCH_FORWARDING,OrderStatus::MATCH_FORWARDED); //未配货 配货中 已配货 已审核 匹配转寄中，已匹配转寄状态
                    $actionID = (int) $_POST['order_action']; //id_order_status = 6 => 缺货状态
                    $selectShip = $ordShip->where(array('track_number' => trim($row[0])))->find();//获取运单信息
                    $select_order = M('Order')->where(array('id_increment'=>trim($row[0])))->find();//获取订单信息
                    if (($selectShip && $actionID) ||( $select_order && $actionID))
                    {
                        $order_id = $selectShip['id_order']? $selectShip['id_order']:$select_order['id_order'];
                        $get_order = D("Order/Order")->where(array('id_order' => $order_id))->find(); //获取订单号信息

                        $msg = $select_order ? '订单号' : '运单号';
                        if ( !in_array($get_order['id_order_status'], $less_stock_status))
                        {
                            $info['error'][] = sprintf('第%s行: '.$msg.':%s  订单状态不是未配货，配货中和已配货状态，不能进行缺货操作', $count++, $row[0]);
                        }
                        else
                        {
                            D('Common/OrderWave')->where(array('id_order' => $order_id))->delete();
                            D('Order/OrderShipping')->where(array('id_order' => $order_id))->delete();
                            D('Order/Order')->where(array('id_order' => $order_id))->save(array('id_shipping' => 0));
                            D("Common/OrderWave")->where(array('id_order' => $order_id))->save(array('id_shipping' => null, 'track_number_id' => null));

                            if ($get_order)
                            {
                                //更新缺货的同时去查找货位库存记录是否有，有就删除，否则会变成扣了多个数量
                                $less = M('OrderWaveLessstock')->where(array('id_order'=>$order_id))->select();
                                if ($less)
                                {
                                    D('Common/OrderWaveLessstock')->where(array('id_order'=> $order_id))->delete();
                                }
                                //更新订单状态为缺货状态
                                $res_one = D("Order/Order")->where(array('id_order' => $order_id))->save(array('id_order_status' => OrderStatus::OUT_STOCK));
                                //订单状态更新为缺货成功后进行减在单处理
                                if ($res_one)
                                {
                                    if(in_array($get_order['id_order_status'], array(OrderStatus::UNPICKING, OrderStatus::PICKING, OrderStatus::PICKED ,OrderStatus::APPROVED))){

                                    UpdateStatusModel::qty_pre_out_rollback($order_id);
                                    D('Common/OrderWave')->where(array('id_order'=>$order_id))->delete();


                                    }else{
                                        $findOrder=M('OrderForward')->where(array('new_order_id'=>$get_order['id_order']))->getField('old_order_id');
                                        M('Forward')->where(array('id_order'=>$findOrder))->save(array('status'=>0));
                                        D("Common/OrderForward")->where(array('new_order_id' =>$get_order['id_order']))->delete();
                                        $order_item_data = D("Order/OrderItem")->where(array('id_order'=>$order_id))->select();
                                        $id_product_skus= array_column($order_item_data,'id_product_sku');
                                        D("Order/Order")->where(array('id_order' => $order_id))->save(array('id_shipping'=>0,'id_warehouse'=>0));
                                        UpdateStatusModel::get_short_order($id_product_skus);                                        
                                    }
                                  
                                    D("Order/OrderRecord")->addHistory($order_id, OrderStatus::OUT_STOCK, 4, $row[0].'订单状态更新为缺货成功');
                                    $info['success'][] = sprintf('第%s行: '.$msg.':%s 状态: %s', $count++, $row[0], '更新缺货成功！');
                                    M('OrderShipping')->where('id_order=' . $order_id)->delete(); 
                                    
                                    
                                }
                                else
                                {
                                    $info['error'][] = sprintf('第%s行: 运单号:%s 更新缺货状态失败！', $count++, $row[0]);
                                }
                            }
                            else
                            {
                                $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，没有找该订单或运单信息！', $count++, $row[0]);
                            }
                        }
                    }
                    else
                    {
                        $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，没有找到订单', $count++, $row[0]);
                    }
                }
                else
                {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新缺货状态', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 删除运单号
     */
    public function delete_track_number() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShipObj = D("Order/OrderShipping");
        $ordObj = D("Order/Order");
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'delete_track_number', $data);
            $data = $this->getDataRow($data);
            //导入记录到文件
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $setPath = './' . C("UPLOADPATH") . 'warehouse' . "/" . date('Ymd') . "/";
            if (!is_dir($setPath)) {
                mkdir($setPath, 0777, TRUE);
            }
            $logTxt = $_POST['settle_date'] . PHP_EOL . $data;
            $getPathFile = $setPath . $user_id . '_' . date('H_i_s') . 'update_rejected' . '.txt';
            file_put_contents($getPathFile, $logTxt, FILE_APPEND);


            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 1);
                $trackNumber = trim($row[0]);
                if ($trackNumber) {
                    $orderShipObj = $ordShipObj->where("track_number='" . $trackNumber . "'")->find();
                    if ($orderShipObj['id_order']) {//($orderShipObj['status_label']=='无信息' or $orderShipObj['status_label']=='') &&
                        $order = $ordObj->find($orderShipObj['id_order']);
                        if ($order) {
                            $ordShipObj->where('id_order_shipping=' . $orderShipObj['id_order_shipping'])->delete();
                            D("Order/OrderRecord")->addHistory($orderShipObj['id_order'], $order['id_order_status'], 3, '删除运单号：' . $trackNumber);
                            $info['success'][] = sprintf('第%s行: 运单号:%s 成功删除', $count++, $trackNumber);
                        }
                    } else {
                        $info['error'][] = sprintf('第%s行: 不能删除，运单号:%s', $count++, $trackNumber);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 没有找到运单号', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除运单号', $path);
        }
        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
        $this->assign('shipping', $shipping);
        $this->assign('infor', $info);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 更新已打包
     */
    public function update_package()
    {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        //所属仓库只能看到所属仓库的订单
        $belong_ware_id = $_SESSION['belong_ware_id'];
        $warehouse = M('Warehouse')->getField('id_warehouse','title',true);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Order/OrderShipping');
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_package', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                if ($row[0]) {
                    $selectShip = $ordShip->where(array('track_number' => trim($row[0])))->find();//运单号信息
                    $selectOrder = M('Order')->field('id_order,id_order_status,id_warehouse')->where(array('id_increment'=>$row[0]))->find();//订单号信息
                    if ($selectShip &&  $selectShip['id_order']) {
                        $order_id = $selectShip['id_order'];
                        $get_order = D("Order/Order")->field('id_order_status,id_warehouse')->where(array('id_order' => $order_id))->find();
                        if(in_array($get_order['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1))
                        {
                            if ( in_array($get_order['id_order_status'],array(OrderStatus::PICKED,OrderStatus::PICKING)))  //订单状态为配货中或者已配货时，进行打包
                            {
                                $result = UpdateStatusModel::add_order_out_all($order_id);//订单状态更新为->更新已打包状态进行扣库存
                                if($result){
                                    $info['error'][] = sprintf('第%s行: 运单号:%s 更新已打包错误' . $result . '，不能更新为已打包', $count++, $row[0]);
                                    continue;
                                }
                                $updateData = array('id_order_status' => OrderStatus::PACKAGED);
                                D("Order/Order")->where('id_order=' . $order_id)->save($updateData);
                                $id_increment = D('Order/Order')->where('id_order=' . $order_id)->getField('id_increment');
                                D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PACKAGED, 4, '更新已打包：' . $row[0] .',扣库存，减在单！');
                                $info['success'][] = sprintf('第%s行: 订单号:%s 运单号:%s 更新状态: %s', $count++, $id_increment, $row[0], '已打包');
//                                //进行订单库存检测
//                                $res_one = UpdateStatusModel::check_stock_right($order_id);
//                                if ($res_one['flag'])
//                                {
//
//                                }
//                                else
//                                {
//                                    $info['error'][] = sprintf('第%s行: 运单号:%s 缺少sku:%s ，请添加库存!', $count++, $row[0],$res_one['data']);
//                                }
                            }
                            else
                            {
                                $show_text = $statusLabel[$get_order['id_order_status']];
                                $info['error'][] = sprintf('第%s行: 运单号:%s 订单状态是' . $show_text . '，不能更新为已打包', $count++, $row[0]);
                            }
                        }
                        else
                        {
                            $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，该订单属于%s仓库', $count++, $row[0],$warehouse[$get_order['id_warehouse']]);
                        }
                    }
                    elseif ($selectOrder &&  $selectOrder['id_order'])
                    {
                        $order_id = $selectOrder['id_order'];
                        if(in_array($selectOrder['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1))
                        {
                            if ( in_array($selectOrder['id_order_status'],array(OrderStatus::PICKED,OrderStatus::PICKING)))  //订单状态为配货中或者已配货时，进行打包
                            {
                                //进行订单库存检测
//                                $res_one = UpdateStatusModel::check_stock_right($order_id);
//                                if ($res_one['flag'])
//                                {
                                    $result = UpdateStatusModel::add_order_out_all($order_id);//订单状态更新为->更新已打包状态进行扣库存
                                    if($result){
                                        $info['error'][] = sprintf('第%s行: 订单号:%s 更新已打包错误' . $result . '，不能更新为已打包', $count++, $row[0]);
                                        continue;
                                    }
                                    $updateData = array('id_order_status' => OrderStatus::PACKAGED);
                                    D("Order/Order")->where('id_order=' . $order_id)->save($updateData);

                                    $track_number = D('Order/OrderShipping')->where('id_order=' . $order_id)->getField('track_number');
                                    D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PACKAGED, 4, '更新已打包：' . $row[0] .',扣库存，减在单！');
                                    $info['success'][] = sprintf('第%s行: 订单号:%s 订单号:%s 更新状态: %s', $count++, $row[0], $track_number, '已打包');
//                                }
//                                else
//                                {
//                                    $info['error'][] = sprintf('第%s行: 订单号:%s 缺少sku:%s ，请添加库存!', $count++, $row[0],$res_one['data']);
//                                }
                            }
                            else
                            {
                                $show_text = $statusLabel[$selectOrder['id_order_status']];
                                $info['error'][] = sprintf('第%s行: 订单号:%s 订单状态是' . $show_text . '，不能更新为已打包', $count++, $row[0]);
                            }
                        }
                        else
                        {
                            $info['error'][] = sprintf('第%s行: 订单号:%s 更新状态失败，该订单属于%s仓库', $count++, $row[0],$warehouse[$selectOrder['id_warehouse']]);
                        }
                    }
                    else
                    {
                        $info['error'][] = sprintf('第%s行: 单号:%s 更新状态失败，没有找到订单', $count++, $row[0]);
                    }
                }
                else
                {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新已打包', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    public function update_package_list()
    {

        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $where = array();

        if(isset($_GET['id_department'])&&$_GET['id_department']){
            $where['upr.id_department'] = trim($_GET['id_department']);
        }
        if(isset($_GET['id_users'])&&$_GET['id_users']){
            $where['upr.id_users'] = trim($_GET['id_users']);
        }
        if(isset($_GET['id_increment'])&&$_GET['id_increment']){
            $where['upr.id_increment'] = trim($_GET['id_increment']);
        }
        if(isset($_GET['track_number'])&&$_GET['track_number']){
            $where['upr.track_number'] = trim($_GET['track_number']);
        }
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['upr.created_at'] = $created_at_array;
        }
        $users = M('Users')->field('id,user_nicename')->where(array('user_status' => 1))->select();
        $users = array_column($users, 'user_nicename', 'id');
        $shipping = D('Common/Shipping')->cache(true,36000)->select();
        $shipping_data = array_column($shipping,'title','id_shipping');
        if(isset($_REQUEST['act']) && $_REQUEST['act']=='export'){

            vendor("PHPExcel.PHPExcel");
            vendor("PHPExcel.PHPExcel.IOFactory");
            vendor("PHPExcel.PHPExcel.Writer.CSV");
            $excel = new \PHPExcel();
            $idx = 2;
            $column = array(
                'id','物流','部门','扫码人员','订单号','快递单号','建立日期'
            );
            $j = 65;
            foreach ($column as $col) {
                $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
                ++$j;
            }
            $list =  M('UpdatePackageRecord')->alias('upr')
                ->field('upr.*')
                ->join('__DEPARTMENT__  as d on d.id_department = upr.id_department','LEFT')
                ->join('__USERS__ as u on u.id = upr.id_users','LEFT')
                ->where($where)->select();

            foreach ($list as $key=>$val) {
                $data = array(
                    $val['id'],$shipping_data[$val['id_shipping']],$department[$val['id_department']],$users[$val['id_users']],$val['id_increment'],$val['track_number'],$val['created_at']
                );
                $j = 65;
                foreach ($data as $key=>$col) {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                    ++$j;
                }
                ++$idx;
            }
            add_system_record(sp_get_current_admin_id(), 7, 2, '导出更新已打包列表');
            $excel->getActiveSheet()->setTitle(date('Y-m-d') . '更新已打包列表.xlsx');
            $excel->setActiveSheetIndex(0);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '更新已打包列表.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $writer->save('php://output');
            exit();
        }else{
            $count = M('UpdatePackageRecord')->alias('upr')
                ->field('upr.*')
                ->join('__DEPARTMENT__  as d on d.id_department = upr.id_department','LEFT')
                ->join('__USERS__ as u on u.id = upr.id_users','LEFT')
                ->where($where)->count();
            $page = $this->page($count, 20);
            $list =  M('UpdatePackageRecord')->alias('upr')
                ->field('upr.*')
                ->join('__DEPARTMENT__  as d on d.id_department = upr.id_department','LEFT')
                ->join('__USERS__ as u on u.id = upr.id_users','LEFT')
                ->where($where)->limit($page->firstRow, $page->listRows)->select();
        }

        $this->assign('shipping_data',$shipping_data);
        $this->assign('users',$users);
        $this->assign('list',$list);
        $this->assign('department',$department);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign("getData",$_GET);
        add_system_record($_SESSION['ADMIN_ID'], 4, 3,'查看更新已打包列表');
        $this->display();
    }

    /**
     * 更新已打包
     */
    public function update_package_code()
    {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        //所属仓库只能看到所属仓库的订单
        $belong_ware_id = $_SESSION['belong_ware_id'];
        $warehouse = M('Warehouse')->getField('id_warehouse','title',true);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Order/OrderShipping');
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    /**
     * 更新已打包_打包
     */
    public function update_package_code_save()
    {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        //所属仓库只能看到所属仓库的订单\
        $_SESSION['belong_ware_id'][0]=1;
        $belong_ware_id = $_SESSION['belong_ware_id'];
        $warehouse = M('Warehouse')->getField('id_warehouse','title',true);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $total = 0;
        $ordShip = D('Order/OrderShipping');
        if (IS_AJAX) {
                $row[0]=trim($_REQUEST['track_number']);
                if (empty($row)) {
                    $return_data['title']='格式不正确';
                    $return_data['code']=0;
                }else{
                    if ($row[0]) {
                        $selectShip = $ordShip->where(array('track_number' => trim($row[0])))->find();//运单号信息
                        $selectOrder = M('Order')->field('id_order,id_order_status,id_warehouse,id_department,id_shipping')->where(array('id_increment'=>$row[0]))->find();//订单号信息
                        if ($selectShip &&  $selectShip['id_order']) {
                            $order_id = $selectShip['id_order'];
                            $get_order = D("Order/Order")->field('id_order_status,id_warehouse')->where(array('id_order' => $order_id))->find();
                            if(in_array($get_order['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1))
                            {
                                if ( in_array($get_order['id_order_status'],array(OrderStatus::PICKED,OrderStatus::PICKING)))  //订单状态为配货中或者已配货时，进行打包
                                {
                                    $result = UpdateStatusModel::add_order_out_all($order_id);//订单状态更新为->更新已打包状态进行扣库存
                                    if($result){
                                        $return_data['title']='运单号:'.$row[0].' 更新已打包错误' . $result . '，不能更新为已打包';
                                        $return_data['code']=0;
                                    }else{
                                        $updateData = array('id_order_status' => OrderStatus::PACKAGED);
                                        D("Order/Order")->where('id_order=' . $order_id)->save($updateData);
                                        // UpdateStatusModel::add_order_out_all($order_id);//订单状态更新为->更新已打包状态进行扣库存
                                        $id_increment = D('Order/Order')->where('id_order=' . $order_id)->getField('id_increment');
                                        D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PACKAGED, 4, '更新已打包：' . $row[0] .',扣库存，减在单！');
                                        //$info['success'][] = sprintf('第%s行: 订单号:%s 运单号:%s 更新状态: %s', $count++, $id_increment, $row[0], '已打包');
                                        $return_data['title']='订单号:'.$id_increment.",运单号:". $row[0]."，更新状态: 已打包";
                                        $return_data['code']=1;
                                        //写入记录
                                        $orderdata=D('Order/Order')->field('id_department,id_shipping')->where('id_order=' . $order_id)->find();
                                        $adddata['id_department']=$orderdata['id_department'];
                                        $adddata['id_shipping']=$orderdata['id_shipping'];
                                        $adddata['id_increment']=$id_increment;
                                        $adddata['track_number']=$row[0];
                                        $adddata['id_users']=$_SESSION['ADMIN_ID'];
                                        $adddata['created_at'] = date('Y-m-d H:i:s');
                                        $result=M('UpdatePackageRecord')->data($adddata)->add();
                                    }

                                }
                                else
                                {
                                    $show_text = $statusLabel[$get_order['id_order_status']];
                                    //$info['error'][] = sprintf('第%s行: 运单号:%s 订单状态是' . $show_text . '，不能更新为已打包', $count++, $row[0]);
                                    $return_data['title']='运单号:'.$row[0].",订单状态是:". $show_text."，不能更新为已打包";
                                    $return_data['code']=0;
                                }
                            }
                            else
                            {
                                //$info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，该订单属于%s仓库', $count++, $row[0],$warehouse[$get_order['id_warehouse']]);
                                $return_data['title']='运单号:'.$row[0].",更新状态失败该订单属于".$warehouse[$get_order['id_warehouse']]."仓库";
                                $return_data['code']=0;
                            }
                        }
                        elseif ($selectOrder &&  $selectOrder['id_order'])
                        {
                            $order_id = $selectOrder['id_order'];
                            if(in_array($selectOrder['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1))
                            {
                                if ( in_array($selectOrder['id_order_status'],array(OrderStatus::PICKED,OrderStatus::PICKING)))  //订单状态为配货中或者已配货时，进行打包
                                {
                                    $result = UpdateStatusModel::add_order_out_all($order_id);//订单状态更新为->更新已打包状态进行扣库存
                                    if($result){
                                        $return_data['title']='订单号:'.$row[0].' 更新已打包错误' . $result . '，不能更新为已打包';
                                        $return_data['code']=0;
                                    }else{
                                        $updateData = array('id_order_status' => OrderStatus::PACKAGED);
                                        D("Order/Order")->where('id_order=' . $order_id)->save($updateData);
                                        //UpdateStatusModel::add_order_out_all($order_id);//订单状态更新为->更新已打包状态进行扣库存
                                        $track_number = D('Order/OrderShipping')->where('id_order=' . $order_id)->getField('track_number');
                                        D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PACKAGED, 4, '更新已打包：' . $row[0] .',扣库存，减在单！');
                                        // $info['success'][] = sprintf('第%s行: 订单号:%s 订单号:%s 更新状态: %s', $count++, $row[0], $track_number, '已打包');
                                        $return_data['title']="订单号:".$row[0]."运单号:".$track_number. "更新状态:已打包";
                                        $return_data['code']=1;
                                        //写入记录
                                        $adddata['id_department']=$selectOrder['id_department'];
                                        $adddata['id_shipping']=$selectOrder['id_shipping'];
                                        $adddata['id_increment']=$row[0];
                                        $adddata['track_number']=$track_number;
                                        $adddata['id_users']=$_SESSION['ADMIN_ID'];
                                        $adddata['created_at'] = date('Y-m-d H:i:s');
                                        $result=M('UpdatePackageRecord')->data($adddata)->add();
                                    }


                                }
                                else
                                {
                                    $show_text = $statusLabel[$selectOrder['id_order_status']];
                                    //$info['error'][] = sprintf('第%s行: 订单号:%s 订单状态是' . $show_text . '，不能更新为已打包', $count++, $row[0]);
                                    $return_data['title']='订单号:'.$row[0].",订单状态是:". $show_text."，不能更新为已打包";
                                    $return_data['code']=0;
                                }
                            }
                            else
                            {
                                //  $info['error'][] = sprintf('第%s行: 订单号:%s 更新状态失败，该订单属于%s仓库', $count++, $row[0],$warehouse[$selectOrder['id_warehouse']]);
                                $return_data['title']='运单号:'.$row[0].",更新状态失败该订单属于".$warehouse[$selectOrder['id_warehouse']]."仓库";
                                $return_data['code']=0;
                            }
                        }
                        else
                        {
                            //$info['error'][] = sprintf('第%s行: 单号:%s 更新状态失败，没有找到订单', $count++, $row[0]);
                            $return_data['title']='单号:'.$row[0].",更新状态失败，没有找到订单";
                            $return_data['code']=0;
                        }
                    }
                }
                add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新已打包', $row[0]);
        }
        $title = $_GET['track_number'];

        if($return_data['code']==0){
            $data=$return_data['title'];
        }else{
            $data = '<ul>';
            $data.='<li style="padding-top:5px;padding-bottom:5px">'.$return_data['title'].'</li>';
            $data.='</ul>';
        }
        echo json_encode($data);
        exit;
    }

     /**
     * 更新重量.格式:运单号,重量
     */
    public function update_weight() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');

            //导入记录到文件
            $path = write_file('warehouse', 'update_weight', $data);

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
//                $track_number = trim($row[0]);
//                $weight = trim($row[0]);
                $track_number = str_replace("'", '', $row[0]);
                $track_number = str_replace('"', '', $track_number);
                $track_number = trim($track_number);
                $weight = trim($row[1]);//重量
                //查找全局是否有重复运单号
                $finded = D('Order/OrderShipping')
                    ->field('id_order, track_number')
                    ->where(array(
                        'track_number' => $track_number
                    ))
                    ->find();
                if (!$finded) {
                    $infor['error'][] = sprintf('第%s行:  运单号:%s 运单号不存在.', $count++, $track_number);
                    continue;
                }else{
                    $updateData = array('weight' => (float)$weight);
                    $update = D("Order/OrderShipping")->where('track_number=' . $track_number)->save($updateData);
                    if($update)$infor['success'][] = sprintf('第%s行: 运单号: %s   重量:%s', $count++, $track_number,(float)$weight);
                    else $infor['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败', $count++, $track_number);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新重量',$path);
        }
        $this->assign('post',$_POST);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 更新波次单为已完成状态
     */
    public function update_wave()
    {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        //所属仓库只能看到所属仓库的订单
        $belong_ware_id = $_SESSION['belong_ware_id'];
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordWave = D('Order/OrderWave');
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_wave', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                if ($row[0]) {
                    $selectWave = $ordWave->where(array(
                        'wave_number' => trim($row[0])
                    ))
                        ->find();
                    if ($selectWave &&  $selectWave['wave_number']) {
                        $wave_number = $selectWave['wave_number'];
                        $today = date('Y-m-d H:i:s');
                        $id_order = $ordWave->field('id_order,id_shipping,track_number_id')->where(array('wave_number'=>$wave_number))->select();
                        $id_orders = '';
                        foreach($id_order as $v){
                            if(empty($v['id_shipping'])||empty($v['track_number_id'])){
                                $info['error'][] = sprintf('第%s行: 波次单号:%s 更新状态失败,未匹配订单运单号', $count++, $row[0]);
                                break;
                            }else{
                                    $id_orders.=$v['id_order'].',';
                            }
                        }
                        $id_orders = trim($id_orders,',');
                       if($id_orders){
                           $id_orders = trim($id_orders,',');
                           $updateOrder = array('id_order_status' => 7);
                           $status = $this->orderModel->field('id_order,id_order_status,id_warehouse')->where(array('id_order'=>array('IN',$id_orders)))->select();
                           foreach($status as $v){
                               if(in_array($v['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1)) {
                                   if ($v['id_order_status'] == 8 || $v['id_order_status'] == 18 || $v['id_order_status'] == 9 || $v['id_order_status'] == 14) {
                                       $res1 = 1;
                                   } else {
                                       $res1 = $this->orderModel->where(array('id_order' => array('EQ', $v['id_order'])))->save($updateOrder);
                                       D("Order/OrderRecord")->addHistory($v['id_order'], 7, 4, '根据波次单号更新状态：' . $wave_number);
                                   }
                               } else {
                                   $info['error'][] = sprintf('第%s行: 波次单号:%s 更新状态失败,波次单内有其他仓库的订单', $count++, $row[0]);
                                   continue;
                               }
                           }
                           $updateData = array('status' => 1);
                           $res2 = $ordWave->where(array('wave_number'=>$wave_number))->save($updateData);
                           if($res1&&$res2){
                               $info['success'][] = sprintf('第%s行: 波次单号:%s 更新状态: %s', $count++, $wave_number, '已完成');
                           }
                           else
                               $info['error'][] = sprintf('第%s行: 波次单号:%s 更新状态失败', $count++, $row[0]);
                       }
                    }
                    else {
                        $info['error'][] = sprintf('第%s行: 波次单号:%s 更新状态失败，没有找到这个波次单', $count++, $row[0]);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新波次单状态', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 退货导入
     */
    public function return_warehouse() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $belong_ware_id = $_SESSION['belong_ware_id'];
//        dump($belong_ware_id);die;
        $where['status'] = 1;
        if(count($belong_ware_id) != 1 || (count($belong_ware_id) == 1 && $belong_ware_id[0] != 1)) {
            $where['id_warehouse'] = array('IN',$belong_ware_id);
        }
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where($where)->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'return_warehouse', $data);
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
                    $order = M('Order')->field('id_order_status,id_increment')->where(array('id_order'=>$id_order))->find();
                    if($order['id_order_status']==OrderStatus::DELIVERING || $order['id_order_status']==OrderStatus::RETURNED || $order['id_order_status']==OrderStatus::REJECTION || $order['id_order_status']==OrderStatus::CLAIMS) {
                        D('Order/Order')->where(array('id_order'=>$id_order))->save(array('id_order_status'=>OrderStatus::RETURN_WAREHOUSE));
                        D("Order/OrderRecord")->addHistory($id_order, OrderStatus::RETURN_WAREHOUSE, 4, '更新订单为退货入库状态，运单号' . $track_number.'，仓库' .$warehouse[$_POST['warehouse_id']]);
                        $infor['success'][] = sprintf('第%s行: 订单号:%s 运单号:%s 仓库名称: %s', $count++, $order['id_increment'], $track_number, $warehouse[$_POST['warehouse_id']]);
                    } else {
                        $infor['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 该运单号不能进行退货入库操作', $count++, $order['id_increment'], $track_number);
                    }
                } else {
                    $infor['error'][] = sprintf('第%s行: 运单号:%s 没有该运单号', $count++, $track_number);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, '更新订单为退货入库状态', $path);
        }

        $this->assign('post', $_POST);
        $this->assign('warehouse', $warehouse);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    /**
     * 退货导入
     */
    public function return_warehouse2() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $belong_ware_id = $_SESSION['belong_ware_id'];
//        dump($belong_ware_id);die;
        $where['status'] = 1;
        if(count($belong_ware_id) != 1 || (count($belong_ware_id) == 1 && $belong_ware_id[0] != 1)) {
            //$where['id_warehouse'] = array('IN',$belong_ware_id);
        }
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where($where)->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'return_warehouse', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            $return_no=date("YmdHis").round(0,99999);
            $all_return_no=0;//退货单数
            $all_return_quantity=0;//退货个数
            $idArray=array();
//            var_dump(sizeof($data));
//            var_dump(count($data));
//            die;
            if(count($data)>=160){
                $infor['error'][] = sprintf('导入的行数过大，最多不超过160行');
            }else{
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
                        $order = M('Order')->field('id_order_status,id_increment,id_warehouse,id_department')->where(array('id_order'=>$id_order))->find();
                        if($order['id_order_status']==OrderStatus::DELIVERING || $order['id_order_status']==OrderStatus::RETURNED || $order['id_order_status']==OrderStatus::REJECTION || $order['id_order_status']==OrderStatus::CLAIMS) {
                            D('Order/Order')->where(array('id_order'=>$id_order))->save(array('id_order_status'=>OrderStatus::RETURN_WAREHOUSE));
                            D("Order/OrderRecord")->addHistory($id_order, OrderStatus::RETURN_WAREHOUSE, 4, '更新订单为退货入库状态，运单号' . $track_number.'，仓库' .$warehouse[$_POST['warehouse_id']]);
                            $ProductData=D("Common/OrderItem")->where(array('id_order'=>$id_order))->field('sku_title,sku,id_product,id_product_sku,product_title,quantity')->select();

                            if(!empty($ProductData)){
                                $total=0;
                                $Prodate['id_return']=0;
                                $Prodate['track_number']=$track_number;
                                foreach($ProductData as $k=>$v){
                                    //$Prodate['sku']=$v['sku'];
                                    $Prodate['id_product']=$v['id_product'];
                                    $Prodate['id_product_sku']=$v['id_product_sku'];
                                    //$Prodate['title']=$v['product_title'];
                                    $Prodate['quantity']=$v['quantity'];
                                    $Prodate['option_value']=$v['sku_title'];
                                    //$total=$total+$v['quantity'];
                                    $id=M('WarehouseReturnProduct')->add($Prodate);
                                    array_push($idArray,$id);
                                    $total=$total+$v['quantity'];
                                }
                                $all_return_no=$all_return_no+1;
                                $all_return_quantity=$all_return_quantity+$total;
                                $id_department=$order['id_department'];

                            }
                            $infor['success'][] = sprintf('第%s行: 订单号:%s 运单号:%s 仓库名称: %s 退货入库单号: %s', $count++, $order['id_increment'], $track_number, $warehouse[$_POST['warehouse_id']],$return_no);
                        } else {
                            $infor['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 该运单号不能进行退货入库操作', $count++, $order['id_increment'], $track_number);
                        }
                    } else {
                        $infor['error'][] = sprintf('第%s行: 运单号:%s 没有该运单号', $count++, $track_number);
                    }
                }
                $adddate['updated_at'] = date('Y-m-d H:i:s');
                $adddate['created_at'] = date('Y-m-d H:i:s');
                $adddate['status'] = 0;
                $adddate['track_number'] =$all_return_no;
                $adddate['id_warehouse'] = $_POST['warehouse_id'];
                $adddate['id_department'] = $id_department;
                $adddate['total'] = $all_return_quantity;
                $adddate['total_received'] = 0;
                $adddate['return_no'] = $return_no;
                $add=M('WarehouseReturn')->add($adddate);

                M('WarehouseReturnProduct')->where(array('id_return_product'=>array('IN',$idArray)))->save(["id_return"=>$add]);
                add_system_record($_SESSION['ADMIN_ID'], 2, 4, '更新订单为退货入库状态', $path);
            }

        }

        $this->assign('post', $_POST);
        $this->assign('warehouse', $warehouse);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    /**
     * 导入订单移除波次单订单
     */
    public function import_wave_order() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_wave_order', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $id_increment = $row[0];//订单号
                $order = M('Order')->field('id_order')->where(array('id_increment'=>$id_increment))->find();
                if($order) {
                    $wave = M('OrderWave')->field('id,id_order,wave_number,track_number_id')->where(array('id_order'=>$order['id_order']))->find();
                    if($wave) {
                        $result = D('Common/OrderWave')->delete($wave['id']);
                        if($result){
                            if ($wave['track_number_id']) {
                                $shipping_track = M('ShippingTrack')->field('id_shipping,track_number')->where(array('id_shipping_track' => $wave['track_number_id']))->find();
//                                D('Common/ShippingTrack')->where(array('id_shipping_track' => $wave['track_number_id']))->save(array('track_status' => 0));
                                D('Order/OrderShipping')->where(array('id_order' => $wave['id_order'],'id_shipping'=>$shipping_track['id_shipping'],'track_number'=>$shipping_track['track_number']))->delete();
                            }
                            $order_data = D("Order/Order")->where(array('id_order' => $wave['id_order']))->find();
                            if (in_array($order_data['id_order_status'],OrderStatus::get_canceled_to_rollback_status()))
                            {
                                UpdateStatusModel::wave_delete_rollback_stock($wave['id_order']);
                            }
                            D('Order/Order')->where(array('id_order' => $wave['id_order']))->save(array('id_order_status' => 4,'id_shipping'=>0,'date_delivery'=>null));
                            D("Order/OrderRecord")->addHistory($wave['id_order'], 4, 4, '移除波次单里的订单，把订单状态改为未配货');
                            $infor['success'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 移除成功', $count++, $id_increment, $wave['wave_number']);
                        }
                    } else {
                        $infor['error'][] = sprintf('第%s行: 订单号:%s 该订单号不在波次单内，不能进行移除操作', $count++, $id_increment);
                    }
                } else {
                    $infor['error'][] = sprintf('第%s行: 订单号:%s 找不到该订单号', $count++, $id_increment);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, '移除波次单订单', $path);
        }
        
        $this->assign('post', $_POST);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    
    public function matching_order_telphone(){
     
        $this->matching_order();
    }
    
    public function matching_order_all(){
        
        $this->matching_order();
    }

    /**
     * 匹配订单信息
     */
    public function matching_order(){
        $infor = array(
            'error'   => array(),
            'warning' => array(),
            'success' => array()
        );
        $ordShip = D("Order/OrderShipping");
        $total = 0;
        $orders = array();
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'matching_order', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            $fields='id_order,id_increment,id_department,tel,id_shipping,id_order_status,created_at,date_delivery,id_zone,address,remark,comment,payment_method,id_warehouse,first_name,last_name,zipcode';
            foreach ($data as $key=> $row) {
                $row = trim($row);
                $tipstr='';
                if (empty($row))
                    continue;
                ++$total;

                if ($row) {                   
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
              
                } else {
                    $infor['error'][] = sprintf('第%s行: 订单不存在', $count++);
                }
            }

        }
//        if($infor['error']){
//            $this->assign('infor', $infor);
//            $this->assign('data', I('post.data'));
//            $this->assign('total', $total);
//            if($_POST['isall']==1){
//                $this->display('matching_order_all');
//            }  
//            if($_POST['istelphone']==1){
//                $this->display('matching_order_telphone');
//            }
//            if(!$_POST['isall']&&!$_POST['istelphone']){
//                $this->display();
//            }
//            exit();          
//        }
        if($orders){
            //导出excle
            try{
                set_time_limit(0);
                vendor("PHPExcel.PHPExcel");
                vendor("PHPExcel.PHPExcel.IOFactory");
                vendor("PHPExcel.PHPExcel.Writer.CSV");
                $excel = new \PHPExcel();
                $column = "运单号,订单号, 产品名, 属性和数量, SKU,部门, 总数量,订单状态,物流,物流状态,下单时间,发货时间\n";
                if($_POST['istelphone']==1){
                    $column = "运单号,订单号,电话, 产品名, 属性和数量, SKU,部门, 总数量,订单状态,物流,物流状态,下单时间,发货时间\n";
                }
                if($_POST['isall']==1){
                    $column = "地区,物流,订单号,电话,运单号,姓名,外部产品名,内部产品名,外文产品名,属性,sku,总价,产品数量,送货地址,留言备注,下单时间,订单状态,发货时间,后台备注,付款方式,邮编,仓库\n";
                    
                }
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
                $shipping_data = D('Common/Shipping')->cache(true,36000)->getField('id_shipping,title');
                $department  = D('Department/Department')->where('type=1')->cache(true,3600)->getField('id_department,title');
                $zoneList=M('zone')->cache(true,36000)->getField('id_zone,title');
                $warehouseList=M('warehouse')->cache(true,36000)->where(array('status'=>1))->getField('id_warehouse,title');
                foreach ($orders as $o) { 
                    if($o){
                        $product_name = [];
                        $attrs = '';
                        $all_productTitle=[];
                        $all_innername=[];
                        $all_foreignTitle=[];
                        $all_attrs=[];
                        $products = $order_item->get_item_list($o['id_order']);
                        $sum_num = 0;
                        $skus =[];
                        $totalPrice=0;
//                        var_dump($products);
                        foreach ($products as $p) {
                            $all_productTitle[]=$p['product_title'];
                            $all_innername[]=$p['inner_name'];
                            $all_foreignTitle[]=$p['foreign_title'];
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
//                            var_dump($p);
                        }
//                        die();
                        $product_name=  implode(';', array_unique($product_name));
                        $attrs = trim($attrs, ';');
                        $sku = implode(';',$skus);
                        $all_productTitle=implode(';',array_unique($all_productTitle));
                        $all_innername=implode(';',array_unique($all_innername));
                        $all_foreignTitle=implode(';',array_unique($all_foreignTitle));
                        $all_attrs=implode(';',$all_attrs);
                        $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
                        $order_shipping = M('OrderShipping')->field('status_label')->where(array('id_order'=>$o['id_order']))->select();
                        $trackStatusLabel = $order_shipping ? implode(',', array_column($order_shipping, 'status_label')) : '';
                        $trackStatusLabel=  str_replace(',', ' ', $trackStatusLabel);
                        $product_name=  str_replace(',', ' ', $product_name);
                        $attrs=  str_replace(',', ' ', $attrs);
                        if(!$_POST['istelphone']&&!$_POST['isall']){
                            $column.=$o['track_number']. "\t," .$o['id_increment']."\t,".$product_name . ',' .$attrs. ',' .trim($sku, ','). "\t," .$department[$o['id_department']] . ','. $sum_num . ',' .$status_name . ',' . $shipping_data[$o['id_shipping']] . ',' .$trackStatusLabel. ',' .$o['created_at']. "\t,".$o['date_delivery']. "\t\n" ;  
                        }
//                        $data = array(
//                            ' '.$o['track_number'],' '.$o['id_increment'],$product_name, $attrs, trim($sku, ','),$department[$o['id_department']],$sum_num,
//                            $status_name,$shipping_data[$o['id_shipping']],$trackStatusLabel,$o['created_at'],$o['date_delivery']
//                        );
                        if($_POST['istelphone']==1){
                            $column.=$o['track_number']. "\t," .$o['id_increment']."\t,".$o['tel']."\t,".$product_name . ',' .$attrs. ',' .trim($sku, ','). "\t," .$department[$o['id_department']] . ','. $sum_num . ',' .$status_name . ',' . $shipping_data[$o['id_shipping']] . ',' .$trackStatusLabel. ',' .$o['created_at']. "\t,".$o['date_delivery']. "\t\n" ;                              
//                            $data = array(
//                            ' '.$o['track_number'],' '.$o['id_increment'],$o['tel'],$product_name, $attrs, trim($sku, ','),$department[$o['id_department']],$sum_num,
//                            $status_name,$shipping_data[$o['id_shipping']],$trackStatusLabel,$o['created_at'],$o['date_delivery']
//                        );
                        }
                        if($_POST['isall']==1){
                            $o['address']=  str_replace(',', ' ', $o['address']);
                            $o['remark']=  str_replace(',', ' ', $o['remark']);
                            $o['comment']=  str_replace(',', ' ', $o['comment']);
                            $paymethodstr=$o['payment_method']=='0'?'货到付款':'在线支付';
                            $column.=$zoneList[$o['id_zone']]. "," .$shipping_data[$o['id_shipping']].",".$o['id_increment']."\t,".$o['tel'] ."\t," .$o['track_number']. "\t," .$o['first_name']. "\t," .$all_productTitle . ','. $all_innername . ',' .$all_foreignTitle . ',' .$all_attrs. ',' .$sku. "\t,".$totalPrice. ",".$sum_num. "," .$o['address']. "," .$o['remark']. ",".$o['created_at']. "\t,".$status_name. ",".$o['date_delivery']. "\t," .$o['comment']. ",".$paymethodstr. "," .$o['zipcode']. "\t,".$warehouseList[$o['id_warehouse']]. "\n" ;     
                            
//                            $data=[$zoneList[$o['id_zone']],$shipping_data[$o['id_shipping']],$o['id_increment'],$o['tel'],$o['track_number'],$o['first_name'].$o['last_name'],$all_productTitle,$all_innername,$all_foreignTitle,$all_attrs,$sku,$totalPrice,$sum_num,$o['address'],$o['remark'],$o['created_at'],$status_name,$o['date_delivery'],$o['comment'],$paymethodstr,$o['zipcode'],$warehouseList[$o['id_warehouse']]];
                        }
//                        $j = 65;
//                        foreach ($data as $key=>$col) {
//                            if($key != 6&&$key!=10&&$key!=11&&$key!=19){
//                                $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
//                            } else {
//                                $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
//                            }
//                            ++$j;
//                        }
//                        ++$idx;
                    }
                }
                add_system_record(sp_get_current_admin_id(), 7, 4, '导出匹配订单信息',$path);
                $filename = date('Ymd') . '订单信息.csv'; //设置文件名
                $this->export_csv($filename, iconv("UTF-8","GBK//IGNORE",$column)); //导出
                exit;                
//                $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
//                $excel->setActiveSheetIndex(0);
//                header('Content-Type: application/vnd.ms-excel');
//                header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
//                header('Cache-Control: max-age=0');
//                $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
//                $writer->save('php://output');
               
//                exit();
            }catch (\Exception $e){
                print_r($e->getMessage());
            }
        }
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    
    protected function export_csv($filename, $data) {
        header("Content-type:text/csv;charset=UTF-8");
        header("Content-Disposition:attachment;filename=" . $filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }    
    /**
     * 导出退货信息， 根据运单号
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    public function return_order_info() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');

            //导入记录到文件
            $path = write_file('warehouse', 'return_order_info', $data);
            $data = $this->getDataRow($data);
            $count = 1;

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
            $col_number = 2;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                //$name = trim($row[2]);
                $track_number = str_replace("'", '', $row[0]);
                $track_number = str_replace(array('"',' ',' ','　'),'', $track_number);
                $track_number = trim($track_number);
//                $weight = trim($row[2]);//重量
                //查找全局是否有重复运单号
                $finded = D('Order/OrderShipping')
                    ->field('id_order, track_number')
                    ->where(array(
                        'track_number' => $track_number
                    ))
                    ->find();
                if($finded){
                    $order_d = $order_model->alias('o')->field('o.id_increment,d.title')
                        ->join('__DEPARTMENT__ d ON (d.id_department = o.id_department)', 'LEFT')
                        ->where(array('o.id_order'=>$finded['id_order']))->find();

                    $pro_title = array();
                    $pro_quantity = 0;
                    $products = $order_item->get_item_list($finded['id_order']);
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
                ++$col_number;
            }

            add_system_record(sp_get_current_admin_id(), 7, 4, '导出退货信息',$path);
            $excel->getActiveSheet()->setTitle(date('Y-m-d') . '退货信息.xlsx');
            $excel->setActiveSheetIndex(0);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . date('Y-m-d'). '退货信息.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $writer->save('php://output');
            exit();
        }
        $this->assign('data', I('post.data'));
        $this->display();
    }
    
    /**
     * 导入问题件
     */
    public function import_problem_prod() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );

        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_problem_prod', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = explode("\t", trim($row), 2);
                $order_shipp = M('OrderShipping')->where(array('track_number'=>$row[0]))->find();//运单号
                $order = M('Order')->where(array('id_increment'=>$row[0]))->find();//订单号
                if($order_shipp) {
                    $order_id = $order_shipp['id_order'];
                    $result = D('Order/Order')->where(array('id_order'=>$order_id))->save(array('id_order_status'=> OrderStatus::PROBLEM,'problem_name'=>$_POST['problem_name']));
                    if($result){
                        D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PROBLEM, 4, '更新订单件为问题件，运单号' . $row[0].'，问题类型：'.$_POST['problem_name']);
                        $info['success'][] = sprintf('第%s行: 运单号:%s 更新状态成功，问题类型：%s', $count++, $row[0], $_POST['problem_name']);
                    } else {
                        $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败', $count++, $row[0]);
                    }
                } else if($order) {
                    $order_id = $order['id_order'];
                    $result = D('Order/Order')->where(array('id_order'=>$order_id))->save(array('id_order_status'=> OrderStatus::PROBLEM,'problem_name'=>$_POST['problem_name']));
                    if($result){
                        D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PROBLEM, 4, '更新订单件为问题件，订单号' . $row[0].'，问题类型：' .$_POST['problem_name']);
                        $info['success'][] = sprintf('第%s行: 订单号:%s 更新状态成功，问题类型：%s', $count++, $row[0], $_POST['problem_name']);
                    } else {
                        $info['error'][] = sprintf('第%s行: 订单号:%s 更新状态失败', $count++, $row[0]);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 订单号或运单号:%s 不存在', $count++, $row[0]);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, '导入问题件订单', $path);
        }        
        
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    
    /**
     * 更新已转寄
     */
    public function update_forward(){
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Order/OrderShipping');
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_forward', $data);
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
                    $selectShip = $ordShip->where(array('track_number' => trim($row[0])))->find();//获取运单信息
                    $select_order = M('Order')->where(array('id_increment'=>trim($row[0])))->find();//获取订单信息
                    if (($selectShip && $actionID) ||( $select_order && $actionID)) {
                        $msg = $select_order ? '订单号' : '运单号';
                        $order_id = $selectShip['id_order']? $selectShip['id_order']:$select_order['id_order'];
                        $get_order = D("Order/Order")->where(array('id_order' => $order_id))->find();
                        $updateData['id_order_status'] = $actionID;
                        $res = D("Order/Order")->where('id_order=' . $order_id)->save($updateData);
                        D("Order/OrderRecord")->addHistory($order_id, $actionID, 4,' 更新为已转寄状态');
                        if($res)
                          $info['success'][] = sprintf('第%s行: '.$msg.':%s 更新状态: %s', $count++, $row[0], '已转寄状态成功');
                        else
                          $info['error'][] = sprintf('第%s行: '.$msg.':%s 更新状态: %s', $count++, $row[0], '已转寄状态失败');
                    }
                    else {
                        $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，没有找到订单', $count++, $row[0]);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, '更新缺货状态', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 更新匹配转寄
     */
    public function update_match_forward() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Order/OrderShipping');
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_match_forward', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $id_increment = $row[0];
                if ($id_increment) {
                    $select_order = M('Order')->where(array('id_increment'=>trim($id_increment)))->find();//获取订单信息
                    if ($select_order) {
                        $order_id = $select_order['id_order'];
                        if($select_order['id_order_status'] == OrderStatus::MATCH_FORWARDING) {
                            $updateData['id_order_status'] = OrderStatus::MATCH_FORWARDED;
                            $res = D("Order/Order")->where('id_order=' . $order_id)->save($updateData);
                            D("Order/OrderRecord")->addHistory($order_id, OrderStatus::MATCH_FORWARDED, 4,' 更新为已匹配转寄');
                            if($res)
                                $info['success'][] = sprintf('第%s行: 订单号:%s 更新状态: %s', $count++, $row[0], '已匹配转寄状态成功');
                            else
                                $info['error'][] = sprintf('第%s行: 订单号:%s 更新状态: %s', $count++, $row[0], '已匹配转寄状态失败');
                        } else {
                            $info['error'][] = sprintf('第%s行: 订单号:%s 更新状态失败，状态不是匹配转寄中，不能进行更新', $count++, $id_increment);
                        }
                    }
                    else {
                        $info['error'][] = sprintf('第%s行: 订单号:%s 更新状态失败，没有找到订单', $count++, $id_increment);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, '更新匹配转寄', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    
    /**
     * 导入sku库存
     */
    public function update_warehouse_stock() {
        set_time_limit(0);
         $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $warehouse = M('Warehouse')->field('id_warehouse,title')->cache(true,1800)->select();
        $warehouse = array_column($warehouse, 'title','id_warehouse');
        $model = new \Think\Model;
        $order_table_name = D('Order/Order')->getTableName();
        $order_item_table_name = D('Order/OrderItem')->getTableName();
        $total = 0;
        if (IS_POST){
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_warehouse_stock', $data);
            $data = $this->getDataRow($data);
            $warehouse_id = $_POST['warehouse_id'];
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                if (count($row) != 2 || !$row[0]) {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
                $sku = $row[0];//sku
                $stock = $row[1];//库存数
                $sku_result = M('ProductSku')->where(array('sku'=>$sku,'status'=>1))->find();
                if($sku_result) {
                    $ware_pro = M('WarehouseProduct')->field('quantity')->where(array('id_product_sku'=>$sku_result['id_product_sku'],'id_warehouse'=>$warehouse_id))->find();
                    if($ware_pro) {
                        D('Common/WarehouseProduct')->where(array('id_product_sku'=>$sku_result['id_product_sku'],'id_warehouse'=>$warehouse_id))->save(array('quantity'=>$stock));
                        add_system_record(sp_get_current_admin_id(), 2, 1, '导入仓库库存，仓库ID：'.$warehouse_id.'，SKU：'.$sku.'，原有库存：'.$ware_pro['quantity'].'，修改为：'.$stock);
                    } else {
//                        $info['error'][] = sprintf('第%s行: SKU:%s 仓库不存在', $count++, $sku);
                        $data_list = array(
                            'id_warehouse'=>$warehouse_id,
                            'id_product'=>$sku_result['id_product'],
                            'id_product_sku'=>$sku_result['id_product_sku'],
                            'quantity' => $stock,
                            'road_num' => 0
                        );
                        D('Common/WarehouseProduct')->add($data_list);
                        add_system_record(sp_get_current_admin_id(), 2, 1, '导入仓库库存，仓库ID：'.$warehouse_id.'，SKU：'.$sku.'，添加为：'.$stock);
                    }
                    if($stock>0) {
                        $where = 'oi.id_product_sku ='.$sku_result['id_product_sku'].' and o.id_order_status=6';
                        $order_data = $model->table($order_table_name . ' as o LEFT JOIN ' . $order_item_table_name . ' as oi ON o.id_order=oi.id_order')
                            ->field('oi.id_order,o.id_zone,o.id_department,o.id_order_status,o.payment_method')
                            ->where($where)
                            ->order('oi.sorting desc,o.date_purchase asc')
                            ->select();

                        //仓库收货后对缺货产品进行未配货的状态更新，并减去仓库库存
                        if($order_data && $stock>0) {
                            /** @var \Order\Model\OrderRecordModel  $order_record */
                            $order_record = D("Order/OrderRecord");
                            foreach ($order_data as $key=>$val) {
                                //if(in_array($val['id_department'],array(4,5,7))){//4,5,

                                //香港地区DF订单减库存后状态改为已审核
                                if($val['id_zone'] == 3 && empty($val['payment_method'])){
                                    $default_id_order_status = \Order\Lib\OrderStatus::UNPICKING;
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
                                        'comment' => '导入sku库存对缺货状态进行更新,更新为：'.$stock,
                                    );
                                    $order_record->addOrderHistory($parameter);
                                }
                                //}
                            }
                        }
                    }
                    $info['success'][] = sprintf('第%s行: SKU:%s 仓库库存更新为 %s', $count++, $sku, $stock);
                } else {
                    $info['error'][] = sprintf('第%s行: SKU:%s 不存在或者隐藏了', $count++, $sku);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 1, '导入sku库存', $path);
        }        
         
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /**
     * 导入退货
     */
    public function import_return() {
        set_time_limit(0);
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $warehouse = M('Warehouse')->field('id_warehouse,title')->cache(true,1800)->select();
        $warehouse = array_column($warehouse, 'title','id_warehouse');
        $model = new \Think\Model;
        $order_table_name = D('Order/Order')->getTableName();
        $order_item_table_name = D('Order/OrderItem')->getTableName();
        $total = 0;
        if (IS_POST){
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_return', $data);
            $data = $this->getDataRow($data);
            $warehouse_id = $_POST['warehouse_id'];
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 1);
                if (count($row) != 1 || !$row[0]) {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
                $order = $row[0];
                $id_increment=$row[0];

//                id_increment
                //只有配送中，理赔，已退货，拒收这四个状态的订单才能导入入库
                //8,10,16,19
                $orderWehre['id_increment']=$row[0];
                $orderWehre['id_order_status']=array('IN','8,10,16,19');
                $orderdata=D("Common/Order")->where($orderWehre)->field('id_order')->find();

                if(!empty($orderdata)){
                    //直接设置订单状态为未配货
                    $id_order=$orderdata['id_order'];
                    D("Common/Order")->where(array('id_order'=>$id_order))->save([ 'id_order_status'=>20]);
                    $ProductData=D("Common/OrderItem")->where(array('id_order'=>$id_order))->field('sku_title,sku,id_product,id_product_sku,product_title,quantity')->select();
                    if(!empty($ProductData)){
                        //$adddate['sku']=$ProductData['sku'];
                        foreach($ProductData as $k=>$v){
                            $adddate['sku']=$v['sku'];
                            $adddate['id_product']=$v['id_product'];
                            $adddate['id_product_sku']=$v['id_product_sku'];
                            $adddate['title']=$v['product_title'];
                            $adddate['quantity']=$v['quantity'];
                            $adddate['option_value']=$v['sku_title'];
                            $add=M('WarehouseReturn')->add($adddate);
//                            var_dump($ProductData);
//                            var_dump($adddate);
//                            var_dump($add);die;
                        }

                    }

                    //

                    $info['success'][] = sprintf('第%s行: 订单号:%s', $count++, $order);
                } else {
                    $info['error'][] = sprintf('第%s行: 订单号:%s 不存在或者状态不对', $count++, $order);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 1, '更新退货入库状态', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /**
     * 恢复匹配错误的订单
     */
    public function return_error_order() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'return_error_order', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $track_number = $row[0];
                if ($track_number) {
                    $select_order = M('Forward')->where(array('track_number'=>trim($track_number),'status'=>1))->select();//获取订单信息
                    if ($select_order) {
                        D('Common/Forward')->where(array('track_number'=>trim($track_number)))->save(array('status'=>0));
                        $order_forward = M('OrderForward')->where(array('tracking_number'=>trim($track_number)))->find();
                        if($order_forward) {
                            D('Order/Order')->where(array('id_order'=>$order_forward['new_order_id']))->save(array('id_order_status'=>6));
                            D('Common/OrderForward')->where(array('tracking_number'=>trim($track_number)))->delete();
                            $info['success'][] = sprintf('第%s行: 运单号:%s 已恢复');
                        } else {
                            $info['error'][] = sprintf('第%s行: 运单号:%s 转寄订单列表不存在该运单号');
                        }
                    } else {
                        $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，没有找到订单', $count++, $track_number);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 清空某个仓库的库存
     */
    public function clean_warehouse_stock() {
        $warehouse_id = $_GET['wid'];
        if($warehouse_id) {
            $warehouse_name = M('Warehouse')->where(array('id_warehouse' => $warehouse_id))->find();
            if($warehouse_name) {
                $warehouse = M('WarehouseProduct')->where(array('id_warehouse' => $warehouse_id))->select();
                if ($warehouse) {
                    $data = array(
                        'quantity' => 0
                    );
                    D('Common/WarehouseProduct')->where(array('id_warehouse' => $warehouse_id))->save($data);
                    echo $warehouse_name['title'] . '库存清空完成';
                } else {
                    echo '该仓库没有产品';
                }
            } else {
                echo '不存在该仓库';
            }
        } else {
            echo '参数错误';
        }
    }

    /**
     * 清空仓库货位库存
     */
    public function clean_warehouse_position_stock() {
        $warehouse_id = $_GET['wid'];
        if($warehouse_id) {
            $warehouse_name = M('Warehouse')->where(array('id_warehouse' => $warehouse_id))->find();
            if($warehouse_name) {
                $warehouse_location = M('WarehouseGoodsAllocation')->where(array('id_warehouse'=>$warehouse_id))->select();
                if($warehouse_location) {
                    try {
                        foreach ($warehouse_location as $k => $v) {
                            $id_warehouse_location = $v['id_warehouse_allocation'];
                            $data = array(
                                'quantity' => 0
                            );
                            D('Common/WarehouseAllocationStock')->where(array('id_warehouse_allocation' => $id_warehouse_location))->save($data);
                            echo $warehouse_name['title'] . '货位：'.$v['goods_name'].'库存清空完成<br>';
                        }
                    } catch (\Exception $e){
                        echo $e->getMessage();
                    }
                } else {
                    echo '该仓库没有货位';
                }
            } else {
                echo '不存在该仓库';
            }
        } else {
            echo '参数错误';
        }
    }

    /**
     * 清空某个转寄仓库
     */
    public function clean_forward_warehouse_stock() {
        $warehouse_id = $_GET['wid'];
        if($warehouse_id) {
            $warehouse_name = M('Warehouse')->where(array('id_warehouse' => $warehouse_id,'forward'=>1))->find();
            if($warehouse_name) {
                $forward_warehouse = M('Forward')->where(array('id_warehouse' => $warehouse_id,'status'=>0))->group('id_order')->select();
                if ($forward_warehouse) {
                        foreach($forward_warehouse as $val) {
                            $id_order = $val['id_order'];
                            D('Order/Order')->where(array('id_order'=>$id_order))->save(array('id_order_status'=>16));
                            D('Common/Forward')->where(array('id_order'=>$id_order))->delete();
                        }
//                    $order_forward = M('OrderForward')->alias('of')->join('__ORDER__ o ON o.id_order=of.new_order_id','INNER')->where(array('o.id_order_status'=>array('IN',array(25,26)),'of.warehouse_id'=>$warehouse_id))->select();
//                    foreach($order_forward as $val) {
//                        $res = D('Order/Order')->where(array('id_order'=>$val['new_order_id']))->save(array('id_order_status'=>6));
//                        D('Order/Order')->where(array('id_order'=>$val['old_order_id']))->save(array('id_order_status'=>16));
//                        D('Common/Forward')->where(array('id_order' => $val['old_order_id']))->delete();
//                        if($res) {
//                            D('Order/OrderForward')->where(array('new_order_id'=>$val['new_order_id']))->delete();
//                        }
//                    }
//                    D('Common/Forward')->where(array('id_warehouse' => $warehouse_id,'status'=>0))->delete();
                    echo $warehouse_name['title'] . '库存清空完成';
                } else {
                    echo '该转寄仓库没有产品';
                }
            } else {
                echo '该仓库不是转寄仓库';
            }
        } else {
            echo '参数错误';
        }
    }

    /**
     * 更新订单发货时间
     */
    public function update_delivery_time(){
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_delivery_time', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $track_number = $row[0];
                if ($track_number) {
                    $select_order = M('OrderShipping')->where(array('track_number'=>trim($track_number)))->find();//获取订单信息
                    if ($select_order) {
                        $id_order = $select_order['id_order'];
                        $order = M('Order')->where(array('id_order'=>$id_order))->find();
                        $data = array(
                            'date_delivery'=>$_POST['time']
                        );
                        D('Order/Order')->where(array('id_order'=>$id_order))->save($data);
                        $info['success'][] = sprintf('第%s行: 运单号:%s 订单号:%s 更新发货时间成功',$count++, $track_number,$order['id_increment']);
                    } else {
                        $info['error'][] = sprintf('第%s行: 运单号:%s 没有找到订单', $count++, $track_number);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '更新订单发货时间', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 清除订单发货时间
     */
    public function clean_delivery_time() {
        $status = array(
            OrderStatus::CANCELED
        );//订单状态
        $data = array(
            'date_delivery'=>null
        );
        $res = D('Order/Order')
            ->where(array('id_order_status'=>array('IN',$status),'created_at'=>array('EGT', date('2017-6-1 00:00:00'))))
        ->save($data);
        if($res) {
            echo '清除成功';
        } else {
            echo '清除失败';
        }
    }
    /**
     * 更新配货中
     */
    public function update_order_picking(){
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        //所属仓库只能看到所属仓库的订单
        $belong_ware_id = $_SESSION['belong_ware_id'];
        $statusLabel =M("OrderStatus")->where(array('status'=>1))->getField('id_order_status,title');
        $warehouse = M('Warehouse')->getField('id_warehouse','title',true);
        $ordShip = D('Order/OrderShipping');
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'update_status', $data);
            $data = $this->getDataRow($data);
            //导入记录到文件
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $setPath = './' . C("UPLOADPATH") . 'warehouse' . "/";
            if (!is_dir($setPath)) {
                mkdir($setPath, 0777, TRUE);
            }
            $logTxt = $_POST['settle_date'] . PHP_EOL . $data;
            $getPathFile = $setPath . $user_id . '_' . date('Y_m_d_H_i_s') . '.txt';
            file_put_contents($getPathFile, $logTxt, FILE_APPEND); 
            foreach ($data as $key=> $row) {
                $num=$key+1;
                $row = trim($row);
                if (empty($row)||  !preg_match('/^[\d\w]+$/', $row)){
                    $infor['error'][] = sprintf('第%s行: 订单号：%s  没有找到订单', $num,$row);
                    continue;
                }
                    
                $selectShip = $ordShip->where(array('track_number' => $row))->field('id_order')->find();//获取运单信息
                $select_order = M('Order')->field('id_order_status,id_warehouse,id_order')->where(array('id_increment'=>$row))->find();//获取订单信息  
                if (($selectShip && $selectShip['id_order'])||($select_order&&$select_order['id_order'])) {
                    $order_id = $selectShip['id_order']?$selectShip['id_order']:$select_order['id_order'];
                    $tipStr=$selectShip['id_order']?'运单号':'订单号';
                    if($selectShip['id_order']){
                        $get_order = D("Order/Order")->field('id_order_status,id_warehouse')->where(array('id_order' => $order_id))->find();
                    }else{
                        $get_order=$select_order;
                    }
                    if(in_array($get_order['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1)) {
                        if (!in_array($get_order['id_order_status'], array(OrderStatus::UNPICKING,OrderStatus::PICKING))) {//只有为未配货,配货中的订单 才那个更新已配货
                            $show_text = $statusLabel[$get_order['id_order_status']];
                            $infor['error'][] = sprintf('第%s行: '.$tipStr.':%s 订单状态已经是' . $show_text . '了,不能更新为配货中', $num, $row);
                        } else {                            
                            D("Order/Order")->where('id_order=' . $order_id)->save(array('id_order_status' => OrderStatus::PICKED));
                            //更新到出库
                            D("Order/Orderout")->where('id_order=' . $order_id)->save(array('id_order_status' => OrderStatus::PICKED)); //配货中
//                            $id_increment = D('Order/Order')->where('id_order=' . $order_id)->getField('id_increment');
//                            $track_number = $ordShip->where('id_order=' . $order_id)->getField('track_number');
                            D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PICKED, 4, '批量导入配货中');
                            $infor['success'][] = sprintf('第%s行: %s:%s  更新状态: %s', $num, $tipStr, $row, '配货中');
                        }
                    } else {
                        $infor['error'][] = sprintf('第%s行: 更新状态失败，%s'.$tipStr.'属于%s仓库', $num,$row,$warehouse[$get_order['id_warehouse']]);
                    }
                } else {
                    $infor['error'][] = sprintf('第%s行: 没有找到订单', $num);
                }
            }            
        }        
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', count($data));        
        $this->display();
    }

    /**
     * 临时数据
     */
    public function temp_import() {
        $arr = array(
            40640=>'A14-无人机2DY',
            40618=>'A14-钢铁侠MK21',
            40616=>'A14-亮灯钢铁侠DY',
            40442=>'A14-祛斑霜DY',
            39874=>'A14-防丢器-DY',
            39846=>'A14-路由器',
            39836=>'A14-无人机广角DY',
            39750=>'A14-无人机DY',
            39684=>'A14-烟雾警报器-DY',
            39668=>'A14-绿巨人浩克Hulk可动手办',
        );
//        $res = D('Order/Order')->where(array('id_department'=>72))->save(array('id_department'=>30));
        foreach($arr as $key=>$track) {
//            $pro = M('ProductSku')->where(array('id_product_sku'=>$key))->find();
            $res = D('Common/Product')->where(array('id_product'=>$key))->save(array('inner_name'=>$track));
            if($res) {
                echo 'success<br>';
            } else {
                echo 'fail<br>';
            }
        }
    }
}
