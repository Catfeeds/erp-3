<?php
namespace Product\Model;
use Common\Model\CommonModel;
class ProductOptionModel extends CommonModel {
    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
    );
	protected $_auto = array ();
	protected function _before_write(&$data) {
		parent::_before_write($data);
	}

    /**
     * 上传图片
     * @param $fileInfo
     * @return array
     */
    public function upload_image($fileInfo){
        $date=date("Ymd");
        $config=array(
            'rootPath' => './'. C("UPLOADPATH"),
            'savePath' => "$date/",
            'maxSize' => 10485760,//10M
            'saveName'   =>    array('uniqid',''),
            'exts'       =>    array('jpg','JPG','png','gif','jpeg'),
            'autoSub'    =>    false,
        );
        $upload = new \Think\Upload($config);
        $info=$upload->upload($fileInfo);
        if ($info) {;
            $newFileName = $info['file']['savename'];
            $getPathFile = '/data/upload/'.$info['file']['savepath'].$newFileName;
            $status =1;
        }else{
            $status = 0;
        }
        return array('status'=>$status,'file_path'=>$getPathFile);
    }
    /**
     * Generate all the possible combinations among a set of nested arrays.
     *
     * @param array $data  The entrypoint array container.
     * @param array $all   The final container (used internally).
     * @param array $group The sub container (used internally).
     * @param mixed $val   The value to append (used internally).
     * @param int   $i     The key index (used internally).
     */
    public function generate_combinations(array $data, array &$all = array(), array $group = array(), $value = null, $i = 0){
        $keys = array_keys($data);
        if (isset($value) === true) {
            array_push($group, $value);
        }
        if ($i >= count($data)) {
            array_push($all, $group);
        } else {
            $currentKey     = $keys[$i];
            $currentElement = $data[$currentKey];
            foreach ($currentElement as $val) {
                $this->generate_combinations($data, $all, $group, $val, $i + 1);
            }
        }
        return $all;
    }

    /**
     * 写入到产品属性表
     * @param string $parameter
     * @return array
     */
    public function write_to_table($parameter=''){
        $product_id = $parameter['product_id'];
        $option = $parameter['option'];
        $option_value = $parameter['option_value'];
        if($option){
            foreach($option as $key=>$item){
                if($item){
                    if(preg_match('/[^\x00-\x80]/',$item['title'])) {
                        $option_id = isset($item['id_product_option']) ? (int)$item['id_product_option'] : 0;
                        if ($option_id) {
                            D('Product/ProductOption')->where('id_product_option=' . $option_id)->save($item);
                        } else {
                            $item['id_product'] = $product_id;
                            $item['type'] = 'drop_down';
                            $option_id = D('Product/ProductOption')->data($item)->add();
                            //$option_id =1;
                        }
                        if (isset($option_value[$key]) or count($option_value[$key])) {
                            $values = $option_value[$key];
                            $values['id_product'] = $product_id;
                            $values['id_product_option'] = $option_id;
                            $this->save_option_value($values, $key);
                        }
                    }
                }
            }
        }
        //新加一个关联产品SKU的部门 $parameter['id_department']    liuruibin   20171017
        $this->reset_sku_list($product_id,$parameter['model'],$parameter['id_department']);
        $this->set_product_sku_and_stock($product_id);//修改产品属性后，如果是删除产品属性，就重新赢藏不需要的SKU ID
        return array('status'=>1);
    }

    /**
     * 保存产品属性值
     * @param $values
     * @param $key
     * @return bool
     */
    public function save_option_value($values,$key){
        if(!count($values)) return false;
        if(isset($_FILES['attr_value'])){
            $attr_val = $_FILES['attr_value'];
            $names = $attr_val['name'][$key]['file_extension'];
            $type = $attr_val['type'][$key]['file_extension'];
            $temp_name = $attr_val['tmp_name'][$key]['file_extension'];
            $error = $attr_val['error'][$key]['file_extension'];
            $size = $attr_val['size'][$key]['file_extension'];
            if($names){
                foreach($names as $fileKey=>$name){
                    if($name){
                        $tempImage['file'] = array('name'=>$name,
                            'type'=>$type[$fileKey],'tmp_name'=>$temp_name[$fileKey],
                            'error'=>$error[$fileKey],'size'=>$size[$fileKey]);
                        $getFile = $this->upload_image($tempImage);
                        $values['image'][$fileKey] = $getFile['status']?$getFile['file_path']:'';
                    }
                }
            }
        }
        if(count($values)){
            $item = $values['title'];
            if($item){
                if(is_array($item)){
                    foreach($item as $iK=>$iV){
//                        if(preg_match('/[^\x00-\x80]/',$values['title'][$iK])) {
                            $option_value_id = isset($values['id_product_option_value'][$iK]) ? $values['id_product_option_value'][$iK] : 0;
                            $set_data = array(
                                'id_product_option' => $values['id_product_option'],
                                'id_product' => $values['id_product'],
                                'title' => $values['title'][$iK],
                                'price' => isset($values['price']) ? $values['price'][$iK] : 0,
                                'code' => $values['code'][$iK],
                                //'image'=>$values['image'][$iK],
                                'sort' => $values['sort'][$iK]);
                            if ($values['image'] && $values['image'][$iK]) {
                                $set_data['image'] = $values['image'][$iK];
                            }
                            if ($option_value_id) {
                                $where = 'id_product_option_value=' . $option_value_id;
                                unset($set_data['id_product_option_value'], $set_data['id_product_option'], $set_data['id_product']);
                                D('Product/ProductOptionValue')->where($where)->save($set_data);
                            } else {
                                D('Product/ProductOptionValue')->data($set_data)->add();
                            }
//                        }
                    }
                }
            }
        }
    }

    /**
     * 设置产品多余的SKU ID 和清除无用的库存。
     * @param $product_id
     */
    public function set_product_sku_and_stock($product_id){
        $all_sku       = D("Common/ProductSku")->where(array('id_product'=>$product_id))->select();
        $opt_val_model = D("Common/ProductOptionValue");
        if($all_sku){
            foreach($all_sku as $sku){
                if($sku['option_value']!=0){
                    $implode         = $sku['option_value']?explode(',',$sku['option_value']):array(0);
                    $count_current   = count($implode);
                    $where           = array('id_product'=>$sku['id_product'],'id_product_option_value'=>array('IN',$implode));
                    $get_value_count = $opt_val_model->where($where)->count();
                    if($count_current!=$get_value_count){
                        $id_product_sku = $sku['id_product_sku'];
                        D("Common/ProductSku")->where(array('id_product_sku'=>$id_product_sku))->save(array('status'=>0));
                    }
                }else{
                    //先添加产品没有设置属性，后面再设置属性，所以需要再查下一次
                    $other = D("Common/ProductSku")->where(array('id_product'=>$sku['id_product']))->count();
                    if($other>1){
                        D("Common/ProductSku")->where(array('id_product_sku'=>$sku['id_product_sku']))->save(array('status'=>0));
                    }
                }
            }
        }
        //如果没有属性，设置最初的为开启
        $value_where           = array('id_product'=>$product_id);
        $value_count = $opt_val_model->where($value_where)->count();
        if($value_count==0){
            D("Common/ProductSku")->where(array('id_product'=>$product_id,'option_value'=>0))->save(array('status'=>1));
        }
    }
    /**
     * 获取产品属性
     * @param $product_id
     * @return mixed
     */
    public function get_attr_list_by_id($product_id){
        /** @var \Product\Model\ProductOptionModel $attr_model */
        $attr_data = $this->where(array('id_product'=>$product_id))->order('sort desc')->select();
        /** @var  \Product\Model\ProductOptionValueModel $option_value */
        $option_value = M('ProductOptionValue');
        foreach($attr_data as $key=>$item){
            $id_product_option = $item['id_product_option'];
            $field = 'id_product_option_value,title,price,code,image,sort';
            $where = array('id_product_option'=>$id_product_option,'id_product'=>$product_id);
            $get_value = $option_value->field($field)->where($where)->order('sort desc')->select();
            $attr_data[$key]['option_values'] =$get_value;
        }
        return $attr_data;
    }

    /**
     * 设置产品SKU
     * @param $product_id
     * @param $model
     * @param $department_id 添加关联产品SKU的部门ID   liuruibin   20171017
     */
    public function reset_sku_list($product_id,$model,$department_id=null){
        /** @var  \Product\Model\ProductOptionValueModel $option_value */
        $option_value = D('Product/ProductOptionValue');
        /** @var  \Product\Model\ProductSkuModel $sku_model */
        $sku_model = D('Product/ProductSku');
        $get_insert_temp_id = array();
        $list = $option_value->where('id_product='.$product_id)->select();
        $option_set = array();$code_array = array();$title_array = array();
        foreach($list as $item){
            $option_id = $item['id_product_option'];
            $value_id  = $item['id_product_option_value'];
            $option_set[$option_id][] = $value_id;
            $code_array[$value_id] = $item['code'];
            $title_array[$value_id] = $item['title'];
        }
        if(count($option_set)){
            $getCombinations = $this->generate_combinations($option_set);
            foreach($getCombinations as $key=>$item){
                sort($item);
                $code_temp = array();
                $title_temp= array();
                foreach($item as $value_id){
                    $code_temp[]  = $code_array[$value_id];
                    $title_temp[] = $title_array[$value_id];
                }
                $sku_string  = $model.''.implode('',$item).'';
                $temp_value  = implode(',',$item);
                $temp_model  = $model.'-'.implode('-',$code_temp);
                $title       = implode('-',$title_temp);
                /*$get_sku_num = (int)str_replace('ST','',$model);
                $sku_len     = strlen($get_sku_num);
                $s_len       = 9-$sku_len;
                $time        = str_replace('.','',microtime(true));
                $barcode     = $get_sku_num.substr($time,-$s_len);*/

                $barcode = D("Common/TempBarcode")->data(array('sku_id'=>$product_id))->add();
//
//                $save_data   = array('id_product'=>$product_id,'title'=>$title,
//                    'sku'=>$sku_string,'model'=>$temp_model,'barcode'=>$barcode,
//                    'option_value'=>$temp_value,'status'=>1);
                $save_data   = array('id_product'=>$product_id,'title'=>$title,
                    'sku'=>$barcode,'model'=>$temp_model,'barcode'=>$barcode,
                    'option_value'=>$temp_value,'status'=>1,'id_department' => $department_id);
                $find_where = array('option_value'=>$temp_value,'id_product'=>$product_id,'status'=>1);
                $find       = $sku_model->where($find_where)->find();
                if($find && $find['id_product_sku']){
                    unset($save_data['sku'],$save_data['barcode']);
                    $sku_model->where('id_product_sku='.$find['id_product_sku'])->save($save_data);
                }else{
                    $get_insert_temp_id[] = $sku_model->data($save_data)->add();
                }
            }
        }else{
            $barcode = D("Common/TempBarcode")->data(array('sku_id'=>$product_id))->add();
            $save_data = array(
                'id_product'=>$product_id,
                'sku'=>$barcode,
                'model'=>$model,
                'option_value'=>0,
                'barcode'=>$barcode,
                'status'=>1,
                'id_department' => $department_id
            );
            $get_result = $sku_model->where("id_product=".$product_id." and status =1")->find();
            if(!$get_result)
            {
                $get_insert_temp_id[] = $sku_model->data($save_data)->add();
            }
        }
        //添加仓库库存
        if(count($get_insert_temp_id)>0){
            $warehouse  = D('Common/Warehouse')->where(array('status'=>1))->select();
            $warehouse_product = D('Common/WarehouseProduct');
            foreach($get_insert_temp_id as $get_insert_id){
                if($warehouse){
                    foreach($warehouse as $w){
                        $id_warehouse = $w['id_warehouse'];
                        $add_ware = array(
                            'id_warehouse' => $id_warehouse,
                            'id_product' => $product_id,
                            'id_product_sku' => $get_insert_id
                        );
                        $find_ware = $warehouse_product->where($add_ware)->find();
                        if(!$find_ware){
                            $add_ware['quantity'] = 0;
                            $add_ware['road_num'] = 0;
                            $warehouse_product->data($add_ware)->add();
                        }
                    }
                }else{
                    $add_ware = array(
                        'id_warehouse' => 1,
                        'id_product' => $product_id,
                        'id_product_sku' => $get_insert_id
                    );
                    $find_ware = $warehouse_product->where($add_ware)->find();
                    if(!$find_ware){
                        $add_ware['quantity'] = 0;
                        $add_ware['road_num'] = 0;
                        $warehouse_product->data($add_ware)->add();
                    }
                }
            }
        }
    }
}