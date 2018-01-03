<?php

namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class UserController extends AdminbaseController {

    protected $users_model, $role_model;

    public function _initialize() {
        parent::_initialize();
        $this->users_model = D("Common/Users");
        $this->role_model = D("Common/Role");
    }

    public function index() {
        $where = array("user_type" => 1);
        /*         * 搜索条件* */
        $user_login = I('request.user_login');
        $user_email = trim(I('request.user_email'));
        $user_tel = trim(I('request.user_tel'));
        $id_department = trim(I('request.id_department'));
        $id_role = trim(I('request.id_role'));
        if ($user_login) {
            $where_name['user_login'] = array('like', "%$user_login%");
            $where_name['user_nicename'] = array('like', "%$user_login%");
            $where_name['_logic'] = 'or';
            $where['_complex'] = $where_name;
        }
        if ($user_email) {
            $where['user_email'] = array('like', "%$user_email%");
            ;
        }
        if ($user_tel) {
            $where['user_tel'] = array('like', "%$user_tel%");
            ;
        }
        if($id_department){
            $whereD['id_department'] = array('EQ',$id_department);
            $deparid = D("department_users")->where($whereD)->getField("id_users",true);
       }
       if($id_role){
        $whereR['role_id'] = array("EQ",$id_role);
        $roleid = D("role_user")->where($whereR)->getField("user_id",true);
       }
       if($deparid && empty($roleid)){
        $where['id'] = array("IN",implode(",",$deparid));
       }else if($roleid && empty($deparid)){
        $where['id'] = array("IN",implode(",",$roleid));
       }else if($deparid && $roleid){
        $where['id'] = array("IN",implode(",",array_intersect($deparid,$roleid)));
       }
        $roles_src = $this->role_model->select();
        $roles = array();
        $role_id = array();
        foreach ($roles_src as $k=>$r) {
            $roleid = $r['id'];
            $role_id[$k] = $r['id'];
            $roles["$roleid"] = $r;
        }
        $count = $this->users_model->where($where)->count();
        $page = $this->page($count, 20);
        $users = $this->users_model
                ->where($where)
                ->order("create_time DESC")
                ->limit($page->firstRow, $page->listRows)
                ->select();
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看管理员列表');
        $role_list = $this->role_model->where("status=1")->getField('id,name', true);
        $depart_list = D('department')->order(' sort asc')->getField('id_department,title', true);
        $this->assign("page", $page->show('Admin'));
        $this->assign("roles", $role_list);
        $this->assign("users", $users);
        $this->assign("id_department", $id_department);
        $this->assign("id_role", $id_role);
        $this->assign("depart_list", $depart_list);
        $this->display();
    }

    public function add() {
        // 按角色显示权限名称  --Lily 2017-11-13
        $whereAll = $this->add_edit_condition();
        $where = $whereAll['role'];
        $roles = $this->role_model->where(array('status' => 1))->where($where)->order("id DESC")->select(); 
        $department = $whereAll['depart'];
        $all_user    = D("Common/Users")->field('id,user_nicename')->where(array('user_status'=>1))->select();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where(array('status'=>1))->select();
        $zone = M('Zone')->field('id_zone,title')->where(array('status'=>1))->select();
        $this->assign("all_user", $all_user);
        $this->assign("department", $department);
        $this->assign('warehouse',$warehouse);
        $this->assign("roles", $roles);
        $this->assign("zone", $zone);
        $this->display();
    }

    public function add_post() {
        if (IS_POST) { //print_r(json_encode($_POST));die;
            if (!empty($_POST['role_id']) && is_array($_POST['role_id'])) {
                $role_ids = $_POST['role_id'];
                unset($_POST['role_id']);
                if ($this->users_model->create() !== false) {
                    $result = $this->users_model->add();
                    if ($result !== false) {
                        $role_user_model = M("RoleUser");
                        foreach ($role_ids as $role_id) {
                            if (sp_get_current_admin_id() != 1 && $role_id == 1) {
                                $this->error("为了网站的安全，非网站创建者不可创建超级管理员！");
                            }
                            $role_user_model->add(array("role_id" => $role_id, "user_id" => $result));
                        }
                        $user_id = $result;
                        if(isset($_POST['belong_ware_id']) && count($_POST['belong_ware_id']) && $user_id) {
                            $data['belong_ware_id'] = implode(',', $_POST['belong_ware_id']);
                            $this->users_model->where(array('id'=>$user_id))->save($data);
                        }
                        if(isset($_POST['belong_zone_id']) && count($_POST['belong_zone_id'])) {
                            $data['belong_zone_id'] = implode(',', $_POST['belong_zone_id']);
                            $this->users_model->where(array('id'=>$user_id))->save($data);
                        }
                        if (isset($_POST['id_department']) && count($_POST['id_department']) && $user_id) {
                            $depart_user = D("Common/DepartmentUsers");
                            $department = $_POST['id_department'];
                            foreach ($department as $item) {
                                $result = $depart_user->where('id_department=' . $item . ' and id_users=' . $user_id)->find();
                                if (!$result) {
                                    $addData = array('id_department' => $item, 'id_users' => $user_id);
                                    $depart_user->data($addData)->add();
                                }
                            }
                        }
                        add_system_record($_SESSION['ADMIN_ID'], 1, 3, '创建后台用户成功');
                        $this->success("添加成功！", U("user/index"));
                    } else {
                        add_system_record($_SESSION['ADMIN_ID'], 1, 3, '创建后台用户失败');
                        $this->error("添加失败！");
                    }
                } else {
                    $this->error($this->users_model->getError());
                }
            } else {
                $this->error("请为此用户指定角色！");
            }
        }
    }

/**
** 管理员 添加 编辑 按角色显示权限名称 业务部部门显示限制 -- Lily 2017-11-13
**/
    public function add_edit_condition(){
        $admin_id = $_SESSION['ADMIN_ID'];
        //角色限制
        $role_id = M("Role")->alias("r")->join("__ROLE_USER__ AS ru On r.id=ru.role_id","LEFT")->where("ru.user_id=".$admin_id)->getField("id",true);
        if($role_id){
        if(in_array("62", $role_id) ||in_array("1", $role_id)  ){
            //超级用户  业务总监
           $where = array();
           $whereD = array();
       }else if(in_array("11", $role_id)){
        //采购部
            $whereD = array();
            $where['id'] = array("IN",array(11,12,13,38,82,84,48));
       }else if(in_array("14", $role_id)  || in_array("72", $role_id)){
        //技术部
            $whereD = array();
            $where['id'] = array("IN",array(14,15,16,64,72,81,85,83,47));
       }else if(in_array("30", $role_id) || in_array("62", $role_id)){
        //业务部
            $where['id'] = array("IN",array(28,29,30,34,35,36,37,66));
            $whereD['id_department'] = array("IN",$_SESSION['department_id']);
       }else if(in_array("8", $role_id)){
        //财务部
            $whereD = array();
            $where['id'] = array("IN",array(8,9,10,60));
       }else if(in_array("26", $role_id)){
        //仓储部
            $whereD = array();
            $where['id'] = array("IN",array(26,27,33,39,40,41,43,78));
       }else if(in_array("50", $role_id) ){
        //客服部
            $whereD = array();
            $where['id'] = array("IN",array(31,32,50,52));
       }else if(in_array("54", $role_id)){
        //物流
            $whereD = array();
            $where['id'] = array("IN",array(54,56));
       }
   }
   $condition['role'] = $where;
   $depatD = M("Department")->where($whereD)->select();
    $condition['depart'] = $depatD;
    return $condition;
    }
    public function edit() {
        // 按角色显示权限名称  --Lily 2017-11-13
        $whereAll = $this->add_edit_condition();
        $where = $whereAll['role'];
        $id = I('get.id', 0, 'intval');
        $roles = $this->role_model->where(array('status' => 1))->where($where)->order("id DESC")->select();
        $this->assign("roles", $roles);
        $role_user_model = M("RoleUser");
        $role_ids = $role_user_model->where(array("user_id" => $id))->getField("role_id", true);
        $this->assign("role_ids", $role_ids);

        $user = $this->users_model->where(array("id" => $id))->find();
        $department = $whereAll['depart'];
        $depart_user = D("Common/DepartmentUsers")->where('id_users=' . $id)->getField('id_department', true);
        $all_user    = D("Common/Users")->field('id,user_nicename')->where(array('user_status'=>1))->order('user_nicename desc')->select();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where(array('status'=>1))->select();
        $warehouse_user = M('Users')->field('belong_ware_id')->where(array('id'=>$id))->find();
        $warehouse_user = explode(',', $warehouse_user['belong_ware_id']);
        $zone = M('Zone')->field('id_zone,title')->where(array('status'=>1))->select();
        $zone_user = M('Users')->field('belong_zone_id')->where(array('id'=>$id))->find();

        $zone_user = explode(',', $zone_user['belong_zone_id']);
        $this->assign("all_user", $all_user);
        $this->assign("department", $department);
        $this->assign("depart_user", $depart_user);
        $this->assign('warehouse_user',$warehouse_user);
        $this->assign('warehouse',$warehouse);
        $this->assign('zone_user',$zone_user);
        $this->assign('zone',$zone);
        $this->assign($user);
        $this->display();
    }

    public function edit_post() {
        if (IS_POST) {
            if (!empty($_POST['role_id']) && is_array($_POST['role_id'])) {
                if (empty($_POST['user_pass'])) {
                    unset($_POST['user_pass']);
                }
                $role_ids = I('post.role_id/a');
                unset($_POST['role_id']);
                $uid = I('post.id', 0, 'intval');                
                if ($this->users_model->create() !== false) {                    
                    $result = $this->users_model->save();
                    if ($result !== false) {
                        $role_user_model = M("RoleUser");
                        $role_user_model->where(array("user_id" => $uid))->delete();
                        foreach ($role_ids as $role_id) {
                            if (sp_get_current_admin_id() != 1 && $role_id == 1) {
                                $this->error("为了网站的安全，非网站创建者不可创建超级管理员！");
                            }
                            $role_user_model->add(array("role_id" => $role_id, "user_id" => $uid));
                        }
                        if(isset($_POST['belong_ware_id']) && count($_POST['belong_ware_id']) && $uid) {
                            $data['belong_ware_id'] = implode(',', $_POST['belong_ware_id']);
                            $this->users_model->where(array('id'=>$uid))->save($data);
                        }
                        if(isset($_POST['belong_zone_id']) && count($_POST['belong_zone_id']) && $uid) {
                            $data['belong_zone_id'] = implode(',', $_POST['belong_zone_id']);
                            $this->users_model->where(array('id'=>$uid))->save($data);
                        }
                        if (isset($_POST['id_department']) && count($_POST['id_department']) && $uid) {
                            $departUser   = D("Common/DepartmentUsers");
                            $department   = $_POST['id_department'];
                            $select_where = array('id_department'=>array('NOT IN',$department),'id_users'=>$uid);
                            $not_in_data  = $departUser->where($select_where)->select();
                            if($not_in_data){
                                foreach($not_in_data as $item){
                                    $id_dep_use = $item['id_department_users'];
                                    $departUser->delete($id_dep_use);
                                }
                            }
                            foreach ($department as $item) {
                                $add_where = array('id_department'=>$item,'id_users'=>$uid);
                                $result    = $departUser->where($add_where)->find();
                                if (!$result) {
                                    $addData = array('id_department' => $item, 'id_users' => $uid);
                                    $departUser->data($addData)->add();
                                }
                            }
                        }
                        $this->success("保存成功！");
                    } else {
                        if ($_POST['id'] && $uid) {
                            $role_user_model = M("RoleUser");
                            $update = array('user_email' => $_POST['user_email'],
                                'user_login' => $_POST['user_login'],
                                'user_nicename' => $_POST['user_nicename'],
                                'user_tel' => $_POST['user_tel']);
                            $this->users_model->save($update);
                            $role_user_model->where(array("user_id" => $uid))->delete();
                            foreach ($role_ids as $role_id) {
                                $role_user_model->add(array("role_id" => $role_id, "user_id" => $uid));
                            }
                        } else {
                            $this->error("保存失败！");
                        }
                        $this->success("保存成功！");
                    }
                } else {
                    $this->error($this->users_model->getError());
                }
                add_system_record($_SESSION['ADMIN_ID'], 2, 3, '保存管理员');
            } else {
                $this->error("请为此用户指定角色！");
            }
        }
    }

    /**
     *  删除
     */
    public function delete() {
        $id = I('get.id', 0, 'intval');
        if ($id == 1) {
            $this->error("最高管理员不能删除！");
        }

        if ($this->users_model->delete($id) !== false) {
            M("RoleUser")->where(array("user_id" => $id))->delete();
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除用户'.$_SESSION['name'].'成功');
            $this->success("删除成功！");
        } else {
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除用户'.$_SESSION['name'].'失败');
            $this->error("删除失败！");
        }
    }

    public function userinfo() {
        $id = sp_get_current_admin_id();
        $user = $this->users_model->where(array("id" => $id))->find();
        $this->assign($user);
        $this->display();
    }

    public function userinfo_post() {
        if (IS_POST) {
            $_POST['id'] = sp_get_current_admin_id();
            $create_result = $this->users_model
                    ->field("id,user_nicename,sex,birthday,user_url,signature")
                    ->create();
            if ($create_result !== false) {
                if ($this->users_model->save() !== false) {
                    $this->success("保存成功！");
                } else {
                    $this->error("保存失败！");
                }
            } else {
                $this->error($this->users_model->getError());
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '修改管理员信息');
        }
    }

    public function ban() {
        $id = I('get.id', 0, 'intval');
        if (!empty($id)) {
            $result = $this->users_model->where(array("id" => $id, "user_type" => 1))->setField('user_status', '0');
            if ($result !== false) {
                $this->success("管理员停用成功！", U("user/index"));
            } else {
                $this->error('管理员停用失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
        add_system_record($_SESSION['ADMIN_ID'], 6, 3, '停用管理员');
    }

    public function cancelban() {
        $id = I('get.id', 0, 'intval');
        if (!empty($id)) {
            $result = $this->users_model->where(array("id" => $id, "user_type" => 1))->setField('user_status', '1');
            if ($result !== false) {
                $this->success("管理员启用成功！", U("user/index"));
            } else {
                $this->error('管理员启用失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
        add_system_record($_SESSION['ADMIN_ID'], 6, 3, '启用管理员');
    }

}
