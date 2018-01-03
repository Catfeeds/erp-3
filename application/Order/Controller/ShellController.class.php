<?php
/**
 * 订单 定时处理
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Product\Controller
 */
namespace Order\Controller;
use Common\Controller\HomebaseController;

class ShellController extends HomebaseController{
	protected $product,$api_url,$status_data;
    protected $domain_advert;
    protected $shipping;
    protected $local_shipping;
    protected $max_order_id = 0;
	public function _initialize() {
		parent::_initialize();
	}

    /**
     * 发送邮件
     * @param $data
     * @return string
     */
    public function send_mail($data){
        try{
            $config = array(
                'is_html'   => true,
                'from_addr' => 'service@ibcep.com',
                'smtp_host' => 'zrbbn.com',
                'smtp_port' => '25',
                'smtp_user' => 'service@ibcep.com',
                'smtp_pwd'  => 'Bslf007',
                'smtp_ssl'  => false,
            );
            $mail = new \PHPMailer();
            // 设置PHPMailer使用SMTP服务器发送Email
            $mail->isSMTP();
            // 设置邮件的字符编码，若不指定，则为'UTF-8'
            $mail->CharSet   = 'utf-8';
            $mail->Encoding  = 'base64';
            $mail->isHTML((bool)$config['is_html']);
            // 添加收件人地址，可以多次使用来添加多个收件人
            $mail->addAddress($data['to_addr']);
            if(isset($data['addCC']) && $data['addCC']){
                $mail->addCC($data['addCC'],$data['addCC']);
            }
            $mail->setFrom($config['from_addr'], $config['from_addr']);
            // 设置邮件头的From字段。
            //$mail->From     = $config['from_addr'];
            // 设置发件人名字
            //$mail->FromName = $config['from_name'];
            // 设置邮件标题
            $mail->Subject    = $data['subject'];
            // 设置邮件正文
            $mail->Body       = $data['content'];
            // 设置SMTP服务器。
            $mail->Host       = $config['smtp_host'];
            $mail->SMTPSecure = (bool)$config['smtp_ssl'] ? 'ssl' : 'tls';
            // 设置SMTP服务器端口。
            $mail->Port       = $config['smtp_port'];
            // 设置为"需要验证"
            $mail->SMTPAuth   = true;
            // 设置用户名和密码。
            $mail->Username   = $config['smtp_user'];
            $mail->Password   = $config['smtp_pwd'];
            $mail->Timeout    = 60;
            if (!$mail->send()) {
                $message = $mail->ErrorInfo;
            } else {
                $message = '发送成功';
            }
        }catch (\Exception $e){
            $message = $e->getMessage();
        }
        return $message;
    }
    /**
     * 缺货订单 有库存通知
     */
    public function out_stock_notice(){
        $where['o.created_at'] = array('GT',date('Y-m-d 00:00:00',strtotime('-2 day')));
        $where['o.id_order_status'] = array('EQ',6);
        $select_field = 'o.id_order,o.id_zone,o.id_increment,o.id_department,p.inner_name,p.title as product_title,oi.product_title as sale_title,oi.id_product_sku,oi.id_product,oi.sku,oi.sku_title,oi.quantity as buy_qty';
        $order_list = D("Order/Order")->alias('o')
            ->field($select_field)
            ->join('__ORDER_ITEM__ oi on o.id_order = oi.id_order','LEFT')
            ->join('__PRODUCT__ p on oi.id_product = p.id_product','LEFT')
            ->where($where)//->fetchSql(true)
            ->order('oi.id_order')->select();
        $Warehouse      = D('Common/Warehouse')->where(array('status'=>1))->order('id_warehouse desc')->select();
        $ware_product   = D('Common/WarehouseProduct');
        $mail_table     = '<table><tr><td>Id</td><td>订单号</td><td>SKU ID</td><td>产品标题</td><td>购买件数</td><td>仓库库存ID</td><td>仓库库存</td></tr>';
        $mail_table_tr  =  array();
        $status         = false;
        $temp_order     = array();
        foreach($order_list as $order) {
            $id_product = $order['id_product'];
            $id_product_sku = $order['id_product_sku'];
            foreach ($Warehouse as $ware_key => $ware) {
                if($ware['id_zone']==$order['id_zone'] or $ware['id_zone']==1){
                    $id_order = $order['id_order'];
                    $ware_where = array(
                        'id_warehouse' => $ware['id_warehouse'],
                        'id_product' => $id_product,
                        'id_product_sku' => $id_product_sku,
                    );
                    $select_ware_product = $ware_product->where($ware_where)->find();
                    if($select_ware_product && $select_ware_product['quantity']>= $order['buy_qty']){
                        $status       = true;
                        $href         = 'http://'.$_SERVER['SERVER_NAME'].U('/Order/Index/info',array('id'=>$id_order));
                        $quantity     = $select_ware_product['quantity'];
                        $id_warehouse = $ware['id_warehouse'];

                        $mail_table_tr[$id_order]  = '<tr><td><a href="'.$href.'">'.$id_order.'</a></td><td>'.
                            $order['id_increment'].'</td><td>'.$order['id_product_sku'].'</td><td>'.$order['inner_name'].'</td><td>'.
                            $order['buy_qty'].'</td><td>'.$id_warehouse.'</td><td>'.$quantity.'</td></tr>';
                        if(isset($temp_order[$id_order])&& !isset($_GET['status'])){
                            unset($mail_table_tr[$id_order]);
                        }
                    }else{
                        $temp_order[$id_order] = $id_order;
                        if(isset($mail_table_tr[$id_order])){
                            unset($mail_table_tr[$id_order]);
                        }
                    }
                }
            }
        }
        $mail_table_tr = $mail_table_tr?implode('',$mail_table_tr):'';
        $mail_table .= $mail_table_tr.'</table>';
        if($status){
            $send_content = array(
                'to_addr' => 'chengguang.tang@stosz.com',
                'subject' => '有库存但缺货的订单',
                'content' => $mail_table,
                'addCC'   => 'sales@awjvv.com',
            );
            $send_result = $this->send_mail($send_content);

            $send_content = array(
                'to_addr' => 'pengxu@stosz.com',
                'subject' => '有库存但缺货的订单',
                'content' => $mail_table,
                'addCC'   => 'sales@awjvv.com',
            );
            $send_result = $this->send_mail($send_content);
            echo '邮件：'.$send_result.'<br />';
        }
        echo $mail_table.'<br />执行完成';

    }
}