<?php
/**
 * 产品模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Product\Controller
 */
namespace Product\Controller;
use Common\Controller\HomebaseController;

class SyncController extends HomebaseController{
	protected $product,$api_url;
	public function _initialize() {
		parent::_initialize();
		$this->product = D("Common/Product");
        $this->api_url = 'http://www.erp.com/product/all/';
	}
    public function update(){
        set_time_limit(0);
        if(isset($_GET['action'])){
            switch($_GET['action']){
                case 'rloxw':
                    $config = array('url'=>'http://www.rloxw.com/','id_department'=>1);
                    break;
                case 'tjihg':
                    $config = array('url'=>'http://www.tjihg.com/','id_department'=>2);
                    break;
                case 'wfpil':
                    $config = array('url'=>'http://www.wfpil.com/','id_department'=>3);
                    break;
                case 'vvcxy':
                    $config = array('url'=>'http://www.vvcxy.com/','id_department'=>4);
                    break;
                case 'ndrow':
                    $config = array('url'=>'http://www.ndrow.com/','id_department'=>5);
                    break;
                case 'diyibaoji':
                    $config = array('url'=>'http://www.diyibaoji.com/','id_department'=>6);
                    break;
                case 'pyjuu':
                    $config = array('url'=>'http://www.pyjuu.com/','id_department'=>7);
                    break;
            }
        }
        echo $_GET['action'].'<br />';
        if($config){
            $this->sync_domain($config);
            $product_config = array('url'=>$config['url'].'api/Sync/product_list','id_department'=>$config['id_department']);
            $this->request_data($product_config);
            echo 'OK';
            exit();
        }
    }
    /**
     * 同步旧的产品数据到新的ERP
     */
    public function index(){
        set_time_limit(0);
        $all_data = array(
            array('url'=>'http://www.rloxw.com/api/Sync/product_list','id_department'=>1),
            //array('url'=>'http://www.tjihg.com/api/Sync/product_list','id_department'=>2),
            //array('url'=>'http://www.wfpil.com/api/Sync/product_list','id_department'=>3),
            //array('url'=>'http://www.vvcxy.com/api/Sync/product_list','id_department'=>4),
            //array('url'=>'http://www.ndrow.com/api/Sync/product_list','id_department'=>5),
            //array('url'=>'http://www.pyjuu.com/api/Sync/product_list','id_department'=>7),
        );
        foreach($all_data as $item){
            $this->request_data($item);
        }
        //$data = array('url'=>'http://www.erp.com/product/all/','id_department'=>1);
        //$this->request_data($data);//多个组循环执行
        echo 'OK';
        exit();
    }

    /**
     * 添加域名到新的erp
     * @param $list
     * @param $id_department
     */
    protected function add_domain($list,$id_department){
        if($list && is_array($list)){
            foreach($list as $item){
                $add_data = array(
                    'id_department' => $id_department,
                    'name' => $item['name'],
                    'ip' => $item['ip'],
                    'copy_url' => $item['copy_url'],
                    'smtp_host' => $item['smtp_host'],
                    'smtp_user' => $item['smtp_user'],
                    'smtp_pwd' => $item['smtp_pwd'],
                    'smtp_port' => $item['smtp_port'],
                    'smtp_ssl' => $item['smtp_ssl'],
                    'status' => $item['status'],
                    'created_at' => date('Y-m-d H:i:s',$item['date_add']),
                    'updated_at' => $item['name'],
                    'updated_at' => date('Y-m-d H:i:s')
                );
                $select =  D("Common/Domain")->where(array('name'=>$item['name']))->find();
                if(!$select){
                    D("Common/Domain")->data($add_data)->add();
                }
            }
        }
    }
    /**
     * 同步域名
     * @param $config
     */
    protected function sync_domain($config){
        $url = $config['url'].'api/Sync/domain_list';
        $id_department = $config['id_department'];
        $get_json = file_get_contents($url);
        $data = $get_json?json_decode($get_json,true):'';
        $page_size = $data['page_size'];

        if($page_size>0){
            for($page=1;$page<=$page_size;$page++){
                $new_url = $url.'?p='.$page;
                echo $new_url.'<br />';
                $get_json = file_get_contents($new_url);
                $data = $get_json?json_decode($get_json,true):'';
                $list = isset($data['status'])&&$data['status']?$data['list']:'';
                $this->add_domain($list,$id_department);
            }
        }
        echo 'Add Domain complete<br /><br />'.PHP_EOL;
    }

    protected function request_data($url_data){
        $url = $url_data['url'];
        $id_department = $url_data['id_department'];
        $get_json = file_get_contents($url);
        $data = $get_json?json_decode($get_json,true):'';
        $products = isset($data['status'])&&$data['status']?$data['product']:'';
        $page_size = $data['page_size'];
        $this->add_products($products,$id_department);
        if($page_size>1){
            for($page=2;$page<=$page_size;$page++){
                $new_url = $url.'?p='.$page;
                echo $new_url.'<br /><br />';
                $get_json = file_get_contents($new_url);
                $data = $get_json?json_decode($get_json,true):'';
                $products = isset($data['status'])&&$data['status']?$data['product']:'';
                $this->add_products($products,$id_department);
            }
        }
    }
    protected function add_products($products,$id_department){
        /** @var \Product\Model\ProductOptionModel $attr_model */
        $attr_model = D('Product/ProductOption');
        if($products){
            foreach($products as $key=>$product){
                $product_data = array(
                    'id_department'=> $id_department,
                    'id_users'=> 1,
                    'title'=> $product['title'],
                    'inner_name'=> $product['inner_name'],
                    'id_category'=> $product['category_id'],
                    'model'=> $product['sku'],
                    'thumbs'=> json_encode($product['smeta']),
                    'purchase_price'=> $product['special_price'],
                    'sale_price'=> $product['price'],
                    'quantity'=> $product['qty'],
                    'special_from_date'=> $product['special_from_date'],
                    'special_to_date'=> $product['special_to_date'],
                    'length'=> $product['length'],
                    'width'=> $product['width'],
                    'height'=> $product['height'],
                    'weight'=> $product['weight'],
                    'status'=> $product['status'],
                    'desc'=> $product['description'],
                    'created_at'=> $product['created_at'],
                    'updated_at'=> $product['updated_at'],
                );
                $find_model = D('Product/Product')->where(array('model'=>array('EQ',$product['sku'])))->find();
                if(!$find_model){
                    $new_product_id = D('Product/Product')->data($product_data)->add();
                    $temp_data = array('id_department'=>$id_department,'product_id'=>$product['id'],'new_product_id'=>$new_product_id);
                    D('Common/TempProduct')->data($temp_data)->add();
                }else{
                    $new_product_id = $find_model['id_product'];
                }
                $product_attr   = $product['product_attr'];
                $parameter = array('product_id'=>$product['id'],'new_product_id'=>$new_product_id,'id_department'=>$id_department);
                $this->add_option($parameter,$product_attr);
                /** @var \Product\Model\ProductOptionModel $attr_model */
                $attr_model = D('Product/ProductOption');
                $attr_model->reset_sku_list($new_product_id,$product['sku']);
            }

        }else{
            $this->error('没有产品数据');
        }
    }
    protected function add_option($parameter,$data){
        $product_id = $parameter['product_id'];
        $new_product_id = $parameter['new_product_id'];
        $id_department = $parameter['id_department'];
        if($product_id && $data){
            $temp_option = D('Common/TempOption');
            foreach($data as $item){
                $option_id = $item['id'];
                $option_array = array(
                    'id_product'=> $new_product_id,
                    'title'=> $item['title'],
                    'type'=> $item['type'],
                    'required'=> $item['is_require'],
                    'remark'=> $item['remark'],
                    'sort'=> $item['sort'],
                );
                $temp_opt_where = array('id_department'=>$id_department,'option_id'=>$option_id,'product_id'=>$product_id);
                $option = $temp_option->where($temp_opt_where)->find();
                if($option && $option['new_option_id']){//属性分类已经写入到数据库
                    $new_option_id = $option['new_option_id'];
                }else{
                    $new_option_id = D('Product/ProductOption')->data($option_array)->add();
                    $temp_data     = array(
                        'id_department'=> $id_department,
                        'option_id'=> $option_id,
                        'new_option_id'=> $new_option_id,
                        'product_id'=> $product_id,
                    );
                    $temp_option->data($temp_data)->add();
                }
                $option_value = $item['option_value'];
                if($option_value){
                    foreach($option_value as $value){
                        $value_id      = $value['id'];
                        $old_option_id = $value['option_id'];
                        $sku_id        = $value['sku_id'];

                        $value_where = array(
                            'id_department'=> $id_department,
                            'product_id'=> $product_id,
                            'option_id'=> $option_id,
                            'value_id'=> $value_id,
                        );
                        $select_value = D('Common/TempOptionValue')->where($value_where)->find();
                        $new_value_id = $select_value?$select_value['new_value_id']:false;
                        if(!$new_value_id){
                            $value_data    = array(
                                'id_product_option'=> $new_option_id,
                                'id_product' => $new_product_id,
                                'title' => $value['title'],
                                'price' => $value['price'],
                                'code' => $value['code'],
                                'image' => $value['file_extension'],
                            );
                            $new_value_id = D('Product/ProductOptionValue')->data($value_data)->add();
                            $value_where['new_value_id'] = $new_value_id;
                            $value_where['sku_id'] = $sku_id;//print_r($value_where);echo '<br /><br />';
                            D('Common/TempOptionValue')->data($value_where)->add();
                        }
                    }
                }
            }

        }
    }
}