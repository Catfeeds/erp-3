<?php

namespace Shipping\Controller;

use Common\Controller\AppframeController;

header("content-Type:text/html;charset=utf-8");

class ZhyzGrabController extends AppframeController
{
    private $lock_write_data = 'lock_shtw_data.lock';
    protected $urls = array(
        'send_order' => "http://220.132.209.89/API/esp.php?A=esp&C=esp1234&S=%s"
    );

    public function _initialize ()
    {
        parent::_initialize();
    }

    /*
     * 易速配中华邮政抓取
     */
    public function grap ()
    {
//        echo 1;die;
        ini_set('max_execution_time', 0);
        $url = $this->urls['send_order'];
//        if (file_exists(CACHE_PATH.$this->lock_write_data)) {
//            echo '已经有进程正在运行';
//            exit;
//        }
        try {
//            file_put_contents(CACHE_PATH.$this->lock_write_data, 'lock');
            $data = date('Y-m-d 00:00:00', strtotime('-41 days'));
            $where['os.id_shipping'] = array('exp', ' IN (112)');
            $where['o.date_delivery'] = array('GT', $data);
            $where['os.track_number'] = array('exp', 'is not null');
            $where['o.id_order_status'] = array('Not In', '4,5,7,9,22');
            $where['_string'] = "os.summary_status_label != '順利送達' or os.summary_status_label is null";
            /*$count =  M('Order')->alias('o')
                ->field("o.*,os.track_number")
                ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                ->where($where)
                ->count();*/

            $data = M('Order')->alias('o')
                ->field("o.*,os.track_number")
                ->join("__ORDER_SHIPPING__ AS os ON os.id_order=o.id_order", 'LEFT')
                ->where($where)
                ->order('os.fetch_count, o.date_delivery')
                //->limit($i,100)
                ->getField('track_number', true);
            $count = count($data);
            if ( empty($data) ) {
                echo 'loop over! \r\n';
                die;
            }

            foreach ($data as $value) {
                $this->result_handler($url, $value);
            }
            echo 'finished \r\n';
        } catch (Exception $e) {
            echo $e->getMessage();
        } finally {
            if ( file_exists(CACHE_PATH . $this->lock_write_data) ) {
                //unlink(CACHE_PATH.$this->lock_write_data);
            }
        }
    }

    /*
     * 抓取结果写入系统
     */
    public function shipping_grap ($status, $grap_status, $track_number, $sign_time = '')
    {
        $shipping_api_name = "\\Shipping\\Lib\\ShippingApi";
        $shipping_api_model = new $shipping_api_name();
        $shipping_api_model->track_number = $track_number;
        $shipping_api_model->status_name = $grap_status;
        switch ($status) {
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
    protected function result_handler ($url, $track_number)
    {

        $get_url = sprintf($url, $track_number);
        $res = file_get_contents($get_url);
        $data = json_decode($res, true);
        if ( $data ) {
            $status = array_pop($data['states_s']);
            $sign_time = array_pop($data['states_t']);//time
            $sign_time = $this->cut_time($sign_time);
            if ( strpos($status, "投遞成功") !== false || strpos($status, "入帳成功") !== false ) {
                $summary_status = "順利送達";
                $sign_time = date('Y-m-d H:i:s');
            } elseif ( strpos($status, "拒收") !== false or strpos($status, "退回") !== false ) {
                $summary_status = "拒收";
            } elseif ( strpos($status, "退貨完成") !== false ) {
                $summary_status = "退貨完成";
            } else {
                $summary_status = "派送中";
            }
        } else {
            $summary_status = '未上綫';
            $status = "数据未录入";
        }

        echo $track_number ;
        echo "\r\n";
        $this->shipping_grap($summary_status, $status, $track_number, $sign_time);
    }

    public function cut_time ($time = null) {
        if (!$time) return false;
        $time_arr = explode(' ', $time);
        $ymd = $time_arr[0];
        $his = $time_arr[1];
        $y = substr($ymd,0,4);
        $m = substr($ymd,4,2);
        $d = substr($ymd,6,2);
        $h = substr($his,0,2);
        $i = substr($his,2,2);
        $s = substr($his,4,2);
        return $y . '-' . $m .'-' . $d . ' ' .$h .':' . $i . ':' . $s;
    }

}
