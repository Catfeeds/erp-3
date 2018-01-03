<?php
namespace Systemrecord\Controller;
use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;

class IndexController extends AdminbaseController {

    protected $system_record;

    public function _initialize() {
        parent::_initialize();
        $this->system_record = D("Systemrecord/SystemRecord");
    }

    /*
     * 记录列表
     */

    public function index() {
        $where = array();
        if(isset($_GET['type']) && $_GET['type']) {
            $where['type'] = $_GET['type'];
        }
        if(isset($_GET['obj_type']) && $_GET['obj_type']) {
            $where['category'] = $_GET['obj_type'];
        }
        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $where['desc'] = array('like','%'.$_GET['keyword'].'%');
        }
        if(isset($_GET['start_time']) && $_GET['start_time']) {
            $created_at_array = array();
            $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $created_at_array[] = array('LT', $_GET['end_time']);
            $where[] = array('created_at'=>$created_at_array);
        }
        
        $count = $this->system_record->where($where)->count();
        $page = $this->page($count, 20);        
        $data = $this->system_record->where($where)
                ->order(array("id_system_record" => "desc"))
                ->limit($page->firstRow, $page->listRows)
                ->select(); 
        foreach ($data as $k=>$v) {
            $user_name = M('Users')->where('id='.$v['id_users'])->getField('user_nicename');
            $data[$k]['user_name'] = !empty($user_name) ? $user_name : $v['user_name'];
        }
        
        $oper_type = SystemRecordModel::get_oper_type();//操作类型
        $oper_obj_type = SystemRecordModel::get_oper_obj_type();//操作类型对象
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看系统记录列表');
        $this->assign("proList", $data);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign('oper_type',$oper_type);
        $this->assign('oper_obj_type',$oper_obj_type);
        $this->display();
    }

    
}
