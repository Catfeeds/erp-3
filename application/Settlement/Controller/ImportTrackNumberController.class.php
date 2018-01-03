<?php

namespace Settlement\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;

/**
 * Class ReturnGoodsController
 *  导入excel改变订单状态为已签收，物流信息相应修改
 *  zhujie #150 20171117
 *
 * @package Settlement\Controller
 */
class ImportTrackNumberController extends AdminbaseController
{

    public $returnGoods;

    public function _initialize ()
    {
        parent::_initialize();
    }

    /**
     *  zhujie  #150 20171118
     * 导入物流单号，改变订单状态为已签收， 物流信息随之改变
     */
    public function import_track_no ()
    {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if ( IS_POST ) {
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $data = I('post.data');
            $id_shipping = I('post.id_shipping');
            $id_order_status = I('post.id_order_status');
            $data = $this->getDataRow($data);
            $count = 1;

            foreach ($data as $row) {
                $row = trim($row);
                if ( empty($row) )
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 3);
                if (count($row) != 3 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }

                $track_number = trim($row[0]);
                $text = trim($row[1]);//物流状态
                $text1 = trim($row[2]);//物流归类状态
                try {
                    $order_list = M('Order')->alias("o")
                        ->join('__ORDER_SHIPPING__ os ON o.id_order = os.id_order')
                        ->field("o.*,os.summary_status_label,os.track_number")
                        ->where(['os.track_number' => $track_number, 'os.id_shipping' => $id_shipping])
                        ->find();
                    /*if ( $order_list['id_order_status'] == 9 && !is_null($order_list['summary_status_label']) ) {
                        $infor['warning'][] = sprintf('第%s行: 运单号:%s 订单状态已经是 <已签收>，不要重复修改', $count++, $row);
                        continue;
                    }*/
                    if ( $order_list ) {
                        if ( $id_order_status == 16 ) { //拒收
                            $this->refuse($order_list['id_order'],$text,$text1);
                        } elseif ( $id_order_status == 10 ) { //已退货
                            $this->returned($order_list['id_order'],$text,$text1);
                        }elseif ( $id_order_status == 8 ) { //配送中
                            $this->delivering($order_list['id_order'],$text,$text1);
                        } else {
                            M()->startTrans();

                            $res3 = D("Order/OrderRecord")->addHistory($order_list['id_order'],OrderStatus::SIGNED,4,"物流状态更新为已签收");
                            $res1 = M('OrderShipping')->where(array('id_order' => $order_list['id_order']))
                                ->setField(array(
                                    'status_label' => $text,
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'summary_status_label' => $text1,
                                    'date_signed' => date('Y-m-d H:i:s'),
                                    'fetch_count' => array('exp', 'fetch_count+1')
                                ));
                            $res2 = M('Order')->where(array('id_order' => $order_list['id_order']))
                                ->setField(array(
                                    'id_order_status' => OrderStatus::SIGNED,
                                    'refused_to_sign' => 0,
                                ));
                            //结算
                            $order_data = M('Order')->where(array('id_order' => $order_list['id_order']))->find();
                            $settlement_exists = M('OrderSettlement')->where(array('id_order' => $order_list['id_order']))->find();
                            $res4 = true;
                            if ( empty($order_data['payment_method']) && empty($settlement_exists) ) {
                                $id_order_shipping = M('OrderShipping')->where(array('id_order' => $order_list['id_order']))->getField('id_order_shipping');
                                //到付，且未有结算记录
                                $res4 = M('OrderSettlement')->add(array(
                                    'id_order_shipping' => $id_order_shipping,
                                    'id_order' => $order_list['id_order'],
                                    'amount_total' => $order_data['price_total'],
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ));
                            }
                            if ( $res1 === false || $res2 === false || $res3 === false || $res4 === false ) {
                                M()->rollback();
                                $infor['warning'][] = sprintf('第%s行: 运单号:%s  事务修改错误，请重试', $count++, $track_number);
                            } else {
                                M()->commit();
                            }
                        }
                    } else {
                        $infor['warning'][] = sprintf('第%s行: 运单号:%s 不存在', $count++, $track_number);
                        continue;
                    }
                } catch (Exception $e) {
                    echo $e->getMessage();
                }

                $infor['success'][] = sprintf('第%s行: 运单号:%s  已修改', $count++, $track_number);
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '修改订单状态');
        }

        $shipping = M('Shipping')->field('id_shipping,title')->where('status=1')->select();
        $this->assign('shipping', $shipping);
        $this->assign('id_shipping', $id_shipping);
        $this->assign('id_order_status', $id_order_status);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    //拒收
    public function refuse ($id_order, $text = '拒收' , $text1="拒收")
    {
        M()->startTrans();
        $res1 = M('OrderShipping')->where(array('id_order' => $id_order))
            ->setField(array(
                'status_label' => $text,
                'updated_at' => date('Y-m-d H:i:s'),
                'summary_status_label' => $text1,
                'fetch_count' => array('exp', 'fetch_count+1')
            ));
        $res2 = M('Order')->where(array('id_order' => $id_order))
            ->setField(array(
                'id_order_status' => OrderStatus::REJECTION,
                'refused_to_sign' => 1,
            ));
        $res3 = D("Order/OrderRecord")->addHistory($id_order,OrderStatus::REJECTION,4,"物流状态更新为拒收");

        if ( $res1 === false || $res2 === false || $res3 === false ) {
            M()->rollback();
            return false;
        } else {
            M()->commit();
            return true;
        }
    }

    //退货
    public function returned ($id_order , $text = '退货完成', $text1 = '退货完成')
    {
        M()->startTrans();
        $res1 = M('OrderShipping')->where(array('id_order' => $id_order))
            ->setField(array(
                'status_label' => $text,
                'updated_at' => date('Y-m-d H:i:s'),
                'summary_status_label' => $text1,
                'fetch_count' => array('exp', 'fetch_count+1')
            ));
        $res2 = M('Order')->where(array('id_order' => $id_order))
            ->setField(array(
                'id_order_status' => OrderStatus::RETURNED,
                'refused_to_sign' => 1,
            ));
        $res3 = D("Order/OrderRecord")->addHistory($id_order,OrderStatus::RETURNED,4,"物流状态更新为退货");

        if ( $res1 === false || $res2 === false || $res3 === false ) {
            M()->rollback();
            return false;
        } else {
            M()->commit();
            return true;
        }
    }

    //配送中
    public function delivering ($id_order , $text = '配送中', $text1 = '配送中')
    {
        M()->startTrans();
        $res1 = M('OrderShipping')->where(array('id_order' => $id_order))
            ->setField(array(
                'status_label' => $text,
                'updated_at' => date('Y-m-d H:i:s'),
                'summary_status_label' => $text1,
                'fetch_count' => array('exp', 'fetch_count+1')
            ));
        $res2 = M('Order')->where(array('id_order' => $id_order))
            ->setField(array(
                'id_order_status' => OrderStatus::DELIVERING,
                'refused_to_sign' => 1,
            ));
        $res3 = D("Order/OrderRecord")->addHistory($id_order,OrderStatus::DELIVERING,4,"物流状态更新为配送中");

        if ( $res1 === false || $res2 === false || $res3 === false ) {
            M()->rollback();
            return false;
        } else {
            M()->commit();
            return true;
        }

    }
}
