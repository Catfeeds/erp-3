<?php
namespace Order\Model;
use Common\Lib\Currency;
use Common\Model\CommonModel;
use Think\Cache\Driver\Redis;
class ApiModel{
    /**
     * 本来准备写到REDIS里面缓存订单数据，因为读取问题，直接写到数据库保存了。
     * @return string
     */
    public function redis_temp_order($post_data){
        try{
            /* @var $sku_model \Product\Model\ProductSkuModel*/
            $sku_model = D("Product/ProductSku");
            //设置部门对应的ID 系统id =>部门订单或SKU开头的ID
            //部门ID从数据库直接读取,不再按之前兄弟公司的部门来设置
            /*$get_config_department = array(
                1=>1,
                2=>2,
                3=>3,
                4=>4,
                5=>5,
                6=>6,
                7=>7,
                14=>8,
                17=>9,
                19=>10,
                21=>11,
                23=>12,
                26=>13,
                28=>15,
                30=>14,
                32=>98,
                34=>99,
                36=>16,
                38=>17,
                40=>18,
                42=>19,
                44=>20,
                46=>21,
                48=>51,
                50=>97,
                52=>96,
                54=>28,
                56=>27,
                58=>25,
                60=>26,
                62=>23,
                64=>22,
                66=>24,
                68=>61,
                70=>71,
                72=>29,
                74=>30,
                76=>31
            ); */

            $data = $this->filter_post_html($post_data);
            if(isset($post_data['key']) && $post_data['email'] && $post_data['key']==md5($post_data['web_url'].$post_data['created_at'])){
                $data['web_url']         = sp_trim_host($data['web_url']);
                $data['id_order_status'] = 1;//初始化订单状态
                $data['created_at']      = date('Y-m-d H:i:s');
                $data['country']         = isset($data['country'])?$data['country']:'中国';
                $data['province']        = isset($data['province'])?$data['province']:'台灣';
                $data['currency_code']   = isset($data['currency_code'])?$data['currency_code']:'TWD';
                $data['payment_method']  = isset($data['payment_method']) && trim($data['payment_method'])?$data['payment_method']:0;
                $data['total_qty_ordered'] = isset($data['number'])?$data['number']:1;
                /** @var \Domain\Model\DomainModel $domain */
                $domain                  = D('Domain/Domain');
                $domain                  = $domain->get_domain($data['web_url']);
                if(!$domain['id_domain']) {
                    $returnData = array(
                        'status'=>false,
                        'message'=>'域名不存在'
                    );
                    exit(json_encode($returnData));
                }
//                $get_config_department = C('GET_DEPARTMENT_ID');
                //$get_ini_department_id = $get_config_department[$domain['id_department']];
                $get_ini_department_id = $domain['id_department'];

                $id_department           = isset($data['order_id']) ? $data['order_id'] : $get_ini_department_id.date('ymdhis').rand(100,999);
                $data['id_department']   = $domain['id_department'];//部门逻辑
                $data['id_domain']       = $domain['id_domain'];
                $data['id_increment']    = $id_department;
                $data['id_warehouse']    = 1;
                $data['id_shipping']     = 0;
                $data['grand_total']     = 0;

                if(isset($data['id_zone'])&& $data['id_zone']){
                    switch($data['id_zone']){
                        case 2:
                            $data['province'] = '台灣';
                            break;
                        case 3:
                            $data['province'] = '香港';
                            break;
                    }
                }
                $temp_post = array(
                    'id_increment'=> $id_department,
                    'post_data'=> json_encode($data),
                    'id_department'=> $domain['id_department'],
                    'data_key' => $post_data['key'],
                    'created_at'=> date('Y-m-d H:i:s')
                );
                if($data['email']){
                    //限制刷单
                    $date = date('Y-m-d 00:00:00');
                    $today_where = array(
                        'id_department'=> $domain['id_department'],
                        'created_at'=>array('EGT', $date),
                        'post_data'=>array('LIKE','%'.trim($data['email']).'%')
                    );
                    $select_today_order = D("Common/TempOrderPost")->where($today_where)->count();
                    if($select_today_order>1000){
                        $temp_post['status'] = 2;
                    }
                }
                D("Common/TempOrderPost")->data($temp_post)->add();
                $status = true; $message = '';
            }else{
                $status = false;$message = '请求错误';
            }
        }catch (\Exception $e){
            $status = false;$message = $e->getMessage();
        }
        $returnData = array('status'=>$status,'message'=>$message,'order_id'=>$id_department,'data'=>$data);
        return json_encode($returnData);
    }

    //写入hk表 zhujie
    public function redis_temp_order_hk($post_data){
        try{
            /* @var $sku_model \Product\Model\ProductSkuModel*/
            $sku_model = D("Product/ProductSku");
            $data = $this->filter_post_html($post_data);
            if(isset($post_data['key']) && $post_data['email'] && $post_data['key']==md5($post_data['web_url'].$post_data['created_at'])){
                $data['web_url']         = sp_trim_host($data['web_url']);
                $data['id_order_status'] = 1;//初始化订单状态
                $data['created_at']      = date('Y-m-d H:i:s');
                $data['country']         = isset($data['country'])?$data['country']:'中国';
                $data['province']        = isset($data['province'])?$data['province']:'台灣';
                $data['currency_code']   = isset($data['currency_code'])?$data['currency_code']:'TWD';
                $data['payment_method']  = isset($data['payment_method']) && trim($data['payment_method'])?$data['payment_method']:0;
                $data['total_qty_ordered'] = isset($data['number'])?$data['number']:1;
                /** @var \Domain\Model\DomainModel $domain */
                $domain                  = D('Domain/Domain');
                $domain                  = $domain->get_domain($data['web_url']);
                if(!$domain['id_domain']) {
                    $returnData = array(
                        'status'=>false,
                        'message'=>'域名不存在'
                    );
                    exit(json_encode($returnData));
                }
//                $get_config_department = C('GET_DEPARTMENT_ID');
                //$get_ini_department_id = $get_config_department[$domain['id_department']];
                $get_ini_department_id = $domain['id_department'];

                $id_department           = isset($data['order_id']) ? $data['order_id'] : $get_ini_department_id.date('ymdhis').rand(100,999);
                $data['id_department']   = $domain['id_department'];//部门逻辑
                $data['id_domain']       = $domain['id_domain'];
                $data['id_increment']    = $id_department;
                $data['id_warehouse']    = 1;
                $data['id_shipping']     = 0;
                $data['grand_total']     = 0;

                if(isset($data['id_zone'])&& $data['id_zone']){
                    switch($data['id_zone']){
                        case 2:
                            $data['province'] = '台灣';
                            break;
                        case 3:
                            $data['province'] = '香港';
                            break;
                    }
                }
                $temp_post = array(
                    'id_increment'=> $id_department,
                    'post_data'=> json_encode($data),
                    'id_department'=> $domain['id_department'],
                    'data_key' => $post_data['key'],
                    'created_at'=> date('Y-m-d H:i:s')
                );
                if($data['email']){
                    //限制刷单
                    $date = date('Y-m-d 00:00:00');
                    $today_where = array(
                        'id_department'=> $domain['id_department'],
                        'created_at'=>array('EGT', $date),
                        'post_data'=>array('LIKE','%'.trim($data['email']).'%')
                    );
                    $select_today_order = D("Common/TempOrderPostHk")->where($today_where)->count();
                    if($select_today_order>1000){
                        $temp_post['status'] = 2;
                    }
                }
                D("Common/TempOrderPostHk")->data($temp_post)->add();
                $status = true; $message = '';
            }else{
                $status = false;$message = '请求错误';
            }
        }catch (\Exception $e){
            $status = false;$message = $e->getMessage();
        }
        $returnData = array('status'=>$status,'message'=>$message,'order_id'=>$id_department,'data'=>$data);
        return json_encode($returnData);
    }

    public function create_order(){
        try{
            /* @var $sku_model \Product\Model\ProductSkuModel*/
            $sku_model = D("Product/ProductSku");
            $data = $this->filter_post_html($_POST);
            if(isset($_POST['key']) && $_POST['email'] && $_POST['key']==md5($_POST['web_url'].$_POST['created_at'])){
                $data['web_url']         = sp_trim_host($data['web_url']);
                $data['id_order_status'] = 1;//初始化订单状态
                $data['created_at']      = date('Y-m-d H:i:s');
                $data['country']         = isset($data['country'])?$data['country']:'中国';
                $data['province']        = isset($data['province'])?$data['province']:'台灣';
                $data['currency_code']   = isset($data['currency_code'])?$data['currency_code']:'TWD';
                $data['payment_method']  = isset($data['payment_method'])?$data['payment_method']:'';
                $data['total_qty_ordered'] = isset($data['number'])?$data['number']:1;
                /** @var \Domain\Model\DomainModel $domain */
                $domain                  = D('Domain/Domain');
                $domain                  = $domain->get_domain($data['web_url']);
                if(!$domain['id_domain']) {
                    $returnData = array(
                        'status'=>false,
                        'message'=>'域名不存在'
                    );
                    exit(json_encode($returnData));
                }
                $data['id_department']   = $domain['id_department'];//部门逻辑
                $data['id_domain']       = $domain['id_domain'];
                $data['id_increment']    = $domain['id_department'].date('ymdhis').rand(100,999);
                $data['id_warehouse']    = 1;
                $data['id_shipping']     = 0;


                $getSkuModel = array();
                $data['grand_total'] = 0;$totalQty = 0;
                $temp_pro_title = array();
                foreach($data['products'] as $pro_key=>$product){
                    $product           = $this->filter_post_html($product);
                    $product['attrs']  = $this->filter_post_html($product['attrs']);
                    $product_id = $product['id_product'];
                    $tempProId[]  = $product_id;
                    $temp_pro_title[] = $product['sale_title'];
                    $sku_result = $sku_model->get_sku_id($product_id,$product['attrs']);
                    $getSkuModel[$product_id] = $sku_result['id'];
                    $data['products'][$pro_key]['id_product_sku'] = $sku_result['id'];
                    $data['products'][$pro_key]['sku'] = $sku_result['sku'];
                    $data['products'][$pro_key]['sku_title'] = $sku_result['title'];
                    $totalQty += $product['qty'];
                    $data['price_total'] += $product['price'];
                }
                //$data['total_qty_ordered'] = isset($data['qty'])?$data['qty']:$totalQty;

//                $repeatWhere = array(
//                    'tel'=>array('EQ',$_POST['tel']),
//                    'first_name'=>array('EQ',$_POST['first_name']),
//                    //'create_at'=>array('EGT',date('Y-m-d H:i:s',strtotime("-2 month"))),
//                );
                $repeatWhere['tel'] = array('EQ',$_POST['tel']);
                $repeatWhere['first_name'] = array('EQ',$_POST['first_name']);
                $repeatWhere['_logic'] = 'or';

                $countOrder  = D("Order/Order")->field('id_order')->where($repeatWhere)->getField('id_order',true);
                if($countOrder){
                    $countItem = 0;$isAttr = false;
                    foreach($tempProId as $proId){
                        $isAttr = true;
                        $getModel = $getSkuModel[$proId];
                        $where  = array('id_order'=>array('IN',$countOrder),'id_product'=>$proId,'id_product_sku'=>$getModel);
                        $countItem = $countItem+ D("Order/OrderItem")->where($where)->group('id_order')->count();
                    }

                    $data['order_repeat'] = count($getSkuModel)?$countItem:count($countOrder);
                    $data['order_count'] = count($countOrder)+1;
                }else{
                    $data['order_repeat'] = 0;$data['order_count'] = 1;
                }

                $order_id = D("Order/Order")->data($data)->add();
                if($order_id){
                    $insert_info = array('id_order'=>$order_id,'ip'=>$data['ip'],'user_agent'=>$data['user_agent']);
                    D("Order/OrderInfo")->data($insert_info)->add();

                    $order_data = $order_id?D("Order/Order")->find($order_id):'';
                    /** @var \Order\Model\OrderRecordModel  $order_record */
                    $order_record = D("Order/OrderRecord");
                    $order_record->addHistory($order_id,1,'未处理订单');
                    $status= true;$message= '成功提交订单';
                    $this->create_item($order_id,$data);//建立产品信息

                    if($order_data&& is_array($order_data)){
                        $order_data['web_url']   = $data['web_url'];
                        $order_data['product_name'] = $temp_pro_title?implode("\r\n",$temp_pro_title):'';
                        $order_data['products']  = $data['products'];
                        if(!$data['payment_method']){
                            $this->create_email($order_data);
                        }
                    }
                }
            }else{
                $status= false;$message= '请求错误';
            }
        }catch (\Exception $e){
            $status= false;$message= $e->getMessage();
        }
        $returnData = array('status'=>$status,'message'=>$message,'order_id'=>$order_id,'data'=>$order_data);
        return json_encode($returnData);
    }

    //异常信息
    static public function abnormal_information($id_order) {
        $href = 'facebook.com';
        $href1 = 'msiela.com';
        $href2 = 'yahoo.com';
        $href3 = 'google';
        $order = M('Order')->where(array('id_order'=>$id_order))->find();
        $zone = M('Zone')->where(array('id_zone'=>$order['id_zone']))->find();
        $order_info = M('OrderInfo')->where(array('id_order'=>$id_order))->find();
        $web_infos = unserialize(htmlspecialchars_decode($order['web_info']));
        $flag = 1;
        if(!empty($web_infos)) {
            if ($web_infos['device'] == 'pc') {
                $device = 'PC端';
            }
            if ($web_infos['orderSubmitTimer'] < 10) {
                $flag = 0;
                $stay_time = '停留时间少于10s';
            }
        }
        if((strpos($order_info['ip_address'],$zone['title']) === false)) {
            $flag = 0;
            $zone_msg = '下单IP地址对应的地区不一致';
        }
        if((strpos($order['http_referer'],$href3) === false) && (strpos($order['http_referer'],$href) === false) && (strpos($order['http_referer'],$href1) === false) && (strpos($order['http_referer'],$href2) === false)) {
//            $flag = 0;
            $ref_msg = '来源不是facebook，建站系统，雅虎和谷歌网站';
        }

        return array('flag'=>$flag,'device'=>$device,'stay_time'=>$stay_time,'zone_msg'=>$zone_msg,'ref_msg'=>$ref_msg);
    }

    /**
     * 建立订单产品
     * @param $order_id
     * @param $data
     */
    public function create_item($order_id,$data){
        if(isset($data['products']) && is_array($data['products'])){//插入产品表，后面做产品表跟订单表关联
            foreach($data['products'] as $product){
                $product           = $this->filter_post_html($product);
                $product['attrs']  = $this->filter_post_html($product['attrs']);
                $product['attrs_title']  = $this->filter_post_html($product['attrs_title']);
                $product_id = $product['id_product'];
                $get_number = isset($product['number'])?$product['number']:1;
                $product['total']      = $product['price']*$product['qty'];
                $product['id_product'] = $product_id;
                $product['quantity']   = $get_number*$product['qty'];
                $product['attrs']      = is_array($product['attrs'])?serialize($product['attrs']):$product['attrs'];
                $product['attrs_title']      = is_array($product['attrs_title'])?serialize($product['attrs_title']):$product['attrs_title'];
                $product['id_order']   = $order_id;
                $product['sorting']    = 0;
                switch($data['id_zone']){
                    case 3:
                        $product['sorting']    = 10;
                        break;
                    case 9:
                        $product['sorting']    = 8;
                        break;
                    default:
                        $product['sorting']    = 0;
                }
                D("Common/OrderItem")->data($product)->add();

                $tempProTitle[] = $product['product_title'];
            }
        }
        //D("Common/Order")->saveStock($result);//订单减库存
    }
    public function create_email($order){
        //TODO: 需要不同国家的邮箱模板
        $host = sp_trim_host($order['web_url']);
        $domain = D('Domain/Domain')->get_domain($host);
        $prefix = str_replace('www.', '', $host);
        $prefix = strtoupper(substr($prefix, 0, 3));
        $order_no = $order['id_increment'];
        if ($domain) {
            $subject = '訂單信息--'.$host;
            $content = "\r\n";
            $content .= sprintf("親愛的 %s 感謝您的訂購以下是訂單信息:\r\n", $order['first_name'].$order['last_name']);
            $content .= '訂單編號:'."#".$prefix.$order_no."\r\n";
            $content .= "產品名:".$order['product_name']."\r\n";
            $content .= "應付價格:".Currency::format($order['price_total'],$order['currency_code'])."\r\n";
            $content .= "付款方式:貨到付款\r\n";
            $content .= "訂單時間:".date('Y-m-d H:i:s')."\r\n";
            $content .= "我們來自"."http://".$host."\r\n";

            $mail_data = array(
                'id_department'=> $order['id_department'],
                'name' => $domain['name'],
                'from_name' => $domain['name'],
                'from_addr' => $domain['smtp_user'],
                'to_name' => $order['first_name'],
                'to_addr' => $order['email'],
                'subject' => $subject,
                'content' => $content,
                'smtp_host' => $domain['smtp_host'],
                'smtp_user' => $domain['smtp_user'],
                'smtp_pwd' => $domain['smtp_pwd'],
                'smtp_port' => $domain['smtp_port'],
                'smtp_ssl' => $domain['smtp_ssl'],
                'is_html' => '0',
                'err_count' => '0',
                'err_msg' => '',
                'status' => '1',
                'type' => '1',
            );
            sp_add_email_queue($mail_data);
        } else {
            //TODO: 邮件添加失败记录
            $message = '邮件发送添加失败';
        }
    }

    /**
     * 过滤html标签
     * @param $data
     * @return array
     */
    public function filter_post_html($data){
        if(is_array($data)){
            foreach($data as $key=>$value){
                $data[$key]=is_array($value)?$value:htmlspecialchars(strip_tags($value));
            }
        }
        return $data;
    }
}