<?php
/**
 * 订单同步
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Product\Controller
 */
namespace Order\Controller;
use Common\Controller\HomebaseController;

class SyncController extends HomebaseController{
	protected $product,$api_url,$status_data;
    protected $domain_advert;
    protected $shipping;
    protected $local_shipping;
    protected $max_order_id = 0;
	public function _initialize() {
		parent::_initialize();
		$this->product = D("Common/Product");
        $this->status_data =  array(
            1=>1,//未处理  旧订单状态ID=>新状态ID
            2=>4,//未配货
            3=>8,//配送中
            4=>9,//已签收
            6=>10,//已退货
            7=>14,//无效订单
            8=>12,//信息不完整
            9=>13,//恶意下单
            10=>2,//待处理
            11=>11,//重复下单
            12=>15,//质量问题/产品破损
            13=>15,//质量问题
            17=>14,//客户取消
            18=>5,//配货中
            19=>6,//缺货
            20=>7,//已配货
            21=>3,//待审核
        );
	}
    /**
     * 同步旧的订单数据到新的ERP
     * 添加参数  action  new_order 只更新  新的订单到系统
     */
    public function index(){
        set_time_limit(0);
        $config = array(
            array('url'=>'http://www.rloxw.com/','id_department'=>1),
            array('url'=>'http://www.tjihg.com/','id_department'=>2),
            array('url'=>'http://www.wfpil.com/','id_department'=>3),
            array('url'=>'http://www.vvcxy.com/','id_department'=>4),
            array('url'=>'http://www.ndrow.com/','id_department'=>5),
            array('url'=>'http://www.pyjuu.com/','id_department'=>7),
        );
        $local_shipping = D("Common/Shipping")->field('id_shipping as id,title')->cache(true,3600)->select();
        $this->local_shipping = array_column($local_shipping,'id','title');

        foreach($config as $item){
            if(!isset($_GET['action'])){
                $this->sync_domain($item);//添加域名到ERP里面   更新最新订单不执行同步域名
            }
            $this->request_data($item);
            $order = D('Order/Order')->where(array('id_department'=>$item['id_department'],'id_increment'=>array('LT',99990000)))
                ->order('id_increment desc')->find();
            if($order){
                $max_order = $order['id_increment'];
                D('Common/TempOrder')->where(array('id'=>$item['id_department']))
                    ->save(array('order_id'=>$max_order));
            }
        }
        echo '建立订单完成<br />'.PHP_EOL;exit();
    }
    /**
     * 第一次 同步旧的订单数据到新的ERP
     */
    public function sync(){
        set_time_limit(0);
        $config = array(
            array('url'=>'http://www.rloxw.com/','id_department'=>1),
            //array('url'=>'http://www.tjihg.com/','id_department'=>2),
            //array('url'=>'http://www.vvcxy.com/','id_department'=>4),
        );
        $local_shipping = D("Common/Shipping")->field('id_shipping as id,title')->cache(true,3600)->select();
        $this->local_shipping = array_column($local_shipping,'id','title');

        foreach($config as $item){
            if(!isset($_GET['action'])){
                $this->sync_domain($item);//添加域名到ERP里面   更新最新订单不执行同步域名
            }
            $this->request_data($item);
            $order = D('Order/Order')->where(array('id_department'=>$item['id_department'],'id_increment'=>array('LT',99990000)))
                ->order('id_increment desc')->find();
            if($order){
                $max_order = $order['id_increment'];
                D('Common/TempOrder')->where(array('id'=>$item['id_department']))
                    ->save(array('order_id'=>$max_order));
            }
        }
        echo '建立订单完成<br />'.PHP_EOL;exit();
    }

    protected function all_user($login_user=false){
        $Data = F('all_api_user');
        $Data = json_decode($Data,true);
        $user = D("Common/Users")->field('user_login,id')->cache(true,36000)->select();
        $all_data = $user?array_column($user,'id','user_login'):'';
        return $login_user?$all_data[$login_user]:$all_data;
    }
    /**
     * 同步域名
     * @param $config
     */
    protected function sync_domain($config){
        $url = $config['url'].'api/Sync/domain_list';
        $id_department = $config['id_department'];
        $get_json = file_get_contents($url);
        $data = $get_json?json_decode($get_json,true):'';
        $page_size = $data['page_size'];

        if($page_size>0){
            for($page=1;$page<=$page_size;$page++){
                $new_url = $url.'?p='.$page;
                echo $new_url.'<br />';
                $get_json = file_get_contents($new_url);
                $data = $get_json?json_decode($get_json,true):'';
                $list = isset($data['status'])&&$data['status']?$data['list']:'';
                $this->add_domain($list,$id_department);
            }
        }
        echo 'Add Domain complete<br /><br />'.PHP_EOL;
    }

    /**
     * 添加域名到新的erp
     * @param $list
     * @param $id_department
     */
    protected function add_domain($list,$id_department){
        if($list && is_array($list)){
            foreach($list as $item){
                $add_data = array(
                    'id_department' => $id_department,
                    'name' => $item['name'],
                    'ip' => $item['ip'],
                    'copy_url' => $item['copy_url'],
                    'smtp_host' => $item['smtp_host'],
                    'smtp_user' => $item['smtp_user'],
                    'smtp_pwd' => $item['smtp_pwd'],
                    'smtp_port' => $item['smtp_port'],
                    'smtp_ssl' => $item['smtp_ssl'],
                    'status' => $item['status'],
                    'created_at' => date('Y-m-d H:i:s',$item['date_add']),
                    //'updated_at' => $item['name'],
                    'updated_at' => date('Y-m-d H:i:s')
                );
               $select =  D("Common/Domain")->where(array('name'=>$item['name']))->find();
                if(!$select){
                    D("Common/Domain")->data($add_data)->add();
                }
            }
        }
    }

    protected function request_data($config){
        $id_department = $config['id_department'];

        $domain_url = $config['url'].'api/Sync/domain_advert';
        $domain_json = file_get_contents($domain_url);
        $domain_data = $domain_json?json_decode($domain_json,true):'';
        $this->domain_advert = $domain_data['list']?array_column($domain_data['list'],'user_login','name'):array();

        $shipping_url = $config['url'].'api/Sync/shipping_list';
        $shipping_json = file_get_contents($shipping_url);
        $shipping_data = $domain_json?json_decode($shipping_json,true):'';
        $this->shipping = $shipping_data['list']?array_column($shipping_data['list'],'title','id'):array();

        if(isset($_GET['action']) && $_GET['action']=='new_order'){
            $temp_order = D('Common/TempOrder')->where(array('id'=>$id_department))->find();
            $this->max_order_id = $temp_order?$temp_order['order_id']:0;
            $get_new_order = $this->max_order_id?'id='.$this->max_order_id:'';
            echo 'Get New Order ====='.PHP_EOL;
        }


        $url = $config['url'].'api/Sync/order_list';

        $get_json = file_get_contents($url.'?'.$get_new_order);
        $data = $get_json?json_decode($get_json,true):'';
        $list = isset($data['status'])&&$data['status']?$data['list']:'';
        $page_size = $data['page_size'];
        $this->add_order($list,$id_department);
        if($page_size>1){
            for($page=2;$page<=$page_size;$page++){
                $new_url = $url.'?p='.$page.'&'.$get_new_order;
                echo '<span style="color:red">'.$new_url.'</span><br />';
                $get_json = file_get_contents($new_url);
                $data = $get_json?json_decode($get_json,true):'';
                $list = isset($data['status'])&&$data['status']?$data['list']:'';
                $this->add_order($list,$id_department);
            }
        }
    }
    protected function add_order($list,$id_department){
        $domain_advert  = $this->domain_advert;
        $old_shipping   = $this->shipping;
        $local_shipping = $this->local_shipping;

        /** @var \Product\Model\ProductOptionModel $attr_model */
        $attr_model = D('Product/ProductOption');
        /** @var \Domain\Model\DomainModel $domain */
        $domain = D("Domain/Domain");
        $new_status = $this->status_data;
        if($list){
            foreach($list as $key=>$order){
                $shipping_id    = $order['shipping_id'];
                $shipping_title = $old_shipping[$shipping_id];
                $ship_id        = $local_shipping[$shipping_title];
                $currency_code = 'TWD';
                switch($order['user_province']){
                    case '台湾':
                    case '台灣':
                        $id_zone=2;
                        $currency_code = isset($order['currency_code'])?$order['currency_code']:'TWD';
                        break;
                    case '香港':
                        $id_zone=3;
                        $currency_code = isset($order['currency_code'])?$order['currency_code']:'HKD';
                        break;
                    case '日本':
                        $id_zone=4;
                        $currency_code = isset($order['currency_code'])?$order['currency_code']:'JPY';
                        break;
                    default:
                        $id_zone= 0;
                }
                $domain_id = $domain->get_domain_id($order['web_url']);
                $user_id   = $this->all_user($domain_advert[$order['web_url']]);
                $current_status_id = $new_status[$order['status_id']];
                $add_data  = array(
                    'id_warehouse' => $order['user_province']=='台湾' or $order['user_province']=='台灣'?2:1,
                    'id_department'=> $id_department,
                    'id_users'=> $user_id,
                    'id_zone' => $id_zone,
                    'identify' => $user_id,
                    'id_shipping' => $ship_id?:0,
                    'id_order_status' => $new_status[$order['status_id']],
                    'id_domain' => $domain_id,
                    'id_increment' => $order['id'],
                    'first_name' => $order['user_name'],
                    'last_name' => '',
                    'country' => $order['user_country'],
                    'tel' => $order['user_tel'],
                    'email' => $order['user_email'],
                    'province' => $order['user_province'],
                    'city' => $order['user_city'],
                    'area' => $order['user_area'],
                    'address' => $order['user_address'],
                    'zipcode' => $order['zip_code'],
                    'remark' => $order['user_remark'],
                    'order_repeat' => $order['order_repeat'],
                    'order_count' => $order['order_total'],
                    'total_qty_ordered' => isset($order['total_qty_ordered'])?$order['total_qty_ordered']:1,
                    'price_total' => $order['grand_total'],
                    'currency_code' => $currency_code,
                    'payment_method' => $order['payment_id']?:0,
                    'payment_status' => $order['payment_status'],
                    'payment_details' => $order['payment_details'],
                    'date_delivery' => $order['delivery_date'],
                    'comment' => $order['order_remark'],
                    'created_at' => $order['create_at'],
                    'date_purchase' => $order['create_at'],
                    'updated_at' => date('Y-m-d H:i:s'),
                );
                $select = D('Order/Order')->where(array('id_increment'=>$order['id']))->find();
                try{
                    if(!$select){
                        $order_id = D('Order/Order')->data($add_data)->add();
                        /** @var \Order\Model\OrderRecordModel  $order_record */
                        $order_record = D("Order/OrderRecord");
                        $order_record->addHistory($order_id,$current_status_id,'1','同步订单到新系统');

                        echo 'add order:'.$order_id.'<br />';
                        $info_data = array(
                            'id_order'=>$order_id,
                            'ip' => $order['ip'],
                            'user_agent' => $order['user_agent'],
                            'ip_address' => $order['ip_address'],
                            'blacklist_level' => $order['blacklist_level'],
                            'blacklist_field' => $order['blacklist_field']
                        );
                        D('Order/OrderInfo')->data($info_data)->add();
                        if($order['products'] && is_array($order['products'])){
                            $order_record->addHistory($order_id,$current_status_id,'1',json_encode($order['products']));

                            foreach($order['products'] as $product){
                                $old_product_id = trim($product['product_id']);
                                $temp_where = array('id_department'=>$id_department,'product_id'=>$old_product_id);
                                $temp_product = D("Common/TempProduct")->where($temp_where)->find();

                                if($temp_product){
                                    $new_product_id = $temp_product['new_product_id'];
                                    $sku_id  = $product['sku_id'];

                                    /*if($sku_id){//如果存在SKU_ID 直接获取 新的SKU_ID
                                        $option_value_where = array(
                                            'id_department'=>$id_department,
                                            'product_id'=>$old_product_id,
                                            'sku_id'=>$sku_id
                                        );

                                    }else{
                                    }*/
                                    try{
                                        $attrs = $product['attrs']?unserialize($product['attrs']):array(0);
                                        $attrs = is_array($attrs)?array('IN',$attrs):0;
                                        $option_value_where = array(
                                            'id_department'=> array('EQ',$id_department),
                                            'product_id'=> array('EQ',$old_product_id),
                                            'value_id'=> $attrs,
                                        );

                                        $select_value = D('Common/TempOptionValue')->where($option_value_where)
                                            ->getField('new_value_id',true);
                                    }catch ( \Exception $e){
                                        print_r($attrs);print_r($order['id']);
                                        print_r($e->getMessage());exit();
                                    }


                                    //产品有属性
                                    if($select_value){
                                        sort($select_value);
                                        $value_string = $select_value?implode(',',$select_value):'';
                                    }else{
                                        //产品无属性
                                        $value_string = 0;
                                    }
                                    if($value_string){
                                        $sku_where = array('option_value'=>$value_string,'id_product'=>$new_product_id);
                                    }else{
                                        //无属性的产品
                                        $sku_where = array('id_product'=>$new_product_id);
                                    }

                                    $select_sku = D('Common/ProductSku')->where($sku_where)->find();
                                    if($select_sku && $select_sku['id_product_sku']){
                                        $add_product = array(
                                            'id_order' => $order_id,
                                            'id_product_sku' => $select_sku['id_product_sku'],
                                            'id_product' => $new_product_id,
                                            'sku' => $select_sku['sku'],
                                            'sku_title' => $select_sku['title'],
                                            'sale_title' => $product['product_title'],
                                            'product_title' => $product['product_title'],
                                            'quantity' => $product['qty'],
                                            'price' => $product['price'],
                                            'total' => $product['total'],
                                            'is_free' => 1,
                                            'attrs' => $select_value?serialize($select_value):'',
                                        );
                                        D('Order/OrderItem')->data($add_product)->add();
                                    }else{
                                        $add_product = array(
                                            'id_order' => $order_id,
                                            'id_product_sku' => '0',
                                            'id_product' => $new_product_id,
                                            'sku' => '---',
                                            'sku_title' => $product['sku_title'],
                                            'sale_title' => $product['product_title'],
                                            'product_title' => $product['product_title'],
                                            'quantity' => $product['qty'],
                                            'price' => $product['price'],
                                            'total' => $product['total'],
                                            'is_free' => 1,
                                            'attrs' => $select_value?serialize($select_value):'',
                                        );
                                        D('Order/OrderItem')->data($add_product)->add();
                                        $this->write_log($order);
                                        //echo D('Common/ProductSku')->where($sku_where)->fetchSql(true)->find();
                                        echo D('Common/TempOptionValue')->where($option_value_where)->fetchSql(true)->select().'  ; '.
                                            D('Common/ProductSku')->where($sku_where)->fetchSql(true)->find().' ;';
                                        echo '插入产品失败,没有找到SKU'.$order['id'].'<br /><br />';
                                    }
                                }else{
                                    echo '没有找到产品'.$order['id'].PHP_EOL;
                                    $this->write_log($order);
                                }
                            }
                        }
                        if(isset($order['delivery_data']) && $order['delivery_data']){
                            $delivery_data = $order['delivery_data'];
                            foreach($delivery_data as $delivery){
                                $shipping_data = array(
                                    'id_shipping'=> $order['shipping_id'],
                                    'id_order'=> $order_id,
                                    'fetch_count'=> $delivery['fetch_count'],
                                    'is_email'=> $delivery['is_email'],
                                    'shipping_name'=> $delivery['shipping_name'],
                                    'track_number'=> $delivery['track_number'],
                                    'status_label'=> $delivery['status_label'],
                                    'date_delivery'=> $order['delivery_date'],
                                    'date_signed'=> $delivery['signed_for_date'],
                                    'status'=> $delivery['status'],
                                    'remark'=> $delivery['remark'],
                                    'is_settlemented'=> 1,
                                    'created_at'=> $delivery['created_at'],
                                    'updated_at'=> $delivery['updated_at'],
                                );
                                $select_shipping = D('Order/OrderShipping')->where(array('track_number'=>$delivery['track_number']))->find();
                                if(!$select_shipping){
                                    D('Order/OrderShipping')->data($shipping_data)->add();
                                }
                            }
                        }
                        if(isset($order['settlement_data']) && $order['settlement_data']){
                            $sett_data = $order['settlement_data'];
                            $settlement = array(
                                'id_users' => $sett_data['user_id'],
                                'id_order_shipping' => $order['shipping_id'],
                                'id_order' => $order_id,
                                'amount_total' => $sett_data['total_amount'],
                                'amount_settlement' => $sett_data['settlement_amount'],
                                'date_settlement' => $sett_data['settle_date'],
                                'created_at' => $sett_data['created_at'],
                                'updated_at' => $sett_data['updated_at'],
                                'status' => $sett_data['status'],
                            );
                            $select_shipping = D('Order/OrderSettlement')->where(array('id_order'=>$order_id))->find();
                            if(!$select_shipping){
                                D('Order/OrderSettlement')->data($settlement)->add();
                            }
                        }
                    }else{
                        $new_order_id = $select['id_order'];
                        if(isset($order['delivery_data']) && $order['delivery_data']){
                            $delivery_data = $order['delivery_data'];
                            foreach($delivery_data as $delivery){
                                $shipping_data = array(
                                    'date_delivery'=> $order['delivery_date'],
                                    'date_signed'=> $delivery['signed_for_date'],
                                );
                                D('Order/OrderShipping')->where(array('track_number'=>$delivery['track_number']))->save($shipping_data);
                            }
                        }

                        if($new_order_id && isset($order['settlement_data']) && $order['settlement_data']){
                            $sett_data = $order['settlement_data'];
                            $settlement = array(
                                'id_users' => $sett_data['user_id'],
                                'id_order_shipping' => $order['shipping_id'],
                                ////'id_order' => $order_id,
                                //'amount_total' => $sett_data['total_amount'],
                                //'amount_settlement' => $sett_data['settlement_amount'],
                                //'date_settlement' => $sett_data['settle_date'],//结款日期
                                'created_at' => $sett_data['created_at'],
                                'updated_at' => $sett_data['updated_at'],
                                //'status' => $sett_data['status'],
                            );
                            D('Order/OrderSettlement')->where(array('id_order'=>$new_order_id))->save($settlement);
                            echo 'update settlement'.$new_order_id.'<br />'.PHP_EOL;
                        }
                        '订单已经存在'.$order['id'].'<br /><br />'.PHP_EOL;
                    }
                }catch (\Exception $e){
                    add_system_record(1, 4, 4,'同步插入订单失败'.$e->getMessage().json_encode($order));
                }
            }

        }else{
            //$this->error('没有订单数据');
            echo '没有订单数据'.PHP_EOL;
        }
    }

    /**
     * 同步单个订单到数据
     */
    public function single(){
        $order_id = I('get.id');
        if(isset($_GET['action'])){
            switch($_GET['action']){
                case 'rloxw':
                    $url = 'http://www.rloxw.com/';
                    $id_department = 1;
                    break;
                case 'tjihg':
                    $url = 'http://www.tjihg.com/';
                    $id_department = 2;
                    break;
                case 'wfpil':
                    $url = 'http://www.wfpil.com/';
                    $id_department = 3;
                    break;
                case 'vvcxy':
                    $url = 'http://www.vvcxy.com/';
                    $id_department = 4;
                    break;
                case 'ndrow':
                    $url = 'http://www.ndrow.com/';
                    $id_department = 5;
                    break;
                case 'diyibaoji':
                    $url = 'http://www.diyibaoji.com/';
                    $id_department = 6;
                    break;
                case 'pyjuu':
                    $url = 'http://www.pyjuu.com/';
                    $id_department = 7;
                    break;
            }
            $this->update_single_order($url,$order_id,$id_department);
        }else{
            echo '请输入你要请求的域名';
        }
        exit();
    }

    /**
     * 根据域名去 同步订单产品
     */
    public function domain_order(){
        $domain = strip_tags($_GET['domain']);
        if($domain){
            $domain = D("Domain/Domain")->where(array('name'=>$domain))->find();
            if($domain){
                $id_domain = $domain['id_domain'];
                $id_department = $domain['id_department'];
                switch($id_department){
                    case 1:
                        $url = 'http://www.rloxw.com/';
                        break;
                    case 2:
                        $url = 'http://www.tjihg.com/';
                        break;
                    case 3:
                        $url = 'http://www.wfpil.com/';
                        break;
                    case 4:
                        $url = 'http://www.vvcxy.com/';
                        break;
                    case 5:
                        $url = 'http://www.ndrow.com/';
                        break;
                    case 6:
                        $url = 'http://www.diyibaoji.com/';
                        break;
                    case 7:
                        $url = 'http://www.pyjuu.com/';
                        break;
                }

                $where = array('id_domain'=>$id_domain,'id_department'=>$id_department);
                $order_list = D('Order/Order')->where($where)->select();
                if($order_list){
                    foreach($order_list as $ord_key=>$order){
                        $id_increment = $order['id_increment'];
                        $select_item = D('Order/OrderItem')->where(array('id_order'=>$order['id_order']))->find();
                        if(!$select_item){
                            $this->update_single_order($url,$id_increment,$id_department);
                        }else{
                            echo $id_increment.'存在产品<br />';
                        }

                    }
                }
            }
        }
        echo '执行完成';
        exit();
    }

    /**
     * 更新当个订单的产品信息
     * @param $url
     * @param $order_id
     * @param $id_department
     */
    protected function update_single_order($url,$order_id,$id_department){
        $url = $url.'api/Sync/single_order?id='.$order_id;
        $get_json = file_get_contents($url);
        $data = $get_json?json_decode($get_json,true):'';
        $list = isset($data['status'])&&$data['status']?$data['data']:'';
        if($list['products']){
            $order = D('Order/Order')->where(array('id_increment'=>$list['id']))->find();
            if($order){
                $new_order_id = $order['id_order'];
                foreach($list['products'] as $product){
                    $temp_where = array('id_department'=>$id_department,'product_id'=>$product['product_id']);
                    $temp_product = D("Common/TempProduct")->where($temp_where)->find();
                    if($temp_product){
                        $new_product_id = $temp_product['new_product_id'];
                        $where_item = array('id_order'=>$new_order_id,'id_product'=>$new_product_id);
                        $find = D('Order/OrderItem')->where($where_item)->find();
                        if(!$find){
                            $add_product = array(
                                'id_order' => $new_order_id,
                                'id_product_sku' => '0',
                                'id_product' => $new_product_id,
                                'sku' => '--',
                                'sku_title' => $product['sku_title'],
                                'sale_title' => $product['product_title'],
                                'product_title' => $product['product_title'],
                                'quantity' => $product['qty'],
                                'price' => $product['price'],
                                'total' => $product['total'],
                                'is_free' => 1,
                                'attrs' => $product['attrs'],
                            );
                            D('Order/OrderItem')->data($add_product)->add();
                            echo $product['product_id'].'添加成功<br />';
                        }else{
                            echo '此产品已经添加。'.$product['product_id'].PHP_EOL;
                        }

                    }else{
                        echo $product['product_id'].' 在新的系统里没有找到对应新的产品。';
                    }

                }
            }else{
                echo '没有找到订单<br />';
            }

        }
    }
    public function single2(){
        $order_id = I('get.id');
        if(isset($_GET['action'])){
            switch($_GET['action']){
                case 'rloxw':
                    $url = 'http://www.rloxw.com/';
                    $id_department = 1;
                    break;
                case 'tjihg':
                    $url = 'http://www.tjihg.com/';
                    $id_department = 2;
                    break;
                case 'wfpil':
                    $url = 'http://www.wfpil.com/';
                    $id_department = 3;
                    break;
                case 'vvcxy':
                    $url = 'http://www.vvcxy.com/';
                    $id_department = 4;
                    break;
                case 'ndrow':
                    $url = 'http://www.ndrow.com/';
                    $id_department = 5;
                    break;
                case 'diyibaoji':
                    $url = 'http://www.diyibaoji.com/';
                    $id_department = 6;
                    break;
                case 'pyjuu':
                    $url = 'http://www.pyjuu.com/';
                    $id_department = 7;
                    break;
            }
            $this->update_single_order2($url,$order_id,$id_department);
        }else{
            echo '请输入你要请求的域名';
        }
        exit();
    }
    protected function update_single_order2($url,$order_id,$id_department){
        $url = $url.'api/Sync/single_order?id='.$order_id;
        $get_json = file_get_contents($url);
        $data = $get_json?json_decode($get_json,true):'';
        $list = isset($data['status'])&&$data['status']?$data['data']:'';
        if($list['products']){
            $order = D('Order/Order')->where(array('id_increment'=>$list['id']))->find();
            if($order){
                $new_order_id = $order['id_order'];
                foreach($list['products'] as $product){
                    $temp_where = array('id_department'=>$id_department,'new_product_id'=>$product['product_id']);
                    $temp_product = D("Common/TempProduct")->where($temp_where)->find();
                    $new_product_id = $product['product_id'];
                    if($new_product_id){
                        $new_product_id = $temp_product['new_product_id'];
                        $where_item = array('id_order'=>$new_order_id,'id_product'=>$new_product_id);
                        $find = D('Order/OrderItem')->where($where_item)->find();
                        if(!$find){
                            $add_product = array(
                                'id_order' => $new_order_id,
                                'id_product_sku' => '0',
                                'id_product' => $new_product_id,
                                'sku' => '--',
                                'sku_title' => $product['sku_title'],
                                'sale_title' => $product['product_title'],
                                'product_title' => $product['product_title'],
                                'quantity' => $product['qty'],
                                'price' => $product['price'],
                                'total' => $product['total'],
                                'is_free' => 1,
                                'attrs' => $product['attrs'],
                            );
                            D('Order/OrderItem')->data($add_product)->add();
                            echo $product['product_id'].'添加成功<br />';
                        }else{
                            echo '此产品已经添加。'.$product['product_id'].PHP_EOL;
                        }
                        
                    }else{
                        echo $product['product_id'].' 在新的系统里没有找到对应新的产品。';
                    }
                    
                }
            }else{
                echo '没有找到订单<br />';
            }
            
        }
    }
    public function write_log($data){
        $setPath = './'.C("UPLOADPATH").'order'."/";
        if(!is_dir($setPath)){
            mkdir($setPath,0777,TRUE);
        }
        $logTxt = json_encode($data).PHP_EOL;
        $getPathFile = $setPath.date('Y_m_d').'.txt';
        file_put_contents($getPathFile,$logTxt,FILE_APPEND);
    }
}