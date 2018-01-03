<?php

namespace Purchase\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use Purchase\Lib\PurchaseStatus;
use Common\Lib\SearchData;
use Common\Lib\Common;
use Common\Lib\Procedure;
use Order\Model\UpdateStatusModel;
header("Content-Type:text/html;charset=utf-8");
/**
 * 采购入库
 * @Author Eva
 * @qq 549251235
 * Class IndexController
 * @package Purchase\Controller
 */
class InController extends AdminbaseController {

    protected $Purchase, $Users;

    public function _initialize() {
        parent::_initialize();
        $this->PurchaseIn = M("PurchaseIn");
        $this->PurchaseInitem = M("PurchaseInitem");
        $this->Purchase = M("Purchase");
        $this->PurchaseProduct= M("PurchaseProduct");
        $this->Users = D("Common/Users");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
        $this->time_start = I('get.start_time', date('Y-m-d 00:00', strtotime('-7 day')));
        $this->time_end = I('get.end_time', date('Y-m-d 00:00', strtotime('+1 day')));
    }
    //采购入库单列表
      public function index(){
          set_time_limit(0);
          // $_GET['start_time'] = $this->time_start;
          // $_GET['end_time'] = $this->time_end;
          if (isset($_GET['id_department']) && $_GET['id_department']) {
              $where['pi.id_department'] = array('EQ', $_GET['id_department']);
          }
          if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
              $where['pi.id_warehouse'] = array('EQ', $_GET['id_warehouse']);
          }
          if (isset($_GET['status']) && $_GET['status']) {
            if($_GET['status']==3){
              $where['_string'] = "(pi.total_received<>pi.total AND pi.status=2) OR pi.status=3";
            }else{
              $where['pi.status'] = array('EQ', $_GET['status']);
            }
          }
           if (isset($_GET['shipping_no']) && $_GET['shipping_no']) {
              $where['pi.shipping_no'] = array('EQ', $_GET['shipping_no']);
          }
          if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $purchase_no=  trim($_GET['purchase_no']);
            $where['pi.purchase_no'] = array('like', "%{$purchase_no}%");
          }
          if ($_GET['alibaba_no']) {
              $alibaba_no=  trim($_GET['alibaba_no']);
              $where['pi.alibaba_no'] = array('like', "%{$alibaba_no}%");
          }
          if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['pi.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
          }
          $createAtArray = array();
          if (isset($_GET['start_time']) && $_GET['start_time']) {
              $createAtArray[] = array('EGT',$_GET['start_time']);
          }
          if($_GET['end_time']) {
              $createAtArray[] = array('LT',$_GET['end_time']);
          }
          //新增入库时间查询  --Lily 20171103
          $IntimeAtArray = array();
          if(isset($_GET['startt_time']) && $_GET['startt_time']){
            $IntimeAtArray[] = array("EGT",$_GET['startt_time']);
          }
          if(isset($_GET['endt_time']) && $_GET['endt_time']){
            $IntimeAtArray[] = array("LT",$_GET['endt_time']);
          }

          if (isset($_GET['sku']) && $_GET['sku']) {
              $id_product_sku = M('ProductSku')->where(array('sku' => trim($_GET['sku'])))->getField('id_product_sku');
              $where['id_product_sku'] = array('EQ', $id_product_sku);
          }
          //添加“产品内部名”搜索条件   liuruibin   20171020
          if(isset($_GET['inner_name']) && $_GET['inner_name']){
              $where['pt.inner_name'] = array('like',"%{$_GET['inner_name']}%");
          }
          //添加“单据类型”搜索条件   liuruibin   20171021
          if (isset($_GET['billtype']) && $_GET['billtype']==0) {
              $where['pi.billtype'] = array('IN', [1,2]);
          }else if(isset($_GET['billtype']) && $_GET['billtype']){
              $where['pi.billtype'] = array('EQ', $_GET['billtype']);
          }else{
              $where['pi.billtype'] = array('EQ', 1);
          }
          if(!empty($createAtArray) && empty($IntimeAtArray)){
              $where[] = array('pi.billdate' => $createAtArray);
            }else if(!empty($IntimeAtArray) && empty($createAtArray)){
              $where[] = array('pi.intime'=>$IntimeAtArray);
            }else if(!empty($createAtArray) && !empty($IntimeAtArray)){
              $where[] = array('pi.billdate' => $createAtArray,'pi.intime'=>$IntimeAtArray);
            }

         // dump($where);die;
          $model = new \Think\Model();
          $users = M('Users')->field('id,user_nicename')->select();
          $users = array_column($users, 'user_nicename', 'id');
          $product_table = D('product')->getTableName();
          //关联产品表，按产品内部名搜索统计   liuruibin   20171020
          $count = $model->table($this->PurchaseIn->getTableName() . ' pi')
              ->join(array($this->PurchaseInitem->getTableName() . ' pii on pii.id_purchasein = pi.id_purchasein'),'RIGHT')
              ->join("{$product_table} pt on pt.id_product=pii.id_product",'left')
              ->where($where)->group('pi.id_purchasein')
              ->select();
          $count = count($count);
          $page = $this->page($count, 20);
          //关联产品表，按产品内部名搜索列表   liuruibin   20171020
          $list = $model->table($this->PurchaseIn->getTableName() . ' pi')
              ->field('pt.inner_name,pi.*,pii.*')
              ->join(array($this->PurchaseInitem->getTableName() . ' pii on pii.id_purchasein = pi.id_purchasein'),'RIGHT')
              ->join("{$product_table} pt on pt.id_product=pii.id_product",'left')
              ->where($where)->group('pi.id_purchasein')
              ->order("pi.updated_at DESC")
              ->limit($page->firstRow, $page->listRows)
              ->select();
         // echo $model->getLastSql();die;
          $departments = array_column(SearchData::search()['departments'],'title','id_department');
          $warehouses = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
          $suppliers = array_column(SearchData::search()['suppliers'],'title','id_supplier');
          $pur_status = array_column(SearchData::search()['pur_status'],'title','id_purchase_status');
          $sup_id = array();
          $ware_id = array();
          $_status = [
              '1' => '<span style="color:blue">未入库</span>',
              '2' => '已入库',
              '3' => '<span style="color:red">部分入库</span>',   // 部分入库  --Lily  20171104
              '4' => '<span class="label label-info">已退货</span>'   // 已退货  --zhujie  20171114
          ];
          foreach ($list as $k => $v) {
              $list[$k]['billtype'] = $v['billtype']==1?'采购入库':'<span style="color:red">采购退货</span>';
              $list[$k]['id_department'] = $departments[$v['id_department']];
              $list[$k]['id_warehouse'] = $warehouses[$v['id_warehouse']];
              $list[$k]['id_users'] = $users[$v['id_users']];
              $list[$k]['inerid'] = $users[$v['inerid']];
              $list[$k]['id_supplier'] = $suppliers[$v['id_supplier']];
              $list[$k]['inner_name'] = M('Product')->field('inner_name')->where(array('id_product' => $v['id_product']))->getField('inner_name');
              switch ($v['purchase_channel']) {
                  case 1:
                      $pur_channel_name = '阿里巴巴';
                      break;
                  case 2:
                      $pur_channel_name = '淘宝';
                      break;
                  case 3:
                      $pur_channel_name = '线下';
                      break;
                  default :
                      $pur_channel_name = '';
              }
              $list[$k]['purchase_channel'] = $pur_channel_name;
          }


         $data['purchase_lists'] = $list;
         $data = array_merge($data,SearchData::search());
         $this->assign('data',$data);
         $this->assign('_status',$_status);
          $this->assign("page", $page->show('Admin'));
         $this->display('index');
      }

   /*
    * 采购明细
    */
    public function detail(){
        $_GET['start_time'] = $this->time_start;
        $_GET['end_time'] = $this->time_end;
        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['pi.id_department'] = array('EQ', $_GET['id_department']);
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where['pi.id_warehouse'] = array('EQ', $_GET['id_warehouse']);
        }
        if (isset($_GET['status']) && $_GET['status']==0) {
            $where['pi.status'] = array('IN', [1,2,3]);
        }else if(isset($_GET['status']) && $_GET['status']!==0){
            $where['pi.status'] = array('EQ', $_GET['status']);
        }else{
            $where['pi.status'] = array('EQ', 1);
        }
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $purchase_no=  trim($_GET['purchase_no']);
            $where['pi.purchase_no'] = array('like', "%{$purchase_no}%");
        }
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['pi.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if ($_GET['alibaba_no']) {
            $alibaba_no=  trim($_GET['alibaba_no']);
            $where['pi.alibaba_no'] = array('like', "%{$alibaba_no}%");
        }
        if ($_GET['shipping_no']) {
            $shipping_no=  trim($_GET['shipping_no']);
            $where['pi.shipping_no'] = array('like', "%{$shipping_no}%");
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $id_product_sku = M('ProductSku')->where(array('sku' => trim($_GET['sku'])))->getField('id_product_sku');
            $where['id_product_sku'] = array('EQ', $id_product_sku);
        }
        //添加“产品内部名”搜索条件
        if(isset($_GET['inner_name']) && $_GET['inner_name']){
            $where['pt.inner_name'] = array('like',"%{$_GET['inner_name']}%");
        }
        $createAtArray = array();
        if (isset($_GET['start_intime']) && $_GET['start_intime']) {
            $inArray[] = array('EGT',$_GET['start_intime']);
        }
        if($_GET['end_intime']) {
            $inArray[] = array('LT',$_GET['end_intime']);

        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray[] = array('EGT',$_GET['start_time']);
        }
        if($_GET['end_time']) {
            $createAtArray[] = array('LT',$_GET['end_time']);

        }
        //添加“单据类型”搜索条件      liuruibin 20171013
        if (isset($_GET['billtype']) && $_GET['billtype']==0) {
            $where['pi.billtype'] = array('IN', [1,2]);
        }else if(isset($_GET['billtype']) && $_GET['billtype']){
            $where['pi.billtype'] = array('EQ', $_GET['billtype']);
        }else{
            $where['pi.billtype'] = array('EQ', 1);
        }
        if(!empty($createAtArray))
        $where[] = array('pi.billdate' => $createAtArray);
        if(!empty($inArray))
            $where[] = array('pi.intime' => $inArray);
        $model = new \Think\Model();
        $users = M('Users')->field('id,user_nicename')->select();
        $users = array_column($users, 'user_nicename', 'id');
        $product_table = D('product')->getTableName();
        //关联产品表，按产品内部名搜索统计
        $count = $model->table($this->PurchaseIn->getTableName() . ' pi')
            ->join(array($this->PurchaseInitem->getTableName() . ' pii on pii.id_purchasein = pi.id_purchasein'),'RIGHT')
            ->join("{$product_table} pt on pt.id_product=pii.id_product",'left')
            ->where($where)->count();
        $page = $this->page($count, 20);
        //关联产品表，按产品内部名搜索列表
        $list = $model->table($this->PurchaseIn->getTableName() . ' pi')
            ->field('pt.inner_name,pi.*,pii.*')
            ->join(array($this->PurchaseInitem->getTableName() . ' pii on pii.id_purchasein = pi.id_purchasein'),'RIGHT')
            ->join("{$product_table} pt on pt.id_product=pii.id_product",'left')
            ->where($where)
            ->order("id_purchaseinitem DESC")
            ->limit($page->firstRow, $page->listRows)
//              ->fetchSql()
            ->select();

//        var_dump($list);
        $departments = array_column(SearchData::search()['departments'],'title','id_department');
        $warehouses = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
        $suppliers = array_column(SearchData::search()['suppliers'],'title','id_supplier');
        $pur_status = array_column(SearchData::search()['pur_status'],'title','id_purchase_status');
        $sup_id = array();
        $ware_id = array();
//          var_dump($list);die;
        foreach ($list as $k => $v) {
            $list[$k]['billtype'] = $v['billtype']==1?'采购入库':'<span style="color:red">采购退货</span>';
            $list[$k]['id_department'] = $departments[$v['id_department']];
            $list[$k]['id_warehouse'] = $warehouses[$v['id_warehouse']];
            $list[$k]['id_users'] = $users[$v['id_users']];
            $list[$k]['inerid'] = $users[$v['inerid']];
            if($v['status']==1){
              $list[$k]['status'] = '未完成';
            }else if($v['status']==2){
              $list[$k]['status'] = '已完成';
            }else{
              $list[$k]['status'] = '部分入库';
            }
            $list[$k]['id_supplier'] = $suppliers[$v['id_supplier']];
            $list[$k]['sku'] = M('ProductSku')->where(array('id_product_sku' => $v['id_product_sku']))->getField('sku');
            $list[$k]['inner_name'] = M('Product')->field('inner_name')->where(array('id_product' => $v['id_product']))->getField('inner_name');

//            $result[$key]['one_price'] = M('Product')->where(array('id_product' => $val['id_product']))->getField('sale_price');
            switch ($v['purchase_channel']) {
                case 1:
                    $pur_channel_name = '阿里巴巴';
                    break;
                case 2:
                    $pur_channel_name = '淘宝';
                    break;
                case 3:
                    $pur_channel_name = '线下';
                    break;
                default :
                    $pur_channel_name = '';
            }
            $list[$k]['purchase_channel'] = $pur_channel_name;
        }


        $data['purchase_lists'] = $list;
        $data = array_merge($data,SearchData::search());
//          var_dump($data['departments']);
        $this->assign('data',$data);
        $this->assign("page", $page->show('Admin'));
        $this->display('detail');
    }


    /*
     * 新增采购入库单
     */
    public function create(){
        $_GET['start_time'] = $this->time_start;
        $_GET['end_time'] = $this->time_end;
        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['p.id_department'] = array('EQ', $_GET['id_department']);
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where['p.id_warehouse'] = array('EQ', $_GET['id_warehouse']);
        }
        if (isset($_GET['status']) && $_GET['status']) {
            $where['p.status'] = array('EQ', $_GET['status']);
        }else{
            $where['p.status'] = array('EQ',5);
        }
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $purchase_no=  trim($_GET['purchase_no']);
            $where['p.purchase_no'] = array('like', "%{$purchase_no}%");
        }
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['p.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if (isset($_GET['shipping_no']) && $_GET['shipping_no']) {
            $shipping_no=  trim($_GET['shipping_no']);
            $where['p.shipping_no'] = array('like', "%{$shipping_no}%");
        }
        if ($_GET['alibaba_no']) {
            $alibaba_no=  trim($_GET['alibaba_no']);
            $where['p.alibaba_no'] = array('like', "%{$alibaba_no}%");
        }
        $createAtArray = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray[] = array('EGT',$_GET['start_time']);
        }
        if($_GET['end_time']) {
            $createAtArray[] = array('LT',$_GET['end_time']);

        }
        if(!empty($createAtArray))
        $where[] = array('p.created_at' => $createAtArray);

        //采购状态为付完款之后

        $data = array();
        if($where){
            $model = new \Think\Model();
            $users = M('Users')->field('id,user_nicename')->select();
            $users = array_column($users, 'user_nicename', 'id');
            $count = $model->table($this->Purchase->getTableName() . ' p')
                ->where($where)->count();
            $page = $this->page($count, 20);
            $list = $model->table($this->Purchase->getTableName() . ' p')
                ->where($where)
                ->order("created_at DESC")
                ->limit($page->firstRow, $page->listRows)
//              ->fetchSql()
                ->select();
//          echo $list;
            $departments = array_column(SearchData::search()['departments'],'title','id_department');
            $warehouses = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
            $suppliers = array_column(SearchData::search()['suppliers'],'title','id_supplier');
            $pur_status = array_column(SearchData::search()['pur_status'],'title','id_purchase_status');
            $sup_id = array();
            $ware_id = array();
//          var_dump($list);die;
            foreach ($list as $k => $v) {
                $list[$k]['billtype'] = $v['billtype']==1?'采购入库':'采购退货';
                $list[$k]['id_department'] = $departments[$v['id_department']];
                $list[$k]['id_warehouse'] = $warehouses[$v['id_warehouse']];
                $list[$k]['id_users'] = $users[$v['id_users']];
                $list[$k]['inerid'] = $users[$v['inerid']];
                $list[$k]['status'] = $pur_status[$v['status']];
                $list[$k]['id_supplier'] = $suppliers[$v['id_supplier']];
                switch ($v['purchase_channel']) {
                    case 1:
                        $pur_channel_name = '阿里巴巴';
                        break;
                    case 2:
                        $pur_channel_name = '淘宝';
                        break;
                    case 3:
                        $pur_channel_name = '线下';
                        break;
                    default :
                        $pur_channel_name = '';
                }
                $list[$k]['purchase_channel'] = $pur_channel_name;
            }

            $data['purchase_lists'] = $list;
            $this->assign("page", $page->show('Admin'));
        }
        $data = array_merge($data,SearchData::search());
        $this->assign('data',$data);
        $this->display('create');
    }

    /*
     * 新增入库单
     */
    public function add(){
        if(IS_POST){
            $count = $this->PurchaseIn->count();
//            var_dump($count);die;
            $supplier = M('Supplier')->where(array('id_supplier'=>$_POST['id_supplier']))->find();
            if(empty($supplier['supplier_url'])) {
                D('Common/Supplier')->where(array('id_supplier'=>$_POST['id_supplier']))->save(array('supplier_url'=>$_POST['supplier_url']));
            }
            if(empty($supplier)) {
                $supp_id = D('Common/Supplier')->add(array('title'=>$_POST['supplier_name'],'supplier_url'=>$_POST['supplier_url'],'id_department'=>$_POST['id_department'],'created_at'=>date('Y-m-d H:i:s')));
            }
            $add_data['billdate'] = date('Y-m-d H:i:s');
            $add_data['shipping_no'] = I('post.shipping_no');
            $add_data['billtype'] = I('post.billtype');
            $add_data['id_erp_purchase'] = I('post.id_erp_purchase');
            $add_data['id_warehouse'] = I('post.id_warehouse');
            $add_data['id_department'] = I('post.id_department');
            $add_data['created_at'] = date('Y-m-d H:i:s');
            $add_data['updated_at'] = date('Y-m-d H:i:s');
            $add_data['track_number'] = I('post.track_number');
            $add_data['id_supplier'] = !empty($_POST['id_supplier'])?I('post.id_supplier'):$supp_id;
            $add_data['purchase_channel'] = I('post.pur_channel');
            $add_data['purchase_no'] = I('post.purchase_no');
            $add_data['alibaba_no'] = I('post.alibaba_no');
            $add_data['inner_purchase_no'] = I('post.inner_purchase_no');
            $add_data['price'] = I('post.price');
            $add_data['total'] = I('post.total');
            $add_data['total_received'] = 0;
            $add_data['price_shipping'] = I('post.price_shipping');
            $add_data['date_from'] = I('post.date_from');
            $add_data['date_to'] = I('post.date_to');
            $add_data['remark'] = I('post.remark');
            $add_data['id_users'] = $_SESSION['ADMIN_ID'];
            $add_data['status'] = 1;
            $attr_name = I('post.attr_name');
            $attr_price = I('post.set_price');  //每个sku价格
            $set_price = I('post.set_price');  //单价
            $total_qty = 0;
            $set_qty = array_filter($_POST['set_qty']);
            $set_price = array_filter($_POST['set_price']);
            $purchase_quantity = array_filter($_POST['purchase_quantity']);
            if ($set_qty) {
                foreach ($set_qty as $pro_id => $item) {
                    $get_qty = array_filter($item);
                    if ($get_qty) {
                        foreach($get_qty as $sku_qty){
                            $total_qty += $sku_qty;
                    }
                }
               }
            }
            $get_in_id = $this->PurchaseIn->data($add_data)->add();
//            var_dump($get_in_id);die;
//            var_dump($set_qty);die;
            $total_price = 0;

            if ($set_qty) {
                foreach ($set_qty as $pro_id => $item) {
//                    $get_qty = array_filter($item);
//                    var_dump($get_qty);die;
                    foreach ($item as $key => $qty) {
                        $sku_ids = $key;
                        $get_attr_name = $attr_name[$pro_id][$key]; //属性名称
                        $array_data = array(
                            'id_purchasein' => $get_in_id,
                            'id_product' => $pro_id,
                            'id_product_sku' => $sku_ids,
                            'option_value' => $get_attr_name,
                            'quantity' => $purchase_quantity[$pro_id][$key],
                            'price' => $set_price[$pro_id][$key],
                            'received' => $set_qty[$pro_id][$key],
                        );
                        $this->PurchaseInitem->data($array_data)->add();
                    }
                }
            }
            $update = array('total_received'=>$total_qty, 'price' => I('post.price'));
            $this->PurchaseIn->where('id_purchasein=' . $get_in_id)->save($update);
            D("Purchase/PurchaseStatus")->add_pur_history($get_in_id, $_POST['hid']=='2'?PurchaseStatus::UNCHECK:PurchaseStatus::UNSUBMIT, '新建采购入库单');
            add_system_record(sp_get_current_admin_id(), 1, 2, '添加采购入库单成功');
            $this->success("保存成功！", U('In/index'));
        }
        if(IS_GET){
            $id = I('get.id/i');
            $pro_table_name = D("Common/Product")->getTableName();
            $pur_or_table_name = $this->Purchase->getTableName();
            $sku_model = D("Common/ProductSku");
            $model = new \Think\Model;
//
//            $dep = $_SESSION['department_id'];
//            $map['id_department'] = array('IN', $dep);

            $purchase = $this->Purchase->where('id_purchase=' . $id)->find();
            $tarray = array();
            $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
            $etime = date('Y-m-d 00:00:00');
            $tarray[] = array('EGT', $stime);
            $tarray[] = array('LT', $etime);
            $twhere[] = array('created_at' => $tarray);
            if ($purchase['id_purchase']) {
                $pur_pro_table_name = D("Common/PurchaseProduct")->getTableName();
                $pur_product = $model->table($pur_pro_table_name . ' AS pp INNER JOIN ' . $pro_table_name . ' AS p ON pp.id_product=p.id_product')
                    ->field('pp.*,p.title,p.thumbs,p.inner_name')->where('pp.id_purchase=' . $purchase['id_purchase'])->order('pp.id_purchase_product')->select();
                foreach ($pur_product as $key => $item) {
                    $get_model = $sku_model->find($item['id_product_sku']);
                    $pur_product[$key]['sku'] = $get_model['sku'];
                    $where['id_product'] = $item['id_product'];
                    $where['id_product_sku'] = $item['id_product_sku'];
                    $wp_where['id_warehouse'] = $purchase['id_warehouse'];
                    $warehouse_pro = M('WarehouseProduct')->field('quantity,road_num')->where($where)->where($wp_where)->find();
                }
            }
            $supplier_name = M('Supplier')->field('title,supplier_url')->where(array('id_supplier' => $purchase['id_supplier']))->find();
            $this->assign('supplier_name', $supplier_name);
            $this->assign('product', $pur_product);
            $this->assign('data', $purchase);
//            var_dump($purchase);
        }
//        var_dump($purchase);die;
        $data = SearchData::search();
        $this->assign('search', $data);
        $this->display();
    }

    /*
     * 编辑
     */
    public function edit(){
        $id = I('get.id/i');
        $pro_table_name = D("Common/Product")->getTableName();
        $pur_or_table_name = $this->PurchaseIn->getTableName();
        $sku_model = D("Common/ProductSku");
        $model = new \Think\Model;
//
//            $dep = $_SESSION['department_id'];
//            $map['id_department'] = array('IN', $dep);

        $purchase = $this->PurchaseIn->where('id_purchasein=' . $id)->find();
//        var_dump($purchase);die;
        $tarray = array();
        $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
        $etime = date('Y-m-d 00:00:00');
        $tarray[] = array('EGT', $stime);
        $tarray[] = array('LT', $etime);
        $twhere[] = array('created_at' => $tarray);
//        var_dump($purchase);die;
        if ($purchase['id_purchasein']) {
            $pur_pro_table_name = D("Common/PurchaseInitem")->getTableName();
            $pur_product = $model->table($pur_pro_table_name . ' AS pii INNER JOIN ' . $pro_table_name . ' AS p ON pii.id_product=p.id_product')
                ->field('pii.*,p.title,p.thumbs,p.inner_name')->where('pii.id_purchasein=' . $purchase['id_purchasein'])->select();
            foreach ($pur_product as $key => $item) {
                $get_model = $sku_model->find($item['id_product_sku']);
                $pur_product[$key]['sku'] = $get_model['sku'];
            }

        }
        $supplier_name = M('Supplier')->field('title,supplier_url')->where(array('id_supplier' => $purchase['id_supplier']))->find();
        $this->assign('supplier_name', $supplier_name);
        $this->assign('product', $pur_product);
        $this->assign('data', $purchase);
        $data = SearchData::search();
        $this->assign('search', $data);
        $this->display();
    }


    /*
     * 保存编辑信息
     */
    public function save_edit(){
        if ($_POST['id_purchasein']) {
            $pur_id = I('post.id_purchasein/i');
            $p_id = I('post.product_id/i');
            $supplier = M('Supplier')->where(array('id_supplier'=>$_POST['id_supplier']))->find();
            if(empty($supplier['supplier_url'])) {
                D('Common/Supplier')->where(array('id_supplier'=>$_POST['id_supplier']))->save(array('supplier_url'=>$_POST['supplier_url']));
            }
            if(empty($supplier)) {
                $supp_id = D('Common/Supplier')->add(array('title'=>$_POST['supplier_name'],'supplier_url'=>$_POST['supplier_url'],'id_department'=>$_POST['id_department'],'created_at'=>date('Y-m-d H:i:s')));
            }
            $add_data['id_warehouse'] = I('post.id_warehouse');
            $add_data['billtype'] = I('post.billtype');
            $add_data['id_department'] = I('post.id_department');
            $add_data['updated_at'] = date('Y-m-d H:i:s');
            $add_data['id_supplier'] = !empty($_POST['id_supplier'])?I('post.id_supplier'):$supp_id;
            $add_data['purchase_channel'] = I('post.pur_channel');
            $add_data['inner_purchase_no'] = I('post.inner_purchase_no');
            $add_data['alibaba_no'] = I('post.alibaba_no');
            $add_data['price'] = isset($total_price) ? $total_price : 0;
            $add_data['total'] = I('post.total');
            $add_data['total_received'] = 0;
            $add_data['price_shipping'] = I('post.price_shipping');
            $add_data['date_from'] = I('post.date_from');
            $add_data['date_to'] = I('post.date_to');
            $add_data['remark'] = I('post.remark');
            $attr_name = I('post.attr_name');
            $attr_price = I('post.set_price');  //每个sku价格
            $total_price = I('post.total_price');  //总价格
            $total_qty = 0;
            $purchase_quantity = array_filter($_POST['purchase_quantity']);
            $set_qty = array_filter($_POST['set_qty']);
            $set_remark = array_filter($_POST['set_remark']);
            if ($set_qty) {
                foreach ($set_qty as $pro_id => $item) {
                    $get_qty = array_filter($item);
                    if ($get_qty) {
                        foreach($get_qty as $sku_qty){
                            $total_qty += $sku_qty;
                        }
                    }
                }
            }


            if($total_price > 0 ){
                $unit_price = round($total_price/$total_qty, 4);
            }
           $this->PurchaseIn->where(array('id_purchasein' => $pur_id))->save($add_data);

            if ($set_qty) {
                $total_received = 0;
                foreach ($set_qty as $pro_id => $item) {
                    $get_qty = $item;
                    if ($get_qty && count($get_qty)) {
                        foreach ($get_qty as $key => $qty) {
                            $sku_ids = $key; //SKU id
                            $get_attr_name = $attr_name[$pro_id][$key]; //属性名称
                            if(!isset($unit_price)){
                                $get_price = $attr_price[$pro_id][$key]; //价格
                                $total_price += $get_price; //总价格
                                $total_received+=$qty; //世纪收货数量
                            }


                            $array_data = array(
                                'id_purchasein' => $pur_id,
                                'id_product' => $pro_id,
                                'id_product_sku' => $sku_ids,
                                'option_value' => $get_attr_name,
                                'received' => $qty,
                                'remark'=>$set_remark[$pro_id][$sku_ids]
                            );
                              $this->PurchaseInitem->where(array('id_purchasein' => $pur_id, 'id_product' => $pro_id, 'id_product_sku' => $sku_ids))->save($array_data);
                        }
                    }
                }
            }
//            $this->split_purchase($pur_id);die;
            $update = array('total_received'=>$total_received, 'price' =>I('post.price'));
            $this->PurchaseIn->where('id_purchasein=' . $pur_id)->save($update);
            D("Purchase/PurchaseStatus")->add_pur_history($pur_id, $_POST['hid']=='2'?PurchaseStatus::UNCHECK:PurchaseStatus::UNSUBMIT, '编辑采购入库单');
            add_system_record(sp_get_current_admin_id(), 1, 3, '编辑采购入库单成功');
            $hid = I('post.hid');
            if(isset($hid)&&$hid){
                $url = U('/Purchase/In/save_submit')."?id=".$hid;
                echo "<script type='text/javascript'>";
                echo "location.href='$url'";
                echo "</script>";
            }else
                 $this->success("保存成功！", U('In/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 1, 3, '编辑采购入库单失败');
            $this->error("保存失败,产品ID不能为空");
        }
    }
    /*
     * 删除采购入库单
     */
    public function delete(){
        $id_purchasein = I('get.id');
        $res1 = $this->PurchaseIn->delete($id_purchasein);
        $res2 = $this->PurchaseInitem->where(array('id_purchasein'=>$id_purchasein))->delete();
        if(res1&&res2){
            $this->success("删除成功！", U('In/index'));
        }
        else{
            $this->error("删除失败");
        }
    }
    /*
     * 提交采购入库单
     */
    public function save_submit()
    {
        $id = I('get.id');
        $user_id = $_SESSION['ADMIN_ID'];
        $procedure_name = 'ERP_INOUT_SUBMIT';
        $array['billid'] = $id;
        $array['userid'] = $user_id;
        $array['tablename'] = 'erp_purchase_in';
        $array['inor'] = 'I';

        //通过erp_purchase_initem 表中的 received入库数量中数量更新库存  jiangqinqing 20171105
        $purchase_initem = M()->table("erp_purchase_initem")->where(" id_purchasein = '".$id."' ")->select();

        //获取现有产品的在途量,
        $id_product_sku = array_column($purchase_initem,"id_product_sku");
        $id_product_sku_road_num  = M()->table("erp_warehouse_product")->where(" id_product_sku IN ('".implode("','",$id_product_sku)."') ")->select();

        $result = Procedure::call($procedure_name,$array);
     
        //更新在途量,坚持到多少减多少原则,将存储过程里的在途量改成程序更新,增加log记录,jiangqinqing  20171127
        D("PurchaseStatus")->updateProductRoadNum($id,$purchase_initem,$id_product_sku_road_num);

        $old = explode(',',$id);
        if($result){
            $str = '';
            $new = array_keys($result);
            foreach($result as $k=>$v){
                $str.= "票据号：".$k."提交失败原因".$v.'。';
            }
            $url = U('/Purchase/In/index');
            echo "<meta charset='UTF-8' /><script type='text/javascript'>alert('".$str."');";
            echo "location.href='$url'";
            echo "</script>";
        }else{
            //匹配缺货订单
            if($new){
                $ids = array_diff($old,$new);
            }else{
                $ids = $old;
            }
            $new_id = trim(implode(',',$ids),',');
            $id_product_sku = $this->PurchaseInitem->where(array('id_purchasein'=>array('IN',$new_id)))->getField('id_product_sku',true);
            UpdateStatusModel::get_short_order($id_product_sku,$id);
            $this->success("提交成功！", U('Purchase/In/index'));
        }

    }
    /**
     * 生成采购打印单页面
     */
    public function get_purchase_dy() {
        $id = I('get.id/i');
        $pro_table_name = D("Common/Product")->getTableName();
        $pur_or_table_name = $this->PurchaseIn->getTableName();
        $sku_model = D("Common/ProductSku");
        $model = new \Think\Model;

        $purchase = $this->PurchaseIn->where('id_purchasein=' . $id)->find();

        $tarray = array();
        $stime = date('Y-m-d 00:00:00', strtotime('-3 day'));
        $etime = date('Y-m-d 00:00:00');
        $tarray[] = array('EGT', $stime);
        $tarray[] = array('LT', $etime);
        $twhere[] = array('created_at' => $tarray);

        if ($purchase['id_purchasein']) {
            /** 内部采购单号查所有入库数量 zx 12/04 **/
            $inner_purchase_total = array();
            $totalreceivednum=''; //总到货数量统计
            $where_inner['pi.inner_purchase_no'] = array('EQ',$purchase['inner_purchase_no']);
            $where_inner['pi.status'] = array('NEQ',1); //过滤掉未入库的状态
            $inner_purchase = M("PurchaseIn")->alias('pi')
                    ->field('pi.id_purchasein,pi.inner_purchase_no,pit.id_product_sku,pit.received')
                    ->join('__PURCHASE_INITEM__ pit ON pi.id_purchasein=pit.id_purchasein')
                    ->where($where_inner)
                    ->select();
            //取出所有的 id_product_sku
            if(!empty($inner_purchase)){
                foreach($inner_purchase as $ik=>$iv){
                    $inner_purchase_total[$iv['id_product_sku']]['received'] += $iv['received'];
                    $inner_purchase_total[$iv['id_product_sku']]['id_product_sku']   =  $iv['id_product_sku'];
                    $totalreceivednum += $iv['received'];
                }
                $inner_purchase_total = array_merge($inner_purchase_total); //键值从0开始
            }
            $purchase['totalreceivednum'] = $totalreceivednum;
            /** 内部采购单号查所有入库数量end **/
            
            $pur_pro_table_name = D("Common/PurchaseInitem")->getTableName();
            $pur_product = $model
                    ->table($pur_pro_table_name . ' AS pii INNER JOIN ' . $pro_table_name . ' AS p ON pii.id_product=p.id_product')
                    ->field('pii.*,p.title,p.thumbs,p.inner_name')
                    ->where('pii.id_purchasein=' . $purchase['id_purchasein'])
                    ->select();
            $purchase['totalreceived']=0;
            $purchase['totalquantity']=0;
            
            foreach ($pur_product as $key => $item) {
                //拼接总入库数 zx 12/05
                foreach($inner_purchase_total as $ipk=>$ipv){
                    if($item['id_product_sku'] == $ipv['id_product_sku']){
                        $pur_product[$key]['total_received_num'] = $ipv['received'];
                        break;
                    }
                }
                
                $get_model = $sku_model->find($item['id_product_sku']);
                $pur_product[$key]['sku'] = $get_model['sku'];
                $pur_product[$key]['barcode'] = $get_model['barcode'];
                $purchase['totalreceived'] += $item['received'];
                $purchase['totalquantity'] += $item['quantity'];
            }
        
            /***** 重构采购入库单信息 zx 12/02 *****/
            $field = "pi.*,pii.*";
            $purchase_list = $model->table($this->PurchaseIn->getTableName() . ' pi')
                  ->field($field)
                  ->join(array($this->PurchaseInitem->getTableName() . ' pii ON pii.id_purchasein = pi.id_purchasein'),'RIGHT')
                  ->where('pii.id_purchasein=' . $purchase['id_purchasein'])
                  ->group('pi.id_purchasein')
                  ->order("pi.updated_at DESC")
                  ->select();

            $_status = [
                  '1' => '<span style="color:blue">未入库</span>',
                  '2' => '已入库',
                  '3' => '<span style="color:red">部分入库</span>',  
                  '4' => '<span class="label label-info">已退货</span>' 
            ];
              
            $_billtype = [
                  '1' => '采购入库',
                  '2' => '<span style="color:red">采购退货</span>',
            ];

            foreach ($purchase_list as $k => $v) {
                switch ($v['purchase_channel']) {
                    case 1:
                        $pur_channel_name = '阿里巴巴';
                        break;
                    case 2:
                        $pur_channel_name = '淘宝';
                        break;
                    case 3:
                        $pur_channel_name = '线下';
                        break;
                    default :
                        $pur_channel_name = '';
                }
                $purchase_list[$k]['purchase_channel'] = $pur_channel_name;
            }   
            /***** end *****/

        }
        $supplier_name = M('Supplier')->field('title,supplier_url')->where(array('id_supplier' => $purchase['id_supplier']))->find();       
        $this->assign('supplier_name', $supplier_name);
        $this->assign('product', $pur_product);
        $this->assign('data', $purchase);
        $data = SearchData::search();
        $departments = array_column(SearchData::search()['departments'],'title','id_department');
        $warehouses = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
        $pur_status = array_column(SearchData::search()['pur_status'],'title','id_purchase_status');
        $users = M("Users")->where("user_status=1")->getField('id,user_nicename');

        $this->assign('search', $data);
        $this->assign('purchase_list', $purchase_list[0]); //采购入库信息 zx 12/02
        $this->assign('departments', $departments);
        $this->assign('warehouses', $warehouses);
        $this->assign('pur_status', $pur_status);
        $this->assign('_status', $_status);
        $this->assign('_billtype', $_billtype); 
        $this->assign('users', $users);
        // 存在action 则为采购入库单的查看    --Lily 2017-10-12
        if(isset($_GET['action']) && $_GET['action']=='look'){
           $this->display("get_purchase_dy1");
        }else{
           $this->display();
        }

    }
    /**
    *查看入库产品分配订单 --Lily 2017-11-08
    */
    public function get_purchase_disOrd(){
      $purchase_id = intval($_GET['id']);
      if(!$purchase_id)
      {$this->error("入库ID不存在！");
      }
      $purchasein_record = M("PurchaseOrderRecord")->where("id_purchasein=".$purchase_id)->getField("order_data");
      $id_order = [];
      if($purchasein_record){
       foreach (json_decode($purchasein_record,true) as $k => $v) {
          $id_order[$k] = $v['id_order'];
        }
    }
    if(!$id_order || empty($id_order)){
      $this->error("訂單ID不存在！");
    }
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where['o.id_department'] = $_GET['id_department'] ? array('EQ', $_GET['id_department']) : array('IN', $department_id);
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id' => $_SESSION['ADMIN_ID'], 'role_id' => 32))->find();
        if ( $role_user ) {
            if ( $_REQUEST['zone_id'] ) {
                $where['o.id_zone'] = array('EQ', $_REQUEST['zone_id']);
            }
            $belong_zone_id = isset($_SESSION['belong_zone_id']) ? $_SESSION['belong_zone_id'] : array(0);
            if ( !isset($where['o.id_zone']) ) {
                $where['o.id_zone'] = array('IN', $belong_zone_id);
            }
        }
        $where['o.id_order'] = is_array($id_order)?array("IN",$id_order):array("EQ",$id_order);
        $today_date = date('Y-m-d 00:00:00');
        $form_data = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $form_data['domain'] = $domain_model->get_all_domain();
        $form_data['domain_address'] = $domain_model->get_all_real_address();
        $form_data['track_status'] = D('Order/OrderShipping')->field('status_label as track_status')
            ->where("status_label is not null or status_label !='' ")
            ->group('status_label')->cache(true, 12000)->select();
            // dump($where);die;
        $today_where = $where;
        $today_where['o.created_at'] = array('EGT', $today_date);
        $count = M("Order")->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->where($where)->count();
            $today_total = M("Order")->alias('o')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->where($today_where)->count();
            $page = $this->page($count, $this->page);
            $order_list = M("Order")->alias('o')->field('o.*,s.date_signed,oi.ip as ip,dt.title as dt_title')
                ->join('__ORDER_SHIPPING__ s ON (o.id_order = s.id_order)', 'LEFT')
                ->join('__ORDER_INFO__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->join('__DEPARTMENT__ dt ON (o.id_department = dt.id_department)', 'LEFT')
                ->where($where)->order("id_order DESC")->limit($page->firstRow . ',' . $page->listRows)->select();
        $order_item = D('Order/OrderItem');
        foreach ($order_list as $key => $o) {
            $order_list[$key] = D("Order/OrderBlacklist")->black_list_and_ip_address($o);
            $order_list[$key]['products'] = $order_item->get_item_list($o['id_order']);
            $order_list[$key]['total_price'] = \Common\Lib\Currency::format($o['price_total'], $o['currency_code']);
            $order_list[$key]['http_referer'] = !empty($o['http_referer']) ? $o['http_referer'] : '--';
        }
        $advertiser = D('Common/Users')->field('id,user_nicename as name')->cache(true, 36000)->select();
        $advertiser = array_column($advertiser, 'name', 'id');
        $department_id = $_SESSION['department_id'];
        $department = D('Department/Department')->where('type=1')->cache(true, 3600)->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $shipping = M('Shipping')->field('id_shipping,title')->where('status=1')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        add_system_record($_SESSION['ADMIN_ID'], 4, 4, '查看入库产品分配订单');
        /** @var \Common\Model\ZoneModel $zone_model */
        $zone_model = D('Common/Zone');
        $role_user = M('RoleUser')->field('role_id')->where(array('user_id' => $_SESSION['ADMIN_ID'], 'role_id' => 32))->find();
        if ( $role_user ) {
            $belong_zone_id = isset($_SESSION['belong_zone_id']) ? $_SESSION['belong_zone_id'] : array(0);

            if ( !empty($belong_zone_id) ) {
                $all_zone = $zone_model->field('`title`,id_zone')->where(['id_zone' => array('IN', $belong_zone_id)])->order('`title` ASC')->select();
                $all_zone = $all_zone ? array_column($all_zone, 'title', 'id_zone') : '';
            }

        } else {
            $all_zone = $zone_model->all_zone();
        }
        // }
        // var_dump($shipping);die;
        $this->assign("all_zone", $all_zone);
        $this->assign("shipping", $shipping);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("advertiser", $advertiser);
        $this->assign("get", $_GET);
        $this->assign("form_data", $form_data);
        $this->assign("page", $page->show('Admin'));
        $this->assign("today_total", $today_total);
        $this->assign("order_total", $count);
        $this->assign("all_domain_total", $all_domain_total);
        $this->assign("warehouse", $warehouse);
        $status_model = D('Order/OrderStatus');
        $this->assign('status_list', $status_model->get_status_label());
        $this->assign("order_list", $order_list);
        $this->display();

    }


    /*
     * 采购入库单列表--导出Excel功能   liuruibin   20171018
     * */
    public function export_index(){
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $column = array(
            '制单日期','采购状态','付款渠道','入库日期','入库人','单据类型','所属仓库','所属部门','创建的员工','供应商','采购单号','内部采购单号','采购快递单号','采购渠道订单号','采购总数量','采购收到总数量','采购总价格','本次采购运费','快递单号','预计发货时间','预计到货时间','建立日期','更新日期','采购渠道','备注'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['pi.id_department'] = array('EQ', $_GET['id_department']);
        }
        if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
            $where['pi.id_warehouse'] = array('EQ', $_GET['id_warehouse']);
        }
        if (isset($_GET['status']) && $_GET['status']) {
            if($_GET['status']==3){
              $where['_string'] = "(pi.total_received<>pi.total AND pi.status=2) OR pi.status=3";
            }else{
              $where['pi.status'] = array('EQ', $_GET['status']);
            }
        }
        if (isset($_GET['shipping_no']) && $_GET['shipping_no']) {
            $where['pi.shipping_no'] = array('EQ', $_GET['shipping_no']);
        }
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $purchase_no=  trim($_GET['purchase_no']);
            $where['pi.purchase_no'] = array('like', "%{$purchase_no}%");
        }
        if ($_GET['alibaba_no']) {
            $alibaba_no=  trim($_GET['alibaba_no']);
            $where['pi.alibaba_no'] = array('like', "%{$alibaba_no}%");
        }
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $inner_purchase_no=  trim($_GET['inner_purchase_no']);
            $where['pi.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        $createAtArray = array();
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray[] = array('EGT',$_GET['start_time']);
        }
        if($_GET['end_time']) {
            $createAtArray[] = array('LT',$_GET['end_time']);
        }
        //新增入库时间查询  --Lily 20171103
         $IntimeAtArray = array();
          if(isset($_GET['startt_time']) && $_GET['startt_time']){
            $IntimeAtArray[] = array("EGT",$_GET['startt_time']);
          }
          if(isset($_GET['endt_time']) && $_GET['endt_time']){
            $IntimeAtArray[] = array("LT",$_GET['endt_time']);
          }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $id_product_sku = M('ProductSku')->where(array('sku' => trim($_GET['sku'])))->getField('id_product_sku');
            $where['id_product_sku'] = array('EQ', $id_product_sku);
        }
        //增加内部名搜索导出 zx 11/27
        if (isset($_GET['inner_name']) && $_GET['inner_name']) {
            //$id_product_sku = M('ProductSku')->where(array('sku' => trim($_GET['sku'])))->getField('id_product_sku');
            $where['pt.inner_name'] = array('like',"%{$_GET['inner_name']}%");

        }
        
        if(!empty($createAtArray)){
              $where[] = array('pi.billdate' => $createAtArray);
            }else if(!empty($IntimeAtArray)){
              $where[] = array('pi.intime'=>$IntimeAtArray);
            }else if(!empty($createAtArray) && !empty($IntimeAtArray)){
              $where[] = array('pi.billdate' => $createAtArray,'pi.intime'=>$IntimeAtArray);
            }
        $model = new \Think\Model();
        $idx = 2;
        $users = M('Users')->field('id,user_nicename')->select();
        $users = array_column($users, 'user_nicename', 'id');
        $list = $model->table($this->PurchaseIn->getTableName() . ' pi')
            ->field('pi.*,pii.*,pt.inner_name')
            ->join(array($this->PurchaseInitem->getTableName() . ' pii on pii.id_purchasein = pi.id_purchasein'),'RIGHT')
            //新增product关联查询，以便内部名搜索 zx 11/27
            ->join("__PRODUCT__ pt ON pii.id_product=pt.id_product",'LEFT') 
            ->where($where)->group('pi.id_purchasein')
            ->order("updated_at DESC")
            ->select();
        $departments = array_column(SearchData::search()['departments'],'title','id_department');
        $warehouses = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
        $suppliers = array_column(SearchData::search()['suppliers'],'title','id_supplier');
        $_status = [
            '1' => '未入库',
            '2' => '已入库',
            '3' => '部分入库'
        ];
        foreach ($list as $k => $v) {
            $list[$k]['billtype'] = $v['billtype']==1?'采购入库':'采购退货';
            $list[$k]['id_department'] = $departments[$v['id_department']];
            $list[$k]['id_warehouse'] = $warehouses[$v['id_warehouse']];
            $list[$k]['id_users'] = $users[$v['id_users']];
            $list[$k]['inerid'] = $users[$v['inerid']];
            $list[$k]['id_supplier'] = $suppliers[$v['id_supplier']];
            switch ($v['purchase_channel']) {
                case 1:
                    $pur_channel_name = '阿里巴巴';
                    break;
                case 2:
                    $pur_channel_name = '淘宝';
                    break;
                case 3:
                    $pur_channel_name = '线下';
                    break;
                default :
                    $pur_channel_name = '';
            }
            $list[$k]['purchase_channel'] = $pur_channel_name;
        }
        foreach ($list as  $k => $val) {
            $payment = $val['payment']&&$val['payment']==1?'货到付款':'通道付款';
            $data = array(
                $val['billdate'],$_status[$val['status']], $payment,$val['intime'],$val['inerid'],$val['billtype'],$val['id_warehouse'],
            $val['id_department'], $val['id_users'], $val['id_supplier'], $val['purchase_no'], $val['inner_purchase_no'], $val['shipping_no'], $val['alibaba_no'],
            $val['total'], $val['total_received'], $val['price'], $val['price_shipping'], $val['track_number'], $val['date_from'], $val['date_to'], $val['created_at'], $val['updated_at'], $val['purchase_channel'], $val['remark']
            );

            $j = 65;
            foreach ($data as $key => $col) {
                if ( $key != 8 && $key != 11 ) {
                    $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                } else {
                    $excel->getActiveSheet()->setCellValue(chr($j) . $idx, $col);
                }
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出仓库入库单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '仓库入库单列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '仓库入库单列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    /*
     * 采购入库单列表 -> 部门入库类 导单--导出Excel功能
     */
    public function part_export(){
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '内部采购单号', '采购渠道订单号', '颜色', '数量', '到货数量','少货数量'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        //获取采购订单ID
        $id_purchasein = I('get.id',0,'intval');
        if (isset($_GET['id']) && $_GET['id']) {
            $where['pp.id_purchasein'] = array('EQ', $id_purchasein);
        }
        $model = new \Think\Model();
        $list = $model->table('erp_purchase_initem AS ep LEFT JOIN erp_purchase_in AS pp ON ep.id_purchasein=pp.id_purchasein')
            ->field('ep.id_purchasein,ep.option_value,ep.quantity,ep.received,pp.inner_purchase_no,pp.total,pp.total_received,pp.alibaba_no')
            ->where($where)
            ->select();
        //循环动态获取所需要的数据
         foreach($list as $k=>$p){
             $data['list'][0] = $p['inner_purchase_no'];
             $data['list'][1] = $p['alibaba_no'];
             $data['list'][2][] = array($p['option_value'],$p['quantity'],$p['received'],'');
        }
        //dump($data);
        if ($data) {
            $k = 2;
            $num = 2;
            $sum = 2;
            foreach ($data as $kk => $items) {
                $j = 65;
                $count = count($items[2]);

                if ($count > 1) {
                    $excel->getActiveSheet()->mergeCells("A" . ($num ? $num : $idx).":"."A" . (($num ? $num : $idx)+$count-1));
                    $excel->getActiveSheet()->mergeCells("B" . ($num ? $num : $idx).":"."B" . (($num ? $num : $idx)+$count-1));
                    $num = (($num ? $num : $idx)+$count);
                } else {
                    $num  += 1;
                }

                foreach ($items as $key => $col) {
                    if (is_array ($col)) {

                        $a = 0;
                        foreach($col as $n=>$c) {
                            $odd_num[] = abs($c[2] - $c[1]); //缺货数量
                            $excel->getActiveSheet()->setCellValue("C" . $sum, $c[0]);
                            $excel->getActiveSheet()->setCellValueExplicit("D" . $sum, $c[1]); //数量
                            $excel->getActiveSheet()->setCellValueExplicit("E" . $sum, $c[2]); //到货数量
                            $excel->getActiveSheet() ->setCellValueExplicit("F". $sum, $odd_num[$n]); //缺货数量
                            $a++;
                            $sum = $sum+1;
                        }
                    } else {
                        $bb = $sum;
                        if ($key > 6) {
                            $bb = $sum - $count;
                        }
                        if (!in_array($key,[2,4,5,6])){
                            if(in_array($key, array(0,1,3))){
                                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $bb, $col);
                            }else{
                                $excel->getActiveSheet()->setCellValue(chr($j) . $bb, $col);
                            }
                        }
                    }
                    ++$j;//横
                }
                ++$idx;//列
                ++$k;
            }
        }

        add_system_record(sp_get_current_admin_id(), 7, 4, '导出采购单部分入库列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '采购单部分入库列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '采购单部分入库列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }

    /*
     * 导出功能方法   liuruibin   20171018
     * */
    public function export_csv($filename,$data)
    {
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=".$filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }
    /*
     * 拆分采购入库单
     */
//    public function split_purchase($id_purchasein){
////        $purchaseIn = $this->PurchaseIn->where(array('id_purchasein'=>$id_purchasein))->find();
//    $purchaseInitem = $this->PurchaseInitem->where(array('id_purchasein'=>$id_purchasein))->select();
//    foreach($purchaseInitem as $k=>$v){
//        if($v['received']!=$v['quantity'])
//        }
//
//    }
//

    function check_short_order(){
        //$new_id = array(3446,4289,4195,1064,4484,4304,4313,4344,3382,4404,4395,4115,4018);

        $id_product_sku = $this->PurchaseInitem->where(array('id_purchasein'=>array('IN',$new_id)))->getField('id_product_sku',true);

        UpdateStatusModel::get_short_order($id_product_sku,$id);
    }

}
