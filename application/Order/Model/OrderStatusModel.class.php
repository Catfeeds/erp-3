<?php
namespace Order\Model;
use Common\Model\CommonModel;
use Order\Lib\OrderStatus;

class OrderStatusModel extends CommonModel {
    protected $page;
    public function _initialize() {
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        //array('id_department', 'require', '部门不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        //array('title', 'require', '标题不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        //array('model', 'require', 'SKU不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    );

	protected function _before_write(&$data) {
		parent::_before_write($data);
	}
    public function page($total_size = 1, $page_size = 0, $current_page = 1, $listRows = 6, $pageParam = '', $pageLink = '', $static = FALSE){
        if ($page_size == 0) {
            $page_size = C("PAGE_LISTROWS");
        }
        if (empty($pageParam)) {
            $pageParam = C("VAR_PAGE");
        }
        $Page = new \Page($total_size, $page_size, $current_page, $listRows, $pageParam, $pageLink, $static);
        $Page->SetPager('Admin', '{first}{prev}&nbsp;{liststart}{list}{listend}&nbsp;{next}{last}', array("listlong" => "9", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => ""));
        return $Page;
    }
    /**
     * 获取状态
     * @param bool $status_id
     * @return array
     */
    public function get_status_label($status_id=false){
        $list = D("Order/OrderStatus")->field('id_order_status,title')->where('status=1')->cache(true,13600)->select();
        $array = array_column($list,'title','id_order_status');
        return $status_id?$array[$status_id]:$array;
    }
    /**
     * 获取用户对应的订单列表
     * @param bool $set_where
     * @return array
     */
    public function get_untreated_order_byusers($set_where=false,$is_tf=false,$admin_id =""){
        /** @var \Order\Model\OrderModel $order_model */
        $order_model = D("Order/Order");
        $where       = $order_model->form_where($_GET);
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $admin_id = $_SESSION['ADMIN_ID'];
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
        }
        
        if($is_tf == false){
            $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";//货到付款订单，过滤已经支付的
        }
        $where['id_order_status'] = isset($set_where['id_order_status'])?$set_where['id_order_status']:array('EQ',1);
        $where['id_users'] = $admin_id;

        //过滤产品
        if($_GET['id_product']){
            $M = new \Think\Model;
            $ord_name = D("Common/Order")->getTableName();
            $ord_ite_name = D("Common/OrderItem")->getTableName();
            $user_name = D("Common/Users")->getTableName();
            $product_where = array('oi.id_product'=>$_GET['id_product'],
                'o.id_department'=>array('IN',$department_id),
                'o.id_order_status'=>$where['id_order_status'],
                'o.id_users'=>array('EQ',$admin_id)
            );
            $find_order = $M->table($ord_name.' AS o LEFT JOIN '.$ord_ite_name.' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where($product_where)
                ->group('oi.id_order')->select();
            $all_id = array_column($find_order,'id_order');

            $where['id_order'] = $all_id?array('IN',$all_id):array('IN',array(0));
        }
        if($set_where){
            $where = array_merge($where,$set_where);
        }
        $count = $order_model->where($where)->count();
        $today_where = $where;
        $today_where['created_at'] = array("EGT", date('Y-m-d'));
        $today_total  = $order_model->where($today_where)->count();
        $form_data  = array();
        $all_domain = D('Common/Domain')
            ->field('`name`,id_domain')->where(array('id_department'=>array('IN',$department_id)))
            ->order('`name` ASC')
            ->select();
        $form_data['domain'] = $all_domain?array_column($all_domain,'name','id_domain'):array();


        $page = $this->page($count, 20);
        $order_list = $order_model->where($where)->order("id_order desc,tel DESC,`first_name` desc,email desc")
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();

        /** @var \Order\Model\OrderBlacklistModel $order_blacklist */
        $order_blacklist = D("Order/OrderBlacklist");
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key]  =  $order_blacklist->black_list_and_ip_address($o);
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
        }
        $all_product = D('Product/Product')
            ->field('id_product,title')->where(array('id_department'=>array('IN',$department_id)))
            ->order('id_product desc')->cache(true,86400)->select();

        //echo $baseSql->where($where)->fetchSql(true)->select();
        $shipping = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();
        $all_zone = D('Common/Zone')->field('`title`,id_zone')->order('`title` ASC')->cache(true, 3600)->select();
        $form_data['zone'] = $all_domain?array_column($all_zone,'title','id_zone'):'';
        /*$form_data['track_status'] = D('Order/OrderShipping')->field('status_label as track_status')
            ->where("status_label is not null or status_label !='' ")
            ->group('status_label')->cache(true, 22000)->select();*/
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true,36000)->select();
        $advertiser = array_column($advertiser,'name','id');
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        return array(
            'form_data'   => $form_data,
            'page'        => $page->show('Admin'),
            'today_total' => $today_total,
            'order_total' => $count,
            'order_list'  => $order_list,
            'shipping'    => $shipping,
            'all_product' => $all_product,
            'advertiser'  => $advertiser,
            'all_zone'    => $all_zone,
        );
    }
    /**
     * 获取订单列表
     * @param bool $set_where
     * @return array
     */
    public function get_untreated_order($set_where=false,$is_tf=false){
        /** @var \Order\Model\OrderModel $order_model */

        $order_model = D("Order/Order");
        $where       = $order_model->form_where($_GET,'o.');
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['o.id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['o.id_department']= $_GET['id_department'];
        }
        //筛选ip
        if(isset($_GET['ip']) && !empty($_GET['ip'])){
            $find_order = D('Order/OrderInfo')->field('id_order')->where(array('ip'=> trim($_GET['ip'])))->select();
            if(!empty($find_order)){
                $where['o.id_order'] = array('IN', array_column($find_order, 'id_order'));
            }else{
                $where['o.id_order'] = 0;
            }
        }
        if(isset($_GET['repeat_num']) && $_GET['repeat_num']){
            if($_GET['repeat_num']==1){
                $where['o.order_repeat'] = array("GT",1);
            }else if($_GET['repeat_num']==2){
                $where['o.order_repeat'] = array("EQ",1);
            }else if($_GET['repeat_num']==3){
                 $where['o.order_repeat'] = array("EQ",0);
            }
            
        }
        if($is_tf == false){
            $where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";//货到付款订单，过滤已经支付的
        }
        $where['o.id_order_status'] = isset($set_where['id_order_status'])?$set_where['id_order_status']:array('EQ',1);

        //过滤产品
        if($_GET['id_product']){
            $M = new \Think\Model;
            $ord_name = D("Common/Order")->getTableName();
            $ord_ite_name = D("Common/OrderItem")->getTableName();
            $product_where = array('oi.id_product'=>$_GET['id_product'],
                'o.id_department'=>array('IN',$department_id),
                'o.id_order_status'=>$where['id_order_status']
            );
            $find_order = $M->table($ord_name.' AS o LEFT JOIN '.$ord_ite_name.' AS oi ON o.id_order=oi.id_order')->field('o.id_order')
                ->where($product_where)
                ->group('oi.id_order')->select();
            $all_id = array_column($find_order,'id_order');
            $where['o.id_order'] = $all_id?array('IN',$all_id):array('IN',array(0));
        }
        if($set_where){
            $where = array_merge($where,$set_where);
        }
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id'=>$_SESSION['ADMIN_ID'],'role_id'=>32))->find();
        if($role_user) {
            $belong_zone_id = isset($_SESSION['belong_zone_id'])?$_SESSION['belong_zone_id']:array(0);
            if(!isset($where['o.id_zone'])){
                $where['o.id_zone']=array('IN',$belong_zone_id);
            }
        }
        if(isset($_GET['sku']) && $_GET['sku']){
            $wherep['oii.sku'] = $_GET['sku'];
        }
        if(isset($_GET['inner_name']) && $_GET['inner_name']){
            $wherep['pp.inner_name'] = array("LIKE",'%'.$_GET['inner_name'].'%');
        }
        $today_where = $where;
        $today_where['created_at'] = array('EGT', date('Y-m-d'));
        $today_total  = $order_model->alias('o')->where($today_where)->count();
        $form_data  = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();
        $form_data['domain_address'] = $domain_model->get_all_real_address();
        if(!empty($wherep)){
            $count = $order_model->alias('o')
            ->join("__ORDER_ITEM__ AS oii ON oii.id_order=o.id_order","LEFT")
            ->join("__PRODUCT__ AS pp ON oii.id_product=pp.id_product","LEFT")
            ->where($where)
            ->where($wherep)
            ->count();
         $page = $this->page($count, $this->page);
        $order_list = $order_model->alias('o')
            ->join("__ORDER_ITEM__ AS oii ON oii.id_order=o.id_order","LEFT")
            ->join("__PRODUCT__ AS pp ON oii.id_product=pp.id_product","LEFT")
            ->where($where)
            ->where($wherep)
            ->order("o.id_order asc")
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        }else{
          $count = $order_model->alias('o')
            ->where($where)
            ->count();
        $page = $this->page($count, $this->page);
        $order_list = $order_model->alias('o')
            ->where($where)
            ->order("o.id_order asc")
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();  
        }
        
       /** @var \Order\Model\OrderBlacklistModel $order_blacklist */
        $order_blacklist = D("Order/OrderBlacklist");
        /** @var \Order\Model\OrderItemModel $order_item */
        $order_item = D('Order/OrderItem');
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
      //  $all_zone = $zone_model->all_zone();

        $role_user = M('RoleUser')->field('role_id')->where(array('user_id'=>$_SESSION['ADMIN_ID'],'role_id'=>32))->find();
        if($role_user) {
            $belong_zone_id = isset($_SESSION['belong_zone_id'])?$_SESSION['belong_zone_id']:array(0);

            if(!empty($belong_zone_id)){
                $all_zone = $zone_model->field('`title`,id_zone')->where(['id_zone'=>array('IN',$belong_zone_id)])->order('`title` ASC')->select();
                $all_zone = $all_zone?array_column($all_zone,'title','id_zone'):'';
            }

        }else{
            $all_zone = $zone_model->all_zone();
        }

        //统计sku所有订单
        /*$arr = array();
        $lists = $order_model->where($where)->order("id_order desc,tel DESC,`first_name` desc,email desc")
            ->select();
        foreach ($lists as $key => $o) {
            $lists[$key]['products'] = $order_item->get_item_list($o['id_order'],10);
        }
        foreach($lists as $list){
            foreach($list['products'] as $v){
                array_push($arr,$v['sku']);
            }
        }
        $new = array_count_values($arr);*/
        foreach ($order_list as $key => $o) {
            $order_list[$key]  =  $order_blacklist->black_list_and_ip_address($o);
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order'],10); 
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'],$o['currency_code']);
            $order_list[$key]['zone_error'] = 0;
            $province = trim($o['province']);
            if($o['id_zone']==2 && !in_array($province,array('台湾','台灣'))){
                $order_list[$key]['zone_error'] = 1;
            }elseif($o['id_zone']==3 && $province!='香港'){
                $order_list[$key]['zone_error'] = 1;
            }
        }
        $all_product = D('Product/Product')
            ->field('id_product,title')->where(array('id_department'=>array('IN',$department_id)))
            ->order('id_product desc')->cache(true,86400)->select();

        /** @var \Shipping\Model\ShippingModel $shipping_model */
        $shipping_model = D("Shipping/Shipping");
        $shipping = $shipping_model->all();

        $form_data['zone'] = $all_zone;
        /*$form_data['track_status'] = D('Order/OrderShipping')->field('status_label as track_status')
            ->where("status_label is not null or status_label !='' ")
            ->group('status_label')->cache(true, 22000)->select();*/
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true,36000)->select();
        $advertiser = array_column($advertiser,'name','id');
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $all_zone = $zone_model->all_zone();
        $payment_method = D('Order/Order')->field('payment_method')
            ->where('payment_method!=0 or payment_method!=""')
            ->group('payment_method')->cache(true,36000)->getField('payment_method',true);
            //显示深紫色的名字   --Lily 2017-10-25
        $name_replace = array("孔文吉","尤美女","王定宇","王育敏","王金平","王惠美","王荣璋","江永昌","江啟臣","江永昌","江啟臣","何欣纯","余宛如","吴玉琴","吴志扬","吴秉叡","吴思瑶","吴焜裕","吴琪銘","呂玉玲","呂孫綾","李坤澤","李俊俋","李彥秀","李應元","李鴻鈞","李麗芬","谷辣斯·尤达卡","邱志偉","邱泰源","邱議瑩","周春米","周陳秀霞","林岱樺","林俊憲","林昶佐","林為洲","林淑芬","林德福","林靜儀","林麗嬋","姚文智","施義芳","柯志恩","柯建銘","段宜康","洪宗熠","洪慈庸","徐永明","徐志榮","徐國勇","徐榛蔚","莊瑞雄","郭正亮","陳其邁","陳宜民","陳宜民","陳怡潔","陳明文","陳亭妃","陳素月","陳曼麗","陳雪生","陳超明","陳歐珀","陳瑩","陳學聖","陳賴素美","馬文君","高志鵬","高金素梅","高潞·以用·巴魕剌","張宏陸","張廖萬堅","張麗善","徐淑華","許智傑","許毓仁","曾銘宗","葉宜津","費鴻泰","黃秀芳","黃昭順","黃偉哲","黃國昌","黃國書","杨曜","杨镇浯","廖国栋","管碧玲","蔡其昌","蔡宜餘","蔡培慧","蔡適應","蒋乃辛","蔣萬安","趙天麟","趙正宇","鄭天財","鄭運鵬","鄭麗君","鄭寶清","劉世芳","劉建國","劉櫃豪","盧秀燕","蕭美琴","賴士葆","賴瑞龍","鐘孔炤","鐘佳濱","簡東明","羅明才","羅致政","蘇巧慧","蘇治芬","蘇嘉全","蘇震清","顧立雄","Sufin Sileko","Kawlo Iyun Pacidal","Kolas Yotaka","Sra Kacaw","Uliw Qaljupayare","許淑華");
       return array(
            'form_data'   => $form_data,
            'page'        => $page->show('Admin'),
            'today_total' => $today_total,
            'order_total' => $count,
            'order_list'  => $order_list,
            'shipping'    => $shipping,
            'all_product' => $all_product,
            'advertiser'  => $advertiser,
            'all_zone'  => $all_zone,
            'payment_method' => $payment_method,
            'name_replace' => $name_replace,
            //'sku_num'  =>$new,
        );
    }
    /**
     * 未处理审核后的订单
     * @param $order_id
     * @param $statusId
     * @param bool $comment
     * @return int
     */
    public function approved($order_id, $status_id, $comment = false){
        $order = D("Order/Order")->find($order_id);
        //如果订单状态不是无效订单，不能进行有效订单的操作
        if (!in_array($order['id_order_status'],OrderStatus::invalid_status()))
        {
            return false;
        }
        //2为待发货状态记录，7为无效订单，订单表记录为无效订单，order History记录无效订单的原因
        $update_data = array('id_order_status' => $status_id);
        $_POST['delivery_date'] = strtotime($_POST['delivery_date'])>strtotime($order['create_at'])?$_POST['delivery_date']:$order['create_at'];
        if (isset($_POST['delivery_date']) && $_POST['delivery_date']) {
            $update_data['delivery_date'] = $_POST['delivery_date'];
        } else {
            $update_data['delivery_date'] = strtotime($order['create_at'])>0?$order['create_at']:date('Y-m-d 10:00:00',strtotime('-1 day'));//$order['create_at'] ? $order['create_at'] : date('Y-m-d');
        }
        //备注订单信息
        $update_data['comment'] = $comment;
        $result = D("Common/Order")->where(array('id_order'=>$order['id_order']))->save($update_data);
        if($result){
            /** @var \Order\Model\OrderRecordModel  $order_record */
            $order_record = D("Order/OrderRecord");
            $order_record->addHistory($order['id_order'],$status_id, $comment);
        }
        $return = 1;
        return $return;
    }
    /**
     * 更新订单状态，并且记录更新信息
     * @param $data
     */
    public function update_status_add_history($data){
        if($data['id_order']  && $data['new_status_id']){
//            $update_data = array('id_order_status'=>$data['new_status_id'],'date_delivery'=>date('Y-m-d H:i:s'));
            $update_data = array('id_order_status'=>$data['new_status_id']);
            $result = D("Order/Order")->where(array('id_order'=>$data['id_order']))->save($update_data);
//            if($result){
                /** @var \Order\Model\OrderRecordModel  $order_record */
                $order_record = D("Order/OrderRecord");
                $comment      = $data['comment']?:'';
                $order_record->addHistory($data['id_order'],$data['new_status_id'],4, $comment);
//            }
        }
    }
    
    /**
     * 审核订单统计
     * @param type $type  1,初审 2,终审 3,修改
     * @param type $checkcnt 单数
     * @param type $validcnt 有效单数       
    */
    public function  check_order_statistics($type,$checkcnt=0,$validcnt=0){
        if(!$type){
            return FALSE;
        }        
        $admin_id = $_SESSION['ADMIN_ID'];
        $rolearr=M("roleUser")->where(array('user_id'=>$admin_id))->getField('role_id',TRUE);
        if(in_array(31, $rolearr)||  in_array(32, $rolearr)){//只记录客服专员和组长的操作
            //今天客服是否已经有审核统计数据
            $statisticsinfo=M('orderCheckStatistics')->where(array('id_users'=>$admin_id,'created_at'=>date('Y-m-d')))->find();
            $updateData=[];  
            if($type==1){
                $updateData['first_trial']=$statisticsinfo['first_trial']+$checkcnt;
                $updateData['first_valid']=$statisticsinfo['first_valid']+$validcnt;
            }
            if($type==2){
                $updateData['last_trial']=$statisticsinfo['last_trial']+$checkcnt;            
                $updateData['last_valid']=$statisticsinfo['last_valid']+$validcnt;                          
            }
            if($type==3){
                $updateData['update_cnt']=$statisticsinfo['update_cnt']+1;
            }
            if($statisticsinfo){
                $res=M('orderCheckStatistics')->where(array('id'=>$statisticsinfo['id']))->save($updateData);
            }else{
                $updateData['id_users']=$admin_id;
                $updateData['created_at']=date('Y-m-d');
                $res=M('orderCheckStatistics')->data($updateData)->add();
            }
            if($res!==FALSE){
                return TRUE;
            }
        }
        return FALSE;
    }                
}
