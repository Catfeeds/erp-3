<?php
/**
 * Created by Liuruibin.
 * Date: 2017/11/23
 * Time: 15:41
 */

namespace Api\Controller;

use Common\Controller\AdminbaseController;


class CheckIpController extends AdminbaseController
{
    protected $login_auth_model,$auth_apply_model,$depart_users;

    function _initialize()
    {
        $this->login_auth_model = D('Common/LoginAuthIp');
        $this->auth_apply_model = D('Common/LoginAuthApply');
        $this->depart_users = D('Common/DepartmentUsers');
    }

    /**
     * 根据用户ID 判断在当前的IP地址是否有权限登录
     * @param null $id_users 参数 用户ID号
     */
    public function checkLoginIP($id_users=null){
        //检查系统是否启动限制
        $login_setting = M('LoginSetting')->where('auth_model = "login"')->find();
        if($login_setting['is_open'] == 0){
            //状态为0，未开启限制
            return true;
        }
        //空参数返回
        if(empty($id_users)){
            return false;
        }
        //查询是否研发部的账号
        $departInfo = $this->depart_users->where('id_department = 21 and id_users = '.$id_users)->find();
        //如果是 系统管理员/研发部 账号 直接返回true
        if($id_users == 1 || $departInfo){
            return true;
        }

        //获取当前时间
        $time_current = strtotime(date('Y-m-d H:i:s'));
        $login_info = $this->login_auth_model->where('id_users = '.$id_users)->order('non_lan_end')->find();//按最后限制的时间查出来
        //如果当前时间已经超出设置时间，没有限制，直接可以登录
        if ($login_info['non_lan_end'] < $time_current) {
            return true;
        }


        //获取IP段 进行IP段的查询
        $get_ip_top = $this->getIp2();
        $login_auth_where['ip_addf'] = array(array('EQ',$get_ip_top[0]),array('EQ',$get_ip_top[1]),array('EQ',$get_ip_top[2]),'or');
        $login_auth_where['id_users'] = $id_users;
        $login_info = $this->login_auth_model->where($login_auth_where)->find();//按最后限制的时间查出来
        //IP权限信息存在用户的IP段 返回true
        if($login_info){
            return true;
        }

        //以上都没有，则进行申请表查询
        $auth_apply_where['id_users'] = array('EQ',$id_users);
        $auth_apply_where['developing_status'] = array('EQ',2);//研发部审核通过
        $auth_apply_where['use_start_time'] = array('ELT',$time_current);
        $auth_apply_where['use_end_time'] = array('EGT',$time_current);
        $auth_apply_info = $this->auth_apply_model->where($auth_apply_where)->find();

        //审核通过 并且登录时间在申请使用的时间范围内
        if($auth_apply_info){
           return true;
        }else{
            return false;
        }

    }

    /**
     * 根据当前IP地址去截取前2段
     * @return string 返回IP号的前两段
     */
    public function getIp2(){
        $ip_current = get_client_ip();
        $ip_arr = explode('.',$ip_current);
        $ip_top1 = $ip_arr[0];//1段IP
        $ip_top2 = $ip_arr[0].'.'.$ip_arr[1];//2段IP
        $ip_top3 = $ip_arr[0].'.'.$ip_arr[1].'.'.$ip_arr[2];//3段IP
        $ip_top_arr = array($ip_top1,$ip_top2,$ip_top3);
        return $ip_top_arr;
    }

    /**
     * 根据IP查询城市信息
     * @param null $clientIP IP地址
     * @return string   所在城市
     * Remark：liuruibin 20171201
     */
    public function getCity($clientIP=null){
        $interfaceIP = 'http://ip.taobao.com/service/getIpInfo.php?ip='.$clientIP;
        $IPinfo = json_decode(file_get_contents($interfaceIP));
        $province = $IPinfo->data->region;
        $city = $IPinfo->data->city;
        $data = $province.$city;
        return $data;
    }
}