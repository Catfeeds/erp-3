<?php
namespace Shipping\Lib;

/**
 * 商一物流接口
 */
class SYShippingApi extends ShippingApi
{
    protected $order_data = array();
    protected $user_id;
    protected $password;
    protected $key;
    protected $wave_number;
    protected $resultid;
    protected $urls = array(
        'get_resultid' => 'http://www.com1express.net/api/getAuth.html?key=%s&userid=%s&password=%s',
        'send_order' => 'http://www.com1express.net/api/CreateOrder.html?key=%s',
        'print_label' => 'http://www.com1express.net/api/getLabel.html?key=%s&ordernum=%s',
    );

    public function __construct(){
        $this->user_id = '610185';
        $this->password = '123654';
        $this->key = 'cbbc010cef1faebea848eabc928edb6c';
        $this->_get_resultid();
    }

    public function send_order($wave_number){

        $this->wave_number = $wave_number;
        $order_data = $this->generate_order_data();

        if(empty($order_data)){
            return false;
        }

        $url = sprintf($this->urls['send_order'], $this->key);
        return $this->batch_post($url, $order_data);
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
            curl_setopt($ch[$key], CURLOPT_POSTFIELDS, $row);
            curl_setopt($ch[$key], CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($row))
            );
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
            if (empty($row['zipcode'])) {
                $this->error = "订单{$row['id_increment']}没有邮编，无法生成运单";
                return false;
            }

            $items = M('OrderItem')
                ->where("id_order_item IN ({$row['items']})")
                ->select();

            $row['country'] = '';

            if ($row['id_zone'] == 7) {
                $row['country'] = 'SG';
            } elseif ($row['id_zone'] == 17){
                if($row['province'] = 'East Malaysia'){
                    $row['country'] = 'MY1';
                }elseif($row['province'] = 'West Malaysia'){
                    $row['country'] = 'MY2';
                }
            }

            if(empty($row['country'])){
                $this->error = "订单{$row['id_increment']}不是发送至马来西亚或者新加坡的或者没有区分东西马来西亚";
                return false;
            }

            foreach ($items as $key => $value) {
                $items[$key] = array(
                    "name" => $value['sale_title'],
                    "cname" => '',
                    "unit_price" => number_format($value['total']/$value['quantity'], 2, '.', ''),
                    "number" => $value['quantity']
                );
            }

            if(empty($row['payment_method'])){
                $COD = $row['price_total'];
            }else{
                $COD = 0;
            }
            $post_data = array_merge(array('items'=>$items), array(
                'resultid' => $this->resultid,
                'userid' => $this->user_id,
                'country' => $row['country'],
                'channel_id' => ($row['attr_id']==1) ? 355 : 356,  //特货:355 普货:356
                'count' => $row['total_qty_ordered'],
                'weight' => 1,
                'remark' => $row['remark'],
                'currencytype' => $row['currency_code'],
                'cod' => $COD,
                'consignee' => array(
                    'name' => $row['last_name'] . $row['first_name'],
                    'phone' => $row['tel'],
                    'address' => $row['address'],
                    'postcode' => $row['zipcode'],
                ),
            ));
            $order_data[$row['id_increment']] = json_encode($post_data);
        }
        return $order_data;
    }

    protected function result_handler($result){
        if(empty($result)){
            $this->error = '没有返回数据';
        }
        $result_arr = json_decode($result, true);
        if(empty($result_arr)){
            $this->error = '无效返回:'.$result;
            return false;
        }else{
            if(strtolower($result_arr['status']) != 'success'){
                $this->error = '返回错误:'.$result_arr['msg'];
                return false;
            }else{
                $result = $result_arr['ordernum'];
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

    private function _get_resultid(){
        $url = $this->urls['get_resultid'];
        $url = sprintf($url, $this->key, $this->user_id, $this->password);
        $result = $this->post($url);
        $result = json_decode($result, true);
        $this->resultid = $result['resultid'];
    }

}