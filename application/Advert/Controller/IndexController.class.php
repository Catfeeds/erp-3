<?php

namespace Advert\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;

class IndexController extends AdminbaseController {

    protected $AdvertData;

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->AdvertData = D("Common/Advert");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }
    
    //总列表
    public function all_list(){
        $count = $this->AdvertData->count();
        $page = $this->page($count, 20);

        $data = $this->AdvertData->join('__USERS__ ON (__USERS__.id=__ADVERT_DATA__.id_users)')
                ->order(array("id_advert_data" => "desc"))
                ->limit($page->firstRow, $page->listRows)
                ->select();
        $this->assign("adverts", $data);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }

    public function index() {
        $count = $this->AdvertData->count();
        $page = $this->page($count, 20);
        
        $dep = $_SESSION['department_id'];
        $map['id_department'] = array('IN',$dep);

        $data = $this->AdvertData->alias('ad')->join('__USERS__ ON (__USERS__.id=__ADVERT_DATA__.id_users)')
                ->where($map)
                ->order(array("id_advert_data" => "desc"))
                ->limit($page->firstRow, $page->listRows)
                ->select();
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看广告数据列表');
        $this->assign("adverts", $data);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }

    /**
     * 添加
     */
    public function add() {
        $id = intval(I("get.id"));
        if ($id == 0) {
            $id = intval(I("post.id"));
        }

        //TODO: 修改只显示广告手用户
//        $role = D('Common/Role');
//        $users = D('Common/Users')
//            ->join('__ROLE_USER__ ON (__ROLE_USER__.user_id=__USERS__.id)')
//            ->join('__ROLE__ ON (__ROLE__.id=__ROLE_USER__.role_id)')
//            ->where($role->getTableName().'.id IN (5,6)')
//            ->select();
        $dep = $_SESSION['department_id'];
        $dep = implode(',', $dep);
        $dep==8 ? '' : ($dep ? $map['id_department'] = array('IN', $dep) : '');
        $list = D("Common/Department")->where($map)->order('id_department ASC')
                ->select();
        
        $users = D('Common/Users')->select();

        $this->assign("list", $list);
        $this->assign('users', $users);
        $this->display();
    }

    /**
     * 添加逻辑
     */
    public function add_post() {

        if (IS_POST) {
            $data = I('post.');
            $data['id_advert'] = 1;
            $data['created_at'] = date('Y-m-d H:i:s');
            if ($this->AdvertData->create($data)) {
                if ($this->AdvertData->add($data)) {
                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加广告数据成功');
                    $this->success("添加成功", U("Index/index"));
                } else {
                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加广告数据失败');
                    $this->error("添加失败！");
                }
            } else {
                $this->error($this->AdvertData->getError());
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

        $users = D('Common/Users')->select();

        $dep = $_SESSION['department_id'];
        $dep = implode(',', $dep);
        $dep==8 ? '' : ($dep ? $map['id_department'] = array('IN', $dep) : '');
        $list = D("Common/Department")->where($map)->order('id_department ASC')
                ->select();

        $dom_table_name = $this->AdvertData->getTableName();
        $dep_table_name = D("Common/Department")->getTableName();
        $data = $this->AdvertData->table($dom_table_name . ' AS d LEFT JOIN ' . $dep_table_name . ' AS u ON d.id_department=u.id_department')->field('d.*,u.id_department,u.title')->where(array("id_advert_data" => $id))->find();

        if (!$data) {
            $this->error("该广告数据不存在！");
        }
        
        $this->assign('users', $users);
        $this->assign("advert", $data);
        $this->assign("list", $list);
        $this->display();
    }

    /**
     * 编辑
     */
    public function edit_post() {
        $id = intval(I("get.id"));
        if ($id == 0) {
            $id = intval(I("post.id_advert_data"));
        }
 
        if (IS_POST) {
            $data = I('post.');
            $data['updated_at'] = date('Y-m-d H:i:s'); 
            if ($this->AdvertData->create($data)) {
                if ($this->AdvertData->save($data) !== false) {
                    add_system_record(sp_get_current_admin_id(), 2, 3, '修改广告数据'.$id.'成功');
                    $this->success("修改成功！", U('Index/index'));
                } else {
                    add_system_record(sp_get_current_admin_id(), 2, 3, '修改广告数据'.$id.'失败');
                    $this->error("修改失败！");
                }
            } else {
                $this->error($this->AdvertData->getError());
            }
        }
    }

    /**
     * 删除
     */
    public function delete() {
        $id = intval(I("get.id"));
        
        $status = $this->AdvertData->delete($id);
        if ($status!==false) {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除广告数据'.$id.'成功');
            $this->success("删除成功！", U('index/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除广告数据'.$id.'失败');
            $this->error("删除失败！");
        }
    }
    
    /**
     * 域名订单列表
     */
    public function domian_order() { 
        ini_set("memory_limit","-1");
        $today = date('Y-m-d 00:00:00');//今天
        $yesday = date('Y-m-d 00:00:00', strtotime('-1 day'));//昨天
        $where = array();
        $dep = $_SESSION['department_id'];
        $user_ids = sp_get_current_admin_id();
        
        $depart_user = M('Order')->field('id_users')->where(array('id_department'=>array('IN',$dep),'id_users'=>array('NEQ','')))->select();
        $depart = M('Department')->where(array('id_users'=>$user_ids))->find();
        $users = M('Users')->field('id')->where(array('superior_user_id'=>$user_ids))->select();
        $user_id = array_column($users, 'id');
        $depart_user_id = array_column($depart_user, 'id_users');
        if($depart) {
            $depart_user_id=array_unique($depart_user_id);
            if(count($dep) > 1) {
                $flag = 4;
                $where['id_department'] = array('IN',$dep);
                if(!empty($depart_user_id)) $uwhere['id'] = array('IN',$depart_user_id);
            } else {
                $flag = 1;//部门负责人            
                $where['id_department'] = array('IN',$dep);
                if(!empty($depart_user_id)) $uwhere['id'] = array('IN',$depart_user_id);
            }
        } else {            
            if($user_id) {
                $flag=2;//组长
                array_push($user_id,$_SESSION['ADMIN_ID']);//把本身用户Id添加进去                
            } else {
                $flag=3;//组员
                $user_id = $_SESSION['ADMIN_ID'];
            }
            $user_id=array_unique($user_id);
            if(!empty($user_id)) $where['id_users'] = array('IN',$user_id);
            if(!empty($user_id)) $uwhere['id'] = array('IN',$user_id);
        }
        
        //选择名称
        if (isset($_GET['user_id']) && $_GET['user_id']) {
            $where['id_users'] = $_GET['user_id'];
        }
        
        //选择域名
        if (isset($_GET['domain_id']) && $_GET['domain_id']) {
            $where['id_domain'] = $_GET['domain_id'];
        }
        
        //选择部门
        if (isset($_GET['department_id']) && $_GET['department_id']) {
            $where['id_department'] = $_GET['department_id'];
        }
        
        //排序
        $sort = isset($_GET['sort']) && $_GET['sort']=='asc'?'asc':'desc';
        $order_by = 'today '.$sort;
        if(isset($_GET['order_by'])){
            switch($_GET['order_by']){
                case 'yesday':
                    $order_by = 'yesday '.$sort;
                    break;
                case 'order_count':
                    $order_by = 'order_count '.$sort;
                    break;
                case 'today':
                    $order_by = 'today '.$sort;
            }
        }


        //组长搜索组员        
        $user_result = M('Users')->field('id,user_nicename')->where($uwhere)->select();
        $user_names = array_column($user_result, 'user_nicename', 'id');
        
        $field = "count(*) as order_count,SUM(IF(created_at >= '".$today."',1,0)) as today,SUM(IF(created_at >= '".$yesday."' and created_at < '".$today."',1,0)) as yesday,id_users,id_domain";
        $where['_string'] = "id_users != ''";
        $order_count = M('Order')->field($field)->where($where)->group('id_domain')->cache(true,600)->select();
        $page = $this->page(count($order_count), 20);
        $order = M('Order')->field($field)->where($where)
//                ->fetchSql()
                ->limit($page->firstRow . ',' . $page->listRows)
                ->group('id_domain')->order($order_by)->cache(true,600)->select();
//        print_r($order);die;
        
        foreach ($order as $k=>$v) {
            $order[$k]['domian'] = M('Domain')->where(array('id_domain'=>$v['id_domain']))->getField('name');
            $order[$k]['name'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
        }

        $department = M('Department')->field('id_department,title')->where(array('id_department'=>array('IN',$dep),'type'=>1))->select();
        $domain = M('Domain')->field('id_domain,name')->where(array('id_department'=>array('IN',$dep)))->order('name ASC')->select();
        $this->assign('order',$order);
        $this->assign("page", $page->show('Admin'));
        $this->assign('user_names',$user_names);
        $this->assign('department',$department);
        $this->assign('flag',$flag);
        $this->assign('domain',$domain);
        $this->display();
    }

    /**
     * 订单列表
     */
    public function all_order() {
        /** @var \Order\Model\OrderModel $order_model */
        $order_model = $this->order;
        $where = $order_model->form_where($_GET,'o.');
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $admin_id = $_SESSION['ADMIN_ID'];
        $where['id_users'] = $admin_id;
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }

        //$where['payment_method'] = array('EQ','0');//广告  下订单列表 可以查看所有订单
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();

        //$formData['product_type'] = $baseSql->getFieldGroupData('product_type');
        $form_data['track_status'] = D('Order/OrderShipping')->field('status_label as track_status')
            ->where("status_label is not null or status_label !='' ")
            ->group('status_label')->cache(true, 12000)->select();


        //今日统计订单 条件
        $today_where = $where;
        $today_where['created_at'] = array('EGT', $today_date);
        $all_domain_total = $order_model->alias('o')->field('count(o.`id_domain`) as total,id_domain')->where($today_where)
            ->order('total desc')->group('o.id_domain')->select();

        //修改过滤物流状态， 当不需要过滤物流状态时，很卡，所以需要判断是否需要过滤物流状态
        if (isset($where['status_label']) && $where['status_label']) {
            $count = $order_model->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.order_id)', 'LEFT')
                ->where($where)->count();
            $today_total = $order_model->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.order_id)', 'LEFT')
                ->where($today_where)->count();
            $page = $this->page($count, $this->page);
            $order_list = $order_model->alias('o')->field('o.*,oi.ip as ip ,s.signed_for_date')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.order_id)', 'LEFT')
                ->join('__ORDER_INFO__ as oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        } else {
            $count = $order_model->alias('o')->where($where)->count();
            $today_total = $order_model->alias('o')->where($today_where)->count();
            $page = $this->page($count, $this->page);
            $order_list = $order_model->alias('o')->field('o.*,oi.ip as ip')->join('__ORDER_INFO__ as oi ON (o.id_order = oi.id_order)', 'LEFT')->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
        }
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true,36000)->select();
        $advertiser = array_column($advertiser,'name','id');
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record($_SESSION['ADMIN_ID'], 4, 4,'查看订单列表');
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $advertiser);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("page", $page->show('Admin'));
        $this->assign("today_total", $today_total);
        $this->assign("order_total", $count);
        $this->assign("all_domain_total", $all_domain_total);

        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("order_list", $order_list);
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $this->assign("all_zone", $all_zone);
        $this->display();
    }
    public function info(){
        $order_id = I('get.id');
        $order = D("Order/Order")->find($order_id);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $orderHistory = D("Order/OrderRecord")
            ->field('*')
            ->join('__USERS__ u ON (__ORDER_RECORD__.id_users = u.id)', 'LEFT')
            ->where(array('id_order'=>$order_id))
            ->order('created_at desc')->select();
        $shipping = D('Common/Shipping')
            ->where(array('id_shipping'=>(int)$order['id_shipping']))->cache(true,3600)
            ->find();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $all_domain = $domain_model->get_all_domain();
        $order['id_domain'] = $all_domain[$order['id_domain']];
        $order['id_order_status'] = $statusLabel[$order['id_order_status']];
        $products = D('Order/OrderItem')->get_item_list($order['id_order']);
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看订单详情');
        $this->assign("order", $order);
        $this->assign("products", $products);
        $this->assign("history", $orderHistory);
        $this->assign("label", $statusLabel);
        $this->assign('shipping_name', $shipping['title']);
        $this->assign('shopping_url', $shipping['track_url']);
        $this->display();
    }

    /**
     * 导出订单列表
     */
    public function export_search() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();

        $column = array(
            '地区', '域名', '订单号', '姓名', '电话号码', '邮箱',
            '产品名和价格', '总价（NTS）', '属性',
            '送货地址', '购买产品数量', '留言备注', '下单时间', '订单状态',
            '发货日期', '运单号', '物流状态'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $where = $this->order->form_where($_GET);
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }

        $orders = $this->order
            ->where($where)
            ->order("id_order ASC")
            ->limit(5000)->select();
        $result = D('Order/OrderStatus')->select();
        $status = array();
        foreach ($result as $statu) {
            $status[(int) $statu['id_order_status']] = $statu;
        }
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        $idx = 2;
        foreach ($orders as $o) {
            $product_name = '';
            $attrs = '';
            $products = $order_item->get_item_list($o['id_order']);
            $product_count = 0;
            foreach ($products as $p) {
                $product_name .= $p['product_title'] . "\n";
                if($p['sku_title']) {
                    $attrs .= $p['sku_title']. ' x ' . $p['quantity'] . ",";
                } else {
                    $attrs .= $p['product_title']. ' x ' . $p['quantity'] . ",";
                }
                $product_count +=$p['quantity'];
            }
            $attrs = trim($attrs, ',');
            $status_name = isset($status[$o['id_order_status']]) ? $status[$o['id_order_status']]['title'] : '未知';
            $domain_title = D('Domain/Domain')->where(array('id_domain'=>$o['id_domain']))->getField('name');
            $getShipObj = D("Order/OrderShipping")->field('track_number,status_label')->where('id_order=' . $o['id_order'])->select();
            $trackNumber = $getShipObj ? implode(',', array_column($getShipObj, 'track_number')) : '';
            $trackStatusLabel = $getShipObj ? implode(',', array_column($getShipObj, 'status_label')) : '';
            $data = array(
                $o['province'], $domain_title, $o['id_increment'], $o['first_name'], $o['tel'], $o['email'],
                $product_name, $o['price_total'], $attrs,
                $o['address'], $product_count, $o['remark'], $o['created_at'], $status_name,
                $o['date_delivery'], ' ' . $trackNumber, $trackStatusLabel
            );
            $j = 65;
            foreach ($data as $col) {
                $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 6, 4, '导出DF订单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '订单信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '订单信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }
	/*
     * 广告列表
     */
    public function all_advert(){
        if(IS_GET){
            if(isset($_GET['id_domain'])&&$_GET['id_domain'])
                $con['a.id_domain'] = array('EQ',$_GET['id_domain']);
            if(isset($_GET['id_users'])&&$_GET['id_users'])
                $con['id_users'] = array('EQ',$_GET['id_users']);
            if ($_GET['start_post_at'] or $_GET['end_post_at']) {
                $conversion_at = array();
                if ($_GET['start_post_at'])
                    $post_at[]= array('EGT',$_GET['start_post_at']);
                if ($_GET['end_post_at'])
                    $post_at[]= array('ELT',$_GET['end_post_at']);
                $con['post_at']= $post_at;
            }
        }
        $current_user = $_SESSION['ADMIN_ID'];;//当前操作者的用户ID
        $department_id = implode(',',$_SESSION['department_id']); //当前操作者所在部门的ID
        $where['id_department'] = array('EQ',$department_id);
        $dep_user = M('department')->where($where)->getField('id_users');//当前部门对应的用户ID
        
        //小主管
        $group['superior_user_id'] = array('EQ',$current_user);
        $group_user = M('Users')->field('id')->where($group)->select();
        if(isset($_GET['id_domain'])&&$_GET['id_domain']){
            $con['a.id_domain'] = array('EQ',$_GET['id_domain']);
        }
        //是部门主管
        $user_list_data = '';
        if($current_user==$dep_user)
        {
            if(empty($_GET['id_users'])){
                $cond['id_department'] = array('IN',$department_id);
                $user_list = M('DepartmentUsers')->field('id_users')->where($cond)->select();
                foreach($user_list as $v){
                    $user_list_data .= $v['id_users'].',';
                }
                $user_list_data = rtrim($user_list_data,',');
                $con['id_users'] = array('IN',$user_list_data);
                $con2['id_users']=array('IN',$user_list_data);
            }else{
                $cond['id_department'] = array('IN',$department_id);
                $user_list = M('DepartmentUsers')->field('id_users')->where($cond)->select();
                foreach($user_list as $v){
                    $user_list_data .= $v['id_users'].',';
                }
                $user_list_data = rtrim($user_list_data,',');
                $con2['id_users'] = array('IN',$user_list_data);
            }
            $count =M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con)->count();
            $page = $this->page($count, 20);
            $adverts = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con)->limit($page->firstRow . ',' . $page->listRows)->select();
//            搜索
            $adverts_search = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con2)->order('name')->select();

        }elseif(!empty($group_user)){
            //是否是组长
            if(empty($_GET['id_users'])){
                foreach($group_user as $v){
                    $user_list_data .= $v['id'].',';
                }
                $user_list_data .= $current_user;
                $con['id_users'] = array('IN',$user_list_data);
                $con2['id_users'] = array('IN',$user_list_data);
            }else{
                foreach($group_user as $v){
                    $user_list_data .= $v['id'].',';
                }
                $user_list_data .= $current_user;
                $con2['id_users'] = array('IN',$user_list_data);
            }

            $count =M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con)->count();
            $page = $this->page($count, 20);
            $adverts = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con)->limit($page->firstRow . ',' . $page->listRows)->select();
            $adverts_search = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con2)->order('name')->select();
        }else if($current_user==1){
            $count =M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con)->count();
            $page = $this->page($count, 20);
            $adverts = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con)->limit($page->firstRow . ',' . $page->listRows)->select();
            $adverts_search = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con2)->order('name')->select();
        }else{
            if(empty($_GET['id_users'])){
                $con['id_users'] = array('EQ',$current_user);
                $con2['id_users'] = array('EQ',$current_user);
            }else{
                $con2['id_users'] = array('EQ',$current_user);
            }
            $count =M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con)->count();
            $page = $this->page($count, 20);
            $adverts = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con)->limit($page->firstRow . ',' . $page->listRows)->select();
            $adverts_search = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')->where($con2)->order('name')->select();
        }

        $users_data = M('Users')->field('id,user_nicename,superior_user_id')->select();
        $users = '';
        foreach($users_data as $v){
            $users[$v['id']] = $v;
        }
        $zones = M('Zone')->getField('id_zone,title',true);

        $this->assign('zones',$zones);
        $adverts_search['users'] = array_unique(array_column($adverts_search,'id_users'));
        $adverts_search['domains'] = array_unique(array_column($adverts_search,'name','id_domain'));
        add_system_record($_SESSION['ADMIN_ID'], 4, 3,'查看广告列表');
        $this->assign('users',$users);
        $this->assign('adverts_search',$adverts_search);
        $this->assign('adverts',$adverts);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }
    /*
  * 费用列表
  */
    public function all_cost(){
//        $previous_day = date("Y-m-d",strtotime("-1 day"));
//        $conversion_at = array();
//        $conversion_at[]= array('EGT',$previous_day);
//        $conversion_at[]= array('ELT',$previous_day);
//        $where['conversion_at']= $conversion_at;
        $zones = M('Zone')->getField('id_zone,title',true);
        if(IS_GET){
            if(isset($_GET['domain'])&&$_GET['domain'])
                $where['a.id_domain'] =M('Domain')->where(['name'=>['LIKE',$_GET['domain']]])->getField('id_domain');
                //$where['a.id_domain'] = array('EQ',$_GET['id_domain']);
            if(isset($_GET['advert_name'])&&$_GET['advert_name'])
                $where['advert_name'] = array('LIKE',$_GET['advert_name']);
            if(isset($_GET['zones'])&&$_GET['zones'])
                $where['z.id_zone'] = array('EQ',$_GET['zones']);

            if ($_GET['start_conversion_at'] or $_GET['end_conversion_at']) {
                $conversion_at = array();
                if ($_GET['start_conversion_at'])
                    $conversion_at[]= array('EGT',$_GET['start_conversion_at']);
                if ($_GET['end_conversion_at'])
                    $conversion_at[]= array('ELT',$_GET['end_conversion_at']);
                $where['conversion_at']= $conversion_at;
            }
        }
        $current_user = $_SESSION['ADMIN_ID'];
        $department_id = implode(',',$_SESSION['department_id']);
        $dep_where['id_department'] = array('IN',$department_id);
        $dep_where['parent_id'] = array("eq",0);
        $dep_user = M('department')->where($dep_where)->field('id_users')->select();
        $dep_user = $dep_user[0]['id_users'];
        //小主管
        $group_where['superior_user_id'] = array('EQ',$current_user);
        $group_user = M('Users')->field('id')->where($group_where)->select();
        //是部门主管
        $user_list_data = ''; 
        if($current_user==$dep_user || $current_user==9 || $current_user==47)
        {
            $cond['id_department'] = array('IN',$department_id);
            $user_list = M('DepartmentUsers')->field('id_users')->where($cond)->select();
            foreach($user_list as $v){
                $user_list_data .= $v['id_users'].',';
            }
            $user_list_data = rtrim($user_list_data,',');
            if(isset($_GET['id_users'])&&$_GET['id_users']) {
                $where['ad.id_users_today'] = array('EQ',$_GET['id_users']);
                $where2['ad.id_users_today'] = array('IN',$user_list_data);
            }
            else{
                $where['ad.id_users_today'] = array('IN',$user_list_data);
                $where2['ad.id_users_today'] = array('IN',$user_list_data);
            }
            $count =M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*')
                ->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->count();
            $page = $this->page($count, 20);
            $costs = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*,z.title')
                ->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->limit($page->firstRow, $page->listRows)->select();

            //筛选
            $costs_search = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*')
                ->alias('a')
                ->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')->where($where2)->order('name')->select();

        }elseif(!empty($group_user) && $current_user!=1 && $current_user!=9 && $current_user!=47){
            //是否是组长
            foreach($group_user as $v){
                $user_list_data .= $v['id'].',';
            }
            $user_list_data .= $current_user;
            if(isset($_GET['id_users'])&&$_GET['id_users']) {
                $where['ad.id_users_today'] = array('EQ',$_GET['id_users']);
                $where2['ad.id_users_today'] = array('IN',$user_list_data);
            }
            else {
                $where['ad.id_users_today'] = array('IN',$user_list_data);
                $where2['ad.id_users_today'] = array('IN',$user_list_data);
            }
            $count = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*')
                ->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->count();
            $page = $this->page($count, 20);

            $costs = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*,z.title')
                ->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->limit($page->firstRow, $page->listRows)->select();
            //筛选
            $costs_search = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*')
                ->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')
                ->alias('a')->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')->where($where2)->order('name')->select();

        }else if($current_user==1){
            if(isset($_GET['id_users'])&&$_GET['id_users']) {
                $where['ad.id_users_today'] = array('EQ',$_GET['id_users']);
             }
               $count = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*')
                ->alias('a')->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->count();
            $page = $this->page($count, 20);
            $costs = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*,z.title')->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->limit($page->firstRow, $page->listRows)->select();
//           筛选
            $costs_search = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*')
                ->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')
                ->alias('a')->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')->where($where2)->order('name')->select();
            }else{
             if(isset($_GET['id_users'])&&$_GET['id_users']) {
                $where['ad.id_users_today'] = array('EQ',$_GET['id_users']);
                $where2['ad.id_users_today'] = array('EQ',$current_user);
            }
            else {
                $where['ad.id_users_today'] = array('EQ',$current_user);
                $where2['ad.id_users_today'] = array('EQ',$current_user);
            } 
            $count = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*')
                ->alias('a')->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->count();
            $page = $this->page($count, 20);
            $costs = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*,z.title')->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->limit($page->firstRow, $page->listRows)->select();
//           筛选
            $costs_search = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*')
                ->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')
                ->alias('a')->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')->where($where2)->order('name')->select();


        }
        $datas['users'] = array_unique(array_column($costs_search,'id_users'));
        $datas['domains'] = array_unique(array_column($costs_search,'id_domain'));
        $datas['advert_name'] = array_unique(array_column($costs_search,'advert_name'));

//        asort($datas['advert_name'],SORT_STRING);
        $users = '';
        $users_data = M('Users')->field('id,user_nicename,superior_user_id')->select();
        foreach($users_data as $v){
            $users[$v['id']] = $v;
        }

        $user_list_data = explode(',',$user_list_data);
        $department_id = $_SESSION['department_id'];
        $department_id = implode(',',$department_id);
        $where['id_department'] = array('IN',$department_id);
        $department = D('Domain/Domain')->where(array('status'=>1))->select();
        $departments = array_column($department,'name','id_domain');
        add_system_record($_SESSION['ADMIN_ID'], 4, 3,'查看费用列表');
        $this->assign('datas',$datas);
        $this->assign('user_list_data',$user_list_data);
        $this->assign('users',$users);
        $this->assign('departments',$departments);

        $this->assign('costs',$costs);
        $this->assign("Page", $page->show('Admin'));
        $this->assign('zones',$zones);
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }

    public function export_cost(){
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '转化日期', '姓名', '域名','广告名', '链接', '地区', '总费用','类型'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

        $zones = M('Zone')->getField('id_zone,title',true);
        if(IS_GET){
            if(isset($_GET['domain'])&&$_GET['domain'])
                $where['a.id_domain'] =M('Domain')->where(['name'=>['LIKE',$_GET['domain']]])->getField('id_domain');
            if(isset($_GET['advert_name'])&&$_GET['advert_name'])
                $where['advert_name'] = array('LIKE',$_GET['advert_name']);
            if(isset($_GET['zones'])&&$_GET['zones'])
                $where['z.id_zone'] = array('EQ',$_GET['zones']);

            if ($_GET['start_conversion_at'] or $_GET['end_conversion_at']) {
                $conversion_at = array();
                if ($_GET['start_conversion_at'])
                    $conversion_at[]= array('EGT',$_GET['start_conversion_at']);
                if ($_GET['end_conversion_at'])
                    $conversion_at[]= array('ELT',$_GET['end_conversion_at']);
                $where['conversion_at']= $conversion_at;
            }
        }
        $current_user = $_SESSION['ADMIN_ID'];
        $department_id = implode(',',$_SESSION['department_id']);
        $dep_where['id_department'] = array('IN',$department_id);
        $dep_where['parent_id'] = array("eq",0);
        $dep_user = M('department')->where($dep_where)->field('id_users')->select();
        $dep_user = $dep_user[0]['id_users'];
        //小主管
        $group_where['superior_user_id'] = array('EQ',$current_user);
        $group_user = M('Users')->field('id')->where($group_where)->select();
        //是部门主管
        $user_list_data = '';
        if($current_user==$dep_user  || $current_user==9 || $current_user==47)
        {
            $cond['id_department'] = array('IN',$department_id);
            $user_list = M('DepartmentUsers')->field('id_users')->where($cond)->select();
            foreach($user_list as $v){
                $user_list_data .= $v['id_users'].',';
            }
            $user_list_data = rtrim($user_list_data,',');
            if(isset($_GET['id_users'])&&$_GET['id_users']) {
                $where['ad.id_users_today'] = array('EQ',$_GET['id_users']);
                $where2['ad.id_users_today'] = array('IN',$user_list_data);
            }
            else{
                $where['ad.id_users_today'] = array('IN',$user_list_data);
                $where2['ad.id_users_today'] = array('IN',$user_list_data);
            }
            $costs = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*,z.title')
                ->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->select();
        }elseif(!empty($group_user) && $current_user!=1 && $current_user!=9 && $current_user!=47){
            //是否是组长
            foreach($group_user as $v){
                $user_list_data .= $v['id'].',';
            }
            $user_list_data .= $current_user;
            if(isset($_GET['id_users'])&&$_GET['id_users']) {
                $where['ad.id_users_today'] = array('EQ',$_GET['id_users']);
                $where2['ad.id_users_today'] = array('IN',$user_list_data);
            }
            else {
                $where['ad.id_users_today'] = array('IN',$user_list_data);
                $where2['ad.id_users_today'] = array('IN',$user_list_data);
            }

            $costs = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*,z.title')
                ->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->select();

        }else if($current_user==1){
            if(isset($_GET['id_users'])&&$_GET['id_users']) {
                $where['ad.id_users_today'] = array('EQ',$_GET['id_users']);
                $where2['ad.id_users_today'] = array('EQ',$current_user);
            }
             $costs = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*,z.title')->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->select();
        }else{

            if(isset($_GET['id_users'])&&$_GET['id_users']) {
                $where['ad.id_users_today'] = array('EQ',$_GET['id_users']);
                $where2['ad.id_users_today'] = array('EQ',$current_user);
            }
            else {
                $where['ad.id_users_today'] = array('EQ',$current_user);
                $where2['ad.id_users_today'] = array('EQ',$current_user);
            }

            $costs = M('Advert')->field('a.id_users,a.id_domain,a.advert_name,a.url,ad.*,z.title')->alias('a')
                ->join('__ADVERT_DATA__ as ad on ad.advert_id = a.advert_id','LEFT')
                ->join('__ZONE__ as z on ad.id_zone = z.id_zone','LEFT')
                ->where($where)->order('conversion_at DESC')->select();



        }
        
        $users = '';
        $users_data = M('Users')->field('id,user_nicename,superior_user_id')->select();
        foreach($users_data as $v){
            $users[$v['id']] = $v;
        }

        $user_list_data = explode(',',$user_list_data);
        $department_id = $_SESSION['department_id'];
        $department_id = implode(',',$department_id);
        $where['id_department'] = array('IN',$department_id);
        $department = D('Domain/Domain')->where(array('status'=>1))->select();
        $departments = array_column($department,'name','id_domain');
        if(!empty($costs)){
            foreach($costs as $k =>$cost){
                $data[] = array(
                    $cost['conversion_at'],$users[$cost['id_users_today']]['user_nicename']?$users[$cost['id_users_today']]['user_nicename']:$users[$cost['id_users']]['user_nicename'],$departments[$cost['id_domain']], $cost['advert_name'], $cost['url'], $cost['title'], $cost['expense'],$cost['type']
                );
            }
        }
        if ($data) {
            foreach ($data as $items) {
                $j = 65;
                foreach ($items as $col) {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                    ++$j;
                }
                ++$idx;
            }
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出费用列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '费用列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '费用列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();

    }

    /*
    * 添加广告
    */
    public function add_advert(){
        $department_id = $_SESSION['department_id'];
        $department_id = implode(',',$department_id);
        $add_user = $_SESSION['ADMIN_ID'];
        $where['id_department'] = array('IN',$department_id);
        $department = D('Domain/Domain')->where($where)->order('name')->select();
        $departments = array_column($department,'name','id_domain');
        $users_data = M('Users')->alias('u')->field('id,user_nicename')
            ->join('__DEPARTMENT_USERS__ as du on du.id_users = u.id','LEFT')
            ->join('__ROLE_USER__ as ru on ru.user_id = u.id','LEFT')
            ->where(array('id_department'=>array('IN',$department_id),'ru.role_id'=>array('IN','28,29,30')))->select();
        $users_data = array_column($users_data,'user_nicename','id');
        if(IS_POST){
            $id_domain = M('Advert')->where(array('id_domain'=>$_POST['id_domain']))->getField('id_users');
            $user = $users_data[$id_domain];
            if($id_domain){
                $this->error("添加失败！".$user."已有这个广告", U('index/add_advert', array('advert_id' =>$res)));
                die;
            }
            $_POST['created_at'] = date('Y-m-d H:i:s');
            $_POST['add_user'] = $add_user;
            $res =  M('Advert')->add($_POST);
            if ($res == false) {
                add_system_record($_SESSION['ADMIN_ID'], 1, 3,'添加广告失败');
                $this->error("保存失败！", U('index/add_advert', array('advert_id' =>$res)));
            }
            else{
                add_system_record($_SESSION['ADMIN_ID'], 1, 3,'添加广告成功');
                $this->success("保存完成！", U('index/all_advert'));
            }

        }
        if(IS_GET){
            $where['advert_id'] = $_GET['advert_id'];
            $advert = M('Advert')->where($where)->find();
            $this->assign('advert',$advert);
        }
        $this->assign('users',$users_data);
        $this->assign('departments',$departments);
        $this->display();
    }
    /*
    * 添加数据
    */
    public function add_data(){
        if(IS_GET){
            if($_GET['advert_id']){
                $where['advert_id'] = array('EQ',$_GET['advert_id']);
                $advert = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain')
                    ->where($where)->find();
                $this->assign('advert',$advert);
            }
            if($_GET['id_advert_data']!=''){
                $where['id_advert_data'] = array('EQ',$_GET['id_advert_data']);
                $advert = M('Advert')->alias('a')->field('a.*,d.name,ad.*')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')
                                     ->join('__ADVERT_DATA__ as ad on a.advert_id = ad.advert_id')
                                     ->where($where)->find();
                $this->assign('advert',$advert);

            }


        }
        if(IS_POST){
            if($_POST['id_advert_data']==''){
                $add['advert_id'] = $_POST['advert_id'];
                $add['conversion'] = $_POST['conversion'];
                $add['cost'] = $_POST['cost'];
                $add['expense'] = $_POST['expense'];
                $add['cmp'] = $_POST['cmp'];
                $add['ctr'] = $_POST['ctr'];
                $add['created_at'] = date('Y-m-d H:i:s');
                $add['conversion_at'] = $_POST['conversion_at'];
                $res = M('AdvertData')->add($add);
                if ($res == false) {
                    add_system_record($_SESSION['ADMIN_ID'], 1, 3,'添加广告数据失败');
                    $this->error("保存失败！", U('index/add_data', array('id_advert_data' =>$res)));
                }else
                {
                    add_system_record($_SESSION['ADMIN_ID'], 1, 3,'添加广告数据成功');
                    $this->success("保存完成！", U('index/add_data',array('id_advert_data' =>$res)));
                }

            }
            else{
                $update['id_advert_data'] = $_POST['id_advert_data'];
                $update['advert_id'] = $_POST['advert_id'];
                $update['conversion'] = $_POST['conversion'];
                $update['cost'] = $_POST['cost'];
                $update['expense'] = $_POST['expense'];
                $update['cmp'] = $_POST['cmp'];
                $update['ctr'] = $_POST['ctr'];
                $update['update_at'] = date('Y-m-d H:i:s');
                $update['conversion_at'] = $_POST['conversion_at'];
                $res = M('AdvertData')->save($update);
//                var_dump($res);die;
                if ($res == false) {
                    add_system_record($_SESSION['ADMIN_ID'], 2, 3,'修改广告数据失败');
                    $this->error("修改失败！", U('index/add_data', array('id_advert_data' =>$_POST['id_advert_data'])));
                }else{
                    add_system_record($_SESSION['ADMIN_ID'], 2, 3,'修改广告数据成功');
                    $this->success("修改完成！", U('index/add_data',array('id_advert_data' =>$_POST['id_advert_data'])));
                }


            }

        }
        $this->display();
    }
    /*
     * 编辑广告
     */
    public function edit_advert(){
        $department_id = $_SESSION['department_id'];
        $department_id = implode(',',$department_id);
        $where['id_department'] = array('IN',$department_id);
        $users_data = M('Users')->alias('u')->field('id,user_nicename')
            ->join('__DEPARTMENT_USERS__ as du on du.id_users = u.id','LEFT')
            ->join('__ROLE_USER__ as ru on ru.user_id = u.id','LEFT')
            ->where(array('id_department'=>array('IN',$department_id),'ru.role_id'=>array('IN','28,29,30')))->select();
        $users_data = array_column($users_data,'user_nicename','id');
        if(IS_GET){
            $where = array();
            $where['advert_id'] = array('EQ',$_GET['advert_id']);
            $advert = M('Advert')->alias('a')->field('a.*,d.name')->join('__DOMAIN__ as d on a.id_domain = d.id_domain')
                                  ->where($where)->find();
            $this->assign('advert',$advert);
            $zones = M('Zone')->getField('id_zone,title',true);
            $this->assign('zones',$zones);
        }
        if(IS_POST){
            $update = $_POST;
            $where['advert_id']= $_POST['advert_id'];
            $update['update_at'] = date('Y-m-d H:i:s');
            $res = M('Advert')->where($where)->save($update);
            if ($res == false) {
                add_system_record($_SESSION['ADMIN_ID'], 2, 3,'编辑广告失败');
                $this->error("保存失败！", U('index/edit_advert', array('advert_id' => $_POST['advert_id'])));
            }
            else{
                add_system_record($_SESSION['ADMIN_ID'], 2, 3,'编辑广告成功');
                $this->success("保存完成！", U('index/all_advert', array('advert_id' => $_POST['advert_id'])));
            }

//            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '编辑广告');
        }
        $this->assign('users',$users_data);
        $this->display();
    }

    /*
     * 添加费用
     */
    public function add_cost(){
//        $previous_day = date("Y-m-d",strtotime("-1 day"));
//        $conversion_at = array();
//        $post_at[]= array('EGT',$previous_day);
//        $post_at[]= array('ELT',$previous_day);
//        $where['post_at']= $post_at;
//        if ($_GET['start_time'] or $_GET['end_time']) {
//            $post_at = array();
//            if ($_GET['start_time'])
//                $post_at[]= array('EGT',$_GET['start_time']);
//            if ($_GET['end_time'])
//                $post_at[]= array('ELT',$_GET['end_time']);
//            $where['post_at']= $post_at;
//        }
        $zones = M('Zone')->getField('id_zone,title',true);
        if (isset($_GET['id_user'])&& $_GET['id_user']) {
            $where['id_users']= I('get.id_user');
        }
        else{
            $where['id_users']= $_SESSION['ADMIN_ID'];
        }
        if (isset($_GET['id_domain'])&& $_GET['id_domain']) {
            $where['id_domain']= I('get.id_domain');
        }
        $department_id = $_SESSION['department_id'];
        $department_id = implode(',',$department_id);
        $where['id_department'] = array('IN',$department_id);
        $where_dimain['id_department'] = array('IN',$department_id);
        $domain = M('Advert')->alias('a')->field('a.id_domain,name')->join('__DOMAIN__ d on d.id_domain = a.id_domain','LEFT')->where($where_dimain)->order('name')->select();
        $domain = array_column($domain,'name','id_domain');
        $res = 0;
        if(IS_POST){
            $id_zone = $_POST['zone_temp'];
            foreach($_POST['data'] as $v){
                if(empty($v['conversion_at'])||empty($v['expense'])){
                   unset($v);continue;
                }
                $v['id_users'] = $_SESSION['ADMIN_ID'];
                empty($v['cmp'])?$v['cmp']=null:$v['cmp'];
                empty($v['ctr'])?$v['ctr']=null:$v['ctr'];
//                empty($v['conversion'])?$v['conversion']= null:$v['conversion'];
                $v['add_user'] = $_SESSION['ADMIN_ID'];
                $find = M('AdvertData')->where(array('advert_id'=>$v['advert_id'],'conversion_at'=>$v['conversion_at'],'id_zone'=>$id_zone))->getField('id_advert_data');
                $v['id_users_today'] =$where['id_users'];
                if($find){
                    $v['update_at'] = date('Y-m-d H:i:s');
                    $v['id_zone'] = $id_zone;
                    $v['id_advert_data'] = $find;
                  $res = M('AdvertData')->save($v);
                }else{
                    $v['id_zone'] = $id_zone;
                    $v['created_at'] = date('Y-m-d H:i:s');
                    $res =  M('AdvertData')->add($v);
                }

            }
            if ($res == false) {
                add_system_record($_SESSION['ADMIN_ID'], 1, 3,'添加费用失败');
                $this->error("添加失败", U('index/add_cost'));
            }else
            {
                add_system_record($_SESSION['ADMIN_ID'], 1, 3,'添加费用成功');
                $this->success("添加完成！", U('index/all_cost'));
            }

        }
        $users_data = M('Users')->alias('u')->field('id,user_nicename')
            ->join('__DEPARTMENT_USERS__ as du on du.id_users = u.id','LEFT')
            ->join('__ROLE_USER__ as ru on ru.user_id = u.id','LEFT')
            ->where(array('id_department'=>array('IN',$department_id),'ru.role_id'=>array('IN','28,29,30')))->select();
        $users_data = array_column($users_data,'user_nicename','id');
        $count =M('Advert')->where($where)->count();
        $page = $this->page($count, 20);
        $adverts =  M('Advert')->where($where)->limit($page->firstRow, $page->listRows)->where(array('advert_status'=> '1' ))->select();
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign('users',$users_data);
        $this->assign('domain',$domain);
        $this->assign('adverts',$adverts);
        $this->assign('zones',$zones);
        $this->display();
    }
//    public function add_users_today()
//    {
//        $where['ad.id_users_today']=array('exp', 'IS NULL');
//        $advert = M('AdvertData')->alias('ad')->field('ad.id_advert_data,a.id_users,ad.id_users_today')
//            ->join('__ADVERT__ as a on a.advert_id = ad.advert_id')
//            ->where($where)->select();
//        foreach($advert as $v){
//            $id_advert_data=intval($v['id_advert_data']);
//            $id_users_today = intval($v['id_users']);
//            $sql="UPDATE `erp_advert_data` SET `id_users_today` = ".$id_users_today." WHERE `id_advert_data` = ".$id_advert_data.";";
//            $res = M()->execute($sql);
//           // $res = M('AdvertData')->where($where2)->data($update)->save();
//            var_dump($res) ;
//        }
//        $advert2 = M('AdvertData')->alias('ad')->field('ad.id_advert_data,a.id_users,ad.id_users_today')
//            ->join('__ADVERT__ as a on a.advert_id = ad.advert_id')
//            ->select();
//        var_dump($advert2) ;
//        var_dump($advert);die;
//    }
    public function edit_advert_data()
    {
        if(IS_GET){
            $where['id_advert_data'] = array('EQ',$_GET['id_advert_data']);
            $advert = M('Advert')->alias('a')->field('a.*,d.name,ad.*')->join('__DOMAIN__ as d on a.id_domain = d.id_domain','LEFT')
                ->join('__ADVERT_DATA__ as ad on a.advert_id = ad.advert_id')
                ->where($where)->find();
            //var_dump($advert);
            $zones = M('Zone')->getField('id_zone,title',true);
            $this->assign('zones',$zones);
            $this->assign('advert',$advert);

        }
        if(IS_POST){
            $update['id_advert_data'] = $_POST['id_advert_data'];
            $update['advert_id'] = $_POST['advert_id'];
            $update['conversion'] = $_POST['conversion'];
            $update['cost'] = $_POST['cost'];

            $where_advert['url'] = $_POST['url'];
            $getAdvert = M("Advert")->where($where_advert)->find();
            if($getAdvert['advert_id']){

                $update['advert_id'] = $getAdvert['advert_id'];
                $update['expense'] = $_POST['expense'];
                $update['cmp'] = $_POST['cmp'];
                $update['ctr'] = $_POST['ctr'];
                $update['update_at'] = date('Y-m-d H:i:s');
                $update['conversion_at'] = $_POST['conversion_at'];
                $update['id_zone'] = $getAdvert['id_zone'];
                $update['type'] = $_POST['type'];
                $res = M('AdvertData')->save($update);
//                var_dump($res);die;
                if ($res == false) {
                    add_system_record($_SESSION['ADMIN_ID'], 2, 3,'编辑广告数据失败');
                    $this->error("修改失败！", U('index/edit_advert_data', array('id_advert_data' =>$_POST['id_advert_data'])));
                }else{
                    add_system_record($_SESSION['ADMIN_ID'], 2, 3,'编辑广告数据成功');
                    $this->success("修改完成！", U('index/all_cost'));
                }
            }else{
                add_system_record($_SESSION['ADMIN_ID'], 2, 3,'编辑广告数据失败');
                $this->error("修改失败！", U('index/edit_advert_data', array('id_advert_data' =>$_POST['id_advert_data'])));
            }



        }

        $this->display();
    }

//    /*
//     * 根据user查找对应的广告
//     */
//    public function select_by_user(){
//        if(isset($_GET['id_users'])&&$_GET['id_users']){
//            $id_users = $_GET['id_users'];
//            $advert_list = M('Advert')->alias('a')->field('d.id_domain,name')->join('__DOMAIN__ as d on d.id_domain = a.id_domain')->where(array('id_users'=>$id_users))->select();
//            if($advert_list){
//                echo json_encode($advert_list);
//            }else{
//                $flag = 1;
//                $msg = '该广告手没有广告！';
//                echo json_encode(array('flag'=>$flag,'msg'=>$msg));
//            }
//        }
//    }
}
