<?php
namespace Common\Model;
use Common\Model\CommonModel;
class WarehouseRecordModel extends CommonModel
{

    protected $error;
    protected $type = array(
        'RECEIVED' => 1,         //收货
        'ON_SALE' => 2,        //上架
        'REDUCE' => 3,          //扣库存
        'MANUAL' => 4,          //手动修改
        'ROLLBACK' => 5,          //回滚
    );

    /**
     * @param $record  array(
     *     'type' => 'ON_SALE', //记录类型
     *     'num' => 10,   //修改数量 +添加 -减少          num 与　num_after 选一个传值
     *     'num_after' => 100 //修改后的数量
     *     'num_before' => 90 //修改前的数量              num 与　num_after 选一个传值
     *     'id_warehouse' => $id_warehouse,   //仓库编号
     *     'id_warehouse_allocation' => $id_warehouse_allocation,      //货位编号
     *     'id_product_sku' => $value['id_product_sku']     //sku编号
     *     'purchase_no' => purchase_no //采购单号  可选
     *     'id_purchase_product' => id_purchase_product //采购单号产品对应关系 可选
     *     'id_order' => id_order  //订单编号 可选(扣库存时填写)
     * )
     * @return bool
     */
    public function write($record){
        $record['type'] = $this->type[$record['type']];
        $record['id_product'] = M('ProductSku')->where(array('id_product_sku' => $record['id_product_sku']))->getFIeld('id_product');
        $record['user_id'] = sp_get_current_admin_id();
        $record['user_name'] = M('Users')->where(array('id'=>$record['user_id']))->getField('user_nicename');
        $record['created_at'] = date('Y-m-d H:i:s');

        if(!isset($record['num_before'])){
            $record['num_before'] = 0;
        }

        if(isset($record['num'])){
            $record['num_after'] = $record['num_before'] + intval($record['num']);
        }elseif(isset($record['num_after'])){
            $record['num'] = intval($record['num_after'] - $record['num_before']);
        }

        return $this->add($record);
    }

    public function read(){
        return $this->alias('wr')
            ->field('wr.id_product_sku, ps.sku,ps.barcode, p.title as product_name, wga.goods_name as allocation,
            ps.title as sku_options, wr.user_name, wr.created_at, wr.num_before, wr.num_after,w.title as warehouse_name')
            ->join("__WAREHOUSE__ as w ON w.id_warehouse=wr.id_warehouse")
            ->join("__PRODUCT__ as p ON p.id_product=wr.id_product", 'left')
            ->join("__PRODUCT_SKU__ as ps ON ps.id_product_sku=wr.id_product_sku", 'left')
            ->join("__WAREHOUSE_GOODS_ALLOCATION__ as wga ON wga.id_warehouse_allocation=wr.id_warehouse_allocation", 'left')
            ->where(array("wr.type"=> array('NOT IN', array($this->type['REDUCE'], $this->type['ROLLBACK']))));
    }

}

