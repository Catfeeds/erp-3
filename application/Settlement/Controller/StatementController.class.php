<?php
namespace Settlement\Controller;
use Common\Controller\AdminbaseController;

class StatementController extends AdminbaseController {

    protected $page;

    public function _initialize() {
        parent::_initialize();
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
    }

    /*
     * 订单明细报表(财务)
     */
    public function order_view() {
    	G('begin');
        $getData = I('get.', "htmlspecialchars");//dump($getData);exit;
        // array(10) {
        //   ["page"] => string(0) ""
        //   ["id_increment6"] => string(0) ""
        //   ["sku6"] => string(0) ""
        //   ["start_time"] => string(16) "2017-09-18 00:00"
        //   ["end_time"] => string(16) "2017-10-18 23:29"
        //   ["shipingstatus6"] => string(9) "已配货"
        //   ["title6"] => string(0) ""
        //   ["shipping_name6"] => string(0) ""
        //   ["id_zone6"] => string(1) "2"
        //   ["displayRow"] => string(0) ""
        // }
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        } 
        $cur_page = $getData['p']? : 1; //默认页数
        $cond=[];
        //默认开始，结束时间
        if(empty($getData['start_time'])&&empty($getData['end_time'])){
            $start_time=date('Y-m-d 00:00',  strtotime('-1months'));
            $end_time=date('Y-m-d 23:29', time());
        }else{
            $start_time=$getData['start_time'];
            $end_time=$getData['end_time'];
        }
        $cond['created_at']=array('between',array($start_time,$end_time));
        foreach ($getData as  $key=>$val){
            if(stripos($key,'6')!==FALSE&&!empty($val)){
                $cond_filed=  substr($key,0 ,stripos($key,'6'));
                if($cond_filed!='id_zone'){
                    $val=  trim($val);
                    $cond[$cond_filed]=array('like',"%{$val}%");
                }else{
                    $cond[$cond_filed]=$val;
                }
            }  
        }
        //         array(3) {
        //   ["created_at"] => array(2) {
        //     [0] => string(7) "between"
        //     [1] => array(2) {
        //       [0] => string(16) "2017-09-18 00:00"
        //       [1] => string(16) "2017-10-18 23:29"
        //     }
        //   }
        //   ["shipingstatus"] => array(2) {
        //     [0] => string(4) "like"
        //     [1] => string(11) "%已配货%"
        //   }
        //   ["id_zone"] => string(1) "2"
        // }
        // 过滤掉无效订单    --Lily   2017-1019
        if(empty($cond['shipingstatus'])){
            $cond['shipingstatus'] = array("NOT IN",array('重复下单','信息不完整','恶意下单','客户取消','测试订单','没货取消'));
        }
        //dump($cond);exit;
        $departmentList=M('department')->where(array('type'=>1))->field('id_department,title')->select();//业务部门
        $zoneList=M('zone')->getField('id_zone,title');//地区选择
        $orderStauts=M('order_status')->field('id_order_status,title')->where("id_order_status not in(11,12,13,14,28,29)")->select();
        $shipList=M('shipping')->where(array('status'=>1))->field('id_shipping,title')->select();
        
        G('end');
        trace(G('begin','end').'s',"t1耗时：");
        
        G('begin');
        $total=M("orderView")->where($cond)->count();
        G('end');
        trace(G('begin','end').'s',"orderView count耗时：");
        trace(M("orderView")->getLastSql(),"sql-----");
        
        $orderViewlist=M("orderView")->where($cond)->page("$cur_page,$this->page")->order('date_purchase DESC')->select();
        //echo M("orderView")->getLastSql();exit;
        $order_increment=[];
        foreach ($orderViewlist as &$val){ 
            $val['qtyout']='';
            if($val['date_delivery']){
                $val['qtyout']=$val['quantity'];
            }
            if(in_array($val['id_increment'], $order_increment)){
                $val['price_total']=0;
                continue;
            }                    
            $order_increment[]=$val['id_increment'];
        }
        $page = $this->page($total, $this->page);
        $this->assign("start_time", $start_time);
        $this->assign("end_time", $end_time);
        $this->assign("getdata", $getData);
        $this->assign("departmentList", $departmentList);
        $this->assign("zoneList", $zoneList);
        $this->assign("orderStauts", $orderStauts);
        $this->assign("shipList", $shipList);
        $this->assign("page", $page->show('Admin'));
        $this->assign("orderViewlist", $orderViewlist);
        $this->display();
    }
    
    /**
     * 导出订单明细
     */
    public function orderViewExport(){
        set_time_limit(0);
        $getData = I('get.', "htmlspecialchars");
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        } 
        $cond=[];
        //默认开始，结束时间
        //默认开始，结束时间
        if(empty($getData['start_time'])&&empty($getData['end_time'])){
            $start_time=date('Y-m-d 00:00',  strtotime('-1months'));
            $end_time=date('Y-m-d 23:29', time());
        }else{
            $start_time=$getData['start_time'];
            $end_time=$getData['end_time'];
        }
        $cond['created_at']=array('between',array($start_time,$end_time));
        foreach ($getData as  $key=>$val){
            if(stripos($key,'6')!==FALSE&&!empty($val)){
                $cond_filed=  substr($key,0 ,stripos($key,'6'));
                if($cond_filed!='id_zone'){
                    $val=  trim($val);
                    $cond[$cond_filed]=array('like',"%{$val}%");
                }else{
                    $cond[$cond_filed]=$val;
                }
            }  
        }     
        $zoneList=M('zone')->getField('id_zone,title');//地区选择

        $str = "订单号,订单状态,是否结算,客户名称,地区,详细地址,下单时间,发货时间,业务部门,广告员,物流企业,快递单号,发货仓库,产品分类,产品内部名,SKU,产品属性,采购单价,销售价,购买数量,发货数量,订单总价,已结款金额,货币类型,产品网址\n";
        for($i=0;$i<=9;$i++){
            $star=$i*10000;
            $orderViewlist=M("orderView")->where($cond)->limit($star,10000)->select();
            $order_increment=[];
            foreach($orderViewlist as $k => $item){
                $item['qtyout']='';
                if($item['date_delivery']){
                    $item['qtyout']=$item['quantity'];
                }                
                if(in_array($item['id_increment'], $order_increment)){
                    $price_total=0;
                }else{
                    $price_total=$item['price_total'];
                    $order_increment[]=$item['id_increment'];
                }
                $issettlement=$item['is_settlemented']==1?'是':'否';
                $item['address']=str_replace(',','  ',$item['address']);
                $item['first_name']=str_replace(',','  ',$item['first_name']);
                $item['last_name']=str_replace(',','  ',$item['last_name']);
                $item['area']=str_replace(',','  ',$item['area']);
                $item['attr_title']=str_replace(',','  ',$item['attr_title']);
                $str.=
                    $item['id_increment']."\t,".
                    $item['shipingstatus'].','.
                    $issettlement.','.
                    $item['first_name'].$item['last_name'].','.
                    $zoneList[$item['id_zone']].','.
                    $item['address'].','.
                    $item['created_at']."\t,".
                    $item['date_delivery']."\t,".
                    $item['title'].','.
                    $item['user_nicename'].','.
                    $item['shipping_name'].','.
                    $item['track_number']."\t,".
                    $item['warename'].','.
                    $item['categoryname'].','.
                    $item['inner_name'].','.
                    $item['sku']."\t,".
                    $item['attr_title'].','.
                    $item['pricecost'].','.
                    $item['price'].','.
                    $item['quantity'].','.
                    $item['qtyout'].','.
                    $price_total.','.
                    $item['amount_settlement'].','.
                    $item['currency_code'].','.
                    $item['name']."\n";
            }
        }

        $filename = date('Ymd').'.csv'; //设置文件名
        $this->export_csv($filename,$str); //导出
        exit;          
        
    }


    /**
     *  采购应付报表
     */
    public function purchase_settlement(){
        $getData = I('get.', "htmlspecialchars");
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        } 
        $cur_page = $getData['p']? : 1; //默认页数
        $cond=[];
        //默认开始，结束时间
        $start_time=$getData['start_time']?$getData['start_time']:date('Y-m-d',  strtotime('-1months'));  
        $end_time=$getData['end_time']?strtotime($getData['end_time']):time(); 
        $cond['billdate']=array('between',array($start_time,date('Y-m-d 23:59:59',$end_time)));
        if($getData['start_purchase_time'] && $getData['end_purchase_time']){
            $cond['inner_purchase_time']=array('between',array($getData['start_purchase_time'],$getData['end_purchase_time']));
        }
        foreach ($getData as  $key=>$val){
            if(stripos($key,'6')!==FALSE&&!empty($val)){
                $cond_filed=  substr($key,0 ,stripos($key,'6'));
                if(in_array($cond_filed,array('purchase_no','sku','inner_purchase_no'))){
                    $cond[$cond_filed]=array('like',"%{$val}%");
                }else{
                    $cond[$cond_filed]=$val;
                }
            }  
        }        
        $warehouseList=M('warehouse')->where(array('type'=>1))->getField('id_warehouse,title');//仓库
        $departmentList=M('department')->where(array('type'=>1))->getField('id_department,title');//业务部门
        $supplierList=M('supplier')->getField('id_supplier,title');  //供应商      
        $psViewlist=M("purchase_settlement_view")->where($cond)->page("$cur_page,$this->page")->order('billdate desc')->select();        
        foreach ($psViewlist as  $key=> $val){//对于图片字段处理
            $thumbs= json_decode($val['thumbs'],TRUE);
            if(stripos($thumbs['photo'][0]['url'], 'http')===FALSE){
                $psViewlist[$key]['pic']="http://erp.msiela.com:90/data/upload/".$thumbs['photo'][0]['url'];
                
            }else{
                $psViewlist[$key]['pic']=$thumbs['photo'][0]['url'];
            }

        }
        $total=M("purchase_settlement_view")->where($cond)->count();
        $page = $this->page($total, $this->page);
        $this->assign("start_time", $start_time);
        $this->assign("end_time", date('Y-m-d',$end_time));
        $this->assign("getdata", $getData);        
        $this->assign("page", $page->show('Admin'));        
        $this->assign("psViewlist", $psViewlist); 
        $this->assign("departmentList", $departmentList); 
        $this->assign("warehouseList", $warehouseList);      
        $this->assign("supplierList", $supplierList); 
        $this->display();        
    }
         
    public function purchaseSettlementExport(){
        $getData = I('get.', "htmlspecialchars");
        $cond=[];
        //默认开始，结束时间
        $start_time=$getData['start_time']?$getData['start_time']:date('Y-m-d',  strtotime('-1months'));  
        $end_time=$getData['end_time']?strtotime($getData['end_time']):time(); 
        $cond['billdate']=array('between',array($start_time,date('Y-m-d 23:59:59',$end_time)));
        if($getData['start_purchase_time'] && $getData['end_purchase_time']){
            $cond['inner_purchase_time']=array('between',array($getData['start_purchase_time'],$getData['end_purchase_time']));
        }
        foreach ($getData as  $key=>$val){
            if(stripos($key,'6')!==FALSE&&!empty($val)){
                $cond_filed=  substr($key,0 ,stripos($key,'6'));
                if(in_array($cond_filed,array('purchase_no','sku','inner_purchase_no'))){
                    $cond[$cond_filed]=array('like',"%{$val}%");
                }else{
                    $cond[$cond_filed]=$val;
                }
            }  
        }      
        $supplierList=M('supplier')->getField('id_supplier,title');  //供应商      
        $psViewlist=M("purchase_settlement_view")->where($cond)->order('billdate desc')->select();  
        $str = "采购单号,采购内部单号,采购内部时间,建立时间,所属仓库,所属部门,供应商,SKU,条形码,内部名,产品属性,采购数量(采购产品),采购数量(采购应付),到货数量(采购应付),更新日期,付款日期\n";
        foreach($psViewlist as $k => $item){
            $str.=
                "\"\t".$item['purchase_no']."\"\t,".
                "\"\t".$item['inner_purchase_no']."\"\t,".
                "\"\t".$item['inner_purchase_time']."\"\t,".
                "\"\t".$item['billdate']."\"\t,".
                $item['warehousename'].','.
                $item['derpartmentname'].','.
                $supplierList[$item['id_supplier']].','.
                $item['sku'].','.
                "\"\t".$item['barcode']."\"\t,".
                $item['inner_name'].','.
                $item['atrrtitle'].','.
                $item['quantity'].','.
                $item['qty'].','.
                $item['qtyin'].','.
                "\"\t".$item['updated_at']."\"\t,"."\"\t".
                $item['date_settlement']."\"\t,"."\n";
        }        
        $filename = date('Ymd').'.csv'; //设置文件名
        $this->export_csv($filename,$str); //导出
        exit;         
    }
    
    /**
     * 采购明细报表
     */
    public function purchase(){
        $getData = I('get.', "htmlspecialchars");
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        } 
        $cur_page = $getData['p']? : 1; //默认页数
        $cond=[];
        //默认开始，结束时间
        $start_time=$getData['start_time']?$getData['start_time']:date('Y-m-d',  strtotime('-1months'));
        $end_time=$getData['end_time']?strtotime($getData['end_time']):time();
        $cond['billdate']=array('between',array($start_time,date('Y-m-d 23:59:59',$end_time)));
        //入库时间
        $start_in_time=$getData['start_in_time'];
        $end_in_time=$getData['end_in_time'];
        if ($start_in_time) {
            $cond['intime'] = array('EGT',$start_in_time);
        }
        if($end_in_time) {
            $cond['intime'] = array('LT',$end_in_time);

        }
        if($getData['start_purchase_time'] && $getData['end_purchase_time']){
            $cond['inner_purchase_time']=array('between',array($getData['start_purchase_time'],$getData['end_purchase_time']));
        }
        foreach ($getData as  $key=>$val){
            if(stripos($key,'6')!==FALSE&&!empty($val)){
                $cond_filed=  substr($key,0 ,stripos($key,'6'));
                if(in_array($cond_filed,array('purchase_no','sku','inner_purchase_no'))){
                    $cond[$cond_filed]=array('like',"%{$val}%");
                }else{
                    $cond[$cond_filed]=$val;
                }
            }  
        }         
        
        $warehouseList=M('warehouse')->where(array('type'=>1))->getField('id_warehouse,title');//仓库
        $departmentList=M('department')->where(array('type'=>1))->getField('id_department,title');//业务部门
        $supplierList=M('supplier')->getField('id_supplier,title');  //供应商      
        $pViewlist=M("purchase_view")->where($cond)->page("$cur_page,$this->page")->order('billdate desc')->select();
        foreach ($pViewlist as  $key=> $val){//对于图片字段处理
            $thumbs= json_decode($val['thumbs'],TRUE);
            if(stripos($thumbs['photo'][0]['url'], 'http')===FALSE){
                $pViewlist[$key]['pic']="http://erp.msiela.com:90/data/upload/".$thumbs['photo'][0]['url'];
            }else{
                $pViewlist[$key]['pic']=$thumbs['photo'][0]['url'];
            }

        }        
        $total=M("purchase_view")->where($cond)->count();
        $page = $this->page($total, $this->page);    
        $this->assign("start_time", $start_time);
        $this->assign("end_time", date('Y-m-d',$end_time));
        $this->assign("getdata", $getData);         
        $this->assign("page", $page->show('Admin'));            
        $this->assign("pViewlist", $pViewlist);  
        $this->assign("warehouseList", $warehouseList);            
        $this->assign("departmentList", $departmentList); 
        $this->assign("supplierList", $supplierList);         
        $this->display();          
    }
    
    /**
     * 导出采购明细
     */
    public function purchaseExport(){
        $getData = I('get.', "htmlspecialchars");
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        } 
        $cond=[];
        //默认开始，结束时间
        $start_time=$getData['start_time']?$getData['start_time']:date('Y-m-d',  strtotime('-1months'));  
        $end_time=$getData['end_time']?strtotime($getData['end_time']):time(); 
        $cond['billdate']=array('between',array($start_time,date('Y-m-d 23:59:59',$end_time)));
        if($getData['start_purchase_time'] && $getData['end_purchase_time']){
            $cond['inner_purchase_time']=array('between',array($getData['start_purchase_time'],$getData['end_purchase_time']));
        }
        foreach ($getData as  $key=>$val){
            if(stripos($key,'6')!==FALSE&&!empty($val)){
                $cond_filed=  substr($key,0 ,stripos($key,'6'));
                if(in_array($cond_filed,array('purchase_no','sku','inner_purchase_no'))){
                    $cond[$cond_filed]=array('like',"%{$val}%");
                }else{
                    $cond[$cond_filed]=$val;
                }
            }  
        }   
        $warehouseList=M('warehouse')->where(array('type'=>1))->getField('id_warehouse,title');//仓库
        $departmentList=M('department')->where(array('type'=>1))->getField('id_department,title');//业务部门
        $supplierList=M('supplier')->getField('id_supplier,title');  //供应商      
        $pViewlist=M("purchase_view")->where($cond)->order('billdate desc')->select();      
        $str = "采购单号,采购内部单号,采购内部时间,建立时间,所属仓库,所属部门,供应商,SKU,条形码,产品属性,采购数量,采购单价,采购时间\n";
        foreach($pViewlist as $k => $item){
            $str.=
                "\"\t".$item['purchase_no']."\"\t,".
                "\"\t".$item['inner_purchase_no']."\"\t,".
                "\"\t".$item['inner_purchase_time']."\"\t,".
                "\"\t".$item['billdate']."\"\t,".
                $warehouseList[$item['id_warehouse']].','.
                $departmentList[$item['id_department']].','.
                $supplierList[$item['id_supplier']].','.
                $item['sku'].','.
                "\"\t".$item['barcode']."\"\t,".
                $item['option_value'].",".
                $item['quantity'].",".                    
                $item['price'].",".                    
                "\"\t".$item['inner_purchase_time']."\"\t\n";
        }        
        $filename = date('Ymd').'.csv'; //设置文件名
        $this->export_csv($filename,$str); //导出
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

    public function purchase_table() {
        if (!empty($_GET['displayRow'])) {
            $this->page = $_GET['displayRow'];
        }
        $_GET['cstart_time'] = I('get.cstart_time', date('Y-m-d 00:00', strtotime('-7 day')));
        $_GET['cend_time'] = I('get.cend_time', date('Y-m-d 00:00', strtotime('+1 day')));
        $where = array();
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = $_GET['id_department'];
        }
        if(isset($_GET['id_supplier']) && $_GET['id_supplier']) {
            $where['id_supplier'] = $_GET['id_supplier'];
        }
        if(isset($_GET['start_time']) && $_GET['start_time']) {
            $time_arr = array();
            $time_arr[] = array('EGT',$_GET['start_time']);
            if($_GET['end_time']) $time_arr[] = array('LT',$_GET['end_time']);
            $where[] = array('inner_purchase_time'=>$time_arr);
        }
        if(isset($_GET['cstart_time']) && $_GET['cstart_time']) {
            $ctime_arr = array();
            $ctime_arr[] = array('EGT',$_GET['cstart_time']);
            if($_GET['cend_time']) $ctime_arr[] = array('LT',$_GET['cend_time']);
            $where[] = array('created_at'=>$ctime_arr);
        }
        if(isset($_GET['status']) && $_GET['status']) {
            $where['status'] = $_GET['status'];
        }

        if(isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $where['purchase_no'] = array('like','%'.$_GET['purchase_no'].'%');
        }
        if(isset($_GET['sku']) && $_GET['sku']) {
            $sku_id = M('ProductSku')->where(array('sku'=>$_GET['sku']))->getField('id_product_sku');
            if(!empty($sku_id)) {
//                $purchase_ids = M('PurchaseInitem')->where(array('id_product_sku'=>$sku_id))->getField('id_purchasein',true);
                $purchase_ids = M('PurchaseProduct')->where(array('id_product_sku'=>$sku_id))->getField('id_purchase',true);
                if(!empty($purchase_ids)) {
                    $where['id_purchase'] = array('IN', $purchase_ids);
                } else {
                    $where['id_purchase'] = array(0);
                }
            } else {
                $where['id_purchase'] = array(0);
            }
        }

        $list_count = M('PurchaseTable')->where($where)->group('id_purchase')->select();
//        $field = "p.*,pi.intime,pi.id_purchasein,pi.id_erp_purchase,ps.date_settlement,ps.amount_settlement,pp.id_product as pid_pro,pp.id_product_sku as pid_pro_sku";
//        $list_count = M('Purchase')->field($field)->alias('p')
//            ->join('__PURCHASE_PRODUCT__ pp ON (p.id_purchase=pp.id_purchase)','LEFT')
//            ->join('__PURCHASE_IN__ pi ON (p.id_purchase=pi.id_erp_purchase)','LEFT')
//            ->join('__PURCHASE_SETTLEMENT__ ps ON (p.id_purchase=ps.id_erp_purchase)','LEFT')
//            ->group('p.id_purchase')
//            ->where($where)
//            ->select();
        $page = $this->page(count($list_count),$this->page);
        $list = M('PurchaseTable')->where($where)->group('id_purchase')->order('inner_purchase_time DESC')->limit($page->firstRow , $page->listRows)->select();
//        $list = M('Purchase')->field($field)->alias('p')
//            ->join('__PURCHASE_PRODUCT__ pp ON (p.id_purchase=pp.id_purchase)','LEFT')
//            ->join('__PURCHASE_IN__ pi ON (p.id_purchase=pi.id_erp_purchase)','LEFT')
//            ->join('__PURCHASE_SETTLEMENT__ ps ON (p.id_purchase=ps.id_erp_purchase)','LEFT')
//            ->group('p.id_purchase')
//            ->order('p.inner_purchase_time DESC')
//            ->where($where)
//            ->limit($page->firstRow , $page->listRows)->select();

        foreach($list as $key=>$val) {
            switch($val['payment']) {
                case 2:
                    $payment = '通道支付';
                    break;
                case 1:
                    $payment = '货到付款';
                    break;
                default:
                    $payment = '';
            }
            $user_name = M('Users')->where(array('id'=>$val['id_users']))->getField('user_nicename');
            $supplier = M('Supplier')->where(array('id_supplier'=>$val['id_supplier']))->getField('title');
            $department = M('Department')->where(array('id_department'=>$val['id_department']))->getField('title');
//            $purchase_pro = M('PurchaseInitem')->where(array('id_purchasein'=>$val['id_purchasein']))->order('id_product_sku ASC')->select();
            $purchase_pro = M('PurchaseProduct')->where(array('id_purchase'=>$val['id_purchase']))->order('id_product_sku ASC')->select();
            $purchase_settle_pro = M('PurchaseSettlement')->where(array('id_erp_purchase'=>$val['id_purchase']))->order('id_product_sku ASC')->select();
            foreach($purchase_pro as $k=>$v) {
                $img = M('Product')->field('inner_name,thumbs')->where(array('id_product'=>$v['id_product']))->find();
                $sku = M('ProductSku')->where(array('id_product_sku'=>$v['id_product_sku']))->getField('sku');
                $purchase_pro[$k]['img'] = json_decode($img['thumbs'],true);
                $purchase_pro[$k]['pro_name'] = $img['inner_name'];
                $purchase_pro[$k]['sku'] = $sku;
                $purchase_pro[$k]['dh_qty'] = $purchase_settle_pro[$k]['qtyin']?$purchase_settle_pro[$k]['qtyin']:0;
                $purchase_pro[$k]['amount_settl'] = $purchase_settle_pro[$k]['amount_settlement']?$purchase_settle_pro[$k]['amount_settlement']:0;
            }
            $list[$key]['pur_pro'] = $purchase_pro;
            $list[$key]['department'] = $department;
            $list[$key]['supplier'] = $supplier;
            $list[$key]['user'] = $user_name;
            $list[$key]['payment'] = $payment;
            $list[$key]['status'] = M('PurchaseStatus')->where(array('id_purchase_status'=>$val['status']))->getField('title');
        }

        $status_where['id_purchase_status'] = array('EGT',2);
        $status = M('PurchaseStatus')->where($status_where)->getField('id_purchase_status,title',true);
        $suppliers = M('Supplier')->getField('id_supplier,title',true);
        $departments = M('Department')->where(array('type'=>1))->getField('id_department,title',true);
        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->assign('supplier',$suppliers);
        $this->assign('departments',$departments);
        $this->assign('status',$status);
        $this->display();
    }
    public function purchase_table2() {
        if (!empty($_GET['displayRow'])) {
            $this->page = $_GET['displayRow'];
        }
        $_GET['cstart_time'] = I('get.cstart_time', date('Y-m-d 00:00', strtotime('-7 day')));
        $_GET['cend_time'] = I('get.cend_time', date('Y-m-d 00:00', strtotime('+1 day')));
        $where = array();
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where['id_department'] = $_GET['id_department'];
        }
        if(isset($_GET['id_supplier']) && $_GET['id_supplier']) {
            $where['id_supplier'] = $_GET['id_supplier'];
        }
        if(isset($_GET['start_time']) && $_GET['start_time']) {
            $time_arr = array();
            $time_arr[] = array('EGT',$_GET['start_time']);
            if($_GET['end_time']) $time_arr[] = array('LT',$_GET['end_time']);
            $where[] = array('inner_purchase_time'=>$time_arr);
        }
        if(isset($_GET['cstart_time']) && $_GET['cstart_time']) {
            $ctime_arr = array();
            $ctime_arr[] = array('EGT',$_GET['cstart_time']);
            if($_GET['cend_time']) $ctime_arr[] = array('LT',$_GET['cend_time']);
            $where[] = array('created_at'=>$ctime_arr);
        }
        if(isset($_GET['status']) && $_GET['status']) {
            $where['status'] = $_GET['status'];
        }

        if(isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $where['purchase_no'] = array('like','%'.$_GET['purchase_no'].'%');
        }
        if(isset($_GET['sku']) && $_GET['sku']) {
            $sku_id = M('ProductSku')->where(array('sku'=>$_GET['sku']))->getField('id_product_sku');
            if(!empty($sku_id)) {
//                $purchase_ids = M('PurchaseInitem')->where(array('id_product_sku'=>$sku_id))->getField('id_purchasein',true);
                $purchase_ids = M('PurchaseProduct')->where(array('id_product_sku'=>$sku_id))->getField('id_purchase',true);
                if(!empty($purchase_ids)) {
                    $where['id_purchase'] = array('IN', $purchase_ids);
                } else {
                    $where['id_purchase'] = array(0);
                }
            } else {
                $where['id_purchase'] = array(0);
            }
        }
        $list = M('PurchaseTable')->where($where)->group('id_purchase')->order('inner_purchase_time DESC')->select();
        foreach($list as $key=>$val) {
            switch($val['payment']) {
                case 2:
                    $payment = '通道支付';
                    break;
                case 1:
                    $payment = '货到付款';
                    break;
                default:
                    $payment = '';
            }
            $purchase_channel = '';

            switch ($val['purchase_channel']) {
                case 1: $purchase_channel = '阿里巴巴 ';
                    break;
                case 2: $purchase_channel = '淘宝 ';
                    break;
                case 3: $purchase_channel = '线下 ';
                    break;
                default:$purchase_channel = '空 ';
            }
            $list[$key]['purchase_channel'] = $purchase_channel;

            $user_name = M('Users')->where(array('id'=>$val['id_users']))->getField('user_nicename');
            $supplier = M('Supplier')->where(array('id_supplier'=>$val['id_supplier']))->getField('title');
            $department = M('Department')->where(array('id_department'=>$val['id_department']))->getField('title');
//            $purchase_pro = M('PurchaseInitem')->where(array('id_purchasein'=>$val['id_purchasein']))->order('id_product_sku ASC')->select();
            $purchase_pro = M('PurchaseProduct')->where(array('id_purchase'=>$val['id_purchase']))->order('id_product_sku ASC')->select();
            $purchase_settle_pro = M('PurchaseSettlement')->where(array('id_erp_purchase'=>$val['id_purchase']))->order('id_product_sku ASC')->select();
            $list[$key]['prodcut']=[]; $aaa=[];
            foreach($purchase_pro as $k=>$v) {
                $img = M('Product')->field('inner_name')->where(array('id_product'=>$v['id_product']))->find();
                $sku = M('ProductSku')->where(array('id_product_sku'=>$v['id_product_sku']))->getField('sku');
                $purchase_pro[$k]['pro_name'] = $img['inner_name'];
                $purchase_pro[$k]['dh_qty'] = $purchase_settle_pro[$k]['qtyin']?$purchase_settle_pro[$k]['qtyin']:0;
                $purchase_pro[$k]['amount_settl'] = $purchase_settle_pro[$k]['amount_settlement']?$purchase_settle_pro[$k]['amount_settlement']:0;
                if(!in_array($purchase_pro[$k]['pro_name'],$aaa)){
                    array_push($aaa,$purchase_pro[$k]['pro_name']);
                    $list[$key]['prodcut'][$purchase_pro[$k]['pro_name']]=$purchase_pro[$k]['quantity'];
                }else{
                    $list[$key]['prodcut'][$purchase_pro[$k]['pro_name']]= $list[$key]['prodcut'][$purchase_pro[$k]['pro_name']]+$purchase_pro[$k]['quantity'];
                }

            }
            $list[$key]['prodcut']['name']=$aaa;
            $list[$key]['alibaba_no']=M('Purchase')->where(array('id_purchase'=>$val['id_purchase']))->getField('alibaba_no');
            $list[$key]['pur_pro'] = $purchase_pro;
            $list[$key]['department'] = $department;
            $list[$key]['supplier'] = $supplier;
            $list[$key]['user'] = $user_name;
            $list[$key]['payment'] = $payment;
            $list[$key]['status'] = M('PurchaseStatus')->where(array('id_purchase_status'=>$val['status']))->getField('title');
        }

        $status_where['id_purchase_status'] = array('EGT',2);
        $status = M('PurchaseStatus')->where($status_where)->getField('id_purchase_status,title',true);
        $suppliers = M('Supplier')->getField('id_supplier,title',true);
        $departments = M('Department')->where(array('type'=>1))->getField('id_department,title',true);
        $this->assign('list',$list);
        $this->assign('supplier',$suppliers);
        $this->assign('departments',$departments);
        $this->assign('status',$status);
        $this->display();
    }
    /**
     * 导出采购报表
     */
    public function purchase_table_export() {
        $_GET['cstart_time'] = I('get.cstart_time', date('Y-m-d 00:00', strtotime('-7 day')));
        $_GET['cend_time'] = I('get.cend_time', date('Y-m-d 00:00', strtotime('+1 day')));
        $where = array();
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where['p.id_department'] = $_GET['id_department'];
        }
        if(isset($_GET['id_supplier']) && $_GET['id_supplier']) {
            $where['p.id_supplier'] = $_GET['id_supplier'];
        }
        if(isset($_GET['start_time']) && $_GET['start_time']) {
            $time_arr = array();
            $time_arr[] = array('EGT',$_GET['start_time']);
            if($_GET['end_time']) $time_arr[] = array('LT',$_GET['end_time']);
            $where[] = array('p.inner_purchase_time'=>$time_arr);
        }
        if(isset($_GET['cstart_time']) && $_GET['cstart_time']) {
            $ctime_arr = array();
            $ctime_arr[] = array('EGT',$_GET['cstart_time']);
            if($_GET['cend_time']) $ctime_arr[] = array('LT',$_GET['cend_time']);
            $where[] = array('p.created_at'=>$ctime_arr);
        }
        if(isset($_GET['status']) && $_GET['status']) {
            $where['p.status'] = $_GET['status'];
        }else {
            $where['p.status'] = array('EGT',2);
        }
        if(isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $where['p.purchase_no'] = array('like','%'.$_GET['purchase_no'].'%');
        }
        if(isset($_GET['sku']) && $_GET['sku']) {
            $sku_id = M('ProductSku')->where(array('sku'=>$_GET['sku']))->getField('id_product_sku');
            if(!empty($sku_id)) {
//                $purchase_ids = M('PurchaseInitem')->where(array('id_product_sku'=>$sku_id))->getField('id_purchasein',true);
                $purchase_ids = M('PurchaseProduct')->where(array('id_product_sku'=>$sku_id))->getField('id_purchase',true);
                if(!empty($purchase_ids)) {
                    $where['p.id_purchase'] = array('IN', $purchase_ids);
                } else {
                    $where['p.id_purchase'] = array(0);
                }
            } else {
                $where['p.id_purchase'] = array(0);
            }
        }
//        $field = "p.*,pi.intime,pi.id_purchasein,pi.id_erp_purchase,ps.date_settlement,ps.amount_settlement,pp.id_product as pid_pro,pp.id_product_sku as pid_pro_sku";
        $field = "p.*,pp.id_product as pid_pro,pp.id_product_sku as pid_pro_sku,pp.quantity";
        $list = M('Purchase')->field($field)->alias('p')
            ->join('__PURCHASE_PRODUCT__ pp ON (p.id_purchase=pp.id_purchase)','LEFT')
//            ->join('__PURCHASE_IN__ pi ON (p.id_purchase=pi.id_erp_purchase)','LEFT')
//            ->join('__PURCHASE_SETTLEMENT__ ps ON (p.id_purchase=ps.id_erp_purchase)','LEFT')
//            ->group('p.id_purchase')
            ->order('p.inner_purchase_time DESC')
            ->where($where)->select();
//        print_r($list);die;

//        $str = "采购日期,采购人,部门,采购单号,产品名称,SKU-属性和采购数量,采购总数,单价,总价,备注,到付日期,sku-付款金额,预付金额,付款方式,供应厂商,SKU-属性和到货数量,到货日期,状态\n";
        $str = "采购日期,采购人,部门,采购单号,状态,产品内部名称,SKU,属性,采购数量,单价,备注,付款日期,付款金额,预付金额,付款方式,供应厂商,入库数量,入库日期\n";
        foreach($list as $k => $item){
            switch($item['payment']) {
                case 2:
                    $payment = '通道支付';
                break;
                case 1:
                    $payment = '货到付款';
                break;
                default:
                    $payment = '';
            }
            $user_name = M('Users')->where(array('id'=>$item['id_users']))->getField('user_nicename');
            $supplier = M('Supplier')->where(array('id_supplier'=>$item['id_supplier']))->getField('title');
            $department = M('Department')->where(array('id_department'=>$item['id_department']))->getField('title');
            $pro = M('Product')->field('inner_name')->where(array('id_product'=>$item['pid_pro']))->find();
            $pro_sku =  M('ProductSku')->field('sku,title')->where(array('id_product_sku'=>$item['pid_pro_sku']))->find();
            $purchase_settle_pro = M('PurchaseSettlement')->where(array('id_erp_purchase'=>$item['id_purchase'],'id_product'=>$item['pid_pro'],'id_product_sku'=>$item['pid_pro_sku']))->find();
            $purchase_in = M('PurchaseIn')->where(array('id_erp_purchase'=>$item['id_purchase']))->find();
//            $purchase_pro = M('PurchaseInitem')->where(array('id_purchasein'=>$item['id_purchasein']))->order('id_product_sku ASC')->select();
//            $purchase_pro = M('PurchaseProduct')->where(array('id_purchase'=>$item['id_purchase']))->order('id_product_sku ASC')->select();
//            $purchase_settle_pro = M('PurchaseSettlement')->where(array('id_erp_purchase'=>$item['id_erp_purchase']))->order('id_product_sku ASC')->select();
//            $pro_name = array();
//            $skus = '';
//            $qtys = '';
//            $amounts = '';
//            foreach($purchase_pro as $k=>$v) {
//                $dh_qty = !empty($purchase_settle_pro) ? ($purchase_settle_pro[$k]['qtyin']?$purchase_settle_pro[$k]['qtyin']:0) : $v['received'];
//                $amount_settlement = $purchase_settle_pro[$k]['amount_settlement']?$purchase_settle_pro[$k]['amount_settlement']:0;
//                $img = M('Product')->field('inner_name,thumbs')->where(array('id_product'=>$v['id_product']))->find();
//                $sku = M('ProductSku')->where(array('id_product_sku'=>$v['id_product_sku']))->getField('sku');
//                $pro_name[] = $img['inner_name'];
//                $skus .= $sku.'-'.$v['option_value'].' x '.$v['quantity'].';';
//                $qtys .= $sku.' x '.$dh_qty.';';
//                $amounts .= $sku.'-'.$amount_settlement.';';
//            }
//            $pro_names = trim(implode(';',array_unique($pro_name)),';');
//            $sku_data = trim($skus,';');
//            $qty_data = trim($qtys,';');
//            $amount_data = trim($amounts,';');
            $status = M('PurchaseStatus')->where(array('id_purchase_status'=>$item['status']))->getField('title');
            $str.=
                $item['inner_purchase_time'].','.
                $user_name.','.
                $department.','.
                "\"\t".$item['purchase_no']."\"\t,".
                $status.','.
                $pro['inner_name'].','.
                $pro_sku['sku'].','.
                $pro_sku['title'].','.
                $item['quantity'].','.
                round(($item['price']-$item['price_shipping'])/$item['total'],2).','.
                $item['remark'].','.
                $purchase_settle_pro['date_settlement'].','.
                $purchase_settle_pro['amount_settlement'].','.
                $item['prepay'].','.
                $payment.','.
                $supplier.','.
                $purchase_settle_pro['qtyin'].','.
                $purchase_in['intime']."\n";
        }
        $filename = date('Ymd').'-财务报表.csv'; //设置文件名
        $this->export_csv($filename,$str); //导出
        exit;
    }
}
