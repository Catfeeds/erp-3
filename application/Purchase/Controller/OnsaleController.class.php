<?php

namespace Purchase\Controller;

use Common\Controller\AdminbaseController;
use Purchase\Lib\PurchaseStatus;

/**
 * 采购模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Purchase\Controller
 */
class OnsaleController extends AdminbaseController {

    protected $Purchase, $Users;

    public function _initialize() {
        parent::_initialize();
        $this->Purchase = D("Common/Purchase");
        $this->Users = D("Common/Users");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /*
     * 采购上架列表
     */
   public function index(){
       $purchase_model = D('Purchase/Purchase');

       $warehouse = M('Warehouse')->cache(true, 3600)->select();
       $department = M('Department')->where(array('type'=>1))->cache(true, 3600)->select();

       $status_onsale_list = PurchaseStatus::onsale_list_status();

       $department = array_column($department, 'title', 'id_department');
       $warehouse = array_column($warehouse, 'title', 'id_warehouse');

       $search = [];

       if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
           $created_at_array = array();
           if ($_GET['start_time'])
               $created_at_array[] = array('EGT', $_GET['start_time']);
           if ($_GET['end_time'])
               $created_at_array[] = array('LT', $_GET['end_time']);
           $search['p.created_at'] = $created_at_array;
       }

       if (!empty($_GET['id_department'])) {
           $search['p.id_department'] = $_GET['id_department'];
       }

       if (!empty($_GET['id_warehouse'])) {
           $search['p.id_warehouse'] = $_GET['id_warehouse'];
       }

       if (!empty($_GET['status'])) {
           $search[] = array('p.status'=>array('EQ', $_GET['status']));
       }
       if (!empty($_GET['inner_purchase_no'])) {
           $search[] = array('p.inner_purchase_no'=>array('EQ', $_GET['inner_purchase_no']));
       }

       if (!empty($_GET['purchase_no'])) {
           $search[] = array('p.purchase_no'=>array('EQ', $_GET['purchase_no']));
       }

       $purchase_model->alias('p')
           ->field('p.*, u.user_nicename as user_name')
           ->join("__USERS__ as u ON u.id=p.id_users", 'left')
           ->order('p.updated_at desc')
           ->where($search)
           ->where(array('p.status'=> array('IN', array_keys($status_onsale_list))));

       $purchase_model_count = clone($purchase_model);
       $count = $purchase_model_count->count();
       $page = $this->page($count, 20);
       $list = $purchase_model->limit($page->firstRow . ',' . $page->listRows)->select();

       foreach($list as &$row){
           $row['operation'] = in_array($row['status'], array(PurchaseStatus::PART_ON_SALE, PurchaseStatus::FINISH_RECEIVE, PurchaseStatus::PART_RECEIVE)) ? 'on_sale' : 'view';
           $row['status'] = isset($status_onsale_list[$row['status']]) ? $status_onsale_list[$row['status']] : '未知状态';
           $row['department_name'] = $department[$row['id_department']];
           $row['warehouse_name'] = $warehouse[$row['id_warehouse']];
       }

       $this->assign('page', $page->show('Admin'));
       $this->assign('status', $status_onsale_list);
       $this->assign('warehouse', $warehouse);
       $this->assign('department', $department);
       $this->assign('list', $list);
       $this->display();
   }

    /**
     * 上架编辑
     */
   public function edit(){
       $id_purchase = I('request.id_purchase');
       $purchase_info = M('Purchase')->alias('p')
           ->field("p.*, w.title as warehouse_name")
           ->join("__WAREHOUSE__ as w ON w.id_warehouse=p.id_warehouse", "LEFT")
           ->where(array('id_purchase'=>$id_purchase))
           ->find();
       $product_info = M('PurchaseProduct')->alias('pp')
           ->field("pp.*, ps.sku, p.title as product_name")
           ->join("__PRODUCT_SKU__ as ps ON ps.id_product_sku=pp.id_product_sku", "LEFT")
           ->join("__PRODUCT__ as p ON ps.id_product=p.id_product", "LEFT")
           ->where(array('id_purchase'=> $id_purchase))
           ->select();
       $warehouse_allocations = M("WarehouseGoodsAllocation")
           ->field("id_warehouse_allocation as value, goods_name as label")
           ->where(array('id_warehouse'=>$purchase_info['id_warehouse']))
           ->select();

       $warehouse_allocations = json_encode($warehouse_allocations);

       $this->assign('warehouse_allocations', $warehouse_allocations);
       $this->assign('purchase_info', $purchase_info);
       $this->assign('product_info', $product_info);
       $this->display();
   }

    /**
     * 保存上架
     */
   public function save(){
        $post_data = I('post.');

        if(!isset($post_data['id_product_sku'])){
            $this->error('没有需要上架的sku', U('Onsale/index'));
        }

       $sku_data = [];

       foreach($post_data['id_product_sku'] as $k=>$v){
           $sku_data[$k]['id_product_sku'] = $post_data['id_product_sku'][$k];
           $sku_data[$k]['id_product'] = $post_data['id_product'][$k];
           $sku_data[$k]['num'] = $post_data['num'][$k];
           $sku_data[$k]['id_warehouse_allocation'] = $post_data['id_warehouse_allocation'][$k];
       }

       $product_info = M('PurchaseProduct')->alias('pp')
           ->field('received, quantity_on_sale, id_product_sku')
           ->where(array('id_purchase'=> $post_data['id_purchase']))
           ->select();

       $product_info = array_column($product_info, null, 'id_product_sku');
       $purchase_info = M('Purchase')->alias('p')
           ->where(array('id_purchase'=>$post_data['id_purchase']))
           ->find();

       //检查上架数量是否超过收货数量
       $product_on_sale_total = array();
       foreach($sku_data as $row){
           $product_on_sale_total[$row['id_product_sku']] = isset($product_on_sale_total[$row['id_product_sku']]) ?
               $product_on_sale_total[$row['id_product_sku']] + $row['num'] :
               $row['num'];
       }

       foreach($product_on_sale_total as $k => $v){
           if($product_on_sale_total[$k] > $product_info[$k]['received'] - $product_info[$k]['quantity_on_sale']){
               $this->error('某sku上架数量超过收货数量');
           }
       }

       //开始处理提交的数据
       foreach($sku_data as $value){

           if(!is_numeric($value['num']) || $value['num'] <= 0
               || empty($value['id_warehouse_allocation'])
               || !is_numeric($value['id_warehouse_allocation'])){
               continue;
           }

           $exist = M('WarehouseAllocationStock')
               ->where(array(
                   'id_product_sku' => $value['id_product_sku'],
                   'id_warehouse_allocation' => $value['id_warehouse_allocation']
               ))->find();

           //添加货位库存
           if(!$exist){
               M('WarehouseAllocationStock')->add(array(
                   'updated_at' => date('Y-m-d H:i:s'),
                   'id_product' => $value['id_product'],
                   'id_warehouse_allocation' => $value['id_warehouse_allocation'],
                   'id_product_sku' => $value['id_product_sku'],
                   'quantity' => $value['num'],
                   'id_users' => $_SESSION['ADMIN_ID']
               ));
           }else{
               M('WarehouseAllocationStock')->where(array('id'=>array('EQ',$exist['id'])))->setField(array(
                   'updated_at' => date('Y-m-d H:i:s'),
                   'quantity' => $exist['quantity']+$value['num'],
                   'id_users' => $exist['id_users'] . ',' . $_SESSION['ADMIN_ID']
               ));
           }

           //添加产品库存
           M('PurchaseProduct')->where(array(
               'id_purchase' => $purchase_info['id_purchase'],
               'id_product_sku' => $value['id_product_sku'],
           ))->setInc('quantity_on_sale', $value['num']);

           //采购单记录更新
           $purchase_info['total_onsale'] += $value['num'];
           if($purchase_info['total_onsale'] >= $purchase_info['total']){
               $status = PurchaseStatus::FINISH;
           }else{
               $status = PurchaseStatus::PART_ON_SALE;
           }
           M('Purchase')->where(array(
               'id_purchase' => $purchase_info['id_purchase']
           ))->save(array(
               'total_onsale' => $purchase_info['total_onsale'],
               'status' => $status
           ));

           //货位操作记录
           D('WarehouseRecord')->write(
               array(
                   'type' => 'ON_SALE', //上架
                   'id_warehouse_allocation' => $value['id_warehouse_allocation'],
                   'id_warehouse' =>$purchase_info['id_warehouse'],
                   'num_before' => !empty($exist['quantity']) ? $exist['quantity'] : 0,
                   'num' => $value['num'],
                   'id_product_sku' => $value['id_product_sku'],
                   'purchase_no' => $purchase_info['purchase_no']
               )
           );

           $where = array(
               'id_warehouse' => $purchase_info['id_warehouse'],
               'id_product_sku' => $value['id_product_sku']
           );
           M('WarehouseProduct')->where($where)->setInc('quantity',$value['num']);
           $road_num = M('WarehouseProduct')->where($where)->getField('road_num');
           if(($value['num']-$road_num)>=0)
               M('WarehouseProduct')->where($where)->setField('road_num',0);
           else
               M('WarehouseProduct')->where($where)->setDec('road_num',$value['num']);

           //仓库收货后对缺货产品进行未配货的状态更新，并减去仓库库存
           if($value['num']>0) {
               $where = 'oi.id_product_sku ='.$value['id_product_sku'].' and o.id_order_status=6';
               $order_data = M('Order')->alias('o')
                   ->field('oi.id_order,o.id_zone,o.id_department,o.id_order_status,o.payment_method')
                   ->join('__ORDER_ITEM__ AS oi ON o.id_order=oi.id_order', 'left')
                   ->where($where)
                   ->order('oi.sorting desc,o.date_purchase asc')
                   ->select();

               if($order_data && $value['num']>0) {
                   /** @var \Order\Model\OrderRecordModel  $order_record */
                   $order_record = D("Order/OrderRecord");
                   foreach ($order_data as $key=>$val) {

                       //香港地区DF订单减库存后状态改为已审核
                       if($val['id_zone'] == 3 && empty($val['payment_method'])){
                           $default_id_order_status = \Order\Lib\OrderStatus::APPROVED;
                       }else{
                           $default_id_order_status = \Order\Lib\OrderStatus::UNPICKING;
                       }

                       $results = \Order\Model\UpdateStatusModel::lessInventory($val['id_order'],$val);
                       if($results['status']) {
                           $update_order = array();
                           $update_order['id_order_status'] = $default_id_order_status;
                           $update_order['id_warehouse'] = isset($results['id_warehouse'])?end($results['id_warehouse']):1;
                           D('Order/Order')->where('id_order='.$val['id_order'])->save($update_order);
                           $parameter  = array(
                               'id_order' => $val['id_order'],
                               'id_order_status' => $default_id_order_status,
                               'type' => 1,
                               'comment' => '上架仓库库存对缺货状态进行更新,上架：'.$value['num'],
                           );
                           $order_record->addOrderHistory($parameter);
                       }
                   }
               }

           }
       }
       add_system_record($_SESSION['ADMIN_ID'], 2, 3,'添加上架');
       $this->success("添加成功！", U('Onsale/index'));
   }

    /*
     * 查看上架
     */
    public function look(){
        $id_purchase = $_GET['id_purchase'];
        $where['pp.id_purchase'] = $id_purchase;
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $purchase_product = M('PurchaseProduct')->alias('pp')->field('pp.*,p.purchase_no,p.id_warehouse')->join('__PURCHASE__ as p on p.id_purchase = pp.id_purchase','LEFT')->where($where)->select();
        $warehouse_allocation_sum = M('WarehouseAllocationStock')->field('sum(quantity) as num,id_product_sku')->group('id_product_sku')->select();
        $warehouse_allocation_sum = array_column($warehouse_allocation_sum,'num','id_product_sku');
        $list = '';
        $i=0;
        foreach($purchase_product as $key=>$value){
            $product_sku = M('ProductSku')->field('sku')->where(array('id_product_sku'=>array('EQ',$value['id_product_sku'])))->find();
            $product = M('Product')->field('title')->where(array('id_product'=>array('EQ',$value['id_product'])))->find();
            $warehouse_allocation_stock = M('WarehouseAllocationStock')->field('goods_name,quantity as goods_quantity,id_product_sku')->alias('was')->join('__WAREHOUSE_GOODS_ALLOCATION__ as wga on wga.id_warehouse_allocation = was.id_warehouse_allocation')->where(array('id_product_sku'=>array('EQ',$value['id_product_sku'])))->group('was.id_warehouse_allocation')->select();
            foreach($warehouse_allocation_stock as $k=>$v){
//                $list[$key][$k] = $v;
                $value['sku'] = implode('',$product_sku);
                $value['title'] = implode('',$product);
                $value['sum'] = $warehouse_allocation_sum[$v['id_product_sku']];
                $list[$i] = array_merge($v,$value);
                $i++;
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 4, 3,'查看采购单上架详情');
        $this->assign('datas',$list);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }
    //搜索提示
    public function search_text(){
        $goods_name = $_GET['goods_name'];
        $id_warehouse = $_GET['id_warehouse'];
        $result = M('WarehouseGoodsAllocation')->field('goods_name,id_warehouse_allocation')
            ->where(array('goods_name'=>array('LIKE','%'.$goods_name.'%'),'id_warehouse'=>$id_warehouse))
            ->select();
        $result = array_column($result,'goods_name');
        $data = '<ul>';
        foreach($result as $value){
            $data.='<li style="padding-top:5px;padding-bottom:5px">'.$value.'</a></li>';
        }
        $data.='</ul>';
        echo json_encode($data);
    }
}
