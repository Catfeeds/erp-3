<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/18
 * Time: 11:28
 */

namespace Warehouse\Controller;

use Common\Controller\AdminbaseController;
use Common\Lib\SearchData;
use Common\Lib\Procedure;
use Order\Lib\OrderStatus;
use Order\UpdateStatus;
use Order\Model\UpdateStatusModel;
class ReturnsController extends AdminbaseController
{

    public function _initialize() {
        parent::_initialize();
        $this->Order = M('Order');
        $this->OrderItem = M('OrderItem');
        $this->OrderReturn = M('OrderReturn');
        $this->OrderShipping = M('OrderShipping');
        $this->OrderReturnitem = M('OrderReturnitem');
        $this->ProductOptionValue = M('ProductOptionValue');
        $this->Product = M('Product');
        $this->Users = D("Common/Users");
        $this->pager      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /**退货订单*/
    public function order_return_list()
    {
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse'])
        {
            $where['ob.id_warehouse'] = array('EQ', $_GET['id_warehouse']);
        }
        if (isset($_GET['id_department']) && $_GET['id_department'])
        {
            $where['ob.id_department'] = array('EQ', $_GET['id_department']);
        }
        if (isset($_GET['status']) && $_GET['status'])
        {
            $where['ob.status'] = array('EQ', $_GET['status']);
        }
        if (isset($_GET['isagain']) && $_GET['isagain'])
        {
            $where['ob.isagain'] = array('EQ', $_GET['isagain']);
        }
        if (isset($_GET['id_increment']) && $_GET['id_increment'])
        {
            $where['b.id_increment'] = array('EQ', $_GET['id_increment']);
        }

        /*创建日期初始化*/
        $created_at_array = array();
        if ($_GET['start_time_create'] or $_GET['end_time_create'])
        {
            if ($_GET['start_time_create'])
            {
                $created_at_array[] = array('EGT', $_GET['start_time_create']);
            }
            if ($_GET['end_time_create'])
            {
                $created_at_array[] = array('LT', $_GET['end_time_create']);
            }
        }
        else
        {
            if (!$_GET['start_time_create'] && !$_GET['end_time_create'])
            {
                $get_data['start_time_create'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time_create'] = date('Y-m-d H:i',time()+3600);
                $created_at_array[] = array('EGT', $get_data['start_time_create']);
                $created_at_array[] = array('LT', $get_data['end_time_create']);
            }
        }
        $where['ob.created_at'] = $created_at_array;

        /*退货日期初始化*/
        $date_return_array = array();
        if ($_GET['start_time_return'] or $_GET['end_time_return'])
        {
            if ($_GET['start_time_return'])
            {
                $date_return_array[] = array('EGT', $_GET['start_time_return']);
            }
            if ($_GET['end_time_return'])
            {
                $date_return_array[] = array('LT', $_GET['end_time_return']);
            }
            $where['ob.date_return'] = $date_return_array;
        }
        //创建人
        $model = new \Think\Model();
        $users = M('Users')->field('id,user_nicename')->select();
        $users = array_column($users, 'user_nicename', 'id');

        $orderReturn = D("Order/OrderReturn")->getTableName();
        $order = D("Order/Order")->getTableName();
        //分页
        $count = $model->table($orderReturn . ' AS ob LEFT JOIN ' . $order . ' AS b ON ob.id_order=b.id_order')
            ->where($where)->count();
        $page = $this->page($count, $this->pager );

        $list = $model->table($orderReturn)->alias('ob')
            ->join("__ORDER__ as b ON ob.id_order=b.id_order", 'left')
            ->join("__ORDER_SHIPPING__ as c ON ob.id_order_shipping=c.id_order_shipping", 'left')
            ->field("ob.id_order_return,ob.id_warehouse,ob.id_department,ob.id_users,ob.id_order,ob.id_order_shipping,
                        ob.date_return,ob.status,ob.remark,ob.created_at,ob.updated_at,ob.isagain,b.id_increment,c.track_number")
            ->where($where)
            ->order("date_return DESC")     //按照 shipping.data_return 排序
            ->limit($page->firstRow, $page->listRows)
            ->select();

        $warehouse = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
        $departments = array_column(SearchData::search()['departments'],'title','id_department');

        $status = OrderStatus::get_order_return_status(); //订单状态
        $isAgain = OrderStatus::get_order_return_again(); //再次派货
        foreach ($list as $k => $v) {
            $list[$k]['id_warehouse'] = $warehouse[$v['id_warehouse']];
            $list[$k]['id_department'] = $departments[$v['id_department']];
            $list[$k]['id_users'] = $users[$v['id_users']];
            $list[$k]['status'] = $status[$v['status']];
            $list[$k]['isagain'] = $isAgain[$v['isagain']];
            //当数据为未提交时，没有退货日期默认为'--'
            if ($v['date_return'] == '0000-00-00 00:00:00')
            {
                $list[$k]['date_return'] = '--';
            }
        }

        $data['return_order_list'] = $list;
        $this->assign("isAgain", $isAgain);
        $this->assign("get", $_GET);
        $this->assign("start_time_create",$get_data['start_time_create']);
        $this->assign("end_time_create",$get_data['end_time_create']);
        $this->assign("departments", $departments);
        $this->assign("warehouse", $warehouse);
        $this->assign("status", $status);
        $this->assign("return_order_list",$list);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /**修改页面条数*/
    public function setpagerow()
    {
        $setRow = is_numeric($_POST['row'])?$_POST['row']:$this->pager;
        $_SESSION['set_page_row'] = $setRow;
    }

    /**创建退货订单*/
    public function create_order_return()
    {
        $warehouse = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
        $departments = array_column(SearchData::search()['departments'],'title','id_department');
        $isAgain = \Order\Lib\OrderStatus::get_order_return_again();
        $this->assign("isAgain", $isAgain);
        $this->assign("department", $departments);
        $this->assign("warehouse", $warehouse);
        $this->display();
    }

    /**退货订单详情列表*/
    public function order_return_detail()
    {
        if (isset($_GET['id_department']) && $_GET['id_department'])
        {
            $where['ob.id_department'] = array('EQ', $_GET['id_department']);
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse'])
        {
            $where['ob.id_warehouse'] = array('EQ', $_GET['id_warehouse']);
        }
        if (isset($_GET['status']) && $_GET['status'])
        {
            $where['ob.status'] = array('EQ', $_GET['status']);
        }
        if (isset($_GET['id_increment']) && $_GET['id_increment'])
        {
            $where['b.id_increment'] = array('EQ', $_GET['id_increment']);
        }

        /*创建日期初始化*/
        $created_at_array = array();
        if ($_GET['start_time_create'] or $_GET['end_time_create'])
        {
            if ($_GET['start_time_create'])
            {
                $created_at_array[] = array('EGT', $_GET['start_time_create']);
            }
            if ($_GET['end_time_create'])
            {
                $created_at_array[] = array('LT', $_GET['end_time_create']);
            }
        }
        else
        {
            if (!$_GET['start_time_create'] && !$_GET['end_time_create'])
            {
                $get_data['start_time_create'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time_create'] = date('Y-m-d H:i',time()+3600);
                $created_at_array[] = array('EGT', $get_data['start_time_create']);
                $created_at_array[] = array('LT', $get_data['end_time_create']);
            }
        }
        $where['ob.created_at'] = $created_at_array;

        /*退货日期初始化*/
        $date_return_array = array();
        if ($_GET['start_time_return'] or $_GET['end_time_return'])
        {
            if ($_GET['start_time_return'])
            {
                $date_return_array[] = array('EGT', $_GET['start_time_return']);
            }
            if ($_GET['end_time_return'])
            {
                $date_return_array[] = array('LT', $_GET['end_time_return']);
            }
            $where['ob.date_return'] = $date_return_array;
        }

        //创建人
        $model = new \Think\Model();
        $users = M('Users')->field('id,user_nicename')->select();
        $users = array_column($users, 'user_nicename', 'id');

        //分页
        $orderReturn = D("Order/OrderReturn")->getTableName();
        $order = D("Order/Order")->getTableName();
        //分页
        $count = $model->table($orderReturn . ' AS ob LEFT JOIN ' . $order . ' AS b ON ob.id_order=b.id_order')
            ->where($where)->count();
        $page = $this->page($count, $this->pager );
        $list = $model->table($orderReturn .  ' AS ob LEFT JOIN ' . $order . ' AS b ON ob.id_order=b.id_order')
            ->field("ob.id_order_return,ob.id_warehouse,ob.id_department,ob.id_users,ob.id_order,ob.id_order_shipping,
                ob.date_return,ob.status,ob.remark,ob.created_at,ob.updated_at,ob.isagain,b.id_increment")
            ->where($where)
            ->order("date_return DESC")     //按照 shipping.data_return 排序
            ->limit($page->firstRow, $page->listRows)
            ->select();

        $warehouse = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
        $departments = array_column(SearchData::search()['departments'],'title','id_department');

        $status = \Order\Lib\OrderStatus::get_order_return_status(); //订单状态
        $isAgain = \Order\Lib\OrderStatus::get_order_return_again(); //再次派货
        foreach ($list as $k => $v) {
            $list[$k]['id_warehouse'] = $warehouse[$v['id_warehouse']];
            $list[$k]['id_department'] = $departments[$v['id_department']];
            $list[$k]['id_users'] = $users[$v['id_users']];
            $list[$k]['status'] = $status[$v['status']];
            $list[$k]['isagain'] = $isAgain[$v['isagain']];
            $map_detail = array();
            $map_detail['id_order_return'] = $v['id_order_return'];
            $order_return_details =  $model->table($this->OrderReturnitem->getTableName())
                ->where($map_detail)
                ->select();
            //获取该订单产品的详情信息
            foreach ($order_return_details as  $e => $s)
            {
                $order_return_details[$e]['id_product'] = $s['id_product'];
                $where = array();
                $where['id_product'] = array('EQ',  $s['id_product']);
                $product_name = $model->table($this->Product->getTableName())
                    ->field('title')
                    ->where($where)
                    ->find();
                $order_return_details[$e]['product_name'] = $product_name['title'];
                if ($s['option_value'])
                {
                    $map = array();
                    $map['id_product_option_value'] = array('IN', $s['option_value']);
                    $attrs_title = $model->table($this->ProductOptionValue->getTableName())
                        ->field('title')
                        ->where($map)
                        ->select();
                    $titles =  array_column($attrs_title,'title');
                    $order_return_details[$e]['attrs_title'] = implode(',',$titles);
                }
                else
                {
                    $order_return_details[$e]['attrs_title'] = "";
                }
            }
            $list[$k]['product'] = $order_return_details;
        }

        $this->assign("get", $_GET);
        $this->assign("start_time_create",$get_data['start_time_create']);
        $this->assign("end_time_create",$get_data['end_time_create']);
        $this->assign("departments", $departments);
        $this->assign("warehouse", $warehouse);
        $this->assign("status", $status);
        $this->assign("return_order_detail",$list);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /**退货订单修改*/
    public function save_post()
    {
        $id_order = $_GET['id_order'];
        $model = new \Think\Model();
        //根据订单号获取订单信息
        $order_data = $model->table($this->Order->getTableName())
            ->where(array('id_order' => $id_order))
            ->find();
        if (!$order_data)
        {
            $this->error("获取不到该订单号信息，请核实后再操作！", U('Returns/order_return_list'));
            die;
        }
        $id_order = $order_data['id_order'];
        $where['o.id_order'] = array('EQ', $order_data['id_order']);
        $model = new \Think\Model();
        $order_return = $model->table($this->OrderReturn->getTableName() . ' o')
            ->where($where)
            ->find();
        //修改操作
        if($order_return && $order_return['status'] == 1)
        {
            $order_return_data = array();
            $order_return_data['id_warehouse'] = $_GET['id_warehouse'];
            $order_return_data['remark'] = $_GET['remark'];
            $order_return_data['isagain'] = $_GET['isagain'];
            $order_return_data['status'] = OrderStatus::SAVE;
            //退货日期的处理
            if ($_GET['status'] == 2) //提交状态
            {
                $order_return_data['date_return'] = date('Y-m-d H:i:s');
            }
            $order_return_data['id_users'] = $_SESSION['ADMIN_ID'];
            $order_return_data['updated_at'] = date('Y-m-d H:i:s');
            $id_order_return = $this->OrderReturn->where(array('id_order_return' => $order_return['id_order_return']))->save($order_return_data);

            if ($id_order_return)
            {
                //无论是否输入产品退货数量，都进行保存操作
                for( $i=0; $i<count($_GET['id_product']); $i++)
                {
                    $map = array();
                    $map['id_order_return'] = array('EQ', $order_return['id_order_return']);
                    $map['id_product'] = array('EQ', $_GET['id_product'][$i]);
                    $data['qty'] = $_GET['id_product_qty'][$i];
                    $data['amt'] = $_GET['amt'][$i];
                    $this->OrderReturnitem->where($map)->save($data);
                }

                //提交操作时进行库存处理
                if ($_GET['status'] == 2) //提交
                {
                    if ($_GET['isagain'] == OrderStatus::YES_AGAIN) //可再次配货状态，退货入库状态为未配货状态
                    {
                        //删除订单的波次单和物流信息
                        D('Order/OrderShipping')->where(array('id_order' => $id_order))->delete();
                        D('Order/Order')->where(array('id_order' => $id_order))->save(array('id_shipping' => 0, 'date_delivery' => null, 'id_order_status' => OrderStatus::UNPICKING));
                        D('Common/OrderWave')->where(array('id_order' => $id_order))->delete();
                        D("Order/OrderRecord")->addHistory($id_order, OrderStatus::UNPICKING, $order_data['id_order_status'], '生成退货订单，把订单状态改为未配货');
                        //更新退货入库单为提交状态
                        $order_data_back = D("Order/OrderReturn")->where(array('id_order_return' => $order_return['id_order_return']))->save(array('status' => OrderStatus::COMMIT));
                        if ($order_data_back)
                        {
                            UpdateStatusModel::wave_delete_rollback_stock($id_order); //进行在单和库存回滚
                            $this->success("数据提交成功,退货成功！", U('Returns/order_return_list'));
                            die;
                        }
                        else
                        {
                            $this->success("数据提交失败！", U('Returns/order_return_list'));
                            die;
                        }
                    }
                    else
                    {
                        UpdateStatusModel::order_return($id_order);
                        $order_data_back = D("Order/OrderReturn")->where(array('id_order_return' => $order_return['id_order_return']))->save(array('status'=>OrderStatus::COMMIT));
                        if ($order_data_back)
                        {
                            //获取退货入库后所有的sku
                            $order_return_item_arr = D("Order/OrderReturnitem")->where(array('id_order_return' => $order_return['id_order_return']))->select();
                            $id_product_sku_arr_old = array_column($order_return_item_arr,'id_product_sku'); //获取sku
                            $id_product_sku_arr = array_unique(array_values($id_product_sku_arr_old)); //获取产品sku去重数组
                            //退货入库成功后进行匹配缺货
                            UpdateStatusModel::get_short_order($id_product_sku_arr);
                            //修改为退货入库并添加记录
                            D("Order/Order")->where(array('id_order' => $id_order))->save(array('id_order_status' => OrderStatus::RETURN_WAREHOUSE)); //退货入库
                            D("Order/OrderRecord")->addHistory($id_order, OrderStatus::RETURN_WAREHOUSE, $order_data['id_order_status'], '生成退货订单，把订单状态改为退货入库');
                            $this->success("数据提交成功,退货成功！", U('Returns/order_return_list'));
                            die;
                        }
                    }
                }
                $this->success("数据保存成功,退货失败！", U('Returns/order_return_list'));
                die;
            }
        }
        $this->error("数据错误！", U('Returns/order_return_list'));
        die;

    }

    /**退货订单新增*/
    public function add_post()
    {
        $id_increment = $_GET['id_increment'];
        $model = new \Think\Model();
        //根据订单号获取订单信息
        $order_data = $model->table($this->Order->getTableName())
            ->where(array('id_increment' => $id_increment))
            ->find();

        if ($order_data['id_order_status'] == OrderStatus::RETURN_WAREHOUSE) //21 该订单为退货入库订单不能生成退货订单
        {
            $this->error("该订单为退货入库订单状态，禁止生成退货单", U('Returns/order_return_list'));
            die;
        }

        if (!in_array($order_data['id_order_status'], OrderStatus::get_all_can_order_return()))
        {
            $this->error("该订单状态不能生成退货单", U('Returns/order_return_list'));
            die;
        }

        if (!$order_data)
        {
            $this->error("获取不到该订单号信息，请核实后再操作！", U('Returns/order_return_list'));
            die;
        }

        $id_order = $order_data['id_order'];
        $where['o.id_order'] = array('EQ', $order_data['id_order']);
        $order_return = $model->table($this->OrderReturn->getTableName() . ' o')
            ->where($where)
            ->find();

        if ($order_return)
        {
            $this->error("该订单号已生成退货单！", U('Returns/order_return_list'));
            die;
        }
        //新增操作
        for( $i=0; $i<count($_GET['id_product']); $i++)
        {
            $where = array();
            $where['o.id_order'] = array('EQ', $id_order);
            $model = new \Think\Model();
            $order_shipping = $model->table($this->OrderShipping->getTableName() . ' o')
                ->field('id_order_shipping, date_return')
                ->where($where)
                ->find();

            $order_return_data = array();
            $order_return_data['id_order'] = $id_order;
            $order_return_data['id_warehouse'] = $_GET['id_warehouse'];
            $order_return_data['id_users'] = $_SESSION['ADMIN_ID'];
            $order_return_data['id_department'] = $order_data['id_department']; //插入部门信息
            $order_return_data['id_order_shipping'] = (int)$order_shipping['id_order_shipping']; //插入退款运单号
            //退货时间处理
            if ($_GET['status'] == 2)
            {
                $order_return_data['date_return'] = date('Y-m-d H:i:s'); //提交时退货时间为当前时间
            }
            else
            {
                $order_return_data['date_return'] = '';  //新增退货订单时默认为空
            }
            $order_return_data['remark'] = $_GET['remark'];
            $order_return_data['isagain'] = $_GET['isagain'];
            $order_return_data['status'] = OrderStatus::SAVE;
            $order_return_data['created_at'] = date('Y-m-d H:i:s');
            $id_order_return = $this->OrderReturn->data($order_return_data)->add();

            if($id_order_return)
            {
                for( $i=0; $i<count($_GET['id_product']); $i++)
                {
                    $data['id_order_return'] = $id_order_return;
                    $data['id_product'] = $_GET['id_product'][$i];
                    $data['id_product_sku'] = $_GET['id_product_sku'][$i];
                    $data['option_value'] = $_GET['id_product'][$i];
                    $data['qty'] = $_GET['id_product_qty'][$i];
                    $data['amt'] = $_GET['amt'][$i];
                    $this->OrderReturnitem->data($data)->add();
                }

                //提交操作时进行库存处理
                if ($_GET['status'] == 2) //提交
                {
                    if ($_GET['isagain'] == OrderStatus::YES_AGAIN)
                    {
                        //删除订单的波次单和物流信息
                        D('Order/OrderShipping')->where(array('id_order' => $id_order))->delete();
                        D('Order/Order')->where(array('id_order' => $id_order))->save(array('id_shipping' => 0, 'date_delivery' => null, 'id_order_status' => OrderStatus::UNPICKING));
                        D('Common/OrderWave')->where(array('id_order' => $id_order))->delete();
                        D("Order/OrderRecord")->addHistory($id_order, OrderStatus::UNPICKING, $order_data['id_order_status'], '生成退货订单，把订单状态改为未配货');
                        //更新退货入库单为提交状态
                        $order_data_back = D("Order/OrderReturn")->where(array('id_order_return' => $id_order_return))->save(array('status' => OrderStatus::COMMIT));
                        if ($order_data_back)
                        {
                            UpdateStatusModel::wave_delete_rollback_stock($id_order); //进行在单和库存回滚
                            $this->success("数据提交成功,退货成功！", U('Returns/order_return_list'));
                            die;
                        }
                        else
                        {
                            $this->success("数据提交失败！", U('Returns/order_return_list'));
                            die;
                        }
                    }
                    else
                    {
                        UpdateStatusModel::order_return($id_order);
                        $order_data_back = D("Order/OrderReturn")->where(array('id_order_return' => $id_order_return))->save(array('status'=>OrderStatus::COMMIT));
                        if ($order_data_back)
                        {
                            $id_product_sku_arr = array_unique(array_values($_GET['id_product_sku'])); //获取产品sku去重数组
                            //退货入库成功后进行匹配缺货
                            UpdateStatusModel::get_short_order($id_product_sku_arr);
                            //修改为退货入库并添加记录
                            D("Order/Order")->where(array('id_order' => $id_order))->save(array('id_order_status' => OrderStatus::RETURN_WAREHOUSE)); //退货入库
                            D("Order/OrderRecord")->addHistory($id_order, OrderStatus::RETURN_WAREHOUSE, $order_data['id_order_status'], '生成退货订单，把订单状态改为退货入库');
                            $this->success("数据提交成功,退货成功！", U('Returns/order_return_list'));
                            die;
                        }
                        else
                        {
                            $this->success("数据保存成功,退货失败！", U('Returns/order_return_list'));
                            die;
                        }
                    }
                }
                $this->success("数据保存成功！", U('Returns/order_return_list'));
                die;
            }
        }
        $this->error("数据保存失败！", U('Returns/order_return_list'));

    }

    /**退货订单修改*/
    public function order_return_edit()
    {
        $id_order = I('get.id_order/i');
        if ($id_order && is_numeric($id_order))
        {
            $where['o.id_order'] = array('EQ', $id_order);
            $model = new \Think\Model();
            $order_return = $model->table($this->OrderReturn->getTableName() . ' o')
                ->where($where)
                ->find();
            //获取该订单的订单号打她
            $order_data = $model->table($this->Order->getTableName() . ' o')
                ->where($where)
                ->find();
            $order_return['id_increment'] = $order_data['id_increment'];
            if ( $order_return['status'] == 2) //退货订单不可修改
            {
                $this->success("该退货订单已提交，不可修改！", U('Returns/order_return_list'));
                die;
            }

            $list = $model->table($this->OrderItem->getTableName() . ' o')
                ->field('id_product_sku, id_product, price, quantity, total, attrs')
                ->where($where)
                ->select();
            $data = array();
            foreach ($list as  $k => $v)
            {
                $data[$k]['id_product'] = $v['id_product'];
                $where = array();
                $where['id_product'] = array('EQ',  $v['id_product']);
                $product_name = $model->table($this->Product->getTableName())
                    ->field('title')
                    ->where($where)
                    ->find();
                $data[$k]['product_name'] = $product_name['title'];
                $data[$k]['id_product_sku'] = $v['id_product_sku'];
                $data[$k]['quantity'] = $v['quantity'];
                $data[$k]['price'] = $v['price'];
                $data[$k]['total'] = $v['total'];
                $data[$k]['attrs'] = implode(',',unserialize($v['attrs']));

                //获取订单详情信息
                $map_detail = array();
                $map_detail['id_order_return'] = array('EQ', $order_return['id_order_return']);
                $map_detail['id_product'] = array('EQ', $v['id_product']);
                $details_return = $model->table($this->OrderReturnitem->getTableName() . ' o')
                    ->field('qty,amt')
                    ->where($where)
                    ->find();
                $data[$k]['qty'] = $details_return['qty'];
                $data[$k]['amt'] = $details_return['amt'];

                //获取产品属性名称
                if( $data[$k]['attrs'])
                {
                    $map = array();
                    $map['o.id_product_option_value'] = array('IN', $data[$k]['attrs']);
                    $attrs_title = $model->table($this->ProductOptionValue->getTableName() . ' o')
                        ->field('title')
                        ->where($map)
                        ->select();
                    $titles =  array_column($attrs_title,'title');
                    $data[$k]['attrs_title'] = implode(',',$titles);
                }
                else
                {
                    $data[$k]['attrs_title'] = '--';
                }
            }

            $warehouse = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
            $departments = array_column(SearchData::search()['departments'],'title','id_department');
            $isAgain = \Order\Lib\OrderStatus::get_order_return_again();
            $this->assign("return_order_detail", $data);
//            echo "<pre>";
//            var_dump($order_return);die;
            $this->assign("order_return", $order_return);
            $this->assign("isAgain", $isAgain);
            $this->assign("department", $departments);
            $this->assign("warehouse", $warehouse);
            $this->display();
        }
        else
        {
            $this->error("订单ID错误！", U('Returns/order_return_list'));
            die;
        }
    }

    /**退货订单详情*/
    public function order_return_info()
    {
        $id_order = I('get.id_order/i');
        if ($id_order && is_numeric($id_order))
        {
            $where['o.id_order'] = array('EQ', $id_order);
            $model = new \Think\Model();
            $order_return = $model->table($this->OrderReturn->getTableName() . ' o')
                ->where($where)
                ->find();
            //获取订单信息
            $order_data = $model->table($this->Order->getTableName() . ' o')
                ->where($where)
                ->find();
            $order_return['id_increment'] = $order_data['id_increment'];
            $list = $model->table($this->OrderItem->getTableName() . ' o')
                ->field('id_product_sku, id_product, price, quantity, total, attrs')
                ->where($where)
                ->select();
            $data = array();
            foreach ($list as  $k => $v)
            {
                $data[$k]['id_product'] = $v['id_product'];
                $where = array();
                $where['id_product'] = array('EQ',  $v['id_product']);
                $product_name = $model->table($this->Product->getTableName())
                    ->field('title')
                    ->where($where)
                    ->find();
                $data[$k]['product_name'] = $product_name['title'];
                $data[$k]['id_product_sku'] = $v['id_product_sku'];
                $data[$k]['quantity'] = $v['quantity'];
                $data[$k]['price'] = $v['price'];
                $data[$k]['total'] = $v['total'];
                $data[$k]['attrs'] = implode(',',unserialize($v['attrs']));

                //获取订单详情信息
                $map_detail = array();
                $map_detail['id_order_return'] = array('EQ', $order_return['id_order_return']);
                $map_detail['id_product'] = array('EQ', $v['id_product']);
                $details_return = $model->table($this->OrderReturnitem->getTableName() . ' o')
                    ->field('qty,amt')
                    ->where($where)
                    ->find();
                $data[$k]['qty'] = $details_return['qty'];
                $data[$k]['amt'] = $details_return['amt'];

                //获取产品属性名称
                if( $data[$k]['attrs'])
                {
                    $map = array();
                    $map['o.id_product_option_value'] = array('IN', $data[$k]['attrs']);
                    $attrs_title = $model->table($this->ProductOptionValue->getTableName() . ' o')
                        ->field('title')
                        ->where($map)
                        ->select();
                    $titles =  array_column($attrs_title,'title');
                    $data[$k]['attrs_title'] = implode(',',$titles);
                }
                else
                {
                    $data[$k]['attrs_title'] = '--';
                }
            }

            $warehouse = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
            $departments = array_column(SearchData::search()['departments'],'title','id_department');
            $isAgain = \Order\Lib\OrderStatus::get_order_return_again();
            $this->assign("return_order_detail", $data);
            $this->assign("order_return", $order_return);
            $this->assign("isAgain", $isAgain);
            $this->assign("department", $departments);
            $this->assign("warehouse", $warehouse);
            $this->display();
        }
        else
        {
            $this->error("订单ID错误！", U('Returns/order_return_list'));
            die;
        }
    }

    /** 通过订单获取产品信息*/
    public function get_product_list()
    {
        $id_increment = $_POST['id_increment'];
        if(!empty($id_increment))
        {
            $model = new \Think\Model();
            //根据订单号获取订单信息
            $order_data = $model->table($this->Order->getTableName())
                ->where(array('id_increment' => $id_increment))
                ->find();
            if (!$order_data)
            {
                echo json_encode(['status' => 0, 'msg' => '获取不到该订单号信息，请核实后再操作！']);
                exit;
            }

            if (!in_array($order_data['id_order_status'], OrderStatus::get_all_can_order_return()))
            {
                echo json_encode(['status' => 0, 'msg' => '该订单状态不能生成退货单']);
                exit;
            }

            if ($order_data['id_order_status'] == OrderStatus::RETURN_WAREHOUSE) //21 该订单为退货入库订单不能生成退货订单
            {
                echo json_encode(['status' => 0, 'msg' => '该订单为退货入库订单状态，禁止生成退货单']);
                exit;
            }

            $where_order_return = array();
            $where_order_return['o.id_order'] = $order_data['id_order'];
            $order_return = $model->table($this->OrderReturn->getTableName() . ' o')
                ->where($where_order_return)
                ->find();
            //退货订单是否存在
            if ($order_return)
            {
                if ( $order_return['status'] == 2) //退货订单是不可修改
                {
                    echo json_encode(['status' => 0, 'msg' => '该退货订单已提交！不能修改']);
                    exit;
                }
                else
                {
                    echo json_encode(['status' => 0, 'msg' => '该订单号已生成退货订单！']);
                    exit;
                }
            }
            else        //退货订单不存在
            {
                $list = $model->table($this->OrderItem->getTableName() . ' o')
                    ->field('id_product_sku, id_product, price, quantity, total, attrs')
                    ->where($where_order_return)
                    ->select();
                $data = array();
                foreach ($list as  $k => $v)
                {
                    $data[$k]['id_product'] = $v['id_product'];
                    $where = array();
                    $where['id_product'] = array('EQ',  $v['id_product']);
                    $product_name = $model->table($this->Product->getTableName())
                        ->field('title')
                        ->where($where)
                        ->find();
                    $data[$k]['product_name'] = $product_name['title'];
                    $data[$k]['id_product_sku'] = $v['id_product_sku'];
                    $data[$k]['quantity'] = $v['quantity'];
                    $data[$k]['price'] = $v['price'];
                    $data[$k]['total'] = $v['total'];
                    $data[$k]['attrs'] = implode(',',unserialize($v['attrs']));

                    if( $data[$k]['attrs'])
                    {
                        $map = array();
                        $map['o.id_product_option_value'] = array('IN', $data[$k]['attrs']);
                        $attrs_title = $model->table($this->ProductOptionValue->getTableName() . ' o')
                            ->field('title')
                            ->where($map)
                            ->select();
                        $titles =  array_column($attrs_title,'title');
                        $data[$k]['attrs_title'] = implode(',',$titles);
                    }
                    else
                    {
                        $data[$k]['attrs_title'] = '--';
                    }
                }
                if($data)
                {
                    echo json_encode(['status' => 1, 'list' => $data, 'msg' => '获取数据成功！']);
                    exit;
                }
            }
        }
        echo json_encode(['status' => 0, 'msg' => '请输入正确的订单号数据！']);
        exit;
    }

    /**
     * 退货入库单
     */
    public function order_return($id_order_return,$uid)
    {
        $procedure_name = 'ERP_INOUT_SUBMIT';
        $array['billid'] = $id_order_return;
        $array['userid'] = $uid;
        $array['tablename'] = 'erp_order_return';
        $array['inor'] = 'I';    //退货入库操作
        Procedure::call($procedure_name, $array);
    }

    /**
     * 根据运单号生成退货单
     */
    public function import_return_order()
    {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        $warehouse = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
        if (IS_POST) {
            $model = new \Think\Model();
            $uid = $_SESSION['ADMIN_ID']; //获取操作人ID
            $data = I('post.data');
            $remark = I('post.remark'); //退货原因
            $isAgain = I('post.isagain'); //是否可再次配货
            //导入记录到文件
            $path = write_file('warehouse', 'return_warehouse', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            $return_no = date("YmdHis") . round(0, 99999);
            $all_return_no = 0;//退货单数
            $all_return_quantity = 0;//退货个数

            if (count($data) >= 500) {
                $infor['error'][] = sprintf('导入的行数过大，最多不超过500行');
            } else {
                foreach ($data as $row) {
                    $row = trim($row);
                    if (empty($row))
                        continue;
                    $total++;
                    $row = explode("\t", trim($row), 2);
                    $track_number = str_replace("'", '', $row[0]);
                    $track_number = str_replace(array('"', ' ', ' ', '　'), '', $track_number);
                    $track_number = trim($track_number);

                    //通过运单号获取订运单信息
                    $order_shipping_data = D('Order/OrderShipping')
                        ->field('id_order, track_number,id_order_shipping,date_return')
                        ->where(array('track_number' => $track_number))
                        ->find();

                    //如果该运单号已生成退货订单，不再生成退货订单
                    $order_return = $model->table($this->OrderReturn->getTableName())
                        ->where(array('id_order' => $order_shipping_data['id_order']))
                        ->find();
                    $order_data = D("Order/Order")->where(array('id_order' => $order_shipping_data['id_order']))->find();
                    if ($order_return || $order_data['id_order_status'] == OrderStatus::RETURN_WAREHOUSE)
                    {
                        $info['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 该运单号已生成退货订单不能进行退货入库操作', $count++, $order_shipping_data['id_increment'], $track_number);
                        continue;
                    }

                    if ($order_shipping_data) {
                        $id_order = $order_shipping_data['id_order'];//订单id
                        $order = D('Order/Order')->field('id_order_status,id_increment,id_warehouse,id_department')->where(array('id_order' => $id_order))->find();

                        $id_order_status_arr = OrderStatus::get_all_can_order_return(); //获取能够生成退货订单的状态
                        if (in_array($order['id_order_status'], $id_order_status_arr)) {

                            //生成退货订单写入erp_order_return
                            $order_return = array();
                            $order_return['id_warehouse'] = $order['id_warehouse'];
                            $order_return['id_department'] = $order['id_department'];
                            $order_return['id_users'] = $uid;
                            $order_return['id_order'] = $id_order;
                            $order_return['id_order_shipping'] = $order_shipping_data['id_order_shipping'];
                            $order_return['date_return'] = date('Y-m-d H:i:s');;  //运单号生成退货订单，退货时间为当前时间
                            $order_return['status'] = OrderStatus::COMMIT; //退货状态为2:保存(不允许修改)
                            $order_return['remark'] = $remark; //退货原因
                            $order_return['created_at'] = date('Y-m-d H:i:s');
                            $order_return['updated_at'] = date('Y-m-d H:i:s');
                            $order_return['isagain'] = $isAgain;  //是否可再次派货
                            //新增退货单
                            $id_order_return = D("Order/OrderReturn")->data($order_return)->add();

                            //获取订单详情信息
                            $order_item_data = D("Common/OrderItem")
                                ->where(array('id_order' => $id_order))
                                ->field('id_product_sku, id_product, price, quantity, total, attrs')
                                ->select();
                            if ($id_order_return && $order_item_data) {
                                $Prodate['track_number'] = $track_number;
                                foreach ($order_item_data as $k => $v) {
                                    $order_return_item_data = array();
                                    $order_return_item_data['id_order_return'] = $id_order_return;
                                    $order_return_item_data['id_product'] = $v['id_product'];
                                    $order_return_item_data['id_product_sku'] = $v['id_product_sku'];
                                    $order_return_item_data['option_value'] = !empty($v['attrs']) ? $v['attrs'] : '--';
                                    $order_return_item_data['qty'] = $v['quantity'];
                                    $order_return_item_data['amt'] = $v['total'];
                                    //新增退货订单详情
                                    D('Order/OrderReturnitem')->data($order_return_item_data)->add();
                                }

                                if ($isAgain == OrderStatus:: YES_AGAIN) //可再次配货
                                {
                                    //删除订单的波次单和物流信息
                                    D('Order/OrderShipping')->where(array('id_order' => $id_order))->delete();
                                    D('Order/Order')->where(array('id_order' => $id_order))->save(array('id_shipping' => 0, 'date_delivery' => null,'id_order_status' => OrderStatus::UNPICKING));
                                    D('Common/OrderWave')->where(array('id_order' => $id_order))->delete();
                                    D("Order/OrderRecord")->addHistory($id_order, OrderStatus::UNPICKING, $order_data['id_order_status'], '生成退货订单，把订单状态改为未配货或已审核');
                                    //更新退货入库单为提交状态
                                    $order_data_back = D("Order/OrderReturn")->where(array('id_order_return' => $id_order_return))->find();
                                    if ($order_data_back['status'] == OrderStatus::COMMIT)
                                    {
                                        UpdateStatusModel::wave_delete_rollback_stock($id_order); //进行在单和库存回滚
                                    }
                                }
                                else
                                {
                                    //调用存储过程->增加流水，加入库存
                                    UpdateStatusModel::order_return($id_order);
                                    $order_data_back = D("Order/OrderReturn")->where(array('id_order_return' => $id_order_return))->find();
                                    if ( $order_data_back['status'] == OrderStatus::COMMIT)
                                    {
                                        //获取退货入库后所有的sku
                                        $order_return_item_arr = D("Order/OrderReturnitem")->where(array('id_order_return' => $id_order_return))->select();
                                        $id_product_sku_arr_old = array_column($order_return_item_arr,'id_product_sku'); //获取sku
                                        $id_product_sku_arr = array_unique(array_values($id_product_sku_arr_old)); //获取产品sku去重数组
                                        //退货入库成功后进行匹配缺货
                                        UpdateStatusModel::get_short_order($id_product_sku_arr);
                                        //更新订单状态为< 21 >退货入库状态
                                         D('Order/Order')->where(array('id_order' => $id_order))->save(array('id_order_status' => OrderStatus::RETURN_WAREHOUSE));
                                        //增加订单历史记录
                                        D("Order/OrderRecord")->addHistory($id_order, OrderStatus::RETURN_WAREHOUSE, $order['id_order_status'], '更新订单为退货入库状态，运单号' . $track_number . '，仓库' . $warehouse[$_POST['warehouse_id']]);
                                    }
                                }
                                $all_return_no = $all_return_no + 1;
                                $all_return_quantity = $all_return_quantity + $total;
                            }
                            $info['success'][] = sprintf('第%s行: 订单号:%s 运单号:%s 仓库名称: %s 退货入库单号: %s', $count++, $order['id_increment'], $track_number, $warehouse[$_POST['warehouse_id']], $return_no);
                        } else {
                            $info['error'][] = sprintf('第%s行: 订单号:%s 运单号:%s 该运单号不能进行退货入库操作', $count++, $order['id_increment'], $track_number);
                        }
                    } else {
                        $info['error'][] = sprintf('第%s行: 运单号:%s 没有该运单号', $count++, $track_number);
                    }
                }
                add_system_record($uid, 2, 4, '更新订单为退货入库状态', $path);
            }
        }
        $isAgain = OrderStatus::get_order_return_again();
        $this->assign("isAgain", $isAgain);
        $this->assign('post', $_POST);
        $this->assign('infor', $info);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /**
     * 批量提交退货订单
     * @param $id_order
     */
    public function create_order_return_all()
    {
        $id_order_return_arr = explode(',',$_GET['id']);
        foreach ($id_order_return_arr as $id_order_return)
        {
            //更新退货状态为提交
            $update_data = array();
            $update_data['date_return'] = date('Y-m-d, H:i:s');
            $update_data['updated_at'] = date('Y-m-d, H:i:s');
            $update_data['status'] = OrderStatus::COMMIT;
            $res = D("Order/OrderReturn")->where(array('id_order_return' => $id_order_return))->save($update_data);
            $order_return_data = D("Order/OrderReturn")->where(array('id_order_return' => $id_order_return))->find();
            $order_data = D('Order/Order')->where(array('id_order' => $order_return_data['id_order']))->find();

            if ($res)
            {
                if ($order_return_data['isagain'] == OrderStatus::YES_AGAIN) //可再次配货状态，进行在单库存回滚
                {
                    //删除订单的波次单和物流信息
                    D('Order/OrderShipping')->where(array('id_order' => $order_return_data['id_order']))->delete();
                    D('Order/Order')->where(array('id_order' => $order_return_data['id_order']))->save(array('id_shipping' => 0, 'date_delivery' => null, 'id_order_status' => OrderStatus::UNPICKING));
                    D('Common/OrderWave')->where(array('id_order' => $order_return_data['id_order']))->delete();
                    D("Order/OrderRecord")->addHistory($order_return_data['id_order'], OrderStatus::UNPICKING, $order_data['id_order_status'], '生成退货订单，把订单状态改为未配货');
                    //更新退货入库单为提交状态
                    $order_data_back = D("Order/OrderReturn")->where(array('id_order_return' => $id_order_return))->find();
                    if ($order_data_back['status'] == OrderStatus::COMMIT)
                    {
                        UpdateStatusModel::wave_delete_rollback_stock($order_return_data['id_order']); //进行在单和库存回滚
                    }
                }
                else
                {
                    UpdateStatusModel::order_return($order_data['id_order']);
                    $order_data_back = D("Order/OrderReturn")->where(array('id_order_return' => $id_order_return))->find();
                    if ($order_data_back['status'] == OrderStatus::COMMIT)
                    {
                        //获取退货入库后所有的sku
                        $order_return_item_arr = D("Order/OrderReturnitem")->where(array('id_order_return' => $id_order_return))->select();
                        $id_product_sku_arr_old = array_column($order_return_item_arr,'id_product_sku'); //获取sku
                        $id_product_sku_arr = array_unique(array_values($id_product_sku_arr_old)); //获取产品sku去重数组
                        //退货入库成功后进行匹配缺货
                        UpdateStatusModel::get_short_order($id_product_sku_arr);

                        //修改订单记录并保存订单记录
                        D("Order/Order")->where(array('id_order' => $order_data['id_order']))->save(array('id_order_status' => OrderStatus::RETURN_WAREHOUSE)); //退货入库
                        D("Order/OrderRecord")->addHistory($order_data['id_order'], OrderStatus::RETURN_WAREHOUSE, $order_data['id_order_status'], '生成退货订单，把订单状态改为退货入库');
                    }
                    else
                    {
                        $this->error("提交失败！", U('Returns/order_return_list'));
                        die;
                    }
                }
            }
        }
        $this->success("提交成功！", U('Returns/order_return_list'));
        die;

    }

}
