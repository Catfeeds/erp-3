<?php

namespace Warehouse\Controller;

use Common\Controller\AdminbaseController;
use Order\Model\UpdateStatusModel;
use SystemRecord\Model\SystemRecordModel;
use Common\Lib\Procedure;

class ChangeController extends AdminbaseController {

    protected $time_start,$time_end,$pager;

    public function _initialize() {
        parent::_initialize();
        $this->time_start = I('get.start_time', date('Y-m-d 00:00', strtotime('-7 day')));
        $this->time_end = I('get.end_time', date('Y-m-d 00:00', strtotime('+1 day')));
        $this->pager = isset($_SESSION['set_page_row']) ? $_SESSION['set_page_row'] : 20;
    }

    //物理调整单
    public function change_stock() {
        $_GET['start_time'] = $this->time_start;
        $_GET['end_time'] = $this->time_end;
        $where = array();
        if(isset($_GET['warehouse_id']) && $_GET['warehouse_id']) {
            $where['oi.id_warehouse'] = $_GET['warehouse_id'];
        }
        if(isset($_GET['status_id']) && $_GET['status_id']) {
            $where['oi.status'] = $_GET['status_id'];
        }
        if(isset($_GET['start_time']) && $_GET['start_time']) {
            $time_arr = array();
            $time_arr[] = array('EGT',$_GET['start_time']);
            if($_GET['end_time']) $time_arr[] = array('LT',$_GET['end_time']);
            $where[] = array('oi.bill_date'=>$time_arr);
        }
        if(isset($_GET['docno']) && $_GET['docno']) {
            $where['oi.docno'] = array('like','%'.$_GET['docno'].'%');
        }
        //增加 sku  内部名的搜索   --Lily  2017-10-26
        if(isset($_GET['sku']) && $_GET['sku']){
            $where['ps.sku'] = array("EQ",$_GET['sku']);
        }
        if(isset($_GET['inner_name']) && $_GET['inner_name']){
            $where['p.inner_name'] = array("LIKE",'%'.$_GET['inner_name'].'%');
        }
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $user = M('Users')->getField('id,user_nicename',true);
        $list_count = M('OtherInout')->alias("oi")
                      ->join("__OTHER_INOUTITEM__ as oii ON oi.id=oii.other_inout_id","LEFT")
                      ->join("__PRODUCT__ as p ON p.id_product=oii.id_product","LEFT")
                      ->join("__PRODUCT_SKU__ as ps ON ps.id_product_sku=oii.id_product_sku","LEFT")
                      ->where($where)->count();
        $page = $this->page($list_count,$this->pager);
        $list = M('OtherInout')->alias("oi")
                      ->join("__OTHER_INOUTITEM__ as oii ON oi.id=oii.other_inout_id","LEFT")
                      ->join("__PRODUCT__ as p ON p.id_product=oii.id_product","LEFT")
                      ->join("__PRODUCT_SKU__ as ps ON ps.id_product_sku=oii.id_product_sku","LEFT")
                      ->field("oi.*,ps.sku,p.inner_name")
                      ->where($where)->limit($page->firstRow.','.$page->listRows)->order('create_time DESC')->select();
        foreach($list as $key=>$val) {
            $other_inoutitem = M('OtherInoutitem')->field('SUM(qty) as qty')->where(array('other_inout_id'=>$val['id']))->find();
            $list[$key]['warehouse_title'] = $warehouse[$val['id_warehouse']];
            $list[$key]['user_name'] = $user[$val['owner_id']];
            $list[$key]['tj_name'] = $user[$val['statuser_id']];
            $list[$key]['count'] = M('OtherInoutitem')->where(array('other_inout_id'=>$val['id']))->count();
            $list[$key]['count_sum'] = $other_inoutitem['qty'] ? $other_inoutitem['qty'] : 0;
        }
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看物理调整单列表');
        $this->assign('list',$list);
        $this->assign('warehouse',$warehouse);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    //添加页面
    public function add() {
        $time = I('post.time', date('Y-m-d 00:00'));
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    //添加明细
    public function add_details() {
        $other_inout = M('OtherInout')->where(array('id'=>$_GET['id']))->find();
        $other_inout_item = M('OtherInoutitem')->where(array('other_inout_id'=>$_GET['id']))->select();
        foreach($other_inout_item as $key=>$val) {
            $product = M('Product')->field('title,inner_name')->where(array('id_product'=>$val['id_product']))->find();
            $product_sku = M('ProductSku')->field('sku')->where(array('id_product_sku'=>$val['id_product_sku']))->find();
            $other_inout_item[$key]['pro_name'] = $product['title'];
            $other_inout_item[$key]['inner_name'] = $product['inner_name'];
            $other_inout_item[$key]['sku'] = $product_sku['sku'];
        }
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $this->assign('warehouse',$warehouse);
        $this->assign('data',$other_inout);
        $this->assign('other_inout_item',$other_inout_item);
        $this->display();
    }

    //导入SKU和数量
    public function import_sku() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $other_inout = M('OtherInout')->where(array('id'=>$_GET['id']))->find();
        $warehouse = M('Warehouse')->where(array('forward'=>0))->getField('id_warehouse,title',true);
        $total = 0;
        if (IS_POST) {
            $other_data['id'] = $id;
            $other_data['bill_date'] = $_POST['bill_date'];
            $other_data['id_warehouse'] = $_POST['id_warehouse'];
            $other_data['description'] = $_POST['description'];
            D('Common/OtherInout')->save($other_data);
            $data = I('post.sku_data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_sku', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            M('OtherInoutitem')->where("other_inout_id = $id")->delete();
            $temp = [];
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row)) continue;
                $row = explode("\t", trim($row), 2);
                $sku = $row[0];
                $sku_qty = $row[1];
                $product_sku = M('ProductSku')->where(array('sku' => $sku, 'status' => 1))->find();
                if ($product_sku) {
                    $where['other_inout_id'] = $id;
                    $where['id_product'] = $product_sku['id_product'];
                    $where['id_product_sku'] = $product_sku['id_product_sku'];
//                    $where['id_warehouse'] = $_POST['id_warehouse'];
                    $other_inoutitem = M('OtherInoutitem')->where($where)->find();
                    $id_warehouse_allocation = M('WarehouseAllocationStock')->where(array('id_product_sku' => $product_sku['id_product_sku']))->getField('id_warehouse_allocation');
                    $warehouse_product = M('WarehouseProduct')->where(array('id_warehouse'=>$_POST['id_warehouse'],'id_product'=>$product_sku['id_product'],'id_product_sku' => $product_sku['id_product_sku']))->find();
                    if((!$warehouse_product&&(int)$_POST['qty']>=0)||($warehouse_product['quantity']+(int)$sku_qty >= 0)) {
                        if($other_inoutitem) {
                            $temp[] = $sku;
                        } else {
                            $datas['other_inout_id'] = $id;
                            $datas['id_product'] = $product_sku['id_product'];
                            $datas['id_product_sku'] = $product_sku['id_product_sku'];
                            $datas['option_value'] = $product_sku['title'];
                            $datas['qty'] = !empty($sku_qty) ? $sku_qty : 0;
                            $datas['id_warehouse_allocation'] = $id_warehouse_allocation;
                            D('Common/OtherInoutitem')->add($datas);
                        }
//                        $info['success'][] = sprintf('第%s行: SKU:%s SKU导入成功',$count++,$sku);
                    } else {
                        $info['error'][] = sprintf('第%s行: SKU:%s 该SKU所在仓库库存不能为负数',$count++,$sku);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行: SKU:%s 这个SKU不存在或者无效',$count++,$sku);
                }
            }
            if($temp){
                $temp = trim(implode(',',$temp));
                $info['error'][] = sprintf('SKU: %s SKU重复，请修正后重新导入',$temp);
                M('OtherInoutitem')->where("other_inout_id = $id")->delete();
            }else
                $info['success'][] = sprintf('全部SKU导入成功');
        }

        $this->assign('warehouse',$warehouse);
        $this->assign('other_inout_data',$other_inout);
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.sku_data'));
        $this->assign('total', $total);
        $this->display();
    }

    //添加单据逻辑
    public function add_post() {
        if(IS_POST){
            $docno = M('OtherInout')->field('docno')->order('docno DESC')->find();
            $docno_num = substr($docno['docno'],2)+1;
            $data['docno'] = $docno ? 'IO'.$docno_num : 'IO'.date('ymd').'0000001';
            $data['bill_date'] = I('post.bill_date');
            $data['id_warehouse'] = I('post.id_warehouse');
            $data['description'] = I('post.description');
            $data['status'] = 1;
            $data['owner_id'] = $_SESSION['ADMIN_ID'];
            $data['statuser_id'] = 0;
            $data['create_time'] = date('Y-m-d H:i:s');

            $other_inout_id = D('Common/OtherInout')->add($data);

            if($other_inout_id) {
                $this->redirect('change/add_details',array('id'=>$other_inout_id));
            } else {
                $this->redirect('change/add');
            }
        }
    }

    //添加明细逻辑
    public function add_details_post() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        if(IS_POST) {
            $other_data['id'] = $id;
            $other_data['bill_date'] = $_POST['bill_date'];
            $other_data['id_warehouse'] = $_POST['id_warehouse'];
            $other_data['description'] = $_POST['description'];
            D('Common/OtherInout')->save($other_data);
            if (!empty($_POST['sku_name'])) {
                $product_sku = M('ProductSku')->where(array('sku' => $_POST['sku_name'], 'status' => 1))->find();
                if ($product_sku) {
                    $where['other_inout_id'] = $id;
                    $where['id_product'] = $product_sku['id_product'];
                    $where['id_product_sku'] = $product_sku['id_product_sku'];
//                    $where['id_warehouse'] = $_POST['id_warehouse'];
                    $other_inoutitem = M('OtherInoutitem')->where($where)->find();
                    $id_warehouse_allocation = M('WarehouseAllocationStock')->where(array('id_product_sku' => $product_sku['id_product_sku']))->getField('id_warehouse_allocation');
                    $warehouse_product = M('WarehouseProduct')->where(array('id_warehouse'=>$_POST['id_warehouse'],'id_product'=>$product_sku['id_product'],'id_product_sku' => $product_sku['id_product_sku']))->find();
                    if((!$warehouse_product&&(int)$_POST['qty'] > 0)||($warehouse_product['quantity']+(int)$_POST['qty'] > 0)) {
                        if ($other_inoutitem) {
                            $data['qty'] = $other_inoutitem['qty'] + (int)$_POST['qty'];
                            D('Common/OtherInoutitem')->where($where)->save($data);
                            $flag = true;
                        } else {
                            $data['other_inout_id'] = $id;
                            $data['id_product'] = $product_sku['id_product'];
                            $data['id_product_sku'] = $product_sku['id_product_sku'];
                            $data['option_value'] = $product_sku['title'];
                            $data['qty'] = !empty($_POST['qty']) ? $_POST['qty'] : 1;
                            $data['id_warehouse_allocation'] = $id_warehouse_allocation;
                            D('Common/OtherInoutitem')->add($data);
                            $flag = true;
                        }
                    } else {
                        $flag = false;
                        $msg = '该SKU所在仓库库存不能为负数';
                    }
                } else {
                    $flag = false;
                    $msg = '这个SKU不存在或者无效';
                }

                if ($flag) {
                    $this->redirect('change/add_details', array('id' => $id));
                } else {
                    $this->error($msg,"javascript:history.back(-1);",'10');
                }
            } else {
                $c_qty = $_POST['c_qty'];
                $msg = array();
                foreach ($c_qty as $item_id => $qty) {
                    $other_inoutitem = M('OtherInoutitem')->where(array('id' => $item_id))->find();
                    $sku = M('ProductSku')->where(array('id_product_sku'=>$other_inoutitem['id_product_sku']))->getField('sku');
                    $warehouse_product = M('WarehouseProduct')->where(array('id_warehouse'=>$_POST['id_warehouse'],'id_product'=>$other_inoutitem['id_product'],'id_product_sku' => $other_inoutitem['id_product_sku']))->find();
                    if((!$warehouse_product&&(int)$qty >= 0)||($warehouse_product['quantity']+(int)$qty >= 0)) {
                        D('Common/OtherInoutitem')->where(array('id' => $item_id))->save(array('qty' => $qty));
                    }else {
                        $msg[] = '该SKU:'.$sku.'所在仓库库存不能为负数';
                    }
                }
                if(!empty($msg)) {
                    $this->error(implode("<br>",$msg),"javascript:history.back(-1);",'10');
                } else {
                    $this->redirect('change/add_details', array('id' => $id));
                }
            }
        }
    }

    //导入SKU和数量逻辑
    public function import_sku_post() {
        $id = isset($_GET['id']) ? $_GET['id'] : $_POST['hid'];
        $other_data['id'] = $id;
        $other_data['bill_date'] = $_POST['bill_date'];
        $other_data['id_warehouse'] = $_POST['id_warehouse'];
        $other_data['description'] = $_POST['description'];
        D('Common/OtherInout')->save($other_data);
        if(IS_POST) {
            $data = I('post.sku_data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_sku', $data);
            $data = $this->getDataRow($data);
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row)) continue;
                $row = explode("\t", trim($row), 2);
                $sku = $row[0];
                $sku_qty = $row[1];
                $product_sku = M('ProductSku')->where(array('sku' => $sku, 'status' => 1))->find();
                if ($product_sku) {
                    $where['other_inout_id'] = $id;
                    $where['id_product'] = $product_sku['id_product'];
                    $where['id_product_sku'] = $product_sku['id_product_sku'];
//                    $where['id_warehouse'] = $_POST['id_warehouse'];
                    $other_inoutitem = M('OtherInoutitem')->where($where)->find();
                    $id_warehouse_allocation = M('WarehouseAllocationStock')->where(array('id_product_sku' => $product_sku['id_product_sku']))->getField('id_warehouse_allocation');
                    if($other_inoutitem) {
                        $data['qty'] = $other_inoutitem['qty']+(int)$sku_qty;
                        D('Common/OtherInoutitem')->where($where)->save($data);
                    } else {
                        $data['other_inout_id'] = $id;
                        $data['id_product'] = $product_sku['id_product'];
                        $data['id_product_sku'] = $product_sku['id_product_sku'];
                        $data['option_value'] = $product_sku['title'];
                        $data['qty'] = !empty($sku_qty) ? $sku_qty : 0;
                        $data['id_warehouse_allocation'] = $id_warehouse_allocation;
                        D('Common/OtherInoutitem')->add($data);
                    }
                    $flag = true;
                } else {
                    $flag = false;
                    $msg = '这个SKU不存在或者无效';
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '物理调整单导入SKU', $path);
            if ($flag) {
                $this->redirect('change/add_details', array('id' => $id));
            } else {
                $this->error($msg);
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
                        $other_inout = M('OtherInout')->where(array('id'=>$id))->find();
                        $other_inout_status = M('OtherInout')->where(array('id'=>$id,'status'=>2))->find();
                        if(!$other_inout_status) {
                            if ($other_inout) {
                                $other_inout_item = M('OtherInoutitem')->where(array('other_inout_id' => $id))->select();
                                if ($other_inout_item) {
                                    $msg_sku = array();
                                    $sku_id = array();
                                    foreach ($other_inout_item as $key => $val) {
                                        $warehouse_product = M('WarehouseProduct')->where(array('id_warehouse'=>$other_inout['id_warehouse'],'id_product'=>$val['id_product'],'id_product_sku' => $val['id_product_sku']))->find();
                                        $sku = M('ProductSku')->where(array('id_product_sku'=>$val['id_product_sku']))->getField('sku');
                                        if((!$warehouse_product&&(int)$val['qty'] >= 0)||($warehouse_product['quantity']+(int)$val['qty'] >= 0)) {
                                            $sku_id[] = $val['id_product_sku'];
                                        } else {
                                            $msg_sku[] = $other_inout['docno'].'单据内的SKU:'.$sku.'所在仓库库存不能为负数';
                                        }
                                    }

                                    if(!empty($msg_sku)) {
                                        $flag = false;
                                        $msg[] = implode("\n",$msg_sku);
                                        continue;
                                    } else {
                                        $res_sub = $this->add_submit($id, 'erp_other_inout');
                                        if(!empty($res_sub['@erromsg'])) {
                                            $flag = false;
                                            $msg[] = $res_sub['@erromsg'];
                                            continue;
                                        } else {
                                            UpdateStatusModel::get_short_order($sku_id);
                                            $flag = true;
                                            $msg[] = $other_inout['docno'] . '提交成功';
                                            continue;
                                        }
                                    }
                                } else {
                                    $flag = false;
                                    $msg[] = $other_inout['docno'] . '调整单没有明细数据,不能提交';
                                    continue;
                                }
                            } else {
                                $flag = false;
                                $msg[] = '该单据不存在';
                                continue;
                            }
                        } else {
                            $flag = false;
                            $msg[] = $other_inout['docno'] . '单据已提交，请勿重复提交';
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
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '提交物理调整单据');
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
                        $other_inout = M('OtherInout')->where(array('id'=>$id,'status'=>1))->find();
                        if($other_inout) {
                            $result = D('Common/OtherInout')->where(array('id'=>$id))->delete();
                            if ($result) {
                                D('Common/OtherInoutitem')->where(array('other_inout_id'=>$id))->delete();
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
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '作废物理调整单据');
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
                        $res = D('Common/OtherInoutitem')->where(array('id'=>$id))->delete();
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

    //提交
    public function other_inout_commit() {
        if(IS_AJAX) {
            $id = $_POST['id'];
            $qty = is_array($_POST['qty']) ? $_POST['qty'] : array($_POST['qty']);
            $other_inout = M('OtherInout')->where(array('id'=>$id))->find();
            if($other_inout) {
                $other_inout_status = M('OtherInout')->where(array('id'=>$id,'status'=>2))->find();
                if(!$other_inout_status) {
                    $other_inout_item = M('OtherInoutitem')->where(array('other_inout_id' => $id))->select();
                    if ($other_inout_item) {
                        $sku_id = array();
                        foreach ($other_inout_item as $key => $val) {
                            $warehouse_product = M('WarehouseProduct')->where(array('id_warehouse'=>$other_inout['id_warehouse'],'id_product'=>$val['id_product'],'id_product_sku' => $val['id_product_sku']))->find();
                            $sku = M('ProductSku')->where(array('id_product_sku'=>$val['id_product_sku']))->getField('sku');
                            if((!$warehouse_product&&(int)$qty[$key] >= 0)||($warehouse_product['quantity']+(int)$qty[$key] >= 0)) {
                                $sku_id[] = $val['id_product_sku'];
                                D('Common/OtherInoutitem')->where(array('id' => $val['id']))->save(array('qty' => $qty[$key]));
                            } else {
                                $msg[] = '该SKU:'.$sku.'所在仓库库存不能为负数';
                            }
                        }
                        if(!empty($msg)) {
                            $status = 0;
                            $message = implode("\n",$msg);
                        } else {
                            $res_sub = $this->add_submit($id, 'erp_other_inout');
                            if(!empty($res_sub['@erromsg'])) {
                                $res = $res_sub['@erromsg'];
                            }
                            if(!empty($res)) {
                                $status = 0;
                                $message = $res;
                            } else {
                                if(!empty($sku_id)) UpdateStatusModel::get_short_order($sku_id);
                                $status = 1;
                                $message = $other_inout['docno'] . '提交成功';
                            }
                        }
                    } else {
                        $status = 0;
                        $message = '该调整单没有明细数据,不能提交';
                    }
                } else {
                    $status = 0;
                    $message = '该调整单已经提交,不能重复提交';
                }
            } else {
                $status = 0;
                $message = '该单据不存在';
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '提交物理调整单据');
            $return = array('status' => $status, 'message' => $message);
            echo json_encode($return);exit();
        }
    }

    //修改页面条数
    public function setpagerow(){
        $setRow = is_numeric($_POST['row'])?$_POST['row']:$this->pager;
        $_SESSION['set_page_row'] = $setRow;
    }

    //搜索sku页面
    public function search_sku() {
        $where =array();
        if(isset($_GET['inner_name'])&& $_GET['inner_name']){
            $where['p.inner_name'] = array('like','%'.$_GET['inner_name'].'%');
        }
        if(isset($_GET['sku'])&& $_GET['sku']){
            $where['ps.sku|ps.barcode'] = array('like','%'.$_GET['sku'].'%');
        }
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['p.id_department']= $_GET['id_department'];
        } else {
            if(isset($_GET['type'])&&$_GET['type']=='pi') $where['p.id_department'] = array('IN',$_SESSION['department_id']);
        }
        $where['ps.status'] = 1;// 使用的SKU状态
        $M = new \Think\Model;
        $pro_table = D("Common/Product")->getTableName();
        $pro_s_table = D("Common/ProductSku")->getTableName();
        $find_count = $M->table($pro_table.' AS p LEFT JOIN '.$pro_s_table.' AS ps ON p.id_product=ps.id_product')
            ->field('count(*) as count')->where($where)->find();
        $count= $find_count['count'];
        $page = $this->page($count,15);

        $proList = $M->table($pro_table.' AS p LEFT JOIN '.$pro_s_table.' AS ps ON p.id_product=ps.id_product')
            ->field('ps.sku,ps.barcode,ps.model,ps.option_value,ps.purchase_price,ps.weight,p.inner_name,p.id_product,p.thumbs,ps.id_product_sku')->where($where)
            ->order("p.id_product DESC")->limit($page->firstRow . ',' . $page->listRows)->select();

        $value_model  = D("Common/ProductOptionValue");
        if($proList && count($proList)){
            foreach($proList as $key=>$item){
                $option_value = $item['option_value'];
                if($option_value){
                    $get_value = $value_model->where('id_product_option_value in('.$option_value.')')->getField('title',true);
                    $proList[$key]['value'] = $get_value?implode('-',$get_value):'';
                    $proList[$key]['img'] = json_decode($item['thumbs'],true);
                }
            }
        }

        if(isset($_GET['type'])&&$_GET['type']=='pi'){
            $department = D('Common/Department')->where(array('type'=>1,'id_department'=>array('IN',$_SESSION['department_id'])))->cache(true,6000)->select();
        } else {
            $department = D('Common/Department')->where('type=1')->cache(true,6000)->select();
        }
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看SKU列表');
        $this->assign('department',$department);
        $this->assign("proList",$proList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    //ajax搜索sku
    public function ajax_get_sku() {
        if(IS_AJAX){
            $id = $_POST['id'];
            $sku = M('ProductSku')->where(array('id_product_sku'=>$id))->find();
            if($sku){
                $sku_name = $sku['sku'];
            } else {
                $sku_name = '';
            }
            echo json_encode(array($sku_name));die;
        }
    }

    //添加存储过程
    public function add_submit($id,$tablename) {
        $user_id = $_SESSION['ADMIN_ID'];
        $procedure_name = 'ERP_INOUT_SUBMIT';
        $array['billid'] = $id;
        $array['userid'] = $user_id;
        $array['tablename'] = $tablename;
        $array['inor'] = 'I';
        $result = Procedure::call($procedure_name,$array);
        return $result;
    }
}