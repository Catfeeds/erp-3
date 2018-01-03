<?php
/**
 * 运单模板
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Report\Controller
 */
namespace Waybill\Controller;
use Common\Controller\AdminbaseController;
class PrintController extends AdminbaseController{
	protected $Waybill;
	public function _initialize() {
		parent::_initialize();
		$this->Waybill=D("Common/WaybillTemplate");
	}
    public function set_page(){
        $wave_number = I('get.num');
        $template_id = I('get.template_id');
        if($wave_number){
            $page_size  = I('get.page_size')?I('get.page_size'):10;
            $order_tab  = M('Order')->getTableName();
            $ship_track_tab = M('ShippingTrack')->getTableName();
            $order_item_tab = M('OrderItem')->getTableName();
            $list_count = D('OrderWave')->alias('ow')->field('o.*,st.track_number,ow.area_code,ow.zipcode,ow.station,ow.other_content')
                ->join($order_tab.' as o ON o.id_order=ow.id_order','left')
                ->join($order_item_tab.' as oi ON o.id_order=oi.id_order','left')
                ->join($ship_track_tab.' as st ON ow.track_number_id=st.id_shipping_track','left')
                ->where(array('ow.wave_number'=>$wave_number))->group('oi.id_order')->order('oi.id_product,ow.id desc')->cache(true,3600)->select();
            if($list_count){
                $page = ceil(count($list_count)/$page_size);
                $firstRow = ($page-1)*$page_size;
                $listRows = $page*$page_size;
                $a_link = '';
                for($i=1;$i<($page+1);$i++){
                    $a_link .=  '<a href="/Waybill/Print/page_print?num='.
                        $wave_number.'&p='.$i.'&page_size='.$page_size.'&template_id='.$template_id.'"
                        style="margin-right:10px;" target="_blank">'.$i.'</a>';
                }
            }else{
                echo '无数据';
            }
        }else{
            echo '没有波次单号';
        }
        $this->assign("page_size",$page_size);
        $this->assign("a_link",$a_link);
        $this->display();
    }
    /**
     * 打印波次单 面单
     */
    public function page_print(){
        $wave_number = I('get.num');
        $template_id = I('get.template_id');
        $template = D("Common/WaybillTemplate")->find($template_id);
        $print_data     = array();
        if($template){
            $template['all_field'] = $template['all_field']?json_decode($template['all_field'],true):'';
            $field = $template['all_field']['data'];
            $box_width = $template['all_field']['box_width'];
            /** @var \Shipping\Model\ShippingModel $shipping_model */
            $shipping_model = D("Shipping/Shipping");
            $all_shipping   = $shipping_model->all();
            $order_tab = M('Order')->getTableName();
            $ship_track_tab = M('ShippingTrack')->getTableName();
            $order_item_tab = M('OrderItem')->getTableName();

            $page_size  = I('get.page_size')?I('get.page_size'):10;
            $list_count = D('OrderWave')->alias('ow')->field('o.*,st.track_number,ow.area_code,ow.zipcode,ow.station,ow.other_content')
                ->join($order_tab.' as o ON o.id_order=ow.id_order','left')
                ->join($order_item_tab.' as oi ON o.id_order=oi.id_order','left')
                ->join($ship_track_tab.' as st ON ow.track_number_id=st.id_shipping_track','left')
                ->where(array('ow.wave_number'=>$wave_number))->group('oi.id_order')
                ->order('oi.id_product desc,oi.id_product_sku desc')->cache(true,3600)->select();
            $page = $this->page(count($list_count), $page_size);

            $list = D('OrderWave')->alias('ow')->field('o.*,st.track_number,ow.area_code,ow.zipcode as zipcode,o.zipcode as order_zipcode,ow.station,ow.other_content')
                ->join($order_tab.' as o ON o.id_order=ow.id_order','left')
                ->join($order_item_tab.' as oi ON o.id_order=oi.id_order','left')
                ->join($ship_track_tab.' as st ON ow.track_number_id=st.id_shipping_track','left')
                ->where(array('ow.wave_number'=>$wave_number))->group('oi.id_order')
                ->order('oi.id_product desc,oi.id_product_sku desc')
                ->limit($page->firstRow . ',' . $page->listRows)->select();

            if($list){
                /** @var \Order\Model\OrderItemModel $ord_item_model */
                $ord_item_model = D('Order/OrderItem');
                /** @var \Waybill\Model\WaybillModel $waybill */
                $waybill  = D("Waybill/Waybill");

                foreach($list as $ord_key=>$item){
                    $address = $item['address'];
                    $other_content = '';
                    if($item['zipcode']){
                        $other_content = $item['other_content']?json_decode($item['other_content'],true):'';
                        //$item['zipcode'] = substr($item['zipcode'],0,3).'-'.substr($item['zipcode'],3,5);
                        $zipCode = array('area'=>$item['area_code'],'zip_code'=>$item['zipcode']);
                    }else{
                        $zipCode = $waybill->get_tw_zip_code($address);
                    }
                    if(empty($item['payment_method'])){
                        $COD = $item['price_total'];
                    }else{
                        $COD = 0;
                    }
                    $products = $ord_item_model->get_item_list($item['id_order']);
                    $product_info = array();
                    $quantity = 0;
                    if($products){
                        foreach($products as $product){
                            $product_info[] = $product['inner_name'].' x '.$product['quantity'].' , '.$product['sku_title'];
                            $quantity += $product['quantity'];
                        }
                    }
                    $product_info = $product_info?'产品总数:'.$quantity.'     ('.implode(';',$product_info).')':'';
                    if($field){
                        foreach($field as $key=>$value){
                            $position = $value?explode(',',$value):array();
                            $track_number = chunk_split($item['track_number'],4,'-');
                            if(substr($track_number,-1)=='-'){
                                $track_number = substr($track_number,0,strlen($track_number)-1);
                            }
                            $font_size = $template['font_size']?$template['font_size']:12;
                            switch($key){
                                case 'id_increment':
                                    $label = $item['id_increment'];
                                    break;
                                case 'quantity':
                                    $label = $quantity;
                                    break;
                                case 'track_number_string':
                                case 'track_number_string2':
                                    $label = $track_number;
                                    break;
                                case 'track_number_string3':
                                    $label = $track_number;
                                    $font_size = $template['track_num_font_size']?$template['track_num_font_size']:12;
                                    break;
                                case 'track_number1':case 'track_number2':
                                    $label = $track_number;
                                break;
                                case 'receipt_date1':case 'receipt_date2':
                                    $label = date('Y-m-d');
                                    break;
                                case 'arrivals1':
                                case 'arrivals2':
                                    $font_size = $item['zipcode']?$font_size-6:$font_size;
                                    $label = date('Ymd',strtotime('+2 day'));
                                    break;
                                case 'arrivals_month':
                                    $label = date('m',strtotime('+2 day')).'月';
                                    break;
                                case 'arrivals_day':
                                    $label = date('d',strtotime('+2 day')).'日';
                                    break;
                                case 'first_name1':case 'first_name2':
                                    $province = ($item['id_zone']==2 or $item['id_zone']==3)?'':$item['province'];
                                    $label = $item['first_name'].' '.$item['last_name'].
                                    ' '.$province.$item['city'].' '.$item['area'].' '.$item['address'].'   <br/>'.$item['tel'];//$item['province'].' '.
                                    break;
                                case 'send_name1':case 'send_name2':
                                    $font_size = $item['zipcode']?$font_size-6:$font_size;
                                    $label = $product_info;
                                    break;
                                case 'remark1':case 'remark2':
                                    $label = $item['remark'];
                                    break;
                                case 'product_title1':case 'product_title2':
                                    $label = $template['product_title']?$template['product_title']:$all_shipping[$item['id_shipping']];
                                    break;
                                case 'id_increment1':case 'id_increment2':
                                    $label = $item['id_increment'];
                                    break;
                                case 'price_total1':case 'price_total2':
                                    $font_size = $item['zipcode']?$font_size+5:$font_size;
                                    switch($item['id_zone']){
                                        case 2:
                                            $currency_code = '元';
                                            break;
                                    }
                                    $label = $item['price_total'].$currency_code;
                                    break;
                                case 'customer_code':
                                case 'customer_code1':
                                case 'customer_code2':
                                    $label = $template['customer_code'];
                                    break;
                                case 'code_and_number':
                                    $label = '客代  '.$template['customer_code'].'<br>單號  '.$track_number;
                                    break;
                                case 'shipping_name':
                                    $label = $template['shipping_name'];
                                    break;
                                case 'zip_code_barcode':
                                case 'zip_code':
                                    $label = is_array($zipCode) && count($zipCode)?implode('-',$zipCode):'';
                                    $font_size = $template['zipcode_font_size']?$template['zipcode_font_size']:18;
                                    break;
                                case 'proxy_point':
                                    $label = $zipCode['area'];
                                    $font_size = $template['zipcode_font_size']?$template['zipcode_font_size']:18;
                                    break;
                                case 'version':
                                    $label = date('ymd').'02 e2.6.6';
                                    break;
                                case 'ESID':
                                    $font_size = 72;
                                    $label = isset($other_content)?$other_content['ESID'].'.':'';
                                    break;
                                case 'SSNA':
                                    $font_size = 36;
                                    $label = $item['station']?$item['station']:'';
                                    break;
                                case 'customer_email':
                                    $domain   = D("Common/Domain")->field('smtp_user')->find($item['id_domain']);
                                    //$font_size = $item['zipcode']?13:$font_size;
                                    $label = $domain['smtp_user']?'客服邮箱:'.$domain['smtp_user']:'';
                                    break;
                                case 'zip_code2' :
                                    $label = $item['order_zipcode'];
                                    break;
                                case 'consignee_country' :
                                    $item['country'] = empty($item['country'])?
                                            M('zone')->where(array('id_zone'=>$item['id_zone']))->getField('title')
                                            : $item['country'];
                                    $label = $item['country'];
                                    break;
                                case 'consignee_name' :case 'consignee_name2' :
                                    $label = $item['first_name'].' '.$item['last_name'];
                                    $label = $this->mb_str_split($label,15);
                                    $label = implode($label, '<br/>');
                                    break;
                                case 'consignee_tel' :case 'consignee_tel2' :
                                    $label = $item['tel'];
                                    break;
                                case 'consignee_province' :
                                    $label = $item['province'];
                                    break;
                                case 'consignee_city':
                                case 'consignee_city2':
                                    $label = $item['city'];
                                    break;
                                case 'consignee_address' :case 'consignee_address2' :
                                $label = $item['address'];
                                break;
                                case 'product_info' :
                                    $label = '';
                                    foreach($products as $product){
                                        $name = str_split($product['inner_name'],34);
                                        $name = implode($name, '<br/>');
                                        $label .= $name .'  *   '. $product['quantity'] . '<br/>';
                                    }
                                    break;
                                case 'date' :
                                    $label = date('Y-m-d');
                                    break;
                                case 'shipping_type' :
                                    $shipping_type = M('OrderWave')->where(array('wave_number'=>$wave_number))->getField('attr_id');
                                    if(empty($shipping_type) || $shipping_type == 2){
                                        $label = "ECOM-PA(普货)";
                                    }else{
                                        $label = "ECOM-DA(特货)";
                                    }
                                    break;
                                case 'track_number_string4' :case 'track_number_string5' :
                                    $label = $item['track_number'];
                                    break;
                                case 'product_info2' :
                                    $label = '';
                                    foreach($products as $product){
                                        $sku_title = empty($product['sku_title']) ? '' : ',' . $product['sku_title'];
                                        $name = "{$product['inner_name']}" . (empty($product['foreign_title']) ? '' : "({$product['foreign_title']})");
                                        $label .= $name .' X '. $product['quantity'] .$sku_title . '<br/>';
                                    }
                                    $label = $this->mb_str_split($label,42);
                                    $label = implode($label, '<br/>');
                                    break;
                                case 'COD':
                                    if(!empty($template['price_font_size'])){
                                        $font_size = $template['price_font_size'];
                                    }else{
                                        $font_size = $item['zipcode']?$font_size+5:$font_size;
                                    }
                                    switch($item['id_zone']){
                                        case 2:
                                            $currency_code = '元';
                                            break;
                                        case 9:
                                            $currency_code = 'đ';
                                            break;
                                    }
                                    $label = $COD.$currency_code;
                                    break;
                            }
                            $field_array[$key]= array(
                                'left' => $position[0],
                                'top' => $position[1],
                                'width' => $box_width[$key]>0?$box_width[$key]:0,
                                'label' => $label,
                                'font_size' => $font_size,
                            );
                        }
                        $print_data[] = $field_array;
                    }
                }
            }
        }else{
            $this->error('没找到当前模板，请重新选择。');
        }
        $this->assign("template",$template);
        $this->assign("font_size",$template['font_size']);
        $this->assign("print_data",$print_data);
        $this->display();
    }
}