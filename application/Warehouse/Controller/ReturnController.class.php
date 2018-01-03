<?php
/**
 * 仓库模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Warehouse\Controller
 */
namespace Warehouse\Controller;
use Common\Controller\AdminbaseController;

class ReturnController extends AdminbaseController {
    protected $Warehouse, $orderModel;
    public function _initialize() {
        parent::_initialize();
        $this->Warehouse = D("Common/Warehouse");
        $this->orderModel = D("Order/Order");
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row']?$_SESSION['set_page_row']:20;
        $this->purchase_status=array("0"=>'待入库','7'=>'部分入库','12'=>'已入库','11'=>'部分上架','8'=>'已完成');
    }
    //新采购收货列表
    public function purchase_list2() {

        if (!empty($_GET['department_id'])) {
            $where['id_department'] = array('EQ', $_GET['department_id']);
        }
        if (!empty($_GET['warehouse_id'])) {
            $where['id_warehouse'] = array('EQ', $_GET['warehouse_id']);
        }
        $where['status'] = array('IN','0,7,12,8,11');
        if ($_GET['status']!=666 && isset($_GET['status'])) {
            $where['status'] = array('EQ', $_GET['status']);
        }
        if(!empty($_GET['track_number'])) {
            $where['return_no'] = array('LIKE', '%' . $_GET['track_number'] . '%');
        }
        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['created_at'] = $created_at_array;
        }

        $department = M('Department')->where('type=1')->select();
        $department = array_column($department, 'title', 'id_department');
        $warehouse = M('Warehouse')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $model = new \Think\Model;

        $count =D('WarehouseReturn')->where($where)->count();
        $page = $this->page($count, 20);
        $pro_list = D('WarehouseReturn')->field('*')->where($where)->order("status ASC")->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign("getData", $_GET);
        $this->assign('department', $department);
        $this->assign('warehouse', $warehouse);
        $this->assign("pro_list", $pro_list);
        $this->assign("purchase_status", $this->purchase_status);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }
    /**
     * 查看采购单
     */
    public function look() {
        $id = $_GET['id'];
        $purchase = D('WarehouseReturn')->find($id);
        $purchase_list = M('WarehouseReturnProduct')->alias('pp')->field('pp.*,pu.sku')->join('__PRODUCT_SKU__ as pu on pu.id_product_sku = pp.id_product_sku')->where(array('id_return' => array('EQ', $id),'type'=>0))->select();
//        var_dump($purchase_list);
        foreach ($purchase_list as $key => $v) {
            $where['id_product'] = $v['id_product'];
            $where['id_product_sku'] = $v['id_product_sku'];
            $load_product = D("Common/Product")->field('thumbs,title,inner_name,id_department')->where(array('id_product' => $v['id_product']))->find();
            $warehouse_pro = M('WarehouseProduct')->field('quantity,road_num')->where(array('id_product' => $v['id_product'], 'id_product_sku' => $v['id_product_sku'], 'id_warehouse' => $purchase['id_warehouse']))->find();

            //属性
            $pro_option = M('ProductSku')->field('title')->where($where)->find();
            $purchase_list[$key]['option'] = $pro_option['title'];
            $purchase_list[$key]['title'] = $load_product['inner_name'];
            $purchase_list[$key]['dep_title']= M('Department')->where(['id_department'=>$load_product['id_department']])->getField('title');
            $purchase_list[$key]['img'] = json_decode($load_product['thumbs'], true);
            $purchase_list[$key]['qty'] = $warehouse_pro['quantity'];
        }
//        var_dump($id);var_dump($purchase_list);die;
//        $department = M('Department')->where(array('id_department' => $purchase['id_department']))->getField('title');
//        $warehouse = M('Warehouse')->where(array('id_warehouse' => $purchase['id_warehouse']))->getField('title');
        $this->assign('purchase_list', $purchase_list);
        $this->assign('data', $purchase);
        $this->display();
    }
    /**
     * 仓库收货2，入库
     */
    public function signed2() {
        $id = I('get.id');
        $purchase = D("Common/WarehouseReturn")->find($id);
        $product_html = '<tr><th>运单号</th><th>产品图片</th><th>SKU</th><th>产品名</th><th>部门</th>
        <th>退货数量</th><th>已收货的数量</th><th>收货数量</th></tr>';
        if(isset($_GET['track_number'])&&!empty($_GET['track_number'])){
            $product_html .= $this->get_product_html2($id,$_GET['track_number']);
        }else{
            $product_html .= $this->get_product_html2($id);
        }

        $this->assign('attr_row', $product_html);
        $this->assign('data', $purchase);
        $this->assign('getData', $_GET);

        $this->display();
    }
    protected function get_product_html2($id,$track_number=null) {
        if(!empty($track_number)){
            $pp_where['pp.track_number']=$track_number;
        }
       $pp_where['pp.id_return']=array('EQ', $id);
        $pp_where['type']=0;

        $purchase_product = M('WarehouseReturnProduct')->alias('pp')
            ->field('pp.*,pu.sku')->join('__PRODUCT_SKU__ as pu on pu.id_product_sku = pp.id_product_sku', 'LEFT')
            ->where($pp_where)->order("id_return DESC")->select();
        $product_row = '';
        foreach ($purchase_product as $key => $v) {
            $load_product = D("Common/Product")->field('thumbs,title,id_department')->where(array('id_product' => $v['id_product']))->find();
            $purchase_product[$key]['title'] = $load_product['title'];
            $purchase_product[$key]['dep_title']= M('Department')->where(['id_department'=>$load_product['id_department']])->getField('title');

            $purchase_product[$key]['img'] = json_decode($load_product['thumbs'], true);
            $photo = !empty($purchase_product[$key]['img']['photo']) ? $purchase_product[$key]['img']['photo'][0]['url'] : '';
            if(empty($v['received'])){
                $v['received']=0;
            }

            $product_row .= '<input type="hidden" name="data[' . $key . '][id_return_product]" value="' . $v['id_return_product'] . '">
            <input type="hidden" name="data[' . $key . '][id_product]" value="' . $v['id_product'] . '">
            <input type="hidden" name="data[' . $key . '][id_product_sku]" value="' . $v['id_product_sku'] . '">
            <input type="hidden" name="data[' . $key . '][id_return]" value="' . $v['id_return'] . '">
            <tr class="tr">
            <td>' . $v['track_number'] . '</td>
            <td><img  src="' . sp_get_image_preview_url($photo) . '" style="height:36px;width: 36px;"></td>
            <td>' . $v['sku'] . '</td>
            <td>' . $purchase_product[$key]['title'] . '</td>
            <td>' . $purchase_product[$key]['dep_title'] . '</td>
            <td class="purchase">' . $v['quantity'] . '</td><td class="received">' . $v['received'] . '</td>
            <td class="add"><input type="text" name="data[' . $key . '][quantity]"></td></tr>';
        }
        return $product_row;
    }

    /**
     * 添加产品 入库  库存新
     */
    public function save_stock2() {
        $id_return = $_POST['id_return'];
        $data = $_POST['data'];
        $sum_received = 0;
        switch ($_POST['method']) {
            case 'wait':
                foreach ($data as $v) {
                    $old_received = M('WarehouseReturnProduct')->field('received')->find($v['id_return_product']);
                    $save['received'] = $v['quantity'] + $old_received['received'];
                    $save['id_return_product'] = $v['id_return_product'];
                    M('WarehouseReturnProduct')->save($save);
                    $sum_received += $v['quantity'];
                }
                $update['id_return'] = $id_return;
                $old_total = D('WarehouseReturn')->where($update)->getField('total_received');
                $update['status'] = 7;
                $update['total_received'] = $old_total + $sum_received;
                $res = D('WarehouseReturn')->save($update);
                if ($res === false) {
                    $this->error("保存失败！", U('return/purchase_list2'));
                } else {
                    $this->success("保存完成！", U('return/purchase_list2'));
                }
                break;
            case 'finish':
               // $track_numberArray=array();
                foreach ($data as $k=>$v) {
                    $old_received = M('WarehouseReturnProduct')->field('received,track_number,id_return')->find($v['id_return_product']);
                    $save['received'] = $v['quantity'] + $old_received['received'];
                    $save['id_return_product'] = $v['id_return_product'];
                    M('WarehouseReturnProduct')->save($save);
                    $sum_received += $v['quantity'];
//                    $track_number= $old_received['track_number'];
//                    if(!in_array($track_number,$track_numberArray)){
//                        array_push($track_numberArray,$track_number);
//                    }

                }


                $update['id_return'] = $id_return;
                $update2['id_return']=$id_return;
                $update2['type']=0;
                $returnData=M('WarehouseReturnProduct')->field('*,sum(quantity) as newquantity,sum(received) as newreceived')->where($update2)->group('id_product_sku')->select();
                if(!empty($returnData)){
                    foreach($returnData as $k =>$v){
                        $Prodate['id_return']=$v['id_return'];
                        $Prodate['track_number']=$v['track_number'];
                        $Prodate['id_product']=$v['id_product'];
                        $Prodate['id_product_sku']=$v['id_product_sku'];
                        $Prodate['quantity']=$v['newquantity'];
                        $Prodate['received']=$v['newreceived'];
                        $Prodate['option_value']=$v['option_value'];
                        $Prodate['type']=1;
                        $id=M('WarehouseReturnProduct')->add($Prodate);
                    }
                }
                $old_total = D('WarehouseReturn')->where($update)->getField('total_received');
                $update['status'] = 12;
                $update['total_received'] = $old_total + $sum_received;
                $res = D('WarehouseReturn')->save($update);
                if ($res === false) {
                    $this->error("保存失败！", U('return/purchase_list2'));
                } else {
                    $this->success("保存完成！", U('return/purchase_list2'));
                }
                break;
        }
    }
}
