<?php

/**
 * 问题件处理
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */

namespace order\Controller;

use Common\Controller\AdminbaseController;
use Think\Event;
use Think\Hook;
use Order\Model\UpdateStatusModel;
use Order\Lib\OrderStatus;

class ProblemShipmentController extends AdminbaseController {

//
    protected $page;
//    问题类型 1-地址错误 2-联系不上 3-退货退款 4-换货 5-拒收 6-其他
    protected $reasontype = ['1' => '地址错误', '2' => '联系不上', '3' => '退货', '4' => '换货', '5' => '拒收', '6' => '其他'];
    protected $resulttype = ['1' => '确定拒收', '2' => '已发邮件', '3' => '可退', '4' => '不可退','5'=>'重新派送','6'=>'联系中'];
    public function _initialize() {
        parent::_initialize();
        $this->page = $_SESSION['set_page_row'] ? (int) $_SESSION['set_page_row'] : 20;
    }

    /**
     * 问题件申请页面
     */
    public function apply() {
        $getData = I('get.', "htmlspecialchars");
        $cur_page = $getData['p']? : 1; //默认页数
        $cond = [];
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }
        $ordershipping = D('Order/OrderShipping')->getTableName();
        $cond['o.id_order_status'] = array('in', array(8, 9, 10, 16, 19, 24)); //查询 配送中，签收，拒收，退货，理赔，已转寄入库

        if (!empty($getData['track_number'])) {
            $getData['track_number']=  trim($getData['track_number']);
            $cond['os.track_number'] = array('like', "%{$getData['track_number']}%");
        }
        if (!empty($getData['id_increment'])) {
            $getData['id_increment']=  trim($getData['id_increment']);
            $cond['o.id_increment'] = array('like', "%{$getData['id_increment']}%");
        }
        $fields = 'o.id_order,o.id_order_status,o.id_increment,o.id_shipping,o.id_department,o.id_domain,os.track_number';
        $count = M('order o')->join("{$ordershipping} as os on os.id_order=o.id_order")->where($cond)->count();
        $orderList = M('order o')->join("{$ordershipping} as os on os.id_order=o.id_order")->page("$cur_page,$this->page")->field($fields)->where($cond)->order('id_order desc')->select();
//        var_dump($orderList);die();
        if ($orderList) {
            $itemFileds = "sale_title,quantity,price,attrs_title,product_title";

            foreach ($orderList as $key => $val) {
                $orderItems = [];
                $orderItems = M('orderItem')->where(array('id_order' => $val['id_order']))->field($itemFileds)->select();
                $orderList[$key]['products'] = $orderItems;
                $orderList[$key]['applyinfo'] = M('problemOrder')->order('id desc')->where(array('id_order' => $val['id_order']))->find();
            }
        }
        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        $departmentList = M('department')->where(array('type' => 1))->getField('id_department,title');
        $domainList = M('domain')->where(array('status' => 1))->getField('id_domain,name');
        $this->assign('orderList', $orderList);
        $this->assign('departmentList', $departmentList);
        $this->assign('domainList', $domainList);
        $this->assign('shipList', $shipList);
        $this->assign('reasontype', $this->reasontype);
        $page = $this->page($count, $this->page);
        $this->assign("page", $page->show('Admin'));
        $this->assign("getData", $getData);
//        var_dump($getData);die();
        $this->display();
    }

    /**
     * 提交申请
     */
    public function submitApply() {
        $return = array('status' => 0, 'message' => 'fail!');
        $postData = is_array($_POST['orderdata']) ? $_POST['orderdata'] : array($_POST['orderdata']);
        $needfield = array('reasontype', 'logisticsdate', 'id_order', 'id_increment');
        foreach ($postData as $key => $val) {//检查必要字段
            if (in_array($key, $needfield) && !$val) {
                $return['message'] = '缺少必要字段！';
                echo json_encode($return);
                exit();
            }
        }
        if (!$postData['problem_order_id']) {
            $postData['status'] = 1;
            $postData['ownerid'] = $_SESSION['ADMIN_ID'];
            $postData['creationdate'] = date('Y-m-d H:i:s', time());
            $Res = M('problemOrder')->add($postData);
            $this->addRecord($Res, 1, $postData['reasontype'], $postData['reason_remark']);
        } else {
            $Res = M('problemOrder')->where(array('id' => $postData['problem_order_id']))->save($postData);
            $this->addRecord($postData['problem_order_id'], 1, $postData['reasontype'], $postData['reason_remark']);
        }

        if ($Res) {
            $return = array('status' => 1, 'message' => '提交成功！');
            add_system_record($_SESSION['ADMIN_ID'], 1, 4, $str);
        }
        echo json_encode($return);
    }

    /**
     * 批量导入问题件申请
     */
    public function more_apply() {
        
        if($_POST){
            
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $data = I('post.import_data');
            //导入记录到文件
            $path = write_file('ProblemShipment', 'more_apply', $data);
            $data = $this->getDataRow($data);
//            var_dump(count($data));die();
            $applyData=[];
            foreach ($data as $line){
                $temp=[];
                $line= preg_replace('/\t+/', "\t", $line);
                list($temp['logisticsdate'],$temp['track_number'],$temp['reasontypestr'],$temp['reason_remark'])=split("\t", $line);
                $applyData[]=$temp;
            }
            $errmsg=[];
            $order_tabke = D("Common/order")->getTableName();
            $return = array('status' => 1, 'message' => '提交成功!','err'=>'');
            $msg = '批量申请问题件';
            //验证数据的正确性
            //本批次不能导入重复运单号
            $track_numberas=array_column($applyData,'track_number');
            $tnum_unique=  array_unique($track_numberas);
            $tnum_repeat=  array_diff_assoc($track_numberas, $tnum_unique);
            if($tnum_repeat){
                $repeatStr=implode(',',$tnum_repeat);
                $errmsg[]="导入数据中,{$repeatStr} 这些运单号有重复！请重新导入！";
            }    
            $userNames = [];
            $ownerIds = M('problemOrder')->distinct(true)->getField('ownerid', true);
            $statuserIds = M('problemOrder')->distinct(true)->getField('statuserid', true);
            $userIds = array_unique(array_merge($ownerIds, $statuserIds));
            $cond_user = array();
            if ($userIds) {
                $cond_user['id'] = array('in', implode(',', $userIds));
                $userNames = M('users')->where($cond_user)->getField('id,user_nicename', true);
            }        
            $total=count($applyData);
            foreach ($applyData as $key=> $apply){
                $linenum=$key+1;
                $orderInfo=[];
                $orderInfo=M('orderShipping os')->join("{$order_tabke} as o on o.id_order=os.id_order")
                        ->field('os.track_number,o.id_increment,os.id_order')->where(array('os.track_number'=>trim($apply['track_number'])))
                        ->find();
                if(!$orderInfo){
                    $errmsg[]="第{$linenum}行,{$apply['track_number']} 运单号无法找到对应数据！";            continue;

                }
                if(strtotime($apply['logisticsdate'])<  strtotime('2017-01-01')){
                    $errmsg[]="第{$linenum}行,{$apply['track_number']} 运单号,物流登记时间有误！";            continue;

                }  
                $probleminfo=M('problemOrder')->where(array('id_order'=>$orderInfo['id_order']))->find();
                if($probleminfo['status']==2){
                    $errmsg[]="第{$linenum}行,{$apply['track_number']} 运单号,已经完成问题件处理！";            continue;

                }
                if($probleminfo['status']!=1){
                    $orderInfo['status']=1;                
                    $orderInfo['creationdate'] = date('Y-m-d H:i:s', time());                 
                }else{
                    $statususer=$probleminfo['statuserid']?$userNames[$probleminfo['statuserid']]:'无';
                    $errmsg[]="第{$linenum}行,{$apply['track_number']} 运单号,已经提交申请。处理人：{$statususer}";continue;
    //                $orderInfo['problem_order_id']=$probleminfo['id'];
                }
                $reasontypearr=$this->reasontype;
                foreach ($reasontypearr as $key=>$val){
                    if($val==  trim($apply['reasontypestr'])){
                        $orderInfo['reasontype'] = $key;
                    }
                }
                $orderInfo['reasontype']=$orderInfo['reasontype']?$orderInfo['reasontype']:6;//  填写有误是 默认其他
                $orderInfo['ownerid'] = $_SESSION['ADMIN_ID'];
                $orderInfo['isapplying']=$probleminfo['status']==1?1:0;
                $orderInfo['logisticsdate']=$apply['logisticsdate'];
                $orderInfo['reason_remark']=$apply['reason_remark'];
                $orderInfo['linenum']=$linenum;
                $orderData[$orderInfo['track_number']]=$orderInfo;
            }

            //数据验证通过  插入数据
            if($orderData){
                foreach ($orderData as $order){
                    if($order['isapplying']==1){
    //                    $Res = M('problemOrder')->where(array('id' => $order['problem_order_id']))->save($order);
    //                    $this->addRecord($order['problem_order_id'], 1, $order['reasontype'], $order['reason_remark']);
                    }else{
//                        $return['success'][]="";
                        $Res = M('problemOrder')->add($order);
                        $this->addRecord($Res, 1, $order['reasontype'], $order['reason_remark']);
                    }
                }
            }    
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            if(!empty($errmsg)){
                $errcnt=count($errmsg);
                $succnt=count($orderData);
                if($tnum_repeat){
                    $errcnt=$errcnt-1;
                }
                array_unshift($errmsg, "一共提交：{$total}条数据  有误数据：{$errcnt} 成功数据：{$succnt}");
                $return['status']=0;
                $return['error'] = $errmsg;
            } else{
                $this->success("数据全部成功导入！", U("Order/ProblemShipment/apply"),3);
                exit();
            }       
            $this->assign("infor", $return);            
            
        }
        $this->display();
    }

    protected function getDataRow($data)
    {
        if (empty($data))
            return array();
        $data = preg_split("~[\r\n]~", $data, -1, PREG_SPLIT_NO_EMPTY);
        return $data;
    }    
    /**
     * 审核列表
     */
    public function handle() {
        $getData = I('get.', "htmlspecialchars");
        $cur_page = $getData['p']? : 1; //默认页数 
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }        
        $order_table = D("Common/order")->getTableName();
        $orderShipping_table = D("Common/orderShipping")->getTableName();
        $orderitem_table = D("Common/orderItem")->getTableName();
        $product_table = D("Common/product")->getTableName();
        $filed="o.id_order,o.price_total as total_price,o.id_increment,o.email,o.id_shipping,o.id_department,o.id_zone,po.logisticsdate,os.track_number,po.reasontype,po.reason_remark,o.first_name,o.tel,o.date_delivery,po.creationdate,po.statusdate,po.ownerid,po.statuserid,po.handle_remark,po.status,po.result,po.id as poid";
        $where=[];
        if (!empty($getData['id_increment'])) {
            $getData['id_increment']=trim($getData['id_increment']);
            $where['o.id_increment'] = array('like',"%{$getData['id_increment']}%");
        }      
        if (!empty($getData['track_number'])) {
            $getData['track_number']=trim($getData['track_number']);
            $where['os.track_number'] = array('like',"%{$getData['track_number']}%");
        }    
        if (!empty($getData['start_time_enter'])) {
            $where['po.creationdate'][] = array('EGT', $getData['start_time_enter']);
        }
        if (!empty($getData['end_time_enter'])) {
            $where['po.creationdate'][] = array('ELT', $getData['end_time_enter'] . ' 23:59:59');
        }     
        if (!empty($getData['start_time_handle'])) {
            $where['po.statusdate'][] = array('EGT', $getData['start_time_handle']);
        }
        if (!empty($getData['end_time_handle'])) {
            $where['po.statusdate'][] = array('ELT', $getData['end_time_handle'] . ' 23:59:59');
        }     
        if (!empty($getData['id_shipping'])) {
            $where['o.id_shipping'] = $getData['id_shipping'];
        }    
        if (!empty($getData['department'])) {
            $where['o.id_department'] = $getData['department'];
        }       
        if (!empty($getData['statuser'])) {
            $where['po.statuserid'] = $getData['statuser'];
        }      
        if (!empty($getData['id_zone'])) {
            $where['o.id_zone'] = $getData['id_zone'];
        }     
        if(isset($getData['result'])){
            if($getData['result']==0){
                $where['po.status'] = 1;
            }
            if($getData['result']>0){
                $where['po.result'] = $getData['result'];
            }
        }
        //获取用户名称数组
        $userNames = [];
        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        $departmentList = M('department')->where(array('type' => 1))->order('title asc')->getField('id_department,title'); //业务部门
        $zoneList = M('zone')->getField('id_zone,title'); //
        $ownerIds = M('problemOrder')->alias('ro')->distinct(true)->getField('ownerid', true);
        $statuserIds = M('problemOrder')->alias('ro')->distinct(true)->getField('statuserid', true);
        $userIds = array_unique(array_merge($ownerIds, $statuserIds));
        $cond_user = array();
        if ($userIds) {
            $cond_user['id'] = array('in', implode(',', $userIds));
            $userNames = M('users')->where($cond_user)->getField('id,user_nicename', true);
        }
        $dataList=M("problemOrder po")->join("{$order_table} o on o.id_order=po.id_order")
                    ->join("{$orderShipping_table} os on os.id_order=po.id_order")->field($filed)->where($where)
                    ->page("$cur_page,$this->page")->order('po.id desc') ->select();
        $count  =M("problemOrder po")->join("{$order_table} o on o.id_order=po.id_order")
                    ->join("{$orderShipping_table} os on os.id_order=po.id_order")->where($where)  ->count();
        $itemFileds = "oi.total,oi.quantity,oi.price,oi.product_title,p.inner_name";
        if ($dataList) {
            foreach ($dataList as $key => $val) {
                $orderItems = M('orderItem oi')->join("{$product_table} p on p.id_product=oi.id_product")->where(array('oi.id_order' => $val['id_order']))->field($itemFileds)->select();
                $dataList[$key]['products'] = $orderItems;
//                $dataList[$key]['total_price'] = array_sum(array_column($orderItems, 'total'));
            }
        }
//        var_dump($dataList[0]);die();
        $page = $this->page($count, $this->page);
        $this->assign("shipList", $shipList);
        $this->assign("zoneList", $zoneList);
        $this->assign("reasonarr", $this->reasontype);
        $this->assign("resulttypearr", $this->resulttype);
        $this->assign("departmentList", $departmentList);
        $this->assign("page", $page->show('Admin'));
        $this->assign('dataList', $dataList);
        $this->assign('userNames', $userNames);
        $this->assign('getData', $getData);

        $this->display();
    }

    /**
     * 处理申请
     */
    public function handleApply() {
        $return = array('status' => 0, 'message' => 'fail!');
        $postData = is_array($_POST['orderdata']) ? $_POST['orderdata'] : array($_POST['orderdata']);
        $needfield = array('poid', 'result');
        foreach ($postData as $key => $val) {//检查必要字段
            if (in_array($key, $needfield) && !$val) {
                $return['message'] = '缺少必要字段！';
                echo json_encode($return);
                exit();
            }
        }
        $postData['status'] = 2;
        $postData['statuserid'] = $_SESSION['ADMIN_ID'];
        $postData['statusdate'] = date('Y-m-d H:i:s', time());
        $updRes = M('problemOrder')->where(array('id' => $postData['poid']))->save($postData);
        $this->addRecord($postData['poid'],2, $postData['result'], $postData['handle_remark']);
        if ($updRes) {
            $return = array('status' => 1, 'message' => '提交成功！');
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, $str);
        }
        echo json_encode($return);
    }



    public function exportHandle() {
        $getData = I('get.', "htmlspecialchars");
        $cur_page = $getData['p']? : 1; //默认页数 
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }        
        $order_table = D("Common/order")->getTableName();
        $orderShipping_table = D("Common/orderShipping")->getTableName();
        $orderitem_table = D("Common/orderItem")->getTableName();
        $product_table = D("Common/product")->getTableName();
        $filed="o.id_order,o.id_increment,o.email,o.id_shipping,o.id_department,o.id_zone,po.logisticsdate,os.track_number,po.reasontype,po.reason_remark,o.first_name,o.tel,o.date_delivery,po.creationdate,po.statusdate,po.ownerid,po.statuserid,po.handle_remark,po.status,po.result";
        $where=[];
        if (!empty($getData['id_increment'])) {
            $getData['id_increment']=trim($getData['id_increment']);
            $where['o.id_increment'] = array('like',"%{$getData['id_increment']}%");
        }      
        if (!empty($getData['track_number'])) {
            $getData['track_number']=trim($getData['track_number']);
            $where['os.track_number'] = array('like',"%{$getData['track_number']}%");
        }    
        if (!empty($getData['start_time_enter'])) {
            $where['po.creationdate'][] = array('EGT', $getData['start_time_enter']);
        }
        if (!empty($getData['end_time_enter'])) {
            $where['po.creationdate'][] = array('ELT', $getData['end_time_enter'] . ' 23:59:59');
        }     
        if (!empty($getData['start_time_handle'])) {
            $where['po.statusdate'][] = array('EGT', $getData['start_time_handle']);
        }
        if (!empty($getData['end_time_handle'])) {
            $where['po.statusdate'][] = array('ELT', $getData['end_time_handle'] . ' 23:59:59');
        }     
        if (!empty($getData['id_shipping'])) {
            $where['o.id_shipping'] = $getData['id_shipping'];
        }     
   
        if (!empty($getData['department'])) {
            $where['o.id_department'] = $getData['department'];
        }       
        if (!empty($getData['id_zone'])) {
            $where['o.id_zone'] = $getData['id_zone'];
        }                
        if (!empty($getData['statuser'])) {
            $where['po.statuserid'] = $getData['statuser'];
        }       
        if(isset($getData['result'])){
            if($getData['result']==0){
                $where['po.status'] = 1;
            }
            if($getData['result']>0){
                $where['po.result'] = $getData['result'];
            }
        }      
 
        //获取用户名称数组
        $userNames = [];
        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        $departmentList = M('department')->where(array('type' => 1))->getField('id_department,title'); //业务部门
        $zoneList = M('zone')->getField('id_zone,title'); //
        $ownerIds = M('problemOrder')->alias('ro')->distinct(true)->getField('ownerid', true);
        $statuserIds = M('problemOrder')->alias('ro')->distinct(true)->getField('statuserid', true);
        $userIds = array_unique(array_merge($ownerIds, $statuserIds));
        $reasonarr=$this->reasontype;
        $resulttypearr=$this->resulttype;
        
        $cond_user = array();
        if ($userIds) {
            $cond_user['id'] = array('in', implode(',', $userIds));
            $userNames = M('users')->where($cond_user)->getField('id,user_nicename', true);
        }
        $dataList=M("problemOrder po")->join("{$order_table} o on o.id_order=po.id_order")
                    ->join("{$orderShipping_table} os on os.id_order=po.id_order")->field($filed)->where($where)
                    ->select();
        $itemFileds = "oi.total,oi.quantity,oi.price,oi.product_title,p.inner_name";
        if ($dataList) {
            foreach ($dataList as $key => $val) {
                $orderItems = M('orderItem oi')->join("{$product_table} p on p.id_product=oi.id_product")->where(array('oi.id_order' => $val['id_order']))->field($itemFileds)->select();
                $dataList[$key]['products'] = $orderItems;
                $dataList[$key]['total_price'] = array_sum(array_column($orderItems, 'total'));
            }
        }
 
        $str = "业务部门,地区,邮箱,物流,物流登记时间,运单号,订单号,问题类型,原因描述,收件人,收件电话,内部名,总价,发货时间,导入时间,处理人,处理时间,处理结果,处理备注\n";
        foreach ($dataList as $k => $val) {
            $productStr = '';
            foreach ($val['products'] as $product) {
                $productStr.="{$product['inner_name']}( x{$product['quantity']})   ;";
            }
            $productStr=str_replace(array("\n","\r",",","\"","\'"),array("",""), $productStr);
            $val['email']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $val['email']);
            $val['first_name']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $val['first_name']);
            $val['reason_remark']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $val['reason_remark']);
            $val['handle_remark']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $val['handle_remark']); 
            $val['tel']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $val['tel']);
            $str.=
                    $departmentList[$val['id_department']] . ',' .
                    $zoneList[$val['id_zone']] . ',' .  
                    $val['email'] . ',' .
                    $shipList[$val['id_shipping']] . ',' .
                    $val['logisticsdate'] . "\t," .
                    $val['track_number'] . "\t," .
                    $val['id_increment'] . "\t," .
                    $reasonarr[$val['reasontype']] . ',' .
                    $val['reason_remark'] . ',' .
                    $val['first_name'] . ',' .
                    $val['tel'] . "\t," .
                    $productStr . ',' . 
                    $val['total_price'] . "\t," .
                    $val['date_delivery'] . "\t," .
                    $val['creationdate'] . "\t," .
                    $userNames[$val['statuserid']] . "\t," .
                    ($val['statusdate'] == '0000-00-00 00:00:00' ? '' : $val['statusdate']) . "\t," .
                    $resulttypearr[$val['result']] . "," .
                    $val['handle_remark'] . "\n";
       
                    
        }
        $filename = date('Ymd') . '.csv'; //设置文件名
        $this->export_csv($filename, iconv("UTF-8","GBK//IGNORE",$str)); //导出
        exit;
    }
    
    /**
     * 操作详情
     */
    public function RecordInfo(){
        $reasonarr=$this->reasontype;
        $resulttypearr=$this->resulttype;        
        $po_id = I('get.id');
        $order_id=M("problemOrder")->where(array('id'=>$po_id))->getField('id_order');
        $order = D("Order/Order")->find($order_id);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $orderHistory = M("problemOrderRecord po")
            ->field('po.*,u.user_nicename')
            ->join('__USERS__ u ON (po.id_users = u.id)', 'LEFT')
            ->where(array('po.id_problem_order'=>$po_id))
            ->order('created_at desc')->select();
        foreach ($orderHistory as &$val){
            if($val['type']==1){
                $val['desc']=$val['user_nicename'].' ： 录入问题件'.' :'.$reasonarr[$val['reasontype']];
            }
            if($val['type']==2){
                $val['desc']=$val['user_nicename'].' ： 处理问题件'.' :'.$resulttypearr[$val['result']];
            }            
        }
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
        $this->assign("reasonarr", $reasonarr);
        $this->assign("resulttypearr", $resulttypearr);
        $this->assign("order", $order);
        $this->assign("products", $products);
        $this->assign("history", $orderHistory);
        $this->assign("label", $statusLabel);
        $this->assign('shipping_name', $shipping['title']);
        $this->assign('shopping_url', $shipping['track_url']);
        $this->display();
    }    
    
    /**
     * 
     */
    public function addRecord($poid,$type,$action,$remark=''){
        $addData=[];
        $addData['id_users'] = $_SESSION['ADMIN_ID'];
        $addData['created_at'] = date('Y-m-d H:i:s', time());
        if($type==1){
            $addData['reasontype'] = $action;
        }
        if($type==2){
            $addData['result'] = $action;
        }
        $addData['id_problem_order']=$poid;
        $addData['type']=$type;
        $addData['remark']=$remark;
        $addRes=M('problemOrderRecord')->add($addData);
        return TRUE;
        
        
    }

    protected function export_csv($filename, $data) {
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=" . $filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }

}
