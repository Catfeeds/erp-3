<?php
namespace Product\Controller;
use Common\Controller\AdminbaseController;
use Grafika\Grafika; // Import package
header("content-type:text/html;charset=utf-8;");
class CheckController extends AdminbaseController {

    /**
     * 查重列表，列表形式展示,所有人看
     */
    public function index() {
        //部门权限
        $departments = $_SESSION['department_id'];
        $where = array();
        //dump($_GET);exit;
        $_SESSION['return_index_url']= $_SERVER['REQUEST_URI'];
        /*if(isset($_GET['first_cate']) && $_GET['first_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['first_cate']);
        }
        if(isset($_GET['secd_cate']) && $_GET['secd_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['secd_cate']);
        }
        if(isset($_GET['three_cate']) && $_GET['three_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['three_cate']);
        }*/
        if(isset($_GET['title']) && $_GET['title']) { //产品名称
            $_GET['title']=trim($_GET['title']);

            $where['pc.title'] = array('like','%'.$_GET['title'].'%');
        }
        if(isset($_GET['inner_name']) && $_GET['inner_name']) { //内部名称
            $_GET['inner_name']=trim($_GET['inner_name']);
            $where['pc.inner_name'] = array('like','%'.$_GET['inner_name'].'%');
        }
        if(isset($_GET['domain']) && $_GET['domain']) { //域名
            $_GET['domain']=trim($_GET['domain']);
            $where['pc.domain'] = array('like','%'.$_GET['domain'].'%');
        }
        if(isset($_GET['extra_domain']) && $_GET['extra_domain']) { //二级域名
            $_GET['extra_domain']=trim($_GET['extra_domain']);
            $where['pc.extra_domain'] = array('like','%'.$_GET['extra_domain'].'%');
        }
        /*if(isset($_GET['ad_username']) && $_GET['ad_username']) {
            $uwhere['user_nicename'] = array('like','%'.$_GET['ad_username'].'%');
            $userid = M('Users')->where($uwhere)->getField('id',true);
            !empty($userid) ?  $where['id_users'] = array('IN',$userid) : $where['id_users'] = array(-1);
        }*/
        if(isset($_GET['department_id']) && $_GET['department_id']) { //部门查询
            $where['pc.id_department'] = array('EQ',$_GET['department_id']);
        }else{
            $where['pc.id_department'] = array('IN',$departments);
        }
        if(isset($_GET['category']) && $_GET['category']) { //分类查询
            $wherec['title'] = array('LIKE','%'.trim($_GET['category']).'%');
            $id_category = M("CheckCategory")->field('id_category,title')->where($wherec)->find();
            $where['cc.id_category'] = array('EQ',$id_category['id_category']);
            $category = $_GET['category'];
        }
        /*if(isset($_GET['cat_title']) && $_GET['cat_title']) {
            $_GET['cat_title']=trim($_GET['cat_title']);
            $catwhere['title'] = array('like','%'.$_GET['cat_title'].'%');
            $id_category = M('CheckCategory')->where($catwhere)->getField('id_category',true);
            !empty($id_category) ?  $where['id_check_category'] = array('IN',$id_category) : $where['id_check_category'] = array(-1);
        }
        if(isset($_GET['source']) && $_GET['source']) {
            if($_GET['source'] == '-1') {
                $where['pid'] = array('EQ',0);
            } else {
                $where['pid'] = array('NEQ',0);
            }
        }*/

        $where['pc.status'] = array('IN',[1,3]); //状态 1：正常 ，3：永久保留
        //$where['id_domain|id_product'] = array('ELT',0);
        /* 验证是否到期 start */
        $nowTime = date('Y-m-d H:i:s');
        $where['pc.end_time'] =['EGT', $nowTime] ; //过滤掉已到期的
        /* 验证是否到期 end */
        $moder = new \Think\Model();
        //$list_count = M('ProductCheck pc')->where($where)->count();
        $list_count = $moder
                //->table("erp_product_check AS pc LEFT JOIN erp_check_category AS cc ON pc.id_check_category=cc.id_category")
                ->table("erp_product_check AS pc LEFT JOIN erp_check_category AS cc ON pc.id_check_category=cc.id_category")
                ->where($where)->count();
        $page = $this->page($list_count,18);
        
        $list = $moder
                ->field('pc.*,cc.title as ctitle,cc.id_category')
                ->table("erp_product_check AS pc LEFT JOIN erp_check_category AS cc ON pc.id_check_category=cc.id_category")
                ->where($where)
                ->limit($page->firstRow . ',' . $page->listRows)
                ->order('check_time DESC')->select();

        foreach ($list as $k=>$v) {
            $list[$k]['img'] = '/data/upload/'.$v['img_url'];
            $list[$k]['department'] = M('Department')->where(array('id_department'=>$v['id_department']))->getField('title');
            $list[$k]['cate_name'] = M('CheckCategory')->where(array('id_category'=>$v['id_check_category']))->getField('title');
            //不需查询用户名称,效率太低
            //$list[$k]['user_name'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
            $list[$k]['source'] = $v['pid'] == 0 ? '新品' : '销档';
        }

        $department = M('Department')->where(array('type'=>1,'id_department'=>array('IN',$departments)))->getField('id_department,title',true);
        $users = M('Users')->where(['user_status'=>1])->getField('id,user_nicename');
        //dump($list);dump($category);exit;
        $this->assign('list',$list);
        $this->assign('department',$department);
        $this->assign('users',$users);
        $this->assign('category',$category);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    /**
     * 已备案列表
     */
    public function filing() {
        $where = array();
        $_SESSION['return_filing_url']= $_SERVER['REQUEST_URI'];
        if(isset($_GET['first_cate']) && $_GET['first_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['first_cate']);
        }
        if(isset($_GET['secd_cate']) && $_GET['secd_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['secd_cate']);
        }
        if(isset($_GET['three_cate']) && $_GET['three_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['three_cate']);
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
           // $where['_string'] = "title like '%".$_GET['pro_title']."%' or "."inner_name like '%".$_GET['pro_title']."%' or "."domain like '%".$_GET['pro_title']."%' or "."style like '%".$_GET['pro_title']."%'";
            $where['title|inner_name|domain|style'] = array('like','%'.$_GET['pro_title'].'%');
        }
        if(isset($_GET['zone']) && $_GET['zone']) {
            $where['zone'] = array('like','%'.$_GET['zone'].'%');
        }else{
            $where['zone'] = array('like','%其他地区%');
        }
        if(isset($_GET['ad_username']) && $_GET['ad_username']) {
            $uwhere['user_nicename'] = array('like','%'.$_GET['ad_username'].'%');
            $userid = M('Users')->where($uwhere)->getField('id',true);
            !empty($userid) ?  $where['id_users'] = array('IN',$userid) : $where['id_users'] = array(99999);
        }
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['id_department'] = array('EQ',$_GET['department_id']);
        }
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['record_time'] = $created_at_array;
        }
        if(!empty($_GET['category'])) {
            $where['id_check_category'] = array('IN',$_GET['category']);
            $category = $_GET['category'];
        }
        if(isset($_GET['cat_title']) && $_GET['cat_title']) {
            $catwhere['title'] = array('like','%'.$_GET['cat_title'].'%');
            $id_category = M('CheckCategory')->where($catwhere)->getField('id_category',true);
            !empty($id_category) ?  $where['id_check_category'] = array('IN',$id_category) : $where['id_check_category'] = array(-1);
        }
        if(isset($_GET['source']) && $_GET['source']) {
            if($_GET['source'] == '-1') {
                $where['pid'] = array('EQ',0);
            } else {
                $where['pid'] = array('NEQ',0);
            }
        }

        $where['_string'] = 'status=1 or status=3';
        $where['id_domain'] = array('GT',0);
        $where['id_product'] = array('GT',0);

        $list_count = M('ProductCheck')->where($where)->count();
        $page = $this->page($list_count,18);
        $list = M('ProductCheck')->where($where)->limit($page->firstRow . ',' . $page->listRows)->order('record_time DESC')->select();

        foreach ($list as $k=>$v) {
            $list[$k]['img'] = '/data/upload/'.$v['img_url'];
            $list[$k]['department'] = M('Department')->where(array('id_department'=>$v['id_department']))->getField('title');
            $list[$k]['cate_name'] = M('CheckCategory')->where(array('id_category'=>$v['id_check_category']))->getField('title');
            $list[$k]['user_name'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
            $list[$k]['source'] = $v['pid'] == 0 ? '新品' : '销档';
        }

        $department = M('Department')->where(array('type'=>1,'id_department'=>array('IN',$_SESSION['department_id'])))->getField('id_department,title',true);
        $this->assign('list',$list);
        $this->assign('department',$department);
        $this->assign('category',$category);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    /**
     * 广告专员查重列表
     */
    public function ad_index() {
        $where = array();
        $_SESSION['return_ad_index_url']= $_SERVER['REQUEST_URI'];
        //$where['id_domain|id_product'] = array('ELT',0);
        $where['status'] = 1;
        if(isset($_GET['first_cate']) && $_GET['first_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['first_cate']);
        }
        if(isset($_GET['secd_cate']) && $_GET['secd_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['secd_cate']);
        }
        if(isset($_GET['three_cate']) && $_GET['three_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['three_cate']);
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
           // $where['_string'] = "title like '%".$_GET['pro_title']."%' or "."inner_name like '%".$_GET['pro_title']."%' or "."domain like '%".$_GET['pro_title']."%' or "."style like '%".$_GET['pro_title']."%'";
            $where['title|inner_name|domain|style'] = array('like','%'.$_GET['pro_title'].'%');
        }
        if(isset($_GET['ad_username']) && $_GET['ad_username']) {
            $uwhere['user_nicename'] = array('like','%'.$_GET['ad_username'].'%');
            $userid = M('Users')->where($uwhere)->getField('id',true);
            !empty($userid) ?  $where['id_users'] = array('IN',$userid) : $where['id_users'] = array(99999);
        }
        if(!empty($_GET['category'])) {
            $where['id_check_category'] = array('IN',$_GET['category']);
            $category = $_GET['category'];
        }
        if(!empty($_GET['slect_check'])) {
            $where['status'] = 1;
            $where['id_domain|id_product'] = array('ELT',0);
        }
        if(!empty($_GET['slect_rep'])) {
            unset($where['status']);
            $where['_string'] = 'status=1 or status=3';
            $where['id_domain'] = array('GT',0);
            $where['id_product'] = array('GT',0);
        }
        if(!empty($_GET['slect_xiao'])) {
            unset($where['status']);
            $where['_string'] = 'status=0 or status=2 or status=6';
            $where['id_domain'] = array('GT',0);
            $where['id_product'] = array('GT',0);
        }
        if(!empty($_GET['slect_check']) && !empty($_GET['slect_rep'])) {
            unset($where['id_domain']);
            unset($where['id_product']);
            unset($where['id_domain|id_product']);
            unset($where['status']);
            $where['_string'] = 'status=1 or status=3';
        }
        if(!empty($_GET['slect_check']) && !empty($_GET['slect_xiao'])) {
            unset($where['id_domain']);
            unset($where['id_product']);
            unset($where['id_domain|id_product']);
            unset($where['status']);
            $where['_string'] = 'status=1 or status=2 or status=0 or status=6';
        }
        if(!empty($_GET['slect_rep']) && !empty($_GET['slect_xiao'])) {
            unset($where['status']);
            $where['id_domain'] = array('GT',0);
           $where['id_product'] = array('GT',0);
            $where['_string'] = 'status=1 or status=3 or status=2 or status=0 or status=6';
        }
        if(!empty($_GET['slect_check']) && !empty($_GET['slect_rep']) && !empty($_GET['slect_xiao'])) {
            unset($where['id_domain']);
            unset($where['id_product']);
            unset($where['id_domain|id_product']);
            unset($where['status']);
            $where['_string'] = 'status=1 or status=3 or status=2 or status=0 or status=6';
        }
        if(empty($_GET['slect_check']) && empty($_GET['slect_rep']) && empty($_GET['slect_xiao'])) {
            $where['status'] = 1;
            $where['id_domain|id_product'] = array('ELT',0);
        }
        if(isset($_GET['source']) && $_GET['source']) {
            if($_GET['source'] == '-1') {
                $where['pid'] = array('EQ',0);
            } else {
                $where['pid'] = array('NEQ',0);
            }
        }
        //暂时屏蔽掉部门,产品查询条件 jiangqinqing 20171030
        unset($where['id_domain']);
        unset($where['id_product']);
        unset($where['id_domain|id_product']);

        $depart = M('Department')->where(array('id_users'=>$_SESSION['ADMIN_ID']))->find();
        if($depart) {
            $where['id_department'] = array('IN',$_SESSION['department_id']);
            $users_data = M('Users')->alias('u')->field('id,user_nicename')
                ->join('__DEPARTMENT_USERS__ as du on du.id_users = u.id','LEFT')
                ->join('__ROLE_USER__ as ru on ru.user_id = u.id','LEFT')
                ->where(array('id_department'=>array('IN',$_SESSION['department_id']),'ru.role_id'=>array('IN','28,29,30')))->select();
            $users_data = array_column($users_data,'user_nicename','id');
        } else {
            $where['id_users'] = $_SESSION['ADMIN_ID'];
            $users_data=[];
        }
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['id_department'] = array('EQ',$_GET['department_id']);
        }
        if(isset($_GET['id_user']) && $_GET['id_user']) {
            $where['id_users'] = array('EQ',$_GET['id_user']);
        }
        $list_count = M('ProductCheck')->where($where)->count();
        $page = $this->page($list_count,18);
        $list = M('ProductCheck')->where($where)->limit($page->firstRow . ',' . $page->listRows)->order('check_time DESC')->select();

        foreach ($list as $k=>$v) {
            $list[$k]['img'] = '/data/upload/'.$v['img_url'];
            $list[$k]['department'] = M('Department')->where(array('id_department'=>$v['id_department']))->getField('title');
            $list[$k]['cate_name'] = M('CheckCategory')->where(array('id_category'=>$v['id_check_category']))->getField('title');
            $list[$k]['user_name'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
            $list[$k]['source'] = $v['pid'] == 0 ? '新品' : '销档';
        }

        $department = M('Department')->where(array('type'=>1,'id_department'=>array('IN',$_SESSION['department_id'])))->getField('id_department,title',true);
        $this->assign('list',$list);
        $this->assign('users_data',$users_data);
        $this->assign('department',$department);
        $this->assign('category',$category);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    /**
     * 查重记录
     */
    public function check_record() {
        $where = array();
        $_SESSION['return_check_record_url']= $_SERVER['REQUEST_URI'];
        $where['id_domain|id_product'] = array('ELT',0);
        if(!empty($_GET['slect_check'])) {
            $where['status'] = 1;
            $where['id_domain|id_product'] = array('ELT',0);
        }
        if(!empty($_GET['slect_rep'])) {
            $where['_string'] = 'status=1 or status=3';
            $where['id_domain'] = array('GT',0);
            $where['id_product'] = array('GT',0);
        }
        if(!empty($_GET['slect_check']) && !empty($_GET['slect_rep'])) {
            unset($where['id_domain']);
            unset($where['status']);
            $where['_string'] = 'status=1 or status=3';
        }

        $where['id_users'] = $_SESSION['ADMIN_ID'];
        $list_count = M('ProductCheck')->where($where)->count();
        $page = $this->page($list_count,18);
        $list = M('ProductCheck')->where($where)->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($list as $k=>$v) {
            $list[$k]['img'] = '/data/upload/'.$v['img_url'];
            $list[$k]['department'] = M('Department')->where(array('id_department'=>$v['id_department']))->getField('title');
            $list[$k]['cate_name'] = M('CheckCategory')->where(array('id_category'=>$v['id_check_category']))->getField('title');
            $list[$k]['user_name'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
        }

        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    /**
     * 导出查重结果
     */
    public function exprot_check() {
        set_time_limit(0);

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();

        $user_id = $_SESSION['ADMIN_ID'];

        $where = array();
        /* 重构查重导出表 start */
        $departments = $_SESSION['department_id'];
         $_SESSION['return_index_url']= $_SERVER['REQUEST_URI'];
        if(isset($_GET['title']) && $_GET['title']) { //产品名称
            $_GET['title']=trim($_GET['title']);

            $where['pc.title'] = array('like','%'.$_GET['title'].'%');
        }
        if(isset($_GET['inner_name']) && $_GET['inner_name']) { //内部名称
            $_GET['inner_name']=trim($_GET['inner_name']);
            $where['pc.inner_name'] = array('like','%'.$_GET['inner_name'].'%');
        }
        if(isset($_GET['domain']) && $_GET['domain']) { //域名
            $_GET['domain']=trim($_GET['domain']);
            $where['pc.domain'] = array('like','%'.$_GET['domain'].'%');
        }
        if(isset($_GET['extra_domain']) && $_GET['extra_domain']) { //二级域名
            $_GET['extra_domain']=trim($_GET['extra_domain']);
            $where['pc.extra_domain'] = array('like','%'.$_GET['extra_domain'].'%');
        }
        if(isset($_GET['department_id']) && $_GET['department_id']) { //部门查询
            $where['pc.id_department'] = array('EQ',$_GET['department_id']);
        }else{
            $where['pc.id_department'] = array('IN',$departments);
        }
        if(isset($_GET['category']) && $_GET['category']) { //分类查询
            $wherec['title'] = array('LIKE','%'.trim($_GET['category']).'%');
            $id_category = M("CheckCategory")->field('id_category,title')->where($wherec)->find();
            $where['cc.id_category'] = array('EQ',$id_category['id_category']);
            $category = $_GET['category'];
        }

        $where['pc.status'] = array('IN',[1,3]); //状态 1：正常 ，3：永久保留

        /* 验证是否到期 start */
        $nowTime = date('Y-m-d H:i:s');
        $where['pc.end_time'] =['EGT', $nowTime] ; //过滤掉已到期的
        /* 验证是否到期 end */
        $moder = new \Think\Model();
        
        $list = $moder
                ->field('pc.*,cc.title as ctitle,cc.id_category')
                ->table("erp_product_check AS pc LEFT JOIN erp_check_category AS cc ON pc.id_check_category=cc.id_category")
                ->where($where)
                ->order('check_time DESC')->select();

        foreach ($list as $k=>$v) {
            $list[$k]['img'] = '/data/upload/'.$v['img_url'];
            $list[$k]['department'] = M('Department')->where(array('id_department'=>$v['id_department']))->getField('title');
            $list[$k]['cate_name'] = M('CheckCategory')->where(array('id_category'=>$v['id_check_category']))->getField('title');
            $list[$k]['source'] = $v['pid'] == 0 ? '新品' : '销档';
        }
        $department = M('Department')->where(array('type'=>1,'id_department'=>array('IN',$departments)))->getField('id_department,title',true);
        $users = M('Users')->where(['user_status'=>1])->getField('id,user_nicename');
        /* 重构查重导出表 over */
        /*
        $where['status'] = 1;
        if(isset($_GET['first_cate']) && $_GET['first_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['first_cate']);
        }
        if(isset($_GET['secd_cate']) && $_GET['secd_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['secd_cate']);
        }
        if(isset($_GET['three_cate']) && $_GET['three_cate']) {
            $where['id_check_category'] = array('EQ',$_GET['three_cate']);
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
            // $where['_string'] = "title like '%".$_GET['pro_title']."%' or "."inner_name like '%".$_GET['pro_title']."%' or "."domain like '%".$_GET['pro_title']."%' or "."style like '%".$_GET['pro_title']."%'";
            $where['title|inner_name|domain|style'] = array('like','%'.$_GET['pro_title'].'%');
        }
        if(isset($_GET['ad_username']) && $_GET['ad_username']) {
            $uwhere['user_nicename'] = array('like','%'.$_GET['ad_username'].'%');
            $userid = M('Users')->where($uwhere)->getField('id',true);
            !empty($userid) ?  $where['id_users'] = array('IN',$userid) : $where['id_users'] = array(99999);
        }
        if(!empty($_GET['category'])) {
            $where['id_check_category'] = array('IN',$_GET['category']);
            $category = $_GET['category'];
        }
        if(!empty($_GET['slect_check'])) {
            $where['status'] = 1;
            $where['id_domain|id_product'] = array('ELT',0);
        }
        if(!empty($_GET['slect_rep'])) {
            unset($where['status']);
            $where['_string'] = 'status=1 or status=3';
            $where['id_domain'] = array('GT',0);
            $where['id_product'] = array('GT',0);
        }
        if(!empty($_GET['slect_xiao'])) {
            unset($where['status']);
            $where['_string'] = 'status=0 or status=2 or status=6';
            $where['id_domain'] = array('GT',0);
            $where['id_product'] = array('GT',0);
        }
        if(!empty($_GET['slect_check']) && !empty($_GET['slect_rep'])) {
            unset($where['id_domain']);
            unset($where['id_product']);
            unset($where['id_domain|id_product']);
            unset($where['status']);
            $where['_string'] = 'status=1 or status=3';
        }
        if(!empty($_GET['slect_check']) && !empty($_GET['slect_xiao'])) {
            unset($where['id_domain']);
            unset($where['id_product']);
            unset($where['id_domain|id_product']);
            unset($where['status']);
            $where['_string'] = 'status=1 or status=2 or status=0 or status=6';
        }
        if(!empty($_GET['slect_rep']) && !empty($_GET['slect_xiao'])) {
            unset($where['status']);
            $where['id_domain'] = array('GT',0);
            $where['id_product'] = array('GT',0);
            $where['_string'] = 'status=1 or status=3 or status=2 or status=0 or status=6';
        }
        if(!empty($_GET['slect_check']) && !empty($_GET['slect_rep']) && !empty($_GET['slect_xiao'])) {
            unset($where['id_domain']);
            unset($where['id_product']);
            unset($where['id_domain|id_product']);
            unset($where['status']);
            $where['_string'] = 'status=1 or status=3 or status=2 or status=0 or status=6';
        }
        if(empty($_GET['slect_check']) && empty($_GET['slect_rep']) && empty($_GET['slect_xiao'])) {
            $where['status'] = 1;
            $where['id_domain|id_product'] = array('ELT',0);
        }
        if(isset($_GET['source']) && $_GET['source']) {
            if($_GET['source'] == '-1') {
                $where['pid'] = array('EQ',0);
            } else {
                $where['pid'] = array('NEQ',0);
            }
        }
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id'=>$user_id))->find();
        if(in_array($role_user['role_id'],array(28,29,30))) {
            $depart = M('Department')->where(array('id_users'=>$_SESSION['ADMIN_ID']))->find();
            if($depart) {
                $where['id_department'] = array('IN',$_SESSION['department_id']);
            } else {
                $where['id_users'] = $_SESSION['ADMIN_ID'];
            }
        }

        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['id_department'] = array('EQ',$_GET['department_id']);
        }
        
        $columns = array(
            '查重日期', '备案日期','类别', '产品名字', '内部名',
            '图片', '业务部', '广告专员', '域名','二级域名',
            '产品链接', '采购链接','款式','备注','来源'
        );*/
        $columns = array(
            'ID', '部门','广告专员', '图片', '产品名称',
            '产品内部名称', '款式', '业务链接', '采购链接', '域名','二级域名',
            '查重日期','备注'
        );
        $j = 65;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        $idx = 2;
        if($list){
            foreach($list as $key=>$val) {
                /*$cat_name = M('CheckCategory')->where(array('id_category' => $val['id_check_category']))->getField('title');
                $depart = M('Department')->where(array('id_department' => $val['id_department']))->getField('title');
                $ad_username = M('Users')->where(array('id' => $val['id_users']))->getField('user_nicename');
                $img = $_SERVER['SERVER_NAME'].'/data/upload/'.$val['img_url'];
                $source = $val['pid'] == 0 ? '新品' : '销档';
                $data = array(
                    $val['check_time'], $val['record_time'], $cat_name, $val['title'],$val['inner_name'],
                    $img, $depart, $ad_username, $val['domain'], $val['extra_domain'],
                    $val['sale_url'], $val['purchase_url'], $val['style'], $val['remark'],$source
                );*/
                $img = $_SERVER['SERVER_NAME'].'/data/upload/'.$val['img_url'];
                $data = array(
                    $val['id_checked'], $val['department'], $users[$val['id_users']], $img,$val['title'],
                    $val['inner_name'],$val['cate_name'], $val['sale_url'], $val['purchase_url'],$val['domain'], $val['extra_domain'], $val['check_time'], $val['remark']
                );
                $j = 65;
                foreach ($data as $col) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                    ++$j;
                }
                ++$idx;
            }
            add_system_record($_SESSION['ADMIN_ID'], 7, 3, '查重信息表');
        } else {
            $this->error('没有数据');
        }
        $excel->getActiveSheet()->setTitle(date('Y-m-d').'查重信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d').'查重信息表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    /**
     * 导出已备案列表
     */
    public function exprot_filing() {
        set_time_limit(0);

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();

        $user_id = $_SESSION['ADMIN_ID'];

        $where = array();
        $where['_string'] = 'status=1 or status=3';
        $where['id_domain'] = array('GT',0);$where['id_product'] = array('GT',0);
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
            $where['title'] = array('like','%'.$_GET['pro_title'].'%');
        }
        if(isset($_GET['ad_username']) && $_GET['ad_username']) {
            $uwhere['user_nicename'] = array('like','%'.$_GET['ad_username'].'%');
            $userid = M('Users')->where($uwhere)->getField('id',true);
            !empty($userid) ? $where['id_users'] = array('IN',$userid) : $where['id_users'] = array(0);
        }
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['id_department'] = array('EQ',$_GET['department_id']);
        }
        if(!empty($_GET['category'])) {
            $where['id_check_category'] = array('IN',$_GET['category']);
        }
        if(isset($_GET['source']) && $_GET['source']) {
            if($_GET['source'] == '-1') {
                $where['pid'] = array('EQ',0);
            } else {
                $where['pid'] = array('NEQ',0);
            }
        }
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['record_time'] = $created_at_array;
        }
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id'=>$user_id))->find();
        if(in_array($role_user['role_id'],array(28,29,30))) {
            $where['id_users'] = $user_id;
        }

        $list = M('ProductCheck')->where($where)->select();

        $columns = array(
            '查重日期', '备案日期','类别', '产品名字','内部名',
            '图片', '业务部', '广告专员', '域名','二级域名',
            '产品链接', '采购链接','款式','备注','来源'
        );
        $j = 65;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        $idx = 2;
        if($list){
            foreach($list as $key=>$val) {
                $cat_name = M('CheckCategory')->where(array('id_category' => $val['id_check_category']))->getField('title');
                $depart = M('Department')->where(array('id_department' => $val['id_department']))->getField('title');
                $ad_username = M('Users')->where(array('id' => $val['id_users']))->getField('user_nicename');
                $img = $_SERVER['SERVER_NAME'].'/data/upload/'.$val['img_url'];
                $source = $val['pid'] == 0 ? '新品' : '销档';
   
                $data = array(
                    $val['check_time'], $val['record_time'], $cat_name, $val['title'],$val['inner_name'],
                    $img, $depart, $ad_username, $val['domain'],$val['extra_domain'],
                    $val['sale_url'], $val['purchase_url'], $val['style'], $val['remark'],$source
                );
                $j = 65;
                foreach ($data as $col) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                    ++$j;
                }
                ++$idx;
            }
            add_system_record($_SESSION['ADMIN_ID'], 7, 3, '查重信息表');
        } else {
            $this->error('没有数据');
        }
        $excel->getActiveSheet()->setTitle(date('Y-m-d').'查重信息表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d').'查重信息表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }

    /**
     * 新增/编辑页面
     */
    public function edit() {
        $check_id = isset($_GET['id'])?(int)$_GET['id']:0;
        $check_model = D("ProductCheck");
        $isADer=0;
        /*$adret=M('RoleUser')->where(array("user_id"=>$_SESSION['ADMIN_ID'],"role_id"=>array('IN','28,29')))->count();
        if($adret > 0){
            $isADer=1;
        }*/
//        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
//        $where['id_department'] = array('IN',$department_id);
        $check_data=array();
        $smeta=array();
        if($check_id){
            //$product_data = $pro_model->where($where)->find($product_id);
            $check_data = $check_model->find($check_id);
            $smeta[0]['url']=$check_data['img_url'];
            $check_data['name_users']=M("Users")->where(array("id"=>$check_data['id_users']))->getField('user_nicename');
            $check_data['cat_name']=M("CheckCategory")->where(array("id_category"=>$check_data['id_check_category']))->getField('title');
        }
        $domain_list=$this->getdomain($check_data['id_product']);
        import("Tree");
        $tree = new \Tree();
        $parent_id = isset($check_data['id_checked'])?$check_data['id_check_category']:0;
        $field = 'id_category as id,parent_id as parentid,sort as listorder,title,status';
        $result = D("Common/CheckCategory")->field($field)
            ->order(array("listorder" => "ASC"))->select();
        foreach ($result as $r) {
            $r['selected'] = $r['id'] == $parent_id ? 'selected' : '';
            $array[] = $r;
        }
        $str = "<option value='\$id' \$selected>\$spacer \$title</option>";
        $tree->init($array);
        $select_category = $tree->get_tree(0, $str);
        $department_id  = $_SESSION['department_id'];
        $where2['id_department'] = array('IN',$department_id); //部门筛选
        $department  = D('Department/Department')->cache(true,3600)->where(array('id_department'=>$where2['id_department'],"type"=>1))->order(' sort asc ')->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        //记录当前登陆用户
        $user = M('Users')->field('id,user_nicename')->where(array('id'=>$_SESSION['ADMIN_ID']))->find();
        //dump($check_data);exit;
        $this->assign("select_category", $select_category);
        $this->assign("user", $user);
        $this->assign("department_id", $department_id);
        $this->assign("product", $check_data);
        $this->assign("smeta",$smeta);
        $this->assign("isader",$isADer);
        $this->assign("domain_list",$domain_list);
        $this->assign("department", $department);
        $this->display();
    }
    /**
     * 图片查重
     */
    public function check_img(){
        $department = $_SESSION['department_id'];
        //查询所有的业务部门
        $all_department = M("Department")->where(array('type'=>1))->getField('id_department,title'); 
        /* 加载图片扩展start */
        vendor("Grafika.Grafika.Grafika");
        vendor("Grafika.autoloader");
        $grafika = new Grafika();
        $editor = $grafika->createEditor();
        /* 加载图片扩展over */
        if(isset($_POST['photos_url'][0]) && $_POST['photos_url'][0]){ //图片存在
            $statusArr = [1,3]; //状态： 1->正常状态 3->永久保存
            $model = M("ProductCheck");
            $wheres['status'] = array('IN',$statusArr);
            $photos_url = $model->where($wheres)->getField('id_checked,img_url'); //获取所有图片路径
            $photos_url = array_filter($photos_url); //过滤空的数据

            $url = dirname(dirname(dirname(dirname(__FILE__)))).'/data/upload/'; // erp根目录
            $original = $url.$_POST['photos_url'][0]; //原图路径
            $requet = array();
            foreach($photos_url as $k=>$v){
                if(file_exists($url.$v)){ //图片路径存在才会进行下去
                    $result = $editor->compare($original, $url.$v); 

                    switch ($result){ //0-10 可能相似，越小越相似 , >10 就有可能相似  >20不相似
                        case 0;
                            $requet[$k] = '相似度99%';
                            break;
                        case ($result>0 && $result<10);
                            $requet[$k] = '相似';
                            break;
                        default :
                            //$requet[$k] =  '系统错误';
                    } 
                }
                
            }

            if(isset($requet) && !empty($requet)){ //有查重结果
                
                foreach($requet as $k=>$v ){
                    //查询已经存在的记录是所属哪个部门
                    $deps = $model->field('id_checked,id_department')->where(array('id_checked'=>$k))->find();
                }
                add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
                if( in_array($deps['id_department'],$department )){ //是属于自己部门的
                    $msg = '该图片的产品已经存在！ID为：'.$deps['id_checked'];
                    echo "<script type='text/javascript'>
                    alert('".$msg."');window.history.go(-1);
                    </script>";
                    exit;
                }else{ //非自己部门
                    $msg = '该图片的产品已经存在！属于：'.$all_department[$deps['id_department']];
                    echo "<script type='text/javascript'>
                    alert('".$msg."');window.history.go(-1);
                    </script>";
                    exit;
                }
            }else{
                    echo "<script type='text/javascript'>
                    alert('没有重复图片，可以使用');window.history.go(-1);
                    </script>";
                    exit;
            }

        }
        
    }
    
    /**
     * 产品添加页的添加查重
     */
    public function product_check(){
        $admin_id  = $_SESSION['ADMIN_ID'];  //用户ID
        //初始化
        $data_msg = ['msg'=>'','status'=>1];
        //接收表单
        $id_department = I("post.id_department",0,'intval');
        $category_select = I("post.category_select",0,'intval');
        $imgval = I("post.imgval");
        $title = I("post.title");
        $inner_name = I("post.inner_name");
        $purchase_url = I("post.purchase_url");
        $pro_msg = I('post.pro_msg');
        if(isset($_POST['id_department']) && !$_POST['id_department']){
            $data_msg = ['msg'=>'请选择部门','status'=>2];
            echo json_encode($data_msg);
            exit;
        }
        if(isset($_POST['imgval']) && !$_POST['imgval']){
            $data_msg = ['msg'=>'请选择图片','status'=>3];
            echo json_encode($data_msg);
            exit;
        }
        $data = array(
            'id_check_category'=>$category_select,
            'id_department'=>$id_department,
            'img_url'=>$imgval,
            'title'=>$title,
            'status'=>4,  //4 : 待审核
            'inner_name'=>$inner_name,
            'purchase_url'=>$purchase_url,
            'check_time'=>date('Y-m-d H:i:s'),
            'end_time'=>date('Y-m-d H:i:s',strtotime('+7 day')), //有效时间默认给7天
            'id_users'=>$admin_id,
            'remark'=>$pro_msg,
        );
        $save_data = M("ProductCheck")->add($data);
        if($save_data){ //添加成功
            add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重成功');
            $data_msg = ['msg'=>'提交成功,请等候审核','status'=>5];
            echo json_encode($data_msg);
        }else{
            add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
            $data_msg = ['msg'=>'提交失败','status'=>4];
            echo json_encode($data_msg);
        }
        
    }
    
    /**
     * 提交保存
     */
    public function save_post() {
        $department = $_SESSION['department_id'];
        //查询所有的业务部门
        $all_department = M("Department")->where(array('type'=>1))->getField('id_department,title'); 
        /* 加载图片扩展start */
        vendor("Grafika.Grafika.Grafika");
        vendor("Grafika.autoloader");
        $grafika = new Grafika();
        $editor = $grafika->createEditor();
        /* 加载图片扩展over */
        if(isset($_POST['post']['id_department']) && !$_POST['post']['id_department']){
            add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
            echo "<script type='text/javascript'>
                    alert('保存失败,部门不能为空');window.history.go(-1);
                    </script>";
            exit;
            //$this->error('部门不能为空');
        }

        if(isset($_POST['post']['extra_domain']) && !empty($_POST['post']['extra_domain']) && (strpos($_POST['post']['extra_domain'], "www")!==0)){
            add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
            echo "<script type='text/javascript'>
                    alert('保存失败,二级域名应该是完整访问链接，如www.abc.com/TW');window.history.go(-1);
                    </script>";
            exit;
        }
        /* 图片查重 start */
        if(isset($_POST['photos_url'][0]) && $_POST['photos_url'][0]){ //图片存在
            $statusArr = [1,3]; //状态： 1->正常状态 3->永久保存
            $model = M("ProductCheck");
            $wheres['status'] = array('IN',$statusArr);
            if(isset($_POST['id_checked']) && !empty($_POST['id_checked'])){ //图片修改
                $wheres['id_checked'] = array('NOT IN',$_POST['id_checked']); //不查询自身
            }
            $photos_url = $model->where($wheres)->getField('id_checked,img_url'); //获取所有图片路径
            $photos_url = array_filter($photos_url); //过滤空的数据
            //dump($photos_url);exit;
            $url = dirname(dirname(dirname(dirname(__FILE__)))).'/data/upload/'; // erp根目录
            $original = $url.$_POST['photos_url'][0]; //原图路径
            $requet = array();
            if(file_exists($original)){ //路径存在继续，避免不存在upload没有同步时的报错
                foreach($photos_url as $k=>$v){
                    if(file_exists($url.$v)){ //图片路径存在才会进行下去
                        $result = $editor->compare($original, $url.$v); 

                        switch ($result){ //0-10 可能相似，越小越相似 , >10 就有可能相似  >20不相似
                            case 0;
                                $requet[$k] = '相似度99%';
                                break;
                            case ($result>0 && $result<9);
                                $requet[$k] = '相似';
                                break;
                            default :
                                //$requet[$k] =  '系统错误';
                        } 
                    }

                }
            }

            if(isset($requet) && !empty($requet)){ //有查重结果
                
                foreach($requet as $k=>$v ){
                    //查询已经存在的记录是所属哪个部门
                    $deps = $model->field('id_checked,id_department')->where(array('id_checked'=>$k))->find();
                }
                add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
                if( in_array($deps['id_department'],$department )){ //是属于自己部门的
                    $msg = '该图片的产品已经存在！ID为：'.$deps['id_checked'];
                    echo "<script type='text/javascript'>
                    alert('".$msg."');window.history.go(-1);
                    </script>";
                    exit;
                }else{ //非自己部门
                    $msg = '该图片的产品已经存在！属于：'.$all_department[$deps['id_department']];
                    echo "<script type='text/javascript'>
                    alert('".$msg."');window.history.go(-1);
                    </script>";
                    exit;
                }
            }

        }
        /* 图片查重 over */
        /** @var \Product\Model\ProductModel $product */
        $product = D('Product/ProductCheck');
        $data = $_POST['post'];
        if(isset($data['cat_name']) && !empty($data['cat_name'])){
            $data['id_check_category']=M("CheckCategory")->where(array("title"=>$data['cat_name']))->order('id_category desc')->getField('id_category');
            if($data['id_check_category']==false){
                add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
                echo "<script type='text/javascript'>
                    alert('保存失败,找不到该三级分类，请重新检索');window.history.go(-1);
                    </script>";
                exit;
            }else{
                $pid=M("CheckCategory")->where(array("id_category"=> $data['id_check_category']))->getField('parent_id');
                if($pid==0){
                    add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
                    echo "<script type='text/javascript'>
                    alert('保存失败,找不到该三级分类，请重新检索');window.history.go(-1);
                    </script>";
                    exit;
                }else{
                    $pid2=M("CheckCategory")->where(array("id_category"=> $pid))->getField('parent_id');
                    if($pid==0) {
                        add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
                        echo "<script type='text/javascript'>
                        alert('保存失败,找不到该三级分类，请重新检索');window.history.go(-1);
                        </script>";
                        exit;
                    }
                }
            }
        }

        if(isset($data['inner_name']) && !empty($data['inner_name'])){
            $data['id_product']=$this->getIdProduct($data['inner_name']);
            if( $data['id_product']>0){
                if(isset($data['id_checked']) && $data['id_checked']){
                    $where=array("id_product"=>$data['id_product'],'id_checked'=>array('NEQ',$data['id_checked']),"status"=>['IN',[0,1,5]]);
                }else{
                    $where=array("id_product"=>$data['id_product'],"status"=>['IN',[0,1,5]]);
                }
                $count=M('ProductCheck')->where($where)->count();
                if($count>0){
                    //$this->error('此产品id'.$data['id_checked'].'已经被使用');
                    $this->error('此产品id'.$data['id_product'].'已经被使用，请在查重列表搜索填写的内部名进行编辑或者删除！');
                }
            }else{
                $this->error('此产品内部名找不到对应的产品id');
            }
        }
        $data['id_users']=M("Users")->where(array("user_nicename"=>$_POST['post']['name_users']))->getField('id');
//        dump($data);exit;

        if(empty($data['id_users']) || $data['id_users']==false) {
            $this->error('此产品用户名找不到对应的用户id');
            //$data['id_users']=0;
        }

        //if($data['id_users']) {
            $data['img_url']=$_POST['photos_url'][0];
            $actTitle = isset($data['id_checked'])?'编辑':'新增';
            if(isset($data['domain']) && !empty($data['domain'])){
                $data['domain']=trim($data['domain']);
                if(strpos($data['domain'],"http") !== false) {
                    $urlArray = parse_url($data['domain']);
                    if (strpos($urlArray['host'], "www") !== false) {
                        $data['domain'] = $urlArray['host'];
                    } else {
                        $data['domain'] = 'www.' . $urlArray['host'];
                    }
                }elseif(strpos($data['domain'],"/") !== false){
                    $urlArray = parse_url("http://".$data['domain']);
                    if (strpos($urlArray['host'], "www") !== false) {
                        $data['domain'] = $urlArray['host'];
                    } else {
                        $data['domain'] = 'www.' . $urlArray['host'];
                    }
                }
                $data['record_time'] = date('Y-m-d H:i:s');
                $data['id_domain'] = M("Domain")->where(array("name"=>$data['domain']))->getField('id_domain');
                if($data['id_domain']) {
//                    if(empty($data['inner_name'])){
//                        add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
//                        echo "<script type='text/javascript'>
//                        alert('备案之前请先填写内部名');window.history.go(-1);
//                        </script>";
//                        exit;
//                    }
                   // $data['inner_name']=$this->get_inner_name($data['id_domain']);
                    if(isset($data['id_checked']) && $data['id_checked']){
                        //save
                        if($data['pid']>0){
                            //转移库存记录
                            $ret=$this->transfer_stock($data['id_department'],$data['pid']);
                            D('Product/ProductCheck')->where('id_checked=' . $data['pid'])->save(['status'=>5]);
                            $data['pid']=0-$data['pid'];
                        }
                        $record_time=D('Product/ProductCheck')->where('id_checked=' . $data['id_checked'])->getField('record_time');
                        if(!empty($record_time)){
                            unset($data['record_time']);
                        }
                        $result=$product->where('id_checked=' . $data['id_checked'])->save($data);
                        $this->add_check_record($_SESSION['ADMIN_ID'],"编辑",$data,$data['id_checked']);
                    }else{
                        //add
                        $data['check_time'] = date('Y-m-d H:i:s');
                        $result=$product->data($data)->add();
                        $this->add_check_record($_SESSION['ADMIN_ID'],"新增",$data,$result);
                    }
                    $adret=M('RoleUser')->where(array("user_id"=>$_SESSION['ADMIN_ID'],"role_id"=>array('IN','28,29,30')))->count();

                    if($adret > 0){
                        $this->success($actTitle."成功", $_SESSION['return_ad_index_url']);
                    }else{
                        if(!empty($data['old_id_domain'])){
                            $this->success($actTitle."成功", $_SESSION['return_filing_url']);
                        }else{
                            $this->success($actTitle."成功", $_SESSION['return_index_url']);
                        }
                    }


                    add_system_record(sp_get_current_admin_id(), 1, 2, '编辑查重成功');
                }else{
                    add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
                    echo "<script type='text/javascript'>
                    alert('保存失败,找不到该域名对应的域名id');window.history.go(-1);
                    </script>";
                    exit;
//                    $this->error("保存失败,找不到该域名对应的域名id");
//                    exit;
                }
            }else{
                if(isset($data['id_checked']) && $data['id_checked']){
                    //save
                    $result=$product->where('id_checked=' . $data['id_checked'])->save($data);
                    $this->add_check_record($_SESSION['ADMIN_ID'],"编辑",$data,$data['id_checked']);
                }else{
                    //add
                    $data['check_time'] = date('Y-m-d H:i:s');
                    // 添加成功后自动给 7 天有效期
                    $data['end_time'] = date('Y-m-d H:i:s',strtotime('+7 day'));
                    $result=$product->data($data)->add();
                    $this->add_check_record($_SESSION['ADMIN_ID'],"新建",$data,$result);
                }
                $adret=M('RoleUser')->where(array("user_id"=>$_SESSION['ADMIN_ID'],"role_id"=>array('IN','28,29,30')))->count();

                if($adret > 0){
                    $this->success($actTitle."成功",  $_SESSION['return_ad_index_url']);
                }else{
                    if(!empty($data['old_id_domain'])){
                        $this->success($actTitle."成功", $_SESSION['return_filing_url']);
                    }else{
                        $this->success($actTitle."成功",  $_SESSION['return_index_url']);
                    }
                }

                add_system_record(sp_get_current_admin_id(), 1, 2, '成功');
            }

        //}else{
        //    add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
        //    $this->error("保存失败,找不到该用户名对应的用户id");
        //    exit;
       // }
    }
    public function getdomain($id_product){
        //$id_product=5889;
        $order=array();
        $order = D('Order')->alias('o')
            ->field('o.id_domain,o.id_department,max(o.date_purchase) as date_purchase')
            ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
            ->order('o.id_order DESC')->where(array('oi.id_product'=>$id_product))->group('o.id_domain')->select();
        if(!empty($order)) {
            foreach($order as $k =>$v){
                $order[$k]['dtitle']=M("Department")->where(array("id_department"=>$v['id_department']))->getField('title');
                $order[$k]['domain']=M("Domain")->where(array("id_domain"=>$v['id_domain']))->getField('name');
            }
        }
        return $order;
        //var_dump($order);die;
    }
    public function getIdProduct($inner_name){
        $id=M("Product")->where(array("inner_name"=>$inner_name,'status'=>1))->getField('id_product');
        if($id >0){
            return $id;
        }else{
            return 0;
        }

        //var_dump($order);die;
    }

    /**
     * 重新编辑页面
     */
    public function repost() {
        $product_id = isset($_GET['id'])?(int)$_GET['id']:0;
        $pro_model = D("ProductCheck");
        $isADer=0;
        $adret=M('RoleUser')->where(array("user_id"=>$_SESSION['ADMIN_ID'],"role_id"=>array('IN','28,29,30')))->count();
        if($adret > 0){
            $isADer=1;
        }
//        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
//        $where['id_department'] = array('IN',$department_id);
        $product_data=array();
        $smeta=array();
        if($product_id){
            //$product_data = $pro_model->where($where)->find($product_id);
            $product_data = $pro_model->find($product_id);
            $smeta[0]['url']=$product_data['img_url'];
            $product_data['name_users']=M("Users")->where(array("id"=>$product_data['id_users']))->getField('user_nicename');
        }
        //$product_list=$this->getProductList($product_data['id_domain']);
        import("Tree");
        $tree = new \Tree();
        $parent_id = isset($product_data['id_checked'])?$product_data['id_check_category']:0;
        $field = 'id_category as id,parent_id as parentid,sort as listorder,title,status';
        $result = D("Common/CheckCategory")->field($field)
            ->order(array("listorder" => "ASC"))->select();
        foreach ($result as $r) {
            $r['selected'] = $r['id'] == $parent_id ? 'selected' : '';
            $array[] = $r;
        }
        $str = "<option value='\$id' \$selected>\$spacer \$title</option>";
        $tree->init($array);
        $select_category = $tree->get_tree(0, $str);
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->cache(true,3600)->where(["type"=>1])->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $domain_list=$this->getdomain($product_data['id_product']);
        $this->assign("select_category", $select_category);
        $this->assign("department_id", $department_id);
        $this->assign("product", $product_data);
        $this->assign("smeta",$smeta);
        $this->assign("isader",$isADer);

        $this->assign("domain_list",$domain_list);
        $this->assign("department", $department);
        $this->display();
    }

    /**
     * 重新编辑提交保存
     */
    public function save_repost() {
        if(isset($_POST['post']['id_department']) && !$_POST['post']['id_department']){
            $this->error('部门不能为空');
        }
//        if(isset($_POST['photos_url'][0]) && !$_POST['photos_url'][0]){
//            $this->error('图片不能为空');
//        }
        /** @var \Product\Model\ProductModel $product */
        $product = D('Product/ProductCheck');
        $data = $_POST['post'];
        $checkdata=D('Product/ProductCheck')->where(array('id_checked'=>$data['id_checked']))->find();
        $data['id_check_category']=$data['id_check_category'];
        $data['inner_name']=$checkdata['inner_name'];
        $data['id_users']=M("Users")->where(array("user_nicename"=>$_POST['post']['name_users']))->getField('id');
        if(empty($data['id_users']) || $data['id_users']==false) {
            $data['id_users']=0;
        }
        //if($data['id_users']) {
        $data['img_url']=$_POST['photos_url'][0];
        if(isset($data['domain']) && !empty($data['domain'])){
            $data['domain']=trim($data['domain']);
            if(strpos($data['domain'],"http") !== false) {
                $urlArray = parse_url($data['domain']);
                if (strpos($urlArray['host'], "www") !== false) {
                    $data['domain'] = $urlArray['host'];
                } else {
                    $data['domain'] = 'www.' . $urlArray['host'];
                }
            }elseif(strpos($data['domain'],"/") !== false){
                $urlArray = parse_url("http://".$data['domain']);
                if (strpos($urlArray['host'], "www") !== false) {
                    $data['domain'] = $urlArray['host'];
                } else {
                    $data['domain'] = 'www.' . $urlArray['host'];
                }
            }
            $data['check_time'] = date('Y-m-d H:i:s');
            $data['id_domain'] = M("Domain")->where(array("name"=>$data['domain']))->getField('id_domain');
            if($data['id_domain']) {
                //判断域名是否匹配
//                $order = D('Order')->field('id_order,id_department,id_users')->order('id_order DESC')->where(array('id_domain'=>$data['id_domain']))->find();
//                if($order['id_department']!=$_POST['post']['id_department']){
//                    if(empty($order['id_department'])){
//                        echo "<script type='text/javascript'>
//                    alert('保存失败,域名对应的部门或者广告专员不匹配');window.history.go(-1);
//                    </script>";
//                        exit;
//                    }
//                    $data['id_department']=$order['id_department'];
//                    $data['id_users']=$order['id_users'];
//                    //$this->error("保存失败,域名对应的部门或者广告专员不匹配");
//                }
                if(isset($data['id_product']) && !empty($data['id_product'])){
                    $data['record_time'] = date('Y-m-d H:i:s');
                }


                //转移库存记录
                //$ret=$this->transfer_stock($data['id_department'],$data['id_checked']);
                $result=$product->where('id_checked=' . $data['id_checked'])->save(['status'=>5]);//变成已消档之后又备案
                $data['pid']=$data['id_checked'];
                unset($data['id_checked']);
                $data['status']=7;
                if(empty($data['id_users'])) {
                    $data['id_users']=0;
                }
                $result=$product->data($data)->add();
                $adret=M('RoleUser')->where(array("user_id"=>$_SESSION['ADMIN_ID'],"role_id"=>array('IN','28,29,30')))->count();
                if($adret > 0){
                    $this->success("成功",$_SESSION['return_canceled_record_list_url'] );
                }else{
                    $this->success("成功",$_SESSION['return_canceled_record_list_url']);
                }

                add_system_record(sp_get_current_admin_id(), 1, 2, '成功');
            }else{
                add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
                echo "<script type='text/javascript'>
                    alert('保存失败,找不到该域名对应的域名id');window.history.go(-1);
                    </script>";
                exit;
//                $this->error("保存失败,找不到该域名对应的域名id");
//                exit;
            }
        }else{
            $data['check_time'] = date('Y-m-d H:i:s');
            $data['pid']=$data['id_checked'];
            $result=$product->where('id_checked=' . $data['id_checked'])->save(['status'=>6]);//变成已消档之后又没备案
            unset($data['id_checked']);
            $data['status']=7;
            if(empty($data['id_users'])) {
                $data['id_users']=0;
            }
            $result=$product->data($data)->add();
            $adret=M('RoleUser')->where(array("user_id"=>$_SESSION['ADMIN_ID'],"role_id"=>array('IN','28,29,30')))->count();
            if($adret > 0){
                $this->success("成功", $_SESSION['return_canceled_record_list_url']);
            }else{
                $this->success("成功", $_SESSION['return_canceled_record_list_url']);
            }
            add_system_record(sp_get_current_admin_id(), 1, 2, '成功');
        }

        //}else{
        //    add_system_record(sp_get_current_admin_id(), 1, 2, '添加查重失败');
        //    $this->error("保存失败,找不到该用户名对应的用户id");
        //    exit;
        // }
    }
    /**
     * 导入记录
     */
    public function import() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $dep = $_SESSION['department_id'];
        if (IS_POST) {
            $data = I('post.data');
            $zone = I('post.zone');
            $path = write_file('check', 'import', $data);
            $data = $this->getDataRow($data);
            $count = 1;
//            var_dump($data);die;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 9);
//                $addData['title']=$row[1];
//                $addData['style']=$row[3];
//                $addData['purchase_url']=$row[6];
//                $addData['remark']=$row[7];
//                $addData['domain']=$row[8];
                $addData['title']=$row[1];
                $addData['zone']=$zone;
                $addData['extra_domain']=$row[5];
                $addData['sale_url']=$row[7];
                $addData['purchase_url']=$row[6];
                $addData['domain']=$row[4];
                $sku=trim($row[8]);
                $productdata=array();
                $productdata=M("Product")->where(array("model"=>$sku))->find();
                if(!empty($productdata)){
                    $countproduct=M('ProductCheck')->where(array("id_product"=>$productdata['id_product'],"status"=>['IN',[0,1,5]]))->count();
                    if($countproduct>0){
                        $info['error'][] = sprintf('第%s行:此查重产品id%s已存在%s', $count++,$productdata['id_product'],$countproduct);
                        continue;
                    }else{
                        $addData['id_product']=$productdata['id_product'];
                        $addData['inner_name']=$productdata['inner_name'];
                    }
                }else{
                    $addData['id_product']=0;
                }
                $category = M('CheckCategory')->where(array('title'=>$row[0]))->order('id_category desc')->getField('id_category');
                if($category) {
                    $addData['id_check_category']=$category;
                    $cat_pid=M("CheckCategory")->where(array("id_category"=> $addData['id_check_category']))->getField('parent_id');
                    if($cat_pid==0){
                        $info['error'][] = sprintf('第%s行:分类不是第三级分类,%s', $count++,$row[0]);
                        continue;
                    }else{
                        $cat_pid2=M("CheckCategory")->where(array("id_category"=> $cat_pid))->getField('parent_id');
                        if($cat_pid2==0) {
                            $info['error'][] = sprintf('第%s行:分类不是第三级分类,%s', $count++,$row[0]);
                            continue;
                        }
                    }
                    //if($users) {
                    //}else{
                    //    $info['error'][] = sprintf('第%s行:没有找到用户', $count++);
                    //}
                    if(strpos($addData['domain'],"http") !== false){
                        $urlArray=parse_url($addData['domain']);
                        if(strpos($urlArray['host'],"www") !== false){
                            $addData['domain']=$urlArray['host'];
                        }else{
                            $addData['domain']='www.'.$urlArray['host'];
                        }
                    }elseif(strpos($addData['domain'],"/") !== false){
                        $urlArray=parse_url("http://".$addData['domain']);
                        if(strpos($urlArray['host'],"www") !== false){
                            $addData['domain']=$urlArray['host'];
                        }else{
                            $addData['domain']='www.'.$urlArray['host'];
                        }
                    }
                    $addData['id_domain'] = M("Domain")->where(array("name"=>$addData['domain']))->getField('id_domain');
                    if($addData['id_domain']) {
                        $row[2]=str_replace('(','（',$row[2]);
                        $row[2]=str_replace(')','）',$row[2]);
                        $department = M('Department')->where(array('title'=>$row[2]))->getField('id_department');
                        if(empty($department)|| $department==false) {
                            $department = M('Order')->where(array('id_domain'=>$addData['id_domain']))->order('id_order DESC')->getField('id_department');
                            if(empty($department)|| $department==false) {
                                $department =0;
                            }
                        }
                        $addData['id_department']=$department;
                        if(!empty($row[3])){
                            $users=M("Users")->where(array("user_nicename"=>$row[3]))->getField('id');
                        }else{
                            $users=false;
                        }

                        if(empty($users) || $users==false) {
                                $users = M('Order')->where(array('id_domain'=>$addData['id_domain']))->order('id_order DESC')->getField('id_users');
                                if(empty($users) || $users==false) {
                                    $users=0;
                                }

                        }
                        $check_domain=true;
                        if($department!=0 && $users!=0){
                            //判断域名是否匹配
                            $order = D('Order')->field('id_order,id_department,id_users')->order('id_order DESC')->where(array('id_domain'=>$addData['id_domain']))->find();
                            if($order['id_department']!=$department || $order['id_users']!=$users){
                                $check_domain=false;
                            }

                        }
                        $addData['id_users']=$users;
                        if(!$check_domain){
                           // var_dump($order['id_users']);var_dump($order['id_department']);var_dump($department);var_dump($users);die;
                            //$info['error'][] = sprintf('第%s行:域名对应的部门或者广告专员不匹配'.$addData['domain'].','.$order['id_department'].','.$department.','.$order['id_users'].','.$users,$count++);
                            //$this->error("保存失败,域名对应的部门或者广告专员不匹配");
                            $addData['id_department']=$order['id_department'];
                            $addData['id_users']=$order['id_users'];
                        }
                            //$addData['inner_name']=$this->get_inner_name($addData['id_domain']);
                            $addData['check_time']=  date('Y-m-d H:i:s');
                            $addData['record_time']=  date('Y-m-d H:i:s');
                            $product = D('Product/ProductCheck');
                            $result=$product->data($addData)->add();
                            $this->add_check_record($_SESSION['ADMIN_ID'],"导入",$addData,$result);
                            $info['success'][] = sprintf('第%s行:导入查重成功,地区：%s,%s,%s,%s,%s,%s,%s', $count++,$addData['zone'], $row[0],$row[1],$row[2],$row[3],$addData['domain'],$row[5],$row[6]);
                    }else{
                        $row[2]=str_replace('(','（',$row[2]);
                        $row[2]=str_replace(')','）',$row[2]);
                        $department = M('Department')->where(array('title'=>$row[2]))->getField('id_department');
                        if(empty($department)|| $department==false) {
                            $info['error'][] = sprintf('第%s行:没有找到对应的部门'.$row[2], $count++);
                        }else{
                            if(!empty($row[3])){
                                $users=M("Users")->where(array("user_nicename"=>$row[3]))->getField('id');
                                if(empty($users) || $users==false) {
                                    $info['error'][] = sprintf('第%s行:没有找到对应的广告专员'.$row[3], $count++);
                                }else{
                                    $addData['id_domain']=0;
                                    $addData['id_department']=$department;
                                    $addData['id_users']=$users;
                                    $addData['check_time']=  date('Y-m-d H:i:s');
                                    $product = D('Product/ProductCheck');
                                    $result=$product->data($addData)->add();
                                    $this->add_check_record($_SESSION['ADMIN_ID'],"导入",$addData,$result);
                                    $info['success'][] = sprintf('第%s行:导入查重成功,地区：%s,%s,%s,%s,%s,%s,%s', $count++, $addData['zone'],$row[0],$row[1],$row[2],$row[3],$addData['domain'],$row[5],$row[6]);
                                }
                            }else{
                                $info['error'][] = sprintf('第%s行:没有找到对应的广告专员'.$row[3], $count++);
                            }
                        }
                        //$info['error'][] = sprintf('第%s行:没有找到域名'.$addData['domain'], $count++);
                    }
                } else {
                    $info['error'][] = sprintf('第%s行:没有找到分类', $count++);
                }
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 5, 3, '导入查重');
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    /**
     * 导入记录
     */
    public function import_pid() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        if (IS_POST) {
            $data = I('post.data');
            $path = write_file('check', 'import_pid', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 3);
                $addData['id_product']=$row[0];
                $addData['domain']=$row[1];
                $addData['extra_domain']=$row[2];
                $id_checked = M("ProductCheck")->where(array("id_product"=>$addData['id_product']))->getField('id_checked');
                if($id_checked) {
                    if(strpos($addData['domain'],"http") !== false){
                        $urlArray=parse_url($addData['domain']);
                        if(strpos($urlArray['host'],"www") !== false){
                            $addData['domain']=$urlArray['host'];
                        }else{
                            $addData['domain']='www.'.$urlArray['host'];
                        }
                    }elseif(strpos($addData['domain'],"/") !== false){
                        $urlArray=parse_url("http://".$addData['domain']);
                        if(strpos($urlArray['host'],"www") !== false){
                            $addData['domain']=$urlArray['host'];
                        }else{
                            $addData['domain']='www.'.$urlArray['host'];
                        }
                    }
                    $addData['id_domain'] = M("Domain")->where(array("name"=>$addData['domain']))->getField('id_domain');
                    if($addData['id_domain']) {
                        $addData['check_time']=  date('Y-m-d H:i:s');
                        $addData['record_time']=  date('Y-m-d H:i:s');
                        $product = D('Product/ProductCheck');
                        $result=$product->where(["id_product"=>$addData['id_product']])->save($addData);
                        $info['success'][] = sprintf('第%s行:匹配查重成功', $count++, $row[0],$row[1],$row[2]);
                    }else {
                        $info['error'][] = sprintf('第%s行:没有找到对应的域名' . $row[1], $count++);
                    }
                }else{
                    $info['error'][] = sprintf('第%s行:没有找到对应的产品查重' . $row[0], $count++);
                }

            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 5, 3, '匹配查重');
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
    public function canceled_record_list(){
        $search = array();
        $_SESSION['return_canceled_record_list_url']= $_SERVER['REQUEST_URI'];
        if(isset($_GET['product_name']) && $_GET['product_name']) {
            $search['pc.title|pc.inner_name|pc.domain|pc.style'] = array('like','%'.$_GET['product_name'].'%');
        }
        if(isset($_GET['user_name']) && $_GET['user_name']) {
            $uwhere['user_nicename'] = array('like','%'.$_GET['user_name'].'%');
            $userid = M('Users')->where($uwhere)->getField('id',true);
            if(!empty($userid)){
                $search['pc.id_users'] = array('IN',$userid);
            }else{
                $search['pc.id_users'] = array('eq',-1);
            }
        }
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $search['pc.id_department'] = array('EQ',$_GET['id_department']);
        }
        if(isset($_GET['category']) && $_GET['category']) {
            $search['pc.id_check_category'] = array('IN', explode(',', $_GET['category']));
            $category = $_GET['category'];
        }
        //筛选状态 -1 已消档
        $where['_string'] = 'pc.status=-1 or pc.status=6';
        $count = M('ProductCheck')->alias('pc')
            ->where($where)
            ->where($search)
            ->count();
        $page = $this->page($count, 18);
        $list = M('ProductCheck')->alias('pc')
            ->field('pc.*, d.title as department_name, u.user_nicename as user_name, cc.title as category_name')
            ->join('__DEPARTMENT__ as d ON d.id_department = pc.id_department', 'left')
            ->join('__USERS__ as u ON u.id = pc.id_users', 'left')
            ->join('__CHECK_CATEGORY__ as cc ON cc.id_category = pc.id_check_category', 'left')
            ->where($where)
            ->where($search)
            ->order('pc.id_checked DESC')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();

        foreach($list as $k =>$v){

            $stockAndPrice=$this->getStockAndPrice2($v['id_product']);
            //$stockAndPrice=$this->getStockAndPrice($v['id_domain']);
            $list[$k]['stock']=$stockAndPrice['stock'];
            $list[$k]['price']=$stockAndPrice['price'];
        }
        if(I('request.show') == 'export'){
            $list = M('ProductCheck')->alias('pc')
                ->field('pc.*, d.title as department_name, u.user_nicename as user_name, cc.title as category_name')
                ->join('__DEPARTMENT__ as d ON d.id_department = pc.id_department', 'left')
                ->join('__USERS__ as u ON u.id = pc.id_users', 'left')
                ->join('__CHECK_CATEGORY__ as cc ON cc.id_category = pc.id_check_category', 'left')
                ->where($where)
                ->where($search)
                ->select();
            $row_map = array(
                array('name'=>'查重时间', 'key'=> 'check_time'),
                array('name'=>'备案时间', 'key'=> 'record_time'),
                array('name'=>'分类', 'key'=> 'category_name'),
                array('name'=>'产品名', 'key'=> 'title'),
                array('name'=>'部门', 'key'=> 'department_name'),
                array('name'=>'广告专员', 'key'=> 'user_name'),
                array('name'=>'域名', 'key'=> 'domain'),
                array('name'=>'采购链接', 'key'=> 'purchase_url'),
                array('name'=>'业务链接', 'key'=> 'sale_url'),
                array('name'=>'款式', 'key'=> 'style'),
                array('name'=>'备注', 'key'=> 'remark'),
                array('name'=>'图片链接', 'key'=> 'img_url'),
            );
            vendor('PHPExcel.ExcelManage');
            $excel = new \ExcelManage();
            $excel->export($list, $row_map, date("Y-m-d") . '已消档列表');
        }else{
            $department  = D('Department/Department')->where(array('type'=>1))->cache(true,3600)->select();
            $style = !empty($_GET['style']) ? $_GET['style'] : 'horizon';

            $this->assign('style', $style);
            $this->assign('department', $department);
            $this->assign('page', $page->show('Admin'));
            $this->assign('list', $list);
            $this->assign('category', $category);
            $this->display();
        }

    }
    public function export_check(){
        $list = M('ProductCheck')->alias('pc')
            ->field('pc.*, d.title as department_name, u.user_nicename as user_name, cc.title as category_name')
            ->join('__DEPARTMENT__ as d ON d.id_department = pc.id_department', 'left')
            ->join('__USERS__ as u ON u.id = pc.id_users', 'left')
            ->join('__CHECK_CATEGORY__ as cc ON cc.id_category = pc.id_check_category', 'left')
//            ->where($where)
//            ->where($search)
            ->select();
        $row_map = array(
            array('name'=>'查重时间', 'key'=> 'check_time'),
            array('name'=>'备案时间', 'key'=> 'record_time'),
            array('name'=>'分类', 'key'=> 'category_name'),
            array('name'=>'产品名', 'key'=> 'title'),
            array('name'=>'产品内部名名', 'key'=> 'inner_name'),
            array('name'=>'产品id', 'key'=> 'id_product'),
            array('name'=>'部门', 'key'=> 'department_name'),
            array('name'=>'广告专员', 'key'=> 'user_name'),
            array('name'=>'域名', 'key'=> 'domain'),
        );
        vendor('PHPExcel.ExcelManage');
        $excel = new \ExcelManage();
        $excel->export($list, $row_map, date("Y-m-d") . '查重列表');
    }
    //审核列表
    public function check_record_list(){
        $search = array();
        $_SESSION['return_check_record_list_url']= $_SERVER['REQUEST_URI'];
        if(isset($_GET['product_name']) && $_GET['product_name']) {
            $search['pc.title'] = array('like','%'.$_GET['product_name'].'%');
        }
        if(isset($_GET['user_name']) && $_GET['user_name']) {
            $uwhere['user_nicename'] = array('like','%'.$_GET['user_name'].'%');
            $userid = M('Users')->where($uwhere)->getField('id',true);
            if(!empty($userid)){
                $search['pc.id_users'] = array('IN',$userid);
            }else{
                $search['pc.id_users'] = array('eq',-1);
            }
        }
        $depart = M('Department')->where(array('id_users'=>$_SESSION['ADMIN_ID']))->find();
        if($depart) {
            $where['pc.id_department'] = array('IN',$_SESSION['department_id']);
        }

        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $search['pc.id_department'] = array('EQ',$_GET['id_department']);
        }

        if(isset($_GET['category']) && $_GET['category']) {
            $search['pc.id_check_category'] = array('IN', explode(',', $_GET['category']));
        }
        if(isset($_GET['status']) && $_GET['status']) {
            $search['pc.status'] = $_GET['status'];
             $search['pc.pid'] = array('NEQ',0);
        }else{
            $where['_string'] = 'pc.status=1  and pc.pid<>0 or pc.status=7 or pc.status=8';
        }

        $category = D('Product/CheckCategory')->get_option_tree();
        $count = M('ProductCheck')->alias('pc')
            ->where($where)
            ->where($search)
            ->count();
        $page = $this->page($count, 18);
        $list = M('ProductCheck')->alias('pc')
            ->field('pc.*, d.title as department_name, u.user_nicename as user_name, cc.title as category_name')
            ->join('__DEPARTMENT__ as d ON d.id_department = pc.id_department', 'left')
            ->join('__USERS__ as u ON u.id = pc.id_users', 'left')
            ->join('__CHECK_CATEGORY__ as cc ON cc.id_category = pc.id_check_category', 'left')
            ->where($where)
            ->where($search)
            ->order('pc.id_checked DESC')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        foreach($list as $k =>$v){
            $stockAndPrice=$this->getStockAndPrice2($v['id_product']);
           // $stockAndPrice=$this->getStockAndPrice($v['id_domain']);
            $list[$k]['stock']=$stockAndPrice['stock'];
            $list[$k]['price']=$stockAndPrice['price'];
        }
//        if(I('request.show') == 'export'){
//            $list = M('ProductCheck')->alias('pc')
//                ->field('pc.*, d.title as department_name, u.user_nicename as user_name, cc.title as category_name')
//                ->join('__DEPARTMENT__ as d ON d.id_department = pc.id_department', 'left')
//                ->join('__USERS__ as u ON u.id = pc.id_users', 'left')
//                ->join('__CHECK_CATEGORY__ as cc ON cc.id_category = pc.id_check_category', 'left')
//                ->where($where)
//                ->where($search)
//                ->select();
//            $row_map = array(
//                array('name'=>'查重时间', 'key'=> 'check_time'),
//                array('name'=>'备案时间', 'key'=> 'record_time'),
//                array('name'=>'分类', 'key'=> 'category_name'),
//                array('name'=>'产品名', 'key'=> 'title'),
//                array('name'=>'部门', 'key'=> 'department_name'),
//                array('name'=>'广告专员', 'key'=> 'user_name'),
//                array('name'=>'域名', 'key'=> 'domain'),
//                array('name'=>'采购链接', 'key'=> 'purchase_url'),
//                array('name'=>'业务链接', 'key'=> 'sale_url'),
//                array('name'=>'款式', 'key'=> 'style'),
//                array('name'=>'备注', 'key'=> 'remark'),
//                array('name'=>'图片链接', 'key'=> 'img_url'),
//            );
//            vendor('PHPExcel.ExcelManage');
//            $excel = new \ExcelManage();
//            $excel->export($list, $row_map, date("Y-m-d") . '已消档列表');
//        }else{
            $department  = D('Department/Department')->where(array('type'=>1,'id_department'=>array('IN',$_SESSION['department_id'])))->cache(true,3600)->select();
            $style = !empty($_GET['style']) ? $_GET['style'] : 'horizon';

            $this->assign('style', $style);
            $this->assign('department', $department);
            $this->assign('page', $page->show('Admin'));
            $this->assign('list', $list);
            $this->assign('category', $category);
            $this->display();
//        }

    }
    public function hide(){
        $id = I('request.id');
        M("ProductCheck")->where(array('id_checked'=>$id))->save(array('status'=>3));
        $this->success('隐藏成功');
    }
    //同意审核
    public function checkRecord(){
        $id = I('request.id');
        $ret=M("ProductCheck")->where(array('id_checked'=>['IN',$id]))->save(array('status'=>1));
        if($ret==false){
            echo 0;
        }else{
            $where['id_checked'] =['IN',$id];
            $data=M("ProductCheck")->where($where)->field('id_department,pid')->select();
            foreach($data as $k => $v){
                $ret=$this->transfer_stock($v['id_department'],$v['pid']);
            }
            echo 1;
        }
        exit;

    }
    //拒绝审核
    public function nocheckRecord(){
        $id = I('request.id');
        $pid=M("ProductCheck")->where(array('id_checked'=>['IN',$id]))->field('pid')->select();
        foreach($pid as $v){
            M("ProductCheck")->where(array('id_checked='.$v['pid']))->save(array('status'=>0));
        }
        $ret=M("ProductCheck")->where(array('id_checked'=>['IN',$id]))->save(array('status'=>8));

        if($ret==false){
            echo 0;
        }else{
            echo 1;
        }
        exit;
    }

    /**
     * 设置延长保护期
     */
    public function extendtime(){
        if($_POST['end_time'] > date('Y-m-d H:i:s')) {
            $data['end_time']=$_POST['end_time'];
            $result=D('Product/ProductCheck')->where('id_checked=' . $_POST['id_checked'])->save($data);
            $this->add_check_record($_SESSION['ADMIN_ID'],"设置延长保护期",$data,$_POST['id_checked']);
            if($result){
                $ret['code']=1;
                $ret['msg'] = '设置成功';
            }else{
                $ret['code']=0;
                $ret['msg'] = '设置成功';
            }
        } else {
            $ret['code']=0;
            $ret['msg'] = '请选择今天之后的时间';
        }

        echo json_encode($ret);
        exit;
    }

    /**
     * 对备案进行永久保留
     */
    public function per_ret(){
        if(IS_AJAX) {
            $id = $_POST['id_checked'];
            $result=D('Product/ProductCheck')->where(array('id_checked'=>$id))->save(array('status'=>3));
            $this->add_check_record($_SESSION['ADMIN_ID'],"备案进行永久保留",array('status'=>3),$id);
            if($result){
                $ret['code']=1;
                $ret['msg'] = '保留成功';
            }else{
                $ret['code']=0;
                $ret['msg'] = '保留失败';
            }
            echo json_encode($ret);
            exit;
        }
    }
    /**
     * 对备案进行取消永久保留
     */
    public function noper_ret(){
        if(IS_AJAX) {
            $id = $_POST['id_checked'];
            $data['record_time'] = date('Y-m-d H:i:s');
            $result=D('Product/ProductCheck')->where(array('id_checked'=>$id))->save(array('status'=>1,'record_time'=>$data['record_time']));
            $this->add_check_record($_SESSION['ADMIN_ID'],"备案进行取消永久保留",array('status'=>1,'record_time'=>$data['record_time']),$id);
            if($result){
                $ret['code']=1;
                $ret['msg'] = '取消保留成功';
            }else{
                $ret['code']=0;
                $ret['msg'] = '取消保留失败';
            }
            echo json_encode($ret);
            exit;
        }
    }
    public function delete(){
        $id = intval(I("get.id"));
        $old_id_domain = intval(I("get.old_id_domain"));
        $pid=D('Product/ProductCheck')->where('id_checked='.$id)->getField('pid');
        $status = D('Product/ProductCheck')->where(array('id_checked' =>$id))->save(['status'=>-1]);
        if ($status) {
            if($pid > 0){
               D('Product/ProductCheck')->where('id_checked=' . $pid)->save(['status'=>0]);
            }
            $this->add_check_record($_SESSION['ADMIN_ID'],"删除查重",['status'=>-1],$id);
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除查重' . $id . '成功');
            if(isset($_GET['old_id_domain'])){
                if(!empty($old_id_domain)){
                    $this->success("删除成功", $_SESSION['return_filing_url']);
                }else{
                    $this->success("删除成功", $_SESSION['return_index_url']);
                }
            }else{
                $this->success("删除成功", $_SESSION['return_canceled_record_list_url']);
            }


            //$this->success("删除成功！", $_SESSION['return_index_url']);
        } else {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除查重' . $id . '失败');
            $this->error("删除失败！");
        }
    }
    //根据域名获取产品列表
//    public function getProductList($id_domain){
//        $skudata=array();
//        if(empty($id_domain)){
//            return $skudata;
//        }
//        $where1['id_domain']=$id_domain;
//        $order = D('Order')->field('id_department,id_users,id_order')->order('id_order DESC')
//            ->where($where1)->find();
//        if(!empty($order)) {
//            $order2 = D('OrderItem')->field('product_title,id_product')->where(['id_order' => $order['id_order']])->group('id_product')->select();
//            if (!empty($order2)) {
//                $data = array();
//                foreach ($order2 as $k => $v) {
//                    $data = M('ProductSku')->alias('ps')
//                        ->field('ps.id_product,ps.id_product_sku,ps.sku,sum(wp.quantity) as quantity,sum(wp.road_num) as road_num,ps.title,ps.purchase_price')
//                        ->join('__WAREHOUSE_PRODUCT__ as wp ON wp.id_product_sku = ps.id_product_sku', 'left')
//                        ->where(array('ps.id_product' => $v['id_product']))
//                        ->group('ps.id_product_sku')
//                        ->select();
//                    foreach ($data as $k2 => $v2) {
//                        $data[$k2]['product_title'] = $v['product_title'];
//                        $data[$k2]['id_department'] = $order['id_department'];
//                        $data[$k2]['id_users'] = $order['id_users'];
//                    }
//                    $skudata[$k] = $data;
//                }
//            }
//        }
//        return $skudata;
//    }
    public function getProductList($id_product){
        $skudata=array();
        if(empty($id_product)){
            return $skudata;
        }
        $product=M('Product')->field('id_department,title,id_users')->where(['id_product'=>$id_product])->find();
        $data = M('ProductSku')->alias('ps')->field('ps.id_product,ps.id_product_sku,ps.sku,sum(wp.quantity) as quantity,sum(wp.road_num) as road_num,ps.title,ps.purchase_price')
            ->join('__WAREHOUSE_PRODUCT__ as wp ON wp.id_product_sku = ps.id_product_sku', 'left')
            ->where(array('ps.id_product' => $id_product))
            ->group('ps.id_product_sku')
            ->select();
        foreach ($data as $k2 => $v2) {
            $data[$k2]['product_title'] = $product['title'];
            $data[$k2]['id_department'] = $product['id_department'];
            $data[$k2]['id_users'] = $product['id_users'];
        }
        $skudata[0] = $data;
        return $skudata;
    }
    //根据域名获取库存和价格
    public function getStockAndPrice($id_domain){
        $returndata=array("stock"=>0,"price"=>0);
        if(empty($id_domain)){
            return $returndata;
        }
        $where1['id_domain']=$id_domain;
        $order = D('Order')->field('id_department,id_users,id_order')->order('id_order DESC')->cache(true,120)
            ->where($where1)->find();
        if(!empty($order)) {
            $order2 = D('OrderItem')->field('product_title,id_product')->where(['id_order' => $order['id_order']])->cache(true,120)->group('id_product')->select();
            if (!empty($order2)) {
                $data = array();
                $Stock=0;
                $purchase_price=0;
                $num=0;
                foreach ($order2 as $k => $v) {
                    $where['wp.id_product']=$v['id_product'];
                    $where['wp.quantity|wp.road_num']=array('GT',0);
                     $data = M('ProductSku')->alias('ps')
                         ->field('sum(wp.quantity) as quantity,sum(wp.road_num) as road_num,ps.purchase_price')
                         ->join('__WAREHOUSE_PRODUCT__ as wp ON wp.id_product_sku = ps.id_product_sku', 'left')
                         ->where($where)
                         ->group('ps.id_product_sku')
                         ->cache(true,120)
                         ->select();
                     foreach($data as $k2=>$v2){
                         $Stock=$v2['quantity']+$v2['road_num']+$Stock;
                         $purchase_price=$purchase_price+$v2['purchase_price'];
                         $num=$num+1;
                     }

                }
            }
        }
        $returndata['stock'] = $Stock;
        $returndata['price'] = $purchase_price/$num;
        $returndata['price'] =round($returndata['price'],2);
        return $returndata;
    }
    //产品id获取库存和价格
    public function getStockAndPrice2($id_product){
        $returndata=array("stock"=>0,"price"=>0);
        if(empty($id_product)){
            return $returndata;
        }
        $Stock=0;
        $purchase_price=0;
        $num=0;
        $where['wp.id_product']=$id_product;
        $where['wp.quantity|wp.road_num']=array('GT',0);
        $data = M('ProductSku')->alias('ps')
            ->field('sum(wp.quantity) as quantity,sum(wp.road_num) as road_num,ps.purchase_price')
            ->join('__WAREHOUSE_PRODUCT__ as wp ON wp.id_product_sku = ps.id_product_sku', 'left')
            ->where($where)
            ->group('ps.id_product_sku')
            ->cache(true,120)
            ->select();
        foreach($data as $k2=>$v2){
            $Stock=$v2['quantity']+$v2['road_num']+$Stock;
            $purchase_price=$purchase_price+$v2['purchase_price'];
            $num=$num+1;
        }
        $returndata['stock'] = $Stock;
        $returndata['price'] = $purchase_price/$num;
        $returndata['price'] =round($returndata['price'],2);
        return $returndata;
    }
    //获取内部名
    public function get_inner_name($id_domain){
        $order = D('Order')->field('id_order')->order('id_order DESC')->where(array('id_domain'=>$id_domain))->find();
        if(!empty($order)) {
            $order2 = D('OrderItem')->alias('oi')->field('p.inner_name')
                ->join('__PRODUCT__ as p ON p.id_product = oi.id_product', 'left')
                ->where(['id_order' => $order['id_order']])->group('oi.id_product')->select();
            $inner_name='';
            $inner_name_array=array();
            foreach($order2 as $v){
                array_push($inner_name_array,$v['inner_name']);
            }
            $inner_name=implode(',',$inner_name_array);
        }
        return $inner_name;
    }

    public function test(){
        $data['id_department']=4;
        $data['pid']=206;
        $ret=$this->transfer_stock($data['id_department'],$data['pid']);
        var_dump($ret);die;
//        $id_domain=804;
//        $where1['id_domain']=$id_domain;
////        $order = D('Order')->alias('o')->field('o.id_department,o.id_users,oi.id_product,oi.product_title')
////            ->join('__ORDER_ITEM__ oi ON (o.id_order = oi.id_order)', 'LEFT')
////            ->group('oi.id_product')
////            ->where($where1)->select();
////        var_dump($order);die;
//        $order = D('Order')->field('id_department,id_users,id_order')->order('id_order DESC')
//            ->where($where1)->find();
//        $skudata=array();
//        if(!empty($order)) {
//            $order2 = D('OrderItem')->field('product_title,id_product')->where(['id_order' => $order['id_order']])->group('id_product')->select();
//            if (!empty($order2)) {
//                $data = array();
//                foreach ($order2 as $k => $v) {
//                    $data = M('ProductSku')->alias('ps')
//                        ->field('ps.id_product,ps.id_product_sku,ps.sku,sum(wp.quantity)as quantity,sum(wp.road_num)as road_num,ps.title,ps.purchase_price')
//                        ->join('__WAREHOUSE_PRODUCT__ as wp ON wp.id_product_sku = ps.id_product_sku', 'left')
//                        ->where(array('ps.id_product' => $v['id_product']))
//                        ->group('id_product_sku')
//                        ->select();
//                    foreach ($data as $k2 => $v2) {
//                        $data[$k2]['product_title'] = $v['product_title'];
//                        $data[$k2]['id_department'] = $order['id_department'];
//                        $data[$k2]['id_users'] = $order['id_users'];
//                    }
//                    $skudata[$k] = $data;
//                }
//            }
//        }
//        var_dump($skudata);die;

    }
    //转移库存
    public function transfer_stock($new_department,$id_check){
        $checkdata=D('Product/ProductCheck')->where(array('id_checked'=>$id_check))->find();
        $old_department=$checkdata['id_department'];
        if($old_department==$new_department){
            return true;
        }
        //$skudata=$this->getProductList($checkdata['id_domain']);
        $skudata=$this->getProductList($checkdata['id_product']);

        foreach($skudata as $k =>$v){
            $data=$v;
            foreach($data as $k2 =>$v2){
                $productdata=M('Product')->where(array('id_product'=>$v2['id_product']))->field('thumbs,title')->find();
                $adddata['thumbs']=$productdata['thumbs'];
                $adddata['id_product']=$v2['id_product'];
                $adddata['id_product_sku']=$v2['id_product_sku'];
                $adddata['product_name']=$productdata['title'];
                $adddata['sku']=$v2['sku'];
                $adddata['old_department']=$old_department;
                $adddata['new_department']=$new_department;
                $adddata['transfer_stock']=$v2['quantity'];
                $adddata['purchase_price']=$v2['purchase_price'];
                $adddata['transfer_stock_count']=$v2['quantity']*$v2['purchase_price'];
                $adddata['transfer_time'] = date('Y-m-d H:i:s');
                $adddata['status']=0;
                $record=M('TransferStock');
                $result=$record->data($adddata)->add();
//                id_product_sku
//                sku
//                quantity
//                road_num
//                title
//                price
//                option
            }

        }
        if($result ==false){
            return false;
        }else{
            return true;
        }

    }

    /**
     * 转移库存记录表
     */
    public function transfer_stock_index() {
        $where = array();

        if(isset($_GET['id_department_old']) && $_GET['id_department_old']) {
            $where['old_department'] = $_GET['id_department_old'];
        }
        if(isset($_GET['id_department_new']) && $_GET['id_department_new']) {
            $where['new_department'] = $_GET['id_department_new'];
        }
        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $fwhere['product_name'] = array('like','%'.$_GET['keyword'].'%');
            $fwhere['sku'] = array('like','%'.$_GET['keyword'].'%');
            $fwhere['_logic'] = 'or';
            $f_ids = M('TransferStock')->where($fwhere)->getField('id',true);//查找产品名称
            if($f_ids) {$where['id'] = array('IN', $f_ids); }
        }
        if ($_GET['start_time'] or $_GET['end_time']) {
            $date_array = array();
            if ($_GET['start_time'])
                $date_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $date_array[] = array('LT', $_GET['end_time']);
            $where['transfer_time'] = $date_array;
        }

        $list_count = M('TransferStock')->where($where)->count();
        $page = $this->page($list_count,20);
        $list = M('TransferStock')->where($where)->limit($page->firstRow.','.$page->listRows)->select();

        foreach($list as $key=>$val){
            $img = json_decode($val['thumbs'], true);
            $list[$key]['img'] = $img['photo'][0]['url'];
            $list[$key]['old_department'] = M('Department')->where(array('id_department'=>$val['old_department']))->getField('title');
            $list[$key]['new_department'] = M('Department')->where(array('id_department'=>$val['new_department']))->getField('title');
        }

        $department = M('Department')->where(array('type'=>1))->order('department_num ASC')->getField('id_department,title',true);
        $this->assign('list',$list);
        $this->assign('department',$department);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    public function syn_inner_name(){
        $data=D('Product/ProductCheck')->where(array('id_domain'=>array('GT',0)))->field('id_domain,id_checked')->select();
        foreach($data as $k => $v){
            $order = D('Order')->field('id_order')->order('id_order DESC')->where(array('id_domain'=>$v['id_domain']))->find();
            if(!empty($order)) {
                $order2 = D('OrderItem')->alias('oi')->field('p.inner_name')
                    ->join('__PRODUCT__ as p ON p.id_product = oi.id_product', 'left')
                    ->where(['id_order' => $order['id_order']])->group('oi.id_product')->select();
                $inner_name='';
                $inner_name_array=array();
                foreach($order2 as $v2){
                    array_push($inner_name_array,$v2['inner_name']);
                }
                $inner_name=implode(',',$inner_name_array);
            }
            $update=array();
            $update['inner_name']=$inner_name;
            $result[$k]=D('Product/ProductCheck')->where('id_checked='.$v['id_checked'])->save($update);
        }
        var_dump($result);die;
    }
    public function syn_id_product(){
        $data=D('Product/ProductCheck')->where(array('id_product'=>0))->field('inner_name,id_checked')->select();
        foreach($data as $k => $v){
            $id_product=$this->getIdProduct($v['inner_name']);
            if($id_product > 0){
                $update=array();
                $update['id_product']=$id_product;
                $result[$k]=D('Product/ProductCheck')->where('id_checked='.$v['id_checked'])->save($update);
            }

        }
        var_dump($result);die;
    }

    /**
     * 导出转移库存记录表
     */
    public function export_transfer_stock() {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();

        $column = array(
            '产品名称', 'SKU', '原部门', '新部门', '转移库存', '采购单价',
            '转移总价', '转移时间', '结款状态'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

        $where = array();

        if(isset($_GET['id_department_old']) && $_GET['id_department_old']) {
            $where['old_department'] = $_GET['id_department_old'];
        }
        if(isset($_GET['id_department_new']) && $_GET['id_department_new']) {
            $where['new_department'] = $_GET['id_department_new'];
        }
        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $fwhere['product_name'] = array('like','%'.$_GET['keyword'].'%');
            $fwhere['sku'] = array('like','%'.$_GET['keyword'].'%');
            $fwhere['_logic'] = 'or';
            $f_ids = M('TransferStock')->where($fwhere)->getField('id',true);//查找产品名称
            if($f_ids) {$where['id'] = array('IN', $f_ids); }
        }
        if ($_GET['start_time'] or $_GET['end_time']) {
            $date_array = array();
            if ($_GET['start_time'])
                $date_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $date_array[] = array('LT', $_GET['end_time']);
            $where['transfer_time'] = $date_array;
        }

        $list = M('TransferStock')->where($where)->select();
        $idx = 2;
        foreach ($list as $item) {
            $old_department = M('Department')->where(array('id_department'=>$item['old_department']))->getField('title');
            $new_department = M('Department')->where(array('id_department'=>$item['new_department']))->getField('title');
            switch ($item['status']) {
                case 0:
                    $settl_name = '未结款';
                    break;
                case 1: $settl_name = '已结款';
                    break;
            }
            $data = array(
                $item['product_name'], $item['sku'], $old_department, $new_department, $item['transfer_stock'],
                $item['purchase_price'],$item['transfer_stock_count'],$item['transfer_time'], $settl_name
            );
            $j = 65;
            foreach ($data as $key=>$col) {
                $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 3, '导出库存转移信息列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '库存转移信息列表'.'.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '库存转移信息列表'.'.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }
    //搜索提示
    public function search_text(){
        $inner_name = $_GET['goods_name'];
        //$id_warehouse = $_GET['id_warehouse'];
        $result = M('Product')->field('inner_name')
            ->where(array('inner_name'=>array('LIKE','%'.$inner_name.'%'),'status'=>1))->limit(5)
            ->select();
        $result = array_column($result,'inner_name');
        $data = '<ul>';
        foreach($result as $value){
            $data.='<li style="padding-top:5px;padding-bottom:5px">'.$value.'</a></li>';
        }
        $data.='</ul>';
        echo json_encode($data);
    }
    //搜索提示-分类
    public function search_cat_text(){

        $title = $_GET['goods_name'];
        $result = M('CheckCategory')
            ->field('parent_id,title,id_category')
            ->where(array('title'=>array('LIKE','%'.$title.'%'),'status' => 1))
            ->select();
        foreach($result as $k =>$v){
            if($v['parent_id']==0){
                unset($result[$k]);
            }else{
                $pid2=M('CheckCategory')->where(array('parent_id'=>$v['id_category'],'status' => 1))->getField('parent_id');
                if($pid2!=0){
                    unset($result[$k]);
                }
            }

        }
        $result = array_column($result,'title');
        $data = '<ul>';
        foreach($result as $value){
            $data.='<li style="padding-top:5px;padding-bottom:5px">'.$value.'</a></li>';
        }
        $data.='</ul>';
        echo json_encode($data);
    }
    public function sel_three_cat(){
        //$result = M('CheckCategory')->field('title')->where(array('title'=>array('LIKE','%'.$title.'%')))->select();


        $result=M("ProductCheck")->field('id_checked,title,id_check_category')->select();
        foreach($result as $k =>$v){
            $pid=M("CheckCategory")->where(array('id_category'=>$v['id_check_category']))->getField('parent_id');
            if($pid==0){
                var_dump($v['title']);
                echo "第一分类，id是".$v['id_checked'];echo '<br/>';
            }else{
                $pid2=M('CheckCategory')->where(array('id_category'=>$pid))->getField('parent_id');
                if($pid2==0){
                    var_dump($v['title']);
                    echo "第二分类，id是".$v['id_checked'];echo '<br/>';
                }else{

                }
            }
        }

        exit;

    }
    /**
     * 结款
     */
    public function ajax_batch_settle() {
        if(IS_AJAX) {
            try {
                $ids = is_array($_POST['id']) ? $_POST['id'] : array($_POST['id']);
                foreach ($ids as $id) {
                    D('Common/TransferStock')->where(array('id' => $id))->save(array('status' => 1));
                }
                $status = 1;
                $message = '修改成功';
            } catch (\Exception $e) {
                $status = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '库存转移批量修改状态');
            $return = array('status' => $status, 'message' => $message,);
            echo json_encode($return);
            exit();
        }
    }

    /**
     * 请求分类数据
     */
    public function ajax_category()
    {
        $category = D('Product/CheckCategory')->getCategory();
       echo json_encode($category);die;
    }
    public function del_alike_check(){
        $data=D('Product/ProductCheck')->field('count(id_checked) as count,id_product')->where(['id_product'=>['gt',0]])->group('id_product')->having('count > 1')->select();
        foreach($data as $k => $v){
            $where['id_product']=$v['id_product'];
            $where['status']=1;
            $where['id_main']=['gt',0];
            $a=D('Product/ProductCheck')->field('id_checked')->where($where)->select();
            $where2['id_product']=$v['id_product'];
            $where2['status']=['in',[0,2]];
            $c=D('Product/ProductCheck')->field('id_checked')->where($where2)->select();
            if(!empty($a)){
                $b=D('Product/ProductCheck')->where($where2)->delete();
            }
            $d=D('Product/ProductCheck')->field('id_checked')->where($where2)->select();
            var_dump($c);
            var_dump($d);
            $result[$k]=$b;
        }
        var_dump($result);die;
    }
    public function get_alike_check(){
        $data=D('Product/ProductCheck')->field('count(id_checked) as count,id_product')->where(['id_product'=>['gt',0]])->group('id_product')->having('count > 1')->select();
        echo "</br>-------------------data------------------</br>";
        var_dump($data);
        foreach($data as $k => $v){
            $where['id_product']=$v['id_product'];
            $where['status']=1;
            $where['id_main']=['gt',0];
            $a=D('Product/ProductCheck')->field('id_checked')->where($where)->select();
            echo "</br>------------------------a------------------</br>";
            var_dump($a);
            $where2['id_product']=$v['id_product'];
            $where2['status']=['in',[0,2]];
            $c=D('Product/ProductCheck')->field('id_checked')->where($where2)->select();
            if(!empty($a)){
                //$b=D('Product/ProductCheck')->where($where2)->delete();
                $b=D('Product/ProductCheck')->field('id_checked')->where($where2)->select();
                echo "</br>------------------------b------------------</br>";
                var_dump($b);
            }
            //$d=D('Product/ProductCheck')->field('id_checked')->where($where2)->select();
            echo "</br>------------------------c------------------</br>";
            var_dump($c);
            //var_dump($d);
            $result[$k]=$b;
        }
        var_dump($result);die;
    }
    //add a record
    public function add_check_record($id_users,$des,$data,$id_check){
//        $id_users=1;
//        $data['555']=111;

//        $des="编辑";
//        $id_check=111;
        $data=json_encode($data);
        $adddata['id_users']=$id_users;
        $adddata['data']=$data;
        $adddata['des']=$des;
        $adddata['id_check']=$id_check;
        $adddata['created_at'] = date('Y-m-d H:i:s');
        $record=M('CheckRecord');
        $result=$record->data($adddata)->add();
        if($result){
            return true;
        }else{
            return fasle;
        }
    }
    public function list_diff_domain(){
        $where = array();
        if(isset($_GET['ad_username']) && $_GET['ad_username']) {
            $uwhere['user_nicename'] = array('like','%'.$_GET['ad_username'].'%');
            $userid = M('Users')->where($uwhere)->getField('id',true);
            !empty($userid) ?  $where['pc.id_users'] = array('IN',$userid) : $where['id_users'] = array(-1);
        }
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['pc.id_department'] = array('EQ',$_GET['department_id']);
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
            $_GET['pro_title']=trim($_GET['pro_title']);
            $where['pc.title|pc.inner_name|pc.domain'] = array('like','%'.$_GET['pro_title'].'%');
        }
        $users = M('Users')->field('id,user_nicename')->where(array('user_status' => 1))->select();
        $users = array_column($users, 'user_nicename', 'id');
        $record=M('ProductCheck');
        $where['pc.id_domain']=array('GT', 0);
        $where['pc.status'] = 1;
        // $where['pc.extra_domain']=['like',"%".pc.extra_domain as ."%"];
//        $count = $record->alias('pc') ->join('__DEPARTMENT__ as d ON d.id_department = pc.id_department', 'left')->where($where)->count();
//        $page = $this->page($count, 20);
        $list = $record->alias('pc')->field('*,d.title as dep,pc.title as title,pc.id_users as id_users') ->join('__DEPARTMENT__ as d ON d.id_department = pc.id_department', 'left')->where($where)->select();
        if(!empty($list)){
            foreach($list as $k =>$v){
                if(stristr($v['extra_domain'],$v['domain'])){
                    unset($list[$k]);
                }
            }
        }
        $department = M('Department')->where(array('type'=>1,'id_department'=>array('IN',$_SESSION['department_id'])))->getField('id_department,title',true);
        $this->assign('department',$department);
        $this->assign('users',$users);
        $this->assign('list',$list);
        //$this->assign("Page", $page->show('Admin'));
        // $this->assign("current_page", $page->GetCurrentPage());
        $this->assign("getData",$_GET);
        add_system_record($_SESSION['ADMIN_ID'], 4, 3,'域名和链接不匹配列表');
        $this->display();

    }
    public function list_check_record(){
        $where = array();
        if(isset($_GET['id_users'])&&$_GET['id_users']){
            $where['cr.id_users'] = trim($_GET['id_users']);
        }
        if(isset($_GET['id_check'])&&$_GET['id_check']){
            $where['cr.id_check'] = trim($_GET['id_check']);
        }
        if(isset($_GET['des'])&&$_GET['des']){
            $where['cr.des'] =array('LIKE','%'.trim($_GET['des']).'%');
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
            $_GET['pro_title']=trim($_GET['pro_title']);
            $where['pc.title|pc.inner_name|pc.domain|pc.style'] = array('like','%'.$_GET['pro_title'].'%');
        }
//        if(isset($_GET['data'])&&$_GET['data']){
//            $data=json_encode($_GET['data']);
//            $where['data'] =array('LIKE','%'.$data.'%');
//        }

        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['cr.created_at'] = $created_at_array;
        }
        $users = M('Users')->field('id,user_nicename')->where(array('user_status' => 1))->select();
        $users = array_column($users, 'user_nicename', 'id');
        $record=M('CheckRecord');
        if(isset($_REQUEST['act']) && $_REQUEST['act']=='export'){
            vendor("PHPExcel.PHPExcel");
            vendor("PHPExcel.PHPExcel.IOFactory");
            vendor("PHPExcel.PHPExcel.Writer.CSV");
            $excel = new \PHPExcel();
            $idx = 2;
            $column = array(
                //'id','物流','部门','扫码人员','订单号','快递单号','建立日期'
            );
            $j = 65;
            foreach ($column as $col) {
                $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
                ++$j;
            }

//            foreach ($list as $key=>$val) {
//                $data = array(
//                    $val['id'],$shipping_data[$val['id_shipping']],$department[$val['id_department']],$users[$val['id_users']],$val['id_increment'],$val['track_number'],$val['created_at']
//                );
//                $j = 65;
//                foreach ($data as $key=>$col) {
//                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
//                    ++$j;
//                }
//                ++$idx;
//            }
            add_system_record(sp_get_current_admin_id(), 7, 2, '导出列表');
            $excel->getActiveSheet()->setTitle(date('Y-m-d') . '列表.xlsx');
            $excel->setActiveSheetIndex(0);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '列表.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $writer->save('php://output');
            exit();
        }else{

            $count = $record->alias('cr')->join('__PRODUCT_CHECK__ AS pc ON pc.id_checked=cr.id_check')->where($where)->count();
            $page = $this->page($count, 20);
            $list = $record->alias('cr')->join('__PRODUCT_CHECK__ AS pc ON pc.id_checked=cr.id_check')->field('cr.*,pc.title,pc.inner_name,pc.domain')->where($where)->limit($page->firstRow, $page->listRows)->select();
            if(!empty($list)){
                foreach($list as $k =>$v){
                    $list[$k]['data']=json_decode($v['data'],true);
                }
            }

        }

        $this->assign('users',$users);
        $this->assign('list',$list);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign("getData",$_GET);
        add_system_record($_SESSION['ADMIN_ID'], 4, 3,'查看查重记录跟踪列表');
        $this->display();

    }
    public function syn_id_dep(){
        $data=D('Product/ProductCheck')->field('id_users,id_checked,id_department')->select();
        foreach($data as $k => $v){
            $dep= D('DepartmentUsers')->field('id_department')->order('id_department_users DESC')->where(array('id_users'=>$v['id_users']))->find();
            if(!empty($dep)) {
                $update=array();
                $update['id_department']=$dep['id_department'];
                if($update['id_department']!=$v['id_department']){
                    echo "原部门".$v['id_department']."改为".$dep['id_department'];echo ',查重id为'.$v['id_checked'];
                    $result[$k]=D('Product/ProductCheck')->where('id_checked='.$v['id_checked'])->save($update);
                }
            }

        }
        var_dump($result);die;
    }
//    /**
//     * 导出已备案列表-有出单
//     */
//    public function exprot_filing_order() {
//        set_time_limit(0);
//        vendor("PHPExcel.PHPExcel");
//        vendor("PHPExcel.PHPExcel.IOFactory");
//        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
//        $excel = new \PHPExcel();
//        $user_id = $_SESSION['ADMIN_ID'];
//        $where = array();
//        $effected_status = \Order\Lib\OrderStatus::get_effective_status();
//        $product_check_model = M('ProductCheck');
//        $order_model = M('Order');
//        $list = $product_check_model->alias('pc')
//            ->field('pc.*')
//            ->where(array('pc.status'=>1))
//            ->where(array('pc.id_domain'=>array('GT', 0),'pc.id_product'=>array('GT', 0)))
//            ->select();
//        $time_15days_before = date('Y-m-d', strtotime('-15 days'));
//        $time_10days_before = date('Y-m-d', strtotime('-10 days'));
//        $time_5days_before = date('Y-m-d', strtotime('-5 days'));
//
//        $columns = array(
//            '查重日期', '备案日期','类别', '产品名字','内部名',
//            '图片', '业务部', '广告专员', '域名','二级域名',
//            '产品链接', '采购链接','款式','备注','来源','15天历史出单量','10天历史出单量','5天历史出单量'
//        );
//        $j = 65;
//        foreach ($columns as $col) {
//            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
//            ++$j;
//        }
//        $idx = 2;
//        if($list) {
//            foreach($list as $key=>$val) {
//                $effected_order_count=0;
//                $effected_order_count2 =0;
//                $effected_order_count3=0;
//                $effected_order_count = $order_model->alias('o')
//                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
//                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
//                    ->where(array('oi.id_product'=>$val['id_product']))
//                    ->where(array('o.created_at'=> array('EGT', $time_15days_before)))
//                    ->count('distinct(o.id_order)');
//
//                if($effected_order_count>=1){
//                    $effected_order_count2 = $order_model->alias('o')
//                        ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
//                        ->where(array('o.id_order_status' => array('IN', $effected_status)))
//                        ->where(array('oi.id_product'=>$val['id_product']))
//                        ->where(array('o.created_at'=> array('EGT', $time_10days_before)))
//                        ->count('distinct(o.id_order)');
//                    $effected_order_count3 = $order_model->alias('o')
//                        ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
//                        ->where(array('o.id_order_status' => array('IN', $effected_status)))
//                        ->where(array('oi.id_product'=>$val['id_product']))
//                        ->where(array('o.created_at'=> array('EGT', $time_5days_before)))
//                        ->count('distinct(o.id_order)');
//                    $cat_name = M('CheckCategory')->where(array('id_category' => $val['id_check_category']))->getField('title');
//                    $depart = M('Department')->where(array('id_department' => $val['id_department']))->getField('title');
//                    $ad_username = M('Users')->where(array('id' => $val['id_users']))->getField('user_nicename');
//                    $img = $_SERVER['SERVER_NAME'].'/data/upload/'.$val['img_url'];
//                    $source = $val['pid'] == 0 ? '新品' : '销档';
//
//                    $data = array(
//                        $val['check_time'], $val['record_time'], $cat_name, $val['title'],$val['inner_name'],
//                        $img, $depart, $ad_username, $val['domain'],$val['extra_domain'],
//                        $val['sale_url'], $val['purchase_url'], $val['style'], $val['remark'],$source,$effected_order_count,$effected_order_count2,$effected_order_count3
//                    );
//                    $j = 65;
//                    foreach ($data as $col) {
//                        $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
//                        ++$j;
//                    }
//                    ++$idx;
//                }
//
//            }
//        } else {
//            $this->error('没有数据');
//        }
//        $excel->getActiveSheet()->setTitle(date('Y-m-d').'出单查重信息表.xlsx');
//        $excel->setActiveSheetIndex(0);
//        header('Content-Type: application/vnd.ms-excel');
//        header('Content-Disposition: attachment;filename="' . date('Y-m-d').'出单查重信息表.xlsx"');
//        header('Cache-Control: max-age=0');
//        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
//        $writer->save('php://output');exit();
//    }

    /**
     * 导出已备案列表-有出单
     */
    public function exprot_filing_order()
    {
        set_time_limit(0);
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();
        $user_id = $_SESSION['ADMIN_ID'];
        $where = array();
        $effected_status = \Order\Lib\OrderStatus::get_effective_status();
        $product_check_model = M('ProductCheck');
        $order_model = M('Order');
        $list = $product_check_model->alias('pc')
            ->field('pc.*')
            ->where(array('pc.status' => 1))
            ->where(array('pc.id_domain' => array('GT', 0), 'pc.id_product' => array('GT', 0),'record_time'=> array('GT', 0)))
            ->select();
        $time_15days_before = date('Y-m-d', strtotime('-15 days'));
        $time_10days_before = date('Y-m-d', strtotime('-10 days'));
        $time_7days_before = date('Y-m-d', strtotime('-7 days'));
        $time_5days_before = date('Y-m-d', strtotime('-5 days'));

        $columns = array(
            '查重日期', '备案日期', '类别', '产品名字', '内部名',
            '图片', '业务部', '广告专员', '域名', '二级域名',
            '产品链接', '采购链接', '款式', '备注', '来源', '15天内出单量', '10天内出单量', '7天内出单量', '5天内出单量',
            '备案5天内出单量','备案7天内出单量','备案10天内出单量','备案15天内出单量',
        );
        $j = 65;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $idx = 2;
        if ($list) {
            foreach ($list as $key => $val) {
                $effected_order_count = 0;
                $effected_order_count2 = 0;
                $effected_order_count3 = 0;
                $effected_order_count4 = 0;
                $effected_order_count5 = 0;
                $effected_order_count7 = 0;
                $effected_order_count10 = 0;
                $effected_order_count15 = 0;
                $effected_order_count = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
                    ->where(array('oi.id_product' => $val['id_product']))
                    ->where(array('o.created_at' => array('EGT', $time_15days_before)))
                    ->count('distinct(o.id_order)');
                $effected_order_count2 = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
                    ->where(array('oi.id_product' => $val['id_product']))
                    ->where(array('o.created_at' => array('EGT', $time_10days_before)))
                    ->count('distinct(o.id_order)');
                $effected_order_count3 = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
                    ->where(array('oi.id_product' => $val['id_product']))
                    ->where(array('o.created_at' => array('EGT', $time_7days_before)))
                    ->count('distinct(o.id_order)');
                $effected_order_count4 = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
                    ->where(array('oi.id_product' => $val['id_product']))
                    ->where(array('o.created_at' => array('EGT', $time_5days_before)))
                    ->count('distinct(o.id_order)');
                $time_15days = date('Y-m-d', (strtotime($val['record_time'])+15*24*3600));
                $time_10days = date('Y-m-d', (strtotime($val['record_time'])+10*24*3600));
                $time_7days = date('Y-m-d', (strtotime($val['record_time'])+7*24*3600));
                $time_5days = date('Y-m-d', (strtotime($val['record_time'])+5*24*3600));
                $effected_order_count5 = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
                    ->where(array('oi.id_product' => $val['id_product']))
                    ->where(array('o.created_at' => array('ELT', $time_5days)))
                    ->count('distinct(o.id_order)');
                $effected_order_count7 = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
                    ->where(array('oi.id_product' => $val['id_product']))
                    ->where(array('o.created_at' => array('ELT', $time_7days)))
                    ->count('distinct(o.id_order)');
                $effected_order_count10 = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
                    ->where(array('oi.id_product' => $val['id_product']))
                    ->where(array('o.created_at' => array('ELT', $time_10days)))
                    ->count('distinct(o.id_order)');
                $effected_order_count15 = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
                    ->where(array('oi.id_product' => $val['id_product']))
                    ->where(array('o.created_at' => array('ELT', $time_15days)))
                    ->count('distinct(o.id_order)');
                $cat_name = '';
                $depart = '';
                $ad_username = '';
                $img = $_SERVER['SERVER_NAME'] . '/data/upload/' . $val['img_url'];
                $source = $val['pid'] == 0 ? '新品' : '销档';
                $data = array(
                    $val['check_time'], $val['record_time'], $cat_name, $val['title'], $val['inner_name'],
                    $img, $depart, $ad_username, $val['domain'], $val['extra_domain'],
                    $val['sale_url'], $val['purchase_url'], $val['style'], $val['remark'], $source, $effected_order_count, $effected_order_count2, $effected_order_count3, $effected_order_count4,
                    $effected_order_count5,$effected_order_count7,$effected_order_count10,$effected_order_count15

                );
                $j = 65;
                foreach ($data as $col) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                    ++$j;
                }
                ++$idx;
                // }

            }
            $excel->getActiveSheet()->setTitle(date('Y-m-d') . '出单查重信息表.xlsx');
            $excel->setActiveSheetIndex(0);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '出单查重信息表.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $writer->save('php://output');
            exit();
        }
    }
}
