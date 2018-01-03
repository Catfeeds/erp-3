<?php
/**
 * 订单接口
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Order\Controller
 */
namespace Order\Controller;
use Think\Controller;
use Common\Lib\Encrypt;
use Order\Model\UpdateStatusModel;
class ApiController extends Controller {
    public function index(){
        echo __METHOD__;
    }

    /**
     * API接口接收数据，建立订单
     */
    public function create_order(){
        $logTxtFile = 'PostOrderData/'.date('Y-m-d').'.txt';
        $getTime = date('Y-m-d H:i:s');
        $logContent = $getTime.' || '.json_encode($_POST).PHP_EOL.PHP_EOL;
        file_put_contents('./'.C("UPLOADPATH").$logTxtFile,$logContent,FILE_APPEND);
        /** @var \Order\Model\ApiModel $api */
        $api = D("Order/Api");
        //$result = $api->create_order();
        $result = $api->redis_temp_order($_POST);//先写入缓存表，然后写入订单表

        echo $result;
        exit();
    }

    /*
     * 先写入hk数据库 zhujie
     * */
    public function create_order_hk(){
        $logTxtFile = 'PostOrderData/'.date('Y-m-d').'.txt';
        $getTime = date('Y-m-d H:i:s');
        $logContent = $getTime.' || '.json_encode($_POST).PHP_EOL.PHP_EOL;
        file_put_contents('./'.C("UPLOADPATH").$logTxtFile,$logContent,FILE_APPEND);
        /** @var \Order\Model\ApiModel $api */
        $api = D("Order/Api");
        //$result = $api->create_order();
        $result = $api->redis_temp_order_hk($_POST);//先写入缓存表，然后写入订单表

        echo $result;
        exit();
    }

    /**
     * API接口接收数据，建立订单
     */
    public function new_create_order(){
        $logTxtFile = 'PostOrderData/'.date('Y-m-d').'-n.txt';
        $getTime = date('Y-m-d H:i:s');
        $post_data = file_get_contents("php://input");
        $logContent = $getTime.' || '.$post_data.PHP_EOL.PHP_EOL;
        file_put_contents('./'.C("UPLOADPATH").$logTxtFile,$logContent,FILE_APPEND);
        /** @var \Order\Model\ApiModel $api */
        $api = D("Order/Api");
        //$result = $api->create_order();
        $post_datas = json_decode($post_data, true);
        $result = $api->redis_temp_order($post_datas);
        echo $result;
        exit();
    }

	/**
	 * 接收加密订单
	 */
    public function create_order_encrypted(){
        $logTxtFile = 'PostOrderData/'.date('Y-m-d').'.txt';
        $getTime = date('Y-m-d H:i:s');
        $post_data = file_get_contents("php://input");
        $logContent = $getTime.' || '.$post_data.PHP_EOL.PHP_EOL;
        file_put_contents('./'.C("UPLOADPATH").$logTxtFile,$logContent,FILE_APPEND);
        $encrypt = new Encrypt();
        $encrypt->set_private_key(file_get_contents('./'.C("UPLOADPATH").'certification/priv_key.pem'));
        $post_data = $encrypt->privateDecrypt($post_data);
        if(empty($post_data)){
            echo json_encode(array('status'=>false, 'message'=>'无效数据'));
        }
        /** @var \Order\Model\ApiModel $api */
        $api = D("Order/Api");
        $post_data = json_decode($post_data, true);
        $result = $api->redis_temp_order($post_data);
        echo $result;
        exit();
    }

    /**
     * 支付通道，更新支付状态
     */
    public function payment(){
        try{
            $order_id = I('post.id/d');
            $key     = I('post.key');
            $md5    = md5($_POST['id'].$_POST['web_url']);
            $data  = $order_id;
            if($key==$md5){
                $data   = D("Common/Order")->where(array('id_increment'=>$order_id))->find();
                if($data){
                    $payment_order_no = strip_tags(I('post.orderNo/s'));
                    $payment_merch_order_no = strip_tags(I('post.merchOrderNo/s'));
                    $update = array();
                    if(trim($_POST['payment_status'])){
                        $update['payment_status'] = strip_tags(I('post.payment_status/s'));
                    }
                    if(trim($_POST['payment_details'])){
                        $update['payment_details'] = strip_tags(I('post.payment_details/s'));
                    }
                    if($payment_order_no){
                        $update['payment_order_no'] = $payment_order_no;
                    }
                    if($payment_order_no){
                        $update['payment_merch_order_no'] = $payment_merch_order_no;
                    }
                    if($update){
                        D("Common/Order")->where(array('id_increment'=>$order_id))->save($update);
                    }
                    $status= true;$message= '更新成功！';
                }else{
                    $status= false;$message= '找不到订单！';
                }
            }else{
                $status= false;$message= '请求错误！';
            }
        }catch (\Exception $e){
            $status= false;$message= $e->getMessage();
        }
        $returnData = array('status'=>$status,'message'=>$message);
        echo json_encode($returnData);
        exit();
    }

    public function post_data(){
        if (function_exists('curl_init')) {
            $time = time();
            $web_info = array(
                "colorDepth"=>"24",
                "browserLan"=>"简体中文",
                "httpHeads"=>array(
                    "HOST"=>"www.dzpas.com",
                )
            );
            $order_data = array(
                'key' => md5('www.asytx.com'.$time),
                'web_url' => 'www.asytx.com',
                'first_name' => '陳惠香',
                'last_name' => null,
                'tel' => '0987381349',
                'email' => '1489978916-test@qq.com',
                'address' => '南投縣埔里鎮中山路三段246號      八之一',
                'remark' => '测试订单',
                'zipcode' => null,//邮编
                'country' => '中国',
                'province' => '台灣',
                'city' => null,
                'area' => null,
                'products' => array(
                    array(
                        'id_product' => 5671,
                        'product_title' => 'ROWILCS輕奢時尚高端提花蕾絲連衣裙長裙大碼文藝范寬鬆舒適',
                        'sale_title' => 'ROWILCS輕奢時尚高端提花蕾絲連衣裙長裙大碼文藝范寬鬆舒適',
                        'price' => 1580,
                        'price_title' => 'NT$1580',
                        'qty' => 1,
                        'attrs' => array(
                            "25567",
                            "25575"
                        )
                    )
                ),
                //erp
                'id_zone' => '2',//地区 ID
                'id_department' => '14',
                'id_users' => '57',//广告手用户ID
                'identify'=>'57',//目前广告手用户ID
                'currency_code' => 'TWD',//货币代码
                'date_purchase'=> date('Y-m-d H:i:s'),
                'payment_method' => 0,//TF线上支付 代码， 货到付款 为了兼容之前ERP，留空
                'payment_status'=>'',//Pending:未支付， processing：已经支付   canceled：取消
                'payment_details' => '',
                'created_at' => $time, //int
                'ip' => '13.2.1.1',
                'user_agent' => '',
                'expends' => array(),
                'web_info' => serialize($web_info)
            );
            $send_url = 'http://www.erp.com/Order/Api/create_order/';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $send_url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($order_data));
            $response = curl_exec($ch);print_r($response);
            if (!curl_errno($ch)) {
                //$curl_info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $order_info = json_decode($response,true);
                //$order_id = $order_info['order_id'];
            } else {
                $curl_info = curl_error($ch);
            }

        }else{
            echo '不支持CURL';
        }
    }

    //查询订单接口
    public function sel_order() {
        try{
            $domain = I('param.name');
            $email = I('param.email');
            $date = I('param.date');
            if(!empty($domain) && !empty($email) && !empty($date)) {
                $day = strtotime($date);
                $createAtArray[] = array('EGT', date('Y-m-d 00:00:00',$day));
                $createAtArray[] = array('ELT', date('Y-m-d 23:59:59',$day));
                $where[] = array('date_purchase' => $createAtArray);
                $where[] = array('email'=>$email);
                $result = D('Domain/Domain')->field('id_domain')->where(array('name'=>array('like','%'.$domain.'%')))->find();
                $where[] = array('id_domain'=>$result['id_domain']);
                if($result) {
                    $order = M('Order')->field('id_increment')->where($where)->find();
                    $message= $order['id_increment'];
                } else {
                    $message = '找不到域名';
                }
            } else {
                $message = '参数不能为空';
            }
        }catch (\Exception $e){
            $message= $e->getMessage();
        }
        echo json_encode($message);exit();
    }

    /**
     * 前端建站取消订单
     */
    public function cancelOrder(){
        $return=array('status'=>0,'msg'=>'');
        $update_data=array('status_id'=>14,'comment'=>'【前端建站取消】');
        $statusarr=M('orderStatus')->where(array('status'=>1))->getField('id_order_status,title');
        $id_increment = I('request.id_increment');
        $id_increment=  trim($id_increment);
        if(empty($id_increment)){
            $return['status']=1000;
            $return['msg']='订单号不能为空！';
        }else{
            $orderinfo=M('order')->where(array('id_increment'=>$id_increment))->field('*')->find();
            if(empty($orderinfo)){
                $return['status']=1001;
                $return['msg']=sprintf(' %s 无法匹配该订单号信息！', $id_increment);
            }else if(!in_array($orderinfo['id_order_status'],array(1,3,22,4,5,7,6))){
                $return['status']=1002;
                $return['msg']=sprintf('%s 该订单状态为：%s, 不能进行取消处理！',  $id_increment,$statusarr[$orderinfo['id_order_status']]);
            }else{
                $update_data['id']=$orderinfo['id_order'];
                $updRes=UpdateStatusModel::cancel($update_data);
                $return['status']=1;                            //修改成功
                $return['msg']=sprintf('%s 取消订单成功！', $id_increment);
            }
        }
        echo json_encode($return);exit();
    }
}