<?php

namespace Shipping\Controller;

use Common\Controller\AppframeController;
use Shipping\Lib\ShippingApi;

header("content-Type:text/html;charset=gbk");

class BsydGrabController extends AppframeController
{
    private $lock_write_data = 'lock_shtw_data.lock';
    protected $urls = array(
        'send_order' => "http://39.108.111.125/cgi-bin/GInfo.dll?EmsApiTrack&cno=%s"
    );

    public function _initialize ()
    {
        parent::_initialize();
    }

    /*
     * 百世亿达物流抓取
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
            $data = date('Y-m-d 00:00:00', strtotime('-30 days'));
            $where['os.id_shipping'] = array('exp', ' IN (116)');
            $where['o.date_delivery'] = array('GT', $data);
            $where['os.track_number'] = array('exp', 'is not null');
            $where['o.id_order_status'] = array('Not In', '4,5,7,16,22');
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
        if (substr($res ,0,3) != '100') {
            return false;
        }
        $xml_string = mb_strcut($res, 3);
        //正则匹配
        preg_match_all("/<STATE>(.*)<\/STATE>/",$xml_string,$status_arr,PREG_PATTERN_ORDER);
        preg_match_all("/<DATETIME>(.*)<\/DATETIME><PLACE>(.*)<\/PLACE><INFO>(.*)<\/INFO>/",$xml_string,$wl,PREG_PATTERN_ORDER);

        $status = $status_arr[1][0];//状态码  3投递成功 8退件
        $sign_time = array_pop($wl[1]);//时间
        $status_title = array_pop($wl[2]).'-'.array_pop($wl[3]);//物流信息
        switch ($status) {
            case 3:
                $summary_status = "順利送達";
                break;
            case 8:
                $summary_status = "拒收";
                break;
            default:
                $summary_status = "派送中";
                break;
        }
        echo $track_number ;
        echo "\r\n";
        echo $status;
        echo "\r\n";
        echo $status_title;
        echo "\r\n";
        echo $sign_time;
        $this->shipping_grap($summary_status, iconv('GBK','UTF-8',$status_title), $track_number, $sign_time);
    }


}
