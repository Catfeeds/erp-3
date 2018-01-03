<?php

/**
 * 恢复订单产品信息
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Product\Controller
 */

namespace Order\Controller;

use Common\Controller\AdminbaseController;

class RecoverController extends AdminbaseController {

    public function recover_data() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        if (IS_POST) {
            if (isset($_POST['start_time']) && $_POST['start_time']) {
                $createAtArray = array();
                $createAtArray[] = array('EGT', $_POST['start_time']);
                if ($_POST['end_time']) {
                    $createAtArray[] = array('LT', $_POST['end_time']);
                }
                $where[] = array('o.created_at' => $createAtArray);
            } else {
                $this->error('请选择日期');
            }

            $M = new \Think\Model();
            $sku_model = D("Product/ProductSku");
            $order_tab = M('Order')->getTableName();
            $order_item_tab = M('OrderItem')->getTableName();
            $where['_string'] = 'oi.id_order_item is NULL';
            $list = $M->table($order_tab . ' as o ')->field('o.id_order,o.id_increment,o.first_name,o.last_name,o.tel')
                    ->join('LEFT JOIN ' . $order_item_tab . ' as oi ON oi.id_order=o.id_order')
                    ->where($where)
                    ->select(); //查找出没有产品信息
            $id_orders = array_column($list, 'id_order');
            $api = D("Order/Api");
            $count = 1;
            if ($list) {
                foreach ($list as $v) {
                    try {
                        $post_result = M('TempOrderPost')->where(array('id_increment' => $v['id_increment']))->find();
                        if (!empty($post_result)) {
                            $data = json_decode($post_result['post_data'], true);
                            foreach($data['products'] as $pro_key=>$product){
                                $product           = $api->filter_post_html($product);
                                $product['attrs']  = $api->filter_post_html($product['attrs']);
                                $product_id = $product['id_product'];
                                $tempProId[]  = $product_id;
                                $temp_pro_title[] = $product['sale_title'];
                                $sku_result = $sku_model->get_sku_id($product_id,$product['attrs']);
                                $data['products'][$pro_key]['id_product_sku'] = $sku_result['id'];
                                $data['products'][$pro_key]['sku'] = $sku_result['sku'];
                                $data['products'][$pro_key]['sku_title'] = $sku_result['title'];
                                $totalQty += $product['qty'];
                                $data['price_total'] += $product['price'];
                            }
                            $api->create_item($v['id_order'], $data); //建立产品信息       
                            $item_result = M('OrderItem')->where(array('id_order' => $v['id_order']))->find();
                            if ($item_result) {
                                $info['success'][] = sprintf('第%s行: 订单号:%s，姓名:%s，电话:%s', $count++, $v['id_increment'], $v['first_name'] . $v['last_name'], $v['tel']);
                            } else {
                                $info['error'][] = sprintf('第%s行: 订单号:%s，姓名:%s，电话:%s，没有找到产品信息', $count++, $v['id_increment'], $v['first_name'] . $v['last_name'], $v['tel']);
                            }
                        } else {
                            $info['error'][] = sprintf('第%s行: 订单号:%s，临时表没有数据', $count++, $v['id_increment']);
                        }
                    } catch (\Exception $e) {
                        add_system_record(1, 4, 4, '恢复产品信息失败' . $e->getMessage());
                    }
                }
                $is_pro_count = M('OrderItem')->field('count(*) as count')->where(array('id_order' => array('IN', $id_orders)))->find();
            }
        }
        $this->assign('infor', $info);
        $this->assign('no_pro_count', count($list));
        $this->assign('pro_count', $is_pro_count['count']);
        $this->display();
    }

    public function update_recover() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        if (IS_POST) {
            if (isset($_POST['start_time']) && $_POST['start_time']) {
                $createAtArray = array();
                $createAtArray[] = array('EGT', $_POST['start_time']);
                if ($_POST['end_time']) {
                    $createAtArray[] = array('LT', $_POST['end_time']);
                }
                $where[] = array('o.created_at' => $createAtArray);
            } else {
                $this->error('请选择日期');
            }

            $M = new \Think\Model();
            $sku_model = D("Product/ProductSku");
            $order_tab = M('Order')->getTableName();
            $order_item_tab = M('OrderItem')->getTableName();
            $where['_string'] = 'oi.sku is NULL';
            $list = $M->table($order_tab . ' as o ')->field('o.id_order,o.id_increment,o.first_name,o.last_name,o.tel')
                    ->join('LEFT JOIN ' . $order_item_tab . ' as oi ON oi.id_order=o.id_order')
                    ->where($where)
                    ->select(); //查找出没有产品信息
            $id_orders = array_column($list, 'id_order');
            $api = D("Order/Api");
            $count = 1;
            if ($list) {
                foreach ($list as $v) {
                    try {
                        $post_result = M('TempOrderPost')->where(array('id_increment' => $v['id_increment']))->find();
                        if (!empty($post_result)) {
                            $data = json_decode($post_result['post_data'], true);
                            foreach($data['products'] as $pro_key=>$product){
                                $product['attrs']  = $api->filter_post_html($product['attrs']);
                                $product_id = $product['id_product'];
                                $sku_result = $sku_model->get_sku_id($product_id,$product['attrs']);
                                $data['products'][$pro_key]['id_product_sku'] = $sku_result['id'];
                                $data['products'][$pro_key]['sku'] = $sku_result['sku'];
                                $data['products'][$pro_key]['sku_title'] = $sku_result['title'];
                            }                            
                            $this->update_item($v['id_order'], $data); //建立产品信息       
                            $item_result = M('OrderItem')->where(array('id_order' => $v['id_order']))->find();
                            if ($item_result) {
                                $info['success'][] = sprintf('第%s行: 订单号:%s，姓名:%s，电话:%s', $count++, $v['id_increment'], $v['first_name'] . $v['last_name'], $v['tel']);
                            } else {
                                $info['error'][] = sprintf('第%s行: 订单号:%s，姓名:%s，电话:%s，没有找到产品信息', $count++, $v['id_increment'], $v['first_name'] . $v['last_name'], $v['tel']);
                            }
                        } else {
                            $info['error'][] = sprintf('第%s行: 订单号:%s，临时表没有数据', $count++, $v['id_increment']);
                        }
                    } catch (\Exception $e) {
                        add_system_record(1, 4, 4, '恢复产品信息失败' . $e->getMessage());
                    }
                }
                $is_pro_count = M('OrderItem')->field('count(*) as count')->where(array('id_order' => array('IN', $id_orders)))->find();
            }
        }
        $this->assign('infor', $info);
        $this->assign('no_pro_count', count($list));
        $this->assign('pro_count', $is_pro_count['count']);
        $this->display();
    }

    public function update_item($order_id,$data){
        if(isset($data['products']) && is_array($data['products'])){//插入产品表，后面做产品表跟订单表关联
            foreach($data['products'] as $product){
                $product_id = $product['id_product'];                
                D("Common/OrderItem")->where(array('id_order'=>$order_id,'id_product'=>$product_id))->save($product);
            }
        }
    }
}
