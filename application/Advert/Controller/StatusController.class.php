<?php

namespace Advert\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use SystemRecord\Model\SystemRecordModel;

class StatusController extends AdminbaseController {

    protected $AdvertData;

    protected $freightArr=array(
        '2'=>[0=>['first_weight'=>1.1,'first_weight_price'=>30,'continued_weight'=>1,'continued_weight_price'=>11,'procedure_price'=>0]],
        '3'=>[0=>['first_weight'=>3,'first_weight_price'=>44,'continued_weight'=>1,'continued_weight_price'=>3.5,'procedure_price'=>0]],
        '15'=>[0=>['first_weight'=>2,'first_weight_price'=>48,'continued_weight'=>1,'continued_weight_price'=>5,'procedure_price'=>0]],
        '17'=>[
            3=>['type'=>3,'first_weight'=>0.5,'first_weight_price'=>18,'continued_weight'=>0.5,'continued_weight_price'=>8,'procedure_price'=>15],
            4=>['type'=>4,'first_weight'=>0.5,'first_weight_price'=>22,'continued_weight'=>0.5,'continued_weight_price'=>8,'procedure_price'=>15]

            ],
        '7'=>[['type'=>0,'first_weight'=>1,'first_weight_price'=>30,'continued_weight'=>1,'continued_weight_price'=>15,'procedure_price'=>35]],
        '4'=>[
            3=>['type'=>3,'first_weight'=>0.5,'first_weight_price'=>45,'continued_weight'=>0.5,'continued_weight_price'=>12,'procedure_price'=>35],
            4=>['type'=>4,'first_weight'=>0.5,'first_weight_price'=>52,'continued_weight'=>0.5,'continued_weight_price'=>12,'procedure_price'=>35]

            ],
        '11'=>[
            3=>['type'=>3,'first_weight'=>1,'first_weight_price'=>40,'continued_weight'=>1,'continued_weight_price'=>10,'procedure_price'=>23],
            1=>['type'=>1,'first_weight'=>1,'first_weight_price'=>46,'continued_weight'=>1,'continued_weight_price'=>12,'procedure_price'=>23],
            2=>['type'=>2,'first_weight'=>1,'first_weight_price'=>55,'continued_weight'=>1,'continued_weight_price'=>15,'procedure_price'=>23],
            ],
        '15'=>[0=>['first_weight'=>0.5,'first_weight_price'=>36,'continued_weight'=>0.5,'continued_weight_price'=>19,'procedure_price'=>0]],

    );

    public function _initialize() {
        parent::_initialize();
        $this->order = D("Order/Order");
        $this->AdvertData = D("Common/AdvertData");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }
    /**
     * 今日处理
     */
    public function today_process(){
        $where = array();
        $id_order_status = I('get.status_id');
        if ($id_order_status > 0) {
            $where['id_order_status'] = array('EQ',$id_order_status);
        } else {
            $where['id_order_status'] =  array('IN', array(3,4,10,11,12,13,14,15));
        }
        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        $data = $model->get_untreated_order_byusers($where);
        $department_id  = $_SESSION['department_id'];
        $admin_id = $_SESSION['ADMIN_ID'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $data['advertiser']);
        $this->assign("get_data", $_GET);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page",$data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data['order_total']);
        //$this->assign("todayWebData", $data['todayWebData']);
        $this->assign("order_list",$data['order_list']);
        $this->assign("shipping",$data['shipping']);
        $this->assign("all_product",$data['all_product']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看已处理订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone",$data['all_zone']);
        $this->display();
    }

    /**
     * 未处理订单
     */
    public function untreated(){
        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        $set_where = array('id_order_status'=>1,'payment_method'=>array('NOT IN','0'));
        $data = $model->get_untreated_order_byusers($set_where,true);
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看未处理订单');
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $data['advertiser']);
        $this->assign("get_data", $_GET);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page",$data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data['order_total']);
        //$this->assign("todayWebData", $data['todayWebData']);
        $this->assign("order_list",$data['order_list']);
        $this->assign("shipping",$data['shipping']);
        $this->assign("all_product",$data['allProduct']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone",$data['all_zone']);
        $this->display();
    }

    /**
     * 待审核订单
     */
    public function pending(){
        $where = array('id_order_status'=>3);
        /** @var \Order\Model\OrderStatusModel $model */
        $model = D('Order/OrderStatus');
        $data= $model->get_untreated_order_byusers($where);
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $data['advertiser']);
        $this->assign("get_data", $_GET);
        $this->assign("form_data", $data['form_data']);
        $this->assign("page",$data['page']);
        $this->assign("today_total", $data['today_total']);
        $this->assign("order_total", $data['order_total']);
        $this->assign("today_web_data", $data['today_web_data']);
        $this->assign("order_list",$data['order_list']);
        $this->assign("shipping",$data['shipping']);
        $this->assign("all_product",$data['all_product']);
        /** @var \Order\Model\OrderStatusModel $status_model */
        $status_model = D('Order/OrderStatus');
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看待审核订单');
        $this->assign('status_list',$status_model->get_status_label());
        $this->assign("all_zone",$data['all_zone']);
        $this->display();
    }

    /**
     * 广告成效统计
     */
    public function effect() {
        $id_department = $_SESSION['department_id'] ? $_SESSION['department_id'] : array();
        $where = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
                $createAtArray[] = array('EGT', $_GET['start_time']);
            if($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
            $where[] = array('o.created_at' => $createAtArray);
        }
        else {
            $all_day = $this->get_the_month(date('Y-m-d', strtotime('-1 month')));
            $createAtArray = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $where[] = array('o.created_at' => $createAtArray);
        }
        $group= M('DepartmentGroup')->field('id_department,title')->where(array('parent_id'=>array('IN',$id_department)))->select();
        $department = M('Department')->field('id_department,title')->where(array('id_department'=>array('IN',$id_department)))->select();
        $department_id = M('Department')->where(array('id_users'=>$_SESSION['ADMIN_ID']))->getField('id_department');
        if($department_id) {
            $flag=1;//主管
        } else {
            $users = M('Users')->field('id,superior_user_id')->where(array('superior_user_id'=>$_SESSION['ADMIN_ID']))->select();
            $user_id = array_column($users, 'id');
            if($user_id) {
                $flag=2;//组长
                array_push($user_id,$_SESSION['ADMIN_ID']);//把本身用户Id添加进去
            } else {
                $flag=3;//组员
                $user_id = $_SESSION['ADMIN_ID'];
            }
            $where['o.id_users'] = array('IN',$user_id);
            $dwhere['id_users'] = array('IN',$user_id);
            $uwhere['id'] = array('IN',$user_id);
        }
        //组长搜索组员
        $user_names = M('Users')->field('id,user_nicename')->where($uwhere)->getField('id,user_nicename',true);

        //搜索部门
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['o.id_department'] = array('EQ',$_GET['department_id']);
        } else {
            $where['o.id_department'] = array('IN',$id_department);
        }
        //搜索小组
        if(isset($_GET['group_id']) && $_GET['group_id']) {
            $user_id2=M('GroupUsers')->field('id_users')->where(array('id_department'=>$_GET['group_id']))->select();
            $user_id3=array();
            foreach ($user_id2 as $k=>$v) {
                $user_id3[$k]=$v['id_users'];
            }
            $user_id2 = implode(',',$user_id3);
            $user_id2 = trim($user_id2,',');

            $where['o.id_users'] = array('IN',$user_id2);

        }
        //选择名称
        if (isset($_GET['user_id']) && $_GET['user_id']) {
            $where[] = array('o.id_users' => $_GET['user_id']);
        }
        //搜索名称
        if(isset($_GET['user_name']) && $_GET['user_name']) {
            $user_name = M('Users')->field('id')->where(array('user_nicename'=>array('LIKE', '%' . $_GET['user_name'] . '%')))->getField('id',true);
            $where['o.id_users'] = array('IN',$user_name);
        }
        //选择域名
        if (isset($_GET['domain_id']) && $_GET['domain_id']) {
            $where[] = array('o.id_domain' => $_GET['domain_id']);
        }
        //选择地区
        if(isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where[] = array('o.id_zone' => $_GET['zone_id']);
            $exwhere['ad.id_zone'] = $_GET['zone_id'];
        }

        $M = new \Think\Model();
        $order_tab = M('Order')->getTableName();
        $advert_tab = M('Advert')->getTableName();
        $advert_data_tab = M('AdvertData')->getTableName();
        $order_item_tab = M('OrderItem')->getTableName();
        $product_sku = M('ProductSku')->getTableName();
        $product = M('Product')->getTableName();

        $shipping = M('Shipping')->where(array('id_shipping'=>25))->find();//天马物流

//        $where['o.id_department'] = array('IN',$id_department);
        $dwhere['id_department'] = array('IN',$id_department);

        $field = 'SUBSTRING(o.created_at,1,10) AS create_date,o.id_users,o.id_domain,o.id_order,o.currency_code,o.id_zone,
                SUM(IF(o.id_order_status NOT IN(1,2,3,11,12,13,14,15),1,0)) as effective,
                SUM(IF(o.id_order_status NOT IN(1,2,3,11,12,13,14,15),o.price_total,0)) as effective_price';

        $result_count = $M->table($order_tab.' as o')->field($field)
                ->where($where)
                ->group('create_date,o.id_domain,o.id_zone')
                ->select();

        $page = $this->page(count($result_count), 50);

        $result = $M->table($order_tab.' as o')->field($field)
                ->where($where)
                ->group('create_date,o.id_domain,o.id_zone')
                ->order('create_date DESC,effective DESC')
                ->limit($page->firstRow . ',' . $page->listRows)
                ->select();
        foreach ($result as $k=>$v) {
            $exwhere['ad.id_users_today'] = $v['id_users'];
            $exwhere['a.id_domain'] = $v['id_domain'];
            $exwhere['ad.conversion_at'] = $v['create_date'];
            $expense = $M->table($advert_tab.' as a')->join('LEFT JOIN '.$advert_data_tab.' as ad ON ad.advert_id=a.advert_id')->field('SUM(ad.expense) as expense')
                        ->where($exwhere)->find();

            $product_result = $M->table($order_item_tab.' as oi')->join('LEFT JOIN '.$product_sku.' as p ON p.id_product_sku=oi.id_product_sku')->field('p.weight,oi.quantity,p.purchase_price')
                                ->where(array('oi.id_order'=>$v['id_order']))->find();

            $result[$k]['effective_price'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price']);
            $result[$k]['expense'] = round($expense['expense']*6.8,2);//广告费
            $result[$k]['weight'] = $product_result['weight'];//产品重量
            $result[$k]['quantity'] = $product_result['quantity'];//产品件数
            $result[$k]['purchase_price'] = $product_result['purchase_price'];//产品采购价
            $result[$k]['name'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');//名称
            $result[$k]['domain'] = M('Domain')->where(array('id_domain'=>$v['id_domain']))->getField('name');//域名
            //单个运费
            $result[$k]['freight'] = get_freight($v['weight'], $shipping['first_weight'], $shipping['first_weight_price'], $shipping['continued_weight'], $shipping['continued_weight_price']);
        }

        $arr_result = array();
        foreach ($result as $key=>$val) {
//            if(empty($val['effective']) || empty($val['effective_price']) || empty($val['name'])) continue;
            $arr_result[$val['create_date']][] = $val;
        }

        $total_effective=0;//总计有效单
        $total_effective_price=0;//总计营业额
        $total_advert_price=0;//总计广告费
        $total_freight=0;//总计运费
        $total_purchase_price=0;//总计采购价
        $total_qty=0;//总计数量
        foreach ($arr_result as $r_key=>$r_val) {
            foreach ($r_val as $key=>$val) {
                $total_effective=$total_effective+$val['effective'];
                $total_effective_price=$total_effective_price+$val['effective_price'];
                $total_advert_price=$total_advert_price+$val['expense'];
                $total_freight=$total_freight+$val['freight'];
                $total_purchase_price=$total_purchase_price+$val['purchase_price'];
                $total_qty=$total_qty+$val['quantity'];
            }
        }
        $total_price = round($total_effective_price/$total_effective,2);//总计客单价
        $total_ad_average_price = round($total_advert_price/$total_effective,2);//总计平均广告费
        $total_roi = $total_advert_price != 0 ? round($total_effective_price/$total_advert_price,2) : 0;//总计ROI

        $tReturns = ($total_effective_price/($total_advert_price+($total_freight)+($total_purchase_price)));
        $total_tzhb = round($tReturns*100,2).'%';//投资回报率

        $Returns = (($total_effective_price-$total_advert_price-$total_freight)-($total_purchase_price))/($total_effective_price*0.8);
        $total_lr = round($Returns*100,2).'%';//利润率


        $domain = M('Order')->field('id_domain')->where($dwhere)->select();
        $id_domain = array_column($domain, 'id_domain');
        $id_domain = array_unique($id_domain);
        if($id_domain){
            $domain_name = M('Domain')->field('id_domain,name')->where(array('id_domain'=>array('IN',$id_domain),'id_department'=>array('IN',$id_department)))->order('name ASC')->select();
            $domain_name = array_column($domain_name, 'name', 'id_domain');
        }

        $zone = M('Zone')->field('id_zone,title')->cache(true,600)->select();
        $zone = array_column($zone, 'title','id_zone');
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看广告成效统计');
        $this->assign('list',$arr_result);
        $this->assign('domain_name',$domain_name);
        $this->assign("page", $page->show('Admin'));
        $this->assign('total_effective',$total_effective);
        $this->assign('total_effective_price',$total_effective_price);
        $this->assign('total_advert_price',$total_advert_price);
        $this->assign('total_price',$total_price);
        $this->assign('total_ad_average_price',$total_ad_average_price);
        $this->assign('total_roi',$total_roi);
        $this->assign('total_tzhb',$total_tzhb);
        $this->assign('total_lr',$total_lr);
        $this->assign('flag',$flag);
        $this->assign('user_names',$user_names);
        $this->assign('group',$group);
        $this->assign('department',$department);
        $this->assign('zone',$zone);
        $this->display();
    }

    /**
     * 广告每日汇总
     */
    public function advert_summary() {
        $where = array();
        $id_department = $_SESSION['department_id'] ? $_SESSION['department_id'] : array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
                $createAtArray[] = array('EGT', $_GET['start_time']);
            if($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
            $where[] = array('o.created_at' => $createAtArray);
        } else {
            $all_day = $this->get_the_month(date('Y-m-d'));
            $createAtArray = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $where[] = array('o.created_at' => $createAtArray);
        }

        //搜索部门
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['o.id_department'] = array('EQ',$_GET['department_id']);
            $dwhere['id_department'] = array('EQ',$_GET['department_id']);
            $owhere['id_department'] = array('EQ',$_GET['department_id']);
        } else {
            $where['o.id_department'] = array('IN',$id_department);
            $dwhere['id_department'] = array('IN',$id_department);
            $owhere['id_department'] = array('IN',$id_department);
        }
        //搜索小组
        if(isset($_GET['group_id']) && $_GET['group_id']) {
            $user_id2=M('GroupUsers')->field('id_users')->where(array('id_department'=>$_GET['group_id']))->select();
            $user_id3=array();
            foreach ($user_id2 as $k=>$v) {
                $user_id3[$k]=$v['id_users'];
            }
            $user_id2 = implode(',',$user_id3);
            $user_id2 = trim($user_id2,',');
            $where['o.id_users'] = array('IN',$user_id2);
            $ewhere[] = array('a.id_users' => array('IN',$user_id2));
        }
        //搜索名称
//        if(isset($_GET['user_id']) && $_GET['user_id']) {
//            $users = M('Users')->field('id')->where(array('superior_user_id'=>$_GET['user_id']))->select();
//            $user = array_column($users, 'id');
//            array_push($user, $_GET['user_id']);
//            $where['o.id_users'] = array('IN',$user);
//            $ewhere[] = array('a.id_users' => array('IN',$user));
//        }

        //选择地区
        if(isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = array('EQ',$_GET['zone_id']);
            $ewhere[] = array('ad.id_zone' => $_GET['zone_id']);
            $owhere[] = array('id_zone' => $_GET['zone_id']);
        }

        $M = new \Think\Model();
        $order_tab = M('Order')->getTableName();

        $department = M('Department')->field('id_department,title')->where(array('id_department'=>array('IN',$id_department)))->select();
//        $users = M('Users')->field('id,superior_user_id')->where(array('superior_user_id'=>$_SESSION['ADMIN_ID']))->getField('id',true);
//        $depart_user = M('Department')->where(array('id_users'=>$_SESSION['ADMIN_ID']))->find();
        $group= M('DepartmentGroup')->field('id_department,title')->where(array('parent_id'=>array('IN',$id_department)))->select();
        $newgroup=array();
        foreach($group as $k => $v){
            $newgroup[$v['id_department']]=$v['title'];
        }
//        if($users) {
//            if($depart_user) {
//                $otherwhere['superior_user_id'] = array('IN',$users);
//                $other_users = M('Users')->field('id,superior_user_id')->where($otherwhere)->getField('id',true);
//            }
//            $uwhere['id'] = !empty($other_users) ? array('IN', array_merge($users,$other_users)) : array('IN',$users);
//        } else {
//            $uwhere['id'] = !empty($users) ? array('IN',$users) : $_SESSION['ADMIN_ID'];
//        }
//
//
//        $user_names = M('Users')->field('id,user_nicename')->where($uwhere)->getField('id,user_nicename',true);
//"SUM(IF(o.id_order_status ='{$singed_status}', 1, 0)) AS count_signed",
        $singed_status = OrderStatus::SIGNED;
        $field = "count(*) as count,SUBSTRING(o.created_at,1,10) AS create_date,o.currency_code,
                SUM(IF(o.id_order_status NOT IN(1,2,3,4,5,6,7,11,12,13,14,15,28,29,30),1,0)) as effective,
                SUM(IF(o.id_order_status ='{$singed_status}', 1, 0)) AS count_signed,
                SUM(IF(o.id_order_status NOT IN(1,2,3,4,5,6,7,11,12,13,14,15,28,29,30),o.price_total,0)) as effective_price";
        $result = $M->table($order_tab.' as o')
                ->field($field)
                ->where($where)
                ->group('create_date,o.currency_code')
                ->order('create_date ASC')
                ->select();
                
        // echo $M->getLastSql();
//        foreach ($result as $k=>$v) {
//            $time = $v['create_date'];
//            $product_result = $this->get_expense_result($time, date('Y-m-d',strtotime("$time +1 day")),true,false,false,false,false,$owhere);
//            $result[$k]['effective_price'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price']);
//            $result[$k]['expense'] = $this->get_expense_result($time, date('Y-m-d',strtotime("$time +1 day")),false,false,false,$ewhere,$dwhere);//广告费
//            $result[$k]['quantity'] = $product_result['one_quantity_count'];//产品件数
//            $result[$k]['purchase_price'] = $product_result['one_purchase_price_count'];//产品采购价
//            $result[$k]['freight'] = round($product_result['one_freight_count'],2);//运费
//        }

        $result_arr = array();
        foreach ($result as $k=>$v) {
            $v['effective_price'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price']);
            $result_arr[$v['create_date']][] = $v;
        }

        $list = $this->get_arr($result_arr);
        foreach ($list as $k=>$v) {
            $time = $v['create_date'];
            $product_result = $this->get_expense_result($time, date('Y-m-d',strtotime("$time +1 day")),true,false,false,false,false,$owhere);
            $list[$k]['expense'] = $this->get_expense_result($time, date('Y-m-d',strtotime("$time +1 day")),false,false,false,$ewhere,$dwhere);//广告费
            $list[$k]['quantity'] = $product_result['one_quantity_count'];//产品件数
            $list[$k]['purchase_price'] = $product_result['one_purchase_price_count'];//产品采购价
            $list[$k]['freight'] = round($product_result['one_freight_count'],2);//运费
        }
        $total_count_num = $this->count_number($list);

        $zone = M('Zone')->cache(true,600)->getField('id_zone,title',true);
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看广告汇总统计');
        $this->assign('list',$list);
        $this->assign('total_count_num',$total_count_num);
//        $this->assign('user_names',$user_names);
        $this->assign('department',$department);
        $this->assign('group',$newgroup);
        $this->assign('zone',$zone);
        $this->display();
    }


    /**
     * 广告每日汇总
     */
    public function export_advert_summary() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '日期', '总订单', '有效单','签收单', '营业额', '客单价', '广告费', '平均广告费', '采购成本', '运费成本', 'ROI ', '有效单占比 ','签收单占比 ', '投资回报率', '利润率',
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }


        $where = array();
        $id_department = $_SESSION['department_id'] ? $_SESSION['department_id'] : array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            if($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
            $where[] = array('o.created_at' => $createAtArray);
        } else {
            $all_day = $this->get_the_month(date('Y-m-d'));
            $createAtArray = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $where[] = array('o.created_at' => $createAtArray);
        }

        //搜索部门
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['o.id_department'] = array('EQ',$_GET['department_id']);
            $dwhere['id_department'] = array('EQ',$_GET['department_id']);
            $owhere['id_department'] = array('EQ',$_GET['department_id']);
        } else {
            $where['o.id_department'] = array('IN',$id_department);
            $dwhere['id_department'] = array('IN',$id_department);
            $owhere['id_department'] = array('IN',$id_department);
        }
        //搜索小组
        if(isset($_GET['group_id']) && $_GET['group_id']) {
            $user_id2=M('GroupUsers')->field('id_users')->where(array('id_department'=>$_GET['group_id']))->select();
            $user_id3=array();
            foreach ($user_id2 as $k=>$v) {
                $user_id3[$k]=$v['id_users'];
            }
            $user_id2 = implode(',',$user_id3);
            $user_id2 = trim($user_id2,',');
            $where['o.id_users'] = array('IN',$user_id2);
            $ewhere[] = array('a.id_users' => array('IN',$user_id2));
        }
        //选择地区
        if(isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = array('EQ',$_GET['zone_id']);
            $ewhere[] = array('ad.id_zone' => $_GET['zone_id']);
            $owhere[] = array('id_zone' => $_GET['zone_id']);
        }
        $M = new \Think\Model();
        $order_tab = M('Order')->getTableName();
        $department = M('Department')->field('id_department,title')->where(array('id_department'=>array('IN',$id_department)))->select();
        $group= M('DepartmentGroup')->field('id_department,title')->where(array('parent_id'=>array('IN',$id_department)))->select();
        $newgroup=array();
        foreach($group as $k => $v){
            $newgroup[$v['id_department']]=$v['title'];
        }
        $singed_status = OrderStatus::SIGNED;
        $field = "count(*) as count,SUBSTRING(o.created_at,1,10) AS create_date,o.currency_code,
                SUM(IF(o.id_order_status NOT IN(1,2,3,11,12,13,14,15),1,0)) as effective,
                SUM(IF(o.id_order_status ='{$singed_status}', 1, 0)) AS count_signed,                
                SUM(IF(o.id_order_status NOT IN(1,2,3,11,12,13,14,15),o.price_total,0)) as effective_price";

        $result = $M->table($order_tab.' as o')->field($field)
            ->where($where)
            ->group('create_date,o.currency_code')
            ->order('create_date ASC')
            ->select();
        $result_arr = array();
        foreach ($result as $k=>$v) {
            $v['effective_price'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price']);
            $result_arr[$v['create_date']][] = $v;
        }

        $list = $this->get_arr($result_arr);

        foreach ($list as $k=>$v) {
            $time = $v['create_date'];
            $product_result = $this->get_expense_result($time, date('Y-m-d',strtotime("$time +1 day")),true,false,false,false,false,$owhere);
            $list[$k]['expense'] = $this->get_expense_result($time, date('Y-m-d',strtotime("$time +1 day")),false,false,false,$ewhere,$dwhere);//广告费
            $list[$k]['quantity'] = $product_result['one_quantity_count'];//产品件数
            $list[$k]['purchase_price'] = $product_result['one_purchase_price_count'];//产品采购价
            $list[$k]['freight'] = round($product_result['one_freight_count'],2);//运费
        }

        $total_count=0;//总计订单
        $total_effective=0;//总计有效单
        $total_effective_price=0;//总计营业额
        $total_advert_price=0;//总计广告费
        $total_freight=0;//总计运费
        $total_purchase_price=0;//总计采购价
        $total_qty=0;//总计数量
        $total_count_signed=0;
        foreach ($list as $key=>$val) {
            $total_count=$total_count+$val['count'];
            $total_effective=$total_effective+$val['effective'];
            $total_count_signed=$total_count_signed+$val['count_signed'];
            $total_effective_price=$total_effective_price+$val['effective_price'];
            $total_advert_price=$total_advert_price+$val['expense'];
            $total_freight=$total_freight+$val['freight'];
            $total_purchase_price=$total_purchase_price+$val['purchase_price'];
            $total_qty=$total_qty+$val['quantity'];
        }

        $total_price = round($total_effective_price/$total_effective,2);//总计客单价
        $total_ad_average_price = round($total_advert_price/$total_effective,2);//总计平均广告费
        $total_roi = $total_advert_price != 0 ? round($total_effective_price/$total_advert_price,2) : 0;//总计ROI

        $cReturns = ($total_effective/$total_count)*100;
        $total_count_price = round($cReturns,2).'%';//有效单占比
        $signed_rate = round(($total_count_signed/$total_effective)*100,2).'%';//签收单占比

        $tReturns = ($total_effective_price/($total_advert_price+($total_freight)+($total_purchase_price)));
        $total_tzhb = round($tReturns*100,2).'%';//投资回报率

        $Returns = (($total_effective_price-$total_advert_price-$total_freight)-($total_purchase_price))/($total_effective_price*0.8);
        $total_lr = round($Returns*100,2).'%';//利润率

        $zone = M('Zone')->cache(true,600)->getField('id_zone,title',true);

        if(!empty($list)){
            $data[] = array(
                '总计', $total_count, $total_effective,$total_count_signed,$total_effective_price,$total_price,$total_advert_price,$total_ad_average_price,$total_purchase_price,
                $total_freight,$total_roi,$total_count_price,$signed_rate,$total_tzhb,$total_lr,
            );
            foreach($list as $key =>$val){
                $tReturns = ($val['effective_price']/($val['expense']+$val['freight']+$val['purchase_price']))*100;
                $Returns = (($val['effective_price']-$val['expense']-$val['freight'])-($val['purchase_price']))/($val['effective_price']*0.8)*100;
                $data[] = array(
                    $val['create_date'],$val['count'],$val['effective'],$val['count_signed'],$val['effective_price'],round($val['effective_price']/$val['effective'],2),
                    !empty($val['expense']) ? $val['expense'] : 0,round($val['expense']/$val['effective'],2),$val['purchase_price'],$val['freight'],
                    round($val['effective_price']/$val['expense'],2),(round($val['effective']/$val['count'],2)*100).'%',(round($val['count_signed']/$val['effective'],2)*100).'%',round($tReturns,2).'%',round($Returns,2).'%',
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
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出部门广告汇总统计');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '部门广告汇总统计.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '部门广告汇总统计.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
    /**
     * 广告详情汇总
     */
    public function advert_check() {
        $where = array();
        $role_user = M('RoleUser')->getTableName();
        $id_department = $_SESSION['department_id'] ? $_SESSION['department_id'] : array();
        if(isset($_GET['start_time']) && $_GET['start_time']) {
            $start_time = $_GET['start_time'];
        } else {
            $start_time = I('get.time');
        }
        $end_time = date('Y-m-d',strtotime("$start_time +1 day"));
        $createAtArray = array();
        $createAtArray[] = array('EGT', $start_time);
        $createAtArray[] = array('LT', $end_time);
        $where[] = array('o.created_at' => $createAtArray);
        //搜索部门
        if(isset($_GET['department_id']) && $_GET['department_id']) {
            $where['o.id_department'] = array('EQ',$_GET['department_id']);
            $dwhere['id_department'] = array('EQ',$_GET['department_id']);
            $owhere['id_department'] = array('EQ',$_GET['department_id']);
        } else {
            $where['o.id_department'] = array('IN',$id_department);
            $dwhere['id_department'] = array('IN',$id_department);
            $owhere['id_department'] = array('IN',$id_department);
        }

        //搜索名称
//        if(isset($_GET['user_id']) && $_GET['user_id']) {
//            $userids = M('Users')->field('id')->where(array('superior_user_id'=>$_GET['user_id']))->getField('id',true);
//            isset($userids) ? array_push($userids, $_GET['user_id']) : $userids = $_GET['user_id'];
//            $where['o.id_users'] = array('IN',$userids);
//            $dowhere['a.id_users'] = array('IN',$userids);
//        }
        //搜索小组
        if(isset($_GET['group_id']) && $_GET['group_id']) {
            $user_id2=M('GroupUsers')->field('id_users')->where(array('id_department'=>$_GET['group_id']))->select();
            $user_id3=array();
            foreach ($user_id2 as $k=>$v) {
                $user_id3[$k]=$v['id_users'];
            }
            $user_id2 = implode(',',$user_id3);
            $user_id2 = trim($user_id2,',');
            $where['o.id_users'] = array('IN',$user_id2);
            $dowhere['a.id_users'] = array('IN',$user_id2);
        }

        //选择地区
        if(isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['o.id_zone'] = array('EQ',$_GET['zone_id']);
            $ewhere[] = array('ad.id_zone' => $_GET['zone_id']);
            $dowhere['ad.id_zone'] = $_GET['zone_id'];
            $owhere[] = array('id_zone' => $_GET['zone_id']);
        }

        $M = new \Think\Model();
        $order_tab = M('Order')->getTableName();

        $department = M('Department')->field('id_department,title')->where(array('id_department'=>array('IN',$id_department)))->select();
        $users = M('Users')->field('id,superior_user_id')->where(array('superior_user_id'=>$_SESSION['ADMIN_ID']))->getField('id',true);
        $depart_user = M('Department')->where(array('id_users'=>$_SESSION['ADMIN_ID']))->find();
        $group= M('DepartmentGroup')->field('id_department,title')->where(array('parent_id'=>array('IN',$id_department)))->select();
        $newgroup=array();
        foreach($group as $k => $v){
            $newgroup[$v['id_department']]=$v['title'];
        }
        if($users) {
            if($depart_user) {
                $otherwhere['superior_user_id'] = array('IN',$users);
                $other_users = M('Users')->field('id,superior_user_id')->where($otherwhere)->getField('id',true);
            }
            $uwhere['id'] = !empty($other_users) ? array('IN', array_merge($users,$other_users)) : array('IN',$users);
        } else {
            $uwhere['id'] = !empty($users) ? array('IN',$users) : $_SESSION['ADMIN_ID'];
        }

        $user_names = M('Users')->field('id,user_nicename')->where($uwhere)->getField('id,user_nicename',true);

        $domains = M('Domain')->where($dwhere)->cache(true,600)->getField('id_domain',true);
        if(!empty($domains)){
            $dowhere['a.id_domain'] = array('IN',$domains);
        }

        $dowhere['ad.conversion_at'] = $start_time;
        $userids =  M('Advert')->alias('a')->join('__ADVERT_DATA__ ad ON a.advert_id=ad.advert_id','LEFT')->where($dowhere)->group('a.id_users')->getField('id_users',true);

        $field = 'count(*) as count,SUBSTRING(o.created_at,1,10) AS create_date,o.currency_code,o.id_users,
                SUM(IF(o.id_order_status NOT IN(1,2,3,11,12,13,14,15),1,0)) as effective,
                SUM(IF(o.id_order_status NOT IN(1,2,3,11,12,13,14,15),o.price_total,0)) as effective_price';

        $user_order = $M->table($order_tab.' as o')->field($field)->where($where)->group('o.id_users')->getField('id_users',true);

        if(empty($userids)){
            $user_ids = array_unique($user_order);
        }elseif(empty($user_order)){
            $user_ids = array_unique($userids);
        }else{
            $user_ids = array_unique(array_merge($userids,$user_order));
        }

        $array_result = array();
        foreach ($user_ids as $user_key=>$user_id) {
            $where['o.id_users'] = $user_id;
            $result = $M->table($order_tab.' as o')->field($field)->where($where)->group('o.id_users,o.currency_code')->order('effective DESC')->select();
            if(empty($result)) {
                $result[0]['count'] = 0;
                $result[0]['create_date'] = $start_time;
                $result[0]['id_users'] = $user_id;
                $result[0]['effective'] = 0;
                $result[0]['name'] = empty($user_id) ? '未知' : M('Users')->where(array('id'=>$user_id))->getField('user_nicename');
                $result[0]['effective_price'] = 0;
                $result[0]['expense'] = $this->get_expense_result($start_time, $end_time,false,false,$user_id,$ewhere,$dwhere);//广告费
                $result[0]['quantity'] = 0;//产品件数
                $result[0]['purchase_price'] = 0;//产品采购价
                $result[0]['freight'] = 0;//运费
            } else {
                foreach ($result as $k=>$v) {
                    $product_result = $this->get_expense_result($start_time, $end_time,true,false,$v['id_users'],false,false,$owhere);
                    $result[$k]['name'] = empty($v['id_users']) ? '未知' : M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
                    $result[$k]['effective_price'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price']);
                    $result[$k]['expense'] = $this->get_expense_result($start_time, $end_time,false,false,$v['id_users'],$ewhere,$dwhere);//广告费
                    $result[$k]['quantity'] = $product_result['one_quantity_count'];//产品件数
                    $result[$k]['purchase_price'] = $product_result['one_purchase_price_count'];//产品采购价
                    $result[$k]['freight'] = $product_result['one_freight_count'];//运费
                }
            }
            $array_result[] = $result;
        }

        $result_arr = array();
        foreach ($array_result as $key=>$val) {
            foreach ($val as $k=>$v) {
                $result_arr[$v['id_users']][] = $v;
            }
        }

        $lists = $this->get_arr($result_arr,true);
        $list = $this->array_sort($lists, 'expense', 'desc');

        $total_count_num = $this->count_number($list);

        $zone = M('Zone')->field('id_zone,title')->cache(true,600)->select();
        $zone = array_column($zone, 'title','id_zone');
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看广告详情汇总统计');
        $this->assign('list',$list);
        $this->assign('total_count_num',$total_count_num);
        $this->assign('user_names',$user_names);
        $this->assign('time',date('Y-m-d',strtotime($start_time)));
        $this->assign('department',$department);
        $this->assign('group',$newgroup);
        $this->assign('zone',$zone);
        $this->display();
    }

    /**
     * 每日投放广告统计
     */
    public function every_advert() {
        $where = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
                $createAtArray[] = array('EGT', $_GET['start_time']);
            if($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
            $where[] = array('created_at' => $createAtArray);
        } else {
            $all_day = $this->get_the_month(date('Y-m-d'));
            $createAtArray = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $where[] = array('created_at' => $createAtArray);
        }
        //选择地区
        if(isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['zone_id'] = array('EQ',$_GET['zone_id']);
            $ewhere[] = array('ad.id_zone' => $_GET['zone_id']);
        }
        $where['id_users'] = array('EQ',$_SESSION['ADMIN_ID']);
        $field = 'count(*) as count,SUBSTRING(created_at,1,10) AS create_date,currency_code,
                SUM(IF(id_order_status NOT IN(1,2,3,11,12,13,14,15),1,0)) as effective,
                SUM(IF(id_order_status NOT IN(1,2,3,11,12,13,14,15),price_total,0)) as effective_price';

        $result = M('Order')->field($field)
                ->where($where)
                ->group('create_date,currency_code')
                ->order('create_date ASC')
                ->select();

        foreach ($result as $k=>$v) {
            $time = $v['create_date'];
            $result[$k]['effective_price'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price']);
            $result[$k]['expense'] = $this->get_expense_result($time, date('Y-m-d',strtotime("$time +1 day")),false,true,false,$ewhere);//广告费
        }

        $result_arr = array();
        foreach ($result as $k=>$v) {
            $result_arr[$v['create_date']][] = $v;
        }

        $list = $this->get_arr($result_arr);

        $total_count_num = $this->count_number($list);

        $zone = M('Zone')->field('id_zone,title')->cache(true,600)->select();
        $zone = array_column($zone, 'title','id_zone');
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看每日投放广告统计');
        $this->assign('list',$list);
        $this->assign('total_count_num',$total_count_num);
        $this->assign('zone',$zone);
        $this->display();
    }

    /**
     * 每日投放广告统计
     */
    public function export_every_advert() {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '日期','总订单','有效单' ,'营业额','客单价','广告费', '平均广告费', '采购成本','运费成本', 'ROI','有效单占比'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }


        $where = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            if($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
            $where[] = array('created_at' => $createAtArray);
        } else {
            $all_day = $this->get_the_month(date('Y-m-d'));
            $createAtArray = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $where[] = array('created_at' => $createAtArray);
        }
        //选择地区
        if(isset($_GET['zone_id']) && $_GET['zone_id']) {
            $where['zone_id'] = array('EQ',$_GET['zone_id']);
            $ewhere[] = array('ad.id_zone' => $_GET['zone_id']);
        }
        $where['id_users'] = array('EQ',$_SESSION['ADMIN_ID']);
        $field = 'count(*) as count,SUBSTRING(created_at,1,10) AS create_date,currency_code,
                SUM(IF(id_order_status NOT IN(1,2,3,11,12,13,14,15),1,0)) as effective,
                SUM(IF(id_order_status NOT IN(1,2,3,11,12,13,14,15),price_total,0)) as effective_price';

        $result = M('Order')->field($field)
            ->where($where)
            ->group('create_date,currency_code')
            ->order('create_date ASC')
            ->select();

        foreach ($result as $k=>$v) {
            $time = $v['create_date'];
            $result[$k]['effective_price'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price']);
            $result[$k]['expense'] = $this->get_expense_result($time, date('Y-m-d',strtotime("$time +1 day")),false,true,false,$ewhere);//广告费
        }

        $result_arr = array();
        foreach ($result as $k=>$v) {
            $result_arr[$v['create_date']][] = $v;
        }

        $list = $this->get_arr($result_arr);

        $total_count=0;//总计订单
        $total_effective=0;//总计有效单
        $total_effective_price=0;//总计营业额
        $total_advert_price=0;//总计广告费
        $total_purchase_price=0;//总计采购价
        $total_qty=0;//总计数量
        $total_freight=0;//总计运费
        foreach ($list as $key=>$val) {
            $total_count=$total_count+$val['count'];
            $total_effective=$total_effective+$val['effective'];
            $total_effective_price=$total_effective_price+$val['effective_price'];
            $total_advert_price=$total_advert_price+$val['expense'];
            $total_freight=$total_freight+$val['freight'];
            $total_purchase_price=$total_purchase_price+$val['purchase_price'];
            $total_qty=$total_qty+$val['quantity'];
        }

        $total_price = round($total_effective_price/$total_effective,2);//总计客单价
        $total_ad_average_price = round($total_advert_price/$total_effective,2);//总计平均广告费
        $total_roi = $total_advert_price != 0 ? round($total_effective_price/$total_advert_price,2) : 0;//总计ROI

        $cReturns = ($total_effective/$total_count)*100;
        $total_count_price = round($cReturns,2).'%';//有效单占比

        $zone = M('Zone')->field('id_zone,title')->cache(true,600)->select();
        $zone = array_column($zone, 'title','id_zone');

        if(!empty($list)){
            $data[] = array(
                "总计", $total_count, $total_effective, $total_effective_price, $total_price, $total_advert_price, $total_ad_average_price, $total_purchase_price,
                $total_qty,$total_roi,$total_count_price
            );
            foreach($list as $key =>$val){
                $data[] = array(
                    $val['create_date'],$val['count'],$val['effective'],$val['effective_price'],round($val['effective_price']/$val['effective'],2),!empty($val['expense']) ? $val['expense'] : 0,
                    round($val['expense']/$val['effective'],2),$val['purchase_price'],$val['freight'],round($val['effective_price']/$val['expense'],2),(round($val['effective']/$val['count'],2)*100).'%'
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
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出每日投放广告统计');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '每日投放广告统计.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '每日投放广告统计.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    //获取指定日期所在月的第一天和最后一天
    protected function get_the_month($date) {
        $firstday = date("Y-m-01", strtotime($date));
        $lastday = date("Y-m-d", strtotime("$firstday +1 month -1 day"));
        return array('first' => $firstday, 'last' => $lastday);
    }

    /**
     * @param $array
     * @return array 广告数据汇总
     */
    protected function count_number($array) {
        $total_count=0;//总计订单
        $total_effective=0;//总计有效单
        $total_effective_price=0;//总计营业额
        $total_advert_price=0;//总计广告费
        $total_freight=0;//总计运费
        $total_purchase_price=0;//总计采购价
        $total_qty=0;//总计数量
        $total_count_signed=0;//签收单
        foreach ($array as $key=>$val) {
            $total_count=$total_count+$val['count'];
            $total_effective=$total_effective+$val['effective'];
            $total_count_signed=$total_count_signed+$val['count_signed'];
            $total_effective_price=$total_effective_price+$val['effective_price'];
            $total_advert_price=$total_advert_price+$val['expense'];
            $total_freight=$total_freight+$val['freight'];
            $total_purchase_price=$total_purchase_price+$val['purchase_price'];
            $total_qty=$total_qty+$val['quantity'];
        }

        $total_price = round($total_effective_price/$total_effective,2);//总计客单价
        $total_ad_average_price = round($total_advert_price/$total_effective,2);//总计平均广告费
        $total_roi = $total_advert_price != 0 ? round($total_effective_price/$total_advert_price,2) : 0;//总计ROI

        $cReturns = ($total_effective/$total_count)*100;
        $total_count_price = round($cReturns,2).'%';//有效单占比
        $signed_rate = round(($total_count_signed/$total_effective)*100,2).'%';//签收单占比
        $tReturns = ($total_effective_price/($total_advert_price+$total_freight+$total_purchase_price));


        $total_tzhb = round($tReturns*100,2).'%';//投资回报率

        $Returns = (($total_effective_price-$total_advert_price-$total_freight)-($total_purchase_price))/($total_effective_price*0.8);
        $total_lr = round($Returns*100,2).'%';//利润率

        $result = array(
            'total_count'=>$total_count,
            'total_effective'=>$total_effective,
            'total_effective_price'=>$total_effective_price,
            'total_advert_price'=>$total_advert_price,
            'total_freight'=>$total_freight,
            'total_purchase_price'=>$total_purchase_price,
            'total_qty'=>$total_qty,
            'total_price'=>$total_price,
            'total_ad_average_price'=>$total_ad_average_price,
            'total_roi'=>$total_roi,
            'total_count_price'=>$total_count_price,
            'total_tzhb'=>$total_tzhb,
            'total_lr'=>$total_lr,
            'total_count_signed'=>$total_count_signed,
            'signed_rate'=>$signed_rate

        );
        return $result;
    }

    /**
     * 处理数组相加
     */
    protected function get_arr($array,$user=false) {
        $item=array();
        foreach($array as $res_k=>$res_v){
            foreach($res_v as $k=>$v) {
                if($user) {
                    if(!isset($item[$v['id_users']])){
                        $item[$v['id_users']]=$v;
                    }else{
                        $item[$v['id_users']]['count']+=$v['count'];
                        $item[$v['id_users']]['count_signed']+=$v['count_signed'];
                        $item[$v['id_users']]['effective']+=$v['effective'];
                        $item[$v['id_users']]['effective_price']+=$v['effective_price'];
                    }
                } else {
                    if(!isset($item[$v['create_date']])){
                        $item[$v['create_date']]=$v;
                    }else{
                        $item[$v['create_date']]['count']+=$v['count'];
                        $item[$v['create_date']]['count_signed']+=$v['count_signed'];
                        $item[$v['create_date']]['effective']+=$v['effective'];
                        $item[$v['create_date']]['effective_price']+=$v['effective_price'];
                    }
                }
            }
        }
        return $item;
    }

    /**
     * 获取广告费，产品采购价，运费
     */
    protected function get_expense_result($start_time,$end_time,$is_pro=true,$is_user=false,$one_use=false,$ewhere=false,$dwhere=false,$owhere=false) {
        $M = new \Think\Model();
        $advert_tab = M('Advert')->getTableName();
        $advert_data_tab = M('AdvertData')->getTableName();
        $product_sku = M('ProductSku')->getTableName();
        $order_item_tab = M('OrderItem')->getTableName();
        $productCt_tab = M('ProductCost')->getTableName();
        $order_tab = M('Order')->getTableName();
        $product_tab = M('Product')->getTableName();
        $createArray = array();
        $createArray[] = array('EGT', $start_time);
        $createArray[] = array('LT', $end_time);
        $owhere[] = array('created_at' => $createArray);
        if($is_user) {$owhere[] = array('id_users' => $_SESSION['ADMIN_ID']);$ewhere[] = array('ad.id_users_today' => $_SESSION['ADMIN_ID']);}
        if($one_use) {$owhere[] = array('id_users' => $one_use);$ewhere[] = array('a.id_users' => $one_use);}
        if($one_use==='0') $ewhere[] = array('a.id_users' => $one_use);


        if(!$is_pro) {
            if(!$is_user) {
                $id_domains = M('Domain')->where($dwhere)->cache(true,600)->getField('id_domain',true);
                if(!empty($id_domains)) $ewhere[] = array('a.id_domain'=>array('IN',$id_domains));
            }
//            $ewhere[] = array('a.advert_status'=>1);
            $ewhere[] = array('ad.conversion_at' => $start_time);
            $expenses = $M->table($advert_tab.' as a')->join('LEFT JOIN '.$advert_data_tab.' as ad ON ad.advert_id=a.advert_id')->field('sum(ad.expense) expense')
                            ->where($ewhere)->find();

            return round($expenses['expense']*6.8,2);
        } else {
            $owhere['id_order_status'] = array('IN',OrderStatus::get_effective_status());
//            $id_orders = M('Order')->where($owhere)->getField('id_order',true);
            $subQuery =  M('Order')->where($owhere) ->field('id_order')->buildSql(); //子查询

//            if($id_orders) {
                $product_result = $M->table($order_item_tab.' as oi')
                        ->join('LEFT JOIN '.$product_sku.' as p ON p.id_product_sku=oi.id_product_sku')
                        ->join("{$product_tab} pt on pt.id_product=oi.id_product",'left')
                        ->join("{$order_tab} o on o.id_order=oi.id_order",'left')
                        ->join("{$productCt_tab} pc on pc.ID_PRODUCT_SKU=oi.id_product_sku  and pc.ID_DEPARTMENT=o.id_department ",'left')
                        ->field('o.id_order,o.id_department,oi.id_product_sku,p.weight,oi.quantity,p.purchase_price,pc.PRICECOST as pricecost,o.id_zone,pt.id_classify')

                        ->where("oi.id_order in ({$subQuery})")->select();
//        var_dump(count($product_result));die();
//            } else {
//                $product_result = array(
//                    array(
//                        'weight'=>0,
//                        'quantity'=>0,
//                        'purchase_price'=>0
//                    )
//                );
//            }

//                        echo count($product_result);
            $shipping = M('Shipping')->where(array('id_shipping'=>25))->find();//天马物流

            $one_freight_count = 0;//一天总的运费
            $one_quantity_count = 0;//一天总的数量
            $one_purchase_price_count = 0;//一天总的采购价
            foreach ($product_result as $key=>$val) {
//                $purchase_price = $val['purchase_price']*$val['quantity'];
                $purchase_price = $val['pricecost']*$val['quantity'];
                $freight =$this->freightCount($val['id_zone'], $val['id_classify'], $val['weight']);
                $one_freight_count = $one_freight_count+$freight;
                $one_quantity_count = $one_quantity_count+$val['quantity'];
                $one_purchase_price_count = $one_purchase_price_count+$purchase_price;
            }
            return array(
                'one_freight_count' => $one_freight_count,
                'one_quantity_count' => $one_quantity_count,
                'one_purchase_price_count' => $one_purchase_price_count
            );
        }
    }

    protected function  freightCount($id_zone,$type,$weight){
        $cnt=0;
        $freightArr=$this->freightArr;
        $curarr=$freightArr[$id_zone];
        if(empty($curarr)){
            return $cnt;
        }
//        首重价格+（包裹重量-首重重量）*续重价格
        $typekey=  array_keys($curarr);
        if(in_array(0, $typekey)){
            $infodata=$curarr[0];
        }else  if(in_array(4, $typekey)){
            if($type==1||$type==2){
                $infodata=$curarr[4];
            }else{
                $infodata=$curarr[$type];
            }
        }else{
            $infodata=$curarr[$type];
        }

        if($weight>$infodata['first_weight']){
            $cnt= $infodata['first_weight_price']+($weight-$infodata['first_weight'])/$infodata['continued_weight']*$infodata['continued_weight_price']+$infodata['procedure_price'];
        }else{
            $cnt=$infodata['first_weight_price']+$infodata['procedure_price'];
        }
        return $cnt;
    }


    /**
     * 货币汇率换算
     */
    protected function get_exchange_rate($currency_code,$price) {
        if(($currency_code && $price) || $price==0) {
            switch ($currency_code) {
                case 'MOP':
                    $prices = round($price*0.86,2);//澳门元汇率
                break;
                case 'USD':
                    $prices = round($price*6.89,2);//美元汇率
                break;
                case 'TWD':
                    $prices = round($price/4.7,2);//台币汇率
                break;
                case 'HKD':
                    $prices = round($price*0.89,2);//港币汇率
                break;
                case 'NTD':
                    $prices = round($price/4.7,2);//新台币汇率
                break;
                case 'SGD':
                    $prices = round($price*4.9676,2);//新加坡汇率
                break;
                case 'JPY':
                    $prices = round($price/16.9,2);//日元汇率
                break;
                case 'VND':
                    $prices = round($price*0.0002986,2);//越南盾汇率
                break;
                case 'Rp':
                    $prices = round($price*0.00051,2);//印尼盾汇率
                break;
                case 'AED':
                    $prices = round($price*1.874,2);//迪拉姆汇率
                break;
                case 'INR':
                    $prices = round($price*0.1052,2);//印度卢比汇率
                break;
                case 'THB':
                    $prices = round($price*0.1997,2);//泰铢汇率
                break;
                case 'RUB':
                    $prices = round($price*0.1204,2);//俄罗斯卢布汇率
                break;
                case '林吉特':
                    $prices = round($price*1.5825,2);//马来西亚林吉特汇率
                break;
                case 'RMB':

                    $prices = $price;//人民币
                break;
                default:
                    $prices = $price;
            }
            return $prices;
        } else {
            return $price;
        }
    }
    /**
    *部门广告费统计    --Lily 2017-11-09
    */
    public function department_cost(){
        //地区
        $zones = M('Zone')->getField('id_zone,title',true);
         //筛选时间 advert->converat  order->create_at
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();$conversionArr = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            $conversionArr[] = array('EGT', $_GET['start_time']);
            if($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
                $conversionArr[] = array('ELT', $_GET['end_time']);
            }
            $where_o[] = array('o.created_at' => $createAtArray);
            $where_ad[] = array('conversion_at'=>$conversionArr);
        }
        if(isset($_GET['id_zone']) && $_GET['id_zone']){
            $where_o['id_zone'] = $_GET['id_zone'];
            $where_ad['a.id_zone'] = $_GET['id_zone'];
        }
       //部门限制搜素
        if(isset($_GET['department_id']) && $_GET['department_id']!=="0"){
            $department_id = $_GET['department_id'];
            }else if(isset($_GET['department_id']) && $_GET['department_id']=="0"){
             $department_id = array("IN",$_SESSION['department_id']);
             }else{
            $department_id = $_SESSION['department_id'][0];
         }
         // dump($department_id);
        //total_qty_ordered -》 订单购买数  price_total-》订单总价格 
         $field = 'o.id_users,o.id_order,o.currency_code,id_department,u.user_nicename,
                SUM(IF(o.id_order_status NOT IN(1,2,3,4,5,6,7,11,12,13,14,15,28,29,30),o.price_total,0)) as effective_price,
                SUM(IF(o.id_order_status NOT IN(1,2,3,4,5,6,7,11,12,13,14,15,28,29,30),1,0)) as total_order';
        //广告费统计
        $where_ad['a.advert_status'] = 1;
        $expense = M("Advert")->alias("a")
                    ->join("__ADVERT_DATA__ AS ad ON ad.advert_id=a.advert_id","LEFT")
                    ->field("SUM(ad.expense) as expense,a.id_users")
                    ->where($where_ad)
                    ->group("a.id_users")
                    ->select();
        // dump($expense);
        //用户
        $users = M("Users")->field("id,user_nicename")->where()->select();
        $users = array_column($users, "user_nicename","id");
        $expense = array_column($expense, "expense","id_users");
        // $where_o['ru.role_id'] = array("IN",array(28,29,30));
        $role = M("RoleUser")->where("role_id IN (28,29,30)")->getField("user_id",true);
        $usersId = implode(",",array_unique($role));
        $where_o['o.id_users'] = array("IN",$usersId);
        $order_data = M("Order")->alias("o")
                    ->join("__USERS__ AS u ON o.id_users=u.id","LEFT")
                    ->field($field)->where($where_o)
                    ->group("o.id_users")->select();
        $data = [];
        $whereD['type'] = 1;
        if(is_array($department_id)){
            //全部部门搜索
            $depart = M("Department")->field("id_department,title,id_users")->where(['id_department'=>['IN',$_SESSION['department_id']]])->where($whereD)->select();
            foreach($order_data as $k=>$v){
                if($v['total_order']==0) continue;
                $de = $v['id_department'];
                $data[$de]['detail'][$k]['id_order'] = $v['id_order'];
                $data[$de]['detail'][$k]['user_nicename'] = $v['user_nicename'];
                $data[$de]['detail'][$k]['total_ordered'] = $v['total_order'];
                $data[$de]['detail'][$k]['effective_price'] =$this->get_exchange_rate($v['currency_code'], $v['effective_price']);//营业额
                $data[$de]['detail'][$k]['expense'] = $this->get_exchange_rate("USD", $expense[$v['id_users']]);//广告费
                $data[$de]['detail'][$k]['ROI'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price'])/$this->get_exchange_rate("USD", $expense[$v['id_users']]); //ROI  营业额除以广告费
                 foreach ($depart as $kk => $vv) {
                    # code...
                   if($vv['id_department']==$v['id_department']){
                        $data[$de]['department_name'] = $vv['title'];
                        $data[$de]['leader_name'] = $users[$vv['id_users']];
                        
                    }
                }
                $data[$de]['total'] = '总计';
                $data[$de]['order_t']  = array_sum(array_column($data[$de]['detail'], "total_ordered"));
                $data[$de]['total_exp']  = array_sum(array_column($data[$de]['detail'], "expense"));
                $data[$de]['total_effective_price']  = array_sum(array_column($data[$de]['detail'], "effective_price"));
                $data[$de]['total_exp_average']  = $data[$de]['total_exp']/$data[$de]['order_t'];
                $data[$de]['total_effective_average']  = $data[$de]['total_effective_price']/$data[$de]['order_t'];
                $data[$de]['total_ROI_average']  = $data[$de]['total_effective_price']/$data[$de]['total_exp'];
            
        }
        }else{
            //单个部门搜索 默认显示一个部门
            $depart = M("Department")->field("id_department,title,id_users")->where("id_department=".$department_id)->where($whereD)->find();
            foreach($order_data as $k=>$v){
                $de = $department_id;
                if($v['id_department'] == $department_id){
                    // if($v['id_users'] == $depart['id_users']) continue;
                    if($v['total_order']==0) continue;
                     $data[$de]['detail'][$k]['id_order'] = $v['id_order'];
                    $data[$de]['detail'][$k]['user_nicename'] = $v['user_nicename'];
                    $data[$de]['detail'][$k]['total_ordered'] = $v['total_order'];
                    $data[$de]['detail'][$k]['effective_price'] =$this->get_exchange_rate($v['currency_code'], $v['effective_price']);//营业额
                    $data[$de]['detail'][$k]['expense'] = $this->get_exchange_rate("USD", $expense[$v['id_users']]);//广告费
                    $data[$de]['detail'][$k]['ROI'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price'])/$this->get_exchange_rate("USD", $expense[$v['id_users']]);//ROI  营业额除以广告费

                    $data[$de]['department_name'] = $depart['title'];
                    $data[$de]['leader_name'] = $users[$depart['id_users']];
                    $data[$de]['total'] = '总计';
                    $data[$de]['order_t']  = array_sum(array_column($data[$de]['detail'], "total_ordered"));
                    $data[$de]['total_exp']  = array_sum(array_column($data[$de]['detail'], "expense"));
                    $data[$de]['total_effective_price']  = array_sum(array_column($data[$de]['detail'], "effective_price"));
                    $data[$de]['total_exp_average']  = $data[$de]['total_exp']/$data[$de]['order_t'];
                    $data[$de]['total_effective_average']  = $data[$de]['total_effective_price']/$data[$de]['order_t'];
                    $data[$de]['total_ROI_average']  = $data[$de]['total_effective_price']/$data[$de]['total_exp'];
                }
                
                 }
        }
        // dump($data);
        $wherede['id_department'] =array("IN",$_SESSION['department_id']);
        $wherede['type'] =1;
        $department = M("Department")->where($wherede)->select();
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看部门广告费用统计');
        $this->assign("department",$department);//dump($department);
        $this->assign("list",$data);
        $this->assign("zone_list",$zones);
        $this->display();
    }


    /**
     * 查看部门广告月数据
     */
    public function month_post(){

        $zones = M('Zone')->getField('id_zone,title',true);
        $where = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();$conversionArr = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            $conversionArr[] = array('EGT', $_GET['start_time']);
            if($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
                $conversionArr[] = array('ELT', $_GET['end_time']);
            }
            $where[] = array('o.created_at' => $createAtArray);
            $where_o[] = array('o.created_at' => $createAtArray);
            $where_ad[] = array('conversion_at'=>$conversionArr);
        }
        else {
            $all_day = $this->get_the_month(date('Y-m-01', strtotime(date("Y-m-d"))));
            $createAtArray = array();$conversionArr = array();
            $createAtArray[] = array('EGT', $all_day['first']);
            $createAtArray[] = array('LT', $all_day['last']);
            $conversionArr[] = array('EGT', $all_day['first']);
            $conversionArr[] = array('ELT', $all_day['last']);
            $where[] = array('o.created_at' => $createAtArray);
            $where_o[] = array('o.created_at' => $createAtArray);
            $where_ad[] = array('conversion_at'=>$conversionArr);
        }
        if(isset($_GET['zone_id']) && $_GET['zone_id']){
            $where['id_zone'] = $_GET['zone_id'];
            $where_a['id_zone'] = $_GET['zone_id'];
            $where_test['a.id_zone'] = $_GET['zone_id'];
        }
        $department_id = $_SESSION['department_id'];
//        $department = M('Department')->where('type=1')->select();
        $department = array();
        foreach($department_id as $v){
            $department[$v]= M('Department')->where(array('type'=>1,'id_department'=>$v))->getField('title');
        }
 
        //默认显示有权限的第一个部门 jiangqinqing 20171109
        if($_GET['department_id'] == 1000){
            $department_id = implode(',',$department_id);
            $department_id = trim($department_id,',');
        }else{
            $department_id  =   $_GET['department_id'] ?$_GET['department_id'] : $department_id[0];  
        }
        
        //搜索小组
        if(isset($_GET['group_id']) && $_GET['group_id']) {
            $user_id2=M('GroupUsers')->field('id_users')->where(array('id_department'=>$_GET['group_id']))->select();
            $user_id3=array();
            foreach ($user_id2 as $k=>$v) {
                $user_id3[$k]=$v['id_users'];
            }
            $user_id2 = implode(',',$user_id3);
            $user_id2 = trim($user_id2,',');
            $where['o.id_users'] = array('IN',$user_id2);
        }
        $M = new \Think\Model();
        $order_tab = M('Order')->getTableName();
        $advert_tab = M('Advert')->getTableName();
        $advert_data_tab = M('AdvertData')->getTableName();
        $order_item_tab = M('OrderItem')->getTableName();
        $product_sku = M('ProductSku')->getTableName();
//        $role_user = M('RoleUser')->getTableName();
//        $product = M('Product')->getTableName();
        $shipping = M('Shipping')->where(array('status'=>1))->select();//天马物流
        $shippings = array_column($shipping,null,'id_shipping');

        $where['o.id_department'] = array('IN',$department_id);
        $group= M('DepartmentGroup')->field('id_department,title')->where(array('parent_id'=>$where['o.id_department']))->select();
        $newgroup=array();
        foreach($group as $k => $v){
            $newgroup[$v['id_department']]=$v['title'];
        }
//        $where['ru.role_id'] = array('between',array('28','30'));
        $field = 'o.id_users,o.id_order,o.currency_code,
                SUM(IF(o.id_order_status NOT IN(1,2,3,4,5,6,7,11,12,13,14,15,28,29,30),1,0)) as effective,
                SUM(IF(o.id_order_status NOT IN(1,2,3,4,5,6,7,11,12,13,14,15,28,29,30),o.price_total,0)) as effective_price,
                SUM(IF(o.id_order_status NOT IN(1,2,3,4,5,6,7,11,12,13,14,15,28,29,30),o.price_total,0)) as total_order';
//        $count = $M->table($order_tab.' as o LEFT JOIN '.$role_user. ' as ru on ru.user_id = o.id_users')->field($field)
//            ->where($where)
//            ->group('id_users,currency_code')
//            ->select();
//        $result = $M->table($order_tab.' as o LEFT JOIN '.$role_user. ' as ru on ru.user_id = o.id_users')->field($field)
        //分组计算
        $result = $M->table($order_tab.' as o ')->field($field)
            ->where($where)
            ->order('total_order desc')
            ->group('id_users,currency_code')
            ->select();
        //获取订单.得到各个单的运费
        $result_arr = $M->table($order_tab.' as o ')->field("o.id_users,o.id_order,o.currency_code,o.id_shipping,os.weight")
            ->join("__ORDER_SHIPPING__ os ON os.id_order = o.id_order")
            ->where($where)
            ->where(["o.id_order_status NOT IN(1,2,3,4,5,6,7,11,12,13,14,15,28,29,30)"])
            ->select();
        $shipping_money = [];
        $purchase_money = [];
        foreach($result_arr as $ra) {
            $shipping_money[$ra['id_users']]['shipping_money'] += get_freight($ra['weight'], $shippings[$ra['id_shipping']]['first_weight'], $shippings[$ra['id_shipping']]['first_weight_price'], $shippings[$ra['id_shipping']]['continued_weight'], $shippings[$ra['id_shipping']]['continued_weight_price']); //运费总价

            $p_money = M("OrderItem")->alias('oi')
                ->field('oi.id_product_sku,oi.quantity,count(pi.id_product_sku) as count, sum(pi.price) as price')
                ->join("__PURCHASE_INITEM__ pi ON pi.id_product_sku = oi.id_product_sku")
                ->where(['oi.id_order'=> $ra['id_order']])
                ->group('pi.id_product_sku')
                ->select();
            foreach($p_money as $p) {
                $purchase_money[$ra['id_users']]['purchase_money'] += round( ($p['price']/$p['count'])*$p['quantity'],2);
            }
        }
        $a = $temporary = array();
        foreach ($result as $k=>$v) {
            $result[$k]['effective_price'] = $this->get_exchange_rate($v['currency_code'], $v['effective_price']);
            if(!in_array($v['id_users'],$temporary)){
                array_push($temporary,$v['id_users']);
                $a[$v['id_users']]['effective'] = (int)$v['effective'];
                $a[$v['id_users']]['id_users'] = $v['id_users'];
                $a[$v['id_users']]['effective_price'] = (float)$result[$k]['effective_price'];
                $a[$v['id_users']]['total_order'] =(int) $v['total_order'];
            }else{
                $a[$v['id_users']]['effective'] += (int)$v['effective'];
                $a[$v['id_users']]['effective_price'] +=(float) $result[$k]['effective_price'];
                $a[$v['id_users']]['total_order'] += (int)$v['total_order'];

            }
        }
        foreach ($a as $k=>$v) {
            $where_a['id_users'] = $v['id_users'];
            $where_test['a.id_users'] = $v['id_users'];
            $expense = $M->table($advert_tab.' as a')->join('LEFT JOIN '.$advert_data_tab.' as ad ON ad.advert_id=a.advert_id')->field('sum(expense) as expense')
                ->where($where_test)->where($where_ad)->find();
            $product_result = $M->table($order_tab.' as o')->join('LEFT JOIN '.$order_item_tab.' as oi ON oi.id_order= o.id_order')->join('LEFT JOIN '.$product_sku.' as ps ON ps.id_product_sku=oi.id_product_sku')->field('sum(ps.weight) as weight ,sum(oi.quantity) quantity,sum(ps.purchase_price) as purchase_price')
                ->where($where_a)->where($where_o)->find();
            $a[$k]['expense'] = $this->get_exchange_rate('USD', $expense['expense']);//广告费
            $a[$k]['weight'] = $product_result['weight'];//产品重量
            $a[$k]['quantity'] = $product_result['quantity'];//产品件数
            $a[$k]['purchase_price'] = $product_result['purchase_price'];//产品采购价
            $a[$k]['name'] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');//名称
            //总运费
            $a[$k]['freight'] = $shipping_money[$v['id_users']]['shipping_money'];
            //总采购价
            $a[$k]['purchase_price_all'] = $purchase_money[$v['id_users']]['purchase_money'];
        }
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看每日投放广告投放数据月统计');
        $this->assign('zones',$zones);
        $this->assign('department',$department);
        $this->assign('selectdepartment',$_GET['department_id']?$_GET['department_id']:$department_id[0]);
        $this->assign('group',$newgroup);
        $this->assign('lists',$a);
        $this->display();

    }

    /**
     * 二维数组根据某个字段进行排序
     * @param type $arr 二维数组
     * @param type $keys 要排序的字段
     * @param type $type 排序方式，默认升序
     */
    public function array_sort($arr, $keys, $type = 'asc') {
        $keysvalue = $new_array = array();
        foreach ($arr as $k => $v) {
            $keysvalue[$k] = $v[$keys];
        }
        if ($type == 'asc') {
            asort($keysvalue);
        } else {
            arsort($keysvalue);
        }
        reset($keysvalue);
        foreach ($keysvalue as $k => $v) {
            $new_array[$k] = $arr[$k];
        }
        return $new_array;
    }

}
