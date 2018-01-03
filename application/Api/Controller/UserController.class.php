<?php

namespace Api\Controller;

use Common\Controller\AdminbaseController;
use Api\Controller\CheckIpController;

class UserController extends AdminbaseController
{


    function _initialize ()
    {
    }

    public function login ()
    {
        if ( !IS_POST ) return false;
        $user = I('post.username');
        $pass = I('post.password');
        $where = [
            'user_status' => 1,
            'user_login' => $user,
        ];
        $result = M('Users')->where($where)->find();
        if ( !empty($result) && $result['user_type'] == 1 ) {

            if ( sp_compare_password($pass, $result['user_pass']) ) {

                $users = ['id_users' => $result['id'], 'user_login' => $result['user_login']];
                $deparment = M('Department')->where(['id_users' => $result['id']])->find();
                //IP权限判断登录    liuruibin   20171123
                $check_ip_obj = new CheckIpController();
                $allow_login = $check_ip_obj->checkLoginIP($result['id']);
                if($allow_login === false){
                    return $this->ajaxReturn(['status' => 0, 'msg' => '非局域网不能登录']);
                }
                return $this->ajaxReturn(['status' => 1, 'msg' => '登录成功', 'data' => [$users['id_users'],
                    $deparment['id_department']]]);
            } else {
                return $this->ajaxReturn(['status' => 0, 'msg' => '密码错误']);
            }
        } else {

            return $this->ajaxReturn(['status' => 0, 'msg' => '登录失败']);
        }
    }

}