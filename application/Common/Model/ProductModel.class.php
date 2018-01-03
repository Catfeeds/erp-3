<?php
namespace Common\Model;
use Common\Model\CommonModel;
class ProductModel extends CommonModel{
	protected function _before_write(&$data) {
		parent::_before_write($data);
	}
    public function getBundle($productId){
        $model = new \Think\Model;
        $bundleName  = D("Common/ProductBundle")->getTableName();
        $proName  = D("Common/Product")->getTableName();
        //$getBundle = D('Common/ProductBundle')->where('id='.$productId)->select();
        $getBundle = $model->table($proName.' pro,'.$bundleName.' as b')->field('b.*,pro.title as product_title,pro.sku')
            ->where('pro.id=b.product_id and b.parent_product_id='.$productId)->select();

        return $getBundle;
    }
    public function addBundleProduct($parProId){
        if(isset($_POST['bundle']) && $_POST['bundle']){
            $bundle = $_POST['bundle'];
            foreach($bundle as $key=>$item){
                /*$productId = (int)$item['product_id'];
                if($productId && $productId !=$parProId){
                    $where = array('parent_product_id'=>array('EQ',$parProId),'product_id'=>array('EQ',$productId));
                    $loadBundle = D('Common/ProductBundle')->field('id')->where($where)->find();
                    $item['parent_product_id'] = $parProId;
                    if(isset($loadBundle['id'])){
                        D('Common/ProductBundle')->where('id='.$loadBundle['id'])->save($item);
                    }else{
                        D('Common/ProductBundle')->data($item)->add();
                    }
                }*/
                $item['parent_product_id'] = $parProId;
                $item['product_id']        = 0;
                $item['number'] = $item['number']?$item['number']:1;
                if($item['title'] && !isset($item['id'])){
                    D('Common/ProductBundle')->data($item)->add();
                }else{
                    $bundleId = $item['id'];
                    unset($item['parent_product_id'],$item['id'],$item['product_id']);
                    D('Common/ProductBundle')->where('id='.$bundleId)->save($item);
                }
            }
        }
    }

    /**
     * 添加或编辑产品，保存产品信息
     * @return array
     */
    public function saveProductData(){
        $getPost = $_POST['post'];
        $getSku = $getPost['sku'];
        $attrValueIdArray = $_POST['attr_value_id'];
        /* @var $valueModel \Common\Model\ProductOptionValueModel*/
        $valueModel = D("Common/ProductOptionValue");
        $proSkuModel = D("Common/ProductSku");
        $prodAttrModel = D("Common/ProductAttr");
        $postFlip = $valueModel->arrayFlipValue($attrValueIdArray);//用于保存attr id到 product_attr表 取出

        if(!empty($_POST['photos_alt']) && !empty($_POST['photos_url'])){
            foreach ($_POST['photos_url'] as $key=>$url){
                $photourl=sp_asset_relative_url($url);
                $_POST['smeta']['photo'][]=array("url"=>$photourl,"alt"=>$_POST['photos_alt'][$key]);
            }
        }
        $_POST['smeta']['thumb'] = sp_asset_relative_url($_POST['smeta']['thumb']);
        $getPost['smeta']=json_encode($_POST['smeta']);
        $getPost['created_at'] = date('Y-m-d H:i:s');
        $getPost['length'] = 0;$getPost['width'] = 0;$getPost['height'] = 0;$getPost['weight'] = 0;
        $getPost['shipping_id'] = 0;
        /*$tempValArr = array();$getCombinations =false;
        if($attrValueIdArray){
            foreach($attrValueIdArray as $getKey=>$postAttr){
                $tempValArr[$getKey] = $valueModel->where(array('option_id'=>$getKey))->getField('id',true);
            }
            $getCombinations = $tempValArr?$valueModel->generateCombinations($tempValArr):false;//数组进行组合，添加到产品属性表
            $getDbFlip  = $valueModel->arrayFlipValue($tempValArr);
        }*/

        if(isset($getPost['id']) && $getPost['id']){
            $productId = (int)$getPost['id'];unset($getPost['id']);
            $getPost['updated_at'] = date('Y-m-d H:i:s');
            D("Common/Product")->where('id='.$productId)->save($getPost);
            //$this->addBundleProduct($productId);//添加套餐产品
            //return array('status'=>1,'message'=>'');
        }else{
            $productId = D("Common/Product")->data($getPost)->add();
        }
        $getCombinations = $valueModel->generateCombinations($attrValueIdArray);
        try{
            if(is_array($getCombinations)){
                $tempSkuData = array();
                foreach($getCombinations as $key=>$item){
                    $tempItem = $item;
                    sort($item);
                    $getAttrString = implode('#',$item);$tempValueID = implode(',',$item);
                    $getChildSku   = $getSku.'#'.$getAttrString.'#';
                    $childSku = $proSkuModel->field('id')->where(array('sku'=>array('EQ',$getChildSku)))->getField(true);
                    if(!$childSku){//查找子SKU是否存在
                        $tempModel = array();
                        if(is_array($tempItem)){//处理产品属性code建立子产品model。
                            foreach($tempItem as $aaa){
                                $reCode = $valueModel->field('code')->cache(true,60)->find($aaa);
                                $tempModel[] =$reCode['code'];
                            }
                        }
                        $tempMM = count($tempModel)?$getSku.'-'.implode('-',$tempModel):$getSku;

                        $childSkuData = array('product_id'=>$productId,'sku'=>$getChildSku,'model'=>$tempMM,'option_value'=>$tempValueID);
                        $cSkuId = $proSkuModel->data($childSkuData)->add();//添加子 sku
                        foreach($item as $vId){
                            $optionId  = $postFlip[$vId];
                            $where = array('sku_id'=>array('EQ',$cSkuId),'option_id'=>array('EQ',$optionId),'option_value_id'=>array('EQ',$vId));
                            $selectAttrId = $prodAttrModel->where($where)->find();//查询子SKU 属性 关联
                            if(!$selectAttrId){
                                $status = 1;
                                $addAttrData = array('sku_id'=>$cSkuId,'option_id'=>$optionId,'option_value_id'=>$vId,'status'=>$status);
                                $prodAttrModel->data($addAttrData)->add();//添加子SKU 属性 关联
                            }
                        }
                        $tempSkuData[] = $cSkuId;
                    }else{
                        $tempSkuData[] = $childSku;
                    }
                }

                $selectAllSku = $proSkuModel->where(array('product_id'=>$productId))->getField('id',true);
                if($selectAllSku){
                    foreach($selectAllSku as $SKUID){
                        if(!in_array($SKUID,$tempSkuData)){
                            $prodAttrModel->where('sku_id='.$SKUID)->delete();
                            $proSkuModel->where('id='.$SKUID)->delete();
                        }
                    }
                }
            }
            $this->addBundleProduct($productId);//添加套餐产品
            $status = true;$message = '';
        }catch (\Exception $e){
            $status = true;$message = $e->getMessage();
        }
        return array('status'=>$status,'message'=>$message);
    }

    /**
     * 获取产品属性
     * @user application\Portal\Controller\ProductController.class.php
     * @user application\Admin\Controller\ProductController.class.php
     * @param $productId 产品ID
     * @param bool $showDisabled  //是否输出禁用的属性
     * @return array
     */
    public function getSelfAttr($productId,$showDisabled=true){
        $productId = (int)$productId;
        $valueModel = D("Common/ProductOptionValue");
        $proSkuModel = D("Common/ProductSku");
        $prodAttrModel = D("Common/ProductAttr");
        $prodOptModel = D("Common/ProductOption");
        $prodOptValModel = D("Common/ProductOptionValue");
        $findSkuId = $proSkuModel->where('product_id='.$productId)->getField('id',true);
        $tempData = array();
        if($findSkuId){
            $selectOption = $prodAttrModel->where(array('sku_id'=>array('in',$findSkuId)));
            $optionId = $selectOption->group('option_id')->getField('option_id',true);
            if($optionId){
                foreach($optionId as $item){
                    $optionData = $prodOptModel->find($item);
                    if($showDisabled){//输出禁用的产品，给后台编辑产品使用
                        $valWhere = array('sku_id'=>array('in',$findSkuId),'option_id'=>array('EQ',$item));
                    }else{//不输出禁用的属性
                        $valWhere = array('sku_id'=>array('in',$findSkuId),'option_id'=>array('EQ',$item),'status'=>1);
                    }
                    $getValueArr = $prodAttrModel->where($valWhere)->group('option_value_id')->select();
                    $tempValArr  =  array();$tempValSku = array();
                    if($getValueArr && is_array($getValueArr)){//此次循环，是为了记录某个属性是否禁用或开启的状态
                        foreach($getValueArr as $tempVal){
                            $tempValArr[$tempVal['option_value_id']] =  $tempVal['status'];
                            $tempValSku[$tempVal['option_value_id']] =  $tempVal['sku_id'];
                        }
                    }
                    //$getValueArr = $prodAttrModel->where($valWhere)->group('option_value_id')->getField('option_value_id',true);
                    //$getValueArr = count($getValueArr)?$getValueArr:array();print_r($prodAttrModel->where($valWhere)->group('option_value_id')->select());
                    $optionValData = $prodOptValModel->where('option_id='.$item)->select();
                    if($optionValData){
                        foreach($optionValData as $key=>$val){
                            if($tempValArr[$val['id']]==0 && !$showDisabled){//不输出禁用的属性
                                unset($optionValData[$key]);
                            }else{
                                //$optionValData[$key]['flag'] = in_array($val['id'],$getValueArr)?1:0;
                                $optionValData[$key]['flag'] = $tempValArr[$val['id']]?1:0;
                                $optionValData[$key]['sku_id'] = $tempValSku[$val['id']];
                            }
                        }
                    }
                    $optionData['option_value'] = $optionValData;
                    $tempData[] = $optionData;
                }
            }
        }
        return $tempData;
    }
    public function getSkuAttrList($productId=0){
        $allChildSku = D("Common/ProductSku")->where('product_id='.$productId)->select();
        $prodOptValModel = D("Common/ProductOptionValue");
        $productRow = '<tr class="headings"><th>Model</th><th>属性</th><th>采购数</th><th>采购单价</th></tr>';
        $attrModel = D("Common/ProductAttr");
        foreach($allChildSku as $cKey=>$cItem){//子SKU数据
            //if($cItem['status']==1){
            $optionVal  = explode(',',$cItem['option_value']);
            $getStatus = true;
            if($optionVal){
                $setWhere =array('sku_id'=>$cItem['id'],'option_value_id'=>array('in',$optionVal));
                $getStatus = $attrModel->where($setWhere)->cache(true,8600)->getField('status',true);
                $getStatus = in_array(0,$getStatus)?0:1;
            }
            if($getStatus){
                $valWhere = array('id'=>array('in',$optionVal),'sku_id'=>array('EQ',$cItem['id']));
                $optSelect  = $prodOptValModel->where($valWhere)->cache(true,8600)->select();
                $productAttr = array();$flag = true;
                if($optSelect){
                    foreach($optSelect as $vItem){
                        $productAttr[] =  $vItem['title'];
                    }
                }
                $proAttrStr = $productAttr?implode(',',$productAttr):'';
                $productRow .='<tr><td>'.$cItem['model'].'</td><td>'.
                    $proAttrStr.'<input type="hidden" value="'.$proAttrStr.'" name="attr_name['.$cItem['id'].']"/>'.
                    '</td><td><input type="text" value="" name="set_qty['.$cItem['id'].']"/></td>
                    <td><input type="text" value="" name="set_price['.$cItem['id'].']"/></td></tr>';
            }

            //}
        }
        return $productRow;
    }

    /**
     * 目前形势，我们的产品不多,零时用到订单里显示产品
     */
    public function getAllProduct(){
        $cache = S('getAllProduct');
        if($cache){
            $productList = unserialize($cache);
        }else{
            $productList = array();
            $product = D("Common/Product")->field('id_product,title')->where('status=1')->select();
            if($product){
                foreach($product as $item){
                    $productList[$item['id_product']] = $item['title'];
                }
            }
            S('getAllProduct',serialize($productList),array('type'=>'file','expire'=>600));
        }
        return $productList;
    }
    public function getProperty($product_id)
    {
        $cache = null;//S('PRODUCT:PROPERTY'.$product_id);
        if ($cache) {
            return $cache;
        }
        $attr = D('Common/ProductAttr');
        $sku = D('Common/ProductSku');
        $option = D('Common/ProductOption');
        $values = D('Common/ProductOptionValue');
        $property = $attr
            ->field('DISTINCT v.id, o.id AS option_id, v.id AS option_value_id, o.title AS option_name,v.title AS option_value ')
            ->join('__PRODUCT_SKU__ ps ON (ps.status=1 AND ps.id=__PRODUCT_ATTR__.sku_id)')
            ->join('__PRODUCT_OPTION__ o ON (__PRODUCT_ATTR__.option_id=o.id)')
            ->join('__PRODUCT_OPTION_VALUE__ v ON (__PRODUCT_ATTR__.option_value_id=v.id)')
            ->where(array(
                $attr->getTableName().'.status' => 1,
                'ps.product_id' => $product_id
            ))
            ->select();
        S('PRODUCT:PROPERTY'.$product_id, $property);
        
        return $property;
    }
}

