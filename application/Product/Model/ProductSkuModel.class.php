<?php
namespace Product\Model;
use Common\Model\CommonModel;
class ProductSkuModel extends CommonModel {
    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
    );
	protected $_auto = array ();
	protected function _before_write(&$data) {
		parent::_before_write($data);
	}
    public function get_sku_id($product_id,$attr){
        if(isset($attr) && count($attr)){
            sort($attr);
            $get_attr_string = implode(',',$attr);
        }
        $where['id_product'] = $product_id;
        if($attr){
            //$where['option_value'] = $get_attr_string;
            //如果产品无属性 就传多属性过来 不受影响
            $where['_string'] = "(option_value='".$get_attr_string."' or option_value=0) and status=1";
        }
        $result = $this->where($where)->field('id_product_sku,model,sku,title')->order('id_product_sku desc')->find();
        return array('id'=>$result['id_product_sku'],'sku'=>$result['sku'],'title'=>$result['title']);
    }
}