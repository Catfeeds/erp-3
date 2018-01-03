<?php
/**
 * Created by Juns <46231996@qq.com>.
 * User: jun
 * Date: 2017/1/14 13:59
 * Description:
 */

namespace Order\Lib;


/**
 * 订单状态
 * @package Order\Lib
 */
class OrderStatus
{

    const UNPROCESS = 1; // 未处理
    const PROCESSING = 2; // 待处理
    const VERIFICATION = 3; // 待审核
    const UNPICKING = 4; // 未配货
    const PICKING = 5; // 配货中
    const OUT_STOCK = 6; // 缺货
    const PICKED = 7; // 已配货
    const DELIVERING = 8; // 配送中
    const SIGNED = 9; // 已签收
    const RETURNED = 10; // 已退货
    const REPEAT = 11; // 重复下单
    const IMPERFECT = 12; // 信息不完整
    const MALICE = 13; // 恶意下单
    const CANCELED = 14; // 客户取消
    const DAMAGED = 15; // 质量问题
    const REJECTION = 16; // 拒收
    const PART_OUT_STOCK = 17; // 部分缺货
    const PACKAGED = 18; // 已打包
    const CLAIMS = 19; // 理赔
    const RETURN_WAREHOUSE = 21; //退货入库
    const APPROVED = 22;  //已审核
    const PROBLEM = 23; //问题件
    const FORWARD = 24;  //已转寄
    const MATCH_FORWARDING = 25; //匹配转寄中
    const MATCH_FORWARDED = 26; //已匹配转寄状态
    const MATCH_FINISH = 27; //转寄完成
    const TESTING = 28; //测试订单
    const OUTOFSTOCK = 29; //没货取消
    const HIDDENORDER = 30; //隐藏订单

    const COMMIT = 2; //已提交
    const SAVE = 1;  //未提交

    const YES_AGAIN = 1; //可再次派货
    const NO_AGAIN = 2; //不可再次派货

    const UNMATCH = 0; //待匹配
    const HAS_MATCH = 1; //已匹配

    /**
     * 获取有效订单状态
     * @return array
     */
    public static function get_effective_status()
    {
        //return array(4,5,6,7,8,9,10,16,17,18,19,21,22,24,25,26,27);
        return array(
            self::UNPICKING ,       // 未配货
            self::PICKING,          // 配货中
            self::OUT_STOCK,        // 缺货
            self::PICKED,           // 已配货
            self::DELIVERING,       // 配送中
            self::SIGNED,           //已签收
            self::RETURNED,         // 已退货
            self::REJECTION,        // 拒收
            self::PART_OUT_STOCK,   // 部分缺货
            self::PACKAGED,         // 已打包
            self::CLAIMS,           // 理赔
            self::RETURN_WAREHOUSE, //退货入库
            self::APPROVED,         //已审核
            self::PROBLEM,          //问题件
            self::FORWARD,           //已转寄
            self::MATCH_FORWARDING,  //匹配转寄中
            self::MATCH_FORWARDED,   //已匹配转寄状态
            self::MATCH_FINISH       //转寄完成
        );
    }

    /**
     * 已审核订单显示的状态
     * @return array
     */
    public static function get_audited_order(){
        return array(
            self::APPROVED,//已审核
            self::UNPICKING,// 未配货
            self::PICKING,// 配货中
            self::OUT_STOCK,// 缺货
            self::PICKED,// 已配货
            self::DELIVERING,// 配送中
            self::SIGNED,// 已签收
            self::RETURNED,// 已退货
            self::PACKAGED,// 已打包
            self::PROBLEM//问题件
        );
    }

    /**
     * 所有可生成退货订单的状态
     * @return array
     */
    public static function get_all_can_order_return()
    {
        return array(
            self::PACKAGED, //已打包
            self::DELIVERING,  //配送中
            self::SIGNED,   //已签收
            self::RETURNED,   //已退货
            self::REJECTION,  //拒收
            self::CLAIMS,    //理赔
        );
    }

    /**
     * 所有拒签的订单状态
     * @return array　
     */
    public static function get_ref_signed_status()
    {
        return array(
            self::DELIVERING,   //8
            self::RETURNED,   //10
            self::REJECTION,   //16
            self::CLAIMS,   //19
            self::RETURN_WAREHOUSE,   //21
        );
    }

    /**
     * 所有未发货的订单状态
     * @return array　
     */
    public static function get_un_delivered_status(){
        return array(
            self::UNPICKING,    //4
            self::PICKING,      //5
            self::PICKED,       //7
            self::OUT_STOCK,    //6
            self::CANCELED,     //14
            self::PART_OUT_STOCK,   //17
            self::PACKAGED,   //18
            self::APPROVED      //22
        );
    }

    /**
     * 所有已发货的订单状态
     * @return array　
     */
    public static function get_delivered_status(){
        return array(
            self::DELIVERING,   //8
            self::SIGNED,   //9
            self::RETURNED,   //10
            self::REJECTION,   //16
            self::CLAIMS,   //19
            self::RETURN_WAREHOUSE,   //21
        );
    }
    
    /**
     * 获取问题件状态
     */
    public static function get_problem_status()
    {
        $arr = array(
            self::ADDRESS_ERROR => '地址错误',
            self::LOSE_PRODUCT => '丢件',
            self::LOGISTICS_NOT_WAREHOUSE => '物流未进仓',
            self::CLEARANCE => '清关',
            self::RETURN_PRODUCT => '退货',
            self::OTHER => '其他',
            self::UNCONFIRMED => '待确认',
            self::CUSTOMS_CHECKED => '海关待查',
        );
        return $arr;
    }

    /**
     * 获取取消订单需要回滚库存的状态
     */
    public static function get_canceled_to_rollback_status()
    {
        return array(
            self::PACKAGED, //已打包
            self::DELIVERING,  //配送中
            self::SIGNED,   //已签收
            self::RETURNED,   //已退货
            self::REJECTION,  //拒收
            self::CLAIMS,    //理赔
        );
    }

    /**
     * 获取退货订单状态
     */
    public static function get_order_return_status()
    {
        $arr = array(
            self::COMMIT => "已提交",
            self::SAVE => "未提交",
        );
        return $arr;
    }

    /**
     * 获取退货订单派货状态
     */
    public static function get_order_return_again()
    {
        $arr = array(
            self::NO_AGAIN => "否",
            self::YES_AGAIN => "是",
        );
        return $arr;
    }

    /**
     * 获取订单状态名称
     */
    public static function get_order_status_name()
    {
        return array(
            self::UNPROCESS => '未处理',
            self::PROCESSING => '待处理',
            self::VERIFICATION => '待审核',
            self::UNPICKING => '未配货',
            self::PICKING =>  '配货中',
            self::OUT_STOCK => '缺货',
            self::PICKED => '已配货',
            self::DELIVERING => '配送中',
            self::SIGNED => '已签收',
            self::RETURNED => '已退货',
            self::REPEAT => '重复下单',
            self::IMPERFECT => '信息不完整',
            self::MALICE => '恶意下单',
            self::CANCELED => '客户取消',
            self::DAMAGED => '质量问题',
            self::REJECTION => '拒收',
            self::PART_OUT_STOCK => '部分缺货',
            self::PACKAGED => '已打包',
            self::CLAIMS => '理赔',
            self::RETURN_WAREHOUSE => '退货入库',
            self::APPROVED => '已审核',
            self::PROBLEM => '问题件',
            self::FORWARD => '已转寄',
            self::MATCH_FORWARDING => '匹配转寄中',
            self::MATCH_FORWARDED => '已匹配转寄状态',
            self::MATCH_FINISH => '转寄完成',
            self::TESTING => '测试订单',
            self::OUTOFSTOCK => '没货取消',

        );
    }

    /**
     * 已处理的订单状态
     */
    public static function deal_order_status()
    {
        return array(
            self::VERIFICATION ,  // '待审核',
            self::UNPICKING ,   //'未配货',
            self::PICKING ,   // '配货中',
            self::OUT_STOCK ,   //'缺货',
            self::PICKED ,   //'已配货',
            self::DELIVERING ,   //'配送中',
            self::SIGNED ,   //'已签收',
            self::RETURNED ,   //'已退货',
            self::REPEAT ,   //'重复下单',
            self::IMPERFECT ,   //'信息不完整',
            self::MALICE ,   //'恶意下单',
            self::CANCELED ,   //'客户取消',
            self::DAMAGED ,   //'质量问题',
            self::REJECTION ,   //'拒收',
            self::PART_OUT_STOCK ,   //'部分缺货',
            self::PACKAGED ,   //'已打包',
            self::CLAIMS ,   //'理赔',
            self::RETURN_WAREHOUSE ,   //'退货入库',
            self::APPROVED ,   //'已审核',
            self::PROBLEM ,   //'问题件',
            self::FORWARD ,   //'已转寄',
            self::MATCH_FORWARDING ,   //'匹配转寄中',
            self::MATCH_FORWARDED ,   //'已匹配转寄状态',
            self::MATCH_FINISH ,   //'转寄完成',
            self::TESTING , //测试订单
            self::OUTOFSTOCK, //没货取消
        );
    }

    /**
     * 无效订单
     */
    public static function invalid_status()
    {
        return array(
            self::REPEAT ,   //'重复下单',
            self::IMPERFECT ,   //'信息不完整',
            self::MALICE ,   //'恶意下单',
            self::CANCELED ,   //'客户取消',
            self::DAMAGED ,   //'质量问题',
            self::UNPROCESS,   //未处理
            self::TESTING , //测试订单
            self::OUTOFSTOCK, //没货取消
        );
    }

}
