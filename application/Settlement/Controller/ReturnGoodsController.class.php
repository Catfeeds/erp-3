<?php

namespace Settlement\Controller;

use Common\Controller\AdminbaseController;
use Common\Model\ReturnGoodsModel;

/**
 * Class ReturnGoodsController
 *  财务退货单列表  zhujie #094 20171116
 * @package Settlement\Controller
 */
class ReturnGoodsController extends AdminbaseController
{

    public $return_goods;

    public function _initialize ()
    {
        parent::_initialize();
        $this->return_goods = D('ReturnGoods');
    }

    public function index ()
    {

        $depart_id = session('department_id');
        if ( isset($_GET['id_department']) && $_GET['id_department'] ) {
            $where['rg.id_department'] = array('EQ', $_GET['id_department'] );
        } else {
            $where['rg.id_department'] = array('IN', implode(',',$depart_id));
        }
        if ( isset($_GET['id_warehouse']) && $_GET['id_warehouse'] ) {
            $where['rg.id_warehouse'] = array('EQ', $_GET['id_warehouse']);
        }
        if ( isset($_GET['id_return']) && $_GET['id_return'] ) {
            $where['rg.id_return'] = array('EQ', $_GET['id_return']);
        }
        if ( isset($_GET['warehouse_status']) && $_GET['warehouse_status'] ) {
            $where['rg.warehouse_status'] = array('EQ', $_GET['warehouse_status']);
        } else {
            if ( I('get.warehouse_status') !== '0' ) {
                $where['rg.warehouse_status'] = 1;
                $_GET['warehouse_status'] = 1;
            }
        }
        if ( isset($_GET['return_type']) && $_GET['return_type'] ) {
            $where['rg.return_type'] = array('EQ', $_GET['return_type']);
        }
        if ( isset($_GET['receive_person']) && $_GET['receive_person'] ) {
            $where['rg.receive_person'] = array('EQ', $_GET['receive_person']);
        }

        if ( isset($_GET['shipping_no']) && $_GET['shiprgng_no'] ) {
            $where['rg.shipping_no'] = array('EQ', $_GET['shipping_no']);
        }
        if ( isset($_GET['track_number']) && $_GET['track_number'] ) {
            $where['rg.track_number'] = array('EQ', $_GET['track_number']);
        }
        if ( isset($_GET['phone']) && $_GET['phone'] != '' ) {
            $where['rg.phone'] = array('EQ', $_GET['phone']);
        }

        if ( isset($_GET['collection_status']) && $_GET['collection_status'] ) {
            $where['rg.collection_status'] = array('EQ', $_GET['collection_status']);
        } else {
            if ($_GET['collection_status'] !== '0') {
                $where['rg.collection_status'] = array('EQ', 1);
                $_GET['collection_status'] = 1;
            }
        }
        if ( isset($_GET['address']) && $_GET['address'] ) {
            $where['rg.address'] = array('like', $_GET['address']);
        }
        if ( isset($_GET['inner_name']) && $_GET['inner_name'] ) {
            $where['p.inner_name'] = array('like', $_GET['inner_name']);
        }


        if ( isset($_GET['purchase_no']) && $_GET['purchase_no'] ) {
            $purchase_no = trim($_GET['purchase_no']);
            $where['rg.purchase_no'] = array('like', "%{$purchase_no}%");
        }
        if ( $_GET['alibaba_no'] ) {
            $alibaba_no = trim($_GET['alibaba_no']);
            $where['rg.alibaba_no'] = array('like', "%{$alibaba_no}%");
        }
        if ( isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no'] ) {
            $inner_purchase_no = trim($_GET['inner_purchase_no']);
            $where['rg.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if ( isset($_GET['start_time']) && $_GET['start_time'] ) {
            $where['rg.created_at'] = array('BETWEEN', [$_GET['start_time'], $_GET['end_time']]);
        } else {
            $new = date('Y-m-d');
            $start_time = date('Y-m-d H:i:s',strtotime($new.'-1 weeks'));
            $end_time =  date($new . " 23:59:59");
            $_GET['start_time'] = $start_time;
            $_GET['end_time'] = $end_time;
            $where['rg.created_at'] = array('BETWEEN', [$start_time,$end_time]);
        }

        if ( isset($_GET['sku']) && $_GET['sku'] ) {
            $id_product_sku = M('ProductSku')->where(array('sku' => trim($_GET['sku'])))->getField('id_product_sku');
            $where['id_product_sku'] = array('EQ', $id_product_sku);
        }

        $depart_id = session('department_id');
        $department = $this->return_goods->getAllDepartment($depart_id);
        $users = $this->return_goods->getAllUsers();

        $where['rg.purchase_status'] = 4;
        $where['rg.warehouse_status'] = 2;
        $count = M('returnGoods')->alias('rg')
            ->field("rg.*")
            ->join('__RETURN_GOODS_ITEM__ rgi ON rg.id_return = rgi.id_return_goods', 'right')
            ->join('__PRODUCT__ p ON p.id_product = rgi.id_product', 'left')
            ->where($where)
            ->order('rg.updated_at desc')
            ->group('rg.id_return')
            ->select();
        $count = count($count);
        $page = $this->page($count, 20);
        $lists = $this->return_goods->getAllReturnGoods($where, $page);

        $warehouse_status = ReturnGoodsModel::$warehouse_status;
        $purchase_status = ReturnGoodsModel::$purchase_status;
        $collection_status = ReturnGoodsModel::$collection_status;
        $express_name = ReturnGoodsModel::$express_info;
        $return_type = ReturnGoodsModel::$return_type;
        $this->assign(compact('lists','warehouse_status', 'purchase_status', 'collection_status','department','users','express_name'));
        $this->assign('_get', I('get.'));
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    //财务已收款
    public function approval ()
    {
        $id_return = I('post.id_return');
        $id_returns = explode(',', $id_return);
        foreach ($id_returns as $v) {
            $res = $this->return_goods->where(['id_return' => $v])->find();
            if ($res['collection_status'] == 2) {
                $this->ajaxReturn(['flag'=>0,'msg' => '退货单已经是收款状态，请勿重复操作']);
            }
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['collection_status'] = 2;
            $appro_res = $this->return_goods->where(['id_return' => $v])->save($data);
        }
        $this->ajaxReturn(['flag'=>1,'msg' => '修改成功']);
    }
}
