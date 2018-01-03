<?php


namespace Product\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;

/**
 * 模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Purchase\Controller
 */
class ImportcheckController extends AdminbaseController {
    private $status	= array(
        0=>array('t.status'=>array('IN',array(1,2))),
        1=>array('t.status'=>array('EQ',1)),
        2=>array('t.status'=>array('EQ',2))
    );

    private $type	= array(
        1=>array('name'=>'import_check', 'cols'=>array('user_name','domain_name','advert_name','post_at','advert_status')),
    );
    /**
     * 导入查重
     */
    public function import_check() {

        $this->import_list(1);
        $this->display();
    }

    /*
     * 列表
     */
    protected function import_list($type_id, $id=0) {

        $where 	= $id>0?array():$this->status[0];
        $pcount	=	20;			/*默认每页显示*/
        if (IS_GET)
        {
            $where=$this->status[I('get.status2')];
            if(!$id)
            {
                $where['t.type'] 	= array('EQ', $this->type[$type_id]['name']);
                // ('0'==I('get.status2')||I('get.status2')) && $where 									= $this->status[I('get.status2')];		/*状态*/
            }
            $created_at_array = array();
            if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
                if ($_GET['start_time'])
                    $created_at_array[] = array('EGT', $_GET['start_time']);
                if ($_GET['end_time'])
                    $created_at_array[] = array('LT', $_GET['end_time']);
                $where['t.create_time'] = $created_at_array;
            }
            if (!empty($_GET['users'])) {
                $where['u.user_nicename'] =array("like",'%'.$_GET['users'].'%');
            }
            $pcount2 = intval(I('get.pcount'));
            if($pcount2>0)$pcount = $pcount2;
        }
        $model = new \Think\Model();
        $import_table_name 			= M('CheckImport')->getTableName();
        $import_data_table_name 	= M('CheckImportData')->getTableName();
        if($id>0)
        {
            $where['id_check_import'] 	= array('EQ', $id);
            $count = $model->table($import_data_table_name)->where($where)->count();
            $page = $this->page($count, $pcount);
            $list = $model->table($import_data_table_name." a")
                ->join("__USERS__ u ON (a.id_users = u.id)", 'LEFT')
                ->join("__DOMAIN__ d ON (a.id_domain = d.id_domain)", 'LEFT')
                ->field('a.*,u.user_nicename as users,d.name as domain')
                ->where($where)
                ->order("id DESC")
                ->limit($page->firstRow, $page->listRows)
                ->select();
        }
        else
        {
            $count = $model->table($import_table_name . " t")
                ->join("{$import_data_table_name} ti ON (t.id = ti.id_check_import)", 'LEFT')
                ->join("__USERS__ u ON (t.owner_id = u.id)", 'LEFT')
                ->field('t.id')
                ->where($where)
                ->group('t.id')
                ->count();
            $page = $this->page($count, $pcount);
            $list = $model->table($import_table_name . " t")
                ->join("{$import_data_table_name} ti ON (t.id = ti.id_check_import)", 'LEFT')
                ->join("__USERS__ u ON (t.owner_id = u.id)", 'LEFT')
                ->field('t.*,ti.*,count(ti.id) as sum,u.user_nicename as owner_id_nicename,t.id as id_check_import')
                ->where($where)
                ->group('t.id')
                ->order("t.id DESC")
                ->limit($page->firstRow, $page->listRows)
                ->select();
        }

        if(!empty($list)&&!$id)
        {
            foreach ($list as $k => &$v)
            {
               // $v['owner_id_nicename'] 		= !empty($v['owner_id']) ? M('Users')->where(array('id' => array('EQ',$v['owner_id'])))->getField('user_nicename') : '';
                $v['statuser_id_nicename'] 	= !empty($v['statuser_id']) ? M('Users')->where(array('id' => array('EQ',$v['owner_id'])))->getField('user_nicename') : '';
            }
        }

        $this->assign('list',$list);
        $this->assign("page",$page->show('Admin'));
        $this->assign("pcount",$pcount);
        $this->assign("type_id", $type_id);

    }
    /**
     * 明细
     */
    private function detail($type_id) {
        $id = I('get.id');
        $import = array();
        if(!empty($id))
        {
            $this->import_list($type_id, $id);
            $import = M('CheckImport')->where(array('id'=>$id))->find();
        }
        $zone  = D('Zone')->cache(true,3600)->select();
        $zone  = $zone?array_column($zone,'title','id_zone'):array();
        $this->assign('zone', $zone);
        $this->assign('id', $id);
        $this->assign('type_id', $type_id);
        $this->assign('import', $import);

    }
    /**
     * 产生新的单据编码
     */
    protected function createDocno($tableName,$prefix) {
        if(empty($tableName)||empty($prefix)){
            return FALSE;
        }
        $prefix = $prefix. date('ymd', time());
        $cond['billdate'] = array('like', '%' . date('Y-m-d', time()) . '%');
        $lastDocno = M($tableName)->where($cond)->order('id desc')->field('docno')->find();
        $lastNum = 0;
        if ($lastDocno['docno']) {
            $lastNum = substr($lastDocno['docno'], strlen($prefix));
        }
        $cur_num = $lastNum + 1;
        return $prefix . str_pad($cur_num, 7, '0', STR_PAD_LEFT);
    }
    /**
     * 明细
     */
    public function look() {

        $type_id 		= I('get.type_id');
        $this->detail($type_id);
        $this->assign('type_id', $type_id);
        $this->display($this->type[$type_id]['name'].'_look');
    }

    /**
     * 添加
     */
    public function add() {

        $type_id 		= I('get.type_id');
        $this->detail($type_id);
        $this->display($this->type[$type_id]['name'].'_add');
    }
    /**
     * 添加
     */
    public function add_post() {

        if (IS_AJAX) {
            $message = array();
            try
            {
                $type_id							= I('post.type_id');
                $id_check_import	= I('post.id_check_import');
                $save_data = array();
                $save_data['description'] 	= I('post.description');
                if($id_check_import>0)
                {
                    M('CheckImport')->where(array('id'=>array('EQ',$id_check_import)))->save($save_data);
                }
                else
                {
                    $save_data['type'] 				= $this->type[$type_id]['name'];
                    $save_data['owner_id'] 		= sp_get_current_admin_id();
                    $save_data['create_time'] = date('Y-m-d H:i:s');
                    $id_check_import = M('CheckImport')->data($save_data)->add();
                }
                $message 	= '保存成功';
                $status 	= 1;

            } catch (\Exception $e) {
                $status 	= 0;
                $message = $e->getMessage();
            }

            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ("广告管理导入保存({$this->type[$type_id]['name']}):".is_array($message)?implode("\n",$message):$message));
            $return = array('status' => $status, 'id' => $id_check_import, 'message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }
    /**
     * 添加
     */
    public function add_post2() {

        if (IS_AJAX) {
            $message = array();
            try
            {

                $type_id							= I('post.type_id');
                $isimport 						= I('post.isimport');
                $data 								= I('post.data');
                $data_tmp							= array();
                $id_check_import	= I('post.id_check_import');

                if(!empty($data))
                {
                    $data 	= $this->getDataRow($data);
                    $count 	= 1;
                    foreach ($data as $k=>$row)
                    {
                        $row = trim($row);
                        if (empty($row))continue;

                        $row = explode("\t", trim($row), count($this->type[$type_id]['cols']));
                        if (count($row) != count($this->type[$type_id]['cols']) || !$row[0]) {
                            $message[] = sprintf('第%s行: 格式不正确', $count++);
                            continue;
                        }
                        $data_tmp[$k] = array();
                        foreach($this->type[$type_id]['cols'] as $k2=>$v){
                            $item[$v] = $row[$k2];
                        }
                        $id_users = M('Users')->where(array('user_nicename'=>$item['user_name']))->getField('id');
                        $id_domain = M('Domain')->where(array('name'=>$item['domain_name']))->getField('id_domain');
                        if($id_users&&$id_domain){
                            if($type_id=='1'){
                                $data_tmp[$k]['id_users'] = $id_users;
                                $data_tmp[$k]['id_domain'] = $id_domain;
                                $data_tmp[$k]['advert_name'] = $item['advert_name'];
                                $data_tmp[$k]['post_at'] = $item['post_at'];
                                $data_tmp[$k]['advert_status'] = ($item['advert_status'] == '启用') ? 1 :($item['advert_status'] == '停用' ?  0 : '');
                                $data_tmp[$k]['add_user'] = $_SESSION['ADMIN_ID'];
                            }else{
                                $data_tmp[$k]['id_users'] = $id_users;
                                $data_tmp[$k]['id_domain'] = $id_domain;
//
//
                                $where_advert['user_nicename'] = $item['user_name'];
                                $where_advert['name'] = $item['domain_name'];
                                $getAdvert = M('Advert')->alias('a')->join('__USERS__  as u on u.id = a.id_users ','LEFT')
                                    ->join('__DOMAIN__  as d on d.id_domain = a.id_domain ','LEFT')
                                    ->where($where_advert)
                                    ->getField('advert_id');
                                if(!$getAdvert){
                                    $message[] = sprintf('第%s行: 广告不存在', $count++);
                                    continue;
                                }
                                $id_zone = M('Zone')->where(array('title'=>$item['zone']))->getField('id_zone');
                                if(!$id_zone){
                                    $message[] = sprintf('第%s行: 地区不存在', $count++);
                                    continue;
                                }
                                $data_tmp[$k]['advert_id'] = $getAdvert;
                                $data_tmp[$k]['id_zone'] =$id_zone;
                                $data_tmp[$k]['created_at'] =date('Y-m-d H:i:s');
                                $data_tmp[$k]['conversion_at'] = $item['conversion_at'];
                                $data_tmp[$k]['expense'] = $item['expense'];
                                $data_tmp[$k]['add_user'] = $_SESSION['ADMIN_ID'];
                            }
                        }else{
                            $message[] = sprintf('第%s行:优化师或者域名有误', $count++);
                            continue;
                        }
                    }
                }
                unset($data);
                if(!empty($message))
                {
                    $return = array('status' => 0, 'id' => $id_check_import, 'message' => (is_array($message)?implode("\n",$message):$message));
                    echo json_encode($return);exit();
                }

                $save_data = array();
                if(!$isimport)
                {
                    $save_data['description'] 	= I('post.description');
                }

                if($id_check_import>0)
                {
                    M('CheckImport')->where(array('id'=>array('EQ',$id_check_import)))->save($save_data);
                }
                else
                {
                    $save_data['type'] 				= $this->type[$type_id]['name'];
                    $save_data['owner_id'] 		= sp_get_current_admin_id();
                    $save_data['create_time'] = date('Y-m-d H:i:s');
                    $id_check_import = M('CheckImport')->data($save_data)->add();
                }

                if(!empty($data_tmp))
                {
                    foreach($data_tmp as $v)
                    {
                        $v['id_check_import'] = $id_check_import;
                        M('CheckImportData')->data($v)->add();
                    }
                }

                $message 	= $isimport?'导入成功':'保存成功';
                $status 	= 1;

            } catch (\Exception $e) {
                $status 	= 0;
                $message = $e->getMessage();
            }

            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ("出库管理导入保存({$this->type[$type_id]['name']}):".is_array($message)?implode("\n",$message):$message));

            $return = array('status' => $status, 'id' => $id_check_import, 'message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }

    /**
     * 删除明细
     */
    public function del_item() {
        if(IS_AJAX) {

            try {

                $ids 			= I('post.ids');
                $type_id	= I('post.type_id');

                if(!empty($ids))
                {
                    M('CheckImportData')->where(array('id'=>array('IN',$ids)))->delete();
                    $status	 = 1;
                    $message	 = '删除成功';
                }

            } catch (\Exception $e) {
                $status	 = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ("出库管理导入删除明细({$this->type[$type_id]['name']}):".is_array($message)?implode("\n",$message):$message));
            $return = array('status'=>$status,'message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }

    /*
     * 删除
     */
    public function delete() {

        if(IS_AJAX) {
            try {

                $id_check_import = I('post.id_check_import');
                $message = array();

                if(!empty($id_check_import))
                {

                    foreach($id_check_import as $id)
                    {
                        M("CheckImport")->where(array("id" => $id))->delete();
                        M("CheckImportData")->where(array("id_check_import" => $id))->delete();
                    }

                    $message = '作废成功';
                }
            } catch (\Exception $e) {

                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('出库导入作废:'.is_array($message)?implode("\n",$message):$message));
            $return = array('message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }

    /**
     * 提交
     */
    public function submit()
    {

        if (IS_AJAX) {

            $message = array();
            try {

                $id_check_import = I('post.id_check_import');

                if(!empty($id_check_import))
                {

                    foreach($id_check_import as $id)
                    {
                        $import 			= M('CheckImport')->where(array('id'=>$id))->find();
                        $import_data 	= M("CheckImportData")->where(array("id_check_import" => $id))->select();
                        if(!empty($import))
                        {

                            $submit	 = $import['type'].'_submit';
                            $type_id = $this->get_type_id($import['type']);
                            if(!empty($import_data))
                            {
                               $ret = $this->$submit($type_id,$import,$import_data);
                                if($ret){
                                    $upd_data=array('status'=>2,'statuser_id' => $_SESSION['ADMIN_ID'], 'status_time' => date('Y-m-d H:i:s'));
                                    M('CheckImport')->where(array('id'=>$id))->save($upd_data);
                                }

                            }

                        }
                    }
                    $message="提交成功";

                }

            } catch (\Exception $e) {

                $message = $e->getMessage();
            }

            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('广告管理导入提交:'.is_array($message)?implode("\n",$message):$message));
            $return = array('message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }

    /**
     * 提交（查重）
     */
    private function import_check_submit($type_id,$import,$import_data)
    {
        $checkObj = M("check");
        $checkDataObj = M("checkData");
        foreach($import_data as $k => $v){
            $add['id_users'] = $v['id_users'];
            $add['id_domain'] = $v['id_domain'];
            $add['check_name'] = $v['check_name'];
            $add['created_at'] = date('Y-m-d H:i:s');
            $add['post_at'] = $v['post_at'];
            $add['check_status'] = ($v['check_status'] == '启用') ? 1 :($v['check_status'] == '停用' ?  0 : '');
            $add['add_user'] = $v['add_user'] ;
            $checkObj->add($add);
        }
        return true;
    }

    public function kkk(){
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $dep = $_SESSION['department_id'];
        if (IS_POST) {
            $data = I('post.data');
            $path = write_file('check', 'import', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 7);
                $addData['title']=$row[1];
                $addData['sale_url']=$row[5];
                $addData['purchase_url']=$row[6];
                $addData['domain']=$row[4];
                $category = M('CheckCategory')->where(array('title'=>$row[0]))->getField('id_category');
                if($category) {
                    $addData['id_check_category']=$category;
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
                        $addData['inner_name']=$this->get_inner_name($addData['id_domain']);
                        $addData['check_time']=  date('Y-m-d H:i:s');
                        $product = D('Product/ProductCheck');
                        $result=$product->data($addData)->add();
                        $info['success'][] = sprintf('第%s行:导入查重成功', $count++, $row[0],$row[1],$row[2],$row[3],$addData['domain'],$row[5],$row[6]);
                    }else{
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
                                    $info['success'][] = sprintf('第%s行:导入查重成功', $count++, $row[0],$row[1],$row[2],$row[3],$addData['domain'],$row[5],$row[6]);
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
     * type id
     */
    private function get_type_id($type)
    {
        $type_id = 0;
        foreach($this->type as $k=>$v)
        {
            if($v['name']==$type)
            {
                $type_id = $k;
            }
        }
        return $type_id;
    }

}
