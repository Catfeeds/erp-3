<?php
namespace Order\Model;
use Common\Model\CommonModel;
class OrderItemModel extends CommonModel {
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('id_department', 'require', '部门不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        array('title', 'require', '标题不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        array('model', 'require', 'SKU不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    );
	protected function _before_write(&$data) {
		parent::_before_write($data);
	}

    /**
     * 获取订单产品
     * @ps 设计的表字段，用起来有点纠结 蓝瘦（为何主键ID需要加上表名字，为了连表 也会存在id_order 相同字段）
     * @param $order_id
     * @param int $cache_time 缓存时间，未处理订单缓存时间比较短
     * @return mixed
     */
    public function get_item_list($order_id,$cache_time=3600){
        //不启用缓存 jiangqinqing 20171205
        //$get_order_item_cache = F('order_item_by_order_id_cache'.$order_id);
        $get_order_item_cache = array();

        if($get_order_item_cache){
            $products = json_decode($get_order_item_cache,true);
        }else{
            $M = new \Think\Model;
            $ord_ite_name = D("Order/OrderItem")->getTableName();
            $product_name = D("Product/Product")->getTableName();
            //增加传入参数数组判断 jiangqinqing 20171205
            if(is_array($order_id)){
                $where['id_order'] = array('IN', implode(",", $order_id));
                $products = $M->table($ord_ite_name.' AS oi LEFT JOIN '.$product_name.' AS p ON oi.id_product=p.id_product')
                ->field('oi.*,p.title,p.inner_name,p.foreign_title')->where($where)->order('oi.sku ASC')->select();
                foreach($products as $key=>$row){
                    $data[$row['id_order']][$row['id_product_sku']] = $row;
                    //$data[$row['id_order']][$row['id_product_sku']]['quantity'] += $row['quantity'];
                }
                return $data; 
            }else{
                $where['id_order'] = array('EQ',$order_id); 

                $products = $M->table($ord_ite_name.' AS oi LEFT JOIN '.$product_name.' AS p ON oi.id_product=p.id_product')
                ->field('oi.*,sum(oi.quantity) quantity ,p.title,p.inner_name,p.foreign_title')->where($where)->group('oi.id_product_sku')->order('oi.sku ASC')->select();
                return $products;    
            }

            //$products = D('Order/OrderItem')->where($where)->select();
            F('order_item_by_order_id_cache'.$order_id,json_encode($products));
        }

        //return $products;
    }
    
    public function get_product_count($order_id) {
        $order_item = M('OrderItem')->field('SUM(quantity) AS quantity')->where(array('id_order'=>$order_id))->find();
        return $order_item['quantity'];
    }
}