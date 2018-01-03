<?php

namespace Order\Controller;

use Common\Controller\AdminbaseController;

class ImportController extends AdminbaseController {

    protected $order, $order_shipping;

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->order_shipping = D('Order/OrderShipping');
    }

    /*
     * 导出指定部门的所有订单数据
     * eg：http://hepxi.com/order/import/import_all_order/id/部门Id
     */

    public function import_all_order() {
        header("Content-type: text/html; charset=utf-8");
        if (isset($_GET['id']) && $_GET['id']) {
            $id_department = $_GET['id'];
            $department = M('Department')->where(array('id_department' => $id_department, 'type' => 1))->cache(true, 3600)->find();
            if ($department) {
                set_time_limit(0);
                ini_set('memory_limit', '-1');

                vendor("PHPExcel.PHPExcel");
                vendor("PHPExcel.PHPExcel.IOFactory");
                vendor("PHPExcel.PHPExcel.Style.NumberFormat");
                $excel = new \PHPExcel();

                $where = array();
                $where['o.id_department'] = array('EQ', $id_department);
                /* @var $ordModel \Common\Model\OrderModel */
                $ordModel = D("Order/Order");
                /* @var $orderItem \Common\Model\OrderItemModel */
                $orderItem = D('Order/OrderItem');

                $field = 'o.*,oi.id_product,oi.id_product_sku,oi.sku,oi.sku_title,oi.sale_title,oi.quantity'; //,oi.product_title
//                $field .= ',os.shipping_name as shipping_name,os.track_number,p.inner_name as product_title';
                $order_list = $ordModel->alias('o')->field($field)
                        ->join($orderItem->getTableName() . ' AS oi ON (o.id_order = oi.id_order)')
                        ->where($where)->order('oi.id_product desc,oi.id_product_sku desc')
//                                ->limit(5000)
                        ->select();

                $columns = array(
                    '域名', '订单号', '姓名', '电话', '邮箱', '产品名称', '属性', '下单时间',
                );
                $j = 65;
                foreach ($columns as $col) {
                    $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
                    ++$j;
                }
                if ($order_list) {
                    $all_domain = D('Common/Domain')->field('`name`,id_domain')->order('`name` ASC')->cache(true, 3600)->select();
                    $all_domain = $all_domain ? array_column($all_domain, 'name', 'id_domain') : '';
                    /** @var \Order\Model\OrderStatusModel $status_model */
                    $idx = 2;
                    foreach ($order_list as $o) {
                        $product_name = '';
                        $attrs = '';
                        $products = D('Order/OrderItem')->get_item_list($o['id_order']);

                        foreach ($products as $p) {
                            $product_name .= $p['product_title'] . "  ";
                            if (!empty($p['sku_title'])) {
                                $attrs .= ',' . $p['sku_title'] . ' x ' . $p['quantity'] . "  ";
                            } else {
                                $attrs .= ',' . $p['product_title'] . ' x ' . $p['quantity'] . "  ";
                            }
                        }
                        $attrs = trim($attrs, ',');
                        $user_name = $o['first_name'] . ' ' . $o['last_name'];

                        $data = array(
                            $all_domain[$o['id_domain']], $o['id_increment'], $user_name, $o['tel'],
                            $o['email'], $product_name, $attrs, $o['created_at']
                        );
                        $j = 65;
                        foreach ($data as $col) {
                            $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                            ++$j;
                        }
                        ++$idx;
                    }
                } else {
                    echo '没有数据';
                    die;
                }
            } else {
                echo '不是业务部门';
                die;
            }
        } else {
            echo '请填写部门ID';
            die;
        }

        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '业务' . $id_department . '组订单信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '业务' . $id_department . '组订单信息表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    public function import_order() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        if (IS_POST) {
            $file = $_FILES['files']['tmp_name'];
            if (!empty($file)) {
                $data = $this->import_excel($file);
                $count = 1;
                $array_data = array();
                foreach ($data as $key => $val) {
                    try {
                        $domain = M('Domain')->field('id_domain,id_department')->where(array('name' => array('like', '%' . $val[0] . '%')))->find(); //域名id
                        if ($domain) {
                            $product = M('Product')->field('id_product')->where(array('title' => $val[6]))->find(); //获取产品id
                            $order_result = M('Order')->field('id_warehouse,id_users,id_zone,province,currency_code')->where(array('id_domain' => $domain['id_domain']))->order('created_at desc')->find();
                            $product_flag = false;
                            if ($product) {
                                $product_flag = true;
                                $col_flag = false;
                                $cod_flag = false;

                                if (isset($val[8])) {//颜色
                                    $col_flag = true;
                                    $pro_option_col = M('ProductOptionValue')->field('id_product_option_value')->where(array('id_product' => $product['id_product'], 'title' => $val[8]))->find();
                                }
                                if (isset($val[9])) {//尺寸
                                    $cod_flag = true;
                                    $pro_option_cod = M('ProductOptionValue')->field('id_product_option_value')->where(array('id_product' => $product['id_product'], 'title' => $val[9]))->find();
                                }

                                if ($col_flag || $cod_flag) {
                                    if ($col_flag) {
                                        $option_color = $pro_option_col['id_product_option_value'];
                                    } else {
                                        $option_color = '';
                                    }
                                    if ($cod_flag) {
                                        $option_code = $pro_option_cod['id_product_option_value'];
                                    } else {
                                        $option_code = '';
                                    }
                                    $arr = array($option_color, $option_code);
                                    asort($arr);
                                    $arrs = serialize($arr);
                                    $attr = implode(',', $arr);
                                    $where['option_value'] = array('EQ', trim($attr, ','));
                                } else {
                                    $where['option_value'] = array('EQ', 0);
                                }
                                $where['id_product'] = array('EQ', $product['id_product']);
                                $pro_sku = M('ProductSku')->field('id_product_sku,sku,title')->where($where)->find();
                            } else {
                                $array_data[] = $val;
                                $info['error'][] = sprintf('第%s行: 产品名:%s，没有找到该产品', $count++, $val[6]);
                            }
                            if ($val[18]) {
                                $order_status = M('OrderStatus')->field('id_order_status')->where(array('title' => $val[18]))->find(); //订单状态
                            }
                            if ($val[3]) {
                                if (substr(intval($val[3]), 0, 1) != 0) {
                                    $tel = '0' . $val[3];
                                }
                            }
                            if ($product_flag) {
                                $order_data = array(
                                    'id_warehouse' => $order_result['id_warehouse'],
                                    'id_department' => $domain['id_department'],
                                    'id_users' => $order_result['id_users'],
                                    'id_shipping' => 0,
                                    'id_zone' => $order_result['id_zone'],
                                    'id_order_status' => $order_status['id_order_status'],
                                    'id_domain' => $domain['id_domain'],
                                    'id_increment' => $domain['id_department'] . date('ymdhis') . rand(100, 999),
                                    'first_name' => $val[2],
                                    'country' => '中国',
                                    'tel' => $tel,
                                    'email' => $val[4],
                                    'province' => $order_result['province'],
                                    'city' => null,
                                    'area' => null,
                                    'address' => $val[10],
                                    'remark' => $val[12],
                                    'total_qty_ordered' => $val[11],
                                    'date_purchase' => $val[13],
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'currency_code' => $order_result['currency_code'],
                                    'payment_method' => '0',
                                    'price_total' => $val[7],
                                    'identify' => $order_result['id_users'],
                                    'payment_status' => 'processing',
                                    'order_count' => $val[19]
                                );
                                $order_result_id = D('Order/Order')->data($order_data)->add();
                                if ($order_result_id) {
                                    $item_data = array(
                                        'id_order' => $order_result_id,
                                        'id_product_sku' => $pro_sku['id_product_sku'],
                                        'id_product' => $product['id_product'],
                                        'sku' => $pro_sku['sku'],
                                        'sku_title' => $pro_sku['title'],
                                        'sale_title' => $val[6],
                                        'product_title' => $val[6],
                                        'quantity' => $val[11],
                                        'price' => $val[7],
                                        'total' => $val[7],
                                        'is_free' => 1,
                                        'attrs' => $arrs
                                    );
//                                    dump($item_data);
                                    D('Order/OrderItem')->data($item_data)->add();
                                }
                            }
                        } else {
                            $info['error'][] = sprintf('第%s行: 域名:%s，没有找到该域名', $count++, $val[0]);
                        }
                    } catch (\Exception $e) {
                        add_system_record(1, 4, 4, '恢复订单信息失败' . $e->getMessage());
//                        $message = $e->getMessage();
                    }
                }
//                dump($array_data);die;
            } else {
                $this->error('请选择文件');
            }
        }
        $this->assign('data', $array_data);
        $this->assign('infor', $info);
        $this->display();
    }

    public function secd_import_order() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        if (IS_POST) {
            $file = $_FILES['files']['tmp_name'];
            if (!empty($file)) {
                $data = $this->import_excel($file);
                $count = 1;
                $array_data = array();
                foreach ($data as $key => $val) {
                    try {
                        $domain = M('Domain')->field('id_domain,id_department')->where(array('name' => array('like', '%' . $val[0] . '%')))->find(); //域名id
                        if ($domain) {
//                            $product = M('Product')->field('id_product')->where(array('title' => $val[6]))->find(); //获取产品id
                            $pro_id = $val[4]; //产品id
                            $order_result = M('Order')->field('id_warehouse,id_users,id_zone,province,currency_code')->where(array('id_domain' => $domain['id_domain']))->order('created_at desc')->find();
                            $col_flag = false;
                            $cod_flag = false;

                            if (isset($val[7])) {//颜色
                                $col_flag = true;
                                $pro_option_col = M('ProductOptionValue')->field('id_product_option_value')->where(array('id_product' => $pro_id, 'title' => $val[7]))->find();
                            }
                            if (isset($val[8])) {//尺寸
                                $cod_flag = true;
                                $pro_option_cod = M('ProductOptionValue')->field('id_product_option_value')->where(array('id_product' => $pro_id, 'title' => $val[8]))->find();
                            }

                            if ($col_flag || $cod_flag) {
                                if ($col_flag) {
                                    $option_color = $pro_option_col['id_product_option_value'];
                                } else {
                                    $option_color = '';
                                }
                                if ($cod_flag) {
                                    $option_code = $pro_option_cod['id_product_option_value'];
                                } else {
                                    $option_code = '';
                                }
                                $arr = array($option_color, $option_code);
                                asort($arr);
                                $arrs = serialize($arr);
                                $attr = implode(',', $arr);
                                $where['option_value'] = array('EQ', trim($attr, ','));
                            } else {
                                $where['option_value'] = array('EQ', 0);
                            }
                            $where['id_product'] = array('EQ', $pro_id);
                            $pro_sku = M('ProductSku')->field('id_product_sku,sku,title')->where($where)->find();

                            if ($val[13]) {
                                $order_status = M('OrderStatus')->field('id_order_status')->where(array('title' => $val[13]))->find(); //订单状态
                            }
                            if ($val[2]) {
                                if (substr(intval($val[2]), 0, 1) != 0) {
                                    $tel = '0' . $val[2];
                                }
                            }
                            $order_data = array(
                                'id_warehouse' => $order_result['id_warehouse'],
                                'id_department' => $domain['id_department'],
                                'id_users' => $order_result['id_users'],
                                'id_shipping' => 0,
                                'id_zone' => $order_result['id_zone'],
                                'id_order_status' => $order_status['id_order_status'],
                                'id_domain' => $domain['id_domain'],
                                'id_increment' => $domain['id_department'] . date('ymdhis') . rand(100, 999),
                                'first_name' => $val[1],
                                'country' => '中国',
                                'tel' => $tel,
                                'email' => $val[3],
                                'province' => $order_result['province'],
                                'city' => null,
                                'area' => null,
                                'address' => $val[9],
                                'remark' => $val[11],
                                'total_qty_ordered' => $val[10],
                                'date_purchase' => $val[12],
                                'created_at' => date('Y-m-d H:i:s'),
                                'currency_code' => $order_result['currency_code'],
                                'payment_method' => '0',
                                'price_total' => $val[6],
                                'identify' => $order_result['id_users'],
                                'payment_status' => 'processing',
                                'order_count' => $val[14]
                            );
                            $order_result_id = D('Order/Order')->data($order_data)->add();
                            if ($order_result_id) {
                                $item_data = array(
                                    'id_order' => $order_result_id,
                                    'id_product_sku' => $pro_sku['id_product_sku'],
                                    'id_product' => $pro_id,
                                    'sku' => $pro_sku['sku'],
                                    'sku_title' => $pro_sku['title'],
                                    'sale_title' => $val[5],
                                    'product_title' => $val[5],
                                    'quantity' => $val[10],
                                    'price' => $val[6],
                                    'total' => $val[6],
                                    'is_free' => 1,
                                    'attrs' => $arrs
                                );
//                                dump($item_data);
                                D('Order/OrderItem')->data($item_data)->add();
                            }
                        } else {
                            $info['error'][] = sprintf('第%s行: 域名:%s，没有找到该域名', $count++, $val[0]);
                        }
                    } catch (\Exception $e) {
                        add_system_record(1, 4, 4, '恢复订单信息失败' . $e->getMessage());
//                        $message = $e->getMessage();
                    }
                }
//                dump($array_data);die;
            } else {
                $this->error('请选择文件');
            }
        }
        $this->assign('data', $array_data);
        $this->assign('infor', $info);
        $this->display();
    }
    
    public function update_import_order() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        if (IS_POST) {
            $file = $_FILES['files']['tmp_name'];
            if (!empty($file)) {
                $data = $this->import_excel($file);
                $count = 1;
                $array_data = array();
                foreach ($data as $key => $val) {
                    try {
                        $order = M('Order')->field('id_order')->where(array('email'=>$val[3],'date_purchase'=>$val[13]))->find();
                        $domain = M('Domain')->field('id_domain,id_department')->where(array('name' => array('like', '%' . $val[0] . '%')))->find(); //域名id
                        if ($domain) {
//                            $product = M('Product')->field('id_product')->where(array('title' => $val[6]))->find(); //获取产品id
                            $pro_id = $val[4]; //产品id
                            $order_result = M('Order')->field('id_warehouse,id_users,id_zone,province,currency_code')->where(array('id_domain' => $domain['id_domain']))->order('created_at desc')->find();
                            $col_flag = false;
                            $cod_flag = false;
                            $kun_flag = false;

                            if (isset($val[7])) {//颜色
                                $col_flag = true;
                                $pro_option_col = M('ProductOptionValue')->field('id_product_option_value')->where(array('id_product' => $pro_id, 'title' => $val[7]))->find();
                            }
                            if (isset($val[8])) {//尺寸
                                $cod_flag = true;
                                $pro_option_cod = M('ProductOptionValue')->field('id_product_option_value')->where(array('id_product' => $pro_id, 'title' => $val[8]))->find();
                            }
                            if (isset($val[9])) {//款式
                                $kun_flag = true;
                                $pro_option_kun = M('ProductOptionValue')->field('id_product_option_value')->where(array('id_product' => $pro_id, 'title' => $val[9]))->find();
                            }

                            if ($col_flag || $cod_flag || $kun_flag) {
                                if ($col_flag) {
                                    $option_color = $pro_option_col['id_product_option_value'];
                                } else {
                                    $option_color = '';
                                }
                                if ($cod_flag) {
                                    $option_code = $pro_option_cod['id_product_option_value'];
                                } else {
                                    $option_code = '';
                                }
                                if($kun_flag) {
                                    $option_kun = $pro_option_kun['id_product_option_value'];
                                } else {
                                    $option_kun = '';
                                }
                                $arr = array($option_color, $option_code, $option_kun);
                                asort($arr);
                                $arrs = serialize($arr);
                                $attr = implode(',', $arr);
                                $where['option_value'] = array('EQ', trim($attr, ','));
                            } else {
                                $where['option_value'] = array('EQ', 0);
                            }
                            $where['id_product'] = array('EQ', $pro_id);
                            $pro_sku = M('ProductSku')->field('id_product_sku,sku,title')->where($where)->find();
                           
                            if ($order['id_order']) {
                                $item_data = array(
                                    'id_order' => $order['id_order'],
                                    'id_product_sku' => $pro_sku['id_product_sku'],
                                    'id_product' => $pro_id,
                                    'sku' => $pro_sku['sku'],
                                    'sku_title' => $pro_sku['title'],
                                    'sale_title' => $val[5],
                                    'product_title' => $val[5],
                                    'quantity' => $val[11],
                                    'price' => $val[6],
                                    'total' => $val[6],
                                    'is_free' => 1,
                                    'attrs' => $arrs
                                );
//                                dump($item_data);
                                D('Order/OrderItem')->data($item_data)->add();
                            }
                        } else {
                            $info['error'][] = sprintf('第%s行: 域名:%s，没有找到该域名', $count++, $val[0]);
                        }
                    } catch (\Exception $e) {
                        add_system_record(1, 4, 4, '恢复订单信息失败' . $e->getMessage());
//                        $message = $e->getMessage();
                    }
                }
//                dump($array_data);die;
            } else {
                $this->error('请选择文件');
            }
        }
        $this->assign('data', $array_data);
        $this->assign('infor', $info);
        $this->display();
    }

    /**
     * 导入excel文件
     * @param  string $file excel文件路径
     * @return array        excel文件内容数组
     */
    function import_excel($file) {
        // 判断文件是什么格式
//        $type = pathinfo($file);
//        $type = strtolower($type["extension"]);
//        $type = $type === 'csv' ? $type : 'Excel5';
        ini_set('max_execution_time', '0');
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        // 判断使用哪种格式
        $objReader = \PHPExcel_IOFactory::createReader('CSV');
        $objPHPExcel = $objReader->load($file);
        $sheet = $objPHPExcel->getSheet(0);
        // 取得总行数 
        $highestRow = $sheet->getHighestRow();
        // 取得总列数      
        $highestColumn = $sheet->getHighestColumn();
        //循环读取excel文件,读取一条,插入一条
        $data = array();
        //从第一行开始读取数据
        for ($j = 2; $j <= $highestRow; $j++) {
            //从A列读取数据
            for ($k = 'A'; $k <= $highestColumn; $k++) {
                // 读取单元格
                $data[$j][] = $objPHPExcel->getActiveSheet()->getCell("$k$j")->getValue();
            }
        }
        return $data;
    }

    public function change_order_zone() {
        $arr = array(
            1170624055753995,
            1170625060817895,
            1170701062335532,
            1170702031102157,
            1170702095921784
        );
        $order = M('Order')->where(array('id_increment'=>array('IN',$arr)))->select();
        foreach($order as $key=>$val) {
            $data = array(
                'id_zone'=>4
            );
            $res = D('Order/Order')->where(array('id_order'=>$val['id_order']))->save($data);
            if($res) {
                echo 'success<br/>';
            } else {
                echo 'fail<br/>';
            }
        }
    }
}
