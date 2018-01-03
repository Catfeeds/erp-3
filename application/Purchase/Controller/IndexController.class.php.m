<?php

namespace Purchase\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;

/**
 * 采购模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Purchase\Controller
 */
class IndexController extends AdminbaseController {

    protected $Purchase, $Users;

    public function _initialize() {
        parent::_initialize();
        $this->Purchase = D("Common/Purchase");
        $this->PurchaseProduct = D("Common/PurchaseProduct");
        $this->Users = D("Common/Users");
    }

    public function all_list() {
        $count = $this->Purchase->count();
        $page = $this->page($count, 20);

        $model = new \Think\Model();
        $list = $model->table($this->Purchase->getTableName() . ' p')->join(array($this->Users->getTableName() . ' u on p.id_users = u.id'))->field('p.*,u.user_login')
                ->where($map)
                ->order("p.id_purchase DESC")
                ->limit($page->firstRow, $page->listRows)
                ->select();

        $sup_id = '';
        $ware_id = '';
        foreach ($list as $k => $v) {
            $sup_id .= $v['id_supplier'];
            $ware_id .= $v['id_warehouse'];
            if (empty($v['track_number'])) {
                continue;
            }
            $str = '';
            $trackings = explode("\n", $v['track_number']);
            foreach ($trackings as $t) {
                $t = trim($t);
//                $str .= sprintf('<a target="_blank" href="https://www.baidu.com/s?wd=%s">%s</a><br/>', $t, $t);
                $str .= sprintf('<a target="_blank" href="https://www.kuaidi100.com/courier/?searchText=%s">%s</a><br/>', $t, $t);
            }
            $list[$k]['track_number'] = $str;
        }

        $supplier_name = D("Common/Supplier")->field('title as sup_title')->where('id_supplier=' . $sup_id)->find();
        $warehouse = M('Warehouse')->field('title as ware_title')->where('id_warehouse=' . $ware_id)->find();


        $this->assign("proList", $list);
        $this->assign("supplier_name", $supplier_name);
        $this->assign("warehouse", $warehouse);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    public function index() {

        $count = $this->Purchase->count();
        $page = $this->page($count, 20);
        $dep = $_SESSION['department_id'];

        if (isset($_GET['depart_id']) && $_GET['depart_id']) {
            $where['p.id_department'] = array('EQ', $_GET['depart_id']);
        } else {
            $where['p.id_department'] = array('IN', $dep);
        }
        if (isset($_GET['ware_id']) && $_GET['ware_id']) {
            $where['p.id_warehouse'] = array('EQ', $_GET['ware_id']);
        }
        if (isset($_GET['status_id']) && $_GET['status_id']) {
            $where['p.status'] = array('EQ', $_GET['status_id']);
        }
        if (isset($_GET['pur_num']) && $_GET['pur_num']) {
            $where['p.purchase_no'] = array('EQ', $_GET['pur_num']);
        }
        if (isset($_GET['id_users']) && $_GET['id_users']) {
            $where['p.id_users'] = array('EQ', $_GET['id_users']);
        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
            $where[] = array('p.created_at' => $createAtArray);
        }

        $department = M('Department')->where(array('id_users' => $user_id))->find();
        $users = M('Users')->field('id,user_nicename')->where(array('superior_user_id' => $user_id))->select();
        $users = array_column($users, 'user_nicename', 'id');

        $flag = 1;
        if (!$department) {
            $flag = 2;
        }

        $model = new \Think\Model();
        $list = $model->table($this->Purchase->getTableName() . ' p')->join(array($this->Users->getTableName() . ' u on p.id_users = u.id'))->field('p.*,u.user_nicename')
                ->where($where)
                ->order("p.id_purchase DESC")
                ->limit($page->firstRow, $page->listRows)
                ->select();

        $pur_status = M('PurchaseStatus')->field('id_purchase_status,title')->select(); //采购单状态
        $pur_status = array_column($pur_status, 'title', 'id_purchase_status');

        $sup_id = array();
        $ware_id = array();
        foreach ($list as $k => $v) {
            $sup_id[] = $v['id_supplier'];
            $ware_id[] = $v['id_warehouse'];

            $list[$k]['product'] = $this->get_pur_pro($v['id_purchase']);
            $list[$k]['pro_name'] = $this->get_pur_pro($v['id_purchase'], true);
            $list[$k]['status_name'] = $pur_status[$v['status']];
        }

        foreach ($sup_id as $k => $v) {
            $supplier_name = D("Common/Supplier")->field('title as sup_title')->where('id_supplier=' . $v)->find();
            $warehouse = M('Warehouse')->field('title as ware_title')->where('id_warehouse=' . $ware_id[$k])->find();
            $list[$k]['sup_name'] = $supplier_name['sup_title'];
            $list[$k]['ware_name'] = $warehouse['ware_title'];
        }

//        echo '<pre>';
//        print_r($list);die;
//        echo '</pre>';

        $ware = M('Warehouse')->where(array('status' => 1))->cache(true, 3600)->select();
        $depart = M('Department')->where(array('type' => 1))->cache(true, 3600)->select();
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看采购订单列表');
        $this->assign("proList", $list);
        $this->assign("page", $page->show('Admin'));
        $this->assign('ware', $ware);
        $this->assign('pur_status', $pur_status);
        $this->assign('depart', $depart);
        $this->assign('flag', $flag);
        $this->assign('users', $users);
        $this->display();
    }

    protected function get_pur_pro($pur_id, $is_name = false) {
        $result = M('PurchaseProduct')->field('id_product,id_product_sku,quantity,price,received')->where(array('id_purchase' => $pur_id))->select();
        $pro_name = array();
        foreach ($result as $key => $val) {
            $result[$key]['sku'] = M('ProductSku')->where(array('id_product_sku' => $val['id_product_sku']))->getField('sku');
            $result[$key]['one_price'] = M('Product')->where(array('id_product' => $val['id_product']))->getField('sale_price');
            $name = M('Product')->field('inner_name')->where(array('id_product' => $val['id_product']))->find();
            $pro_name = $name['inner_name'];
        }
        if ($is_name) {
            return !empty($pro_name) ? $pro_name : '';
        } else {
            return $result;
        }
    }

    //作废采购订单
    public function get_invalid() {
        $flag = array();
        if (IS_AJAX) {
            $id = I('post.id');
            $data['id_purchase'] = $id;
            $data['status'] = I('post.status');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $result = D("Common/Purchase")->data($data)->save();
            if ($result) {
                $flag['flag'] = 1;
                $flag['msg'] = '作废成功';
            } else {
                $flag['flag'] = 0;
                $flag['msg'] = '作废失败';
            }
            add_system_record(sp_get_current_admin_id(), 7, 3, '作废采购订单');
            echo json_encode($flag);
            exit();
        }
    }

    /**
     * 新建采购单页面
     */
    public function create() {
        $dep = $_SESSION['department_id'];
        $map['id_department'] = array('IN', $dep);
        $where['title'] = array('NOT IN', '');
        //产品多的时候需要用文本框输入，然后ajax搜索了 ,目前产品不多使用select下拉
        $product = D("Common/Product")->where($map)->where($where)->order('id_product desc')->select();
        $supplier = D("Common/Supplier")->where($map)->select();
        $warehouse = D("Common/Warehouse")->select();
        $department = D("Common/Department")->where($map)->where('type=1')->select();
        $this->assign("warehouse", $warehouse);
        $this->assign("supplier", $supplier);
        $this->assign("products", $product);
        $this->assign('department', $department);
        $this->display();
    }

    /**
     * 生成产品属性
     */
    public function get_attr() {
        /** @var  $product \Common\Model\ProductModel */
        $id = I('post.product_id/i'); //产品Id
        $warehouse_id = I('post.warehouse_id/i'); //仓库id
        $pro_table_name = D("Common/Product")->getTableName();

        $model = new \Think\Model;

        $load_product = D("Common/Product")->find($id);
        $sku_where = array('id_product' => $id, 'status' => 1);
        $all_child_sku = D("Common/ProductSku")->where($sku_where)->select();

        $product_row = '<tr class="productBox' . $id . '"><td colspan="10" style="background-color: #f5f5f5;">' . $load_product['title'] . '
        <span class="deleteBox" delete="productBox' . $id . '" title="删除" style="margin-right:10px;font-size:20px;color:red;cursor: pointer;">x</span></td></tr>
        <tr class="headings productBox' . $id . '"><th>SKU</th><th>属性</th><th>采购单价</th><th>数量</th><th>采购金额</th><th>库存</th><th>在途数量</th><th>缺货量</th><th>近三日销量</th><th>日均销量</th></tr>';

        $tarray = array();
        $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
        $etime = date('Y-m-d 00:00:00');
        $tarray[] = array('EGT', $stime);
        $tarray[] = array('LT', $etime);
        $twhere[] = array('created_at' => $tarray);

        foreach ($all_child_sku as $c_key => $c_item) {//子SKU数据
//            $option_val = explode(',', $c_item['option_value']);
            $get_status = true;
            if ($get_status) {
                $set_qty = '';
                $set_price = '';
                $count_price = 0;
                $where['id_product'] = $id;
                $where['id_product_sku'] = $c_item['id_product_sku'];
                $wp_where['id_warehouse'] = !empty($warehouse_id) ? $warehouse_id : 1;
                $warehouse_pro = M('WarehouseProduct')->field('quantity,road_num')->where($where)->where($wp_where)->find();
                //sku缺货量            
                $swhere['id_order_status'] = 6;
                $sku_result = M('Order')->alias('o')->field('COUNT(*) as count_qty')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($swhere)->find();
                //三日平均销量
                $twhere['id_order_status'] = array('IN', OrderStatus::get_effective_status());
                $od_sale = M('Order')->alias('o')->field('COUNT(*) as count')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($twhere)->find();
                //近三日销量
                $td_sale = $this->get_three_sale($id, $c_item['id_product_sku']);
                $product_row .= '<tr class="productBox' . $id . '"><input type="hidden" value="' . $c_item['title'] . '" name="attr_name[' . $id . '][' . $c_item['id_product_sku'] . ']"/>' .
                        '<td>' . $c_item['sku'] . '</td> ' .
                        '<td>' . $c_item['title'] . '</td>' .
                        '<td><input type="text" class="sprice' . $c_key . '" value="' . $set_price . '" name="set_price[' . $id . '][' . $c_item['id_product_sku'] . ']" onchange="price_change(' . $c_key . ')"/></td>' .
                        '<td><input type="text" class="sqt' . $c_key . '" value="' . $set_qty . '" name="set_qty[' . $id . '][' . $c_item['id_product_sku'] . ']" onchange="qty_change(' . $c_key . ')"/></td>' .
                        '<td><span class="cprice' . $c_key . '">' . $count_price . '</span></td>' .
                        '<td>' . $warehouse_pro['quantity'] . '</td>' .
                        '<td>' . $warehouse_pro['road_num'] . '</td>' .
                        '<td>' . $sku_result['count_qty'] . '</td>' .
                        '<td>' . $td_sale . '</td>' .
                        '<td>' . round($od_sale['count'] / 3, 2) . '</td>' .
                        '</tr>';
            }
        }
        echo json_encode(array('status' => 1, 'row' => $product_row));
        exit();
    }

    /**
     * 生成采购单逻辑
     */
    public function save_post() {
        if ($_POST['product_id']) {
            $count = $this->Purchase->count();
            $add_data['id_warehouse'] = I('post.id_warehouse');
            $add_data['id_department'] = I('post.id_department');
            $add_data['created_at'] = date('Y-m-d H:i:s');
            $add_data['track_number'] = I('post.track_number');
            $add_data['id_supplier'] = I('post.id_supplier');
            $add_data['purchase_channel'] = I('post.pur_channel');
            $add_data['price'] = isset($total_price) ? $total_price : 0;
            $add_data['total'] = isset($total_qty) ? $total_qty : 0;
            $add_data['total_received'] = 0;
            $add_data['remark'] = I('post.remark');
            $add_data['id_users'] = $_SESSION['ADMIN_ID'];
            $add_data['date_from'] = I('post.from_date');
            $add_data['status'] = 19;
            $add_data['purchase_no'] = date('Y') . I('post.id_department') . sp_get_current_admin_id() . $count + 1;
            $get_in_id = D("Common/Purchase")->data($add_data)->add();

            $attr_name = I('post.attr_name');
            $attr_price = I('post.set_price');
            $set_qty = array_filter($_POST['set_qty']);
            $total_qty = 0;
            $total_price = 0;
            if ($set_qty) {
                foreach ($set_qty as $pro_id => $item) {
                    $get_qty = array_filter($item);
                    if ($get_qty && count($get_qty)) {
                        $temp_array = array();
                        foreach ($get_qty as $key => $qty) {
                            $sku_ids = $key;
                            $get_attr_name = $attr_name[$pro_id][$key]; //属性名称
                            $get_price = $attr_price[$pro_id][$key]; //价格
                            $total_qty += $qty; //数量
                            $total_price += $get_price * $qty; //价格
                            $array_data = array(
                                'id_purchase' => $get_in_id,
                                'id_product' => $pro_id,
                                'id_product_sku' => $sku_ids,
                                'option_value' => $get_attr_name,
                                'quantity' => $qty,
                                'price' => $get_price,
                                'received' => 0,
                            );
                            D("Common/PurchaseProduct")->data($array_data)->add();
                            $warehouse_product = M('WarehouseProduct')->field('id_product,road_num,id_product_sku,id_warehouse,quantity')
                                    ->where(array('id_warehouse' => I('post.id_warehouse')))
                                    ->where(array('id_product_sku' => $sku_ids))
                                    ->where(array('id_product' => $pro_id))
                                    ->find();
                            $datas = array(
                                'id_warehouse' => I('post.id_warehouse'),
                                'id_product' => $pro_id,
                                'id_product_sku' => $sku_ids,
                                'quantity' => 0,
                                'road_num' => $qty
                            );
                            if ($sku_ids == $warehouse_product['id_product_sku'] && I('post.id_warehouse') == $warehouse_product['id_warehouse']) {
                                $datas['quantity'] = $warehouse_product['quantity'];
                                $datas['road_num'] = $warehouse_product['road_num'] + $qty;
                                D("Common/WarehouseProduct")->where(array('id_product_sku' => $sku_ids))->where(array('id_warehouse' => I('post.id_warehouse')))->save($datas);
                            } else {
                                D("Common/WarehouseProduct")->data($datas)->add();
                            }
                            $tempArray[] = $arrayData;
                        }
                        $add_data['product_option_id'] = serialize($temp_array);
                    }
                }
            }
            $update = array('total' => $total_qty, 'price' => $total_price);
            D("Common/Purchase")->where('id_purchase=' . $get_in_id)->save($update);
            D("Purchase/PurchaseStatus")->add_pur_history($get_in_id, 19, '新建采购单');
            add_system_record(sp_get_current_admin_id(), 1, 2, '添加采购订单成功');
            $this->success("保存成功！", U('Index/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 1, 2, '添加采购订单失败');
            $this->error("保存失败,产品ID不能为空");
        }
    }

    /**
     * 编辑采购单页面
     */
    public function edit() {
        $id = I('get.id/i');
        $pro_table_name = D("Common/Product")->getTableName();
        $pur_or_table_name = $this->Purchase->getTableName();
        $sku_model = D("Common/ProductSku");
        $model = new \Think\Model;

        $dep = $_SESSION['department_id'];
        $map['id_department'] = array('IN', $dep);

        $purchase = $this->Purchase->where('id_purchase=' . $id)->find();
        $warehouse = M('Warehouse')->select();
        $supplier = M('Supplier')->where($map)->select();
        $department = D("Common/Department")->where($map)->where('type=1')->select();

        $tarray = array();
        $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
        $etime = date('Y-m-d 00:00:00');
        $tarray[] = array('EGT', $stime);
        $tarray[] = array('LT', $etime);
        $twhere[] = array('created_at' => $tarray);
        if ($purchase['id_purchase']) {
            $pur_pro_table_name = D("Common/PurchaseProduct")->getTableName();
            $pur_product = $model->table($pur_pro_table_name . ' AS pp INNER JOIN ' . $pro_table_name . ' AS p ON pp.id_product=p.id_product')
                            ->field('pp.*,p.title,p.thumbs,p.inner_name')->where('pp.id_purchase=' . $purchase['id_purchase'])->order('pp.id_purchase_product')->select();
            foreach ($pur_product as $key => $item) {
                $get_model = $sku_model->find($item['id_product_sku']);
                $pur_product[$key]['sku'] = $get_model['sku'];
                $where['id_product'] = $item['id_product'];
                $where['id_product_sku'] = $item['id_product_sku'];
                $wp_where['id_warehouse'] = $purchase['id_warehouse'];
                $warehouse_pro = M('WarehouseProduct')->field('quantity,road_num')->where($where)->where($wp_where)->find();
                //sku缺货量            
                $swhere['id_order_status'] = 6;
                $sku_result = M('Order')->alias('o')->field('COUNT(*) as count_qty')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($swhere)->find();
                //三日平均销量
                $twhere['id_order_status'] = array('IN', OrderStatus::get_effective_status());
                $od_sale = M('Order')->alias('o')->field('COUNT(*) as count')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($twhere)->find();
                //近三日销量
                $td_sale = $this->get_three_sale($item['id_product'], $item['id_product_sku']);
                $pur_product[$key]['sku_qty'] = $warehouse_pro['quantity'];
                $pur_product[$key]['sku_road_qty'] = $warehouse_pro['road_num'];
                $pur_product[$key]['sku_qh_sale'] = $sku_result['count_qty'];
                $pur_product[$key]['sku_three_sales'] = $td_sale;
                $pur_product[$key]['sku_three_sale'] = round($od_sale['count'] / 3, 2);
            }
        }
        $supplier_name = M('Supplier')->where(array('id_supplier' => $purchase['id_supplier']))->getField('title');
        $this->assign('product', $pur_product);
        $this->assign('data', $purchase);
        $this->assign('warehouse', $warehouse);
        $this->assign('supplier', $supplier);
        $this->assign('department', $department);
        $this->assign('supplier_name', $supplier_name);
        $this->display();
    }

    /**
     * 编辑采购单逻辑
     */
    public function edit_post() {
        if ($_POST['product_id']) {
            $pur_id = I('post.id/i');
            $p_id = I('post.product_id/i');
            $add_data['id_warehouse'] = I('post.id_warehouse');
            $add_data['id_department'] = I('post.id_department');
            $add_data['updated_at'] = date('Y-m-d H:i:s');
            $add_data['id_supplier'] = I('post.id_supplier');
            $add_data['purchase_channel'] = I('post.pur_channel');
            $add_data['price'] = isset($total_price) ? $total_price : 0;
            $add_data['total'] = isset($total_qty) ? $total_qty : 0;
            $add_data['total_received'] = 0;
            $add_data['remark'] = I('post.remark');
            $add_data['status'] = 19;
            $result = D("Common/Purchase")->where(array('id_purchase' => $id))->save($add_data);

            $pur_pro = M('PurchaseProduct')->where(array('id_purchase' => $pur_id))->select();
            $pur_pro_id = $pur_pro[0]['id_product']; //原有的产品id
            if ($pur_pro_id != $p_id) {
                D("Common/PurchaseProduct")->where(array('id_purchase' => $pur_id))->delete();
            }
            $attr_name = I('post.attr_name');
            $attr_price = I('post.set_price');
            $set_qty = array_filter($_POST['set_qty']);
            $total_qty = 0;
            $total_price = 0;
            if ($set_qty) {
                foreach ($set_qty as $pro_id => $item) {
                    $get_qty = array_filter($item);
                    if ($get_qty && count($get_qty)) {
                        $temp_array = array();
                        foreach ($get_qty as $key => $qty) {
                            $sku_ids = $key; //SKU id
                            $get_attr_name = $attr_name[$pro_id][$key]; //属性名称
                            $get_price = $attr_price[$pro_id][$key]; //价格
                            $total_qty += $qty; //数量
                            $total_price += $get_price * $qty; //价格
                            $array_data = array(
                                'id_purchase' => $pur_id,
                                'id_product' => $pro_id,
                                'id_product_sku' => $sku_ids,
                                'option_value' => $get_attr_name,
                                'quantity' => $qty,
                                'price' => $get_price,
                                'received' => 0,
                            );
                            $warehouse_product = M('WarehouseProduct')->field('id_product,road_num,id_product_sku,id_warehouse,quantity')->where(array('id_warehouse' => I('post.id_warehouse'), 'id_product_sku' => $sku_ids, 'id_product' => $pro_id))->find();
                            if ($pur_pro_id == $pro_id) {
                                $pur_product = M('PurchaseProduct')->where(array('id_purchase' => $pur_id, 'id_product' => $pro_id, 'id_product_sku' => $sku_ids))->find();
                                D("Common/PurchaseProduct")->where(array('id_purchase' => $pur_id, 'id_product' => $pro_id, 'id_product_sku' => $sku_ids))->save($array_data);
                                if ($qty != $pur_product['quantity'] && $warehouse_product['road_num'] >= $pur_product['quantity']) {
                                    $datas['road_num'] = ($warehouse_product['road_num'] - $pur_product['quantity']) + $qty;
                                    D("Common/WarehouseProduct")->where(array('id_product_sku' => $sku_ids))->where(array('id_warehouse' => I('post.id_warehouse')))->save($datas);
                                }
                            } else {
                                D("Common/PurchaseProduct")->data($array_data)->add();
                                $datas = array(
                                    'id_warehouse' => I('post.id_warehouse'),
                                    'id_product' => $pro_id,
                                    'id_product_sku' => $sku_ids,
                                    'road_num' => $qty
                                );
                                if ($sku_ids == $warehouse_product['id_product_sku'] && I('post.id_warehouse') == $warehouse_product['id_warehouse']) {
                                    $datas['quantity'] = $warehouse_product['quantity'];
                                    $datas['road_num'] = $warehouse_product['road_num'] + $qty;
                                    D("Common/WarehouseProduct")->where(array('id_product_sku' => $sku_ids))->where(array('id_warehouse' => I('post.id_warehouse')))->save($datas);
                                } else {
                                    D("Common/WarehouseProduct")->data($datas)->add();
                                }
                            }
                            $tempArray[] = $arrayData;
                        }
                        $add_data['product_option_id'] = serialize($temp_array);
                    }
                }
            }
            $update = array('total' => $total_qty, 'price' => $total_price);
            D("Common/Purchase")->where('id_purchase=' . $pur_id)->save($update);
            D("Purchase/PurchaseStatus")->add_pur_history($pur_id, 19, '编辑采购单');
            add_system_record(sp_get_current_admin_id(), 1, 2, '编辑采购订单成功');
            $this->success("保存成功！", U('Index/index'));
        } else {
            $this->error("保存失败,产品ID不能为空");
        }
    }

    //所有产品设置
    public function all_product_list() {
        $product = D('Common/Product');
        $count = $product->count();
        $page = $this->page($count, 20);
        $table = $product->getTableName();

        $products = $product
                ->order(array('id_product' => 'desc'))
                ->limit($page->firstRow, $page->listRows)
                ->select();

        $this->assign('products', $products);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }

    /**
     * 产品设置页面
     */
    public function product_list() {
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where = array();
        if(isset($_GET['product_name']) && $_GET['product_name']){
            $where['p.title'] = array('LIKE', '%'.$_GET['product_name'].'%');
        }
        if (I('get.pro_title')&&I('get.pro_title')) {
            $where['title'] = array('LIKE', '%'.I('get.pro_title').'%');
        }
        if (I('get.pro_name')&&I('get.pro_name')) {
            $where['inner_name'] = array('LIKE', '%'.I('get.pro_name').'%');
        }
        if (I('get.id_department')) {
            $where['id_department'] = I('get.id_department');
        }else{
            $where['id_department'] = array('IN',$department_id);
        }

        $product = D('Common/Product');
        $table = $product->getTableName();
        $dep = $_SESSION['department_id'];
        $dep = implode(',', $dep);
        $dep == 8 ? '' : ($dep ? $map['id_department'] = array('IN', $dep) : '');

        $count = $product->where($where)->count();
        $page = $this->page($count, 20);
        $products = $product
                ->where($where)
                ->order(array('id_product' => 'desc'))
                ->limit($page->firstRow, $page->listRows)
                ->select();
        foreach ($products as $k => $v) {
            $img = json_decode($v['thumbs'], true);
            $products[$k]['img'] = $img['photo'][0]['url'];
        }
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->order('id_department asc')->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看产品设置列表');
        $this->assign("department", $department);
        $this->assign('products', $products);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }

    /**
     * 产品设置编辑页面
     */
    public function product_edit() {
        $id = intval(I("get.id"));
        if ($id == 0) {
            $id = intval(I("post.id"));
        }
        $table = D('Product/Product');
        $product = $table->where(array('id_product' => $id))->find();
        $this->assign('product', $product);
        $this->display();
    }

    /**
     * 产品设置编辑逻辑
     */
    public function product_edit_post() {
        $product_id = I('post.product_id/i');
        if (IS_POST) {
            $product = D('Common/Product');

            $data = I('post.');
            $data['id_product'] = $product_id;
            if ($data) {
                if ($product->save($data) !== false) {
                    add_system_record(sp_get_current_admin_id(), 2, 2, '产品' . $product_id . '设置成功');
                    $this->success("修改成功！", U('Index/product_list'));
                } else {
                    add_system_record(sp_get_current_admin_id(), 2, 2, '产品' . $product_id . '设置失败');
                    $this->error("修改失败！");
                }
            } else {
                $this->error($product->getError());
            }
        }
    }

    //采购收货列表
    public function purchase_list() {

        if (!empty($_GET['department_id'])) {
            $where['po.id_department'] = array('EQ', $_GET['department_id']);
        }
        if (!empty($_GET['warehouse_id'])) {
            $where['po.id_warehouse'] = array('EQ', $_GET['warehouse_id']);
        }
        if (!empty($_GET['status_id'])) {
            $where['po.status'] = array('EQ', $_GET['status_id']);
        }
        if (!empty($_GET['purchase_no'])) {
            $where['po.purchase_no'] = array('EQ', $_GET['purchase_no']);
        }
        if (!empty($_GET['track_number'])) {
            $where['po.track_number'] = array('LIKE', '%' . $_GET['track_number'] . '%');
        }
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['po.created_at'] = $created_at_array;
        }

        $department = M('Department')->where('type=1')->select();
        $warehouse = M('Warehouse')->select();

        $pur_or_table_name = $this->Purchase->getTableName();
        $user_table_name = D("Common/Users")->getTableName();
        $model = new \Think\Model;

        $count = $model->table($pur_or_table_name . ' as po LEFT JOIN ' . $user_table_name . ' as u ON u.id=po.id_users')->field('po.*,u.user_nicename')->where($where)->count();
        $page = $this->page($count, 20);
        $pro_list = $model->table($pur_or_table_name . ' as po LEFT JOIN ' . $user_table_name . ' as u ON u.id=po.id_users')
                        ->field('po.*,u.user_nicename')
                        ->where($where)->order("po.id_purchase DESC")
                        ->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($pro_list as $k => $p) {
            if (empty($p['track_number'])) {
                continue;
            }
            $str = '';
            $trackings = explode("\n", $p['track_number']);
            foreach ($trackings as $t) {
                $t = trim($t);
//                $str .= sprintf('<a target="_blank" href="https://www.baidu.com/s?wd=%s">%s</a><br/>', $t, $t);
                $str .= sprintf('<a target="_blank" href="https://www.kuaidi100.com/courier/?searchText=%s">%s</a><br/>', $t, $t);
            }
            $pro_list[$k]['track_number'] = $str;
        }
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看采购收货列表');
        $this->assign("getData", $_GET);
        $this->assign('department', $department);
        $this->assign('warehouse', $warehouse);
        $this->assign("pro_list", $pro_list);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    //新采购收货列表
    public function purchase_list2() {

        if (!empty($_GET['department_id'])) {
            $where['po.id_department'] = array('EQ', $_GET['department_id']);
        }
        if (!empty($_GET['warehouse_id'])) {
            $where['po.id_warehouse'] = array('EQ', $_GET['warehouse_id']);
        }
        if (!empty($_GET['status'])) {
            $where['po.status'] = array('EQ', $_GET['status']);
        }
        if (!empty($_GET['purchase_no'])) {
            $where['po.purchase_no'] = array('EQ', $_GET['purchase_no']);
        }
//        if(!empty($_GET['track_number'])) {
//            $where['po.track_number'] = array('LIKE', '%' . $_GET['track_number'] . '%');
//        }
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['po.created_at'] = $created_at_array;
        }

        $department = M('Department')->where('type=1')->select();
        $department = array_column($department, 'title', 'id_department');
        $purchase_status = M('Purchase_status')->where(array('id_purchase_status' => array('IN', '4,6,7,8')))->select();
        $status = array();
        foreach ($purchase_status as $value) {
            $status[$value['id_purchase_status']] = $value['title'];
        }
        $warehouse = M('Warehouse')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $pur_or_table_name = $this->Purchase->getTableName();
        $user_table_name = D("Common/Users")->getTableName();
        $supplier = M('Supplier')->getTableName();
        $model = new \Think\Model;
        $count = $model->table($pur_or_table_name . ' as po LEFT JOIN ' . $user_table_name . ' as u ON u.id=po.id_users')->field('po.*,u.user_nicename')->where($where)->count();
        $page = $this->page($count, 20);
        $pro_list = $model->table($pur_or_table_name . ' as po LEFT JOIN ' . $user_table_name . ' as u ON u.id=po.id_users LEFT JOIN ' . $supplier . ' as s on s.id_supplier = po.id_supplier')
                        ->field('po.*,u.user_nicename,s.title')
                        ->where($where)->order("po.id_purchase DESC")
                        ->limit($page->firstRow . ',' . $page->listRows)->select();
        foreach ($pro_list as $k => $p) {
            if (empty($p['track_number'])) {
                continue;
            }
            $str = '';
            $trackings = explode("\n", $p['track_number']);
            foreach ($trackings as $t) {
                $t = trim($t);
//                $str .= sprintf('<a target="_blank" href="https://www.baidu.com/s?wd=%s">%s</a><br/>', $t, $t);
                $str .= sprintf('<a target="_blank" href="https://www.kuaidi100.com/courier/?searchText=%s">%s</a><br/>', $t, $t);
            }
            $pro_list[$k]['track_number'] = $str;
        }
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看采购收货列表');

        $this->assign("getData", $_GET);
        $this->assign('department', $department);
        $this->assign('purchase_status', $status);
        $this->assign('warehouse', $warehouse);
        $this->assign("pro_list", $pro_list);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /**
     * 仓库收货2，入库
     */
    public function signed2() {
        $id = I('get.id');
        $purchase = D("Common/Purchase")->find($id);
        $product_html = '<tr><th>产品图片</th><th>SKU</th><th>产品名</th>
        <th>采购单价</th><th>采购数</th><th>已收货的数量</th><th>收货数量</th></tr>';
        $product_html .= $this->get_product_html($id);
        $this->assign('attr_row', $product_html);
        $this->assign('data', $purchase);
        $this->display();
    }

    /**
     * 仓库收货，入库
     */
    public function signed() {
        $id = I('get.id');
        $purchase = D("Common/Purchase")->find($id);
        $pur_product = D("Common/PurchaseProduct")->where('id_purchase=' . $id)->order('id_product')->select();

        $get_options = array();
        $temp_product = array();
        foreach ($pur_product as $item) {
            $product_id = $item['id_product'];
            $get_options[$product_id][$item['id_product_sku']] = array(
                'id' => $item['id_purchase_product'],
                'number' => $item['quantity'],
                'price' => $item['price'],
                'receive' => $item['received']);
            $temp_product[$product_id] = $product_id;
            $temp_product['id_purchase'] = $id;
            dump($temp_product);
            die;
        }
        dump($temp_product);

        $product_html = '';
        if ($temp_product) {
            foreach ($temp_product as $pro_id) {
                $options = $get_options[$pro_id];
//               $this->get_product_html($pro_id, $options);
            }
        }
        $this->assign('attr_row', $product_html);
        $this->assign('data', $purchase);
        $this->display();
    }

    /**
     * 查看采购单
     */
    public function look() {
        $id = $_GET['id'];
        $tarray = array();
        $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
        $etime = date('Y-m-d 00:00:00');
        $tarray[] = array('EGT', $stime);
        $tarray[] = array('LT', $etime);
        $twhere[] = array('created_at' => $tarray);

        $purchase = D("Common/Purchase")->find($id);
        $purchase_list = M('PurchaseProduct')->alias('pp')->field('pp.*,pu.sku')->join('__PRODUCT_SKU__ as pu on pu.id_product_sku = pp.id_product_sku')->where(array('id_purchase' => array('EQ', $id)))->select();
        foreach ($purchase_list as $key => $v) {
            $where['id_product'] = $v['id_product'];
            $where['id_product_sku'] = $v['id_product_sku'];
            $load_product = D("Common/Product")->field('thumbs,title')->where(array('id_product' => $v['id_product']))->find();
            $warehouse_pro = M('WarehouseProduct')->field('quantity,road_num')->where(array('id_product' => $v['id_product'], 'id_product_sku' => $v['id_product_sku'], 'id_warehouse' => $purchase['id_warehouse']))->find();
            //sku缺货量            
            $swhere['id_order_status'] = 6;
            $sku_result = M('Order')->alias('o')->field('COUNT(*) as count_qty')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($swhere)->find();
            //三日平均销量
            $twhere['id_order_status'] = array('IN', OrderStatus::get_effective_status());
            $od_sale = M('Order')->alias('o')->field('COUNT(*) as count')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($twhere)->find();
            //近三日销量
            $td_sale = $this->get_three_sale($v['id_product'], $v['id_product_sku']);
            //属性
            $pro_option = M('ProductSku')->field('title')->where($where)->find();
            $purchase_list[$key]['option'] = $pro_option['title'];
            $purchase_list[$key]['title'] = $load_product['title'];
            $purchase_list[$key]['img'] = json_decode($load_product['thumbs'], true);
            $purchase_list[$key]['qty'] = $warehouse_pro['quantity'];
            $purchase_list[$key]['road_num'] = $warehouse_pro['road_num'];
            $purchase_list[$key]['order_qty'] = $sku_result['count_qty'];
            $purchase_list[$key]['oday_sale'] = round($od_sale['count'] / 3, 2);
            $purchase_list[$key]['tday_sale'] = $td_sale;
        }
        $department = M('Department')->where(array('id_deprtment' => $purchase['id_department']))->getField('title');
        $warehouse = M('Warehouse')->where(array('id_warehouse' => $purchase['id_warehouse']))->getField('title');
        $supplier = M('Supplier')->where(array('id_supplier' => $purchase['id_supplier']))->getField('title');
        switch ($purchase['purchase_channel']) {
            case 1:
                $pur_channel = '阿里巴巴';
                break;
            case 2:
                $pur_channel = '淘宝';
                break;
            case 3:
                $pur_channel = '线下';
                break;
        }
        $pur_record = M('PurchaseRecord')->where(array('id_purchase' => $id))->select();
        $pur_status = M('PurchaseStatus')->field('id_purchase_status,title')->select(); //采购单状态
        $pur_status = array_column($pur_status, 'title', 'id_purchase_status');
        $this->assign('purchase_list', $purchase_list);
        $this->assign('data', $purchase);
        $this->assign('supplier', $supplier);
        $this->assign('department', $department);
        $this->assign('warehouse', $warehouse);
        $this->assign('pur_channel', $pur_channel);
        $this->assign('pur_record', $pur_record);
        $this->assign('pur_status', $pur_status);
        $this->display();
    }

    /**
     * 获取近三天的销量
     * @param type $pro_id
     * @param type $sku_id
     * @return string
     */
    protected function get_three_sale($pro_id, $sku_id) {
        $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
        $etime = date('Y-m-d 00:00:00');
        $tarray = array();
        $tarray[] = array('EGT', $stime);
        $tarray[] = array('LT', $etime);
        $twhere[] = array('created_at' => $tarray);
        $twhere['id_product'] = $pro_id;
        $twhere['id_product_sku'] = $sku_id;
        $twhere['id_order_status'] = array('IN', OrderStatus::get_effective_status());
        $od_sale = M('Order')->alias('o')->field('SUBSTRING(created_at,9,2) as create_date,COUNT(*) as count')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($twhere)->group('create_date')->select();
//        dump($od_sale);
//        $od_sale = array_column($od_sale, 'count','create_date');
        $str = '';
        foreach ($od_sale as $key => $val) {
            $str .= $val['create_date'] . '号：' . $val['count'] . '<br>';
        }
        return $str;
    }

    protected function get_product_html($id) {
        $purchase_product = M('PurchaseProduct')->alias('pp')->field('pp.*,pu.sku')->join('__PRODUCT_SKU__ as pu on pu.id_product_sku = pp.id_product_sku', 'LEFT')->where(array('pp.id_purchase' => array('EQ', $id)))->select();
        $product_row = '';
        foreach ($purchase_product as $key => $v) {
            $load_product = D("Common/Product")->field('thumbs,title')->where(array('id_product' => $v['id_product']))->find();
            $purchase_product[$key]['title'] = $load_product['title'];
            $purchase_product[$key]['img'] = json_decode($load_product['thumbs'], true);
            $photo = !empty($purchase_product[$key]['img']['photo']) ? $purchase_product[$key]['img']['photo'][0]['url'] : '';
            $product_row .= '<input type="hidden" name="data[' . $key . '][id_purchase_product]" value="' . $v['id_purchase_product'] . '"><input type="hidden" name="data[' . $key . '][id_product]" value="' . $v['id_product'] . '"><input type="hidden" name="data[' . $key . '][id_product_sku]" value="' . $v['id_product_sku'] . '"><input type="hidden" name="data[' . $key . '][id_purchase]" value="' . $v['id_purchase'] . '"><tr class="tr"><td><img  src="' . sp_get_image_preview_url($photo) . '" style="height:36px;width: 36px;"></td><td>' . $v['sku'] . '</td><td>' . $purchase_product[$key]['title'] . '</td><td>' . $v['price'] . '</td><td class="purchase">' . $v['quantity'] . '</td><td class="received">' . $v['received'] . '</td><td class="add"><input type="text" name="data[' . $key . '][quantity]"></td></tr>';
        }
        return $product_row;
    }

    /**
     * 添加产品 入库  库存新
     */
    public function save_stock2() {
        $id_purchase = $_POST['id_purchase'];
        $data = $_POST['data'];
        $sum_received = 0;
        switch ($_POST['method']) {
            case 'wait':
                foreach ($data as $v) {
                    $old_received = M('PurchaseProduct')->field('received')->find($v['id_purchase_product']);
                    $save['received'] = $v['quantity'] + $old_received['received'];
                    $save['id_purchase_product'] = $v['id_purchase_product'];
                    M('PurchaseProduct')->save($save);
//                    $where['id_warehouse'] = $_POST['id_warehouse'];
//                    $where['id_product'] = $v['id_product'];
//                    $where['id_product_sku'] = $v['id_product_sku'];
//                    M('WarehouseProduct')->where($where)->setInc('quantity',$v['quantity']);
//                    $road_num = M('WarehouseProduct')->where($where)->getField('road_num');
//                    if(($v['quantity']-$road_num)>=0)
//                        M('WarehouseProduct')->where($where)->setField('road_num',0);
//                    else
//                        M('WarehouseProduct')->where($where)->setDec('road_num',$v['quantity']);
                    $sum_received += $v['quantity'];
                }
                $update['id_purchase'] = $id_purchase;
                $old_total = M('Purchase')->where($update)->getField('total_received');
                $update['status'] = 6;
                $update['total_received'] = $old_total + $sum_received;
                $res = M('Purchase')->save($update);
                if ($res === false) {
                    $this->error("保存失败！", U('index/purchase_list2'));
                } else {
                    $this->success("保存完成！", U('index/purchase_list2'));
                }
                break;
            case 'finish':
                foreach ($data as $v) {
                    $old_received = M('PurchaseProduct')->field('received')->find($v['id_purchase_product']);
                    $save['received'] = $v['quantity'] + $old_received['received'];
                    $save['id_purchase_product'] = $v['id_purchase_product'];
                    M('PurchaseProduct')->save($save);
//                    $where['id_warehouse'] = $_POST['id_warehouse'];
//                    $where['id_product'] = $v['id_product'];
//                    $where['id_product_sku'] = $v['id_product_sku'];
//                    M('WarehouseProduct')->where($where)->setInc('quantity',$v['quantity']);
//                    $road_num = M('WarehouseProduct')->where($where)->getField('road_num');
//                    if(($road_num-$v['quantity'])>=0)
//                    M('WarehouseProduct')->where($where)->setDec('road_num',$v['quantity']);
                    $sum_received += $v['quantity'];
                }
                $update['id_purchase'] = $id_purchase;
                $old_total = M('Purchase')->where($update)->getField('total_received');
                $update['status'] = 7;
                $update['total_received'] = $old_total + $sum_received;
                $res = M('Purchase')->save($update);
                if ($res === false) {
                    $this->error("保存失败！", U('index/purchase_list2'));
                } else {
                    $this->success("保存完成！", U('index/purchase_list2'));
                }
                break;
            case 'reject':
                $update['id_purchase'] = $id_purchase;
                $update['status'] = 8;
                $update['remark'] = $_POST['remark'];
                $res = M('Purchase')->save($update);
                if ($res === false) {
                    $this->error("保存失败！", U('index/purchase_list2'));
                } else {
                    $this->success("保存完成！", U('index/purchase_list2'));
                }
                break;
        }
    }

    /**
     * 添加产品 入库  库存
     */
    public function save_stock() {
        $set_all_qty = I('post.set_qty'); //收货数量
        $purchase_id = I('post.id'); //采购id
        $sku_ids = I('post.sku_id');
        $add_all_qty = I('post.add_qty');
        $total_entry = 0;
        $temp_product = array();
        $purchase_obj = D('Common/Purchase');
        $pru_pro_obj = D("Common/PurchaseProduct");
        $sku_obj = D("Common/ProductSku");
        $pro_obj = D("Common/Product");
        $order_table_name = D('Order/Order')->getTableName();
        $order_item_table_name = D('Order/OrderItem')->getTableName();
        $model = new \Think\Model;
        try {
            foreach ($set_all_qty as $product_id => $all_qty) {
                $all_qty = array_filter($all_qty);
                if ($all_qty) {
                    foreach ($all_qty as $sku_id => $qty) {
                        $pur_pro_id = $sku_ids[$sku_id];
                        $load_pur_pro = $pru_pro_obj->find($pur_pro_id);
                        $purchase = $purchase_obj->find($purchase_id);
                        if ($load_pur_pro) {
                            $total_entry += $qty;
                            $receive = $load_pur_pro['received'] + $qty;
                            $data = array('received' => $receive, 'id_purchase_product' => $pur_pro_id);
                            $pru_pro_obj->save($data); //保存采购的产品

                            $warehouse_product = M('WarehouseProduct')->field('id_product,road_num,id_product_sku,id_warehouse,quantity')
                                    ->where(array('id_warehouse' => $purchase['id_warehouse']))
                                    ->where(array('id_product_sku' => $load_pur_pro['id_product_sku']))
                                    ->where(array('id_product' => $product_id))
                                    ->find();

                            $warehouse_data = array(
                                //收到的货多于采购的量者在路上(road_num)为0
                                'road_num' => $warehouse_product['road_num'] - $qty <= 0 ? 0 : ($warehouse_product['road_num'] - $qty),
                                'quantity' => $warehouse_product['quantity'] + $qty,
                            );

                            D("Common/WarehouseProduct")->where(array('id_product_sku' => $load_pur_pro['id_product_sku']))->where(array('id_warehouse' => $purchase['id_warehouse']))->save($warehouse_data);
//
                            $product = $pro_obj->field('quantity')->find($product_id);
                            $pro_qty = $product['quantity'] + $qty;
                            $pro_obj->where('id_product=' . $product_id)->save(array('quantity' => $pro_qty));
//                            $temp_product[$product_id] = $product_id; //array('product_id'=>$productId,'sku_id'=>$skuId);
                            //TODO: 入库更新订单算法问题
                            //1. 一单多品时只查找了一个sku的产品,另一个产品的sku没有匹配问题
                            $order_data = $model->table($order_table_name . ' as o LEFT JOIN ' . $order_item_table_name . ' as oi ON o.id_order=oi.id_order')->field('o.*,oi.*')
                                    ->where('oi.id_product_sku = ' . $load_pur_pro['id_product_sku'] . ' and o.id_order_status=6')
                                    ->order('o.date_purchase ASC')
                                    ->select();

                            //仓库收货后对缺货产品进行未配货的状态更新，并减去仓库库存
                            if ($order_data && $qty > 0) {
                                foreach ($order_data as $key => $val) {
                                    $results = \Order\Model\UpdateStatusModel::lessInventory($val['id_order'], $val);
                                    if ($results['status']) {
                                        $update_order_info = array();
                                        $update_order_info['id_order_status'] = 4;
                                        $update_order_info['id_warehouse'] = isset($results['id_warehouse']) ? end($results['id_warehouse']) : 1;
                                        D('Order/Order')->where('id_order=' . $val['id_order'])->save($update_order_info);
                                        D('Order/OrderRecord')->addHistory($val['id_order'], 4, 1, '更改仓库库存对缺货状态进行更新');
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $purchase = D("Common/Purchase")->find($purchase_id);
            $received_number = $purchase['total_received'] + $total_entry;
            if ($received_number >= $purchase['total']) {
                $status = 2;
            } else {
                $status = $_POST['status'] > -1 ? $_POST['status'] : 1;
            }
            $datas = array('total_received' => $received_number, 'id_purchase' => $purchase_id, 'status' => $status, 'updated_at' => date('Y-m-d H:i:s'));
            D("Common/Purchase")->save($datas);
            //处理额外 收到的产品库存
            if ($add_all_qty) {
                foreach ($add_all_qty as $pro_id => $add_qty) {
                    $add_qty = array_filter($add_qty);
                    if ($add_qty) {
                        foreach ($add_qty as $get_sku_id => $add_number) {
                            $add_number = (int) $add_number;
                            if ($add_number > 0) {
                                $product = $pro_obj->field('quantity')->find($pro_id);
                                $pro_qty = $product['quantity'] + $add_number;
                                $pro_obj->where('id_product=' . $pro_id)->save(array('quantity' => $pro_qty));

                                $pur_add = array('id_purchase' => $purchase_id, 'id_product' => $pro_id, 'id_product_sku' => $get_sku_id, 'quantity' => $add_number);
                                D("Common/PurchaseProduct")->data($pur_add)->add();

                                $order_data = $model->table($order_table_name . ' as o LEFT JOIN ' . $order_item_table_name . ' as oi ON o.id_order=oi.id_order')->field('o.*,oi.*')
                                        ->where('oi.id_product_sku=' . $get_sku_id . ' and o.id_order_status=6')
                                        ->order('o.date_purchase desc')
                                        ->select();
                                //仓库收货后对缺货产品进行未配货的状态更新，并减去仓库库存
                                if ($order_data) {
                                    foreach ($order_data as $key => $val) {
                                        $product_other = $pro_obj->field('quantity')->find($pro_id);
                                        $warehouse_oteher_product = M('WarehouseProduct')->field('id_product,road_num,id_product_sku,id_warehouse,quantity')->where('id_warehouse=' . $purchase['id_warehouse'] . ' and id_product_sku=' . $val['id_product_sku'] . ' and id_product=' . $product_id)->find();
                                        $surplus_pro_qty = $product_other['quantity'] - $val['quantity']; //产品总库存
                                        $surplus_wpro_qty = $warehouse_oteher_product['quantity'] - $val['quantity']; //仓库库存
                                        if ($surplus_wpro_qty < 0)
                                            continue;
                                        $pro_obj->where('id_product=' . $pro_id)->save(array('quantity' => $surplus_pro_qty));
                                        D("Common/WarehouseProduct")->where(array('id_product_sku' => $val['id_product_sku']))->where(array('id_warehouse' => $purchase['id_warehouse']))->save(array('quantity' => $surplus_wpro_qty));
                                        D('Order/Order')->where('id_order=' . $val['id_order'])->save(array('id_order_status' => 4));
                                    }
                                }

                                $temp_product[$pro_id] = array('product_id' => $pro_id, 'sku_id' => $get_sku_id);
                            }
                        }
                    }
                }
            }
            //添加 消息通知
//            if($temp_product){
//                $update_data = array('products'=>$temp_product);
//                Hook::listen('event:warehouse:add_storage',$updateData);
//            }
            add_system_record(sp_get_current_admin_id(), 2, 3, '采购产品' . $purchase_id . '收货成功，采购总数量：' . $purchase['total'] . '，收到总数量：' . $purchase['total_received']);
            $status = 1;
            $message = '收货成功！';
        } catch (\Exception $e) {
            $status = 0;
            $message = $e->getMessage();
        }
        echo json_encode(array('status' => $status, 'messageg' => $message));
        exit();
    }

    /**
     * 统计指定时间内的指定产品的销售数据
     */
    public function statistics() {
        //默认显示
        //昨天订单
        //状态[未配货][配货中]
        $where = array();
        $time_start = I('post.time_start', date('Y-m-d 00:00', strtotime('-1 day')));
        $time_end = I('post.time_end', date('Y-m-d 00:00'));
        $_POST['time_start'] = $time_start;
        $_POST['time_end'] = $time_end;
        $status_id = I('post.status_id');
        $shippingId = I('post.shipping_id');
        $department_ids = I('post.id_department');
        $warehouse_id = I('post.warehouse_id');
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where['id_department'] = isset($department_ids) && $department_ids != '' ? array('EQ', $department_ids) : array('IN', $department_id);
        if ($shippingId) {
            $where[] = "`id_shipping` = '$shippingId'";
        }
        if ($department_ids) {
            $where[] = "`id_department` = '$department_ids'";
        }
        if ($warehouse_id) {
            $where[] = "`id_warehouse` = '$warehouse_id'";
        }
        if ($status_id > 0) {
            $where[] = "`id_order_status` = '$status_id'";
        } else {
            $where[] = "`id_order_status` IN (4,5,6,7,8,9,10)"; //只需要有效订单
        }
        if ($time_start)
            $where[] = "`created_at` >= '$time_start'";
        if ($time_end)
            $where[] = "`created_at` < '$time_end'";

        $warehouse = M('Warehouse')->select();
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
                ->field($order_table . '.id_order AS order_id, id_order_status,id_shipping,i.id_product,i.id_product_sku, i.quantity,
                i.product_title,i.sku_title, i.id_order_item order_item_id, i.sku')
                ->join("__ORDER_ITEM__ i ON (__ORDER__.id_order = i.id_order)", 'INNER')
                ->where($where)
                ->order('i.sku ASC')
                ->select();

        $count = 0;
        $stat = array();
        $stat_shipping = array();
        $stat_product = array();
        $order_count = array(); //订单总数
        $product_count = 0; // 产品总数
        $tempProModel = array();
        foreach ($orders as $o) {
            $order_count[] = $o['order_id'];
//            if (isset($shippings[$o['id_shipping']])) {
//                $shipping_name = $shippings[$o['id_shipping']]['title'];
//            }else{
//                $shipping_name = '无物流';
//            }
            $shipping_name = '无物流';

            $img = json_decode($products[(int) $o['id_product']]['thumbs'], true);
            //直接使用产品的内部名称
            $product_name = $products[(int) $o['id_product']]['inner_name'];
            if (empty($product_name))
                $product_name = $products[(int) $o['id_product']]['title'];

            if (!isset($stat[$shipping_name][$product_name])) {
                $stat[$shipping_name][$product_name] = array();
            }

            $attrIdMd5 = '';
            if (!isset($stat[$shipping_name][$product_name][$o['sku_title']])) {
                $stat[$shipping_name][$product_name][$o['sku_title']]['qty'] = (int) $o['quantity'];
            } else {
                $stat[$shipping_name][$product_name][$o['sku_title']]['qty'] += (int) $o['quantity'];
            }
//            $stat[$shipping_name][$product_name][$o['sku_title']]['status_title'] = D('Order/OrderStatus')->where('id_order_status='.$o['id_order_status'])->getField('title');
            $stat[$shipping_name][$product_name][$o['sku_title']]['img'] = $img['photo'][0]['url'];
            $stat[$shipping_name][$product_name][$o['sku_title']]['sku'] = $o['sku'];

            $attrIdMd5 = md5($product_name . $o['sku_title']);

            if ($o['id_product_sku']) {
                $getSkuModel = D("Common/ProductSku")->cache(true, 3600)->find($o['id_product_sku']);
                $tempProModel[$attrIdMd5] = $getSkuModel['sku'];
            } else {
                $getSkuModel = D("Common/ProductSku")->cache(true, 3600)->find($o['id_product']);
                $tempProModel[$attrIdMd5] = $getSkuModel['sku'];
            }

            $product_count += (int) $o['quantity'];
        }

        foreach ($stat as $sp_name => $p_s) {
            ksort($stat[$sp_name]);
        }

        //计算物流与产品数
        foreach ($stat as $sp_name => $p_s) {
            if (!isset($stat_shipping[$sp_name]))
                $stat_shipping[$sp_name] = 0;
            foreach ($p_s as $p_name => $p_pro) {
                if (!isset($stat_product[$sp_name . $p_name]))
                    $stat_product[$sp_name . $p_name] = 0;
                $stat_product[$sp_name . $p_name] += count($p_pro);
                $stat_shipping[$sp_name] += count($p_pro);
            }
        }

        $order_count = array_unique($order_count);
        $order_count = count($order_count);
        $department_id = $_SESSION['department_id'];
        $department = D('Department/Department')->where('type=1')->cache(true, 3600)->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
        $status_model = D('Order/OrderStatus')->field('id_order_status,title')->where('status=1 and id_order_status IN (4,5,6,7,8,9,10)')->select();
        $status_model = array_column($status_model, 'title', 'id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看销售统计');
        $this->assign('shippings', $shippings);
        $this->assign('status_list', $status_model);
        $this->assign('statistics', $stat);
        $this->assign('stat_shipping', $stat_shipping);
        $this->assign('stat_product', $stat_product);
        $this->assign('product_count', $product_count);
        $this->assign('order_count', $order_count);
        $this->assign('post', $_POST);
        $this->assign('attr_sku', $tempProModel);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign('warehouse', $warehouse);
        $this->display();
    }

    //每日统计
    public function every_day() {
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != '' ? array('EQ', $_GET['id_department']) : array('IN', $department_id);
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time'])
                $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $create_at[] = array('LT', $_GET['end_time']);
            $where['created_at'] = $create_at;
        }else {
            $where['id_order_status'] = array('GT', 0);
        }
        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = $_GET['id_department'];
        }

        $field = "SUBSTRING(created_at,1,10) AS set_date,SUM(IF(`id_order_status` IN(2,3,4,5,6,7,8,9,10,16),1,0)) as effective,
        count(id_order) as total,
        SUM(IF(`id_order_status` IN(10,11,12,13,14,15),1,0)) as invalid,
        SUM(IF(`id_order_status`=1,1,0)) AS status1,SUM(IF(`id_order_status`=2,1,0)) AS status2,
        SUM(IF(`id_order_status`=3,1,0)) AS status3,SUM(IF(`id_order_status`=4,1,0)) AS status4,
        SUM(IF(`id_order_status`=5,1,0)) AS status5,SUM(IF(`id_order_status`=6,1,0)) AS status6,
        SUM(IF(`id_order_status`=7,1,0)) AS status7,SUM(IF(`id_order_status`=8,1,0)) AS status8,
        SUM(IF(`id_order_status`=9,1,0)) AS status9,
        SUM(IF(`id_order_status`=10,1,0)) AS status10,SUM(IF(`id_order_status`=11,1,0)) AS status11,
        SUM(IF(`id_order_status`=12,1,0)) AS status12,SUM(IF(`id_order_status`=13,1,0)) AS status13,
        SUM(IF(`id_order_status`=14,1,0)) AS status14,SUM(IF(`id_order_status`=15,1,0)) AS status15
        ";
        $count = $ordModel->field($field)->where($where)
                        ->order('set_date desc')
                        ->group('set_date')->select();
        $page = $this->page(count($count), 20);
        $selectOrder = $ordModel->field($field)->where($where)->order('set_date desc')
                        ->group('set_date')->limit($page->firstRow . ',' . $page->listRows)->select();

        $department_id = $_SESSION['department_id'];
        $department = D('Department/Department')->where('type=1')->cache(true, 3600)->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看状态统计');
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("list", $selectOrder);
        //$this->assign("shipping",$shipping);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    //搜索提示供应商
    public function get_supp_attr() {
        $keyword = $_POST['value'];
        if (!empty($keyword)) {
            $map['title'] = array('like', '%' . $keyword . '%');
            $supplier = M('Supplier')->field('id_supplier,title')->where($map)->select();
            if ($supplier) {
                $data = '<ul>';
                foreach ($supplier as $value) {
                    $data .= '<li><a class="sup' . $value['id_supplier'] . '" href="javascript:;" onclick="get_supp(' . $value['id_supplier'] . ')" >' . $value['title'] . '</a></li>';
                }
                $data .= '</ul>';
            } else {
                $data = 0;
            }
        } else {
            $data = 0;
        }
        echo $data;
    }

    //搜索提示产品名称
    public function get_product_title() {
        $keyword = $_POST['value'];
        $id_department = $_POST['id_department'];
        if (!empty($keyword)) {
            $where['inner_name'] = array('like', '%' . $keyword . '%');
            if ($id_department)
                $where['id_department'] = array('EQ', $id_department);
            $product = M('Product')->field('id_product,inner_name')->where($where)->select();
            if ($product) {
                $data = '<ul>';
                foreach ($product as $value) {
                    $data .= '<li><a class="pro' . $value['id_product'] . '" href="javascript:;" onclick="get_pro_param(' . $value['id_product'] . ')" >' . $value['inner_name'] . '</a></li>';
                }
                $data .= '</ul>';
            } else {
                $data = 0;
            }
        } else {
            $data = 0;
        }
        echo $data;
    }

    /*
     * 待审核的采购列表
     */

    public function waiting_approval() {
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $supplier = M('Supplier')->getField('id_supplier,title');
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $where['purchase_no'] = $_GET['purchase_no'];
        }
        if (isset($_GET['id_users']) && $_GET['id_users']) {
            $id_users = M('Users')->where(array('user_nicename' => $_GET['id_users']))->getField('id');
            $where['id_users'] = $id_users;
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $id_purchase = M('PurchaseProduct')->alias('pp')
                    ->field('id_purchase')
                    ->join('__PRODUCT_SKU__ ps on ps.id_product_sku = pp.id_product_sku')
                    ->where(array('sku' => $_GET['sku']))
                    ->getField('id_purchase', true);
            $new = '';
            foreach ($id_purchase as $k => $v) {
                $new .= 'id_purchase = ' . $v . ' OR ';
            }
            $where[] = substr($new, 0, -3);
        }
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['created_at'] = $created_at_array;
        }

        $where['status'] = '1';
        $lists = $this->Purchase->where($where)->select();
        foreach ($lists as $key => $list) {
            $purchase_channel = '';
            switch ($list['purchase_channel']) {
                case 1: $purchase_channel = '阿里巴巴 ';
                    break;
                case 2: $purchase_channel = '淘宝 ';
                    break;
                case 3: $purchase_channel = '线下 ';
                    break;
                default:$purchase_channel = '空 ';
            }
            $lists[$key]['purchase_channel'] = $purchase_channel;
            $where_pro['id_purchase'] = $list['id_purchase'];
            $lists[$key]['purchase_product'] = $this->PurchaseProduct->alias('pp')
                            ->join('__PRODUCT_SKU__ as ps on ps.id_product_sku = pp.id_product_sku', 'LEFT')
                            ->where($where_pro)->select();
            $lists[$key]['user_nicename'] = M('Users')->where(array('id' => $list['id_users']))->getField('user_nicename');
        }
        $this->assign('warehouse', $warehouse);
        $this->assign('supplier', $supplier);
        $this->assign('lists', $lists);
        $this->display();
    }

    /*
     * 批量审核采购订单
     */

    public function check_purchase() {
        $purchase_no = $_GET['purchase_no'];
        $check = $_GET['check'];
        if ($check == 'pass') {
            $data['status'] = 2;
            $data['updated_at'] = date('Y-m-d H:i:s');
        } elseif ($check == 'refuse') {
            $data['status'] = 3;
            $data['remark'] = $_GET['reason'];
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $result = $this->Purchase->where(array('purchase_no' => array('IN', $purchase_no)))->save($data);
        if ($result) {
            $flag = 0;
            $msg = '审核完成';
            echo json_encode(array('flag' => $flag, 'msg' => $msg));
        } else {
            $flag = 1;
            $msg = '审核失败';
            echo json_encode(array('flag' => $flag, 'msg' => $msg));
        }
    }

    /*
     * 逐个审核采购单
     */

    public function check_single() {
        if (IS_GET) {
            $id_purchase = $_GET['id_purchase'];
            $list = $this->Purchase->where(array('id_purchase' => $id_purchase))->find();
            $purchase_channel = '';
            switch ($list['purchase_channel']) {
                case 1: $purchase_channel = '阿里巴巴 ';
                    break;
                case 2: $purchase_channel = '淘宝 ';
                    break;
                case 3: $purchase_channel = '线下 ';
                    break;
                default:$purchase_channel = '空 ';
            }
            $list['purchase_channel'] = $purchase_channel;
            $list['purchase_product'] = $this->PurchaseProduct->alias('pp')
                            ->join('__WAREHOUSE_PRODUCT__ as wp on wp.id_product_sku = pp.id_product_sku', 'LEFT')
                            ->join('__PRODUCT_SKU__ as ps on ps.id_product_sku = pp.id_product_sku', 'LEFT')
                            ->where(array('id_purchase' => $id_purchase))->select();
        }
        if (IS_POST) {
            $check = $_POST['check'];
            $id_purchase = $_POST['id_purchase'];
            if ($check == 'pass') {
                $data['status'] = '2';
                $data['updated_at'] = date('Y-m-d H:i:s');
            }

            if ($check == 'refuse') {
                $data['status'] = '3';
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            $result = $this->Purchase->where(array('id_purchase' => $id_purchase))->save($data);
            if ($result) {
                $this->success("审核完成！", U('index/waiting_approval'));
            } else {

                $this->error("审核失败！", U('index/check_single', array('id_purchase' => $id_purchase)));
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 6, 3, '审核采购单');
        $this->assign('list', $list);
        $this->display();
    }

    /*
     * 导出采购单
     */

    public function export_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '采购单号', '仓库', '供应商', '产品名', 'SKU', '单价', '采购金额',
            '采购数量', '总金额', '审核状态',
            '采购渠道', '创建人', '创建时间', '备注'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $where['purchase_no'] = $_GET['purchase_no'];
        }
        if (isset($_GET['id_users']) && $_GET['id_users']) {
            $id_users = M('Users')->where(array('user_nicename' => $_GET['id_users']))->getField('id');
            $where['id_users'] = $id_users;
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $id_purchase = M('PurchaseProduct')->alias('pp')
                    ->field('id_purchase')
                    ->join('__PRODUCT_SKU__ ps on ps.id_product_sku = pp.id_product_sku')
                    ->where(array('sku' => $_GET['sku']))
                    ->getField('id_purchase', true);
            $new = '';
            foreach ($id_purchase as $k => $v) {
                $new .= 'id_purchase = ' . $v . ' OR ';
            }
            $where[] = substr($new, 0, -3);
        }
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['created_at'] = $created_at_array;
        }

        $where['status'] = '1';
        $purchases = $this->Purchase
                        ->where($where)
                        ->order("id_purchase ASC")
                        ->limit(10000)->select();
        $warehouse = M('Warehouse')->where(array('statsu' => 1))->getField('id_warehouse,title');
        $supplier = M('Supplier')->getField('id_supplier,title');
        foreach ($purchases as $o) {
            $user = M('Users')->where(array('id' => $o['id_users']))->getField('user_nicename');
            $purchase_product = $this->PurchaseProduct->where(array('id_purchase' => $o['id_purchase']))->select();
            $purchase_channel = '';
            switch ($o['purchase_channel']) {
                case 1: $purchase_channel = '阿里巴巴 ';
                    break;
                case 2: $purchase_channel = '淘宝 ';
                    break;
                case 3: $purchase_channel = '线下 ';
                    break;
                default:$purchase_channel = '空 ';
            }
            foreach ($purchase_product as $product) {
                $sku = M('ProductSku')->where(array('id_product_sku' => $product['id_product_sku']))->find();
                $data[] = array(
                    $o['purchase_no'], $warehouse[$o['id_warehouse']], $supplier[$o['id_supplier']], $sku['title'], $sku['sku'], '单价', $product['price'],
                    $product['quantity'], $o['price'], '待审核', $purchase_channel, $user, $o['created_at'], $o['remark']
                );
            }
        }
        if ($data) {
            foreach ($data as $items) {
                $j = 65;
                foreach ($items as $col) {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                    ++$j;
                }
                ++$idx;
            }
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出采购单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '采购单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '采购单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    /*
     * 采购预警页面
     */

    public function purchase_warning() {
        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where['p.id_department'] = $_GET['department_id'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where_pro['id_warehouse'] = $_GET['id_warehouse'];
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $where['sku'] = $_GET['sku'];
        }
        if (isset($_GET['title']) && $_GET['title']) {
            $where_p[] = "title like '%" . $_GET['title'] . "%'";
        }
        $department = M('Department')->where('type=1')->select();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $where['id_order_status'] = 6;
        $stockout = M('Order')->field('count(o.id_order) as stockout,oi.id_product_sku,oi.id_product,oi.sku_title,oi.sku,p.id_department')->alias('o')
                ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order')
                ->join('__PRODUCT__ as p on p.id_product = oi.id_product')
                ->where($where)
                ->group('id_product_sku')
                ->order('stockout DESC')
                ->select();
        $newarr = array();
        foreach ($stockout as $key => $value) {
            $where_pro['id_product_sku'] = $value['id_product_sku'];
            $where_p['id_product'] = $value['id_product'];
            $product = M('Product')->where($where_p)->field('title,inner_name')->find();
            $warehouse_product = M('WarehouseProduct')->where($where_pro)->select();
            $stockout[$key]['warehouse_product'] = $warehouse_product;
            $newarr[] = array_merge($stockout[$key], $product);
        }

        $this->assign('newarr', $newarr);
        $this->assign('department', $department);
        $this->assign("warehouse", $warehouse);
        $this->display();
    }

    /*
     * 导出采购预警
     */

    public function export_warning() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '采购SKU', '产品名', '内部名', '采购单价', '仓库', '库存', '在途量',
            '近三日销量', '日均量', '缺货量'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where['p.id_department'] = $_GET['department_id'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where_pro['id_warehouse'] = $_GET['id_warehouse'];
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $where['sku'] = $_GET['sku'];
        }
        if (isset($_GET['title']) && $_GET['title']) {
            $where_p[] = "title like '%" . $_GET['title'] . "%'";
        }
        $department = M('Department')->where('type=1')->select();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $where['id_order_status'] = 6;
        $stockout = M('Order')->field('count(o.id_order) as stockout,oi.id_product_sku,oi.id_product,oi.sku_title,oi.sku,p.id_department')->alias('o')
                ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order')
                ->join('__PRODUCT__ as p on p.id_product = oi.id_product')
                ->where($where)
                ->group('id_product_sku')
                ->order('stockout DESC')
                ->limit(10000)
                ->select();
        $warehouse = M('Warehouse')->where(array('statsu' => 1))->getField('id_warehouse,title');
        $stockoutc = array();
        foreach ($stockout as $o) {
            $where_pro['id_product_sku'] = $o['id_product_sku'];
            $where_p['id_product'] = $o['id_product'];
            $products = M('Product')->where($where_p)->field('title,inner_name')->find();
            $warehouse_product = M('WarehouseProduct')->where($where_pro)->select();
            foreach ($warehouse_product as $product) {
                $data[] = array(
                    $o['sku'], $products['title'], $products['inner_name'], '采购单价', $warehouse[$product['id_warehouse']], $product['quantity'], $product['road_num'], '近三日销量', '日均量', $o['stockout']
                );
            }
        }
        if ($data) {
            foreach ($data as $items) {
                $j = 65;
                foreach ($items as $col) {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                    ++$j;
                }
                ++$idx;
            }
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出采购预警列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '采购预警信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '采购预警信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

}
