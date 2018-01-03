<?php

namespace Purchase\Controller;

use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;
use Purchase\Lib\PurchaseStatus;
use Common\Lib\SearchData;
/**
 * 采购入库
 * @Author Eva
 * @qq 549251235
 * Class IndexController
 * @package Purchase\Controller
 */
class PrintController extends AdminbaseController {

    protected $Purchase, $Users;

    public function _initialize() {
        parent::_initialize();
        $this->PurchaseView= M("PurchaseView");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }
    /*
     *
     */
      public function index(){
          if (isset($_GET['id_department']) && $_GET['id_department']) {
              $where['id_department'] = array('EQ', $_GET['id_department']);
          }
          if (isset($_GET['id_warehouse']) && $_GET['id_warehouse']) {
              $where['id_warehouse'] = array('EQ', $_GET['id_warehouse']);
          }
          if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
              $where['purchase_no'] = array('EQ', $_GET['purchase_no']);
          }
          if (isset($_GET['id_supplier']) && $_GET['id_supplier']) {
              $where['id_supplier'] = array('EQ', $_GET['id_supplier']);
          }
          if (isset($_GET['start_time']) && $_GET['start_time']) {
              $createAtArray = array();
              $createAtArray[] = array('EGT', $_GET['start_time']);
              if ($_GET['end_time']) {
                  $createAtArray[] = array('LT', $_GET['end_time']);
              }
          }else{
              $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-7 days')));
              $createAtArray[] = array('LT', date('Y-m-d',strtotime('+1 day')));
          }
          $where[] = array('billdate' => $createAtArray);
          $count = $this->PurchaseView->where($where)->count();
          $page = $this->page($count, 20);
          $data['views'] = $this->PurchaseView->where($where)->order('billdate DESC')->limit($page->firstRow, $page->listRows)->select();

          $departments = array_column(SearchData::search()['departments'],'title','id_department');
          $warehouses = array_column(SearchData::search()['warehouses'],'title','id_warehouse');
          $suppliers = array_column(SearchData::search()['suppliers'],'title','id_supplier');
          foreach($data['views'] as $k=>$v){
              $data['views'][$k]['id_warehouse'] = $warehouses[$v['id_warehouse']];
              $data['views'][$k]['id_department'] = $departments[$v['id_department']];
              $data['views'][$k]['id_supplier'] = $suppliers[$v['id_supplier']];
              $data['views'][$k]['sku'] = M('ProductSku')->where(array('id_product_sku' => $v['id_product_sku']))->getField('sku');
              $data['views'][$k]['inner_name'] = M('Product')->field('inner_name')->where(array('id_product' => $v['id_product']))->getField('inner_name');
              $data['views'][$k]['thumbs'] = json_decode($v['thumbs'],true)['photo'][0]['url'];
          }
//          var_dump(   $data['views']);die;
          $data = array_merge($data,SearchData::search());
          $this->assign('data',$data);
          $this->assign("page", $page->show('Admin'));
          $this->display('index');


      }
}
