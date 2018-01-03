<?php
namespace Shipping\Lib;
header("content-Type:text/html;charset=utf-8");
/**
 * 森鸿台湾物流接口
 */
class SHTWShippingApi extends ShippingApi
{
    protected $order_data = array();
    protected $wave_number;
    protected $urls = array(
        'send_order' => 'https://www.hct.com.tw/phone/searchGoods_Main_Xml.ashx',
    );
    protected $app_key;
    protected $app_secret;
    public function __construct(){
        $this->app_key = '';
        $this->app_secret = '';
    }

    public function send_order($wave_number){
        $this->wave_number = $wave_number;
        $order_data = $this->generate_order_data();
        $url = $this->urls['send_order'];
        if($order_data){
            //构建xml文件，分次发送
            $new = array();
            $count = 0;
            $temp= array_slice($order_data,$count*40,40);
            while($temp){
                $xml = '<?xml version="1.0" encoding="utf-8"?>';
                $xml.= '<qrylist>';
                foreach($temp as $key=>$value){
                    $xml .= <<< str
<order orderid="{$value}"></order>
str;
                }
                $xml .= '</qrylist>';
                $no = $this->encrypt($xml);
                $post_data = array(
                    'no'=>$no,
                    'v'=>'7856A92C813BFEE003AAEB434545ACC3'
                );
                $result= $this->post($url, $post_data);
                $new+=$this->result_handler($result);
                ++$count;
                $temp = array_slice($order_data,$count*40,40);
            }
            if(!$new){
                return false;
            }else{
                return $new;
            }
        }
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
        $xml = simplexml_load_string($this->decrypt($result));
        $result = json_decode(json_encode($xml),TRUE);
        $return = array();
        if(empty($result)){
            $this->error = '没有返回数据';
            return false;
        }else{
            if(count($result['orders'])==1){
                $return[ $result['orders']['@attributes']['ordersid']] = $result['orders']['@attributes']['ordersid'];
            }else{
                foreach($result['orders'] as $key=>$v){
                    $return[$v['@attributes']['ordersid']] = $v['@attributes']['ordersid'];
                }
            }

        }
        return $return;
    }

    protected function generate_order_data(){
        $items = array();
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
        foreach($data as $row) {
            $items[]= M('OrderShipping')->alias('os')
                ->where(array('id_order'=>$row['id_order']))
                ->getField('track_number');
        }
        return $items;
    }

    /*
     * no加密
     */
    public function encrypt($input) {
        $date = date('Ymd');
        $key = date('Ymd',strtotime("$date +40 day"));
        $iv = 'VKXHKJVG';  //$iv为加解密向量
        $size = 8; //填充块的大小,单位为bite    初始向量iv的位数要和进行pading的分组块大小相等!!!
        $input = $this->pkcs5_pad($input, $size);  //对明文进行字符填充
        $td = mcrypt_module_open(MCRYPT_DES, '', 'cbc', '');    //MCRYPT_DES代表用DES算法加解密;'cbc'代表使用cbc模式进行加解密.
        mcrypt_generic_init($td, $key, $iv);
        $data = mcrypt_generic($td, $input);    //对$input进行加密
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $data = base64_encode($data);   //对加密后的密文进行base64编码
        return $data;
    }
    /*
    * 对明文进行给定块大小的字符填充
    */
    public function pkcs5_pad($text, $blocksize) {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    /*
    * 在采用DES加密算法,cbc模式,pkcs5Padding字符填充方式,对密文进行解密函数
    */
    public function decrypt($crypt) {
        $crypt = base64_decode($crypt);   //对加密后的密文进行解base64编码
        $date = date('Ymd');
        $key = date('Ymd',strtotime("$date +40 day"));
        $iv = 'VKXHKJVG';  //$iv为加解密向量
        $td = mcrypt_module_open(MCRYPT_DES, '', 'cbc', '');    //MCRYPT_DES代表用DES算法加解密;'cbc'代表使用cbc模式进行加解密.
        mcrypt_generic_init($td, $key, $iv);
        $decrypted_data = mdecrypt_generic($td, $crypt);    //对$input进行解密
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $decrypted_data = $this->pkcs5_unpad($decrypted_data); //对解密后的明文进行去掉字符填充
        $decrypted_data = rtrim($decrypted_data);   //去空格
        return $decrypted_data;
    }

    public function pkcs5_unpad($text) {
        $pad = ord($text{strlen($text)-1});
        if ($pad>strlen($text))
            return false;
        return substr($text,0, -1 * $pad);
    }
}