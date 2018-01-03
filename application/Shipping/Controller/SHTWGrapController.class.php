<?php
namespace Shipping\Controller;
use Common\Controller\AppframeController;
use SystemRecord\Model\SystemRecordModel;
header("content-Type:text/html;charset=utf-8");
class SHTWGrapController extends AppframeController {
    private $lock_write_data = 'lock_shtw_data.lock';
    protected $urls = array(
        'send_order' => 'https://www.hct.com.tw/phone/searchGoods_Main_Xml.ashx',
    );
    public function _initialize() {
        parent::_initialize();
    }
    /*
     * 新竹台湾物流状态抓取
     */
    public function grap(){
//        echo 1;die;
        ini_set('max_execution_time', 0);
        $url = $this->urls['send_order'];
//        if (file_exists(CACHE_PATH.$this->lock_write_data)) {
//            echo '已经有进程正在运行';
//            exit;
//        }
        try {
//            file_put_contents(CACHE_PATH.$this->lock_write_data, 'lock');
            $data = date('Y-m-d 00:00:00',strtotime('-41 days'));
            $where['os.id_shipping'] = array('exp',' IN (29,94,96)');
            $where['o.date_delivery'] = array('GT', $data);
            $where['os.track_number'] = array('exp','is not null');
            $where['o.id_order_status'] = array('Not In','4,5,7,16,22');
            $where['_string'] = "os.summary_status_label != '順利送達' or os.summary_status_label is null";
            $count =  M('Order')->alias('o')
                ->field("o.*,os.track_number")
                ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                ->where($where)
                ->count();
//            var_dump($count);die;

            for($i = 0;$i<$count||$i<$count+100;$i+=100){
                $data = '';
                $data =  M('Order')->alias('o')
                    ->field("o.*,os.track_number")
                    ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                    ->where($where)
                    ->order('os.fetch_count, o.date_delivery')
                    ->limit($i,100)
                    ->getField('track_number',true);
//                file_put_contents('5.txt',json_encode($data),FILE_APPEND);
                if(empty($data)) {
                    echo '循环结束';die;
                }
                $xml = '<?xml version="1.0" encoding="utf-8"?>';
                $xml.= '<qrylist>';
                foreach($data as $key=>$value){
                    $xml .= <<< str
<order orderid="{$value}"></order>
str;
                }
                $xml .= '</qrylist>';
                $no = $this->encrypt($xml);
                $post_data = array(
                    'no'=>$no,
                    'v'=>'553CD428F23923DB890938AE8C393CE6'
                );
                $result= $this->post($url, $post_data);
                $result=$this->result_handler($result);
                $count =  M('Order')->alias('o')
                    ->field("o.*, ow.track_number_id,os.track_number")
                    ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                    ->where($where)
                    ->count();
            }

            echo '完成';
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        finally {
            if (file_exists(CACHE_PATH.$this->lock_write_data)) {
                unlink(CACHE_PATH.$this->lock_write_data);
            }
        }
    }
    /*
     * 抓取结果写入系统
     */
    public function shipping_grap($status,$grap_status,$track_number,$sign_time = ''){
        $shipping_api_name = "\\Shipping\\Lib\\ShippingApi";
        $shipping_api_model = new $shipping_api_name();
        $shipping_api_model->track_number = $track_number;
        $shipping_api_model->status_name = $grap_status;
        switch($status){
            case '派送中':
                $shipping_api_model->status_type = '派送中';
                break;
            case '送達':
                $shipping_api_model->status_type = '順利送達';
                break;
            case '問題件':
                $shipping_api_model->status_type = '問題件';
                break;
            case '拒絕':
                $shipping_api_model->status_type = '拒收';
                break;
            case '签收':
                $shipping_api_model->status_type = '順利送達';
                break;
        }
        $result = $shipping_api_model->update_status($sign_time);
    }
    /*
     * 结果处理
     */
    protected function result_handler($result,$type = 1){
        $return = array();
        $xml = $this->decrypt($result);
        $obj = simplexml_load_string($xml);
        $result = json_decode(json_encode($obj),TRUE);
        //file_put_contents('6.txt',json_encode($result),FILE_APPEND);
        foreach($result['orders'] as $key=>$v){

            if(is_array($v['order'])){

                foreach($v['order'] as $vv){
                    $track_number = $vv['@attributes']['orderid'];
                    $grap_status = $vv['@attributes']['status'];
                    $wrktime = $vv['@attributes']['wrktime'];
                    if (!$track_number && !$grap_status && !$wrktime) {
                        $track_number = $vv['orderid'];
                        $grap_status = $vv['status'];
                        $wrktime = $vv['wrktime'];
                    }

                    if(strpos($grap_status,'配送中')!==false||strpos($grap_status,'已抵達')!==false||strpos($grap_status,'途中')!==false || strpos($grap_status,'客戶不在') !== false){
                        $status = '派送中';
                        break;
                    }
                    elseif(strpos($grap_status,'送達。貨物件數')!==false){
                        $status = '签收';
                        break;
                    }
                    elseif(strpos($grap_status,'所保管中')!==false){
                        if(strpos($grap_status,'取收回')!==false||strpos($grap_status,'拒絕')!==false){
                            $status = '拒絕';
                            break;
                        }

                    }
                    elseif(strpos($grap_status,'請撥空領取')!==false){
                          $status = '派送中';
                        break;
                    }
                    elseif(strpos($grap_status,'取收回')!==false||strpos($grap_status,'拒絕')!==false||strpos($grap_status,'已退回')!==false){
                        $status = '拒絕';
                        break;
                    }
                }

                if($type = 1){
                    $this->shipping_grap($status,$grap_status,$track_number,$wrktime);
                }
            }
            echo $track_number."--于".$wrktime."--".$grap_status."--".$status."<br/>";
        }
    }
    /*
   * no加密
   */
    public function encrypt($input) {
        $date = date('Ymd');
        $key = date('Ymd',strtotime("$date +76 day"));
        $iv = 'QJWOQPAC';  //$iv为加解密向量
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
        $key = date('Ymd',strtotime("$date +76 day"));
        $iv = 'QJWOQPAC';  //$iv为加解密向量
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
    protected function post($url,$data=array(),$ssl=true){
        $ch = curl_init();
        if ($ssl){
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT,1800);
        if(is_array($data)){
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode($data));
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    private function returnResult($error=0, $message='', $data = array()){
        header("content-type: application/json");
        $result['error'] = $error;
        $result['message'] = $message;
        if(!empty($data)){
            $result['data'] = $data;
        }
        echo json_encode($result);exit;
    }
    public function kill_lock(){
        if (file_exists(CACHE_PATH.$this->lock_write_data)) {
            unlink(CACHE_PATH.$this->lock_write_data);
            echo '删除成功';
        }
    }



    //临时用的 查询物流
    function checkStatus(){
        if($_POST['no_data']){

            ini_set('max_execution_time', 0);
            $url = $this->urls['send_order'];

            $no_data_array = preg_split("~[\r\n]~", $_POST['no_data'], -1, PREG_SPLIT_NO_EMPTY);;

            $xml = '<?xml version="1.0" encoding="utf-8"?>';
            $xml.= '<qrylist>';

        foreach($no_data_array as $key=>$value){
            $xml .= <<< str
<order orderid="{$value}"></order>
str;
           }
            $xml .= '</qrylist>';
            $no = $this->encrypt($xml);
            $post_data = array(
                'no'=>$no,
                'v'=>'553CD428F23923DB890938AE8C393CE6'
            );
            $result= $this->post($url, $post_data);

            $result=$this->result_handler($result,2);
        }else{
            $html_str = "<div><form action='' method='post'>
            <span>快递单号:</span><br/>
            <textarea id='no_data' name='no_data' style='width:250px;height:300px'></textarea>
            <input type='submit' value='提交'>
            </form></div>";
            echo $html_str;
        }

    }

}
