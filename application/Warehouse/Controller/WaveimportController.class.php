<?php
/**
 * Created by PhpStorm.
 * User: AsoulWangXiaohei
 * Date: 2017/6/6
 * Time: 14:38
 */

namespace Warehouse\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use Order\Model\UpdateStatusModel;

class WaveimportController extends AdminbaseController
{

    public function _initialize() {
        parent::_initialize();
        $this->pager      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /**
     * 导入波次单列表页
     */
    public function wave_import_list()
    {
        $where = array();
        //订单号筛选
        $id_increment = $_GET['id_increment'];
        if (!empty(trim($id_increment)))
        {
            $order_data = D("Order/Order")->where(array('id_increment' => $id_increment))->find();
            if ($order_data)
            {
                $where['o.id_order'] = array('EQ', $order_data['id_order']);
            }
            else
            {
                $where['o.id_order'] = array('EQ', 0);  //如果没有该订单，就默认为0
            }
        }
        //波次单号筛选
        $wave_number = $_GET['wave_number'];
        if (isset($wave_number) && $wave_number)
        {
            $where['o.wave_number'] = array('EQ', $wave_number);
        }
        //状态筛选
        $status = $_GET['status'];
        if (isset($status) && $status)
        {
            $where['o.status'] = array('EQ', $status);
        }
        //创建时间筛选
        if ($_GET['start_time'])
        {
           $where['o.created_at'] = array('EGT', $_GET['start_time']);
        }
        if ($_GET['end_time'])
        {
            $where['o.created_at'] = array('LT', $_GET['end_time']);
        }

        //获取人员信息
        $users = M('Users')->field('id,user_nicename')->select();
        $users = array_column($users, 'user_nicename', 'id');

        //分页
        $count = D("Common/WaveImport")->alias('o')
                ->join("__ORDER__ as ob on ob.id_order = o.id_order",'LEFT')
                ->where($where)
                ->count();
        $page = $this->page($count, $this->pager );

        //获取导入的波次单列表
        $lists = D("Common/WaveImport")->alias('o')
                ->join("__ORDER__ as ob on ob.id_order = o.id_order",'LEFT')
                ->where($where)
                ->field("o.*,ob.id_increment")
                ->order("created_at DESC")     //按照 created_at排序
                ->limit($page->firstRow, $page->listRows)
                ->select();

        //对列表数据进行输出修改
        $status = OrderStatus::get_order_return_status();
        if ($lists)
        {
            foreach ($lists as $k => $v)
            {
                $lists[$k]['id_user_creat'] = isset($users[$v['id_user_creat']]) ? $users[$v['id_user_creat']] : '--';
                $lists[$k]['id_user_save'] = isset($users[$v['id_user_save']]) ? $users[$v['id_user_save']] : '--';
                $lists[$k]['updated_at'] = !empty($v['updated_at']) ? $v['updated_at'] : '--';
                $lists[$k]['status'] = isset($status[$v['status']]) ? $status[$v['status']] : '--';
            }
        }

        $this->assign("get_data", $_GET);
        $this->assign("lists", $lists);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /**
     * 导入波次单(直接提交)
     */
    public function wave_import()
    {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST)
        {
            $data = I('post.data');
            $remark = I('post.remark');
            //导入记录到文件
            $path = write_file('warehouse', 'wave_import', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row)
            {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $id_increment = $row[0];//订单号
                //获取订单自增Id
                $order = M('Order')->field('id_order,id_order_status')->where(array('id_increment'=>$id_increment))->find();
                if($order)
                {
                    //获取该订单的导入波次单记录
                    $wave_import_data = D("Common/WaveImport")->where(array('id_order' => $order['id_order']))->find();
                    //获取该id_order的波次单信息
                    $wave = M('OrderWave')->field('id,id_order,wave_number,track_number_id')->where(array('id_order'=>$order['id_order']))->find();
                    if( $wave && !$wave_import_data)    //该订单的波次单状态且并未导入波次单操作
                    {
                        //删除该订单的波次单记录
                        $result = D('Common/OrderWave')->delete($wave['id']);
                        if ($result)
                        {
                            //如果是扣过库存的订单，要进行库存回滚和在单增加
                            if (in_array($order['id_order_status'], OrderStatus::get_canceled_to_rollback_status()))
                            {
                                UpdateStatusModel::wave_delete_rollback_stock($order['id_order']);
                            }
                            if ($wave['track_number_id'])
                            {
                                $shipping_track = M('ShippingTrack')->field('id_shipping,track_number')->where(array('id_shipping_track' => $wave['track_number_id']))->find();
                                D('Order/OrderShipping')->where(array('id_order' => $wave['id_order'],'id_shipping'=>$shipping_track['id_shipping'],'track_number'=>$shipping_track['track_number']))->delete();
                            }
                            //修改订单状态为未配货，相应的运单id_shipping设置为0
                           $res_one =  D('Order/Order')->where(array('id_order' => $wave['id_order']))->save(array('id_order_status' => OrderStatus::UNPICKING,'id_shipping'=>0));
                            D("Order/OrderRecord")->addHistory($wave['id_order'], 4, 4, '移除波次单里的订单，把订单状态改为未配货');

                            //增加wave_import记录
                            if ( $res_one )
                            {
                                $wave_import_add = array();
                                $wave_import_add['id_order'] = $order['id_order'];
                                $wave_import_add['wave_number'] = $wave['wave_number']; //波次单号
                                $wave_import_add['remark'] = $remark;
                                $wave_import_add['id_user_creat'] = $_SESSION['ADMIN_ID']; //创建人
                                $wave_import_add['id_user_save'] = $_SESSION['ADMIN_ID']; //提交人
                                $wave_import_add['created_at'] = date('Y-m-d H:i:s');
                                $wave_import_add['status'] = OrderStatus::COMMIT; //已提交
                                $wave_import_id = D("Common/WaveImport")->data($wave_import_add)->add();
                                if ($wave_import_id)
                                {
                                    $infor['success'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 移除成功,导入记录并提交成功', $count++, $id_increment, $wave['wave_number']);
                                }
                                else
                                {
                                    $infor['error'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 移除成功,导入记录但提交失败，请通知erp人员修改！', $count++, $id_increment, $wave['wave_number']);
                                }
                            }
                            else
                            {
                                $infor['error'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 移除成功,提交失败,请通知erp人员!', $count++, $id_increment, $wave['wave_number']);
                            }
                        }
                        else
                        {
                            $infor['error'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 移除失败，请稍后重试该订单操作！', $count++, $id_increment, $wave['wave_number']);
                        }
                    }
                    elseif ($wave && $wave_import_data)   //如果该订单的导入波次单记录存在，就直接进行修改
                    {
                        if ($wave_import_data['status'] == OrderStatus::SAVE)
                        {
                            //删除该订单的波次单记录
                            $result = D('Common/OrderWave')->delete($wave['id']);
                            if ($result)
                            {
                                //如果是扣过库存的订单，要进行库存回滚和在单增加
                                if (in_array($order['id_order_status'], OrderStatus::get_canceled_to_rollback_status()))
                                {
                                    UpdateStatusModel::wave_delete_rollback_stock($order['id_order']);
                                }
                                if ($wave['track_number_id'])
                                {
                                    $shipping_track = M('ShippingTrack')->field('id_shipping,track_number')->where(array('id_shipping_track' => $wave['track_number_id']))->find();
                                    D('Order/OrderShipping')->where(array('id_order' => $wave['id_order'],'id_shipping'=>$shipping_track['id_shipping'],'track_number'=>$shipping_track['track_number']))->delete();
                                }
                                //修改订单状态为未配货，相应的运单id_shipping设置为0
                                $res_one =  D('Order/Order')->where(array('id_order' => $wave['id_order']))->save(array('id_order_status' => OrderStatus::UNPICKING,'id_shipping'=>0));
                                D("Order/OrderRecord")->addHistory($wave['id_order'], 4, 4, '移除波次单里的订单，把订单状态改为未配货');

                                //提交wave_import记录
                                if ( $res_one )
                                {
                                    $wave_import_update = array();
                                    $wave_import_update['remark'] = $remark;
                                    $wave_import_update['id_user_save'] = $_SESSION['ADMIN_ID']; //提交人
                                    $wave_import_update['status'] = OrderStatus::COMMIT; //已提交
                                    $res = D("Common/WaveImport")->where(array('id_wave_import' => $wave_import_data['id_wave_import']))->save($wave_import_update);
                                    if ($res)
                                    {
                                        $infor['success'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 移除成功,导入波次单并提交成功！', $count++, $id_increment, $wave['wave_number']);
                                    }
                                    else
                                    {
                                        $infor['error'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 移除成功,导入波次单提交失败(如果出现该提示，请通知erp人员修改*！', $count++, $id_increment, $wave['wave_number']);
                                    }
                                }
                                else
                                {
                                    $infor['error'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 移除成功，导入波次单记录提交失败，请通知erp人员修改*', $count++, $id_increment, $wave['wave_number']);
                                }
                            }
                            else
                            {
                                $infor['error'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 移除失败，请稍后重试该订单操作！', $count++, $id_increment, $wave['wave_number']);
                            }
                        }
                        else
                        {
                            $infor['error'][] = sprintf('第%s行: 订单号:%s 该订单号已导入波次单并已提交，不能进行提交操作', $count++, $id_increment);
                        }
                    }
                    else
                    {
                        $infor['error'][] = sprintf('第%s行: 订单号:%s 该订单号不在波次单内，不能进行移除操作', $count++, $id_increment);
                    }
                }
                else
                {
                    $infor['error'][] = sprintf('第%s行: 订单号:%s 找不到该订单号', $count++, $id_increment);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, '提交移除波次单订单', $path);
        }

        $this->assign('post', $_POST);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('remark', I('post.remark'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 导入波次单(保存)
     */
    public function wave_import_save() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST)
        {
            $data = I('post.data');
            $remark = I('post.remark');
            //导入记录到文件
            $path = write_file('warehouse', 'wave_import_save', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row)
            {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
                $id_increment = $row[0];//订单号
                //获取订单自增Id
                $order = M('Order')->field('id_order')->where(array('id_increment'=>$id_increment))->find();
                if($order)
                {
                    //获取该订单的导入波次单记录
                    $wave_import_data = D("Common/WaveImport")->where(array('id_order' => $order['id_order']))->find();
                    //获取该id_order的波次单信息
                    $wave = M('OrderWave')->field('id,id_order,wave_number,track_number_id')->where(array('id_order'=>$order['id_order']))->find();
                    if( $wave && !$wave_import_data)    //该订单的波次单状态且并未导入波次单操作
                    {
                        $wave_import_add = array();
                        $wave_import_add['id_order'] = $order['id_order'];
                        $wave_import_add['wave_number'] = $wave['wave_number']; //波次单号
                        $wave_import_add['remark'] = $remark;
                        $wave_import_add['id_user_creat'] = $_SESSION['ADMIN_ID']; //创建人
                        $wave_import_add['created_at'] = date('Y-m-d H:i:s');
                        $wave_import_add['status'] = OrderStatus::SAVE; //未提交
                        $wave_import_id = D("Common/WaveImport")->data($wave_import_add)->add();
                        if ($wave_import_id)
                        {
                            $infor['success'][] = sprintf('第%s行: 订单号:%s 波次单号:%s 导入波次单添加记录成功', $count++, $id_increment, $wave['wave_number']);
                        }
                    }
                    elseif ($wave && $wave_import_data)   //如果该订单的导入波次单记录存在，就直接进行修改
                    {
                        if ($wave_import_data['status'] == OrderStatus::SAVE)
                        {
                            $wave_import_update = array();
                            $wave_import_update['remark'] = $remark; //提交理由
                            $wave_import_update['id_user_creat'] = $_SESSION['ADMIN_ID'];  //修改操作人
                            $wave_import_update['updated_at'] = date('Y-m-d H:i:s');
                            $res = D("Common/WaveImport")->where(array('id_wave_import'=> $wave_import_data['id_wave_import']))->save($wave_import_update);
                            if ($res)
                            {
                                $infor['success'][] = sprintf('第%s行: 订单号:%s 该订单号已导入波次单，保存操作成功！', $count++, $id_increment);
                            }
                            else
                            {
                                $infor['error'][] = sprintf('第%s行: 订单号:%s 该订单号已导入波次单，提交操作失败！', $count++, $id_increment);
                            }
                        }
                        else
                        {
                            $infor['error'][] = sprintf('第%s行: 订单号:%s 该订单号已导入波次单并已提交，不能进行保存操作', $count++, $id_increment);
                        }
                    }
                    else
                    {
                        $infor['error'][] = sprintf('第%s行: 订单号:%s 该订单号不在波次单内，不能进行导入波次单操作', $count++, $id_increment);
                    }
                }
                else
                {
                    $infor['error'][] = sprintf('第%s行: 订单号:%s 找不到该订单号', $count++, $id_increment);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 4, '导入波次单订单，添加记录(未提交)', $path);
        }

        $this->assign('post', $_POST);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('remark', I('post.remark'));
        $this->assign('total', $total);
        $this->display('wave_import');
    }

    /**
     * 导入波次单删除
     */
    public function wave_delete()
    {
        if(IS_AJAX){
            $return = array('status' => 0, 'message' => '数据删除失败！');
            $ids = is_array($_POST['ids']) ? $_POST['ids'] : array($_POST['ids']);
            $msg = '删除数据';
            $where = [];
            $where['id_wave_import']  = array( 'IN', implode(',',$ids));
            $where['status'] = array( 'EQ', OrderStatus::SAVE);
            $res = D("Common/WaveImport")->where($where)->delete();
            if($res === FALSE){
                echo json_encode($return);
                exit();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => 1, 'message' => '数据删除成功!');
            echo json_encode($return);
            exit();
        }
    }

    /**
     * 提交波次单
     */
    public function wave_commit()
    {
        if(IS_AJAX)
        {
            $return = array('status' => 0, 'message' => '提交数据失败！');
            $ids = is_array($_POST['ids']) ? $_POST['ids'] : array($_POST['ids']);
            $msg = '提交数据';
            $wave_import_update = array('status'=> OrderStatus::COMMIT, 'id_user_save' => $_SESSION['ADMIN_ID'], 'updated_at' => date('Y-m-d H:i:s'));
            $where = [];
            $where['id_wave_import']  = array('in',implode(',',$ids));
            //批量更新导入波次单状态
            $res = D("Common/WaveImport")->where($where)->save($wave_import_update);
            if ($res)
            {
                $count = count($ids);
                $error_count = $success_count = 0;
                foreach ($ids as $id)
                {
                    //获取导入波次单和订单信息
                    $wave_import_info = D("Common/WaveImport")->alias("o")
                        ->join("__ORDER__ as ob ON ob.id_order = o.id_order","LEFT")
                        ->join("__ORDER_WAVE__ as ot on ot.id_order = ob.id_order","LEFT")
                        ->field("o.*,ob.id_order_status,ot.id,ot.id_order,ot.wave_number,ot.track_number_id")
                        ->where(array('o.id_wave_import' => $id))
                        ->find();
                    //删除订单的波次单记录
                    $result = D('Common/OrderWave')->delete($wave_import_info['id']);
                    if ($result)
                    {
                        //如果是扣过库存的订单，要进行库存回滚和在单增加
                        if (in_array($wave_import_info['id_order_status'], OrderStatus::get_canceled_to_rollback_status()))
                        {
                            UpdateStatusModel::wave_delete_rollback_stock($wave_import_info['id_order']);
                        }
                        if ($wave_import_info['track_number_id'])
                        {
                            $shipping_track = M('ShippingTrack')->field('id_shipping,track_number')->where(array('id_shipping_track' => $wave_import_info['track_number_id']))->find();
                            D('Order/OrderShipping')->where(array('id_order' => $wave_import_info['id_order'],'id_shipping'=>$shipping_track['id_shipping'],'track_number'=>$shipping_track['track_number']))->delete();
                        }
                        //修改订单状态为未配货，相应的运单id_shipping设置为0
                        D('Order/Order')->where(array('id_order' => $wave_import_info['id_order']))->save(array('id_order_status' => OrderStatus::UNPICKING,'id_shipping'=>0));
                        D("Order/OrderRecord")->addHistory($wave_import_info['id_order'], 4, 4, '移除波次单里的订单，把订单状态改为未配货');
                        $success_count++;
                    }
                    else
                    {
                        $error_count++;
                        continue;
                    }
                }
            }
            else
            {
                echo json_encode($return);
                exit();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
            $return = array('status' => 1, 'message' => sprintf('总共%s条数据,失败%s条，提交成功%s条',$count,$error_count,$success_count));
            echo json_encode($return);
            exit();
        }

    }

    /**修改页面条数*/
    public function setpagerow()
    {
        $setRow = is_numeric($_POST['row'])?$_POST['row']:$this->pager;
        $_SESSION['set_page_row'] = $setRow;
    }

}
