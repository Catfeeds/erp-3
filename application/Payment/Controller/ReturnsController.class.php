<?php

/**
 * 退换货订单处理
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

class ReturnsController extends AdminbaseController {

    protected $order, $page;

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->page = $_SESSION['set_page_row'] ? (int) $_SESSION['set_page_row'] : 20;
    }

    /**
     * 退换货申请页面
     */
    public function apply() {
        $getData = I('get.', "htmlspecialchars");
        $cur_page = $getData['p']? : 1; //默认页数
        $ordershipping = D("Order/OrderShipping")->getTableName();        
        $cond = [];
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }
        $cond['o.id_order_status'] = array('in', array(8,9)); //查询配送中，退货，拒收，理赔的订单
        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        if (!empty($getData['id_shipping'])) {
            $cond['o.id_shipping'] = $getData['id_shipping'];
        }
        if (!empty($getData['keywords'])) {
            $getData['keywords']=  trim($getData['keywords']);
            $cond['o.id_increment'] = array('like', "%{$getData['keywords']}%");
        }
        if (!empty($getData['track_number'])) {
            $getData['track_number']=  trim($getData['track_number']);
            $cond['os.track_number'] = array('like', "%{$getData['track_number']}%");
        }        
        $applycon['status'] = array('NEQ', 3);
        $fields = 'o.id_order,o.id_order_status,o.id_increment,o.id_shipping,os.track_number,o.price_total as total_price';
        $count = M('order o')->join("{$ordershipping} os on os.id_order=o.id_order",'left')->where($cond)->count();
        $orderList = M('order o')->join("{$ordershipping} os on os.id_order=o.id_order",'left')->page("$cur_page,$this->page")->field($fields)->where($cond)->order('id_order desc')->select();
        if ($orderList) {
            $itemFileds = "sale_title,quantity,price,quantity*price as itemtotal,attrs_title,product_title";
            foreach ($orderList as $key => $val) {
                $applycon['id_order'] = $val['id_order'];
                $isapply = M('returnsOrder')->where($applycon)->getField('id');
                if ($isapply) {
                    $orderList[$key]['noapply'] = 1;
                    $orderList[$key]['applyedId'] = $isapply;
                }
                $orderItems = M('orderItem')->where(array('id_order' => $val['id_order']))->field($itemFileds)->select();
                foreach ($orderItems as &$item) {
                    $item['attrs_title'] = implode(',', unserialize($item['attrs_title']));
                }
                $orderList[$key]['products'] = $orderItems;
//                $orderList[$key]['total_price'] = array_sum(array_column($orderItems, 'itemtotal'));
            }
        }
        $this->assign('orderList', $orderList);
        $this->assign('getData', $getData);
        $page = $this->page($count, $this->page);
        $this->assign("page", $page->show('Admin'));

        $this->assign("shipList", $shipList);
//        var_dump($shipList);die();
        $this->display();
    }

    /**
     * 提交申请
     */
    public function submitApply() {
        $return = array('status' => 0, 'message' => 'fail!');
        $postData = is_array($_POST['orderdata']) ? $_POST['orderdata'] : array($_POST['orderdata']);
        if ($postData['type'] == 1) {
            $str = '退货申请';
        } else {
            $str = '换货申请';
        }
        $needfield = array('reason', 'source', 'id_order', 'id_increment', 'type');
        foreach ($postData as $key => $val) {//检查必要字段
            $postData[$key]=trim($val);
            if (in_array($key, $needfield) && !$val) {
                $return['message'] = '缺少必要字段！';
                echo json_encode($return);
                exit();
            }
        }
        $isexit=M('returnsOrder')->where(array('id_increment'=>$postData['id_increment'],'status'=>1))->count();
        if($isexit>=1){
                $return['message'] = '该记录已经录入，请勿重复录入！';
                echo json_encode($return);
                exit();            
        }
        $postData['billdate'] = date('Y-m-d H:i:s', time());
        $postData['status'] = 1;
        $postData['ownerid'] = $_SESSION['ADMIN_ID'];
        $postData['creationdate'] = date('Y-m-d H:i:s', time());
        $insetRes = M('returnsOrder')->add($postData);
        if ($insetRes) {
            $return = array('status' => 1, 'message' => '提交成功！');
            add_system_record($_SESSION['ADMIN_ID'], 1, 4, $str.'订单号：'.$postData['id_increment']);
        }
        echo json_encode($return);
    }

    /**
     * 退换货审核列表
     */
    public function handle() {
//        申请来源 1-售后邮箱 2-问题件 3-物流自退
        $sourcearr = array('1' => '售后邮箱', '2' => '问题件', '3' => '物流自退');
//        退货单据状态  1-待审核 2-申请通过 3-申请失败
        $statusarr = array('1' => '待审核', '2' => '申请通过', '3' => '申请失败');
//         1-质量原因 2-质不对版 3-个人原因（不想要）4-其他
        $reasonarr = array('1' => '质量原因', '2' => '质不对板', '3' => '个人原因（不想要）', '4' => '其他');
        $getData = I('get.', "htmlspecialchars");
        $ordershipping = D("Order/OrderShipping")->getTableName();          
        $cur_page = $getData['p']? : 1; //默认页数
        $cond = [];
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }
        if (!empty($getData['keywords'])) {
            $getData['keywords']=  trim($getData['keywords']);
            $cond['ro.id_increment'] = array('like', "%{$getData['keywords']}%");
        }

        if (!empty($getData['status'])) {
            $cond['ro.status'] = $getData['status'];
        }
        if (!empty($getData['start_time'])) {
            $cond['ro.creationdate'][] = array('EGT', $getData['start_time']);
        }
        if (!empty($getData['end_time'])) {
            $cond['ro.creationdate'][] = array('ELT', $getData['end_time'] . ' 23:59:59');
        }
        if (!empty($getData['check_start_time'])) {
            $cond['ro.statusdate'][] = array('EGT', $getData['check_start_time']);
        }
        if (!empty($getData['check_end_time'])) {
            $cond['ro.statusdate'][] = array('ELT', $getData['check_end_time'] . ' 23:59:59');
            
        } 
        if(empty($getData['check_start_time'])&&$getData['check_end_time']){
            $cond['ro.statusdate'][] = array('neq', '0000-00-00 00:00:00');
        }    
//        var_dump($cond);die();
        $order_table = D("Common/order")->getTableName();
        //获取盘点用户名称数组
        $userNames = [];
        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        $departmentList=M('department')->where(array('type'=>1))->getField('id_department,title');//业务部门
        $domainList=M('domain')->where(array('status'=>1))->getField('id_domain,name');//
        $ownerIds = M('returnsOrder')->alias('ro')->distinct(true)->where($cond)->getField('ownerid', true);
        $statuserIds = M('returnsOrder')->alias('ro')->distinct(true)->where($cond)->getField('statuserid', true);
        $userIds = array_unique(array_merge($ownerIds, $statuserIds));
        $cond_user = array();
        if ($userIds) {
            $cond_user['id'] = array('in', implode(',', $userIds));
            $userNames = M('users')->where($cond_user)->getField('id,user_nicename', true);
        }
        if (!empty($getData['id_shipping'])) {
            $cond['o.id_shipping'] = $getData['id_shipping'];
        }
        if(!empty($getData['id_department'])){
            $cond['o.id_department'] = $getData['id_department'];
        }
        if (!empty($getData['track_number'])) {
            $getData['track_number']=  trim($getData['track_number']);
            $cond['os.track_number'] = array('like', "%{$getData['track_number']}%");
        }        

        $applyList = M('returnsOrder')->order('ro.id desc')->alias('ro')->page("$cur_page,$this->page")->join("$order_table  o ON o.id_order= ro.id_order")->join("{$ordershipping} os on os.id_order=o.id_order",'left')->where($cond)->field('ro.*,o.id_shipping as id_shipping,o.id_department,o.id_domain,os.track_number,o.price_total as total_price')->select();
//            var_dump(M('returnsOrder')->getLastSql());die();
        $count = M('returnsOrder')->alias('ro')->join("$order_table  o ON o.id_order= ro.id_order")->join("{$ordershipping} os on os.id_order=o.id_order",'left')->where($cond)->field('ro.*,o.id_shipping as id_shipping')->count();
        $itemFileds = "sale_title,quantity,price,quantity*price as itemtotal,attrs_title,product_title";
        if ($applyList) {
            foreach ($applyList as $key => $val) {
                $orderItems = M('orderItem')->where(array('id_order' => $val['id_order']))->field($itemFileds)->select();
                foreach ($orderItems as &$item) {
                    $item['attrs_title'] = implode(',', unserialize($item['attrs_title']));
                }
                $applyList[$key]['products'] = $orderItems;
//                $applyList[$key]['total_price'] = array_sum(array_column($orderItems, 'itemtotal'));
            }
        }
        $page = $this->page($count, $this->page);
        $this->assign("shipList", $shipList);
        $this->assign("domainList", $domainList);
        
        $this->assign("reasonarr", $reasonarr);
        $this->assign("departmentList", $departmentList);
        $this->assign("page", $page->show('Admin'));
        $this->assign('orderList', $applyList);
        $this->assign('sourcearr', $sourcearr);
        $this->assign('statusarr', $statusarr);
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

        $needfield = array('id', 'id_increment');
        foreach ($postData as $key => $val) {//检查必要字段
            if (in_array($key, $needfield) && !$val) {
                $return['message'] = '缺少必要字段！';
                echo json_encode($return);
                exit();
            }
        }
        $str = '订单号： ' . $postData['id_increment'] . ($postData['type'] == 1 ? '  退货' : ' 换货');
        if ($postData['status'] == 2) {
            $str.="申请通过 ,";
        } else {
            $str.='申请失败 ,';
        }
        $updData = $postData;    
        $updData['statuserid'] = $_SESSION['ADMIN_ID'];
        $updData['statusdate'] = date('Y-m-d H:i:s', time());
        $updRes = M('returnsOrder')->where(array('id' => $postData['id']))->save($updData);
        if ($updRes) {
            $return = array('status' => 1, 'message' => '提交成功！');
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, $str);
        }
        echo json_encode($return);
    }
    
    
    public function  submitRemark(){
        $return = array('status' => 0, 'message' => 'fail!');
        $postData = is_array($_POST['orderdata']) ? $_POST['orderdata'] : array($_POST['orderdata']);
        $needfield = array('id', 'id_increment');
        foreach ($postData as $key => $val) {//检查必要字段
            if (in_array($key, $needfield) && !$val) {
                $return['message'] = '缺少必要字段！';
                echo json_encode($return);
                exit();
            }
        }
        if(empty($postData['check_remark'])){
                $return['message'] = '提交备注信息不能空！';
                echo json_encode($return);
                exit();            
        }
        $updData = [];
        $updData['check_remark'] = $postData['check_remark'];
        $updRes = M('returnsOrder')->where(array('id' => $postData['id']))->save($updData);
        if ($updRes) {
            $return = array('status' => 1, 'message' => '提交成功！');
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, $str);
        }
        echo json_encode($return);        
    }

    public function exportApply() {
//        申请来源 1-售后邮箱 2-问题件 3-物流自退
        $sourcearr = array('1' => '售后邮箱', '2' => '问题件', '3' => '物流自退');
//        退货单据状态  1-待审核 2-申请通过 3-申请失败
        $statusarr = array('1' => '待审核', '2' => '申请通过', '3' => '申请失败');
//         1-质量原因 2-质不对版 3-个人原因（不想要）4-其他
        $reasonarr = array('1' => '质量原因', '2' => '质不对板', '3' => '个人原因（不想要）', '4' => '其他');
        $getData = I('get.', "htmlspecialchars");
        $ordershipping = D("Order/OrderShipping")->getTableName();          
        $cur_page = $getData['p']? : 1; //默认页数
        $cond = [];
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }
        if (!empty($getData['keywords'])) {
            $getData['keywords']=  trim($getData['keywords']);
            $cond['ro.id_increment'] = array('like', "%{$getData['keywords']}%");
        }

        if (!empty($getData['status'])) {
            $cond['ro.status'] = $getData['status'];
        }
        if (!empty($getData['start_time'])) {
            $cond['ro.creationdate'][] = array('EGT', $getData['start_time']);
        }
        if (!empty($getData['end_time'])) {
            $cond['ro.creationdate'][] = array('ELT', $getData['end_time'] . ' 23:59:59');
        }
        if (!empty($getData['check_start_time'])) {
            $cond['ro.statusdate'][] = array('EGT', $getData['check_start_time']);
        }
        if (!empty($getData['check_end_time'])) {
            $cond['ro.statusdate'][] = array('ELT', $getData['check_end_time'] . ' 23:59:59');
            
        } 
        if(empty($getData['check_start_time'])&&$getData['check_end_time']){
            $cond['ro.statusdate'][] = array('neq', '0000-00-00 00:00:00');
        }

        $order_table = D("Common/order")->getTableName();
//        var_dump($cond);die();
        //获取盘点用户名称数组
        $userNames = [];
        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        $departmentList=M('department')->where(array('type'=>1))->getField('id_department,title');//业务部门
        $domainList=M('domain')->where(array('status'=>1))->getField('id_domain,name');//
        $ownerIds = M('returnsOrder')->alias('ro')->distinct(true)->where($cond)->getField('ownerid', true);
        $statuserIds = M('returnsOrder')->alias('ro')->distinct(true)->where($cond)->getField('statuserid', true);
        $userIds = array_unique(array_merge($ownerIds, $statuserIds));
        $cond_user = array();
        if ($userIds) {
            $cond_user['id'] = array('in', implode(',', $userIds));
            $userNames = M('users')->where($cond_user)->getField('id,user_nicename', true);
        }
        if (!empty($getData['id_shipping'])) {
            $cond['o.id_shipping'] = $getData['id_shipping'];
        }
        if(!empty($getData['id_department'])){
            $cond['o.id_department'] = $getData['id_department'];
        }
        if (!empty($getData['track_number'])) {
            $getData['track_number']=  trim($getData['track_number']);
            $cond['os.track_number'] = array('like', "%{$getData['track_number']}%");
        }    
        $applyList = M('returnsOrder')->order('ro.id desc')->alias('ro')->join("$order_table  o ON o.id_order= ro.id_order")->join("{$ordershipping} os on os.id_order=o.id_order",'left')->where($cond)->field('ro.*,o.id_shipping as id_shipping,o.id_department,o.id_domain,os.track_number')->select();
//            var_dump(M('returnsOrder')->getLastSql());die();  
        $itemFileds = "sale_title,quantity,price,attrs_title,product_title";
        if ($applyList) {
            foreach ($applyList as $key => $val) {
                $orderItems = M('orderItem')->where(array('id_order' => $val['id_order']))->field($itemFileds)->select();
                foreach ($orderItems as &$item) {
                    $item['attrs_title'] = implode(',', unserialize($item['attrs_title']));
                }
                $applyList[$key]['products'] = $orderItems;
                $applyList[$key]['total_price'] = array_sum(array_column($orderItems, 'price'));
            }
        }
//        var_dump($applyList);die();
        $str = "订单编号,运单号,商品信息,总价,物流企业,业务部门,域名,退换货方式,退换货原因,是否回收,退款金额,申请来源,申请状态,申请人,申请时间,申请备注,审核人,审核时间,审核备注\n";
        foreach ($applyList as $k => $val) {
            $productStr='';
            foreach ($val['products'] as $product) {
                $productStr.="{$product['product_title']}({$product['attrs_title']} x{$product['quantity']})  ;";
            }
            $productStr=str_replace(array("\n","\r",",","\"","\'"),array("",""), $productStr);
            $val['apply_remark']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $val['apply_remark']);
            $val['check_remark']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $val['check_remark']);
            $domainList[$val['id_domain']] =str_replace(array("\"","\'"),array("",""), $domainList[$val['id_domain']] ); 
            $val['check_remark']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $val['check_remark']);
            $recyclestr='';
            if($val['recycle']==1){ $recyclestr='是'; }
            if($val['recycle']==2){ $recyclestr='否'; }            
            $str.=
                    $val['id_increment'] . "\t," .
                    $val['track_number'] . "\t," .
                    $productStr . ',' .              
                    $val['total_price'] . ',' .
                    $shipList[$val['id_shipping']] . ',' .
                    $departmentList[$val['id_department']] . ',' .
                    $domainList[$val['id_domain']] . "\t," .                    
                    ($val['type'] == 1 ? '退货' : '换货') . ',' .
                    $reasonarr[$val['reason']] . ',' .
                    $recyclestr. ',' .
                    $val['refundmoney']. ',' .
                    $sourcearr[$val['source']] . ',' .
                    $statusarr[$val['status']] . ',' .
                    $userNames[$val['ownerid']] . ',' .
                    $val['creationdate'] . "\t," .
                    $val['apply_remark'] . "," .
                    $userNames[$val['statuserid']] . ',' .
                    ($val['statusdate']=='0000-00-00 00:00:00'?'':$val['statusdate']) . "\t," .
                    $val['check_remark'] . "\n";
        }
        $filename = date('Ymd') . '.csv'; //设置文件名
        $this->export_csv($filename,  iconv("UTF-8","GBK//IGNORE",$str)); //导出
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

}
