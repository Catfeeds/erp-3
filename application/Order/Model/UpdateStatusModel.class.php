<?php
namespace Order\Model;
use Common\Model\CommonModel;
use Order\Lib\OrderStatus;
use Common\Lib\Procedure;

class UpdateStatusModel{
    /**
     * 编辑订单 发送通知
     * @param $params
     */
    public function editOrder(&$params){
        /* @var $order \Common\Model\OrderModel*/
        $order  = D("Common/Order");
        $order_data = $order->find($params['id']);
        if(in_array($order_data['status_id'],array(18,19))){
            $title = '[紧急]配货中的订单被修改了';
            $content = '
            订单号:'.$params['id'].'被修改了修息,请及时跟进
            ';
            $userId  = 1;//通知的人
            $mobile = '13888888888';
            $message = array(
                'user_id'=>$userId,
                'title'=>$title,
                'content'=>$content,
                'level'=>2
            );

            $sms = array(
                'name'=>$order_data['web_url'],
                'mobile'=>$mobile,
                'content'=>$content,
                'type'=>1,
            );
            /* @var $user \Common\Model\UsersModel*/
            $user  = D("Common/Users");
            $sendUser = $user->getRoleUser(9);
            if($sendUser){
                foreach($sendUser as $u){
                    $message['user_id'] = $u['id']?$u['id']:$userId;
                    $sms['mobile'] = $u['user_tel']?$u['user_tel']:$mobile;
                    create_message($message);
                    create_sms($sms);
                }
            }
        }

    }

    /**
     * 取消订单，发送通知
     * @param $params
     */
    public function cancel(&$params){
        $id_order = $params['id'];
        /* @var $order \Common\Model\OrderModel*/
        $order  = D("Order/Order");
        $order_data = $order->find($id_order);
        $get_wave_order = M('OrderWave')->where(array('id_order' => $id_order))->find();
        if($order_data['id_order_status'] == OrderStatus::MATCH_FORWARDING || $order_data['id_order_status'] == OrderStatus::MATCH_FORWARDED || $order_data['id_order_status'] == OrderStatus::MATCH_FINISH){
            $order_forward = M('OrderForward')->where(array('new_order_id'=>$id_order))->find();
            if($order_forward) {
                $forward = D('Common/Forward')->where(array('track_number'=>$order_forward['tracking_number']))->save(array('status'=>OrderStatus::UNMATCH));
                if($forward) {
                    D('Common/OrderForward')->where(array('new_order_id'=>$id_order))->delete();
                }
            }
        }
        if($get_wave_order) {
            D('Common/OrderWave')->where(array('id_order'=>$id_order))->delete();
            $params['comment'] = '取消订单同时清除波次单信息';
        }
        //返回的结果加上一个备注comment字段 在无效订单显示出来     liuruibin   20171012
        $result = D("Order/Order")->where(array('id_order'=>$id_order))->save(array('id_order_status'=>$params['status_id'],'comment'=>$params['comment'],'date_delivery'=>null));

        if($result){
            /** @var \Order\Model\OrderRecordModel  $order_record */
            $order_record = D("Order/OrderRecord");
            $order_record->addHistory($id_order,$params['status_id'],4, $params['comment']);

            $status_to_rollback = OrderStatus::get_canceled_to_rollback_status();//可进行回滚库存的操作订单
            //如果订单是未配货,配货中,已配货,已审核状态只进行在单处理回滚
            $status_to_rollback_qty_pre_out = array(OrderStatus::UNPICKING,OrderStatus::PICKING,OrderStatus::PICKED,OrderStatus::APPROVED);
            if (in_array($order_data['id_order_status'],$status_to_rollback_qty_pre_out))
            {
                self::qty_pre_out_rollback($order_data['id_order']);
            }
            //如果订单状态是配送中,已打包,已签收,就进行库存回滚
            if(in_array($order_data['id_order_status'],$status_to_rollback)){
                self::inventory_rollback($order_data['id_order'],true);
            }

        }

        return true;
    }

    /**
     * 审核通过订单  发送通知
     * @param $params
     */
    public static function approved($params){
        /* @var $order \Common\Model\OrderModel*/
        $order  = D("Order/Order");
        $order_data = $order->find($params['id_order']);
        $result = self::lessInventory($params['id_order'],$order_data);//订单审核通过后 订单减库存
        //        暂时屏蔽 自动变为缺货
        $OUT_STOCK = \Order\Lib\OrderStatus::OUT_STOCK;

        //香港地区DF订单减库存后状态改为已审核
        if($order_data['id_zone'] == 3 && empty($order_data['payment_method'])){
            $default_id_order_status = \Order\Lib\OrderStatus::APPROVED;
        }else{
            $default_id_order_status = \Order\Lib\OrderStatus::UNPICKING;
        }
        //某部门 自动变为缺货
        //if(in_array($order_data['id_department'],array(4,5,7))){//4,5,
        $params['id_order_status'] = $result['status']?$default_id_order_status:$OUT_STOCK;// 库存不够把订单改为缺货订单 4 未配货 :6 缺货
        //}
        $params['id_warehouse'] = $result['id_warehouse']?end($result['id_warehouse']):0;
        $order->where(array('id_order'=>$params['id_order']))->save($params);
        /** @var \Order\Model\OrderRecordModel  $order_record */
        $order_record = D("Order/OrderRecord");
        if($params['id_order_status']==$OUT_STOCK){
            $params['comment']    = $result['message']?'操作（订单审核）：'.$result['message']:'操作（订单审核）：'.'系统自动减库存,库存不足。'.$params['comment'];
            $order_record->addHistory($params['id_order'],$params['id_order_status'],4, $params['comment']);
        }
    }

    /**
     * 审核通过订单  发送通知  扣减转寄仓库存
     */
    public static function minus_stock($params) {
        $order  = D("Order/Order");
        $params['id_order_status'] = \Order\Lib\OrderStatus::MATCH_FORWARDING;;
        $order->where(array('id_order'=>$params['id_order']))->save($params);
        $order_record = D("Order/OrderRecord");
        $order_record->addHistory($params['id_order'],$params['id_order_status'],4, $params['comment']);
    }

    /**
     * 转寄仓匹配订单逻辑，错误的转寄仓匹配
     */
    public static function minus_forward_warehouse($id_order,$comment) {
        $order = M('Order')->where(array('id_order'=>$id_order))->find();
        $order_item = M('OrderItem')->where(array('id_order'=>$id_order))->select();
        $order_forward_id = array();
        $warehouse_id = array();
        $tracking_number = array();
        $new_order_id = array();
        $order_arr = array();
        foreach($order_item as $k=>$val) {
            $order_arr[] = array(
                $val['id_product_sku'] => $val['quantity']
            );
            $fwhere = array();
            $fwhere['id_product'] = $val['id_product'];
            $fwhere['id_product_sku'] = $val['id_product_sku'];
            $fwhere['total'] = $val['quantity'];
            $fwhere['status'] = 0;
            $forward = M('Forward')->where($fwhere)->order('created_at ASC')->select();//转寄仓库
            if($forward) {
                foreach($forward as $key=>$v) {
                    $id_zone = M('Warehouse')->where(array('id_warehouse'=>$v['id_warehouse'],'status'=>1))->getField('id_zone');
                    if($order['id_zone'] == $id_zone) {
                        $new_order_id = $val['id_order'];
                        $order_forward_id = $v['id_order'];
                        $warehouse_id = $v['id_warehouse'];
                        $tracking_number[] = $v['track_number'];
                        break;
                    }
                }
            }
        }

        $for_arr = array();
        if(!empty($order_forward_id)){
            $forwards = M('Forward')->where(array('id_order'=>$order_forward_id))->select();
            foreach($forwards as $k=>$v) {
                $for_arr[] = array(
                    $v['id_product_sku']=>$v['total']
                );
            }
        } else {
            $forwards = array();
        }

        if(!empty($tracking_number) && count(array_unique($tracking_number)) == 1 && count($forwards)==count($order_item) && $for_arr==$order_arr) {
            D('Common/Forward')->where(array('id_order'=>$order_forward_id))->save(array('status'=>OrderStatus::HAS_MATCH));
            $forward_order = M('OrderForward')->where(array('tracking_number'=>$tracking_number[0]))->find();
            if(!$forward_order) {
                $data = array(
                    'new_order_id' => $new_order_id,
                    'old_order_id' => $order_forward_id,
                    'warehouse_id' => $warehouse_id,
                    'tracking_number' => $tracking_number[0],
                    'created_time' => date('Y-m-d H:i:s')
                );
                D('Common/OrderForward')->add($data);
                $up_data = array(
                    'id_warehouse' => $warehouse_id,
                    'id_order'=>$new_order_id,
                    'comment'=>$comment
                );
                UpdateStatusModel::minus_stock($up_data);
            }
        }
    }

    /**
     * 查询仓库  当地区仓库没有 就 查询深圳仓库
     * @param $item_data
     * @param $order_data
     * @return array
     */
    public static function select_warehouse($item_data,$order_data,$id_warehouse=1){
        $flag = 1;
        $M = new \Think\Model;
        $ware_table = D("Warehouse/Warehouse")->getTableName();
        $ware_pro_table = D("Warehouse/WarehouseProduct")->getTableName();
        $temp_stock     = array();
        $temp_warehouse = array();
        $temp_data      = array();
        foreach ($item_data as $item) {
            $qty = $item['quantity'];
            $child_sku_id = $item['id_product_sku'];
            $product_id = $item['id_product'];
            $where_ware_pro = array(
                'wp.id_product' => $product_id,
                'wp.id_product_sku' => $child_sku_id,
                'wp.quantity' => array('EGT', $qty),
                //'w.id_zone' => $order_data['id_zone'],
                'wp.id_warehouse' => $id_warehouse,
            );
            $join_string = $ware_table.' AS w LEFT JOIN '.$ware_pro_table.' as wp  ON w.id_warehouse=wp.id_warehouse';
            $stock = $M->table($join_string)->field('wp.quantity,wp.id_warehouse_product,wp.id_warehouse')
                ->where($where_ware_pro)->order('w.priority desc')->find();
            /*
              //一个地区多个仓库扣减库存问题，所以修改为先查询地址所有仓库，再执行此方法
              //设置当个地区没有仓库时， 去查询默认仓库，去减库存  如果都没有，就变缺货
            if(!$stock && $order_data['id_zone']!=1){
                unset($where_ware_pro['w.id_zone']);
                $order_data['id_zone'] = 1;
                $where_ware_pro['wp.id_warehouse'] = 1;
                return self::select_warehouse($item_data,$order_data);
                break;
                //$stock = $M->table($join_string)->field('wp.quantity,wp.id_warehouse_product,wp.id_warehouse')->where($where_ware_pro)->order('w.priority')->find();
            }*/
            if($stock){
                $temp_warehouse[$stock['id_warehouse']] = $stock['id_warehouse'];
                $temp_ware_ids['id_warehouse'] = $stock['id_warehouse'];
                $id_warehouse_product = $stock['id_warehouse_product'];
                $final_quantity       = $stock['quantity']- $qty;
                $temp_stock[$id_warehouse_product] = $final_quantity;

                $temp_data[$id_warehouse_product] = array(
                    'id_increment' => $order_data['id_increment'],
                    'id_order'  => $item['id_order'],
                    'id_warehouse' => $stock['id_warehouse'],
                    'id_warehouse_product' => $stock['id_warehouse_product'],
                    'quantity' => $qty,
                    'final_quantity' => $final_quantity,
                    'id_product' => $product_id,
                    'id_product_sku' => $child_sku_id,
                    'sku' => $item['sku'],
                );
            }else{
                $temp_pro_sku[] = $item['product_title'].'('.$item['sku'].')';
                $flag = 0;
                if($order_data['id_zone']==1){
                    break;
                }
            }
        }
        return array('warehouse'=>$temp_warehouse,'temp_data'=>$temp_data,'flag'=>$flag);
    }

    /**
     * 查询订单 地区的仓库
     * @param $item_data
     * @param $order_data
     * @return array
     */
    public static function select_zone_warehouse($item_data,$order_data){
        /** @var \Warehouse\Model\WarehouseModel $zone_warehouse */
        $zone_warehouse = D("Warehouse/Warehouse");
        $zone_all_ware  = D("Common/Warehouse")->where(array('id_zone'=>$order_data['id_zone']))
            ->field('id_warehouse,title')->cache(true, 3600)->select();
        if($zone_all_ware){
            $zone_all_ware = array_column($zone_all_ware,'id_warehouse');
            $zone_all_ware = array_merge($zone_all_ware, array(1));
        }else{
            //如果地区没有仓库，直接执行默认仓库
            $order_data['id_zone'] = 1;
            $zone_all_ware[0] =1;
        }
        foreach($zone_all_ware as $key=>$item){
            $get_data = self::select_warehouse($item_data,$order_data,$item);
            if($get_data['flag']){
                return $get_data;
                break;
            }
        }
        return $get_data;
    }

    /**
     * 订单减库存
     * @param $order_id
     * @param bool|false $order_data //考虑不需要多次读取数据库
     * @param bool|true $check_status //是否需要验证状态， 导入缺货时候，不需要验证状态
     * @return array
     */
    public static function lessInventory($order_id,$order_data = false,$check_status=true){
        $message = '';
        add_system_record(sp_get_current_admin_id(), 4, 4, '调用了老的减库存函数');
        return array(
            'status'=>0,
            'id_warehouse'=>0,
            'message'=>'调用了老的减库存函数,不可以扣库存.'
        );
//        try {
//            //$order_data = $order_data?$order_data:D("Order/Order")->find($order_id);//批量操作，会存在减多次库存
//            $order_data = D("Order/Order")->find($order_id);
//            if(!in_array($order_data['id_order_status'],array(3,6)) && $check_status){
//                return array(
//                    'status'=>0,
//                    'id_warehouse'=>0,
//                    'message'=>'订单状态不是待审核或缺货状态，不可以扣库存.'
//                );
//            }
//            $order_item_data = D("Order/OrderItem")->where(array('id_order'=>$order_id))->select();
//            $temp_pro_sku  = array();
//            $M = new \Think\Model;
//            $ware_table = D("Warehouse/Warehouse")->getTableName();
//            $ware_pro_table = D("Warehouse/WarehouseProduct")->getTableName();
//            /* @var $user \Common\Model\UsersModel*/
//            $user  = D("Common/Users");
//            $temp_ware_ids = array();//一个订单只减同一个仓库的产品，这个逻辑没有说
//            //TODO: 减库存算法问题
//            //1. 如果一单多品并且在不同的仓库里,现在的算法会更新两个仓库, 但是返回只取最后一个仓库
//            //2. 仓库为1, 订单为3时, "'wp.quantity'=>array('GT',0)"就会把库存减为负数
//            $flag = true;
//            if ($order_item_data) {
//                $get_warehouse_data = self::select_zone_warehouse($order_item_data,$order_data);
//                $flag               = $get_warehouse_data['flag'];
//                $temp_warehouse     = $get_warehouse_data['warehouse'];
//                $temp_data          = $get_warehouse_data['temp_data'];
//                $temp_ware_ids      = $temp_warehouse;
//
//                //香港地区DF订单减库存后状态改为已审核
//                if($order_data['id_zone'] == 3 && empty($order_data['payment_method'])){
//                    $default_id_order_status = \Order\Lib\OrderStatus::APPROVED;
//                }else{
//                    $default_id_order_status = \Order\Lib\OrderStatus::UNPICKING;
//                }
//
//                if(count($temp_warehouse)>1){
//                    $flag = 0;
//                }
//                if($flag){
//                    /** @var \Order\Model\OrderRecordModel  $order_record */
//                    $order_record = D("Order/OrderRecord");
//                    $comment      = '';
//                    foreach($temp_data as $ware_pro_id=>$stock_data){
//                        $set_qty   = $stock_data['final_quantity'];
//                        $set_where = array('id_warehouse_product' => $ware_pro_id );
//                        $save_data = array('quantity'=>$set_qty);
//                        D("Common/WarehouseProduct")->where($set_where)->save($save_data);
//                        $get_warehouse_id = end($temp_warehouse);
//                        $get_sku    = isset($temp_data[$ware_pro_id]['sku'])?' '.$temp_data[$ware_pro_id]['sku'].' 扣减'.$temp_data[$ware_pro_id]['quantity']:'';
//                        $comment    .= $get_sku.', '.'  仓库ID: '.$get_warehouse_id.' 库存更新为：'.$set_qty.'。';
//                        /*$parameter  = array(
//                            'id_order' => $order_id,
//                            'id_order_status' => $default_id_order_status,
//                            'type' => 1,
//                            'user_id' => 1,
//                            'comment' => $comment,
//                        );*/
//                        //仓位减库存
//                        $result = self::less_warehouse_allocation_stock($temp_data[$ware_pro_id]);
//                        if($result['status']){
//                            $comment  .= '  '.$result['comment'];
//                        }
//                    }
//                    $parameter  = array(
//                        'id_order' => $order_id,
//                        'id_order_status' => $default_id_order_status,
//                        'type' => 1,
////                        'user_id' => 1,
//                        'comment' => $comment,
//                    );
//                    $order_record->addOrderHistory($parameter);
//                }
//
//                /*if($temp_pro_sku){
//                    $title = '订单：'.$order_id.' 产品缺货通知';
//                    $content = implode(',',$temp_pro_sku).' 缺货';
//                    $userId  = 1;//通知的人
//                    $mobile = '13788888888';
//                    $message = array(
//                        'user_id'=>$userId,
//                        'title'=>$title,
//                        'content'=>$content,
//                        'level'=>1,
//                    );
//                    $sms = array(
//                        'name'=>$order_data['web_url'],
//                        'mobile'=>$mobile,
//                        'content'=>$content,
//                        'type'=>1,
//                    );
//
//                    $sendUser = $user->getRoleUser(4);
//                    if($sendUser){
//                        foreach($sendUser as $u){
//                            $mobile = trim($u['user_tel'])?trim($u['user_tel']):$mobile;
//                            $message['user_id'] = $u['id']?$u['id']:$userId;
//                            $sms['mobile'] = $mobile;
//                            create_message($message);
//                            create_sms($sms);
//                        }
//                    }
//                    //create_sms($sms);
//                }*/
//            }else{
//                $flag = 0;
//            }
//        } catch (\Exception $e) {
//            print_r($e->getMessage());
//            $message = $e->getMessage();
//        }
//        return array('status'=>$flag,'id_warehouse'=>$temp_ware_ids,'message'=>$message);
    }

    public static function less_warehouse_allocation_stock($data){
        $status = false;
        $comment = '';
        try{
            $M = new \Think\Model;
            $ware_stock_table = D("Common/WarehouseAllocationStock")->getTableName();
            $ware_stock_loc_table = D("Common/WarehouseGoodsAllocation")->getTableName();
            $where = array(
                'was.id_product' => $data['id_product'],
                'was.id_product_sku' => $data['id_product_sku'],
                'wga.id_warehouse' => $data['id_warehouse'],
                'was.quantity' => array('GT',0),//array('EGT',$data['quantity']),//查找库存大于0的货位
            );
            $join_string = $ware_stock_table.' AS was LEFT JOIN '.$ware_stock_loc_table.' as wga  ON was.id_warehouse_allocation=wga.id_warehouse_allocation';
            $stock = $M->table($join_string)->field('was.id,was.quantity,wga.id_warehouse,wga.goods_name,wga.id_warehouse_allocation')
                ->where($where)->order('was.updated_at asc')->select();
            if($stock){
                $goods_name = array();
                $flag       = true;
                foreach($stock as $item){
                    $final_quantity = $item['quantity']-$data['quantity'];
                    //如果第一个结果 就够减库存，减完库存跳出循环
                    if($final_quantity >= 0 && $flag){
//                        D("Common/WarehouseAllocationStock")
//                            ->where(array('id'=>$item['id']))
//                            ->save(array('quantity'=>$final_quantity));
                        D("Common/WarehouseRecord")->write(
                            array(
                                'id_order' => $data['id_order'],
                                'type' => 'REDUCE',
                                'num_before' => $item['quantity'],
                                'num_after' => $final_quantity,
                                'id_warehouse' => $item['id_warehouse'],
                                'id_warehouse_allocation'=> $item['id_warehouse_allocation'],
                                'id_product_sku' => $data['id_product_sku']
                            )
                        );
                        $goods_name[] = '建议货位'.$item['goods_name'].'捡货数量'.$data['quantity'];
                        break;
                    }else{
                        //第一个库存不够 或多次减库存的时候
                        $flag = false;

                        if($final_quantity >= 0){
//                            D("Common/WarehouseAllocationStock")
//                                ->where(array('id'=>$item['id']))
//                                ->save(array('quantity'=>$final_quantity));
                            D("Common/WarehouseRecord")->write(
                                array(
                                    'id_order' => $data['id_order'],
                                    'type' => 'REDUCE',
                                    'num_before' => $item['quantity'],
                                    'num_after' => $final_quantity,
                                    'id_warehouse' => $item['id_warehouse'],
                                    'id_warehouse_allocation'=> $item['id_warehouse_allocation'],
                                    'id_product_sku' => $data['id_product_sku']
                                )
                            );
                            $goods_name[] = '建议货位'.$item['goods_name'].'捡货数量'.$data['quantity'];
                            break;
                        }else{
                            //每次减不够时， 减去当前减掉的库存
                            $data['quantity'] = $data['quantity']-$item['quantity'];
                            $goods_name[] = $item['goods_name'].'捡货数量'.$item['quantity'];
//                            D("Common/WarehouseAllocationStock")
//                                ->where(array('id'=>$item['id']))
//                                ->save(array('quantity'=>0));
                            D("Common/WarehouseRecord")->write(
                                array(
                                    'id_order' => $data['id_order'],
                                    'type' => 'REDUCE',
                                    'num_before' => $item['quantity'],
                                    'num_after' => 0,
                                    'id_warehouse' => $item['id_warehouse'],
                                    'id_warehouse_allocation'=> $item['id_warehouse_allocation'],
                                    'id_product_sku' => $data['id_product_sku']
                                )
                            );
                        }
                    }
                }
                if($goods_name){
                    $set_good_name = implode(',',$goods_name);
                    $stock_data = array(
                        'id_order' => $data['id_order'],
                        'id_product' => $data['id_product'],
                        'id_product_sku' => $data['id_product_sku'],
                        'sku' => $data['sku'],
                        'desc' => $set_good_name,
                    );
                    D("Common/OrderWaveLessstock")->data($stock_data)->add();

                    /** @var \Order\Model\OrderRecordModel  $order_record */
                    $order_record = D("Order/OrderRecord");
                    $comment = ' 货位:'.$set_good_name;
                    $status = true;
                }
            }
        }catch (\Exception $e){
            add_system_record(sp_get_current_admin_id(), 4, 4, '仓位 减库存错误：'.$e->getMessage());
        }
        return array('status'=>$status,'comment'=>$comment);
    }

    public static function less_warehouse_allocation_stock_tips($data) {
        $status = false;
        $comment = '';
        try{
            $M = new \Think\Model;
            $ware_stock_table = D("Common/WarehouseAllocationStock")->getTableName();
            $ware_stock_loc_table = D("Common/WarehouseGoodsAllocation")->getTableName();
            $where = array(
                'was.id_product' => $data['id_product'],
                'was.id_product_sku' => $data['id_product_sku'],
                'wga.id_warehouse' => $data['id_warehouse'],
                'was.quantity' => array('GT',0),//array('EGT',$data['quantity']),//查找库存大于0的货位
            );
            $join_string = $ware_stock_table.' AS was LEFT JOIN '.$ware_stock_loc_table.' as wga  ON was.id_warehouse_allocation=wga.id_warehouse_allocation';
            $stock = $M->table($join_string)->field('was.id,was.quantity,wga.id_warehouse,wga.goods_name,wga.id_warehouse_allocation')
                ->where($where)->order('was.updated_at asc')->select();
            if($stock){
                $goods_name = array();
                $flag       = true;
                foreach($stock as $item){
                    $final_quantity = $item['quantity']-$data['quantity'];
                    //如果第一个结果 就够减库存，减完库存跳出循环
                    if($final_quantity >= 0 && $flag){
                        $goods_name[] = '建议货位'.$item['goods_name'].'捡货数量'.$data['quantity'];
                        break;
                    }else{
                        //第一个库存不够 或多次减库存的时候
                        $flag = false;
                        if($final_quantity >= 0){
                            $goods_name[] = '建议货位'.$item['goods_name'].'捡货数量'.$data['quantity'];
                            break;
                        }else{
                            //每次减不够时， 减去当前减掉的库存
                            $data['quantity'] = $data['quantity']-$item['quantity'];
                            $goods_name[] = $item['goods_name'].'捡货数量'.$item['quantity'];
                        }
                    }
                }
                if($goods_name){
                    $set_good_name = implode(',',$goods_name);
                    $stock_data = array(
                        'id_order' => $data['id_order'],
                        'id_product' => $data['id_product'],
                        'id_product_sku' => $data['id_product_sku'],
                        'sku' => $data['sku'],
                        'desc' => $set_good_name,
                    );
                    D("Common/OrderWaveLessstock")->data($stock_data)->add();
                    $comment = $set_good_name;
                    $status = true;
                }
            }
        }catch (\Exception $e){
            add_system_record(sp_get_current_admin_id(), 4, 4, '仓位 减库存错误：'.$e->getMessage());
        }
        return array('status'=>$status,'comment'=>$comment);
    }

    /**
     * 在单回滚
     * @param $order_id
     * @param bool|false $order_data
     */
    public static function qty_pre_out_rollback($order_id,$order_data = false){
        $message = ' 在单回滚:';

        try{
            $order_data      = $order_data?$order_data:D("Order/Order")->find($order_id);
            $order_item_data = D("Order/OrderItem")->where(array('id_order'=>$order_id))->select();
            $id_warehouse    = $order_data['id_warehouse'];
            $product_sku_arr = array();
            foreach($order_item_data as $product){
                $product_sku_arr[] = $product['id_product_sku'];
                $where = array(
                    'id_warehouse' => $id_warehouse,
                    'id_product' => $product['id_product'],
                    'id_product_sku' => $product['id_product_sku'],
                );
                $warehouse_product = D("Common/WarehouseProduct")->where($where)->find();
                $qty_preout = $warehouse_product['qty_preout']-$product['quantity']; //取消后，减在单
                D("Common/WarehouseProduct")->where($where)->save(array('qty_preout'=> $qty_preout));
                $message .= ' SKU ID:'.$product['id_product_sku'].' QTY_PREOUT:-'.$product['quantity'];
            }
            D("Order/Order")->where(array('id_order' => $order_id))->save(array('id_shipping'=>0,'id_warehouse'=>0));
            //在单回滚后进行跳单操作
            UpdateStatusModel::get_short_order($product_sku_arr);
        }catch (\Exception $e){
            $message .=$e->getMessage();
        }
        return $message;
    }

    /**
     * 库存回滚
     * @param $order_id
     * @param bool|false $order_data
     */
    public static function inventory_rollback($order_id,$order_data = false){
        $message = ' 库存回滚:';

//        $records = M('WarehouseRecord')
//            ->where(array('id_order'=>$order_id))
//            ->select();
//
//        if(empty($records)){
//            return '未关联货位库存, 不进行回滚';
//        }

        try{
            $order_data      = $order_data?$order_data:D("Order/Order")->find($order_id);
            $order_item_data = D("Order/OrderItem")->where(array('id_order'=>$order_id))->select();
            $id_warehouse    = $order_data['id_warehouse'];
            foreach($order_item_data as $product){
                $where = array(
                    'id_warehouse' => $id_warehouse,
                    'id_product' => $product['id_product'],
                    'id_product_sku' => $product['id_product_sku'],
                );
                $warehouse_product = D("Common/WarehouseProduct")->where($where)->find();
                $quantity = $warehouse_product['quantity']+$product['quantity']; //订单取消后，加库存
                //增加库存流水
                self::add_warehouse_ftp($order_item_data['id_order'],"取消订单产品入库");
                //库存回滚之后进行匹配缺货订单操作
                self::get_short_order(array($product['id_product_sku']));
                D("Common/WarehouseProduct")->where($where)->save(array('quantity'=>$quantity));
                $message .= ' SKU ID:'.$product['id_product_sku'].' QTY:+'.$product['quantity'];
            }
//            self::allocation_stock_rollback($order_id);
        }catch (\Exception $e){
            $message .=$e->getMessage();
        }
        return $message;
    }

    /*
     * 提交审核
     */
    public static function approveds($params)
    {
        /* @var $order \Common\Model\OrderModel*/
        $order  = D("Order/Order");
        $order_data = $order->find($params['id_order']);
        $OUT_STOCK = OrderStatus::OUT_STOCK; //缺货状态
        $return  = self::check_stock($params['id_order']); //判断该订单是否缺货

        /** @var \Order\Model\OrderRecordModel  $order_record */
        $order_record = D("Order/OrderRecord");
        if ($return['status'])
        {
           return  $order_record->addHistory($params['id_order'],OrderStatus::VERIFICATION,3, $return['message']);
        }
        if ($return['match_forward'])
        {
            return;
        }
        //香港地区DF订单减库存后状态改为已审核
        if($order_data['id_zone'] == 3 && empty($order_data['payment_method']))
        {
            $default_id_order_status = OrderStatus::APPROVED; //审核通过
        }
        else
        {
            $default_id_order_status = OrderStatus::UNPICKING; //未配货
        }

        $params['id_order_status'] = $return['flag'] ? $default_id_order_status : $OUT_STOCK; // 库存不够把订单改为缺货订单 4 未配货 :6 缺货
        $params['id_warehouse'] = $return['id_warehouse'];
        $res = $order->where(array('id_order'=>$params['id_order']))->save($params);
        //订单状态为未配货或者审核通过时，且订单状态修改成功时增加在单
        if ($res && $return['flag'])
        {
            self::add_warehouse_product_preout($order_data['id_order']);
        }

        if($params['id_order_status'] == $OUT_STOCK)
        {
            $params['comment']    = $return['message']?'操作（订单审核）：'.$return['message']:'操作（订单审核）：'.'系统自动减库存,库存不足。'.$params['comment'];
            $order_record->addHistory($params['id_order'],$params['id_order_status'],3, $return['message']);
        }
        else
        {
            if ($params['id_order_status'] == OrderStatus::UNPICKING)
            {
                $order_record->addHistory($params['id_order'], $params['id_order_status'], 3, '审核通过，订单状态改为未配货'.$return['message']);
            }
            else
            {
                $order_record->addHistory($params['id_order'], $params['id_order_status'], 3, '审核通过，订单状态改为审核通过'.$return['message']);
            }
        }
    }

    /**
     * 提交审核验证有效库存,提交库存在单
     * @param $order_id
     * @return bool
     */
    public static function check_stock($order_id)
    {
        $flag = 0;
        $order_item_arr = D("Order/OrderItem")->where(array('id_order' => $order_id))->select();
        $order_data = D("Order/Order")->where(array('id_order' => $order_id))->find();//获取该订单的仓库ID
        //如果是越南订单先进行地区匹配
        if ($order_data['id_zone'] == 9) //是否越南仓发货
        {
            $province = M('Region')->where(array('LEFT(name,20)'=>array('EQ', trim($order_data['province']))))->find();
            $city = M('Region')->where(array('id_parent' => $province['id_region']))
                ->where(array('LEFT(name,20)'=>array('EQ', trim($order_data['city']))))
                ->find();
            if (empty($province) || empty($city))
            {
                return array('flag' => $flag, 'status' => 1,'id_warehouse' => 0, 'message' => '没有匹配到越南地区的发货地址');
            }
        }
        //获取产品信息进行库存的有限匹配
        $select_warehouse = self::select_warehouse_match($order_data);

        $order_item_data = array();
        foreach ($order_item_arr as $v)
        {
            @$order_item_data[$v['sku']]['quantity'] = $order_item_data[$v['sku']]['quantity']+$v['quantity'];
            $order_item_data[$v['sku']]['id_product'] = $v['id_product'];
            $order_item_data[$v['sku']]['id_product_sku'] = $v['id_product_sku'];
        }

        $count = count($order_item_data);
        foreach ($select_warehouse as $id_warehouse)
        {
            //如果取出的仓库是越南转寄仓订单，用转寄仓的方式匹配订单 仓库为18为越南转寄仓
            if ($id_warehouse == 18)
            {
                $res_one = UpdateStatusModel::match_forward_order($order_id);
                if ($res_one['flag'])
                {
                    UpdateStatusModel::into_forward_order($order_id,$res_one['data']);
                    return array('match_forward' =>1);
                }
            }
            $i = 0;

            foreach ($order_item_data as  $val)
            {
                $where = array();
                $where['id_warehouse'] = array('EQ',$id_warehouse); //仓库ID
                $where['id_product'] = array('EQ',$val['id_product']); //产品ID
                $where['id_product_sku'] = array('EQ',$val['id_product_sku']); //sku
                $where['quantity'] = array('GT',0); //库存大于0
                //获取该产品的库存信息
                $warehouse_product_data = D("Order/WarehouseProduct")->where($where)->find();
                if (!$warehouse_product_data)
                {
                    break;   //该种产品缺货那就直接跳出循环，进入下一仓库查询;
                }
                //有限库存数量
                $qty_valid = $warehouse_product_data['quantity']-$warehouse_product_data['qty_preout']; //有限库存 = 库存 - 在单

                if ( $qty_valid < $val['quantity'])   //有效库存不足
                {
                    break;   //该种产品缺货那就直接跳出循环，进入下一仓库查询
                }
                $i++;
                if ($count == $i)   //当该订单的所有产品都满足有效库存时，选择该仓库，否则生成缺货
                {
                    //var_dump("$qty_valid:".$qty_valid); var_dump("quantity:".$val['quantity']); var_dump("$order_id:".$order_id);
//                    $array = array(
//                        'id_order'=>$val['id_order'],
//                        'id_warehouse'=>$id_warehouse,
//                        'id_product'=>$val['id_product'],
//                        'id_product_sku'=>$val['id_product_sku'],
//                        'sku'=>$val['sku'],
//                        'quantity'=>$val['quantity']
//                    );
//                    //仓位减库存
//                    $result = self::less_warehouse_allocation_stock_tips($array);
//                    if($result['status']){
//                        $comment  = '  '.$result['comment'];
//                    }
                    return  array('flag' => 1, 'id_warehouse' => $id_warehouse);
                }
            }
        }

        return  array('flag' => $flag, 'id_warehouse' => 0 ,'message' => '订单缺货');
    }

    /**
     * 订单审核时增加库存在单;
     * @param $id_order
     */
    public static function add_warehouse_product_preout($id_order)
    {
        $procedure_name = 'ERP_BILLINOUT_SUBMIT'; //审核通过调用增加在单的存储过程
        $array['billid'] = $id_order;
        $array['userid'] = $_SESSION['ADMIN_ID'];
        $array['tablename'] = 'erp_order';
        $array['inor'] = 'O';   //订单出库
        return Procedure::call($procedure_name,$array);
    }

    /**
     * 根据订单号选择配送的仓库，返回该地区所有的仓库
     * @param $order_data
     * @return $zone_all_ware
     */
    public static function select_warehouse_match($order_data)
    {
        $zone_all_ware  = D("Common/Warehouse")->where(array('id_zone'=>$order_data['id_zone']))
            ->field('id_warehouse,title')->order('priority asc')->select();
        if($zone_all_ware)
        {
            $zone_all_warehouse = array_column($zone_all_ware,'id_warehouse');
            $zone_all_ware = array_merge($zone_all_warehouse, array(1));
        }
        else
        {
            //如果地区没有仓库，直接执行默认仓库
            $zone_all_ware[0] =1;
        }
        return $zone_all_ware;
    }

    /**
     * 修改订单状态为配货中时，插入销售出库订单和销售订单出库表，存储过程
     * @param $id_order
     */
    public static function add_order_out_all($id_order)
    {
        $order_item_data = D("Order/OrderItem")->where(array('id_order' => $id_order))->select();
        $order_data = D("Order/Order")->where(array('id_order' => $id_order))->find();
        $id_orderout =  D("Order/Orderout")->add($order_data);
        D("Order/Orderout")->where(array('id_orderout' => $id_orderout))->save(array('date_delivery' => date('Y-m-d H:i:s'))); //更新发货时间为当前时间
        foreach ( $order_item_data as $item)
        {
            $item_order = self::array_remove($item,'id_order_outitem');
            $item_order = self::array_remove($item_order,'id_order');
            $item_order['id_orderout'] = $id_orderout;
            $item_order['qtyout'] = $item_order['quantity'];  //默认出库数量为购买数量
            D("Order/OrderOutitem")->add($item_order);
        }
        $result = self::get_order_out_procedure($id_orderout,$_SESSION['ADMIN_ID']); //调用存储过程
        if(!empty($result)){
            $result = $result[$id_orderout];
        }
        return $result;
    }

    /**
     * 调用订单状态为配货中的存储过程
     * @param $id_order
     * @param $userId
     */
    public static function get_order_out_procedure($id_order,$userId)
    {
        $procedure_name = 'ERP_INOUT_SUBMIT'; //订单状态改为配货中调用存储过程
        $array['billid'] = $id_order;
        $array['userid'] = $userId;
        $array['tablename'] = 'erp_orderout';
        $array['inor'] = 'O';   //订单出库
        return Procedure::call($procedure_name,$array);
    }

    /**
     * 去掉数组中指定键值
     * @param $data
     * @param $key
     * @return mixed
     */
    public static function array_remove($data, $key){
        if(!array_key_exists($key, $data)){
            return $data;
        }
        $keys = array_keys($data);
        $index = array_search($key, $keys);
        if($index !== FALSE){
            array_splice($data, $index, 1);
        }
        return $data;
    }

    /**
     * 货位库存回滚
     */
    public static function allocation_stock_rollback($order_id){
        //查询最后一条扣库存操作记录
        M()->startTrans();
        try{
            $records = M('WarehouseRecord')
                ->where(array('id_order'=>$order_id))
                ->select();
            foreach($records as $record) {
                $num_before = M('WarehouseAllocationStock')
                    ->where(array(
                        'id_warehouse_allocation' => $record['id_warehouse_allocation'],
                        'id_product_sku' => $record['id_product_sku'],
                    ))->getField('quantity');

                if (!is_null($num_before)) {
                    //回滚货位库存
                    $qty_rollback = abs($record['num']);
                    M('WarehouseAllocationStock')
                        ->where(array(
                            'id_warehouse_allocation' => $record['id_warehouse_allocation'],
                            'id_product_sku' => $record['id_product_sku'],
                        ))
                        ->setInc('quantity', $qty_rollback);
                    //写入回滚操作记录
                    D('Common/WarehouseRecord')
                        ->write(array(
                            'type' => 'ROLLBACK',
                            'num' => $qty_rollback,
                            'num_before' => $num_before,
                            'id_warehouse' => $record['id_warehouse'],
                            'id_warehouse_allocation' => $record['id_warehouse_allocation'],
                            'id_product_sku' => $record['id_product_sku'],
                        ));

                    //将扣记录与订单号解除绑定，避免再次回滚
                    M('WarehouseRecord')
                        ->where(array('id_warehouse_record' => $record['id_warehouse_record']))
                        ->setField('id_order', '');
                }
            }
        }catch (\Exception $e){
            M()->rollback();
        }
        M()->commit();
    }

    /**
     * 根据仓库信息和产品信息获取缺货订单
     * @param $id_product_sku_arr
     * @return mixed
     */
    public static function  get_short_order($id_product_sku_arr,$id_purchasein = 0)
    {
        //获取该sku产品的缺货订单
        $model = new \Think\Model;
        $order_table_name = D('Order/Order')->getTableName();
        $order_item_table_name = D('Order/OrderItem')->getTableName();
        $id_product_sku_arr = implode(',',$id_product_sku_arr);
        $where = 'oi.id_product_sku in ('.$id_product_sku_arr.') and o.id_order_status=6';
        $order_data_arr = $model->table($order_table_name . ' as o LEFT JOIN ' . $order_item_table_name . ' as oi ON o.id_order=oi.id_order')
            ->field('oi.id_order,o.id_zone,o.id_department,o.id_order_status,o.payment_method')
            ->where($where)
            ->order('o.id_zone = 3 desc, o.id_zone = 2 desc, o.id_zone asc, o.created_at asc')
            ->select();
		
        if($order_data_arr)
        {
            $order_data_new = array();
            //取出的重复订单进行去重处理
            foreach ($order_data_arr as $val)
            {
                $order_data_new[$val['id_order']]['id_order'] = $val['id_order'];
                $order_data_new[$val['id_order']]['id_zone'] = $val['id_zone'];
                $order_data_new[$val['id_order']]['id_department'] = $val['id_department'];
                $order_data_new[$val['id_order']]['id_order_status'] = $val['id_order_status'];
                $order_data_new[$val['id_order']]['payment_method'] = $val['payment_method'];
            }


		

            //缺货订单进行有效库存校验
            foreach ($order_data_new as $order_data)
            {
                //如果不是越南地区，先进行匹配转寄仓操作，id_zone 为9 为越南地区
                if ($order_data['id_zone'] != 9)
                {
                    $res_one = self::match_forward_order($order_data['id_order']);
                    if ($res_one['flag'])
                    {
                        self::into_forward_order($order_data['id_order'],$res_one['data']);
                    }
                    else
                    {
                        $return = self::check_stock($order_data['id_order']);

                        //匹配缺货成功,进行加在单处理
                        if ($return['flag'])
                        {
							//增加匹配订单记录,jiangqinqing 20171110
							$order_recode_data_arr[]['id_order'] = $order_data['id_order'];

                            //香港地区DF订单校验有效库存后状态改为已审核
                            if($order_data['id_zone'] == 3 && empty($order_data['payment_method']))
                            {
                                $default_id_order_status = OrderStatus::APPROVED; //审核通过
                            }
                            else
                            {
                                $default_id_order_status = OrderStatus::UNPICKING; //未配货
                            }

                            $update['id_order_status'] = $return['flag'] ? $default_id_order_status : OrderStatus::OUT_STOCK; // 库存不够把订单改为缺货订单 4 未配货 :6 缺货
                            $update['id_warehouse'] = $return['id_warehouse'];
                            $res = D("Order/Order")->where(array('id_order'=>$order_data['id_order']))->save($update); //匹配缺货订单后，修改订单状态为未配货或审核通过
                            //订单状态为未配货或者审核通过时，且订单状态修改成功时增加在单
                            if ($res && $return['flag'])
                            {
                                self::add_warehouse_product_preout($order_data['id_order']);
                            }
                            else
                            {
                                continue;
                            }
                            /** @var \Order\Model\OrderRecordModel  $order_record */
                            $order_record = D("Order/OrderRecord");
                            if($update['id_order_status'] != OrderStatus::OUT_STOCK)
                            {
                                if($update['id_order_status'] == OrderStatus::UNPICKING)
                                {
                                    //添加操作记录缺货状态更改为未配货
                                    $order_record->addHistory($order_data['id_order'], $update['id_order_status'], 3, '匹配缺货成功，订单状态改为未配货'.$return['message']);
                                }
                                else
                                {
                                    //添加操作记录缺货状态更改为已审核
                                    $order_record->addHistory($order_data['id_order'], $update['id_order_status'], 3, '匹配缺货成功，订单状态改为已审核'.$return['message']);
                                }
                            }
                        }
                    }
                }
                else
                {
                    $return = self::check_stock($order_data['id_order']);

                    //匹配缺货成功,进行加在单处理
                    if ($return['flag'])
                    {
						//增加匹配订单记录,jiangqinqing 20171110
						$order_recode_data_arr[]['id_order'] = $order_data['id_order'];
                        //香港地区DF订单校验有效库存后状态改为已审核
                        if($order_data['id_zone'] == 3 && empty($order_data['payment_method']))
                        {
                            $default_id_order_status = OrderStatus::APPROVED; //审核通过
                        }
                        else
                        {
                            $default_id_order_status = OrderStatus::UNPICKING; //未配货
                        }

                        $update['id_order_status'] = $return['flag'] ? $default_id_order_status : OrderStatus::OUT_STOCK; // 库存不够把订单改为缺货订单 4 未配货 :6 缺货
                        $update['id_warehouse'] = $return['id_warehouse'];
                        $res = D("Order/Order")->where(array('id_order'=>$order_data['id_order']))->save($update); //匹配缺货订单后，修改订单状态为未配货或审核通过
                        //订单状态为未配货或者审核通过时，且订单状态修改成功时增加在单
                        if ($res && $return['flag'])
                        {
                            self::add_warehouse_product_preout($order_data['id_order']);
                        }
                        else
                        {
                            continue;
                        }
                        /** @var \Order\Model\OrderRecordModel  $order_record */
                        $order_record = D("Order/OrderRecord");
                        if($update['id_order_status'] != OrderStatus::OUT_STOCK)
                        {
                            if($update['id_order_status'] == OrderStatus::UNPICKING)
                            {
                                //添加操作记录缺货状态更改为未配货
                                $order_record->addHistory($order_data['id_order'], $update['id_order_status'], 3, '匹配缺货成功，订单状态改为未配货'.$return['message']);
                            }
                            else
                            {
                                //添加操作记录缺货状态更改为已审核
                                $order_record->addHistory($order_data['id_order'], $update['id_order_status'], 3, '匹配缺货成功，订单状态改为已审核'.$return['message']);
                            }
                        }
                    }
                }
            }

			//增加采购单入库关联的订单记录  jiangqinqing 20171105
			$record_data['id_purchasein']	= $id_purchasein;
			$record_data['create_time']		=	time();
			$record_data['order_data']		=	json_encode($order_recode_data_arr);
			M()->table("erp_purchase_order_record")->add($record_data);
        }
        else
        {
            return $return = array('status' => 0, 'msg' => '该产品无相应退货订单！');
        }
    }

    /**
     * 波次单删除，如果是扣过库存的要进行库存回滚加在单
     * @param $id_order
     * @return $mix
     */
    public static function wave_delete_rollback_stock($id_order)
    {
        $message = ' 库存回滚:';

        try{
            $order_data      = D("Order/Order")->find($id_order);
            $order_item_data = D("Order/OrderItem")->where(array('id_order'=>$id_order))->select();
            $id_warehouse    = $order_data['id_warehouse'];
            if ( $order_data && $order_item_data && $id_warehouse)
            {
                foreach($order_item_data as $product)
                {
                    $where = array(
                        'id_warehouse' => $id_warehouse,
                        'id_product' => $product['id_product'],
                        'id_product_sku' => $product['id_product_sku'],
                    );
                    $warehouse_product = D("Common/WarehouseProduct")->where($where)->find();
                    $quantity = $warehouse_product['quantity']+$product['quantity']; //已减库存删除波次单后，加库存
                    $qty_preout = $warehouse_product['qty_preout']+$product['quantity'];  //已减库存删除波次单后，加在单
                    D("Common/WarehouseProduct")->where($where)->save(array('quantity'=>$quantity, 'qty_preout' => $qty_preout));
                    $message .= ' SKU ID:'.$product['id_product_sku'].' QTY:+'.$product['quantity'];
                }
                //增加流水
                self::add_warehouse_ftp($id_order,"订单状态调整入库");
            }
        }catch (\Exception $e){
            $message .=$e->getMessage();
        }
        return $message;
    }

    /**
     * 根据订单信息增加库存流水
     * @param $id_order
     * @param $bill_type
     * @return $mix
     */
    public static function add_warehouse_ftp($id_order,$bill_type)
    {
        $order_data      = D("Order/Order")->find($id_order);
        $order_item_data = D("Order/OrderItem")->where(array('id_order'=>$id_order))->select();
        $id_warehouse    = $order_data['id_warehouse'];
        if ( $order_data && $order_item_data && $id_warehouse)
        {
            foreach($order_item_data as $product)
            {
                $warehouse_ftp_add = array();
                $warehouse_ftp_add['id_warehose'] = $id_warehouse;
                $warehouse_ftp_add['id_product'] = $product['id_product'];
                $warehouse_ftp_add['id_product_sku'] = $product['id_product_sku'];
                $warehouse_ftp_add['docno'] = $id_order;
                $warehouse_ftp_add['billtype'] = $bill_type; //单据类型
                $warehouse_ftp_add['id_users'] = $_SESSION['ADMIN_ID'];
                $warehouse_ftp_add['billdate'] = date('Y-m-d H:i:s'); //入库时间
                $warehouse_ftp_add['qtychange'] = $product['quantity'];  //改变数量
                $warehouse_ftp_add['amtchange'] = 0;    //改变金额
                $warehouse_ftp_add['qty_alloc'] = 0;  //上架数量
                D("Common/StorageFtp")->data($warehouse_ftp_add)->add();
            }
        }
    }

    /**
     * 根据订单信息检测库存是否存在
     * @param $order_id
     * @return $mix
     */
    public static function check_stock_right($order_id)
    {
        $flag = 0;
        $order_item_data = D("Order/OrderItem")->where(array('id_order' => $order_id))->select();
        $order_data = D("Order/Order")->where(array('id_order' => $order_id))->find();//获取该订单的仓库ID
        //获取产品信息进行库存的有限匹配
        $count = count($order_item_data);
        $i = 0;
        $short_sku = '';
        foreach ($order_item_data as $key => $val)
        {
            $where = array();
            $where['id_warehouse'] = $order_data['id_warehouse']; //仓库ID
            $where['id_product'] = $val['id_product']; //产品ID
            $where['id_product_sku'] = $val['id_product_sku']; //sku
            //获取sku信息
            $sku_data = D("Common/ProductSku")->where(array('id_product_sku' => $val['id_product_sku']))->find();
            //获取该产品的库存信息
            $warehouse_product_data = D("Order/WarehouseProduct")->where($where)->find();
            //进行库存校验
            if ($warehouse_product_data['quantity'] >0 && $warehouse_product_data['quantity'] >= $val['quantity'])
            {
                $i++;
                if ($i >= $count)
                {
                    return  array('flag' => 1, 'message' => '该订单库存存在');die;
                }
                break;
            }
            else
            {
                if ($sku_data['sku'])
                {
                    $short_sku .= $sku_data['sku'] .'--';
                }
            }
        }
        return  array('flag' => $flag, 'data' => $short_sku, 'message' => '该订单库存不存在，请添加库存！');die;
    }

    /**
     * 匹配转寄仓
     * @param $id_order
     * @param $mix
     */
    public static function match_forward_order($id_order)
    {
        $flag = 0;
        $order = M('Order')->where(array('id_order'=>$id_order))->find(); //获取订单信息
        $order_item_arr = M('OrderItem')->where(array('id_order'=>$id_order))->select();  //获取订单详情信息
        //对订单详情进行组合处理
        $order_item = array();
        foreach ($order_item_arr as $v)
        {
            @$order_item[$v['sku']]['quantity'] = $order_item[$v['sku']]['quantity']+$v['quantity'];
            $order_item[$v['sku']]['id_product'] = $v['id_product'];
            $order_item[$v['sku']]['id_product_sku'] = $v['id_product_sku'];
        }
        //判断越南发货则判断对应省市
        if ($order['id_zone'] == 9) //是否越南仓发货
        {
            $province = M('Region')->where(array('LEFT(name,20)'=>array('EQ', trim($order['province']))))->find();
            $city = M('Region')->where(array('id_parent' => $province['id_region']))
                ->where(array('LEFT(name,20)'=>array('EQ', trim($order['city']))))
                ->find();
            if (empty($province) || empty($city))
            {
                return array('flag' => $flag, 'mes' => '越南订单，未找到相应的省市！');
                die;
            }
        }
        $count = count($order_item);
        //判断该订单产品与转寄仓产品是否完全一致
        $order_forward_id = array();
        foreach ($order_item as $key => $val)
        {
            $forward_where = array();
            $forward_where['o.id_product'] = $val['id_product'];
            $forward_where['o.id_product_sku'] = $val['id_product_sku'];
            $forward_where['o.total'] = $val['quantity'];
            $forward_where['o.status'] = 0; //未匹配
            $forward_where['w.id_zone'] = $order['id_zone'];

            $forward_arr = M('Forward')->alias('o')
                            ->field('o.*')
                            ->join('__ORDER__ oi on o.id_order = oi.id_order','left')
                            ->join('__WAREHOUSE__ w on o.id_warehouse = w.id_warehouse','left')
                            ->where($forward_where)
                            ->select();//转寄仓库
            //加个判断，针对订单退回的地区不是本地区的仓库

            if ($forward_arr)
            {
                $forward = array();
                foreach ($forward_arr as $v)
                {
                    @$forward[$v['sku']]['total'] = $forward[$v['sku']]['total']+$v['total'];
                    $forward[$v['sku']]['id_warehouse'] = $v['id_warehouse'];
                    $forward[$v['sku']]['id_order'] = $v['id_order'];
                }
                foreach($forward as $v)
                {
                    $order_forward_id[] = $v['id_order'];
                }
            }
            else
            {
                return array('flag' => $flag, 'mes' => '未在转寄仓中找到订单！');
                die;
            }
        }
        //查找id_order的重复数
        $result = array();
        foreach ($order_forward_id as $id_order)
        {
            @$result[$id_order] = $result[$id_order] + 1;
        }
        //进行具体匹配
        foreach ( $result as $id_order => $val)
        {
            if ( $val == $count )
            {
                $forward_count = M('Forward')->where(array('id_order' => $id_order ))->select();//转寄仓库
                $forward_data = array();
                foreach ($forward_count as $v)
                {
                    @$forward_data[$v['sku']]['total'] = $forward_data[$v['sku']]['total']+$v['total'];
                }
                //匹配转寄仓订单产品数与所配订单是否一样
                if ( count($forward_data) == $count)
                {
                    return array('flag' => 1, 'data' => $id_order); // 返回id_order
                    die;
                }
            }
        }
        return array('flag' => $flag, 'mes' => '未在转寄仓中找到订单！');
        die;
    }

    /**
     * 匹配到转寄仓的订单进行关联数据保存
     * @param $new_order_id
     * @param $old_order_id
     */
    public static function into_forward_order($new_order_id,$old_order_id)
    {
        //转寄仓订单更新为已配货
        D('Common/Forward')->where(array('id_order'=>$old_order_id))->save(array('status'=>OrderStatus::HAS_MATCH));
        $forward_data = D('Common/Forward')->where(array('id_order' => $old_order_id))->find();
        if ($forward_data)
        {
            $data = array(
                'new_order_id' => $new_order_id,
                'old_order_id' => $old_order_id,
                'warehouse_id' => $forward_data['id_warehouse'],
                'tracking_number' => $forward_data['track_number'],
                'created_time' => date('Y-m-d H:i:s')
            );
            D('Common/OrderForward')->add($data);
            $up_data = array(
                'id_warehouse' => $forward_data['id_warehouse'],
                'id_order'=>$new_order_id,
                'comment'=>'操作（订单审核）：'.'匹配到转寄仓订单，扣减转寄仓库存数量'
            );
            UpdateStatusModel::minus_stock($up_data);
        }

    }

    /**
     * 对部门进行排序
     */
    public static function sort_department($id_department_arr = array())
    {
        //获取业务部门信息
        $department_arr = M('Department')->where(array('type'=>1))->order('department_num ASC')->cache(true, 86400)->select();
        $department_title = array();
        foreach ($department_arr as $department)
        {
            $str_arr = str_split($department['title']);
            $department_index = intval($str_arr[0].$str_arr[4].$str_arr[8]); //部门信息匹配逻辑
            $department_title[$department_index]['title'] = $department['title'];
            $department_title[$department_index]['id_department'] = $department['id_department'];
        }
        $index_arr = array_keys($department_title);
        sort($index_arr);
        $department_title_new = array();
        $department_title_match = array();
        foreach ($index_arr as $index)
        {
            $department_title_new[$index] = $department_title[$index];
        }
        //进行部门筛选
        if ($id_department_arr)
        {
            foreach ($index_arr as $index)
            {
                $index_arr = str_split($index);
                if (in_array($index_arr[0],$id_department_arr))
                {
                    $department_title_match[$index] = $department_title[$index];
                }
            }
            return $department_title_match;
        }
        return $department_title_new;
    }

    /**
     * 获取订单的货币信息
     */
    public static function get_currency()
    {
        $currency_data = D('currency')->order("id_currency desc")->cache(true, 86400)->select(); //获取货币信息
        $currency_data_new = array();
        foreach ($currency_data as $currency)
        {
            $currency_data_new[$currency['code']]['currency_code'] = $currency['symbol_left']? $currency['symbol_left']: $currency['symbol_right'];
            $currency_data_new[$currency['code']]['left'] = $currency['symbol_left']? 1: 0;
        }
        return $currency_data_new;
    }

    /**
     * 加库存加流水处理
     * @param $id_order
     */
    public static function order_return($id_order)
    {
        $order_data      = D("Order/Order")->find($id_order);
        $order_item_data = D("Order/OrderItem")->where(array('id_order'=>$id_order))->select();
        $id_warehouse    = $order_data['id_warehouse'];
        if ( $order_data && $order_item_data && $id_warehouse)
        {
            foreach($order_item_data as $product)
            {
                $where = array(
                    'id_warehouse' => $id_warehouse,
                    'id_product' => $product['id_product'],
                    'id_product_sku' => $product['id_product_sku'],
                );
                $warehouse_product = D("Common/WarehouseProduct")->where($where)->find();
                $quantity = $warehouse_product['quantity']+$product['quantity']; //加库存
                D("Common/WarehouseProduct")->where($where)->save(array('quantity'=>$quantity));
            }
            //增加流水
            self::add_warehouse_ftp($id_order,"订单状态调整入库");
        }
    }

}
