<?php
namespace Shipping\Lib;

/**
 * 越南物流接口
 */
class YNShippingApi extends ShippingApi
{
    protected $order_data = array();
    protected $api_key;
    protected $wave_number;
    protected $urls = array(
        'send_order' => 'https://services.shipchung.vn/api/rest/courier/create',
    );

    public function __construct(){
        $this->api_key = 'bb565d3f61b05ef6e09af28834040a0a';
    }

    public function send_order($wave_number){

        $this->wave_number = $wave_number;

        $order_data = $this->generate_order_data();
        if(empty($order_data)){
            return false;
        }

        $url = $this->urls['send_order'];
        return $this->batch_post($url, $order_data);
    }

    protected function batch_post($url, $data){
        // 创建批处理cURL句柄
        $mh = curl_multi_init();
        $ch = [];
        $result = [];
        $error = [];
        //初始化
        foreach($data as $key => $row){
            $ch[$key] = curl_init();
            curl_setopt($ch[$key], CURLOPT_SSL_VERIFYHOST, 1);
            curl_setopt($ch[$key], CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch[$key], CURLOPT_URL, $url);
            curl_setopt($ch[$key], CURLOPT_HEADER, 0);
            curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch[$key], CURLOPT_POSTFIELDS, json_encode($row));
            curl_setopt($ch[$key], CURLOPT_HTTPHEADER, array("Content-type: application/json"));
            curl_multi_add_handle($mh,$ch[$key]);
        }

        //执行
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        //结果处理
        foreach($ch as $key => $value){
            $result[$key] = $this->result_handler(curl_multi_getcontent($value));
            if(!$result[$key]){
                unset($result[$key]);
                $error[$key] = "订单{$key}发送错误:" . $this->error.'<br/>';
            }
            curl_multi_remove_handle($mh, $value);
        }
        curl_multi_close($mh);

        if(!empty($error)){
            $this->error = $error;
        }
        return $result;

    }

    protected function generate_order_data(){
        $order_data = [];

        $data = M('OrderWave')->alias('ow')
            ->field("o.*, os.weight, group_concat(oi.id_order_item) as items, os.has_sent")
            ->join("__ORDER__ AS o ON o.id_order=ow.id_order", 'LEFT')
            ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
            ->join("__ORDER_ITEM__ AS oi ON oi.id_order=ow.id_order", 'LEFT')
            ->where(array("ow.wave_number" => array('EQ', $this->wave_number)))
            ->where(array("os.has_sent"=>0))
            ->group('oi.id_order')
            ->limit(30)  //一次最多只发送30单
            ->select();

        if(empty($data)){
            $this->error = "所有运单都已发送给物流";
            return false;
        }

        foreach($data as $row) {
            $from_VN = false;
            if($row['id_warehouse'] == 9){$from_VN = true;}   //是否越南仓发货
            $items = M('OrderItem')
                ->where("id_order_item IN ({$row['items']})")
                ->select();

            $province = M('Region')
                ->where(array('LEFT(name,20)'=>array('EQ', trim($row['province']))))->find();

            $city = M('Region')
                ->where(array('id_parent' => $province['id_region']))
                ->where(array('LEFT(name,20)'=>array('EQ', trim($row['city']))))->find();


            if(empty($row['city']) || empty($row['province'])){
                $this->error = "根据订单{$row['id_increment']}地址信息无法找到对应省市，无法生成运单";
                return false;
            }else{
                $row['city'] = $city['tag'];
                $row['province'] = $province['tag'];
            }

            foreach ($items as $key => $value) {
                $items[$key] = array(
                    "Name" => empty($value['foreign_title']) ? $value['sale_title'] : $value['foreign_title'],
                    "Price" => $value['total'],
                    "Quantity" => $value['quantity'],
                    "Weight" => 600,
                );
                if($from_VN){
                    $items[$key]["BSIN"] = $value['sku'];
                }
            }

            if(!empty($row['payment_method']) || empty($row['price_total'])){
                $COD = 2;  //no-cod 不代收款
            }else{
                $COD = 1;  //cod
            }

            $order_data[$row['id_increment']] =
                array(
                    'Items' => $items,

                    'From' => array(
                        'Stock' => $from_VN ? 160046 : 166295,
                    ),

                    'Order' => array(
                        'ProductName' => $items[0]['Name'],
                        'Quantity' => $row['total_qty_ordered'],
                        'Amount' => $row['price_total'] == 0 && $COD ==2 ? 20000 : $row['price_total'],
                        'Collect' => $row['price_total'],
                    ),

                    'To' => array(
                        'Name' => $row['last_name'] . $row['first_name'],
                        'Phone' => $row['tel'],
                        'Address' => $row['address'],
                        'Province' => $row['city'],   //接口问题，省市调转
                        'City' => $row['province']
                    ),
                    'Type' => 'excel',
                    'Config' => array(
                        "Service" => 2,
                        "CoD" => $COD,
                        "Protected" => 2,
                        "Checking" => 1,
                        "Payment" => 2,
                        "Fragile" => 2,
                        "AutoAccept" => 0
                    ),
                    'Domain' => 'erp.msiela.com',
                    'ApiKey' => $this->api_key,
                );

        }
        return $order_data;
    }

    /**
     * @param $result
     * @return mixed 失败返回false,否则返回各订单对应的运单号
     */
    protected function result_handler($result){
        if(empty($result)){
            $this->error = '没有返回数据';
            return false;
        }
        $result_arr = json_decode($result, true);
        if(empty($result_arr)){
            $this->error = '无效返回:'.$result;
            return false;
        }else{
            if($result_arr['code'] != 'SUCCESS'){
                $this->error = '返回错误:'.$result_arr['message'];
                return false;
            }else{
                $result = $result_arr['data']['TrackingCode'];
            }
        }
        return $result;
    }

    public function get_error(){
        if(is_array($this->error)){
            return implode(',', $this->error);
        }else{
            return $this->error;
        }
    }

    public function get_status(){
//  拒收  			客户取消、等待确认退还
//	退貨完成   		退还
//	順利送達  		发货成功
//	派送中 			等待审阅、已审阅、提货中、提货不成功、提货、发货中、已发货
//	未上綫

        $type = array(
            'refuse' => '拒收',
            'return_finish' => '退貨完成',
            'received' => '順利送達',
            'sending' => '派送中',
            'off_line' => '未上綫'
        );

        $status = array(
            array(
                'name' => '等待审阅',
                'id' => '12',
                'type' => 'sending'
            ),
            array(
                'name' => '已审阅',
                'id' => '13',
                'type' => 'sending'
            ),
            array(
                'name' => '提货中',
                'id' => '14',
                'type' => 'sending'
            ),
            array(
                'name' => '提货不成功',
                'id' => '15',
                'type' => 'sending'
            ),
            array(
                'name' => '已提货',
                'id' => '16',
                'type' => 'sending'
            ),
            array(
                'name' => '发货中',
                'id' => '17',
                'type' => 'sending'
            ),
            array(
                'name' => '发货失败',
                'id' => '18',
                'type' => 'refuse'
            ),
            array(
                'name' => '已发货',
                'id' => '19',
                'type' => 'received'
            ),
            array(
                'name' => '等待确认退还',
                'id' => '20',
                'type' => 'refuse'
            ),
            array(
                'name' => '退还',
                'id' => '21',
                'type' => 'return_finish'
            ),
            array(
                'name' => '客户取消',
                'id' => '22',
                'type' => 'off_line'
            ),

        );

        $data = file_get_contents("php://input");

        //记录推送过来的数据
        write_file('Shipping', 'YN', var_export($data, true));

        $data = json_decode($data,true);
        $status_id = $data['StatusId'];
        $track_number = $data['TrackingCode'];

        foreach($status as $row){
            if($row['id'] == $status_id){
                $status_name = $row['name'];
                $status_type = $type[$row['type']];
                break;
            }
        }
        if(!isset($status_name) || !isset($status_type)){
            $status_name = $data['StatusName'];
            $status_type = '未上綫';
        }

        $this->track_number = $track_number;
        $this->status_name = $status_name;
        $this->status_type = $status_type;
        return true;
    }


}