<?php

namespace Shipping\Controller;

use Common\Controller\AdminbaseController;
use QL\QueryList;

/**
 * Class TempFindController
 * //临时查询物流单号
 *
 * @package \Shipping\Controller
 */
class TempFindController extends AdminbaseController
{
    public $shtwController;
    public $urls = [
        'xz' => 'https://www.hct.com.tw/phone/searchGoods_Main_Xml.ashx',
        'hm' => 'http://www.t-cat.com.tw/Inquire/Trace.aspx',
    ];

    public function index ()
    {
        if ( IS_POST ) {
            $ship_name = I('post.ship_name');
            $no_data = I('post.no_data');
            $no_data_array = preg_split("~[\r\n]~", $no_data, -1, PREG_SPLIT_NO_EMPTY);
            switch ($ship_name) {
                case 'xz': //新竹
                    ini_set('max_execution_time', 0);
                    $url = $this->urls[$ship_name];
                    $xml = '<?xml version="1.0" encoding="utf-8"?>';
                    $xml .= '<qrylist>';

                    foreach ($no_data_array as $key => $value) {
                        $xml .= <<< str
                        <order orderid="{$value}"></order>
str;
                    }
                    $xml .= '</qrylist>';
                    $no = encrypt($xml);
                    $post_data = array(
                        'no' => $no,
                        'v' => '553CD428F23923DB890938AE8C393CE6'
                    );
                    $result = send_curl_request($url, $post_data);
                    $result = $this->result_handler($result, 2);
                    dump($result);
                    exit;
                    break;
                case 'hm' : //黑猫
                    $params = [];
                    $params['__EVENTTARGET'] = 'ctl00$ContentPlaceHolder1$btnSend';
                    $params['__EVENTARGUMENT'] = '';
                    $params['__VIEWSTATE'] = '/wEPDwULLTE2ODAyMTAzNDBkZHngR2yLNdcoB1YXtf+bAIxi/AHF';
                    $params['__VIEWSTATEGENERATOR'] = '9A093EFF';
                    $params['__EVENTVALIDATION'] = '/wEWDALXz8K8AwKUhrKJAQL5nJT0BgLes/beDALDytjJAgKo4bq0CAKN+JyfDgLyjv+JBAKHub7IDALsz6CzAgKUhvK7DAK97Mp+etzK3cOKerX3pzYyBL/kZYAJxkM=';
                    $params['q'] = '站內搜尋';
                    $params['cx'] = '005475758396817196247:vpg-mgvhr44';
                    $params['cof'] = 'FORID:11';
                    $params['ie'] = 'UTF-8';
                    foreach ($no_data_array as $key => $value) {
                        $k = 'ctl00$ContentPlaceHolder1$txtQuery'.($key+1);
                        $params[$k] = $value;
                    }
                    $ql = QueryList::run('Request', [
                        'http' => [
                            'Accept' =>'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                            'Cookie'=>'ASP.NET_SessionId=l2hqpybkoe201qbb33sklpzp; 
                    citrix_ns_id=bkfdkQXlEilk6JwxT26E/9Y2fS0A020; __utmt=1; __utma=8454064.1437399592.1493275404.1504522844.1504601545.6; __utmb=8454064.3.10.1504601545; __utmc=8454064; __utmz=8454064.1502696596.4.4.utmcsr=baidu|utmccn=(organic)|utmcmd=organic',
                            'User-Agent'=>'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
                            'Host'=>'www.t-cat.com.tw',
                            'Accept-Encoding'=>'gzip, deflate',
                            'Upgrade-Insecure-Requests'=>1,
                            //'Content-Type'=>'application/x-www-form-urlencoded',
                            'method' => 'POST',
                            'params' => $params,
                            'target' => $this->urls[$ship_name]
                        ],
                        'callback' => function ($html, $args) {
                            //处理html的回调方法
                            return $html;
                        },
                        'args' => '传给回调函数的参数'
                    ]);

                    $data = $ql->setQuery(['table' => ['table.tablelist',
                        'html',
                        'a']])->data;
                    $html =  ($data[0]['table']);
                    echo '<table>'. $html.'</table>';exit;

            }
        }
        $this->display();
    }

    protected function result_handler ($result, $type = 1)
    {
        $return = array();
        $xml = decrypt($result);
        $obj = simplexml_load_string($xml);
        $result = json_decode(json_encode($obj), TRUE);

        //file_put_contents('6.txt',json_encode($result),FILE_APPEND);
        foreach ($result['orders'] as $key => $v) {

            if ( is_array($v['order']) ) {

                foreach ($v['order'] as $vv) {
                    $track_number = $vv['@attributes']['orderid'];
                    $grap_status = $vv['@attributes']['status'];
                    $wrktime = $vv['@attributes']['wrktime'];

                    if ( strpos($vv['@attributes']['status'], '配送中') !== false || strpos($vv['@attributes']['status'], '已抵達') !== false || strpos($vv['@attributes']['status'], '途中') !== false ) {
                        $status = '派送中';
                        break;
                    } elseif ( strpos($vv['@attributes']['status'], '送達。貨物件數') !== false ) {
                        $status = '签收';
                        $sign_time = $vv['@attributes']['wrktime'] . ':00';
                        break;
                    } elseif ( strpos($vv['@attributes']['status'], '所保管中') !== false ) {
                        if ( strpos($vv['@attributes']['status'], '取收回') !== false || strpos($vv['@attributes']['status'], '拒絕') !== false ) {
                            $status = '拒收';
                            break;
                        }

                    } elseif ( strpos($vv['@attributes']['status'], '請撥空領取') !== false ) {
                        $status = '派送中';
                        break;
                    } elseif ( strpos($vv['@attributes']['status'], '取收回') !== false || strpos($vv['@attributes']['status'], '拒絕') !== false ) {
                        $status = '拒收';
                        break;
                    }
                }

                echo $track_number . "--于" . $wrktime . "--" . $status . "<br>";
            } else {
                echo $v['@attributes']['ordersid'] .'--无<br/>';
            }
        }

    }
}
