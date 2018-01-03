<?php
namespace SystemRecord\Model;
use Common\Model\CommonModel;
class SystemRecordModel extends CommonModel{
	
    const TYPE_INSER = 1; //新增
    const TYPE_EDIT = 2; //编辑
    const TYPE_DELETE = 3; //删除
    const TYPE_CHECK = 4; //查看
    const TYPE_IMPORT = 5;//导入
    const TYPE_ORTHER = 6;//其他
    const TYPE_EXPORT = 7;//导出

    const WAREHOUSE = 1; //仓库
    const PRODUCT = 2; //产品    
    const ORTHER = 3;//其他
    const ORDER = 4; //订单
    
    protected function _before_write(&$data) {
        parent::_before_write($data);
    }
    
    public function not_empty($arg)
    {
        return !empty($arg);
    }
    
    //获取操作类型
    public static function get_oper_type()
    {
        $arr = array(
            self::TYPE_INSER => '新增',
            self::TYPE_EDIT => '编辑',
            self::TYPE_DELETE => '删除',
            self::TYPE_CHECK => '查看',
            self::TYPE_IMPORT => '导入',
            self::TYPE_ORTHER => '其他',
            self::TYPE_EXPORT => '导出',
        );
        return $arr;
    }
    
    //获取操作类型对象
    public static function get_oper_obj_type()
    {
        $arr = array(
            self::WAREHOUSE => '仓库',
            self::PRODUCT => '产品',
            self::ORTHER => '其他',
            self::ORDER => '订单',
        );
        return $arr;
    }
}