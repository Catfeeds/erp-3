<?php
namespace Domain\Model;
use Common\Model\CommonModel;
class DomainModel extends CommonModel {
    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('name', 'require', '域名不能为空！', CommonModel::MUST_VALIDATE, 'regex', CommonModel:: MODEL_BOTH),
        array('name', '', '域名已经存在！', 1, 'unique', CommonModel::MODEL_INSERT),
        array('ip', 'require', 'IP地址不能为空！', 1, 'regex', CommonModel:: MODEL_BOTH),
        //array('copy_url', 'require', '参考网站不能为空！', 0, 'callback', 'not_empty' ),
        array('copy_url', 'require', '参考网站不能为空！', 1, 'regex', CommonModel:: MODEL_BOTH),
    );
    protected $_auto = array(
    );
    protected function _before_write(&$data) {
        parent::_before_write($data);
    }
    public function not_empty($arg) {
        return !empty($arg);
    }

    /**
     * 下单
     * @param $domain_name
     * @return int
     */
    public function get_domain_id($domain_name){
        $data = $this->field('id_domain')->where(array('name'=>array('EQ',$domain_name)))->cache(true,3600)->find();
        return isset($data['id_domain']) && $data['id_domain']?$data['id_domain']:0;
    }
    public function get_domain($domain_name){
        $data = $this->where(array('name'=>array('EQ',$domain_name)))->cache(true,3600)->find();
        return $data;
    }

    /**
     * 获取当前部门所有域名
     * @return array
     */
    public function get_all_domain(){
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where['id_department'] = array('IN', $department_id);
        $all_domain = D('Domain/Domain')
            ->field('`name`,id_domain')->where(array('id_department'=>array('IN',$department_id)))
            ->order('`name` ASC')->cache(true, 1200)
            ->select();
        $return = $all_domain?array_column($all_domain,'name','id_domain'):array();
        return $return;
    }

    /**
     * 获取广告投放地址
     * @return array|mixed
     */
    public function get_all_real_address(){
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $de_md5 = md5(json_encode($department_id));
        $f_key = 'domain_all_real_address_cache'.$de_md5;
        $get_real_address_cache = F($f_key);
        if($get_real_address_cache && $get_real_address_cache!='[]'){
            $real_address = json_decode($get_real_address_cache,true);
        }else{
            $real_address = D('Domain/Domain')
                ->field('`name`,real_address,id_domain,id_department')->where(array('id_department'=>array('IN',$department_id)))
                ->order('`name` ASC')->cache(true, 1200)
                ->select();
            $real_address = $real_address?array_column($real_address,'real_address','id_domain'):array();
            F($f_key,json_encode($real_address));
        }
        return $real_address;
    }
}