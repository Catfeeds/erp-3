<?php
namespace Oms\Controller;
use Think\Controller;
use Think\Exception;

class OmsController extends Controller{

    private $shopify_app_secret = '7289b6d8b64fc73594996da2f6a6c2767174e143e579a05e05e084011117d66f';
    public function index(){
        echo __METHOD__;
    }

    /**
     * API接口接收数据，插入中间表
     */
    public function insert_data(){
        $hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
        $data = file_get_contents('php://input');
        $verified = $this->verify_webhook($data, $hmac_header);
        if($verified) {
            $setPath = './' . C("UPLOADPATH") . 'oms_data/';
            if(!is_dir($setPath)){
                mkdir($setPath,0777,TRUE);
            }
            $logTxtFile = 'oms_data/' . date('Y-m-d') . '.txt';
            $getTime = date('Y-m-d H:i:s');
            $logContent = $getTime . ' || ' . $data . PHP_EOL . PHP_EOL;
            file_put_contents('./' . C("UPLOADPATH") . $logTxtFile, $logContent, FILE_APPEND);
//            $oms = D("Oms/Oms");
//            $result = $oms->insert_temp_order();//先写入中间表
//            echo $result;
            exit();
        } else {
            echo '验证失败，密钥不正确';
        }
    }

    //验证接口
    public function verify_webhook($data, $hmac_header)
    {
        $calculated_hmac = base64_encode(hash_hmac('sha256', $data, $this->shopify_app_secret, true));
        return ($hmac_header == $calculated_hmac);
    }
}