<?php
/**
 * 订单统计
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */
namespace Order\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use Order\Model\ApiModel;
use Think\Event;
use Think\Hook;
use Order\Model\UpdateStatusModel;
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
        //地区和邮编是否必须
        $zone_zipmust=M('zone')->getField('id_zone,zip_must');
        $orderstatusArr=M("OrderStatus")->where(array('status'=>1))->getField('id_order_status,title');
        $bef_order_status = M("Order")->where("id_order in(".implode(",",$order_ids).")")->field("id_order_status")
                          ->select();
        $checkcnt=0;
        //是否为问题件  --lily 2017-11-21
        
        //验证邮编 及订单状态的可操作性
        foreach($order_ids as $id){
            $orderId = (int)$id;
            $orderinfo=M('order')->where(array('id_order'=>$orderId))->field('id_zone,zipcode,id_order_status,id_increment')->find();
            $order_quantity=M("OrderItem")->where(array('id_order'=>$orderId))->getField('quantity',true);
            switch($action){
                case 1:case 2:
                    if(in_array($orderinfo['id_order_status'], array(1,3))){//只有待处理 和待审核的订单才结算到客服审单统计
                        $checkcnt++;
                    }
                    if(in_array(0,$order_quantity)||  in_array(NULL, $order_quantity)){
                        $return = array('status' => 0, 'message' => "{$orderinfo['id_increment']}  订单号,商品销售数量不能为空或者0！");
                        echo json_encode($return);exit();
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
                        $return = array('status' => 0, 'message' => "该订单状态为:{$orderstatusArr[$orderinfo['id_order_status']]},不能修改为无效订单！");
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
                /* @var $order \Common\Model\OrderModel*/
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
                $message[]  = '此订单状态不能执行此操作。'.$id;
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
        if($status == 1){
            if($action==1){
                $id_order_status = 1;
            }else if($action==3){
                $id_order_status = $_POST['invalid_status'];
            }
            if($_POST['is_question_order'] ==1){
            $order_list = M("Order")->where("id_order in(".implode(",",$order_ids).")")
                          ->select();
            $order_list[0]['id_order_status'] = $bef_order_status[0]['id_order_status'];
            $order_list['product'] = D('Order/OrderItem')->get_item_list($order_list[0]['id_order']);
            $dataP['question_order_before'] = json_encode($order_list);
            $dataP['id_department'] = $order_list[0]['id_department'];
            $order_list['comment'] = $comment;
            $order_list[0]['id_order_status'] = $id_order_status;
            $dataP['question_order_after'] = json_encode($order_list);
            $dataP['id_user'] = $_SESSION['ADMIN_ID'];
            $dataP['create_time'] = date("Y-m-d H:i:s");
            M("OrderQuestion")->add($dataP,array(),true);
        }
        }
        $message = count($message) ? implode('   ', $message) : '修改成功';
        add_system_record(sp_get_current_admin_id(), 2, 4, '更新未处理DF订单');
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
            $set_where['created_at'] = $created_at_array;
        }
        //訂單状态
        $id_order_status = I('get.status_id');
        if ($id_order_status) {
            $set_where['id_order_status'] = array('EQ',$id_order_status);
        } else {
            $set_where = array('id_order_status'=>1);
        }
        //状态
        //$set_where = array('id_order_status'=>1);
        $data = $model->get_untreated_order($set_where);
        if ($data['order_list'])
        {
            foreach ($data['order_list'] as $key => $val )
            {
                $data['order_list'][$key]['erg_infos'] = ApiModel::abnormal_information($val['id_order']);
                $data['order_list'][$key]['web_infos'] = !empty($val['web_info'])?unserialize(htmlspecialchars_decode($val['web_info'])):'';
                $data['order_list'][$key]['tel_email_short'] = substr($val['tel'],-6,6). "/" . substr($val['email'],0,5);
                $data['order_list'][$key]['tel_email_all'] = $val['tel']. "</br>" .$val['email'];
                $data['order_list'][$key]['http_referer'] = !empty($val['http_referer']) ? $val['http_referer'] : '--';
                $st = date('Y-m-d '."00:00:00");
                $et = date('Y-m-d '."23:59:59");
                $data['order_list'][$key]['is_red'] = D("Order/Order")->where(["created_at"=>['between',[$st,$et]],'first_name'=>$val['first_name'],'tel'=>$val['tel']])->order('id_order ASC')->select(); //查询当天名字和电话一样的订单
                // $data['order_list'][$key]['cur_num'] = array_keys($data['order_list'][$key]['is_red'],$val['id_order']); dump($end[$key]);
                $data['order_list'][$key]['amo_num'] = count($data['order_list'][$key]['is_red'])-1;
                //$data['order_list'][$key]['cur_num']=0;
                foreach ($data['order_list'][$key]['is_red'] as $k => $value) {
                    if($value['id_order']==$val['id_order']){
                         $data['order_list'][$key]['cur_num'] = $k;
                    }
                }
                if(($data['order_list'][$key]['is_red']!=='') && $data['order_list'][$key]['amo_num']!==0 && ($data['order_list'][$key]['cur_num']==$data['order_list'][$key]['amo_num'])  ){
                    $data['order_list'][$key]['be_red']=1;
                }
                    }

        }
       // dump($data['order_list']);
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
        $this->assign("name_replace",$data['name_replace']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看未处理DF订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone", $data['all_zone']);
        $this->display();
    }

    /**
     * 待审核订单
     */
    public function unapproved(){
        $where = array('id_order_status'=>3);//array('IN',array(2))
        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        $data = $model->get_untreated_order($where);
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
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看待审核DF订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone", $data['all_zone']);
        $this->display();
    }

    /**
     * 今日处理
     */
    public function today_process(){
//        ini_set("memory_limit","-1");
        $where = array();
        $id_order_status = I('get.status_id');
        if ($id_order_status > 0) {
            $where['id_order_status'] = array('EQ',$id_order_status);
        } else {
            $where['id_order_status'] =  array('IN', OrderStatus::deal_order_status());
        }
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
            $where['created_at'] = $created_at_array;
        }
        $data = $model->get_untreated_order($where);
        if ($data['order_list'])
        {
            foreach ($data['order_list'] as $key => $val )
            {
                $data['order_list'][$key]['tel_email_short'] = substr($val['tel'], -6, 6) . "/" . substr($val['email'], 0, 5);
                $data['order_list'][$key]['tel_email_all'] = $val['tel'] . "</br>" . $val['email'];
                $data['order_list'][$key]['http_referer'] = !empty($val['http_referer']) ? $val['http_referer'] : '--';
            }
        }
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
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看已处理DF订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone", $data['all_zone']);
        $this->assign("warehouse", $warehouse);
        $this->display();
    }
    /**
     * 无效订单
     */
    public function invalid(){
        ini_set("memory_limit","-1");
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
            $where['created_at'] = $created_at_array;
        }
        $id_order_status = I('get.status_id');
        if ($id_order_status > 0) {
            $where['id_order_status'] = array('EQ', $id_order_status);
        } else {
            $where['id_order_status'] =  array('IN', array(11, 12,13, 14, 15,28,29,30));
        }
        $result = $model->get_untreated_order($where);
        if ($result['order_list'])
        {
            foreach ($result['order_list'] as $key => $val )
            {
                $result['order_list'][$key]['http_referer'] = !empty($val['http_referer']) ? $val['http_referer'] : '--';
            }
        }
        $department_id  = $_SESSION['department_id'];
        $all_product = D('Common/Product')->field('id_product,title')->order('id_product desc')->cache(true, 86400)->select();
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
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
        $status_model = D('Order/OrderStatus')->where(array('id_order_status'=>array('IN',array(11, 12,13, 14, 15,28,29,30))))->select();
        $status_model = array_column($status_model, 'title', 'id_order_status');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看无效DF订单');
        $this->assign('status_list',$status_model);
        $this->assign("all_zone", $result['all_zone']);
        $this->display();
    }

    /**
     * 待审核订单
     */
    public function pending(){
        $where = array('id_order_status'=>3);
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
            $where['created_at'] = $created_at_array;
        }

        $model = D('Order/OrderStatus');
        $data= $model->get_untreated_order($where);
        if ($data['order_list'])
        {
            foreach ($data['order_list'] as $key => $val )
            {
                $data['order_list'][$key]['tel_email_short'] = substr($val['tel'],-6,6). "/" . substr($val['email'],0,5);
                $data['order_list'][$key]['tel_email_all'] = $val['tel']. "</br>" .$val['email'];
                $data['order_list'][$key]['http_referer'] = !empty($val['http_referer']) ? $val['http_referer'] : '--';
            }
        }
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
        $this->assign("sku_num",$data['sku_num']);
        $this->assign("name_replace",$data['name_replace']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看待审核DF订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone", $data['all_zone']);
        $this->assign("id_order", $data['id_order_da']); //所有待处理订单的 id_order  为一键审核提供订单ID
        $this->display();
    }

    /*
     * 导出未处理或者待审核订单
     */
    public function export_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $column = array(
            '地区', '广告专员','域名', '订单号', '姓名',
            '产品名和价格','sku和数量', '待审核总数','总价（NTS）', '属性',
            '送货地址','邮编' ,'购买产品数量', '留言备注', '下单时间', '订单状态'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
//        dump($_GET);die;
        $where = $this->order->form_where($_GET);
        $where['id_order_status']=$_GET['id_order_status'];
        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";//货到付款订单，过滤已经支付的
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }

        //ip筛选
        if(isset($_GET['ip']) && !empty($_GET['ip'])){
            $find_order = D('Order/OrderInfo')->field('id_order')->where(array('ip'=> trim($_GET['ip'])))->select();
            if(!empty($find_order)){
                $where['id_order'] = array('IN', array_column($find_order, 'id_order'));
            }else{
                $where['id_order'] = 0;
            }
        }
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id'=>$_SESSION['ADMIN_ID'],'role_id'=>32))->find();
        if($role_user) {
            $belong_zone_id = isset($_SESSION['belong_zone_id'])?$_SESSION['belong_zone_id']:array(0);
            if(!isset($where['id_zone'])){
                $where['id_zone']=array('IN',$belong_zone_id);
            }
        }
        $orders = $this->order
            ->where($where)
            ->order("id_order ASC")
            ->limit(1000)->select();

        //统计sku所有订单
        $order_item = D('Order/OrderItem');
        $lists = $orders;
        $arr = array();
        foreach ($lists as $key => $o) {
            $lists[$key]['products'] = $order_item->get_item_list($o['id_order'],10);
        }
        foreach($lists as $list){
            foreach($list['products'] as $v){
                array_push($arr,$v['sku']);
            }
        }
        $new = array_count_values($arr);
        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();


        foreach ($orders as $o) {
            $sku = '';
            $product_name = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            $temp_is_exit = '';
            foreach ($products as $p) {
                $sku.=$p['sku'].'  待审核总数:'.$new[$p['sku']].'  ';
                $parameter = array('id_zone'=>$o['id_zone'],'sku'=>$p['sku'],'product_title'=>$p['product_title']);
                $get_is_exit = D('Warehouse/Warehouse')->is_exist_warehouse($p['id_product'],$p['id_product_sku'],$parameter);
                $get_is_exit = str_replace("</br>","      ",$get_is_exit);
                if($get_is_exit){
                    $temp_is_exit .= $get_is_exit;
                }
                $product_name .= $p['product_title'] . "\n";
                if($p['sku_title']) {
                    $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                } else {
                    $attrs .= $p['product_title']. ' x ' . $p['quantity'] . ",";
                }
                $product_count +=$p['quantity'];
            }
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $user_name = M('Users')->where(array('id'=>$o['id_users']))->getField('user_nicename');
            $domain_title = D('Domain/Domain')->where(array('id_domain'=>$o['id_domain']))->getField('name');
            $data = array(
                $all_zone[$o['id_zone']], $user_name, $domain_title, $o['id_increment'], $o['first_name'].' '.$o['last_name'],
                $product_name,$temp_is_exit,$sku, $o['price_total'], $attrs,
                $o['province'].$o['city'].$o['area'].$o['address'],$o['zipcode'] ,$product_count, $o['remark'], $o['created_at'], $status_name
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                if($key != 8 && $key != 11) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                } else {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }


    /**
     * AJXA提交通过审核订单*
     */
    public function approved(){
        /* @var $order \Common\Model\OrderModel*/
        $return = array(

            'status'=>1,
            'message'=>''
        );
        //判断是否有action 传过来，如果有则为一键审核  --Lily 2017-11-09
        if(isset($_POST['action']) && $_POST['action']){
            $order_model = D("Order/Order");
            $where['id_order_status'] = 3;
            $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";
            $order_list = $order_model->where($where)->order("date_purchase asc")->limit(100)->select();
            $order_ids = array_column($order_list,"id_order");
            //判断是否有待审核的订单   --Lily  2017-11-09
            if(empty($order_ids)){
                $return = array(
            'status'=>0,
            'message'=>'没有待审核的订单'
        );
                echo json_encode($return);exit();
            }
        }else{
            $order  = D("Common/Order");
            $order_ids   = is_array($_POST['order_id'])?$_POST['order_id']:array($_POST['order_id']);
            $comment   =  $_POST['order_remark'];
        }
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
                $order = M('Order')->where(array('id_order'=>$order_id))->find();
                if($order['id_order_status'] != OrderStatus::VERIFICATION) continue; //如果不是待审核状态就直接跳过
                //如果不是越南订单，先进行转寄仓的匹配 id_zone 为9的是越南订单
                if ($order['id_zone'] != 9)
                {
                    $res_one = UpdateStatusModel::match_forward_order($order_id);
                    if ($res_one['flag'])
                    {
                        UpdateStatusModel::into_forward_order($order_id,$res_one['data']);
                    }
                    else
                    {
                        $status_id = OrderStatus::UNPICKING; //未配货状态
                        $update_data = array(
                            'id_order'=>$order_id,
                            'status_id'=>$status_id,
                            'comment'=>!empty($_POST['order_remark'])?$comment:$order['comment']
                        );
                        UpdateStatusModel::approveds($update_data);
                    }
                }
                else
                {
                    $status_id = OrderStatus::UNPICKING; //未配货状态
                    $update_data = array(
                        'id_order'=>$order_id,
                        'status_id'=>$status_id,
                        'comment'=>!empty($_POST['order_remark'])?$comment:$order['comment']
                    );
                    UpdateStatusModel::approveds($update_data);
                }
            }
            D('Order/OrderStatus')->check_order_statistics(2,count($order_ids),count($order_ids));//审核统计
            if($return['status'] == 1)
                $return['message'] = '操作成功';
        }catch (\Exception $e){
            $message = $e->getMessage();
            $status = 0;
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '更新待审核DF订单');
        echo json_encode($return);exit();
    }

/**
* AJAX 提交  一键审核 所有待审核订单
*/
public function checkAll(){
    $return =array(
        'status'=>1,
        'message'=>''
        );
    $id_order = mb_substr($_POST['id_order'],1);
    $order  = D("Common/Order");
    foreach($id_order as $id){
            $orderId = (int)$id;
            $order_quantity=M("OrderItem")->where(array('id_order'=>$orderId))->getField('quantity',true);
            $orderinfo=M('order')->where(array('id_order'=>$orderId))->field('id_zone,zipcode,id_order_status,id_increment')->find();
            if(in_array(0,$order_quantity)||  in_array(NULL, $order_quantity)){
                $return = array('status' => 0, 'message' => "{$orderinfo['id_increment']}  订单号,商品销售数量不能为空或者0！");
                echo json_encode($return);exit();
            }
        }
        try{
            foreach($id_order as $id){
                $order_id = (int)$id;
                $order = M('Order')->where(array('id_order'=>$order_id))->find();
                if($order['id_order_status'] != OrderStatus::VERIFICATION) continue; //如果不是待审核状态就直接跳过
                    $status_id = OrderStatus::UNPICKING; //未配货状态
                    $update_data = array(
                        'id_order'=>$order_id,
                        'status_id'=>$status_id,
                      );
                    UpdateStatusModel::approveds($update_data);
           }
            D('Order/OrderStatus')->check_order_statistics(2,count($id_order),count($id_order));//审核统计
            if($return['status'] == 1)
                $return['message'] = '操作成功';
        }catch (\Exception $e){
            $message = $e->getMessage();
            $status = 0;
        }
    
    add_system_record(sp_get_current_admin_id(), 2, 4, '一键审核所有待审核订单');
    // add_system_record(sp_get_current_admin_id(), 2, 4, '一键审核所有待审核订单');
    echo json_encode($return);exit();
}

    /*
     * 临时更新状态
     */
    public function update_demo() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        $ord = D('Order/Order');
        if (IS_POST) {
            $data = I('post.data');
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 1);
                $order = $ord->where(array(
                    'id_increment' => trim($row[0])
                    ))
                    ->find();

                if($order  && $order['id_order']){
                    $order_id = $order['id_order'];
                    D("Order/Order")->where(array(
                    'id_order' => $order_id
                    ))->save(array('id_order_status' => 4));
                    $infor['success'][] = sprintf('第%s行: 订单号:%s 更新状态: %s', $count++, $row[0],'未配货');
                }else{
                    $infor['error'][] = sprintf('第%s行: 没有找到订单', $count++);
                }
            }
        }
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 仓库没有SKU 记录的 订单列表
     */
    public function warehouse_empty_log(){
        $M = new \Think\Model;
        $ord_name = D("Common/Order")->getTableName();
        $ord_ite_name = D("Common/OrderItem")->getTableName();
        $ware_pro_name = D("Common/WarehouseProduct")->getTableName();
        $product_where = array(
            '_string' => '(id_warehouse_product IS NULL or ps.status=0) and o.id_order_status=3',
        );
        $order_list = $M->table($ord_name.' AS o LEFT JOIN '.$ord_ite_name.' AS oi ON o.id_order=oi.id_order
            LEFT JOIN __PRODUCT_SKU__ as ps on oi.id_product_sku=ps.id_product_sku
            LEFT JOIN '.
            $ware_pro_name.' as wp ON wp.`id_product_sku`=oi.`id_product_sku`')->field('o.*')
            ->where($product_where)
            ->group('oi.id_order')->select();
        /** @var \Order\Model\OrderBlacklistModel $order_blacklist */
        $order_blacklist = D("Order/OrderBlacklist");
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key]  =  $order_blacklist->black_list_and_ip_address($o);
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order'],10);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
        }
        $this->assign("order_list",$order_list);
        $this->display();
    }

    /**
     *  已审核列表  只有香港DF订单有该状态
     *  显示所有有效订单：
     *  未配货、配货中、缺货、已配货、配送中、已签收、已退货、已打包、已审核、问题件
     */
    public function approved_list(){
        $where = array(
            'id_order_status'=>['IN',OrderStatus::get_audited_order()]);
        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        $data= $model->get_untreated_order($where);
        if ($data['order_list'])
        {
            foreach ($data['order_list'] as $key => $val )
            {
                $data['order_list'][$key]['http_referer'] = !empty($val['http_referer']) ? $val['http_referer'] : '--';
            }
        }
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
        $this->assign("sku_num",$data['sku_num']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看已审核DF订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone", $data['all_zone']);
        $this->assign("id_order_status",OrderStatus::APPROVED);
        $this->display();
    }

    /**
     * 已审核订单确认
     */
    public function approved_confirm(){
        /* @var $order \Common\Model\OrderModel*/
        $order  = D("Common/Order");
        $order_ids = I('request.order_id');
        if($order_ids === 'all'){
            $order_ids = M('Order')
                ->where(array('id_order_status'=>\Order\Lib\OrderStatus::APPROVED))
                ->select();
            if(!empty($order_ids)){
                $order_ids = array_column($order_ids, 'id_order');
            }else{
                echo json_encode(array('status'=>0, 'message'=>'当前没有已审核订单'));exit();
            }
        }

        $comment   =  $_POST['order_remark'];
        try{
            foreach($order_ids as $id){
                $order_id = (int)$id;
                $status_id = \Order\Lib\OrderStatus::UNPICKING;
                $update_data = array(
                    'id_order_status'=>$status_id,
                );
                //直接设置订单状态为未配货
                $order->where(array('id_order'=>$order_id))->save($update_data);
                $order_record = D("Order/OrderRecord");
                $order_record->addHistory($order_id, $status_id, 4, $comment);
            }
            $status = 1;$message = '操作成功';
        }catch (\Exception $e){
            $message = $e->getMessage();
            $status = 0;
        }
        add_system_record(sp_get_current_admin_id(), 2, 4, '更新已审核DF订单');
        $return  = array('status'=>$status,'message'=>$message);
        echo json_encode($return);exit();
    }

    /**
     * 更新确认状态
     * confirmation_status
     * 1:未联系;2:约时间中;3:确定时间;
     */
    public function update_confirmation_status(){
        $confirmation_status = I('confirmation_status');
        $id_order = I('order_id');
        if(empty($id_order)){
            echo json_encode(array('status'=>'fail','message'=>'参数错误'));exit();
        }else{
            $OrderModel = D('Common/Order');
            if(is_array($id_order)){
                $OrderModel->where(array('id_order'=>array('IN', $id_order)));
            }else{
                $OrderModel->where(array('id_order'=>array('EQ', $id_order)));
            }
            D('Common/Order')->setField(array('confirmation_status'=>$confirmation_status));
        }
        echo json_encode(array('status'=>1,'message'=>'更新成功'));exit();
    }

    /*
     * 导出有效订单或者无效订单
     */
    public function export_audited_search(){
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        //无效单 导出excel 添加电话列     liuruibin 20171013
        $column = array(
            '订单号', '部门', '广告专员','域名', '地区', '订单状态','姓名','来源','总价（NTS）',
            '产品名',  '属性','电话',
            '送货地址','邮编' ,'购买产品数量', '留言信息', '备注信息', '下单时间', '重复数'
        );
        $j = 65;
        foreach($column as $col){
            $excel->getActiveSheet()->setCellValue(chr($j).'1',$col);
            ++$j;
        }
        $where = $this->order->form_where($_GET);
        if($_GET['status_id']==OrderStatus::APPROVED){
            $where['id_order_status']=array('IN',OrderStatus::get_audited_order());
        }else if($_GET['status_id']==0){
            $where['id_order_status']=array('IN', array(11, 12,13, 14, 15));//筛选无效订单
        }else{
            $where['id_order_status']=$_GET['status_id'];
        }
        /*dump($where['id_order_status']);die;*/
        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";//货到付款订单，过滤已经支付的
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }

        //ip筛选
        if(isset($_GET['ip']) && !empty($_GET['ip'])){
            $find_order = D('Order/OrderInfo')->field('id_order')->where(array('ip'=> trim($_GET['ip'])))->select();
            if(!empty($find_order)){
                $where['id_order'] = array('IN', array_column($find_order, 'id_order'));
            }else{
                $where['id_order'] = 0;
            }
        }
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id'=>$_SESSION['ADMIN_ID'],'role_id'=>32))->find();
        if($role_user) {
            $belong_zone_id = isset($_SESSION['belong_zone_id'])?$_SESSION['belong_zone_id']:array(0);
            if(!isset($where['id_zone'])){
                $where['id_zone']=array('IN',$belong_zone_id);
            }
        }
        $orders = $this->order
            ->where($where)
            ->order("id_order ASC")
            ->limit(1000)->select();

        //统计sku所有订单
        $order_item = D('Order/OrderItem');
        $lists = $orders;
        $arr = array();
        foreach ($lists as $key => $o) {
            $lists[$key]['products'] = $order_item->get_item_list($o['id_order'],10);
        }
        foreach($lists as $list){
            foreach($list['products'] as $v){
                array_push($arr,$v['sku']);
            }
        }
        $new = array_count_values($arr);
        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();


        foreach ($orders as $o) {
            $sku = '';
            $product_name = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            $temp_is_exit = '';
            foreach ($products as $p) {
                $sku.=$p['sku'].'  待审核总数:'.$new[$p['sku']].'  ';
                $parameter = array('id_zone'=>$o['id_zone'],'sku'=>$p['sku'],'product_title'=>$p['product_title']);
                $get_is_exit = D('Warehouse/Warehouse')->is_exist_warehouse($p['id_product'],$p['id_product_sku'],$parameter);
                $get_is_exit = str_replace("</br>","      ",$get_is_exit);
                if($get_is_exit){
                    $temp_is_exit .= $get_is_exit;
                }
                $product_name .= $p['product_title'] . "\n";
                if($p['sku_title']) {
                    $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                } else {
                    $attrs .= $p['product_title']. ' x ' . $p['quantity'] . ",";
                }
                $product_count +=$p['quantity'];
            }
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $user_name = M('Users')->where(array('id'=>$o['id_users']))->getField('user_nicename');
            $department_title = M('department')->where(array('id_department' => $o['id_department']))->getField('title');
            $domain_title = D('Domain/Domain')->where(array('id_domain'=>$o['id_domain']))->getField('name');
            //无效单 导出excel 添加电话列$o['tel']     liuruibin 20171013
            $data = array(
                $o['id_increment'], $department_title, $user_name, $domain_title, $all_zone[$o['id_zone']], $status_name, $o['first_name'].' '.$o['last_name'],$o['http_referer'], $o['price_total'],
                $product_name, $attrs,$o['tel'],
                $o['province'].$o['city'].$o['area'].$o['address'],$o['zipcode'] ,$product_count, $o['remark'], $o['comment'], $o['created_at'], $o['order_repeat']
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                if($key != 8 && $key != 11) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j).$idx, $col);
                } else {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    //未配货,配货中,已配货,已打包等订单状态,
    function update_order_cancel_status(){
        //更新为无效订单状态
        $order_status = array(
            '11'=>'重复下单',
            '12'=>'信息不完整',
            '13'=>'恶意下单',
            '14'=>'客户取消',
            '29'=>'没货取消',      
            '30'=>'隐藏订单',                        
        );   

        if (IS_POST) {
            $data = I('post.data');
            $path = write_file('order', 'order_status_update', $data);
            $data = $this->getDataRow($data);
            
            //查看订单状态是否在允许的范围之类 
            $all_order_data = M()->table("erp_order")->where(" id_increment in ('".implode("','",$data)."')")->select();
            $count = 1;
            foreach ($all_order_data as $key=>$row) {
                $msg = $select_order ? '订单号' : '运单号';
                //更新订单状态
                $res = D('Order/Order')->where(array('id_increment'=>$row['id_increment']))->save(array('id_order_status'=>$_POST['status_id']));
                if(in_array($row['id_order_status'],array(4,5,6,7)) && $res){
                    //已打包,将减了的库存增加回去,
                    if($row['id_order_status'] == 7){
                        //库存回滚
                        UpdateStatusModel::inventory_rollback($row['id_order']);
                    }else{
                        //未配货,配货中,已配货 ,将在单量给退回去对应的产品
                        //在单回滚,
                        UpdateStatusModel::qty_pre_out_rollback($row['id_order']);
                        //清除波次单
                        D('Common/OrderWave')->where(array('id_order'=>$order_id))->delete();
                    }
                    $comment = '更新订单状态未'.$order_status[$_POST['status_id']];
                    D("Order/OrderRecord")->addHistory($row['id_order'],$_POST['status_id'],4, $comment);
                    $info['success'][] = sprintf('第%s行订单号'.$row['id_increment'].': 更新成功', $count++);  
                }else{
                    $info['error'][] = sprintf('第%s行: '.$msg.':%s  订单状态不是未配货，配货中和已配货状态，不能进行缺货操作', $count++, $row[0]);    
                }
            }
        }

        $this->assign('infor', $info);
        $this->assign('order_status',$order_status);
        $this->display();
    }


}
