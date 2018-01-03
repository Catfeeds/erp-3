<?php

namespace Warehouse\Controller;
use Common\Controller\AdminbaseController;

/**
 * 模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Purchase\Controller
 */
class InoutController extends AdminbaseController {

    protected $Purchase, $Users;

    public function _initialize() {
        parent::_initialize();
        $this->AllocationStock = D("Common/WarehouseAllocationStock");
        $this->Users = D("Common/Users");
        $this->pager = isset($_SESSION['set_page_row']) ? $_SESSION['set_page_row'] : 20;
        $this->status  =array("1"=>"未提交","2"=>"已提交");
    }
    /*
     * 上架列表
     */
    public function indexofin(){
        $where = array();
        $created_at_array = array();
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
           if ($_GET['start_time'])
               $created_at_array[] = array('EGT', $_GET['start_time']);
           if ($_GET['end_time'])
               $created_at_array[] = array('LT', $_GET['end_time']);
            $where['wai.created_at'] = $created_at_array;
        }else{
            $_GET['start_time']=date('Y-m-d 00:00',  strtotime('-7days'));
            $_GET['end_time']=date('Y-m-d 23:59',  time());
            $created_at_array[] = array('EGT', $_GET['start_time']);
            $created_at_array[] = array('LT', $_GET['end_time']);
            $where['wai.created_at'] = $created_at_array;
        }
        if (!empty($_GET['status'])) {
            $where['wai.status'] = $_GET['status'];
        }
        if (!empty($_GET['user'])) {
            $where['user.user_nicename'] =array("like",'%'.$_GET['user'].'%');
        }
        $where['type']=1;
        //$where['wga.id_warehouse'] = array('EQ', $id_warehouse);
        $count = M('WarehouseAllocationInout')->alias('wai')
            ->field("wai.*,user.user_nicename")
            ->join('__USERS__ as user on user.id = wai.id_users','LEFT')->where($where)->order("created_at DESC")->count();
        $page = $this->page($count, $this->pager );

        $list = M('WarehouseAllocationInout')->alias('wai')
            ->field("wai.*,user.user_nicename")
            ->join('__USERS__ as user on user.id = wai.id_users','LEFT')
            ->where($where)->order("created_at DESC")->limit($page->firstRow, $page->listRows)->select();
        $user = M('Users')->getField('id,user_nicename',true);
        foreach($list as $key => $val){
            $inoutitem = M('WarehouseAllocationIn')->field('SUM(qty) as qty,count(id) as count')->where(array('warehouse_allocation_inout_id'=>$val['id']))->find();
            $list[$key]['tj_name'] = $user[$val['statuser_id']];
            $list[$key]['count'] = $inoutitem['count'];
            $list[$key]['count_sum'] = $inoutitem['qty'];
        }
        //var_dump($list);die;
        $this->assign('list',$list);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("status", $this->status);

        $this->display();
    }
    //新增/编辑明细页_上架
    public function detail(){
        $where = array();
        if($_REQUEST['action']=='add' && isset($_REQUEST['action'])){
            $inoutdata['id_users']=$_SESSION['ADMIN_ID'];
            $inoutdata['created_at']=date('Y-m-d H:i:s');
            $inoutdata['docno']=$this->createDocno('WarehouseAllocationInout','IO');

            $inout=M('WarehouseAllocationInout')->add($inoutdata);
            if($inout != false){
                $where['warehouse_allocation_inout_id']=$inout;
            }

        }else{
            $where['warehouse_allocation_inout_id']=$_REQUEST['id'];
        }
        $data= M('WarehouseAllocationInout') ->where(['id'=>$where['warehouse_allocation_inout_id']])->find();
        //$where['wga.id_warehouse'] = array('EQ', $id_warehouse);
        $list = M('WarehouseAllocationIn')->alias('wai')->field("wai.*,pk.sku,p.title,p.inner_name,user.user_nicename,wga.id_warehouse,wga.goods_name,wga.id_warehouse")
            ->join('__WAREHOUSE_GOODS_ALLOCATION__ as wga on wga.id_warehouse_allocation = wai.id_warehouse_allocation','LEFT')
            ->join('__USERS__ as user on user.id = wai.id_users','LEFT')
          //  ->join('__WAREHOUSE_ALLOCATION_INOUT__ as wain on wain.id = wai.warehouse_allocation_inout_id','LEFT')
            ->join('__PRODUCT_SKU__ as pk on wai.id_product_sku = pk.id_product_sku','LEFT')
            ->join('__PRODUCT__ as p on wai.id_product = p.id_product','LEFT')
            ->where($where)->order("created_at DESC")->select();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $this->assign('list',$list);
        $this->assign('data',$data);
        $this->assign('warehouse',$warehouse);
        $this->assign('id',$where['warehouse_allocation_inout_id']);
        $this->display();
    }
    //导入SKU，货位和数量
    public function import_sku() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        $other_inout = M('WarehouseAllocationIn')->where(array('warehouse_allocation_inout_id'=>$_GET['id']))->find();
        if(IS_POST) {
            if(isset($_GET['case']) && $_GET['case']=="out"){
                $model=D('Common/WarehouseAllocationOut');
                $case=1;
            }else{
                $model=D('Common/WarehouseAllocationIn');
            }
            $data = I('post.sku_data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_sku_out', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {

                $row = trim($row);
                $row = explode("\t", trim($row), 4);
                if (empty($row) || count($row)!=4){
                    $flag = false;
                    $info['error'][] = sprintf('第%s行: 格式错误',$count++,$sku);
                    continue;
                }
                $sku = $row[0];
                $warehouse=$row[1];
                $wa_name = $row[2];
                $qty = $row[3];
                $id_warehouse=M('Warehouse')->where(['title'=>$warehouse])->getField('id_warehouse');
                if($id_warehouse > 0){
                    $product_sku = M('ProductSku')->where(array('sku' => $sku, 'status' => 1))->find();
                    $warehouse_allocation = M('WarehouseGoodsAllocation')->where(array('goods_name' =>$wa_name,'id_warehouse'=>$id_warehouse))->find();
                    if ($product_sku && $warehouse_allocation) {
                        $indata['warehouse_allocation_inout_id']=$id;
                        $indata['id_users']=$_SESSION['ADMIN_ID'];
                        $indata['created_at']=date('Y-m-d H:i:s');
                        $indata['id_product']=$product_sku['id_product'];
                        $indata['id_product_sku']=$product_sku['id_product_sku'];
                        $indata['id_warehouse_allocation']=$warehouse_allocation['id_warehouse_allocation'];
                        $indata['warehouse_allocation_name']=$warehouse_allocation['goods_name'];
                        $indata['qty']=$qty;
                        $in=$model->add($indata);
                        if($in==false){
                            $flag = false;
                            $info['error'][] = sprintf('第%s行: sku %s 导入失败',$count++,$sku);
                        }else{
                            $flag = true;
                            $info['success'][] = sprintf('第%s行: 导入成功',$count++);
                        }

                    } else {
                        $flag = false;
                        $msg = 'sku或者货位编码不正确';
                        $info['error'][] = sprintf('第%s行: sku %s 或者货位编码 %s 不正确',$count++,$sku,$wa_name);
                    }
                }else{
                    $flag = false;
                    $msg = '找不到仓库';
                    $info['error'][] = sprintf('第%s行: 找不到仓库',$count++,$warehouse);
                }

            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '上架单导入SKU', $path);
        }
        $this->assign('data',$other_inout);
        $this->assign('id',$id);
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->display();
    }
    //导入SKU，货位和数量
    public function import_sku_out() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        $other_inout = M('WarehouseAllocationOut')->where(array('warehouse_allocation_inout_id'=>$_GET['id']))->find();
        if(IS_POST) {
                $model=D('Common/WarehouseAllocationOut');
                $case=1;

            $data = I('post.sku_data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_sku_out', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {

                $row = trim($row);
                $row = explode("\t", trim($row), 4);
                if (empty($row) || count($row)!=4){
                    $flag = false;
                    $info['error'][] = sprintf('第%s行: 格式错误',$count++,$sku);
                    continue;
                }
                $sku = $row[0];
                $warehouse=$row[1];
                $wa_name = $row[2];
                $qty = $row[3];
                $id_warehouse=M('Warehouse')->where(['title'=>$warehouse])->getField('id_warehouse');
                if($id_warehouse > 0){
                    $product_sku = M('ProductSku')->where(array('sku' => $sku, 'status' => 1))->find();
                    $warehouse_allocation = M('WarehouseGoodsAllocation')->where(array('goods_name' =>$wa_name,'id_warehouse'=>$id_warehouse))->find();
                    if ($product_sku && $warehouse_allocation) {
                        $indata['warehouse_allocation_inout_id']=$id;
                        $indata['id_users']=$_SESSION['ADMIN_ID'];
                        $indata['created_at']=date('Y-m-d H:i:s');
                        $indata['id_product']=$product_sku['id_product'];
                        $indata['id_product_sku']=$product_sku['id_product_sku'];
                        $indata['id_warehouse_allocation']=$warehouse_allocation['id_warehouse_allocation'];
                        $indata['warehouse_allocation_name']=$warehouse_allocation['goods_name'];
                        $indata['qty']=$qty;
                        $in=$model->add($indata);
                        if($in==false){
                            $flag = false;
                            $info['error'][] = sprintf('第%s行: sku %s 导入失败',$count++,$sku);
                        }else{
                            $flag = true;
                            $info['success'][] = sprintf('第%s行: 导入成功',$count++);
                        }

                    } else {
                        $flag = false;
                        $msg = 'sku或者货位编码不正确';
                        $info['error'][] = sprintf('第%s行: sku %s 或者货位编码 %s 不正确',$count++,$sku,$wa_name);
                    }
                }else{
                    $flag = false;
                    $msg = '找不到仓库';
                    $info['error'][] = sprintf('第%s行: 找不到仓库',$count++,$warehouse);
                }

            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '下架单导入SKU', $path);
        }
        $this->assign('data',$other_inout);

        $this->assign('id',$id);
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->display();
    }

    //批量作废
    public function batch_del() {
        if(IS_AJAX) {
            try {
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                if ($ids && is_array($ids)) {
                    $flag = true;
                    $count = 0;
                    $msg = array();
                    foreach ($ids as $key=>$id) {
                        $inout = M('WarehouseAllocationInout')->where(array('id'=>$id,'status'=>1))->find();
                        if($inout) {
                            $result = D('Common/WarehouseAllocationInout')->where(array('id'=>$id))->delete();
                            if ($result) {
                                if($inout['type']==2){
                                    $count=D('Common/WarehouseAllocationOut')->where(array('warehouse_allocation_inout_id'=>$id))->delete();
                                }else{
                                    $count=D('Common/WarehouseAllocationIn')->where(array('warehouse_allocation_inout_id'=>$id))->delete();
                                }
                            }
                            $msg[] = '作废成功';
                        } else {
                            $flag = false;
                            $msg[] = '当前对象必须是未提交状态';
                            $count++;
                            continue;
                        }
                    }
                    if($flag) {
                        $status = 1;
                        $message = implode("\r\n",$msg);
                    } else {
                        $status = 0;
                        $message = '失败，行数：'.$count."\r\n".implode("\r\n",$msg);
                    }
                }
            }catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '作废上下架单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }
    //导入SKU，货位和数量逻辑
    public function import_sku_post() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        if(IS_POST) {
            if(isset($_GET['case']) && $_GET['case']=="out"){
                $model=D('Common/WarehouseAllocationOut');
                $case=1;
            }else{
                $model=D('Common/WarehouseAllocationIn');
            }
            $data = I('post.sku_data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_sku_out', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {

                $row = trim($row);
                if (empty($row)) continue;
                $row = explode("\t", trim($row), 4);
                $sku = $row[0];
                $warehouse=$row[1];
                $wa_name = $row[2];
                $qty = $row[3];
                $id_warehouse=M('Warehouse')->where(['title'=>$warehouse])->getField('id_warehouse');
                if($id_warehouse > 0){
                    $product_sku = M('ProductSku')->where(array('sku' => $sku, 'status' => 1))->find();
                    $warehouse_allocation = M('WarehouseGoodsAllocation')->where(array('goods_name' =>$wa_name,'id_warehouse'=>$id_warehouse))->find();
                    if ($product_sku && $warehouse_allocation) {
                        $indata['warehouse_allocation_inout_id']=$id;
                        $indata['id_users']=$_SESSION['ADMIN_ID'];
                        $indata['created_at']=date('Y-m-d H:i:s');
                        $indata['id_product']=$product_sku['id_product'];
                        $indata['id_product_sku']=$product_sku['id_product_sku'];
                        $indata['id_warehouse_allocation']=$warehouse_allocation['id_warehouse_allocation'];
                        $indata['warehouse_allocation_name']=$warehouse_allocation['goods_name'];
                        $indata['qty']=$qty;
                        $in=$model->add($indata);
                        if($in==false){
                            $flag = false;
                            $info['error'][] = sprintf('第%s行: sku %s 导入失败',$count++,$sku);
                        }else{
                            $flag = true;
                            $info['success'][] = sprintf('第%s行: 导入成功',$count++);
                        }

                    } else {
                        $flag = false;
                        $msg = 'sku或者货位编码不正确';
                        $info['error'][] = sprintf('第%s行: sku %s 或者货位编码 %s 不正确',$count++,$sku,$wa_name);
                    }
                }else{
                    $flag = false;
                    $msg = '找不到仓库';
                    $info['error'][] = sprintf('第%s行: 找不到仓库',$count++,$warehouse);
                }

            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '上下架单导入SKU', $path);
//            if ($flag) {
//                if(isset($_GET['case']) && $_GET['case']=="out"){
//                    $this->redirect('inout/detailout', array('id' => $id));
//                    $case=1;
//                }else{
//                    $this->redirect('inout/detail', array('id' => $id));
//                }
//
//            } else {
//                $this->error($info);
//            }
        }
    }
    //保存-上架
    public function save()
    {
        $flag = true;
        if (IS_POST) {
            $id = $_POST['id'];
            if(!empty($_REQUEST['created_at']) || !empty($_REQUEST['description'])){
                $ret=M('WarehouseAllocationInout')->where(array('id'=>$id))->save(['created_at'=>$_REQUEST['created_at'],'description'=>$_REQUEST['description']]);
            }
            if(!isset($_REQUEST['case']) || $_REQUEST['case']!=1){
                $wa_name = $_POST['wa_name'];
                $id_warehouse = $_POST['id_warehouse'];
                $qty = $_POST['qty'];

                $product_sku = M('ProductSku')->where(array('sku' => $_POST['sku_name'], 'status' => 1))->find();
                $warehouse_allocation = M('WarehouseGoodsAllocation')->where(array('goods_name' =>$wa_name,'id_warehouse'=>$id_warehouse))->find();
                if ($product_sku && $warehouse_allocation) {
                    $indata['warehouse_allocation_inout_id']=$id;
                    $indata['id_users']=$_SESSION['ADMIN_ID'];
                    $indata['created_at']=date('Y-m-d H:i:s');
                    $indata['id_product']=$product_sku['id_product'];
                    $indata['id_product_sku']=$product_sku['id_product_sku'];
                    $indata['id_warehouse_allocation']=$warehouse_allocation['id_warehouse_allocation'];
                    $indata['warehouse_allocation_name']=$warehouse_allocation['goods_name'];
                    $indata['qty']=$qty;
                    $in=M('WarehouseAllocationIn')->add($indata);
                } else {
                    $flag = false;
                    $msg = 'sku或者货位编码不正确';
                }
            }else{
                if($ret){
                    $status=1;
                    $message='';
                }else{
                    $status=0;
                    $message='保存失败';
                }
                $return = array('status' => $status, 'message' => $message);
                echo json_encode($return);exit();
            }

        }
        if ($flag) {
            $this->redirect(U('warehouse/inout/detail/action/edit', array('id' => $id)));
            //$this->success("新增成功", U('warehouse/inout/detail/action/edit', array('id' => $id)));
        } else {
            $this->error($msg);
        }
    }
    //保存-下架
    public function saveout()
    {
        $flag = true;
        if (IS_POST) {
            $id = $_POST['id'];
            if(!empty($_REQUEST['created_at']) || !empty($_REQUEST['description'])){
                $ret=M('WarehouseAllocationInout')->where(array('id'=>$id))->save(['created_at'=>$_REQUEST['created_at'],'description'=>$_REQUEST['description']]);
            }
            if(!isset($_REQUEST['case']) || $_REQUEST['case']!=1){
                $wa_name = $_POST['wa_name'];
                $qty = $_POST['qty'];
                $id = $_POST['id'];
                $id_warehouse=$_POST['id_warehouse'];
                $product_sku = M('ProductSku')->where(array('sku' => $_POST['sku_name'], 'status' => 1))->find();
                $warehouse_allocation = M('WarehouseGoodsAllocation')->where(array('goods_name' =>$wa_name,'id_warehouse'=>$id_warehouse))->find();
                if ($product_sku && $warehouse_allocation) {
                    $indata['warehouse_allocation_inout_id']=$id;
                    $indata['id_users']=$_SESSION['ADMIN_ID'];
                    $indata['created_at']=date('Y-m-d H:i:s');
                    $indata['id_product']=$product_sku['id_product'];
                    $indata['id_product_sku']=$product_sku['id_product_sku'];
                    $indata['id_warehouse_allocation']=$warehouse_allocation['id_warehouse_allocation'];
                    $indata['warehouse_allocation_name']=$warehouse_allocation['goods_name'];
                    $indata['qty']=$qty;
                    $in=M('WarehouseAllocationOut')->add($indata);
                } else {
                    $flag = false;
                    $msg = 'sku或者货位编码不正确';
                }
            }else{
                if($ret){
                    $status=1;
                    $message='';
                }else{
                    $status=0;
                    $message='保存失败';
                }
                $return = array('status' => $status, 'message' => $message);
                echo json_encode($return);exit();
            }

        }
        if ($flag) {
            $this->redirect(U('warehouse/inout/detailout/action/edit', array('id' => $id)));
            //$this->success("新增成功", U('warehouse/inout/detailout/action/edit', array('id' => $id)));
        } else {
            $this->error($msg);
        }
    }
    //查看明细页
    public function look(){
        $where = array();
        $where['warehouse_allocation_inout_id']=$_REQUEST['id'];
        //$where['wga.id_warehouse'] = array('EQ', $id_warehouse);
        $case=0;
        if(isset($_GET['case']) && $_GET['case']=="out"){
            $model=D('Common/WarehouseAllocationOut');
            $case=1;
        }else{
            $model=D('Common/WarehouseAllocationIn');
        }
        $list = $model->alias('wai')->field("wai.*,wain.*,pk.sku,p.title,p.inner_name,user.user_nicename,wga.id_warehouse,wga.goods_name,wga.id_warehouse")
            ->join('__WAREHOUSE_GOODS_ALLOCATION__ as wga on wga.id_warehouse_allocation = wai.id_warehouse_allocation','LEFT')
            ->join('__USERS__ as user on user.id = wai.id_users','LEFT')
            ->join('__WAREHOUSE_ALLOCATION_INOUT__ as wain on wain.id = wai.warehouse_allocation_inout_id','LEFT')
            ->join('__PRODUCT_SKU__ as pk on wai.id_product_sku = pk.id_product_sku','LEFT')
            ->join('__PRODUCT__ as p on wai.id_product = p.id_product','LEFT')
            ->where($where)->select();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $this->assign('list',$list);
        $this->assign('warehouse',$warehouse);
        $this->assign('case',$case);
        $this->display();

    }
    //提交
    public function update_status(){
        if(IS_AJAX) {
            try {
                $ivIds = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                $msg = '提交';
                //'statuser_id'=>$_SESSION['ADMIN_ID'],'status_time'=>  date('Y-m-d H:i:s',time())
                $upd_data=array('status'=>2,'statuser_id' => $_SESSION['ADMIN_ID'], 'status_time' => date('Y-m-d H:i:s'));
                if ($ivIds && is_array($ivIds)) {
                    foreach ($ivIds as $ivid) {
                        $ivstatus = M('WarehouseAllocationInout')->field('*')->where(array('id'=>$ivid))->find();
                        if($ivstatus['type']==2){
                            $count=D('Common/WarehouseAllocationOut')->where(array('warehouse_allocation_inout_id'=>$ivid))->count();
                        }else{
                            $count=D('Common/WarehouseAllocationIn')->where(array('warehouse_allocation_inout_id'=>$ivid))->count();
                        }
                        if(0>=$count){
                            $status = 0;
                            $message = $msg.'失败';
                            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
                            $return = array('status' => $status, 'message' => $message);
                            echo json_encode($return);exit();

                        }
                        if($ivstatus['status']==1) {//  防止多次提交
                            $ret=M('WarehouseAllocationInout')->where(array('id'=>$ivid))->save($upd_data);
                            if($ret){
                                //更新库存
                                $this->update_stock($ivid,$ivstatus['type']);
                            }
                        }
                    }
                    $status = 1;
                    $message = $msg.'成功';
                }
            } catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }

            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }
    //删除明细
    public function del_in(){
        if(IS_AJAX) {
            try {
                if(isset($_GET['case']) && $_GET['case']=="out"){
                    $model=D('Common/WarehouseAllocationOut');
                }else{
                    $model=D('Common/WarehouseAllocationIn');
                }
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                if ($ids && is_array($ids)) {
                    $flag = true;
                    $count = 0;
                    foreach ($ids as $key=>$id) {
                        $res = $model->where(array('id'=>$id))->delete();
                        if(!$res) {
                            $flag = false;
                            $count++;
                            continue;
                        }
                    }

                    if($flag) {
                        $status = 1;
                        $message = '成功';
                    } else {
                        $status = 0;
                        $message = '失败，行数：'.$count.'，当前对象删除失败';
                    }
                }
            }catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除物理调整单明细');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }
    //更新库存
    public function update_stock($id,$type){

        if(isset($type) && $type==2){
            $model=D('Common/WarehouseAllocationOut');
        }else{
            $model=D('Common/WarehouseAllocationIn');
        }
        $indata = $model->where(array('warehouse_allocation_inout_id'=>$id))->select();
        if($indata==false){
            return false;
        }
        foreach($indata as $k => $v){
            $where2['id_product']= $v['id_product'];
            $where2['id_product_sku']= $v['id_product_sku'];
            $where2['id_warehouse_allocation']=$v['id_warehouse_allocation'];
            $data=M('WarehouseAllocationStock')->field('id,quantity')->where($where2)->find();
            if(empty($data)){
                //return false;;
                //没有相对的sku和货位时增加一条记录
                $add['id_warehouse_allocation']=$v['id_warehouse_allocation'];
                $add['id_warehouse']=M('WarehouseGoodsAllocation')->where(['id_warehouse_allocation'=>$v['id_warehouse_allocation']])->getField('id_warehouse');
                $add['id_product']=$v['id_product'];
                $add['id_product_sku']=$v['id_product_sku'];
                if(isset($type) && $type==2){
                    $add['quantity']=0;//待确定是否负数
                }else{
                    $add['quantity']=$v['qty'];
                }
                $add['updated_at'] = date('Y-m-d H:i:s');
                $add['id_users'] = $_SESSION['ADMIN_ID'];
                M('WarehouseAllocationStock')->add($add);
            }else{
                //更改库存
                $stock_before=$data['quantity'];
                if(isset($type) && $type==2){
                    if($stock_before >$v['qty']){
                        $stock_new=$stock_before-$v['qty'];
                    }else{
                        $stock_new=0;
                    }

                }else{
                    $stock_new=$stock_before+$v['qty'];
                }
                $update['quantity']=$stock_new;
                $result=M('WarehouseAllocationStock')->where('id='.$data['id'])->save($update);
//                if($result==false){
//                    return false;
//                }
            }

        }
        return true;
    }
    /**
     * 产生新的单据编码
     */
    protected function createDocno($tableName,$prefix) {
        if(empty($tableName)||empty($prefix)){
            return FALSE;
        }
        $prefix = $prefix. date('ymd', time());
        $cond['billdate'] = array('like', '%' . date('Y-m-d', time()) . '%');
        $lastDocno = M($tableName)->where($cond)->order('id desc')->field('docno')->find();
        $lastNum = 0;
        if ($lastDocno['docno']) {
            $lastNum = substr($lastDocno['docno'], strlen($prefix));
        }
        $cur_num = $lastNum + 1;
        return $prefix . str_pad($cur_num, 7, '0', STR_PAD_LEFT);
    }
    //搜索提示
    public function search_text(){
        $goods_name = $_GET['goods_name'];
        if(isset($_GET['id_warehouse']) && !empty($_GET['id_warehouse'])){
            $where['id_warehouse'] = $_GET['id_warehouse'];
        }
        $where['goods_name']=array('LIKE','%'.$goods_name.'%');
        $result = M('WarehouseGoodsAllocation')->field('goods_name,id_warehouse_allocation')
            ->where($where)->limit(5)
            ->select();
        $result = array_column($result,'goods_name');
        $data = '<ul>';
        foreach($result as $value){
            $data.='<li style="padding-top:5px;padding-bottom:5px">'.$value.'</a></li>';
        }
        $data.='</ul>';
        echo json_encode($data);
    }
    //新增上架
    public function in(){
        //做记录
        //erp_warehouse_allocation_inout 货位上下架主表
        //erp_warehouse_allocation_in货位上架表
        $inoutdata['id_users']=1;
        $inoutdata['created_at']=date('Y-m-d H:i:s');
        $inout=M('WarehouseAllocationInout')->add($inoutdata);
        if($inout == false){
            echo 0;
            exit;
        }
        $stock_in=I('request.in');
        $indata['warehouse_allocation_inout_id']=$inout;
        $indata['id_users']=$_SESSION['ADMIN_ID'];
        $indata['created_at']=date('Y-m-d H:i:s');
        $indata['id_product']=I('request.id_product');
        $indata['id_product_sku']=I('request.id_product_sku');
        $indata['id_warehouse_allocation']=I('request.id_warehouse_allocation');
        $indata['warehouse_allocation_name']=I('request.id_warehouse_allocation');
        $indata['qty']=$stock_in;
        $in=M('WarehouseAllocationIn')->add($indata);
        if($in == false){
            echo 0;
            exit;
        }else{
            $where2['id_product']= $indata['id_product'];
            $where2['id_product_sku']= $indata['id_product_sku'];
            $where2['id_warehouse_allocation']=$indata['id_warehouse_allocation'];
            $data=M('WarehouseAllocationStock')->field('id,quantity')
                ->where($where2)
                ->find();
            if(empty($data)){
                echo 0;
                exit;
            }
            //更改库存
            $stock_before=$data['quantity'];
            $stock_new=$stock_before+$stock_in;
            $update['quantity']=$stock_new;
            $result=M('WarehouseAllocationStock')->where('id='.$data['id'])->save($update);
            if($result==false){
                echo 0;
                exit;
            }
            echo 1;
            exit;
        }
    }
    /*
     * 下架列表
     */
    public function indexofout(){
        $where = array();
        $created_at_array = array();
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {

            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['wai.created_at'] = $created_at_array;
        }else{
            $_GET['start_time']=date('Y-m-d 00:00',  strtotime('-7days'));
            $_GET['end_time']=date('Y-m-d 23:59',  time());
            $created_at_array[] = array('EGT', $_GET['start_time']);
            $created_at_array[] = array('LT', $_GET['end_time']);
            $where['wai.created_at'] = $created_at_array;
    }
        if (!empty($_GET['status'])) {
            $where['wai.status'] = $_GET['status'];
        }
        if (!empty($_GET['user'])) {
            $where['user.user_nicename'] =array("like",'%'.$_GET['user'].'%');
        }
        $where['type']=2;
        //$where['wga.id_warehouse'] = array('EQ', $id_warehouse);
        $count = M('WarehouseAllocationInout')->alias('wai')
            ->field("wai.*,user.user_nicename")
            ->join('__USERS__ as user on user.id = wai.id_users','LEFT')->where($where)->order("created_at DESC")->count();
        $page = $this->page($count, $this->pager );
        $list = M('WarehouseAllocationInout')->alias('wai')
            ->field("wai.*,user.user_nicename")
            ->join('__USERS__ as user on user.id = wai.id_users','LEFT')
            ->where($where)->order("created_at DESC")->limit($page->firstRow, $page->listRows)->select();
        $user = M('Users')->getField('id,user_nicename',true);
        foreach($list as $key => $val){
            $inoutitem = M('WarehouseAllocationOut')->field('SUM(qty) as qty,count(id) as count')->where(array('warehouse_allocation_inout_id'=>$val['id']))->find();
            $list[$key]['tj_name'] = $user[$val['statuser_id']];
            $list[$key]['count'] = $inoutitem['count'];
            $list[$key]['count_sum'] = $inoutitem['qty'];
        }
        //var_dump($list);die;
        $this->assign('list',$list);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("status", $this->status);

        $this->display();
    }
    //新增/编辑明细页—下架
    public function detailout(){
        $where = array();
        if($_REQUEST['action']=='add' && isset($_REQUEST['action'])){
            $inoutdata['id_users']=$_SESSION['ADMIN_ID'];
            $inoutdata['created_at']=date('Y-m-d H:i:s');
            $inoutdata['type']=2;
            $inout=M('WarehouseAllocationInout')->add($inoutdata);
            if($inout != false){
                $where['warehouse_allocation_inout_id']=$inout;
            }

        }else{
            $where['warehouse_allocation_inout_id']=$_REQUEST['id'];
        }
        $data= M('WarehouseAllocationInout') ->where(['id'=>$where['warehouse_allocation_inout_id']])->find();
        //$where['wga.id_warehouse'] = array('EQ', $id_warehouse);
        $list = M('WarehouseAllocationOut')->alias('wai')->field("wai.*,pk.sku,p.title,p.inner_name,user.user_nicename,wga.id_warehouse,wga.goods_name,wga.id_warehouse")
            ->join('__WAREHOUSE_GOODS_ALLOCATION__ as wga on wga.id_warehouse_allocation = wai.id_warehouse_allocation','LEFT')
            ->join('__USERS__ as user on user.id = wai.id_users','LEFT')
            //  ->join('__WAREHOUSE_ALLOCATION_INOUT__ as wain on wain.id = wai.warehouse_allocation_inout_id','LEFT')
            ->join('__PRODUCT_SKU__ as pk on wai.id_product_sku = pk.id_product_sku','LEFT')
            ->join('__PRODUCT__ as p on wai.id_product = p.id_product','LEFT')
            ->where($where)->order("created_at DESC")->select();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $this->assign('list',$list);
        $this->assign('data',$data);
        $this->assign('warehouse',$warehouse);
        $this->assign('id',$where['warehouse_allocation_inout_id']);
        $this->display();
    }
    //修改页面条数
    public function setpagerow(){
        $setRow = is_numeric($_POST['row'])?$_POST['row']:$this->pager;
        $_SESSION['set_page_row'] = $setRow;
    }



    //下架
    public function out(){
        $id = I('request.id');
        if(empty($id)){
            echo 0;
            exit;
        }
        $data=M('WarehouseAllocationStock')->alias('was')->field('was.quantity,was.id_product,was.id_product_sku,was.id_warehouse_allocation')
            ->join('__PRODUCT_SKU__ as pk on was.id_product_sku = pk.id_product_sku','LEFT')
            ->join('__PRODUCT__ as p on was.id_product = p.id_product','LEFT')
            ->where(["id"=>$id])
            ->find();
        if(empty($data)){
            echo 0;
            exit;
        }
        //更改库存
        $stock_before=$data['quantity'];
        $stock_out=I('request.out');
        $stock_new=$stock_before-$stock_out;
        $update=array();
        $update['quantity']=$stock_new;
        $result=M('WarehouseAllocationStock')->where('id='.$id)->save($update);
        if($result==false){
            echo 0;
            exit;
        }
        //做记录
        //erp_warehouse_allocation_inout 货位上下架主表
        //erp_warehouse_allocation_in货位上架表
        //erp_warehouse_allocation_out货位下架表
        $inoutdata['id_users']=1;
        $inoutdata['created_at']=date('Y-m-d H:i:s');
        $inout=M('WarehouseAllocationInout')->add($inoutdata);
//erp_warehouse_allocation_inout_id	int	外键关联
//id_users	int	制单人
//created_at	datetime	上架日期
//id_product	int	产品
//id_product_sku	int	产品 sku
//id_warehouse_allocation	int	货位编码
//warehousename	varchar	货位编码
//qty	int	上架数量
        if($inout == false){
            echo 0;
            exit;
        }
        $indata['erp_warehouse_allocation_inout_id']=$inout;
        $indata['id_users']=1;
        $indata['created_at']=date('Y-m-d H:i:s');
        $indata['id_product']=$data['id_product'];
        $indata['id_product_sku']=$data['id_product_sku'];
        $indata['id_warehouse_allocation']=$data['id_warehouse_allocation'];
        $indata['warehouse_allocation_name']=$data['id_warehouse_allocation'];
        $indata['qty']=$stock_out;
        $in=M('WarehouseAllocationOut')->add($indata);
        if($in == false){
            echo 0;
            exit;
        }else{
            echo 1;
            exit;
        }

    }
}
