<?php

namespace Purchase\Controller;

use Common\Controller\AdminbaseController;
use Common\Lib\SearchData;
use Common\Model\ReturnGoodsModel;

/**
 * Class returnController
 * //仓库退货单  zhujie #094
 *
 * @package \Purchase\Controller
 */
class returnGoodsController extends AdminbaseController
{
    /**
     * @var \Model|\Think\Model
     */
    public $return_goods;
    /**
     * @var \Model|\Think\Model
     */
    public $return_goods_item;

    public $PurchaseIn;

    public $PurchaseProduct;

    /**
     * returnGoodsController constructor.
     */
    public function __construct ()
    {
        parent::__construct();
        $this->return_goods = D('ReturnGoods');
        $this->return_goods_item = D('ReturnGoodsItem');
        $this->PurchaseIn = D("PurchaseIn");
        $this->PurchaseProduct = D('PurchaseProduct');
    }

    /**
     * 显示
     */
    public function index ()
    {

        $depart_id = session('department_id');
        if ( isset($_GET['id_department']) && $_GET['id_department'] ) {
            $where['rg.id_department'] = array('EQ', $_GET['id_department'] );
        } else {
            $where['rg.id_department'] = array('IN', implode(',',$depart_id));
        }
        if ( isset($_GET['id_warehouse']) && $_GET['id_warehouse'] ) {
            $where['rg.id_warehouse'] = array('EQ', $_GET['id_warehouse']);
        }
        if ( isset($_GET['warehouse_status']) && $_GET['warehouse_status'] ) {
            $where['rg.warehouse_status'] = array('EQ', $_GET['warehouse_status']);
        } else {
            if ( I('get.warehouse_status') !== '0' ) {
                $where['rg.warehouse_status'] = 1;
                $_GET['warehouse_status'] = 1;
            }
        }
        if ( isset($_GET['return_type']) && $_GET['return_type'] ) {
            $where['rg.return_type'] = array('EQ', $_GET['return_type']);
        }
        if ( isset($_GET['receive_person']) && $_GET['receive_person'] ) {
            $where['rg.receive_person'] = array('EQ', $_GET['receive_person']);
        }

        if ( isset($_GET['shipping_no']) && $_GET['shiprgng_no'] ) {
            $where['rg.shipping_no'] = array('EQ', $_GET['shipping_no']);
        }
        if ( isset($_GET['track_number']) && $_GET['track_number'] ) {
            $where['rg.track_number'] = array('EQ', $_GET['track_number']);
        }
        if ( isset($_GET['phone']) && $_GET['phone'] != '' ) {
            $where['rg.phone'] = array('EQ', $_GET['phone']);
        }


        if ( isset($_GET['purchase_no']) && $_GET['purchase_no'] ) {
            $purchase_no = trim($_GET['purchase_no']);
            $where['rg.purchase_no'] = array('like', "%{$purchase_no}%");
        }
        if ( $_GET['alibaba_no'] ) {
            $alibaba_no = trim($_GET['alibaba_no']);
            $where['rg.alibaba_no'] = array('like', "%{$alibaba_no}%");
        }
        if ( isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no'] ) {
            $inner_purchase_no = trim($_GET['inner_purchase_no']);
            $where['rg.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if ( isset($_GET['start_time']) && $_GET['start_time'] ) {
            $where['rg.created_at'] = array('BETWEEN', [$_GET['start_time'], $_GET['end_time']]);
        } else {
            $new = date('Y-m-d');
            $start_time = date('Y-m-d H:i:s',strtotime($new.'-1 weeks'));
            $end_time =  date($new . " 23:59:59");
            $_GET['start_time'] = $start_time;
            $_GET['end_time'] = $end_time;
            $where['rg.created_at'] = array('BETWEEN', [$start_time,$end_time]);
        }

        if ( isset($_GET['sku']) && $_GET['sku'] ) {
            $id_product_sku = M('ProductSku')->where(array('sku' => trim($_GET['sku'])))->getField('id_product_sku');
            $where['id_product_sku'] = array('EQ', $id_product_sku);
        }
        $department = $this->return_goods->getAllDepartment($depart_id);
        $users = $this->return_goods->getAllUsers();
        //$where['rg.purchase_status'] = 2;
        $where['rg.return_type'] = 1;

        $count = M('returnGoods')->alias('rg')
            ->field("rg.*")
            ->join('__RETURN_GOODS_ITEM__ rgi ON rg.id_return = rgi.id_return_goods', 'right')
            ->join('__PRODUCT__ p ON p.id_product = rgi.id_product', 'left')
            ->where($where)
            ->order('rg.updated_at desc')
            ->group('rg.id_return')
            ->select();
        $count = count($count);
        $page = $this->page($count, 20);
        $list = $this->return_goods->getAllReturnGoods($where, $page);
        $warehouse = $this->return_goods->getAllWarehouse();
        $warehouse_status = ReturnGoodsModel::$warehouse_status;
        $return_type = ReturnGoodsModel::$return_type;
        $express_name = ReturnGoodsModel::$express_info;
        $collection_status = ReturnGoodsModel::$collection_status;
        $this->assign(compact('department', 'users', 'list', 'warehouse', 'warehouse_status', 'return_type', 'express_name','collection_status'));
        $this->assign('_get', I('get.'));
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }


    /**
     * 编辑页
     */
    public function edit ()
    {
        $id = I('get.id');
        $where['id_return'] = $id;
        $purchase = $this->return_goods->where(['id_return' => $id])->find();
        if ( $purchase['warehouse_status'] == 2 ) {
            $this->error('此退货单状态已经是 退货完成状态，不可编辑', U('Purchase/ReturnGoods/index'));
        }
        if ( $purchase['id_return'] ) {
            $pur_pro_table_name = D("Common/ReturnGoodsItem")->getTableName();
            $pur_product = M()->table($pur_pro_table_name . ' AS rgi INNER JOIN __PRODUCT__ AS p ON rgi.id_product=p.id_product')
                ->join('__WAREHOUSE_PRODUCT__ wp ON wp.id_product_sku = rgi.id_product_sku')
                ->field('rgi.*,p.title,p.thumbs,p.inner_name,wp.quantity as qty,wp.road_num,wp.qty_preout')
                ->where('rgi.id_return_goods=' . $purchase['id_return'])->select();
            foreach ($pur_product as $key => $item) {
                $get_model = M('ProductSku')->find($item['id_product_sku']);
                $pur_product[$key]['sku'] = $get_model['sku'];
            }

        }
        $supplier_name = M('Supplier')->field('title,supplier_url')->where(array('id_supplier' => $purchase['id_supplier']))->find();

        $return_type = ReturnGoodsModel::$return_type;
        $express_info = ReturnGoodsModel::$express_info;
        $this->assign(compact('return_type', 'express_info'));
        $this->assign('supplier_name', $supplier_name);
        $this->assign('product', $pur_product);
        $data = SearchData::search();
        $this->assign('search', $data);
        $this->assign('data', $purchase);
        $this->display();
    }

    //只保存数据 不提交
    public function just_save ()
    {

        $data_return_good = [
            'track_number' => I('post.track_number'),
            'qty_true' => I('post.qty_true'),
            'price_true' => I('post.price_true'),
            'express_id' => I('post.express_id'),
            'remark' => I('post.remark'),
            'price_shipping' => I('post.price_shipping'),
            'updated_at' => date('Y-m-d H:i:s'),
            'id_users_operate' => get_current_admin_id()
        ];
        $id_return = I('post.id');
        $set_qty = I('post.set_qty');
        $set_remark = I('post.set_remark');
        $return_goods_res = $this->return_goods->where(['id_return' => $id_return])
            ->save($data_return_good);

        foreach ($set_qty as $product_id => $v) {
            foreach ($v as $id_product_sku => $c_qty_true) {

                $array_data = array(
                    'c_qty_true' => $c_qty_true,
                    'c_qty_curr' => $c_qty_true,
                    'remark' => $set_remark[$product_id][$id_product_sku]
                );
                //更新退货详细表
                $res = $this->return_goods_item->where([
                    'id_return_goods' => $id_return,
                    'id_product' => $product_id,
                    'id_product_sku' => $id_product_sku])
                    ->setField($array_data);
                if ( $res === false ) {
                    M()->rollback();
                    $this->error($id_product_sku . '更新退货详情表失败');
                    exit;
                }
            }
        }
        if ($return_goods_res !== false) {
            $this->success("保存成功", U('returnGoods/index'));
            exit;
        }
        $this->success("保存失败");exit;
    }

    /**
     * 退换货提交
     */
    public function save_edit ()
    {
        $data_return_good = [
            'track_number' => I('post.track_number'),
            'qty_true' => I('post.qty_true'),
            'price_true' => I('post.price_true'),
            'express_id' => I('post.express_id'),
            'remark' => I('post.remark'),
            'price_shipping' => I('post.price_shipping'),
            'warehouse_status' => 2,
            'updated_at' => date('Y-m-d H:i:s'),
            'id_users_operate' => get_current_admin_id()
        ];
        $id_return = I('post.id');
        $id_warehouse = I('post.id_warehouse');//仓库id
        $return_type = I('post.return_type');//1 库存退货 or  2 在途退货
        $set_qty = I('post.set_qty');
        $set_remark = I('post.set_remark');

        $return_g_res = $this->return_goods->where(['id' => $id_return])->find();
        if ( !$return_g_res ) {
            $this->error('退货单不存在', U('Purchase/ReturnGoods/index'));
        }

        //事务
        M()->startTrans();
        foreach ($set_qty as $product_id => $v) {
            foreach ($v as $id_product_sku => $c_qty_true) {

                $array_data = array(
                    'c_qty_true' => $c_qty_true,
                    'c_qty_curr' => $c_qty_true,
                    'remark' => $set_remark[$product_id][$id_product_sku]
                );
                //更新退货详细表
                $res = $this->return_goods_item->where([
                    'id_return_goods' => $id_return,
                    'id_product' => $product_id,
                    'id_product_sku' => $id_product_sku])
                    ->setField($array_data);
                if ( $res === false ) {
                    M()->rollback();
                    $this->error($id_product_sku . '更新退货详情表失败');
                    exit;
                }
                //更新库存;
                switch ($return_type) {
                    case 1 ://库存退货
                        //判断有效库存足够
                        $warehouse_res = M("WarehouseProduct")->where([
                            'id_warehouse' => $id_warehouse,
                            'id_product' => $product_id,
                            'id_product_sku' => $id_product_sku,
                        ])->find();
                        $useful_stock = $warehouse_res['quantity'] - $warehouse_res['qty_preout'];//可用库存
                        if ( abs($c_qty_true) > 0 ) {
                            if ( $useful_stock < abs($c_qty_true) ) {
                                M()->rollback();
                                $this->error($id_product_sku . '库存不够,无法退货');
                                exit;
                            }
                        }

                        $update_WarehouseProduct = [
                            'quantity' => ['exp', 'quantity+' . $c_qty_true],
                        ];
                        $update_WarehouseProduct_res = M("WarehouseProduct")->where([
                            'id_warehouse' => $id_warehouse,
                            'id_product' => $product_id,
                            'id_product_sku' => $id_product_sku,
                        ])
                            ->setField($update_WarehouseProduct);
                        if ( $update_WarehouseProduct_res === false ) {
                            M()->rollback();
                            $this->error($id_product_sku . '更新库存表失败');
                            exit;
                        }
                        //增加日经销存表
                        $StorageFtp_insert_res = M("StorageFtp")->add([
                            'id_warehose' => $id_warehouse,
                            'id_product' => $product_id,
                            'docno' => $id_return,
                            'id_product_sku' => $id_product_sku,
                            'billtype' => "库存退货",
                            'id_users' => sp_get_current_admin_id(),
                            'billdate' => date('Y-m-d H:i:s'),
                            'qtychange' => $c_qty_true,
                        ]);
                        if ( $StorageFtp_insert_res === false ) {
                            M()->rollback();
                            $this->error($id_product_sku . '增加日进销存表失败');
                            exit;
                        }
                        break;
                    case 2 ://在途退货

                        //判断在途量足够
                        $warehouse_res = M("WarehouseProduct")->where([
                            'id_warehouse' => $id_warehouse,
                            'id_product' => $product_id,
                            'id_product_sku' => $id_product_sku,
                        ])->find();
                        $useful_road = $warehouse_res['road_num'];//可用在途量
                        if ( abs($c_qty_true) > 0 ) {
                            if ( $useful_road < abs($c_qty_true) ) {
                                M()->rollback();
                                $this->error($id_product_sku . '在途量不够,无法退货');
                                exit;
                            }
                        }

                        $update_WarehouseProduct = [
                            'road_num' => ['exp', 'road_num+' . $c_qty_true],
                        ];
                        $update_WarehouseProduct_res = M("WarehouseProduct")->where([
                            'id_warehouse' => $id_warehouse,
                            'id_product' => $product_id,
                            'id_product_sku' => $id_product_sku,
                        ])->setField($update_WarehouseProduct);


                        if ( $update_WarehouseProduct_res === false ) {

                            M()->rollback();
                            $this->error($id_product_sku . '库存表没有更新,无法退货');
                            exit;
                        }
                        break;

                }

            }
        }

        //更新采购入库表状态为已退货
        $PurchaseIn_update = $this->PurchaseIn->where(['purchase_no' => $return_g_res['purchase_no']])->save(['status' => 4]);
        if ( $PurchaseIn_update === false ) {
            M()->rollback();
            $this->error('采购入库表状态 更新失败');
            exit;
        }

        //更新退货表
        $return_goods_res = $this->return_goods->where(['id_return' => $id_return])
            ->save($data_return_good);
        if ( $return_goods_res === false ) {
            M()->rollback();
            $this->error($id_return . '退货表无法更新');
            exit;
        }
        M()->commit();
        add_system_record(sp_get_current_admin_id(), 2, 1, '编辑保存退货单');
        $this->success("退货成功", U('Purchase/ReturnGoods/index'));

    }

    //查看
    public function view ()
    {
        $id = I('get.id/i');
        $data = $this->return_goods->where('id_return=' . $id)->find();

        if ( $data['id_return'] ) {
            $pur_pro_table_name = D("Common/ReturnGoodsItem")->getTableName();
            $pur_product = M()->table($pur_pro_table_name . ' AS pii INNER JOIN __PRODUCT__ AS p ON pii.id_product=p.id_product')
                ->field('pii.*,p.title,p.thumbs,p.inner_name')
                ->where('pii.id_return_goods=' . $data['id_return'])->select();
            $data['totalreturn'] = 0;
            foreach ($pur_product as $key => $item) {
                $get_model = M('ProductSku')->find($item['id_product_sku']);
                $pur_product[$key]['sku'] = $get_model['sku'];
                $pur_product[$key]['barcode'] = $get_model['barcode'];
                $data['totalreturn'] += $item['c_qty_true'];
            }

        }
        $search = SearchData::search();
        $departments = array_column(SearchData::search()['departments'], 'title', 'id_department');
        $warehouses = array_column(SearchData::search()['warehouses'], 'title', 'id_warehouse');
        $suppliers = array_column(SearchData::search()['suppliers'], 'title', 'id_supplier');
        $this->assign(compact('data', 'search', 'departments', 'warehouses', 'suppliers'));

        $this->assign('product', $pur_product);
        $this->display();
    }

    //导出
    public function export_index ()
    {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Writer.CSV");
        $excel = new \PHPExcel();
        $idx = 2;
        $column = array(
            '采购单号', '内部采购单号', '采购渠道订单号', '退货时间', 'SKU', '退货数量', '实际退货数量', '单价',
            '退货总数','退货总价', '实际退货总数','实际退货总价', '退货运费', '部门', '采购员', '仓库状态', '退货方式', '备注'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }

        if ( isset($_GET['id_department']) && $_GET['id_department'] ) {
            $where['rg.id_department'] = array('EQ', $_GET['id_department']);
        }
        if ( isset($_GET['id_warehouse']) && $_GET['id_warehouse'] ) {
            $where['rg.id_warehouse'] = array('EQ', $_GET['id_warehouse']);
        }
        if ( isset($_GET['warehouse_status']) && $_GET['warehouse_status'] ) {
            $where['rg.warehouse_status'] = array('EQ', $_GET['warehouse_status']);
        } else {
            if ( I('get.warehouse_status') !== '0' ) {
                $where['rg.warehouse_status'] = 1;
                $_GET['warehouse_status'] = 1;
            }
        }
        if ( isset($_GET['return_type']) && $_GET['return_type'] ) {
            $where['rg.return_type'] = array('EQ', $_GET['return_type']);
        }

        if ( isset($_GET['shipping_no']) && $_GET['shiprgng_no'] ) {
            $where['rg.shipping_no'] = array('EQ', $_GET['shipping_no']);
        }
        if ( isset($_GET['purchase_no']) && $_GET['purchase_no'] ) {
            $purchase_no = trim($_GET['purchase_no']);
            $where['rg.purchase_no'] = array('like', "%{$purchase_no}%");
        }
        if ( $_GET['alibaba_no'] ) {
            $alibaba_no = trim($_GET['alibaba_no']);
            $where['rg.alibaba_no'] = array('like', "%{$alibaba_no}%");
        }
        if ( isset($_GET['inner_purchase_no']) && $_GET['inner_purchase_no'] ) {
            $inner_purchase_no = trim($_GET['inner_purchase_no']);
            $where['rg.inner_purchase_no'] = array('like', "%{$inner_purchase_no}%");
        }
        if ( isset($_GET['start_time']) && $_GET['start_time'] ) {
            $where['rg.created_at'] = array('BETWEEN', [$_GET['start_time'], $_GET['end_time']]);
        }

        if ( isset($_GET['sku']) && $_GET['sku'] ) {
            $id_product_sku = M('ProductSku')->where(array('sku' => trim($_GET['sku'])))->getField('id_product_sku');
            $where['id_product_sku'] = array('EQ', $id_product_sku);
        }
        $depart_id = session('department_id');
        $department = $this->return_goods->getAllDepartment($depart_id);
        $users = $this->return_goods->getAllUsers();
        $where['rg.purchase_status'] = 2;

        $lists = M('returnGoods')->alias('rg')
            ->field("rg.*,pk.sku,rgi.option_value,rgi.quantity,rgi.c_qty_true,rgi.c_qty,rgi.price")
            ->join('__RETURN_GOODS_ITEM__ rgi ON rg.id_return = rgi.id_return_goods', 'right')
            ->join('__PRODUCT__ p ON p.id_product = rgi.id_product', 'left')
            ->join('__PRODUCT_SKU__ pk ON pk.id_product_sku = rgi.id_product_sku', 'left')
            ->where($where)
            ->order('rg.updated_at desc')
            ->select();

        $warehouse = $this->return_goods->getAllWarehouse();
        $warehouse_status = ReturnGoodsModel::$warehouse_status;
        $return_type = ReturnGoodsModel::$return_type;
        $express_name = ReturnGoodsModel::$express_info;
        $all_lists = [];

        foreach ($lists as $k => $v) {
            $all_lists[$v['id_return']]['id_return'] = $v['id_return'];
            $all_lists[$v['id_return']]['purchase_no'] = $v['purchase_no'];
            $all_lists[$v['id_return']]['id_warehouse'] = $v['id_warehouse'];
            $all_lists[$v['id_return']]['id_department'] = $v['id_department'];
            $all_lists[$v['id_return']]['id_users'] = $v['id_users'];
            $all_lists[$v['id_return']]['id_users_operate'] = $v['id_users_operate'];
            $all_lists[$v['id_return']]['id_supplier'] = $v['id_supplier'];
            $all_lists[$v['id_return']]['inner_purchase_no'] = $v['inner_purchase_no'];
            $all_lists[$v['id_return']]['alibaba_no'] = $v['alibaba_no'];
            $all_lists[$v['id_return']]['total_price'] = $v['total_price'];
            $all_lists[$v['id_return']]['total_qty'] = $v['total_qty'];
            $all_lists[$v['id_return']]['qty_true'] = $v['qty_true'];
            $all_lists[$v['id_return']]['price_true'] = $v['price_true'];
            $all_lists[$v['id_return']]['purchase_status'] = $v['purchase_status'];
            $all_lists[$v['id_return']]['warehouse_status'] = $v['warehouse_status'];
            $all_lists[$v['id_return']]['return_type'] = $v['return_type'];
            $all_lists[$v['id_return']]['return_time'] = $v['return_time'];
            $all_lists[$v['id_return']]['sku_list'][$k]['sku'] = $v['sku'];
            /*$all_lists[$v['id_return']]['sku_list'][$k]['option_value'] = $v['option_value'];
            $all_lists[$v['id_return']]['sku_list'][$k]['quantity'] = $v['quantity'];*/
            $all_lists[$v['id_return']]['sku_list'][$k]['c_qty_true'] = $v['c_qty_true'];
            $all_lists[$v['id_return']]['sku_list'][$k]['c_qty'] = $v['c_qty'];
            $all_lists[$v['id_return']]['sku_list'][$k]['price'] = $v['price'];
        }
        foreach ($all_lists as $p) {
            $data[] = array(
                $p['purchase_no'], $p['inner_purchase_no'], $p['alibaba_no'], $p['return_time'],$p['sku_list'] ,'', '', '', $p['total_qty'],$p['total_price'],$p['qty_true'], $p['price_true'], $p['price_shipping'],  $department[$p['id_department']], $users[$p['id_users']], $warehouse_status[$p['warehouse_status']], strip_tags ($return_type[$p['return_type']]),$p['remark']
            );
        }
        if ( $data ) {
            $k = 2;
            $num = 2;
            $sum = 2;
            foreach ($data as $kk => $items) {
                $j = 65;
                $count = count($items[4]);
                if ( $count > 1 ) {
                    $excel->getActiveSheet()->mergeCells("A" . ($num ? $num : $idx) . ":" . "A" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("B" . ($num ? $num : $idx) . ":" . "B" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("C" . ($num ? $num : $idx) . ":" . "C" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("D" . ($num ? $num : $idx) . ":" . "D" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("I" . ($num ? $num : $idx) . ":" . "I" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("J" . ($num ? $num : $idx) . ":" . "J" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("K" . ($num ? $num : $idx) . ":" . "K" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("L" . ($num ? $num : $idx) . ":" . "L" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("M" . ($num ? $num : $idx) . ":" . "M" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("N" . ($num ? $num : $idx) . ":" . "N" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("O" . ($num ? $num : $idx) . ":" . "O" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("P" . ($num ? $num : $idx) . ":" . "P" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("Q" . ($num ? $num : $idx) . ":" . "Q" . (($num ? $num : $idx) + $count - 1));
                    $excel->getActiveSheet()->mergeCells("R" . ($num ? $num : $idx) . ":" . "R" . (($num ? $num : $idx) + $count - 1));
                    $num = (($num ? $num : $idx) + $count);
                } else {
                    $num += 1;
                }
                foreach ($items as $key => $col) {

                    if ( is_array($col) ) {

                        $a = 0;
                        foreach ($col as $c) {
                            $excel->getActiveSheet()->setCellValue("E" . $sum, $c['sku']);
                            $excel->getActiveSheet()->setCellValue("F" . $sum, $c['c_qty']);
                            $excel->getActiveSheet()->setCellValue("G" . $sum, $c['c_qty_true']);
                            $excel->getActiveSheet()->setCellValue("H" . $sum, $c['price']);
                            $a++;
                            $sum = $sum + 1;
                        }
                    } else {
                        $bb = $sum;
                        if ( $key > 7 ) {
                            $bb = $sum - $count;
                        }
                        if ( !in_array($key, [4, 5, 6, 7,]) ) {
                            if ( in_array($key, array(0, 2)) ) {
                                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $bb, $col);
                            } else {
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
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出退货单列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '创建退货单列表信息.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '创建退货单列表信息.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
}