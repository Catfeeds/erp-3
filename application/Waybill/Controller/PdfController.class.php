<?php
/**
 * 运单模板
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Report\Controller
 */
namespace Waybill\Controller;
use Common\Controller\HomebaseController;header("Content-type: text/html; charset=utf-8");
class PdfController extends HomebaseController{

    protected $Waybill;
    protected $urls = array(
        'send_order' => "http://47.90.48.6:8800/egs?cmd=query_suda7_dash&address_1="
    );
    public function _initialize() {
        parent::_initialize();
        $this->Waybill=D("Common/WaybillTemplate");
    }
    function mb_str_split($str,$split_length=1,$charset="UTF-8",$total_len=false){
        if(func_num_args()==1){
            return preg_split('/(?<!^)(?!$)/u', $str);
        }
        if($split_length<1)return false;
        if($total_len){
            $len = $total_len;
        }else{
            $len = mb_strlen($str, $charset);
        }
        $arr = array();
        for($i=0;$i<$len;$i+=$split_length){
            $s = mb_substr($str, $i, $split_length, $charset);
            $arr[] = $s;
        }
        return $arr;
    }
    protected function subpart($cont,$l,$utf){
        $len = mb_strlen($cont, $utf);
        for($i=0; $i<$len; $i+=$l)
            $arr[] = mb_substr($cont, $i, $l, $utf);
        return $arr;
    }
    function utf8_str_split($str, $split_len = 1){
        if (!preg_match('/^[0-9]+$/', $split_len) || $split_len < 1)
            return FALSE;
        $len = mb_strlen($str, 'UTF-8');
        if ($len <= $split_len)
            return array($str);
        preg_match_all('/.{'.$split_len.'}|[^\x00]{1,'.$split_len.'}$/us', $str, $ar);
        return $ar[0];
    }
    /**
     * 打印波次单 面单
     */
    public function page_print(){

        set_time_limit(0);
        ini_set('memory_limit', '-1');
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
            $list = D('OrderWave')->alias('ow')->field('o.*,st.track_number,ow.zipcode as zipcode,o.zipcode as order_zipcode,ow.area_code,ow.station,ow.other_content,COUNT(oi.id_order) AS oi_count')
                ->join($order_tab.' as o ON o.id_order=ow.id_order','left')
                ->join($order_item_tab.' as oi ON o.id_order=oi.id_order','left')
                ->join($ship_track_tab.' as st ON ow.track_number_id=st.id_shipping_track','left')
                ->where(array('ow.wave_number'=>$wave_number))->group('oi.id_order')
                ->order('oi.id_product ASC,oi_count ASC,oi.sku DESC,oi.quantity DESC')->select();
            if($list){
                /** @var \Order\Model\OrderItemModel $ord_item_model */
                $ord_item_model = D('Order/OrderItem');
                /** @var \Waybill\Model\WaybillModel $waybill */
                $waybill  = D("Waybill/Waybill");
                $count_list = count($list);
                foreach($list as $ord_key=>$item){
                    $address = $item['address'];
                    $other_content = '';
                    if($item['zipcode']){
                        $other_content = $item['other_content']?json_decode($item['other_content'],true):'';
                        //$item['zipcode'] = substr($item['zipcode'],0,3).'-'.substr($item['zipcode'],3,5);
                        $zipCode = array('area'=>$item['area_code'],'zip_code'=>$item['zipcode']);
                    }else{
                        //壹加壹黑猫
                        //邮编号修改成统一接口调用  liuruibin     20171205
                        $template_id_arr = array(54);
                        if(in_array($template_id,$template_id_arr)){
                            $zipCodeStr = $this->get_zipcode($address);//获取整串邮编号码
                            if($zipCodeStr){
                                //拆分为区号 和 邮编号
                                $zipCodeArr = explode('-',$zipCodeStr);
                                $zipCode['area'] = $zipCodeArr[0];//区号
                                $zipCode['zip_code'] = $zipCodeArr[1].'-'.$zipCodeArr[2];//地区邮编
                            }
                        }
                        else{
                            $zipCode = $waybill->get_tw_zip_code($address);
                        }
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
//                        var_dump($products);die;
                        $temp = [];
                        foreach($products as $pro_key=>$product){
                            //如果是"易速配中华邮政-台湾"ID 161，产品信息只显示一个内部名的信息   liuruibin   20171026
                            //新增"壹加壹-新加坡"ID 167，产品信息只显示一个内部名的信息   liuruibin   20171113
                            if($template_id == 161 || $template_id == 167){
                                $product_inner_name = $product['inner_name'];
                            }
//                            var_dump($temp);die;
                            $br_string = $pro_key%2==0?'<br/>':'';
                            if(!in_array($product['inner_name'],$temp)){
                                $product_info[$product['inner_name']] = $product['inner_name'].' , '.$product['sku_title'].'x '.$product['quantity'].';';
                                $temp[] = $product['inner_name'];
                            }
                            else{
                                $product_info[$product['inner_name']].= $product['sku_title'].'x '.$product['quantity'].';';
                            }
                            $quantity += $product['quantity'];
                        }
                    }
                    $product_info = $product_info?'产品总数:('.$quantity.')  '.implode(';',$product_info).'':'';
                    if($field){
                        foreach($field as $key=>$value){
                            $is_image = false;
                            $position = $value?explode(',',$value):array();
                            $track_number = chunk_split($item['track_number'],4,'-');
                            if(substr($track_number,-1)=='-'){
                                $track_number = substr($track_number,0,strlen($track_number)-1);
                            }
                            $font_size = $template['font_size']?$template['font_size']:12;
                            $font='';
                            $height='';
                            switch($key){
                                case 'id_increment':
                                    $label = $item['id_increment'];
                                    break;
                                case 'id_increment_times':
                                    $label = $item['id_increment'];
                                    $font = 'kaiu';
                                    break;
                                case 'quantity':
                                    $label = $quantity;
                                    break;
                                case 'value':case 'value1':
                                    if(in_array($item['id_shipping'],array('86','90','100')) ){
                                        $label = (int)$quantity*15;
                                        break;
                                    }elseif($item['id_shipping'] == '104'){
                                        if($item['price_total']<2000)
                                             $label = $item['price_total'];
                                        elseif ($item['price_total']>=2000)
                                            $label = 1500;
                                        break;
                                    }
                                case 'track_number_string':
                                case 'track_number_string2':
                                    $label = $track_number;
                                    break;
                                case 'track_number_string3':
                                    $label = $track_number;
                                    $font_size = $template['track_num_font_size']?$template['track_num_font_size']:12;
                                    break;
                                case 'track_number1':case 'track_number2':
                                $label = str_replace('-','',$track_number);
                                $barcode = $this->barcode($label,0);
                                $label = realpath('./'.$barcode);
                                $is_image = true;
                                //壹加壹运单模板，条形码固定高度  liuruibin 20171106
                                if($template_id == 54){
                                    $height = 52;
                                }
                                //远洋黑猫运单模板，条形码固定高度  liuruibin 20171127
                                if($template_id == 66){
                                    $height = 62;
                                }
                                break;
                                case 'xz_track_number_string':
                                    $str=str_replace('-','',$track_number);
                                    $label = substr($str,0,3).'-'.substr($str,3,3).'-'.substr($str,6,4);
                                    break;
                                case 'track_number_yufen':case 'track_number_128':case 'track_number2_128':case 'track_number3_128':
                                    $label = str_replace('-','',$track_number);
//                                $barcode = $this->barcode($label,0);
                                    $label = realpath('./'.$this->barcode($label,0, 'BCGcode128'));
                                    $is_image = true;
                                    break;
                                case 'receipt_date1':case 'receipt_date2':
                                $label = date('Y-m-d');
                                break;
                                case 'arrivals1':
                                case 'arrivals2':
                                    $font_size = $item['zipcode']?18:$font_size;
                                    $label = date('Y-m-d',strtotime('+2 day'));
                                    break;
                                case 'arrivals_month':
                                    $label = (int)date('m',strtotime('+2 day')).'月';
                                    break;
                                case 'arrivals_day':
                                    $label = date('d',strtotime('+2 day')).'日';
                                    break;
                                case 'first_name1':case 'first_name2':
                                $subpart = '';
                                $province = ($item['id_zone']==2 or $item['id_zone']==3)?'':$item['province'];
                                //壹加壹面单，顶部收件人和电话独占一行 收件市用**隐藏  liuruibin   20171018
                                if($template_id == 54 && $key == "first_name1"){
                                    $first_zipCode = str_replace('-','',$zipCode['zip_code']);
                                    $detail_address = $first_zipCode.'<br/>'.substr_replace($item['address'],'***',1,5);
                                }else{
                                    $detail_address = '<br/>'.$province.$item['city'].''.$item['area'].$item['address'];
                                }
                                $label = $item['first_name'].' '.$item['last_name'].$detail_address;
                                if($template_id==54){
                                    $hand_label     = $label;
                                }else{
                                    $hand_label     = str_replace('<br/>','',$label);
                                }
                                $total_width = $box_width[$key];
                                $line_len = ceil($total_width/($font_size-3));
                                $total_len  = mb_strlen($hand_label,"UTF-8");
                                $tel    = !($template_id == 54 && $key == "first_name1") ? $item['tel']:'';
                                if($total_len>$line_len){
                                    $subpart  = $this->mb_str_split($hand_label, $line_len, "utf8",$total_len);
                                    $label    = $subpart?implode('<br/>',$subpart).'<br/>'.$tel:$hand_label.'<br/>'.$tel;
                                }else{
                                    $label    = $label.'<br/>'.$tel;
                                }

                                break;
                                case 'send_name1':case 'send_name2':
                                $subpart = '';
                                $font_size = $template['sender_font_size']?$template['sender_font_size']:12;
                                $font_size = $font_size-2;
                                $label     = str_replace('<br/>','',$product_info);
                                $total_width = $box_width[$key];
                                $line_len = ceil($total_width/($font_size-3));
                                $total_len  = mb_strlen($label,"UTF-8");
                                if($total_len>$line_len){
                                    $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                    $label    = $subpart?implode('<br/>',$subpart):$label;
                                }
                                //壹加壹--运单模板特殊处理--寄件人
                                if($template_id == 54){
                                    $label = $label;
                                }
                                //$box_width[$key] = $box_width[$key]>0?$box_width[$key]:$template['width']-30;
                                break;
                                case 'remark1':case 'remark2':
                                $subpart = '';
                                $label = trim($item['remark']);
                                if($label){
                                    $total_width = $box_width[$key];
                                    $line_len = ceil($total_width/($font_size-5));
                                    $total_len  = mb_strlen($label,"UTF-8");
                                    if($total_len>$line_len){
                                        $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                        $label    = $subpart?implode('<br/>',$subpart):$label;
                                    }
                                }
                                //壹加壹备注字体设置10号  liuruibin   20171107
                                if($template_id==54){
                                    $font_size = 10;
                                }
                                //$box_width[$key] = $box_width[$key]>0?$box_width[$key]:150;
                                break;
                                case 'product_title1':case 'product_title2':
                                $label = $template['product_title']?$template['product_title']:$all_shipping[$item['id_shipping']];
                                break;
                                case 'id_increment1':case 'id_increment2':
                                $label = $item['id_increment'];
                                break;
                                case 'price_total1':
                                case 'price_total2':
                                case 'price_total3':
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
                                    $label = $item['price_total'].$currency_code;
                                    break;
                                case 'customer_code':
                                case 'customer_code1':
                                    if($template_id == 54){
                                        $label = $item['id_increment'];
                                        break;
                                    }
                                case 'customer_code2':
                                    $label = $template['customer_code'];
                                    if($key=='customer_code'){
                                        $barcode = $this->barcode(str_replace('-','',$label),12);
                                        $label = realpath('./'.$barcode);
                                        $is_image = true;
                                    }
                                    break;
                                case 'code_and_number':
                                    $label = '客代  '.$template['customer_code'].'<br>單號  '.$track_number;
                                    break;
                                case 'shipping_name':
                                    $label = $template['shipping_name'];
                                    break;
                                case 'shipping_name_big':
                                    $label = $template['shipping_name'];
                                    $font_size = 50;
                                    break;
                                case 'zip_code_barcode':
                                case 'zip_code':
                                    $label = is_array($zipCode) && count($zipCode)?implode('-',$zipCode):'';
                                    $font_size = $template['zipcode_font_size']?$template['zipcode_font_size']:18;
                                    if($key=='zip_code_barcode'){
                                        //壹加壹运单模板, 新增邮编号码前缀号"+"
                                        if($template_id == 54){
                                            $label = '+'.$label;
                                        }
                                        //远洋黑猫面单, 新增邮编号码前缀号"+",并固定高度    liuruibin   20171127
                                        if($template_id == 66){
                                            $label = '+'.$label;
                                            $height = 66;
                                        }
                                        if($zipCode['area'] && $zipCode['zip_code']){
                                            $barcode = $this->barcode(str_replace('-','',$label),0);
                                            $label = realpath('./'.$barcode);
                                            $is_image = true;
                                        }else{
                                            $label = null;
                                            $is_image = false;
                                        }
                                    }
                                    break;
                                case 'proxy_point':
                                    $label = $zipCode['area'];
                                    $font_size = $template['zipcode_font_size']?$template['zipcode_font_size']:18;
                                    break;
                                case 'version':
                                    $label = date('ymd').'02 e2.6.6';
                                    if($template_id == 54 || $template_id == 66){
                                        //壹加壹 远洋-黑猫面单不要有后面的版本  liuruibin  20171107
                                        $label = '17113002';
                                    }
                                    break;
                                case 'ESID':
                                    $font_size = 68;
                                    $label = isset($other_content)?$other_content['ESID'].'.':'';
                                    break;
                                case 'SSNA':
                                    $font_size = 30;
                                    $label = $item['station']?$item['station']:'';
                                    break;
                                case 'customer_email':
                                    $domain   = D("Common/Domain")->field('smtp_user')->find($item['id_domain']);
                                    //$font_size = $item['zipcode']?13:$font_size;
                                    $label = $domain['smtp_user']?'客服邮箱:'.$domain['smtp_user']:'';
                                    break;
                                case 'order_serial_number':
                                    //$font_size = 12;
                                    $label = ($ord_key+1).'/'.$count_list;
                                    break;
                                case 'zip_code2' :
                                    $label = $item['order_zipcode'];
                                    break;
                                case 'zip_code3' :case 'zip_code4' :
                                    $label = $item['order_zipcode'];
                                    break;
                                case 'consignee_country' :
                                    $item['country'] = M('zone')->where(array('id_zone'=>$item['id_zone']))->getField('title');
                                    if($item['country'] == '新加坡') $item['country'] = 'Singapore';
                                    $label = $item['country'];
                                    break;
                                case 'consignee_name' :case 'consignee_name2' :
                                $label = $item['first_name'].' '.$item['last_name'];
                                $total_width = empty($box_width[$key]) ? 15 : $box_width[$key];
                                $line_len = ceil($total_width/($font_size));
                                $total_len  = mb_strlen($label,"UTF-8");
                                if($total_len>$line_len){
                                    $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                    $label    = $subpart?implode('<br/>',$subpart):$label;
                                }

//                                    $label = $this->mb_str_split($label,15);
//                                    $label = implode($label, '<br/>');
                                break;
                                case 'consignee_tel' :case 'consignee_tel2' :
                                $label = $item['tel'];
                                break;
                                case 'consignee_province' :case 'consignee_province2' :
                                $label = $item['province'];
                                break;
                                case 'consignee_city':
                                case 'consignee_city2':
                                    $label = $item['city'];
                                    break;
                                case 'consignee_address' :case 'consignee_address2' :
                                $label = $item['address'];
                                $total_width = empty($box_width[$key]) ? 42 : $box_width[$key];
                                $line_len = ceil($total_width/($font_size));
                                $total_len  = mb_strlen($label,"UTF-8");
                                if($total_len>$line_len){
                                    $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                    $label    = $subpart?implode('<br/>',$subpart):$label;
                                }
                                    break;
                                case 'zone_code':
                                    $label = $this->zone_code($item['address']);
                                    $font_size = 70;
                                    break;
                                case 'product_info' :
                                    $label = '';
                                    //如果是"易速配中华邮政-台湾"ID 161，产品信息只显示一个内部名的信息   liuruibin   20171026
                                    //新增"壹加壹-新加坡"ID 167，产品信息只显示一个内部名的信息   liuruibin   20171113
                                    if($template_id==161 || $template_id=167){
                                        $product_inner_name = $this->mb_str_split($product_inner_name,10);
                                        $label = implode($product_inner_name, '<br/>');;
                                    }else{
                                        foreach($products as $product){
                                            $sku_title = empty($product['sku_title']) ? '' : ',' . $product['sku_title'];
                                            $name = !empty($product['sale_title'])? $product['sale_title'] :$product['inner_name'];
                                            $name = $this->mb_str_split($name,30);
                                            $name = implode($name, '<br/>');
                                            $label .= $name .' X '. $product['quantity'] .$sku_title . '<br/>';
                                        }
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
                                        $name = "{$product['inner_name']}" . (empty($product['sale_title']) ? '' : "({$product['sale_title']})");
                                        $name = "{$product['inner_name']}" . (empty($product['sale_title']) ? '' : "({$product['sale_title']})");
                                        $label .= $name .' X '. $product['quantity'] .$sku_title . '；  ';
                                    }
                                    $label = $this->mb_str_split($label,42);
                                    $label = implode($label, '<br/>');
                                    break;
                                case 'Payment':
                                    $label = empty($item['payment_method'])?'COD':'Pre-paid';
                                    break;
                                case 'Qty':
                                    $quantity = '';
                                    foreach($products as $product){

                                        $quantity .= $product['quantity']."<br/>";

                                    }
                                    $label    = $quantity;
                                    $font_size = 12;
                                    break;
                                case 'Amount':
                                    $amount = '';
                                    foreach($products as $product){
                                        $amount .= $product['price']."<br/>";
                                    }
                                    $label    = $amount;
                                    $font_size = 12;
                                    break;
                                case 'times_shipper_name':
                                    $label = 'Cuckoo';
                                    break;
                                case 'times_shipper_phone':
                                    $label = '0755-8597845';
                                    break;
                                case 'foreign_product_info' :
                                    $label = '';
                                    foreach($products as $product){
                                        $name = !empty($product['sale_title'])? $product['sale_title'] :$product['inner_name'];
                                        $label .= $name .' X '. $product['quantity']. ';  ';
                                    }
                                    $total_width = empty($box_width[$key]) ? 42 : $box_width[$key];
                                    $line_len = ceil($total_width/($font_size));
                                    $total_len  = mb_strlen($label,"UTF-8");
                                    if($total_len>$line_len){
                                        $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                        $label    = $subpart?implode('<br/>',$subpart):$label;
                                    }
//                                    $font = 'angsana';
//                                    $font_size = 12;
                                    break;
                                case 'foreign_product_info_bjt' :
                                    $label = '';
                                    foreach($products as $product){
                                        $name = !empty($product['sale_title'])? $product['sale_title'] :$product['inner_name'];
                                        $label .= $name .' X '. $product['quantity']. ';  ';
                                    }
                                    $total_width = empty($box_width[$key]) ? 42 : $box_width[$key];
                                    $line_len = ceil($total_width/($font_size));
                                    $total_len  = mb_strlen($label,"UTF-8");
                                    if($total_len>$line_len){
                                        $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                        $label    = $subpart?implode('<br/>',$subpart):$label;
                                    }
                                    $font = 'angsana';
//                                    $font_size = 12;
                                    break;
                                case 'foreign_product_info2' :
                                    $label = '';
                                    foreach($products as $product){
                                        //$name = $product['foreign_title'];
                                        $name = !empty($product['sale_title'])? $product['sale_title'] :$product['inner_name'];
                                        $label .= $name .' X '. $product['quantity']. ';  ';
                                    }
                                    $label = $this->mb_str_split($label,25);
                                    $label = implode($label, '<br/>');
                                    $font = 'arial';
                                    break;
                                case 'foreign_product_info3' :
                                    $label = '';
                                    foreach($products as $product){
                                        $name = !empty($product['sale_title'])? $product['sale_title'] :$product['inner_name'];
                                        $label .= $name .' X '. $product['quantity']. ';  ';
                                    }

                                    $total_width = empty($box_width[$key]) ? 42 : $box_width[$key];
                                    $line_len = ceil($total_width/($font_size));
                                    $total_len  = mb_strlen($label,"UTF-8");
                                    if($total_len>$line_len){
                                        $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                        $label    = $subpart?implode('<br/>',$subpart):$label;
                                    }
                                    $font = 'arial';
                                    break;
                                case 'inner_product_info' :
                                    $label = '';
                                    foreach($products as $product){
                                        $sku_title = empty($product['sku_title']) ? '' : ',' . $product['sku_title'];
                                        $name = "{$product['inner_name']}";
                                        $label .= $name .' X '. $product['quantity'] .$sku_title . '；  ';
                                    }

                                    $total_width = empty($box_width[$key]) ? 42 : $box_width[$key];
                                    $line_len = ceil($total_width/($font_size));
                                    $total_len  = mb_strlen($label,"UTF-8");
                                    if($total_len>$line_len){
                                        $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                        $label    = $subpart?implode('<br/>',$subpart):$label;
                                    }
                                    $font = 'simhei';
                                    break;
                                case 'inner_product_info_big' :
                                    $label = '';
                                    foreach($products as $product){
                                        $sku_title = empty($product['sku_title']) ? '' : ',' . $product['sku_title'];
                                        $name = "{$product['inner_name']}";
                                        $label .= $name .' X '. $product['quantity'] .$sku_title . '；  ';
                                    }

                                    $total_width = empty($box_width[$key]) ? 42 : $box_width[$key];
                                    $line_len = ceil($total_width/($font_size));
                                    $total_len  = mb_strlen($label,"UTF-8");
                                    if($total_len>$line_len){
                                        $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                        $label    = $subpart?implode('<br/>',$subpart):$label;
                                    }
                                    $font = 'simhei';
                                    $font_size = 25;
                                    break;
                                case 'COD':case 'COD2':
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
                                        case 17:
                                            $currency_code = 'RM';
                                            break;
                                    }
                                $label = $COD.$currency_code;
                                break;
                                case 'times_COD':case 'times_COD2':$label = $COD;break;
                                case 'COD_code1':
                                case 'COD_code2':$font_size=40;$label = $COD;break;
                                case 'xz_COD':$label = $COD;$font_size = 30;break;
                                case 'COD_code1':
                                case 'COD_code2':$font_size=40;$label = $COD;break;
                                case 'id_increment_code':
                                    $label = $item['id_increment'];
                                    $label = realpath('./'.$this->barcode($label,0, 'BCGcode128'));
                                    $is_image = true;
                                    break;
                                case 'id_increment_code39':
                                    $label = $item['id_increment'];
                                    $label = realpath('./'.$this->barcode($label,0, 'BCGcode39'));
                                    $is_image = true;
                                    break;
                                case 'id_increment_code128':
                                case 'id_increment_code128_2':
                                    $label = $item['id_increment'];
                                    $label = realpath('./'.$this->barcode($label,0, 'BCGcode128'));
                                    $is_image = true;
                                    break;
                            }
                            $field_array[$key]= array(
                                'left' => $position[0],
                                'top' => $position[1],
                                'width' => $box_width[$key]>0?$box_width[$key]:0,
                                'height' => $height,
                                'label' => $label,
                                'font_size' => $font_size,
                                'is_image'  => $is_image,
                                'font' => !empty($font) ? $font : '',
                            );
                        }
                        $print_data[] = $field_array;
                    }
                }
            }
        }else{
            $this->error('没找到当前模板，请重新选择。');
        }
//        var_dump($print_data);exit;
        $pdf_data = array();
        $template['waybill_image'] = realpath('./'.$template['waybill_image']);
        foreach($print_data as $item_data){
            $pdf_data[] = array(
                'template' => $template,
                'data' => $item_data,
            );
        }
//        var_dump($print_data);exit;
        $page_show_number = isset($template['page_show_number'])&& $template['page_show_number']>0?$template['page_show_number']:1;
        $this->out_pdf($pdf_data,$template['width'],$template['height'],$page_show_number);
//        $this->assign("template",$template);
//        $this->assign("font_size",$template['font_size']);
//        $this->assign("print_data",$print_data);
//        $this->display();
    }
    public function out_pdf($data=array(),$width=500,$height=500,$number=1){
        import("tFPDF");
        $page_width = $width;
        $page_height = $height*$number;
        $id_shipping = isset($data[0]['template']['id_shipping'])?$data[0]['template']['id_shipping']:0;

        switch($id_shipping){
            //添加一个物流ID判断 117 壹加壹-新加坡 liuruibin  20171110
            case 21:case 56:case 31:case 64:case 66:case 65:case 74:case 60:case 86:case 82:case 117:
                $font="simhei";
                $orientation = 'p';
                break;
            case 58:
                $font="arial";
                $orientation = $number>1?'p':'l';
                break;
            case 70:
                $font="angsana";
                $orientation = 'p';
                break;
            case 88:case 98:
                $font="simhei";
                $orientation = 'p';
                break;
            default:
                $font="simhei";
                $orientation = $number>1?'p':'l';
        }
        $pdf = new \tFPDF($orientation,'pt',array($page_width,$page_height));
        if(is_array($data)){
            $i = 0;
            foreach($data as $d_key=>$item){
                $page_flag = $d_key%$number;
                if($number==1 or $d_key==0){
                    $pdf->AddPage();
                }elseif($number>1 && $page_flag==0){
                    $pdf->AddPage();$i = 0;
                }
                $pdf->AddFont($font,'',$font.'.ttf',true);
                $pdf->SetFont($font,'',$item['template']['font_size']);

                if($item['template']['waybill_image']){
                    $background_height = $number>1?$i*$height:0;
                    $pdf->Image($item['template']['waybill_image'], 0,$background_height, $width, $height);
                }
                if(is_array($item['data'])){
                    foreach($item['data'] as $item_data){
                        $item_top = $number>1?$item_data['top']+$height*$i:$item_data['top'];
                        if($item_data['is_image']){
                            $item_data['height'] = isset($item_data['height'])?$item_data['height']:'';
                            $pdf->Image($item_data['label'],$item_data['left'], $item_top, $item_data['width'], $item_data['height']);
                        }else{
                            if(!empty($item_data['font'])){
                                $pdf->AddFont($item_data['font'],'',$item_data['font'].'.ttf',true);
                                $pdf->SetFont($item_data['font'],'',$item_data['font_size']);
                            }else{
                                $pdf->AddFont($font,'',$font.'.ttf',true);
                                $pdf->SetFont($font,'',$item_data['font_size']);
                            }
                            if(strpos($item_data['label'],'<br')){
                                $get_label = explode('<br/>',$item_data['label']);
//                                $height_top = ceil($item_data['font_size']/3);
                                $height_top = $item_data['font_size'];
                                $height_top = $height_top>12?$height_top:12;
                                foreach($get_label as $key=> $label){
                                    $top  = $key*$height_top;
                                    $pdf->Text($item_data['left'], $item_top+$top, $label);
                                }
                            }else{
                                $pdf->Text($item_data['left'], $item_top, $item_data['label']);
                            }
                        }
                    }
                }
                $i++;
            }


        }
//        $pdf->AddPage();
//
//        $pdf->Image('http://www.newerp.com/data/upload/20170313/58c5f60db75e8.jpg', 0, 0, 500, 500);
//        $barcode = $this->barcode('40000204227',0);
//        $pdf->Image('http://www.newerp.com'.$barcode,36, 100, 200, 50);
//
//        $pdf->AddFont('kaiu','','kaiu.ttf',true);
//        $pdf->SetFont('kaiu','',14);
//        $pdf->Text(207, 24, 'testsssssss');
//        $pdf->Text(200, 200, '新竹縣竹北市福興東路二段');
//        $pdf->Write (5,'zxc123<br />\\n你好');
//
//        $pdf->AddPage();
//        $pdf->Image('http://www.newerp.com/data/upload/20170313/58c5f60db75e8.jpg', 0, 0, 500, 500);
//        $pdf->Image('http://www.newerp.com'.$barcode,36, 100, 200, 50);
//
//        //$pdf->SetFont('msyh','I',8);
//        $pdf->Text(100, 100, 'testsssssss');

        $pdf->Output();
        exit();
    }
    private function barcode($barcode_code='empty',$is_number=12, $btype="BCGcode39"){
        $setPath = './'.C("UPLOADPATH").'barcode'."/".date('Ym')."/";
        if(!is_dir($setPath)){
            mkdir($setPath,0777,TRUE);
        }
        $file = $setPath.$barcode_code.'.png';
        if(file_exists($file)){
            unlink($file);
        }
        import('BCGFontFile');
        import('BCGColor');
        import('BCGDrawing');
        import($btype);
        $Arial = 'Arial.ttf';
        $font = new \BCGFontFile($Arial,18);
        $color_black = new \BCGColor(0, 0, 0);
        $color_white = new \BCGColor(255, 255, 255);
        $drawException = null;
        try {
            $calss_name = "\\".$btype;
            $code = new $calss_name();
            $code->setScale(2); // Resolution
            $code->setThickness(30); // Thickness
            $code->setForegroundColor($color_black); // Color of bars
            $code->setBackgroundColor($color_white); // Color of spaces
            $code->setFont($is_number); // Font (or 0)
            $code->parse($barcode_code); // Text
        } catch(\Exception $exception) {
            $drawException = $exception;
        }

        /* Here is the list of the arguments
        1 - Filename (empty : display on screen)
        2 - Background color */

        $drawing = new \BCGDrawing($file, $color_white);
        if($drawException) {
            $drawing->drawException($drawException);
        } else {
            $drawing->setBarcode($code);
            $drawing->draw();
        }
//        header('Content-Type: image/png');
//        header('Content-Disposition: inline; filename="barcode.png"');
        $drawing->finish(\BCGDrawing::IMG_FORMAT_PNG);
        return str_replace('./','/',$file);
    }
    /*
     *森鸿台湾获取地区码
     */
    public function zone_code($zone){
        $zone_code = urlencode($zone);
        $url = 'http://is1fax.hct.com.tw:8080/GET_ERSTNO/Addr_Compare_Simp.aspx?addr='.$zone_code;
         //curl发送get请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
        $str = substr($output,0,strpos($output,'<!DOCTYPE'));
        $str1 = substr($str,strpos($str,'<BR>')+4);
        $str2 = strpos($str1,'<BR>');
        $str3 = substr($str1,$str2+16,3);
        return $str3!="<BR"?$str3:'';
    }

    /**
     * @param null $address 收货地址
     * @return mixed 返回调用状态和邮编号
     */
    public function get_zipcode($address = null){
        $url_service = $this->urls['send_order'];
        $address = urlencode($address);
        $url = $url_service . $address;
        $get_data = file_get_contents($url);
        $arr = str_replace(array('=','&'), array('"=>"','","'),'array("'.$get_data.'")');
        eval("\$arr"." = $arr;");
        return $arr['suda7_1'];
    }
}