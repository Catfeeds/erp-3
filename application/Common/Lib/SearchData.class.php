<?php
/**
 * Created by Eva <46231996@qq.com>.
 * User: Eva
 * Date: 2016/12/11 22:28
 * Description:获取一些常用的查询数据
 */

namespace Common\Lib;

class SearchData
{
    public static function search()
    {
        $ware = M('Warehouse')->where(array('status' => 1))->cache(true, 3600)->select();
        $depart = M('Department')->where(array('type' => 1))->cache(true, 3600)->select();
        $supplier = M('Supplier')->cache(true, 3600)->select();
        $pur_status = M('PurchaseStatus')->field('id_purchase_status,title')->where(array('status' => 1))->cache(true, 3600)->select(); //采购单状态
        $data = array(
            'warehouses'=>$ware,
            'departments'=>$depart,
            'suppliers'=>$supplier,
            'pur_status'=>$pur_status
        );
        return $data;
    }
}