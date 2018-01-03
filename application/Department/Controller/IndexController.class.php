<?php

namespace Department\Controller;

use Common\Controller\AdminbaseController;

/**
 * 部门模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Department\Controller
 */
class IndexController extends AdminbaseController {

    protected $department;

    public function _initialize() {
        parent::_initialize();
        $this->department = D("Common/Department");
    }
    public function checkdiff($a1,$a2) {
        $dif1=array_diff($a1,$a2);
        $dif2=array_diff($a2,$a1);
        $diff=array_merge($dif1,$dif2);
        return $diff;
    }
    public function index() {
        $M = new \Think\Model;               
        $dep_table_name = D("Common/Department")->getTableName();
        $gro_table_name = D("Common/DepartmentGroup")->getTableName();
        $user_table_name = D("Common/Users")->getTableName();       
        $list = $M->table($dep_table_name . ' AS d LEFT JOIN ' . $user_table_name . ' AS u ON d.id_users=u.id')
                ->field('d.*,u.user_nicename')->order('d.sort asc')
                ->select();
        $list = catTree($list); //调用无限级分类函数
        $this->assign("list", $list);
        $this->display();
    }
     
    public function create() {
        $id = I('get.id');
        $data = array();
        if ($id) {
            $data = $this->department->find($id);
        }
        //获取上级部门 id   -Lily 2017-11-30
        $parent_id = $this->department->field("parent_id")->find($id);
        if($parent_id['parent_id']!="0"){
            $parent_id = $parent_id['parent_id'];
        }
        $user = D('Common/Users')->order('id desc')->select();
        $department = D("Common/Department")->order('sort asc')->select();
        $this->assign("department", $department);
        $this->assign("user", $user);
        $this->assign("parent_id", $parent_id);
        $this->assign("data", $data);
        $this->display();
    }
    public function creategroup() {
        $did = I('get.did');

        $ddata =  D("Common/DepartmentGroup")->find($did);
        $gid = I('get.gid');
        $gdata=array();
        if($gid){
            $gdata = D("Common/DepartmentGroup")->find($gid);

            $departUser   = D("Common/GroupUsers");
            $select_where = array('id_department'=>$gid);
            $groupuser_select  = $departUser->where($select_where)->field('id_users')->select();
            $groupuser=array();
            foreach($groupuser_select as  $v){
                array_push($groupuser,$v['id_users']);
            }
        }
        $user = D('Common/Users')->order('user_nicename desc')->select();
        $department = D("Common/Department")->order('id_department desc')->where(array('type'=>array('NEQ',2)))->select();
        $this->assign("department", $department);
        $this->assign("user", $user);
        $this->assign("groupuser", $groupuser);
        $this->assign("ddata", $ddata);
        $this->assign("gdata", $gdata);
        $this->display();
    }

    public function save_post() {
        if (IS_POST) {
            $data = $_POST;
            $data['updated_at'] = date('Y-m-d H:i:s');
            if (isset($data['id']) && $data['id']) {
                $result = $this->department->where('id_department=' . $data['id'])->save($data);
                $msg = $result ? '部门' . $data['id'] . '修改成功' : '部门' . $data['id'] . '修改失败';
                $status = 2;
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $result = $this->department->data($data)->add();
                $msg = $result ? '部门添加成功' : '部门添加失败';
                $status = 1;
            }
            if ($result) {
                add_system_record(sp_get_current_admin_id(), $status, 3, $msg);
                $this->success($msg, U("index/index"));
            } else {
                add_system_record(sp_get_current_admin_id(), $status, 3, $msg);
                $this->error($msg, U("index/index"));
            }
        } else {
            $this->error("保存失败");
        }
    }
    public function save_group_post() {

        if (IS_POST) {
            $data = $_POST;
            $data['updated_at'] = date('Y-m-d H:i:s');
            if (isset($data['id']) && $data['id']) {
                $result =D("Common/DepartmentGroup")->where('id_department=' . $data['id'])->save($data);
                $msg = $result ? '小组' . $data['id'] . '修改成功' : '小组' . $data['id'] . '修改失败';
                $status = 2;
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $result = D("Common/DepartmentGroup")->data($data)->add();
                $msg = $result ? '小组添加成功' : '小组添加失败';
                $status = 1;
                $data['id']=$result;
            }
            if ($result) {
                if (isset($data['group_user_id']) && count($data['group_user_id'])) {
                    $departUser   = D("Common/GroupUsers");
                    $department   = $data['id'];
                    //get group user
                    $select_where = array('id_department'=>$department);
                    $userdata_select  = $departUser->where($select_where)->field('id_users')->select();
                    $userdata=array();
                    foreach($userdata_select as  $v){
                        array_push($userdata,$v['id_users']);
                    }
                    if(!empty($userdata)){
                        $diff=$this->checkdiff($userdata,$data['group_user_id']);
                    }else{
                        $diff=$_POST['group_user_id'];
                    }
                    if(!empty($diff)){
                        foreach($diff as $v){
                            $where= array('id_department' => $department, 'id_users' => $v);
                            if(in_array($v,$_POST['group_user_id'] )){
                                $ret=$departUser->data($where)->add();
                            }else{
                                $ret=$departUser->where($where)->delete();
                            }
                        }
                    }
                }
                add_system_record(sp_get_current_admin_id(), $status, 3, $msg);
                $this->success($msg, U("index/index"));
            } else {
                add_system_record(sp_get_current_admin_id(), $status, 3, $msg);
                $this->error($msg, U("index/index"));
            }
        } else {
            $this->error("保存失败");
        }
    }
    /*
     * 部门删除
     */

    public function delete() {
        $id = intval(I("get.id"));
        $count=$this->department->where(array("parent_id" => $id))->count();
        if ($count > 0) {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除部门' . $id . '失败');
            $this->error("请先删除该部门下的小组再删除部门！");
        }
        $status = $this->department->delete($id);
        if ($status) {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除部门' . $id . '成功');
            $this->success("删除成功！", U('index/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除部门' . $id . '失败');
            $this->error("删除失败！");
        }
    }

    /*
     * 小组删除
     */
    public function deletegroup() {
        $id = intval(I("get.id"));
        $status = D("Common/DepartmentGroup")->delete($id);
        if ($status) {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除小组' . $id . '成功');
            $this->success("删除成功！", U('index/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除小组' . $id . '失败');
            $this->error("删除失败！");
        }
    }

}
