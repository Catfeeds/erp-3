<?php
/**
 * 结算模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Warehouse\Controller
 */
namespace Settlement\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;

class StatisticsController extends AdminbaseController {
    protected $Warehouse, $orderModel;
    public function _initialize() {
        parent::_initialize();
        $this->Warehouse = D("Common/Warehouse");
        $this->orderModel = D("Order/Order");
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row']?$_SESSION['set_page_row']:20;
    }
    /**
     * 更新拒收价格
     */
    public function every_day(){
        $M = new \Think\Model;
        /* @var $ord_model \Common\Model\OrderModel */
        $ord_model = D("Order/Order");
        $department = M('Department')->where('type=1')->select();
        /* @var $ord_ship \Common\Model\OrderShippingModel */
        $ord_ship_table = D('Order/OrderShipping')->getTableName();
        /* @var $orderItem \Common\Model\OrderItemModel */
        $ord_sett = D('Order/OrderSettlement');
        $ord_table = $ord_model->getTableName();
        $ord_sett_table = $ord_sett->getTableName();
        $fieldString = 'SUBSTRING(s.date_signed,1,10) AS set_date,SUM(os.amount_total) AS amount_total,
    (SUM(IF(os.`status`=1,os.amount_total,0))+SUM(IF(os.`status`=0,amount_total,0))) AS no_sett,
    SUM(IF(os.`status`=2,amount_total,0)) AS sett,
    (SUM(CASE os.`status` WHEN 0 THEN 1 ELSE 0 END)+
    SUM(CASE os.`status` WHEN 1 THEN 1 ELSE 0 END)) AS no_order,COUNT(os.`status`) AS all_order
    ';
        $where = array();
        if(isset($_GET['shipping_id'])&& $_GET['shipping_id']){
            $where[]= array('o.id_shipping'=>$_GET['shipping_id']);
        }
        if(isset($_GET['department_id'])&& $_GET['department_id']){
            $where[]= array('o.id_department'=>$_GET['department_id']);
        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
            $createAtArray = array();
            if ($_GET['start_time']) $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $createAtArray[] = array('LT', $_GET['end_time']);
            $where[]= array('s.date_signed'=>$createAtArray);
        }
        if(count($where)==0){
            $where = array('os.status' => array('in',array(0,1,2)));
        }

        $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')";//货到付款订单，过滤已经支付的
        
        $count =$M->table($ord_sett_table . ' AS os LEFT JOIN ' . $ord_table . ' AS o ON os.id_order=o.id_order
         LEFT JOIN '.$ord_ship_table.' as s ON s.id_order=os.id_order')
            ->field($fieldString)
            ->where($where)
            ->order('set_date desc')
            ->group('set_date')->select();
        $page = $this->page(count($count), 20);

        $selectOrder = $M->table($ord_sett_table . ' AS os LEFT JOIN ' . $ord_table . ' AS o ON os.id_order=o.id_order
         LEFT JOIN '.$ord_ship_table.' as s ON s.id_order=os.id_order')
            ->field($fieldString)
            ->where($where)
            ->order('set_date desc')
            ->group('set_date')
            ->limit($page->firstRow , $page->listRows)
            ->select();


        $shipping = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看应实结统计列表');

        $this->assign("list",$selectOrder);
        $this->assign("shipping",$shipping);
        $this->assign('department',$department);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }
    
    /**
     * 签收率统计
     */
    public function receipt_rate() {
        $M = new \Think\Model;
        /* @var $ord_model \Common\Model\OrderModel */
        $ord_model = D("Order/Order");
        $department = M('Department')->where('type=1')->select();
        $shipping = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();
        /* @var $ord_ship \Common\Model\OrderShippingModel */
        $ord_ship_table = D('Order/OrderShipping')->getTableName();
        $ord_table = $ord_model->getTableName();
        
        $where = array();
        if(isset($_GET['shipping_id'])&& $_GET['shipping_id']){
            $where[]= array('o.id_shipping'=>$_GET['shipping_id']);
        }
        if(isset($_GET['department_id'])&& $_GET['department_id']){
            $where[]= array('o.id_department'=>$_GET['department_id']);
        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
            $createAtArray = array();
            if ($_GET['start_time']) $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $createAtArray[] = array('LT', $_GET['end_time']);
            $where[]= array('o.date_delivery'=>$createAtArray);
        }
        $where[] = array('o.id_order_status IN (8,9,10,16)');

        $field = 'count("o.*") AS count,o.id_shipping,
                SUBSTRING(o.date_delivery,1,10) AS delivery_date';
//        $str = 'SUM(IF(o.id_order_status=9,1,0)) AS sign_count';
        
        $all_list = $M->table($ord_table.' as o LEFT JOIN '.$ord_ship_table.' as os ON os.id_order=o.id_order')
                ->field($field)
                ->where($where)
                ->group('delivery_date,o.id_shipping')->cache(true,600)->select();

        $page = $this->page(count($all_list), 20);
        
        $all_list = $M->table($ord_table.' as o LEFT JOIN '.$ord_ship_table.' as os ON os.id_order=o.id_order')
                ->field($field)
                ->where($where)
                ->order('delivery_date DESC')
                ->group('delivery_date,o.id_shipping')->cache(true,600)
                ->limit($page->firstRow . ',' . $page->listRows)
                ->select();

        $result_list = array();
        foreach ($all_list as $key=>$val) {
            $result_list[$val['delivery_date']][] = $val;
        }

        foreach($result_list as $k=>$v){
            foreach($v as $kk=>$vv) {
                $time = $k;
                if(!empty($time)) {
                    $result_list[$k][$kk]['shipping_name'] = D('Order/Shipping')->where(array('id_shipping'=>$vv['id_shipping']))->getField('title');

                    $two_time = date('Y-m-d',strtotime("$time +2 day"));
                    $four_time = date('Y-m-d',strtotime("$time +4 day"));
                    $six_time = date('Y-m-d',strtotime("$time +6 day"));
                    $eight_time = date('Y-m-d',strtotime("$time +8 day"));

                    $result_list[$k][$kk]['two_counts'] = $this->get_result($time, $two_time, 9, $_GET['department_id'],$vv['id_shipping']);
                    $result_list[$k][$kk]['four_counts'] = $this->get_result($time, $four_time, 9, $_GET['department_id'],$vv['id_shipping']);
                    $result_list[$k][$kk]['six_counts'] = $this->get_result($time, $six_time, 9, $_GET['department_id'],$vv['id_shipping']);
                    $result_list[$k][$kk]['eight_counts'] = $this->get_result($time, $eight_time, 9, $_GET['department_id'],$vv['id_shipping']);
                    $result_list[$k][$kk]['signed_counts'] = $this->get_result($time, date('Y-m-d 00:00:00', strtotime('+1 day')), 9, $_GET['department_id'],$vv['id_shipping']);
                }
            }
        }
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看结款签收率统计列表');
        $this->assign('list',$result_list);
        $this->assign("shipping",$shipping);
        $this->assign('department',$department);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }
    
    public function get_result($time,$e_time,$status,$department_id,$shipping_id) {
        $M = new \Think\Model;
        /* @var $ord_model \Common\Model\OrderModel */
        $ord_table = M('Order')->getTableName();
        $ord_ship_table = M('OrderShipping')->getTableName();
        
        $atArray = array();
        $atArray[] = array('EGT', $time);
        $atArray[] = array('LT', $e_time);
        $twhere[]= array('os.date_signed'=>$atArray);
        $twhere[]=array('o.id_order_status'=>$status);
        $stArray = array();
        $stArray[] = array('EGT', $time);
        $stArray[] = array('LT', date('Y-m-d', strtotime($time .'+1 day')));
        $twhere[]= array('o.date_delivery'=>$stArray);
        if(isset($department_id) && $department_id) {
            $twhere[] = array('o.id_department'=>$department_id);
        }
        if(isset($shipping_id) && $shipping_id) {
            $twhere[] = array('o.id_shipping'=>$shipping_id);
        }
        
        $result = $M->table($ord_table.' as o LEFT JOIN '.$ord_ship_table.' as os ON os.id_order=o.id_order')
                ->field('count(*) as count')
                ->where($twhere)
                ->cache(true,600)
//                ->fetchSql(true)
                ->find();
//        dump($result);die;
        return $result['count'];
    }

    /**
     * 预估利润
     */

    public function estimate_profit()
    {
        $ord_model = D("Order/Order");
        $where = array();
        $departments  = D('Department/Department')->where('type=1')->cache(true,3600)->cache(true,3600)->select();
        $departments = array_column($departments,'title','id_department');
        $currency = M('Currency')->field('title,code')->select();
        $currency = array_column($currency,'title','code');
//        dump($currency);
//        $currency_symbols = $ord_model->field('distinct currency_code as currency_code')->cache(true,3600)->select();
////        dump($currency_symbols);
//        $currency_symbols= array_column($currency_symbols,'currency_code');
        $field_effect = 'SUM(IF(`id_order_status` IN(4,5,6,7,8,9,10,16),1,0)) as effective,sum(price_total) as price_total';
        $field_finish = 'SUM(IF(`id_order_status` IN(4,5,6,7,8,9,10,16),1,0)) as finish,sum(amount_total) as price_total';
//        $field_unfinish = 'DISTINCT o.id_order as id_order,o.price_total price_total';

        $lists = $data = array();
        foreach($departments as $key=>$department){
            foreach($currency as $k=>$currency_symbol){
//                dump($currency_symbol);
                $where['o.id_department']= array('EQ',$key);
                $where['currency_code'] = $k;
                if ($_GET['start_time'] or $_GET['end_time']) {
                    $created_at = array();
                    if ($_GET['start_time'])
                        $created_at[]= array('EGT',$_GET['start_time']);
                    if ($_GET['end_time'])
                        $created_at[]= array('LT',$_GET['end_time']);
                    $where['o.created_at']=  $created_at;
                }
                $con = "(os.status_label NOT IN ('代收退貨完成','客樂得貨物退回中','拒收(調查處理中)','退貨完成') OR os.status_label IS NULL) AND ( oss.`status` != 2)";
                $_string = "o.payment_method is NULL OR o.payment_method='' or o.payment_method='0'";
                $effect = $ord_model->alias('o')->field($field_effect)
                    ->where($where)->where(array('id_order_status'=>array('not in','1,2,3,11,12,13,14,15'),$_string))->cache(true,600)->find();
//                dump($effect);
                $finish = $ord_model->alias('o')->field($field_finish)
                    ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order','left')
                    ->where(array('id_order_status'=>array('in','4,5,6,7,8,9,10,16'),'status'=>'2',$_string))->cache(true,600)->where($where)->find();
                $unfinish_list = $ord_model->alias('o')->field('if(o.id_order,1,0) as unfinish,o.price_total as price_total')
                    ->join('__ORDER_SHIPPING__ as os on o.id_order = os.id_order','left')
                    ->join('__ORDER_SETTLEMENT__ as oss on o.id_order = oss.id_order','left')
                    ->where($con,$_string)
                    ->where($where)->group('o.id_order')->cache(true,600)->select();
                $unfinish  = array();
                foreach($unfinish_list as $v){
                        $unfinish['unfinish']+=(int)$v['unfinish'];
                        $unfinish['price_total']+=(int)$v['price_total'];
                }
                $effect = array_filter($effect);
                $finish = array_filter($finish);
                $unfinish = array_filter($unfinish);
                if(!empty($effect)){
                    $lists[$key][$k]['effect']=$effect;
                }
                if(!empty($unfinish)){
                    $lists[$key][$k]['unfinish']=$unfinish;
                }
                if(!empty($finish)){
                    $lists[$key][$k]['finish']=$finish;
                }


                $data[$key]['effect']+=$effect['effective'];
//                dump($data[$key]['effect']);
                $data[$key]['finish']+=$finish['finish'];
                $data[$key]['unfinish']+=$unfinish['unfinish'];
                if($key==='HKD'){
                    $data[$key]['effect_total']+=$effect['price_total']*$_GET[$k];
                    $data[$key]['finish_total']+=$finish['price_total']*$_GET[$k];
                    $data[$key]['unfinish_total']+=$unfinish['price_total']*$_GET[$k];

                }else{

                    $data[$key]['effect_total']+=$effect['price_total']/$_GET[$k];
                    $data[$key]['finish_total']+=$finish['price_total']/$_GET[$k];
                    $data[$key]['unfinish_total']+=$unfinish['price_total']/$_GET[$k];
                }
            }
        }
//        dump($lists);
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看预估利润统计列表');
        $this->assign("lists",$lists);
        $this->assign("data",$data);
        $this->assign("department",$departments);
        $this->assign("currency",$currency);
        $this->display();
    }

    public function actual_profit()
    {
        $ord_model = D("Order/Order");
        $where = array();
        /*****查询所有的部门********/
        $departments  = D('Department/Department')->where('type=1')->cache(true,3600)->cache(true,3600)->select();
        $departments = array_column($departments,'title','id_department');
//        $currency_symbols = $ord_model->field('distinct currency_code as currency_code')->cache(true,3600)->select();
//        $currency_symbols= array_column($currency_symbols,'currency_code');
        $currency = M('Currency')->field('title,code')->select();
        $currency = array_column($currency,'title','code');

        $field_effect = 'SUM(IF(`id_order_status` IN(4,5,6,7,8,9,10,16),1,0)) as effective,sum(price_total) as price_total';
        $field_finish = 'SUM(IF(`id_order_status` IN(4,5,6,7,8,9,10,16),1,0)) as finish,sum(amount_total) as price_total';
        $lists = $data = array();
        foreach($departments as $key=>$department){
            foreach($currency as $k=>$currency_symbol){
                $where['id_department']= array('EQ',$key);
                $where['currency_code'] = $k;

                if ($_GET['start_time'] or $_GET['end_time']) {
                    $created_at = array();
                    if ($_GET['start_time'])
                        $created_at[]= array('EGT',$_GET['start_time']);
                    if ($_GET['end_time'])
                        $created_at[]= array('LT',$_GET['end_time']);
                    $where['o.created_at']=  $created_at;
                }
                $_string = "o.payment_method is NULL OR o.payment_method='' or o.payment_method='0'";
                $effect = $ord_model->alias('o')->field($field_effect)
                    ->where(array('id_order_status'=>array('not in','1,2,3,11,12,13,14,15'),$_string))->where($where)->cache(true,600)->find();
                $finish = $ord_model->alias('o')->field($field_finish)
                    ->join('__ORDER_SETTLEMENT__ as os on os.id_order = o.id_order','left')
                    ->where(array('id_order_status'=>array('in','4,5,6,7,8,9,10,16'),'status'=>'2',$_string))->cache(true,600)->where($where)->find();
                $con = "(os.status_label NOT IN ('代收退貨完成','客樂得貨物退回中','拒收(調查處理中)','退貨完成') OR os.status_label IS NULL) AND ( oss.`status` != 2)";
                $unfinish_list = $ord_model->alias('o')->field('if(o.id_order,1,0) as unfinish,o.price_total as price_total')
                    ->join('__ORDER_SHIPPING__ as os on o.id_order = os.id_order','left')
                    ->join('__ORDER_SETTLEMENT__ as oss on o.id_order = oss.id_order','left')
                    ->where($con,$_string)
                    ->where($where)->group('o.id_order')->cache(true,600)->select();
                $unfinish  = array();
                foreach($unfinish_list as $v){
                    $unfinish['unfinish']+=(int)$v['unfinish'];
                    $unfinish['price_total']+=(int)$v['price_total'];
                }
//               未结款已顺利送达
                $co = '順利送達';
                $unfinish_arrived = $ord_model->alias('o')->field('sum(price_total) as unfinish_arrived_total')
                    ->join('__ORDER_SHIPPING__ as os on os.id_order = o.id_order')
                    ->join('__ORDER_SETTLEMENT__ as oss on oss.id_order = o.id_order')
                    ->where(array('status_label'=>array('EQ',$co),'oss.status = 0',$_string))
                    ->where($where)->cache(true,600)
//                    ->fetchSql()
                    ->find();
//                其余未结款
                $c = "(os.status_label NOT IN ('代收退貨完成','客樂得貨物退回中','拒收(調查處理中)','退貨完成','順利送達') OR os.status_label IS NULL) AND ( oss.`status` != 2)";
                $unfinish_other_list = $ord_model->alias('o')->field('price_total')
                    ->join('__ORDER_SHIPPING__ as os on os.id_order = o.id_order','LEFT')
                    ->join('__ORDER_SETTLEMENT__ as oss on oss.id_order = o.id_order','LEFT')
                    ->where($c,$_string)
                    ->where($where)->cache(true,600)->group('o.id_order')->select();
                $unfinish_other  = array();
                foreach($unfinish_other_list as $v){
                    $unfinish_other['unfinish_other_total']+=(int)$v['price_total'];
                }
                $effect = array_filter($effect);
                $finish = array_filter($finish);
                $unfinish = array_filter($unfinish);
                $unfinish_arrived = array_filter($unfinish_arrived);
                $unfinish_other = array_filter($unfinish_other);
                if(!empty($effect)){
                    $lists[$key][$k]['effect']=$effect;
                }
                if(!empty($unfinish)){
                    $lists[$key][$k]['unfinish']=$unfinish;
                }
                if(!empty($finish)){
                    $lists[$key][$k]['finish']=$finish;
                }
                if(!empty($unfinish_arrived)){
                    $lists[$key][$k]['unfinish_arrived']=$unfinish_arrived;
                }
                if(!empty($unfinish_other)){
                    $lists[$key][$k]['unfinish_other']=$unfinish_other;
                }
                $data[$key]['effect']+=$effect['effective'];
                $data[$key]['finish']+=$finish['finish'];
                $data[$key]['unfinish']+=$unfinish['unfinish'];

                if($key==='HKD'){
                    $data[$key]['effect_total']+=$effect['price_total']*$_GET[$k];
                    $data[$key]['finish_total']+=$finish['price_total']*$_GET[$k];
                    $data[$key]['unfinish_total']+=$unfinish['price_total']*$_GET[$k];

                }else{

                    $data[$key]['effect_total']+=$effect['price_total']/$_GET[$k];
                    $data[$key]['finish_total']+=$finish['price_total']/$_GET[$k];
                    $data[$key]['unfinish_total']+=$unfinish['price_total']/$_GET[$k];
                }
            }
        }
        /*****查询所有的货币名********/

        add_system_record(sp_get_current_admin_id(), 4, 2, '查看实际利润统计列表');
        /***********展示数据*************/
        $this->assign("lists",$lists);
        $this->assign("data",$data);
        $this->assign("department",$departments);
//        $this->assign("currency_symbols",$currency_symbols);
        $this->assign("currency",$currency);
        $this->display();
    }

    public function receipt_rate_group_by_department(){
        $shipping = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();

        $result_list = D('Common/Order')->alias('o')
            ->join("__DEPARTMENT__ AS d ON d.id_department=o.id_department", "LEFT")
            ->group('o.id_department')
            ->statistics_receipt_rate(array("d.title", "o.id_department"));

        foreach($result_list as &$result){
            $result['rate_signed'] = number_format($result['count_signed']/$result['count_delivered'] * 100, 2) . '%';
        }

        if( I('request.show')== 'export_excel'){
            $row_map = array(
                array('name'=>'部门', 'key'=> 'title'),
                array('name'=>'发货单数', 'key'=> 'count_delivered'),
                array('name'=>'签收单', 'key'=> 'count_signed'),
                array('name'=>'签收率', 'key'=> 'rate_signed')
            );
            vendor('PHPExcel.ExcelManage');
            $excel = new \ExcelManage();
            $excel->export($result_list, $row_map, date("Y-m-d") . '签收率部门统计');
        }else{
            $this->assign('list',$result_list);
            $this->assign("shipping",$shipping);
            $this->display();
        }

    }

    public function receipt_rate_group_by_date(){
        $shipping = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();

        $department_id = I('request.department_id');

        $result_list = D('Common/Order')->alias('o')
            ->where(array('o.id_department'=>$department_id))
            ->group('day')
            ->statistics_receipt_rate(array("DATE(o.date_delivery) AS day"));

        foreach($result_list as &$result){
            $result['rate_signed'] = number_format($result['count_signed']/$result['count_delivered'] * 100, 2) . '%';
        }

        if( I('request.show')== 'export_excel'){
            $row_map = array(
                array('name'=>'日期', 'key'=> 'day'),
                array('name'=>'发货单数', 'key'=> 'count_delivered'),
                array('name'=>'签收单', 'key'=> 'count_signed'),
                array('name'=>'签收率', 'key'=> 'rate_signed')
            );
            vendor('PHPExcel.ExcelManage');
            $excel = new \ExcelManage();
            $excel->export($result_list, $row_map, date("Y-m-d") . '签收率日统计');
        }else{
            $this->assign('list',$result_list);
            $this->assign("shipping",$shipping);
            $this->display();
        }
    }

    public function ROI(){
        //排序数组
        $t1 = microtime(true);
        $ROI = $effective_total
            = $tw_total = $hk_total = $mc_total
            = $total
            = $expense_sum_usd = $expense_sum_rmb
            = array();

        $users_model = D('Common/Users');
        //获取组长列表
        $team_leaders = $users_model->alias('u')
            ->field('u.id, u.user_nicename as name')
            ->join("__ROLE_USER__ as ru ON ru.user_id=u.id", 'LEFT')
            ->join("__ROLE__ as r ON ru.role_id=r.id", 'LEFT')
            ->where(array('r.id'=>28))
            //->where(array('u.user_status'=>1))
            ->select();

        $team_leaders_keys = array_column($team_leaders, 'id');
        $team_leaders = array_column($team_leaders, 'name', 'id');


        $user_search = [];
        if (isset($_GET['team_leader']) && $_GET['team_leader']) {
            $user_search[]= array(
                'u.id' => $_GET['team_leader'],
                'u.superior_user_id' => $_GET['team_leader'],
                '_logic' => 'or'
            );
        }

        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $user_search[]= array(
                'd.id_department' => $_GET['department_id'],
            );
        }

        //获取优化师列表
        if(isset($_GET['view']) && $_GET['view']==1){
            $users = $users_model->alias('u')
                ->field("u.id, d.title as department_name,u.user_nicename as user_name, u.superior_user_id")
                ->join("__ROLE_USER__ as ru ON ru.user_id=u.id", 'LEFT')
                ->join("__ROLE__ as r ON ru.role_id=r.id", 'LEFT')
                ->join("__DEPARTMENT_USERS__ du ON u.id=du.id_users", 'LEFT')
                ->join("__DEPARTMENT__ as d ON d.id_department=du.id_department", 'LEFT')
                ->where($user_search)
                ->where(array(
                    'r.id' => array('IN', array(28,29,30)),
                    'd.id_department' => array('neq', 32),
                    // 'u.user_status' => 1
                ))//优化师角色ID:29 //组长ID：28
                ->group('u.id')
                ->select();
        }else{
            $users = $users_model->alias('u')
                ->field("u.id, d.title as department_name,u.user_nicename as user_name, u.superior_user_id")
                ->join("__ROLE_USER__ as ru ON ru.user_id=u.id", 'LEFT')
                ->join("__ROLE__ as r ON ru.role_id=r.id", 'LEFT')
                ->join("__DEPARTMENT_USERS__ du ON u.id=du.id_users", 'LEFT')
                ->join("__DEPARTMENT__ as d ON d.id_department=du.id_department", 'LEFT')
                ->where($user_search)
                ->where(array(
                    'r.id' => array('IN', array(28,29)),
                    'd.id_department' => array('neq', 32),
                    // 'u.user_status' => 1
                ))//优化师角色ID:29 //组长ID：28
                ->group('u.id')
                ->select();
        }
        $t2 = microtime(true);
        $order_model = D('Common/order');
        $advert_model = D('Common/Advert');

        $date_search = [];
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            if ($_GET['start_time']) $date_search[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $date_search[] = array('LT', $_GET['end_time']);
        }else{//默认搜索当月
            $current_month_first_day = date('Y-m-1');
            $date_search= array('EGT', $current_month_first_day);
        }

       // if(isset($_GET['view']) && $_GET['view']==2){
                //查出金鹰的人员
                $order_data_user_other = $order_model->alias('o')
                    ->field('o.id_department,d.title as department_name,o.id_users,u.user_nicename as user_name,u.superior_user_id')
                    ->join("__DEPARTMENT__ as d ON d.id_department=o.id_department", 'LEFT')
                    ->join("__USERS__ as u ON u.id=o.id_users", 'LEFT')
                    ->where(array('o.created_at' => $date_search))
                    ->where(array('o.id_department'=>'32'))
                    ->where(array('o.id_order_status'=> array('IN', OrderStatus::get_effective_status()))) //有效订单
                    ->where("o.id_zone IN (2,3,15,7,4,9,18,11,17,22,24)")  //只统计台湾、香港、澳门三个地区的订单(2,3,15),后来增加新加坡，日本，越南，迪拜营业额(7,4,9,18),泰国11，马来西亚17MYS，印度尼西亚22Rp，韩国24KRW
                    ->group('o.id_users')->select();

                if(!empty($order_data_user_other)){
                    $user_other[0]=[];
                    foreach($order_data_user_other as $v2){
                        $user_other[0]['id']=$v2['id_users'];
                        $user_other[0]['id_department']=$v2['id_department'];
                        $user_other[0]['department_name']=$v2['department_name'];
                        $user_other[0]['user_name']=$v2['user_name'];
                        $user_other[0]['superior_user_id']=$v2['superior_user_id'];
                        $users=array_merge($users,$user_other);
                    }

                }
      //  }
        $t3 = microtime(true);
        foreach($users as $key => $user){
            //组长
            if(in_array($user['superior_user_id'], $team_leaders_keys)){
                $users[$key]['team_leader'] = $team_leaders[$user['superior_user_id']];
            }elseif(in_array($user['id'], $team_leaders_keys)){
                $users[$key]['team_leader'] = $team_leaders[$user['id']];
            }else{
                $users[$key]['team_leader'] = '';
            }
            //马来西亚17MYS，印度尼西亚22Rp，韩国24KRW
            if($user['id_department']==32){
                //订单数据
                $order_data = $order_model->alias('o')
                    ->field('COUNT(*) AS effective_count,
                SUM(IF(id_zone=2, o.price_total, 0)) AS tw_total,
                SUM(IF(id_zone=3, o.price_total, 0)) AS hk_total,
                SUM(IF(id_zone=15, o.price_total, 0)) AS mc_total,
                SUM(IF(id_zone=7, o.price_total, 0)) AS sg_total,
                SUM(IF(id_zone=4, o.price_total, 0)) AS jp_total,
                SUM(IF(id_zone=9, o.price_total, 0)) AS vnm_total,
                SUM(IF(id_zone=18, o.price_total, 0)) AS aed_total,
                SUM(IF(id_zone=17, o.price_total, 0)) AS mys_total,
                SUM(IF(id_zone=22, o.price_total, 0)) AS rp_total,
                SUM(IF(id_zone=24, o.price_total, 0)) AS kpw_total,
                SUM(IF(id_zone=11, o.price_total, 0)) AS tha_total
                '
                    )
                    //结款管理--ROI列表：增加新加坡，日本，越南，迪拜营业额,增加泰国营业额
                    ->where(array('o.created_at' => $date_search))
                    ->where(array('o.id_users'=> $user['id'],'o.id_department'=>32))
                    ->where(array('o.id_order_status'=> array('IN', OrderStatus::get_effective_status()))) //有效订单
                    ->where("o.id_zone IN (2,3,15,7,4,9,18,11,17,22,24)")  //只统计台湾、香港、澳门三个地区的订单(2,3,15),后来增加新加坡，日本，越南，迪拜营业额(7,4,9,18),后来增加tai营业额(11)
                    ->group('o.id_users')->find();
            }else{
                //订单数据
                $order_data = $order_model->alias('o')
                    ->field('COUNT(*) AS effective_count,
                SUM(IF(id_zone=2, o.price_total, 0)) AS tw_total,
                SUM(IF(id_zone=3, o.price_total, 0)) AS hk_total,
                SUM(IF(id_zone=15, o.price_total, 0)) AS mc_total,
                SUM(IF(id_zone=7, o.price_total, 0)) AS sg_total,
                SUM(IF(id_zone=4, o.price_total, 0)) AS jp_total,
                SUM(IF(id_zone=9, o.price_total, 0)) AS vnm_total,
                SUM(IF(id_zone=18, o.price_total, 0)) AS aed_total,
                SUM(IF(id_zone=17, o.price_total, 0)) AS mys_total,
                SUM(IF(id_zone=22, o.price_total, 0)) AS rp_total,
                SUM(IF(id_zone=24, o.price_total, 0)) AS kpw_total,
                SUM(IF(id_zone=11, o.price_total, 0)) AS tha_total
                '
                    )
                    //结款管理--ROI列表：增加新加坡，日本，越南，迪拜营业额,增加泰国营业额
                    ->where(array('o.created_at' => $date_search))
                    ->where(array('o.id_users'=> $user['id'],'o.id_department'=>array('neq', 32)))
                    ->where(array('o.id_order_status'=> array('IN', OrderStatus::get_effective_status()))) //有效订单
                    ->where("o.id_zone IN (2,3,15,7,4,9,18,11,17,22,24)")  //只统计台湾、香港、澳门三个地区的订单(2,3,15),后来增加新加坡，日本，越南，迪拜营业额(7,4,9,18),后来增加tai营业额(11)
                    ->group('o.id_users')->find();
            }
            $sql=$order_model->getLastSql();
           // var_dump($order_data);die;
            $users[$key]['effective_count'] = $effective_count[$key] = !empty($order_data) ? $order_data['effective_count'] : 0;
            $tw_total[$key] = !empty($order_data) ? $order_data['tw_total'] : 0;
            $hk_total[$key] = !empty($order_data) ? $order_data['hk_total'] : 0;
            $mc_total[$key] = !empty($order_data) ? $order_data['mc_total'] : 0;
            $sg_total[$key] = !empty($order_data) ? $order_data['sg_total'] : 0;
            $jp_total[$key] = !empty($order_data) ? $order_data['jp_total'] : 0;
            $vnm_total[$key] = !empty($order_data) ? $order_data['vnm_total'] : 0;
            $aed_total[$key] = !empty($order_data) ? $order_data['aed_total'] : 0;
            $tha_total[$key] = !empty($order_data) ? $order_data['tha_total'] : 0;
            $mys_total[$key] = !empty($order_data) ? $order_data['mys_total'] : 0;
            $rp_total[$key] = !empty($order_data) ? $order_data['rp_total'] : 0;
            $kpw_total[$key] = !empty($order_data) ? $order_data['kpw_total'] : 0;
            //广告数据
            $advert_data = $advert_model->alias('a')
                ->field("SUM(ad.expense) AS expense_sum")
                ->join("__ADVERT_DATA__ AS ad ON ad.advert_id=a.advert_id", 'LEFT')
                ->where(array('ad.id_users_today'=> $user['id']))
                ->where(array('ad.id_zone'=>array('IN', array(2,3,15,7,4,9,18,11,17,22,24))))
                ->where(array('ad.conversion_at' => $date_search))
                ->group('ad.id_users_today')->find();
            $expense_sum_usd[$key] = !empty($advert_data) ? $advert_data['expense_sum'] : 0;
            $expense_sum_rmb[$key] = $this->_exchange($expense_sum_usd[$key], 'USD');

            $total[$key] =  $this->_exchange($tw_total[$key], 'TWD') +
                            $this->_exchange($hk_total[$key], 'HKD') +
                            $this->_exchange($mc_total[$key], 'MOP')+
                            $this->_exchange($sg_total[$key], 'SG')+
                            $this->_exchange($jp_total[$key], 'JP')+
                            $this->_exchange($vnm_total[$key], 'VNM')+
                            $this->_exchange($aed_total[$key], 'AED')+
                            $this->_exchange($mys_total[$key], 'MYS')+
                            $this->_exchange($rp_total[$key], 'RP')+
                            $this->_exchange($kpw_total[$key], 'KPW')+
                            $this->_exchange($tha_total[$key], 'THA')
            ;

            $ROI[$key] = $expense_sum_rmb[$key] == 0 ? 0 : floatval($total[$key]) / $expense_sum_rmb[$key];

            $users[$key]['tw_total'] = number_format($tw_total[$key], 2);
            $users[$key]['hk_total'] = number_format($hk_total[$key], 2);
            $users[$key]['mc_total'] = number_format($mc_total[$key], 2);
            $users[$key]['sg_total'] = number_format($sg_total[$key], 2);
            $users[$key]['jp_total'] = number_format($jp_total[$key], 2);
            $users[$key]['vnm_total'] = number_format($vnm_total[$key], 2);
            $users[$key]['aed_total'] = number_format($aed_total[$key], 2);
            $users[$key]['tha_total'] = number_format($tha_total[$key], 2);
            $users[$key]['mys_total'] = number_format($mys_total[$key], 2);
            $users[$key]['rp_total'] = number_format($rp_total[$key], 2);
            $users[$key]['kpw_total'] = number_format($kpw_total[$key], 2);
            $users[$key]['total'] = number_format($total[$key], 2);
            $users[$key]['expense_sum_usd'] = number_format($expense_sum_usd[$key], 2);
            $users[$key]['expense_sum_rmb'] = number_format($expense_sum_rmb[$key], 2);
            $users[$key]['ROI'] = number_format($ROI[$key], 2);
        }
        $t4 = microtime(true);
        $sort_by = !empty(I('request.sort_by')) ? I('request.sort_by') : 'ROI';
        if(!empty($users) && !isset($$sort_by)){
            echo '未知排序方式';exit;
        }else{
            $sort = strtolower(I('request.sort'))=='asc' ? SORT_ASC : SORT_DESC;
            array_multisort($$sort_by, $sort, SORT_NUMERIC, $users);
        }
        if( I('request.show')== 'export_excel'){
            $row_map = array(
                array('name'=>'部门', 'key'=> 'department_name'),
                array('name'=>'组长', 'key'=> 'team_leader'),
                array('name'=>'姓名', 'key'=> 'user_name'),
                array('name'=>'订单', 'key'=> 'effective_count'),
                array('name'=>'营业额(澳门)', 'key'=> 'mc_total'),
                array('name'=>'营业额(台湾)', 'key'=> 'tw_total'),
                array('name'=>'营业额(香港)', 'key'=> 'hk_total'),
                array('name'=>'营业额(新加坡)', 'key'=> 'sg_total'),
                array('name'=>'营业额(日本)', 'key'=> 'jp_total'),
                array('name'=>'营业额(越南)', 'key'=> 'vnm_total'),
                array('name'=>'营业额(迪拜)', 'key'=> 'aed_total'),
                array('name'=>'营业额(泰国)', 'key'=> 'tha_total'),
                array('name'=>'营业额(马来西亚)', 'key'=> 'mys_total'),
                array('name'=>'营业额(韩国)', 'key'=> 'kpw_total'),
                array('name'=>'营业额(印尼)', 'key'=> 'rp_total'),
                array('name'=>'总营业额', 'key'=> 'total'),
                array('name'=>'广告费(美元)', 'key'=> 'expense_sum_usd'),
                array('name'=>'广告费(人民币)', 'key'=> 'expense_sum_rmb'),
                array('name'=>'ROI', 'key'=> 'ROI'),
            );
            vendor('PHPExcel.ExcelManage');
            $excel = new \ExcelManage();
            $excel->export($users, $row_map, date("Y-m-d") . 'ROI统计');
        }else{
            $this->assign('t1', $t1);
            $this->assign('t2', $t2);
            $this->assign('t3', $t3);
            $this->assign('t4', $t4);
            $this->assign('sql', $sql);
            $departments = D('Common/Department')->where('type=1')->cache(true, 3600)->select();
            $this->assign('team_leaders',$team_leaders);
            $this->assign('departments',$departments);
            $this->assign('list',$users);
            $this->display();
        }
    }

    /**
     * 转换成人民币
     * @param $value string
     * @param $code string
     * @param null $exchange_rate float
     * @return mixed
     */
    private function _exchange($value, $code, $exchange_rate=null){
        switch($code){
            case 'TWD':
                $exchange_rate = is_null($exchange_rate) ? 4.7 : $exchange_rate;
                $value /= $exchange_rate;
                break;
            case 'HKD':
                $exchange_rate = is_null($exchange_rate) ? 0.89 : $exchange_rate;
                $value *= $exchange_rate;
                break;
            case 'MOP':
                $exchange_rate = is_null($exchange_rate) ? 0.86 : $exchange_rate;
                $value *= $exchange_rate;
                break;
                //新加坡，日本，越南，迪拜
            case 'SG':
                $exchange_rate = is_null($exchange_rate) ? 4.9676 : $exchange_rate;
                $value *= $exchange_rate;
                break;
            case 'JP':
                $exchange_rate = is_null($exchange_rate) ? 16.9 : $exchange_rate;
                $value /= $exchange_rate;
                break;
            case 'VNM':
                $exchange_rate = is_null($exchange_rate) ? 0.0002986 : $exchange_rate;
                $value *= $exchange_rate;
                break;
            case 'AED':
                $exchange_rate = is_null($exchange_rate) ? 1.874 : $exchange_rate;
                $value *= $exchange_rate;
                break;
            case 'THA':
                $exchange_rate = is_null($exchange_rate) ? 0.1997: $exchange_rate;
                $value *= $exchange_rate;
                break;
            case 'USD':
                $exchange_rate = is_null($exchange_rate) ? 6.89 : $exchange_rate;
                $value *= $exchange_rate;
                break;
            case 'MYS':
                $exchange_rate = is_null($exchange_rate) ? 1.5825 : $exchange_rate;
                $value *= $exchange_rate;
                break;
            case 'RP':
                $exchange_rate = is_null($exchange_rate) ? 0.00051 : $exchange_rate;
                $value *= $exchange_rate;
                break;
            case 'KPW':
                $exchange_rate = is_null($exchange_rate) ? 0.006 : $exchange_rate;
                $value *= $exchange_rate;
                break;
        }
        return $value;
    }

}
