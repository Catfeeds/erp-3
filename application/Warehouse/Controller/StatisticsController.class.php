<?php

namespace Warehouse\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;

/**
 * 订单统计
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */
class StatisticsController extends AdminbaseController {

    protected $order, $page;

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->page = $_SESSION['set_page_row'] ? (int) $_SESSION['set_page_row'] : 20;
    }

    public function every_day() {
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");

        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time'])
                $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $create_at[] = array('LT', $_GET['end_time']);
            $where['created_at'] = $create_at;
        }else {
            $where['id_order_status'] = array('GT', 0);
        }
        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = $_GET['id_department'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where['id_warehouse'] = $_GET['id_warehouse'];
        }

//        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";
        $effective_status = OrderStatus::get_effective_status();
        //每日统计将待审核作为有效单
        array_push($effective_status, OrderStatus::VERIFICATION);
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

        $department = D('Department/Department')->where('type=1')->cache(true, 3600)->select();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        add_system_record(sp_get_current_admin_id(), 4, 4, '仓库查看订单统计');
        $this->assign("department", $department);
        $this->assign("warehouse", $warehouse);
        $this->assign("list", $selectOrder);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    public function every_day_unpicking () {
        $ordModel = D("Order/OrderRecord");
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time'])
                $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $create_at[] = array('LT', $_GET['end_time']);
            $where['orc.created_at'] = $create_at;
        }
        $where['orc.id_order_status'] =4;
        //$where['orc.desc'] =array('LIKE','%%');
       // %审核通过%' and `desc` NOT LIKE '%库存更新为%' and `desc` NOT LIKE '%更新缺货，匹配其他仓库库存%' GROUP BY `desc`
        $where['_string'] = "orc.desc LIKE '%审核%' or orc.desc LIKE '%库存更新为%' or orc.desc LIKE '%更新缺货，匹配其他仓库库存%' ";

        $field = "SUBSTRING(orc.created_at,1,10) AS set_date,COUNT(orc.id_order) AS total,
          SUM(IF(o.id_order_status=4,1,0)) AS status1,SUM(IF(o.id_order_status=5,1,0)) AS status2,
        SUM(IF(o.id_order_status=18,1,0)) AS status3,SUM(IF(o.id_order_status=7 or o.id_order_status=8 or o.id_order_status=9 or o.id_order_status=10
        or o.id_order_status=16 or o.id_order_status=23 or o.id_order_status=19 or o.id_order_status=21 or o.id_order_status=24,1,0)) AS status4";
        $count = $ordModel->alias('orc')
            ->join("__ORDER__ as o ON o.id_order=orc.id_order", "LEFT")
            ->field($field)->where($where)->order('set_date desc')->group('set_date')->select();
        $page = $this->page(count($count), 20);
        $selectOrder = $ordModel->alias('orc')
            ->join("__ORDER__ as o ON o.id_order=orc.id_order", "LEFT")
            ->field($field)->where($where)->order('set_date desc')->group('set_date')->limit($page->firstRow . ',' . $page->listRows)->select();
       // var_dump($ordModel->getLastSql());
        add_system_record(sp_get_current_admin_id(), 4, 4, '仓库查看未配货订单统计');
        $this->assign("list", $selectOrder);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }


    public function every_day_checkorder () {
        $ordModel = D("Order/Order");
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time'] && !isset($_GET['end_time'])){
                $where['SUBSTRING(o.created_at, 1, 10)'] = $_GET['start_time'];
            }else{
                if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
                $where['o.created_at']= $create_at;
                $create_at[] = array('EGT', $_GET['start_time']);
            }
        }else{
            $where['SUBSTRING(o.created_at, 1, 10)'] = date('Y-m-d');
        }

        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where['o.id_department'] = $_GET['department_id'];
        }

        $field = "SUBSTRING(o.created_at,1,10) AS set_date,COUNT(o.id_order) AS total,o.id_department";
        $count = $ordModel->alias('o')
           // ->join("__ORDER_RECORD__ as orc ON o.id_order=orc.id_order", "LEFT")
           ->field('o.id_department')
           ->where($where)->group('o.id_department')->select();
//        var_dump($ordModel->getLastSql());
//        var_dump(count($count));die;
        $page = $this->page(count($count), 20);
        $selectOrder =  $ordModel->alias('o')
       //     ->join("__ORDER_RECORD__ as orc ON o.id_order=orc.id_order", "LEFT")
            ->field($field)->where($where)->order('o.id_department ASC')->group('o.id_department')->limit($page->firstRow . ',' . $page->listRows)->select();
        $where2['orc.id_order_status'] =4;
        $where2['_string'] = "orc.desc LIKE '%审核%'";
        foreach($selectOrder as $k =>$v){
            $where2['id_department']=$v['id_department'];
            $where2['SUBSTRING(o.created_at, 1, 10)']= $where['SUBSTRING(o.created_at, 1, 10)'];
            $selectOrder2 =  $ordModel->alias('o')
                ->join("__ORDER_RECORD__ as orc ON o.id_order=orc.id_order", "LEFT")
                ->field("orc.created_at as checktime,o.created_at as creattime")->where($where2)->select();
            $overorder=0;
            foreach($selectOrder2 as $v2){

                $time=strtotime($v2['checktime'])-strtotime($v2['creattime']);
                $h=intval(date("H",strtotime($v2['creattime'])));
                if(21>=$h and $h>=9){
                    if($time>4*3600){
                        $overorder=$overorder+1;
                    }
                }else{
                    if($time>13*3600){
                        $overorder=$overorder+1;
                    }
                }
               // var_dump($all);var_dump($time);die;

            }
            $selectOrder[$k]['overorder']=$overorder;$selectOrder[$k]['check']= count($selectOrder2);
        }
        $department = M('Department')->where(array('type'=>1))->getField('id_department,title',true);
        $this->assign('department',$department);
        add_system_record(sp_get_current_admin_id(), 4, 4, '客服审单时效统计');
        $this->assign("list", $selectOrder);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }
    public function every_day_checkorder_detail () {
        $ordModel = D("Order/Order");
        if (isset($_GET['start_time']) && $_GET['start_time']) {
                $where2['SUBSTRING(o.created_at, 1, 10)'] = $_GET['start_time'];
        }else{
            $where2['SUBSTRING(o.created_at, 1, 10)'] = date('Y-m-d');
        }

        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where2['id_department'] = $_GET['department_id'];
        }
        $where2['orc.id_order_status'] =4;
        $where2['_string'] = "orc.desc LIKE '%审核%'";
        $selectOrder2 =  $ordModel->alias('o')
                ->join("__ORDER_RECORD__ as orc ON o.id_order=orc.id_order", "LEFT")
                ->field("orc.created_at as checktime,o.created_at as creattime,o.id_increment,id_department,id_zone,o.id_order")->where($where2)->select();
        $overorder=[];
        foreach($selectOrder2 as $k=>$v2){
            $time=strtotime($v2['checktime'])-strtotime($v2['creattime']);
            $h=intval(date("H",strtotime($v2['creattime'])));
            if(21>=$h and $h>=9){
                if($time>4*3600){
                   // $overorder=$overorder+1;
                    $overorder[$k]=$v2;
                }
            }else{
                if($time>13*3600){
                   // $overorder=$overorder+1;
                    $overorder[$k]=$v2;
                }
            }
        }
        $selectOrder=$overorder;
        $department = M('Department')->where(array('type'=>1))->cache(true, 6000)->getField('id_department,title',true);
        $this->assign('department',$department);
        $zone = M('Zone')->cache(true, 6000)->getField('id_zone,title',true);
        $this->assign('zone',$zone);
        add_system_record(sp_get_current_admin_id(), 4, 4, '客服审单时效统计详情');
        $this->assign("list", $selectOrder);
        $this->display();
    }
    public function status_statistics() {
        if (isset($_GET['shipping_id']) && $_GET['shipping_id']) {
            $where[] = array('o.id_shipping' => $_GET['shipping_id']);
            $total_where[] = array('id_shipping' => $_GET['shipping_id']);
        }

        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = $_GET['id_department'];
            $total_where['id_department'] = $_GET['id_department'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where['id_warehouse'] = $_GET['id_warehouse'];
            $total_where['id_warehouse'] = $_GET['id_warehouse'];
        }
        if (isset($_GET['id_zone']) && $_GET['id_zone']) {
            $where['id_zone'] = $_GET['id_zone'];
            $total_where['id_zone'] = $_GET['id_zone'];
        }

//        按照下单时间搜索，暂时去掉
//        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
//            $createAtArray = array();
//            if ($_GET['start_time'])
//                $createAtArray[] = array('EGT', $_GET['start_time']);
//            if ($_GET['end_time'])
//                $createAtArray[] = array('LT', $_GET['end_time']);
//            $where[] = array('date_purchase' => $createAtArray);
//            $total_where = array('date_purchase' => $createAtArray);
//        }
//        按照发货时间统计
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            if ($_GET['start_time'])
                $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $createAtArray[] = array('LT', $_GET['end_time']);
            $where[] = array('o.date_delivery' => $createAtArray);
            $total_where = array('o.date_delivery' => $createAtArray);
        }

        $where['_string'] = 'o.date_delivery IS NOT NULL';
        $ordModel = D("Order/Order");
        $M = new \Think\Model;
        $ordName = $ordModel->getTableName();
        $ordShipping = D("Order/OrderShipping");
        $ordShiName = $ordShipping->getTableName();
        $statusList = $ordShipping->group('summary_status_label')->select();
        $tempStatus = array();
        $setStaList = array();
        $temp_string = array();
        foreach ($statusList as $key => $status) {
            if (!in_array($status['summary_status_label'], $temp_string)) {
                $temp_string[] = $status['summary_status_label'];
                if(empty($status['summary_status_label'])) {
                    $tempStatus[] = "SUM(IF(os.`summary_status_label`=''or os.`summary_status_label` is null,1,0)) AS status" . $key;
                } else {
                    $tempStatus[] = "SUM(IF(os.`summary_status_label`='" . $status['summary_status_label'] . "',1,0)) AS status" . $key;
                }
                $setStaList['status' . $key] = !empty($status['summary_status_label']) ? $status['summary_status_label'] : '空';
            }
        }
        $tempStatus = count($tempStatus) ? ',' . implode(',', $tempStatus) : '';
        $fieldStr = "SUBSTRING(o.date_delivery,1,10) AS set_date,count(o.id_order) as count_all" . $tempStatus;

        $count = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->select();

        $page = $this->page(count($count), 20);
        $selectOrder = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->limit($page->firstRow, $page->listRows)->select();

//        暂时不展示有效单
//        if ($selectOrder) {
//            foreach ($selectOrder as $s_key => $s_item) {
//                $set_date = $s_item['set_date'];
//                $total_where['SUBSTRING(created_at,1,10)'] = $set_date;
////                $total_where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";
//                $effective = $ordModel->field('SUM(IF(`id_order_status` IN(2,3,4,5,6,7,8,9,10,16),1,0)) as effective')
//                                ->where($total_where)->find();
//                $selectOrder[$s_key]['effective'] = $effective ? $effective['effective'] : 0;
//            }
//        }
        $shipping = D("Common/Shipping")->where('status=1')->cache(true, 6000)->select();
        $shipItem = array();
        if ($shipping) {
            foreach ($shipping as $item) {
                $shipItem[$item['id_shipping']] = $item['title'];
            }
        }
        $zones = M('Zone')->field('id_zone,title')->select();
        $zones = array_column($zones,'title','id_zone');
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department = D('Common/Department')->where('type=1')->cache(true, 6000)->select();
        add_system_record(sp_get_current_admin_id(), 4, 1, '仓库查看物流状态统计');
        $this->assign('department', $department);
        $this->assign('zones', $zones);
        $this->assign("warehouse", $warehouse);
        $this->assign("shipping", $shipItem);
        $this->assign("list", $selectOrder);
        $this->assign("status_list", $setStaList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    public function status_statistics_time() {
        if (isset($_GET['shipping_id']) && $_GET['shipping_id']) {
            $where[] = array('o.id_shipping' => $_GET['shipping_id']);
            $total_where[] = array('id_shipping' => $_GET['shipping_id']);
        }

        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = $_GET['id_department'];
            $total_where['id_department'] = $_GET['id_department'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where['id_warehouse'] = $_GET['id_warehouse'];
            $total_where['id_warehouse'] = $_GET['id_warehouse'];
        }
        if (isset($_GET['id_zone']) && $_GET['id_zone']) {
            $where['id_zone'] = $_GET['id_zone'];
            $total_where['id_zone'] = $_GET['id_zone'];
        }

//        按照下单时间搜索，暂时去掉
//        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
//            $createAtArray = array();
//            if ($_GET['start_time'])
//                $createAtArray[] = array('EGT', $_GET['start_time']);
//            if ($_GET['end_time'])
//                $createAtArray[] = array('LT', $_GET['end_time']);
//            $where[] = array('date_purchase' => $createAtArray);
//            $total_where = array('date_purchase' => $createAtArray);
//        }
//        按照发货时间统计
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            if ($_GET['start_time'])
                $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $createAtArray[] = array('LT', $_GET['end_time']);
            $where[] = array('o.date_delivery' => $createAtArray);
            $total_where = array('o.date_delivery' => $createAtArray);
        }

        $where['_string'] = 'o.date_delivery IS NOT NULL';
        $ordModel = D("Order/Order");
        $M = new \Think\Model;
        $ordName = $ordModel->getTableName();
        $ordShipping = D("Order/OrderShipping");
        $ordShiName = $ordShipping->getTableName();
        $statusList = $ordShipping->group('summary_status_label')->select();
        $tempStatus = array();
        $setStaList = array();
        $temp_string = array();
        foreach ($statusList as $key => $status) {
            if (!in_array($status['summary_status_label'], $temp_string)) {
                $temp_string[] = $status['summary_status_label'];
                if(empty($status['summary_status_label'])) {
                    $tempStatus[] = "SUM(IF(os.`summary_status_label`=''or os.`summary_status_label` is null,1,0)) AS status" . $key;
                } else {
                    $tempStatus[] = "SUM(IF(os.`summary_status_label`='" . $status['summary_status_label'] . "',1,0)) AS status" . $key;
                }
                $setStaList['status' . $key] = !empty($status['summary_status_label']) ? $status['summary_status_label'] : '空';
            }
        }
        $tempStatus = count($tempStatus) ? ',' . implode(',', $tempStatus) : '';
        $fieldStr = "SUBSTRING(o.date_delivery,1,10) AS set_date,count(o.id_order) as count_all" . $tempStatus;

        $count = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->select();

        $page = $this->page(count($count), 20);
        $selectOrder = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->limit($page->firstRow, $page->listRows)->select();

        foreach($selectOrder as $k=>$v){
            unset( $where['set_date']);
            unset( $where['o.date_delivery']);
            $where['os.summary_status_label']="順利送達";
           // $where['set_date']=$v['set_date'];
            $where['o.date_delivery']=array('between',[$v['set_date']." 00:00:00",$v['set_date']." 23:59:59"]);
            //$where['os.date_signed']=array('exp','is not null');

            $selectOrder2 = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
                ->field("SUBSTRING(o.date_delivery,1,10) AS set_date,count(o.id_order) as count_all,SUBSTRING(os.date_signed,1,10) AS date_signed2")->where($where)
                ->group('date_signed2')->order('date_signed desc') ->select();

            if(empty($selectOrder2)){
                $selectOrder[$k]['rejected1']="0%";
                $selectOrder[$k]['rejected2']="0%";
                $selectOrder[$k]['rejected3']="0%";
                $selectOrder[$k]['rejected4']="0%";
            }else{
                $rejected1=0;
                $rejected2=0;
                $rejected3=0;
                $rejected4=0;
                foreach($selectOrder2 as $v2){
                   // strtotime($v['set_date'])
                    if((3*24*3600+strtotime($v2['set_date'])) > strtotime($v2['date_signed2']) && strtotime($v2['date_signed2'])>= (0*24*3600+strtotime($v2['set_date']))){
                        $rejected1=$rejected1+$v2['count_all'];
                    }else if((6*24*3600+strtotime($v2['set_date'])) > strtotime($v2['date_signed2']) && strtotime($v2['date_signed2'])>= (3*24*3600+strtotime($v2['set_date']))){
                        $rejected2=$rejected2+$v2['count_all'];
                    }elseif((10*24*3600+strtotime($v2['set_date'])) > strtotime($v2['date_signed2']) && strtotime($v2['date_signed2'])>= (6*24*3600+strtotime($v2['set_date']))){
                        $rejected3=$rejected3+$v2['count_all'];
                    }elseif(strtotime($v2['date_signed2']) >= (10*24*3600+strtotime($v2['set_date']))){
                        $rejected4=$rejected4+$v2['count_all'];
                    }
                }

                $Returns1=$rejected1/$v['count_all']*100;
                $Returns2=$rejected2/$v['count_all']*100;
                $Returns3=$rejected3/$v['count_all']*100;
                $Returns4=$rejected4/$v['count_all']*100;
                $selectOrder[$k]['rejected1']=number_format($Returns1,2).'%';
                $selectOrder[$k]['rejected2']=number_format($Returns2,2).'%';
                $selectOrder[$k]['rejected3']=number_format($Returns3,2).'%';
                $selectOrder[$k]['rejected4']=number_format($Returns4,2).'%';
            }
            //$v['set_date']+3*24*3600.">=date_signed"
        }

//        暂时不展示有效单
//        if ($selectOrder) {
//            foreach ($selectOrder as $s_key => $s_item) {
//                $set_date = $s_item['set_date'];
//                $total_where['SUBSTRING(created_at,1,10)'] = $set_date;
////                $total_where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";
//                $effective = $ordModel->field('SUM(IF(`id_order_status` IN(2,3,4,5,6,7,8,9,10,16),1,0)) as effective')
//                                ->where($total_where)->find();
//                $selectOrder[$s_key]['effective'] = $effective ? $effective['effective'] : 0;
//            }
//        }
        $shipping = D("Common/Shipping")->where('status=1')->cache(true, 6000)->select();
        $shipItem = array();
        if ($shipping) {
            foreach ($shipping as $item) {
                $shipItem[$item['id_shipping']] = $item['title'];
            }
        }
        $zones = M('Zone')->field('id_zone,title')->select();
        $zones = array_column($zones,'title','id_zone');
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department = D('Common/Department')->where('type=1')->cache(true, 6000)->select();
        add_system_record(sp_get_current_admin_id(), 4, 1, '仓库查看物流状态统计');
        $this->assign('department', $department);
        $this->assign('zones', $zones);
        $this->assign("warehouse", $warehouse);
        $this->assign("shipping", $shipItem);
        $this->assign("list", $selectOrder);
        $this->assign("status_list", $setStaList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }
    public function export_status_statistics() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();

        if (isset($_GET['shipping_id']) && $_GET['shipping_id']) {
            $where[] = array('o.id_shipping' => $_GET['shipping_id']);
            $total_where[] = array('id_shipping' => $_GET['shipping_id']);
        }

        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = $_GET['id_department'];
            $total_where['id_department'] = $_GET['id_department'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where['id_warehouse'] = $_GET['id_warehouse'];
            $total_where['id_warehouse'] = $_GET['id_warehouse'];
        }
        if (isset($_GET['id_zone']) && $_GET['id_zone']) {
            $where['id_zone'] = $_GET['id_zone'];
            $total_where['id_zone'] = $_GET['id_zone'];
        }
//        按照发货时间统计
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            if ($_GET['start_time'])
                $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $createAtArray[] = array('LT', $_GET['end_time']);
            $where[] = array('o.date_delivery' => $createAtArray);
            $total_where = array('o.date_delivery' => $createAtArray);
        }
        $ordModel = D("Order/Order");
        $M = new \Think\Model;
        $ordName = $ordModel->getTableName();
        $ordShipping = D("Order/OrderShipping");
        $ordShiName = $ordShipping->getTableName();
        $statusList = $ordShipping->group('summary_status_label')->select();
        $tempStatus = array();
        $setStaList = array();
        $temp_string = array();
        foreach ($statusList as $key => $status) {
            if (!in_array($status['summary_status_label'], $temp_string)) {
                $temp_string[] = $status['summary_status_label'];
                if(empty($status['summary_status_label'])) {
                    $tempStatus[] = "SUM(IF(os.`summary_status_label`=''or os.`summary_status_label` is null,1,0)) AS status" . $key;
                } else {
                    $tempStatus[] = "SUM(IF(os.`summary_status_label`='" . $status['summary_status_label'] . "',1,0)) AS status" . $key;
                }
                $setStaList['status' . $key] = !empty($status['summary_status_label']) ? $status['summary_status_label'] : '空';
            }
        }
        $tempStatus = count($tempStatus) ? ',' . implode(',', $tempStatus) : '';
        $fieldStr = "SUBSTRING(o.date_delivery,1,10) AS set_date,count(o.id_order) as count_all" . $tempStatus;


        $selectOrder = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->select();
        $shipping = D("Common/Shipping")->where('status=1')->cache(true, 6000)->select();
        $shipItem = array();
        if ($shipping) {
            foreach ($shipping as $item) {
                $shipItem[$item['id_shipping']] = $item['title'];
            }
        }
        $zones = M('Zone')->field('id_zone,title')->select();
        $zones = array_column($zones,'title','id_zone');
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department = D('Common/Department')->where('type=1')->cache(true, 6000)->select();

//        add_system_record(sp_get_current_admin_id(), 4, 1, '仓库查看物流状态统计');
//        $this->assign('department', $department);
//        $this->assign('zones', $zones);
//        $this->assign("warehouse", $warehouse);
//        $this->assign("shipping", $shipItem);
//        $this->assign("list", $selectOrder);
//        $this->assign("status_list", $setStaList);
//        $this->assign("page", $page->show('Admin'));
//        $this->display();
        $arrayFlip = array_flip($setStaList);

        $column = array(
            '日期', '发货单'
        );
        foreach($setStaList as $key=>$statusTitle){
            array_push($column,$statusTitle);
        }
        array_push($column,'拒收率');
        array_push($column,'退货率');
        array_push($column,'签收率');
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        $idx = 2;
        if(!empty($selectOrder)){
            foreach($selectOrder as $k =>$item){
                $dataItem = array(
                );
                foreach($item as $tag){
                   array_push($dataItem,$tag) ;
                }
                $reTitle1 = $arrayFlip['拒收'];
                $rejectedTotal = $item[$reTitle1];
                if($rejectedTotal>0){
                    $rejected = ($rejectedTotal/$item['count_all'])*100;
                    array_push($dataItem,number_format($rejected,2).'%') ;
                }else{
                    array_push($dataItem,'') ;
                }
                $reTitle1 = $arrayFlip['退貨完成'];
                $rejectedTotal = $item[$reTitle1];
                if($rejectedTotal>0){
                    $Returns = ($rejectedTotal/$item['count_all'])*100;
                    array_push($dataItem, number_format($Returns,2).'%') ;
                }else{
                    array_push($dataItem,'') ;
                }
                $reTitle1 = $arrayFlip['順利送達'];
                $rejectedTotal = $item[$reTitle1];
                if($rejectedTotal>0){
                    $Returns = ($rejectedTotal/$item['count_all'])*100;
                    array_push($dataItem,number_format($Returns,2).'%') ;
                }else{
                    array_push($dataItem,'') ;
                }
                $data[] =$dataItem;

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
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出物流状态统计');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '物流状态统计列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '物流状态统计列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();

    }
    public function export_status_statistics_time() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();

        if (isset($_GET['shipping_id']) && $_GET['shipping_id']) {
            $where[] = array('o.id_shipping' => $_GET['shipping_id']);
            $total_where[] = array('id_shipping' => $_GET['shipping_id']);
        }

        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = $_GET['id_department'];
            $total_where['id_department'] = $_GET['id_department'];
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where['id_warehouse'] = $_GET['id_warehouse'];
            $total_where['id_warehouse'] = $_GET['id_warehouse'];
        }
        if (isset($_GET['id_zone']) && $_GET['id_zone']) {
            $where['id_zone'] = $_GET['id_zone'];
            $total_where['id_zone'] = $_GET['id_zone'];
        }
//        按照发货时间统计
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            if ($_GET['start_time'])
                $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $createAtArray[] = array('LT', $_GET['end_time']);
            $where[] = array('o.date_delivery' => $createAtArray);
            $total_where = array('o.date_delivery' => $createAtArray);
        }
        $ordModel = D("Order/Order");
        $M = new \Think\Model;
        $ordName = $ordModel->getTableName();
        $ordShipping = D("Order/OrderShipping");
        $ordShiName = $ordShipping->getTableName();
        $statusList = $ordShipping->group('summary_status_label')->select();
        $tempStatus = array();
        $setStaList = array();
        $temp_string = array();
        foreach ($statusList as $key => $status) {
            if (!in_array($status['summary_status_label'], $temp_string)) {
                $temp_string[] = $status['summary_status_label'];
                if(empty($status['summary_status_label'])) {
                    $tempStatus[] = "SUM(IF(os.`summary_status_label`=''or os.`summary_status_label` is null,1,0)) AS status" . $key;
                } else {
                    $tempStatus[] = "SUM(IF(os.`summary_status_label`='" . $status['summary_status_label'] . "',1,0)) AS status" . $key;
                }
                $setStaList['status' . $key] = !empty($status['summary_status_label']) ? $status['summary_status_label'] : '空';
            }
        }
        $tempStatus = count($tempStatus) ? ',' . implode(',', $tempStatus) : '';
        $fieldStr = "SUBSTRING(o.date_delivery,1,10) AS set_date,count(o.id_order) as count_all" . $tempStatus;


        $selectOrder = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->select();
        foreach($selectOrder as $k=>$v){
            unset( $where['set_date']);
            unset( $where['o.date_delivery']);
            $where['os.summary_status_label']="順利送達";
            // $where['set_date']=$v['set_date'];
            $where['o.date_delivery']=array('between',[$v['set_date']." 00:00:00",$v['set_date']." 23:59:59"]);
            $where['os.date_signed']=array('exp','is not null');

            $selectOrder2 = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
                ->field("SUBSTRING(o.date_delivery,1,10) AS set_date,count(o.id_order) as count_all,SUBSTRING(os.date_signed,1,10) AS date_signed2")->where($where)
                ->group('date_signed2')->order('date_signed desc') ->select();

            if(empty($selectOrder2)){
                $selectOrder[$k]['rejected1']="0%";
                $selectOrder[$k]['rejected2']="0%";
                $selectOrder[$k]['rejected3']="0%";
                $selectOrder[$k]['rejected4']="0%";
            }else{
                $rejected1=0;
                $rejected2=0;
                $rejected3=0;
                $rejected4=0;

                foreach($selectOrder2 as $v2){
                    // strtotime($v['set_date'])
                    if((3*24*3600+strtotime($v2['set_date'])) > strtotime($v2['date_signed2']) && strtotime($v2['date_signed2'])>= (0*24*3600+strtotime($v2['set_date']))){
                        $rejected1=$rejected1+$v2['count_all'];
                    }else if((6*24*3600+strtotime($v2['set_date'])) > strtotime($v2['date_signed2']) && strtotime($v2['date_signed2'])>= (3*24*3600+strtotime($v2['set_date']))){
                        $rejected2=$rejected2+$v2['count_all'];
                    }elseif((10*24*3600+strtotime($v2['set_date'])) > strtotime($v2['date_signed2']) && strtotime($v2['date_signed2'])>= (6*24*3600+strtotime($v2['set_date']))){
                        $rejected3=$rejected3+$v2['count_all'];
                    }elseif(strtotime($v2['date_signed2']) >= (10*24*3600+strtotime($v2['set_date']))){
                        $rejected4=$rejected4+$v2['count_all'];
                    }
                }

                $Returns1=$rejected1/$v['count_all']*100;
                $Returns2=$rejected2/$v['count_all']*100;
                $Returns3=$rejected3/$v['count_all']*100;
                $Returns4=$rejected4/$v['count_all']*100;
                $selectOrder[$k]['rejected1']=number_format($Returns1,2).'%';
                $selectOrder[$k]['rejected2']=number_format($Returns2,2).'%';
                $selectOrder[$k]['rejected3']=number_format($Returns3,2).'%';
                $selectOrder[$k]['rejected4']=number_format($Returns4,2).'%';
            }
            //$v['set_date']+3*24*3600.">=date_signed"
        }

        $shipping = D("Common/Shipping")->where('status=1')->cache(true, 6000)->select();
        $shipItem = array();
        if ($shipping) {
            foreach ($shipping as $item) {
                $shipItem[$item['id_shipping']] = $item['title'];
            }
        }
        $zones = M('Zone')->field('id_zone,title')->select();
        $zones = array_column($zones,'title','id_zone');
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department = D('Common/Department')->where('type=1')->cache(true, 6000)->select();

        $arrayFlip = array_flip($setStaList);

        $column = array(
            '日期', '发货单','0-3天签收率','4-6天签收率','7-10天签收率','超过10天签收率','拒收率','退货率','签收率'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        $idx = 2;
        if(!empty($selectOrder)){
            foreach($selectOrder as $k =>$item){
                $dataItem = array($item['set_date'],$item['count_all'],$item['rejected1'],$item['rejected2'],$item['rejected3'],$item['rejected4']

                );
                $reTitle1 = $arrayFlip['拒收'];
                $rejectedTotal = $item[$reTitle1];
                if($rejectedTotal>0){
                    $rejected = ($rejectedTotal/$item['count_all'])*100;
                    array_push($dataItem,number_format($rejected,2).'%') ;
                }else{
                    array_push($dataItem,'') ;
                }
                $reTitle1 = $arrayFlip['退貨完成'];
                $rejectedTotal = $item[$reTitle1];
                if($rejectedTotal>0){
                    $Returns = ($rejectedTotal/$item['count_all'])*100;
                    array_push($dataItem, number_format($Returns,2).'%') ;
                }else{
                    array_push($dataItem,'') ;
                }
                $reTitle1 = $arrayFlip['順利送達'];
                $rejectedTotal = $item[$reTitle1];
                if($rejectedTotal>0){
                    $Returns = ($rejectedTotal/$item['count_all'])*100;
                    array_push($dataItem,number_format($Returns,2).'%') ;
                }else{
                    array_push($dataItem,'') ;
                }
                $data[] =$dataItem;

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
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出物流状态统计');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '物流状态统计列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '物流状态统计列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();

    }
    public function sku_statistics() {
        
        if(isset($_GET['sku_title']) && $_GET['sku_title']) {
            $pro_sku_id = M('ProductSku')->field('id_product_sku')->where(array('sku'=>array('LIKE', '%' . $_GET['sku_title'] . '%')))->select();
            $pro_sku_id = array_column($pro_sku_id, 'id_product_sku');
            $where['id_product_sku'] = array('IN',$pro_sku_id);
        }
        if(isset($_GET['pro_inner_title']) && $_GET['pro_inner_title']) {
            $pro_id = M('Product')->field('id_product')->where(array('inner_name'=>array('LIKE', '%' . $_GET['pro_inner_title'] . '%')))->select();
            $pro_id = array_column($pro_id, 'id_product');
            $where['id_product'] = array('IN',$pro_id);
        }
        if(isset($_GET['warehouse_id']) && $_GET['warehouse_id']) {
            $where['id_warehouse'] = array('EQ',$_GET['warehouse_id']);
        }  
        if(isset($_GET['department_id']) && $_GET['department_id']) {
//            $where['id_department'] = array('EQ',$_GET['department_id']);
            $pro_id = M('Product')->field('id_product')->where(array('id_department'=>$_GET['department_id']))->select();
            $pro_id = array_column($pro_id, 'id_product');
            $where['id_product'] = $pro_id ? array('IN',$pro_id) : array(0);
        }       
//        $where['ps.status'] = 1;// 使用的SKU状态
        
        $M = new \Think\Model();
        $pro_tab = M('Product')->getTableName();
        $pro_sku_tab = M('ProductSku')->getTableName();
        $order_tab = M('Order')->getTableName();
        $order_item_tab = M('OrderItem')->getTableName();
        
        $count = M('WarehouseProduct')->where($where)->count();
        $page = $this->page($count, 20);        
        $product = M('WarehouseProduct')->where($where)->order("id_warehouse ASC,id_product_sku ASC")->limit($page->firstRow . ',' . $page->listRows)->select();
        
        foreach ($product as $key=>$val) {
            $order_count = $M->table($order_tab.' as o ')->join('LEFT JOIN '.$order_item_tab.' as oi ON oi.id_order=o.id_order')->field('count(*) as count')
                    ->where(array('o.id_order_status'=>array('NOT IN',array(1, 2, 3, 11, 12, 13, 14, 15)),'oi.id_product_sku'=>$val['id_product_sku']))->find();
            $order_qh = $M->table($order_tab.' as o ')->join('LEFT JOIN '.$order_item_tab.' as oi ON oi.id_order=o.id_order')->field('count(*) as count')
                    ->where(array('o.id_order_status'=>6,'oi.id_product_sku'=>$val['id_product_sku']))->find();
            $product[$key]['order_count'] = $order_count['count'];
            $add_count = M('WarehouseAllocationStock')->field('SUM(quantity) as add_quantity')->where(array('id_product_sku'=>$val['id_product_sku']))->find();
            $product[$key]['add_count'] = $add_count['add_quantity'];
            $product[$key]['sku'] = M('ProductSku')->where(array('id_product_sku'=>$val['id_product_sku']))->getField('sku');
            $product[$key]['inner_name'] = M('Product')->where(array('id_product'=>$val['id_product']))->getField('inner_name');
            $product[$key]['qh'] = $order_qh['count'];
            $product[$key]['warehouse'] = M('Warehouse')->where(array('id_warehouse'=>$val['id_warehouse']))->getField('title');
        }
        
        $department = M('Department')->where('type=1')->cache(true,86400)->select();
        $warehouse = M('Warehouse')->where('status=1')->cache(true,86400)->select();
        $this->assign('list',$product);
        $this->assign('department',$department);
        $this->assign('warehouse',$warehouse);
        $this->assign("page", $page->show('Admin'));
        $this->assign("getData", $_GET);
        $this->display();
    }
    
    
    public function shipment_order_statistics(){
        $getData=$_GET;
        $where=[];
        $where['o.id_order_status'] = array('NOT IN',array(1,2,3,11,12,13,14,15));//去除无效单
        $cur_page = $getData['p']? : 1; //默认页数
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }        
        if($getData['id_increment']){
            $where['o.id_increment']=array('like',"%{$getData['id_increment']}%");
        }
        if ($getData['created_at_s']) {
            $where['o.created_at'][] = array('EGT', $getData['created_at_s']);
        }
        if ($getData['created_at_e']) {
            $where['o.created_at'][] = array('ELT', "{$getData['created_at_e']} 23:59:59");
        }
        if ($getData['date_delivery_s']) {
            $where['o.date_delivery'][] = array('EGT', $getData['date_delivery_s']);
        }
        if ($getData['date_delivery_e']) {
            $where['o.date_delivery'][] = array('ELT', "{$getData['date_delivery_e']} 23:59:59");
        }
        if ($getData['date_signed_s']) {
            $where['os.date_signed'][] = array('EGT', $getData['date_signed_s']);
        }
        if ($getData['date_signed_e']) {
            $where['os.date_signed'][] = array('ELT', "{$getData['date_signed_e']} 23:59:59");
        }
        if ($getData['id_department']) {
            $where['o.id_department'] = $getData['id_department'];
        }
        $departmentList = M('department')->where(array('type' => 1))->getField('id_department,title'); //业务部门
        $orderItem = M('orderItem')->getTableName();
        $ordership = M('orderShipping')->getTableName();
        $warehousePro = M('warehouseProduct')->getTableName();
        $subQuery = M('orderRecord ord')->field('max(ord.created_at)')->where("ord.id_order_status=5 and ord.id_order=o.id_order")->buildSql(); //到货时间
        $fields = "o.id_order,o.id_warehouse,o.id_department,o.id_increment,o.created_at,{$subQuery} arrival_time,o.date_delivery,os.date_signed"; //
        $count = M('order')->alias('o')->join("$ordership os on os.id_order=o.id_order", 'left')->where($where)->count();
        $orderList = M('order')->alias('o')
                ->field($fields)
                ->join("$ordership os on os.id_order=o.id_order", 'left')
                ->where($where)
                ->page("$cur_page,$this->page")
                ->order('o.id_order desc')
                ->select();


        foreach ($orderList as &$item) {
            $whereItem = [];
            $whereItem['oi.id_order'] = $item['id_order'];
            $whereItem['wp.id_warehouse'] = $item['id_warehouse'];
            $item['skuarr'] = M('orderItem oi')->join("$warehousePro wp on wp.id_product_sku=oi.id_product_sku and wp.id_product=oi.id_product", 'left')->field('oi.sku,wp.quantity,oi.id_product')->where($whereItem)->select();
            $delivery_days=(strtotime(date('Y-m-d',  strtotime($item['date_delivery']))) - strtotime(date('Y-m-d',  strtotime($item['created_at'])))) /86400;
            $signed_days=(strtotime(date('Y-m-d',  strtotime($item['date_signed']))) - strtotime(date('Y-m-d',  strtotime($item['created_at'])))) /86400;
            $no_delivery_days = (strtotime(date('Y-m-d')) - strtotime(date('Y-m-d',  strtotime($item['created_at'])))) /86400;

            $item['no_delivery_days'] = $item['arrival_time'] ? '--' : $no_delivery_days;
            $item['delivery_days'] = $delivery_days > 0?$delivery_days: '';
            $item['signed_days'] = $signed_days> 0?$signed_days : '';
        }
        $page = $this->page($count, $this->page);
        $this->assign("page", $page->show('Admin'));
        $this->assign("getData", $getData);
        $this->assign("orderList", $orderList);
        $this->assign("departmentList", $departmentList);

        $this->display();
    }

    /**
     * 导出发货订单
     */
    public function shipment_order_export() {
        $getData = $_GET;
        $where = [];
        $where['o.id_order_status'] = array('NOT IN', array(1, 2, 3, 11, 12, 13, 14, 15)); //去除无效单     
        if ($getData['id_increment']) {
            $where['o.id_increment'] = array('like', "%{$getData['id_increment']}%");
        }
        if ($getData['created_at_s']) {
            $where['o.created_at'][] = array('EGT', $getData['created_at_s']);
        }
        if ($getData['created_at_e']) {
            $where['o.created_at'][] = array('ELT', "{$getData['created_at_e']} 23:59:59");
        }
        if ($getData['date_delivery_s']) {
            $where['o.date_delivery'][] = array('EGT', $getData['date_delivery_s']);
        }
        if ($getData['date_delivery_e']) {
            $where['o.date_delivery'][] = array('ELT', "{$getData['date_delivery_e']} 23:59:59");
        }
        if ($getData['date_signed_s']) {
            $where['os.date_signed'][] = array('EGT', $getData['date_signed_s']);
        }
        if ($getData['date_signed_e']) {
            $where['os.date_signed'][] = array('ELT', "{$getData['date_signed_e']} 23:59:59");
        }
        if ($getData['id_department']) {
            $where['o.id_department'] = $getData['id_department'];
        }
        $departmentList = M('department')->where(array('type' => 1))->getField('id_department,title'); //业务部门
        $ordership = M('orderShipping')->getTableName();
        $warehousePro = M('warehouseProduct')->getTableName();
        $subQuery = M('orderRecord ord')->field('max(ord.created_at)')->where("ord.id_order_status=5 and ord.id_order=o.id_order")->buildSql(); //到货时间
        $fields = "o.id_order,o.id_warehouse,o.id_department,o.id_increment,o.created_at,{$subQuery} arrival_time,o.date_delivery,os.date_signed"; //
        $orderList = M('order')->alias('o')
                ->field($fields)
                ->join("$ordership os on os.id_order=o.id_order", 'left')
                ->where($where)
                ->order('o.id_order desc')
                ->select();

        $str = "订单编号,业务部门,下单时间,到货时间,库存数量,发货时间,未到货时间,签收时间,发货天数,签收天数\n";
        foreach ($orderList as $item) {
            $whereItem = [];
            $whereItem['oi.id_order'] = $item['id_order'];
            $whereItem['wp.id_warehouse'] = $item['id_warehouse'];
            $item['skuarr'] = M('orderItem oi')->join("$warehousePro wp on wp.id_product_sku=oi.id_product_sku and wp.id_product=oi.id_product", 'left')->field('oi.sku,wp.quantity,oi.id_product')->where($whereItem)->select();
            $delivery_days=(strtotime(date('Y-m-d',  strtotime($item['date_delivery']))) - strtotime(date('Y-m-d',  strtotime($item['created_at'])))) /86400;
            $signed_days=(strtotime(date('Y-m-d',  strtotime($item['date_signed']))) - strtotime(date('Y-m-d',  strtotime($item['created_at'])))) /86400;
            $no_delivery_days = (strtotime(date('Y-m-d')) - strtotime(date('Y-m-d',  strtotime($item['created_at'])))) /86400;

            $item['no_delivery_days'] = $item['arrival_time'] ? '--' : $no_delivery_days;
            $item['delivery_days'] = $delivery_days > 0?$delivery_days: '';
            $item['signed_days'] = $signed_days> 0?$signed_days : '';
            $skustr='';
            foreach ($item['skuarr'] as $sku){
                $skustr.=$sku['sku'].'*'.$sku['quantity'].';';
            }
            $skustr=  trim($skustr, ';');
            $str.=
                    $item['id_increment'] . "\t," .
                    $departmentList[$item['id_department']].','.
                    $item['created_at'] . "\t," .
                    $item['arrival_time'] . "\t," .
                    $skustr . "," .
                    $item['date_delivery'] . "\t," .
                    $item['no_delivery_days'] . "\t," .
                    $item['date_signed'] . "\t," .
                    $item['delivery_days'] . "," .
                    $item['signed_days'] . "\n" ;
                   
        }
        $filename = date('Ymd') . '.csv'; //设置文件名
        $this->export_csv($filename, $str); //导出
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
