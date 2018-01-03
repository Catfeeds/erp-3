<?php

namespace Goodslocation\Controller;

use Common\Controller\AdminbaseController;

class WarehouserecordController extends AdminbaseController {

    public function index()
    {
        $warehouse_record_model = D('Common/WarehouseRecord');

        $search = [];
        $allocation_search = I('request.allocation');
        $sku_search = I('request.sku');
        $product_name_search = I('request.product_name');
        $user_name_search = I('request.user_name');
        $id_warehouse = I('request.id_warehouse');

        empty($allocation_search) || $search[] = array('wga.goods_name' => trim($allocation_search));
        //empty($sku_search) || $search[] = array('ps.sku' => array('LIKE', '%'.trim($sku_search).'%'));
        empty($sku_search) || $search[] = 'ps.sku LIKE "%'.trim($sku_search).'%" or ps.barcode LIKE "'.trim($sku_search).'"';
        empty($product_name_search) || $search[] = array('p.title' => array('LIKE', '%'.trim($product_name_search).'%'));
        empty($user_name_search) || $search[] = array('wr.user_name' => array('LIKE', '%'.trim($user_name_search).'%'));
        empty($id_warehouse) || $search[] = array('wr.id_warehouse' => array('EQ', $id_warehouse));
        $warehouse_record_model->read()->where($search);

        //复制一个模型用于计数
        $warehouse_record_model_count = clone $warehouse_record_model;
        $count = $warehouse_record_model_count->count();
        $page = $this->page($count, 20);

        $result = $warehouse_record_model
            ->limit($page->firstRow, $page->listRows)
            ->order('wr.id_warehouse_record DESC')
            ->select();

        $warehouses = M('Warehouse')->select();
        $warehouses = array_column($warehouses, 'title', 'id_warehouse');

        $this->assign('warehouses', $warehouses);
        $this->assign('list', $result);
        $this->assign('page', $page->show('Admin'));
        $this->display();
    }

}
