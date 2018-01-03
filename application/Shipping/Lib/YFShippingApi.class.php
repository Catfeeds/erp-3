<?php
namespace Shipping\Lib;

/**
 * 裕丰物流接口
 */
class YFShippingApi extends ShippingApi
{
    protected $order_data = array();
    protected $sign;
    protected $wave_number;
    protected $urls = array(
        'send_order' => 'http://218.16.117.83:8666/Api.aspx',
    );

    public function send_order($wave_number){

        $this->wave_number = $wave_number;

        $order_data = $this->generate_order_data();
        $order_data = $this->format($order_data);

        $url = $this->urls['send_order'];
        $post_data = array(
            'service' => 'tms_order_notify',
            'content' => $order_data,
        );
        $result = $this->post($url, $post_data);
        return $this->result_handler($result);
    }

    protected function result_handler($result){
        if(empty($result)){
            $this->error = '没有返回数据';
        }
        $result_arr = $this->xml2arr($result);
        if(empty($result_arr)){
            $this->error = '无效返回:'.$result;
        }else{
            if($result_arr['is_success'] == 'F'){
                $this->error = '返回错误:'.$result_arr['error'];
            }
        }
        if(!empty($this->error)) return false;

        return true;
    }

    /**
     * 格式化订单信息
     */
    protected function format($data){
        $result = '<request>';
        foreach($data as $row){
            $result .= $this->arr2xml($row, 'order');
        }
        $result .= '</request>';
        return $result;
    }

    protected function generate_order_data(){
        $order_data = [];

        $data = M('OrderWave')->alias('ow')
            ->field("o.*, ow.track_number_id")
            ->join("__ORDER__ AS o ON o.id_order=ow.id_order")
            ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
            ->where(array("ow.wave_number" => array('EQ', $this->wave_number)))
            ->where(array("os.has_sent"=>0))
            ->select();

        if(empty($data)){
            $this->error = "所有运单都已发送给物流";
            return false;
        }

        foreach($data as $row) {
            $order_data[] = array(
                'tms_service_code' => 010003,
                'order_code' => $row['track_number_id'],
                'receiver_name' => $row['last_name'] . $row['first_name'],
                'receiver_address' => $row['address'],
                'receiver_mobile' => $row['tel'],
                'pcs' => $row['total_qty_ordered'],
                'total_amount' => $row['price_total'],
                'remark' => $row['remark'],
            );
        }
        return $order_data;
    }

}