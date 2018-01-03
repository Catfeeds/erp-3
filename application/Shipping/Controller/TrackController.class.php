<?php
namespace Shipping\Controller;
use Common\Controller\HomebaseController;
use Common\Lib\Currency;

class TrackController extends HomebaseController {

    protected $shipping;

    public function _initialize() {
        parent::_initialize();
        $this->shipping = D("Common/Shipping");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /*
     * 接受嘉里推送信息
     */
    public function status() {
        $config = C('KERRYTJ_API_CONFIG');
        $logTxtFile = 'shipping/'.date('Y-m-d').'jl_api.txt';
        $getTime = date('Y-m-d H:i:s');
        $logContent = $getTime.' || '.json_encode($_REQUEST).PHP_EOL.PHP_EOL;
        file_put_contents('./'.C("UPLOADPATH").$logTxtFile,$logContent,FILE_APPEND);
        
        $data = is_array($_REQUEST)?$_REQUEST:json_decode($_REQUEST,true);
        //file_put_contents('./'.C("UPLOADPATH").$logTxtFile,$data,FILE_APPEND);
        if($_SERVER['HTTP_HOST']!='http://erp.msiela.com:9090'){
            $erp_url = 'http://erp.msiela.com:9090/shipping/track/status';
            $result = send_curl_request($erp_url,$_REQUEST);
            echo $result;exit();
        }

        $message  = '';
        if($data['Key']==$config['ERP_UPDATE_STATUS']){
            try{
                /** @var \Order\Model\OrderRecordModel  $order_record */
                $order_record = D("Order/OrderRecord");
                $order_model      = D("Common/Order");
                $order_ship_model = D("Common/OrderShipping");
                $order_sett_model = D("Common/OrderSettlement");
                $shipping = $data['Data'];
                if($shipping && is_array($shipping)){
                    foreach($shipping as $item){
                        $BLN        = htmlspecialchars(strip_tags($item['BLN']));
                        $Status     = trim(htmlspecialchars(strip_tags($item['Status'])));
                        $Date       = htmlspecialchars(strip_tags($item['Date']));
                        $Station    = htmlspecialchars(strip_tags($item['Station']));
                        $Status     = strpos($Status, '拒收')!== false?'拒收':$Status;
                        $order_ship = $order_ship_model->where(array('track_number'=>$BLN))->find();
                        $record_data = false;
                        if($order_ship && $order_ship['id_order']){
                            $order_where = array('id_order'=>$order_ship['id_order']);
                            $fetch_count = $order_ship['fetch_count']+1;
                            $order_ship_update = array(
                                'status_label' => $Status,
                                'updated_at'   => date('Y-m-d H:i:s'),
                                'fetch_count'  => $fetch_count,
                            );

                            switch($Status){
                                case '順利送達':
                                    $id_order_status = 9 ;
                                    $settlement = $order_sett_model->where($order_where)->find();
                                    if(!$settlement){
                                        $order = $order_model->find($order_ship['id_order']);
                                        $add_data = array(
                                            'id_users'=>0,
                                            'id_order_shipping' => $order['id_shipping'],
                                            'id_order' => $order['id_order'],
                                            'amount_total' => $order['price_total'],
                                            'amount_settlement' => 0,
                                            'created_at' => date('Y-m-d H:i:s'),
                                            'status' => 0,
                                        );
                                        $order_sett_model->data($add_data)->add();
                                    }
                                    $comment   = '嘉里->顺利送达';
                                    $order_update = array('id_order_status'=>$id_order_status);
                                    $order_ship_update['date_signed'] = $Date;
                                    $order_ship_update['summary_status_label'] = '順利送達';
                                    break;
                                case '拒收':
                                    $id_order_status = 16 ;
                                    $comment   = '嘉里->拒收';
                                    $order_update = array('id_order_status'=>$id_order_status,'refused_to_sign'=>1);
                                    $order_ship_update['summary_status_label'] = '拒收';
                                    break;
                                case '退貨完成':
                                    $id_order_status = 10 ;
                                    $comment   = '嘉里->已退货';
                                    $order_update = array('id_order_status'=>$id_order_status);
                                    $order_ship_update['date_return'] = $Date;
                                    $order_ship_update['summary_status_label'] = '退貨完成';
                                    break;
                                default:
                                    $status_label = $Status;
                                    $order_update = '';
                                    if(empty($order_ship['status_label'])){//上线时间
                                        $order_ship_update['date_online'] = $Date;
                                    }
                            }
                            if($order_update && is_array($order_update)){
                                $order_model->where($order_where)->save($order_update);
                                $record_data  = array(
                                    'id_order' => $order_ship['id_order'],
                                    'id_order_status' => $id_order_status,
                                    'type' => 1,
                                    'user_id' => 1,
                                    'comment' => $comment,
                                );
                                $order_record->addOrderHistory($record_data);
                            }
                            $order_ship_model->where(array('id_order_shipping'=>$order_ship['id_order_shipping']))
                                ->save($order_ship_update);
                        }else{
                            $message   .= $BLN.' 沒有找到運單號. ';
                        }
                    }
                    $array = array(
                        'status'=> 1,
                        'message' => $message,
                    );
                }else{
                    $array = array(
                        'status'=> 0,
                        'message' => 'Data 無數據',
                    );
                }
            }catch (\Exception $e){
                $array = array(
                    'status'=> 0,
                    'message' => $e->getMessage(),
                );
            }
        }else{
            $array = array(
                'status'=> 0,
                'message' => 'KEY碼錯誤',
            );
        }
        echo json_encode($array);
        exit();
    }
    public function update_refused_to_sign(){
        $where = array('status_label' =>array('LIKE','%拒收%'));
        $order_shipping = D('Order/OrderShipping')->where($where)->select();
        /** @var \Order\Model\OrdeItemModel $order_Model */
        $order_Model = D('Order/Order');
        foreach($order_shipping as $shipping){
            $order_id = $shipping['id_order'];
            $update_data = array('refused_to_sign'=>1);
            $order_Model->where(array('id_order'=>$order_id))->save($update_data);
        }
        echo 'OK';
    }
}
