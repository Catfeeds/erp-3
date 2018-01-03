<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class MainController extends AdminbaseController {
	
    public function index(){
    	$mysql= M()->query("select VERSION() as version");
    	$mysql=$mysql[0]['version'];
    	$mysql=empty($mysql)?L('UNKNOWN'):$mysql;
    	
    	//server infomaions
    	$info = array(
    			L('OPERATING_SYSTEM') => PHP_OS,
    			L('OPERATING_ENVIRONMENT') => $_SERVER["SERVER_SOFTWARE"],
    	        L('PHP_VERSION') => PHP_VERSION,
    			L('PHP_RUN_MODE') => php_sapi_name(),
				L('PHP_VERSION') => phpversion(),
    			L('MYSQL_VERSION') =>$mysql,
    			L('PROGRAM_VERSION') => THINKCMF_VERSION . "&nbsp;&nbsp;&nbsp;",
    			L('UPLOAD_MAX_FILESIZE') => ini_get('upload_max_filesize'),
    			L('MAX_EXECUTION_TIME') => ini_get('max_execution_time') . "s",
    			L('DISK_FREE_SPACE') => round((@disk_free_space(".") / (1024 * 1024)), 2) . 'M',
    	);
		$time = date('Y-m-d');
		$message_all_count = M('Message')->alias('m')
			->join('__MESSAGE_USERS__ mu ON m.id_message=mu.id_message','LEFT')
			->where(array('m.show_start_time'=>array('ELT',$time),array('m.show_end_time'=>array('EGT',$time)),'mu.id_users'=>$_SESSION['ADMIN_ID']))
			->group('m.id_message')->select();
		$page = $this->page(count($message_all_count),1);
		$message_all = M('Message')->alias('m')
			->join('__MESSAGE_USERS__ mu ON m.id_message=mu.id_message','LEFT')
			->where(array('m.show_start_time'=>array('ELT',$time),array('m.show_end_time'=>array('EGT',$time)),'mu.id_users'=>$_SESSION['ADMIN_ID']))
			->group('m.id_message')->order('m.id_message DESC')->limit($page->firstRow,$page->listRows)->select();
		foreach($message_all as $k=>$v) {
			$message_all[$k]['user_nicename'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
			$message_all[$k]['show_s_time'] = substr($v['show_start_time'],0,10);
			$message_all[$k]['show_e_time'] = substr($v['show_end_time'],0,10);
		}
    	$this->assign('server_info', $info);
		$this->assign('messages', $message_all);
		$this->assign('page',$page->show('Admin'));
    	$this->display();
    }
}