<?php
namespace Order\Model;
use Common\Model\CommonModel;
class OrderBlacklistModel {
    /**
     * 处理黑名单与 IP 地址
     * 订单连表查询出订单，时间太短，后面再优化
     * @param $data
     */
    public function black_list_and_ip_address($data){
        $order_info = D('Order/OrderInfo');
        $info = $order_info->where(array('id_order'=>$data['id_order']))->find();
        $data['ip']              = $info['ip'];
        $data['ip_address']      = $info['ip_address'];
        $data['blacklist_level'] = $info['blacklist_level'];
        $data['blacklist_field'] = $info['blacklist_field'];
        $data = array_map(function($item) {
            if (is_string($item)) {
                return str_replace("'", '', $item);
            }
            return $item;
        }, $data);
        if(empty($data['ip_address'])){
            try{
                import("getGeoIpAddress");
                $getGeoIpAddress = New \getGeoIpAddress();
                $Reader  = $getGeoIpAddress->reader();
                $orderModel = D("Order/Order");
                $blacklist = D('Common/Blacklist');

                $record = $Reader->city($data['ip']);
                $ipAddress = trim($record->country->names['zh-CN'].' '.$record->city->names['zh-CN']);
            }catch (\Exception $e){
                $ipAddress = '';
                //print_r($e->getMessage());
            }
            if($ipAddress){
                //黑名单 查询
                $where = " (`field`='phone' and  `title` LIKE '%".$data['tel']."%') or ";
                $first_name = trim($data['first_name'])?$data['first_name']:$data['last_name'];
                if($first_name){
                    $where .= " (`field`='name' and  `title` LIKE '%".$first_name."%') or ";
                }
                if($data['email']){
                    $where .= " (`field`='email' and  `title` LIKE '%".$data['email']."%') or ";
                }
                $where .= " (`field`='address' and  `title` LIKE '%".$data['address']."%') or";
                $where .= " (`field`='ip' and  `title` ='".$data['ip']."')";
                $result = $blacklist->where($where)->order('level desc')->find();
                $result['level'] = $result['level']?$result['level']:0;
                $field = isset($result['field'])?$result['field']:'';
                $update = array('ip_address'=>$ipAddress,
                    'blacklist_level'=>$result['level'],
                    'blacklist_field'=>$field);
                $order_info->where(array('id_order'=>$data['id_order']))->save($update);
                $data['ip_address'] = $ipAddress;
                $data['blacklist_level'] = $result['level'];
                $data['blacklist_field'] = $field;
            }
        }
        return $data;
    }
}