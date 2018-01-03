<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Tuolaji <479923197@qq.com>
// +----------------------------------------------------------------------
/**
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;
use Api\Controller\CheckIpController;
class PublicController extends AdminbaseController {

    public function _initialize() {
        C(S('sp_dynamic_config'));//加载动态配置
		//国外服务器 跳转到国内服务器// /* *Common\Controller\AdminbaseController;*/
		if($_SERVER['SERVER_NAME']=='www.hepxi.com' or $_SERVER['SERVER_NAME']=='hepxi.com'){
			//header("Location:http://erp.stosz.com/admin");
			//exit();
		}
    }
    
    //后台登陆界面
    public function login() {
        $admin_id=session('ADMIN_ID');
    	if(!empty($admin_id)){//已经登录
    		redirect(U("admin/index/index"));
    	}else{
    	    $site_admin_url_password =C("SP_SITE_ADMIN_URL_PASSWORD");
    	    $upw=session("__SP_UPW__");
    		if(!empty($site_admin_url_password) && $upw!=$site_admin_url_password){
    			redirect(__ROOT__."/");
    		}else{
				//获取IP并查询所在区域 	liuruibin	20171201
				$check_ip_obj = new CheckIpController();
				$IPaddress = get_client_ip();
				$IPcity = $check_ip_obj->getCity($IPaddress);
				$this->assign(compact('IPaddress','IPcity'));
    		    session("__SP_ADMIN_LOGIN_PAGE_SHOWED_SUCCESS__",true);
    			$this->display(":login");
    		}
    	}
    }
    
    public function logout(){
//        add_system_record($_SESSION['ADMIN_ID'], 2, 2, '用户'.$_SESSION['name'].'退出成功');
    	session('ADMIN_ID',null); 
    	redirect(__ROOT__."/admin/public/login");
    }
    
    public function dologin(){
        $login_page_showed_success=session("__SP_ADMIN_LOGIN_PAGE_SHOWED_SUCCESS__");
        if(!$login_page_showed_success){
            $this->error('login error!');
        }
    	$name = I("request.username");
    	if(empty($name)){
    		$this->error(L('USERNAME_OR_EMAIL_EMPTY'));
    	}
    	$pass = I("request.password");
    	if(empty($pass)){
    		$this->error(L('PASSWORD_REQUIRED'));
    	}
    	$verrify = I("request.verify");
    	if(empty($verrify)){
    		$this->error(L('CAPTCHA_REQUIRED'));
    	}
    	//验证码
    	if(!sp_check_verify_code()){
    		$this->error(L('CAPTCHA_NOT_RIGHT'));
    	}else{
    		$user = D("Common/Users");
    		if(strpos($name,"@")>0){//邮箱登陆
    			$where['user_email']=$name;
    		}else{
    			$where['user_login']=$name;
    		}
    		
    		$result = $user->where($where)->find();
    		if(!empty($result) && $result['user_type']==1){
    			if(sp_compare_password($pass,$result['user_pass'])){
    				
    				$role_user_model=M("RoleUser");
    				
    				$role_user_join = C('DB_PREFIX').'role as b on a.role_id =b.id';
    				
    				$groups=$role_user_model->alias("a")->join($role_user_join)->where(array("user_id"=>$result["id"],"status"=>1))->getField("role_id",true);
    				
    				if( $result["id"]!=1 && ( empty($groups) || empty($result['user_status']) ) ){
    					$this->error(L('USE_DISABLED'));
    				}
					//判断IP权限是否能登录    liuruibin   20171123
					$check_ip_obj = new CheckIpController();
					$allow_login = $check_ip_obj->checkLoginIP($result["id"]);
					if($allow_login === false){
						$this->error('非局域网不能登录');
					}
    				//登入成功页面跳转
    				session('ADMIN_ID',$result["id"]);
					session('belong_ware_id', explode(',', $result['belong_ware_id']));
					session('belong_zone_id', explode(',', $result['belong_zone_id']));
    				session('name',$result["user_login"]);
    				$result['last_login_ip'] = get_client_ip(0,true);
    				$result['last_login_time'] = date("Y-m-d H:i:s");
    				$user->save($result);
    				cookie("admin_username",$name,3600*24*30);
                    $Department_name = D('Common/Department')->getTableName();
                    $depart_where = array('du.id_users'=>$result["id"]);//,'d.type'=>1
                    $depart_user = D("Common/DepartmentUsers")->alias('du')->field('du.id_department')
                        ->join($Department_name.' d ON (d.id_department = du.id_department)', 'LEFT')
                        ->where($depart_where)->order('d.id_department asc')->select();
                    $depart_user = $depart_user?array_column($depart_user,'id_department'):'';
                    $allow_ip = D("Common/UsersAllowIp")->getField('ip',true);
                    F('user_allow_ip',json_encode($allow_ip));
                    session('last_login_time',time());
                    session('department_id',$depart_user);
                    session('last_login_ip',$result["last_login_ip"]);
                    add_system_record($_SESSION['ADMIN_ID'], 6, 3, '用户'.$_SESSION['name'].'登录成功');
    				$this->success(L('LOGIN_SUCCESS'),U("Index/index"));
    			}else{
    				$this->error(L('PASSWORD_NOT_RIGHT'));
    			}
    		}else{
    			$this->error(L('USERNAME_NOT_EXIST'));
    		}
    	}
    }


}