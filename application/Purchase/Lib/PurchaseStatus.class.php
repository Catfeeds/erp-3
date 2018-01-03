<?php
/**
 * Created by Eva <549251235@qq.com>.
 * Date: 2017/3/17 14:33
 * Description:
 */

namespace Purchase\Lib;


/**
 * 采购状态
 * @package Purchase\Lib
 */
class PurchaseStatus
{
    const UNSUBMIT        =   1  ;       //待提交
    const UNCHECK         =   2  ;       //待审核
    const FINISHCHECK     =   3  ;       //已审核
    const REJECTCHECK     =   4  ;       //已驳回
    const PAYMENT         =   5  ;       //已付款
    const REJECTPAYMENT   =   6  ;       //拒绝付款
    const PART_RECEIVE    =   7  ;       //部分收货
    const FINISH          =   8  ;       //已完成
    const REJECT          =   9  ;       //拒收
    const CANCEL          =   10 ;       //已取消
    const CANCLE          =   10 ;       //已取消（兼容）
    const PART_ON_SALE    =   11 ;       //部分上架
    const FINISH_RECEIVE  =   12 ;       //收货完成

    //收货列表展示状态
    public static function received_list_status(){
        return array(
            self::PAYMENT => '已付款',            //已付款
            self::PART_RECEIVE => '部分收货',       //部分收货
            self::FINISH_RECEIVE => '收货完成',     //收货完成
            self::REJECT=>'拒收',             //拒收
        );
    }

    //上架列表展示状态
    public static function onsale_list_status(){
        return array(
            self::FINISH_RECEIVE => '已收货',     //已收货
            self::PART_RECEIVE => '部分收货',       //部分收货
            self::PART_ON_SALE => '部分上架',       //部分收货
            self::FINISH => '已完成',             //已完成
        );
    }
}