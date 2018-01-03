<?php
namespace Shipping\Controller;
use Common\Controller\AppframeController;
use QL\QueryList;
use SystemRecord\Model\SystemRecordModel;
header("content-Type:text/html;charset=utf-8");
class TcatGrabController extends AppframeController {
    private $lock_write_data = 'lock_shtw_data.lock';
    protected $urls = array(
        'send_order' => "http://www.t-cat.com.tw/Inquire/TraceDetail.aspx?BillID=%s&ReturnUrl=Trace.aspx"
    );
    public function _initialize() {
        parent::_initialize();
    }
    /*
     * 黑猫物流状态抓取
     */
    public function grap(){
//        echo 1;die;
        ini_set('max_execution_time', 0);
        $url = $this->urls['send_order'];
//        if (file_exists(CACHE_PATH.$this->lock_write_data)) {
//            echo '已经有进程正在运行';
//            exit;
//        }
        try {
//            file_put_contents(CACHE_PATH.$this->lock_write_data, 'lock');
            //$data = date('Y-m-d 00:00:00',strtotime('-41 days'));
            $where['os.id_shipping'] = array('exp',' IN (17,21,22,24,25,26,27,28,43,74,119)');
            //$where['o.date_delivery'] = array('GT', $data);
            $where['os.track_number'] = array('exp','is not null');
            $where['o.id_order_status'] = array('Not In','4,5,7,9,10,16,22');
            //$where['_string'] = "os.summary_status_label != '順利送達' or os.summary_status_label is null";
            /*$count =  M('Order')->alias('o')
                ->field("o.*,os.track_number")
                ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                ->where($where)
                ->count();*/

            $data =  M('Order')->alias('o')
                ->field("o.*,os.track_number")
                ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                ->where($where)
                ->order('os.fetch_count, o.date_delivery')
                //->limit($i,100)
                ->getField('track_number',true);
            $count = count($data);
            if(empty($data)) {
                echo 'loop over! \r\n';die;
            }

            foreach($data as $value) {
                echo $value . "\n\r";
                $this->result_handler($url, $value);
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

        $get_url =  sprintf($url, $value);
        $rules = array(
            'title'=>array('.r2','text',''),    //获取纯文本格式的标题,并调用回调函数1
            'summary'=>array('.bl12','text',''), //获取纯文本的文章摘要，但保strong标签并去除input标签
        );
        $hj = QueryList::Query($get_url,$rules);
        $datas = $hj->data;
        $status = null;
        $sign_time = null;
        $summary_status = null;
        if ($datas[0]['title']) {
            $status = $datas[0]['title'];
            $sign_time = $datas[1]['summary'];
        } else {
            $status = $datas[1]['summary'];
            $sign_time = $datas[2]['summary'];
        }
        echo $status ." " . $sign_time;
        echo "\r\n";
        if (strpos($status,"順利送達") !==false ) {
            $summary_status = "順利送達";
        } elseif (strpos($status,"拒收") !==false or strpos($status,"退回") !==false ) {
            $summary_status = "拒收";
        } elseif (strpos($status,"退貨完成") !==false ) {
            $summary_status = "退貨完成";
        } else {
            $summary_status = "派送中";
        }
        if (!$datas){
            $summary_status = '未上綫';
            $status = "数据未录入";
        }
        $track_number = $value;
        $this->shipping_grap($summary_status,$status,$track_number,$sign_time);
    }

}
