<?php
/**
 * 订单接口
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */
namespace Product\Controller;
use Common\Controller\HomebaseController;
class ApiController extends HomebaseController {
    /**
     * 某个部门下所有产品
     */
    public function index(){
        try{
            $domain_url = strip_tags($_GET['domain']);
            if($domain_url){
                /** @var \Domain\Model\DomainModel $domain_model */
                $domain_model            = D('Domain/Domain');
                $domain                  = $domain_model->get_domain($domain_url);

                if(!$domain['id_domain']) {
                    $returnData = array(
                        'status'=>false,
                        'message'=>'域名不存在'
                    );
                    exit(json_encode($returnData));
                }
                $id_department   = $domain['id_department'];

                $where = array('status'=>1,'id_department'=>$id_department);
                $count = D("Common/Product")
                    ->where($where)->cache(true, 18600)
                    ->count();
                $page_size = 300;
                $page = $this->page($count, $page_size);
                $product   = array();
                $pro_list = D("Common/Product")->where($where)
                    ->order("id_product DESC")->limit($page->firstRow , $page->listRows)->cache(true, 18600)
                    ->select();
                /** @var \Product\Model\ProductOptionModel $product_option */
                $product_option = D('Product/ProductOption');
                if($pro_list){
                    foreach($pro_list as $key=>$item){
                        $pro_list[$key]['attr_list'] = $product_option->get_attr_list_by_id($item['id_product']);
                    }
                }
                $page_count = ceil($count/$page_size);
            }

            $status =true;$message= '';
        }catch (\Exception $e){
            $status= false;$message= $e->getMessage();
        }
        $data = array(
            'status'=> $status,
            'message'=> $message,
            'page_size'=> $page_count,
            'count'=> $count,
            'data'=> $pro_list,
        );
        echo json_encode($data);exit();
    }
    public function get(){
        try{
            $product_id = (int)$_GET['id'];
            $product   = D("Common/Product")->where(array('id_product'=>$product_id))->find();
            if($product){
                $product['id'] = $product['id_product'];
                $product['purchase_price'] = $product['special_price'];
                $product['price'] = $product['sale_price'];
                $product['type'] = 'simple';
                $product['bundle'] = '';
                unset($product['id_product'],$product['sale_price']);
                /** @var \Product\Model\ProductOptionModel $attr_model */
                $attr_model = D("Product/ProductOption");
                $attr_list  = $attr_model->get_attr_list_by_id($product['id']);
                if($attr_list){
                    foreach($attr_list as $key=>$option){
                        $attr_list[$key]['id'] =  $option['id_product_option'];
                        $option_value  = $option['option_values'];
                        if($option_value){
                            foreach($option_value as $k=>$v){
                                $option_value[$k]['id'] = $v['id_product_option_value'];
                                unset($option_value[$k]['id_product_option_value']);
                            }
                        }
                        $attr_list[$key]['option_value'] = $option_value;
                        unset($attr_list[$key]['option_values'],$attr_list[$key]['id_product_option']);
                    }
                }
                $product['product_attr'] = $attr_list;
            }
            $status =true;$message= '';
        }catch (\Exception $e){
            $status= false;$message= $e->getMessage();
        }
        echo json_encode(array('status'=>$status,'message'=>$message,'product'=>$product));
        exit();
    }

    /**
     * 更新产品SKU状态
     * 前端模板需要设置某些产品SKU无用的。
     */
    public function update_sku_status(){
        set_time_limit(0);
        $product_id = $_GET['id']?explode(',',$_GET['id']):false;
        $update_status = isset($_GET['action']) && $_GET['action']=='status'?0:1;//设置状态
        if($product_id){
            $all_sku = D("Common/ProductSku")->where(array('id_product'=>array('IN',$product_id)))->select();
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
                        D("Common/ProductSku")->where(array('id_product_sku'=>$id_product_sku))->save(array('status'=>$update_status));
                        $this->add_warehouse_qty($sku);
                    }
                }else{
                    //先添加产品没有设置属性，后面再设置属性，所以需要再查下一次
                    $other = D("Common/ProductSku")->where(array('id_product'=>$sku['id_product']))->count();
                    if($other>1){
                        D("Common/ProductSku")->where(array('id_product_sku'=>$sku['id_product_sku']))->save(array('status'=>0));
                    }else{
                        D("Common/ProductSku")->where(array('id_product_sku'=>$sku['id_product_sku']))->save(array('status'=>$update_status));
                        $this->add_warehouse_qty($sku);
                    }
                }
            }
        }
    }
    /**
     * 上面 update_sku_status 更改产品SKU状态 使用到
     * 添加到仓库
     * @param $sku
     */
    protected  function add_warehouse_qty($sku){
        if($sku['id_product'] && $sku['id_product_sku']){
            $warehouse_product = D('Common/WarehouseProduct');
            $all_warehouse     = D('Common/Warehouse')->field('id_warehouse')->select();
            $all_warehouse     = array_column($all_warehouse,'id_warehouse');
            foreach($all_warehouse as $ware_id){
                $add_ware = array(
                    'id_warehouse' => $ware_id,
                    'id_product' => $sku['id_product'],
                    'id_product_sku' => $sku['id_product_sku']
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