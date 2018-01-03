<?php

namespace Common\Model;

use Common\Model\CommonModel;

/**
 * Class ReturnGoodsModel
 * zhujie #094
 * @package Purchase\Model
 */
class ReturnGoodsModel extends CommonModel
{
    /**
     * @var array
     */
    public static $warehouse_status = [
        1 => '<span style="color: blue;">未退货</span>',
        2 => '已退货',
        //3 => '部分退货',
    ];
    /**
     * @var array
     */
    public static $collection_status = [
        1 => '未收款',
        2 => '已收款',
        //3 => '部分收款',
    ];
    /**
     * @var array
     */
    public static $purchase_status = [
        1 => '<span style="color: blue;">未审核</span>',
        2 => '审核通过',
        3 => '审核拒绝',
        4 => '采购确认',
    ];
    //物流信息  liuruibin 20171107
    /**
     * @var array
     */
    public static $express_info = [
        1 => '韵达快递',
        2 => '申通快递',
        3 => '中通快递',
        4 => '圆通快递',
        5 => '国通快递',
        6 => '顺丰快递',
        7 => '天天快递',
        8 => '邮政EMS',
        9 => '百世快递',
        10 => '天天快递',
    ];


    public static $return_type = [
        1 => '<span style="color:red">库存退货</span>',
        2 => '<span style="color:blue">在途退货</span>',
    ];


    /**
     * @param null $where
     *
     * @return mixed
     */
    public function getAllReturnGoodsCount ($where = null)
    {

        $count = M('returnGoods')->alias('rg')
            ->join('__RETURN_GOODS_ITEM__ rgi ON rg.id_return = rgi.id_return_goods', 'right')
            ->join('__PRODUCT__ p ON p.id_product = rgi.id_product', 'left')
            ->where($where)
            ->group('rg.id_return')
            ->count();
        return $count;
    }

    /**
     * @param null $where
     * @param      $page
     *
     * @return mixed
     */
    public function getAllReturnGoods ($where = null, $page)
    {

        $list = M('returnGoods')->alias('rg')
            ->field("rg.*,p.inner_name")
            ->join('__RETURN_GOODS_ITEM__ rgi ON rg.id_return = rgi.id_return_goods', 'right')
            ->join('__PRODUCT__ p ON p.id_product = rgi.id_product', 'left')
            ->where($where)
            ->order('rg.updated_at desc')
            ->group('rg.id_return')
            ->limit($page->firstRow, $page->listRows)
            ->select();
        return $list;
    }

    /**
     * @param null $where
     *
     * @return mixed
     */
    public function getByCondition ($where = null)
    {
        return M('returnGoods')->alias('rg')
            ->field('rg.*,rgi.option_value,rgi.quantity,rgi.price,ps.sku')
            ->join('__RETURN_GOODS_ITEM__ rgi ON rgi.id_return_goods = rg.id_return')
            ->join('__PRODUCT_SKU__ ps ON rgi.id_product_sku = ps.id_product_sku')
            ->where($where)
            ->select();
    }

    /**
     * @return array
     */
    public function getAllUsers ()
    {
        $users = M('Users')->field('id,user_nicename')->where(['user_status' => 1])->select();
        return array_column($users, 'user_nicename', 'id');
    }


    /**
     * @param null $department session存储的部门id
     *
     * @return array
     */
    public function getAllDepartment ($department = null)
    {
        if ( $department ) {
            $department = M('Department')
                ->field('id_department,title')
                ->where(['parent_id' => 0, 'id_department' => ['IN', $department], 'type' => 1])
                ->order('sort asc')
                ->select();
        } else {
            $department = M('Department')->field('id_department,title')->where(['parent_id' => 0, 'type' => 1])
                ->order('sort asc')
                ->select();
        }

        return array_column($department, 'title', 'id_department');
    }

    /**
     * @return array
     */
    public function getAllWarehouse ()
    {
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where(['status' => 1])->select();
        return array_column($warehouse, 'title', 'id_warehouse');
    }

}
