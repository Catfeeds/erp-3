<?php
namespace Order\Model;
use Common\Model\CommonModel;
class OrderShippingModel extends CommonModel{
    public function updateShipping($orderId,$getTrackNumber,$remark='',$data=array()){
        try{
            $shipping = D("Common/Shipping")->field('id_shipping,title')->cache(true,86400)->select();
            $shipTemp = $shipping?array_column($shipping,'title','id_shipping'):array();
            $order    = D("Common/Order")->find($orderId);
            $shippingId = $order['id_shipping'];
            $shippingName = $shippingId?$shipTemp[$shippingId]:'';

            $getTrackNumber = is_array($getTrackNumber)?$getTrackNumber:explode(',',str_replace('，',',',$getTrackNumber));
            foreach($getTrackNumber as $item){
                $getShipping = D("Common/OrderShipping")->where('id_order='.$orderId)->find();
                if(!isset($getShipping['track_number']) or $getShipping['track_number']){
                    //如果存在订单跟踪号了，是添加第个跟踪号，就添加一条记录
                    unset($getShipping['is_email'],$getShipping['id']);
                    $getShipping['id_order'] = $orderId;
                    $getShipping['created_at'] = date('Y-m-d H:i:s');
                    $getShipping['track_number'] = $item;
                    $getShipping['remark'] = $remark;
                    $getShipping['id_shipping']= $shippingId;
                    $getShipping['shipping_name'] = $shippingName;
                    $getShipping['fetch_count'] = 0;
                    D("Common/OrderShipping")->data($getShipping)->add();
                }else{
                    $shipData = array('track_number'=>$item,'remark'=>$remark,'shipping_name'=>$shippingName);//$_POST['order_remark']
                    D("Common/OrderShipping")->where('id_order_shipping='.$getShipping['id'])->save($shipData);
                }
            }
            $updOrder =  array('id_order_status'=>8);//,'delivery_date'=>isset($data['delivery_date'])?$data['delivery_date']:''
            D("Common/Order")->where('id_order='.$orderId)->save($updOrder);
//            D("Common/OrderStatusHistory")->addHistory($orderId,3,$remark);
//            send_shipping_track($order,$getTrackNumber);
            $status = 1;$message = '操作成功。';
            //D("Common/Order")->saveStock($orderId);//订单减库存
        }catch (\Exception $e){
            $status = 0;$message = $e->getMessage();
        }
        return array('status'=>$status,'message'=>$message);
    }
    public function getShipInfo($orderId,$field='track_number'){
        $ship = $this->field($field)->where(array('id_order'=>$orderId))->select();
        return $ship;
    }
    //查询物流id对应的发货物流
    public function getShip($orderId,$field='shipping_name'){
        $ship = $this->field($field)->where(array('id_shipping'=>$orderId))->select();
        return $ship;
    }
}

