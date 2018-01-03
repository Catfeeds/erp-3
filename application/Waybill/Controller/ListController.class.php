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
class ListController extends AdminbaseController{
	protected $Waybill;
	public function _initialize() {
		parent::_initialize();
		$this->Waybill=D("Common/WaybillTemplate");
	}

    /**
     * 模板列表
     */
    public function index(){
        $shipping = D("Common/Shipping")->getTableName();
        //新增筛选条件"运单模板标题"搜索      liuruibin   20171023
        if(isset($_GET['title']) && $_GET['title']){
            $title = $_GET['title'];
            $where['w.title'] = array('like',"%{$title}%");
        }
        //新增筛选条件"物流名称"搜索     liuruibin   20171023
        if(isset($_GET['shipping_title']) && $_GET['shipping_title']){
            $shipping_title = $_GET['shipping_title'];
            $where['s.title'] = array('like',"%{$shipping_title}%");
        }
        $where['w.status'] = array('EQ','1');
        $count =  $this->Waybill->alias('w')->field('w.*,s.title as shipping_title')
            ->join($shipping.' as s ON w.id_shipping= s.id_shipping','left')->where($where)->count();
        $page = $this->page($count, 12);
        $list = $this->Waybill->alias('w')->field('w.*,s.title as shipping_title')
            ->join($shipping.' as s ON w.id_shipping= s.id_shipping','left')
            ->where($where)
            ->order('id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
            // dump($list);die;
        $this->assign("list",$list);
        $this->assign("Page", $page->show('Admin'));
        $this->display();
    }

    /**
     * 编辑页面 from 表单
     */
    public function edit(){
        /** @var \Shipping\Model\ShippingModel $shipping */
        $shipping = D("Shipping/Shipping");
        $all_shipping = $shipping->all();
        $waybill  = array();$all_field = array();$css_value= array();
        $field_label = array(
            'track_number_string' => '包裹查询号码1',
            'track_number_string2' => '包裹查询号码2',
            'track_number_string3' => '包裹查询号码3-自定义大小',
            'track_number1' => '快递单号 条形码1',
            'track_number_yufen' => '快递单号 裕丰',
            'track_number2' => '快递单号 条形码2',
            'receipt_date1' => '收货日1',
            'quantity'=>'件数',
            'receipt_date2' => '收货日2',
            'arrivals1' => '预定配达时间',
            'arrivals2' => '预定配达时间2',
            'arrivals_month' => '预定配达月',
            'arrivals_day' => '预定配达日',
            'first_name1' => '收货人1',
            'first_name2' => '收货人2',
            'send_name1' => '寄件人1',
            'send_name2' => '寄件人2',
            'remark1' => '备注1',
            'remark2' => '备注2',
            'product_title1' => '品名1',
            'product_title2' => '品名2',
            'id_increment1' => '订单号1',
            'id_increment2' => '订单号2',
            'price_total1' => '代收款1',
            'price_total2' => '代收款2',
            'price_total3' => '代收款3',
            'customer_code' => '客户代码 条形码',
            'customer_code1' => '客户代码1',
            'customer_code2' => '客户代码2',
            'id_increment' => '订单编号',
            'shipping_name' => '物流名',
            'zip_code_barcode' => '邮编条码',
            'zip_code' => '邮编',
            'proxy_point' => '代理点',
            'version' => '版本',
            'ESID' => '到著簡碼',
            'SSNA' => '發送站-中心',
            'customer_email' => '客服邮箱',
            'order_serial_number' => '单序号',
            'consignee_name' => '收货人姓名',
            'consignee_name2' => '收货人姓名2',
            'consignee_tel' => '收货人电话',
            'consignee_province' => '收货人省',
            'consignee_city' => '收货人市',
            'consignee_city2' => '收货人市',
            'consignee_address' => '收货人地址',
            'consignee_address2' => '收货人地址',
            'consignee_tel2' => '收货人电话2',
            'zip_code2' => '收货人邮编',
            'zip_code3' => '收货人邮编3',
            'zip_code4' => '收货人邮编4',
            'product_info' => '产品信息',
            'date' => '当天日期',
            'shipping_type' => '物流类型',
            'track_number_string4' => '面单号',
            'track_number_string5' => '面单号2',
            'product_info2' => '中文(外文)产品信息',
            'inner_product_info' => '内部产品信息',
            'inner_product_info_big' => '内部产品信息big',
            'foreign_product_info' => '外文产品信息',
            'foreign_product_info2' => '外文产品信息壹加壹',
            'foreign_product_info3' => '外文产品信息壹加壹下',
            'id_increment_code39' => '编码39订单号条码',
            'id_increment_code128' => '编码128订单号条码',
            'id_increment_code128_2' => '编码128订单号条码2',
            'COD' => 'COD',
            'COD2' => 'COD2',
            'Qty'=>'数量',
            'Amount'=>'产品总价',
            'Payment'=>'付款方式',
            'times_shipper_name'=>'泰国发货人',
            'times_shipper_phone'=>'泰国发货人电话',
            'times_COD'=>'泰国金额',
            'xz_COD'=>'新竹金额',
            'zone_code'=>'地区码',
            'times_COD'=>'泰国金额',
            'zone_code'=>'地区码',
            'xz_track_number_string' => '新竹包裹查询号码',
            "id_increment_times"=>'泰国单号',
            //中通piece
            'value'=>'报税总价',
            'value2'=>'报税总价2',
             'COD_code1' => 'COD_code1',
             'COD_code2' => 'COD_code2',
            'times_COD'=>'泰国金额',
            'zone_code'=>'地区码',
            'times_COD'=>'泰国金额',
            'times_COD2'=>'泰国金额2',
            'zone_code'=>'地区码',
            'xz_track_number_string' => '新竹包裹查询号码',
            "id_increment_times"=>'泰国单号',
            "shipping_name_big"=>'物流名称big',
            "track_number_128"=>'快递单号128',
            "track_number2_128"=>'快递单号128第2',
            "track_number3_128"=>'快递单号128第3',
            "id_increment_times"=>'泰国单号',
            //中通piece
            'value'=>'报税总价',
             'COD_code1' => 'COD_code1',
             'COD_code2' => 'COD_code2',
            'foreign_product_info_bjt'=>'指定外文的外文'
        );
        $box_width = array();
        if(isset($_GET['id'])){
            $id = I('get.id');
            $waybill = D("Common/WaybillTemplate")->find($id);

            if($waybill['all_field']){
                $get_json = json_decode($waybill['all_field'],true);
                $all_field = $get_json['data'];
                $box_width  = $get_json['box_width'];
            }
            $field = array_keys($all_field);
            if(count($all_field)){
                foreach($all_field as $key=>$value){
                    $get_value = $value?explode(',',$value):array();
                    if(count($get_value)==2){
                        $css_value[$key] = 'left:'.$get_value[0].'px;top:'.$get_value[1].'px;';
                    }
                }
            }
        }else{
            $field = array_keys($field_label);
            $i= 1;$left = 0; $top = 6;$default_left = 0;
            foreach($field as $key=>$value){
                $key_count = $key+1;
                $l = $key_count%4;
                if($l==0){
                    $top = $i*60;$default_left =0;
                    $i++;
                }
                $default_left = $key==0?0:$l*180;
                $css_value[$value] = 'left:'.$default_left.'px;top:'.$top.'px;';
                $box_width[$value]  = '';
            }
            $waybill['width'] = 780;$waybill['height'] = 780;
        }
        $waybill['page_show_number'] = isset($waybill['page_show_number'])?$waybill['page_show_number']:1;
        $this->assign("waybill",$waybill);
        $this->assign("field",$field);
        $this->assign("field_label",$field_label);
        $this->assign("all_field",$all_field);
        $this->assign("box_width",$box_width);
        $this->assign("css_value",$css_value);
        $this->assign("shipping",$all_shipping);
		$this->display();
	}

    /**
     * 保存编辑模板
     */
    public function edit_post(){
        try{
            $data = array('data'=>$_POST['all_field'],'box_width'=>$_POST['box_width']);
            $_POST['all_field'] = json_encode($data);
            $id = I('get.id');
            if($_FILES['waybill_image'] && $_FILES['waybill_image']['name']){
                $get_file = upload_image($_FILES);
                $_POST['waybill_image'] = $get_file['status']?$get_file['file_path']:'';
            }else{
                unset($_POST['waybill_image']);
            }
            if($id){
                D("Common/WaybillTemplate")->where(array('id'=>$id))->save($_POST);
            }else{
                $_POST['created_at'] = date('Y-m-d H:i:s');
                $id =  D("Common/WaybillTemplate")->data($_POST)->add();
            }
            $this->success('', U("Waybill/List/edit",array('id'=>$id)));
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
    }
    
    /**
     * 复制页面表单
     */
    public function copy() {
        /** @var \Shipping\Model\ShippingModel $shipping */
        $shipping = D("Shipping/Shipping");
        $all_shipping = $shipping->all();
        $waybill  = array();$all_field = array();$css_value= array();
        $field_label = array(
            'track_number_string' => '包裹查询号码1',
            'track_number_string2' => '包裹查询号码2',
            'track_number_string3' => '包裹查询号码3-自定义大小',
            'track_number1' => '快递单号 条形码1',
            'track_number2' => '快递单号 条形码2',
            'receipt_date1' => '收货日1',
            'receipt_date2' => '收货日2',
            'arrivals1' => '预定配达时间',
            'arrivals2' => '预定配达时间2',
            'arrivals_month' => '预定配达月',
            'arrivals_day' => '预定配达日',
            'first_name1' => '收货人1',
            'first_name2' => '收货人2',
            'send_name1' => '寄件人1',
            'send_name2' => '寄件人2',
            'remark1' => '备注1',
            'remark2' => '备注2',
            'product_title1' => '品名1',
            'product_title2' => '品名2',
            'id_increment1' => '订单号1',
            'id_increment2' => '订单号2',
            'price_total1' => '代收款1',
            'price_total2' => '代收款2',
            'customer_code' => '客户代码 条形码',
            'customer_code1' => '客户代码1',
            'customer_code2' => '客户代码2',
            'id_increment' => '订单编号',
            'shipping_name' => '物流名',
            'zip_code_barcode' => '邮编条码',
            'zip_code' => '邮编',
            'proxy_point' => '代理点',
            'version' => '版本',
            'ESID' => '到著簡碼',
            'SSNA' => '發送站-中心',
            'customer_email' => '客服邮箱',
            'consignee_country' => '收货人国家',
            'consignee_name' => '收货人姓名',
            'consignee_name2' => '收货人姓名2',
            'consignee_tel' => '收货人电话',
            'consignee_province' => '收货人省',
            'consignee_city' => '收货人市',
            'consignee_city2' => '收货人市',
            'consignee_address' => '收货人地址',
            'consignee_address2' => '收货人地址2',
            'product_info' => '产品信息',
            'inner_product_info' => '内部产品信息',
            'foreign_product_info' => '外文产品信息',
            'COD' => 'COD',
            'COD2' => 'COD2',

        );
        $box_width = array();
        if(isset($_GET['id'])){
            $id = I('get.id');
            $waybill = D("Common/WaybillTemplate")->find($id);

            if($waybill['all_field']){
                $get_json = json_decode($waybill['all_field'],true);
                $all_field = $get_json['data'];
                $box_width  = $get_json['box_width'];
            }
            $field = array_keys($all_field);
            if(count($all_field)){
                foreach($all_field as $key=>$value){
                    $get_value = $value?explode(',',$value):array();
                    if(count($get_value)==2){
                        $css_value[$key] = 'left:'.$get_value[0].'px;top:'.$get_value[1].'px;';
                    }
                }
            }
        }else{
            $field = array_keys($field_label);
            $i= 1;$left = 0; $top = 6;$default_left = 0;
            foreach($field as $key=>$value){
                $key_count = $key+1;
                $l = $key_count%4;
                if($l==0){
                    $top = $i*60;$default_left =0;
                    $i++;
                }
                $default_left = $key==0?0:$l*180;
                $css_value[$value] = 'left:'.$default_left.'px;top:'.$top.'px;';
                $box_width[$value]  = '';
            }
            $waybill['width'] = 780;$waybill['height'] = 780;
        }
        $waybill['page_show_number'] = isset($waybill['page_show_number'])?$waybill['page_show_number']:1;
        $this->assign("waybill",$waybill);
        $this->assign("field",$field);
        $this->assign("field_label",$field_label);
        $this->assign("all_field",$all_field);
        $this->assign("box_width",$box_width);
        $this->assign("css_value",$css_value);
        $this->assign("shipping",$all_shipping);
        $this->display();
    }
    
    /**
     * 复制页面添加数据
     */
    public function copy_post() {
        try{
            $data = array('data'=>$_POST['all_field'],'box_width'=>$_POST['box_width']);
            $_POST['all_field'] = json_encode($data);
            $id = I('get.id');
            $wt = M('WaybillTemplate')->where(array('id'=>$id))->find();
            $_POST['waybill_image'] = $wt['waybill_image'];
            if($_FILES['waybill_image'] && $_FILES['waybill_image']['name']){
                $get_file = upload_image($_FILES);
                $_POST['waybill_image'] = $get_file['status']?$get_file['file_path']:'';
            }
            $_POST['created_at'] = date('Y-m-d H:i:s');
            $id =  D("Common/WaybillTemplate")->data($_POST)->add();
            $this->success('保存成功', U("Waybill/List/index"));
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
    }

    /**
     * 删除模板
     */
    public function delete(){
        D("Common/WaybillTemplate")->where(array('id'=>I('get.id')))->delete();
        $this->success('', U("Waybill/List/index"));
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
            $list = D('OrderWave')->alias('ow')->field('o.*,st.track_number,ow.area_code,ow.zipcode as zipcode,o.zipcode as order_zipcode,ow.station,ow.other_content,COUNT(oi.id_order) AS oi_count')
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
                                    $font_size = $item['zipcode']?12:$font_size;
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
//                                    $font_size = $item['zipcode']?12:$font_size;
                                    $font_size = $template['sender_font_size']?$template['sender_font_size']:12;
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