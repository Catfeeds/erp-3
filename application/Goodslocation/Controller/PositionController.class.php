<?php

namespace Goodslocation\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;
use Order\Lib\OrderStatus;

class PositionController extends AdminbaseController {
    public function _initialize() {
        parent::_initialize();
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }
    public function index()
    {
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $where = array();
        if(isset($_GET['id_warehouse'])&&$_GET['id_warehouse']){
            $where['aga.id_warehouse'] = $_GET['id_warehouse'];
            if(isset($_GET['area_title'])&&$_GET['area_title']){
                $w['title'] = $_GET['area_title'];
                $w['id_warehouse'] = $_GET['id_warehouse'];
                $id_goods_area = M('WarehouseGoodsArea')->field('id_goods_area')->where($w)->find();
                $id_goods_area = implode('',$id_goods_area);
                $where['aga.id_goods_area'] = $id_goods_area;
             }
        }
        if(isset($_GET['id_department'])&&$_GET['id_department']){
            $where['p.id_department'] = trim($_GET['id_department']);
        }
        if(isset($_GET['goods_name'])&&$_GET['goods_name']){
            $where['aga.goods_name'] = array('LIKE',trim($_GET['goods_name']));
        }
        if(isset($_GET['sku'])&&$_GET['sku']){
            $key_where['pk.sku'] = array('LIKE', '%' .trim($_GET['sku']) . '%');
            $key_where['pk.barcode'] = array('LIKE', '%' . trim($_GET['sku']) . '%');
            $key_where['_logic'] = 'or';
            $where['_complex'] = $key_where;
        }
        if(isset($_GET['title'])&&$_GET['title']){
            $where['p.title'] = trim($_GET['title']);
        }
        if(isset($_GET['inner_name'])&&$_GET['inner_name']){
            $where['p.inner_name'] = array('LIKE', '%' .trim($_GET['inner_name']) . '%');
        }
        $count = M('WarehouseGoodsAllocation')->alias('aga')
            ->field('aga.id_warehouse,p.id_department,aga.goods_name,pk.sku,p.title,was.quantity,aga.id_warehouse_allocation,aga.id_goods_area')
            ->join('__WAREHOUSE_ALLOCATION_STOCK__  was on was.id_warehouse_allocation = aga.id_warehouse_allocation','LEFT')
            ->join('__PRODUCT_SKU__ as pk on was.id_product_sku = pk.id_product_sku','LEFT')
            ->join('__PRODUCT__ as p on was.id_product = p.id_product','LEFT')
            ->where($where)->count();
        $page = $this->page($count, 20);
        $area_list = M('WarehouseGoodsArea')->field('id_goods_area,title')->select();
        $area_list = array_column($area_list,'title','id_goods_area');

        $add_goods_list = M('WarehouseGoodsAllocation')->alias('aga')
                        ->field('aga.id_warehouse,was.id,p.id_department,aga.goods_name,pk.sku,pk.barcode,p.title,was.quantity,aga.id_warehouse_allocation,aga.id_goods_area,was.id_product_sku,p.inner_name,pk.title as pktitle')
                        ->join('__WAREHOUSE_ALLOCATION_STOCK__  was on was.id_warehouse_allocation = aga.id_warehouse_allocation','LEFT')
                        ->join('__PRODUCT_SKU__ as pk on was.id_product_sku = pk.id_product_sku','LEFT')
                        ->join('__PRODUCT__ as p on was.id_product = p.id_product','LEFT')
                        ->where($where)->limit($page->firstRow, $page->listRows)->select();

        foreach($add_goods_list as &$row){
            $tmp_quantity = M('Order')->alias('o')
                ->field('SUM(ABS(wr.num)) as tmp_quantity')
                ->join('__WAREHOUSE_RECORD__ as wr ON wr.id_order = o.id_order', 'LEFT')
                ->where(array(
                    'o.id_order_status'=>array('IN', array(OrderStatus::APPROVED, OrderStatus::UNPICKING, OrderStatus::PICKING))
                ))   //未配货、配货中、已审核订单    即扣了库存但是实际产品还在仓库中的订单
                ->where(array(
                    'wr.id_product_sku' => $row['id_product_sku'],
                    'wr.id_warehouse_allocation' => $row['id_warehouse_allocation'],
                    'wr.type' => 3,
                ))
                ->find();
            $tmp_quantity = empty($tmp_quantity) ? 0 : intval($tmp_quantity['tmp_quantity']);
            $row['actual_quantity'] = intval($row['quantity']) + $tmp_quantity;
        }

        $this->assign('add_goods_list',$add_goods_list);
        $this->assign('warehouse',$warehouse);
        $this->assign('department',$department);
        $this->assign('area_list',$area_list);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        add_system_record($_SESSION['ADMIN_ID'], 4, 3,'查看货位列表');
        $this->display();
    }

    public function export()
    {
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();


        $column = array(
            '所属仓库', '部门', '货位区域名称', '货位名称', 'SKU', '条形码', '产品名', '可用库存', '实际库存'
        );
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        $idx = 2;

        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $department  = D('Department/Department')->where('type=1')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $where = array();
        if(isset($_GET['id_warehouse'])&&$_GET['id_warehouse']){
            $where['aga.id_warehouse'] = $_GET['id_warehouse'];
            if(isset($_GET['area_title'])&&$_GET['area_title']){
                $w['title'] = $_GET['area_title'];
                $w['id_warehouse'] = $_GET['id_warehouse'];
                $id_goods_area = M('WarehouseGoodsArea')->field('id_goods_area')->where($w)->find();
                $id_goods_area = implode('',$id_goods_area);
                $where['aga.id_goods_area'] = $id_goods_area;
            }
        }
        if(isset($_GET['id_department'])&&$_GET['id_department']){
            $where['p.id_department'] = trim($_GET['id_department']);
        }
        if(isset($_GET['goods_name'])&&$_GET['goods_name']){
            $where['aga.goods_name'] = array('LIKE',trim($_GET['goods_name']));
        }
        if(isset($_GET['sku'])&&$_GET['sku']){
            $key_where['pk.sku'] = array('LIKE', '%' .trim($_GET['sku']) . '%');
            $key_where['pk.barcode'] = array('LIKE', '%' . trim($_GET['sku']) . '%');
            $key_where['_logic'] = 'or';
            $where['_complex'] = $key_where;
        }
        if(isset($_GET['title'])&&$_GET['title']){
            $where['p.title'] = trim($_GET['title']);
        }

        $area_list = M('WarehouseGoodsArea')->field('id_goods_area,title')->select();
        $area_list = array_column($area_list,'title','id_goods_area');
        $add_goods_list = M('WarehouseGoodsAllocation')->alias('aga')
            ->field('aga.id_warehouse,was.id,p.id_department,aga.goods_name,pk.sku,pk.barcode,p.title,was.quantity,aga.id_warehouse_allocation,aga.id_goods_area,was.id_product_sku')
            ->join('__WAREHOUSE_ALLOCATION_STOCK__  was on was.id_warehouse_allocation = aga.id_warehouse_allocation','LEFT')
            ->join('__PRODUCT_SKU__ as pk on was.id_product_sku = pk.id_product_sku','LEFT')
            ->join('__PRODUCT__ as p on was.id_product = p.id_product','LEFT')
            ->where($where)->select();

        foreach($add_goods_list as &$row){
            $tmp_quantity = M('Order')->alias('o')
                ->field('SUM(ABS(wr.num)) as tmp_quantity')
                ->join('__WAREHOUSE_RECORD__ as wr ON wr.id_order = o.id_order', 'LEFT')
                ->where(array(
                    'o.id_order_status'=>array('IN', array(OrderStatus::APPROVED, OrderStatus::UNPICKING, OrderStatus::PICKING))
                ))   //未配货、配货中、已审核订单    即扣了库存但是实际产品还在仓库中的订单
                ->where(array(
                    'wr.id_product_sku' => $row['id_product_sku'],
                    'wr.id_warehouse_allocation' => $row['id_warehouse_allocation'],
                    'wr.type' => 3,
                ))
                ->find();
            $tmp_quantity = empty($tmp_quantity) ? 0 : intval($tmp_quantity['tmp_quantity']);
            $row['actual_quantity'] = intval($row['quantity']) + $tmp_quantity;
        }

//        $this->assign('add_goods_list',$add_goods_list);
//        $this->assign('warehouse',$warehouse);
//        $this->assign('department',$department);
//        $this->assign('area_list',$area_list);
//        $this->assign("Page", $page->show('Admin'));
//        $this->assign("current_page", $page->GetCurrentPage());
//        add_system_record($_SESSION['ADMIN_ID'], 4, 3,'查看货位列表');
//        $this->display();

        if(!empty($add_goods_list)){
            foreach($add_goods_list as $k =>$goods){
                $data[] = array(
                    $warehouse[$goods['id_warehouse']],
                    $department[$goods['id_department']],
                    $area_list[$goods['id_goods_area']],
                    $goods['goods_name'],
                    $goods['sku'],
                   $goods['barcode'],
                    $goods['title'],
                    $goods['quantity'],
                    $goods['actual_quantity'],
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
        add_system_record(sp_get_current_admin_id(), 7, 4, '导出货位列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '货位列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '货位列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }
    /*
     * 添加货位
     */
    public function add()
    {
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        if(IS_POST)
        {
            $_POST['id_goods_area'] = strtoupper($_POST['id_goods_area']);
            $_POST['goods_name'] = strtoupper($_POST['goods_name']);
            $res = M('WarehouseGoodsAllocation')->add($_POST);
            if ($res == false) {
                $this->error("添加失败！", U('Position/add'));
            }else{
                $this->success("添加完成！", U('Position/index'));
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 2, 3,'添加货位');
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /*
     * 根据仓库名称查询对应的货位区域名称
     */
    public function select_by_warehoues()
    {
        $id_warehouse = $_GET['id_warehouse'];
        $titles = M('WarehouseGoodsArea')->field('id_goods_area,title')->where(array('id_warehouse'=>$id_warehouse))->order('title')->select();
        if($titles){
            echo json_encode($titles);
        }else{
            $flag = 1;
            $msg = '未查询到仓库对应的货位区域名称，请先添加！';
            echo json_encode(array('flag'=>$flag,'msg'=>$msg));
        }

    }

    /*
     * 添加数据前验证数据是否已存在
     */

    public function select_find()
    {
        $id_warehouse = $_GET['id_warehouse'];
        $id_goods_area = $_GET['id_goods_area'];
        $goods_name = $_GET['goods_name'];
        $id_warehouse_allocation = $_GET['id_warehouse_allocation'];
        $find  = M('WarehouseGoodsAllocation')->field('id_warehouse_allocation')->where(array('id_warehouse'=>$id_warehouse,'id_goods_area'=>$id_goods_area,'goods_name'=>$goods_name))->find();
        $find = implode('',$find);
        if($find)
        {
            if($find == $id_warehouse_allocation){
                $flag = 0;
                $msg = '货位信息未修改';
                echo json_encode(array('flag'=>$flag,'msg'=>$msg));
            }else{
                $flag = 1;
                $msg = '已存在相同货位信息，不能添加';
                echo json_encode(array('flag'=>$flag,'msg'=>$msg));
            }
        }else{
            $flag = 0;
            $msg = '数据不存在，可添加';
            echo json_encode(array('flag'=>$flag,'msg'=>$msg));
        }
    }
    /*
     * 编辑货位
     */
    public function edit()
    {
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $id_warehouse_allocation = $_GET['id_warehouse_allocation'];
        if(isset($_GET['id'])&&$_GET['id']){
            $where_e['id'] = $_GET['id'];
        }
        $where_e['wga.id_warehouse_allocation'] = $id_warehouse_allocation;
        if($id_warehouse_allocation)
        {

            $list = M('WarehouseGoodsAllocation')->alias('wga')->field('wga.*,was.*,pk.sku,p.title,wga.id_warehouse as id_warehouse')
                ->join('__WAREHOUSE_ALLOCATION_STOCK__ was on wga.id_warehouse_allocation = was.id_warehouse_allocation','LEFT')
                ->join('__PRODUCT_SKU__ as pk on was.id_product_sku = pk.id_product_sku','LEFT')
                ->join('__PRODUCT__ as p on was.id_product = p.id_product','LEFT')
                ->where($where_e)->find();
        }
        if(IS_POST){
            $_POST['goods_name'] = strtoupper($_POST['goods_name']);
            $id_warehouse_allocation = $_POST['id_warehouse_allocation'];

            $res1 = M('WarehouseGoodsAllocation')->save($_POST);
            if($res1===false){
                add_system_record($_SESSION['ADMIN_ID'], 2, 3,'修改货位失败');
                $this->error("修改失败！", U('Position/edit',array('id_warehouse_allocation'=>$_POST['id_warehouse_allocation'])));
                die;
            }
            $data['quantity'] = $_POST['quantity'];
            $data['id'] = $_POST['id'];
            $where_id['id'] = $_POST['id'];
            $stock_info = M('WarehouseAllocationStock')->where($where_id)->find();
            $old_quantity = $stock_info['quantity'];
            $id_warehouse = M('WarehouseGoodsAllocation')->where(array('id_warehouse_allocation'=> $id_warehouse_allocation))->getField('id_warehouse');
            $data['id_users'] = $stock_info['id_users'].','.$_SESSION['ADMIN_ID'];
            $res2 = M('WarehouseAllocationStock')->save($data);

            D('Common/WarehouseRecord')->write(
                array(
                    'type' => 'MANUAL',
                    'num_before' => $old_quantity,
                    'num_after' => $data['quantity'],
                    'id_warehouse' => $id_warehouse,
                    'id_warehouse_allocation' => $id_warehouse_allocation,
                    'id_product_sku' => $stock_info['id_product_sku'],
                )
            );
            if($res2===false){
                add_system_record($_SESSION['ADMIN_ID'], 2, 3,'修改库存失败');
                $this->error("修改失败！", U('Position/edit',array('id_warehouse_allocation'=>$_POST['id_warehouse_allocation'])));
                die;
            }
            $where['id_product'] = $_POST['id_product'];
            $where['id_warehouse'] = $_POST['id_warehouse'];
            $where['id_product_sku'] = $_POST['id_product_sku'];
            $quantity = M('WarehouseProduct')->where($where)->getField('quantity');
            $compare = (int)($old_quantity-$_POST['quantity']);
            if($compare<0){
                $quantity += ($_POST['quantity'] - $old_quantity) ;
            }elseif($compare>0){
                $quantity -= ($old_quantity - $_POST['quantity']);
            }else{
                $this->success("库存未修改！",U('Position/index'));
                die;
            }

            $res3 = M('WarehouseProduct')->where($where)->setField(array('quantity'=>$quantity));
            if($res3===false){
                add_system_record($_SESSION['ADMIN_ID'], 2, 3,'修改库存失败');
                $this->error("修改失败！", U('Position/edit',array('id_warehouse_allocation'=>$_POST['id_warehouse_allocation'])));
                die;
            }
            $this->updateQuantity($_POST['id_product_sku'],$quantity);
            add_system_record($_SESSION['ADMIN_ID'], 2, 3,'修改货位成功');
            $this->success("修改完成！",U('Position/index'));

        }
        $this->assign('list',$list);
        $this->assign('warehouse',$warehouse);
        //var_dump($list);var_dump($warehouse);
        $this->display();
    }

    /*
     * 导入货位
     */
    public function import_position()
    {
        $infor = array(
            'error'   => array(),
            'warning' => array(),
            'success' => array()
        );
        $warehouses = M('Warehouse')->field('id_warehouse,title')->select();
        $warehouses = array_column($warehouses,'id_warehouse','title');
        $titles = M('WarehouseGoodsArea')->field('id_warehouse,title,id_goods_area')->select();
        $total = 0;
        if (IS_POST) {
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('goodslocation', 'import_position', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            $flag = false;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", $row);
                if (count($row) != 4 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
                $warehouse = trim($row[0], '\'" ');
                $area = strtoupper(trim($row[1], '\'" '));
                $goods_name = trim($row[2], '\'" ');
                $channel_name = trim($row[3], '\'" ');
//                $sku = trim($row[4], '\'" ');
//                $quantity = trim($row[5], '\'" ');
              //  if($quantity<0) $str = substr($quantity,0,1);//符号，-
              //  $quantity = empty($quantity) ? 0 : $quantity;

                if($warehouses[$warehouse]==null)
                {
                    $infor['error'][] = sprintf('第%s行: 仓库:%s 不存在.', $count++, $warehouse);
                    continue;
                }
                foreach($titles as $v)
                {
                    if($v['id_warehouse']==$warehouses[$warehouse]&&$v['title']==$area){
                            $flag = true;
                            $find  = M('WarehouseGoodsAllocation')
                                     ->where(array('id_warehouse'=>$warehouses[$warehouse],'id_goods_area'=>$v['id_goods_area'],'goods_name'=>$goods_name))
                                     ->getField('id_warehouse_allocation');
                            $channel = M('WarehouseCargochannel')->where(array('id_warehouse'=>$warehouses[$warehouse],'id_warehouse_area'=>$v['id_goods_area'],'channel_name'=>$channel_name))
                                    ->find();
                            if ($find) {
                                if(!$channel){
                                    $cdata = array(
                                        'id_warehouse_area'=>$v['id_goods_area'],
                                        'id_warehouse'=>$v['id_warehouse'],
                                        'channel_name'=>strtoupper($channel_name),
                                        'created_at'=>date('Y-m-d H:i:s'),
                                        'id_warehouse_local'=>$find
                                    );
                                    M('WarehouseCargochannel')->add($cdata);
                                }
                               // if ($sku || $quantity) {
//                                    $result = $this->import_stock($v['id_warehouse'], $find, $sku, $quantity, $str);
//                                    if ($result['flag'] == true) {
//                                        $infor['success'][] = sprintf('第%s行: 仓库:%s  货位区域:%s   货位名称:%s  通道名称:%s  SKU:%s  原有库存:%s 现有库存:%s.', $count++, $warehouse, $area, $goods_name, $channel_name, $sku, $result['qty_befor'], $result['qty_after']);
//                                        break;
//                                    } else {
//                                        $infor['error'][] = sprintf('第%s行: 仓库:%s  货位区域:%s   货位名称:%s 通道名称:%s SKU:%s  %s.', $count++, $warehouse, $area, $goods_name, $channel_name, $sku, $result['message']);
//                                        break;
//                                    }
                              //  } else {
                                    if ($channel) {
                                        $infor['error'][] = sprintf('第%s行: 仓库:%s  货位区域:%s  货位名称:%s  通道名称:%s 已存在.', $count++, $warehouse, $area, $goods_name, $channel_name);
                                    } else {
                                        $infor['error'][] = sprintf('第%s行: 仓库:%s  货位区域:%s  已存在 货位名称:%s 通道名称:%s.', $count++, $warehouse, $area, $goods_name, $channel_name);
                                    }
                                    break;
                              //  }
                            }else{
                                $data = array(
                                    'id_goods_area'=>$v['id_goods_area'],
                                    'id_warehouse'=>$v['id_warehouse'],
                                    'goods_name'=>strtoupper($goods_name)
                                );
                                $add  = M('WarehouseGoodsAllocation')
                                      ->add($data);
                                if(!$channel) {
                                    $cdata = array(
                                        'id_warehouse_area' => $v['id_goods_area'],
                                        'id_warehouse' => $v['id_warehouse'],
                                        'channel_name' => strtoupper($channel_name),
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'id_warehouse_local' => $add
                                    );
                                    M('WarehouseCargochannel')->add($cdata);
                                }
                                if($add)
                                {
//                                    if($sku||$quantity){
//                                        $result = $this->import_stock($v['id_warehouse'],$add,$sku,$quantity,$str);
//                                        if($result['flag'] == true){
//                                            $infor['success'][] = sprintf('第%s行: 仓库:%s  货位区域:%s   货位名称:%s  通道名称:%s  SKU:%s  库存:%s  原有库存:%s 现有库存:%s.', $count++, $warehouse,$area,$goods_name,$channel_name,$sku,$result['qty_befor'],$result['qty_after']);
//                                            break;
//                                        }else{
//                                            $infor['error'][] = sprintf('第%s行: 仓库:%s  货位区域:%s   货位名称:%s  通道名称:%s SKU:%s   %s.', $count++, $warehouse,$area,$goods_name,$channel_name,$sku,$result['message']);
//                                            break;
//                                        }
//                                    }else{
                                        $infor['success'][] = sprintf('第%s行: 仓库:%s  货位区域:%s 货位名称:%s 通道名称:%s 导入成功.', $count++, $warehouse,$area,$goods_name,$channel_name);
                                        break;
                                   // }
                                }else
                                {
                                    $infor['error'][] = sprintf('第%s行: 仓库:%s  货位区域:%s  货位名称:%s 通道名称:%s 导入失败', $count++, $warehouse,$area,$goods_name,$channel_name);
                                    break;
                                }
                            }
                        }else
                        $flag = false;
                }
                if($flag == false){
                    $infor['error'][] = sprintf('第%s行: 仓库:%s  货位区域:%s  不存在', $count++, $warehouse,$area);
                }
            }

        }
        add_system_record($_SESSION['ADMIN_ID'], 5, 2, '导入货位',$path);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

    /*
     * 导入货位的时候顺便导入库存
     */
    public function import_stock($id_warehouse,$id_warehouse_allocation,$sku,$quantity = 0,$str){
        $result = M('ProductSku')->field('id_product_sku,id_product')->where(array('sku'=>$sku))->find();
        $info = array();
        if($result){
            $where['id_warehouse_allocation'] = $id_warehouse_allocation;
            $where['id_product_sku'] = $result['id_product_sku'];
            $find = M('WarehouseAllocationStock')->where($where)->find();
            if($find){
                if($quantity < 0) $quantity = substr($quantity,1);
//                $info['flag'] = false;
//                $info['message'] = '此货位已经存在此SKU库存';
                if($str == '-') {
                    $qty = $find['quantity']-$quantity;
                } else {
                    $qty = $find['quantity']+$quantity;
                }
                if($qty > 0 ) {
                    $res = D('Common/WarehouseAllocationStock')->where(array('id' => $find['id']))->save(array('quantity' => $qty));
                    if ($res) {
                        $find_store = M('WarehouseProduct')->where(array('id_warehouse' => $id_warehouse, 'id_product' => $result['id_product'], 'id_product_sku' => $result['id_product_sku']))->getField('quantity');
                        if ($str == '-') {
                            $ware_qty = $find_store - $quantity;
                        } else {
                            $ware_qty = $find_store + $quantity;
                        }
                        $add_data['quantity'] = $ware_qty;
                        if ($find_store === null) {
                            $add_data['id_warehouse'] = $id_warehouse;
                            $add_data['id_product'] = $result['id_product'];
                            $add_data['id_product_sku'] = $result['id_product_sku'];
                            $add_data['road_num'] = 0;
                            $store = M('WarehouseProduct')->add($add_data);
                        } else {
                            $store = M('WarehouseProduct')->where(array('id_warehouse' => $id_warehouse, 'id_product' => $result['id_product'], 'id_product_sku' => $result['id_product_sku']))->save(array('quantity' => $ware_qty));
                        }
                        if ($store) {
                            $this->updateQuantity($result['id_product_sku'], $ware_qty);
                            $info['flag'] = true;
                            $info['qty_befor'] = $find['quantity'];
                            $info['qty_after'] = $qty;
                        } else {
                            $info['flag'] = false;
                            $info['message'] = '更新库存失败';
                        }
                    } else {
                        $info['flag'] = false;
                        $info['message'] = '更新库存失败';
                    }
                    D('Common/WarehouseRecord')->write(
                        array(
                            'type' => 'MANUAL',
                            'num' => $quantity,
                            'num_before' => $find['quantity'],
                            'id_warehouse' => $id_warehouse,
                            'id_warehouse_allocation' => $id_warehouse_allocation,
                            'id_product_sku' => $result['id_product_sku'],
                        )
                    );
                } else {
                    $info['flag'] = false;
                    $info['message'] = '更新库存失败，库存不能为负数';
                }
            }else{
                if($quantity >= 0) {
                    $data['id_warehouse_allocation'] = $id_warehouse_allocation;
                    $data['id_product'] = $result['id_product'];
                    $data['id_product_sku'] = $result['id_product_sku'];
                    $data['updated_at'] = date('Y-m-d H:i:s');
                    $data['id_users'] = $_SESSION['ADMIN_ID'];
                    $data['quantity'] = $quantity;
                    $add = M('WarehouseAllocationStock')->add($data);
                    D('Common/WarehouseRecord')->write(
                        array(
                            'type' => 'MANUAL',
                            'num' => $quantity,
                            'num_before' => 0,
                            'id_warehouse' => $id_warehouse,
                            'id_warehouse_allocation' => $id_warehouse_allocation,
                            'id_product_sku' => $data['id_product_sku'],
                        )
                    );
                    if ($add) {
                        $add_data['quantity'] = $quantity;
                        $find_store = M('WarehouseProduct')->where(array('id_warehouse' => $id_warehouse, 'id_product' => $result['id_product'], 'id_product_sku' => $result['id_product_sku']))->getField('quantity');
                        if ($find_store === null) {
                            $add_data['id_warehouse'] = $id_warehouse;
                            $add_data['id_product'] = $result['id_product'];
                            $add_data['id_product_sku'] = $result['id_product_sku'];
                            $add_data['road_num'] = 0;
                            $store = M('WarehouseProduct')->add($add_data);
                        } else {
                            $store = M('WarehouseProduct')->where(array('id_warehouse' => $id_warehouse, 'id_product' => $result['id_product'], 'id_product_sku' => $result['id_product_sku']))->setField(array('quantity' => $add_data['quantity'] + $find_store));
                        }
                        if ($store) {
                            $this->updateQuantity($data['id_product_sku'], $quantity);
                            $info['flag'] = true;
                            $info['qty_befor'] = 0;
                            $info['qty_after'] = $quantity;
                        } else {
                            $info['flag'] = false;
                            $info['message'] = '更新库存失败';
                        }
                    } else {
                        $info['flag'] = false;
                        $info['message'] = '添加库存失败';
                    }
                } else {
                    $info['flag'] = false;
                    $info['message'] = '添加货位库存第一次不能为负数';
                }
            }
        }else{
            $info['flag'] = false;
            $info['message'] = '不存在此SKU';
        }
        return $info;
    }
    //仓库收货后对缺货产品进行未配货的状态更新，并减去仓库库存
    public function updateQuantity($id_product_sku,$quantity){
        $model = new \Think\Model;
        $order_table_name = D('Order/Order')->getTableName();
        $order_item_table_name = D('Order/OrderItem')->getTableName();
        $where = 'oi.id_product_sku ='.$id_product_sku.' and o.id_order_status=6';
        $order_data = $model->table($order_table_name . ' as o LEFT JOIN ' . $order_item_table_name . ' as oi ON o.id_order=oi.id_order')
            ->field('oi.id_order,o.id_zone,o.id_department,o.id_order_status,o.payment_method')
            ->where($where)
            ->order('oi.sorting desc,o.date_purchase asc')
            ->select();

        //仓库收货后对缺货产品进行未配货的状态更新，并减去仓库库存
        if($order_data && $quantity>0) {
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
                        //'user_id' => 1,
                        'comment' => '更改仓库库存对缺货状态进行更新,更新为：'.$quantity,
                    );
                    $order_record->addOrderHistory($parameter);
                }
            }
        }
        return true;
    }
}
