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

class ImportProblemController extends AdminbaseController
{

    public function _initialize() {
        parent::_initialize();
        $this->pager      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /**
     * 导入问题订单列表页
     */
    public function import_problem_list()
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
        //快递单号筛选
        $track_number = $_GET['track_number'];
        if (!empty(trim($track_number)))
        {
            $order_shipping = D("Common/OrderShipping")->where(array('track_number' => $track_number))->find();
            if ($order_shipping)
            {
                $where['o.id_order'] = array('EQ', $order_shipping['id_order']);
            }
            else
            {
                $where['o.id_order'] = array('EQ', 0);  //如果没有该订单，就默认为0
            }
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
        $count = D("Common/ImportProblem")->alias('o')
            ->join("__ORDER__ as ob on ob.id_order = o.id_order",'LEFT')
            ->join("__ORDER_SHIPPING__ as ot on ot.id_order = o.id_order",'LEFT')
            ->where($where)
            ->count();
        $page = $this->page($count, $this->pager );

        //获取导入的波次单列表
        $lists = D("Common/ImportProblem")->alias('o')
            ->join("__ORDER__ as ob on ob.id_order = o.id_order",'LEFT')
            ->join("__ORDER_SHIPPING__ as ot on ot.id_order = o.id_order",'LEFT')
            ->where($where)
            ->field("o.*,ob.id_increment,ob.problem_name,ot.track_number")
            ->order("created_at DESC")     //按照 created_at排序
            ->limit($page->firstRow, $page->listRows)
            ->select();

        //对列表数据进行输出修改
        $status = OrderStatus::get_order_return_status();
        if ($lists)
        {
            $order_status_name = OrderStatus::get_order_status_name(); //获取订单状态对应的名称
            foreach ($lists as $k => $v)
            {
                $lists[$k]['id_order_status_before'] = isset($order_status_name[$v['id_order_status_before']]) ? $order_status_name[$v['id_order_status_before']] : '--';
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
     * 导入问题订单(直接提交)
     */
    public function import_problem()
    {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST)
        {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_wave_order', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row)
            {
                $total++;
                $row = explode("\t", trim($row), 2);
                $order_shipping = M('OrderShipping')->where(array('track_number'=>$row[0]))->find();//运单号
                $order = M('Order')->where(array('id_increment'=>$row[0]))->find();//订单号
                if($order_shipping)
                {
                    $order_id = $order_shipping['id_order'];
                    $order_data = M('Order')->where(array('id_order'=>$order_id))->find();//订单号
                    $import_problem_data = D("Common/ImportProblem")->where(array('id_order'=>$order_id))->find(); //获取该订单的导入记录
                    $result = D('Order/Order')->where(array('id_order'=>$order_id))->save(array('id_order_status'=> OrderStatus::PROBLEM,'problem_name'=>$_POST['problem_name']));
                    if($result && !$import_problem_data) //当该记录不存在时
                    {
                        //订单状态更新为问题订单后，添加记录到import_problem
                        $import_problem_add = array();
                        $import_problem_add['id_order'] = $order_id;
                        $import_problem_add['id_order_status_before'] = $order_data['id_order_status']; //更新之前的订单状态
                        $import_problem_add['id_user_creat'] = $_SESSION['ADMIN_ID']; //创建人
                        $import_problem_add['id_user_save'] = $_SESSION['ADMIN_ID']; //提交人
                        $import_problem_add['created_at'] = date('Y-m-d H:i:s'); //创建时间
                        $import_problem_add['updated_at'] = date('Y-m-d H:i:s'); //提交时间
                        $import_problem_add['status'] = OrderStatus::COMMIT; //提交
                        $import_problem_id = D("Common/ImportProblem")->data($import_problem_add)->add();
                        if ($import_problem_id)
                        {
                            D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PROBLEM, 4, '更新订单件为问题件，运单号' . $row[0].'，问题类型：'.$_POST['problem_name']);
                            $info['success'][] = sprintf('第%s行: 运单号:%s 更新状态成功，问题类型：%s, 写入记录成功', $count++, $row[0], $_POST['problem_name']);
                        }
                        else
                        {
                            $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态成功，问题类型：%s, 写入记录失败', $count++, $row[0], $_POST['problem_name']);
                        }
                    }
                    elseif ($result && $import_problem_data) //当该记录不存在时
                    {
                        if ($import_problem_data['status'] == OrderStatus::SAVE)
                        {
                            $import_problem_update = array();
                            $import_problem_update['id_user_save'] = $_SESSION['ADMIN_ID']; //提交人
                            $import_problem_update['updated_at'] = date('Y-m-d H:i:s'); //提交时间
                            $import_problem_update['status'] = OrderStatus::COMMIT; //提交
                            $import_problem_id = D("Common/ImportProblem")->where(array( 'id_import_problem' => $import_problem_data['id_import_problem'] ))->save($import_problem_update);
                            if ($import_problem_id)
                            {
                                $info['success'][] = sprintf('第%s行: 运单号:%s 更新状态成功，问题类型：%s,记录修改并提交成功', $count++, $row[0],$_POST['problem_name']);
                            }
                            else
                            {
                                $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态成功，记录修改并提交失败，请通知erp人员！', $count++, $row[0]);
                            }
                        }
                        else
                        {
                            $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，该记录已创建并已提交', $count++, $row[0]);
                        }
                    }
                    else
                    {
                        $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败', $count++, $row[0]);
                    }
                }
                else if($order)
                {
                    $order_id = $order['id_order'];
                    $import_problem_data = D("Common/ImportProblem")->where(array('id_order'=>$order_id))->find(); //获取该订单的导入记录
                    $result = D('Order/Order')->where(array('id_order'=>$order_id))->save(array('id_order_status'=> OrderStatus::PROBLEM,'problem_name'=>$_POST['problem_name']));
                    if($result && !$import_problem_data)
                    {
                        //订单状态更新为问题订单后，添加记录到import_problem
                        $import_problem_add = array();
                        $import_problem_add['id_order'] = $order['id_order'];
                        $import_problem_add['id_order_status_before'] = $order['id_order_status']; //更新之前的订单状态
                        $import_problem_add['id_user_creat'] = $_SESSION['ADMIN_ID']; //创建人
                        $import_problem_add['id_user_save'] = $_SESSION['ADMIN_ID']; //提交人
                        $import_problem_add['created_at'] = date('Y-m-d H:i:s'); //创建时间
                        $import_problem_add['updated_at'] = date('Y-m-d H:i:s'); //提交时间
                        $import_problem_add['status'] = OrderStatus::COMMIT; //提交
                        $import_problem_id = D("Common/ImportProblem")->data($import_problem_add)->add();
                        if ($import_problem_id)
                        {
                            D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PROBLEM, 4, '更新订单件为问题件，运单号' . $row[0].'，问题类型：'.$_POST['problem_name']);
                            $info['success'][] = sprintf('第%s行: 运单号:%s 更新状态成功，问题类型：%s, 写入记录成功', $count++, $row[0], $_POST['problem_name']);
                        }
                        else
                        {
                            $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态成功，问题类型：%s, 写入记录失败', $count++, $row[0], $_POST['problem_name']);
                        }
                    }
                    elseif ($result && $import_problem_data)
                    {
                        if ($import_problem_data['status'] == OrderStatus::SAVE)
                        {
                            $import_problem_update = array();
                            $import_problem_update['id_user_save'] = $_SESSION['ADMIN_ID']; //提交人
                            $import_problem_update['updated_at'] = date('Y-m-d H:i:s'); //提交时间
                            $import_problem_update['status'] = OrderStatus::COMMIT; //提交
                            $import_problem_id = D("Common/ImportProblem")->where(array( 'id_import_problem' => $import_problem_data['id_import_problem'] ))->save($import_problem_update);
                            if ($import_problem_id)
                            {
                                $info['success'][] = sprintf('第%s行: 运单号:%s 更新状态成功，问题类型：%s,记录修改并提交成功', $count++, $row[0],$_POST['problem_name']);
                            }
                            else
                            {
                                $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态成功，记录修改并提交失败，请通知erp人员！', $count++, $row[0]);
                            }
                        }
                        else
                        {
                            $info['error'][] = sprintf('第%s行: 运单号:%s 更新状态失败，该记录已创建并已提交', $count++, $row[0]);
                        }
                    }
                    else
                    {
                        $info['error'][] = sprintf('第%s行: 订单号:%s 更新状态失败', $count++, $row[0]);
                    }
                }
                else
                {
                    $info['error'][] = sprintf('第%s行: 订单号或运单号:%s 不存在', $count++, $row[0]);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, '导入问题订单', $path);
        }

        $this->assign('post', $_POST);
        $this->assign('infor', $info);
        $this->assign('data', I('post.data'));
        $this->assign('problem_name', I('post.problem_name'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 导入问题订单(保存)
     */
    public function import_problem_save() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST)
        {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('warehouse', 'import_wave_order', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row)
            {
                $total++;
                $row = explode("\t", trim($row), 2);
                $order_shipping = M('OrderShipping')->where(array('track_number'=>$row[0]))->find();//运单号
                $order = M('Order')->where(array('id_increment'=>$row[0]))->find();//订单号
                if($order_shipping)
                {
                    $order_id = $order_shipping['id_order'];
                    $order_data = M('Order')->where(array('id_order'=>$order_id))->find();//订单号
                    $import_problem_data = D("Common/ImportProblem")->where(array('id_order'=>$order_id))->find(); //获取该订单的导入记录
                    if (!$import_problem_data)
                    {
                        $import_problem_add = array();
                        $import_problem_add['id_order'] = $order_id;
                        $import_problem_add['id_order_status_before'] = $order_data['id_order_status']; //更新之前的订单状态
                        $import_problem_add['id_user_creat'] = $_SESSION['ADMIN_ID']; //创建人
                        $import_problem_add['created_at'] = date('Y-m-d H:i:s'); //创建时间
                        $import_problem_add['status'] = OrderStatus::SAVE; //提交
                        $import_problem_id = D("Common/ImportProblem")->data($import_problem_add)->add();
                        if ($import_problem_id)
                        {
                            $info['success'][] = sprintf('第%s行: 运单号:%s , 写入记录成功', $count++, $row[0]);
                        }
                        else
                        {
                            $info['error'][] = sprintf('第%s行: 运单号:%s , 记录写入失败', $count++, $row[0]);
                        }
                    }
                    else
                    {
                        $info['error'][] = sprintf('第%s行: 运单号:%s , 该记录已存在！', $count++, $row[0]);
                    }
                }
                else if($order)
                {
                    $order_id = $order['id_order'];
                    $import_problem_data = D("Common/ImportProblem")->where(array('id_order'=>$order_id))->find(); //获取该订单的导入记录
                    if (!$import_problem_data)
                    {
                        $import_problem_add = array();
                        $import_problem_add['id_order'] = $order_id;
                        $import_problem_add['id_order_status_before'] = $order['id_order_status']; //更新之前的订单状态
                        $import_problem_add['id_user_creat'] = $_SESSION['ADMIN_ID']; //创建人
                        $import_problem_add['created_at'] = date('Y-m-d H:i:s'); //创建时间
                        $import_problem_add['status'] = OrderStatus::SAVE; //提交
                        $import_problem_id = D("Common/ImportProblem")->data($import_problem_add)->add();
                        if ($import_problem_id)
                        {
                            $info['success'][] = sprintf('第%s行: 运单号:%s , 写入记录成功', $count++, $row[0]);
                        }
                        else
                        {
                            $info['error'][] = sprintf('第%s行: 运单号:%s , 记录写入失败', $count++, $row[0]);
                        }
                    }
                    else
                    {
                        $info['error'][] = sprintf('第%s行: 运单号:%s , 该记录已存在！', $count++, $row[0]);
                    }
                }
                else
                {
                    $info['error'][] = sprintf('第%s行: 订单号或运单号:%s 不存在', $count++, $row[0]);
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 4, '导入问题订单', $path);
        }

        $this->assign('post', $_POST);
        $this->assign('infor', $info);
        $this->assign('data', I('post.data'));
        $this->assign('remark', I('post.remark'));
        $this->assign('total', $total);
        $this->display('import_problem');
    }

    /**
     * 导入问题订单删除
     */
    public function import_problem_delete()
    {
        if(IS_AJAX){
            $return = array('status' => 0, 'message' => '数据删除失败！');
            $ids = is_array($_POST['ids']) ? $_POST['ids'] : array($_POST['ids']);
            $msg = '删除数据';
            $where = [];
            $where['id_import_problem']  = array( 'IN', implode(',',$ids));
            $where['status'] = array( 'EQ', OrderStatus::SAVE);
            $res = D("Common/ImportProblem")->where($where)->delete();
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
     * 提交导入问题订单
     */
    public function import_problem_commit()
    {
        if(IS_AJAX)
        {
            $ids = is_array($_POST['ids']) ? $_POST['ids'] : array($_POST['ids']);
            $problem_name = $_POST['problem_name']; //问题类型
            $msg = '提交数据';
            $import_problem_update = array('status'=> OrderStatus::COMMIT, 'id_user_save' => $_SESSION['ADMIN_ID'], 'updated_at' => date('Y-m-d H:i:s'));
            $where = [];
            $where['id_import_problem']  = array('in',implode(',',$ids));
            //批量更新问题订单
            $res = D("Common/ImportProblem")->where($where)->save($import_problem_update);
            $count = count($ids);
            if ($res)
            {
                $import_problem_data = D("Common/ImportProblem")->where($where)->select();
                $id_order_arr = array_column($import_problem_data, 'id_order');
                $update_where['id_order'] = array('IN', implode(',',$id_order_arr));
                $order_update = array('id_order_status' => OrderStatus::PROBLEM, 'problem_name' => $problem_name);
                $res_two = D("Order/Order")->where($update_where)->save($order_update);
                if ($res_two)
                {
                    add_system_record($_SESSION['ADMIN_ID'], 2, 3, $msg);
                    $return = array('status' => 1, 'message' => sprintf('总共%s条数据,提交成功!',$count));
                    echo json_encode($return);
                    exit();
                }
            }
        }
        $return = array('status' => 0, 'message' => '数据提交失败',);
        echo json_encode($return);
        exit();
    }

    /**修改页面条数*/
    public function setpagerow()
    {
        $setRow = is_numeric($_POST['row'])?$_POST['row']:$this->pager;
        $_SESSION['set_page_row'] = $setRow;
    }

}
