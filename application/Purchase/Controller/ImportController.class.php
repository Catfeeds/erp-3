<?php

namespace Purchase\Controller;

use Common\Controller\AdminbaseController;
use Purchase\Model\PurchaseStatusModel;

class ImportController extends AdminbaseController {

    protected $time_start,$time_end,$pager;

    public function _initialize() {
        parent::_initialize();
        $this->time_start = I('get.start_time', date('Y-m-d 00:00', strtotime('-7 day')));
        $this->time_end = I('get.end_time', date('Y-m-d 00:00', strtotime('+1 day')));
        $this->pager = isset($_SESSION['set_page_row']) ? $_SESSION['set_page_row'] : 20;
    }

    /**
     * 导入供应商
     */
    public function import_supplier() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $id_department = $_SESSION['department_id'];
        $department = M('Department')->field('id_department,title')->where(array('type'=>1,'id_department'=>array('IN',$id_department)))->select();
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('supplier', 'import_supplier', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                if (count($row) != 2 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
                $supplier_name = $row[0];//供应商名称
                $supplier_address = $row[1];//店铺地址
                $supplier = M('Supplier')->field('title')->where(array('title'=>$supplier_name))->find();
                if(!$supplier) {
                    $data = array(
                        'id_department'=>$_POST['id_department'],
                        'title'=>$row[0],
                        'supplier_url'=>$row[1],
                        'created_at'=>date('Y-m-d H:i:s'),
                        'id_users'=>$_SESSION['ADMIN_ID']
                    );
                    $result = D('Common/Supplier')->add($data);
                    if($result) {
                        $infor['success'][] = sprintf('第%s行: 供应商:%s 店铺网址:%s 导入成功', $count++, $supplier_name, $supplier_address);
                    } else {
                        $infor['error'][] = sprintf('第%s行: 供应商:%s 店铺网址:%s 导入失败', $count++, $supplier_name, $supplier_address);
                    }                    
                } else {
                    $infor['error'][] = sprintf('第%s行: 供应商:%s 已存在', $count++, $supplier_name);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 1, 3, '新增供应商', $path);
        }
        
        $this->assign('post', $_POST);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->assign('department',$department);
        $this->display();
    }

    /**
     * 调整导入产品采购成本
     */
    public function import_purchase_price() {
        $_GET['start_time'] = $this->time_start;
        $_GET['end_time'] = $this->time_end;
        $where = array();
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
        $where['type'] = 1;
        $user = M('Users')->getField('id,user_nicename',true);
        $list_count = M('PurchaseImport')->where($where)->count();
        $page = $this->page($list_count,$this->pager);
        $list = M('PurchaseImport')->where($where)->limit($page->firstRow.','.$page->listRows)->order('created_time DESC')->select();
        foreach($list as $key=>$val) {
            $purchase_import_data = M('PurchaseImportData')->field('SUM(price) as price')->where(array('id_purchase_import_id'=>$val['id']))->find();
            $list[$key]['user_name'] = $user[$val['owner_id']];
            $list[$key]['tj_name'] = $user[$val['statuser_id']];
            $list[$key]['count'] = M('PurchaseImportData')->where(array('id_purchase_import_id'=>$val['id']))->count();
            $list[$key]['count_sum'] = $purchase_import_data['price'] ? $purchase_import_data['price'] : 0;
        }
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看产品采购成本导入列表');
        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    /**
     * 调整导入产品SKU运费
     */
    public function import_purchase_weight() {
        $_GET['start_time'] = $this->time_start;
        $_GET['end_time'] = $this->time_end;
        $where = array();
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
        $where['type'] = 2;
        $user = M('Users')->getField('id,user_nicename',true);
        $list_count = M('PurchaseImport')->where($where)->count();
        $page = $this->page($list_count,$this->pager);
        $list = M('PurchaseImport')->where($where)->limit($page->firstRow.','.$page->listRows)->order('created_time DESC')->select();
        foreach($list as $key=>$val) {
            $purchase_import_data = M('PurchaseImportData')->field('SUM(weight) as weight')->where(array('id_purchase_import_id'=>$val['id']))->find();
            $list[$key]['user_name'] = $user[$val['owner_id']];
            $list[$key]['tj_name'] = $user[$val['statuser_id']];
            $list[$key]['count'] = M('PurchaseImportData')->where(array('id_purchase_import_id'=>$val['id']))->count();
            $list[$key]['count_sum'] = $purchase_import_data['weight'] ? $purchase_import_data['weight'] : 0;
        }
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看产品运费导入列表');
        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    /**
     * 添加导入产品采购成本页面
     */
    public function import_price_add() {
        $this->display();
    }

    /**
     * 添加导入产品运费页面
     */
    public function import_weight_add() {
        $this->display();
    }

    /**
     * 添加导入产品采购成本明细页面
     */
    public function import_price_add_details() {
        $purchase_import = M('PurchaseImport')->where(array('id'=>$_GET['id']))->find();
        $purchase_import_data = M('PurchaseImportData')->where(array('id_purchase_import_id'=>$_GET['id']))->select();
        foreach($purchase_import_data as $key=>$val) {
            $product = M('Product')->field('title,inner_name')->where(array('id_product'=>$val['id_product']))->find();
            $product_sku = M('ProductSku')->field('sku')->where(array('id_product_sku'=>$val['id_product_sku']))->find();
            $purchase_import_data[$key]['pro_name'] = $product['title'];
            $purchase_import_data[$key]['inner_name'] = $product['inner_name'];
            $purchase_import_data[$key]['sku'] = $product_sku['sku'];
        }
        $this->assign('data',$purchase_import);
        $this->assign('purchase_import_data',$purchase_import_data);
        $this->display();
    }

    /**
     * 添加导入产品运费明细页面
     */
    public function import_weight_add_details() {
        $purchase_import = M('PurchaseImport')->where(array('id'=>$_GET['id']))->find();
        $purchase_import_data = M('PurchaseImportData')->where(array('id_purchase_import_id'=>$_GET['id']))->select();
        foreach($purchase_import_data as $key=>$val) {
            $product = M('Product')->field('title,inner_name')->where(array('id_product'=>$val['id_product']))->find();
            $product_sku = M('ProductSku')->field('sku')->where(array('id_product_sku'=>$val['id_product_sku']))->find();
            $purchase_import_data[$key]['pro_name'] = $product['title'];
            $purchase_import_data[$key]['inner_name'] = $product['inner_name'];
            $purchase_import_data[$key]['sku'] = $product_sku['sku'];
        }
        $this->assign('data',$purchase_import);
        $this->assign('purchase_import_data',$purchase_import_data);
        $this->display();
    }

    /**
     * 导入SKU和数量
     */
    public function import_price_sku() {
        $other_inout = M('PurchaseImport')->where(array('id'=>$_GET['id']))->find();
        $this->assign('data',$other_inout);
        $this->display();
    }

    /**
     * 导入SKU和数量
     */
    public function import_weight_sku() {
        $other_inout = M('PurchaseImport')->where(array('id'=>$_GET['id']))->find();
        $this->assign('data',$other_inout);
        $this->display();
    }

    /**
     * 添加单据逻辑
     */
    public function import_price_add_post() {
        if(IS_POST){

            $data = array(
                'table'=>'PurchaseImport',
                'type'=>PurchaseStatusModel::IMPORT_PRICE
            );

            $purchase_import_id = PurchaseStatusModel::add_post($data);

            if($purchase_import_id) {
                $this->redirect('import/import_price_add_details',array('id'=>$purchase_import_id));
            } else {
                $this->redirect('import/import_price_add');
            }
        }
    }

    /**
     * 添加导入运费单据逻辑
     */
    public function import_weight_add_post() {
        if(IS_POST){

            $data = array(
                'table'=>'PurchaseImport',
                'type'=>PurchaseStatusModel::IMPORT_WEIGHT
            );

            $purchase_weight_id = PurchaseStatusModel::add_post($data);

            if($purchase_weight_id) {
                $this->redirect('import/import_weight_add_details',array('id'=>$purchase_weight_id));
            } else {
                $this->redirect('import/import_weight_add');
            }
        }
    }

    /**
     * 添加明细逻辑
     */
    public function import_price_add_details_post() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        if(IS_POST) {
            $other_data['id'] = $id;
            $other_data['bill_date'] = $_POST['bill_date'];
            $other_data['description'] = $_POST['description'];
            D('Common/PurchaseImport')->save($other_data);
            if (!empty($_POST['sku_name'])) {
                $product_sku = M('ProductSku')->where(array('sku' => $_POST['sku_name'], 'status' => 1))->find();
                if ($product_sku) {
                    $where['id_purchase_import_id'] = $id;
                    $where['id_product'] = $product_sku['id_product'];
                    $where['id_product_sku'] = $product_sku['id_product_sku'];
                    $purchase_import_data = M('PurchaseImportData')->where($where)->find();
                    if($purchase_import_data) {
                        D('Common/PurchaseImportData')->where($where)->save(array('price'=>$_POST['qty']));
                    } else {
                        $data['id_purchase_import_id'] = $id;
                        $data['id_product'] = $product_sku['id_product'];
                        $data['id_product_sku'] = $product_sku['id_product_sku'];
                        $data['price'] = !empty($_POST['qty']) ? $_POST['qty'] : 0;
                        D('Common/PurchaseImportData')->add($data);
                    }
                    $flag = true;
                } else {
                    $flag = false;
                    $msg = '这个SKU不存在或者无效';
                }

                if ($flag) {
                    $this->redirect('import/import_price_add_details', array('id' => $id));
                } else {
                    $this->error($msg);
                }
            } else {
                $c_qty = $_POST['c_qty'];
                foreach ($c_qty as $item_id => $qty) {
                    D('Common/PurchaseImportData')->where(array('id' => $item_id))->save(array('price' => $qty));
                }
                $this->redirect('import/import_price_add_details', array('id' => $id));
            }
        }
    }

    /**
     * 添加运费明细逻辑
     */
    public function import_weight_add_details_post() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        if(IS_POST) {
            $other_data['id'] = $id;
            $other_data['bill_date'] = $_POST['bill_date'];
            $other_data['description'] = $_POST['description'];
            D('Common/PurchaseImport')->save($other_data);
            if (!empty($_POST['sku_name'])) {
                $product_sku = M('ProductSku')->where(array('sku' => $_POST['sku_name'], 'status' => 1))->find();
                if ($product_sku) {
                    $where['id_purchase_import_id'] = $id;
                    $where['id_product'] = $product_sku['id_product'];
                    $where['id_product_sku'] = $product_sku['id_product_sku'];
                    $purchase_import_data = M('PurchaseImportData')->where($where)->find();
                    if($purchase_import_data) {
                        D('Common/PurchaseImportData')->where($where)->save(array('weight'=>$_POST['qty']));
                    } else {
                        $data['id_purchase_import_id'] = $id;
                        $data['id_product'] = $product_sku['id_product'];
                        $data['id_product_sku'] = $product_sku['id_product_sku'];
                        $data['weight'] = !empty($_POST['qty']) ? $_POST['qty'] : 0;
                        D('Common/PurchaseImportData')->add($data);
                    }
                    $flag = true;
                } else {
                    $flag = false;
                    $msg = '这个SKU不存在或者无效';
                }

                if ($flag) {
                    $this->redirect('import/import_weight_add_details', array('id' => $id));
                } else {
                    $this->error($msg);
                }
            } else {
                $c_qty = $_POST['c_qty'];
                foreach ($c_qty as $item_id => $qty) {
                    D('Common/PurchaseImportData')->where(array('id' => $item_id))->save(array('weight' => $qty));
                }
                $this->redirect('import/import_weight_add_details', array('id' => $id));
            }
        }
    }

    /**
     * 导入SKU和数量逻辑
     */
    public function import_price_sku_post() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        $other_data['id'] = $id;
        $other_data['bill_date'] = $_POST['bill_date'];
        $other_data['description'] = $_POST['description'];
        D('Common/PurchaseImport')->save($other_data);
        if(IS_POST) {
            $data = I('post.sku_data');
            //导入记录到文件
            $path = write_file('purchase', 'import_price_sku', $data);
            $data = $this->getDataRow($data);
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row)) continue;
                $row = explode("\t", trim($row), 2);
                $sku = $row[0];
                $sku_qty = $row[1];
                $product_sku = M('ProductSku')->where(array('sku' => $sku, 'status' => 1))->find();
                if ($product_sku) {
                    $where['id_purchase_import_id'] = $id;
                    $where['id_product'] = $product_sku['id_product'];
                    $where['id_product_sku'] = $product_sku['id_product_sku'];
                    $purchase_import_data = M('PurchaseImportData')->where($where)->find();
                    if($purchase_import_data) {
                        D('Common/PurchaseImportData')->where($where)->save(array('price'=>$sku_qty));
                    } else {
                        $data['id_purchase_import_id'] = $id;
                        $data['id_product'] = $product_sku['id_product'];
                        $data['id_product_sku'] = $product_sku['id_product_sku'];
                        $data['price'] = !empty($sku_qty) ? $sku_qty : 0;
                        D('Common/PurchaseImportData')->add($data);
                    }
                    $flag = true;
                } else {
                    $flag = false;
                    $msg = '这个SKU不存在或者无效';
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '产品采购成本导入SKU', $path);
            if ($flag) {
                $this->redirect('import/import_price_add_details', array('id' => $id));
            } else {
                $this->error($msg);
            }
        }
    }

    /**
     * 导入SKU和数量逻辑
     */
    public function import_weight_sku_post() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        $other_data['id'] = $id;
        $other_data['bill_date'] = $_POST['bill_date'];
        $other_data['description'] = $_POST['description'];
        D('Common/PurchaseImport')->save($other_data);
        if(IS_POST) {
            $data = I('post.sku_data');
            //导入记录到文件
            $path = write_file('purchase', 'import_weight_sku', $data);
            $data = $this->getDataRow($data);
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row)) continue;
                $row = explode("\t", trim($row), 2);
                $sku = $row[0];
                $sku_qty = $row[1];
                $product_sku = M('ProductSku')->where(array('sku' => $sku, 'status' => 1))->find();
                if ($product_sku) {
                    $where['id_purchase_import_id'] = $id;
                    $where['id_product'] = $product_sku['id_product'];
                    $where['id_product_sku'] = $product_sku['id_product_sku'];
                    $purchase_import_data = M('PurchaseImportData')->where($where)->find();
                    if($purchase_import_data) {
                        D('Common/PurchaseImportData')->where($where)->save(array('weight'=>$sku_qty));
                    } else {
                        $data['id_purchase_import_id'] = $id;
                        $data['id_product'] = $product_sku['id_product'];
                        $data['id_product_sku'] = $product_sku['id_product_sku'];
                        $data['weight'] = !empty($sku_qty) ? $sku_qty : 0;
                        D('Common/PurchaseImportData')->add($data);
                    }
                    $flag = true;
                } else {
                    $flag = false;
                    $msg = '这个SKU不存在或者无效';
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '产品SKU重量导入SKU', $path);
            if ($flag) {
                $this->redirect('import/import_weight_add_details', array('id' => $id));
            } else {
                $this->error($msg);
            }
        }
    }

    /**
     * 批量提交
     */
    public function import_price_batch_sub() {
        if(IS_AJAX) {
            try {
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                $type = $_POST['type'];
                if ($ids && is_array($ids)) {
                    $flag = true;
                    $msg = array();
                    foreach ($ids as $key=>$id) {
                        $purchase_import = M('PurchaseImport')->where(array('id'=>$id))->find();
                        $purchase_import_status = M('PurchaseImport')->where(array('id'=>$id,'status'=>2))->find();
                        if(!$purchase_import_status) {
                            if ($purchase_import) {
                                $purchase_import_data = M('PurchaseImportData')->where(array('id_purchase_import_id' => $id))->select();
                                if ($purchase_import_data) {
                                    foreach($purchase_import_data as $key=>$val) {
                                        if($type==1) {
                                            $data['purchase_price'] = $val['price'];
                                        } else {
                                            $data['weight'] = $val['weight'];
                                        }
                                        D('Product/ProductSku')->where(array('id_product_sku'=>$val['id_product_sku']))->save($data);
                                    }
                                    D('Common/PurchaseImport')->where(array('id'=>$id))->save(array('status'=>2,'statuser_id'=>$_SESSION['ADMIN_ID'],'status_time'=>date('Y-m-d H:i:s')));
                                    $flag = false;
                                    $msg[] = $purchase_import['docno'] . '提交成功';
                                    continue;
                                } else {
                                    $flag = false;
                                    $msg[] = $purchase_import['docno'] . '调整单没有明细数据,不能提交';
                                    continue;
                                }
                            } else {
                                $flag = false;
                                $msg[] = '该单据不存在';
                                continue;
                            }
                        } else {
                            $flag = false;
                            $msg[] = $purchase_import['docno'] . '单据已提交，请勿重复提交';
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
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '提交导入产品单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    /**
     * 批量作废
     */
    public function import_price_batch_del() {
        if(IS_AJAX) {
            try {
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                if ($ids && is_array($ids)) {
                    $flag = true;
                    $count = 0;
                    $msg = array();
                    foreach ($ids as $key=>$id) {
                        $other_inout = M('PurchaseImport')->where(array('id'=>$id,'status'=>1))->find();
                        if($other_inout) {
                            $result = D('Common/PurchaseImport')->where(array('id'=>$id))->delete();
                            if ($result) {
                                D('Common/PurchaseImportData')->where(array('id_purchase_import_id'=>$id))->delete();
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
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '作废产品导入单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    /**
     * 批量删除
     */
    public function import_price_batch_del_det() {
        if(IS_AJAX) {
            try {
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                if ($ids && is_array($ids)) {
                    $flag = true;
                    $count = 0;
                    foreach ($ids as $key=>$id) {
                        $res = D('Common/PurchaseImportData')->where(array('id'=>$id))->delete();
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
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除产品导入明细');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    /**
     * 提交
     */
    public function purchase_price_commit() {
        if(IS_AJAX) {
            $id = $_POST['id'];
            $type = $_POST['type'];
            $qty = is_array($_POST['qty']) ? $_POST['qty'] : array($_POST['qty']);
            $purchase_import = M('PurchaseImport')->where(array('id'=>$id))->find();
            if($purchase_import) {
                $purchase_import_status = M('PurchaseImport')->where(array('id'=>$id,'status'=>2))->find();
                if(!$purchase_import_status) {
                    $purchase_import_data = M('PurchaseImportData')->where(array('id_purchase_import_id' => $id))->select();
                    if ($purchase_import_data) {
                        foreach ($purchase_import_data as $key => $val) {
                            if($type==1) {
                                D('Common/PurchaseImportData')->where(array('id' => $val['id']))->save(array('price'=>$qty[$key]));
                                D('Product/ProductSku')->where(array('id_product_sku'=>$val['id_product_sku']))->save(array('purchase_price'=>$qty[$key]));
                            } else {
                                D('Common/PurchaseImportData')->where(array('id' => $val['id']))->save(array('weight'=>$qty[$key]));
                                D('Product/ProductSku')->where(array('id_product_sku'=>$val['id_product_sku']))->save(array('weight'=>$qty[$key]));
                            }
                        }
                        D('Common/PurchaseImport')->where(array('id'=>$id))->save(array('status'=>2,'statuser_id'=>$_SESSION['ADMIN_ID'],'status_time'=>date('Y-m-d H:i:s')));
                        $status = 1;
                        $message = $purchase_import['docno'] . '提交成功';
                    } else {
                        $status = 0;
                        $message = '该单据没有明细数据,不能提交';
                    }
                } else {
                    $status = 0;
                    $message = '该单据已经提交,不能重复提交';
                }
            } else {
                $status = 0;
                $message = '该单据不存在';
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '提交产品导入单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    /**
     * 修改页面条数
     */
    public function setpagerow(){
        $setRow = is_numeric($_POST['row'])?$_POST['row']:$this->pager;
        $_SESSION['set_page_row'] = $setRow;
    }
    /*
     * Eva
     * QQ:549251235
     * 更新采购快递单号
     */
    public function import_alibaba(){
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('purchase', 'import_alibaba', $data);
            $data = $this->getDataRow($data);

            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);

                if ($row[0]) {
                    $search = M('Purchase')->field('id_purchase')->where("inner_purchase_no = '{$row[0]}' or alibaba_no = '$row[0]'")->select();
                    if($search){
                         foreach($search as $key=>$value){
                            if($value['id_purchase']){
                                M('Purchase')->startTrans();
                                $save['id_purchase'] = $value['id_purchase'];
                                $save['shipping_no'] = trim($row[1]);
                                $res = M('Purchase')->save($save);
                                if($res === 0)
                                    $info['error'][] = sprintf('第%s行: 内部单号或渠道单号：%s 导入失败1', $count++,$row[0]);
                                else{
                                   $id_purchaseins = M('PurchaseIn')->where("id_erp_purchase = '{$value['id_purchase']}'")->getField('id_purchasein',true);
                                   if($id_purchaseins){
                                       $id_purchaseins = trim(implode(',',$id_purchaseins));
                                       $save_pi['id_purchasein'] = array('In',$id_purchaseins);
                                       $save_pi['shipping_no'] = trim($row[1]);
                                       $res2 = M('PurchaseIn')->save($save_pi);
                                       if($res&&$res2){
                                           M('Purchase')->commit();
                                           $info['success'][] = sprintf('第%s行: 内部单号或渠道单号：%s 导入成功', $count++,$row[0]);
                                       }
                                       else{
                                           $info['error'][] = sprintf('第%s行: 内部单号或渠道单号：%s 导入失败2', $count++,$row[0]);
                                           M('Purchase')->rollback();
                                       }
                                   }else{
                                       if($res){
                                           $info['success'][] = sprintf('第%s行: 内部单号或渠道单号：%s 导入成功', $count++,$row[0]);
                                           M('Purchase')->commit();
                                       }

                                   }

                                }
                            }
                         }

                    }else
                        $info['error'][] = sprintf('第%s行: 内部单号或渠道单号：%s 不存在', $count++,$row[0]);
                }
                else
                {
                    $info['error'][] = sprintf('第%s行: 格式不正确', $count++);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '导入采购快递单号', $path);
        }
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
}