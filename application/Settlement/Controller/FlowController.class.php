<?php

namespace Settlement\Controller;

use Common\Controller\AdminbaseController;
use Purchase\Lib\PurchaseStatus;
use Common\Lib\SearchData;

/**
 * 部门模块
 * @Author Eva
 * @qq 549251235
 */
class FlowController extends AdminbaseController {
    public function _initialize() {
        parent::_initialize();
        $this->page = $_SESSION['set_page_row'] ?(int)$_SESSION['set_page_row'] : 20;
    }

    /*
     * 结算列表
     * */

    public function index() {
        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['ps.id_department'] = array('EQ', $_GET['id_department']);
        }
        if (isset($_GET['purchase_no']) && $_GET['purchase_no']) {
            $where['purchase_no'] = array('EQ', $_GET['purchase_no']);
        }
        if (isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no']) {
            $where['inner_purchase_no'] = array('EQ', $_GET['inner_purchase_no']);
        }
        if (isset($_GET['start_time']) && $_GET['start_time']) {
            $createAtArray = array();
            $createAtArray[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time']) {
                $createAtArray[] = array('LT', $_GET['end_time']);
            }
        }else{
            $createAtArray[] = array('EGT', date('Y-m-d', strtotime('-7 days')));
            $createAtArray[] = array('LT', date('Y-m-d', strtotime('+1 day')));
        }

        $where[] = array('date_settlement' => $createAtArray);
        $departments = array_column(SearchData::search()['departments'],'title','id_department');
        $suppliers = array_column(SearchData::search()['suppliers'],'title','id_supplier');
        $users = M('Users')->field('id,user_nicename')->select();
        $users = array_column($users, 'user_nicename', 'id');
        $res = M("PurchaseSettlement")->select();
        $count = M("PurchaseSettlement")->field('ps.*,p.purchase_no,p.inner_purchase_no')->alias('ps')->join('__PURCHASE__ as p on p.id_purchase = ps.id_erp_purchase','LEFT')
            ->where($where)->count();
        $page = $this->page($count, 20);
        $list = M("PurchaseSettlement")->field('ps.*,p.purchase_no,p.inner_purchase_no')->alias('ps')->join('__PURCHASE__ as p on p.id_purchase = ps.id_erp_purchase','LEFT')
            ->where($where)
            ->order("id_purchase_settlement DESC")
            ->limit($page->firstRow, $page->listRows)
            ->select();
        foreach($list as $k=>$v){
            $list[$k]['id_department'] = $departments[$v['id_department']];
            $list[$k]['id_supplier'] = $suppliers[$v['id_supplier']];
            $list[$k]['id_users'] = $users[$v['id_users']];
            $list[$k]['sku'] = M('ProductSku')->where(array('id_product_sku' => $v['id_product_sku']))->getField('sku');
            $list[$k]['inner_name'] = M('Product')->field('inner_name')->where(array('id_product' => $v['id_product']))->getField('inner_name');
        }
        $data['purchase_lists'] = $list;
        $data = array_merge($data,SearchData::search());
        $this->assign('data',$data);
        $this->assign("page", $page->show('Admin'));
        $this->display();

    }
}
