<?php

/**
 * 盘点模块
 * @Author wz
 * Class IndexController
 * @package Warehouse\Controller
 */

namespace Warehouse\Controller;

use Common\Controller\AdminbaseController;
use Common\Lib\Procedure;
use Order\Model\UpdateStatusModel;

class InventoryController extends AdminbaseController {

    public function _initialize() {
        parent::_initialize();
        $this->warehouseList=M('Warehouse')->where(array('status' => 1))->getField('id_warehouse,title', true);
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
    }

    /**
     * 盘点列表 
     */
    public function index() {
        $getData = I('get.', "htmlspecialchars");
        $cur_page = $getData['p']? : 1; //默认页数
        $cond_inventory = [];
        if (!empty($getData['docno'])) {
            $cond_inventory['docno'] = array('like', "%{$getData['docno']}%");
        }
        if (!empty($getData['id_warehouse'])) {
            $cond_inventory['id_warehouse'] = $getData['id_warehouse'];
        }
        if (!empty($getData['status'])) {
            $cond_inventory['status'] = $getData['status'];
        }
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }
        //默认开始，结束时间
        $start_time=$getData['start_time']?$getData['start_time']:date('Y-m-d',  strtotime('-7days'));  
        $end_time=$getData['end_time']?strtotime($getData['end_time']):time();
        
        $cond_inventory['bill_date']=array('between',array($start_time,date('Y-m-d 23:59:59',$end_time)));
        //获取盘点用户名称数组
        $userNames = [];
        $ownerIds = M('Inventory')->distinct(true)->where($cond_inventory)->getField('owner_id', true);
        $statuserIds = M('Inventory')->distinct(true)->where($cond_inventory)->getField('statuser_id', true);
        $userIds = array_unique(array_merge($ownerIds, $statuserIds));
        $cond = array();
        if ($userIds) {
            $cond['id'] = array('in', implode(',', $userIds));
            $userNames = M('users')->where($cond)->getField('id,user_login', true);
        }
        $count = M('Inventory')->where($cond_inventory)->count();
        $inventoryList = M('Inventory')->page("$cur_page,$this->page")->where($cond_inventory)->order('create_time desc')->select();
        $page = $this->page($count, $this->page);
        $this->assign('warehouseList', $this->warehouseList);
        $this->assign('userNames', $userNames);
        $this->assign('inventoryList', $inventoryList);
        $this->assign('getData', $getData);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', date('Y-m-d',$end_time));        
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /**
     * 批量提交盘点单
     */
    public function update_Inventory_status() {
        if (IS_AJAX) {
            try {
                $ivIds = is_array($_POST['inventoryIds']) ? $_POST['inventoryIds'] : array($_POST['inventoryIds']);
                $msg = '提交盘点单';
                $upd_data = array('status'=>2, 'instatus' => 2, 'outstatus' => 2, 'statuser_id' => $_SESSION['ADMIN_ID'], 'status_time' => date('Y-m-d H:i:s', time()));
                $iv_table = D("Common/Inventory")->getTableName();               
                $procedureData=array('userid'=>$_SESSION['ADMIN_ID'],'tablename'=>$iv_table,'inor'=>'I');
                if ($ivIds && is_array($ivIds)) {
                    foreach ($ivIds as $ivid) {
                        $ivstatus = M('Inventory')->field('status')->where(array('id' => $ivid))->find();
                        if ($ivstatus['status'] == 1) {//  防止多次提交
                            $procedureData['billid']=$ivid;//                            
                            $inputRes=Procedure::call('ERP_INOUT_SUBMIT', $procedureData);
                            //对于盘点差异值为正 的产品 进行跳单处理
                            $jumpwhere="qty_diff>0 and inventory_id=".$ivid;
                            $jumpIdSku= M('Inventoryitem')->where($jumpwhere)->field('id_product_sku')->select();  
                            if($jumpIdSku){
                                $jumpIdSku=  array_column($jumpIdSku, 'id_product_sku');
                                UpdateStatusModel::get_short_order($jumpIdSku);
                            }
                            M('Inventory')->where(array('id' => $ivid))->save($upd_data);
                        }
                    }
                    $status = 1;
                    $message = $msg . '成功';
                }
            } catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);
            exit();
        }
    }

    /**
     * 新增盘点单
     */
    public function add_inventory() {
        if (IS_AJAX) {//新增盘点  
            $return = array('status' => 0, 'message' => 'fail');
            $id_warehouse=I('post.id_warehouse');
            if (isset($_POST['inventory_type'])&&$_POST['inventory_type']==1) {//抽盘数据  先检查skus是否有错误
                $skus = is_array($_POST['skus']) ? $_POST['skus'] : array($_POST['skus']);
                $check_res = $this->check_skus($skus,$id_warehouse);
                if ($check_res['status'] != 1) {
                    $return['message'] = $check_res['message'];
                    echo json_encode($check_res); exit();
                }
            }
            //盘点日期不允许在月结日期之前  
//            $periodInfo=M('period')->where(array('id_warehouse'=>$id_warehouse,'isendaccount'=>'N'))->min('datebegin');
//            if($periodInfo){
//                if(strtotime($periodInfo)<  strtotime($_POST['bill_date'])){
//                    $return['message']='盘点日期不允许在月结日期之前！';
//                    echo json_encode($return); exit();                
//                }                
//            }

            //写入erp_inventory表格数据
            $IVData = [];
            $IVData['docno'] = $this->createDocno();
            $IVData['bill_date'] = $_POST['bill_date']? : date('Y-m-d 00:00:00', time());
            $IVData['id_warehouse'] = I('post.id_warehouse');
            $IVData['inventory_type'] = I('post.inventory_type');
            $IVData['description'] = I('post.description');
            $IVData['owner_id'] = $_SESSION['ADMIN_ID'];
            $IVData['create_time'] = date('Y-m-d H:i:s', time());
            $last_ivid = M('Inventory')->data($IVData)->add();
            //写入erp_inventoryitemp和erp_inventoryitems数据
            if ($_POST['inventory_type']==1) {
                $add_res = $this->addByRandom($last_ivid, $IVData['id_warehouse'], $skus);
            } else {
                $add_res = $this->addByOverall($last_ivid, $IVData['id_warehouse']);
            }
            if(!$add_res){
                $return['message']='写入数据失败，请重新操作！';
                echo json_encode($return); exit();
            }
            $this->updateTotal($last_ivid);
            $return = array('status' => 1, 'message' => 'suc！','ivid'=>$last_ivid);
            echo json_encode($return); exit();
        }

      
        $this->assign('warehouseList', $this->warehouseList);
        $this->assign('cur_data', date('Y-m-d'));
        $this->display();
    }

    /**
     * 明细
     */
    public function  detail_item(){
        $getData = I('get.', "htmlspecialchars");
        if (!empty($getData['displayRow'])) {
            $this->page = $getData['displayRow'];
        }        
        
        $cur_page = $getData['p']? : 1; //默认页数
        $ivId=$getData['ivid'];
        $ivInfo=M('Inventory')->where(array('id'=>$ivId))->find();
        $pro_table = D("Common/Product")->getTableName();
        $pro_s_table = D("Common/ProductSku")->getTableName();
        $iv_table = D("Common/Inventoryitem")->getTableName();
        $where='pt.id_product=pst.id_product and pst.id_product_sku=ivt.id_product_sku and pst.id_product=ivt.id_product and ivt.id_product=pt.id_product';//多表连接条件
        $where.=' and ivt.inventory_id='.$ivId ;
        if($getData['searchsku']){
            $getData['searchsku']=  trim($getData['searchsku']);
            $where.=' and pst.sku like '."'%{$getData['searchsku']}%'";
        }
        $fields='pst.sku,pst.barcode,pt.title as ptitle,pt.inner_name,pst.title as attr,ivt.qty_book,ivt.id as ivitemid,ivt.descriptopn,ivt.qty_count,ivt.qty_diff';
        $count= M()->table(array(  $pro_table=> 'pt',$pro_s_table => 'pst',$iv_table=>'ivt'))->field($fields)->where($where)->count();
        $itemData = M()->table(array(  $pro_table=> 'pt',$pro_s_table => 'pst',$iv_table=>'ivt'))->field($fields)->where($where)->page("$cur_page,$this->page")->select();
        $page = $this->page($count, $this->page);
        $this->assign('ivId', $ivId);      
        $this->assign('skus', json_encode(array_column($itemData,'sku')));
        $this->assign('itemData', $itemData);
        $this->assign('getData', $getData);
        $this->assign('warehouseList', $this->warehouseList);
        $this->assign('ivInfo', $ivInfo);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }
    
    /**
     * 通过sku 修改盘点数据
     */
    public function  updateRealBySku(){
        if (IS_AJAX) {
            try {
                $skunums = is_array($_POST['skunums']) ? $_POST['skunums'] : array($_POST['skunums']);
                $msg = '修改盘点数量';
                if ($skunums) {
                    foreach ($skunums as  $sn) {
                        $sn['sku']=  trim($sn['sku']);
                        //通过sku查询  id_product 和id_product_sku
                        $checkSkuRes=$this->check_skus(array($sn['sku']),$_POST['id_warehouse']);
                        if($checkSkuRes['status']!=1){
                            $return['message']=$sn['sku'].'无法成功匹配！';
                            echo json_encode($return); exit();
                        } 
                        if($sn['skunum']<0||is_null($sn['skunum'])){
                            $return['message']=$sn['sku'].'的盘点数据请输入正整数！';
                            echo json_encode($return); exit();                            
                        }
                        $findinfo=M('product_sku')->where(array('sku'=>$sn['sku']))->Field('id_product_sku,id_product')->find(); 
                        $updcond=array('inventory_id'=>$_POST['ivid'],'id_product_sku' => $findinfo['id_product_sku'],'id_product'=> $findinfo['id_product']);  
                        $findBookNum=M('inventoryitem')->where($updcond)->getField('qty_book');
                        if(is_null($findBookNum)){//新增sku盘点
                            
                            $skuRealNum=[];
                            $skuRealNum[$sn['sku']]=$sn['skunum'];                            
                            $addres=$this->addByRandom($_POST['ivid'], $_POST['id_warehouse'], array($sn['sku']),$skuRealNum);
                        }else{
                            
                        $updata=array('qty_count'=>$sn['skunum'],'qty_diff'=>($sn['skunum']-$findBookNum));
                        $updres = M('inventoryitem')->where($updcond)->save($updata);
                        $updpres = M('inventoryitemp')->where($updcond)->save(array('qty_count'=>$sn['skunum']));                            
                        }

                    }
                    $status = 1;
                    $message = $msg . '成功';
                }else{
                    $status = 0;
                    $message='sku数据为空！';
                }
            } catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            $this->updateTotal($_POST['ivid']);
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);
            exit();
        }
    }
    
    /**
     * 新增sku
     */
    public function  addNewSku(){
        if (IS_AJAX) {
            $return = array('status' => 0, 'message' => 'fail');
            $postData = I('post.', "htmlspecialchars");
            //验证sku是否可用
            $checkSkuRes=$this->check_skus(array($postData['sku']));
            if($checkSkuRes['status']!=1){
                $return['message']=$postData['sku'].'无法成功匹配！';
                echo json_encode($return); exit();
            }
            if($postData['sku']<0){
               $return['message']=$postData['sku'].'的盘点数据请输入正整数！';
               echo json_encode($return); exit();                
            }
            //查询是否本仓库的sku 及在库数量
            $skuinfo=M('product_sku')->where(array('sku'=>$postData['sku']))->field('id_product_sku,id_product')->find();
            //写入itme和itemp表
            $skuRealNum=[];
            $skuRealNum[$postData['sku']]=$postData['realcount'];
            $updres=$this->addByRandom($postData['ivid'], $postData['id_warehouse'], array($postData['sku']),$skuRealNum);
            if(!$updres){
                $return['message']=$postData['sku'].'修改数据失败！';
                echo json_encode($return); exit();                
            }
            $this->updateTotal($postData['ivid']);            
            $return = array('status' => 1, 'message' => 'suc！');
            echo json_encode($return); exit();
  
        }        
    }
    
    public function updateRealById(){
        $return = array('status' => 0, 'message' => 'fail');
        $postData = I('post.', "htmlspecialchars");
        if(empty($postData['ivdata'])){
            $return['message']='数据为空！';
            echo json_encode($return); exit();
        }
        $ivdata=$postData['ivdata'];
        $groupdata=[];
        foreach ($ivdata as $val){            
            list($ivid,$field)=  explode('_', $val['name']);
            $groupdata[$ivid][$field]=$val['value'];
        }
        foreach ($groupdata as $id =>$val){
            $findinfo=M('inventoryitem')->where(array('id'=>$id))->Field('id_product_sku,id_product,qty_book')->find();
            $updata=array('qty_count'=>$val['qtycount'],'qty_diff'=>($val['qtycount']-$findinfo['qty_book']),'descriptopn'=>$val['descriptopn']);
            $updres = M('inventoryitem')->where(array('id'=>$id))->save($updata);
            $updcond=array('inventory_id'=>$_POST['ivid'],'id_product_sku' => $findinfo['id_product_sku'],'id_product'=> $findinfo['id_product']); 
            $updpres = M('inventoryitemp')->where($updcond)->save(array('qty_count'=>$val['qtycount']));    
            if($updres===FALSE||$updpres===FALSE){
                $return['message']='更新数据失败！';
                echo json_encode($return); exit();
            }
        }       
        $this->updateTotal($postData['ivid']); 
        $return = array('status' => 1, 'message' => 'suc!');
        echo json_encode($return); exit();
    }
    
    /**
     * 删除盘点单
     */
    public function  deleteInventory(){
        if (IS_AJAX) {
            try {
                $ivIds = is_array($_POST['inventoryIds']) ? $_POST['inventoryIds'] : array($_POST['inventoryIds']);
                $msg = '删除盘点单';          
                if ($ivIds) {
                    foreach ($ivIds as $ivid) {
                        $delIvCond=array('id'=>$ivid);
                        $delres=M('inventory')->where($delIvCond)->delete(); 
                        $delIvItem=array('inventory_id'=>$ivid);
                        $delitemres=M('inventoryitem')->where($delIvItem)->delete(); 
                        $delitempres=M('inventoryitemp')->where($delIvItem)->delete(); 
                    }
                    $status = 1;
                    $message = $msg . '成功';
                }
            } catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);
            exit();
        }        
    }
    
    /**
     * 删除盘点单明细
     */
    public function  deleteInventoryItem(){
        if (IS_AJAX) {
            try {
                $ivitemIds = is_array($_POST['ivitemids']) ? $_POST['ivitemids'] : array($_POST['ivitemids']);
                $msg = '删除盘点单明细'; 
                if ($ivitemIds) {                    
                    foreach ($ivitemIds as $ivitemid) {
                        //先查询出盘点明细中  sku 和产品id 和盘点id  便于删除itemp表数据
                        $findItemInfo=M('inventoryitem')->where(array('id'=>$ivitemid))->field('inventory_id,id_product,id_product_sku')->find();      
                        $delitemres=M('inventoryitem')->where(array('id'=>$ivitemid))->delete(); 
                        $delitempres=M('inventoryitemp')->where($findItemInfo)->delete(); 
                    }
                    $status = 1;
                    $message = $msg . '成功';
                }
            } catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);
            exit();
        }        
    }    

    /**
     * 全盘方式   写入erp_inventoryitem和erp_inventoryitemp数据
     * @param type $ivId
     * @param type $id_warehouse
     * @return boolean
     */
    protected function addByOverall($ivId, $id_warehouse) {
        if (empty($ivId) || empty($id_warehouse)) {
            return FALSE;
        }
        $iv_table = D("Common/Inventoryitem")->getTableName();
        $wpt_table = D("Common/WarehouseProduct")->getTableName();
        $pst_table = D("Common/ProductSku")->getTableName();
        $ivp_table = D("Common/Inventoryitemp")->getTableName();
        $wstock_table=D("Common/WarehouseAllocationStock")->getTableName();
        
        
        $insertstr="INSERT INTO {$iv_table }(`inventory_id`,`id_product`,`id_product_sku`,`option_value`,`qty_book`,`qty_count`,`qty_diff`)";
        $selectstr="select {$ivId},psku.id_product,psku.id_product_sku,psku.option_value,wpt.quantity as qty_book,0,wpt.quantity*-1  as qty_diff";
        
        $fromstr="{$wpt_table} as wpt , {$pst_table} as psku ";
        $wherestr = 'psku.id_product = wpt.id_product ANd psku.id_product_sku = wpt.id_product_sku AND psku.status = 1 AND wpt.id_warehouse=' . $id_warehouse .' GROUP BY psku.id_product,psku.id_product_sku';
        $allItemRes=M()->execute($insertstr.' '.$selectstr.' from '.$fromstr.'  where '.$wherestr);
        
        
        $ivpstr="INSERT INTO {$ivp_table }(`inventory_id`,`id_product`,`id_product_sku`,`id_warehouse_allocation`,`option_value`)";
        $ivpSlectstr="select {$ivId},psku.id_product,psku.id_product_sku,psku.option_value,";
        $subquerystr=" (select id_warehouse_allocation from {$wstock_table} as wst where wst.id_product=psku.id_product ANd psku.id_product_sku = wst.id_product_sku ORDER BY wst.updated_at desc LIMIT 1) as id_warehouse_allocation";
        $ivpfromstr="{$pst_table} as psku  left join {$wpt_table} as wpt on  psku.id_product = wpt.id_product ANd psku.id_product_sku = wpt.id_product_sku " ;
        $ivpwherestr = ' psku.status = 1 and  wpt.id_warehouse='. $id_warehouse. ' GROUP BY psku.id_product,psku.id_product_sku';  
        $allItemPRes=M()->execute($ivpstr.' '.$ivpSlectstr.$subquerystr .' from '.$ivpfromstr.'  where '.$ivpwherestr);
        if($allItemRes===false||$allItemPRes===false){
            return FALSE;
        }
        return TRUE;
    }

    /**
     * 抽盘方式 或者 修改明细时,添加新的sku  写入erp_inventoryitem和erp_inventoryitemp数据 
     * @param type $ivId 盘点主表id
     * @param type $id_warehouse  仓库id
     * @param type $skus  skus数组
     * @param type $skus  skus数 对应的盘点数
     */
    protected function addByRandom($ivId, $id_warehouse, $skus,$skurealnum=array()) {
        if (empty($skus) || empty($id_warehouse) || empty($ivId)) {
            return FALSE;
        }
        $itemData = [];
        $where = '  sku in (\'' . implode("','", $skus) . "')";
        $fields='id_product_sku,id_product,option_value,sku';
        $itemData= M('product_sku')->field($fields)->where($where)->select();
        foreach ($itemData as $item) {
            $itemPData = [];
            $item['inventory_id'] = $ivId;
            $findbook=M('warehouse_product')->where(array('id_warehouse'=>$id_warehouse,'id_product_sku'=>$item['id_product_sku'],'id_product'=>$item['id_product']))->getField('quantity');
            $item['qty_book']=$findbook?:0;
            if($skurealnum){
                $item['qty_count']=$skurealnum[$item['sku']];
                $item['qty_diff']=$skurealnum[$item['sku']]-$item['qty_book'];                
                $itemPData['qty_count'] =$skurealnum[$item['sku']];
            }
            $addItemRes = M('inventoryitem')->add($item);
            //货位编码可能为空  先直接查询 erp_warehouse_allocation_stock表
            
            $itemPData['inventory_id'] = $ivId;
            $itemPData['id_product_sku'] = $item['id_product_sku'];
            $itemPData['id_product'] = $item['id_product'];
            $itemPData['option_value'] = $item['option_value'];
            $itemPData['id_warehouse_allocation'] = M('warehouse_allocation_stock')->where(array('id_product' => $itemPData['id_product'], 'id_product_sku' => $itemPData['id_product_sku']))->order('updated_at desc')->limit(1)->getField('id_warehouse_allocation');
            $addItemPRes = M('inventoryitemp')->add($itemPData);
            if (!$addItemRes || !$addItemPRes) {
                return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * 更新统计
     */
    protected function updateTotal($ivid){
        $sql='';
        $ivitem_table = D("Common/inventoryitem")->getTableName();
        $suminfo=M('inventoryitem')->where(array('inventory_id'=>$ivid))-> Field( 'sum(qty_book) as booksum,sum(qty_count) as cntsum,sum(qty_diff) as diffcum ')->find() ;
        
        $updata=array('total_book'=>$suminfo['booksum'],'total_count'=>$suminfo['cntsum'],'total_diff'=>$suminfo['diffcum']);
        $updres=M('inventory')->where(array('id'=>$ivid))-> save($updata);
        if($updres===FALSE){
            return FALSE;
        }
        return TRUE;
    }
    /**
     * 检查sku的正确性
     * @param type $skus
     * @return boolean
     */
    protected function check_skus($skus,$id_warehouse=0) {
        $return = array('status' => 0, 'message' => 'fail');
        if (empty($skus)) {
            $return['message'] = 'sku数据为空！';
            return $return;
        }
        foreach ($skus as $sku) {
            $skuinfo=NULL;
            $skuinfo= M('product_sku')->where(array('sku' => $sku, 'status' => 1))->field('id_product_sku,id_product')->find();
            if (!$skuinfo) {
                $return['message'] = $sku . 'sku状态不可用！';
                return $return;
            }
            if($id_warehouse){//抽盘时  查询是否为该仓库的产品
                $skuinfo['id_warehouse']=$id_warehouse;
                $iswarehouse=M('warehouse_product')->where($skuinfo)->count();    
                if(!$iswarehouse){
                    $return['message'] = $sku . '无法成功匹配该仓库！';
                    return $return;                    
                }
            }
        }
        $return = array('status' => 1, 'message' => 'suc!');
        return $return;
    }

    /**
     * 产生新的单据编码
     */
    protected function createDocno() {
        $prefix = 'IV' . date('ymd', time());
        $cond['bill_date'] = array('like', '%' . date('Y-m-d', time()) . '%');
        $lastDocno = M('Inventory')->where($cond)->order('id desc')->field('docno')->find();
        $lastNum = 0;
        if ($lastDocno['docno']) {
            $lastNum = substr($lastDocno['docno'], strlen($prefix));
        }
        $cur_num = $lastNum + 1;
        return $prefix . str_pad($cur_num, 7, '0', STR_PAD_LEFT);
    }

}
