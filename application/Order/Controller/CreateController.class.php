<?php
/**
 * 定时建立订单
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Product\Controller
 */
namespace Order\Controller;
use Common\Controller\HomebaseController;
use Think\Exception;
use Think\Log;

header("Content-type: text/html; charset=utf-8");
class CreateController extends HomebaseController{
    private $lock_write_data = 'lock_write_data.lock';
	public function _initialize() {
		parent::_initialize();
	}

    public function write_data(){
	    if (file_exists(CACHE_PATH.$this->lock_write_data)) {
	        echo '已经有进程正在运行';
	        exit;
        }
        try {

            //file_put_contents(CACHE_PATH.$this->lock_write_data, 'lock');
            $list = D("Common/TempOrderPost")
                ->where(array('status' => 1))
                ->order('created_at ASC')
                ->limit(0, 120)
                ->select();



            /* @var $sku_model \Product\Model\ProductSkuModel */
            $sku_model = D("Product/ProductSku");
            /** @var \Order\Model\ApiModel $api */
            $api = D("Order/Api");
            $str_token = array('key', 'web_url', 'tel', 'first_name', 'last_name', 'email', 'address',
                'remark', 'country', 'province', 'city', 'currency_code', 'payment_status', 'payment_details',
                'ip', 'user_agent');
            if ($list) {
                foreach ($list as $post_data) {
                    try {
                        echo sprintf('开始生成:%s%s', $post_data['id_increment'], PHP_EOL);
                        $data = json_decode($post_data['post_data'], true);
                        $id_department=$data['id_department']?$data['id_department']:0;
                        if($id_department){
                            $data['id_department_master']=M('department')->where(array('id_department'=>$id_department))->getField('id_users');
                        }
                        //检查是否已有生成过订单
                        $order = D('Order/Order')
                            ->field('email')
                            ->where(array(
                                'id_increment' => $post_data['id_increment']
                            ))
                            ->find();
                        if ($order && $order['email'] == $data['email']) {
                            //订单已生成
                          /*  echo sprintf('订单已生成:%s%s', $post_data['id_increment'], PHP_EOL);
                            D("Common/TempOrderPost")
                                ->where(array('id_increment' => $post_data['id_increment']))
                                ->save(array('status' => 0));
                            continue; */
                        }
                        //将输入转化为字符串
                        array_map(function($item) use (&$data){
                            if (is_object($data[$item]) || is_array($data[$item])) {
                                if (is_object($data[$item])) {
                                    $data[$item] = get_object_vars($data[$item]);
                                }
                                $data[$item] = implode('', $data[$item]);
                            } else {
                                $data[$item] = trim((string)$data[$item]);
                            }
                        }, $str_token);
                        $getSkuModel = array();
                        $data['grand_total'] = 0;
                        $totalQty = 0;
                        $temp_pro_title = array();
                        $tempProId      = array();
                        foreach ($data['products'] as $pro_key => $product) {
                            $product = $api->filter_post_html($product);
                            $product = $this->cleanup($product);
                            $product['attrs'] = $api->filter_post_html($product['attrs']);
                            $product['attrs'] = array_map(function($item){
                                return (int)$item;
                            }, $product['attrs']);
                            $data['products'][$pro_key] = $product;

                            $product_id = $product['id_product'];
                            $tempProId[] = $product_id;
                            $temp_pro_title[] = $product['sale_title'];
                            $sku_result = $sku_model->get_sku_id($product_id, $product['attrs']);
                            $getSkuModel[$product_id] = $sku_result['id'];
                            $data['products'][$pro_key]['id_product_sku'] = $sku_result['id'];
                            $data['products'][$pro_key]['sku'] = $sku_result['sku'];
                            $data['products'][$pro_key]['sku_title'] = $sku_result['title'];
                            $totalQty += $product['qty'];
                            $data['price_total'] += $product['price'];
                        }
                        $data['total_qty_ordered'] = isset($data['qty'])?$data['qty']:$totalQty;

                        $repeatWhere['tel'] = array('EQ', $data['tel']);
                        if (trim($data['first_name'])) {
                            $repeatWhere['first_name'] = array('EQ', trim($data['first_name']));
                        }
                        if (trim($data['last_name'])) {
                            $repeatWhere['last_name'] = array('EQ', trim($data['last_name']));
                        }
                        $repeatWhere['_logic'] = 'or';
                        $countOrder = D("Order/Order")->field('id_order')->where($repeatWhere)->getField('id_order', true);
//                        $ipCountOrder = M('OrderInfo')->where(array('ip'=>$data['ip']))->select();
                        //ip 和域名
                        $orderinfoTab=M('OrderInfo')->getTableName();
                        $ipCountOrder=M('order o')->join("{$orderinfoTab} oi on oi.id_order=o.id_order")
                                ->where(array('oi.ip'=>$data['ip'],'o.id_domain'=>$data['id_domain']))->count();
                        if ($countOrder||$ipCountOrder) {
                            $countItem = 0;
                            if($countOrder) {
                                $isAttr = false;
                                foreach ($tempProId as $proId) {
                                    $isAttr = true;
                                    $getModel = $getSkuModel[$proId];
                                    $where = array('id_order' => array('IN', $countOrder), 'id_product' => $proId);
                                    if ($getModel) {
                                        $where['id_product_sku'] = $getModel;
                                    }
                                    $countItem = $countItem + D("Order/OrderItem")->where($where)->count();
                                }
                            }
//                            $ip_count = count($ipCountOrder)>=3?count($ipCountOrder):0;
                            $data['order_repeat'] = count($getSkuModel) ? $countItem+$ipCountOrder : count($countOrder)+$ipCountOrder;
                            $data['order_count'] = count($countOrder) + 1;
                        } else {
                            $data['order_repeat'] = 0;
                            $data['order_count'] = 1;
                        }

                        //自动审核测试订单,直接归类到测试订单 jiangqinqing 20171102
                        $data['id_order_status'] = $this->updateOrderStatus($data);

                        //检测黑名单
                        if ($this->blacklistdelete($data) ){
                            throw new Exception("this orer in blacklist");continue;
                        }


                        echo sprintf('订单的地址:%s%s', $data['address'], PHP_EOL);
                        $order_id = D("Order/Order")->data($data)->add();
                        if ($order_id) {
                            $insert_info = array('id_order' => $order_id, 'ip' => $data['ip'], 'user_agent' => $data['user_agent']);
                            D("Order/OrderInfo")->data($insert_info)->add();

                            $order_data = $order_id ? D("Order/Order")->find($order_id) : '';
                            /** @var \Order\Model\OrderRecordModel $order_record */
                            $order_record = D("Order/OrderRecord");
                            $order_record->addHistory($order_id, 1, '未处理订单');
                            $status = true;
                            $message = '成功提交订单';
                            try{
                                // 出错后被TP halt掉
                                $api->create_item($order_id, $data);//建立产品信息
                                if ($order_data && is_array($order_data)) {
                                    $order_data['web_url'] = $data['web_url'];
                                    $order_data['product_name'] = $temp_pro_title ? implode("\r\n", $temp_pro_title) : '';
                                    $order_data['products'] = $data['products'];
                                    if (!$data['payment_method'] && in_array($order_data['id_zone'],array(2,3))) {
                                        $api->create_email($order_data);
                                    }
                                }
                                //D("Common/TempOrderPost")->where(array('id_increment'=>$post_data['id_increment']))->delete();
                                D("Common/TempOrderPost")->where(array('id_increment' => $post_data['id_increment']))->save(array('status' => 0));
                                echo $post_data['id_increment'] . ' OK' . PHP_EOL;
                            } catch (\Exception $ex) {
                                echo sprintf('生成产品出错:%s,%s', $post_data['id_increment'], $ex);
                                add_system_record(1, 4, 4, '插入订单产品失败' . $ex->getMessage());
                            }

                        }
                    } catch (\Exception $e) {
                        D("Common/TempOrderPost")
                            ->where(array('id_increment' => $post_data['id_increment']))
                            ->save(array('status' => 0));
                        $error_data = array(
                            'id_department'=>$post_data['id_department'],
                            'id_increment'=>$post_data['id_increment'],
                            'post_data' => json_encode($data),
                            'error_message'=> $e->getMessage(),
                            'created_at' => date('Y-m-d H:i:s')
                        );
                        D("Common/TempOrderPostError")->data($error_data)->add();
                        //echo sprintf('生成出错:%s,%s', $post_data['id_increment'], $e);
                        //add_system_record(1, 4, 4, '插入订单失败' . $e->getMessage() . json_encode($data));
                    }
                }
            }
            echo '完成';
        }
        catch (Exception $e) {
	        echo $e->getMessage();
        }
        finally {
            if (file_exists(CACHE_PATH.$this->lock_write_data)) {
                unlink(CACHE_PATH.$this->lock_write_data);
            }
        }
    }

    /**
     * 清除数据问题
     * @param $product
     */
    private function cleanup($product) {
        if (isset($product['parent_product_id'])) {
            $product['parent_product_id'] = (int)$product['parent_product_id'];
        }
        if (isset($product['product_id'])) {
            $product['product_id'] = (int)$product['product_id'];
        }
        if (isset($product['id_product'])) {
            $product['id_product'] = (int)$product['id_product'];
        }
        if (isset($product['qty'])) {
            $product['qty'] = (int)$product['qty'];
        }
        if (isset($product['price'])) {
            $product['price'] = (float)$product['price'];
        }
        if (isset($product['title'])) {
            $product['title'] = trim($product['title']);
        }
        if (isset($product['product_title'])) {
            $product['product_title'] = trim($product['product_title']);
        }
        if (isset($product['price_title'])) {
            $product['price_title'] = trim($product['price_title']);
        }
        if (isset($product['prefix'])) {
            $product['prefix'] = trim($product['prefix']);
        }
        if (isset($product['subfix'])) {
            $product['subfix'] = trim($product['subfix']);
        }
        return $product;
    }
    //匹配订单地址
    public function match_address() {
        $where['address']=['LIKE','%å°%'];
        $where['created_at']=['GT','2017-08-22 00:00:00'];
        $order = M('Order')->field("address,id_increment")->where($where)->select();
        var_dump($order);
        foreach($order as $o){
            $owhere['id_increment']=$o['id_increment'];
            $list = D("Common/TempOrderPost")
                ->where($owhere)
                ->find();
            $data = json_decode($list['post_data'], true);
            $save['address']=$data['address'];
            $save['id_order_status']=1;
            $owhere['address']=['LIKE','%å°%'];
            $owhere['created_at']=['GT','2017-08-22 00:00:00'];
            $aaa=D("Order/Order")->where($owhere)->save($save);
            var_dump($aaa);
        }
        exit;
    }

    //匹配测试订单,自动把订单状态定义成 测试订单
    function updateOrderStatus($data){
        //匹配测试订单 ,zhi
        if(strstr($data['first_name'],'测试') || strstr($data['first_name'],'測試') ){
            return 28;
        }

        return 1;
    }


    /**
     * @param $data
     * 黑名单表中的数据 不进入erp系统
     * @return bool
     */
    public function blacklistdelete ($data)
    {
        $blacklist = M('Blacklist')->select();
        $true_arr = [];
        foreach ($blacklist as $v) {
            if ($data['tel'] == $v['field']  ||
                $data['first_name'].$data['last_name'] == $v['field'] ||
                $data['email'] == $v['field'] ||
                $data['address'] == $v['field'] ||
                $data['ip'] == $v['field']
            ) {
                $true_arr[] = 1; // 找到
            } else {
                $true_arr[] = 2 ; //找不到
            }
        }
        if (in_array(1,$true_arr)) {
            return true;
        }
        return false;
    }

    //把hk服务器 TempOrderPostHk表推送到  sz_erp  TempOrderPost表
    public function getOrderHKtoSZ () {
        $order_SZ = M('TempOrderPost','erp_','mysql://star:STARZGZhMzM3NDc0NzM2@39.108.186.132/new_erp#utf8');
        $list = M('TempOrderPostHk')->where(array('status' => 1))
            ->order('created_at ASC')
            ->limit(0, 120)
            ->select();
        try {
            M()->startTrans();
            foreach ($list as $v) {
                $insert_id = $order_SZ->add([
                    'id_increment' => $v['id_increment'],
                    'id_department' => $v['id_department'],
                    'post_data' => $v['post_data'],
                    'data_key' => $v['data_key'],
                    'status' => $v['status'],
                    'created_at' => $v['created_at'],
                ]);
                Log::write($order_SZ->_sql() . '.insert_id:'.$insert_id);
                if ($insert_id ) {
                    $update_res = M('TempOrderPostHk')->where(array('id_increment' => $v['id_increment']))
                        ->save(array('status' => 0));
                    if ($update_res === false) {
                        M()->rollback();
                    } else {
                        echo "\r\n";
                        echo $v['id_increment'];
                        M()->commit();
                    }
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}