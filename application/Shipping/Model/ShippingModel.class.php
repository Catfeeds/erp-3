<?php
namespace Shipping\Model;
use Common\Model\CommonModel;
class ShippingModel extends CommonModel{
    public function all($shipping_id = false) {
        $cache = F('getAllShippingList');
        if ($cache) {
            $shipping_list = unserialize($cache);
        } else {
            $shipping_list = array();
            $shipping =  D("Common/Shipping")->field('id_shipping,title')->cache(true, 600)->select();
            if ($shipping) {
                foreach ($shipping as $item) {
                    $shipping_list[$item['id_shipping']] = $item['title'];
                }
            }
            F('getAllShippingList', serialize($shipping_list));
        }
        $return = $shipping_id ? $shipping_list[$shipping_id] : $shipping_list;
        return $return;
    }

    /**
     * 发送订单信息到嘉里
     * @param $data
     */
    public function kerrytj_send_order($data,$is_special='0'){
        $config = C('KERRYTJ_API_CONFIG');
        $url = $config['REQUEST_URL'];//'http://adm.kerrytj.com/ked/api/RequestShipment.ashx';
        $key = $config['KEY'];//'78d2c66257dc48c8a78306f0e5287aab';
        $CustomerNo = $is_special==1?$config['SPECIAL_CUSTOMER_NO']:$config['CUSTOMER_NO'];//'22220123';
        if($data){
            $set_data = array(
                'ShipDate' => date('Ymd'),
                'CustomerNo' => $CustomerNo,
                'Key' => $key,
                'Data' => $data
            );
        }
        $result = send_curl_request($url,json_encode($set_data));
        if($result){
            $setPath = './'.C("UPLOADPATH").'PostOrderData'."/".date('Ym')."/";
            if(!is_dir($setPath)){
                mkdir($setPath,0777,TRUE);
            }
            $file = date('Ymd').'.txt';
            file_put_contents($setPath.$file,json_encode($set_data),FILE_APPEND);
        }

        return $result?json_decode($result,true):'';
    }
}

