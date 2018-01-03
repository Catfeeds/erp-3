<?php
namespace Order\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use Department\Controller\DepartmentTreeController;

/**
 * 订单统计
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */
class StatisticsController extends AdminbaseController{
	protected $order,$page;
	public function _initialize() {
		parent::_initialize();
		$this->order=D("Order/Order");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
	}

    /**
     * 每天订单统计
     */
    public function every_day(){
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        $de_tree = DepartmentTreeController::getDepartment();
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time']) $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
            $where['created_at']= $create_at;
        }else{
            $where['id_order_status'] = array('GT',0);
        }
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }
        
        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";

        $effective_status = OrderStatus::get_effective_status();
        //每日统计将待审核作为有效单
        array_push($effective_status, OrderStatus::VERIFICATION);

        //存在action 则为每日部门统计   --Lily 2017-11-15
        if(isset($_GET['department']) && $_GET['department']=='deartment_total'){
            $id_depar = "id_department,";
            $id_department = 'id_department';
        }
        $field = "SUBSTRING(created_at,1,10) AS set_date,".$id_depar."SUM(IF(`id_order_status` IN(".implode(',', $effective_status)."),1,0)) as effective,
        count(id_order) as total,
        SUM(IF(`id_order_status` IN(10,11,12,13,14,15),1,0)) as invalid,
        SUM(IF(`id_order_status`=1,1,0)) AS status1,SUM(IF(`id_order_status`=2,1,0)) AS status2,
        SUM(IF(`id_order_status`=3,1,0)) AS status3,SUM(IF(`id_order_status`=4,1,0)) AS status4,
        SUM(IF(`id_order_status`=5,1,0)) AS status5,SUM(IF(`id_order_status`=6,1,0)) AS status6,
        SUM(IF(`id_order_status`=7,1,0)) AS status7,SUM(IF(`id_order_status`=8,1,0)) AS status8,
        SUM(IF(`id_order_status`=9,1,0)) AS status9,
        SUM(IF(`id_order_status`=10,1,0)) AS status10,SUM(IF(`id_order_status`=11,1,0)) AS status11,
        SUM(IF(`id_order_status`=12,1,0)) AS status12,SUM(IF(`id_order_status`=13,1,0)) AS status13,
        SUM(IF(`id_order_status`=14,1,0)) AS status14,SUM(IF(`id_order_status`=15,1,0)) AS status15,
        SUM(IF(`id_order_status`=29,1,0)) AS status29,SUM(IF(`id_order_status`=30,1,0)) AS status30,
        SUM(IF(`id_order_status`=28,1,0)) AS status28,SUM(IF(`id_order_status`=16,1,0)) AS status16,
        SUM(IF(`id_order_status`=18,1,0)) AS status18,SUM(IF(`id_order_status`=19,1,0)) AS status19,
        SUM(IF(`id_order_status`=21,1,0)) AS status21,SUM(IF(`id_order_status`=23,1,0)) AS status23,
        SUM(IF(`id_order_status`=17,1,0)) AS status17,SUM(IF(`id_order_status`=22,1,0)) AS status22,
        SUM(IF(`id_order_status`=24,1,0)) AS status24,SUM(IF(`id_order_status`=25,1,0)) AS status25,
        SUM(IF(`id_order_status`=26,1,0)) AS status26,SUM(IF(`id_order_status`=27,1,0)) AS status27
        ";
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        
        if(isset($_GET['department']) && $_GET['department']=='deartment_total'){
            $count = $ordModel->field($field)->where($where)
            ->order('set_date desc')
            ->group('set_date,id_department')->select();
        $page = $this->page(count($count), 20);
            $selectData = $ordModel->field($field)->where($where)->order('set_date desc')
            ->group("set_date,id_department")->limit($page->firstRow . ',' . $page->listRows)->select();
            foreach ($selectData as $k => $v) {
              foreach($de_tree as $kk=>$vv){
               if(in_array($department[$v['id_department']], $vv)){ 
                  foreach($vv as $de_id=>$de_name){
                    if($de_id == $v['id_department']){
                      $selectOrder[$v['set_date']]['num'] += 1;
                      $selectOrder[$v['set_date']][$kk][$k]['department'] = $department[$v['id_department']];
                      $selectOrder[$v['set_date']][$kk][$k]['total'] = $v['total'];
                      $selectOrder[$v['set_date']][$kk][$k]['effective'] = $v['effective'];
                      $selectOrder[$v['set_date']][$kk][$k]['invalid'] = $v['invalid'];
                      $selectOrder[$v['set_date']][$kk][$k]['status1'] = $v['status1'];
                      $selectOrder[$v['set_date']][$kk][$k]['status2'] = $v['status2'];
                      $selectOrder[$v['set_date']][$kk][$k]['status3'] = $v['status3'];
                      $selectOrder[$v['set_date']][$kk][$k]['status4'] = $v['status4'];
                      $selectOrder[$v['set_date']][$kk][$k]['status5'] = $v['status5'];
                      $selectOrder[$v['set_date']][$kk][$k]['status6'] = $v['status6'];
                      $selectOrder[$v['set_date']][$kk][$k]['status7'] = $v['status7'];
                      $selectOrder[$v['set_date']][$kk][$k]['status8'] = $v['status8'];
                      $selectOrder[$v['set_date']][$kk][$k]['status9'] = $v['status9'];
                      $selectOrder[$v['set_date']][$kk][$k]['status10'] = $v['status10'];
                      $selectOrder[$v['set_date']][$kk][$k]['status11'] = $v['status11'];
                      $selectOrder[$v['set_date']][$kk][$k]['status12'] = $v['status12'];
                      $selectOrder[$v['set_date']][$kk][$k]['status13'] = $v['status13'];
                      $selectOrder[$v['set_date']][$kk][$k]['status14'] = $v['status14'];
                      $selectOrder[$v['set_date']][$kk][$k]['status15'] = $v['status15'];
                      $selectOrder[$v['set_date']][$kk][$k]['status29'] = $v['status29'];
                      $selectOrder[$v['set_date']][$kk][$k]['status30'] = $v['status30'];
                      $selectOrder[$v['set_date']][$kk][$k]['status28'] = $v['status28'];
                      $selectOrder[$v['set_date']][$kk][$k]['status16'] = $v['status16'];
                      $selectOrder[$v['set_date']][$kk][$k]['status18'] = $v['status18'];
                      $selectOrder[$v['set_date']][$kk][$k]['status19'] = $v['status19'];
                      $selectOrder[$v['set_date']][$kk][$k]['status21'] = $v['status21'];
                      $selectOrder[$v['set_date']][$kk][$k]['status23'] = $v['status23'];
                      $selectOrder[$v['set_date']][$kk][$k]['status17'] = $v['status17'];
                      $selectOrder[$v['set_date']][$kk][$k]['status22'] = $v['status22'];
                      $selectOrder[$v['set_date']][$kk][$k]['status24'] = $v['status24'];
                      $selectOrder[$v['set_date']][$kk][$k]['status25'] = $v['status25'];
                      $selectOrder[$v['set_date']][$kk][$k]['status26'] = $v['status26'];
                      $selectOrder[$v['set_date']][$kk][$k]['status27'] = $v['status27'];
                    }else{
                      continue;
                    }
                  }
                 }
              }
            }
        }else{
            $count = $ordModel->field($field)->where($where)
            ->order('set_date desc')
            ->group('set_date')->select();
        $page = $this->page(count($count), 20);
          $selectOrder = $ordModel->field($field)->where($where)->order('set_date desc')
            ->group('set_date')->limit($page->firstRow . ',' . $page->listRows)->select();
         }
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看DF订单统计');
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("list",$selectOrder);
        //$this->assign("shipping",$shipping);
        $this->assign("page",$page->show('Admin'));
        if(isset($_GET['department']) && $_GET['department']=='deartment_total'){
            $this->display("every_day_department");
        }else{
           $this->display(); 
        }
        
    }

    /**
    * 每日统计的导出  --Lily 2017-11-14
    */
    public function every_day_export(){
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        //存在action 则为每日部门统计导出   --Lily 2017-11-15
        if(isset($_GET['department']) && $_GET['department']=='deartment_total'){
            $depart = "id_department,";
            $column = array(
            '日期','部门','总订单','有效订单','无效订单','未处理','待处理','待审核','未配货','配货中','缺货','已配货','配送中','已签收','已退货','重复下单','信息不完整','恶意下单','客户取消','质量问题/产品破损','没货取消','隐藏订单','测试订单','拒收','已打包','理赔','退货入库','问题件','部分缺货','已审核','已转寄入库','匹配转寄中','已匹配转寄','转寄完成'
        );
        }else{
           $column = array(
            '日期','总订单','有效订单','无效订单','未处理','待处理','待审核','未配货','配货中','缺货','已配货','配送中','已签收','已退货','重复下单','信息不完整','恶意下单','客户取消','质量问题/产品破损','没货取消','隐藏订单','测试订单11','拒收','已打包','理赔','退货入库','问题件','部分缺货','已审核','已转寄入库','匹配转寄中','已匹配转寄','转寄完成'
        ); 
        }
        //导出超过26列的问题解决   -- Lily 2017-11-16
        $key = ord("A");//A--65
        $key2 = ord("@");//@--64
        foreach ($column as $col) {
            if($key>ord("Z")){
                $key2 += 1;
                $key = ord("A");
                $colum = chr($key2).chr($key);//超过26个字母时才会启用  
            }else{
                if($key2>=ord("A")){
                    $colum = chr($key2).chr($key);//超过26个字母时才会启用  
                }else{
                    $colum = chr($key);
                }
            }
            $excel->getActiveSheet()->setCellValue($colum . '1', $col);
            ++$j;  $key += 1;
        }
        $j = 65;
        $idx = 2;
        $ordModel = D("Order/Order");
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time']) $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
            $where['created_at']= $create_at;
        }else{
            $where['id_order_status'] = array('GT',0);
        }
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }
        
        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";

        $effective_status = OrderStatus::get_effective_status();
        //每日统计将待审核作为有效单
        array_push($effective_status, OrderStatus::VERIFICATION);

        $field = "SUBSTRING(created_at,1,10) AS set_date,".$depart."SUM(IF(`id_order_status` IN(".implode(',', $effective_status)."),1,0)) as effective,
        count(id_order) as total,
        SUM(IF(`id_order_status` IN(10,11,12,13,14,15),1,0)) as invalid,
        SUM(IF(`id_order_status`=1,1,0)) AS status1,SUM(IF(`id_order_status`=2,1,0)) AS status2,
        SUM(IF(`id_order_status`=3,1,0)) AS status3,SUM(IF(`id_order_status`=4,1,0)) AS status4,
        SUM(IF(`id_order_status`=5,1,0)) AS status5,SUM(IF(`id_order_status`=6,1,0)) AS status6,
        SUM(IF(`id_order_status`=7,1,0)) AS status7,SUM(IF(`id_order_status`=8,1,0)) AS status8,
        SUM(IF(`id_order_status`=9,1,0)) AS status9,
        SUM(IF(`id_order_status`=10,1,0)) AS status10,SUM(IF(`id_order_status`=11,1,0)) AS status11,
        SUM(IF(`id_order_status`=12,1,0)) AS status12,SUM(IF(`id_order_status`=13,1,0)) AS status13,
        SUM(IF(`id_order_status`=14,1,0)) AS status14,SUM(IF(`id_order_status`=15,1,0)) AS status15,
        SUM(IF(`id_order_status`=29,1,0)) AS status29,SUM(IF(`id_order_status`=30,1,0)) AS status30,
        SUM(IF(`id_order_status`=28,1,0)) AS status28,SUM(IF(`id_order_status`=16,1,0)) AS status16,
        SUM(IF(`id_order_status`=18,1,0)) AS status18,SUM(IF(`id_order_status`=19,1,0)) AS status19,
        SUM(IF(`id_order_status`=21,1,0)) AS status21,SUM(IF(`id_order_status`=23,1,0)) AS status23,
        SUM(IF(`id_order_status`=17,1,0)) AS status17,SUM(IF(`id_order_status`=22,1,0)) AS status22,
        SUM(IF(`id_order_status`=24,1,0)) AS status24,SUM(IF(`id_order_status`=25,1,0)) AS status25,
        SUM(IF(`id_order_status`=26,1,0)) AS status26,SUM(IF(`id_order_status`=27,1,0)) AS status27
        ";
        // dump($field);die;
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        if(isset($_GET['department']) && $_GET['department']=='deartment_total') {
             $selectData = $ordModel->field($field)->where($where)->order('set_date desc')
            ->group('set_date,id_department')->select();
        
        foreach ($selectData as $k => $v) {
               // $selectOrder[$v['set_date']]['set_date'] = $v['set_date'];
               $selectOrder[$v['set_date']][$k]['department'] = $department[$v['id_department']];
               $selectOrder[$v['set_date']][$k]['total'] = $v['total'];
               $selectOrder[$v['set_date']][$k]['effective'] = $v['effective'];
               $selectOrder[$v['set_date']][$k]['invalid'] = $v['invalid'];
               $selectOrder[$v['set_date']][$k]['status1'] = $v['status1'];
               $selectOrder[$v['set_date']][$k]['status2'] = $v['status2'];
               $selectOrder[$v['set_date']][$k]['status3'] = $v['status3'];
               $selectOrder[$v['set_date']][$k]['status4'] = $v['status4'];
               $selectOrder[$v['set_date']][$k]['status5'] = $v['status5'];
               $selectOrder[$v['set_date']][$k]['status6'] = $v['status6'];
               $selectOrder[$v['set_date']][$k]['status7'] = $v['status7'];
               $selectOrder[$v['set_date']][$k]['status8'] = $v['status8'];
               $selectOrder[$v['set_date']][$k]['status9'] = $v['status9'];
               $selectOrder[$v['set_date']][$k]['status10'] = $v['status10'];
               $selectOrder[$v['set_date']][$k]['status11'] = $v['status11'];
               $selectOrder[$v['set_date']][$k]['status12'] = $v['status12'];
               $selectOrder[$v['set_date']][$k]['status13'] = $v['status13'];
               $selectOrder[$v['set_date']][$k]['status14'] = $v['status14'];
               $selectOrder[$v['set_date']][$k]['status15'] = $v['status15'];
               $selectOrder[$v['set_date']][$k]['status29'] = $v['status29'];
               $selectOrder[$v['set_date']][$k]['status30'] = $v['status30'];
               $selectOrder[$v['set_date']][$k]['status28'] = $v['status28'];
               $selectOrder[$v['set_date']][$k]['status16'] = $v['status16'];
               $selectOrder[$v['set_date']][$k]['status18'] = $v['status18'];
               $selectOrder[$v['set_date']][$k]['status19'] = $v['status19'];
               $selectOrder[$v['set_date']][$k]['status21'] = $v['status21'];
               $selectOrder[$v['set_date']][$k]['status23'] = $v['status23'];
               $selectOrder[$v['set_date']][$k]['status17'] = $v['status17'];
               $selectOrder[$v['set_date']][$k]['status22'] = $v['status22'];
               $selectOrder[$v['set_date']][$k]['status24'] = $v['status24'];
               $selectOrder[$v['set_date']][$k]['status25'] = $v['status25'];
               $selectOrder[$v['set_date']][$k]['status26'] = $v['status26'];
               $selectOrder[$v['set_date']][$k]['status27'] = $v['status27'];
             }
        foreach ($selectOrder as  $k => $val) {
            $data[] = array(
                $k,$val
               );
        }
        if($data) {
           $num = 2;
            $sum = 2;
            foreach ($data as $kk => $vv) {
               $j = 65;
               $count = count($vv[1]);
               if($count>1){
                 $excel->getActiveSheet()->mergeCells("A" . ($num ? $num : $idx).":"."A" . (($num ? $num : $idx)+$count-1));
                 $num = (($num ? $num : $idx)+$count);
               }else{
                $num += 1;
               }
               foreach($vv as $key=>$col){
                if(is_array($col)){
                    foreach($col as $aa){
                       $excel->getActiveSheet()->setCellValue("B" . $sum, $aa['department']); 
                       $excel->getActiveSheet()->setCellValueExplicit("C" . $sum, $aa['total']); 
                       $excel->getActiveSheet()->setCellValueExplicit("D" . $sum, $aa['effective']); 
                       $excel->getActiveSheet()->setCellValueExplicit("E" . $sum, $aa['invalid']); 
                       $excel->getActiveSheet()->setCellValueExplicit("F" . $sum, $aa['status1']); 
                       $excel->getActiveSheet()->setCellValueExplicit("G" . $sum, $aa['status2']); 
                       $excel->getActiveSheet()->setCellValueExplicit("H" . $sum, $aa['status3']); 
                       $excel->getActiveSheet()->setCellValueExplicit("I" . $sum, $aa['status4']); 
                       $excel->getActiveSheet()->setCellValueExplicit("J" . $sum, $aa['status5']); 
                       $excel->getActiveSheet()->setCellValueExplicit("K" . $sum, $aa['status6']); 
                       $excel->getActiveSheet()->setCellValueExplicit("L" . $sum, $aa['status7']); 
                       $excel->getActiveSheet()->setCellValueExplicit("M" . $sum, $aa['status8']); 
                       $excel->getActiveSheet()->setCellValueExplicit("N" . $sum, $aa['status9']); 
                       $excel->getActiveSheet()->setCellValueExplicit("O" . $sum, $aa['status10']); 
                       $excel->getActiveSheet()->setCellValueExplicit("P" . $sum, $aa['status11']); 
                       $excel->getActiveSheet()->setCellValueExplicit("Q" . $sum, $aa['status12']); 
                       $excel->getActiveSheet()->setCellValueExplicit("R" . $sum, $aa['status13']); 
                       $excel->getActiveSheet()->setCellValueExplicit("S" . $sum, $aa['status14']); 
                       $excel->getActiveSheet()->setCellValueExplicit("T" . $sum, $aa['status15']); 
                       $excel->getActiveSheet()->setCellValueExplicit("U" . $sum, $aa['status29']); 
                       $excel->getActiveSheet()->setCellValueExplicit("V" . $sum, $aa['status30']); 
                       $excel->getActiveSheet()->setCellValueExplicit("W" . $sum, $aa['status28']); 
                       $excel->getActiveSheet()->setCellValueExplicit("X" . $sum, $aa['status16']); 
                       $excel->getActiveSheet()->setCellValueExplicit("Y" . $sum, $aa['status18']); 
                       $excel->getActiveSheet()->setCellValueExplicit("Z" . $sum, $aa['status19']); 
                       $excel->getActiveSheet()->setCellValueExplicit("AA" . $sum, $aa['status21']); 
                       $excel->getActiveSheet()->setCellValueExplicit("AB" . $sum, $aa['status23']); 
                       $excel->getActiveSheet()->setCellValueExplicit("AC" . $sum, $aa['status17']); 
                       $excel->getActiveSheet()->setCellValueExplicit("AD" . $sum, $aa['status22']); 
                       $excel->getActiveSheet()->setCellValueExplicit("AE" . $sum, $aa['status24']); 
                       $excel->getActiveSheet()->setCellValueExplicit("AF" . $sum, $aa['status25']); 
                       $excel->getActiveSheet()->setCellValueExplicit("AG" . $sum, $aa['status26']); 
                       $excel->getActiveSheet()->setCellValueExplicit("AH" . $sum, $aa['status27']); 
                        $sum = $sum+1;
                    }
                }else{
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $sum, $col);
                }
                 ++$j;
               }
           ++$idx;
            }
          }
    } else {
          $selectOrder = $ordModel->field($field)->where($where)->order('set_date desc')
            ->group('set_date')->select();
        foreach ($selectOrder as  $k => $val) {
            $data = array(
                $val['set_date'],$val['total'],$val['effective'],$val['invalid'],$val['status1'],$val['status2'],$val['status3'],$val['status4'],$val['status5'],$val['status6'],$val['status7'],$val['status8'],$val['status9'],$val['status10'],$val['status11'],$val['status12'],$val['status13'],$val['status14'],$val['status15'],$val['status29'],$val['status30'],$val['status28'],$val['status16'],$val['status18'],$val['status19'],$val['status21'],$val['status23'],$val['status17'],$val['status22'],$val['status24'],$val['status25'],$val['status26'],$val['status27']
            );

            $j = 65;
            $key = ord("A");//A--65
            $key2 = ord("@");//@--64
            foreach ($data as  $col) {
                  if($key>ord("Z")){ 
                        $key2 += 1;
                        $key = ord("A");
                        $colum = chr($key2).chr($key);//超过26个字母时才会启用  
                    }else{
                        if($key2>=ord("A")){
                            $colum = chr($key2).chr($key);//超过26个字母时才会启用  
                        }else{
                            $colum = chr($key);
                        }
                    }
                $excel->getActiveSheet()->setCellValueExplicit($colum . $idx, $col);
                ++$j;
                $key += 1;
            }
            ++$idx; 
        } 
        }
        add_system_record(sp_get_current_admin_id(), 4, 4, '导出DF订单统计');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '导出DF订单统计.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '导出DF订单统计.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    /**
     * 物流（黑猫）上线时间差统计
     * SELECT TIMESTAMPDIFF(HOUR,os.date_delivery,os.date_online)/24 AS sjc,
     * os.created_at,os.date_online,os.date_delivery,o.id_shipping FROM
     * `erp_order_shipping` AS os LEFT JOIN `erp_order` AS o ON o.id_order=os.id_order
     * WHERE o.id_shipping=28 AND o.id_department=1 AND os.`date_online` IS NOT NULL
     * AND os.date_online!=''  ORDER BY sjc DESC
     */
    public function online_time_differ(){
        /* @var $ordModel \Common\Model\OrderModel */
        $ord_table = D("Order/Order")->getTableName();;
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time']) $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
            $where['o.created_at']= $create_at;
        }else{
            $where['o.created_at'] = array('EGT', date('Y-m-d H:i:s',strtotime('-1 month')));
        }
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }
        $where['_string']= " (os.`date_online` IS NOT NULL or os.date_online!='')";
        $get_department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $get_department?array_column($get_department,'title','id_department'):array();
        $department_data = array();
        $ord_shipping = D("Order/OrderShipping");
        foreach($department as $id_department=>$title){
            if(isset($_GET['id_department']) && $_GET['id_department']){
                $where['id_department']= $_GET['id_department'];
            }else{
                $where['id_department']= $id_department;
            }
            $group_by_shipping = $ord_shipping->alias('os')->field('count(*) get_count,SUM(TIMESTAMPDIFF(HOUR,os.date_delivery,os.date_online)/24) AS day,o.id_shipping')
                ->join($ord_table.' as o on o.id_order=os.id_order','LEFT')
                ->where($where)->group('o.id_shipping')->order('o.id_shipping')->select();
            $shipping_data = array();
            if($group_by_shipping){
                foreach($group_by_shipping as $g_key=>$group){
                    $g_ship_id  = $group['id_shipping'];
                    $average_count = $group['day']/$group['get_count'];
                    $shipping_data[$g_ship_id]  = $average_count?number_format($average_count,2):0;
                }
            }
            $department_data[$id_department] = $shipping_data;
            if(isset($_GET['id_department']) && $_GET['id_department']){
                break;
            }
        }
        /** @var \Shipping\Model\ShippingModel $shipping */
        $shipping = D("Shipping/Shipping");
        $all_shipping = $shipping->all();
        $this->assign("shipping",$all_shipping);
        $this->assign("department",$department);
        $this->assign("department_data",$department_data);
        $this->display();
    }

    /**
     * 部门每日统计
     */
    public function department_summary(){
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        $where    = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time'] && !isset($_GET['end_time'])){
                $where['SUBSTRING(`created_at`, 1, 10)'] = $_GET['start_time'];
            }else{
                if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
                $where['created_at']= $create_at;
                $create_at[] = array('EGT', $_GET['start_time']);
            }
        }else{
            $where['SUBSTRING(`created_at`, 1, 10)'] = date('Y-m-d');
        }

        if(isset($_GET['zone_id']) && !empty($_GET['zone_id'])){
            $where['id_zone'] = $_GET['zone_id'];
        }
        // 负责人USER ID    对应部门ID
        $group_config = array(
//            '9'   =>['1'=>[1,4,7,14],'8'=>[23,26,28,36,42,34,50]] ,//1、4、7、8 ,12,13,15,16,19业务组,谷歌测试部,雅虎测试部
//            '33'   =>['1'=>[2,5,17],'10'=>[40,44,46]] ,//1、4、7、8 ,12,13,15,16,19业务组,谷歌测试部,雅虎测试部
//            '33'  => array(2,5,17,30,38,40,44,46),//2 、5、9,14,17,18,20,21 业务组
//            '26'  => array(3,19),//3 、10 业务组
//            '155' => array(21),//11 业务组
            '9'   =>['15'=>[1,23,64,4,36,28],'10'=>[7,14,26,42,62],'9'=>[52,34,50]] ,
            '33'   =>['33'=>[2,17,30,38,44,66],'19'=>[5,40,46,74]] ,
            '26'   =>['26'=>[3,60],'34'=>[19,58,76]] ,
            '155'  =>['155'=>[21,56,54]] ,
            '1'   =>['1'=>[48,68,70]] ,
          //  '9'   =>['9'=>[52]]

        );
        $users_model = D("Common/Users");
        $summary_data = array();
        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";
        $Department_name = D('Common/Department')->getTableName();

        foreach($group_config as $director=>$jinli){
            $director_data = $users_model->find($director);
            $user_name     = $director_data['user_nicename'];
            foreach($jinli as $director2=>$department){
                $director_data2 = $users_model->find($director2);
                $user_name2     = $director_data2['user_nicename'];
                foreach($department as $depart_id){
                    $where['id_department']= $depart_id;
                    $depart_user = D("Common/Users")->alias('u')->field('d.title,u.user_nicename')
                        ->join($Department_name.' d ON (d.id_users =u.id)', 'LEFT')
                        ->where(array('d.id_department'=>$depart_id))->find();

                    $effective_status = OrderStatus::get_effective_status();
                    $field = "count(id_order) as total,
                    SUM(IF(`id_order_status` IN(".implode(',', $effective_status)."),1,0)) as effective,
                    SUM(IF(`id_order_status` IN(10,11,12,13,14,15),1,0)) as invalid,
                    SUM(IF(`id_zone`=3,1,0)) as total_hk,
                    SUM(IF(`id_order_status` IN(".implode(',', $effective_status).") and `id_zone`=3,1,0)) as effective_hk,
                     SUM(IF(`id_zone` =2,1,0)) as total_tw,
                    SUM(IF(`id_order_status` IN(".implode(',', $effective_status).") and `id_zone`=2,1,0)) as effective_tw,
                    SUM(IF(`id_zone`>3,1,0)) as total_other,
                    SUM(IF(`id_order_status` IN(".implode(',', $effective_status).") and `id_zone`>3,1,0)) as effective_other
        ";
                    $select_Order = $ordModel->field($field)->where($where)->find();
                    $summary_data[$user_name][$user_name2][] = array_merge($depart_user,$select_Order);
                }
            }


        }

        $zone = M('Zone')->select();

        $this->assign("zone",$zone);
        $this->assign("list",$summary_data);
        $this->display();
    }
    public function export_department_summary() {
        set_time_limit(0);
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();
        $columns = array(
            '业务总监', '经理', '部门', '部门负责人', '台湾总订单',
            '台湾有效单', '香港总订单', '香港有效单', '其他地区总订单', '其他地区有效单',
            '合计总订单', '合计有效单'
        );
        $j = 65;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $idx = 2;
        $ordModel = D("Order/Order");
        $where    = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $create_at = array();
            if ($_GET['start_time'] && !isset($_GET['end_time'])){
                $where['SUBSTRING(`created_at`, 1, 10)'] = $_GET['start_time'];
            }else{
                if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
                $where['created_at']= $create_at;
                $create_at[] = array('EGT', $_GET['start_time']);
            }
        }else{
            $where['SUBSTRING(`created_at`, 1, 10)'] = date('Y-m-d');
        }

        if(isset($_GET['zone_id']) && !empty($_GET['zone_id'])){
            $where['id_zone'] = $_GET['zone_id'];
        }
        // 负责人USER ID    对应部门ID
        $group_config = array(
            '9'   =>['15'=>[1,23,64,4,36,28],'10'=>[7,14,26,42,62],'9'=>[52,34,50]] ,
            '33'   =>['33'=>[2,17,30,38,44,66],'19'=>[5,40,46,74]] ,
            '26'   =>['26'=>[3,60],'34'=>[19,58,76]] ,
            '155'  =>['155'=>[21,56,54]] ,
            '1'   =>['1'=>[48,68,70]] ,
            //  '9'   =>['9'=>[52]]

        );
        $users_model = D("Common/Users");
        $summary_data = array();
        $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";
        $Department_name = D('Common/Department')->getTableName();

        foreach($group_config as $director=>$jinli){
            $director_data = $users_model->find($director);
            $user_name     = $director_data['user_nicename'];
            foreach($jinli as $director2=>$department){
                $director_data2 = $users_model->find($director2);
                $user_name2     = $director_data2['user_nicename'];
                foreach($department as $depart_id){
                    $where['id_department']= $depart_id;
                    $depart_user = D("Common/Users")->alias('u')->field('d.title,u.user_nicename')
                        ->join($Department_name.' d ON (d.id_users =u.id)', 'LEFT')
                        ->where(array('d.id_department'=>$depart_id))->find();

                    $effective_status = OrderStatus::get_effective_status();
                    $field = "count(id_order) as total,
                    SUM(IF(`id_order_status` IN(".implode(',', $effective_status)."),1,0)) as effective,
                    SUM(IF(`id_order_status` IN(10,11,12,13,14,15),1,0)) as invalid,
                    SUM(IF(`id_zone`=3,1,0)) as total_hk,
                    SUM(IF(`id_order_status` IN(".implode(',', $effective_status).") and `id_zone`=3,1,0)) as effective_hk,
                     SUM(IF(`id_zone` =2,1,0)) as total_tw,
                    SUM(IF(`id_order_status` IN(".implode(',', $effective_status).") and `id_zone`=2,1,0)) as effective_tw,
                    SUM(IF(`id_zone`>3,1,0)) as total_other,
                    SUM(IF(`id_order_status` IN(".implode(',', $effective_status).") and `id_zone`>3,1,0)) as effective_other
        ";
                    $select_Order = $ordModel->field($field)->where($where)->find();
                    $summary_data[$user_name][$user_name2][] = array_merge($depart_user,$select_Order);
                }
            }


        }
        $zone = M('Zone')->select();

        if ($summary_data) {
            //var_dump($summary_data);
            foreach ($summary_data as $key => $zongjian) {
                //var_dump($jinli);
                foreach($zongjian as $k2 => $jinli){
                    foreach($jinli as $k3 => $depart){
                        //var_dump($depart);
                        $data = array(
                            $key,$k2,
                            $depart['title'],$depart['user_nicename'],
                            $depart['total_tw'],$depart['effective_tw'],
                            $depart['total_hk'],$depart['effective_hk'],$depart['total_other'],$depart['effective_other'],
                            $depart['total'],$depart['effective']
                        );
                        $j = 65;
                        foreach ($data as $col) {
                            $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                            ++$j;
                        }
                        ++$idx;
                    }

                    // }
                }



            }
            $excel->getActiveSheet()->setTitle(date('Y-m-d') . '导出订单汇总.xlsx');
            $excel->setActiveSheetIndex(0);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '导出订单汇总.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $writer->save('php://output');
            exit();
        }
    }
    public function order_beyond_100_per_day(){

        set_time_limit(0);
        $order_model = D('Common/Order');
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();

        $result = $order_model->alias('o')
            ->field("
                d.name as domain_name, DATE(o.created_at) as date, u.user_nicename as user_name,o.id_domain,
                SUM(IF(o.id_order_status IN (".implode(',', $effective_status)."), 1, 0)) AS effective_count,
                SUM(IF(o.id_zone = 2 AND o.id_order_status IN (".implode(',', $effective_status)."), 1, 0)) AS tw_effective_count,
                SUM(IF(o.id_zone = 3 AND o.id_order_status IN (".implode(',', $effective_status)."), 1, 0)) AS hk_effective_count,
                SUM(IF(o.id_zone = 15 AND o.id_order_status IN (".implode(',', $effective_status)."), 1, 0)) AS mc_effective_count,
                SUM(IF(o.id_zone = 2 AND o.id_order_status IN (".implode(',', $effective_status)."), o.price_total, 0)) AS tw_effective_total,
                SUM(IF(o.id_zone = 3 AND o.id_order_status IN (".implode(',', $effective_status)."), o.price_total, 0)) AS hk_effective_total,
                SUM(IF(o.id_zone = 15 AND o.id_order_status IN (".implode(',', $effective_status)."), o.price_total, 0)) AS mc_effective_total,
                count(*) AS all_count,dp.title as department_name, MAX(o.id_order) as id_order
            ")
            ->join("__DOMAIN__ as d ON o.id_domain=d.id_domain", 'left')
            ->join("__USERS__ as u ON u.id=o.id_users", 'left')
            ->join("__DEPARTMENT__ as dp ON dp.id_department=o.id_department", 'left')
            ->where(array("o.created_at"=>array(array('EGT', date('2017-3-1')), array('LT', date('2017-4-1')))))
            ->having("effective_count > 100")
            ->group('o.id_domain, DATE(o.created_at)')
            ->select();

        $domains = [];
        foreach($result as &$row){
            if(!isset($domains[$row['id_domain']])){
                $domains[$row['id_domain']] = $order_model->alias('o')
                    ->where(array("o.id_order_status"=> array('IN', $effective_status)))
                    ->where(array("o.created_at"=>array(array('EGT', date('2017-3-1')), array('LT', date('2017-4-1')))))
                    ->where(array('o.id_domain'=> $row['id_domain']))
                    ->count();
            }
            $row['product_name'] = M('OrderItem')->where(array('id_order'=>$row['id_order']))->getField('product_title');
            $row['month_total'] = $domains[$row['id_domain']];
        }

        vendor('PHPExcel.ExcelManage');
        $row_map = array(
            array('name'=>'日期', 'key'=> 'date'),
            array('name'=>'域名', 'key'=> 'domain_name'),
            array('name'=>'产品名', 'key'=> 'product_name'),
            array('name'=>'部门', 'key'=> 'department_name'),
            array('name'=>'广告专员', 'key'=> 'user_name'),
            array('name'=>'总订单', 'key'=> 'all_count'),
            array('name'=>'有效订单', 'key'=> 'effective_count'),
            array('name'=>'台湾有效订单', 'key'=> 'tw_effective_count'),
            array('name'=>'香港有效订单', 'key'=> 'hk_effective_count'),
            array('name'=>'澳门有效订单', 'key'=> 'mc_effective_count'),
            array('name'=>'台湾有效订单总额', 'key'=> 'tw_effective_total'),
            array('name'=>'香港有效订单总额', 'key'=> 'hk_effective_total'),
            array('name'=>'澳门有效订单总额', 'key'=> 'mc_effective_total'),
            array('name'=>'当月总有效订单', 'key'=> 'month_total'),
        );
        $excel = new \ExcelManage();
        $excel->export($result, $row_map, date("Y-m-d") . '3-1日后订单统计');
    }

    //超过1000个订单导出
    public function export_max_order() {
        set_time_limit(0);
        $limit = empty(I('request.limit')) ? 1000 : I('request.limit');
        $order_model = D('Common/Order');
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();

        $field = "MAX(o.id_order) as id_order,MAX(o.id_users) as id_users,MAX(o.id_department) as id_department,o.id_domain,count(*) as order_counts,i.id_product";
        $result = $order_model->alias('o')
            ->field($field)
            ->join("__ORDER_ITEM__ as i ON o.id_order=i.id_order")
            ->where(array('o.id_order_status'=>array('IN',$effective_status)))
            ->group('i.id_product')
            ->having("order_counts >= {$limit}")
            ->order('id_department ASC')
            ->select();
        
        foreach($result as &$row){
            $row['domain_name'] = M('Domain')->where(array('id_domain'=>$row['id_domain']))->getField('name');
            $row['real_address'] = M('Domain')->where(array('id_domain'=>$row['id_domain']))->getField('real_address');
            $row['product_title'] = M('Product')->where(array('id_product'=>$row['id_product']))->getField('title');
            $row['product_name'] = M('Product')->where(array('id_product'=>$row['id_product']))->getField('inner_name');
            $row['user_name'] = M('Users')->where(array('id'=>$row['id_users']))->getField('user_nicename');
            $row['depart_name'] = M('Department')->where(array('id_department'=>$row['id_department']))->getField('title');
        }

        vendor('PHPExcel.ExcelManage');
        $row_map = array(
            array('name'=>'域名', 'key'=> 'domain_name'),
            array('name'=>'部门', 'key'=> 'depart_name'),
            array('name'=>'广告专员', 'key'=> 'user_name'),
            array('name'=>'产品ID', 'key'=> 'id_product'),
            array('name'=>'产品名', 'key'=> 'product_title'),
            array('name'=>'产品内部名', 'key'=> 'product_name'),
            array('name'=>'有效订单总数', 'key'=> 'order_counts'),
            array('name'=>'投放域名', 'key'=> 'real_address'),
        );
        $excel = new \ExcelManage();
        $excel->export($result, $row_map, date("Y-m-d") . '总数超过1000个订单统计');
    }

    //域名订单统计
    public function export_domain_order() {
        set_time_limit(0);
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();
        //所有已发货的状态 arr
//        $all_delivered_status =  OrderStatus::get_delivered_status();

//        $product_sku = array(
//            'ST128125976114',
//            'ST8132875084',
//            'ST128124874908',
//            'ST11071659240',
//            'ST7051569210',
//            'ST128123473570',
//            'ST11071659238',
//            'ST21829469028',
//            'ST131457371940',
//            'ST131457371934',
//            'ST7055975602',
//            'ST705267109471096',
//            'ST8132875086',
//            'ST7055975604',
//            'ST1001869915',
//            'ST705557518875196',
//            'ST70557',
//            'ST1281281',
//            'ST8131874530',
//            'ST65030973004',
//            'ST110699',
//            'ST65030973006',
//            'ST7053572244',
//        );
//        $product_sku_ids = M('ProductSku')->where(array('sku'=>array('IN',$product_sku)))->getField('id_product_sku',true);
//        $domain_arr = array(
//            'www.ymvbd.com'
//        );
//        $id_domain = M('Domain')->where(array('name'=>array('IN',$domain_arr)))->getField('id_domain',true);
//        $where['id_domain'] = array('IN',$id_domain);
//        $where['id_zone'] = 3;
        $where['id_order_status'] = array('IN',$effective_status);
        $where[] = array("created_at"=>array(array('EGT', date('2017-06-01')), array('LT', date('2017-07-1'))));
//        $where['ps.sku'] = array('IN',$product_sku);
        $result = M('Order')->where($where)->select();
//        $result = M('Order')->alias('o')
//            ->field(
//                "p.title,ps.sku,
//                COUNT(*) AS count_delivered,
//                SUM(IF(o.id_order_status=9,1,0)) AS count_signed,
//                SUM(IF(o.id_order_status in (".implode(',',$all_delivered_status)."),1,0)) AS fh_signed,
//                SUM(IF(o.id_order_status=16,1,0)) AS js_signed,
//                SUM(IF(o.id_order_status=10 or o.id_order_status=21,1,0)) AS th_signed"
//                )
//            ->join('__ORDER_ITEM__ oi ON (o.id_order=oi.id_order)','LEFT')
//            ->join('__PRODUCT_SKU__ ps ON (oi.id_product_sku=ps.id_product_sku)','LEFT')
//            ->join('__DEPARTMENT__ p ON (o.id_department=p.id_department)','LEFT')
//            ->where($where)->group('ps.sku')->select();

        $results = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($results as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }
        $all_zone = D('Common/Zone')->all_zone();
        foreach($result as &$row){
            $product_name = '';
            $attrs = '';
            $products = D('Order/OrderItem')->get_item_list($row['id_order']);
            $product_count = 0;
            foreach ($products as $p) {
                $product_name .= $p['product_title'] . "\n";
                if($p['sku_title']) {
                    $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                } else {
                    $attrs .= $p['product_title']. ' x ' . $p['quantity'] . ",";
                }
                $product_count +=$p['quantity'];
            }
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$row['id_order_status']]) ? $status[$row['id_order_status']]['title'] : '未知';
            $row['user_name'] = M('Users')->where(array('id'=>$row['id_users']))->getField('user_nicename');
            $row['domain_title'] = D('Domain/Domain')->where(array('id_domain'=>$row['id_domain']))->getField('name');
            $shipping_name = M('Shipping')->where(array('id_shipping'=>$row['id_shipping']))->getField('title');
            $getShipObj = D("Order/OrderShipping")->field('track_number,status_label')->where('id_order=' . $row['id_order'])->select();
            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $row['zone'] = $all_zone[$row['id_zone']];
            $row['fname'] = $row['first_name'].' '.$row['last_name'];
            $row['pro_name'] = $product_name;
            $row['attrs'] = $attrs;
            $row['address'] = $row['province'].$row['city'].$row['area'].$row['address'];
            $row['product_count'] = $product_count;
            $row['status_name'] = $status_name;
            $row['shipping_name'] = $shipping_name;
            $row['trackNumber'] = ' '.$trackNumber;
            $row['trackStatusLabel'] = $trackStatusLabel;
            $row['id_increments'] = ' '.$row['id_increment'];
//            $row['tels'] = ' '.$row['tel'];
//                $row['rate'] = number_format($row['count_signed']/$row['count_delivered'] * 100, 2) . '%';
        }

        vendor('PHPExcel.ExcelManage');
        $row_map = array(
//            array('name'=>'部门','key'=>'title'),
//            array('name'=>'SKU','key'=>'sku'),
//            array('name'=>'发货数量','key'=>'fh_signed'),
//            array('name'=>'拒收数量','key'=>'js_signed'),
//            array('name'=>'退货数量','key'=>'th_signed'),
//            array('name'=>'签收数量','key'=>'count_signed'),
//            array('name'=>'订单总数','key'=>'count_delivered'),
//            array('name'=>'签收率','key'=>'rate')
            array('name'=>'地区', 'key'=> 'zone'),
            array('name'=>'广告专员', 'key'=> 'user_name'),
            array('name'=>'域名', 'key'=> 'domain_title'),
            array('name'=>'来源', 'key'=> 'http_referer'),
            array('name'=>'订单号', 'key'=> 'id_increments'),
            array('name'=>'姓名', 'key'=> 'fname'),
//            array('name'=>'电话号码', 'key'=> 'tels'),
//            array('name'=>'邮箱', 'key'=> 'email'),
            array('name'=>'产品名和价格', 'key'=> 'pro_name'),
            array('name'=>'总价', 'key'=> 'price_total'),
            array('name'=>'属性', 'key'=> 'attrs'),
            array('name'=>'送货地址', 'key'=> 'address'),
            array('name'=>'购买产品数量', 'key'=> 'product_count'),
            array('name'=>'重复数', 'key'=> 'order_repeat'),
            array('name'=>'留言备注', 'key'=> 'remark'),
            array('name'=>'下单时间', 'key'=> 'created_at'),
            array('name'=>'订单状态', 'key'=> 'status_name'),
            array('name'=>'发货日期', 'key'=> 'date_delivery'),
            array('name'=>'物流名称', 'key'=> 'shipping_name'),
            array('name'=>'运单号', 'key'=> 'trackNumber'),
            array('name'=>'物流状态', 'key'=> 'trackStatusLabel'),
        );
        $excel = new \ExcelManage();
        $excel->export($result, $row_map, date("Y-m-d") . '6月1号至6月30号订单信息导出');
    }

    public function csv_order() {
        set_time_limit(0);
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();
//        $where['id_department'] = ;
//        $where['id_order_status'] = array('IN',OrderStatus::REJECTION);
//        $field = "count(*) as order_counts,o.*,oi.*";
        $where[] = array("created_at"=>array(array('EGT', date('2017-08-18')), array('LT', date('2017-08-21'))));
        $where[] = array('id_order_status'=>array('IN',$effective_status));
        $result = M('Order')->where($where)->select();
//        $result = M('Order')->alias('o')->field($field)->join('__ORDER_ITEM__ oi ON oi.id_order=o.id_order','LEFT')->where($where)
//            ->group('oi.id_product')
//            ->having("order_counts >= 50")
//            ->select();

        $str = "地区,部门,广告专员,域名,订单号,姓名,产品名和价格,总价,属性,送货地址,购买产品数量,重复数,留言备注,下单时间,订单状态,发货日期,物流名称,运单号,物流状态\n";
        $results = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($results as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }
        $all_zone = D('Common/Zone')->all_zone();
        foreach($result as $row){
            $product_name = '';
            $attrs = '';
            $sku = '';
            $products = D('Order/OrderItem')->get_item_list($row['id_order']);
            $product_count = 0;
            foreach ($products as $p) {
//                $product_name .= $p['product_title'] . ";";
                if($p['sku_title']) {
                    $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ";";
                } else {
                    $attrs .= $p['product_title']. ' x ' . $p['quantity'] . ";";
                }
                $product_count +=$p['quantity'];
                $product_name .= $p['inner_name'] . ";";
                $sku .= $p['sku'].';';
            }
            $attrs = trim($attrs, ';');
            $product_name = trim($product_name,';');
            $sku = trim($sku,';');
            $status_name = isset($status[$row['id_order_status']]) ? $status[$row['id_order_status']]['title'] : '未知';
            $user_name = M('Users')->where(array('id'=>$row['id_users']))->getField('user_nicename');
            $domain_title = D('Domain/Domain')->where(array('id_domain'=>$row['id_domain']))->getField('name');
            $shipping_name = M('Shipping')->where(array('id_shipping'=>$row['id_shipping']))->getField('title');
            $getShipObj = D("Order/OrderShipping")->field('track_number,summary_status_label')->where('id_order=' . $row['id_order'])->select();
            $trackNumber = $getShipObj ? implode(';', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(';', array_column($getShipObj, 'summary_status_label')) : '';
            $fname = $row['first_name'].' '.$row['last_name'];
            $address = $row['province'].$row['city'].$row['area'].$row['address'];
            $addresss = str_replace(',',';',$address);
            $department = D('Department')->where(array('id_department'=>$row['id_department']))->getField('title');
            $ip_zone = D('OrderInfo')->where(array('id_order'=>$row['id_order']))->getField('ip_address');
            $web_infos = unserialize(htmlspecialchars_decode($row['web_info']));
            if(!empty($web_infos)) {
                if ($web_infos['device'] == 'pc') {
                    $device = 'PC端';
                } else {
                    $device = '手机端';
                }
            } else {
                $device = '';
            }

            $str.=
//                $all_zone[$row['id_zone']].','.
//                $department.','.
//                $user_name.','.
//                $product_name.','.
//                $sku.','.
//                $trackStatusLabel.','.
//                $row['http_referer'].','.
//                $row['created_at'].','.
//                $ip_zone.','.
//                $device."\n";
                $all_zone[$row['id_zone']].','.
                $department.','.
                $user_name.','.
                $domain_title.','.
                "\"\t".$row['id_increment']."\"\t,".
                $fname.','.
                $product_name.','.
                $row['price_total'].','.
                $attrs.','.
                $addresss.','.
                $product_count.','.
                $row['order_repeat'].','.
                $row['remark'].','.
                $row['created_at'].','.
                $status_name.','.
                $row['date_delivery'].','.
                $shipping_name.','.
                "\"\t".$trackNumber."\"\t,".
                $trackStatusLabel."\n";
        }
        $filename = date('Ymd').'-0818至0820订单信息.csv'; //设置文件名
        $this->export_csv($filename,$str); //导出
        exit;
    }

    //导出名字，邮箱
    public function export_ne_order() {
        set_time_limit(0);
        $order_model = D('Common/Order');
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();

        $field = "MAX(o.id_zone) as id_zone,MAX(o.id_order) as id_order,MAX(o.id_users) as id_users,MAX(o.id_department) as id_department,o.id_domain,count(*) as order_counts,i.id_product";
        $result = $order_model->alias('o')
            ->field($field)
            ->join("__ORDER_ITEM__ as i ON o.id_order=i.id_order",'LEFT')
            ->where(array('o.id_order_status'=>array('IN',$effective_status),array('o.created_at'=>array('ELT','2017-05-31'))))
            ->group('i.id_product')
            ->having("order_counts >= 500")
            ->order('id_department ASC')
            ->select();

        $str = "地区,部门,域名,产品名,内部名,出单数量\n";
//        $res = array();
        foreach($result as &$row){
            $zone = M('Zone')->where(array('id_zone'=>$row['id_zone']))->getField('title');
            $domain_name = M('Domain')->where(array('id_domain'=>$row['id_domain']))->getField('name');
            $product_title = M('Product')->where(array('id_product'=>$row['id_product']))->getField('title');
            $product_name = M('Product')->where(array('id_product'=>$row['id_product']))->getField('inner_name');
//            $row['user_name'] = M('Users')->where(array('id'=>$row['id_users']))->getField('user_nicename');
            $depart_name = M('Department')->where(array('id_department'=>$row['id_department']))->getField('title');
//            $res[] = M('Order')->alias('o')->field('first_name,last_name,email')
//                ->where(array('o.id_order_status'=>array('IN',$effective_status),'o.id_domain'=>$row['id_domain'],'o.email'=>array('notlike','%qq.com%')))
//                ->select();
            $str.=
                $zone.','.
                $depart_name.','.
                $domain_name.','.
                $product_title.','.
                $product_name.','.
                $row['order_counts']."\n";
        }

//        foreach($res as $k=>$val) {
//            foreach($val as $kk=>$vv) {
//                $res[$k][$kk]['domain_name'] = M('Domain')->where(array('id_domain'=>$result[$k]['id_domain']))->getField('name');
//                $res[$k][$kk]['depart_name'] = M('Department')->where(array('id_department'=>$result[$k]['id_department']))->getField('title');
//                $res[$k][$kk]['name'] = $vv['first_name'].$vv['last_name'];
//            }
//        }
//
//        $res_array = array();
//        foreach ($res as $key=>$val) {
//            foreach($val as $kk=>$vv) {
//                foreach ($vv as $k=>$v) {
//                    $res_array[$kk][$k] = $v;
//                }
//            }
//        }

//        vendor('PHPExcel.ExcelManage');
//        $row_map = array(
//            array('name'=>'域名', 'key'=> 'domain_name'),
//            array('name'=>'部门', 'key'=> 'depart_name'),
//            array('name'=>'姓名', 'key'=> 'name'),
//            array('name'=>'邮箱', 'key'=> 'email'),
//        );
//        $excel = new \ExcelManage();
//        $excel->export($result, $row_map, date("Y-m-d") . '导出域名对应的姓名和邮箱统计');
        $filename = date('Ymd').'产品单量超过500的订单信息.csv'; //设置文件名
        $this->export_csv($filename,$str); //导出
        exit;
    }

    //导出一周每天超过50单的产品+网站+域名
    public function export_pd_order() {
        set_time_limit(0);
        $order_model = D('Common/Order');
        $effective_status = \Order\Lib\OrderStatus::get_effective_status();

        $field = "o.id_domain,i.id_product,SUM(IF(o.id_order_status IN (".implode(',', $effective_status)."), 1, 0)) AS effective_count";
        $result = $order_model->alias('o')
            ->field($field)
            ->join("__ORDER_ITEM__ as i ON o.id_order=i.id_order")
            ->where(array("o.created_at"=>array(array('EGT', date('2017-05-22')), array('LT', date('2017-05-27')))))
            ->group('i.id_product')
            ->having("effective_count >= 50")
            ->order('effective_count DESC')
            ->select();

        foreach($result as &$row){
            $row['domain_name'] = M('Domain')->where(array('id_domain'=>$row['id_domain']))->getField('name');
            $row['real_domain_name'] = M('Domain')->where(array('id_domain'=>$row['id_domain']))->getField('real_address');
            $row['product_title'] = M('Product')->where(array('id_product'=>$row['id_product']))->getField('title');
        }

        vendor('PHPExcel.ExcelManage');
        $row_map = array(
            array('name'=>'域名', 'key'=> 'domain_name'),
            array('name'=>'投放地址', 'key'=> 'real_domain_name'),
            array('name'=>'产品名字', 'key'=> 'product_title'),
            array('name'=>'单数', 'key'=> 'effective_count'),
        );
        $excel = new \ExcelManage();
        $excel->export($result, $row_map, date("Y-m-d") . '导出超过50单统计');
    }

    /**
     * 客服审单统计
     */
    public function check_order(){
        $getData = I('get.', "htmlspecialchars");
        $cur_page = $getData['p']? : 1; //默认页数 
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }    
        
        $userids = M('orderCheckStatistics')->distinct(true)->getField('id_users', true);
        if ($userids) {
            $cond_user['id'] = array('in', implode(',', $userids));
            $userNames = M('users')->where($cond_user)->getField('id,user_nicename', true);
        }     
        $cond=[];
        if (!empty($getData['userid'])) {
            $cond['id_users']=$getData['userid'];
        }         
        if (!empty($getData['start_time'])) {
            $cond['created_at'][]= array('EGT', $getData['start_time']);
        }   
        if (!empty($getData['end_time'])) {
            $cond['created_at'][]= array('ELT', $getData['end_time']." 23:59:59");
        }           
        $fileds="id_users,created_at,first_trial,first_valid,truncate((first_valid/first_trial*100),2) as first_rate,last_trial,last_valid,truncate((last_valid/last_trial*100),2) as last_rate,update_cnt";
        $count  =M("orderCheckStatistics")->where($cond)->field($fileds)->order('id desc')->count();      
        $list=M("orderCheckStatistics")->where($cond)->field($fileds)->page("$cur_page,$this->page")->order('id desc')->select();
        $page = $this->page($count, $this->page);        
        $this->assign("list", $list);
        $this->assign("page", $page->show('Admin'));        
        $this->assign("userNames", $userNames);
        $this->assign("getData", $getData);
        $this->display();        
    }
    /**
     *  客服审单统计导出
     */
    public function check_order_export(){
        $getData = I('get.', "htmlspecialchars");
        $userids = M('orderCheckStatistics')->distinct(true)->getField('id_users', true);
        if ($userids) {
            $cond_user['id'] = array('in', implode(',', $userids));
            $userNames = M('users')->where($cond_user)->getField('id,user_nicename', true);
        }     
        $cond=[];
        if (!empty($getData['userid'])) {
            $cond['id_users']=$getData['userid'];
        }         
        if (!empty($getData['start_time'])) {
            $cond['created_at'][]= array('EGT', $getData['start_time']);
        }   
        if (!empty($getData['end_time'])) {
            $cond['created_at'][]= array('ELT', $getData['end_time']." 23:59:59");
        }           
        $fileds="id_users,created_at,first_trial,first_valid,truncate((first_valid/first_trial*100),2) as first_rate,last_trial,last_valid,truncate((last_valid/last_trial*100),2) as last_rate,update_cnt";     
        $list=M("orderCheckStatistics")->where($cond)->field($fileds)->order('id desc')->select();        
        $str = "日期,客服,初审单数,初审有效单,初审有效单比例,终审单数,终审有效单,终审有效单比例,修改单数\n";
        foreach ($list as $k => $val) {
            $productStr = '';         
            $str.=
                    $val['created_at'] . "\t," .
                    $userNames[$val['id_users']] . ',' .
                    $val['first_trial'] . "," .
                    $val['first_valid'] . "," .
                    $val['first_rate'] . "%," .
                    $val['last_trial'] . ',' .
                    $val['last_valid'] . ',' .
                    $val['last_rate'] . "%," .
                    $val['update_cnt'] . "\n" ;                    
        }
        $filename = date('Ymd') . '客服审单统计.csv'; //设置文件名
        $this->export_csv($filename, iconv("UTF-8","GBK//IGNORE",$str)); //导出
        exit;        
    }

    protected  function export_csv($filename,$data)
    {
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=".$filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }
}