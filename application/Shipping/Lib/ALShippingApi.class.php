<?php
namespace Shipping\Lib;

/**
 * 阿里物流接口
 */
class ALShippingApi extends ShippingApi
{
    protected $order_data = array();
    protected $custumer_id;
    protected $custumer_userid;
    protected $wave_number;
    protected $urls = array(
        'auth' => 'http://www.sz56t.com:8082/selectAuth.htm?username=TEST&password=123456',
        'send_order' => 'http://www.sz56t.com:8082/createOrderApi.htm',
    );

    public function __construct(){
        $this->custumer_id = 17301;
        $this->custumer_userid = 13761;
//        $this->_auth();
    }

    public function send_order($wave_number){

        $this->wave_number = $wave_number;
        $order_data = $this->generate_order_data();

        if(empty($order_data)){
            return false;
        }

        return $this->batch_post($this->urls['send_order'], $order_data);
    }

    protected function batch_post($url, $data, $retry = 0){
        // 创建批处理cURL句柄
        $mh = curl_multi_init();
        $ch = [];
        $result = [];
        $error = [];

        //初始化
        foreach($data as $key => $row){
            $ch[$key] = curl_init();
            curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch[$key], CURLOPT_POST, 1);
            curl_setopt($ch[$key], CURLOPT_POSTFIELDS, array('param'=>$row));
            curl_setopt($ch[$key], CURLOPT_URL, $url);
            curl_multi_add_handle($mh,$ch[$key]);
        }

        //执行
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        //结果处理
        foreach($ch as $key => $value){
            $result[$key] = $this->result_handler(curl_multi_getcontent($value));
            if(empty($result[$key])){
                unset($result[$key]);
                $error[$key] = "订单{$key}发送错误:" . $this->error;
            }
            curl_multi_remove_handle($mh, $value);
        }
        curl_multi_close($mh);

        if(!empty($error)){
            $this->error = $error;
        }
        return $result;
    }

    protected function generate_order_data(){
        $order_data = [];

        $data = M('OrderWave')->alias('ow')
            ->field("o.*, os.weight, group_concat(oi.id_order_item) as items")
            ->join("__ORDER__ AS o ON o.id_order=ow.id_order")
            ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order")
            ->join("__ORDER_ITEM__ AS oi ON oi.id_order=ow.id_order", 'LEFT')
            ->where(array("ow.wave_number" => array('EQ', $this->wave_number)))
            ->where(array("os.has_sent"=>0))
            ->group('oi.id_order')
            ->select();

        if(empty($data)){
            $this->error = "所有运单都已发送给物流";
            return false;
        }

        foreach($data as $row) {

            $items = M('OrderItem')
                ->where("id_order_item IN ({$row['items']})")
                ->select();

            foreach ($items as $key => $value) {
                $items[$key] = array(
                    'invoice_amount' => $value['price_total'],
                    'invoice_pcs' => $value['total_qty_ordered'],
                    'invoice_title' => $value['sale_title'],
//                    'invoice_weight' => $value['address'],
//                    'item_transactionid' => $value['zipcode'],
                    'sku_code' => $value['sku'],
                );
            }

            /**
             *  URL1/createOrderApi.htm?param=
             * {
             * "buyerid":"",
             * "consignee_address":"收件地址街道，必填",
             * "consignee_city":"城市",
             * "consignee_mobile":"手机号,选填。为方便派送最好填写",
             * "order_returnsign":"退回标志，默认N表示不退回，Y标表示退回。中邮可以忽略该属性",
             * "consignee_name":"收件人,必填",
             * "trade_type":"ZYXT",
             * "consignee_postcode":"邮编，有邮编的国家必填",
             * "consignee_state":"州/省",
             * "consignee_telephone":"收件电话，必填",
             * "country":"收件国家二字代码，必填",
             * "customer_id":"客户ID，必填",
             * "customer_userid":"登录人ID，必填",
             * "orderInvoiceParam":
             * [
             * {"invoice_amount":"申报总价值，必填",
             * "invoice_pcs":"件数，必填",
             * "invoice_title":"英文品名，必填",
             * "invoice_weight":"单件重","item_id":"",
             * "item_transactionid":"",
             * "sku":"中文品名",
             * "sku_code":"配货信息",
             * "hs_code":"海关编码"}
             * ],
             * "order_customerinvoicecode":"原单号，必填",
             * "product_id":"运输方式ID，必填",
             * "weight":"总重，选填，如果sku上有单重可不填该项",
             * "product_imagepath":"图片地址，多图片地址用分号隔开",
             * "order_transactionurl":"产品销售地址",
             * "consignee_email":"邮箱，选填"
             * }
             */
            $post_data =
                array(
                'consignee_address' => $row['address'],
                'consignee_mobile' => $row['tel'],
//                'consignee_city' => $row['country'],
//                'order_returnsign' => $row['country'],
                'consignee_name' => $row['first_name'] . $row['last_name'],
                'trade_type' => 'ZYXT',
                'consignee_postcode' => $row['zipcode'],
//                'consignee_state' => $row['country'],
                'consignee_telephone' => $row['tel'],
                'country' => 'US',
                'customer_id' => $this->custumer_id,
                'customer_userid' => $this->custumer_userid,
                'orderInvoiceParam' => $items,
                'order_customerinvoicecode' => $row['id_increment'],
                'product_id' => 5402,
//                'weight' => $row['total_qty_ordered'],
            );
            $order_data[$row['id_increment']] = json_encode($post_data);
        }
        return $order_data;
    }

    protected function result_handler($result){
        if(empty($result)){
            $this->error = '没有返回数据';
        }
        $result_arr = json_decode(urldecode($result), true);
        if(empty($result_arr)){
            $this->error = '无效返回:'.$result;
            return false;
        }else{
            if(strtolower($result_arr['act']) != 'true'){
                $this->error = '返回错误:'.$result_arr['message'];
                return false;
            }else{
                $result = $result_arr['tracking_number'];
            }
        }
        return $result;
    }

    public function get_error(){
        if(is_array($this->error)){
            return implode(',', $this->error);
        }else{
            return $this->error;
        }
    }

    //无法解析，暂时不用
    private function _auth(){
        $url = $this->urls['auth'];
//        $url = sprintf($url, $this->key, $this->user_id, $this->password);
        $result = $this->post($url);
        $result = json_decode($result, true);
        $this->customer_id = $result['resultid'];
        $this->customer_userid = $result['resultid'];
    }

}