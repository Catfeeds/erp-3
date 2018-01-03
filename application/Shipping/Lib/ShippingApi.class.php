<?php
namespace Shipping\Lib;
use \Order\Lib\OrderStatus;

/**
 * 物流接口公共类
 */
class ShippingApi
{

    protected $error;
    public $status_name;
    public $status_type;
    public $track_number;
    protected $id_order;

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

    public function arr2xml($arr, $root= 'xml'){
        $xml = "<$root>";
        foreach ($arr as $key=>$val){
            if(is_array($val)){
                $xml.="<".$key.">".$this->arr2xml($val)."</".$key.">";
            }else{
                $xml.="<".$key.">".$val."</".$key.">";
            }
        }
        $xml.="</$root>";
        return $xml;
    }

    public function xml2arr($xml){
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }

    public function get_error(){
        return $this->error;
    }

    public function get_status(){
        return true;
    }
    public function update_status($sign_time = ''){
        if(!method_exists($this, 'get_status')){
            $this->error='无法更新';
            return false;
        }
        if(!$this->get_status()) return false;
        $this->id_order = M('OrderShipping')->where(array('track_number' => $this->track_number))->getField('id_order');
        if(empty($this->id_order)){
            $this->error = '无效单号';
            return false;
        }
        switch($this->status_type){
            case '拒收':
                $res = $this->refuse();
                break;
            case '問題件':
                $res = $this->question();
                break;
            case '退貨完成':
                $res = $this->return_finish($sign_time);
                break;
            case '順利送達':
                $res = $this->received($sign_time);
                break;
            case '派送中':
                $res = $this->sending($sign_time);
                break;
            default:
                $res = $this->off_line();
                break;
        }
        if(empty($res)){
            $this->error = '更新状态失败';
            return false;
        }else{
            return $this->track_number;
        }
    }

    private function refuse()
    {
        M()->startTrans();
        $res1 = M('OrderShipping')->where(array('id_order' => $this->id_order))
            ->save(array(
                'status_label' => $this->status_name,
                'updated_at' => date('Y-m-d H:i:s'),
                'summary_status_label' => $this->status_type,
                'fetch_count' => array('exp','fetch_count+1')
            ));
        $res2 = M('Order')->where(array('id_order' => $this->id_order))
            ->save(array(
                'id_order_status' => OrderStatus::REJECTION,
                'refused_to_sign' => 1,
            ));
        $res3 = M('OrderRecord')->add(array(
            'id_users' => 1,
            'id_order' => $this->id_order,
            'id_order_status' => OrderStatus::REJECTION,
            'type' => 4,
            'user_name' => '系统',
            'desc' => '物流状态更新为拒收',
            'created_at' => date('Y-m-d H:i:s')
        ));
        if($res1 === false || $res2 === false || $res3 === false){
            M()->rollback();
            return false;
        }else{
            M()->commit();
            return true;
        }
    }
    private function question()
    {
        M()->startTrans();
        $res1 = M('OrderShipping')->where(array('id_order' => $this->id_order))
            ->save(array(
                'status_label' => $this->status_name,
                'updated_at' => date('Y-m-d H:i:s'),
                'summary_status_label' => $this->status_type,
                'fetch_count' => array('exp','fetch_count+1')
            ));
        $res2 = M('Order')->where(array('id_order' => $this->id_order))
            ->save(array(
                'id_order_status' => OrderStatus::REJECTION,
                'refused_to_sign' => 1,
            ));
        $res3 = M('OrderRecord')->add(array(
            'id_users' => 1,
            'id_order' => $this->id_order,
            'id_order_status' => OrderStatus::REJECTION,
            'type' => 4,
            'user_name' => '系统',
            'desc' => '物流状态更新为问题件',
            'created_at' => date('Y-m-d H:i:s')
        ));
        if($res1 === false || $res2 === false || $res3 === false){
            M()->rollback();
            return false;
        }else{
            M()->commit();
            return true;
        }
    }
    private function return_finish($sign_time= null){
        M()->startTrans();
        $res1 = M('OrderShipping')->where(array('id_order' => $this->id_order))
            ->save(array(
                'status_label' => $this->status_name,
                'date_return' => $sign_time,
                'updated_at' => date('Y-m-d H:i:s'),
                'summary_status_label' => $this->status_type,
                'fetch_count' => array('exp','fetch_count+1')
            ));
        $res2 = M('Order')->where(array('id_order' => $this->id_order))
            ->save(array(
                'id_order_status' => OrderStatus::RETURNED
            ));
        $res3 = M('OrderRecord')->add(array(
            'id_users' => 1,
            'id_order' => $this->id_order,
            'id_order_status' => OrderStatus::RETURNED,
            'type' => 4,
            'user_name' => '系统',
            'desc' => '物流状态更新为已退货',
            'created_at' => date('Y-m-d H:i:s')
        ));
        if($res1 === false || $res2 === false || $res3 === false){
            M()->rollback();
            return false;
        }else{
            M()->commit();
            return true;
        }
    }

    private function received($sign_time){
        M()->startTrans();
        $res1 = M('OrderShipping')->where(array('id_order' => $this->id_order))
            ->save(array(
                'status_label' => $this->status_name,
                'updated_at' => date('Y-m-d H:i:s'),
                'summary_status_label' => $this->status_type,
                'date_signed' => $sign_time,
                'fetch_count' => array('exp','fetch_count+1')
            ));
        $res2 = M('Order')->where(array('id_order' => $this->id_order))
            ->save(array(
                'id_order_status' => OrderStatus::SIGNED,
                'refused_to_sign' => 0,
            ));
        $res3 = M('OrderRecord')->add(array(
            'id_users' => 1,
            'id_order' => $this->id_order,
            'id_order_status' => OrderStatus::SIGNED,
            'type' => 4,
            'user_name' => '系统',
            'desc' => '物流状态更新为已签收',
            'created_at' => date('Y-m-d H:i:s')
        ));
        //结算
        $order_data = M('Order')->where(array('id_order'=>$this->id_order))->find();
        $settlement_exists = M('OrderSettlement')->where(array('id_order'=>$this->id_order))->find();
        $res4 = true;
        if(empty($order_data['payment_method']) && empty($settlement_exists)){
            $id_order_shipping = M('OrderShipping')->where(array('id_order'=>$this->id_order))->getField('id_order_shipping');
            //到付，且未有结算记录
            $res4 = M('OrderSettlement')->add(array(
                'id_order_shipping' => $id_order_shipping,
                'id_order' => $this->id_order,
                'amount_total' => $order_data['price_total'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ));
        }
        if($res1 === false || $res2 === false || $res3 === false || $res4 === false){
            M()->rollback();
            return false;
        }else{
            M()->commit();
            return true;
        }
    }

    private function sending($sign_time = null){
        if ($sign_time) {
            $data_online = M('OrderShipping')->where(array('id_order' => $this->id_order))->getField('date_online');
        }

        M()->startTrans();
        $res = M('OrderShipping')->where(array('id_order' => $this->id_order))
            ->save(array(
                'status_label' => $this->status_name,
                'updated_at' => date('Y-m-d H:i:s'),
                'date_online' => !$data_online ? $sign_time : $data_online,
                'summary_status_label' => $this->status_type,
                'fetch_count' => array('exp','fetch_count+1')
            ));

        $res2 = M('Order')->where(array('id_order' => $this->id_order))
            ->save(array(
                'id_order_status' => OrderStatus::DELIVERING,
                'refused_to_sign' => 0,
            ));
        if ($res !== false || $res2 !== false ) {
            M()->commit();
            return true;
        } else {
            M()->rollback();
            return false;
        }
    }

    private function off_line(){
        $res = M('OrderShipping')->where(array('id_order' => $this->id_order))
            ->save(array(
                'status_label' => $this->status_name,
                'updated_at' => date('Y-m-d H:i:s'),
                'summary_status_label' => $this->status_type,
                'fetch_count' => array('exp','fetch_count+1')
            ));
        return $res;
    }

}