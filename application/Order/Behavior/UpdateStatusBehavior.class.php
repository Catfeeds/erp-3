<?php
/**
 * Created by Juns <46231996@qq.com>.
 * User: jun
 * Date: 2016/10/9 20:06
 * Description:
 */

namespace Order\Behavior;
use Think\Behavior;
use Think\Hook;

class UpdateStatusBehavior extends Behavior
{
    /**
     * 状态更新.在这里更新订单状态的新值. 并做相应的处理
     * @param mixed $params<p>
     * array(target_id => 10101100, new_value => 2)
     * </p>
     */
    public function run(&$params)
    {
    }

    /**
     * 编辑订单 发送通知
     * @param $params
     */
    public function editOrder(&$params){
        /* @var $order \Common\Model\OrderModel*/
        $order  = D("Common/Order");
        $orderObj = $order->find($params['id']);
        if(in_array($orderObj['status_id'],array(18,19))){
            $title = '[紧急]配货中的订单被修改了';
            $content = '
            订单号:'.$params['id'].'被修改了修息,请及时跟进
            ';
            $userId  = 1;//通知的人
            $mobile = '13888888888';
            $message = array(
                'user_id'=>$userId,
                'title'=>$title,
                'content'=>$content,
                'level'=>2,
            );

            $sms = array(
                'name'=>$orderObj['web_url'],
                'mobile'=>$mobile,
                'content'=>$content,
                'type'=>1,
            );
            /* @var $user \Common\Model\UsersModel*/
            $user  = D("Common/Users");
            $sendUser = $user->getRoleUser(9);
            if($sendUser){
                foreach($sendUser as $u){
                    $message['user_id'] = $u['id']?$u['id']:$userId;
                    $sms['mobile'] = $u['user_tel']?$u['user_tel']:$mobile;
                    create_message($message);
                    create_sms($sms);
                }
            }
        }

    }

    /**
     * 取消订单，发送通知
     * @param $params
     */
    public function cancel(&$params){print_r($params);exit();
        /* @var $order \Common\Model\OrderModel*/
        $order  = D("Common/Order");
        $orderObj = $order->find($params['id']);
        if(in_array($orderObj['status_id'],array(18,19))){
            $title = '[紧急]配货中的订单被修改了';
            $content = '
            订单号:'.$params['id'].'被取消了,请及时跟进
            ';
            $userId  = 1;//通知的人
            $mobile = '13888888888';
            $message = array(
                'user_id'=>$userId,
                'title'=>$title,
                'content'=>$content,
                'level'=>2,
            );
            $sms = array(
                'name'=>$orderObj['web_url'],
                'mobile'=>$mobile,
                'content'=>$content,
                'type'=>1,
            );
            /* @var $user \Common\Model\UsersModel*/
            $user  = D("Common/Users");
            $sendUser = $user->getRoleUser(9);
            if($sendUser){
                foreach($sendUser as $u){
                    $message['user_id'] = $u['id']?$u['id']:$userId;
                    $sms['mobile'] = $u['user_tel']?$u['user_tel']:$mobile;
                    create_message($message);
                    create_sms($sms);
                }
            }
        }
        $result = D("Common/Order")->where('id=' . $params['id'])->save(array('status_id'=>$params['status_id']));
        D("Common/OrderStatusHistory")->addHistory($params['id'], $params['status_id'], $params['comment']);
        return true;
    }

    /**
     * 审核通过订单  发送通知
     * @param $params
     */
    public function approved(&$params){
        /* @var $order \Common\Model\OrderModel*/
        $order  = D("Common/Order");
        $orderObj = $order->find($params['id']);
        $result = $this->lessInventory($params['id'],$orderObj);//订单审核通过后 订单减库存
        $params['status_id'] = $result?2:2;// 库存不够把订单改为缺货订单 2:19
        $order->where('id='.$params['id'])->save($params);
        /* @var $orderHistory \Common\Model\OrderModel*/
        $orderHistory = D("Common/OrderStatusHistory");
        $orderHistory->addHistory($params['id'],$params['status_id'],$params['comment']);
    }

    /**
     * 减库存
     * @param $orderId
     * @return bool
     */
    public function lessInventory($orderId,$orderObj = false){
        $flag = true;
        add_system_record(sp_get_current_admin_id(), 4, 4, '调用了老的减库存函数');
        return false;
        try {
            $orderObj = $orderObj?$orderObj:D("Common/Order")->find($orderId);
            $orderId = (int)$orderId;
            $oItemObj = D("Common/OrderItem")->where('order_id=' . $orderId)->select();
            $tempProSku  = array();
            if ($oItemObj) {
                foreach ($oItemObj as $item) {
                    $qty = $item['qty'];
                    $childSkuId = $item['sku_id'];
                    $productId = $item['product_id'];
                    $product = D('Common/Product')->find($productId);
                    $proQty = $product['qty'] - $qty;
                    D('Common/Product')->where('id=' . $productId)->save(array('qty' => $proQty));
                    //$skuWhere = array('product_id'=>$productId,'sku_id'=>$childSkuId);//->where($skuWhere)
                    $productSku = D("Common/ProductSku")->find($childSkuId);
                    if ($productSku['id']&& $productSku['qty']>0) {
                        $setQty = $productSku['qty'] - $qty;
                        D("Common/ProductSku")->where('id=' . $productSku['id'])->save(array('qty' => $setQty));
                    }else{
                        $tempProSku[] = $product['inner_name'].'('.$productSku['model'].')';
                        $flag = false;
                    }
                }
                if($tempProSku){
                    $title = '订单：'.$orderId.' 产品缺货通知';
                    $content = implode(',',$tempProSku).' 缺货';
                    $userId  = 1;//通知的人
                    $mobile = '13788888888';
                    $message = array(
                        'user_id'=>$userId,
                        'title'=>$title,
                        'content'=>$content,
                        'level'=>1,
                    );
                    $sms = array(
                        'name'=>$orderObj['web_url'],
                        'mobile'=>$mobile,
                        'content'=>$content,
                        'type'=>1,
                    );
                    /* @var $user \Common\Model\UsersModel*/
                    $user  = D("Common/Users");
                    $sendUser = $user->getRoleUser(4);
                    if($sendUser){
                        foreach($sendUser as $u){
                            $mobile = trim($u['user_tel'])?trim($u['user_tel']):$mobile;
                            $message['user_id'] = $u['id']?$u['id']:$userId;
                            $sms['mobile'] = $mobile;
                            create_message($message);
                            create_sms($sms);
                        }
                    }
                    //create_sms($sms);
                }
            }
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
        return $flag;
    }
}