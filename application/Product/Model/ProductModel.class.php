<?php
namespace Product\Model;
use Common\Model\CommonModel;
class ProductModel extends CommonModel {
    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('id_department', 'require', '部门不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        array('title', 'require', '标题不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        array('model', 'require', 'model不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    );
	protected $_auto = array (
		array ('post_date', 'mGetDate', self::MODEL_INSERT, 'callback' ),
		array ('post_modified', 'mGetDate',self::MODEL_BOTH, 'callback' )
	);
	protected function _before_write(&$data) {
		parent::_before_write($data);
	}

    /**
     * 保存数据到产品表
     * @param string $parameter
     * @return array
     */
    public function write_to_table($parameter=''){
        $pattern = '/[^\x00-\x80]/';//匹配中文正则
        try{
            $pro_data        = $_POST['post'];//I('post');
            if(!empty($_POST['photos_alt']) && !empty($_POST['photos_url'])){
                foreach ($_POST['photos_url'] as $key=>$url){
                    $photourl= sp_asset_relative_url($url);
                    $_POST['smeta']['photo'][]=array("url"=>$photourl,"alt"=>$_POST['photos_alt'][$key]);
                }
            }
            $_POST['smeta']['thumb'] = sp_asset_relative_url($_POST['smeta']['thumb']);
            $pro_data['thumbs'] = json_encode($_POST['smeta']);
            $pro_data['id_users'] = $_SESSION['ADMIN_ID'];
            $pro_data['status']   = (int)$pro_data['status'];
            if(isset($pro_data['id']) && $pro_data['id']){
                $product_id = (int)$pro_data['id'];unset($pro_data['id']);
                $pro_data['updated_at'] = date('Y-m-d H:i:s');
                $this->where(array('id_product'=>$product_id))->save($pro_data);
                $load_product = $this->find($product_id);
                $pro_data['model'] = $load_product['model'];
            }else{
                $pro_data['length'] = 0;
                $pro_data['width']  = 0;
                $pro_data['height'] = 0;
                $pro_data['weight'] = 0;
                $pro_data['shipping_id'] = 0;
                $pro_data['purchase_price'] = 0;
                $pro_data['created_at'] = date('Y-m-d H:i:s');
                if(!preg_match($pattern,$pro_data['inner_name']) || strlen($pro_data['inner_name']) > 40 ){
                    return array('status'=>0,'message'=>'内部名格式不正确');
                }
                if (!trim($pro_data['inner_name'])) {
                    return array('status'=>0,'message'=>'内部名不能为空');
                }
                if($pro_data['id_department']<1){
                    return array('status'=>0,'message'=>'部门错误,请选择部门');
                }
                //SELECT * FROM `erp_product` WHERE `id_department`=7 order by `model` desc
				$get_config_department = C('GET_DEPARTMENT_ID');
                $get_ini_department = $pro_data['id_department'];

                $select_where_sku = 'XG'.$get_ini_department;    

                $select_max_sku = D("Common/Product")->field('model')->where(array('id_department'=>$pro_data['id_department'],'model'=>['like',$select_where_sku.'%']))->order('`model` desc')->find();
                if($select_max_sku){
                    $select_max_sku = (int)str_replace('XG','',$select_max_sku['model'])+1;
                }else{
                    $get_config_department = C('GET_DEPARTMENT_ID');
                    $get_ini_department = $pro_data['id_department'];
                    if(!$get_ini_department){
                        return array('status'=>0,'message'=>'没有找到部门对应的ID');
                    }
                    $select_max_sku = $get_ini_department.'0001';
                }
 
                $pro_data['model'] = 'XG'.$select_max_sku;
                
                $find_model = $this->where("model='".$pro_data['model']."' or inner_name='".$pro_data['inner_name']."'")->find();
       
                //重复了就再生成一个
                if($find_model) {
                    $select_max_sku = $select_max_sku + 666;
   
                    $pro_data['model'] = 'XG' . $select_max_sku;
                    
                    $find_model = $this->where("model='" . $pro_data['model'] . "' or inner_name='" . $pro_data['inner_name'] . "'")->find();
                    if($find_model) {
                        return array('status'=>0,'message'=>'SKU或内部名已经存在('.$pro_data['model'].')');
                    }else{
                        $product_id = $this->data($pro_data)->add();
                    }
                }else{
                    $product_id = $this->data($pro_data)->add();
                }
            }

            $attr_data       = I('attr');
            $attr_value_data = I('attr_value');
            /** @var \Product\Model\ProductOptionModel $attr_model */
            $attr_model = D('Product/ProductOption');
            //数组添加部门ID-id_department 关联产品SKU的部门   liuruibin 20171017
            $option_array = array('product_id'=> $product_id,'model'=>$pro_data['model'],'option'=> $attr_data,'option_value'=> $attr_value_data,'id_department' => $pro_data['id_department']);
            $opt_result = $attr_model->write_to_table($option_array);
            $result  = array('status'=>1,'message'=>'');
            //开启或关闭产品状态
            if($product_id){
                $this->on_off_status($product_id,$pro_data['status']);
            }
        }catch ( \Exception $e){
            $result = array('status'=>0,'message'=>$e->getMessage());
        }
        return $result;
    }

    /**
     * 开启或关闭产品
     * @param $product_id
     * @param int $status
     */
    public function on_off_status($product_id,$status=0){
        $all_sku = D("Common/ProductSku")->where(array('id_product'=>$product_id,'status'=>1))->select();
        $opt_val_model = D("Common/ProductOptionValue");

        foreach($all_sku as $sku){
            $id_product_sku  = $sku['id_product_sku'];
            if($sku['option_value']!=0){
                $implode         = $sku['option_value']?explode(',',$sku['option_value']):array(0);
                $count_current   = count($implode);
                $where           = array('id_product'=>$sku['id_product'],'id_product_option_value'=>array('IN',$implode));
                $get_value_count = $opt_val_model->where($where)->count();

                if($count_current!=$get_value_count){
                    D("Common/ProductSku")->where(array('id_product_sku'=>$id_product_sku))->save(array('status'=>0));
                }else{
                    D("Common/ProductSku")->where(array('id_product_sku'=>$id_product_sku))->save(array('status'=>$status));
                }
            }else{
                //先添加产品没有设置属性，后面再设置属性，所以需要再查下一次
                $other = D("Common/ProductSku")->where(array('id_product'=>$sku['id_product'],'status'=>1))->count();
                if($other>1){
                    D("Common/ProductSku")->where(array('id_product_sku'=>$sku['id_product_sku']))->save(array('status'=>0));
                }else{
                    D("Common/ProductSku")->where(array('id_product_sku'=>$sku['id_product_sku']))->save(array('status'=>$status));
                }
            }
        }
    }
}
