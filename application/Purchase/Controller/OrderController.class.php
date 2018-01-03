<?php
/**
 * 采购管理--订单管理
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */
namespace Purchase\Controller;

use Common\Controller\AdminbaseController;
use Order\Model\UpdateStatusModel;
use Order\Lib\OrderStatus;
header("Content-Type:text/html;charset=utf-8;");
class OrderController extends AdminbaseController {

    protected $order,$page;

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /**
     * 订单列表
     * 修复 用货号搜索订单出现重复订单的情况  liuruibin   20171021
     */
    public function index() {
        $t1 = microtime(true);
        /** @var \Order\Model\OrderModel $order_model */
        $oi_table = D("Common/OrderItem")->group('id_order')->select();//排列唯一的订单ID，避免订单关联重复现象
        $oi_table_sql = D("Common/OrderItem")->_sql();//获取查询语句，每次关联时用到

        $pro_table = D("Common/Product")->getTableName();
        $order_model = $this->order;
        $where = $order_model->form_where($_GET,'o.');
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['o.id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['o.id_department']= $_GET['id_department'];
        }
        if ($_GET['start_time'] or $_GET['end_time'])
        {
            if ($_GET['start_time'])
            {
                $createAtArray[] = array('EGT', $_GET['start_time']);
            }
            if ($_GET['end_time'])
            {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
        }
        else
        {
            $_GET['start_time'] = date('Y-m-d', strtotime('-7 days'));
            $_GET['end_time'] = date('Y-m-d', strtotime('+1 day'));
            $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-7 days')));
            $createAtArray[] = array('LT', date('Y-m-d', strtotime('+1 day')));
        }
        $where[] = array('o.created_at' => $createAtArray);
//        $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();
        $t2 = microtime(true);
        //$formData['product_type'] = $baseSql->getFieldGroupData('product_type');
        $form_data['track_status'] = D('Order/OrderShipping')->field('status_label as track_status')
            ->where("status_label is not null or status_label !='' ")
            ->group('status_label')->cache(true, 12000)->select();


        //今日统计订单 条件
        $today_where = $where;
        $today_where['o.created_at'] = array('EGT', $today_date);
        $all_domain_total = $order_model->alias('o')->field('count(`id_domain`) as total,id_domain')->where($today_where)
            ->order('total desc')->group('id_domain')->select();
        if($_GET['productname']){
            $all_domain_total = $order_model->alias('o')->join("({$oi_table_sql}) as oit on oit.id_order=o.id_order ", 'LEFT')->field('count(`id_domain`) as total,id_domain')->join("{$pro_table} as pro on pro.id_product=oit.id_product",'left')->where($today_where)->where("pro.inner_name like '%{$_GET['productname']}%'")
            ->order('total desc')->group('id_domain')->select();
        }

        //修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
        if (isset($_GET['status_label']) && $_GET['status_label']) {
            $where['s.status_label'] = strip_tags(trim($_GET['status_label']));
            $count = $order_model->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->where($where)->count();
            $today_total = $order_model->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->where($today_where)->count();
            if($_GET['productname']){
                $count = $order_model->alias('o')->join("({$oi_table_sql}) as oit on oit.id_order=o.id_order ", 'LEFT')->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')->join("{$pro_table} as pro on pro.id_product=oit.id_product",'left')->where("pro.inner_name like '%{$_GET['productname']}%'")->where($where)->count();
                $today_total = $order_model->alias('o')->join("({$oi_table_sql}) as oit on oit.id_order=o.id_order ", 'LEFT')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')->join("{$pro_table} as pro on pro.id_product=oit.id_product",'left')->where("pro.inner_name like '%{$_GET['productname']}%'")
                ->where($today_where)->count();
            }
            $page = $this->page($count, $this->page);
            $order_list = $order_model->alias('o')->field('o.*,s.date_signed,oi.ip as ip')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->join('__ORDER_INFO__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
            if($_GET['productname']){
                $order_list = $order_model->alias('o')->field('o.*,s.date_signed,oi.ip as ip')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->join("({$oi_table_sql}) as oit on oit.id_order=o.id_order ", 'LEFT')
                ->join('__ORDER_INFO__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->join("{$pro_table} as pro on pro.id_product=oit.id_product",'left')
                ->where($where)->order("id_order DESC")->where("pro.inner_name  like '%{$_GET['productname']}%'")->limit($page->firstRow . ',' . $page->listRows)->select();                
            }            

        } else {
            $count = $order_model->alias('o')->where($where)->count();
            $today_total = $order_model->alias('o')->where($today_where)->count();
            if($_GET['productname']){
                $count = $order_model->alias('o')->join("({$oi_table_sql})  as oit on oit.id_order=o.id_order ", 'LEFT')->join("{$pro_table} as pro on pro.id_product=oit.id_product",'left')->where("pro.inner_name like '%{$_GET['productname']}%'")->where($where)->count();
                $today_total = $order_model->alias('o')->join("({$oi_table_sql}) as oit on oit.id_order=o.id_order ", 'LEFT')->join("{$pro_table} as pro on pro.id_product=oit.id_product",'left')->where("pro.inner_name like '%{$_GET['productname']}%'")->where($today_where)->count();
            }
            $page = $this->page($count, $this->page);
            $order_list = $order_model->alias('o')->field('o.*,oi.ip as ip')->join('__ORDER_INFO__ oi ON (o.id_order = oi.id_order)', 'LEFT')->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
            if($_GET['productname']){
                $order_list = $order_model->alias('o')->join("({$oi_table_sql}) as oit on oit.id_order=o.id_order ", 'LEFT')->join("{$pro_table} as pro on pro.id_product=oit.id_product",'left')->field('o.*,oi.ip as ip')->join('__ORDER_INFO__ oi ON (o.id_order = oi.id_order)', 'LEFT')->where("pro.inner_name like '%{$_GET['productname']}%'")->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
            }

        }
        $sql=$order_model->getLastSql();
        $t3 = microtime(true);
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $order_list[$key]['tel_email_short'] = substr($o['tel'],-6,6). "/" . substr($o['email'],0,5);
            $order_list[$key]['tel_email_all'] = $o['tel']. "\t\n" .$o['email'];
        }

        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true,36000)->select();
        $advertiser = array_column($advertiser,'name','id');
        $department_id  = $_SESSION['department_id'];
        $where2['type'] = 1; 
        //部门筛选过滤,如不需过滤，直接删掉
        if (I('get.id_department')){
            //$where2['id_department'] = I('get.id_department'); 
        }else{
            //$where2['id_department'] = array('IN',$department_id);
        }
        $where2['id_department'] = array('IN',$department_id);
        //部门筛选
        $department  = D('Department/Department')->where($where2)->order('sort asc')->cache(true,3600)->select();
        //$department  = $department?array_column($department,'title','id_department'):array();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $t4 = microtime(true);
        add_system_record($_SESSION['ADMIN_ID'], 4, 4,'查看DF订单列表');
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $_SESSION['get'] = $_GET;
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->assign('t1', $t1);
        $this->assign('t2', $t2);
        $this->assign('t3', $t3);
        $this->assign('t4', $t4);
        $this->assign('sql', $sql);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $advertiser);
        $this->assign("form_data", $form_data);
        $this->assign("page", $page->show('Admin'));
        $this->assign("today_total", $today_total);
        $this->assign("order_total", $count);
        $this->assign("all_domain_total", $all_domain_total);
        $this->assign("warehouse", $warehouse);

        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("order_list", $order_list);
        $this->display();
    }
    /**
     * 订单详情
     */
    public function info(){
        $order_id = I('get.id');
        $order = D("Order/Order")->find($order_id);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $orderHistory = D("Order/OrderRecord")
            ->field('*')
            ->join('__USERS__ u ON (__ORDER_RECORD__.id_users = u.id)', 'LEFT')
            ->where(array('id_order'=>$order_id))
            ->order('created_at desc, id_order_status = 4 desc, id_order_status = 25 desc, id_order_status asc')->select();
        $shipping = D('Common/Shipping')
            ->where(array('id_shipping'=>(int)$order['id_shipping']))->cache(true,3600)
            ->find();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $all_domain = $domain_model->get_all_domain();
        $order['id_domain'] = $all_domain[$order['id_domain']];
        $order['id_order_status'] = $statusLabel[$order['id_order_status']];
        $products = D('Order/OrderItem')->get_item_list($order['id_order']);
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看DF订单详情');
        $this->assign("order", $order);
        $this->assign("products", $products);
        $this->assign("history", $orderHistory);
        $this->assign("label", $statusLabel);
        $this->assign('shipping_name', $shipping['title']);
        $this->assign('shopping_url', $shipping['track_url']);
        $this->display();
    }
    /**
     * 编辑订单 from表单
     */
    public function edit_order() {
        $getId = I('get.id/i');
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where = array('id_order' => array('EQ', $getId));
        $where['id_department'] = array('IN', $department_id);
        $order = $this->order->where($where)->find();
        if(!$order){
            $this->error("你没有权限操作此订单！");
        }
        /** @var \Common\Model\ProductModel $product */
        $product = D('Common/Product');
        $order_item = D('Order/OrderItem');
        $products = $order_item->where(array(
                    'id_order' => $order['id_order']
                ))->order('id_product desc')->select();
        /** @var  $options \Product\Model\ProductOptionModel */
        $options = D('Product/ProductOption');
        $all_attr = array();
        $arr_html = array();
        
        if ($products) {
            foreach ($products as $key => $product) {                
                $product_id = $product['id_product'];
                if(!empty($product_id)) {
                    $sku_id = $product['id_product_sku'];
                    $sku_id_tmp=$sku_id+rand(1,1000);                    
                    $arr_html[$product_id]['id_product'] = $product_id;
                    $arr_html[$product_id]['product_title'] = $product['product_title'];
                    $arr_html[$product_id]['id_order_item'] = $product['id_order_item'];
                    $arr_html[$product_id]['id_order_items'][$sku_id_tmp] = $product['id_order_item'];
                    $arr_html[$product_id]['quantity'][$sku_id_tmp] = $product['quantity'];
                    $arr_html[$product_id]['attrs'][$sku_id_tmp] = unserialize($product['attrs']);
                    $arr_html[$product_id]['attr_option_value'][$sku_id_tmp] = $options->get_attr_list_by_id($product_id);
                    $arr_html[$product_id]['attr_option_value_data'] = $options->get_attr_list_by_id($product_id);
                    $arr_html[$product_id]['sku_id'] = $product['id_product_sku'];
                    $arr_html[$product_id]['sku_id_tmp'] = $sku_id_tmp;                    
                    $products[$key]['attr_option_value'] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                    $all_attr[$product_id][$sku_id] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                }
            }
        }
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("products", $arr_html);
        $this->assign("all_attr", $all_attr);
        /** @var \Warehouse\Model\WarehouseModel $warehouse_model */
        $warehouse_model = D('Warehouse/Warehouse');
        $all_warehouse   = $warehouse_model->all_warehouse();
        $this->assign("all_warehouse", $all_warehouse);
        $this->assign("order", $order);
        $this->assign('get', $_SESSION['get']);
        $this->display();
    }
    
    //编辑订单添加产品属性
    public function get_attr_html() {
        if(IS_AJAX) {
            $pro_id = I('post.id');
            $order_id = I('post.order_id');
            
            $order_item = D('Order/OrderItem');
            $products = $order_item->where(array(
                        'id_order' => $order_id
                    ))->order('id_product desc')->select();
            $options = D('Product/ProductOption');
            $all_attr = array();
            $select_attr = array();
            $select_num = array();
            
            if ($products) {
                foreach ($products as $key => $product) {                
                    $product_id = $product['id_product'];
                    if(!empty($product_id)) {
                        $sku_id = $product['id_product_sku'];
                        $select_attr[$product_id]['attrs'] = unserialize($product['attrs']);
                        $select_attr[$product_id]['quantity'] = $product['quantity'];
                        $all_attr[$product_id] = $all_attr[$product_id] ? $all_attr[$product_id] : $options->get_attr_list_by_id($product_id);
                    }
                }
            }
            
//            dump($select_attr);
            
            $html = '<tr class="productAttrRow'.$pro_id.'">';
            $html .= '<td>';
            foreach ($all_attr[$pro_id] as $k=>$val) {
                $html .= $val['title'].'&nbsp';
                $html .= '<select name="option_id['.$pro_id.']['.$val["id_product_option"].'][]">';
                foreach($val["option_values"] as $kk => $vv) {
                    $selected = in_array($vv['id_product_option_value'],$select_attr[$pro_id]['attrs']) ? 'selected' : '';  
                    $html .= '<option value ="'.$vv['id_product_option_value'].'" '.$selected.'>'.$vv['title'].'</option>';
                }
                $html .= '</select>&nbsp;&nbsp;&nbsp;';                    
            }
            $html .= '<input name="number['.$pro_id.'][]" value="'.$select_attr[$pro_id]['quantity'][0].'" type="text">&nbsp;&nbsp;';
            $html .= '<a href="javascript:void(1);" class="deleteOrderAttr" pro_id="'.$pro_id.'">删除</a>';
            $html .= '</td>';
            $html .= '</tr>';
            echo $html;die();
        }
    }

    /**
     * 处理编辑订单
     */
    public function edit_order_post() {
        $orderId = I('get.id');
        $order = D("Order/Order")->find($orderId);
        $listchoose=$_SESSION['get'];
        //清理订单数据缓存
        F('order_item_by_order_id_cache'.$orderId,NULL);
        if ($order)
        {
            F('order_item_by_order_id_cache'.$orderId,null);
            if (isset($_POST['action']) && $_POST['action'] == 'delete_attr')
            {
                if ($order['id_order_status'] != OrderStatus::OUT_STOCK) //订单状态非缺货状态，编辑操作失效
                {
                    $this->error("该订单状态非缺货状态，属性修改失败！", U('Purchase/order/edit_order', array('id' => $orderId)),3);
                }
                else
                {
                    $itemId = I('post.order_attr_id');
                    if ($orderId && $itemId)
                    {
                        $deleteData = D("Order/OrderItem")->find($itemId);
                        $comment = '删除产品属性：' . json_encode($deleteData);
                        D("Order/OrderItem")->where('id_order_item=' . $itemId)->delete();
                        D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],3, $comment);
                    }
                    exit();
                }
            }
            if (IS_POST)
            {
                $data = I('post.');
                if (isset($data['id_order_status']))
                {
                    unset($data['id_order_status']);
                }
                $data['updated_at'] = date('Y-m-d H:i:s');
                $res =  D('Order/Order')->save($data);
                if ($res)
                {
                    if ($order['id_order_status'] == OrderStatus::OUT_STOCK)
                    {
                        $product_id = array();
                        foreach ($data['option_id'] as $key => $val)
                        {
                            $product_id[] = $key;
                            $temp = array();

                            foreach($val as $k => $v)
                            {
                                foreach($v as $kk => $vv)
                                {
                                    $temp[$kk][] = $vv;
                                }
                            }
                            $product = D('Product/Product')->field('title,inner_name,sale_price')->where('id_product='.$key)->find();
                            foreach($temp as $psk =>$psv)
                            {

                                $option_value = asort($psv);
                                $option_value = implode(',', $psv);
                                $product_sku = D('Product/ProductSku')->where("status=1 and option_value='$option_value' and id_product=$key")->order('id_product_sku desc')->find();
                                $item_result = D('Order/OrderItem')->where('id_product='.$key.' and id_product_sku='.$product_sku['id_product_sku'].' and id_order='.$data['id_order'])->find();
                                $item_data['id_order'] = $data['id_order'];
                                $item_data['id_product'] = $key;
                                $item_data['id_product_sku'] = $product_sku['id_product_sku'];
                                $item_data['sku'] = $product_sku['sku'];
                                $item_data['sku_title'] = $product_sku['title'];
                                $item_result2 = D('Order/OrderItem')->where('id_product='.$key.' and id_order='.$data['id_order'])->find();
                                $item_data['sale_title'] = $item_result2['sale_title'];
                                $item_data['product_title'] = $product['title'];
                                $item_data['quantity'] = $data['number'][$key][$psk];
                                $item_data['price'] = $product['sale_price'];
                                $item_data['total'] = $product['sale_price']*$data['number'][$key][$psk];
                                $item_data['attrs'] = serialize($psv);
                                if (array_keys($data['order_item_id'][$key])[$psk] == $psk)
                                {
                                    D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$key][$psk])->data($item_data)->save();
                                }
                                else
                                {
                                    if($product_sku['id_product_sku'] == $item_result['id_product_sku'] || empty($data['number'][$key][$psk])) continue;
                                    D('Order/OrderItem')->data($item_data)->add();
                                }
                                if ($data['number'][$key][$psk] == 0)
                                {
                                    D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$key][$psk])->delete();
                                }
                            }
                        }
                        $lastval=0;
                        $the_key=0;                        
                        foreach ($data['pro_id'] as $pro_key => $pro_val)
                        {
                            if($pro_val==$lastval){
                                $the_key++;
                            }else{
                                $lastval=$pro_val;
                                $the_key=0;
                            }                                
                            if (!in_array($pro_val,$product_id))
                            {
                                $other_product = D('Product/Product')->field('title,inner_name,sale_price')->where('id_product='.$pro_val)->find();
                                $item_id = $data['order_item_id'][$pro_val][$the_key];
                                $other_item_data['total'] = $other_product['sale_price']*$data['number'][$pro_val][$the_key];
                                $other_item_data['quantity'] = $data['number'][$pro_val][$the_key];
                                D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$pro_val][$the_key])->data($other_item_data)->save();
                            }

                            if ($data['number'][$pro_val][$the_key] == 0)
                            {
                                D('Order/OrderItem')->where('id_order_item='.$data['order_item_id'][$pro_val][$the_key])->delete();
                            }
                        }
                        if (isset($_POST['id_order_status']) && $_POST['id_order_status'] >0 && $_POST['id_order_status'] != $order['id_order_status'])
                        {
                            $order['id_order_status'] = (int)$_POST['id_order_status'];
                        }
                        add_system_record(sp_get_current_admin_id(), 2, 4, '编辑DF订单属性');
                        //
                        if ($order['id_zone'] !=9 )
                        {
                            //先进行转寄仓匹配
                            $result_one = UpdateStatusModel::match_forward_order($orderId);
                            if ($result_one['flag'])
                            {
                                UpdateStatusModel::into_forward_order($orderId,$result_one['data']);
                                $this->success('订单属性保存成功;匹配转寄仓数据成功！', U('Purchase/order/index', $listchoose),3);
                                die;
                            }
                            //匹配该缺货订单
                            $res_two = UpdateStatusModel::check_stock($order['id_order']); //进行有效库存匹配
                            if ($res_two['flag'])
                            {
                                $update_data ['id_warehouse'] = $res_two['id_warehouse'];
                                $update_data ['id_order_status'] = OrderStatus::UNPICKING; //未配货
                                $res_three = D("Order/Order")->where(array('id_order' => $orderId))->save($update_data);
                                if ($res_three)
                                {
                                    //更新状态成功进行加在单处理
                                    UpdateStatusModel::add_warehouse_product_preout($order['id_order']);
                                    $message = '订单数据保存成功;属性修改成功;匹配有效库存成功，更新订单状态成功！';
                                    D("Order/OrderRecord")->addHistory($orderId, OrderStatus::UNPICKING,3, '采购管理-订单列表-缺货订单修改属性，匹配有效库存成功！');
                                    $this->success($message, U('Purchase/order/index', $listchoose),3);
                                    die;
                                }
                                else
                                {
                                    D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],2, '采购管理-订单列表-编辑属性，匹配库存成功，修改订单状态失败！');
                                    $message = '订单数据保存成功;属性修改成功;匹配有效库存成功，更新订单状态失败,请稍后重试！';
                                    $this->success($message, U('Purchase/order/index', $listchoose),3);
                                    die;
                                }
                            }
                            else
                            {
                                D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],2, '采购管理-订单列表-编辑属性，匹配有效库存失败！');
                                $message = '订单数据保存成功;属性修改成功;匹配有效库存失败！';
                                $this->success($message, U('Purchase/order/index', $listchoose),3);
                                die;
                            }
                        }
                        else
                        {
                            $res_two = UpdateStatusModel::check_stock($order['id_order']); //进行有效库存匹配
                            if ($res_two['flag'])
                            {
                                $update_data ['id_warehouse'] = $res_two['id_warehouse'];
                                $update_data ['id_order_status'] = OrderStatus::UNPICKING; //未配货
                                $res_three = D("Order/Order")->where(array('id_order' => $orderId))->save($update_data);
                                if ($res_three)
                                {
                                    //更新状态成功进行加在单处理
                                    UpdateStatusModel::add_warehouse_product_preout($order['id_order']);
                                    $message = '订单数据保存成功;属性修改成功;匹配有效库存成功，更新订单状态成功！';
                                    D("Order/OrderRecord")->addHistory($orderId, OrderStatus::UNPICKING,3, '采购管理-订单列表-缺货订单修改属性，匹配有效库存成功！');
                                    $this->success($message, U('Purchase/order/index', $listchoose),3);
                                    die;
                                }
                                else
                                {
                                    D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],2, '采购管理-订单列表-编辑属性，匹配库存成功，修改订单状态失败！');
                                    $message = '订单数据保存成功;属性修改成功;匹配有效库存成功，更新订单状态失败,请稍后重试！';
                                    $this->success($message, U('Purchase/order/index', $listchoose),3);
                                    die;
                                }
                            }
                            else
                            {
                                D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],2, '采购管理-订单列表-编辑属性，匹配有效库存失败！');
                                $message = '订单数据保存成功;属性修改成功;匹配有效库存失败！';
                                $this->success($message, U('Purchase/order/index', $listchoose),3);
                                die;
                            }
                        }
                    }
                    else
                    {
                        D("Order/OrderRecord")->addHistory($orderId, $order['id_order_status'],2, '采购管理-订单列表-编辑订单信息');
                        $message = '订单数据保存成功;非缺货状态，不能进行属性修改！';
                        $this->success($message, U('Purchase/order/index', $listchoose),3);
                        die;
                    }
                }
                else
                {
                    $this->error("保存失败！", U('Purchase/order/edit_order', array('id' => $orderId)),3);
                    die;
                }
            }
        }
        else
        {
            $this->error("该订单数据不存在！", U('Purchase/order/edit_order', array('id' => $orderId)), 3);
            die;
        }
    }

    public function ref_sign_statistics(){
        $sort = isset($_GET['sort']) && $_GET['sort']=='asc'?'asc':'desc';
        $order_by = 'o.id_domain '.$sort;
        if(isset($_GET['order_by'])){
            switch($_GET['order_by']){
                case 'total':
                    $order_by = 'total '.$sort;
                    break;
                case 'effective':
                    $order_by = 'effective '.$sort;
                    break;
                case 'refused_to_sign':
                    $order_by = 'refused_to_sign '.$sort;
                    break;
                case 'delivery':
                    $order_by = 'delivery '.$sort;
                    break;
                case 'smooth_delivery':
                    $order_by = 'smooth_delivery '.$sort;
                    break;
                default:
                    $order_by = 'o.id_domain desc';
            }
        }
        $where = array();
        $department_id = $_SESSION['department_id'];

        $where['o.id_department'] = array('IN', $department_id);

        if (isset($_GET['id_department']) && $_GET['id_department']) {//搜索部门编号
            $where['o.id_department'] = $_GET['id_department'];
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

        $where[] = array('o.created_at' => $createAtArray);
        if(isset($_GET['user_name']) && $_GET['user_name']){
            $user_name   = I('request.user_name/s','', array('trim', 'htmlspecialchars'));
            $get_id_user = D("Common/Users")->where(array('user_nicename'=>array('like','%'.$user_name.'%')))->getField('id',true);
            if($get_id_user){
                $where['o.id_users'] = array('IN',$get_id_user);
            }else{
                $where['o.id_users'] = array('EQ', false);
            }
        }
        if(isset($_GET['domain']) && $_GET['domain']){
            $get_domain = I('request.domain/s','', array('trim', 'htmlspecialchars'));
            $id_domain = D("Common/domain")->where(array('name'=>array('like','%'.$get_domain.'%')))->getField('id_domain',true);
            if(!empty($id_domain)){
                $where['o.id_domain']     = array('IN',$id_domain);
            }else{
                $where['o.id_domain'] = array('EQ', false);
            }
        }
        $field_str     = "count(o.id_order) as total,SUM(IF(o.`id_order_status` IN(2,3,4,5,6,7,8,9,10,16,17),1,0)) as effective,o.id_users,o.id_domain";
        $field_str    .= ",SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成'),1,0)) AS refused_to_sign";
        $field_str    .= ",SUM(IF(os.track_number!='',1,0)) as delivery";
        $field_str    .= ",SUM(IF(os.status_label='順利送達',1,0)) as smooth_delivery";
        //$count_order   = D("Order/Order")->field($field_str)->group('id_domain')->select();
        /* @var $ordModel \Order\Model\OrderModel */
        $ord_model = D("Order/Order");
        $M = M();
        $ord_name = $ord_model->getTableName();
        /** @var  $ordShipping \Common\Model\OrderShippingModel */
        $ord_shipping = D("Order/OrderShipping");
        $ord_Shi_name = $ord_shipping->getTableName();
        $count_sql = $M->table($ord_name . ' AS o ')
            ->field('count(o.id_domain)')
            ->where($where)->group('o.id_domain, o.id_users')->select(false);
        $count = $M->table('('.$count_sql.') AS T')->cache(true, 3600)->count();
        $page = $this->page($count, 20);
        $list = $M->table($ord_name . ' AS o LEFT JOIN ' . $ord_Shi_name . ' AS os ON o.id_order=os.id_order')
            ->field($field_str)->where($where)->group('o.id_domain, o.id_users')->order($order_by)
            ->limit($page->firstRow, $page->listRows)
            ->cache(true,600)->select();

        foreach($list as $key=>$item){
            $id_domain = $item['id_domain'];
            $product_data = $ord_model->alias('o')
                ->join('__ORDER_ITEM__ OI ON (o.id_order = OI.id_order)', 'LEFT')
                ->where(array('o.id_domain'=>$id_domain))->order('o.id_order desc')->cache(true,3600)->find();
            $list[$key]['domain_name'] = D("Common/domain")->where(array('id_domain'=>$id_domain))->getField('name');
            $list[$key]['user_name'] = D("Common/Users")->where(array('id'=>$item['id_users']))->getField('user_nicename');
            $list[$key]['advert_name'] = $product_data['product_title']?$product_data['product_title']:'<span style="color:red">没有找到对应名字</span>';
        }
        $where2['type'] = 1; 
        //部门筛选过滤,如不需过滤，直接删掉
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where2['id_department'] = array('IN',$department_id);
        //部门筛选
        $department  = D('Department/Department')->where($where2)->order('sort asc')->cache(true,3600)->select();
        //$department  = $department?array_column($department,'title','id_department'):array();

        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign('list',$list);
        $this->assign("page",$page->show('Admin'));
        $this->display();
    }
}
