<?php
namespace Shipping\Controller;
use Common\Controller\AppframeController;
use QL\QueryList;
use SystemRecord\Model\SystemRecordModel;
header("content-Type:text/html;charset=utf-8");
class SgxGrabController extends AppframeController {
    private $lock_write_data = 'lock_shtw_data.lock';
    protected $urls = array(
        'send_order' => "http://115.29.184.71:8082/trackIndex.htm"
    );
    public function _initialize() {
        parent::_initialize();
    }
    /*
     * 博威日本状态抓取
     */
    public function Grap(){
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
            $where['os.id_shipping'] = array('exp',' IN (111)');
            $where['o.date_delivery'] = array('GT', $data);
            $where['os.track_number'] = array('exp','is not null');
            $where['o.id_order_status'] = array('Not In','4,5,7,22');
            $where['_string'] = "os.summary_status_label != '順利送達' or os.summary_status_label is null";
            $count =  M('Order')->alias('o')
                ->field("o.*,os.track_number")
                ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                ->where($where)
                ->count();
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
                    echo 'loop over! \r\n';die;
                }
                foreach($data as $value) {
                    $this->result_handler($url, $value);
                }


                $count =  M('Order')->alias('o')
                    ->field("o.*, ow.track_number_id,os.track_number")
                    ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                    ->where($where)
                    ->count();
            }
            echo 'finished \r\n';
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        finally {
            if (file_exists(CACHE_PATH.$this->lock_write_data)) {
                //unlink(CACHE_PATH.$this->lock_write_data);
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
            case '順利送達':
                $shipping_api_model->status_type = '順利送達';
                break;
            case '退貨完成':
                $shipping_api_model->status_type = '退貨完成';
                break;
            case '拒收':
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
    protected function result_handler($url, $value){

        $params['documentCode'] = $value;
        $hj = QueryList::run('Request', [
            'http' => [
                'Accept' =>'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Cookie'=>'ASP.NET_SessionId=l2hqpybkoe201qbb33sklpzp; 
                    citrix_ns_id=bkfdkQXlEilk6JwxT26E/9Y2fS0A020; __utmt=1; __utma=8454064.1437399592.1493275404.1504522844.1504601545.6; __utmb=8454064.3.10.1504601545; __utmc=8454064; __utmz=8454064.1502696596.4.4.utmcsr=baidu|utmccn=(organic)|utmcmd=organic',
                'User-Agent'=>'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
                'Host'=>'115.29.184.71:8082',
                'Accept-Encoding'=>'gzip, deflate',
                'Upgrade-Insecure-Requests'=>1,
                //'Content-Type'=>'application/x-www-form-urlencoded',
                'method' => 'POST',
                'params' => $params,
                'target' => $url
            ],
            'callback' => function ($html, $args) {
                //处理html的回调方法
                return $html;
            }
        ]);
        $datas = $hj->setQuery(['title' =>['.div_li4:last','text'],'time' => ['.div_li3:last','text']])->data;
        if ($datas[0]['title'] != "最新状态") {
            $status = $datas[0]['title'];
            $sign_time = substr($datas[0]['time'],0,19);
        }
        if (strpos($status,"配達完了") !==false or strpos($status, "自取") !==false) {
            $summary_status = "順利送達";
        } elseif (strpos($status,"拒收") !==false or strpos($status,"退回") !==false ) {
            $summary_status = "拒收";
        } elseif (strpos($status,"退貨完成") !==false ) {
            $summary_status = "退貨完成";
        } else {
            $summary_status = "派送中";
        }
        if ($datas[0]['title'] == '最新状态'){
            $summary_status = '未上綫';
            $status = "数据未录入";
        }
        $track_number = $value;
        echo $value . ' ' . $sign_time . '\n\r';
        $this->shipping_grap($summary_status,$status,$track_number,$sign_time);
    }

}
