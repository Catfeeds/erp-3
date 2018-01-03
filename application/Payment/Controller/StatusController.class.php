<?php
/**
 * 订单统计
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */
namespace Payment\Controller;
use Common\Controller\AdminbaseController;
use Think\Event;
use Think\Hook;
use Order\Model\UpdateStatusModel;
use Order\Lib\OrderStatus;
class StatusController extends AdminbaseController{
	protected $order,$page;
	public function _initialize() {
		parent::_initialize();
		$this->order=D("Order/Order");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
	}

    /**
     * 未处理订单，初步审核订单后，订单进入待审核列表
     */
    public function update_status(){
        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        /** @var \Order\Model\UpdateStatusModel $update_status */
        $update_status = D('Order/UpdateStatus');
        $order_ids = is_array($_POST['order_id'])?$_POST['order_id']:array($_POST['order_id']);
        $action = $_POST['action'];
        $comment   =  $_POST['order_remark']?:'';
        $message = array();
        //验证邮编 及订单状态的可操作性
        $checkcnt=0;
        $zone_zipmust=M('zone')->getField('id_zone,zip_must');    
        $orderstatusArr=M("OrderStatus")->where(array('status'=>1))->getField('id_order_status,title');        
        foreach($order_ids as $id){
            $orderId = (int)$id;
            $orderinfo=M('order')->where(array('id_order'=>$orderId))->field('id_zone,id_increment,zipcode,id_order_status')->find();
            switch($action){
                case 1:case 2:
                    if(in_array($orderinfo['id_order_status'], array(1,3))){//只有待处理 和待审核的订单才结算到客服审单统计
                        $checkcnt++;
                    }
                    if($zone_zipmust[$orderinfo['id_zone']]&&empty($orderinfo['zipcode'])){
                        $return = array('status' => 0, 'message' => '该地区订单必须填写邮编！');
                        echo json_encode($return);exit();                
                    }                      
                    break;
                case 3:
                    if(in_array($orderinfo['id_order_status'], array(1,3))){//只有待处理 和待审核的订单才结算到客服审单统计
                        $checkcnt++;
                    }                    
                    if(!in_array($orderinfo['id_order_status'], array(1,3,22,4,6))){
                        $return = array('status' => 0, 'message' => "{$orderinfo['id_increment']},该订单状态为:{$orderstatusArr[$orderinfo['id_order_status']]},不能修改为无效订单！");
                        
                        echo json_encode($return);exit();                         
                    }
                    break;
                
            }            
        }        
        
        foreach($order_ids as $id){
            $orderId = (int)$id;
            switch($action){
                case 1:case 2:
                    //TODO:事件点--订单审核通过
                    $result = $model->approved($orderId,OrderStatus::VERIFICATION,$comment);//2为order_status 待发货状态ID
                    break;
                break;
                case 3:
                //TODO:事件点--订单取消
                $order  = D("Order/Order");
                $order_data = $order->find($orderId);
               //在配货中，货已经发发货的订单不能处理为为无效单(5,7,8,9,18,22) =>修改为已扣库存的订单不能处理为无效订单 （8,9,18）
                 if(in_array($order_data['id_order_status'],OrderStatus::get_canceled_to_rollback_status())){
                     $result = false;
                 }else{
                    $invalid_status = $action==2?10:$_POST['invalid_status'];
                    $update_data = array('id'=>$orderId,'status_id'=>$invalid_status,'comment'=>$comment.'操作:订单管理-未处理订单');
                    $result = UpdateStatusModel::cancel($update_data);
                    }
                break;
            }
            if(!$result){
                $message[]  = $id.'此订单状态不能执行此操作  ';
            }
        }
        $lastcheck=$_POST['is_lastcheck']==2?2:1;
        switch ($action){
            case 1:case 2:
                D('Order/OrderStatus')->check_order_statistics($lastcheck,$checkcnt,$checkcnt);//审核统计
                break;
            case 3:
                D('Order/OrderStatus')->check_order_statistics($lastcheck,$checkcnt);//审核统计
                break;
        }            
        $status = count($message) ? 0 : 1;
        $message = count($message) ? implode('   ', $message) : '修改成功';
        add_system_record(sp_get_current_admin_id(), 2, 4, '更新未处理TF订单');
        $return = array('status' => $status, 'message' => $message);
        echo json_encode($return);exit();
    }
    /**
     * 未处理订单
     */
    public function untreated(){
        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        /*创建日期初始化*/
        $created_at_array = array();
        if ($_GET['start_time'] or $_GET['end_time'])
        {
            if ($_GET['start_time'])
            {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ($_GET['end_time'])
            {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
        }
        else
        {
            if (!$_GET['start_time'] && !$_GET['end_time'])
            {
                $get_data['start_time'] = $_GET['start_time'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time'] = $_GET['end_time'] = date('Y-m-d H:i',time());
                $created_at_array[] = array('EGT', $get_data['start_time']);
                $created_at_array[] = array('LT', $get_data['end_time']);
            }
        }
        $set_where['created_at'] = $created_at_array;
        $this->assign("start_time",$get_data['start_time']);
        $this->assign("end_time",$get_data['end_time']);

        $set_where = array('id_order_status'=>1,'payment_method'=>array('NOT IN','0'));
        if(isset($_GET['payment_method']) && $_GET['payment_method']){
            $set_where['payment_method']= $_GET['payment_method'];
        }
        $data = $model->get_untreated_order($set_where,true); 
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $data['advertiser']);
        $this->assign("get_data", $_GET);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page",$data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data['order_total']);
        //$this->assign("todayWebData", $data['todayWebData']);
        $this->assign("order_list",$data['order_list']);
        $this->assign("shipping",$data['shipping']);
        $this->assign("all_product",$data['allProduct']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看未处理TF订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone", $data['all_zone']);
        $this->assign("payment_method", $data['payment_method']);
        $this->display();
    }

    /**
     * 待审核订单
     */
    public function unapproved(){
        $where = array('id_order_status'=>3,'payment_method'=>array('NOT IN','0'));//array('IN',array(2))
        if(isset($_GET['payment_method']) && $_GET['payment_method']){
            $where['payment_method']= $_GET['payment_method'];
        }

        /*创建日期初始化*/
        $created_at_array = array();
        if ($_GET['start_time'] or $_GET['end_time'])
        {
            if ($_GET['start_time'])
            {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ($_GET['end_time'])
            {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
        }
        else
        {
            if (!$_GET['start_time'] && !$_GET['end_time'])
            {
                $get_data['start_time'] = $_GET['start_time'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time'] = $_GET['end_time'] = date('Y-m-d H:i',time());
                $created_at_array[] = array('EGT', $get_data['start_time']);
                $created_at_array[] = array('LT', $get_data['end_time']);
            }
        }
        $where['created_at'] = $created_at_array;
        $this->assign("start_time",$get_data['start_time']);
        $this->assign("end_time",$get_data['end_time']);

        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        $data = $model->get_untreated_order($where,true);
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $data['advertiser']);
        $this->assign("get_data", $_GET);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page",$data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data['order_total']);
        $this->assign("today_web_data", $data['today_web_data']);
        $this->assign("order_list",$data['order_list']);
        $this->assign("shipping",$data['shipping']);
        $this->assign("all_product",$data['all_product']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看待审核TF订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone", $data['all_zone']);
        $this->assign("payment_method", $data['payment_method']);
        $this->display();
    }

    /**
     * 今日处理
     */
    public function today_process(){
//        $where = array('id_order_status'=>array('IN',array(3,4,10,11,12,13,14,15)),'payment_method'=>array('NOT IN','0'));
        $id_order_status = I('get.status_id');
        if ($id_order_status > 0) {
            $where['id_order_status'] = array('EQ',$id_order_status);
        } else {
            $where['id_order_status'] =  array('IN', OrderStatus::deal_order_status());
        }
        $where['payment_method'] = array('NOT IN','0');

        /*创建日期初始化*/
        $created_at_array = array();
        if ($_GET['start_time'] or $_GET['end_time'])
        {
            if ($_GET['start_time'])
            {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ($_GET['end_time'])
            {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
        }
        else
        {
            if (!$_GET['start_time'] && !$_GET['end_time'])
            {
                $get_data['start_time'] = $_GET['start_time'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time'] = $_GET['end_time'] = date('Y-m-d H:i',time());
                $created_at_array[] = array('EGT', $get_data['start_time']);
                $created_at_array[] = array('LT', $get_data['end_time']);
            }
        }
        $where['created_at'] = $created_at_array;
        $this->assign("start_time",$get_data['start_time']);
        $this->assign("end_time",$get_data['end_time']);

        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        $data = $model->get_untreated_order($where,true);
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $data['advertiser']);
        $this->assign("get_data", $_GET);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page",$data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data['order_total']);
        //$this->assign("todayWebData", $data['todayWebData']);
        $this->assign("order_list",$data['order_list']);
        $this->assign("shipping",$data['shipping']);
        $this->assign("all_product",$data['all_product']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看已处理TF订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone", $data['all_zone']);
        $this->assign("warehouse", $warehouse);
        $this->display();
    }
    /**
     * 无效订单
     */
    public function invalid(){
        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        $where = array();

        /*创建日期初始化*/
        $created_at_array = array();
        if ($_GET['start_time'] or $_GET['end_time'])
        {
            if ($_GET['start_time'])
            {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ($_GET['end_time'])
            {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
        }
        else
        {
            if (!$_GET['start_time'] && !$_GET['end_time'])
            {
                $get_data['start_time'] = $_GET['start_time'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time'] = $_GET['end_time'] = date('Y-m-d H:i',time());
                $created_at_array[] = array('EGT', $get_data['start_time']);
                $created_at_array[] = array('LT', $get_data['end_time']);
            }
        }
        $where['created_at'] = $created_at_array;
        $this->assign("start_time",$get_data['start_time']);
        $this->assign("end_time",$get_data['end_time']);

        $id_order_status = I('get.status_id');
        if ($id_order_status > 0) {
            $where['id_order_status'] = array('EQ', $id_order_status);
        } else {
            $where['id_order_status'] =  array('IN', array(10, 11, 12,13, 14, 15));
        }
        $where['payment_method'] = array('NOT IN','0');
        $result = $model->get_untreated_order($where,true);
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $all_product = D('Common/Product')->field('id_product,title')->order('id_product desc')->cache(true, 86400)->select();
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $result['advertiser']);
        $this->assign("get_data", $_GET);
        $this->assign("form_data", $result['form_data']);
        $this->assign("page", $result['page']);
        $this->assign("today_total", $result['today_total']);
        $this->assign("order_total", $result['order_total']);
        $this->assign("today_web_data", $result['today_web_data']);
        $this->assign("order_list", $result['order_list']);
        $this->assign("shipping", $result['shipping']);
        $this->assign("all_product", $all_product);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus')->where(array('id_order_status'=>array('IN',array(11, 12,13, 14, 15))))->select();
        $status_model = array_column($status_model, 'title', 'id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看无效TF订单');
        $this->assign('status_list',$status_model);
        $this->assign("all_zone", $result['all_zone']);
        $this->display();
    }

    /**
     * 待审核订单
     */
    public function pending(){
        $where = array('id_order_status'=>3,'payment_method'=>array('NOT IN','0'));
        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        $data= $model->get_untreated_order($where,true);
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $data['advertiser']);
        $this->assign("get_data", $_GET);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page",$data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data['order_total']);
        $this->assign("today_web_data", $data['today_web_data']);
        $this->assign("order_list",$data['order_list']);
        $this->assign("shipping",$data['shipping']);
        $this->assign("all_product",$data['all_product']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看待审核TF订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone", $data['all_zone']);
        $this->display();
    }

    /**
     * AJXA提交通过审核订单
     */
    public function approved(){
        /* @var $order \Common\Model\OrderModel*/
        $order  = D("Order/Order");
        $order_ids   = is_array($_POST['order_id'])?$_POST['order_id']:array($_POST['order_id']);
        $comment   =  $_POST['order_remark'];
        //$shippingIds = $_POST['shippingIds'];//订单审核时， 现在要求去掉物流
        foreach($order_ids as $id){
            $orderId = (int)$id;
            $order_quantity=M("OrderItem")->where(array('id_order'=>$orderId))->getField('quantity',true);
            $orderinfo=M('order')->where(array('id_order'=>$orderId))->field('id_zone,zipcode,id_order_status,id_increment')->find();
            if(in_array(0,$order_quantity)||  in_array(NULL, $order_quantity)){
                $return = array('status' => 0, 'message' => "{$orderinfo['id_increment']}  订单号,商品销售数量不能为空或者0！");
                echo json_encode($return);exit();  
            }            
        }        
        try{
            foreach($order_ids as $id){
                $order_id = (int)$id;
                $status_id = OrderStatus::UNPICKING;             //未配货状态
                $update_data = array(
                    'id_order'=>$order_id,
                    'status_id'=>$status_id,
                    'comment'=>$comment
                );
                UpdateStatusModel::approveds($update_data);
                //$order->where('id='.$orderId)->save($updateData);
                //D("Order/OrderRecord")->addHistory($order_id,$status_id,4,$comment);
            }
            $status = 1;$message = '操作成功';
        }catch (\Exception $e){
            $message = $e->getMessage();
            $status = 0;
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '更新待审核TF订单');
        $return  = array('status'=>$status,'message'=>$message);
        echo json_encode($return);exit();
    }
    
    public function status_statistics(){
        if(isset($_GET['shipping_id']) && $_GET['shipping_id']){
            $where[]= array('o.id_shipping'=>$_GET['shipping_id']);
        }
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }

        /*创建日期初始化*/
        $created_at_array = array();
        if ($_GET['start_time'] or $_GET['end_time'])
        {
            if ($_GET['start_time'])
            {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ($_GET['end_time'])
            {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
        }
        else
        {
            if (!$_GET['start_time'] && !$_GET['end_time'])
            {
                $get_data['start_time'] = $_GET['start_time'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time'] = $_GET['end_time'] = date('Y-m-d H:i',time());
                $created_at_array[] = array('EGT', $get_data['start_time']);
                $created_at_array[] = array('LT', $get_data['end_time']);
            }
        }
        $where[ ] = array('date_purchase'=> $created_at_array);
        $this->assign("start_time",$get_data['start_time']);
        $this->assign("end_time",$get_data['end_time']);

//        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
//            $createAtArray = array();
//            if ($_GET['start_time']) $createAtArray[] = array('EGT', $_GET['start_time']);
//            if ($_GET['end_time']) $createAtArray[] = array('LT', $_GET['end_time']);
//            $where[]= array('date_purchase'=>$createAtArray);
//        }
        $where[] = array('os.track_number !=""');
        $where[] = array('o.payment_method != 0');

        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        $M = new \Think\Model;
        $ordName = $ordModel->getTableName();
        /** @var  $ordShipping \Common\Model\OrderShippingModel */
        $ordShipping = D("Order/OrderShipping");
        $ordShiName = $ordShipping->getTableName();
        $statusList = $ordShipping->group('summary_status_label')->select();
        $tempStatus = array();
        $setStaList = array();
        foreach($statusList as $key=>$status){
            $tempStatus[] = "SUM(IF(os.`summary_status_label`='".$status['summary_status_label']."',1,0)) AS status".$key;
            $setStaList['status'.$key] = !empty($status['summary_status_label']) ? $status['summary_status_label'] : '无信息';
        }
        $tempStatus = count($tempStatus)?','.implode(',',$tempStatus):'';
        $fieldStr   = "SUBSTRING(o.created_at,1,10) AS set_date,count(os.id_order) as count_all".$tempStatus;

        $count = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->select();

        $page = $this->page(count($count), 20);
        $selectOrder = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $shipping = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();
        $shipItem = array();
        if($shipping){
            foreach($shipping as $item){
                $shipItem[$item['id_shipping']] = $item['title'];
            }
        }
        $department_id  = $_SESSION['department_id'];
        $department = D('Common/Department')->where('type=1')->cache(true,6000)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看物流状态统计');
        $this->assign("department_id", $department_id);
        $this->assign('department',$department);
        $this->assign("shipping",$shipItem);
        $this->assign("list",$selectOrder);
        $this->assign("status_list",$setStaList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /**
     * 支付通道预付款，先写在里面，后期优化
     */
    public function authorizing(){
        $id = I('get.id');
        $order = D("Order/Order")->find($id);
        if($order){
            try{
                $domain =  D("Common/domain")->find($order['id_domain']);
                $config = array(
                    //'getway' => 'https://api.yiji.com/gateway.html',
                    //https://openapi.yijifu.net/gateway.html
                    'getway' => 'https://openapiglobal.yiji.com/gateway.html',
                    'protocol' => 'httpPost',
                    'version' => '1.0',
                    'signType' => 'MD5',
                    'returnUrl' => $domain['name'].'/yjf/return_url.php',
                    'notifyUrl' => $domain['name'].'/yjf/notify_url.php',
                    'service' => 'espOrderPay',
                    'ckey' => '7f0cfc1c46e1786da239117d28a61c9a',
                    'partnerId' => '20161130020011940297',
                    'userId' => '20161130020011940297',
                );

                $ckey = $config['ckey'];
                $isAccept = 'true';
                $payment_order_no = $order['payment_order_no']?$order['payment_order_no'].rand(0,99):'ST'.date('YmdHis').'O'.$order['id_order'].rand(0,99);
                $payment_merch_order_no = $order['payment_merch_order_no']?:'ST'.$order['id_increment'];
                $auth_data = array(
                    'version' => $config['version'],
                    'protocol' => $config['protocol'],
                    'service' => 'espOrderJudgment',
                    'notifyUrl' => $config['notifyUrl'],
                    'returnUrl' => $config['returnUrl'],
                    'signType' => $config['signType'],
                    'partnerId' => $config['partnerId'],
                    //'userId' => $payment['partnerId'],
                    'orderNo' => $payment_order_no,
                    'merchOrderNo' => $payment_merch_order_no,
                    'resolveReason' => '接收交易',
                    'isAccept' => $isAccept,
                );
                ksort($auth_data);
                $signSrc = "";
                foreach($auth_data as $k=>$v) {
                    if(empty($v)||$v==="")
                        unset($auth_data[$k]);
                    else
                        $signSrc.= $k.'='.$v.'&';
                }
                $signSrc = trim($signSrc, '&').$ckey;
                if($auth_data['signType']==="MD5")
                    $auth_data['sign'] = md5($signSrc);

                $getway = $config['getway'];
                $ssl = substr($getway, 0, 8) == "https://" ? TRUE : FALSE;
                $result = send_curl_request($getway,$auth_data,$ssl);
                if($result){
                    $arr = json_decode($result, true);
                    // if($arr['resultCode']=='EXECUTE_SUCCESS'){
                    $merchOrderNo = $arr['merchOrderNo']; //ERP订单号
                    $orderNo = $arr['orderNo']; //旧后台订单号
                    $desc = $resultMessage = $arr['resultMessage'];
                    $status = strtolower($arr['status']);
                    if($arr['status']=='success'){
                        if($isAccept=='true'){
                            //接受
                        }else{
                            //取消操作
                        }
                    }else{

                    }
                }else{
                    $status = 'fail';
                    $desc   = 'Authorization failure!';
                }
            }catch (\Exception $e){
                $status = 'fail';
                $desc   = '预授权失败：'.$e->getMessage();
            }

            $data = array(
                'notify' => 'notify',
                'payment_order_no' => $order['payment_order_no']?:$orderNo,
                'payment_merch_order_no' => $order['payment_merch_order_no']?:$merchOrderNo,
                'payment_status' => $status,
                'payment_details' => sprintf('%s {%s, %s}', $desc, $orderNo, $merchOrderNo)
            );
            D("Order/Order")->where(array('id_order' => $id))->save($data);
        }
        //print_r($arr);
        //print_r($auth_data);
        //print_r($data);
        echo print_r(array('status'=>1,'message'=>$desc));
        exit();
    }
}
