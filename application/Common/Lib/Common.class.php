<?php
/**
 * Created by Juns <46231996@qq.com>.
 * User: jun
 * Date: 2016/12/11 22:28
 * Description:
 */

namespace Common\Lib;
use Order\Lib\OrderStatus;
/**
 * 货币转换类
 * @package Common\Lib
 */
class Common
{
    /**
     * 获取近三天的销量
     * @param type $pro_id
     * @param type $sku_id
     * @return string
     */
    public static function get_three_sale($pro_id, $sku_id) {
        $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
        $etime = date('Y-m-d 00:00:00');
        $tarray = array();
        $tarray[] = array('EGT', $stime);
        $tarray[] = array('LT', $etime);
        $twhere[] = array('created_at' => $tarray);
        $twhere['id_product'] = $pro_id;
        $twhere['id_product_sku'] = $sku_id;
        $twhere['id_order_status'] = array('IN', OrderStatus::get_effective_status());
        $od_sale = M('Order')->alias('o')->field('SUBSTRING(created_at,9,2) as create_date,COUNT(*) as count')->join('__ORDER_ITEM__ as oi ON oi.id_order=o.id_order')->where($twhere)->group('create_date')->select();
        $str = '';
        foreach ($od_sale as $key => $val) {
            $str .= $val['create_date'] . '号：' . $val['count'] . '<br>';
        }
        return $str;
    }
}