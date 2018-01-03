<?php
namespace Portal\Controller;
use Common\Controller\HomebaseController;
use Common\Lib\Currency;
class WarehouseController extends HomebaseController {
    /**
     * 更新 缺货的订单有库存可以扣就变成未配货
     */
//    public function update_out_stock(){
//        header("Content-type: text/html; charset=utf-8");
//        set_time_limit(0);
//        $where = isset($_GET['id'])?array('id_increment'=>I('get.id')):array('id_order_status'=>6);
//        $where['id_order_status'] = 6;
//        $count = D('Order/Order')->where($where)->count();
//        $page_size = isset($_GET['num'])?(int)$_GET['num']:500;
//        $page = $this->page($count, $page_size);
//        $order = D('Order/Order')->where($where)->order('updated_at desc')->limit($page->firstRow , $page->listRows)->select();
//        if($order){
//            /** @var \Order\Model\OrderRecordModel  $order_record */
//            $order_record = D("Order/OrderRecord");
//            foreach($order as $item){
//                $results = \Order\Model\UpdateStatusModel::lessInventory($item['id_order'],$item);
//                $update_order = array();
//                $update_order['updated_at']      = date('Y-m-d H:i:s');
//                if($results['status']) {
//                    $update_order = array();
//                    $update_order['id_order_status'] = 4;
//                    $update_order['id_warehouse']    = isset($results['id_warehouse'])?end($results['id_warehouse']):1;
//                    D('Order/Order')->where('id_order='.$item['id_order'])->save($update_order);
//                    $parameter  = array(
//                        'id_order' => $item['id_order'],
//                        'id_order_status' => 4,//库存满足，4订单一定是未配货状态
//                        'type' => 1,
//                        'user_id' => 1,
//                        'comment' => '定时缺货的订单,有库存自动就变成未配货',
//                    );
//                    $order_record->addOrderHistory($parameter);
//                    //D('Order/OrderRecord')->addHistory($val['id_order'],4,1,'更改仓库库存对缺货状态进行更新,更新为：'.$data['quantity']);
//                    echo $item['id_increment'].'未配货<br>';
//                }else{
//                    D('Order/Order')->where('id_order='.$item['id_order'])->save($update_order);
//                    echo $item['id_increment'].' 此单无库存，不能更新为未配货<br>';
//                }
//            }
//        }
//        echo '执行完成';
//    }
}


