<?php
namespace Supplier\Controller;
use Common\Controller\AdminbaseController;

class IndexController extends AdminbaseController {

    protected $supplier;

    public function _initialize() {
        parent::_initialize();
        $this->supplier = D("Common/Supplier");
    }
    
    //所有供应商列表
//    public function all_list() {
//        $user_id = sp_get_current_admin_id();
//        $where = array();
//        if(isset($_GET['sname']) && $_GET['sname']) {
//            $where['title'] = array('like',array('%'.$_GET['sname'].'%'));
//        }
//        if(isset($_GET['id_users']) && $_GET['id_users']) {
//            $where['id_users'] = array('EQ',$_GET['id_users']);
//        }
//        
//        $users = M('Users')->field('id,user_nicename')->where(array('superior_user_id'=>$user_id))->select();
//        $users = array_column($users, 'user_nicename', 'id');
//        
//        $count = $this->supplier->where($where)->count();
//        
//        $page = $this->page($count, 20);
//
//        $data = $this->supplier->where($where)
//                ->order(array("id_supplier" => "desc"))
//                ->limit($page->firstRow, $page->listRows)
//                ->select(); //获取跟当前部门有关的数据
//        foreach ($data as $k=>$v) {
//            $data[$k]['uname'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
//        }
//        $this->assign("suppliers", $data);
//        $this->assign("Page", $page->show('Admin'));
//        $this->assign("current_page", $page->GetCurrentPage());
//        $this->assign('users',$users);
//        $this->display();
//    }

    /*
     * 供应商列表
     */

    public function index() {
        $dep = $_SESSION['department_id'];        
        $user_id = sp_get_current_admin_id();
        $where = array();
        if(isset($_GET['sname']) && $_GET['sname']) {
            $where['title'] = array('like',array('%'.$_GET['sname'].'%'));
        }
        if(isset($_GET['id_users']) && $_GET['id_users']) {
            $where['id_users'] = array('EQ',$_GET['id_users']);
        }
        
        $department = M('Department')->where(array('id_users'=>$user_id))->find();
        $users = M('Users')->field('id,user_nicename')->where(array('superior_user_id'=>$user_id))->select();
        $users = array_column($users, 'user_nicename', 'id');
        
        $flag = 1;
        if(!$department) {
            $where['id_department'] = array('IN', $dep);
            $where['id_users'] = array('EQ',$user_id);
            $flag = 2;
        }

        $count = $this->supplier->where($where)->count();
        
        $page = $this->page($count, 20);

        $data = $this->supplier->where($where)
                ->order(array("id_supplier" => "desc"))
                ->limit($page->firstRow, $page->listRows)
                ->select(); //获取跟当前部门有关的数据
        
        foreach ($data as $k=>$v) {
            $data[$k]['uname'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
        }
        
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看供应商列表');
        $this->assign("suppliers", $data);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign('users',$users);
        $this->assign('flag',$flag);
        $this->display();
    }

    /**
     * 添加
     */
    public function add() {
        $dep = $_SESSION['department_id'];
        $where['id_department'] = array('IN', $dep);
        $where['type'] = 1;
        $list = D("Common/Department")->where($where)->order('id_department ASC')
                ->select();
        $this->assign("list", $list);
        $this->display();
    }
    
    /**
     * 添加
     */
    public function add_post() {
        if (IS_POST) {
            $data = I('post.');
            $data['created_at'] = date('Y-m-d H:i:s', time());
            $data['id_users'] = sp_get_current_admin_id();
            if ($this->supplier->create($data)) {
                if ($this->supplier->add($data)) {
                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加供应商成功');
                    $this->success("供应商添加成功", U("index/index"));                                       
                } else {
                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加供应商失败');
                    $this->error('供应商添加失败');
                }
            } else {
                $this->error($this->supplier->getError());
            }
        }
    }
    
    /**
     * 编辑
     */
    public function edit() {
        $id = intval(I("get.id"));
        if ($id == 0) {
            $id = intval(I("post.id"));
        }
        $dom_table_name = D("Common/Supplier")->getTableName();
        $dep_table_name = D("Common/Department")->getTableName();
        
        $dep = $_SESSION['department_id'];
        $where['id_department'] = array('IN', $dep);
        $where['type'] = 1;
        
        $list = D("Common/Department")->where($where)->order('id_department ASC')
                ->select();
        
        $data = $this->supplier->where(array("id_supplier" => $id))->find();
        if (!$data) {
            $this->error("该供应商不存在！");
        }
        $this->assign("data", $data);
        $this->assign("list", $list);
        $this->display();
    }
    
    /**
     * 编辑
     */
    public function edit_post() {
        $id = intval(I("get.id"));
        if ($id == 0) {
            $id = intval(I("post.id"));
        }
        if (IS_POST) {
            if ($this->supplier->create()) {
                if ($this->supplier->save()) {
                    add_system_record(sp_get_current_admin_id(), 2, 3, '修改供应商'.I('post.id_supplier').'成功');
                    $this->success("修改成功！", U('index/index'));
                } else {
                    add_system_record(sp_get_current_admin_id(), 2, 3, '修改供应商'.I('post.id_supplier').'失败');
                    $this->error("修改失败！");
                }
            } else {
                $this->error($this->supplier->getError());
            }
        }
    }


    /*
     * 删除
     */
    public function delete() {
        $id = intval(I("get.id"));
        $status = $this->supplier->delete($id);   
        if ($status) {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除供应商'.$id.'成功');
            $this->success("删除成功！", U('index/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除供应商'.$id.'失败');
            $this->error("删除失败！");
        }
    }
}
