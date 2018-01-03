<?php
namespace Shipping\Controller;
use Common\Controller\AppframeController;
use SystemRecord\Model\SystemRecordModel;
header("content-Type:text/html;charset=utf-8");
class CXCGrapController extends AppframeController {
    private $lock_write_data = 'lock_cxc_data.lock';
    protected $urls = array(
        'send_order' => 'http://42.3.224.83:7028/BGNCXCInterfaceNew/searchScanInfo.do',
    );
    public function _initialize() {
        parent::_initialize();
    }
    /*
     * CXC物流状态抓取
     */
    public function grap(){
        $url = $this->urls['send_order'];
//        if (file_exists(CACHE_PATH.$this->lock_write_data)) {
//            echo '已经有进程正在运行';
//            exit;
//        }
        try {
            $data = date('Y-m-d 00:00:00',strtotime('-2 month'));
//            file_put_contents(CACHE_PATH.$this->lock_write_data, 'lock');
            $where['o.id_shipping'] = array('EQ', 65);
            $where['o.date_delivery'] = array('GT', $data);
            $where['os.track_number'] = array('exp','is not null');
            $where['o.id_order_status'] = array('Not In','4,5,7,22');
//            $where['os.track_number'] = array('EQ','10170719111448933');
            $where['_string'] = "os.summary_status_label <>'順利送達' or os.summary_status_label is null";
            $count =  M('Order')->alias('o')
                ->field("o.*, ow.track_number_id,os.track_number")
                ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                ->where($where)
                ->count();
            for($i = 0;$i<$count||$i<$count+100;$i+=100){
                $data = '';
                $data =  M('Order')->alias('o')
                    ->field("o.*, ow.track_number_id,os.track_number")
                    ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                    ->where($where)
                    ->order('o.date_delivery ASC')
                    ->limit($i,100)
                    ->getField('track_number',true);
                $post_data = array(
                    'company_id'=>'98171006',
                    'data'=>json_encode($data)
                );
                $result= $this->post($url, $post_data);
                $result = json_decode($result,true);
                //抓取到物流的处理
                $shipping_api_name = "\\Shipping\\Lib\\ShippingApi";
                $shipping_api_model = new $shipping_api_name();
                if($result['success']== true&&$result['data']){
                    foreach($result['data'] as $key=>$value){
                        $shipping_api_model->track_number = $key;
                        switch($value[0]['scanType']){
                            case '完成':
                            $shipping_api_model->status_type = '順利送達';
                            $shipping_api_model->status_name = $value[0]['description'];
                            break;
                            case '收件':
                                $shipping_api_model->status_type = '派送中';
                                $shipping_api_model->status_name = $value[0]['description'];
                                break;
                            case '入场':case'入場':
                                $shipping_api_model->status_type = '派送中';
                                $shipping_api_model->status_name = $value[0]['description'];
                                break;
                            case '出场':case '出場':
                                $shipping_api_model->status_type = '派送中';
                                $shipping_api_model->status_name = $value[0]['description'];
                                break;
                            case '问题件':case'問題件':
                                if(strpos($value[0]['description'],'拒收')){
                                    $shipping_api_model->status_type = '拒收';
                                    $shipping_api_model->status_name = $value[0]['description'];
                                    break;
                                }elseif(strpos($value[0]['description'],'退貨完成')){
                                    $shipping_api_model->status_type = '退貨完成';
                                    $shipping_api_model->status_name = $value[0]['description'];
                                    break;
                                }else{
                                    $shipping_api_model->status_type = '派送中';
                                    $shipping_api_model->status_name = $value[0]['description'];
                                    break;
                                }
                            default:
                                $shipping_api_model->status_type = '未上綫';
                                $shipping_api_model->status_name = '';
                                break;
                        }
                        $res = $shipping_api_model->update_status( $value[0]['eventDate']);
                    }
                }
                $count =  M('Order')->alias('o')
                    ->field("o.*, ow.track_number_id,os.track_number")
                    ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                    ->where($where)
                    ->count();
                sleep(3);
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

    protected function post($url,$data=array(),$ssl=true){
        $ch = curl_init();
        if ($ssl){
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        if(is_array($data)){
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode($data));
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
