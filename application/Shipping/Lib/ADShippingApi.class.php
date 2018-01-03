<?php
namespace Shipping\Lib;

/**
 * 艾迪物流接口
 */
class ADShippingApi extends ShippingApi
{
    protected $order_data = array();
    protected $wave_number;
    protected $urls = array(
        'send_order' => 'http://api.aprche.net/OpenWebService.asmx/Insure_Waybill',
    );
    protected $app_key;
    protected $app_secret;

    public function __construct(){
        $this->app_key = '89BD1457C44C46478E6FBAF83257BE4D';
        $this->app_secret = '8ECBD810B21E48F693A375087F0B5392';
    }

    public function send_order($wave_number){
        $this->wave_number = $wave_number;
        $order_data = $this->generate_order_data();
        $url = $this->urls['send_order'];
        $post_data = "AppKey={$this->app_key}&AppSecret={$this->app_secret}&ToKenCategory=7&WaybillnfoXml={$order_data}";
        $result = $this->post_data($url, $post_data);
        return $this->result_handler($result);
    }

    private function post_data($url,$data=array()){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    protected function result_handler($result){
        if(empty($result)){
            $this->error = '没有返回数据';
        }
        $result_arr = $this->xml2arr($result);
        if(empty($result_arr)){
            $this->error = '无效返回:'.$result;
        }else{
            if($result_arr['Result'] != '处理成功'){
                $this->error = '返回错误:'.$result_arr['Result'];
            }
        }
        if(!empty($this->error)) return false;

        return true;
    }

    protected function generate_order_data(){
        $data = M('OrderWave')->alias('ow')
            ->field("o.*, ow.track_number_id,os.track_number")
            ->join("__ORDER__ AS o ON o.id_order=ow.id_order")
            ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
            ->where(array("ow.wave_number" => array('EQ', $this->wave_number)))
            ->where(array("os.has_sent"=>0))
            ->select();

        if(empty($data)){
            $this->error = "所有运单都已发送给物流";
            return false;
        }

        $xml = '<InsertWaybillService>';

        foreach($data as $row) {
            if($row['id_warehouse'] == 14){
                $freight_way = 40001;  //迪拜仓发货
            }else{
                $freight_way = 40002;  //深圳仓发货
            }

            $items = M('OrderItem')->alias('oi')
                ->join("__PRODUCT_SKU__ as ps ON oi.id_product_sku = ps.id_product_sku")
                ->where(array('id_order'=>$row['id_order']))
                ->select();
            $item_xml = '<CustomsInfo>';
            foreach ($items as $key => $value) {
                $item_xml .= <<< str
<ProductName_CN>{$value['product_title']}</ProductName_CN>
<ProductName_EN>{$value['sale_title']}</ProductName_EN>
<DeclareQuantity>{$value['quantity']}</DeclareQuantity>
<DeclarePrice>{$value['price']}</DeclarePrice>
<ProductSKU>{$value['sku']}</ProductSKU>
str;
            }
            $item_xml .= '</CustomsInfo>';
            $order_xml = '<WaybillInfo>';
            $order_xml .= <<< str
<CustomerWaybillNumber>{$row['id_increment']}</CustomerWaybillNumber>
<ServiceNumber>{$row['track_number']}</ServiceNumber>
<FreightWayId>{$freight_way}</FreightWayId>
<BuyerFullName>{$row['first_name']} {$row['last_name']}</BuyerFullName>
<BuyerAddress>{$row['province']}{$row['city']}{$row['address']}</BuyerAddress>
<BuyerPhone>{$row['tel']}</BuyerPhone>
<BuyerCountryName_EN>DUBAI</BuyerCountryName_EN>
<ProductName_CN>{$items[0]['product_title']}</ProductName_CN>
<ProductName_EN>{$items[0]['sale_title']}</ProductName_EN>
<DeclareQuantity>{$row['total_qty_ordered']}</DeclareQuantity>
<DeclarePrice>{$row['price_total']}</DeclarePrice>
<CustomsInfoList>{$item_xml}</CustomsInfoList>
str;
            $order_xml .= '</WaybillInfo>';
            $xml .= $order_xml;
        }
        $xml .= '</InsertWaybillService>';
        return $xml;
    }

}