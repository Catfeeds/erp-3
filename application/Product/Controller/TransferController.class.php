<?php

namespace Product\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;
use Common\Lib\Procedure;

class TransferController extends AdminbaseController {

    protected $time_start,$time_end,$pager;

    public function _initialize() {
        parent::_initialize();
        $this->time_start = I('get.start_time', date('Y-m-d 00:00', strtotime('-7 day')));
        $this->time_end = I('get.end_time', date('Y-m-d 00:00', strtotime('+1 day')));
        $this->pager = isset($_SESSION['set_page_row']) ? $_SESSION['set_page_row'] : 20;
    }

    //产品调拨单列表
    public function index() {
        $_GET['start_time'] = $this->time_start;
        $_GET['end_time'] = $this->time_end;
        $where = array();
        if(isset($_GET['out_department_id']) && $_GET['out_department_id']) {
            $where['c_orig_depart_id'] = $_GET['out_department_id'];
        }
        if(isset($_GET['in_department_id']) && $_GET['in_department_id']) {
            $where['c_dest_depart_id'] = $_GET['in_department_id'];
        }
        if(isset($_GET['status_id']) && $_GET['status_id']) {
            $where['status'] = $_GET['status_id'];
        }
        if(isset($_GET['start_time']) && $_GET['start_time']) {
            $time_arr = array();
            $time_arr[] = array('EGT',$_GET['start_time']);
            if($_GET['end_time']) $time_arr[] = array('LT',$_GET['end_time']);
            $where[] = array('bill_date'=>$time_arr);
        }
        if(isset($_GET['docno']) && $_GET['docno']) {
            $where['docno'] = array('like','%'.$_GET['docno'].'%');
        }

        $user = M('Users')->getField('id,user_nicename',true);
        $list_count = M('ProductTransfer')->where($where)->count();
        $page = $this->page($list_count,$this->pager);
        $list = M('ProductTransfer')->where($where)->limit($page->firstRow.','.$page->listRows)->order('create_time DESC')->select();
        foreach($list as $key=>$val) {
            $product_transferitem = M('ProductTransferitem')->field('SUM(qty_stock) as qty')->where(array('erp_product_transfer_id'=>$val['id']))->find();
            $list[$key]['user_name'] = $user[$val['owner_id']];
            $list[$key]['tj_name'] = $user[$val['statuser_id']];
            $list[$key]['count'] = M('ProductTransferitem')->where(array('erp_product_transfer_id'=>$val['id']))->count();
            $list[$key]['count_sum'] = $product_transferitem['qty'] ? $product_transferitem['qty'] : 0;
            $list[$key]['out_department'] = M('Department')->where(array('id_department'=>$val['c_orig_depart_id']))->getField('title');
            $list[$key]['in_department'] = M('Department')->where(array('id_department'=>$val['c_dest_depart_id']))->getField('title');
        }
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看产品调拨单列表');
        $department = M('Department')->where(array('type'=>1))->getField('id_department,title',true);
        $this->assign('department',$department);
        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    //调出部门主管审核
    public function out_department_check() {
        $_GET['start_time'] = $this->time_start;
        $_GET['end_time'] = $this->time_end;
        $where = array();
        if(isset($_GET['out_department_id']) && $_GET['out_department_id']) {
            $where['c_orig_depart_id'] = $_GET['out_department_id'];
        }
        if(isset($_GET['in_department_id']) && $_GET['in_department_id']) {
            $where['c_dest_depart_id'] = $_GET['in_department_id'];
        }
        if(isset($_GET['status_id']) && $_GET['status_id']) {
            $where['out_status'] = $_GET['status_id'];
        }
        if(isset($_GET['start_time']) && $_GET['start_time']) {
            $time_arr = array();
            $time_arr[] = array('EGT',$_GET['start_time']);
            if($_GET['end_time']) $time_arr[] = array('LT',$_GET['end_time']);
            $where[] = array('bill_date'=>$time_arr);
        }
        if(isset($_GET['docno']) && $_GET['docno']) {
            $where['docno'] = array('like','%'.$_GET['docno'].'%');
        }
        $where['status'] = 2;
        $user = M('Users')->getField('id,user_nicename',true);
        $list_count = M('ProductTransfer')->where($where)->count();
        $page = $this->page($list_count,$this->pager);
        $list = M('ProductTransfer')->where($where)->limit($page->firstRow.','.$page->listRows)->order('create_time DESC')->select();
        foreach($list as $key=>$val) {
            $product_transferitem = M('ProductTransferitem')->field('SUM(qty_stock) as qty')->where(array('erp_product_transfer_id'=>$val['id']))->find();
            $list[$key]['user_name'] = $user[$val['owner_id']];
            $list[$key]['tj_name'] = $user[$val['statuser_id']];
            $list[$key]['count'] = M('ProductTransferitem')->where(array('erp_product_transfer_id'=>$val['id']))->count();
            $list[$key]['count_sum'] = $product_transferitem['qty'] ? $product_transferitem['qty'] : 0;
            $list[$key]['out_department'] = M('Department')->where(array('id_department'=>$val['c_orig_depart_id']))->getField('title');
            $list[$key]['in_department'] = M('Department')->where(array('id_department'=>$val['c_dest_depart_id']))->getField('title');
        }
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看产品调拨单列表');
        $department = M('Department')->where(array('type'=>1))->getField('id_department,title',true);
        $this->assign('department',$department);
        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    //调入部门主管审核
    public function in_department_check() {
        $_GET['start_time'] = $this->time_start;
        $_GET['end_time'] = $this->time_end;
        $where = array();
        if(isset($_GET['out_department_id']) && $_GET['out_department_id']) {
            $where['c_orig_depart_id'] = $_GET['out_department_id'];
        }
        if(isset($_GET['in_department_id']) && $_GET['in_department_id']) {
            $where['c_dest_depart_id'] = $_GET['in_department_id'];
        }
        if(isset($_GET['status_id']) && $_GET['status_id']) {
            $where['in_status'] = $_GET['status_id'];
        }
        if(isset($_GET['start_time']) && $_GET['start_time']) {
            $time_arr = array();
            $time_arr[] = array('EGT',$_GET['start_time']);
            if($_GET['end_time']) $time_arr[] = array('LT',$_GET['end_time']);
            $where[] = array('bill_date'=>$time_arr);
        }
        if(isset($_GET['docno']) && $_GET['docno']) {
            $where['docno'] = array('like','%'.$_GET['docno'].'%');
        }
        $where['status'] = 2;
        $where['out_status'] = 2;
        $user = M('Users')->getField('id,user_nicename',true);
        $list_count = M('ProductTransfer')->where($where)->count();
        $page = $this->page($list_count,$this->pager);
        $list = M('ProductTransfer')->where($where)->limit($page->firstRow.','.$page->listRows)->order('create_time DESC')->select();
        foreach($list as $key=>$val) {
            $product_transferitem = M('ProductTransferitem')->field('SUM(qty_stock) as qty')->where(array('erp_product_transfer_id'=>$val['id']))->find();
            $list[$key]['user_name'] = $user[$val['owner_id']];
            $list[$key]['tj_name'] = $user[$val['statuser_id']];
            $list[$key]['count'] = M('ProductTransferitem')->where(array('erp_product_transfer_id'=>$val['id']))->count();
            $list[$key]['count_sum'] = $product_transferitem['qty'] ? $product_transferitem['qty'] : 0;
            $list[$key]['out_department'] = M('Department')->where(array('id_department'=>$val['c_orig_depart_id']))->getField('title');
            $list[$key]['in_department'] = M('Department')->where(array('id_department'=>$val['c_dest_depart_id']))->getField('title');
        }
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看产品调拨单列表');
        $department = M('Department')->where(array('type'=>1))->getField('id_department,title',true);
        $this->assign('department',$department);
        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    //保存单据页面
    public function add() {
        $time = I('post.time', date('Y-m-d 00:00'));
        $department = M('Department')->where(array('type'=>1))->getField('id_department,title',true);
        $this->assign('department',$department);
        $this->display();
    }

    //添加单据逻辑
    public function add_post() {
        if(IS_POST){
            $docno = M('ProductTransfer')->field('docno')->order('docno DESC')->find();
            $docno_num = substr($docno['docno'],3)+1;
            $data['docno'] = $docno ? 'PIV'.$docno_num : 'PIV'.date('ymd').'0000001';
            $data['bill_date'] = I('post.bill_date');
            $data['c_orig_depart_id'] = I('post.out_department_id');
            $data['c_dest_depart_id'] = I('post.in_department_id');
            $data['description'] = I('post.description');
            $data['status'] = 1;
            $data['owner_id'] = $_SESSION['ADMIN_ID'];
            $data['statuser_id'] = 0;
            $data['create_time'] = date('Y-m-d H:i:s');

            $product_transfer_id = D('Common/productTransfer')->add($data);

            if($product_transfer_id) {
                $this->redirect('transfer/add_details',array('id'=>$product_transfer_id));
            } else {
                $this->redirect('transfer/add');
            }
        }
    }

    //明细列表
    public function add_details() {
        $product_transfer = M('ProductTransfer')->where(array('id'=>$_GET['id']))->find();
        $product_transfer_item = M('ProductTransferitem')->where(array('erp_product_transfer_id'=>$_GET['id']))->select();
        foreach($product_transfer_item as $key=>$val) {
            $product = M('Product')->field('title,inner_name')->where(array('id_product'=>$val['id_product']))->find();
            $product_sku = M('ProductSku')->field('sku')->where(array('id_product_sku'=>$val['id_product_sku']))->find();
            $product_transfer_item[$key]['pro_name'] = $product['title'];
            $product_transfer_item[$key]['inner_name'] = $product['inner_name'];
            $product_transfer_item[$key]['sku'] = $product_sku['sku'];
        }
        $department = M('Department')->where(array('type'=>1))->getField('id_department,title',true);

        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看产品调拨单明细列表');
        $this->assign('department',$department);
        $this->assign('data',$product_transfer);
        $this->assign('product_transfer_item',$product_transfer_item);
        $this->display();
    }

    //调出明细列表
    public function out_details() {
        $product_transfer = M('ProductTransfer')->where(array('id'=>$_GET['id']))->find();
        $product_transfer_item = M('ProductTransferitem')->where(array('erp_product_transfer_id'=>$_GET['id']))->select();
        foreach($product_transfer_item as $key=>$val) {
            $product = M('Product')->field('title,inner_name')->where(array('id_product'=>$val['id_product']))->find();
            $product_sku = M('ProductSku')->field('sku')->where(array('id_product_sku'=>$val['id_product_sku']))->find();
            $product_transfer_item[$key]['pro_name'] = $product['title'];
            $product_transfer_item[$key]['inner_name'] = $product['inner_name'];
            $product_transfer_item[$key]['sku'] = $product_sku['sku'];
        }
        $department = M('Department')->where(array('type'=>1))->getField('id_department,title',true);

        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看产品调出单明细列表');
        $this->assign('department',$department);
        $this->assign('data',$product_transfer);
        $this->assign('product_transfer_item',$product_transfer_item);
        $this->display();
    }

    //调入明细列表
    public function in_details() {
        $product_transfer = M('ProductTransfer')->where(array('id'=>$_GET['id']))->find();
        $product_transfer_item = M('ProductTransferitem')->where(array('erp_product_transfer_id'=>$_GET['id']))->select();
        foreach($product_transfer_item as $key=>$val) {
            $product = M('Product')->field('title,inner_name')->where(array('id_product'=>$val['id_product']))->find();
            $product_sku = M('ProductSku')->field('sku')->where(array('id_product_sku'=>$val['id_product_sku']))->find();
            $product_transfer_item[$key]['pro_name'] = $product['title'];
            $product_transfer_item[$key]['inner_name'] = $product['inner_name'];
            $product_transfer_item[$key]['sku'] = $product_sku['sku'];
        }
        $department = M('Department')->where(array('type'=>1))->getField('id_department,title',true);

        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看产品调入单明细列表');
        $this->assign('department',$department);
        $this->assign('data',$product_transfer);
        $this->assign('product_transfer_item',$product_transfer_item);
        $this->display();
    }

    //导入SKU和数量
    public function import_sku() {
        $product_transfer = M('ProductTransfer')->where(array('id'=>$_GET['id']))->find();
        $product_transferitem = M('ProductTransferitem')->where(array('erp_product_transfer_id'=>$_GET['id']))->select();
        $department = M('Department')->where(array('type'=>1))->getField('id_department,title',true);
        $this->assign('department',$department);
        $this->assign('data',$product_transfer);
        $this->assign('product_transferitem',$product_transferitem);
        $this->display();
    }

    //添加明细逻辑
    public function add_details_post() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        if(IS_POST) {
            $other_data['id'] = $id;
            $other_data['bill_date'] = $_POST['bill_date'];
            $other_data['c_orig_depart_id'] = $_POST['out_department_id'];
            $other_data['c_dest_depart_id'] = $_POST['in_department_id'];
            $other_data['description'] = $_POST['description'];
            D('Common/ProductTransfer')->save($other_data);
            if (!empty($_POST['pro_id'])) {
                $product = M('Product')->where(array('id_product'=>$_POST['pro_id']))->find();
                if($product) {
                    $product_transfer_item = M('ProductTransferitem')->where(array('id_product'=>$_POST['pro_id'],'erp_product_transfer_id'=>$id))->select();
                    if(!$product_transfer_item) {
                        $product_sku = M('ProductSku')->where(array('id_product' => $product['id_product'], 'status' => 1))->select();
                        $msgs = array();
                        if ($product_sku) {
                            foreach ($product_sku as $key => $val) {
                                $product_sku_stock = M('WarehouseProduct')->field('SUM(quantity) as qty')->where(array('id_product_sku' => $val['id_product_sku']))->find();
                                $data['erp_product_transfer_id'] = $id;
                                $data['id_product'] = $val['id_product'];
                                $data['id_product_sku'] = $val['id_product_sku'];
                                $data['option_value'] = $val['title'];
                                $data['qty_stock'] = $product_sku_stock['qty'] ? $product_sku_stock['qty'] : 0;
                                $data['amt_stock'] = $product_sku_stock['qty'] * $val['purchase_price'];
                                D('Common/ProductTransferitem')->add($data);
                                $flag = true;
                            }

                        } else {
                            $flag = false;
                            $msg = 'SKU不存在或者无效';
                        }
                    } else {
                        $flag = false;
                        $msg = '产品已存在，请勿重复添加';
                    }
                } else {
                    $flag = false;
                    $msg = '产品ID不存在或者无效';
                }

                if ($flag) {
                    $this->redirect('transfer/add_details', array('id' => $id));
                } else {
                    $this->error(!empty($msgs)?implode("<br>",$msgs):$msg);
                }
            }
        }
    }

    //导入产品逻辑
    public function import_sku_post() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        $other_data['id'] = $id;
        $other_data['bill_date'] = $_POST['bill_date'];
        $other_data['c_orig_depart_id'] = $_POST['out_department_id'];
        $other_data['c_dest_depart_id'] = $_POST['in_department_id'];
        $other_data['description'] = $_POST['description'];
        D('Common/ProductTransfer')->save($other_data);
        if(IS_POST) {
            $data = I('post.sku_data');
            //导入记录到文件
            $path = write_file('product', 'import_sku', $data);
            $data = $this->getDataRow($data);
            $msg = array();
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row)) continue;
                $row = explode("\t", trim($row), 2);
                $pro_id = $row[0];
                $product = M('Product')->where(array('id_product' => $pro_id,'status'=>1))->find();
                $in_product = M('Product')->where(array('id_product' => $pro_id,'id_department'=>$_POST['in_department_id'],'status'=>1))->find();
                $out_product = M('Product')->where(array('id_product' => $pro_id,'id_department'=>$_POST['out_department_id'],'status'=>1))->find();
                if($product) {
                    if ($out_product) {
                        if (!$in_product) {
                            $product_sku = M('ProductSku')->where(array('id_product' => $pro_id, 'status' => 1))->select();
                            if ($product_sku) {
                                $product_transfer_item = M('ProductTransferitem')->where(array('id_product'=>$_POST['pro_id'],'erp_product_transfer_id'=>$id))->select();
                                if(!$product_transfer_item) {
                                    foreach ($product_sku as $key => $val) {
                                        $product_sku_stock = M('WarehouseProduct')->field('SUM(quantity) as qty')->where(array('id_product_sku' => $val['id_product_sku']))->find();
                                        $data['erp_product_transfer_id'] = $id;
                                        $data['id_product'] = $val['id_product'];
                                        $data['id_product_sku'] = $val['id_product_sku'];
                                        $data['option_value'] = $val['title'];
                                        $data['qty_stock'] = $product_sku_stock['qty'] ? $product_sku_stock['qty'] : 0;
                                        $data['amt_stock'] = $product_sku_stock['qty'] * $val['purchase_price'];
                                        D('Common/ProductTransferitem')->add($data);
                                        $flag = true;
                                    }
                                } else {
                                    continue;
                                }
                            } else {
                                $flag = false;
                                $msg[] = '这个产品ID:' . $pro_id . '的SKU不存在或者无效';
                                continue;
                            }
                        } else {
                            $flag = false;
                            $msg[] = '这个产品ID:' . $pro_id . '已经在调入部门中，不能重复进行调拨';
                            continue;
                        }
                    } else {
                        $flag = false;
                        $msg[] = '这个产品ID:' . $pro_id . '不在调出部门中，不能进行调拨';
                        continue;
                    }
                } else {
                    $flag = false;
                    $msg[] = '这个产品ID:' . $pro_id . '不存在';
                    continue;
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '产品调拨导入产品', $path);
            if ($flag) {
                $this->redirect('transfer/add_details', array('id' => $id));
            } else {
                $this->error(implode("\n",$msg));
            }
        }
    }

    //批量提交
    public function batch_sub() {
        if(IS_AJAX) {
            try {
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                if ($ids && is_array($ids)) {
                    $flag = true;
                    $msg = array();
                    foreach ($ids as $key=>$id) {
                        $product_transfer = M('ProductTransfer')->where(array('id'=>$id))->find();
                        $product_transfer_status = M('ProductTransfer')->where(array('id'=>$id,'status'=>2))->find();
                        if(!$product_transfer_status) {
                            if ($product_transfer) {
                                $product_transfer_item = M('ProductTransferitem')->where(array('erp_product_transfer_id' => $id))->select();
                                if ($product_transfer_item) {
                                    $result = D('Common/ProductTransfer')->where(array('id' => $id))->save(array('status' => 2, 'statuser_id' => $_SESSION['ADMIN_ID'], 'status_time' => date('Y-m-d H:i:s')));
                                    if ($result) {
                                        $msg[] = $product_transfer['docno'] . '提交成功';
                                    } else {
                                        $flag = false;
                                        $msg[] = '单据提交失败';
                                        continue;
                                    }
                                } else {
                                    $flag = false;
                                    $msg[] = $product_transfer['docno'] . '调拨单没有明细数据,不能提交';
                                    continue;
                                }
                            } else {
                                $flag = false;
                                $msg[] = '该单据不存在';
                                continue;
                            }
                        } else {
                            $flag = false;
                            $msg[] = $product_transfer['docno'] . '单据已提交，请勿重复提交';
                            continue;
                        }
                    }
                    if($flag) {
                        $status = 1;
                        $message = implode("\r\n",$msg);
                    } else {
                        $status = 0;
                        $message = implode("\r\n",$msg);
                    }
                }
            }catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '提交产品调拨单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    //调出主管审核提交
    public function outdepartment_batch_sub() {
        if(IS_AJAX) {
            try {
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                if ($ids && is_array($ids)) {
                    $flag = true;
                    $msg = array();
                    foreach ($ids as $key=>$id) {
                        $product_transfer = M('ProductTransfer')->where(array('id'=>$id))->find();
                        $product_transfer_status = M('ProductTransfer')->where(array('id'=>$id,'status'=>2,'out_status'=>2))->find();
                        if(!$product_transfer_status) {
                            if ($product_transfer) {
                                $product_transfer_item = M('ProductTransferitem')->where(array('erp_product_transfer_id' => $id))->select();
                                if ($product_transfer_item) {
                                    $result = D('Common/ProductTransfer')->where(array('id' => $id))->save(array('status' => 2, 'out_status'=>2));
                                    if ($result) {
                                        $msg[] = $product_transfer['docno'] . '产品调出单提交成功';
                                    } else {
                                        $flag = false;
                                        $msg[] = '单据提交失败';
                                        continue;
                                    }
                                } else {
                                    $flag = false;
                                    $msg[] = $product_transfer['docno'] . '调拨单没有明细数据,不能提交';
                                    continue;
                                }
                            } else {
                                $flag = false;
                                $msg[] = '该单据不存在';
                                continue;
                            }
                        } else {
                            $flag = false;
                            $msg[] = $product_transfer['docno'] . '单据已提交，请勿重复提交';
                            continue;
                        }
                    }
                    if($flag) {
                        $status = 1;
                        $message = implode("\r\n",$msg);
                    } else {
                        $status = 0;
                        $message = implode("\r\n",$msg);
                    }
                }
            }catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '提交产品调出单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    //调入主管审核提交
    public function indepartment_batch_sub() {
        if(IS_AJAX) {
            try {
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                if ($ids && is_array($ids)) {
                    $flag = true;
                    $msg = array();
                    foreach ($ids as $key=>$id) {
                        $product_transfer = M('ProductTransfer')->where(array('id'=>$id))->find();
                        $product_transfer_status = M('ProductTransfer')->where(array('id'=>$id,'status'=>2,'out_status'=>2,'in_status'=>2))->find();
                        if(!$product_transfer_status) {
                            if ($product_transfer) {
                                $product_transfer_item = M('ProductTransferitem')->where(array('erp_product_transfer_id' => $id))->select();
                                if ($product_transfer_item) {
                                    $result = D('Common/ProductTransfer')->where(array('id' => $id))->save(array('status' => 2,'out_status'=>2,'in_status'=>2));
                                    if ($result) {
                                        $pro_ids = array_unique(array_column($product_transfer_item,'id_product'));
                                        foreach($pro_ids as $pid) {
                                            $data = array(
                                                'id_department'=>$product_transfer['c_dest_depart_id']
                                            );
                                            D('Common/Product')->where(array('id_product'=>$pid))->save($data);
                                        }
                                        foreach ($product_transfer_item as $k => $v) {
                                            D('Common/ProductSku')->where(array('id_product_sku'=>$v['id_product_sku']))->save(array('id_department'=>$product_transfer['c_dest_depart_id']));
                                        }
                                        $msg[] = $product_transfer['docno'] . '产品调入单提交成功';
                                    } else {
                                        $flag = false;
                                        $msg[] = '单据提交失败';
                                        continue;
                                    }
                                } else {
                                    $flag = false;
                                    $msg[] = $product_transfer['docno'] . '调拨单没有明细数据,不能提交';
                                    continue;
                                }
                            } else {
                                $flag = false;
                                $msg[] = '该单据不存在';
                                continue;
                            }
                        } else {
                            $flag = false;
                            $msg[] = $product_transfer['docno'] . '单据已提交，请勿重复提交';
                            continue;
                        }
                    }
                    if($flag) {
                        $status = 1;
                        $message = implode("\r\n",$msg);
                    } else {
                        $status = 0;
                        $message = implode("\r\n",$msg);
                    }
                }
            }catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '提交产品调入单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
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
                        $product_transfer = M('ProductTransfer')->where(array('id'=>$id,'status'=>1))->find();
                        if($product_transfer) {
                            $result = D('Common/ProductTransfer')->where(array('id'=>$id))->delete();
                            if ($result) {
                                D('Common/ProductTransferitem')->where(array('erp_product_transfer_id'=>$id))->delete();
                            }
                            $msg[] = $product_transfer['docno'].'作废成功';
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
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除产品调拨单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    //批量删除
    public function batch_del_det() {
        if(IS_AJAX) {
            try {
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                if ($ids && is_array($ids)) {
                    $flag = true;
                    $count = 0;
                    foreach ($ids as $key=>$id) {
                        $res = D('Common/ProductTransferitem')->where(array('id'=>$id))->delete();
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
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除产品调拨明细');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    //提交
    public function product_transfer_commit() {
        if(IS_AJAX) {
            $id = $_POST['id'];
            $in_department = $_POST['in_department'];
            $product_transfer = M('ProductTransfer')->where(array('id'=>$id))->find();
            if($product_transfer) {
                $product_transfer_status = M('ProductTransfer')->where(array('id'=>$id,'status'=>2))->find();
                if(!$product_transfer_status) {
                    $product_transfer_item = M('ProductTransferitem')->where(array('erp_product_transfer_id' => $id))->select();
                    if ($product_transfer_item) {
                        $result = D('Common/ProductTransfer')->where(array('id' => $id))->save(array('status' => 2, 'statuser_id' => $_SESSION['ADMIN_ID'], 'status_time' => date('Y-m-d H:i:s')));
//                        if ($result) {
//                            foreach ($product_transfer_item as $key => $val) {
//                                $data = array(
//                                    'id_department'=>$in_department
//                                );
//                                D('Common/Product')->where(array('id_product'=>$val['id_product']))->save($data);
//                                D('Common/ProductSku')->where(array('id_product'=>$val['id_product_sku']))->save($data);
//                            }
//                        }
                        $status = 1;
                        $message = $product_transfer['docno'] . '提交成功';
                    } else {
                        $status = 0;
                        $message = '该调拨单没有明细数据,不能提交';
                    }
                } else {
                    $status = 0;
                    $message = '该调拨单已经提交,不能重复提交';
                }
            } else {
                $status = 0;
                $message = '该单据不存在';
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '提交产品调拨单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    //修改页面条数
    public function setpagerow(){
        $setRow = is_numeric($_POST['row'])?$_POST['row']:$this->pager;
        $_SESSION['set_page_row'] = $setRow;
    }

    //搜索产品页面
    public function search_product() {
        $where =array();
        if(isset($_GET['pro_title'])&& $_GET['pro_title']){
            $where['p.title'] = array('like','%'.trim($_GET['pro_title']).'%');
        }
        if(isset($_GET['inner_name'])&& $_GET['inner_name']){
            $where['p.inner_name'] = array('like','%'.trim($_GET['inner_name']).'%');
        }
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['p.id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['p.id_department']= $_GET['id_department'];
        }
        $where['status'] = 1;// 使用的SKU状态
        $M = new \Think\Model;
        $pro_table = D("Common/Product")->getTableName();
        $find_count = $M->table($pro_table.' AS p ')
            ->field('count(*) as count')->where($where)->find();
        $count= $find_count['count'];
        $page = $this->page($count,15);

        $proList = $M->table($pro_table.' AS p ')
            ->field('p.title,p.inner_name,p.id_product,p.thumbs,p.id_product')->where($where)
            ->order("p.id_product DESC")->limit($page->firstRow . ',' . $page->listRows)->select();

        if($proList && count($proList)){
            foreach($proList as $key=>$item){
                $proList[$key]['img'] = json_decode($item['thumbs'],true);
            }
        }
        $department_id  = $_SESSION['department_id'];
        $department = D('Common/Department')->where('type=1')->cache(true,6000)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看SKU列表');
        $this->assign("department_id", $department_id);
        $this->assign('department',$department);
        $this->assign("proList",$proList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    //ajax搜索sku
    public function ajax_get_pro() {
        if(IS_AJAX){
            $id = $_POST['id'];
            $pro = M('Product')->where(array('id_product'=>$id))->find();
            if($pro){
                $pro_name = $pro['id_product'];
            } else {
                $pro_name = '';
            }
            echo json_encode(array($pro_name));die;
        }
    }
}