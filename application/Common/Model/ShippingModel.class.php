<?php
namespace Common\Model;
use Common\Model\CommonModel;

class ShippingModel extends CommonModel {

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('title', 'require', '物流名字不能为空！', CommonModel::MUST_VALIDATE, 'regex', CommonModel:: MODEL_BOTH),
        array('title', '', '物流名字已经存在！', 1, 'unique', CommonModel::MODEL_INSERT),
        array('track_url', 'require', '跟踪地址不能为空！', 1, 'regex', CommonModel:: MODEL_BOTH),
        //array('copy_url', 'require', '参考网站不能为空！', 0, 'callback', 'not_empty' ),
//        array('copy_url', 'require', '参考网站不能为空！', 1, 'regex', CommonModel:: MODEL_BOTH),
    );
    protected $_auto = array(
    );

    protected function _before_write(&$data) {
        parent::_before_write($data);
    }

    public function not_empty($arg) {
        return !empty($arg);
    }

}
