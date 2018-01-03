<?php

namespace Order\Model;

use Common\Model\CommonModel;

class OrderModel extends CommonModel {
    public function _initialize() {
        parent::_initialize();

        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row']?$_SESSION['set_page_row']:20;
    }

    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('id_department', 'require', '部门不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        array('title', 'require', '标题不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        array('model', 'require', 'SKU不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    );

    protected function _before_write(&$data) {
        parent::_before_write($data);
    }

    /**
     * form表单条件
     * @param $get_data
     * @return array
     */
    public function form_where($get_data, $prefix = '') {
//        var_dump($get_data);die();
        $get_data = array_filter($get_data);
        $return = array();
        if (is_array($get_data) && count($get_data)) {
            $get_data['keyword'] = str_replace("'", '', $get_data['keyword']);
            if ($get_data['keyword']) {
                $get_data['keyword']=trim($get_data['keyword']);
                $type=$get_data['keywordtype'];
                if($get_data['ismore']){
                    $likeoreq='like';
                }else{
                    $likeoreq='eq';
                }
                if(!in_array($type, array('username','id_domain','track_number'))){                      
                    if($type=='address'){
                        $return[$prefix.$type]=array('like',"%{$get_data['keyword']}%");
                    }else{
                        $return[$prefix.$type]=$likeoreq=='eq'?array($likeoreq,$get_data['keyword']):array($likeoreq,"%{$get_data['keyword']}%");
                    }
                    
                }
                if($type=='username'){
                    $userIds=M('users')->where(array('user_nicename'=>array('LIKE', '%' . $get_data['keyword'] . '%')))->getField('id',true);
                    if($userIds){
                        $return[$prefix . 'id_users'] = array('in', $userIds);
                    }else{
                        $return[$prefix . 'id_users'] = 0;
                    }                       
                }
                if($type=='id_domain'){
                    $domain = M('Domain')->field('id_domain')->where(array('name'=>array('LIKE', '%' . $get_data['keyword'] . '%')))->select();
                    if($domain) {
                        $domain_id = array_column($domain, 'id_domain');
                        $return[$prefix . 'id_domain'] = array('IN', $domain_id);                    
                    }else{
                        $return[$prefix . 'id_domain'] = 0;
                    }              
                }   
                if($type=='track_number'){
                    $order_id = M('OrderShipping')->where(array('track_number'=>array('LIKE', '%' . $get_data['keyword'] . '%')))->getField('id_order');//查找运单号
                    if($order_id) {
                        $return[$prefix . 'id_order'] = array('IN', $order_id);     
                    }else{
                        $return[$prefix . 'id_order'] = 0;
                    }
                }       
//                var_dump($return);die();
//                if (strpos($get_data['keyword'], ',') !== false) {
//                    $keyword = trim($get_data['keyword'], ',');
//                    $return[$prefix . 'id_increment'] = array('IN', $keyword);
//                } else {
//                    if ((int) $get_data['keyword'] > 0)
//                    $key_where[$prefix . 'id_increment'] = array('EQ', $get_data['keyword']);
//                    $key_where[$prefix . 'id_domain'] = array('LIKE', '%' . $get_data['keyword'] . '%');
//                    $key_where[$prefix . 'http_referer'] = array('LIKE', '%' . $get_data['keyword'] . '%');  //来源查询
//                    $key_where[$prefix . 'first_name'] = array('LIKE', '%' . $get_data['keyword'] . '%');
//                    $key_where[$prefix . 'tel'] = array('LIKE', '%' . $get_data['keyword'] . '%');
//                    $key_where[$prefix . 'address'] = array('LIKE', '%' . $get_data['keyword'] . '%');
//                    $key_where[$prefix . 'email'] = array('LIKE', '%' . $get_data['keyword'] . '%');
//                    $key_where[$prefix . 'remark'] = array('LIKE', '%' . $get_data['keyword'] . '%');                    
//                    $order_id = M('OrderShipping')->where(array('track_number'=>$get_data['keyword']))->getField('id_order');//查找运单号
//                    if($order_id) $key_where[$prefix . 'id_order'] = array('EQ', $order_id); 
////                    $product_name = M('OrderItem')->field('id_order')->where(array('sale_title'=>array('LIKE', '%' . $get_data['keyword'] . '%')))->select();//查找产品名称
////                    if($product_name) {$order_ids = array_column($product_name, 'id_order');$key_where[$prefix . 'id_order'] = array('IN', $order_ids); }                 
//                    $domain = M('Domain')->field('id_domain')->where(array('name'=>array('LIKE', '%' . $get_data['keyword'] . '%')))->select();
//                    if($domain) {$domain_id = array_column($domain, 'id_domain');$key_where[$prefix . 'id_domain'] = array('IN', $domain_id);}//查找域名
//                    $order_id = M('OrderWave')->field('id_order')->where(array('wave_number'=>$get_data['keyword']))->find();
//                    $order_id=implode(',',$order_id);
//                    if($order_id){
//                        $key_where[$prefix . 'id_order'] = array('EQ', $order_id);
//                    }
//                    //广告专员查询
//                    $userIds=M('users')->where(array('user_nicename'=>array('LIKE', '%' . $get_data['keyword'] . '%')))->getField('id',true);
//                    if($userIds){
//                        $key_where[$prefix . 'id_users'] = array('in', $userIds);
//                    }                
//                    $key_where['_logic'] = 'or';
//                    $return['_complex'] = $key_where;
//                }
            }

            if ($get_data['start_time'] or $get_data['end_time'])
            {
                $created_at_array = array();
                if (!$get_data['start_time'] && !$get_data['end_time'])
                {
                    $get_data['start_time'] = date('Y-m-d H:i:s',time()-86400*7);
                    $get_data['end_time'] = date('Y-m-d H:i:s',time());
                    $created_at_array[] = array('EGT', $get_data['start_time']);
                    $created_at_array[] = array('LT', $get_data['end_time']);
                }
                if ($get_data['start_time'])
                {
                    $created_at_array[] = array('EGT', $get_data['start_time']);
                }
                if ($get_data['end_time'])
                {
                    $created_at_array[] = array('LT', $get_data['end_time']);
                }
                $return[$prefix . 'created_at'] = $created_at_array;
            }
            if($get_data['delivery_start_time'] or $get_data['delivery_end_time']){
                $temp_arr=array();
                if ($get_data['delivery_start_time'])
                    $temp_arr[] = array('EGT', $get_data['delivery_start_time']);
                if ($get_data['delivery_end_time'])
                    $temp_arr[] = array('LT', $get_data['delivery_end_time']);
                $return[$prefix . 'date_delivery'] = $temp_arr;                
            }
            if ($get_data['trackStartDate'] or $get_data['trackEndDate']) {
                $trackDateArray = array();
                if ($get_data['trackStartDate'])
                    $trackDateArray[] = array('EGT', $get_data['trackStartDate']);
                if ($get_data['trackEndDate'])
                    $trackDateArray[] = array('LT', $get_data['trackEndDate']);
                $return['track_date'] = $trackDateArray;
            } 
            if($get_data['settlement_start_time'] or $get_data['settlement_end_time']){
                $settlementDateArray = array();
                if ($get_data['settlement_start_time'])
                    $trackDateArray[] = array('EGT', $get_data['settlement_start_time']);
                if ($get_data['settlement_end_time'])
                    $trackDateArray[] = array('LT', $get_data['settlement_end_time']);
                $return['os.date_settlement'] = $trackDateArray;                
            }
            
            /*if($get_data['track_status']) {
                $status_label = array();
                $order_ids = D('Order/OrderShipping')->field('id_order')->where(array('status_label'=>$get_data['track_status']))->select();
                $order_ids = array_column($order_ids, 'id_order');
                $return[$prefix . 'id_order'] = array('IN', $order_ids);
            }*/
            //if($get_data['delivery_start_date']){ $return['delivery_date'] = array('EGT',$get_data['delivery_start_date']);}
            //if($get_data['delivery_end_date']){  $return['delivery_date'] = array('ELT',$get_data['delivery_end_date']); }
            if ($get_data['delivery_start_date'] or $get_data['delivery_end_date']) {
                $delivery_date_array = array();
                if ($get_data['delivery_start_date'])
                    $delivery_date_array[] = array('EGT', $get_data['delivery_start_date']);
                if ($get_data['delivery_end_date'])
                    $delivery_date_array[] = array('LT', $get_data['delivery_end_date']);
                $return[$prefix . 'delivery_date'] = $delivery_date_array;
            }
            if ($get_data['status_id']) {
                $return[$prefix . 'id_order_status'] = array('EQ', $get_data['status_id']);
            }
            if ($get_data['id_shipping']) {
                $return[$prefix . 'id_shipping'] = array('EQ', $get_data['id_shipping']);
            }
            if ($get_data['department_id']&&is_array($get_data['department_id'])) {
                $department_id = trim(implode(',',$get_data['department_id']));
                $return[$prefix . 'id_department'] = array('IN', $department_id);
            }
            if ($get_data['department_id']) {
                $return[$prefix . 'id_department'] = array('IN', $get_data['department_id']);
            }
            if ($get_data['id_increment']) {
                $return[$prefix . 'id_increment'] = array('IN', $get_data['id_increment']);
            }
            if ($get_data['zone_id']) {
                $return[$prefix . 'id_zone'] = array('EQ', $get_data['zone_id']);
            }
            
            if ($get_data['id_domain'])
                $return['id_domain'] = array('EQ', $get_data['id_domain']);
            if ($get_data['order_repeat']) {
                $return['order_repeat'] = $get_data['order_repeat'] == 1 ? array('GT', 0) : array('EQ', 1);
            }
            if (isset($get_data['province']) && $get_data['province']) {
                $return[$prefix . 'province'] = array('EQ', $get_data['province']);
            }
            if (isset($get_data['id_warehouse']) && $get_data['id_warehouse']) {
                $return[$prefix . 'id_warehouse'] = array('EQ', $get_data['id_warehouse']);
            }
            if (isset($get_data['confirmation_status']) && $get_data['confirmation_status']) {
                $return[$prefix . 'confirmation_status'] = array('EQ', $get_data['confirmation_status']);
            }
            if (isset($get_data['http_referer']) && $get_data['http_referer']) {
                $return[$prefix . 'http_referer'] = array('LIKE', '%' . $get_data['http_referer'] . '%');
            }
        }
        return $return;
    }

    public function getStatusLabel($statusId = false) {
        $cache = S('getAllOrderStatus');
        if ($cache) {
            $statusList = unserialize($cache);
        } else {
            $statusList = array();
            $allStatus = D("Order/OrderStatus")->field('id_order_status,title')->select();
            if ($allStatus) {
                foreach ($allStatus as $item) {
                    $statusList[$item['id_order_status']] = $item['title'];
                }
            }
            S('getAllOrderStatus', serialize($statusList), array('type' => 'file', 'expire' => 600));
        }
        $return = $statusId ? $statusList[$statusId] : $statusList;
        return $return;
    }

    public function page($total_size = 1, $page_size = 0, $current_page = 1, $listRows = 6, $pageParam = '', $pageLink = '', $static = FALSE)
    {
        if ($page_size == 0) {
            $page_size = C("PAGE_LISTROWS");
        }
        if (empty($pageParam)) {
            $pageParam = C("VAR_PAGE");
        }
        $Page = new \Page($total_size, $page_size, $current_page, $listRows, $pageParam, $pageLink, $static);
        $Page->SetPager('Admin', '{first}{prev}&nbsp;{liststart}{list}{listend}&nbsp;{next}{last}', array("listlong" => "9", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => ""));
        return $Page;
    }
    
    /**
     * 获取仓库管理下的订单
     * @param bool $status
     * @return array
     */
    public function getWarehouseOrder($status = false) {
        $getStaArr = $status ? $status : array('IN', array(4, 5, 6));
        $getFormWhere = $this->form_where($_GET);
        $getFormWhere['id_order_status'] = $getStaArr;
//        $_SESSION['department_id'] ? $getFormWhere['id_department'] = array('IN',$_SESSION['department_id']) : '';
        //$getFormWhere['shipping_id'] = array('NEQ','');
        $M = new \Think\Model;
        $ordTable = D("Order/Order")->getTableName();
        if ($_GET['product_id']) {
            $ordIteTable = D("Order/OrderItem")->getTableName();
            $findOrder = $M->table($ordTable . ' AS o LEFT JOIN ' . $ordIteTable . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                            ->where(array('oi.id_product' => $_GET['product_id'], 'o.id_order_status' => $getStaArr))
                            ->group('oi.id_order')->select();
            $allId = array_column($findOrder, 'id_order');

            $getFormWhere['id_order'] = $allId ? array('IN', $allId) : 0;
        }
        switch ($_GET['action']) {
            //无运单号 页面    可以另外新建方法分开连表查询   目前为了快速
            case 'untracknumber':
                $ordShiTable = D("Common/OrderShipping")->getTableName();
                $selOrder = $M->table($ordTable . ' AS o LEFT JOIN ' . $ordShiTable . ' AS os ON o.id_order=os.id_order')->field('o.id_order')
                                ->where("(os.track_number = '' OR  os.track_number IS NULL)")
                                ->where(array('o.id_order_status' => $getStaArr))
                                ->group('os.id_order')->cache(true, 3600)->select();
                $getAllId = $selOrder ? array_column($selOrder, 'id_order') : '';
                $getFormWhere['id_order'] = (isset($getFormWhere['id_order']) && $getFormWhere['id_order'] && $getAllId) ? array('IN', array_merge($getFormWhere['id_order'][1], $getAllId)) : array('IN', $getAllId);
                break;
        }
        $baseSql = D("Common/Order")->where($getFormWhere);
        $count = $baseSql->count();
        $todayDate = date('Y-m-d');
        $todayTotal = D("Common/Order")->where($getFormWhere)->where(array('date_delivery' => array('like', $todayDate . '%')))->count();
        $formData = array();
        $formData['web_url'] = D('Common/Domain')
                ->field('`name` web_url')
                ->order('`name` ASC')
                ->cache(true, 3600)
                ->select();


        $page = $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
        $page = $this->page($count, $page);
        $orderList = $baseSql->where($getFormWhere)->order("date_delivery asc,tel DESC,first_name desc,email desc")->limit($page->firstRow . ',' . $page->listRows)->select();
        $order_item = D('Order/OrderItem');
        foreach ($orderList as $key => $o) {
            $orderList[$key]['products'] = $order_item->get_item_list($o['id_order']);
        }

        $shipping = D("Common/Shipping")->field('id_shipping,title')->cache(true, 86400)->select();
        $shipTemp = $shipping ? array_column($shipping, 'title', 'id_shipping') : array();

        //echo $baseSql->where($getFormWhere)->fetchSql(true)->select();
        $TProWhere = array('id_shipping' => $getStaArr, 'date_delivery' => array('EGT', date('Y-m-d 00:00:00')));
        $productCount = D("Order/order")->field('SUM(`order_count`) as total')->where($TProWhere)->select();
        $allProduct = D('Common/Product')->field('id_product,title')->order('id_product desc')->cache(true, 86400)->select();

        $returnArr = array(
            'form_data' => $formData,
            'page' => $page->show('Admin'),
            'todayTotal' => $todayTotal,
            'orderTotal' => $count,
            'order_list' => $orderList,
            'shipping' => $shipTemp,
            'product' => D("Common/product")->getAllProduct(),
            'todayProduct' => $productCount,
            'allProduct' => $allProduct,
        );
        return $returnArr;
    }

    /**
     * 匹配退货的订单
     * @param array $params
     */
    public function matchOrder($params) {
        $M = new \Think\Model;
        $ordName = D("Common/Order")->getTableName();
        $ordIteName = D("Common/OrderItem")->getTableName();
        $findOrder = array();
        $returnId = D("Common/OrderReturn")->cache(true, 13600)->getField('id_order', true);
        if (!$returnId) {
            return 0;
        }
        foreach ($params as $key => $item) {
            $where = array('oi.id_product' => $item['id_product'], 'o.id_order' => array('IN', $returnId), 'id_product_sku' => $item['id_product_sku'], 'o.id_order_status' => 10);

            $count = $M->table($ordName . ' AS o LEFT JOIN ' . $ordIteName . ' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                            ->where($where)
                            //->fetchSql(true)
                            ->group('oi.id_order_status')->select();
            if (count($count) > 0) {
                $findOrder[$key] = $count;
            }
        }
        return count($params) == count($findOrder) ? 1 : 0;
    }


    public function order_list_by_status($status_id){
        if($status_id == 6){
            $where = $this->form_where($_GET,'o.');
            //所属仓库只能看到所属仓库的订单
            $belong_ware_id = $_SESSION['belong_ware_id'];
            if(isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
                $where['id_warehouse'] = array('EQ',$_GET['id_warehouse']);
            } else {
                if (count($belong_ware_id) != 1 || (count($belong_ware_id) == 1 && $belong_ware_id[0] != 1)) {
                    $hwhere['id_warehouse'] = array('IN', $belong_ware_id);
                    $where['id_warehouse'] = array('IN', $belong_ware_id);
                }
            }
        }else{
            $where = $this->form_where($_GET);
        }

        $M = new \Think\Model;
        if (isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['id_zone'] = $_GET['zone_id'];
        }
        if(isset($_GET['sku']) && $_GET['sku']) {
            $where['oi.sku'] = I('get.sku');//array('LIKE',I('get.sku').'%');//I('get.sku');
            //$where['oi.id_order'] = array('NEQ','');
        }
        $where['id_order_status'] = array('EQ',$status_id);
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();
        //$formData['product_type'] = $baseSql->getFieldGroupData('product_type');
        $form_data['track_status'] = D('Order/OrderShipping')->field('status_label as track_status')
            ->where("status_label is not null or status_label !='' ")
            ->group('status_label')->cache(true, 12000)->select();

        //今日统计订单 条件
        $today_where = $where;
        $today_where['o.created_at'] = array('EGT', $today_date);
//        $all_domain_total = $this->field('count(`id_domain`) as total,id_domain')->where($today_where)
//            ->order('total desc')->group('id_domain')->select();
        //修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
        if (isset($where['status_label']) && $where['status_label']) {
            if($_GET['sku']){
                $count = $this->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)
                    ->select();
                $count = $count?count($count):0;
                $today_total = $this->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;
                $page = $this->page($count, $this->page);
                $order_list = $this->alias('o')
                    ->field('o.*,s.date_signed')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->group('oi.id_order')
                    ->limit($page->firstRow . ',' . $page->listRows)->select();

            }else{
                $count = $this->alias('o')->field('o.id_order')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->select();
                $count = $count?count($count):0;
                $today_total = $this->alias('o')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($today_where)->select();
                $today_total = $today_total?count($today_total):0;

                $page = $this->page($count, $this->page);
                $order_list = $this->alias('o')->field('o.*,s.date_signed')
                    ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                    ->where($where)->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
            }

        } else {
            if($_GET['sku']){
                $count = $this->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->group('oi.id_order')
                    ->select();
                $count = $count?count($count):0;
                $today_total = $this->alias('o')->field('oi.id_order')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($today_where)
                    ->group('oi.id_order')
                    ->count();
                $today_total = $today_total?count($today_total):0;
                $page = $this->page($count, $this->page);
                $order_list = $this->alias('o')->field('o.*,os.date_signed')
                    ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
                    ->join('__ORDER_SHIPPING__ os on o.id_order = os.id_order','LEFT')
                    ->where($where)
                    ->group('oi.id_order')
                    ->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)
                    ->select();

            }else{
                $count = $this->alias('o')->where($where)->count();
                $today_total = $this->alias('o')->where($today_where)->count();
                $page = $this->page($count, $this->page);
                $order_list = $this->alias('o')
                    ->where($where)
                    ->order("o.id_order DESC")
                    ->limit($page->firstRow . ',' . $page->listRows)->select();
            }

        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $order_list[$key]['shipping_name'] = D('Common/Shipping')->where('id_shipping='.$o['id_shipping'])->getField('title');
        }
        $department = M('Department')->where('type=1')->select();
        $zone = M('Zone')->select();
        $hwhere['status'] = 1;
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where($hwhere)->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus')->field('id_order_status,title')->where('status=1 and id_order_status IN (4,5,6,7,8,9,10,14)')->select();
        $status_model = array_column($status_model,'title','id_order_status');
       $data = array(
           'zone'=>$zone,
           'department'=>$department,
           "get"=>$_GET,
            "form_data"=>$form_data,
            "page"=>$page->show('Admin'),
            "today_total"=>$today_total,
            "order_total"=>$count,
            "all_domain_total"=>$all_domain_total,
            "order_list"=>$order_list,
           "status_list"=>$status_model,
           'warehouse'=>$warehouse
       );
      return $data;
    }

}
