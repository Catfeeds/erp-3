<?php

namespace Domain\Controller;

use Common\Controller\AdminbaseController;
use Common\Model\RoleModel;
use phpseclib\Net\SFTP;
use phpseclib\Net\SSH2;
use Think\Model;
use Order\Lib\OrderStatus;

class IndexController extends AdminbaseController {

    protected $role_model;

    /** @var  \Think\Model */
    protected $auth_access_model;

    /** @var  \Common\Model\DomainModel */
    protected $domain;

    public function _initialize() {
        parent::_initialize();
        $this->role_model = D("Common/Role");
        $this->domain = D("Common/Domain");
    }

    //所有域名列表
    public function all_list() {
        $count = $this->domain->count();
        $page = $this->page($count, 20);

        $data = $this->domain
                ->order(array("id_domain" => "desc"))
                ->limit($page->firstRow, $page->listRows)
                ->select(); //获取跟当前部门有关的数据
        add_system_record(sp_get_current_admin_id(), 4, 3, '所有域名列表');
        $this->assign("domains", $data);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();var_dump($page);
    }

    /**
     * 有add添加，edit编辑，delete删除
     */
    public function index() {
        $dep = $_SESSION['department_id'];
        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $where['d.name'] = array('LIKE', '%' . $_GET['keyword'] . '%');
        }
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where['_string'] = "d.id_department=".$_GET['id_department']." AND d.id_department in(".implode(",",$dep).")";
        }else{
            $where['d.id_department'] = array('IN',$dep);
        }
        //筛选有效订单     --Lily 2017*11-09
        $whereO['o.id_order_status'] = array("IN",OrderStatus::get_effective_status());
        $data = $this->domain->alias("d")
                ->join("__ORDER__ AS o ON d.id_domain=o.id_domain","LEFT")
                ->order(array("d.id_domain" => "desc"))
                ->field("d.*,count(o.id_order) as qty_ordered")
                ->group("o.id_domain")
                ->where($where)
                ->where($whereO)
               ->select(); //获取跟当前部门有关的数据
        //如果搜索的域名没有订单则搜不出来 ，所以不计算有效单，直接搜索出该域名的记录  --Lily 2017-11-13
         if(empty($data)||!$data){
            $data = $this->domain->alias("d")->where($where)->select();
        } 
       //分页 --Lily 20117-11-09
        $per_num = 50; //每一页的条数
        $p = $_GET['p']; //接收前台传过来的p值
        $p==""?'0':$p;  //判断p值是否存在
        $start = $p==0?$p*$per_num:($p-1)*$per_num; //数组截取的开始位置
        $listda = array_slice($data,$start,$per_num,true);//数组截取值 分页
        $num = count($data);//dump($num);exit;//总条数
        $page = $this->page($num, $per_num );
        $whereD['type'] = 1;
        $whereD['id_department'] = array("IN",implode(",",$dep));
        $depart_list = D('department')->where($whereD)->getField('id_department,title', true);
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看域名列表');;
        $this->assign("domains", $listda);
        $this->assign("depart_list", $depart_list);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }
    /**
     * 域名部分导出列表
     */
    public function export_index(){
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $column = array(
            '域名','IP地址','部门','创建时间','有效订单'
        );
        $j = 65;
        $idx = 2;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $export_url = I('get.export_url'); //获取到所需要导入的所有ID，字符串 , 分割
        $arr_url = explode(',', $export_url);
        if($arr_url){
            $where['d.id_domain'] = array('IN',$arr_url);
        }
        
        $dep = $_SESSION['department_id'];
        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $where['d.name'] = array('LIKE', '%' . $_GET['keyword'] . '%');
        }
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where['_string'] = "d.id_department=".$_GET['id_department']." AND d.id_department in(".implode(",",$dep).")";
        }else{
            $where['d.id_department'] = array('IN',$dep);
        }
        // $where['type'] = array('EQ',1);
        //获取跟当前部门有关的域名
        // 获取每个域名的创建时间  有效订单   --Lily 2017-11-09 
        //$whereO['o.id_order_status'] = array("IN",OrderStatus::get_effective_status());
        //避免无效单为0时导出来没有数据，现修改下链表查询方式 zx 11/30
        $effective_status = OrderStatus::get_effective_status();
        $list = $this->domain
                ->alias("d")
                ->join("__ORDER__ AS o ON o.id_domain=d.id_domain","LEFT")
                ->order(array("d.id_domain" => "desc"))
                //->field('d.name,d.ip,d.id_department,d.created_at,count(o.id_order) as qty_ordered')
                ->field("d.name,d.ip,d.id_department,d.created_at,o.id_order_status,SUM(IF(`id_order_status` IN(".implode(',', $effective_status)."),1,0)) as qty_ordered")
                ->group("o.id_domain")
                ->where($where)
                //->where($whereO)
                ->order(array("d.id_domain" => "desc"))
                ->select();
         if(empty($list)||!$list){
        $data = $this->domain->alias("d")->where($where)->select();
    }
        //获取所有业务部门
        $departments = M('Department')->where(['type'=>1])->getField('id_department,title');
        foreach ($list as  $k => $val) {
            $data = array(
                $val['name'],$val['ip'],$departments[$val['id_department']],$val['created_at'],$val['qty_ordered']
            );

            $j = 65;
            foreach ($data as $key => $col) {
                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出域名列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '导出域名列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '导出域名列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
    /**
     * 域名全部导出列表
     */
    public function export_all(){
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $column = array(
            '域名','IP地址','部门','创建时间','有效订单'
        );
        $j = 65;
        $idx = 2;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
         $dep = $_SESSION['department_id'];
        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $where['d.name'] = array('LIKE', '%' . $_GET['keyword'] . '%');
        }
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where['_string'] = "d.id_department=".$_GET['id_department']." AND d.id_department in(".implode(",",$dep).")";
        }else{
            $where['d.id_department'] = array('IN',$dep);
        }
        // $where['type'] = array('EQ',1);
        //获取跟当前部门有关的域名
        // 获取每个域名的创建时间  有效订单   --Lily 2017-11-09
        $whereO['o.id_order_status'] = array("IN",OrderStatus::get_effective_status());
        $list = $this->domain
                ->alias("d")
                ->join("__ORDER__ AS o ON o.id_domain=d.id_domain","LEFT")
                ->field('d.name,d.ip,d.id_department,d.created_at,count(o.id_order) as qty_ordered')
                ->group("o.id_domain")
                ->where($where)
                ->where($whereO)
                ->order(array("d.id_domain" => "desc"))
                ->select();
        //获取所有业务部门
        $departments = M('Department')->where(['type'=>1])->getField('id_department,title');
        foreach ($list as  $k => $val) {
            $data = array(
                $val['name'],$val['ip'],$departments[$val['id_department']],$val['created_at'],$val['qty_ordered']
            );

            $j = 65;
            foreach ($data as $key => $col) {
                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出域名列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '导出域名列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '导出域名列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    /**
     * 添加
     */
    public function add() {
        $dep = $_SESSION['department_id'];

        $list = D("Common/Department")->where(array('id_department'=>array('IN',$dep),'type'=>1))->order('id_department ASC')
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
            $data['name'] = trim($data['name']);
            $data['created_at'] = date('Y-m-d H:i:s', time());
            $data['id_admin'] = $_SESSION['ADMIN_ID'];
            if ($this->domain->create($data)) {
                $this->sftp_edit_domain($data['name']);
                if ($this->domain->add($data)) {

                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加域名成功');
                    $this->success("添加域名成功", U("index/index"));
                } else {
                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加域名失败');
                    $this->error('添加域名失败');
                }
            } else {
                $this->error($this->domain->getError());
            }
        }
    }

    /**
     * 删除
     */
    public function delete() {
        $id = intval(I("get.id"));

        $status = $this->domain->delete($id);
        if ($status !== false) {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除域名'.$id.'成功');
            $this->success("删除成功！", U('index/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除域名'.$id.'失败');
            $this->error("删除失败！");
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
        $dom_table_name = D("Common/Domain")->getTableName();
        $dep_table_name = D("Common/Department")->getTableName();

        $dep = $_SESSION['department_id'];
        $list = D("Common/Department")->where(array('id_department'=>array('IN',$dep),'type'=>1))->order('id_department ASC')
                ->select();

        $data = $this->domain->table($dom_table_name . ' AS d LEFT JOIN ' . $dep_table_name . ' AS u ON d.id_department=u.id_department')->where(array("id_domain" => $id))->find();

        if (!$data) {
            $this->error("该域名不存在！");
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
            $data = I('post.');
            $data['name'] = trim($data['name']);
            $data['updated_at'] = date('Y-m-d H:i:s', time());
            $data['id_admin'] = $_SESSION['ADMIN_ID'];
            if ($this->domain->create($data)) {
                $this->sftp_edit_domain($data['name']);
                if ($this->domain->save($data)) {
                    add_system_record(sp_get_current_admin_id(), 2, 3, '修改域名'.$data['id_domain'].'成功');
                    $this->success("修改成功！", U('index/index'));
                } else {
                    add_system_record(sp_get_current_admin_id(), 2, 3, '修改域名'.$data['id_domain'].'失败');
                    $this->error("修改失败！");
                }
            } else {
                $this->error($this->domain->getError());
            }
        }
    }

    private function sftp_edit_domain ($domain)
    {
        $domain = trim($domain);
        $sites = C("REMOTE_HOST");
        foreach ($sites as $k=>$v) {
            $sftp = new SFTP($v["REMOTE_HOST"]);
            if (!$sftp->login($v["REMOTE_HOST_USER"], $v["REMOTE_HOST_PASS"])) {
                exit('SFTP Login Failed');
            }
            $str = "NameVirtualHost ".$v['REMOTE_HOST_INTRANET_IP']."
                    <VirtualHost ".$v['REMOTE_HOST_INTRANET_IP'].">
                        ServerName fecsean.com
                        ServerAlias {$domain}
                        DocumentRoot ". $v['REMOTE_HOST_PATH'] ."
                        ErrorLog /home/logs/error_log
                        CustomLog /home/logs/access_log combined
                        DirectoryIndex index.php index.php4 index.php5 index.html index.htm
                        <Directory  ". $v['REMOTE_HOST_PATH'] .">
                            Options -Indexes +IncludesNOEXEC +FollowSymLinks
                            allow from all
                            AllowOverride All Options=Includes,IncludesNOEXEC,Indexes,MultiViews,FollowSymLinks
                        </Directory>
                    </VirtualHost>
                    ";
            $sftp->put('/etc/httpd/vhost/'. $domain .'.conf', $str);
            $sftp->disconnect();
            $ssh = new SSH2($v["REMOTE_HOST"]);
            if (!$ssh->login($v["REMOTE_HOST_USER"], $v["REMOTE_HOST_PASS"])) {
                exit('SSH Login Failed');
            }
            $ssh->exec('service httpd reload');
            $ssh->disconnect();
        }

    }

    /**
    **域名备案
    */
    public function domain_list(){
        $dep = $_SESSION['department_id'];
        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $where['_string'] = "id_department=".$_GET['keyword']." AND id_department in(".implode(",",$dep).")";
        }else{
            $where['id_department'] = array('IN',$dep);
        }
        $where['type'] = array('EQ',1);
        $count = $this->domain->where($where)->count();
        $page = $this->page($count, 20);
        $data = $this->domain->where($where)
                ->order(array("id_domain" => "desc"))
                ->limit($page->firstRow, $page->listRows)
                ->field("id_domain,id_admin,id_department,created_at,updated_at,name,smtp_user,status")
                ->select(); //获取跟当前部门有关的数据
        $depart_list = D('department')->getField('id_department,title', true);
        $user_list = D('users')->getField('id,user_nicename', true);
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看域名列表');
        $this->assign("domains", $data);
        $this->assign("user_list", $user_list);
        $this->assign("depart_list", $depart_list);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }


    /**
     * 角色授权
     */
    public function authorize() {
        $this->auth_access_model = D("Common/AuthAccess");
        //角色ID
        $roleid = intval(I("get.id"));
        if (!$roleid) {
            $this->error("参数错误！");
        }
        import("Tree");
        $menu = new \Tree();
        $menu->icon = array('│ ', '├─ ', '└─ ');
        $menu->nbsp = '&nbsp;&nbsp;&nbsp;';
        $result = $this->initMenu();
        $newmenus = array();
        $priv_data = $this->auth_access_model->where(array("role_id" => $roleid))->getField("rule_name", true); //获取权限表数据
        foreach ($result as $m) {
            $newmenus[$m['id']] = $m;
        }

        foreach ($result as $n => $t) {
            $result[$n]['checked'] = ($this->_is_checked($t, $roleid, $priv_data)) ? ' checked' : '';
            $result[$n]['level'] = $this->_get_level($t['id'], $newmenus);
            $result[$n]['parentid_node'] = ($t['parentid']) ? ' class="child-of-node-' . $t['parentid'] . '"' : '';
        }
        $str = "<tr id='node-\$id' \$parentid_node>
                       <td style='padding-left:30px;'>\$spacer<input type='checkbox' name='menuid[]' value='\$id' level='\$level' \$checked onclick='checknode(this);'> \$name</td>
	    			</tr>";
        $menu->init($result);
        $categorys = $menu->get_tree(0, $str);

        $this->assign("categorys", $categorys);
        $this->assign("roleid", $roleid);
        $this->display();
    }

    /**
     * 角色授权
     */
    public function authorize_post() {
        $this->auth_access_model = D("Common/AuthAccess");
        if (IS_POST) {
            $roleid = intval(I("post.roleid"));
            if (!$roleid) {
                $this->error("需要授权的角色不存在！");
            }
            if (is_array($_POST['menuid']) && count($_POST['menuid']) > 0) {

                $menu_model = M("Menu");
                $auth_rule_model = M("AuthRule");
                $this->auth_access_model->where(array("role_id" => $roleid, 'type' => 'admin_url'))->delete();
                foreach ($_POST['menuid'] as $menuid) {
                    $menu = $menu_model->where(array("id" => $menuid))->field("app,model,action")->find();
                    if ($menu) {
                        $app = $menu['app'];
                        $model = $menu['model'];
                        $action = $menu['action'];
                        $name = strtolower("$app/$model/$action");
                        $this->auth_access_model->add(array("role_id" => $roleid, "rule_name" => $name, 'type' => 'admin_url'));
                    }
                }

                $this->success("授权成功！", U("Rbac/index"));
            } else {
                //当没有数据时，清除当前角色授权
                $this->auth_access_model->where(array("role_id" => $roleid))->delete();
                $this->error("没有接收到数据，执行清除授权成功！");
            }
        }
    }

    /**
     *  检查指定菜单是否有权限
     * @param array $menu menu表中数组
     * @param int $roleid 需要检查的角色ID
     */
    private function _is_checked($menu, $roleid, $priv_data) {

        $app = $menu['app'];
        $model = $menu['model'];
        $action = $menu['action'];
        $name = strtolower("$app/$model/$action");
        if ($priv_data) {
            if (in_array($name, $priv_data)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取菜单深度
     * @param $id
     * @param $array
     * @param $i
     */
    protected function _get_level($id, $array = array(), $i = 0) {

        if ($array[$id]['parentid'] == 0 || empty($array[$array[$id]['parentid']]) || $array[$id]['parentid'] == $id) {
            return $i;
        } else {
            $i++;
            return $this->_get_level($array[$id]['parentid'], $array, $i);
        }
    }

    public function member() {
        //TODO 添加角色成员管理
    }

    /*
     * 批量导入域名，格式限定每行三列： 域名、IP地址、SMTP用户
     */
    public function import() {
        $dep = $_SESSION['department_id'];
        if (IS_POST) {

            $data = I('post.data');
            $id_department = I('post.id_department'); //部门id

            //导入记录到文件
            $path = write_file('import', 'import', $data);
            $count = 1;
            $total = 0;
            $data = $this->getDataRow($data);
           foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 2);
               if (count($row) != 2 || !$row[0]) {  //判断格式
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }

                $data['id_department'] = $id_department; //部门ID
                $data['name'] = trim($row[0]); //域名
                //$data['ip'] = trim($row[1]);  // ip 47.90.110.28
                $data['ip'] = '47.90.110.28';  // ip 47.90.110.28
                $data['smtp_user'] = trim($row[1]); //SMTP用户
                $data['created_at'] = date('Y-m-d H:i:s', time()); //时间
                $data['id_admin'] = $_SESSION['ADMIN_ID']; //用户ID
                $data['smtp_host'] = 'yoyoemail.com'; //SMTP主机
                $data['smtp_pwd'] = 'Achuangshi888'; //	SMTP密码
                $data['smtp_port'] = 25; //	SMTP端口
                $data['status'] = 1; //状态，1可用，0不可用
                

                if(empty($data['name'])){
                    $infor['error'][] = sprintf('第%s行:找不到对应的域名 %s  %s  %s %s ', $count,$conversion_at,$type,$url,$expense);
                    exit('1');
                }else{

                    $where_name['name'] = $data['name'];
                    $domain = $this->domain->where($where_name)->find();
                    //dump($domain);exit;
                    if($domain['name']){ //已经存在该域名，提示错误信息
                        $department_name = M('Department')->field('title')->where(array('id_department'=>$domain['id_department']))->find();
                        
                        $infor['error'][] = sprintf('第%s行:%s     已存在这条域名 ,部门是%s', $count,$data['name'],$department_name['title']); //已经存在该域名，提示错误信息

                        }else{

                                if ($this->domain->add($data)) { //添加成功

                                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加域名成功'); //添加系统操作记录
                                    $this->sftp_edit_domain($data['name']); // 生成配置
                                    $infor['success'][] = sprintf('第%s行: %s 添加成功', $count, $data['name']);
                                } else { //添加失败
                                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加域名失败');
                                     $infor['error'][] = sprintf('第%s行: %s 添加失败', $count, $data['name']);
                                }
                        }
                    }


                $count++;
            }
            //add_system_record($_SESSION['ADMIN_ID'], 5, 3, '导入广告数据',$path);
        }

        $list = D("Common/Department")->where(array('id_department'=>array('IN',$dep),'type'=>1))->order('id_department ASC')
                ->select();
        $this->assign("list", $list);
        $this->assign('infor', $infor);
        $this->display();
    }

}
