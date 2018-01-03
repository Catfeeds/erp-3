<?php
namespace Shipping\Lib;

/**
 * CXC物流接口
 */
class CXCShippingApi extends ShippingApi
{
    protected $partner_id;

    protected $order_data = array();
    protected $urls = array(
        'send_order' => 'http://42.3.224.83:7028/BGNCXCInterface/add.do',
    );

    public function send_order($wave_number){
        $this->wave_number = $wave_number;
        $order_data = $this->generate_order_data();
        if(empty($order_data)){
            return false;
        }
        $url = $this->urls['send_order'];
        //分批发送
        $add['key'] = array(
            'username' => 'CBXGCN1684945',
            'password' => 'C3625XCBG48N4'
        );
        $new = array();
        $count = 0;
        $add['data']= array_slice($order_data['data'],$count*100,100);
        while($add['data']){
            $result = $this->json_post($url, http_build_query(array('data'=>json_encode($add))));
            if($this->result_handler($result)){
                $new+=$this->result_handler($result);
            }
            ++$count;
            $add['data'] = array_slice($order_data['data'],$count*100,100);
        }
        if(!$new){
            return false;
        }else{
            return $new;
        }
    }

    protected function json_post($url,$data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type"=>"application/x-www-form-urlencoded;charset=UTF-8"));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    protected function generate_order_data(){
        $order_data = [];
        $date = date('Y-m-d H:i:s');

        $data = M('OrderWave')->alias('ow')
            ->field("o.*, os.weight, group_concat(oi.product_title) as product_name")
            ->join("__ORDER__ AS o ON o.id_order=ow.id_order")
            ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order")
            ->join("__ORDER_ITEM__ AS oi ON oi.id_order=ow.id_order", 'LEFT')
            ->where(array("ow.wave_number" => array('EQ', $this->wave_number)))
            ->where(array("os.has_sent"=>0))
            ->group('oi.id_order')
            ->select();

        if(empty($data)){
            $this->error = "所有运单都已发送给物流";
            return false;
        }

        foreach($data as $row) {

            if(empty($row['payment_method'])){
                $COD = $row['price_total'];
            }else{
                $COD = 0;
            }
            $order_data[] = array(
                'cmCode' => 98171006,
                'bdShipmentno' => 0,
                'bdCode' => $row['id_increment'],
                'bdCaddress' => $row['province'] . $row['city'] . $row['area'] . $row['address'],
                'bdConsigneename' => $row['last_name'] . $row['first_name'],
                'bdConsigneephone' => $row['tel'],
                'bdGoodsnum' => $row['total_qty_ordered'],
                'bdWeidght' => 1,
                'createdate' => $date,
                'bdPackageno' => $row['id_increment'],
                'bdProductprice' => $row['price_total'],
                'bdPurprice' => $COD,
                'bdProductname' => $row['product_name']
            );
        }

        $post_data = array(
            'data' => $order_data,
            'key' => array(
                'username' => 'CBXGCN1684945',
                'password' => 'C3625XCBG48N4'
            )
        );
        return $post_data;
    }

    protected function result_handler($result){
        $return = array();
        if(empty($result)){
            $this->error = '没有返回数据';
        }
        $result_arr = json_decode($result, true);
        if(empty($result_arr['result'])){
            $this->error = '无效返回:'.$result;
        }else{
            foreach($result_arr['result'] as $v){
                $return[$v['billCode']] = $v['billCode'];
                if($v['status'] == 1){
                    $return[$v['billCode']] = $v['billCode'];
                }
            }
        }
        return $return;
    }

    public function get_error(){
        if(is_array($this->error)){
            return implode(',', $this->error);
        }else{
            return $this->error;
        }
    }

    public function get_status(){

        $type = array(
            'refuse' => '拒收',
            'return_finish' => '退貨完成',
            'received' => '順利送達',
            'sending' => '派送中',
            'off_line' => '未上綫'
        );

        $api_key = 'Iv9UBgjMDp81MKwax2SV';
        $key = I('request.key');

        if($key != $api_key){
            $this->error = 'invalid key';
            return false;
        }

        $data = file_get_contents("php://input");
        write_file('shipping','cxc_update', var_export($data, true));
        $data = json_decode($data,true);
        $this->status_name = $data['status_name'];
        $this->track_number = $data['tracking_code'];


        if(empty($this->status_name) || !isset($type[$data['status_type']])){
            $this->error = 'invalid status name OR invalid type';
            return false;
        }
        $this->status_type = $type[$data['status_type']];
        return true;
    }

}