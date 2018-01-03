<?php

namespace Order\Repository;

use Think\Controller;

/**
 * Class WlRepositoryController
 * 处理物流格式
 *
 * @package \Order\Repository
 */
class WlRepositoryController extends Controller
{
    public function lists ($where = null, $country)
    {
        $lists = M('DomainOrderInfo')->where($where)->select();
        $this->{$country . 'export'}($lists);
    }

    private function TWexport ($lists)
    {

        $filename = "dingdang" . date('Y-m-d') . ".xls";//先定义一个excel文件
        header("Content-Type: application/vnd.ms-execl");
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=$filename");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo "域名" . "\t";
        echo "訂單編號" . "\t";
        echo "溫層" . "\t";
        echo "距離" . "\t";
        echo "規格" . "\t";
        echo "代收貨款" . "\t";
        echo "收件人 - 姓名" . "\t";
        echo "收件人 - 電話" . "\t";
        echo "收件人 - 手機" . "\t";
        echo "收件人 - 地址" . "\t";
        echo "寄件人-姓名（颜色）" . "\t";
        echo "寄件人-電話（尺码）" . "\t";
        echo "寄件人-地址（数量）" . "\t";
        echo "出貨日期" . "\t";
        echo "預定配達日期" . "\t";
        echo "預定配達時間" . "\t";
        echo "品名" . "\t";
        echo "易碎物品" . "\t";
        echo "精密儀器" . "\t";
        echo "備註" . "\t";
        echo '' . "\t";
        echo '' . "\t";
        echo '' . "\t";
        echo "IP" . "\n";

        foreach ($lists as $row) {
            echo $row["domain"] . "\t";
            echo $row["id"] . "\t";
            echo '' . "\t";
            echo '' . "\t";
            echo '' . "\t";
            echo $row["price"] . "\t";
            echo $row["name"] . "\t";
            echo $row["email"] . "\t";
            echo $row["tel"] . "\t";
            echo $row["addr"] . "\t";
            echo $row["color"] . "-" . $row["size"] . "\t";
            if ( $row["fcolor"] !== "" ) {
                echo $row["fcolor"] . "-" . $row["fsize"] . "\t";
            } else {
                echo '' . "\t";
            }
            if ( $row["tcolor"] !== "" ) {
                echo $row["tcolor"] . "-" . $row["tsize"] . "\t";
            } else {
                echo '' . "\t";
            }
            echo date("Y-m-d H:i:s", strtotime($row['lastdate'])) . "\t";
            echo $row['storage_remark'] . "\t";
            echo $row["num"] . "\t";
            echo $row['product_no'] . "\t";
            echo '' . "\t";
            echo '' . "\t";
            echo str_replace("\r\n", "", $row["remark"]) . "\t";
            echo '' . "\t";
            echo '' . "\t";
            echo '' . "\t";
            echo $row["ip"] . "\n";

        }
        exit;
    }


    private function MYexport ($lists)
    {

        $filename = "dingdang" . date('Y-m-d') . ".xls";//先定义一个excel文件
        header("Content-Type: application/vnd.ms-execl");
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=$filename");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo "运单" . "\t";
        echo "转单号" . "\t";
        echo "訂單編號" . "\t";
        echo "类别" . "\t";
        echo "件数" . "\t";
        echo "出货渠道" . "\t";
        echo "实际重量" . "\t";
        echo "收货人/公司" . "\t";
        echo "收货人" . "\t";
        echo "收货人电话" . "\t";
        echo "收货人地址1" . "\t";
        echo "收货人地址2" . "\t";
        echo "收货人地址3" . "\t";
        echo "电子邮箱" . "\t";
        echo "邮政编码" . "\t";
        echo "目的地" . "\t";
        echo "C.O.D" . "\t";
        echo "货币类型" . "\t";
        echo "关税" . "\t";
        echo "备注" . "\t";
        echo "材积1" . "\t";
        echo "材积2" . "\t";
        echo "中文品名1" . "\t";
        echo "英文品名1" . "\t";
        echo "数量1" . "\t";
        echo "申报价值1" . "\t";
        echo "中文品名2" . "\t";
        echo "英文品名2" . "\t";
        echo "数量2" . "\t";
        echo "申报价值2" . "\t";
        echo "订单日期" . "\n";


        foreach ($lists as $row) {
            echo '' . "\t";
            echo '' . "\t";
            echo $row["id"] . "\t";
            echo '包裹' . "\t";
            echo '1' . "\t";
            if ( stripos($row['product_no'], "t") ) {
                echo 'ECOM-GMS-M' . "\t";
            } else {
                echo 'ECOM-GMS-P' . "\t";
            }
            echo '' . "\t";
            echo $row["name"] . " " . $row["names"] . "\t";
            echo $row["name"] . " " . $row["names"] . "\t";
            echo $row["tel"] . "\t";
            echo $row["addr"] . "\t";
            echo '' . "\t";
            echo '' . "\t";
            echo $row["email"] . "\t";
            echo $row["code"] . "\t";
            if ( preg_match("^((8[7-9])|9[0-9])\d{3,}^", $row['code']) ) {
                echo 'East Malaysia' . "\t";
            } else {
                echo 'West Malaysia' . "\t";
            }
            echo $row["price"] . "\t";
            echo 'MYR' . "\t";
            echo '' . "\t";
            echo $row["color"] . "-" . $row["size"] . "\t";
            if ( $row["colors"] ) {
                echo '赠品-' . $row["colors"] . "-" . $row["sizes"] . "\t";
            } else {
                echo '' . "\t";
            }
            echo '' . "\t";
            echo '鞋' . "\t";
            echo 'shoes' . "\t";
            echo $row["num"] . "\t";
            echo $row["MYprice"] . "\t";
            echo $row['product_no'] . "" . "" . "\t";
            echo '' . "\t";
            echo '' . "\t";
            echo '' . "\t";
            echo date("Y-m-d H:i:s", strtotime($row['lastdate'])) . "\n";
        }
    }


}
