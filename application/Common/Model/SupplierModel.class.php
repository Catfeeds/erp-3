<?php
namespace Common\Model;
use Common\Model\CommonModel;
class SupplierModel extends CommonModel{
	
	//自动验证
	protected $_validate = array(
			//array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('title', 'require', '供应商名称不能为空！', CommonModel::MUST_VALIDATE, 'regex', CommonModel:: MODEL_BOTH ),
        array('title', '', '供应商已经存在！', 1, 'unique', CommonModel::MODEL_INSERT ),
        //array('ip', 'require', 'IP地址不能为空！', 1, 'regex', CommonModel:: MODEL_BOTH ),
        //array('copy_url', 'require', '参考网站不能为空！', 0, 'callback', 'not_empty' ),
        //array('copy_url', 'require', '参考网站不能为空！', 1, 'regex', CommonModel:: MODEL_BOTH ),
	);
	
	protected $_auto = array(
        
	);
	
	protected function _before_write(&$data) {
		parent::_before_write($data);
	}
	public function not_empty($arg)
    {
        return !empty($arg);
    }
}