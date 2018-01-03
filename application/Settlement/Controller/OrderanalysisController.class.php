<?php

namespace Settlement\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;

class OrderanalysisController extends AdminbaseController {

    protected $orderModel;

    public function _initialize() {
        parent::_initialize();
        $this->orderModel = D("Order/Order");
//        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
    }

    //订单情况分析
    public function every_month() {
        set_time_limit(0);
//        ini_set("memory_limit","-1");
        $t1 = microtime(true);
        $M = new \Think\Model;
        /* @var $ord_model \Common\Model\OrderModel */
        $ord_model = D("Order/Order");
        $ord_ship_table = D('Order/OrderShipping')->getTableName();
        $ord_sett = D('Order/OrderSettlement');
        $ord_table = $ord_model->getTableName();
        $ord_sett_table = $ord_sett->getTableName();

        $where = array();
        $department = M('Department')->where('type=1')->select();
        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where[] = array('o.id_department' => $_GET['department_id']);
        }

        if (isset($_GET['time']) && $_GET['time']) {
            $all_day = $this->get_the_month($_GET['time']);
            $createAtArray = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $where[] = array('o.created_at' => $createAtArray);
        } else {
            $all_day = $this->get_the_month(date('Y-m-d'));
            $createAtArray = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $where[] = array('o.created_at' => $createAtArray);
        }
        
        $department = M('Department')->where('type=1')->select();
        $department_result = array_column($department, 'id_department');
        
        $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')"; //货到付款订单，过滤已经支付的
        $where['o.id_order_status'] = array('NOT IN', array(1, 2, 3, 11, 12, 13, 14, 15));//去除无效单
        $field = 'DISTINCT o.id_order,o.id_department,o.currency_code,o.price_total,ost.amount_total,ost.status,o.id_order_status,os.status_label';
        $result = $M->table($ord_table . ' as o')
                ->join('LEFT JOIN ' . $ord_sett_table . ' as ost ON o.id_order=ost.id_order')
                ->join('__ORDER_SHIPPING__ as os ON os.id_order = o.id_order', 'left')
                ->field($field)
                ->where($where)
//                ->fetchSql(true)
                ->order('o.id_department ASC,o.id_order DESC')
//                ->limit(6000)
                ->select();
        $sql=$M->getLastSql();
        $t2 = microtime(true);
        $arr_result = array();

        foreach ($result as $k => $v) {
            if (in_array($v['id_department'], $department_result)) {
                $cwhere['code|symbol_left|symbol_right'] = $v['currency_code'];
                $currency = M('Currency')->where($cwhere)->getField('title');
                if($currency=='台币'||$currency=='新台币') {
                    $currency = '台币';
                }
                $v['currency_code'] = $currency;
                //$v['status_label'] = M('OrderShipping')->where(array('id_order' => $v['id_order']))->getField('status_label');
                $arr_result[$v['id_department']][$v['currency_code']][] = $v;
            }
        }
        $t3 = microtime(true);
        $results = $this->get_param($arr_result,true);
        $t4 = microtime(true);
        $result_list = $this->get_every_month_param($results, $_GET['tb'], $_GET['gb'], $_GET['jpy'], $_GET['xjp']);

        $this->assign('t1', $t1);
        $this->assign('t2', $t2);
        $this->assign('t3', $t3);
        $this->assign('t4', $t4);
        $this->assign('sql', $sql);
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看订单情况分析列表');
        $this->assign('list', $result_list);
        $this->assign('department', $department);
        $this->display();
    }

    //结算统计
    public function settle_count() {
        $t1 = microtime(true);
        $M = new \Think\Model;
        /* @var $ord_model \Common\Model\OrderModel */
        $shipping = D("Common/Shipping")->where('status=1')->cache(true, 6000)->select();
        $ord_model = D("Order/Order");
        $ord_ship_table = D('Order/OrderShipping')->getTableName();
        $ord_sett = D('Order/OrderSettlement');
        $ord_table = $ord_model->getTableName();
        $ord_sett_table = $ord_sett->getTableName();

        $where = array();
        $department = M('Department')->where('type=1')->select();
        $department_result = array_column($department, 'id_department');
        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where[] = array('o.id_department' => $_GET['department_id']);
        }
        if (isset($_GET['shipping_id']) && $_GET['shipping_id']) {
            $where[] = array('o.id_shipping' => $_GET['shipping_id']);
        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            if ($_GET['start_time'])
                $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $createAtArray[] = array('LT', $_GET['end_time']);
            $where[] = array('o.created_at' => $createAtArray);
        }
        if (isset($_GET['signed_start_time']) && $_GET['signed_start_time']) {
            $createSgArray = array();
            if ($_GET['signed_start_time'])
                $createSgArray[] = array('EGT', $_GET['signed_start_time']);
            if ($_GET['signed_end_time'])
                $createSgArray[] = array('LT', $_GET['signed_end_time']);
//            $where[] = array('s.date_signed' => $createSgArray);
            $dwhere[] = array('date_signed' => $createSgArray);
            $order_shipping = M('OrderShipping')->field('id_order')->where($dwhere)->select();
            $id_orders = array_column($order_shipping,'id_order');
            $where[] = array('o.id_order'=>array('IN',$id_orders));
        }

        $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')"; //货到付款订单，过滤已经支付的

        $fieldString = 'SUM(IF(o.id_order_status=9 and os.amount_total>0,os.amount_total,0)) AS amount_total,
        SUM(IF(o.id_order_status=9 and os.amount_total>0,1,0)) AS all_order,
        SUM(IF(os.status=0 and o.id_order_status=9 and os.amount_total>0,1,0)) AS no_sett_count,        
        SUM(IF(os.`status`=0 and o.id_order_status=9 and os.amount_total>0,os.amount_total,0)) AS no_sett,
        SUM(IF(os.status=2,1,0)) AS sett_count,
        SUM(IF(os.`status`=2,os.amount_settlement,0)) AS sett,        
        o.currency_code,o.id_department';

        $list = $M->table($ord_sett_table . ' AS os ')
                ->join('LEFT JOIN ' . $ord_table . ' AS o ON os.id_order=o.id_order')
//                ->join('LEFT JOIN ' . $ord_ship_table . ' as s ON s.id_order=o.id_order')
                ->field($fieldString)
                ->where($where)
//            ->fetchSql(true)
                ->order('o.id_department ASC')
                ->group('o.id_department,o.currency_code')
                ->select();
        $sql=$M->getLastSql();
        $t2 = microtime(true);
        $arr = array();
        foreach ($list as $k => $v) {
            if (in_array($v['id_department'], $department_result)) {
                $cwhere['code|symbol_left|symbol_right'] = $v['currency_code'];
                $currency = M('Currency')->where($cwhere)->getField('title');
                if($currency=='台币'||$currency=='新台币') {
                    $currency = '台币';
                }
                $v['currency_code'] = $currency;
                $arr[$v['id_department']][] = $v;
            }
        }
        $t3 = microtime(true);
        $arr_result = array();
        foreach ($arr as $key=>$val) {
            $arr_result[$key] = $this->cur_count($arr, $key);
        }
        $t4 = microtime(true);
        $result_list = $this->get_settle_count_param($arr_result, $_GET['tb'], $_GET['gb'], $_GET['jpy'], $_GET['xjp']);
        $this->assign('t1', $t1);
        $this->assign('t2', $t2);
        $this->assign('t3', $t3);
        $this->assign('t4', $t4);
        $this->assign('sql', $sql);
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看结算统计列表');
        $this->assign('list', $result_list);
        $this->assign('department', $department);
        $this->assign("shipping", $shipping);
        $this->display();
    }

    //订单结款统计
    public function settle_every_month() {
        ini_set("memory_limit","-1");
        set_time_limit(0);
        $M = new \Think\Model;
        /* @var $ord_model \Common\Model\OrderModel */
        $ord_model = D("Order/Order");
        $department = M('Department')->where('type=1')->select();
        $department_result = array_column($department, 'id_department');
        $shipping = D("Common/Shipping")->where('status=1')->cache(true, 6000)->select();
        /* @var $ord_ship \Common\Model\OrderShippingModel */
        $ord_ship_table = D('Order/OrderShipping')->getTableName();
        /* @var $orderItem \Common\Model\OrderItemModel */
        $ord_sett = D('Order/OrderSettlement');
        $ord_table = $ord_model->getTableName();
        $ord_sett_table = $ord_sett->getTableName();
        $ordership_table=M('OrderShipping')->getTableName();        

        $where = array();
        if (isset($_GET['shipping_id']) && $_GET['shipping_id']) {
            $where[] = array('o.id_shipping' => $_GET['shipping_id']);
        }
        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where[] = array('o.id_department' => $_GET['department_id']);
        }
        if (isset($_GET['time']) && $_GET['time']) {
            $all_day = $this->get_the_month($_GET['time']);
            $createAtArray = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $where[] = array('o.created_at' => $createAtArray);
        } else {
            $all_day = $this->get_the_month(date('Y-m-d', strtotime('-1 month')));
            $createAtArray = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $where[] = array('o.created_at' => $createAtArray);
        }

        $where['_string'] = "(o.payment_method is NULL OR o.payment_method='' or o.payment_method='0')"; //货到付款订单，过滤已经支付的
        $where['o.id_order_status'] = array('NOT IN', array(1, 2, 3, 11, 12, 13, 14, 15));//去除无效单
        $field = 'DISTINCT o.id_order,o.id_department,o.id_shipping,o.currency_code,o.price_total,ost.amount_total,ost.status,o.id_order_status,ost.remark,os.status_label';
        
        $result = $M->table($ord_table . ' as o')
                ->join('LEFT JOIN ' . $ord_sett_table . ' as ost ON o.id_order=ost.id_order')
                ->join('LEFT JOIN ' . $ordership_table . ' as os ON o.id_order=os.id_order')                
                ->field($field)
                ->where($where)
//                ->fetchSql(true)
                ->order('o.id_department ASC,o.id_shipping ASC')
                ->select();        


        $arr_result = array();
        foreach ($result as $key => $val) {
            if (in_array($val['id_department'], $department_result)) {
                $arr_result[$val['id_department']][$val['id_shipping']][] = $val;
            }
        }
//        foreach ($arr_result as $arr_key => $arr_val) {
//            foreach ($arr_val as $key => $val) {
//                foreach ($val as $k => $v) {
//                    $arr_result[$arr_key][$key][$k]['status_label'] = M('OrderShipping')->where(array('id_order' => $v['id_order']))->getField('status_label');
//                }
//            }
//        }        

        $results = $this->get_param($arr_result);

        $result_list = $this->get_settle_every_month_param($results, $_GET['tb'], $_GET['gb'], $_GET['jpy'], $_GET['xjp']);

        add_system_record(sp_get_current_admin_id(), 4, 4, '查看订单结算统计');

        $this->assign('list', $result_list);
        $this->assign("shipping", $shipping);
        $this->assign('department', $department);
        $this->display();
    }
    
    //订单结款统计数据处理
    protected function cur_count($array,$id_department) {
        $item=array();
        foreach ($array[$id_department] as $k=>$v) {
            if(!isset($item[$v['currency_code']])){
                $item[$v['currency_code']]=$v;
            }else{
                $item[$v['currency_code']]['amount_total']+=$v['amount_total'];
                $item[$v['currency_code']]['all_order']+=$v['all_order'];
                $item[$v['currency_code']]['no_sett_count']+=$v['no_sett_count'];
                $item[$v['currency_code']]['no_sett']+=$v['no_sett'];
                $item[$v['currency_code']]['sett_count']+=$v['sett_count'];
                $item[$v['currency_code']]['sett']+=$v['sett'];
            }
        }
        
        return $item;
    }
    
    //汇率转换
    protected function get_every_month_param($array, $tb, $gb, $jpy, $xjp) {
        if (is_array($array)) {
            foreach ($array as $keys => $vals) {
                foreach ($vals as $key => $val) {
                    if ($val['currency_code'] == '台币') {
                        if (isset($tb) && $tb) {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] / $tb, 2);
                            $array[$keys][$key]['ship_price'] = round($val['ship_price'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] / $tb, 2); //台币汇率   
                            $array[$keys][$key]['sesults_section_price'] = round($val['sesults_section_price'] / $tb, 2); //台币汇率   
                        }
                    }
                    if ($val['currency_code'] == '港币') {
                        if (isset($gb) && $gb) {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] * $gb, 2);
                            $array[$keys][$key]['ship_price'] = round($val['ship_price'] * $gb, 2); //台币汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] * $gb, 2); //台币汇率   
                            $array[$keys][$key]['sesults_section_price'] = round($val['sesults_section_price'] * $gb, 2); //台币汇率                  
                        }
                    }
                    if ($val['currency_code'] == '新加坡元') {
                        if (isset($xjp) && $xjp) {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] / $xjp, 2);
                            $array[$keys][$key]['ship_price'] = round($val['ship_price'] / $xjp, 2); //台币汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] / $xjp, 2); //台币汇率   
                            $array[$keys][$key]['sesults_section_price'] = round($val['sesults_section_price'] / $xjp, 2); //台币汇率                   
                        }
                    }
                    if ($val['currency_code'] == '日元') {
                        if (isset($jpy) && $jpy) {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] / $jpy, 2);
                            $array[$keys][$key]['ship_price'] = round($val['ship_price'] / $jpy, 2); //台币汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] / $jpy, 2); //台币汇率   
                            $array[$keys][$key]['sesults_section_price'] = round($val['sesults_section_price'] / $jpy, 2); //台币汇率                
                        }
                    }
                }
            }

            return $array;
        }
    }

    //汇率转换
    protected function get_settle_count_param($array, $tb, $gb, $jpy, $xjp) {
        if (is_array($array)) {
            foreach ($array as $keys => $vals) {
                foreach ($vals as $key => $val) {
                    if ($val['currency_code'] == '台币') {
                        if (isset($tb) && $tb) {
                            $array[$keys][$key]['amount_total'] = round($val['amount_total'] / $tb, 2);
                            $array[$keys][$key]['sett'] = round($val['sett'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['no_sett'] = round($val['no_sett'] / $tb, 2); //台币汇率                        
                        }
                    }
                    if ($val['currency_code'] == '港币') {
                        if (isset($gb) && $gb) {
                            $array[$keys][$key]['amount_total'] = round($val['amount_total'] * $gb, 2);
                            $array[$keys][$key]['sett'] = round($val['sett'] * $gb, 2); //港币汇率
                            $array[$keys][$key]['no_sett'] = round($val['no_sett'] * $gb, 2); //港币汇率                        
                        }
                    }
                    if ($val['currency_code'] == '新加坡元') {
                        if (isset($xjp) && $xjp) {
                            $array[$keys][$key]['amount_total'] = round($val['amount_total'] / $xjp, 2);
                            $array[$keys][$key]['sett'] = round($val['sett'] / $xjp, 2); //日元汇率
                            $array[$keys][$key]['no_sett'] = round($val['no_sett'] / $xjp, 2); //日元汇率                        
                        }
                    }
                    if ($val['currency_code'] == '日元') {
                        if (isset($jpy) && $jpy) {
                            $array[$keys][$key]['amount_total'] = round($val['amount_total'] / $jpy, 2);
                            $array[$keys][$key]['sett'] = round($val['sett'] / $jpy, 2); //新加坡元汇率
                            $array[$keys][$key]['no_sett'] = round($val['no_sett'] / $jpy, 2); //新加坡元汇率                        
                        }
                    }
                }
            }

            return $array;
        }
    }
    
    //订单结款汇率转换
    protected function get_settle_every_month_param($array, $tb, $gb, $jpy, $xjp) {
        if (is_array($array)) {
            foreach ($array as $keys => $vals) {
                foreach ($vals as $key => $val) {
                    if ($val['currency_code'] == 'TWD') {
                        if (isset($tb) && $tb) {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] / $tb, 2);
                            $array[$keys][$key]['sett_price'] = round($val['sett_price'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['no_sett_price'] = round($val['no_sett_price'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['is_ship_price'] = round($val['is_ship_price'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['no_ship_price'] = round($val['no_ship_price'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['delivery_price'] = round($val['delivery_price'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['custody_price'] = round($val['custody_price'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['cargo_return_price'] = round($val['cargo_return_price'] / $tb, 2); //台币汇率
//                            $array[$keys][$key]['rejected_price'] = round($val['rejected_price']/$tb,2); //台币汇率
                            $array[$keys][$key]['no_msg_price'] = round($val['no_msg_price'] / $tb, 2); //台币汇率
                            $array[$keys][$key]['smooth_delivery_price'] = round($val['smooth_delivery_price'] / $tb, 2); //台币汇率
                        } else {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] / 4.7, 2);
                            $array[$keys][$key]['sett_price'] = round($val['sett_price'] / 4.7, 2); //台币汇率
                            $array[$keys][$key]['no_sett_price'] = round($val['no_sett_price'] / 4.7, 2); //台币汇率
                            $array[$keys][$key]['is_ship_price'] = round($val['is_ship_price'] / 4.7, 2); //台币汇率
                            $array[$keys][$key]['no_ship_price'] = round($val['no_ship_price'] / 4.7, 2); //台币汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] / 4.7, 2); //台币汇率
                            $array[$keys][$key]['delivery_price'] = round($val['delivery_price'] / 4.7, 2); //台币汇率
                            $array[$keys][$key]['custody_price'] = round($val['custody_price'] / 4.7, 2); //台币汇率
                            $array[$keys][$key]['cargo_return_price'] = round($val['cargo_return_price'] / 4.7, 2); //台币汇率
//                            $array[$keys][$key]['rejected_price'] = round($val['rejected_price']/4.7,2); //台币汇率
                            $array[$keys][$key]['no_msg_price'] = round($val['no_msg_price'] / 4.7, 2); //台币汇率
                            $array[$keys][$key]['smooth_delivery_price'] = round($val['smooth_delivery_price'] / 4.7, 2); //台币汇率
                            $array[$keys][$key]['signed_return_price'] = round($val['signed_return_price'] / 4.7, 2); //台币汇率
                        }
                    }
                    if ($val['currency_code'] == 'HKD') {
                        if (isset($gb) && $gb) {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] * $gb, 2);
                            $array[$keys][$key]['sett_price'] = round($val['sett_price'] * $gb, 2); //港币汇率
                            $array[$keys][$key]['no_sett_price'] = round($val['no_sett_price'] * $gb, 2); //港币汇率
                            $array[$keys][$key]['is_ship_price'] = round($val['is_ship_price'] / $gb, 2); //台币汇率
                            $array[$keys][$key]['no_ship_price'] = round($val['no_ship_price'] * $gb, 2); //港币汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] * $gb, 2); //港币汇率
                            $array[$keys][$key]['delivery_price'] = round($val['delivery_price'] * $gb, 2); //港币汇率
                            $array[$keys][$key]['custody_price'] = round($val['custody_price'] * $gb, 2); //港币汇率
                            $array[$keys][$key]['cargo_return_price'] = round($val['cargo_return_price'] * $gb, 2); //港币汇率
//                            $array[$keys][$key]['rejected_price'] = round($val['rejected_price']*$gb,2); //港币汇率
                            $array[$keys][$key]['no_msg_price'] = round($val['no_msg_price'] * $gb, 2); //港币汇率
                            $array[$keys][$key]['smooth_delivery_price'] = round($val['smooth_delivery_price'] * $gb, 2); //港币汇率
                        } else {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] * 0.89, 2);
                            $array[$keys][$key]['sett_price'] = round($val['sett_price'] * 0.89, 2); //港币汇率
                            $array[$keys][$key]['no_sett_price'] = round($val['no_sett_price'] * 0.89, 2); //港币汇率
                            $array[$keys][$key]['is_ship_price'] = round($val['is_ship_price'] * 0.89, 2); //台币汇率
                            $array[$keys][$key]['no_ship_price'] = round($val['no_ship_price'] * 0.89, 2); //港币汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] * 0.89, 2); //港币汇率
                            $array[$keys][$key]['delivery_price'] = round($val['delivery_price'] * 0.89, 2); //港币汇率
                            $array[$keys][$key]['custody_price'] = round($val['custody_price'] * 0.89, 2); //港币汇率
                            $array[$keys][$key]['cargo_return_price'] = round($val['cargo_return_price'] * 0.89, 2); //港币汇率
//                            $array[$keys][$key]['rejected_price'] = round($val['rejected_price']*0.89,2); //港币汇率
                            $array[$keys][$key]['no_msg_price'] = round($val['no_msg_price'] * 0.89, 2); //港币汇率
                            $array[$keys][$key]['smooth_delivery_price'] = round($val['smooth_delivery_price'] * 0.89, 2); //港币汇率
                            $array[$keys][$key]['signed_return_price'] = round($val['signed_return_price'] * 0.89, 2); //港币汇率
                        }
                    }
                    if ($val['currency_code'] == 'SGD') {
                        if (isset($xjp) && $xjp) {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] / $xjp, 2);
                            $array[$keys][$key]['sett_price'] = round($val['sett_price'] / $xjp, 2); //新加坡元汇率
                            $array[$keys][$key]['no_sett_price'] = round($val['no_sett_price'] / $xjp, 2); //新加坡元汇率
                            $array[$keys][$key]['is_ship_price'] = round($val['is_ship_price'] / $xjp, 2); //台币汇率
                            $array[$keys][$key]['no_ship_price'] = round($val['no_ship_price'] / $xjp, 2); //新加坡元汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] / $xjp, 2); //新加坡元汇率
                            $array[$keys][$key]['delivery_price'] = round($val['delivery_price'] / $xjp, 2); //新加坡元汇率
                            $array[$keys][$key]['custody_price'] = round($val['custody_price'] / $xjp, 2); //新加坡元汇率
                            $array[$keys][$key]['cargo_return_price'] = round($val['cargo_return_price'] / $xjp, 2); //新加坡元汇率
//                            $array[$keys][$key]['rejected_price'] = round($val['rejected_price']/$xjp,2); //新加坡元汇率
                            $array[$keys][$key]['no_msg_price'] = round($val['no_msg_price'] / $xjp, 2); //新加坡元汇率
                            $array[$keys][$key]['smooth_delivery_price'] = round($val['smooth_delivery_price'] / $xjp, 2); //新加坡元汇率
                        } else {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] / 0.2, 2);
                            $array[$keys][$key]['sett_price'] = round($val['sett_price'] / 0.2, 2); //新加坡元汇率
                            $array[$keys][$key]['no_sett_price'] = round($val['no_sett_price'] / 0.2, 2); //新加坡元汇率
                            $array[$keys][$key]['is_ship_price'] = round($val['is_ship_price'] / 0.2, 2); //台币汇率
                            $array[$keys][$key]['no_ship_price'] = round($val['no_ship_price'] / 0.2, 2); //新加坡元汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] / 0.2, 2); //新加坡元汇率
                            $array[$keys][$key]['delivery_price'] = round($val['delivery_price'] / 0.2, 2); //新加坡元汇率
                            $array[$keys][$key]['custody_price'] = round($val['custody_price'] / 0.2, 2); //新加坡元汇率
                            $array[$keys][$key]['cargo_return_price'] = round($val['cargo_return_price'] / 0.2, 2); //新加坡元汇率
//                            $array[$keys][$key]['rejected_price'] = round($val['rejected_price']/0.2,2); //新加坡元汇率
                            $array[$keys][$key]['no_msg_price'] = round($val['no_msg_price'] / 0.2, 2); //新加坡元汇率
                            $array[$keys][$key]['smooth_delivery_price'] = round($val['smooth_delivery_price'] / 0.2, 2); //新加坡元汇率
                            $array[$keys][$key]['signed_return_price'] = round($val['signed_return_price'] / 0.2, 2); //新加坡元汇率
                        }
                    }
                    if ($val['currency_code'] == 'JPY') {
                        if (isset($jpy) && $jpy) {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] / $jpy, 2);
                            $array[$keys][$key]['sett_price'] = round($val['sett_price'] / $jpy, 2); //日元汇率
                            $array[$keys][$key]['no_sett_price'] = round($val['no_sett_price'] / $jpy, 2); //日元汇率
                            $array[$keys][$key]['is_ship_price'] = round($val['is_ship_price'] / $jpy, 2); //台币汇率
                            $array[$keys][$key]['no_ship_price'] = round($val['no_ship_price'] / $jpy, 2); //日元汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] / $jpy, 2); //日元汇率
                            $array[$keys][$key]['delivery_price'] = round($val['delivery_price'] / $jpy, 2); //日元汇率
                            $array[$keys][$key]['custody_price'] = round($val['custody_price'] / $jpy, 2); //日元汇率
                            $array[$keys][$key]['cargo_return_price'] = round($val['cargo_return_price'] / $jpy, 2); //日元汇率
//                            $array[$keys][$key]['rejected_price'] = round($val['rejected_price']/$jpy,2); //日元汇率
                            $array[$keys][$key]['no_msg_price'] = round($val['no_msg_price'] / $jpy, 2); //日元汇率
                            $array[$keys][$key]['smooth_delivery_price'] = round($val['smooth_delivery_price'] / $jpy, 2); //日元汇率
                        } else {
                            $array[$keys][$key]['effective_price'] = round($val['effective_price'] / 16.9, 2);
                            $array[$keys][$key]['sett_price'] = round($val['sett_price'] / 16.9, 2); //日元汇率
                            $array[$keys][$key]['no_sett_price'] = round($val['no_sett_price'] / 16.9, 2); //日元汇率
                            $array[$keys][$key]['is_ship_price'] = round($val['is_ship_price'] / 16.9, 2); //台币汇率
                            $array[$keys][$key]['no_ship_price'] = round($val['no_ship_price'] / 16.9, 2); //日元汇率
                            $array[$keys][$key]['signed_price'] = round($val['signed_price'] / 16.9, 2); //日元汇率
                            $array[$keys][$key]['delivery_price'] = round($val['delivery_price'] / 16.9, 2); //日元汇率
                            $array[$keys][$key]['custody_price'] = round($val['custody_price'] / 16.9, 2); //日元汇率
                            $array[$keys][$key]['cargo_return_price'] = round($val['cargo_return_price'] / 16.9, 2); //日元汇率
//                            $array[$keys][$key]['rejected_price'] = round($val['rejected_price']/16.9,2); //日元汇率
                            $array[$keys][$key]['no_msg_price'] = round($val['no_msg_price'] / 16.9, 2); //日元汇率
                            $array[$keys][$key]['smooth_delivery_price'] = round($val['smooth_delivery_price'] / 16.9, 2); //日元汇率
                            $array[$keys][$key]['signed_return_price'] = round($val['signed_return_price'] / 16.9, 2); //日元汇率
                        }
                    }
                }
            }

            return $array;
        }
    }    
    
    protected function get_param($array,$is_other=false) {
        $results = array();
        $res = array();

        foreach ($array as $arr_keys => $arr_vals) {
            foreach ($arr_vals as $keys => $vals) {
                if($is_other){
                    $results[] = $this->get_param_other2($arr_keys, $keys, $array);
                } else {
                    $results[] = $this->get_param_other($arr_keys, $keys, $array);
                }
            }
        }
        
        foreach ($results as $key=>$val) {
            foreach($val as $kk=>$vv) {
                foreach ($vv as $k=>$v) {
                    $res[$kk][$k] = $v;
                }
            }
        }
        
        return $res;
    }

    protected function get_param_other($department_id, $shipping_id, $arr) {
        $results = array();
        $effective_price = 0;
        $sett = 0;
        $sett_price = 0;
        $no_sett = 0;
        $no_sett_price = 0;
        $no_ship = 0;
        $no_ship_price = 0;
        $signed = 0;
        $signed_price = 0;
        $delivery = 0;
        $delivery_price = 0;
        $custody = 0;
        $custody_price = 0;
        $cargo_return = 0;
        $cargo_return_price = 0;
        $no_msg = 0;
        $no_msg_price = 0;
        $smooth_delivery = 0;
        $smooth_delivery_price = 0;
        $signed_return = 0;
        $signed_return_price = 0;
        foreach ($arr[$department_id][$shipping_id] as $ks => $vs) {
            $effective_price = $effective_price+$vs['price_total'];
            if ($vs['status'] == 2 && $vs['remark'] != '退货导入') {
                $sett++;
                $sett_price = $sett_price+$vs['amount_settlement'];
            }
//            if (($vs['status'] == 0 || $vs['status'] == '' || $vs['status'] == 1) && ($vs['status_label'] != '拒收(調查處理中)' || $vs['status_label'] != '代收退貨完成' || $vs['status_label'] != '客樂得貨物退回中' || $vs['status_label'] != '退貨完成')) {
//                $no_sett++;
//                $no_sett_price = $no_sett_price+$vs['amount_total'];
//            }
            if (in_array($vs['id_order_status'], array(4, 5, 6, 7, 17)) && $vs['status'] == 0) {
                $no_ship++;
                $no_ship_price = $no_ship_price+$vs['price_total'];
            }
            if ($vs['status_label'] == '順利送達' && $vs['status'] == 0) {
                $smooth_delivery++;
                $smooth_delivery_price = $smooth_delivery_price+$vs['price_total'];
            }            
            if ($vs['status'] == 0 && ($vs['status_label'] == '不在家' || $vs['status_label'] == '公司行號休息' || $vs['status_label'] == '到所自取' || $vs['status_label'] == '另約時間' || $vs['status_label'] == '地址不明(調查處理中)' || $vs['status_label'] == '已集貨' 
                || $vs['status_label'] == '搬家(調查處理中)' || $vs['status_label'] == '調查處理中' || $vs['status_label'] == '轉寄配送中' || $vs['status_label'] == '轉運中' || $vs['status_label'] == '配送中' || $vs['status_label'] == '預備配送中')) {
                $delivery++;
                $delivery_price = $delivery_price+$vs['price_total'];
            }
            if ($vs['status_label'] == '暫置營業所保管中' && $vs['status'] == 0) {
                $custody++;
                $custody_price = $custody_price+$vs['price_total'];
            }
            if ($vs['status_label'] == '' && $vs['status'] == 0) {
                $no_msg++;
                $no_msg_price = $no_msg_price+$vs['price_total'];
            }     
            if ($vs['status'] == 0 && ($vs['status_label'] == '代收退貨完成' || $vs['status_label'] == '客樂得貨物退回中' || $vs['status_label'] == '退貨完成' || $vs['status_label'] == '拒收(調查處理中)')) {
                $cargo_return++;
                $cargo_return_price = $cargo_return_price+$vs['price_total'];
            }              
            if (($vs['status'] == 2 && $vs['remark'] != '退货导入') || ($vs['status'] == 0 && $vs['status_label'] == '順利送達')) {
                $signed++;
                $signed_price = $signed_price+$vs['price_total'];
            }
            if($vs['status'] == 2 && $vs['remark'] == '退货导入') {
                $signed_return++;
                $signed_return_price = $signed_return_price+$vs['amount_settlement'];
            }
            
            $results[$department_id][$shipping_id]['currency_code'] = $vs['currency_code'];
        }
        
        $effective = count($arr[$department_id][$shipping_id]);
        $no_sett = $no_ship+$smooth_delivery+$delivery+$custody+$no_msg;
        $no_sett_price = $no_ship_price+$smooth_delivery_price+$delivery_price+$custody_price+$no_msg_price;
        
        $results[$department_id][$shipping_id]['id_department'] = $department_id;
        $results[$department_id][$shipping_id]['id_shipping'] = $shipping_id;
        $results[$department_id][$shipping_id]['effective'] = $effective;
        $results[$department_id][$shipping_id]['effective_price'] = $effective_price;
        $results[$department_id][$shipping_id]['sett'] = $sett;
        $results[$department_id][$shipping_id]['sett_price'] = $sett_price;
        $results[$department_id][$shipping_id]['no_sett'] = $no_sett;
        $results[$department_id][$shipping_id]['no_sett_price'] = $no_sett_price;
        $results[$department_id][$shipping_id]['no_ship'] = $no_ship;
        $results[$department_id][$shipping_id]['no_ship_price'] = $no_ship_price;
        $results[$department_id][$shipping_id]['signed'] = $signed;
        $results[$department_id][$shipping_id]['signed_price'] = $signed_price;
        $results[$department_id][$shipping_id]['delivery'] = $delivery;
        $results[$department_id][$shipping_id]['delivery_price'] = $delivery_price;
        $results[$department_id][$shipping_id]['custody'] = $custody;
        $results[$department_id][$shipping_id]['custody_price'] = $custody_price;
        $results[$department_id][$shipping_id]['cargo_return'] = $cargo_return;
        $results[$department_id][$shipping_id]['cargo_return_price'] = $cargo_return_price;
        $results[$department_id][$shipping_id]['no_msg'] = $no_msg;
        $results[$department_id][$shipping_id]['no_msg_price'] = $no_msg_price;
        $results[$department_id][$shipping_id]['smooth_delivery'] = $smooth_delivery;
        $results[$department_id][$shipping_id]['smooth_delivery_price'] = $smooth_delivery_price;
        $results[$department_id][$shipping_id]['is_ship'] = $effective-$no_ship;
        $results[$department_id][$shipping_id]['is_ship_price'] = $effective_price-$no_ship_price;
        $results[$department_id][$shipping_id]['signed_return'] = $signed_return;
        $results[$department_id][$shipping_id]['signed_return_price'] = $signed_return_price;
        
        return $results;
    }
    
    protected function get_param_other2($department_id, $currency_code, $arr) {
        $results = array();
        $effective_price = 0;
        $ship_count = 0;
        $ship_price = 0;
        $signed_count = 0;
        $signed_price = 0;
        $sesults_section = 0;
        $sesults_section_price = 0;

        foreach ($arr[$department_id][$currency_code] as $ks => $vs) {
            $effective_price = $effective_price+$vs['price_total'];
            if ($vs['status'] == 2) {
                $sesults_section++;
                $sesults_section_price = $sesults_section_price+$vs['amount_total'];
            }
            if (in_array($vs['id_order_status'], array(4, 5, 6, 7, 17)) && $vs['status'] == 0) {
                $no_ship++;
                $no_ship_price = $no_ship_price+$vs['price_total'];            }
            
            if ($vs['status'] == 2 || ($vs['status'] == 0 && $vs['status_label'] == '順利送達')) {
                $signed_count++;
                $signed_price = $signed_price+$vs['price_total'];
            }
        }
        
        $effective = count($arr[$department_id][$currency_code]);
        $ship_count = $effective-$no_ship;
        $ship_price = $effective_price-$no_ship_price;
        
        $results[$department_id][$currency_code]['id_department'] = $department_id;
        $results[$department_id][$currency_code]['currency_code'] = $currency_code;
        $results[$department_id][$currency_code]['effective'] = $effective;
        $results[$department_id][$currency_code]['effective_price'] = $effective_price;
        $results[$department_id][$currency_code]['ship_count'] = $ship_count;
        $results[$department_id][$currency_code]['ship_price'] = $ship_price;
        $results[$department_id][$currency_code]['signed_count'] = $signed_count;
        $results[$department_id][$currency_code]['signed_price'] = $signed_price;
        $results[$department_id][$currency_code]['sesults_section'] = $sesults_section;
        $results[$department_id][$currency_code]['sesults_section_price'] = $sesults_section_price;
        
        return $results;
    }

    //获取指定日期所在月的第一天和最后一天
    protected function get_the_month($date) {
        $firstday = date("Y-m-01 00:00:00", strtotime($date));
        $lastday = date("Y-m-d 00:00:00", strtotime("$firstday +1 month"));
        return array('first' => $firstday, 'last' => $lastday);
    }

}
