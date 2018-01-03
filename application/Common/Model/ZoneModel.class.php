<?php
namespace Common\Model;
use Common\Model\CommonModel;
class ZoneModel extends CommonModel
{
	
	protected $_validate = array(
		//array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
		//array('user_login', 'require', '用户名称不能为空！', 1, 'regex', CommonModel:: MODEL_INSERT  ),
	);
	
	protected $_auto = array(
	    //array('create_time','mGetDate',CommonModel:: MODEL_INSERT,'callback'),
	);
	protected function _before_write(&$data) {
		parent::_before_write($data);
	}

    /**
     * 是有地址
     * @return array|mixed|string
     */
    public function all_zone(){
        $get_all_zone = F('get_web_all_zone');
        if($get_all_zone){
            $all_zone = json_decode($get_all_zone,true);
        }else{
            $all_zone = $this->field('`title`,id_zone')->order('`title` ASC')->cache(true, 36000)->select();
            $all_zone = $all_zone?array_column($all_zone,'title','id_zone'):'';
            F('get_web_all_zone',json_encode($all_zone));
        }
        return $all_zone;
    }
}

