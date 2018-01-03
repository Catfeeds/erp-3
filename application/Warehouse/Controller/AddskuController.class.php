<?php

namespace Warehouse\Controller;
use Common\Controller\HomebaseController;

class AddskuController extends HomebaseController {

    //添加新增的商品sku库存
    public function batch_add_post() {
        try {
            $warehouse_id = I('get.id');
            $product_id = I('get.pro_id');
            $warehouse = M("Warehouse")->find($warehouse_id);
            if ($warehouse_id && $warehouse) {
                if ($product_id) {
                    $product = M('Product')->where(array('id_product' => $product_id))->select();
                } else {
                    $product = M('Product')->select();
                }
                if ($product && is_array($product)) {
                    foreach ($product as $k => $val) {
                        $id_product_sku = M('ProductSku')->field('id_product_sku')->where('id_product=' . $val['id_product'])->select();
                        if (!$id_product_sku) continue;
                        foreach ($id_product_sku as $sku) {
                            $warehouse_pro_sku = M('WarehouseProduct')->where(array('id_product_sku' => $sku['id_product_sku'], 'id_warehouse' => $warehouse_id))->getField('id_product_sku');
                            if ($warehouse_pro_sku) continue;
                            $data_list = array(
                                'id_warehouse' => $warehouse_id,
                                'id_product' => $val['id_product'],
                                'id_product_sku' => $sku['id_product_sku'],
                                'quantity' => 0,
                                'road_num' => 0
                            );
                            D('Common/WarehouseProduct')->add($data_list);
                        }
                    }
                }
                $status = 1;
                $message = '成功';
            } else {
                $status = 0;
                $message = '参数不正确';
            }
        } catch (\Exception $e) {
            $status = 0;
            $message = $e->getMessage();
        }
        $return = array('status' => $status, 'message' => $message);
        echo json_encode($return);
        exit();
    }

}
