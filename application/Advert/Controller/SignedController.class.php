<?php

namespace Advert\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;
use Order\Lib\OrderStatus;

class SignedController extends AdminbaseController {

    protected $AdvertData;

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->AdvertData = D("Common/AdvertData");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }
    protected function get_ref_sign(){
        $sort = isset($_GET['sort']) && $_GET['sort']=='asc'?'asc':'desc';
        $order_by = 'o.id_domain '.$sort;
        if(isset($_REQUEST['order_by'])){
            switch($_REQUEST['order_by']){
                case 'total':
                    $order_by = 'total '.$sort;
                    break;
                case 'effective':
                    $order_by = 'effective '.$sort;
                    break;
                case 'refused_to_sign':
                    $order_by = 'refused_to_sign '.$sort;
                    break;
                case 'delivery':
                    $order_by = 'delivery '.$sort;
                    break;
                case 'smooth_delivery':
                    $order_by = 'smooth_delivery '.$sort;
                    break;
                case 'rejection_rate':
                    $order_by = 'rejection_rate '.$sort;
                    break;
                default:
                    $order_by = 'o.id_domain desc';
            }
        }
        $where = array();
        $department_id = $_SESSION['department_id'];
        $user_id       = $_SESSION['ADMIN_ID'];
        $all_user      = D("Common/Users")->field('id,user_nicename')->cache(true,3600)->select();
        $subordinate =  D("Common/Users")->get_all_subordinate($user_id);
        $all_domain    = D("Common/Domain")->field('id_domain,name')->cache(true,3600)->select();
        $department    = D("Common/Department")->where(array('id_users'=>$user_id))->cache(true,3600)->find();
        if($department or $user_id==1){
            $where['o.id_department'] = array('IN',$department_id);
        }else{
            if($subordinate){
                array_push($subordinate,$user_id);
                rsort($subordinate);
            }
            $id_users      = $subordinate?:$user_id;
            $where['o.id_users'] = is_array($id_users) && count($id_users)>1?array('IN',$id_users):$id_users;
        }

        if (isset($_GET['id_department']) && $_GET['id_department']) {//搜索部门编号
            $where['o.id_department'] = $_GET['id_department'];
        }

        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
            $create_at = array();
            if ($_GET['start_time']) $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
            $where['o.created_at']     = $create_at;
            //$total_where = array('o.created_at'=>$create_at);
        }
        if(isset($_GET['user_name']) && $_GET['user_name']){
            $user_name   = I('request.user_name/s','', array('trim', 'htmlspecialchars'));
            $get_id_user = D("Common/Users")->where(array('user_nicename'=>array('like','%'.$user_name.'%')))->getField('id',true);
            if($get_id_user){
                $where['o.id_users'] = array('IN',$get_id_user);
            }else{
                $where['o.id_users'] = array('EQ', false);
            }
        }
        if(isset($_GET['domain']) && $_GET['domain']){
            $get_domain = I('request.domain/s','', array('trim', 'htmlspecialchars'));
            $id_domain = D("Common/domain")->where(array('name'=>array('like','%'.$get_domain.'%')))->getField('id_domain',true);
            if(!empty($id_domain)){
                $where['o.id_domain']     = array('IN',$id_domain);
            }else{
                $where['o.id_domain'] = array('EQ', false);
            }
        }
        $field_str     = "count(o.id_order) as total,SUM(IF(o.`id_order_status` IN(2,3,4,5,6,7,8,9,10,16,17),1,0)) as effective,o.id_users,o.id_domain";
        $field_str    .= ",SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0)) AS refused_to_sign";
        $field_str    .= ",SUM(IF(os.track_number!='',1,0)) as delivery";
        $field_str    .= ",SUM(IF(os.status_label='順利送達' and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='順利送達',1,0)) as smooth_delivery";
        $field_str    .= ",(SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0)))/(SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0))+SUM(IF(os.status_label='順利送達' and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='順利送達',1,0))) as rejection_rate";
        //$count_order   = D("Order/Order")->field($field_str)->group('id_domain')->select();
        /* @var $ordModel \Order\Model\OrderModel */
        $ord_model = D("Order/Order");
        $M = new \Think\Model;
        $ord_name = $ord_model->getTableName();
        /** @var  $ordShipping \Common\Model\OrderShippingModel */
        $ord_shipping = D("Order/OrderShipping");
        $ord_Shi_name = $ord_shipping->getTableName();
        $count_sql = $M->table($ord_name . ' AS o ')
            ->field("count(o.id_domain)")
            ->where($where)->group('o.id_domain, o.id_users')->select(false);
        $count = $M->table('('.$count_sql.') AS T')->cache(true, 3600)->count();
        $page = $this->page($count, 20);
        $list = $M->table($ord_name . ' AS o LEFT JOIN ' . $ord_Shi_name . ' AS os ON o.id_order=os.id_order')
            ->field($field_str)->where($where)->group('o.id_domain, o.id_users')->order($order_by)
            ->limit($page->firstRow, $page->listRows)
            ->cache(true,600)->select();
        foreach($list as $key=>$item){
            $id_domain = $item['id_domain'];
            $product_data = $ord_model->alias('o')
                ->join('__ORDER_ITEM__ OI ON (o.id_order = OI.id_order)', 'LEFT')
                ->where(array('o.id_domain'=>$id_domain))->order('o.id_order desc')->cache(true,3600)->find();
            $list[$key]['advert_name'] = $product_data['product_title']?$product_data['product_title']:'<span style="color:red">没有找到对应名字</span>';
        }
        return array(
            'list'=>$list,
            'all_user'=>array_column($all_user,'user_nicename','id'),
            'all_domain'=>array_column($all_domain,'name','id_domain'),
            'page'=>$page->show('Admin')
        );
    }
    /**
     * 部门拒签率
     */
    public function depart_ref_sign(){
        $data = $this->get_ref_sign();

        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $department_id  = $_SESSION['department_id'];
        $this->assign('order_by',$_REQUEST['order_by']);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign('list',$data['list']);
        $this->assign('all_user',$data['all_user']);
        $this->assign('all_domain',$data['all_domain']);
        $this->assign("page",$data['page']);
        $this->display();
    }
    /**
     * 产品签收率
     */
    public function product_sign(){
        $sort = isset($_GET['sort']) && $_GET['sort']=='asc'?'asc':'desc';
        $order_by = 'o.id_domain '.$sort;
        if(isset($_REQUEST['order_by'])){
            switch($_REQUEST['order_by']){
                case 'total':
                    $order_by = 'total '.$sort;
                    break;
                case 'effective':
                    $order_by = 'effective '.$sort;
                    break;
                case 'refused_to_sign':
                    $order_by = 'refused_to_sign '.$sort;
                    break;
                case 'delivery':
                    $order_by = 'delivery '.$sort;
                    break;
                case 'smooth_delivery':
                    $order_by = 'smooth_delivery '.$sort;
                    break;
                case 'rejection_rate':
                    $order_by = 'rejection_rate '.$sort;
                    break;
                default:
                    $order_by = 'o.id_domain desc';
            }
        }
        $where = array();
        $department_id = $_SESSION['department_id'];
        $user_id       = $_SESSION['ADMIN_ID'];
        $all_user      = D("Common/Users")->field('id,user_nicename')->cache(true,3600)->select();
        $subordinate =  D("Common/Users")->get_all_subordinate($user_id);
        $all_domain    = D("Common/Domain")->field('id_domain,name')->cache(true,3600)->select();
        $department    = D("Common/Department")->where(array('id_users'=>$user_id))->cache(true,3600)->find();
        if($department or $user_id==1){
            $where['o.id_department'] = array('IN',$department_id);
        }else{
            if($subordinate){
                array_push($subordinate,$user_id);
                rsort($subordinate);
            }
            $id_users      = $subordinate?:$user_id;
            $role_user = M('RoleUser')->field('role_id')->where(array('user_id'=>$user_id))->find();
            if(in_array($role_user['role_id'],array(28,29,30))) {
                $where['o.id_users'] = is_array($id_users) && count($id_users)>1?array('IN',$id_users):$id_users;
            }
            //$where['o.id_users'] = is_array($id_users) && count($id_users)>1?array('IN',$id_users):$id_users;
        }

        if (isset($_GET['id_department']) && $_GET['id_department']) {//搜索部门编号
            $where['o.id_department'] = $_GET['id_department'];
        }

        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
            $create_at = array();
            if ($_GET['start_time']) $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
            $where['o.created_at']     = $create_at;
            //$total_where = array('o.created_at'=>$create_at);
        }
        if (isset($_GET['start_date_delivery']) && $_GET['start_date_delivery']) {//搜索物流运单号表的订单
            $date_delivery = array();
            if ($_GET['start_date_delivery']) $date_delivery[] = array('EGT', $_GET['start_date_delivery']);
            if ($_GET['end_date_delivery']) $date_delivery[] = array('LT', $_GET['end_date_delivery']);
            $where['o.date_delivery']     = $date_delivery;
            //$total_where = array('o.created_at'=>$create_at);
        }

        if(isset($_GET['title']) && $_GET['title']){
            //$where['p.title']     = $_GET['title'];
            $where['p.title']=array('like','%'.$_GET['title'].'%');

        }
        //是否设置id_user   --Lily  2017-10-25
        if(isset($_GET['id_users']) && $_GET['id_users']){
            $where['o.id_users']=$_GET['id_users'];
        }
        if($_GET['id_shipping']){
            $where['os.id_shipping']     = $_GET['id_shipping'];
        }
        if(isset($_GET['inner_name']) && $_GET['inner_name']){
            //$where['p.inner_name']     = $_GET['inner_name'];
            $where['p.inner_name']=  array('like','%'.$_GET['inner_name'].'%');
        }
        if(isset($_GET['sku']) && $_GET['sku']){
            $where['p.model']     = $_GET['sku'];
        }
        $field_str     = "count(o.id_order) as total,SUM(IF(o.`id_order_status` IN(2,3,4,5,6,7,8,9,10,16,17),1,0)) as effective,p.title,p.inner_name,p.model";
        $field_str    .= ",SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0)) AS refused_to_sign";
        $field_str    .= ",SUM(IF(os.track_number!='',1,0)) as delivery";
        $field_str    .= ",SUM(IF(os.status_label='順利送達' and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='順利送達',1,0)) as smooth_delivery";
        $field_str    .= ",(SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0)))/(SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0))+SUM(IF(os.status_label='順利送達' and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='順利送達',1,0))) as rejection_rate";
        $ord_model = D("Order/Order");
        $M = new \Think\Model;
        $ord_name = $ord_model->getTableName();
        /** @var  $ordShipping \Common\Model\OrderShippingModel */
        $ord_shipping = D("Order/OrderShipping");
        $ord_Shi_name = $ord_shipping->getTableName();
        $username = M("Users")->alias("u")->join("__ROLE_USER__ AS ru ON ru.user_id=u.id","LEFT")->join("__ROLE__ AS r ON r.id=ru.role_id","LEFT")->field("distinct u.user_nicename,u.id")->where("r.id=29 AND u.user_status=1")->select();
        //dump($username);
        $count = M('Order')->alias('o')
            ->join("__ORDER_ITEM__ AS oi ON oi.id_order=o.id_order", "LEFT")
            ->join("__PRODUCT__ AS p ON oi.id_product=p.id_product", "LEFT")
            ->join("__ORDER_SHIPPING__ AS os ON o.id_order=os.id_order", "LEFT")
            ->where($where)->count('distinct(oi.id_product)');

//        $count_sql = $M->table($ord_name . ' AS o ')
//            ->field("count(oi.id_product)")
//            ->where($where)->group('oi.id_product')->select(false);
       // $count = $M->table('('.$count_sql.') AS T')->count();
        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        $page = $this->page($count, 20);
        $list =  M('Order')->alias('o')
            ->join("__ORDER_ITEM__ AS oi ON oi.id_order=o.id_order", "LEFT")
            ->join("__PRODUCT__ AS p ON oi.id_product=p.id_product", "LEFT")
            ->join("__ORDER_SHIPPING__ AS os ON o.id_order=os.id_order", "LEFT")
            //$M->table($ord_name . ' AS o LEFT JOIN ' . $ord_Shi_name . ' AS os ON o.id_order=os.id_order')
            ->field($field_str)->where($where)
            ->group('oi.id_product')->order($order_by)
            ->limit($page->firstRow, $page->listRows)
            ->cache(true,600)->select();
        //var_dump($list);die;
        $data = array(
            'list'=>$list,
            'page'=>$page->show('Admin')
        );
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $department_id  = $_SESSION['department_id'];
        $this->assign('order_by',$_REQUEST['order_by']);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("username", $username);
        $this->assign('list',$data['list']);
        $this->assign("page",$data['page']);
        $this->assign("shipList",$shipList);
        $this->display();
    }
    
    public function exportProduct_sign(){
         $sort = isset($_GET['sort']) && $_GET['sort']=='asc'?'asc':'desc';
        $order_by = 'o.id_domain '.$sort;
        if(isset($_REQUEST['order_by'])){
            switch($_REQUEST['order_by']){
                case 'total':
                    $order_by = 'total '.$sort;
                    break;
                case 'effective':
                    $order_by = 'effective '.$sort;
                    break;
                case 'refused_to_sign':
                    $order_by = 'refused_to_sign '.$sort;
                    break;
                case 'delivery':
                    $order_by = 'delivery '.$sort;
                    break;
                case 'smooth_delivery':
                    $order_by = 'smooth_delivery '.$sort;
                    break;
                case 'rejection_rate':
                    $order_by = 'rejection_rate '.$sort;
                    break;
                default:
                    $order_by = 'o.id_domain desc';
            }
        }
        $where = array();
        $department_id = $_SESSION['department_id'];
        $user_id       = $_SESSION['ADMIN_ID'];
        $all_user      = D("Common/Users")->field('id,user_nicename')->cache(true,3600)->select();
        $subordinate =  D("Common/Users")->get_all_subordinate($user_id);
        $all_domain    = D("Common/Domain")->field('id_domain,name')->cache(true,3600)->select();
        $department    = D("Common/Department")->where(array('id_users'=>$user_id))->cache(true,3600)->find();
        if($department or $user_id==1){
            $where['o.id_department'] = array('IN',$department_id);
        }else{
            if($subordinate){
                array_push($subordinate,$user_id);
                rsort($subordinate);
            }
            $id_users      = $subordinate?:$user_id;
            $role_user = M('RoleUser')->field('role_id')->where(array('user_id'=>$user_id))->find();
            if(in_array($role_user['role_id'],array(28,29,30))) {
                $where['o.id_users'] = is_array($id_users) && count($id_users)>1?array('IN',$id_users):$id_users;
            }

        }

        if (isset($_GET['id_department']) && $_GET['id_department']) {//搜索部门编号
            $where['o.id_department'] = $_GET['id_department'];
        }

        if (isset($_GET['id_users']) && $_GET['id_users']) {//搜索部门编号
            $where['o.id_users'] = $_GET['id_users'];
        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
            $create_at = array();
            if ($_GET['start_time']) $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
            $where['o.created_at']     = $create_at;
            //$total_where = array('o.created_at'=>$create_at);
        }
        if (isset($_GET['start_date_delivery']) && $_GET['start_date_delivery']) {//搜索物流运单号表的订单
            $date_delivery = array();
            if ($_GET['start_date_delivery']) $date_delivery[] = array('EGT', $_GET['start_date_delivery']);
            if ($_GET['end_date_delivery']) $date_delivery[] = array('LT', $_GET['end_date_delivery']);
            $where['o.date_delivery']     = $date_delivery;
            //$total_where = array('o.created_at'=>$create_at);
        }

        if(isset($_GET['title']) && $_GET['title']){
            //$where['p.title']     = $_GET['title'];
            $where['p.title']=array('like','%'.$_GET['title'].'%');

        }
        if($_GET['id_shipping']){
            $where['os.id_shipping']     = $_GET['id_shipping'];
        }
        if(isset($_GET['inner_name']) && $_GET['inner_name']){
            //$where['p.inner_name']     = $_GET['inner_name'];
            $where['p.inner_name']=  array('like','%'.$_GET['inner_name'].'%');
        }
        if(isset($_GET['sku']) && $_GET['sku']){
            $where['p.model']     = $_GET['sku'];
        }
        $field_str     = "count(o.id_order) as total,SUM(IF(o.`id_order_status` IN(2,3,4,5,6,7,8,9,10,16,17),1,0)) as effective,p.title,p.inner_name,p.model";
        $field_str    .= ",SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0)) AS refused_to_sign";
        $field_str    .= ",SUM(IF(os.track_number!='',1,0)) as delivery";
        $field_str    .= ",SUM(IF(os.status_label='順利送達' and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='順利送達',1,0)) as smooth_delivery";
        $field_str    .= ",(SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0)))/(SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0))+SUM(IF(os.status_label='順利送達' and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='順利送達',1,0))) as rejection_rate";
        $ord_model = D("Order/Order");
        $M = new \Think\Model;
        $ord_name = $ord_model->getTableName();
        /** @var  $ordShipping \Common\Model\OrderShippingModel */
        $ord_shipping = D("Order/OrderShipping");
        $ord_Shi_name = $ord_shipping->getTableName();


        $shipList = M('shipping')->where(array('status' => 1))->getField('id_shipping,title');
        $list =  M('Order')->alias('o')
            ->join("__ORDER_ITEM__ AS oi ON oi.id_order=o.id_order", "LEFT")
            ->join("__PRODUCT__ AS p ON oi.id_product=p.id_product", "LEFT")
            ->join("__ORDER_SHIPPING__ AS os ON o.id_order=os.id_order", "LEFT")
            //$M->table($ord_name . ' AS o LEFT JOIN ' . $ord_Shi_name . ' AS os ON o.id_order=os.id_order')
            ->field($field_str)->where($where)
            ->group('oi.id_product')->order($order_by)->select();
        $str = "产品名,内部名,产品sku,总订单,有效单,已发货,已签收,已拒签,签收率,拒签率\n";
        foreach ($list as $item){
            $totalsign= $item['smooth_delivery']+$item['refused_to_sign'];
            $str.=
                    $item['title'] . "," .
                    $item['inner_name'] . "," .
                    $item['model'] . "," .
                    $item['total'] . "," .   
                    $item['effective'] . "," .
                    $item['delivery'] . "," .
                    $item['smooth_delivery'] . "," .
                    $item['refused_to_sign'] . "," .
                    ($item['smooth_delivery']/$totalsign*100).'%,'.
                    ($item['refused_to_sign']/$totalsign*100)."%\n";
           
        }   
        $filename = date('Ymd') . '.csv'; //设置文件名
        $this->export_csv($filename,  iconv("UTF-8","GBK//IGNORE",$str)); //导出
        exit;
    }

    protected function export_csv($filename, $data) {
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=" . $filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }        
    /**
     * 产品拒签率
     */
    public function product_ref_sign(){

        $sort = isset($_GET['sort']) && $_GET['sort']=='asc'?'asc':'desc';
        $order_by = 'o.id_domain '.$sort;
        if(isset($_REQUEST['order_by'])){
            switch($_REQUEST['order_by']){
                case 'total':
                    $order_by = 'total '.$sort;
                    break;
                case 'effective':
                    $order_by = 'effective '.$sort;
                    break;
                case 'refused_to_sign':
                    $order_by = 'refused_to_sign '.$sort;
                    break;
                case 'delivery':
                    $order_by = 'delivery '.$sort;
                    break;
                case 'smooth_delivery':
                    $order_by = 'smooth_delivery '.$sort;
                    break;
                case 'rejection_rate':
                    $order_by = 'rejection_rate '.$sort;
                    break;
                default:
                    $order_by = 'o.id_domain desc';
            }
        }
        $where = array();
        $department_id = $_SESSION['department_id'];
        $user_id       = $_SESSION['ADMIN_ID'];
        $all_user      = D("Common/Users")->field('id,user_nicename')->cache(true,3600)->select();
        $subordinate =  D("Common/Users")->get_all_subordinate($user_id);
        $all_domain    = D("Common/Domain")->field('id_domain,name')->cache(true,3600)->select();
        $department    = D("Common/Department")->where(array('id_users'=>$user_id))->cache(true,3600)->find();
        if($department or $user_id==1){
            $where['o.id_department'] = array('IN',$department_id);
        }else{
            if($subordinate){
                array_push($subordinate,$user_id);
                rsort($subordinate);
            }
            $id_users      = $subordinate?:$user_id;
            $role_user = M('RoleUser')->field('role_id')->where(array('user_id'=>$user_id))->find();
            if(in_array($role_user['role_id'],array(28,29,30))) {
                $where['o.id_users'] = is_array($id_users) && count($id_users)>1?array('IN',$id_users):$id_users;
            }

        }

        if (isset($_GET['id_department']) && $_GET['id_department']) {//搜索部门编号
            $where['o.id_department'] = $_GET['id_department'];
        }

        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
            $create_at = array();
            if ($_GET['start_time']) $create_at[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) $create_at[] = array('LT', $_GET['end_time']);
            $where['o.created_at']     = $create_at;
            //$total_where = array('o.created_at'=>$create_at);
        }
        if (isset($_GET['start_date_delivery']) && $_GET['start_date_delivery']) {//搜索物流运单号表的订单
            $date_delivery = array();
            if ($_GET['start_date_delivery']) $date_delivery[] = array('EGT', $_GET['start_date_delivery']);
            if ($_GET['end_date_delivery']) $date_delivery[] = array('LT', $_GET['end_date_delivery']);
            $where['o.date_delivery']     = $date_delivery;
            //$total_where = array('o.created_at'=>$create_at);
        }

        if(isset($_GET['title']) && $_GET['title']){
            //$where['p.title']     = $_GET['title'];
            $where['p.title']=array('like','%'.$_GET['title'].'%');

        }
        if(isset($_GET['inner_name']) && $_GET['inner_name']){
            //$where['p.inner_name']     = $_GET['inner_name'];
            $where['p.inner_name']=  array('like','%'.'A2'.'%');
        }
        if(isset($_GET['sku']) && $_GET['sku']){
            $where['p.model']     = $_GET['sku'];
        }
        $field_str     = "count(o.id_order) as total,SUM(IF(o.`id_order_status` IN(2,3,4,5,6,7,8,9,10,16,17),1,0)) as effective,p.title,p.inner_name,p.model";
        $field_str    .= ",SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0)) AS refused_to_sign";
        $field_str    .= ",SUM(IF(os.track_number!='',1,0)) as delivery";
        $field_str    .= ",SUM(IF(os.status_label='順利送達' and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='順利送達',1,0)) as smooth_delivery";
        $field_str    .= ",(SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0)))/(SUM(IF(os.`status_label` in ('暫置營業所保管中','拒收(調查處理中)','退貨完成','地址不明(調查處理中)','代收退貨完成') and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='拒收',1,0))+SUM(IF(os.status_label='順利送達' and os.summary_status_label is null,1,0))+SUM(IF(os.summary_status_label='順利送達',1,0))) as rejection_rate";
        $ord_model = D("Order/Order");
        $M = new \Think\Model;
        $ord_name = $ord_model->getTableName();
        /** @var  $ordShipping \Common\Model\OrderShippingModel */
        $ord_shipping = D("Order/OrderShipping");
        $ord_Shi_name = $ord_shipping->getTableName();

        $count = M('Order')->alias('o')
            ->join("__ORDER_ITEM__ AS oi ON oi.id_order=o.id_order", "LEFT")
           // ->join("__PRODUCT__ AS p ON oi.id_product=p.id_product", "LEFT")
            ->where($where)->count('distinct(oi.id_product)');
//        $count_sql = $M->table($ord_name . ' AS o ')
//            ->field("count(oi.id_product)")
//            ->where($where)->group('oi.id_product')->select(false);
        // $count = $M->table('('.$count_sql.') AS T')->count();

        $page = $this->page($count, 20);
        $list =  M('Order')->alias('o')
            ->join("__ORDER_ITEM__ AS oi ON oi.id_order=o.id_order", "LEFT")
            ->join("__PRODUCT__ AS p ON oi.id_product=p.id_product", "LEFT")
            ->join("__ORDER_SHIPPING__ AS os ON o.id_order=os.id_order", "LEFT")
            //$M->table($ord_name . ' AS o LEFT JOIN ' . $ord_Shi_name . ' AS os ON o.id_order=os.id_order')
            ->field($field_str)->where($where)
            ->group('oi.id_product')->order($order_by)
            ->limit($page->firstRow, $page->listRows)
            ->cache(true,600)->select();
        //var_dump($list);die;
        $data = array(
            'list'=>$list,
            'page'=>$page->show('Admin')
        );
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $department_id  = $_SESSION['department_id'];
        $this->assign('order_by',$_REQUEST['order_by']);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign('list',$data['list']);
        $this->assign("page",$data['page']);
        $this->display();
    }
    

    /**
     * 拒签率
     */
    public function ref_sign(){
        $data = $this->get_ref_sign();

        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $department_id  = $_SESSION['department_id'];

        $this->assign('order_by',$_REQUEST['order_by']);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign('list',$data['list']);
        $this->assign('all_user',$data['all_user']);
        $this->assign('all_domain',$data['all_domain']);
        $this->assign("page",$data['page']);
        $this->display();
    }

    /**
     * 部门签收率
     */
    public function receipt_rate_by_department() {
        $shipping = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();
        $department = M('Department')->where(array('type'=>1,'id_department'=>array('IN',$_SESSION['department_id'])))->cache(true,6000)->getField('id_department,title',true);
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where[] = array('o.id_department'=>array('EQ',$_GET['id_department']));
        } else {
            $where[] = array('o.id_department'=>array('IN',$_SESSION['department_id']));
        }
        if($_GET['id_zone']){
            $where[] = array('o.id_zone'=>$_GET['id_zone']);
        }
        $result_list = D('Common/Order')->alias('o')
            ->where($where)
            ->join("__DEPARTMENT__ AS d ON d.id_department=o.id_department", "LEFT")
            ->group('o.id_department')
            ->statistics_receipt_rate(array("d.title", "o.id_department"));

        foreach($result_list as &$result){
            $result['rate_signed'] = number_format($result['count_signed']/$result['count_delivered'] * 100, 2) . '%';
            $result['rate_denied'] = number_format(($result['count_delivered']-$result['count_signed'])/$result['count_delivered'] * 100, 2) . '%';
        }
        $zoneList=M('zone')->getField('id_zone,title',true);

        if( I('request.show')== 'export_excel'){
            $row_map = array(
                array('name'=>'部门', 'key'=> 'title'),
                array('name'=>'发货单数', 'key'=> 'count_delivered'),
                array('name'=>'签收单', 'key'=> 'count_signed'),
                array('name'=>'签收率', 'key'=> 'rate_signed'),
                array('name'=>'拒签率', 'key'=> 'rate_denied')
            );
            vendor('PHPExcel.ExcelManage');
            $excel = new \ExcelManage();
            $excel->export($result_list, $row_map, date("Y-m-d") . '签收率部门统计');
        }else{
            $this->assign('list',$result_list);
            $this->assign('zoneList',$zoneList);            
            $this->assign("shipping",$shipping);
            $this->assign('department',$department);
            $this->display();
        }
    }

    public function receipt_rate_by_date() {
        $shipping = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();
        $department = M('Department')->where(array('type'=>1))->cache(true,6000)->getField('id_department,title',true);
        if(isset($_GET['id_department']) && $_GET['id_department']) {
            $where[] = array('o.id_department'=>array('EQ',$_GET['id_department']));
        } else {
            $where[] = array('o.id_department'=>array('EQ',$_SESSION['department_id']));
        }
        if($_GET['id_zone']){
            $where[] = array('o.id_zone'=>$_GET['id_zone']);
        }       
        $result_list = D('Common/Order')->alias('o')
            ->where($where)
            ->group('day')
            ->statistics_receipt_rate(array("DATE(o.date_delivery) AS day"));

        foreach($result_list as &$result){
            $result['rate_signed'] = number_format($result['count_signed']/$result['count_delivered'] * 100, 2) . '%';
            $result['rate_denied'] = number_format(($result['count_delivered']-$result['count_signed'])/$result['count_delivered'] * 100, 2) . '%';
        }
        $zoneList=M('zone')->getField('id_zone,title',true);
        if( I('request.show')== 'export_excel'){
            $row_map = array(
                array('name'=>'日期', 'key'=> 'day'),
                array('name'=>'发货单数', 'key'=> 'count_delivered'),
                array('name'=>'签收单', 'key'=> 'count_signed'),
                array('name'=>'签收率', 'key'=> 'rate_signed'),
                 array('name'=>'拒签率', 'key'=> 'rate_denied')
            );
            vendor('PHPExcel.ExcelManage');
            $excel = new \ExcelManage();
            $excel->export($result_list, $row_map, date("Y-m-d") . '签收率日统计');
        }else{
            $this->assign('list',$result_list);
            $this->assign('zoneList',$zoneList);            
            $this->assign("shipping",$shipping);
            $this->assign('department',$department);
            $this->display();
        }
    }
    
    /**
     * 产品签收率
     */
    public function  sign_rate_product(){
        $getData=I('get.','','htmlspecialchars,trim');
        //所有已发货的状态 arr
        $all_delivered_status =  OrderStatus::get_delivered_status();  
        $singed_status = OrderStatus::SIGNED;
        $where=[];   
        if($getData['date_delivery']){
            $date_delivery=date('Y-m-d',strtotime($getData['date_delivery']));            
        }else{
            $date_delivery=date('Y-m-d');
        }
        $date_delivery_arr[] = array('EGT', $date_delivery);
        $date_delivery_arr[] = array('LT', ($date_delivery.' 23:59:59'));
        $where['o.date_delivery'] = $date_delivery_arr;
        if($getData['id_department']){
            $where['o.id_department']=$getData['id_department'];
        } else{
            $where['o.id_department']=array('in',$_SESSION['department_id']);
        }       
        if($getData['id_shipping']){
            $where['o.id_shipping']=$getData['id_shipping'];
        }
        if($getData['id_zone']){
            $where['o.id_zone']=$getData['id_zone'];
        }
        $where['o.id_order_status'] = array('IN', $all_delivered_status);
                
        $field = array(
            "COUNT(*) AS count_delivered",
            "SUM(IF(o.id_order_status ='{$singed_status}', 1, 0)) AS count_signed",
             "p.id_product","p.thumbs","p.inner_name"
        );
        $orderItemTb=M('orderItem')->getTableName();
        $productTb=M('product')->getTableName();        
        $list=M('order o')->join("{$orderItemTb} oi on oi.id_order=o.id_order",'left')
                ->join("{$productTb} p on p.id_product=oi.id_product")
                ->where($where)->group('oi.id_product')->field($field)->select();
        foreach ($list as &$val){
            $val['thumbs']=  json_decode($val['thumbs'], true);
            $val['rate_signed'] = number_format($val['count_signed']/$val['count_delivered'] * 100, 2) . '%';
        }
        if($getData['isexport']==1){
            $row_map = array(
                array('name'=>'内部名', 'key'=> 'inner_name'),
                array('name'=>'发货单数', 'key'=> 'count_delivered'),
                array('name'=>'签收单', 'key'=> 'count_signed'),
                array('name'=>'签收率', 'key'=> 'rate_signed')
            );
            vendor('PHPExcel.ExcelManage');
            $excel = new \ExcelManage();
            $excel->export($list, $row_map, date("Y-m-d") . '签收率日统计');            
        }
        $departList=M('department')->order('title')->where(array('id_department'=>array('in',$_SESSION['department_id'])))->getField('id_department,title');
        $shippingList=M('shipping')->where(array('status'=>1))->getField('id_shipping,title');
        $zoneList=M('zone')->getField('id_zone,title');
        $this->assign('departList',$departList);
        $this->assign('zoneList',$zoneList); 
        $this->assign('shippingList',$shippingList);        
        $this->assign('signedList',$list);        
        $this->assign('getData',$getData);
        $this->display();
    }
}










