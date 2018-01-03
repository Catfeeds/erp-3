<?php
/**
 * Created by Juns <46231996@qq.com>.
 * User: jun
 * Date: 2016/12/11 22:28
 * Description:
 */

namespace Common\Lib;

/**
 * 货币转换类
 * @package Common\Lib
 */
class Currency
{
    private static function _currency()
    {
        static $currencies = null;
        if (!$currencies) {
            // Load currency from db
            $cache = D('Currency')->cache(true, 84600)->select();
            $currencies = array();
            foreach ($cache as $c) {
                $currencies[$c['code']] = $c;
            }
        }
        return $currencies;
    }
    public static function format($value, $code = 'TWD', $decimals = 0)
    {
        $currencies = static::_currency();
        if (isset($currencies[$code])) {
            $currency = $currencies[$code];
        } else {
            $currency = $currencies['TWD'];
        }
        if (!$currency)
            return $value;
        $format_value = $currency['symbol_left'].
            number_format($value, $decimals).
            $currency['symbol_right'];
        return $format_value;
    }
}