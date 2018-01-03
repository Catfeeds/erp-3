<?php

namespace Purchase\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use Purchase\Lib\PurchaseStatus;
//header("content-type:text/html;charset=utf-8;");
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
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
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

    /**
     * 采购单列表
     */
    public function index() {
        $dep = $_SESSION['department_id'];
        $_SESSION['return_purchase_index_url']= $_SERVER['REQUEST_URI'];
        if($_GET['isajax']==1){//ajax结算统计信息
            $ajaxdata=[];
            if($_GET['data']){
                foreach ($_GET['data'] as $dataval){
                    $ajaxdata[$dataval['name']]=$dataval['value'];
                }
                $_GET=  array_merge($_GET,$ajaxdata);
            }
        }
        if (isset($_GET['depart_id']) && $_GET['depart_id']) {
            $where['p.id_department'] = array('EQ', $_GET['depart_id']);
        } else {
            $where['p.id_department'] = array('IN', $dep);
        }
        if (isset($_GET['ware_id']) && $_GET['ware_id']) {
            $where['p.id_warehouse'] = array('EQ', $_GET['ware_id']);
        }
        if (isset($_GET['status_id']) && $_GET['status_id']) {
            $where['p.status'] = $_GET['status_id'];
        }
        if (isset($_GET['shipping_no']) && $_GET['shipping_no']) {
            $where['p.shipping_no'] = $_GET['shipping_no'];
        }
        if (isset($_GET['pur_num']) && $_GET['pur_num']) {
            $pur_num=  trim($_GET['pur_num']);
            $where['p.purchase_no'] = array('like', "%{$pur_num}%");
        }

        //增加采购员名字筛选
        if (isset($_GET['shop_id']) && $_GET['shop_id']) {
            $where['p.id_users'] = array('EQ', $_GET['shop_id']);
        }
        /*if (isset($_GET['id_users']) && $_GET['id_users']) {
            $where['p.id_users'] = array('EQ', $_GET['id_users']);
        }*/
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
        }else{
            $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-7 days')));
            $createAtArray[] = array('LT', date('Y-m-d', strtotime('+1 day')));
        }
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['p.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if ($_GET['alibaba_no']) {
            $alibaba_no=  trim($_GET['alibaba_no']);
            $where['p.alibaba_no'] = array('like', "%{$alibaba_no}%");
        }
        $purchaseTimeArray = array();
        if (isset($_GET['start_purchase_time']) && $_GET['start_purchase_time']) {
            $purchaseTimeArray[] = array('EGT',$_GET['start_purchase_time']);
        }
        if(isset($_GET['end_purchase_time']) && $_GET['end_purchase_time']) {
            $purchaseTimeArray[] = array('LT',$_GET['end_purchase_time']);

        }
        if(!empty($purchaseTimeArray)){
            $where['p.inner_purchase_time'] =$purchaseTimeArray;
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $id_purchase = M('PurchaseProduct')->alias('pp')
                ->field('id_purchase')
                ->join('__PRODUCT_SKU__ ps on ps.id_product_sku = pp.id_product_sku')
                ->where(array('sku' => $_GET['sku']))
                ->getField('id_purchase', true);
            $new = '';
            foreach ($id_purchase as $k => $v) {
                $new .= 'p.id_purchase = ' . $v . ' OR ';
            }
            if(!empty($id_purchase)){
                $where[] = substr($new, 0, -3);
            }else{
                //为空就找不到
                $where['p.id_purchase'] =-1;
            }

        }
        if (isset($_GET['pro_name']) && $_GET['pro_name']) { //内部产品名搜索存在
            $pro_name  = I('get.pro_name');
            $wherepr['inner_name'] = array('like', "%{$pro_name}%");
            $products = M('Product')->field('id_product,inner_name')->where($wherepr)->select(); //根据内部产品名获取产品id
            foreach($products as $k=>$v){
                $product_purchase[] = M('PurchaseProduct')->field('id_purchase,id_product')->where(array('id_product'=>$v['id_product']))->select(); //根据产品id_product获取 id_purchase
            }
            $new = '';
            foreach ($product_purchase as $key => $val) {
                foreach($val as $k=>$v){
                    $new .= 'p.id_purchase = ' . $v['id_purchase'] . ' OR ';
                }

            }
            if(!empty($new)){
                $where[] = substr($new, 0, -3);
            }else{
                //为空就找不到
                $where['p.id_purchase'] =-1;
            }
        }

        $where[] = array('p.created_at' => $createAtArray);
        //echo '<pre>';print_r($where);exit;
        $department = M('Department')->where(array('id_users' => $user_id))->find();

        $users = M('Users')->field('id,user_nicename')->where(array('id_user' => $user_id,'user_status'=>1))->select();
        $users = array_column($users, 'user_nicename', 'id');
        $flag = 1;
        if (!$department) {
            $flag = 2;
        }
        $model = new \Think\Model();
        $count = $model->table($this->Purchase->getTableName() . ' p')->join(array($this->Users->getTableName() . ' u on p.id_users = u.id'))->field('p.*,u.user_nicename')
                ->where($where)->count();
        $page = $this->page($count, 20);

        $list = $model->table($this->Purchase->getTableName() . ' p')
            ->join(array($this->Users->getTableName() . ' u on p.id_users = u.id'))
            ->field('p.*,u.user_nicename')
            ->where($where)
            ->order("p.id_purchase DESC")
            ->limit($page->firstRow, $page->listRows)
            ->select();
        if($_GET['isajax']==1){//ajax结算统计信息
            //统计信息
            if($where['p.status']){
                $where_status['p.status']=$where['p.status'];
            }else{
               $where_status['p.status']=array('NEQ',10);
            }
            // echo json_encode($where);exit;
            $ppTable = D("PurchaseProduct")->getTableName();
            $statisticsInfo=$model->table($this->Purchase->getTableName() . ' p')
                    ->join("$ppTable as pp on pp.id_purchase=p.id_purchase",'left')
                    ->field('count(DISTINCT(p.id_purchase)) as totalcnt,sum(pp.quantity) as totalpp')
                    ->where($where)->where($where_status)->find();

            $statisticsInfo['totalprice']=$model->table($this->Purchase->getTableName() . ' p')->where($where)->where($where_status)->getField('sum(p.price) as totalprice');
            //新增一个运费总价的统计   liuruibin   20171010
            $statisticsInfo['totalshipping']=$model->table($this->Purchase->getTableName() . ' p')->where($where)->where($where_status)->getField('sum(p.price_shipping) as totalshipping'); echo 1;die;
//            var_dump($model->getLastSql());die();
            echo json_encode($statisticsInfo);
            exit();
            //统计信息
//            $ppTable = D("PurchaseProduct")->getTableName();
//            $statisticsInfo=$model->table($this->Purchase->getTableName() . ' p')
//                    ->join("$ppTable as pp on pp.id_purchase=p.id_purchase",'left')
//                    ->field('count(DISTINCT(p.id_purchase)) as totalcnt,sum(pp.quantity) as totalpp')
////                    ->field('count(DISTINCT(p.id_purchase)) as totalcnt,sum(pp.quantity) as totalpp,truncate(sum(pp.quantity*pp.price)+p.price_shipping,4) as totalprice')
//                ->group('p.id_purchase')
//                ->where($where)->find();
//
//            $statisticsInfo2['totalcnt']=0;
//            $statisticsInfo2['totalpp']=0;
//            $statisticsInfo2['totalprice']=0;
//
//            foreach($statisticsInfo as $v){
//                $statisticsInfo2['totalcnt']= $statisticsInfo2['totalcnt']+$v['totalcnt'];
//                $statisticsInfo2['totalpp']= $statisticsInfo2['totalpp']+$v['totalpp'];
////                $statisticsInfo2['totalprice']= $statisticsInfo2['totalprice']+$v['totalprice'];
//            }
//            $statisticsInfo2['totalprice']=$model->table($this->Purchase->getTableName() . ' p')->where($where)->getField('sum(p.price) as totalprice');
//            echo json_encode($statisticsInfo2);
//            exit();
        }
        
        $pur_status = M('PurchaseStatus')->field('id_purchase_status,title')->select(); //采购单状态
        $pur_status = array_column($pur_status, 'title', 'id_purchase_status');

        $sup_id = array();
        $ware_id = array();
        $shop_id = array();
        //echo '<pre>';print_r($list);exit;
        foreach ($list as $k => $v) {
//            var_dump($v);die();
            $sup_id[] = $v['id_supplier'];
            $ware_id[] = $v['id_warehouse'];
            $shop_id[] = $v['id_shop'];

            $list[$k]['product'] = $this->get_pur_pro($v['id_purchase']);

            $list[$k]['pro_name'] = $this->get_pur_pro($v['id_purchase'], true);
            $list[$k]['status_name'] = $pur_status[$v['status']];
            $list[$k]['totalprice']=  array_sum(array_column($list[$k]['product'], 'itemtotalprice'))+$list[$k]['price_shipping'];

            switch ($v['purchase_channel']) {
                case 1:
                    $pur_channel_name = '阿里巴巴';
                    break;
                case 2:
                    $pur_channel_name = '淘宝';
                    break;
                case 3:
                    $pur_channel_name = '线下';
                    break;
                default :
                    $pur_channel_name = '';
            }
            $list[$k]['pur_channel_name'] = $pur_channel_name;
        }
        foreach ($sup_id as $k => $v) {
            $supplier_name = D("Common/Supplier")->field('title as sup_title')->where('id_supplier=' . $v)->find();
            $warehouse = M('Warehouse')->field('title as ware_title')->where('id_warehouse=' . $ware_id[$k])->find();
            $list[$k]['sup_name'] = $supplier_name['sup_title'];
            $list[$k]['ware_name'] = $warehouse['ware_title'];
        }
        $ware = M('Warehouse')->where(array('status' => 1))->cache(true, 3600)->select();
        $depart = M('Department')->where(array('type' => 1))->where(array('id_department'=>array('in',$dep)))->order('sort asc')->select();
        //查询所有采购部人员
        $shop_users = M()->query("SELECT a.id,a.user_nicename,b.* FROM erp_users AS a LEFT JOIN erp_department_users AS b ON a.id=b.id_users WHERE b.id_department=19");
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看采购订单列表');
        //dump($list);exit;
        $this->assign("proList", $list);
        $this->assign("page", $page->show('Admin'));
        $this->assign('ware', $ware);
        $this->assign('statisticsInfo', $statisticsInfo);
        $this->assign('pur_status', $pur_status);
        $this->assign('depart', $depart);
        $this->assign('flag', $flag);
        $this->assign('shop_users', $shop_users); //所有采购部人员
        $this->assign('users', $users);
        $this->display();
    }

    protected function get_pur_pro($pur_id, $is_name = false) {
        $result = M('PurchaseProduct')->field('id_product,option_value,id_product_sku,quantity,price,received,truncate(quantity*price,4) as itemTotalPrice')->where(array('id_purchase' => $pur_id))->select();
        $pro_name = array();
        foreach ($result as $key => $val) {
            $result[$key]['sku'] = M('ProductSku')->where(array('id_product_sku' => $val['id_product_sku']))->getField('sku');
            $wherep['id_product'] =  $val['id_product'];
            $pdata= M('Product')->field('inner_name,id_users,sale_price,title')->where($wherep)->find();
            $result[$key]['pro_name'] = $pdata['inner_name'];
            $result[$key]['one_price'] = $pdata['sale_price'];
            $result[$key]['id_users_sales'] = $pdata['id_users']; //获取广告员的ID
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
            $data['inner_purchase_no'] = null;
            $data['alibaba_no'] = null;
            $result = D("Common/Purchase")->data($data)->save();
            if ($result) {
                $flag['flag'] = 1;
                $flag['msg'] = '取消成功';
            } else {
                $flag['flag'] = 0;
                $flag['msg'] = '取消失败';
            }
            D("Purchase/PurchaseStatus")->add_pur_history($id, PurchaseStatus::CANCLE, '取消采购单');
            add_system_record(sp_get_current_admin_id(), 2, 3, '取消采购订单');
            echo json_encode($flag);
            exit();
        }
    }

    /**
     * 新建采购单页面
     */
    public function create() {
        if($_GET){
            $id_product_sku = $_GET['id_product_sku'];
            $id = $_GET['id_product']; //产品Id
//            $id_purchase = I('post.id_purchase/i');//采购id 编辑时才有
//            $warehouse_id = I('post.warehouse_id/i'); //仓库id
            $product = M('Product')->field('inner_name,id_department,id_product')->where(array('id_product'=>$id))->find();

            $load_product = D("Common/Product")->find($id);
            $sku_where = array('id_product_sku' => array('IN',$id_product_sku), 'status' => 1);
            $all_child_sku = D("Common/ProductSku")->where($sku_where)->select();
            $product_row = '<tr class="productBox' . $id . '"><td colspan="10" style="background-color: #f5f5f5;">' . $load_product['inner_name'] . '</td></tr>
        <tr class="headings productBox' . $id . '"><th>SKU</th><th>属性</th><th>采购单价</th><th>数量</th><th>可用库存</th><th>实际库存</th><th>在途数量</th><th>缺货量</th><th>近三日销量</th><th>日均销量</th></tr>';
            $tarray = array();
            $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
            $etime = date('Y-m-d 00:00:00');
            $tarray[] = array('EGT', $stime);
            $tarray[] = array('LT', $etime);
            $twhere[] = array('created_at' => $tarray);

            $status = array(                //需计算的订单状态
                OrderStatus::UNPICKING,     //未配货
                OrderStatus::PICKED,        //已配货
                OrderStatus::APPROVED,      //已审核
            );

            foreach ($all_child_sku as $c_key => $c_item) {
                $get_status = true;
                if ($get_status) {
                    $pur_pro = M('PurchaseProduct')->where(array('id_purchase'=>$id_purchase,'id_product'=>$id,'id_product_sku'=>$c_item['id_product_sku']))->find();
                    $set_qty = isset($pur_pro['price']) ? $pur_pro['price'] : '';
                    $set_price = isset($pur_pro['quantity']) ? $pur_pro['quantity'] : '';
                    $count_price = isset($pur_pro) ? $pur_pro['quantity']*$pur_pro['price'] : 0;
                    $where['id_product'] = $id;
                    $where['id_product_sku'] = $c_item['id_product_sku'];
                    $wp_where['id_warehouse'] = !empty($warehouse_id) ? $warehouse_id : 1;
                    $warehouse_pro = M('WarehouseProduct')->field('quantity,road_num')->where($where)->where($wp_where)->find();
                    //计算实际库存
                    $warehouse_pro['quantity'] = empty($warehouse_pro['quantity']) ? 0 : $warehouse_pro['quantity'];
                    $actual_quantity = M('Order')->alias('o')
                        ->field("SUM(oi.quantity) AS actual_quantity")
                        ->join("__ORDER_ITEM__ as oi ON o.id_order=oi.id_order", 'left')
                        ->where(array('oi.id_product_sku'=>$c_item['id_product_sku']))
                        ->where(array('o.id_order_status'=> array('IN', $status)))
                        ->find();
                    $actual_quantity = empty($actual_quantity['actual_quantity']) ? 0 : $actual_quantity['actual_quantity'] + $warehouse_pro['quantity'];
                    //sku缺货量
                    $swhere['id_order_status'] = 6;
                    $sku_result = M('Order')->alias('o')->field('COUNT(*) as count_qty')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($swhere)->find();
                    //三日平均销量
                    $twhere['id_order_status'] = array('IN', OrderStatus::get_effective_status());
                    $od_sale = M('Order')->alias('o')->field('COUNT(*) as count')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($twhere)->find();
                    //近三日销量
                    $td_sale = $this->get_three_sale($id, $c_item['id_product_sku']);
                    $product_row .= '<tr class="productBox' . $id . '" data-sku-id="'.$c_item['sku'].'"><input type="hidden" value="' . $c_item['title'] . '" name="attr_name[' . $id . '][' . $c_item['id_product_sku'] . ']"/>' .
                        '<td>' . $c_item['sku'] . '</td> ' .
                        '<td>' . $c_item['title'] . '</td>' .
                        '<td><input type="text" class="sprice dsprice sprice' . $c_item['id_product_sku'] . '" value="' . $set_price . '" name="set_price[' . $id . '][' . $c_item['id_product_sku'] . ']" onchange="price_change(' . $c_item['id_product_sku'] . ')"/></td>' .
                        '<td><input type="text" class="sqt dsqt sqt' . $c_item['id_product_sku'] . '" value="' . $set_qty . '" name="set_qty[' . $id . '][' . $c_item['id_product_sku'] . ']" onchange="qty_change(' . $c_item['id_product_sku'] . ')"/></td>' .
                        '<input type="hidden" class="hid_p hid_p' . $c_item['id_product_sku'] . '" value="">'.
//                        '<td><span class="cprice cprice' . $c_key . '">' . $count_price . '</span></td>' .
                        '<td>' . $warehouse_pro['quantity'] . '</td>' .
                        '<td>' . $actual_quantity . '</td>' .
                        '<td>' . $warehouse_pro['road_num'] . '</td>' .
                        '<td>' . $sku_result['count_qty'] . '</td>' .
                        '<td>' . $td_sale . '</td>' .
                        '<td>' . round($od_sale['count'] / 3, 2) . '</td>' .
                        '</tr>';
                }
            }
            $product_row = json_encode($product_row);
            $this->assign('product_row',$product_row);
            $this->assign('product',$product);


        }
        $dep = $_SESSION['department_id'];
        $map['id_department'] = array('IN', $dep);
        $where['title'] = array('NOT IN', '');
        //产品多的时候需要用文本框输入，然后ajax搜索了 ,目前产品不多使用select下拉
        $product = D("Common/Product")->where($map)->where($where)->order('id_product desc')->select();
        $supplier = D("Common/Supplier")->where($map)->select();
        $warehouse = D("Common/Warehouse")->select();
        $department = D("Common/Department")->where($map)->where('type=1')->order('sort asc')->select();
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
        $id_purchase = I('post.id_purchase/i');//采购id 编辑时才有
        $warehouse_id = I('post.warehouse_id/i'); //仓库id
        $pro_table_name = D("Common/Product")->getTableName();

        $model = new \Think\Model;

        $load_product = D("Common/Product")->find($id);
        $sku_where = array('id_product' => $id, 'status' => 1);
        $all_child_sku = D("Common/ProductSku")->where($sku_where)->select();

        //<span class="deleteBox" delete="productBox' . $id . '" title="删除" style="margin-right:10px;font-size:20px;color:red;cursor: pointer;">x</span>
        $product_row = '<tr class="productBox' . $id . '"><td colspan="10" style="background-color: #f5f5f5;">' . $load_product['inner_name'] . '<span class="deleteBox'.$id.'" delete="productBox' . $id . '" title="删除" style="margin-right:10px;font-size:20px;color:red;cursor: pointer;">x</span></td></tr>
        <tr class="headings productBox' . $id . '"><th>SKU</th><th>属性</th><th>采购单价</th><th>数量</th><th>可用库存</th><th>实际库存</th><th>在途数量</th><th>缺货量</th><th>近三日销量</th><th>日均销量</th></tr>';

        $tarray = array();
        $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
        $etime = date('Y-m-d 00:00:00');
        $tarray[] = array('EGT', $stime);
        $tarray[] = array('LT', $etime);
        $twhere[] = array('created_at' => $tarray);

        $status = array(                //需计算的订单状态
            OrderStatus::UNPICKING,     //未配货
            OrderStatus::PICKED,        //已配货
            OrderStatus::APPROVED,      //已审核
        );

        foreach ($all_child_sku as $c_key => $c_item) {//子SKU数据
//            $option_val = explode(',', $c_item['option_value']);
            $get_status = true;
            if ($get_status) {
                $pur_pro = M('PurchaseProduct')->where(array('id_purchase'=>$id_purchase,'id_product'=>$id,'id_product_sku'=>$c_item['id_product_sku']))->find();

                $set_qty = isset($pur_pro['quantity']) ? $pur_pro['quantity'] : '';
                $set_price = isset($pur_pro) ? $pur_pro['quantity']*$pur_pro['price'] : 0;
                $count_price = isset($pur_pro['price']) ? $pur_pro['price'] : '';
                $where['id_product'] = $id;
                $where['id_product_sku'] = $c_item['id_product_sku'];
                $wp_where['id_warehouse'] = !empty($warehouse_id) ? $warehouse_id : 1;
                $warehouse_pro = M('WarehouseProduct')->field('quantity,road_num')->where($where)->where($wp_where)->find();

                //计算实际库存
                $warehouse_pro['quantity'] = empty($warehouse_pro['quantity']) ? 0 : $warehouse_pro['quantity'];
                $actual_quantity = M('Order')->alias('o')
                    ->field("SUM(oi.quantity) AS actual_quantity")
                    ->join("__ORDER_ITEM__ as oi ON o.id_order=oi.id_order", 'left')
                    ->where(array('oi.id_product_sku'=>$c_item['id_product_sku']))
                    ->where(array('o.id_order_status'=> array('IN', $status)))
                    ->find();
                $actual_quantity = empty($actual_quantity['actual_quantity']) ? 0 : $actual_quantity['actual_quantity'] + $warehouse_pro['quantity'];

                //sku缺货量
                $swhere['id_order_status'] = 6;
                $sku_result = M('Order')->alias('o')->field('COUNT(*) as count_qty')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($swhere)->find();
                //三日平均销量
                $twhere['id_order_status'] = array('IN', OrderStatus::get_effective_status());
                $od_sale = M('Order')->alias('o')->field('COUNT(*) as count')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($twhere)->find();
                //近三日销量
                $td_sale = $this->get_three_sale($id, $c_item['id_product_sku']);
                $product_row .= '<tr class="productBox' . $id . '" data-sku-id="'.$c_item['sku'].'"><input type="hidden" value="' . $c_item['title'] . '" name="attr_name[' . $id . '][' . $c_item['id_product_sku'] . ']"/>' .
                    '<td>' . $c_item['sku'] . '</td> ' .
                    '<td>' . $c_item['title'] . '</td>' .
                    '<td><input type="text" class="sprice dsprice sprice' . $c_item['id_product_sku'] . '" value="' . $set_price . '" name="set_price[' . $id . '][' . $c_item['id_product_sku'] . ']" onchange="price_change(' . $c_item['id_product_sku'] . ')"/></td>' .
                    '<td><input type="text" class="sqt dsqt sqt' . $c_item['id_product_sku'] . '" value="' . $set_qty . '" name="set_qty[' . $id . '][' . $c_item['id_product_sku'] . ']" onchange="qty_change(' . $c_item['id_product_sku'] . ')"/></td>' .
                    '<input type="hidden" class="hid_p hid_p' . $c_item['id_product_sku'] . '" value="">'.
//                        '<td><span class="cprice cprice' . $c_key . '">' . $count_price . '</span></td>' .
                    '<td>' . $warehouse_pro['quantity'] . '</td>' .
                    '<td>' . $actual_quantity . '</td>' .
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
//        echo json_encode($_POST['set_price']);die;
        $info = array(
            'status' => 0,
            'message' => ''
        );
        if (empty($_POST['product_id'])) {
            add_system_record(sp_get_current_admin_id(), 1, 2, '添加采购订单失败');
            $info = array(
                'status'=>1,
                'message'=>'保存失败,产品ID不能为空'
            );
            echo json_encode($info);exit();
        }
        if(empty($_POST['alibaba_no'])){
            $info = array(
                'status'=>1,
                'message'=>'保存失败,采购渠道订单号必填！'
            );
            echo json_encode($info);exit();
        }
        $count = $this->Purchase->count();

        $supplier = M('Supplier')->where(array('id_supplier'=>$_POST['id_supplier']))->find();
        if(empty($supplier['supplier_url'])) {
            D('Common/Supplier')->where(array('id_supplier'=>$_POST['id_supplier']))->save(array('supplier_url'=>$_POST['supplier_url']));
        }
        if(empty($supplier)) {
            $supp_id = D('Common/Supplier')->add(array('title'=>$_POST['supplier_name'],'supplier_url'=>$_POST['supplier_url'],'id_department'=>$_POST['id_department'],'created_at'=>date('Y-m-d H:i:s')));
        }

        $attr_name = I('post.attr_name');
        $attr_price = I('post.set_price');  //每个sku价格

        $add_data['id_warehouse'] = I('post.id_warehouse');
        $add_data['id_department'] = I('post.id_department');
        $add_data['created_at'] = date('Y-m-d H:i:s');
        $add_data['track_number'] = I('post.track_number');
        $add_data['id_supplier'] = !empty($_POST['id_supplier'])?I('post.id_supplier'):$supp_id;
        $add_data['purchase_channel'] = I('post.pur_channel');
        $add_data['payment'] = I('post.payment');
        $add_data['inner_purchase_no'] = I('post.inner_purchase_no');
        $add_data['date_from'] = I('post.date_from');
        $add_data['inner_purchase_time'] = I('post.inner_purchase_time');
        if(empty($add_data['inner_purchase_time'])){
            $add_data['inner_purchase_time'] = date('Y-m-d');
        }

        $add_data['date_to'] = I('post.date_to');
        $add_data['prepay'] = I('post.prepay');
        $add_data['alibaba_no'] = trim(I('post.alibaba_no'));
        $check_no= $this->check_inner_purchase_no($add_data['inner_purchase_no']);
        if(!$check_no){
            $info = array(
                'status'=>1,
                'message'=>'保存失败,内部采购单号重复'
            );
            echo json_encode($info);exit();
        }
        $check_alibabano=$this->check_alibaba_no($add_data['alibaba_no']);
        if(!$check_alibabano){
            $info = array(
                'status'=>1,
                'message'=>'保存失败,采购渠道订单号重复'
            );
            echo json_encode($info);exit();
        }
        $add_data['price'] = isset($total_price) ? $total_price : 0;
        $add_data['total'] = isset($total_qty) ? $total_qty : 0;
        $add_data['total_received'] = 0;
        $add_data['remark'] = I('post.remark');
        $add_data['id_users'] = $_SESSION['ADMIN_ID'];
        $add_data['status'] = $_POST['hid']=='2'?PurchaseStatus::UNCHECK:PurchaseStatus::UNSUBMIT;
        $add_data['purchase_no'] = date('Y') . I('post.id_department') . sp_get_current_admin_id() . $count + 1;
        $add_data['price_shipping'] = I('post.price_shipping');


        $total_qty = 0;

        $set_qty = array_filter($_POST['set_qty']);
        if ($set_qty) {
            foreach ($set_qty as $pro_id => $item) {
                $get_qty = array_filter($item);
                if (empty($get_qty)) {
                    add_system_record(sp_get_current_admin_id(), 1, 2, '添加采购订单失败');
                    $info = array(
                        'status'=>1,
                        'message'=>'保存失败,采购单价和采购数量不能都为空'
                    );
                    echo json_encode($info);
                    exit;
                }else{
                    foreach($get_qty as $sku_qty){
                        $total_qty += $sku_qty;
                    }
                }
            }
        }

//        if($total_price > 0 ){
//            $unit_price = round($total_price/$total_qty, 2);
//        }

//        var_dump($add_data);die;
        //修改内部采购单号状态
        $arr = ['status'=>2];
        $wherep['oid_purchase'] = $add_data['inner_purchase_no'];
        $save_purchase =  M('PurchaseInOrder')->where($wherep)->save($arr);
        //修改内部采购单号状态
        $get_in_id = D("Common/Purchase")->data($add_data)->add();
        $total_price = I('post.total_price');  //总价格
        if ($set_qty) {
            foreach ($set_qty as $pro_id => $item) {
                $get_qty = array_filter($item);
                foreach ($get_qty as $key => $qty) {
                    $sku_ids = $key;
                    $get_attr_name = $attr_name[$pro_id][$key]; //属性名称

//                    if(!isset($unit_price)){
//                        $get_price = $attr_price[$pro_id][$key]; //价格
//                        $total_price += $get_price; //总价格
//                    }
                    $get_price = $attr_price[$pro_id][$key]; //价格
//                    $total_price += $get_price; //总价格

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
                }
            }
        }
        $update = array('total' => $total_qty, 'price' => $total_price);
        D("Common/Purchase")->where('id_purchase=' . $get_in_id)->save($update);
        D("Purchase/PurchaseStatus")->add_pur_history($get_in_id, $_POST['hid']=='2'?PurchaseStatus::UNCHECK:PurchaseStatus::UNSUBMIT, '新建采购单');
        add_system_record(sp_get_current_admin_id(), 1, 2, '添加采购订单成功');
        echo json_encode($info);
    }

    public function read_purchase(){

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
        $supplier_name = M('Supplier')->field('title,supplier_url')->where(array('id_supplier' => $purchase['id_supplier']))->find();
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
            $supplier = M('Supplier')->where(array('id_supplier'=>$_POST['id_supplier']))->find();
            if(empty($supplier['supplier_url'])) {
                D('Common/Supplier')->where(array('id_supplier'=>$_POST['id_supplier']))->save(array('supplier_url'=>$_POST['supplier_url']));
            }
            if(empty($supplier)) {
                $supp_id = D('Common/Supplier')->add(array('title'=>$_POST['supplier_name'],'supplier_url'=>$_POST['supplier_url'],'id_department'=>$_POST['id_department'],'created_at'=>date('Y-m-d H:i:s')));
            }
            $add_data['id_warehouse'] = I('post.id_warehouse');
            $add_data['id_department'] = I('post.id_department');
            $add_data['alibaba_no'] = trim(I('post.alibaba_no'));
            $add_data['updated_at'] = date('Y-m-d H:i:s');
            $add_data['id_supplier'] = !empty($_POST['id_supplier'])?I('post.id_supplier'):$supp_id;
            $add_data['purchase_channel'] = I('post.pur_channel');
            $add_data['inner_purchase_time'] = I('post.inner_purchase_time');
            if(empty($add_data['inner_purchase_time'])){
                $add_data['inner_purchase_time'] = date('Y-m-d');
            }
            $add_data['inner_purchase_no'] = I('post.inner_purchase_no');
            $check_no= $this->check_inner_purchase_no($add_data['inner_purchase_no'],$pur_id);
            if(!$check_no){
                $this->error("保存失败,内部采购单号重复");
                exit;
            }
            if(empty($_POST['alibaba_no'])){
                $this->error("保存失败,采购渠道订单号必填");
                exit();
            }
            $check_alibaba=$this->check_alibaba_no($add_data['alibaba_no'], $pur_id);
            if(!$check_alibaba){
                $this->error("保存失败,采购渠道订单号重复");
                exit;
            }
            $add_data['price'] = isset($total_price) ? $total_price : 0;
            $add_data['total'] = isset($total_qty) ? $total_qty : 0;
            $add_data['total_received'] = 0;
            $add_data['remark'] = I('post.remark');
            $add_data['date_from'] = I('post.date_from');
            $add_data['date_to'] = I('post.date_to');
            $add_data['status'] = $_POST['hid']=='2'?PurchaseStatus::UNCHECK:PurchaseStatus::UNSUBMIT;
            $add_data['price_shipping'] = I('post.price_shipping');
            $add_data['prepay'] = I('post.prepay');
            $attr_name = I('post.attr_name');
            $attr_price = I('post.set_price');  //每个sku价格


            $total_qty = 0;

            $set_qty = array_filter($_POST['set_qty']);
            if ($set_qty) {
                foreach ($set_qty as $pro_id => $item) {
                    $get_qty = array_filter($item);
                    if (empty($get_qty)) {
                        add_system_record(sp_get_current_admin_id(), 1, 2, '添加采购订单失败');
                        $this->error("保存失败,产品属性库存不能都为空");
                        exit;
                    }else{
                        foreach($get_qty as $sku_qty){
                            $total_qty += $sku_qty;
                        }
                    }
                }
            }

//            if($total_price > 0 ){
//                $unit_price = round($total_price/$total_qty, 2);
//            }

            D("Common/Purchase")->where(array('id_purchase' => $pur_id))->save($add_data);
            $total_price = I('post.total_price');  //总价格
            $pur_pro = M('PurchaseProduct')->where(array('id_purchase' => $pur_id))->select();
            $pur_pro_id = $pur_pro[0]['id_product']; //原有的产品id
            if ($pur_pro_id != $p_id) {
                foreach ($pur_pro as $k=>$val) {
                    $ware_pro = M('WarehouseProduct')->field('road_num')->where(array('id_warehouse' => I('post.id_warehouse'), 'id_product_sku' => $val['id_product_sku'], 'id_product' => $val['id_product']))->find();
                    if($ware_pro['road_num'] >= $val['quantity']) {
                        $datas['road_num'] = $ware_pro['road_num'] - $val['quantity'];
                        D("Common/WarehouseProduct")->where(array('id_product' => $val['id_product'],'id_product_sku' => $val['id_product_sku'],'id_warehouse' => I('post.id_warehouse')))->where(array())->save($datas);
                    }
                }
                D("Common/PurchaseProduct")->where(array('id_purchase' => $pur_id))->delete();
            }

            if ($set_qty) {
                foreach ($set_qty as $pro_id => $item) {
                    $get_qty = array_filter($item);
                    if ($get_qty && count($get_qty)) {
                        foreach ($get_qty as $key => $qty) {
                            $sku_ids = $key; //SKU id
                            $get_attr_name = $attr_name[$pro_id][$key]; //属性名称

//                            if(!isset($unit_price)){
//                                $get_price = $attr_price[$pro_id][$key]; //价格
//                                $total_price += $get_price; //总价格
//                            }
                            $get_price = $attr_price[$pro_id][$key]; //价格

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
                            $pur_product = M('PurchaseProduct')->where(array('id_purchase' => $pur_id, 'id_product' => $pro_id, 'id_product_sku' => $sku_ids))->find();
                            if ($pur_product) {
                                D("Common/PurchaseProduct")->where(array('id_purchase' => $pur_id, 'id_product' => $pro_id, 'id_product_sku' => $sku_ids))->save($array_data);
                                if ($qty != $pur_product['quantity'] && $warehouse_product['road_num'] >= $pur_product['quantity']) {
                                    $datas['road_num'] = ($warehouse_product['road_num'] - $pur_product['quantity']) + $qty;
                                    D("Common/WarehouseProduct")->where(array('id_product_sku' => $sku_ids,'id_warehouse' => I('post.id_warehouse')))->save($datas);
                                }
                            } else {
                                D("Common/PurchaseProduct")->data($array_data)->add();
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
                            }
                        }
                    }
                }
            }
            $update = array('total' => $total_qty, 'price' => $total_price);
            D("Common/Purchase")->where('id_purchase=' . $pur_id)->save($update);
            D("Purchase/PurchaseStatus")->add_pur_history($pur_id, $_POST['hid']=='2'?PurchaseStatus::UNCHECK:PurchaseStatus::UNSUBMIT, '编辑采购单');
            add_system_record(sp_get_current_admin_id(), 1, 3, '编辑采购订单成功');
            if(isset($_SESSION['return_purchase_index_url'])){
                $this->success("保存成功",  $_SESSION['return_purchase_index_url']);
            }else{
                $this->success("保存成功！", U('Index/index'));
            }


        } else {
            add_system_record(sp_get_current_admin_id(), 1, 3, '编辑采购订单失败');
            $this->error("保存失败,产品ID不能为空");
        }
    }
    /*
     * 检查内部采购单号是否重复
     */
    public function check_inner_purchase_no($inner_purchase_no,$purchase_id=0){
        if(empty($inner_purchase_no)){
            return true;
        }
        $purchase = M('Purchase')->where(array('inner_purchase_no'=>$inner_purchase_no,'id_purchase'=>array('NEQ',$purchase_id)))->count();
        if(!empty($purchase) && $purchase>0){
            return false;
        }else{
            return true;
        }
    }
    /*
     * 检查采购渠道订单号是否重复
     */
    public function check_alibaba_no($alibaba_no,$purchase_id=0){
        if(empty($alibaba_no)){
            return true;
        }
        $where_a=[];
        if($purchase_id){
            $where_a['id_purchase']=array('neq',$purchase_id);
        }

        $where_a['alibaba_no']=$alibaba_no;
        $purchase = D('Purchase')->where($where_a)->count();
        if(!empty($purchase) && $purchase>0){
            return false;
        }else{
            return true;
        }
    }
    /*
     * 检查内部采购单号是否重复
     */
//    public function check_no(){
//        $inner_purchase_no=$_REQUEST['inner_purchase_no'];
//        $purchase_id=$_REQUEST['purchase_id'];
//        if(empty($inner_purchase_no)){
//           echo 1;
//        }
//        $purchase = M('Purchase')->where(array('inner_purchase_no'=>$inner_purchase_no,'id_purchase'=>array('NEQ',$purchase_id)))->count();
//        if(!empty($purchase) && $purchase>0){
//           echo 0;
//        }else{
//           echo 1;
//        }
//        exit;
//    }
    /**
     * 生成采购打印单页面
     */
    public function get_purchase_dy() {
        $pur_id = I('get.id/i');
        $purchase = M('Purchase')->field('id_department,id_users,purchase_no,id_purchase')->where(array('id_purchase'=>$pur_id))->find();
        $purchase_pro = M('PurchaseProduct')->where(array('id_purchase'=>$pur_id))->select();
        foreach ($purchase_pro as $key=>$val) {
            $product = M('Product')->field('title,inner_name,thumbs')->where(array('id_product'=>$val['id_product']))->find();
            $purchase_pro[$key]['img'] = json_decode($product['thunmbs'], true);
            $purchase_pro[$key]['title'] = $product['title'];
            $purchase_pro[$key]['sku'] = M('ProductSku')->where(array('id_product_sku'=>$val['id_product_sku']))->getField('sku');
        }
        $department = M('Department')->where(array('id_department'=>$purchase['id_department']))->getField('title');
        $user= M('Users')->where(array('id'=>$purchase['id_users']))->getField('user_nicename');
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看采购单打印页面');
        $this->assign('data',$purchase);
        $this->assign('list',$purchase_pro);
        $this->assign('department',$department);
        $this->assign('user',$user);
        $this->display();
    }

    /**
     * 批量更新采购单状态
     */
    public function update_pur_status() {
        if(IS_AJAX) {
            try {
                $purIds = is_array($_POST['pur_id']) ? $_POST['pur_id'] : array($_POST['pur_id']);
                $pur_stauts = $_POST['pur_status'];
                $msg = $pur_stauts==PurchaseStatus::UNCHECK ? '提交采购单' : '取消采购单';
                if ($purIds && is_array($purIds)) {
                    foreach ($purIds as $pur_id) {
                        $pur = M('Purchase')->field('status')->where(array('id_purchase'=>$pur_id))->find();
                        if(($pur['status'] == PurchaseStatus::UNSUBMIT && $pur_stauts == PurchaseStatus::UNCHECK) || ($pur['status'] != PurchaseStatus::CANCLE && $pur_stauts == PurchaseStatus::CANCLE)) {
                            $data['status'] = $pur_stauts;
                            D('Common/Purchase')->where(array('id_purchase'=>$pur_id))->save($data);
                            D("Purchase/PurchaseStatus")->add_pur_history($pur_id, $pur_stauts, $msg);
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
        $product = D('Common/Product');
        $dep = $_SESSION['department_id'];

        $where = array();
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = array('EQ', $_GET['id_department']);
        } else {
            $where['id_department'] = array('IN', $dep);
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
            $where['title'] = array('LIKE','%'.$_GET['pro_title'].'%');
        }
        if(isset($_GET['pro_name']) && $_GET['pro_name']) {
            $where['inner_name'] = array('LIKE','%'.$_GET['pro_name'].'%');
        }

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
        $department = M('Department')->field('id_department,title')->where(array('id_department'=>array('IN',$dep),'type'=>1))->order('sort asc')->select();
        $department = array_column($department, 'title', 'id_department');
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看产品设置列表');
        $this->assign('products', $products);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign('department',$department);
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
        $product = M('Product')->where(array('id_product' => $id))->find();
        $timewhere = array();
        $timewhere[] = array('EGT',date('Y-m-d', strtotime('-3 month')));
        $timewhere[] = array('LT',date('Y-m-d'));
        $where[] = array('p.created_at'=>$timewhere);
        $where['pp.id_product'] = array('EQ',$id);
        $pur_pro = M('PurchaseProduct')->alias('pp')->join('__PURCHASE__ AS p ON pp.id_purchase=p.id_purchase')->field('SUM(pp.quantity) as quantity,SUM(pp.price) as price')->where($where)->find();
        $purchase_price = round($pur_pro['price']/$pur_pro['quantity'],2);//采购金额，三个月采购单
        $this->assign('product', $product);
        $this->assign('purchase_price',$purchase_price);
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

    /**
     * 导出产品信息
     */
    public function export_product_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
//            '产品ID', '重量','产品名', '内部名', '采购价'
            '产品名','内部名','产品SKU','属性','产品SKU_ID','采购成本','重量'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $dep = $_SESSION['department_id'];

        $where = array();
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where['p.id_department'] = array('EQ', $_GET['id_department']);
        } else {
            $where['p.id_department'] = array('IN', $dep);
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
            $where['p.title'] = array('LIKE','%'.$_GET['pro_title'].'%');
        }
        if(isset($_GET['pro_name']) && $_GET['pro_name']) {
            $where['p.inner_name'] = array('LIKE','%'.$_GET['pro_name'].'%');
        }
//        $where['ps.status'] = 1;
        $products = M('Product')->alias('p')->join('__PRODUCT_SKU__ ps ON ps.id_product=p.id_product')->field('p.title,p.inner_name,ps.id_product_sku,ps.sku,ps.title as attr_name,ps.purchase_price,ps.weight')->where($where)->order(array('p.id_product' => 'desc'))->select();
        foreach ($products as $key=>$val) {
//            $timewhere = array();
//            $timewhere[] = array('EGT',date('Y-m-d', strtotime('-3 month')));
//            $timewhere[] = array('LT',date('Y-m-d'));
//            $ewhere[] = array('p.created_at'=>$timewhere);
//            $ewhere['pp.id_product'] = array('EQ',$val['id_product']);
//            $pur_pro = M('PurchaseProduct')->alias('pp')->join('__PURCHASE__ AS p ON pp.id_purchase=p.id_purchase')->field('SUM(pp.quantity) as quantity,SUM(pp.price) as price')->where($ewhere)->find();
//            $purchase_price = round($pur_pro['price']/$pur_pro['quantity'],2);//采购金额，三个月采购单

            $data = array(
                $val['title'],$val['inner_name'],$val['sku'],$val['attr_name'],$val['id_product_sku'],$val['purchase_price'],$val['weight']
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 2, '导出产品信息列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '产品信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '产品信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }


    /**
     * 新增 导出采购订单信息     liuruibin   20171010
     */
    public function export_index_search(){
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '采购单号', '采购内部单号', '采购快递单号', '采购渠道订单号', '内部采购时间','仓库', '供应商','广告员', '产品名', 'SKU', '采购单价',
            '采购数量', '采购金额', '总金额','运费', '采购渠道','状态', '创建人'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $dep = $_SESSION['department_id'];
        $_SESSION['return_purchase_index_url']= $_SERVER['REQUEST_URI'];
        if (isset($_GET['depart_id']) && $_GET['depart_id']) {
            $where['p.id_department'] = array('EQ', $_GET['depart_id']);
        } else {
            $where['p.id_department'] = array('IN', $dep);
        }
        if (isset($_GET['ware_id']) && $_GET['ware_id']) {
            $where['p.id_warehouse'] = array('EQ', $_GET['ware_id']);
        }
        if (isset($_GET['status_id']) && $_GET['status_id']) {
            $where['p.status'] = $_GET['status_id'];
        }
        if (isset($_GET['shipping_no']) && $_GET['shipping_no']) {
            $where['p.shipping_no'] = $_GET['shipping_no'];
        }
        if (isset($_GET['pur_num']) && $_GET['pur_num']) {
            $pur_num=  trim($_GET['pur_num']);
            $where['p.purchase_no'] = array('like', "%{$pur_num}%");
        }
        //增加采购员名字筛选
        if (isset($_GET['shop_id']) && $_GET['shop_id']) {

            $where['p.id_users'] = array('EQ', $_GET['shop_id']);
        }
        /* 此功能已被 采购员名字筛选 替换
        if (isset($_GET['id_users']) && $_GET['id_users']) {
            $where['p.id_users'] = array('EQ', $_GET['id_users']);
        }*/
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
        }else{
            $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-7 days')));
            $createAtArray[] = array('LT', date('Y-m-d', strtotime('+1 day')));
        }
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['p.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if ($_GET['alibaba_no']) {
            $alibaba_no=  trim($_GET['alibaba_no']);
            $where['p.alibaba_no'] = array('like', "%{$alibaba_no}%");
        }
        $purchaseTimeArray = array();
        if (isset($_GET['start_purchase_time']) && $_GET['start_purchase_time']) {
            $purchaseTimeArray[] = array('EGT',$_GET['start_purchase_time']);
        }
        if(isset($_GET['end_purchase_time']) && $_GET['end_purchase_time']) {
            $purchaseTimeArray[] = array('LT',$_GET['end_purchase_time']);

        }
        if(!empty($purchaseTimeArray)){
            $where['p.inner_purchase_time'] =$purchaseTimeArray;
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $id_purchase = M('PurchaseProduct')->alias('pp')
                ->field('id_purchase')
                ->join('__PRODUCT_SKU__ ps on ps.id_product_sku = pp.id_product_sku')
                ->where(array('sku' => $_GET['sku']))
                ->getField('id_purchase', true);
            $new = '';
            foreach ($id_purchase as $k => $v) {
                $new .= 'p.id_purchase = ' . $v . ' OR ';
            }
            if(!empty($id_purchase)){
                $where[] = substr($new, 0, -3);
            }else{
                //为空就找不到
                $where['p.id_purchase'] =-1;
            }

        }
        if (isset($_GET['pro_name']) && $_GET['pro_name']) { //内部产品名搜索存在
            $pro_name  = I('get.pro_name');
            $wherepr['inner_name'] = array('like', "%{$pro_name}%");
            $products = M('Product')->field('id_product,inner_name')->where($wherepr)->select(); //根据内部产品名获取产品id
            foreach($products as $k=>$v){
                $product_purchase[] = M('PurchaseProduct')->field('id_purchase,id_product')->where(array('id_product'=>$v['id_product']))->select(); //根据产品id_product获取 id_purchase
            }
            $new = '';
            foreach ($product_purchase as $kkey => $val) {
                foreach($val as $k=>$v){
                    $new .= 'p.id_purchase = ' . $v['id_purchase'] . ' OR ';
                }
                //$new .= 'p.id_purchase = ' . $v['id_purchase'] . ' OR ';
            }
            if(!empty($product_purchase)){
                $where[] = substr($new, 0, -3);
            }else{
                //为空就找不到
                $where['p.id_purchase'] =-1;
            }
        }
        $where[] = array('p.created_at' => $createAtArray);
        $department = M('Department')->where(array('id_users' => $user_id))->find();
        //$users = M('Users')->field('id,user_nicename')->where(array('superior_user_id' => $user_id))->select();
        //$users = array_column($users, 'user_nicename', 'id');
        $users = M('Users')->field('id,user_nicename')->where(array('id_user' => $user_id,'user_status'=>1))->select();
        $users = array_column($users, 'user_nicename', 'id');

        $flag = 1;
        if (!$department) {
            $flag = 2;
        }
        $model = new \Think\Model();
        $list = $model->table($this->Purchase->getTableName() . ' p')->join(array($this->Users->getTableName() . ' u on p.id_users = u.id'))->field('p.*,u.user_nicename')
            ->where($where)
            ->order("p.id_purchase DESC")
            ->select();

        $pur_status = M('PurchaseStatus')->field('id_purchase_status,title')->select(); //采购单状态
        $pur_status = array_column($pur_status, 'title', 'id_purchase_status');

        $sup_id = array();
        $ware_id = array();
        foreach ($list as $k => $v) {
//            var_dump($v);die();
            $sup_id[] = $v['id_supplier'];
            $ware_id[] = $v['id_warehouse'];

            $list[$k]['product'] = $this->get_pur_pro($v['id_purchase']);

            $list[$k]['pro_name'] = $this->get_pur_pro($v['id_purchase'], true);
            $list[$k]['status_name'] = $pur_status[$v['status']];
            $list[$k]['totalprice']=  array_sum(array_column($list[$k]['product'], 'itemtotalprice'))+$list[$k]['price_shipping'];

            switch ($v['purchase_channel']) {
                case 1:
                    $pur_channel_name = '阿里巴巴';
                    break;
                case 2:
                    $pur_channel_name = '淘宝';
                    break;
                case 3:
                    $pur_channel_name = '线下';
                    break;
                default :
                    $pur_channel_name = '';
            }
            $list[$k]['pur_channel_name'] = $pur_channel_name;
        }

        foreach ($sup_id as $k => $v) {
            $supplier_name = D("Common/Supplier")->field('title as sup_title')->where('id_supplier=' . $v)->find();
            $warehouse = M('Warehouse')->field('title as ware_title')->where('id_warehouse=' . $ware_id[$k])->find();
            $list[$k]['sup_name'] = $supplier_name['sup_title'];
            $list[$k]['ware_name'] = $warehouse['ware_title'];
        }
        foreach($list as $p){
            $data[] = array(
                 $p['purchase_no'],$p['inner_purchase_no'],$p['shipping_no'],$p['alibaba_no'],$p['inner_purchase_time'],$p['ware_name'],$p['sup_name'],'','',$p['product'],'','','',$p['price'],$p['price_shipping'],$p['pur_channel_name'],$p['status_name'],$p['user_nicename']
            );
        }
        //dump($data);exit;
        if ($data) {
            $k = 2;
            $num = 2;
            $sum = 2;
            foreach ($data as $kk => $items) {
                $j = 65;
                $count = count($items[9]);
                if ($count > 1) {
                    $excel->getActiveSheet()->mergeCells("A" . ($num ? $num : $idx).":"."A" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("B" . ($num ? $num : $idx).":"."B" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("C" . ($num ? $num : $idx).":"."C" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("D" . ($num ? $num : $idx).":"."D" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("E" . ($num ? $num : $idx).":"."E" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("F" . ($num ? $num : $idx).":"."F" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("G" . ($num ? $num : $idx).":"."G" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("N" . ($num ? $num : $idx).":"."N" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("O" . ($num ? $num : $idx).":"."O" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("P" . ($num ? $num : $idx).":"."P" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("Q" . ($num ? $num : $idx).":"."Q" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("R" . ($num ? $num : $idx).":"."R" . (($num ? $num : $idx)+$count-1));
                    /*echo ($num ? $num : $idx) . ':' . (($num ? $num : $idx)+$count-1);
                    $num = (($num ? $num : $idx)+$count);
                    echo "<br/>";
                    echo $num;
                    echo "<br/>";*/
                    $num = (($num ? $num : $idx)+$count);
                } else {
                    $num  += 1;
                }
                //dump($items);exit;
                foreach ($items as $key => $col) {

                    if (is_array ($col)) {

                        $a = 0;
                        foreach($col as $c) {

                            $excel->getActiveSheet()->setCellValue("H" . $sum, $users[$c['id_users_sales']]);
                            $excel->getActiveSheet()->setCellValue("I" . $sum, $c['pro_name']);
                            $excel->getActiveSheet()->setCellValue("J" . $sum, $c['sku']);
                            $excel->getActiveSheet()->setCellValueExplicit("K". $sum, $c['price']);
                            $excel->getActiveSheet()->setCellValueExplicit("L". $sum, $c['quantity']);
                            $excel->getActiveSheet()->setCellValueExplicit("M". $sum, $c['itemtotalprice']);
                            $a++;
                            $sum = $sum+1;
                        }
                    } else {
                        $bb = $sum;
                        if ($key > 11) {
                            $bb = $sum - $count;
                        }
                        if (!in_array($key,[9,10,11])){
                            //echo $col;
                            //echo chr($j) . $bb."----$key-----$col<br/>";
                            if(in_array($key, array(0,3))){
                                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $bb, $col);
                            }else{
                                $excel->getActiveSheet()->setCellValue(chr($j) . $bb, $col);
                            }
                        }
                    }
                    ++$j;//横
                }
                ++$idx;//列
                ++$k;
            }
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出采购订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '采购订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '采购订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    /**
     * 导入产品sku采购成本
     */
    public function update_product_weight() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $dep = $_SESSION['department_id'];
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('product', 'update_product_sku_pruprice', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            $total = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $pro_id = $row[0];//产品sku_id
                $pur_price = $row[1];//采购成本
                $product = M('Product')->alias('p')->join('__PRODUCT_SKU__ ps ON ps.id_product=p.id_product')->where(array('ps.id_product_sku'=>$row[0],'p.id_department'=>array('IN',$dep)))->find();
                if($product) {
                    $data['purchase_price'] = $pur_price;
                    D('Product/ProductSku')->where(array('id_product_sku'=>$row[0]))->save($data);
                    $info['success'][] = sprintf('第%s行: 产品SKU:%s 更新采购成本：%sRMB', $count++, $product['sku'], $pur_price);
                } else {
                    $info['error'][] = sprintf('第%s行: 没有找到产品SKU', $count++);
                }
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 5, 3, '导入sku采购价');
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 导入产品sku重量
     */
    public function update_productsku_weight() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $dep = $_SESSION['department_id'];
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('product', 'update_product_sku_weight', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $pro_id = $row[0];//产品sku_id
                $weight = $row[1];//重量
                $product = M('Product')->alias('p')->join('__PRODUCT_SKU__ ps ON ps.id_product=p.id_product')->where(array('ps.id_product_sku'=>$row[0],'p.id_department'=>array('IN',$dep)))->find();
                if($product) {
                    $data['weight'] = $weight;
                    D('Product/ProductSku')->where(array('id_product_sku'=>$row[0]))->save($data);
                    $info['success'][] = sprintf('第%s行: 产品SKU:%s 更新重量：%skg', $count++, $product['sku'], $weight);
                } else {
                    $info['error'][] = sprintf('第%s行: 没有找到产品SKU', $count++);
                }
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 5, 3, '导入sku重量');
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
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
        //新增根据供应商和采购专员筛选
        if (!empty($_GET['supplier_sname'])) {
            $_GET['supplier_sname']=trim($_GET['supplier_sname']);
            $keyword_complex_supplier['title']  = array('like',"%".$_GET['supplier_sname']."%");
            $supplier_id=M("Supplier")->where($keyword_complex_supplier)->field('id_supplier')->select();

            if(!empty($supplier_id)){
                $supplier_id=array_column($supplier_id,'id_supplier');
                $where['po.id_supplier'] = array('IN',$supplier_id);
            }
        }
        if (!empty($_GET['purchase_users'])) {
            //purchase_users
            $_GET['purchase_users']=trim($_GET['purchase_users']);
            $keyword_complex_users['user_nicename']  = array('like',"%".$_GET['purchase_users']."%");
            $id_users=M("Users")->where($keyword_complex_users)->field('id')->select();
            if($id_users){
                $id_users=array_column($id_users,'id');
                $where['po.id_users'] = array('IN',$id_users);
            }
        }

        if (!empty($_GET['purchase_no'])) {
            $where['po.purchase_no'] = array('EQ', $_GET['purchase_no']);
        }
        if (!empty($_GET['inner_purchase_no'])) {
            $where['po.inner_purchase_no'] = array('EQ', $_GET['inner_purchase_no']);
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
        $received_list_status = PurchaseStatus::received_list_status();
        $where['status'] = array('IN',array_keys($received_list_status));
        $department = M('Department')->where('type=1')->select();
        $department = array_column($department, 'title', 'id_department');
        $supplierdata=M('Supplier')->select();
        $supplierdata = array_column($supplierdata, 'title', 'id_supplier');
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
            ->where($where)->order("po.status ASC")
            ->limit($page->firstRow . ',' . $page->listRows)->select();
        foreach ($pro_list as $k => $p) {
            if (empty($p['track_number'])) {
                continue;
            }
            $str = '';
            $trackings = explode("\n", $p['track_number']);
            foreach ($trackings as $t) {
                $t = trim($t);
                $str .= sprintf('<a target="_blank" href="https://www.kuaidi100.com/courier/?searchText=%s">%s</a><br/>', $t, $t);
            }
            $pro_list[$k]['track_number'] = $str;
        }
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看采购收货列表');

        $this->assign("getData", $_GET);
        $this->assign('department', $department);

        $this->assign('supplier', $supplierdata);
        $this->assign('purchase_status', $received_list_status);
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
        <th>采购数</th><th>已收货的数量</th><th>收货数量</th></tr>';
        $product_html .= $this->get_product_html2($id);
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
            // dump($temp_product);
            die;
        }
        // dump($temp_product);

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

        $status = array(                //需计算的订单状态
            OrderStatus::UNPICKING,     //未配货
            OrderStatus::PICKED,        //已配货
            OrderStatus::APPROVED,      //已审核
        );

        foreach ($purchase_list as $key => $v) {
            $where['id_product'] = $v['id_product'];
            $where['id_product_sku'] = $v['id_product_sku'];
            $load_product = D("Common/Product")->field('thumbs,title,inner_name')->where(array('id_product' => $v['id_product']))->find();
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
            $purchase_list[$key]['title'] = $load_product['inner_name'];
            $purchase_list[$key]['img'] = json_decode($load_product['thumbs'], true);
            $purchase_list[$key]['qty'] = $warehouse_pro['quantity'];
            $purchase_list[$key]['road_num'] = $warehouse_pro['road_num'];
            $purchase_list[$key]['order_qty'] = $sku_result['count_qty'];
            $purchase_list[$key]['oday_sale'] = round($od_sale['count'] / 3, 2);
            $purchase_list[$key]['tday_sale'] = $td_sale;

            //计算实际库存
            $purchase_list[$key]['qty'] = empty($purchase_list[$key]['qty']) ? 0 : $purchase_list[$key]['qty'];
            $actual_quantity = M('Order')->alias('o')
                ->field("SUM(oi.quantity) AS actual_quantity")
                ->join("__ORDER_ITEM__ as oi ON o.id_order=oi.id_order", 'left')
                ->where(array('oi.id_product_sku'=>$v['id_product_sku']))
                ->where(array('o.id_order_status'=> array('IN', $status)))
                ->find();
            $purchase_list[$key]['actual_quantity'] = empty($actual_quantity['actual_quantity']) ? 0 : $actual_quantity['actual_quantity'] + $purchase_list[$key]['qty'];
        }
        $department = M('Department')->where(array('id_department' => $purchase['id_department']))->getField('title');
        $warehouse = M('Warehouse')->where(array('id_warehouse' => $purchase['id_warehouse']))->getField('title');
        $supplier = M('Supplier')->field('title,supplier_url')->where(array('id_supplier' => $purchase['id_supplier']))->find();
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
        //$pur_record_count = M('PurchaseRecord')->where(array('id_purchase' => $id))->count();
        //$pur_record_page = $this->page($pur_record_count, 5);
        //$pur_record = M('PurchaseRecord')->where(array('id_purchase' => $id))->limit($pur_record_page->firstRow, $pur_record_page->listRows)->select();
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
       // $this->assign("page", $pur_record_page->show('Admin'));
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
    protected function get_product_html2($id) {
        $purchase_product = M('PurchaseProduct')->alias('pp')->field('pp.*,pu.sku')->join('__PRODUCT_SKU__ as pu on pu.id_product_sku = pp.id_product_sku', 'LEFT')->where(array('pp.id_purchase' => array('EQ', $id)))->select();
        $product_row = '';
        foreach ($purchase_product as $key => $v) {
            $load_product = D("Common/Product")->field('thumbs,title')->where(array('id_product' => $v['id_product']))->find();
            $purchase_product[$key]['title'] = $load_product['title'];
            $purchase_product[$key]['img'] = json_decode($load_product['thumbs'], true);
            $photo = !empty($purchase_product[$key]['img']['photo']) ? $purchase_product[$key]['img']['photo'][0]['url'] : '';
            $product_row .= '<input type="hidden" name="data[' . $key . '][id_purchase_product]" value="' . $v['id_purchase_product'] . '"><input type="hidden" name="data[' . $key . '][id_product]" value="' . $v['id_product'] . '"><input type="hidden" name="data[' . $key . '][id_product_sku]" value="' . $v['id_product_sku'] . '"><input type="hidden" name="data[' . $key . '][id_purchase]" value="' . $v['id_purchase'] . '"><tr class="tr"><td><img  src="' . sp_get_image_preview_url($photo) . '" style="height:36px;width: 36px;"></td><td>' . $v['sku'] . '</td><td>' . $purchase_product[$key]['title'] . '</td><td class="purchase">' . $v['quantity'] . '</td><td class="received">' . $v['received'] . '</td><td class="add"><input type="text" name="data[' . $key . '][quantity]"></td></tr>';
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
                $update['status'] = PurchaseStatus::PART_RECEIVE;
                $update['total_received'] = $old_total + $sum_received;
                $res = M('Purchase')->save($update);
                D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::PART_RECEIVE, '部分收货');
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
                $update['status'] = PurchaseStatus::FINISH_RECEIVE;
                $update['total_received'] = $old_total + $sum_received;
                $res = M('Purchase')->save($update);
                D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::FINISH_RECEIVE, '收货完成');
                if ($res === false) {
                    $this->error("保存失败！", U('index/purchase_list2'));
                } else {
                    $this->success("保存完成！", U('index/purchase_list2'));
                }
                break;
            case 'reject':
                $update['id_purchase'] = $id_purchase;
                $update['status'] = PurchaseStatus::REJECT;
                $update['remark'] = $_POST['remark'];
                $res = M('Purchase')->save($update);
                D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::REJECT, '拒收');
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
     *重写销售统计列表
     */
    public function statistics(){
        set_time_limit(60);
        //获取该产品的拿货链接 和  备注    --Lily  2017-12-05
        if(isset($_POST['MSG']) && $_POST['MSG']=='MSG'){
            $id_product = $_POST['id_product'];
            $product_data = M("Product")->where("id_product=".$id_product)->field("purchase_url,pro_msg")->find();
            if($product_data['pro_msg']==null || $product_data['pro_msg']==""){
                $product_data['pro_msg']= '该产品没有备注信息';
            }
            if($product_data['purchase_url']==null || $product_data['purchase_url']==""){
                $product_data['purchase_url']= '该产品没有拿货链接';
            }
            echo json_encode($product_data);
            exit();
        }
        //参数筛选
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();//有效状态订单
        $where=[];
        if($_POST['warehouse_id']){
            $where['o.id_warehouse']=$_POST['warehouse_id'];
        }
        if($_POST['shipping_id']){
            $where['o.id_shipping']=$_POST['shipping_id'];
        }
        //是否存在部门查询 如不存在则默认显示当前用户所在部门的第一个部门   --Lily  2017-11-03
        if(isset($_POST['id_department']) && $_POST['id_department'] !=="0"){
            $where['o.id_department']=$_POST['id_department'];
        }else if(isset($_POST['id_department']) && $_POST['id_department'] =="0"){
           $where['o.id_department']=array('IN',$_SESSION['department_id']);
        }else{
            $where['o.id_department']=array('EQ',$_SESSION['department_id'][0]);
        }
        //获取所有有效订单状态
        $statusmodecon=array('status'=>1,'id_order_status'=>array('in',$effective_status));
        if($_POST['status_id']){
            $where['o.id_order_status']=$_POST['status_id'];
        }else{

            if ($_POST['status_id'] === '0') {
                $where['o.id_order_status']=array('in',$effective_status);
            } else {
                $where['o.id_order_status']=6;
                $_POST['status_id'] = 6;
            }
        }
        if (isset($_POST['start_time']) && $_POST['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_POST['start_time']);
            if ($_POST['end_time']) {
                $createAtArray[] = array('LT', $_POST['end_time']);
            }
        }else{
            //时间默认调整为前一天 zx 11/20
            $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-1 days')));
            $createAtArray[] = array('LT', date('Y-m-d', strtotime('+1 day')));
        }
        //增加产品内部名$_POST['innername']，SKU$_POST['sku'] 查询    liuruibin   20171019
        if(isset($_POST['innername']) && $_POST['innername']){
            $where['pt.inner_name'] = array('like','%'.$_POST['innername'].'%');
        }
        if(isset($_POST['sku']) && $_POST['sku']){
            $where['oi.sku'] = array('EQ',$_POST['sku']);
        }
        $where[] = array('o.created_at' => $createAtArray);
        $departmentList = M('department')->where(array('type' => 1))->where(array('id_department'=>array('in',$_SESSION['department_id'])))->order('sort asc')->getField('id_department,title');
        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        $warehouseList = M('warehouse')->where(array('status' => 1))->getField('id_warehouse,title');

        $status_model = D('Order/OrderStatus')->where($statusmodecon)->getField('id_order_status,title');
        $orderitem_table = D('Order/orderItem')->getTableName();
        $product_table = D('product')->getTableName();
        $order = D('order')->getTableName();
        //有效单
        //array_push($effective_status, OrderStatus::VERIFICATION);
        //新增1个链表查询 __WAREHOUSE_PRODUCT__(获取 在途量 库存量)  11/13 zx
        $fields="SUM(IF(`id_order_status` IN(".implode(',', $effective_status)."),1,0)) as effective_number,o.id_order_status,oi.id_product_sku,oi.sku,oi.sku_title,oi.id_product,pt.inner_name,pt.thumbs,pt.title,sum(oi.quantity) as quantity,wp.quantity as wp_quantity,wp.road_num,wp.qty_preout";

        $staticsinfo=M('order o')
                ->join("{$orderitem_table} oi on o.id_order=oi.id_order",'left')
                ->join("{$product_table} pt on pt.id_product=oi.id_product",'left')
                ->join("__WAREHOUSE_PRODUCT__ as wp on oi.id_product_sku=wp.id_product_sku",'left')
                ->where($where)->field($fields)->group('oi.id_product_sku')->select();
        /* 重构统计start zx  11/18 */
        //取出所有的 id_product_sku
    if(!empty($staticsinfo)){
        $sku_arr = array_column( $staticsinfo,'id_product_sku');
        $where_sku['id_product_sku'] = array("IN",implode(',', $sku_arr));
        $purchase_products = M("PurchaseProduct")->field('id_product_sku,price,quantity')->where($where_sku)->select();
        // 重算平均采购单价 zx  11/18
        foreach($purchase_products as $skey=>$sval){
            $purchase_prices[$sval['id_product_sku']]['count'] +=  $sval['quantity'];
            $purchase_prices[$sval['id_product_sku']]['sum']   +=  (int)$sval['price']*$sval['quantity'];
            $purchase_sprices[$sval['id_product_sku']]['id_product_sku']   =  $sval['id_product_sku'];
            //平均采购单价
            $purchase_sprices[$sval['id_product_sku']]['single_price'] = round($purchase_prices[$sval['id_product_sku']]['sum']/$purchase_prices[$sval['id_product_sku']]['count'],2);
        }
        $purchase_sprices = array_merge($purchase_sprices);

        $model = new \Think\Model();
        //总产品数 orderItem
        $where_total_product['oi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_total_product['o.id_order_status'] = array("IN",$effective_status);
        $total_product = $model->table('erp_order o')
                    ->join("__ORDER_ITEM__ oi ON o.id_order=oi.id_order","left")
                    ->field('oi.quantity,oi.id_product_sku')
                    ->where($where_total_product)->select();
        foreach($total_product as $pkey=>$pval){
            $product_stNumber[$pval['id_product_sku']]['id_product_sku']   =  $pval['id_product_sku'];
            $product_stNumber[$pval['id_product_sku']]['total']  +=  $pval['quantity'];

        }
        $product_stNumber = array_merge($product_stNumber);

        //总采购数 PurchaseInitem PurchaseIn
        $where_total_purchase['pi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_total_purchase['po.status'] = array("EQ",5); //已付款
        $where_total_purchase['p.billtype'] = array("NEQ",2); //非退货
        $total_purchase = $model->table('erp_purchase_in p')
                    ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein",'left')
                    ->join("__PURCHASE__ as po on p.id_erp_purchase=po.id_purchase",'left')
                    ->field('pi.id_product_sku,sum(pi.quantity) as total')
                    ->group('pi.id_product_sku')
                    ->where($where_total_purchase)->select();
        
        foreach($total_purchase as $tkey=>$tval){
            $purchase_stNumber[$tval['id_product_sku']]['id_product_sku']   =  $tval['id_product_sku'];
            $purchase_stNumber[$tval['id_product_sku']]['total']   +=  $tval['total'];

        }
        $purchase_stNumber = array_merge($purchase_stNumber);

        //总退货数 return_goods return_goods_item zx 12/06
        $where_total_return['rgi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_total_return['rg.warehouse_status'] = array("EQ",2); //已退货
        $total_return = $model->table('erp_return_goods rg')
                    ->join("__RETURN_GOODS_ITEM__ as rgi on rgi.id_return_goods=rg.id_return",'left')
                    ->field('rgi.c_qty_true,rgi.id_product_sku,rg.id_return')
                    ->where($where_total_return)
                    ->select();
        foreach($total_return as $rkey=>$rval){
            $total_sReturn[$rval['id_product_sku']]['id_product_sku']   =  $rval['id_product_sku'];
            $total_sReturn[$rval['id_product_sku']]['return_total']   +=  abs($rval['c_qty_true']);

        }
        $total_sReturn = array_merge($total_sReturn);
        

        //总入库数 PurchaseInitem PurchaseIn
        $where_warehouse['pi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_warehouse['p.status'] = array("IN",[2,3]); //已入库+部分入库 received
        $where_warehouse['p.billtype'] = array("NEQ",2); //非退货
        $total_warehouse = $model->table('erp_purchase_in p')
                    ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein",'left')
                    //->field('pi.id_product_sku,pi.received')
                    ->field('pi.id_product_sku,sum(pi.received) as received')
                    ->group('pi.id_product_sku')
                    ->where($where_warehouse)->select();
        foreach($total_warehouse as $wkey=>$wval){
            $purchase_swarehouse[$wval['id_product_sku']]['id_product_sku']   =  $wval['id_product_sku'];
            $purchase_swarehouse[$wval['id_product_sku']]['received']   +=  $wval['received'];

        }
        $purchase_swarehouse = array_merge($purchase_swarehouse);

        //总未入库数
        $where_no_warehouse['pi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_no_warehouse['p.status'] = array("EQ",1); //未入库 received
        $where_no_warehouse['p.billtype'] = array("NEQ",2); //非退货
        $total_no_warehouse = $model->table('erp_purchase_in p')
                    ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein",'left')
                    //->field('pi.id_product_sku,pi.received')
                    ->field('pi.id_product_sku,sum(pi.received) as received')
                    ->group('pi.id_product_sku')
                    ->where($where_no_warehouse)->select();

        foreach($total_no_warehouse as $nkey=>$nval){

            $purchase_no_swarehouse[$nval['id_product_sku']]['id_product_sku']   =  $nval['id_product_sku'];
            $purchase_no_swarehouse[$nval['id_product_sku']]['received']   +=  $nval['received'];

        }
        $purchase_no_swarehouse = array_merge($purchase_no_swarehouse);

        /* 总退货数 zx 11-20 先暂时注释，等采购退货上线后再修改 */
        /*$where_back_purchase['pi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_back_purchase['p.status'] = array("EQ",4); //退货 received
        $back_warehouse = $model->table('erp_purchase_in p')
                    ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein",'left')
                    ->field('pi.id_product_sku,pi.received')
                    ->where($where_back_purchase)->select();
        foreach($back_warehouse as $bkey=>$bval){
            $back_warehouse[$bval['id_product_sku']]['count'] +=  $bval['received'];

            $back_swarehouse[$bval['id_product_sku']]['id_product_sku']   =  $bval['id_product_sku'];
            $back_swarehouse[$bval['id_product_sku']]['received']   =  $back_warehouse[$bval['id_product_sku']]['count'] ;

        }
        $back_swarehouse = array_merge($back_swarehouse);*/
        /* 总退货数 zx 11-20 先暂时注释，等采购退货上线后再修改 */
    }
        
        /* 重构统计end zx  11/20 */
        foreach ($staticsinfo  as $key=> $itme ){
            $staticsinfo[$key]['pro_name']=$itme['inner_name']?$itme['inner_name']:$itme['title'];
            $img = json_decode($itme['thumbs'], true);
            $staticsinfo[$key]['img'] = $img['photo'][0]['url'];
            // 拼接产品单价 zx 11/18
            foreach($purchase_sprices as $sikey=>$sival){
                if($itme['id_product_sku'] == $sival['id_product_sku']){
                    $staticsinfo[$key]['single_price'] = $sival['single_price'];
                    break;
                }
            }
            // 拼接总产品数 zx 11/20
            foreach($product_stNumber as $pkey=>$pval){
                if($itme['id_product_sku'] == $pval['id_product_sku']){
                    $staticsinfo[$key]['total_product'] = $pval['total'];
                    break;
                }
            }

            // 拼接总采购数 zx 11/20
            foreach($purchase_stNumber as $tkey=>$tval){
                if($itme['id_product_sku'] == $tval['id_product_sku']){
                    $staticsinfo[$key]['total_purchase'] = $tval['total'];
                    break;
                }
            }
            
            // 拼接总退货数 zx 12/06
            foreach($total_sReturn as $rsk=>$rsv){
                if($itme['id_product_sku'] == $rsv['id_product_sku']){
                    $staticsinfo[$key]['total_return'] = $rsv['return_total'];
                    break;
                }
            }
            
            // 拼接总入库数 zx 11/18
            foreach($purchase_swarehouse as $wkey=>$wval){
                if($itme['id_product_sku'] == $wval['id_product_sku']){
                    $staticsinfo[$key]['total_warehouse'] = $wval['received'];
                    break;
                }
            }
            // 拼接总未入库数 zx 11/18
            foreach($purchase_no_swarehouse as $nkey=>$nval){
                if($itme['id_product_sku'] == $nval['id_product_sku']){
                    $staticsinfo[$key]['total_no_warehouse'] = $nval['received'];
                    break;
                }
            }
            // 拼接总退货数 zx 11/20 先暂时注释，等采购退货上线后再修改
            /*foreach($back_swarehouse as $bkey=>$bkey){
                if($itme['id_product_sku'] == $bkey['id_product_sku']){
                    $staticsinfo[$key]['back_swarehouse'] = $bkey['received'];
                    break;
                }
            }*/

        }

        $ordercnt=M('order o')->where($where)
            ->join("{$orderitem_table} oi on o.id_order=oi.id_order",'left')
            ->join("{$product_table} pt on pt.id_product=oi.id_product",'left')
              ->count();
        $product_count=array_sum(array_column($staticsinfo, 'quantity'));
        $this->assign('shippings', $shipList);
        $this->assign('status_list', $status_model);
        $this->assign('statistics', $staticsinfo);
        $this->assign('product_count', $product_count);
        $this->assign('order_count', $ordercnt);
        $this->assign('post', $_POST);
        $this->assign("department_id", $_POST['id_department']);
        $this->assign("departmentlist", $departmentList);
        $this->assign('warehouse', $warehouseList);
        $this->display();
    }

        /**
     * 统计指定时间内的指定产品的销售数据
     */
    public function statistics1() {
        //默认显示
        //昨天订单
        //状态[未配货][配货中]
        $where = array();
//        $_POST['time_start'] = $time_start;
//        $_POST['time_end'] = $time_end;
        $status_id = I('post.status_id');
        $shippingId = I('post.shipping_id');
        $department_ids = I('post.id_department');
        $warehouse_id = I('post.warehouse_id');
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where['id_department'] = isset($department_ids) && $department_ids != '' ? array('EQ', $department_ids) : array('IN', $department_id);
        if ($shippingId) {
            $where[] = "`id_shipping` = '$shippingId'";
        }
        if ($warehouse_id) {
            $where[] = "`id_warehouse` = '$warehouse_id'";
        }

        $effective_status = \Order\Lib\OrderStatus::get_effective_status();
        if ($status_id > 0) {
            $where[] = "`id_order_status` = '$status_id'";
        } else {
            $where[] = "`id_order_status` IN (".implode(',', $effective_status).")"; //只需要有效订单
        }

        if (isset($_POST['start_time']) && $_POST['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_POST['start_time']);
            if ($_POST['end_time']) {
                $createAtArray[] = array('LT', $_POST['end_time']);
            }
        }else{
            $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-7 days')));
            $createAtArray[] = array('LT', date('Y-m-d', strtotime('+1 day')));
        }

        $where[] = array('created_at' => $createAtArray);
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
                i.product_title,i.sku_title, i.id_order_item order_item_id')
            ->join("__ORDER_ITEM__ i ON (__ORDER__.id_order = i.id_order)", 'LEFT')
            ->where($where)
            ->order('i.sku ASC')
            ->select();
//        dump($orders);die;
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
            if (empty($product_name))
                $product_name = $products[(int) $val['id_product']]['title'];
            $img = json_decode($products[(int) $val['id_product']]['thumbs'], true);
            $orders[$key]['pro_name'] = $product_name;
            $orders[$key]['img'] = $img['photo'][0]['url'];
            $orders[$key]['sku'] = M('ProductSku')->where(array('id_product_sku'=>$val['id_product_sku']))->getField('sku');
        }

        $results = $this->get_arr($orders);

//        foreach ($orders as $o) {
//            $order_count[] = $o['order_id'];
////            if (isset($shippings[$o['id_shipping']])) {
////                $shipping_name = $shippings[$o['id_shipping']]['title'];
////            }else{
////                $shipping_name = '无物流';
////            }
//            $shipping_name = '无物流';
//
//            $img = json_decode($products[(int) $o['id_product']]['thumbs'], true);
//            //直接使用产品的内部名称
//            $product_name = $products[(int) $o['id_product']]['inner_name'];
//            if (empty($product_name))
//                $product_name = $products[(int) $o['id_product']]['title'];
//
//            if (!isset($stat[$shipping_name][$product_name])) {
//                $stat[$shipping_name][$product_name] = array();
//            }
//
//            $attrIdMd5 = '';
//            if (!isset($stat[$shipping_name][$product_name][$o['sku_title']])) {
//                $stat[$shipping_name][$product_name][$o['sku_title']]['qty'] = (int) $o['quantity'];
//            } else {
//                $stat[$shipping_name][$product_name][$o['sku_title']]['qty'] += (int) $o['quantity'];
//            }
////            $stat[$shipping_name][$product_name][$o['sku_title']]['status_title'] = D('Order/OrderStatus')->where('id_order_status='.$o['id_order_status'])->getField('title');
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
//
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
        $department_id = $_SESSION['department_id'];
        $department = D('Department/Department')->where('type=1')->cache(true, 3600)->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
        $status_model = D('Order/OrderStatus')->field('id_order_status,title')->where('status=1 and id_order_status IN (4,5,6,7,8,9,10)')->select();
        $status_model = array_column($status_model, 'title', 'id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看销售统计');
        $this->assign('shippings', $shippings);
        $this->assign('status_list', $status_model);
        $this->assign('statistics', $results);
//        $this->assign('stat_shipping', $stat_shipping);
//        $this->assign('stat_product', $stat_product);
        $this->assign('product_count', $product_count);
        $this->assign('order_count', $order_count);
        $this->assign('post', $_POST);
//        $this->assign('attr_sku', $tempProModel);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
//        var_dump($department);die;
        $this->assign('warehouse', $warehouse);
        $this->display();
    }

    public function export_statistics(){
        set_time_limit(0);
        ini_set("memory_limit","-1");
       //参数筛选
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();//有效状态订单
        $where=[];
        if($_POST['warehouse_id']){
            $where['o.id_warehouse']=$_POST['warehouse_id'];
        }
        if($_POST['shipping_id']){
            $where['o.id_shipping']=$_POST['shipping_id'];
        }
        if($_POST['id_department']){
            $where['o.id_department']=$_POST['id_department'];
        }else{
            $where['o.id_department']=array('in',$_SESSION['department_id']);
        }
        if($_POST['status_id']){
            $where['o.id_order_status']=$_POST['status_id'];
        }else{
            $where['o.id_order_status']=array('in',$effective_status);
        }
        // 增加sku 内部名的搜索 导出  -- Lily  20171104
        if($_POST['sku']){
            $where['oi.sku'] = array("EQ",$_POST['sku']);
        }
        if($_POST['innername']){
            $where['pt.inner_name'] = array("LIKE",'%'.$_POST['innername'].'%');
        }
        if (isset($_POST['start_time']) && $_POST['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_POST['start_time']);
            if ($_POST['end_time']) {
                $createAtArray[] = array('LT', $_POST['end_time']);
            }
        }else{
            //默认时间变为前一天 zx 11/20
            $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-1 days')));
            $createAtArray[] = array('LT', date('Y-m-d', strtotime('+1 day')));
        }
        $where[] = array('o.created_at' => $createAtArray);

        $departmentList = M('department')->where(array('type' => 1))->where(array('id_department'=>array('in',$_SESSION['department_id'])))->getField('id_department,title');
        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        $warehouseList = M('warehouse')->where(array('status' => 1))->getField('id_warehouse,title');
        $statusmodecon=array('status'=>1,'id_order_status'=>array('in',$effective_status));
        $status_model = D('Order/OrderStatus')->where($statusmodecon)->getField('id_order_status,title');
        $orderitem_table = D('Order/orderItem')->getTableName();
        $product_table = D('product')->getTableName();
        //有效单
        //array_push($effective_status, OrderStatus::VERIFICATION);

        //新增1个链表查询 __WAREHOUSE_PRODUCT__(获取 在途量 库存量)  11/13 zx
        $fields="SUM(IF(`id_order_status` IN(".implode(',', $effective_status)."),1,0)) as effective_number,o.id_order_status,oi.id_product_sku,oi.sku,oi.sku_title,oi.id_product,pt.inner_name,pt.thumbs,pt.title,sum(oi.quantity) as quantity,wp.quantity as wp_quantity,wp.road_num,wp.qty_preout,pt.purchase_url,pt.pro_msg";
        $staticsinfo=M('order o')
                ->join("{$orderitem_table} oi on o.id_order=oi.id_order",'left')
                ->join("{$product_table} pt on pt.id_product=oi.id_product",'left')
                ->join("__WAREHOUSE_PRODUCT__ as wp on oi.id_product_sku=wp.id_product_sku",'left')
                ->where($where)->field($fields)->group('oi.id_product_sku')->select();
        /* 重新计算 zx 11/20 */
        //取出所有的 id_product_sku
        $sku_arr = array_column( $staticsinfo,'id_product_sku');
        $where_sku['id_product_sku'] = array("IN",implode(',', $sku_arr));
        $purchase_products = M("PurchaseProduct")->field('id_product_sku,price,quantity')->where($where_sku)->select();
        // 重算平均采购单价 zx  11/18
        foreach($purchase_products as $skey=>$sval){
            $purchase_prices[$sval['id_product_sku']]['count'] +=  $sval['quantity'];
            $purchase_prices[$sval['id_product_sku']]['sum']   +=  (int)$sval['price']*$sval['quantity'];
            $purchase_sprices[$sval['id_product_sku']]['id_product_sku']   =  $sval['id_product_sku'];
            //平均采购单价
            $purchase_sprices[$sval['id_product_sku']]['single_price'] = round($purchase_prices[$sval['id_product_sku']]['sum']/$purchase_prices[$sval['id_product_sku']]['count'],2);
        }
        $purchase_sprices = array_merge($purchase_sprices);

        $model = new \Think\Model();
        //总产品数 orderItem
        $where_total_product['oi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_total_product['o.id_order_status'] = array("IN",$effective_status);
        $total_product = $model->table('erp_order o')
                    ->join("__ORDER_ITEM__ oi ON o.id_order=oi.id_order","left")
                    ->field('oi.quantity,oi.id_product_sku')
                    ->where($where_total_product)->select();
        foreach($total_product as $pkey=>$pval){

            $product_stNumber[$pval['id_product_sku']]['id_product_sku']   =  $pval['id_product_sku'];
            $product_stNumber[$pval['id_product_sku']]['total']  +=  $pval['quantity'];

        }
        $product_stNumber = array_merge($product_stNumber);

        //总采购数 PurchaseInitem PurchaseIn
        $where_total_purchase['pi.id_product_sku'] = array("IN",implode(',', $sku_arr));;
        $where_total_purchase['po.status'] = array("EQ",5); //已付款
        $total_purchase = $model->table('erp_purchase_in p')
                    ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein",'left')
                    ->join("__PURCHASE__ as po on p.id_erp_purchase=po.id_purchase",'left')
                    ->field('pi.id_product_sku,sum(pi.quantity) as total')
                    ->group('pi.id_product_sku')
                    ->where($where_total_purchase)->select();
        foreach($total_purchase as $tkey=>$tval){

            $purchase_stNumber[$tval['id_product_sku']]['id_product_sku']   =  $tval['id_product_sku'];
            $purchase_stNumber[$tval['id_product_sku']]['total'] +=  $tval['total'];

        }
        $purchase_stNumber = array_merge($purchase_stNumber);
        
        //总退货数 return_goods return_goods_item zx 12/06
        $where_total_return['rgi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_total_return['rg.warehouse_status'] = array("EQ",2); //已退货
        $total_return = $model->table('erp_return_goods rg')
                    ->join("__RETURN_GOODS_ITEM__ as rgi on rgi.id_return_goods=rg.id_return",'left')
                    ->field('rgi.c_qty_true,rgi.id_product_sku,rg.id_return')
                    ->where($where_total_return)
                    ->select();
        foreach($total_return as $rkey=>$rval){
            $total_sReturn[$rval['id_product_sku']]['id_product_sku']   =  $rval['id_product_sku'];
            $total_sReturn[$rval['id_product_sku']]['return_total']   +=  abs($rval['c_qty_true']);

        }
        $total_sReturn = array_merge($total_sReturn);

        //总入库数 PurchaseInitem PurchaseIn
        $where_warehouse['pi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_warehouse['p.status'] = array("IN",[2,3]); //已入库+部分入库 received
        $total_warehouse = $model->table('erp_purchase_in p')
                    ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein",'left')
                    ->field('pi.id_product_sku,sum(pi.received) as received')
                    ->group('pi.id_product_sku')
                    ->where($where_warehouse)->select();
        foreach($total_warehouse as $wkey=>$wval){

            $purchase_swarehouse[$wval['id_product_sku']]['id_product_sku']   =  $wval['id_product_sku'];
            $purchase_swarehouse[$wval['id_product_sku']]['received']  +=  $wval['received'];

        }
        $purchase_swarehouse = array_merge($purchase_swarehouse);

        //总未入库数
        $where_no_warehouse['pi.id_product_sku'] = array("IN",implode(',', $sku_arr));
        $where_no_warehouse['p.status'] = array("EQ",1); //未入库 received
        $total_no_warehouse = $model->table('erp_purchase_in p')
                    ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein",'left')
                    ->field('pi.id_product_sku,sum(pi.received) as received')
                    ->group('pi.id_product_sku')
                    ->where($where_no_warehouse)->select();

        foreach($total_no_warehouse as $nkey=>$nval){

            $purchase_no_swarehouse[$nval['id_product_sku']]['id_product_sku']   =  $nval['id_product_sku'];
            $purchase_no_swarehouse[$nval['id_product_sku']]['received'] +=  $nval['received'];

        }
        $purchase_no_swarehouse = array_merge($purchase_no_swarehouse);
        /* 重构统计 zx 11/20 */
        foreach ($staticsinfo  as $key=> $itme ){

            // 拼接产品单价 zx 11/18
            foreach($purchase_sprices as $sikey=>$sival){
                if($itme['id_product_sku'] == $sival['id_product_sku']){
                    $staticsinfo[$key]['single_price'] = $sival['single_price'];
                    break;
                }
            }
            // 拼接总产品数 zx 11/20
            foreach($product_stNumber as $pkey=>$pval){
                if($itme['id_product_sku'] == $pval['id_product_sku']){
                    $staticsinfo[$key]['total_product'] = $pval['total'];
                    break;
                }
            }

            // 拼接总采购数 zx 11/20
            foreach($purchase_stNumber as $tkey=>$tval){
                if($itme['id_product_sku'] == $tval['id_product_sku']){
                    $staticsinfo[$key]['total_purchase'] = $tval['total'];
                    break;
                }
            }
            
            // 拼接总退货数 zx 12/06
            foreach($total_sReturn as $rsk=>$rsv){
                if($itme['id_product_sku'] == $rsv['id_product_sku']){
                    $staticsinfo[$key]['total_return'] = $rsv['return_total'];
                    break;
                }
            }
            
            // 拼接总入库数 zx 11/18
            foreach($purchase_swarehouse as $wkey=>$wval){
                if($itme['id_product_sku'] == $wval['id_product_sku']){
                    $staticsinfo[$key]['total_warehouse'] = $wval['received'];
                    break;
                }
            }
            // 拼接总未入库数 zx 11/18
            foreach($purchase_no_swarehouse as $nkey=>$nval){
                if($itme['id_product_sku'] == $nval['id_product_sku']){
                    $staticsinfo[$key]['total_no_warehouse'] = $nval['received'];
                    break;
                }
            }

        }
        /* 重构统计 zx 11/20 */
        
        //重构导出格式 zx 11/23
        $getField = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N');
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $objPHPExcel = new \PHPExcel();
        $setRowName = array('产品','属性','SKU','销售数量','有效订单','总产品数','总采购数','总入库数','总未入库','在途量','库存量','单价','备注','拿货链接');
        $num  = 2;
        foreach($setRowName as $r=>$v){
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$r].'1',$v);
            $j++;
        }
            
         foreach($staticsinfo as $order){
              $return_purchase = 0;  
              $pro_name=$order['inner_name']?$order['inner_name']:$order['title'];
                if(!$order['id_product']){
                    continue;
                }

                if($order['total_purchase'] >= $order['total_warehouse']){
                    $rel_warehouse = $order['total_warehouse'];
                }else{
                    $rel_warehouse = $order['total_purchase'];
                }
                //总采购 = 总采购 - 总已退货
                $return_purchase = $order['total_purchase'] - $order['total_return'];
                
                $tempRow = array(
                    $pro_name,
                    trim($order['sku_title'],','),
                    $order['sku'],
                    $order['quantity']?$order['quantity']:0,
                    $order['effective_number']?$order['effective_number']:0,
                    $order['total_product']?$order['total_product']:0,
                    $return_purchase,
                    $rel_warehouse?$rel_warehouse:0,
                    $order['total_no_warehouse']?$order['total_no_warehouse']:0,
                    $order['road_num']?$order['road_num']:0,
                    $order['wp_quantity']?$order['wp_quantity']:0,
                    $order['single_price']?$order['single_price']:0,
                    $order['pro_msg'],
                    $order['purchase_url']
                );
                foreach ($tempRow as $row => $value) {
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($getField[$row] . $num, $value);
                }
                $num++;
            }
        
        add_system_record($_SESSION['ADMIN_ID'], 7, 4, '销售统计');
        $objPHPExcel->getActiveSheet()->setTitle('order');
        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.date('Y-m-d').'销售统计.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;

    }

    protected function export_csv($filename, $data) {
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=" . $filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }

    /**
     * 导出销售统计
     */
    public function export_statistics1() {

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '产品','属性','SKU','销售数量'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

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
        if ($warehouse_id) {
            $where[] = "`id_warehouse` = '$warehouse_id'";
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
                i.product_title,i.sku_title, i.id_order_item order_item_id')
            ->join("__ORDER_ITEM__ i ON (__ORDER__.id_order = i.id_order)", 'LEFT')
            ->where($where)
            ->order('i.sku ASC')
            ->select();

        $order_count = array(); //订单总数
        $product_count = 0; // 产品总数

        foreach ($orders as $key=>$val){
            $order_count[] = $val['order_id'];
            $product_count += $val['quantity'];
            $product_name = $products[(int) $val['id_product']]['inner_name'];
            if (empty($product_name))
                $product_name = $products[(int) $val['id_product']]['title'];
            $img = json_decode($products[(int) $val['id_product']]['thumbs'], true);
            $orders[$key]['pro_name'] = $product_name;
            $orders[$key]['img'] = $img['photo'][0]['url'];
            $orders[$key]['sku'] = M('ProductSku')->where(array('id_product_sku'=>$val['id_product_sku']))->getField('sku');
        }

        $results = $this->get_arr($orders);

        foreach ($results as $key=>$val) {
            $data = array(
                $val['pro_name'],$val['sku_title'],$val['sku'],$val['quantity']
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 2, '导出销售统计列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '销售统计列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '销售统计列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
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

    //每日统计
    public function every_day() {
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != '' ? array('EQ', $_GET['id_department']) : array('IN', $department_id);
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
        }else{
            $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-7 days')));
            $createAtArray[] = array('LT', date('Y-m-d', strtotime('+1 day')));
        }

        $where[] = array('created_at' => $createAtArray);
        $effective_status = OrderStatus::get_effective_status();
        $field = "SUBSTRING(created_at,1,10) AS set_date,SUM(IF(`id_order_status` IN(".implode(',', $effective_status)."),1,0)) as effective,
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
        $where2['type'] = 1;
        //部门筛选过滤,如不需过滤，直接删掉
        $where2['id_department'] = array('IN',$department_id);
        //部门筛选
        $department = D('Department/Department')->where($where2)->cache(true, 3600)->order('sort asc')->select();
        //$department = $department ? array_column($department, 'title', 'id_department') : array();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看状态统计');
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("list", $selectOrder);
        //$this->assign("shipping",$shipping);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /**
     * 搜索提示供应商
     */
    public function get_supp_attr() {
        $keyword = trim($_POST['value']);
        $pro_id = $_POST['pro_id'];
        if (!empty($keyword)) {
            $map['title'] = array('like', '%' . $keyword . '%');
            $map['id_users'] = array('EQ',sp_get_current_admin_id());
            $supplier = M('Supplier')->field('id_supplier,title')->where($map)->select();
//            echo $supplier;die;
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
            if(!empty($pro_id)) {
                $pur = M('Purchase')->alias('p')->join('__PURCHASE_PRODUCT__ AS pp ON pp.id_purchase=p.id_purchase')->field('p.id_supplier')->where(array('pp.id_product'=>$pro_id))->group('p.id_purchase')->select();
                $id_suppliers = array_column($pur, 'id_supplier');
                if($id_suppliers) $supplier = M('Supplier')->field('id_supplier,title')->where(array('id_supplier'=>array('IN',$id_suppliers)))->select();
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
        }
        echo $data;
    }

    public function get_supplier_title() {
        if(IS_AJAX) {
            $url = trim($_POST['value']);
            $id_department = $_POST['id_department'];
            $supplier = M('Supplier')->where(array('supplier_url' => $url, 'id_department' => $id_department))->find();
            if ($supplier) {
                $data = array(
                    'id' => $supplier['id_supplier'],
                    'title' => $supplier['title'],
                );
            } else {
                $data = '';
            }
            echo json_encode($data);die;
        }
    }

    /**
     * 搜索提示产品名称
     */
    public function get_product_title() {
        $keyword = trim($_POST['value']);
        $id_department = $_POST['id_department'];
        if (!empty($keyword)) {
            $where['p.inner_name'] = array('like', '%' . $keyword . '%');
           // if ($id_department) $where['p.id_department'] = array('EQ', $id_department);
            $where['ps.status'] = 1;
            $product = M('Product')->alias('p')->join('__PRODUCT_SKU__ ps ON ps.id_product=p.id_product')->field('p.id_product,p.inner_name')->where($where)->group('p.id_product')->select();
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

    /**
     * 搜索供应商链接
     */
    public function get_supp_url() {
        $supp_id = I('post.supp_id');//供应商ID
        $id_department = $_POST['id_department'];
        $supplier = M('Supplier')->field('supplier_url')->where(array('id_supplier' => $supp_id))->find();
        if ($supplier) {
            $url = $supplier['supplier_url'];
        } else {
            $url = '';
        }
        echo $url;die;
    }

    /**
     * 待审核的采购列表
     */

    public function waiting_approval() {
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $supplier = M('Supplier')->getField('id_supplier,title');
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $purchase_no=  trim($_GET['purchase_no']);
            $where['purchase_no'] = array('like', "%{$purchase_no}%");
        }
        if (isset($_GET['inner_name']) && $_GET['inner_name']) {
            $where_pro['inner_name'] = $_GET['inner_name'];
        }
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        //增加采购员名字筛选
        if (isset($_GET['shop_id']) && $_GET['shop_id']) {
            //$id_users = M('Users')->where(array('user_nicename' => $_GET['shop_id']))->getField('id');
            $where['id_users'] = array('EQ', $_GET['shop_id']);
        }
        /* 此功能已被 采购员名字筛选 替换
        if (isset($_GET['id_users']) && $_GET['id_users']) {
            $id_users = M('Users')->where(array('user_nicename' => $_GET['id_users']))->getField('id');
            $where['id_users'] = $id_users;
        }*/
        $purchaseTimeArray = array();
        if (isset($_GET['start_purchase_time']) && $_GET['start_purchase_time']) {
            $purchaseTimeArray[] = array('EGT',$_GET['start_purchase_time']);
        }
        if(isset($_GET['end_purchase_time']) && $_GET['end_purchase_time']) {
            $purchaseTimeArray[] = array('LT',$_GET['end_purchase_time']);

        }
        if(!empty($purchaseTimeArray)){
            $where['inner_purchase_time'] =$purchaseTimeArray;
        }
        $dep = $_SESSION['department_id'];
        if (isset($_GET['depart_id']) && $_GET['depart_id']) {
            $where['id_department'] = array('EQ', $_GET['depart_id']);
        } else {
            $where['id_department'] = array('IN', $dep);
        }

//        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
//        $where['id_department'] = array('IN',$department_id);
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
            if(!empty($id_purchase)){
                $where[] = substr($new, 0, -3);
            }else{
                //为空就找不到
                $where['id_purchase'] =-1;
            }

        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
        }else{
            $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-7 days')));
            $createAtArray[] = array('LT', date('Y-m-d', strtotime('+1 day')));
        }

        $where[] = array('created_at' => $createAtArray);
        if(isset($_REQUEST['status_id'])&&$_REQUEST['status_id']==0){
            $where['status'] = array('IN',[PurchaseStatus::UNCHECK,PurchaseStatus::FINISHCHECK,PurchaseStatus::REJECTCHECK]);
        }elseif(isset($_REQUEST['status_id'])&&$_REQUEST['status_id']==PurchaseStatus::FINISHCHECK){
            $where['status'] = PurchaseStatus::FINISHCHECK;
        }elseif(isset($_REQUEST['status_id'])&&$_REQUEST['status_id']==PurchaseStatus::REJECTCHECK){
            $where['status'] = PurchaseStatus::REJECTCHECK;
        }else{
            $where['status'] = PurchaseStatus::UNCHECK;
            $_GET['status_id']= PurchaseStatus::UNCHECK;
        }

        $count = $this->Purchase->where($where)->count();
        $page = $this->page($count,20);
        $lists = $this->Purchase->where($where)->order('created_at DESC')->limit($page->firstRow, $page->listRows)->select();
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
                ->field('pp.*,ps.*,p.thumbs,p.inner_name')
                ->join('__PRODUCT_SKU__ as ps on ps.id_product_sku = pp.id_product_sku', 'LEFT')
                ->join('__PRODUCT__ as p on p.id_product = pp.id_product', 'LEFT')
                ->where($where_pro)->select();
            $lists[$key]['user_nicename'] = M('Users')->where(array('id' => $list['id_users']))->getField('user_nicename');
        }
        //查询所有采购部人员
        $shop_users = M()->query("SELECT a.id,a.user_nicename,b.* FROM erp_users AS a LEFT JOIN erp_department_users AS b ON a.id=b.id_users WHERE b.id_department=19");

        $pur_status=[PurchaseStatus::UNCHECK=>'未审核',PurchaseStatus::FINISHCHECK =>'已审核',PurchaseStatus::REJECTCHECK=>'审核拒绝'];
        $this->assign('pur_status', $pur_status);

        $where2['type'] = 1;
        //部门筛选过滤,如不需过滤，直接删掉
        if (I('get.id_department')){
            $where2['id_department'] = I('get.id_department');
        }else{
            $where2['id_department'] = array('IN',$dep);
        }
        //部门筛选过滤
        $depart = M('Department')->where($where2)->order('sort asc')->cache(true, 3600)->select();
        $this->assign('depart', $depart);
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看待审核采购订单列表');
        $this->assign('warehouse', $warehouse);
        $this->assign('shop_users', $shop_users); //所有采购部人员
        $this->assign('supplier', $supplier);
        $this->assign('lists', $lists);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }

    /**
     * 批量审核采购订单
     */

    public function check_purchase() {
        $purchase_no = $_REQUEST['purchase_no'];
        $check = $_REQUEST['check'];
        if ($check == 'pass') {
            $data['status'] = PurchaseStatus::FINISHCHECK;
            $data['updated_at'] = date('Y-m-d H:i:s');
            $purchase_no = explode(',',$purchase_no);
            foreach($purchase_no as $v){
                $id_purchase = M('Purchase')->where(array('purchase_no'=>$v))->getField('id_purchase');
                if($id_purchase)
                    $res = D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::FINISHCHECK, '已通过审核');
            }
        } elseif ($check == 'refuse') {
            $data['status'] = PurchaseStatus::REJECTCHECK;
            $data['remark'] = $_REQUEST['reason'];
            $data['updated_at'] = date('Y-m-d H:i:s');
            $purchase_no = explode(',',$purchase_no);
            foreach($purchase_no as $v){
                $id_purchase = M('Purchase')->where(array('purchase_no'=>$v))->getField('id_purchase');
                if($id_purchase)
                    $res = D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::REJECTCHECK, '已驳回审核'.',原因是'.$data['remark']);
            }
        }
        $result = $this->Purchase->where(array('purchase_no' => array('IN', $purchase_no)))->save($data);
        if ($result) {
            $flag = 0;
            $msg = '审核完成';
        } else {
            $flag = 1;
            $msg = '审核失败';

        }
        echo json_encode(array('flag' => $flag, 'msg' => $msg));
        exit;

    }

    /**
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
            $id_purchase=intval($id_purchase);
            $list['purchase_product'] = $this->PurchaseProduct->alias('pp')->field('pp.*,wp.*,ps.*,pp.quantity as quantity,p.title as ptitle')
                ->join('__WAREHOUSE_PRODUCT__ as wp on wp.id_product_sku = pp.id_product_sku', 'LEFT')
                ->join('__PRODUCT_SKU__ as ps on ps.id_product_sku = pp.id_product_sku', 'LEFT')
                ->join('__PRODUCT__ as p on p.id_product = pp.id_product', 'LEFT')
                ->where(array('pp.id_purchase' => $id_purchase,'wp.id_warehouse'=>$list['id_warehouse']))->select();
        }
        if (IS_POST) {
            $check = $_POST['check'];
            $id_purchase = $_POST['id_purchase'];
            if ($check == 'pass') {
                $data['status'] = PurchaseStatus::FINISHCHECK;
                $data['updated_at'] = date('Y-m-d H:i:s');
                D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::FINISHCHECK, '已通过审核');
            }

            if ($check == 'refuse') {
                $data['status'] = PurchaseStatus::REJECTCHECK;
                $data['updated_at'] = date('Y-m-d H:i:s');
                D("Purchase/PurchaseStatus")->add_pur_history($id_purchase, PurchaseStatus::REJECTCHECK, '已驳回审核'.',原因是'.$data['remark']);
            }
            $result = $this->Purchase->where(array('id_purchase' => $id_purchase))->save($data);
            if ($result) {
                $this->success("审核完成！", U('index/waiting_approval'));
            } else {

                $this->error("审核失败！", U('index/check_single', array('id_purchase' => $id_purchase)));
            }
            exit;
        }
        add_system_record($_SESSION['ADMIN_ID'], 6, 3, '审核采购单');


        $department = M('Department')->where(array('id_department'=>$list['id_department']))->getField('title');
        $nicename= M('Users')->where(array('id'=>$list['id_users']))->getField('user_nicename');
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看采购单打印页面');
        $this->assign('department',$department);
        $this->assign('nicename',$nicename);

        $supplier_name = D("Common/Supplier")->field('title as sup_title')->where('id_supplier=' . $list['id_supplier'])->find();
        $warehouse = M('Warehouse')->field('title as ware_title')->where('id_warehouse=' . $list['id_warehouse'])->find();
        $this->assign("supplier_name", $supplier_name);
        $this->assign("warehouse", $warehouse);

        $this->assign('list', $list);
        $this->display();
    }

    /**
     * 导出采购单
     */

    public function export_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '采购单号','内部采购单号','内部采购时间', '内部名','仓库', '供应商', '产品名', 'SKU', '单价', '采购金额',
            '采购数量', '总金额', '审核状态',
            '采购渠道', '创建人', '创建时间', '备注'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $purchase_no=  trim($_GET['purchase_no']);
            $where['purchase_no'] = array('like', "%{$purchase_no}%");
        }
        $dep = $_SESSION['department_id'];
        if (isset($_GET['depart_id']) && $_GET['depart_id']) {
            $where['id_department'] = array('EQ', $_GET['depart_id']);
        } else {
            $where['id_department'] = array('IN', $dep);
        }
        //增加采购员名字筛选
        if (isset($_GET['shop_id']) && $_GET['shop_id']) {
            //$id_users = M('Users')->where(array('user_nicename' => $_GET['shop_id']))->getField('id');
            $where['id_users'] = array('EQ', $_GET['shop_id']);
        }
        /* 此功能已被 采购员名字筛选 替换
        if (isset($_GET['id_users']) && $_GET['id_users']) {
            $id_users = M('Users')->where(array('user_nicename' => $_GET['id_users']))->getField('id');
            $where['id_users'] = $id_users;
        }*/
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if (isset($_GET['inner_name']) && $_GET['inner_name']) {
            $where_pro['inner_name'] = $_GET['inner_name'];
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
        if(isset($_REQUEST['status_id'])&&$_REQUEST['status_id']==0){
            $where['status'] = array('IN',[PurchaseStatus::UNCHECK,PurchaseStatus::FINISHCHECK,PurchaseStatus::REJECTCHECK]);
        }elseif(isset($_REQUEST['status_id'])&&$_REQUEST['status_id']==PurchaseStatus::FINISHCHECK){
            $where['status'] = PurchaseStatus::FINISHCHECK;
        }elseif(isset($_REQUEST['status_id'])&&$_REQUEST['status_id']==PurchaseStatus::REJECTCHECK){
            $where['status'] = PurchaseStatus::REJECTCHECK;
        }else{
            $where['status'] = PurchaseStatus::UNCHECK;
            $_GET['status_id']= PurchaseStatus::UNCHECK;
        }

       // $where['status'] = PurchaseStatus::UNCHECK;
        $purchases = $this->Purchase
            ->where($where)
            ->order("id_purchase ASC")
            ->limit(10000)->select();
        $warehouse = M('Warehouse')->where(array('statsu' => 1))->getField('id_warehouse,title');
        $supplier = M('Supplier')->getField('id_supplier,title');
        $pur_status=[PurchaseStatus::UNCHECK=>'未审核',PurchaseStatus::FINISHCHECK =>'已审核',PurchaseStatus::REJECTCHECK=>'审核拒绝'];
        foreach ($purchases as $o) {
            $where_pro['id_purchase'] = $o['id_purchase'];
            $user = M('Users')->where(array('id' => $o['id_users']))->getField('user_nicename');
//            $purchase_product = $this->PurchaseProduct
//                ->alias('pp')
//                ->join('__PRODUCT_SKU__ as ps on ps.id_product_sku = pp.id_product_sku', 'LEFT')
//                ->join('__PRODUCT__ as p on p.id_product = pp.id_product', 'LEFT')
//                ->where($where_pro)->select();
            $purchase_product = $this->PurchaseProduct->alias('pp')
                ->field('pp.*,ps.*,p.thumbs,p.inner_name')
                ->join('__PRODUCT_SKU__ as ps on ps.id_product_sku = pp.id_product_sku', 'LEFT')
                ->join('__PRODUCT__ as p on p.id_product = pp.id_product', 'LEFT')
                ->where($where_pro)->select();
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
                $sku = M('ProductSku')
                    ->where(array('id_product_sku' => $product['id_product_sku']))->find();
                $data[] = array(
                    $o['purchase_no'],$o['inner_purchase_no'],$o['inner_purchase_time'],$product['inner_name'],$warehouse[$o['id_warehouse']], $supplier[$o['id_supplier']], $sku['title'],
                    $sku['sku'],$product['price'], $product['price']*$product['quantity'],
                    $product['quantity'], $o['price'], $pur_status[$o['status']], $purchase_channel, $user, $o['created_at'], $o['remark']
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

    /**
     * 导出采购单（待付款）
     */

    public function export_search2() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $depart = M('Department')->where(array('type' => 1))->cache(true, 3600)->select();
        $depart = array_column($depart,'title','id_department');
        //筛选有效的用户
        $users = M('Users')->field('id,user_nicename')->select();
        $users = array_column($users, 'user_nicename', 'id');

        /*$column = array(
            '部门','采购单号', '内部采购单号','采购渠道订单号','内部名','仓库', '供应商', '产品名', 'SKU', '单价', '采购金额',
            '采购数量', '审核状态',
            '采购渠道', '创建人', '创建时间', '内部采购时间','备注'
        );*/
        $column = array(
            '部门',
            '采购单号', '采购内部单号', '采购快递单号', '采购渠道订单号', '内部采购时间','仓库', '供应商','广告员', '产品名', 'SKU', '采购单价',
            '采购数量', '采购金额', '总金额','总数量','运费', '采购渠道','状态', '创建人', '创建时间', '内部采购时间','备注'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        if (isset($_GET['department_id']) && $_GET['department_id']){
            if(count($_GET['department_id']) > 1){
              $where['id_department'] = array('IN', implode(",",$_GET['department_id'])); 
            }else{
               $where['id_department'] = array('EQ', implode(",",$_GET['department_id'])); 
            }
         }
        
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if (isset($_GET['inner_name']) && $_GET['inner_name']) {
            $where_pro['inner_name'] = $_GET['inner_name'];
        }
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $purchase_no=  trim($_GET['purchase_no']);
            $where['purchase_no'] = array('like', "%{$purchase_no}%");
        }
        if ($_GET['alibaba_no']) {
            $alibaba_no=  trim($_GET['alibaba_no']);
            $where['alibaba_no'] = array('like', "%{$alibaba_no}%");
        }
        //增加采购员名字筛选
        if (isset($_GET['shop_id']) && $_GET['shop_id']) {

            $where['id_users'] = array('EQ', $_GET['shop_id']);
        }
        /* 此功能已被 采购员名字筛选 替换
        if (isset($_GET['id_users']) && $_GET['id_users']) {
            $id_users = M('Users')->where(array('user_nicename' => $_GET['id_users']))->getField('id');
            $where['id_users'] = $id_users;
        }*/
        if (isset($_GET['warehouse_id']) && $_GET['warehouse_id']) {
            $where['id_warehouse'] = $_GET['warehouse_id'];
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
        $purchaseTimeArray = array();
        if (isset($_GET['start_purchase_time']) && $_GET['start_purchase_time']) {
            $purchaseTimeArray[] = array('EGT',$_GET['start_purchase_time']);
        }
        if(isset($_GET['end_purchase_time']) && $_GET['end_purchase_time']) {
            $purchaseTimeArray[] = array('LT',$_GET['end_purchase_time']);

        }
        if(!empty($purchaseTimeArray)){
            $where['inner_purchase_time'] =$purchaseTimeArray;
        }
        if(isset($_REQUEST['status_id'])&&$_REQUEST['status_id']==0){
            $where['status'] = array('IN',[PurchaseStatus::FINISHCHECK,PurchaseStatus::PAYMENT,PurchaseStatus::REJECTPAYMENT]);
        }elseif(isset($_REQUEST['status_id'])&&$_REQUEST['status_id']==PurchaseStatus::PAYMENT){
            $where['status'] = PurchaseStatus::PAYMENT;
        }elseif(isset($_REQUEST['status_id'])&&$_REQUEST['status_id']==PurchaseStatus::REJECTPAYMENT){
            $where['status'] = PurchaseStatus::REJECTPAYMENT;
        }else{
            $where['status'] = PurchaseStatus::FINISHCHECK;
            $_GET['status_id']= PurchaseStatus::FINISHCHECK;
        }
        $pur_status=[PurchaseStatus::FINISHCHECK =>'已审核',PurchaseStatus::PAYMENT=>'已付款',PurchaseStatus::REJECTPAYMENT=>'拒绝付款'];
       // $where['status'] = PurchaseStatus::FINISHCHECK;;

        $list = M()->table($this->Purchase->getTableName() . ' p')->join(array($this->Users->getTableName() . ' u on p.id_users = u.id'))->field('p.*,u.user_nicename')
            ->where($where)
            ->order("p.id_purchase DESC")
            ->select();

        $pur_status = M('PurchaseStatus')->field('id_purchase_status,title')->select(); //采购单状态
        $pur_status = array_column($pur_status, 'title', 'id_purchase_status');

        $sup_id = array();
        $ware_id = array();
        foreach ($list as $k => $v) {
//            var_dump($v);die();
            $sup_id[] = $v['id_supplier'];
            $ware_id[] = $v['id_warehouse'];
            $depart_id[] = $v['id_department'];

            $list[$k]['product'] = $this->get_pur_pro($v['id_purchase']);
            $list[$k]['pro_name'] = $this->get_pur_pro($v['id_purchase'], true);
            $list[$k]['status_name'] = $pur_status[$v['status']];
            $list[$k]['totalprice']=  array_sum(array_column($list[$k]['product'], 'itemtotalprice'))+$list[$k]['price_shipping'];

            switch ($v['purchase_channel']) {
                case 1:
                    $pur_channel_name = '阿里巴巴';
                    break;
                case 2:
                    $pur_channel_name = '淘宝';
                    break;
                case 3:
                    $pur_channel_name = '线下';
                    break;
                default :
                    $pur_channel_name = '';
            }
            $list[$k]['pur_channel_name'] = $pur_channel_name;
        }

        foreach ($sup_id as $k => $v) {
            $supplier_name = D("Common/Supplier")->field('title as sup_title')->where('id_supplier=' . $v)->find();
            $warehouse = M('Warehouse')->field('title as ware_title')->where('id_warehouse=' . $ware_id[$k])->find();
            $department = M('department')->field('title as depart_name')->where('id_department=' . $depart_id[$k])
                ->find();
            $list[$k]['sup_name'] = $supplier_name['sup_title'];
            $list[$k]['ware_name'] = $warehouse['ware_title'];
            $list[$k]['department_name'] = $department['depart_name'];
        }
        foreach($list as $p){
            /*$data[] = array(
                $p['department_name'],
                $p['purchase_no'],$p['inner_purchase_no'],$p['shipping_no'],$p['alibaba_no'],$p['inner_purchase_time'],$p['ware_name'],$p['sup_name'],'',$p['product'],'','','',$p['price'],$p['price_shipping'],$p['pur_channel_name'],$p['status_name'],$p['user_nicename']
            );*/

            $data[] = array(
                $p['department_name'],
                $p['purchase_no'],$p['inner_purchase_no'],$p['shipping_no'],$p['alibaba_no'],$p['inner_purchase_time'],$p['ware_name'],$p['sup_name'],'','',$p['product'],'','','',$p['price'],$p['total'],$p['price_shipping'],$p['pur_channel_name'],$p['status_name'],$p['user_nicename'],$p['created_at'],$p['inner_purchase_time'], $p['remark']
            );
        }
        if ($data) {
            $k = 2;
            $num = 2;
            $sum = 2;
            foreach ($data as $kk => $items) {
                $j = 65;
                $count = count($items[10]);
                if ($count > 1) {
                    $excel->getActiveSheet()->mergeCells("A" . ($num ? $num : $idx).":"."A" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("B" . ($num ? $num : $idx).":"."B" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("C" . ($num ? $num : $idx).":"."C" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("D" . ($num ? $num : $idx).":"."D" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("E" . ($num ? $num : $idx).":"."E" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("F" . ($num ? $num : $idx).":"."F" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("G" . ($num ? $num : $idx).":"."G" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("H" . ($num ? $num : $idx).":"."H" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("O" . ($num ? $num : $idx).":"."O" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("P" . ($num ? $num : $idx).":"."P" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("Q" . ($num ? $num : $idx).":"."Q" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("R" . ($num ? $num : $idx).":"."R" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("S" . ($num ? $num : $idx).":"."S" . (($num ? $num : $idx)
                            +$count-1));
                    $excel->getActiveSheet()->mergeCells("T" . ($num ? $num : $idx).":"."T" . (($num ? $num : $idx)
                            +$count-1));
                    $excel->getActiveSheet()->mergeCells("U" . ($num ? $num : $idx).":"."U" . (($num ? $num : $idx)
                            +$count-1));
                    $excel->getActiveSheet()->mergeCells("V" . ($num ? $num : $idx).":"."V" . (($num ? $num : $idx)
                            +$count-1));
                    $excel->getActiveSheet()->mergeCells("W" . ($num ? $num : $idx).":"."W" . (($num ? $num : $idx)
                            +$count-1));
                    /*echo ($num ? $num : $idx) . ':' . (($num ? $num : $idx)+$count-1);
                    $num = (($num ? $num : $idx)+$count);
                    echo "<br/>";
                    echo $num;
                    echo "<br/>";*/
                    $num = (($num ? $num : $idx)+$count);
                } else {
                    $num  += 1;
                }
                foreach ($items as $key => $col) {

                    if (is_array ($col)) {

                        $a = 0;
                        foreach($col as $c) {
                            $excel->getActiveSheet()->setCellValue("I" . $sum, $users[$c['id_users_sales']]);
                            $excel->getActiveSheet()->setCellValue("J" . $sum, $c['pro_name']);
                            $excel->getActiveSheet()->setCellValue("K" . $sum, $c['sku']);
                            $excel->getActiveSheet()->setCellValueExplicit("L". $sum, $c['price']);
                            $excel->getActiveSheet()->setCellValueExplicit("M". $sum, $c['quantity']);
                            $excel->getActiveSheet()->setCellValueExplicit("N". $sum, $c['itemtotalprice']);
                            $a++;
                            $sum = $sum+1;
                        }
                    } else {
                        $bb = $sum;
                        if ($key > 13) {
                            $bb = $sum - $count;
                        }
                        if (!in_array($key,[11,12,13])){
                            //echo $col;
                            //echo chr($j) . $bb."----$key-----$col<br/>";
                            if(in_array($key, array(1,4))){
                                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $bb, $col);
                            }else{
                                $excel->getActiveSheet()->setCellValue(chr($j) . $bb, $col);
                            }
                        }
                    }
                    ++$j;//横
                }
                ++$idx;//列
                ++$k;
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

    /**
     * 采购预警页面
     */

    public function purchase_warning() {
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);

        $de_where = array('type'=>1,'id_department'=>array('IN',$department_id));
        $department = M('Department')->where($de_where)->order(' sort asc ')->select();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        if (isset($_GET['department_id']) && $_GET['department_id']) {
            if($_GET['department_id'] == '1000'){
                $where['p.id_department'] = array('IN',$department_id);
            }else{
                $where['p.id_department'] = $_GET['department_id'];
            }
        }else{
            $where['p.id_department'] = $department_id[0];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where_pro['id_warehouse'] = $_GET['id_warehouse'];
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $where['sku'] = $_GET['sku'];
        }
        if (isset($_GET['innername']) && $_GET['innername']) {
            $where[] = "p.inner_name like '%" . $_GET['innername'] . "%'";
        }

        if (isset($_GET['title']) && $_GET['title']) {
            $where[] = "p.title like '%" . $_GET['title'] . "%'";
        }

        if (isset($_GET['order']) && $_GET['order'] == 'innername') {
         // echo 1;die;
            $count = M('Product')->alias('p')->field('p.inner_name,p.title,p.id_product,ps.sku,ps.title as sku_title,ps.id_product_sku')
                ->join('__PRODUCT_SKU__ as ps on ps.id_product = p.id_product')->order('inner_name ASC')->where($where)
                ->count();
            $page = $this->page($count, 60);
            $lists = M('Product')->alias('p')->field('p.inner_name,p.title,p.id_product,ps.sku,ps.title as sku_title,ps.id_product_sku,p.purchase_url')
                ->join('__PRODUCT_SKU__ as ps on ps.id_product = p.id_product')->order('inner_name ASC')->where($where)->limit($page->firstRow, $page->listRows)->select();
            foreach($lists as $key=>$product){
                $where_stockout['id_order_status'] = 6;
                $where_stockout['id_product_sku'] = $product['id_product_sku'];
                $stockout = M('Order')->alias('o')->field('sum(oi.quantity) as stockout')
                    ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order','LEFT')
                    ->join('__PRODUCT__ as p on p.id_product = oi.id_product','LEFT')
                    ->where($where_stockout)
                    ->find();
                $lists[$key]['stockout'] = $stockout['stockout'];
                $where_pro['id_product_sku'] = $product['id_product_sku'];
                $warehouse_product = M('WarehouseProduct')->where($where_pro)->select();
                foreach($warehouse_product as $k =>$v){
                    $warehouse_product[$k]['true_quantity']=$this->getTrueQuantity($where_pro['id_product_sku'],$v['quantity']);
                    //$warehouse_product[$k]['true_quantity']=$v['quantity']+1;
                }
                if($warehouse_product)
                    $lists[$key]['warehouse_product'] = $warehouse_product;
            }
        }elseif(isset($_GET['orderby']) && $_GET['orderby'] == 'daysale') {
         // echo 2;die;
            $where['id_order_status'] = array('IN','4,5,6,7,8,9,10,16');
            $date_start = date('Y-m-d',strtotime('-3 day'));
            $data_end = date('Y-m-d');
            $where['_string'] = "o.created_at >= '".$date_start."' and o.created_at < '".$data_end."'";
            $count = M('Order')->alias('o')
                ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order','LEFT')
                ->join('__PRODUCT__ as p on p.id_product = oi.id_product','LEFT')
                ->where($where)
                ->group('id_product_sku')
                ->select();
            $count = count($count);
            $sort='DESC';
            if(isset($_REQUEST['sort']) && !empty($_REQUEST['sort'])){
                $sort=$_REQUEST['sort'];
            }
            $page = $this->page($count, 60);
            $lists = M('Order')->field('count(o.id_order) as 3daysale,oi.id_product_sku,oi.id_product,oi.sku_title,oi.sku,p.id_department,p.inner_name,p.title as title,p.thumbs,p.purchase_url')->alias('o')
                ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order','LEFT')
                ->join('__PRODUCT__ as p on p.id_product = oi.id_product','LEFT')
                ->where($where)
                ->group('id_product_sku')
                ->order('3daysale '.$sort)
                ->limit($page->firstRow, $page->listRows)
                ->select();

            foreach ($lists as $key => $value) {
                $where_pro['id_product_sku'] = $value['id_product_sku'];
                $warehouse_product = M('WarehouseProduct')->where($where_pro)->select();
                foreach($warehouse_product as $k =>$v){
                    //$warehouse_product[$k]['true_quantity']=$v['quantity']+1;
                    $warehouse_product[$k]['true_quantity']=$this->getTrueQuantity($where_pro['id_product_sku'],$v['quantity']);
                }
                $lists[$key]['warehouse_product'] = $warehouse_product;
                $img = json_decode($value['thumbs'], true);
                $lists[$key]['img'] = $img['photo'][0]['url'];

                $where_stockout['o.id_order_status'] = 6;
                $where_stockout['oi.id_product_sku']= $value['id_product_sku'];
                $stockout = M('Order')->field('count(o.id_order) as stockout')->alias('o')
                    ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order','LEFT')
                    ->where($where_stockout)
                    ->group('oi.id_product_sku')
                    ->select();
                $lists[$key]['stockout']=$stockout[0]['stockout'];
            }

        }else{
            // echo 3;die;
            $where['id_order_status'] = 6;
            $count = M('Order')->alias('o')
                ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order','LEFT')
                ->join('__PRODUCT__ as p on p.id_product = oi.id_product','LEFT')
                ->where($where)
                ->group('id_product_sku')
                ->select();
            $count = count($count);
            $page = $this->page($count, 60);
            $sort='DESC';
            if(isset($_REQUEST['sort']) && !empty($_REQUEST['sort'])){
                $sort=$_REQUEST['sort'];
            }
            $lists = M('Order')->field('count(o.id_order) as order_sum,sum(oi.quantity) as stockout,oi.id_product_sku,oi.id_product,oi.sku_title,oi.sku,p.id_department,p.inner_name,p.title as title,p.thumbs,p.purchase_url,u.user_nicename,d.name')->alias('o')
                ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order','LEFT')
                ->join('__PRODUCT__ as p on p.id_product = oi.id_product','LEFT')
                ->join('__DOMAIN__ as d on d.id_domain = o.id_domain','LEFT')
                ->join('__USERS__ as u on u.id = o.id_users','LEFT')
                ->where($where)
                ->group('id_product_sku')
                ->order('stockout '.$sort)
                ->limit($page->firstRow, $page->listRows)
                ->select();
            foreach ($lists as $key => $value) {
                $where_pro['id_product_sku'] = $value['id_product_sku'];
                $warehouse_product = M('WarehouseProduct')->where($where_pro)->select();
                foreach($warehouse_product as $k =>$v){
                    //$warehouse_product[$k]['true_quantity']=$v['quantity']+1;
                    $warehouse_product[$k]['true_quantity']=$this->getTrueQuantity($where_pro['id_product_sku'],$v['quantity']);
                }
                $lists[$key]['warehouse_product'] = $warehouse_product;
                $img = json_decode($value['thumbs'], true);
                $lists[$key]['img'] = $img['photo'][0]['url'];
                $lists[$key]['pro_qty'] = $this->getProductQuantity($value['id_product']);
            }
            $lists = $this->array_sort($lists,'pro_qty',$sort);
        }
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看采购预警');
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign('newarr', $lists);
        $this->assign('department', $department);
        $this->assign('select_department', empty($_GET['department_id'])?$department_id[0]:intval($_GET['department_id']));
        $this->assign("warehouse", $warehouse);
        $this->display();
    }
    public function getTrueQuantity($id_product_sku,$available_quantity){
        $status_unpicking = \Order\Lib\OrderStatus::UNPICKING; //未配货
        $status_picked = \Order\Lib\OrderStatus::PICKED;     //已配货
        $status_approved = \Order\Lib\OrderStatus::APPROVED;   //已审核
        $related_order = M('Order')->alias('o')
            ->field("SUM(IF(o.id_order_status={$status_unpicking}, 1, 0)) AS unpicking_quantity,
                            SUM(IF(o.id_order_status={$status_picked}, 1, 0)) AS picked_quantity,
                            SUM(IF(o.id_order_status={$status_approved}, 1, 0)) AS approved_quantity")
            ->join("__ORDER_ITEM__ as oi ON o.id_order=oi.id_order", 'left')
            ->where(array('oi.id_product_sku'=>$id_product_sku))
            ->find();
        $unpicking_quantity = isset($related_order['unpicking_quantity']) ? $related_order['unpicking_quantity'] : 0;
        $picked_quantity = isset($related_order['picked_quantity']) ? $related_order['picked_quantity'] : 0;
        $approved_quantity = isset($related_order['approved_quantity']) ? $related_order['approved_quantity'] : 0;
        $actual_quantity = $available_quantity +$unpicking_quantity + $picked_quantity + $approved_quantity;
        return $actual_quantity;
    }
    public function getProductQuantity($id_product) {
        $lists = M('Order')->field('count(o.id_order) as stockout')->alias('o')
            ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order','LEFT')
            ->join('__PRODUCT__ as p on p.id_product = oi.id_product','LEFT')
            ->where(array('o.id_order_status'=>6,'oi.id_product'=>$id_product))
            ->order('stockout DESC')
            ->find();
        return $lists['stockout'];
    }
    /**
     * 二维数组根据某个字段进行排序
     * @param type $arr 二维数组
     * @param type $keys 要排序的字段
     * @param type $type 排序方式，默认升序
     */
    public function array_sort($arr, $keys, $type = 'desc') {
        $keysvalue = $new_array = array();
        foreach ($arr as $k => $v) {
            $keysvalue[$k] = $v[$keys];
        }
        if ($type == 'asc') {
            asort($keysvalue);
        } else {
            arsort($keysvalue);
        }
        reset($keysvalue);
        foreach ($keysvalue as $k => $v) {
            $new_array[$k] = $arr[$k];
        }
        return $new_array;
    }


    /**
     * 导出采购预警
     */

    public function export_warning() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '采购SKU', '业务员', '产品名', '内部名', '采购链接','属性', '采购单价', '仓库', '库存','可用库存', '在途量','在单量',
            '近三日销量', '日均量', '缺货量','备注'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        if (isset($_GET['department_id']) && $_GET['department_id'] && $_GET['department_id'] !=1000 ) {
            //1000为所有部门
            $where['p.id_department'] = $_GET['department_id'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where_pro['id_warehouse'] = $_GET['id_warehouse'];
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $where['sku'] = $_GET['sku'];
        }
        if (isset($_GET['innername']) && $_GET['innername']) {
            $where[] = "p.inner_name like '%" . $_GET['innername'] . "%'";
        }

        if (isset($_GET['title']) && $_GET['title']) {
            $where[] = "p.title like '%" . $_GET['title'] . "%'";
        }

        if (isset($_GET['order']) && $_GET['order'] == 'innername') {
            $stockout = M('Product')->alias('p')->field('p.inner_name,p.title,p.id_product,ps.sku,ps.title as sku_title,ps.id_product_sku,p.purchase_url,p.pro_msg')
                ->join('__PRODUCT_SKU__ as ps on ps.id_product = p.id_product','LEFT')->order('inner_name ASC')->where($where)->limit(5000)->select();
//太慢
            foreach($stockout as $key=>$product){
                $where_stockout['id_order_status'] = 6;
                $where_stockout['id_product_sku'] = $product['id_product_sku'];
                $stockout2 = M('Order')->alias('o')->field('sum(oi.quantity) stockout')
                    ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order','LEFT')
                    ->join('__PRODUCT__ as p on p.id_product = oi.id_product','LEFT')
                    ->where($where_stockout)
                    ->find();
                $stockout[$key]['stockout'] = $stockout2['stockout'];
            }

        }
        else{
            $where['id_order_status'] = 6;
            $stockout = M('Order')->field('sum(oi.quantity) as stockout,oi.id_product_sku,oi.id_product,oi.sku_title,oi.sku,p.id_department,p.inner_name,p.title as title,p.purchase_url,u.user_nicename,p.pro_msg')->alias('o')
                ->join('__ORDER_ITEM__ as oi on oi.id_order = o.id_order','LEFT')
                ->join('__PRODUCT__ as p on p.id_product = oi.id_product','LEFT')
                ->join('__USERS__ as u on u.id = o.id_userS','LEFT')
                ->where($where)
                ->group('id_product_sku')
                ->order('stockout DESC')
                ->limit(5000)
                ->select();
        }

        $department = M('Department')->where('type=1')->select();
        //为了提升导出速度,暂时做精简 jiangqinqing 20171103
        //$warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
       // $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        //$warehouse = M('Warehouse')->where(array('statsu' => 1))->getField('id_warehouse,title');
        $date_start = date('Y-m-d',strtotime('-3 day'));
        $data_end = date('Y-m-d');
        $where_date = "created_at >= '".$date_start."' and created_at < '".$data_end."'";

        //多次循环查询改成一次查询,循环组合数据 jiangqinqing 20171102
        $id_product_sku_arr =   array_column($stockout,'id_product_sku');
        $warehouse_product_all = M('WarehouseProduct')->where(array('id_product_sku'=>array('IN',$id_product_sku_arr)))->select();//            统计近三日销量
        foreach($warehouse_product_all as $key=>$wpa){
            $warehouse_product_id[$wpa['id_product_sku']][] = $wpa;
        }

        foreach ($stockout as $o) {
           // $where_pro['id_product_sku'] = $o['id_product_sku'];
           //$warehouse_product = M('WarehouseProduct')->where($where_pro)->select();
            $warehouse_product = $warehouse_product_id[$o['id_product_sku']];

            //为了提升导出速度,暂时做精简 jiangqinqing 20171103
            /*$order_item =  M('OrderItem')->alias('oi')
                ->join('__ORDER__ as o on o.id_order = oi.id_order','LEFT')
                ->field('count(id_order_item) as count,DATE_FORMAT(created_at,"%Y-%m-%d") as new_created_at')
                ->where(array('id_product_sku'=>$o['id_product_sku'],$where_date,'id_order_status'=>array('IN','4,5,6,7,8,9,10,16')))
                ->group('new_created_at')
                ->select(); */
            $sum = '';
            $sales = '';
            if($order_item){
                foreach($order_item as $v){
                    $sales .= $v['new_created_at'].':'.$v['count'].'    ';
                    $sum+=$v['count'];
                }
            }else{
                $sales =  '无';
            }
            if($sum!=0 )
                $daily = round($sum/3,2);
            else
                $daily = '';

            //统计日均销量
            //为了提升导出速度,暂时做精简 jiangqinqing 20171103
           /* $where_price['id_product_sku'] = $o['id_product_sku'];
            $date = date('Y-m-d',strtotime('-90 day'));
            $where_price[] = "created_at >= '".$date."'";
            $purchase_product = M('PurchaseProduct')->alias('pp')->field('pp.price,pp.quantity')
                ->join('__PURCHASE__ as p on p.id_purchase = pp.id_purchase','LEFT')
                ->where($where_price)
                ->select(); */
            $sum = '';
            $count = '';
            foreach($purchase_product as $k=>$v){
                $count+=$v['quantity'];
                $sum+=(int)$v['price']*$v['quantity'];
            }
            $price = round($sum/$count,2);

            if($warehouse_product){
                foreach ($warehouse_product as $product) {
                    $data[] = array(
                        $o['sku'],$o['user_nicename'], $o['title'], $o['inner_name'],$o['purchase_url'], $o['sku_title'], $price, $warehouse[$product['id_warehouse']], $product['quantity'],$product['quantity']-$product['qty_preout'], $product['road_num'],$product['qty_preout'], $sales, $daily, $o['stockout'],$o['pro_msg']
                    );
                }
            }else{
                $data[] = array(
                    $o['sku'],$o['user_nicename'], $o['title'], $o['inner_name'], $o['purchase_url'] ,$o['sku_title'],  $price, '', '', '', '', '', $sales, $daily, $o['stockout'],$o['pro_msg']
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

    /*
     *根据sku生成采购单
     */
    public function addrow(){
        $sku['sku'] = I('get.sku');
        $warehouse_id = I('get.warehouse');
        $c_item = D("Common/ProductSku")->where($sku)->find();
        if($c_item){
            $id = $c_item['id_product'];
            $id_product_sku = $c_item['id_product_sku'];
            $load_product = D("Common/Product")->find($id);
            $product_row = '<tr class="productBox' . $id . '"><td colspan="10" style="background-color: #f5f5f5;">' . $load_product['title'] . '<span class="deleteBox'.$id.'" delete="productBox' . $id . '" title="删除" style="margin-right:10px;font-size:20px;color:red;cursor: pointer;">x</span></td></tr>
        <tr class="headings productBox' . $id . '"><th>SKU</th><th>属性</th><th>采购单价</th><th>数量</th><th>可用库存</th><th>实际库存</th><th>在途数量</th><th>缺货量</th><th>近三日销量</th><th>日均销量</th></tr>';
            $tarray = array();
            $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
            $etime = date('Y-m-d 00:00:00');
            $tarray[] = array('EGT', $stime);
            $tarray[] = array('LT', $etime);
            $twhere[] = array('created_at' => $tarray);

            $status = array(                //需计算的订单状态
                OrderStatus::UNPICKING,     //未配货
                OrderStatus::PICKED,        //已配货
                OrderStatus::APPROVED,      //已审核
            );

            $where['id_product'] = $id;
            $where['id_product_sku'] = $c_item['id_product_sku'];
            $wp_where['id_warehouse'] = !empty($warehouse_id) ? $warehouse_id : 1;
            $warehouse_pro = M('WarehouseProduct')->field('quantity,road_num')->where($where)->where($wp_where)->find();

            //计算实际库存
            $warehouse_pro['quantity'] = empty($warehouse_pro['quantity']) ? 0 : $warehouse_pro['quantity'];
            $actual_quantity = M('Order')->alias('o')
                ->field("SUM(oi.quantity) AS actual_quantity")
                ->join("__ORDER_ITEM__ as oi ON o.id_order=oi.id_order", 'left')
                ->where(array('oi.id_product_sku'=>$c_item['id_product_sku']))
                ->where(array('o.id_order_status'=> array('IN', $status)))
                ->find();
            $actual_quantity = empty($actual_quantity['actual_quantity']) ? 0 : $actual_quantity['actual_quantity'] + $warehouse_pro['quantity'];

            //sku缺货量
            $swhere['id_order_status'] = 6;
            $sku_result = M('Order')->alias('o')->field('COUNT(*) as count_qty')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($swhere)->find();
            //三日平均销量
            $twhere['id_order_status'] = array('IN', OrderStatus::get_effective_status());
            $od_sale = M('Order')->alias('o')->field('COUNT(*) as count')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($where)->where($twhere)->find();
            //近三日销量
            $td_sale = $this->get_three_sale($id, $c_item['id_product_sku']);
            $product_row_temp= '<tr class="productBox' . $id . '" data-sku-id="'.$c_item['sku'].'"><input type="hidden" value="' . $c_item['title'] . '" name="attr_name[' . $id . '][' . $c_item['id_product_sku'] . ']"/>' .
                '<td>' . $c_item['sku'] . '</td> ' .
                '<td>' . $c_item['title'] . '</td>' .
                '<td><input type="text" class="sprice dsprice sprice' . $c_item['id_product_sku'] . '" value="' . $set_price . '" name="set_price[' . $id . '][' . $c_item['id_product_sku'] . ']" onchange="price_change(' . $c_item['id_product_sku'] . ')"/></td>' .
                '<td><input type="text" class="sqt dsqt sqt' . $c_item['id_product_sku'] . '" value="' . $set_qty . '" name="set_qty[' . $id . '][' . $c_item['id_product_sku'] . ']" onchange="qty_change(' . $c_item['id_product_sku'] . ')"/></td>' .
                '<input type="hidden" class="hid_p hid_p' . $c_item['id_product_sku'] . '" value="">'.
//                        '<td><span class="cprice cprice' . $c_key . '">' . $count_price . '</span></td>' .
                '<td>' . $warehouse_pro['quantity'] . '</td>' .
                '<td>' . $actual_quantity . '</td>' .
                '<td>' . $warehouse_pro['road_num'] . '</td>' .
                '<td>' . $sku_result['count_qty'] . '</td>' .
                '<td>' . $td_sale . '</td>' .
                '<td>' . round($od_sale['count'] / 3, 2) . '</td>' .
                '</tr>';
            $data['flag'] = 0;
            $data['pro_id'] = $id;
            $data['msg1'] = $product_row_temp;
            $data['msg2'] = $product_row.$product_row_temp;
        }else{
            $data['flag'] = 1;
            $data['msg'] = '未能匹配到产品，请检查sku是否正确';
        }
        echo json_encode($data);
    }

    /*
     *生成采购单号
     */
    public function get_purchase_order(){
        $department_id  = $_SESSION['department_id'];
        //部门筛选过滤,如不需过滤，直接删掉
        $where['id_department'] = array('IN',$department_id);
        $where['type'] = 1;
        //部门筛选
        $department  = D('Department/Department')->where($where)->order('sort asc')->select();
        $admin_id  = $_SESSION['ADMIN_ID'];
        $id_users = I('get.depart_id'); //部门id
        ### 显示所有的内部采购单号
        $where2['o.id_department'] = array('IN',$department_id);
        $where2['type'] = 1;
        $M = new \Think\Model;
        $count = M("PurchaseInOrder")->where($where)->count();
        $page = $this->page($count,20);

        $lists = $M
                ->table('(erp_purchase_in_order as o LEFT JOIN erp_users AS u ON o.id_users=u.id) LEFT JOIN erp_department AS d ON o.id_department=d.id_department')
                ->where($where2)
                ->field('o.*,u.user_nicename,d.title')
                ->order('o.id DESC')
                ->limit($page->firstRow, $page->listRows)
                ->select();
        ###
        if($id_users){
            $wherep['id_department'] = $id_users;

            $department_code = D('Department/Department')->field('department_code')->where($wherep)->find();
            $data['id_department'] = $id_users; //部门id
            $time = time();     //生成时间戳
            $data['create_time'] = date('Y-m-d H:i:s',$time);
            $data['id_users'] = $admin_id;     //操作者id
            $data['department_code'] = $department_code['department_code'];     //部门代号
            $data['oid_purchase'] = $department_code['department_code'].date("YmdHis",$time);     //部门代号
            $data['status'] = 1;     //状态 1为初始状态 2表示已用
            $purchase_in_order = M('PurchaseInOrder')->add($data);
            if($purchase_in_order){ //生产成功
                $this->assign("oid_purchase", $data['oid_purchase']);
            }
        }
        $this->assign("lists", $lists);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("department", $department);
        $this->display();
    }

    /*
     *验证采购单号  键盘弹起事件验证所有内部采购单号
     */
    public function verificate_purchase_order(){
    //键盘弹起事件验证所有内部采购单号
        $keyword = trim($_POST['value']);
        $department_id  = $_SESSION['department_id'];  //部门权限ID
        $admin_id  = $_SESSION['ADMIN_ID'];  //用户ID
        $id_department = $_POST['id_department'];
        if (!empty($keyword)) {
            $where['oid_purchase'] = array('like', '%' . $keyword . '%');
            $where['id_users'] = $admin_id;
           // if ($id_department) $where['p.id_department'] = array('EQ', $id_department);
            $where['status'] = 1; // 状态1表示可用
            $inner_purchase = M('PurchaseInOrder')->field('oid_purchase,id')->where($where)->select();
            if ($inner_purchase) {
                $data = '<ul>';
                foreach ($inner_purchase as $value) {
                    $data .= '<li><a class="pinner' . $value['id'] . '" href="javascript:;" onclick="get_inner_purchase(' . $value['id'] . ')" >' . $value['oid_purchase'] . '</a></li>';
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
     *查看在途量  zx 11/28
     */
    public function search_road_num(){        
        $id_purchasein = I('post.id_purchasein',0,'intval');
        $arr = array(
          'status'=>1,
          'msg'=>'初始状态'
        );
        if(empty($id_purchasein)){
            $arr['msg'] = '<p style="color:red;line-height:120px;text-align:center;">该数据有误</p>';
            echo json_encode($arr);
            exit;
        }
        $result = M("PurchaseInRoadnumRecord pir")
                ->field('pir.*,pi.inner_purchase_no as purchase_no')
                ->join("erp_purchase_in pi ON pi.id_purchasein=pir.id_purchasein",'left')
                ->where('pir.id_purchasein='.$id_purchasein)
                ->find();

        if($result){
            $purchase_no = $result['purchase_no']; 
            //入库前在途量 json格式
            $old_roadnum_arr = json_decode($result['old_roadnum'],TRUE);   
            //入库后在途量 json格式
            $new_roadnum_arr = json_decode($result['new_roadnum'],TRUE);    
            $create_time = date("Y-m-d H:i:s",$result['create_time']);
            $received_num_arr = json_decode($result['received_num'],TRUE);  //入库量 json格式
            $old_roadnum = '';
            $new_roadnum = '';
            $received_num = '';
            //避免在循环里查询，现分别拼接入库前在途量、入库后在途量、入库量
            foreach($old_roadnum_arr as $ok=>$ov){
                $old_roadnum_sku_arr[] = $ok;
            }
            if(!empty($old_roadnum_sku_arr)){
                $where_old['ps.id_product_sku'] = array("IN",  implode(',', $old_roadnum_sku_arr));
                $old_roadnum_array = M("ProductSku")->alias('ps')
                        ->field('ps.id_product_sku,ps.sku,ps.title,p.inner_name')
                        ->join("__PRODUCT__ p ON p.id_product=ps.id_product",'LEFT')
                        ->where($where_old)
                        ->select(); 
                if($old_roadnum_array){
                    foreach($old_roadnum_arr as $ok=>$ov){
                        foreach($old_roadnum_array as $orak=>$orav){
                            if($ok == $orav['id_product_sku']){
                                //入库前在途量
                                $new_all[$ok]['id_product_sku'] = $orav['id_product_sku'];
                                $new_all[$ok]['sku'] = $orav['sku'];
                                $new_all[$ok]['title'] = $orav['title'];
                                $new_all[$ok]['inner_name'] = $orav['inner_name'];
                                $new_all[$ok]['old_roadnum'] = $ov;
                                break;
                            }
                        }
                    }
                }
            }
            foreach($new_roadnum_arr as $nk=>$nv){
                $new_roadnum_sku_arr[] = $nk;
            }
            if(!empty($new_roadnum_sku_arr)){
                $where_nld['ps.id_product_sku'] = array("IN",  implode(',', $new_roadnum_sku_arr));
                $new_roadnum_array = M("ProductSku")->alias('ps')
                        ->field('ps.id_product_sku,ps.id_product,ps.sku,ps.title,p.inner_name')
                        ->join("__PRODUCT__ p ON ps.id_product=p.id_product",'LEFT')
                        ->where($where_nld)
                        ->select(); 
                if($new_roadnum_array){
                    foreach($new_roadnum_arr as $nk=>$nv){
                         foreach($new_roadnum_array as $nrak=>$nrav){
                             if($nk == $nrav['id_product_sku']){
                                 //入库后在途量
                                 if(empty($new_all[$nk]['sku'])){
                                     $new_all[$nk]['sku'] = $nrav['sku'];
                                     break;
                                 }
                                 if(empty($new_all[$nk]['id_product_sku'])){
                                     $new_all[$nk]['id_product_sku'] = $nrav['id_product_sku'];
                                     break;
                                 }
                                 if(empty($new_all[$nk]['inner_name'])){
                                     $new_all[$nk]['inner_name'] = $nrav['inner_name'];
                                     break;
                                 }
                                 if(empty($new_all[$nk]['title'])){
                                     $new_all[$nk]['title'] = $nrav['title'];
                                     break;
                                 }
                                 
                                 $new_all[$nk]['new_roadnum'] = $nv;
                                 break;
                             }
                         }
                     } 
                 }
            }
            
            
            
            
            foreach($received_num_arr as $rk=>$rv){
                $received_num_sku_arr[] = $rk;
            }
            if(!empty($received_num_sku_arr)){
                $where_rld['ps.id_product_sku'] = array("IN",  implode(',', $received_num_sku_arr));
                $received_num_array = M("ProductSku")->alias('ps')
                        ->field('ps.id_product_sku,ps.id_product,ps.sku,ps.title,p.inner_name')
                        ->join("__PRODUCT__ p ON ps.id_product=p.id_product",'LEFT')
                        ->where($where_rld)
                        ->select();
                if($received_num_array){
                    foreach($received_num_arr as $rk=>$rv){
                        foreach($received_num_array  as $rrak=>$rrav){
                            if($rk == $rrav['id_product_sku']){
                                //入库量
                                if(empty($new_all[$rk]['id_product_sku'])){
                                    $new_all[$rk]['id_product_sku'] = $rrav['id_product_sku'];
                                    break;
                                }
                                if(empty($new_all[$rk]['sku'])){
                                    $new_all[$rk]['sku'] = $rrav['sku'];
                                    break;
                                }
                                if(empty($new_all[$rk]['title'])){
                                     $new_all[$rk]['title'] = $rrav['title'];
                                     break;
                                 }
                                 if(empty($new_all[$rk]['inner_name'])){
                                     $new_all[$rk]['inner_name'] = $rrav['inner_name'];
                                     break;
                                 }
                                
                                $new_all[$rk]['received_num'] = $rv;
                                break;
                            }
                        }
                    }
                }
            }
            if(!empty($new_all)){
                //拼接总采购量
                $sku_arr = array_column( $new_all,'id_product_sku');
                $where_total_purchase['id_product_sku'] = array("IN",implode(',', $sku_arr));
                $where_total_purchase['po.status'] = array("EQ",5); //已付款
                $where_total_purchase['p.billtype'] = array("NEQ",2); //非退货
                $where_total_purchase['pi.id_purchasein'] = array("EQ",$id_purchasein);
                $model = new \Think\Model();
                $total_purchase = $model->table('erp_purchase_in p')
                            ->join("__PURCHASE_INITEM__ as pi on pi.id_purchasein=p.id_purchasein",'left')
                            ->join("__PURCHASE__ as po on p.id_erp_purchase=po.id_purchase",'left')
                            ->field('pi.id_product_sku,sum(pi.quantity) as total')
                            ->group('pi.id_product_sku')
                            ->where($where_total_purchase)->select();
                foreach($new_all as $k=>$v){
                    foreach($total_purchase as $tkey=>$tval){
                        if($v['id_product_sku'] == $tval['id_product_sku']){
                            $new_all[$k]['total_purchase'] = $tval['total'];
                            break;
                        }
                    }
                }  
            }
            
            
            /* 拼接表格table start */
            $msg .= '内部采购单号为：'.$purchase_no.'<br/>入库时间为：'.$create_time;
            $msg .= '<table border="1" cellpadding="0" cellspacing="0" style="text-align:center;min-width:750px;"><tr><th style="padding:3px;">内部名</th><th width="64px;">sku</th><th style="padding:0 3px 0 3px;">属性名</th><th style="padding:0 3px 0 3px;">采购量</th><th style="padding:0 3px 0 3px;">入库前在途量</th><th style="padding:0 3px 0 3px;">入库量</th><th style="padding:0 3px 0 3px;">入库后在途量</th></tr>';
            foreach($new_all as $key=>$val){
                $msg .= '<tr><td style="padding:5px 10px 5px 10px;">'.$val['inner_name'].'</td><td>'.$val['sku'].'</td><td style="padding:0 10px 0 10px;">'.$val['title'].'</td><td>'.$val['total_purchase'].'</td><td>'.$val['old_roadnum'].'</td><td>'.$val['received_num'].'</td><td>'.$val['new_roadnum'].'</td></tr>' ;
                
            }
            $msg .= '</table> ';
            /* 拼接表格table over */
            
            $arr['msg'] = $msg;

            echo json_encode($arr);
            exit;
        }else{
            $arr['status'] = 2;
            $arr['msg'] = '<p style="color:red;line-height:120px;text-align:center;">暂无该数据的在途量</p>';
            echo json_encode($arr);
            exit;
        }
    
    }

}
