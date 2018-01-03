<?php
namespace Common\Model;

use Common\Model\CommonModel;
use Order\Lib\OrderStatus;

class OrderModel extends CommonModel
{
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        //array('user_login', 'require', '用户名称不能为空！', 1, 'regex', CommonModel:: MODEL_INSERT  ),
        //array('user_pass', 'require', '密码不能为空！', 1, 'regex', CommonModel:: MODEL_INSERT ),
    );
    
    protected $_auto = array(
        array('create_at', 'mGetDate', CommonModel:: MODEL_INSERT, 'callback'),
        //array('birthday','',CommonModel::MODEL_UPDATE,'ignore')
    );
    
    //用于获取时间，格式为2016-08-08 18:18:18,注意,方法不能为private
    function mGetDate()
    {
        return date('Y-m-d H:i:s');
    }
    
    /**
     * 退货状态 label
     * @return array
     */
    public function returnStatusLabel()
    {
        return array(1 => '质量问题', 2 => '产品破损', 3 => '产品完好/拒收');
    }
    
    /**
     * 获取订单基本状态信息
     * @param bool $statusId
     * @return mixed
     */
    public function getBaseStatus($statusId = false)
    {
        if ($statusId) {
            $where = array('parent_id' => array('EQ', $statusId));
        } else {
            $statusId = 0;
            $where = array('parent_id' => array('EQ', $statusId), 'status' => array('EQ', 1));
        }
        $select = D("Common/OrderStatus")->where($where)->cache(true, 360)->select();
        return $select;
    }
    
    public function getStatusLabel($statusId = false)
    {
        $cache = S('getAllOrderStatus');
        if ($cache) {
            $statusList = unserialize($cache);
        } else {
            $statusList = array();
            $allStatus = D("Common/OrderStatus")->field('id,title')->select();
            if ($allStatus) {
                foreach ($allStatus as $item) {
                    $statusList[$item['id']] = $item['title'];
                }
            }
            S('getAllOrderStatus', serialize($statusList), array('type' => 'file', 'expire' => 600));
        }
        $return = $statusId ? $statusList[$statusId] : $statusList;
        return $return;
    }
    
    public function untreatedToWaitDelivery($orderId, $statusId, $comment = false)
    {
        $order = D("Common/Order")->find($orderId);
        /*if(in_array($order['status_id'],array(1,7,8,21)) &&$statusId==21){
        }else{
            $return = 0;
        }*/
        //2为待发货状态记录，7为无效订单，订单表记录为无效订单，order History记录无效订单的原因
        $updateData = array('status_id' => $statusId);
        $_POST['delivery_date'] = strtotime($_POST['delivery_date'])>strtotime($order['create_at'])?$_POST['delivery_date']:$order['create_at'];
        if (isset($_POST['delivery_date']) && $_POST['delivery_date']) {
            $updateData['delivery_date'] = $_POST['delivery_date'];
        } else {
            $updateData['delivery_date'] = strtotime($order['create_at'])>0?$order['create_at']:date('Y-m-d 10:00:00',strtotime('-1 day'));//$order['create_at'] ? $order['create_at'] : date('Y-m-d');
        }
        //备注订单信息
        $updateData['order_remark'] = $comment;
        D("Common/Order")->where('id=' . $order['id'])->save($updateData);
        D("Common/OrderStatusHistory")->addHistory($orderId, $statusId, $comment);
        $return = 1;
        return $return;
    }
    
    /**
     * 待发货或无效订单状态
     * @param $orderId
     * @param $statusId
     * @param bool $comment
     * @comment  订单当前状态处于未处理或无效订单修改后，可以转为无效订单
     */
    public function checkOrderStatus($orderId, $statusId, $comment = false, $data = false)
    {
        $order = $this->find($orderId);
        /*if(in_array($order['status_id'],array(1,7,8,21))){
            //2为待发货状态记录，7为无效订单，订单表记录为无效订单，order History记录无效订单的原因
        }else{
            $return = 0;
        }*/
        if ($statusId == 2) {
            $deliveryDate = (isset($data['delivery_date']) && $data['delivery_date']) ? $data['delivery_date'] : false;
            $deliveryDate = $deliveryDate ? $deliveryDate : $order['create_at'];
            $updateData = array('status_id' => $statusId, 'delivery_date' => $deliveryDate);
            //$deliveryDate = $order['create_at']?date('Y-m-d',strtotime($order['create_at'])):'';
            
            //写入数据到配送表
            $shipData = array('order_id'    => $order['id'], 'shipping_date' => $deliveryDate,
                              'shipping_id' => $data['shipping_id'], 'created_at' => date('Y-m-d H:i:s'));
            $getShip = D("Common/OrderShipping")->where('order_id=' . $order['id'])->find();
            if ($getShip) {
                D("Common/OrderShipping")->where('id=' . $getShip['id'])->save($shipData);
            } else {
                D("Common/OrderShipping")->data($shipData)->add();
            }
        } else {
            $updateData = array('status_id' => $statusId);
        }
        //备注订单信息
        $updateData['order_remark'] = $comment;
        D("Common/Order")->where('id=' . $order['id'])->save($updateData);
        D("Common/OrderStatusHistory")->addHistory($orderId, $statusId, $comment);
        $return = 1;
        return $return;
    }
    
    protected function _before_write(&$data)
    {
        parent::_before_write($data);
        
        if (!empty($data['user_pass']) && strlen($data['user_pass']) < 25) {
            $data['user_pass'] = sp_password($data['user_pass']);
        }
    }
    
    /**
     * 获取某个键值 的分组信息
     * @param $field
     * @param $where
     * @return mixed
     */
    public function getFieldGroupData($field, $where = false, $selectField = false)
    {
        if (isset($where) && $where) {
            $model = D("Common/Order")->where($where);
        } else {
            $model = D("Common/Order");
        }
        $selectField = $selectField ? $selectField : "count(*) as count," . $field;
        $data = $model->field($selectField)->group($field)->cache(true, 60)->select();
        return $data;
    }
    
    /**
     * form表单过滤条件
     * @param $getData
     * @return array
     */
    public function getFormWhere($getData,$prefix='')
    {
        $getData = array_filter($getData);
        $return = array();
        if (is_array($getData) && count($getData)) {
            if ($getData['keyword']) {
                if (strpos($getData['keyword'], ',') !== false) {
                    $keyword = trim($getData['keyword'], ',');
                    $return[$prefix.'id'] = array('IN', $keyword);
                } else {
                    if ((int)$getData['keyword'] > 0) $returnOr[$prefix.'id'] = array('EQ', $getData['keyword']);
                    $returnOr[$prefix.'web_url'] = array('LIKE', '%' . $getData['keyword'] . '%');
                    $returnOr[$prefix.'user_name'] = array('LIKE', '%' . $getData['keyword'] . '%');
                    $returnOr[$prefix.'user_tel'] = array('LIKE', '%' . $getData['keyword'] . '%');
                    $returnOr[$prefix.'user_address'] = array('LIKE', '%' . $getData['keyword'] . '%');
                    $returnOr[$prefix.'user_email'] = array('LIKE', '%' . $getData['keyword'] . '%');
                    //$returnOr['track_number'] = array('LIKE','%'.$getData['keyword'].'%');
                    //$returnOr['track_status'] = array('LIKE','%'.$getData['keyword'].'%');
                    $returnOr[$prefix.'user_remark'] = array('LIKE', '%' . $getData['keyword'] . '%');
                    //$returnOr['product_color'] = array('LIKE','%'.$getData['keyword'].'%');
                    //$returnOr['product_size'] = array('LIKE','%'.$getData['keyword'].'%');
                    //$returnOr['product_name'] = array('LIKE','%'.$getData['keyword'].'%');
                    $returnOr['_logic'] = 'or';
                    $return['_complex'] = $returnOr;
                }
            }
            if ($getData['start_time'] or $getData['end_time']) {
                $createAtArray = array();
                if ($getData['start_time']) $createAtArray[] = array('EGT', $getData['start_time']);
                if ($getData['end_time']) $createAtArray[] = array('LT', $getData['end_time']);
                $return['create_at'] = $createAtArray;
            }
            //if($getData['end_time']){ $return['create_at'] = array('ELT',$getData['end_time']); }
            
            //if($getData['trackStartDate']){ $return['track_date'] = array('EGT',$getData['trackStartDate']); }
            //if($getData['trackEndDate']){ $return['track_date'] = array('ELT',$getData['trackEndDate']); }
            if ($getData['trackStartDate'] or $getData['trackEndDate']) {
                $trackDateArray = array();
                if ($getData['trackStartDate']) $trackDateArray[] = array('EGT', $getData['trackStartDate']);
                if ($getData['trackEndDate']) $trackDateArray[] = array('LT', $getData['trackEndDate']);
                $return['track_date'] = $trackDateArray;
            }
            //if($getData['deliveryStartDate']){ $return['delivery_date'] = array('EGT',$getData['deliveryStartDate']);}
            //if($getData['deliveryEndDate']){  $return['delivery_date'] = array('ELT',$getData['deliveryEndDate']); }
            if ($getData['deliveryStartDate'] or $getData['deliveryEndDate']) {
                $deliveryDateArray = array();
                if ($getData['deliveryStartDate']) $deliveryDateArray[] = array('EGT', $getData['deliveryStartDate']);
                if ($getData['deliveryEndDate']) $deliveryDateArray[] = array('LT', $getData['deliveryEndDate']);
                $return['delivery_date'] = $deliveryDateArray;
            }
            if ($getData['status_id']) {
                $return['status_id'] = array('EQ', $getData['status_id']);
            }
            if ($getData['shipping_id']) {
                $return['shipping_id'] = array('EQ', $getData['shipping_id']);
            }
            if ($getData['web_url']) $return['web_url'] = array('EQ', $getData['web_url']);
            if ($getData['product_type']) $return['product_type'] = array('EQ', $getData['product_type']);
            if ($getData['delivery_status']) {
                $return['track_status'] = $getData['delivery_status'] == 1 ? array('NEQ', $getData['delivery_status']) : array('EQ', $getData['delivery_status']);
            }
            if ($getData['track_status']) $return['status_label'] = array('EQ', $getData['track_status']);
            if ($getData['order_cancel']) {
                $return['order_cancel'] = $getData['order_cancel'] == 1 ? array('EQ', 1) : array('EQ', 0);
            }
            if ($getData['order_repeat']) {
                $return['order_repeat'] = $getData['order_repeat'] == 1 ? array('GT', 0) : array('EQ', 1);
            }
            if ($getData['delivery']) {
                $return['delivery'] = $getData['delivery'] == 1 ? array('EQ', 1) : array('EQ', 0);
            }
            if(isset($getData['user_province']) && $getData['user_province']){
                $return['user_province'] =  array('EQ', $getData['user_province']);
            }
        }
        return $return;
    }

    /**
     * 订单点击发货，减库存
     * @param $orderId
     * @return bool 返回库存不足
     */
    public function saveStock($orderId)
    {
        $flag = true;
        try {
            $orderId = (int)$orderId;
            $oItemObj = D("Common/OrderItem")->where('order_id=' . $orderId)->select();
            if ($oItemObj) {
                foreach ($oItemObj as $item) {
                    $qty = $item['qty'];
                    $childSkuId = $item['sku_id'];
                    $productId = $item['product_id'];
                    $product = D('Common/Product')->find($productId);
                    $proQty = $product['qty'] - $qty;
                    D('Common/Product')->where('id=' . $productId)->save(array('qty' => $proQty));
                    //$skuWhere = array('product_id'=>$productId,'sku_id'=>$childSkuId);//->where($skuWhere)
                    $productSku = D("Common/ProductSku")->find($childSkuId);
                    if ($productSku['id']&& $productSku['qty']>0) {
                        $setQty = $productSku['qty'] - $qty;
                        D("Common/ProductSku")->where('id=' . $productSku['id'])->save(array('qty' => $setQty));
                    }else{
                        $flag = false;
                    }
                }
            }
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
        return $flag;
    }
    
    /**
     * 退货
     * @return int
     */
    public function returnOrder()
    {
        $orderId = (int)$_POST['order_id'];
        $order = D("Common/Order")->find($orderId);
        $getStatusId = $_POST['status_id'];
        $returnOrder = false;
        switch ($getStatusId) {
            case 1:
            case 2:
                $statusId = 12;
                break;
            case 3:
                $statusId = 6;
                $returnOrder = array('order_id'      => $orderId,
                                     'product_id'    => 1,
                                     'product_title' => 'empty', 'track_number' => $order['track_number'],
                                     'shipping_id'   => $order['shipping_id'], 'qty' =>1);
                break;
        }
        if ($order['id'] && $order['status_id'] != $statusId) {
            $comment = $this->returnStatusLabel($getStatusId) . '  ' . $_POST['remark'];
            if ($returnOrder) {//产品完好，才存入可转发订单
                $returnOrder['remark'] = $comment;
                D("Common/ReturnOrderProduct")->data($returnOrder)->add();
            }
            D("Common/Order")->where('id=' . $orderId)->save(array('status_id' => $statusId));
            D("Common/OrderStatusHistory")->addHistory($orderId, $statusId, $comment);
            $status = 1;
        } else {
            $status = 0;
        }
        return $status;
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
    public function getWarehouseOrder($status = false)
    {
        $getStaArr = $status ? $status : array('IN', array(2, 18, 19));
        $getFormWhere = D("Common/Order")->getFormWhere($_GET);
        $getFormWhere['status_id'] = $getStaArr;
        //$getFormWhere['shipping_id'] = array('NEQ','');
        $M = new \Think\Model;
        $ordTable = D("Common/Order")->getTableName();
        if ($_GET['product_id']) {
            $ordIteTable = D("Common/OrderItem")->getTableName();
            $findOrder = $M->table($ordTable . ' AS o LEFT JOIN ' . $ordIteTable . ' AS oi ON o.id=oi.order_id')->field('o.id')
                ->where(array('oi.product_id' => $_GET['product_id'], 'o.status_id' => $getStaArr))
                ->group('oi.order_id')->select();
            $allId = array_column($findOrder, 'id');
            
            $getFormWhere['id'] = $allId ? array('IN', $allId) : 0;
        }
        switch ($_GET['action']) {
            //无运单号 页面    可以另外新建方法分开连表查询   目前为了快速
            case 'untracknumber':
                $ordShiTable = D("Common/OrderShipping")->getTableName();
                $selOrder = $M->table($ordTable . ' AS o LEFT JOIN ' . $ordShiTable . ' AS os ON o.id=os.order_id')->field('o.id')
                    ->where("(os.track_number = '' OR  os.track_number IS NULL)")
                    ->where(array('o.status_id' => $getStaArr))
                    ->group('os.order_id')->cache(true,3600)->select();
                $getAllId = $selOrder ? array_column($selOrder, 'id') : '';
                $getFormWhere['id'] = (isset($getFormWhere['id']) && $getFormWhere['id'] && $getAllId) ? array('IN', array_merge($getFormWhere['id'][1], $getAllId)) : array('IN', $getAllId);
                break;
        }
        $baseSql = D("Common/Order")->where($getFormWhere);
        $count = $baseSql->count();
        $todayDate = date('Y-m-d');
        $todayTotal = D("Common/Order")->where($getFormWhere)->where(array('delivery_date' => array('like', $todayDate . '%')))->count();
        $formData = array();
        $formData['web_url'] = D('Common/Domain')
            ->field('`name` web_url')
            ->order('`name` ASC')
            ->cache(true, 3600)
            ->select();
        
        
        $page = $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
        $page = $this->page($count, $page);
        $orderList = $baseSql->where($getFormWhere)->order("delivery_date asc,user_tel DESC,user_name desc,user_email desc")->limit($page->firstRow . ',' . $page->listRows)->select();
        $order_item = D('Common/OrderItem');
        foreach ($orderList as $key => $o) {
            $orderList[$key]['products'] = $order_item->getItemsList($o['id']);
        }
        
        $shipping = D("Common/Shipping")->field('id,title')->cache(true, 86400)->select();
        $shipTemp = $shipping ? array_column($shipping, 'title', 'id') : array();
        
        //echo $baseSql->where($getFormWhere)->fetchSql(true)->select();
        $TProWhere = array('status_id' => $getStaArr, 'delivery_date' => array('EGT', date('Y-m-d 00:00:00')));
        $productCount = D("Common/order")->field('SUM(`total_qty_ordered`) as total')->where($TProWhere)->select();
        $allProduct = D('Common/Product')->field('id,title')->order('id desc')->cache(true, 86400)->select();
        
        $returnArr = array('form_data'    => $formData,
                           'page'         => $page->show('Admin'),
                           'todayTotal'   => $todayTotal,
                           'orderTotal'   => $count,
                           'order_list'   => $orderList,
                           'shipping'     => $shipTemp,
                           'product'      => D("Common/product")->getAllProduct(),
                           'todayProduct' => $productCount,
                           'allProduct'   => $allProduct,
        );
        return $returnArr;
    }
    
    public function getInvalid($status = false)
    {
        $getFormWhere = $this->getFormWhere($_GET);
        $getFormWhere['status_id'] = $status ? $status : array('IN', array(5, 7, 8, 9, 10, 11));
        $baseSql = D("Common/Order")->where($getFormWhere);
        $count = $baseSql->count();
        $todayDate = date('Y-m-d 00:00:00');
        $todayTotal = D("Common/Order")->where($getFormWhere)->where(array('create_at' => array('EGT', $todayDate)))->count();
        $formData = array();
        $allWebUrl = $baseSql->getFieldGroupData('web_url');
        $formData['web_url'] = $allWebUrl;
        //$formData['product_type'] = $baseSql->getFieldGroupData('product_type');
        //$formData['track_status'] = $baseSql->getFieldGroupData('track_status');
        $allWebTotal = array();
        if ($allWebUrl && $todayTotal > 0 && is_array($allWebUrl)) {
            foreach ($allWebUrl as $item) {
                $todayWebWhere = array('web_url' => array('EQ', $item['web_url']), 'create_at' => array('EGT', $todayDate));
                $getWebData = $this->getFieldGroupData('web_url', $todayWebWhere);
                if ($getWebData[0]['count'] > 0) {
                    $allWebTotal[$getWebData[0]['web_url']] = $getWebData[0]['count'];
                }
            }
        }
        $pageSize = $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
        $page = $this->page($count, $pageSize);
        $orderList = $baseSql->where($getFormWhere)->order("id desc,user_tel DESC,user_name desc,user_email desc")->limit($page->firstRow . ',' . $page->listRows)->select();
        $order_item = D('Common/OrderItem');
        foreach ($orderList as $key => $o) {
            $orderList[$key]['products'] = $order_item->getItemsList($o['id']);
        }
        $shipping = D("Common/Shipping")->where('status=1')->cache(true, 6000)->select();
        $return = array(
            'form_data'    => $formData,
            'page'         => $page->show('Admin'),
            'todayTotal'   => $todayTotal,
            'orderTotal'   => $count,
            'todayWebData' => $allWebTotal,
            'order_list'   => $orderList,
            'shipping'     => $shipping,
        );
        return $return;
    }

    /**
     * 匹配退货的订单
     * @param array $params
     */
    public function matchOrder($params){
        $M = new \Think\Model;
        $ordName = D("Common/Order")->getTableName();
        $ordIteName = D("Common/OrderItem")->getTableName();
        $findOrder  = array();
        $returnId = D("Common/ReturnOrderProduct")->cache(true, 13600)->getField('order_id',true);
        if(!$returnId){
            return 0;
        }
        foreach($params as $key=>$item){
            $where = array('oi.product_id'=>$item['product_id'],'o.id'=>array('IN',$returnId),'sku_id'=>$item['sku_id'],'o.status_id'=>6);

            $count = $M->table($ordName.' AS o LEFT JOIN '.$ordIteName.' AS oi ON o.id=oi.order_id')->field('o.id')
                ->where($where)
                //->fetchSql(true)
                ->group('oi.order_id')->select();
            if(count($count)>0){
                $findOrder[$key] = $count;
            }

        }
        return count($params)==count($findOrder)?1:0;
    }

    /**
     * 导出订单属性排序
     * @param $data
     * @return array
     */
    public function optionSort($data){
        $productOption = D("Common/ProductOption");
        if(is_array($data)&& $data){
            foreach($data as $Key=>$item){
                $number = false;
                if(isset($item['number'])){
                    $number = $item['number'];unset($item['number']);
                }
                if(is_array($item) && count($item)){
                    foreach($item as $ke=>$val){
                        if(is_array($val) && $val['option_id']){
                            $proOpt = $productOption->cache(true,3600)->find($val['option_id']);
                            $item[$ke]['sort'] = $proOpt['sort']?$proOpt['sort']:0;
                        }
                    }
                }
                $sort = array_column($item, 'sort');
                array_multisort($sort, SORT_DESC, $item);
                $data[$Key] =   $item;
                if($number){
                    $data[$Key]['number'] = $number;
                }
            }
        }
        return $data;
    }

    /**
     * 处理黑名单与 IP 地址
     * @param $data
     */
    public function black_list_and_ip_address($data){
        if(empty($data['ip_address'])){
            try{
                import("getGeoIpAddress");
                $getGeoIpAddress = New \getGeoIpAddress();
                $Reader  = $getGeoIpAddress->reader();
                $orderModel = D("Common/Order");
                $blacklist = D('Common/OrderBlacklist');

                $record = $Reader->city($data['ip']);
                $ipAddress = trim($record->country->names['zh-CN'].' '.$record->city->names['zh-CN']);
            }catch (\Exception $e){
                $ipAddress = '';
            }
            if($ipAddress){
                //黑名单 查询
                $where = " (field_name='phone' and  `value` LIKE '%".$data['user_tel']."%') or ";
                $where .= " (field_name='name' and  `value` LIKE '%".$data['user_name']."%') or ";
                $where .= " (field_name='email' and  `value` LIKE '%".$data['user_email']."%') or ";
                $where .= " (field_name='address' and  `value` LIKE '%".$data['user_address']."%') or";
                $where .= " (field_name='ip' and  `value` ='".$data['ip']."')";
                $result = $blacklist->where($where)->order('level desc')->find();
                $result['level'] = $result['level']?$result['level']:0;
                $field = isset($result['field_name'])?$result['field_name']:'';
                $update = array('ip_address'=>$ipAddress,
                    'blacklist_level'=>$result['level'],
                    'blacklist_field'=>$field);
                $orderModel->where('id='.$data['id'])->save($update);
                $data['ip_address'] = $ipAddress;
                $data['blacklist_level'] = $result['level'];
            }
        }
        return $data;
    }

    /*
     * 广告投放列表， 每位广告手对应的域名
     */
    public function getCorrespondUser(){
        $M = new \Think\Model;
        $orderProTable = D("Common/Order")->getTableName();
        $domainTable = D("Common/Domain")->getTableName();
        $usersTable = D("Common/Users")->getTableName();
        $selData= $M->table($orderProTable . ' AS o LEFT JOIN ' . $domainTable . ' AS d ON o.`id_domain`= d.id_domain'.
            ' LEFT JOIN '.$usersTable.' as u ON u.id = o.`id_uers`'
        )->field('d.name,o.id_uers as id_users ,o.id_domain as id_domain,u.user_nicename')->cache(true,60)->select();
        return $selData;
    }

    /*
     * 拒签率统计
     * 额外统计字段 @param $extra_field
     */
    public function statistics_receipt_rate($extra_field = array()){
        //已签收状态 str
        $singed_status = OrderStatus::SIGNED;
        //所有已发货的状态 arr
        $all_delivered_status =  OrderStatus::get_delivered_status();

        $field = array(
            "COUNT(*) AS count_delivered",
            "SUM(IF(o.id_order_status ='{$singed_status}', 1, 0)) AS count_signed",
        );
        if(!empty($extra_field)){
            $field = array_merge($field, $extra_field);
        }

        if(isset($_GET['shipping_id'])&& $_GET['shipping_id']){
            $where[]= array('o.id_shipping'=>$_GET['shipping_id']);
        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            if ($_GET['start_time']) $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $createAtArray[] = array('LT', $_GET['end_time']);
            $where[]= array('o.date_delivery'=>$createAtArray);
        }else{//默认搜索当月发货订单
            $current_month_first_day = date('Y-m-d', strtotime(date('Y-m-1')));
            $where[]= array('o.date_delivery'=>array('EGT', $current_month_first_day));
        }
        $where[] = array('o.id_order_status'=>array('IN', $all_delivered_status));

        return $result_list = $this->where($where)->field($field)->select();
    }
}

