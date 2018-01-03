<?php

namespace Warehouse\Controller;
use Common\Controller\HomebaseController;
use Order\Lib\OrderStatus;
use Order\Model\UpdateStatusModel;

class UpdateController extends HomebaseController {

    //货位库存更新至sku仓库库存
    public function storage() {
        $count = M('WarehouseProduct')->where(array('id_warehouse'=> array('IN', array(1,2,7))))->count();
        echo '总共',$count,'条记录';
        for($i=0; $i<$count; $i+=10000){
            $records = M('WarehouseProduct')
                ->field('id_warehouse_product,id_warehouse,id_product_sku')
                ->where(array('id_warehouse'=> array('IN', array(1,2,7))))
                ->limit($i, 10000)->select();
            M()->startTrans();
            foreach($records as $row){
                $quantity = M('WarehouseAllocationStock')->alias('was')
                    ->field('SUM(was.quantity) as count')
                    ->join("__WAREHOUSE_GOODS_ALLOCATION__ AS wga ON wga.id_warehouse_allocation = was.id_warehouse_allocation", 'left')
                    ->where(array(
                        'id_product_sku' => $row['id_product_sku'],
                        'wga.id_warehouse' => $row['id_warehouse']
                    ))
                    ->find();
                $quantity = empty($quantity['count']) ? 0 : $quantity['count'];
                M('WarehouseProduct')
                    ->where(array(
                        'id_warehouse_product'=>$row['id_warehouse_product']
                    ))
                    ->save(array(
                        'quantity' => $quantity
                    ));
            }
            M()->commit();
            echo '更新了10000条';
        }
        echo '更新完成';
    }

    //对缺货订单检查库存并扣库存
    public function storage_check(){
        ini_set('max_execution_time', '0');
        $count = M('Order')
            ->where(array('id_order_status'=>OrderStatus::OUT_STOCK))
            ->count();
        echo 'total:',$count,";\n";

        //统计匹配成功数量和失败数量
        $success_count = 0;

        for($i=0; $i<1; $i++)
        {
            $orders = M('Order')
                ->where(array('id_order_status'=>OrderStatus::OUT_STOCK))
                ->order('id_order asc')
                ->select();
            foreach ($orders as $order)
            {
                $orderId = $order['id_order'];
                //如果缺货地区不是越南地区(id_zone为9)
                if ($order['id_zone'] != 9)
                {
                    //先进行转寄仓匹配
                    $result_one = UpdateStatusModel::match_forward_order($orderId);
                    if ($result_one['flag'])
                    {
                        UpdateStatusModel::into_forward_order($orderId,$result_one['data']);
                        $success_count++;
                        echo sprintf('匹配转寄仓成功:%s%s', $order['id_increment'], PHP_EOL);
                        continue;
                    }
                    //匹配该缺货订单
                    $res_two = UpdateStatusModel::check_stock($order['id_order']); //进行有效库存匹配
                    if ($res_two['flag'])
                    {
                        $success_count++;
                        //进行加在单处理
                        $order_info = D("Order/OrderItem")->where(array('id_order' => $orderId))->select();
                        $id_warehouse    = $res_two['id_warehouse'];
                        foreach ($order_info as $product)
                        {
                            $where = array(
                                'id_warehouse' => $id_warehouse,
                                'id_product' => $product['id_product'],
                                'id_product_sku' => $product['id_product_sku'],
                            );
                            $warehouse_product = D("Common/WarehouseProduct")->where($where)->find();
                            $qty_preout = $warehouse_product['qty_preout']+$product['quantity']; //加在单
                            $res_three =  D("Common/WarehouseProduct")->where($where)->save(array('qty_preout' => $qty_preout));
                           // var_dump("$res_three:".$res_three);
                            //var_dump("sql:".D("Common/WarehouseProduct")->getLastSql());
                        }

                        if ($res_three)
                        {
                            //更新状态成功进行加在单处理
//                            UpdateStatusModel::add_warehouse_product_preout($order['id_order']);
                            $update_data ['id_warehouse'] = $res_two['id_warehouse'];
                            $update_data ['id_order_status'] = OrderStatus::UNPICKING; //未配货
                            D("Order/Order")->where(array('id_order' => $orderId))->save($update_data);
                            D("Order/OrderRecord")->addHistory($orderId, OrderStatus::UNPICKING,4, '匹配缺货定时脚本执行，匹配有效库存成功！');
                            echo sprintf('匹配缺货定时脚本执行，匹配有效库存成功:%s%s', $order['id_increment'], PHP_EOL);
                        }
                        else
                        {
                            D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],4, '匹配缺货定时脚本执行，匹配库存成功，修改订单状态失败！');
                            echo sprintf('匹配缺货定时脚本执行，匹配库存成功，修改订单状态失败:%s%s', $order['id_increment'], PHP_EOL);
                        }
                    }
                    else
                    {
                        D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],4, '匹配缺货定时脚本执行，匹配有效库存失败！');
                    }
                }
                else
                {
                    $res_two = UpdateStatusModel::check_stock($order['id_order']); //进行有效库存匹配
                   // var_dump("$res_two".$res_two);
                    if ($res_two['flag'])
                    {
                        $success_count++;
                        //进行加在单处理
                        $order_info = D("Order/OrderItem")->where(array('id_order' => $orderId))->select();
                        $id_warehouse    = $res_two['id_warehouse'];
                        foreach ($order_info as $product)
                        {
                            $where = array(
                                'id_warehouse' => $id_warehouse,
                                'id_product' => $product['id_product'],
                                'id_product_sku' => $product['id_product_sku'],
                            );
                            $warehouse_product = D("Common/WarehouseProduct")->where($where)->find();
                            $qty_preout = $warehouse_product['qty_preout']+$product['quantity']; //加在单
                            $res_three =  D("Common/WarehouseProduct")->where($where)->save(array('qty_preout' => $qty_preout));
                          //  var_dump("$res_three:".$res_three);
                            //var_dump("sql:".D("Common/WarehouseProduct")->getLastSql());
                        }

                        if ($res_three)
                        {
                            $update_data ['id_warehouse'] = $res_two['id_warehouse'];
                            $update_data ['id_order_status'] = OrderStatus::UNPICKING; //未配货
                            D("Order/Order")->where(array('id_order' => $orderId))->save($update_data);
                            //更新状态成功进行加在单处理
//                            UpdateStatusModel::add_warehouse_product_preout($order['id_order']);

                                D("Order/OrderRecord")->addHistory($orderId, OrderStatus::UNPICKING, 4, '匹配缺货定时脚本执行，匹配有效库存成功1！');
                            echo sprintf('匹配缺货定时脚本执行，匹配有效库存成功！:%s%s', $order['id_increment'], PHP_EOL);
                        }
                        else
                        {
                            D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'], 4, '匹配缺货定时脚本执行，匹配库存成功，修改订单状态失败1！');
                            echo sprintf('匹配缺货定时脚本执行，匹配库存成功，修改订单状态失败！:%s%s', $order['id_increment'], PHP_EOL);
                        }
                    }
                    else
                    {
                        D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],4, '匹配缺货定时脚本执行，匹配有效库存失败1！');
                    }
                }
            }
        }
        echo sprintf('跳单成功数！:%s%s', $success_count, PHP_EOL);
    }

    /**
     * 回滚匹配转寄错误订单
     */
    public function match_forward_back()
    {
        //获取已匹配转寄的订单
        $where = array();
        //匹配转寄中和已匹配转寄状态
        $where['o.id_order_status'] = array('IN',array(OrderStatus::MATCH_FORWARDING));
        $where['oi.id_product'] = array('NEQ',13588);
        $count = D("Order/Order")->alias('o')->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','left')->where($where)->count();
        echo 'total:',$count,";\n";
        echo "<br>";
        $modify_count = 0;
        for($i=0; $i<$count; $i+=500)
        {
            $orders = D("Order/Order")->alias('o')->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','left')->where($where)->limit($i,500)->select();
            foreach ($orders as $order_info)
            {
                $where_forward = array();
                $where_forward['oi.new_order_id'] = array('EQ',$order_info['id_order']);
                $where_forward['o.status'] = array('EQ',OrderStatus::HAS_MATCH); //已转寄匹配
                $id_order = $order_info['id_order'];
                //获取匹配到的转寄转寄仓订单
                $forward_data_info = D('Common/Forward')->alias('o')
                                ->field('o.*')
                                ->join('__ORDER_FORWARD__ oi on oi.old_order_id = o.id_order','left')
                                ->where($where_forward)
                                ->find();

                //用最新的匹配转寄方法找到转寄匹配订单
                $order_item_arr = M('OrderItem')->where(array('id_order'=>$id_order))->select();  //获取订单详情信息
                //对订单详情进行组合处理
                $order_item = array();
                foreach ($order_item_arr as $v)
                {
                    @$order_item[$v['sku']]['quantity'] = $order_item[$v['sku']]['quantity']+$v['quantity'];
                    $order_item[$v['sku']]['id_product'] = $v['id_product'];
                    $order_item[$v['sku']]['id_product_sku'] = $v['id_product_sku'];
                    $order_item[$v['sku']]['status'] = $v['status'];
                }

                $count = count($order_item);
                //判断该订单产品与转寄仓产品是否完全一致
                $order_forward_id = array();
                foreach ($order_item as $key => $val)
                {
                    $forward_where = array();
                    $forward_where['id_product'] = $val['id_product'];
                    $forward_where['id_product_sku'] = $val['id_product_sku'];
                    $forward_where['total'] = $val['quantity'];
                    $forward_where['status'] = OrderStatus::HAS_MATCH; //已匹配
                    $forward_arr = M('Forward')->where($forward_where)->order('created_at ASC')->select();//转寄仓库
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
                            $id_zone = M('Warehouse')->where(array('id_warehouse'=>$v['id_warehouse'],'status'=>1))->getField('id_zone');
                            //匹配转寄仓的仓库地区是否与订单相同
                            if ($order_info['id_zone'] == $id_zone)
                            {
                                $order_forward_id[] = $v['id_order'];
                            }
                        }
                    }
                }
                //查找id_order的重复数
                $result = array();
                foreach ($order_forward_id as $id_order)
                {
                    @$result[$id_order] = $result[$id_order] + 1;
                }
                //进行具体匹配
                $match_order_id = array();
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
                            $match_order_id[] = $id_order;
                        }
                    }
                }
                //进行数据筛选,如果订单不与相应的转寄仓订单匹配，则进行数据回删
                if (!in_array($forward_data_info['id_order'],$match_order_id))
                {
                    /** @var \Order\Model\OrderRecordModel  $order_record */
                    $order_record = D("Order/OrderRecord");
                    $modify_count++;
                    //更新相应订单状态
                    $data_update = array();
                    $data_update['id_order_status'] = OrderStatus::OUT_STOCK;
                    $data_update['id_warehouse'] = null;
                    $data_update['id_shipping'] = null;
                    D("Order/Order")->where(array('id_order' => $order_info['id_order']))->save($data_update);
                    D("Common/OrderForward")->where(array('old_order_id' => $forward_data_info['id_order']))->delete();
                    D("Common/Forward")->where(array('id_order' => $forward_data_info['id_order']))->save(array('status' => OrderStatus::UNMATCH));
                    $order_record->addHistory($order_info['id_order'], OrderStatus::OUT_STOCK, 3, '匹配错误转寄仓回滚，转寄仓订单ID:'.$forward_data_info['id_order']);
                    echo sprintf('查到的错误订单ID:%s,相应的转寄仓订单ID%s%s', $order_info['id_order'], $forward_data_info['id_order'],PHP_EOL);
                    echo "<br>";
                }
            }
        }
        echo sprintf('总共回滚转寄仓数量:%s%s', $modify_count, PHP_EOL);
    }

}
