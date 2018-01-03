<?php
/*
 *      _______ _     _       _     _____ __  __ ______
 *     |__   __| |   (_)     | |   / ____|  \/  |  ____|
 *        | |  | |__  _ _ __ | | _| |    | \  / | |__
 *        | |  | '_ \| | '_ \| |/ / |    | |\/| |  __|
 *        | |  | | | | | | | |   <| |____| |  | | |
 *        |_|  |_| |_|_|_| |_|_|\_\\_____|_|  |_|_|
 */
/*
 *     _________  ___  ___  ___  ________   ___  __    ________  _____ ______   ________
 *    |\___   ___\\  \|\  \|\  \|\   ___  \|\  \|\  \ |\   ____\|\   _ \  _   \|\  _____\
 *    \|___ \  \_\ \  \\\  \ \  \ \  \\ \  \ \  \/  /|\ \  \___|\ \  \\\__\ \  \ \  \__/
 *         \ \  \ \ \   __  \ \  \ \  \\ \  \ \   ___  \ \  \    \ \  \\|__| \  \ \   __\
 *          \ \  \ \ \  \ \  \ \  \ \  \\ \  \ \  \\ \  \ \  \____\ \  \    \ \  \ \  \_|
 *           \ \__\ \ \__\ \__\ \__\ \__\\ \__\ \__\\ \__\ \_______\ \__\    \ \__\ \__\
 *            \|__|  \|__|\|__|\|__|\|__| \|__|\|__| \|__|\|_______|\|__|     \|__|\|__|
 */
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace Portal\Controller;
use Common\Controller\HomebaseController;
use Common\Lib\Currency;

/**
 * 首页
 */
class TestController extends HomebaseController {
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
    public function compare_db(){
        //$result1 = M('new_erp','erp_','mysql://root:@localhost:3306/new_erp')->query("SELECT count(*) as count FROM `erp_order`");
        //$result1 = M('new_erp','erp_','mysql://mbl_erponnew:jfsf29300fOJ99x@localhost:3306/new_erp')->query("SELECT count(*) as count FROM `erp_order`");
		$result1 = M('new_erp','erp_','mysql://erpdb:MmQ1MDcxMjkyMDNl@localhost:3306/new_erp')->query("SELECT count(*) as count FROM `erp_order`");//root:YjZmNzYzYWQ1ODk2
        $get_count1 = isset($result1[0])?$result1[0]['count']:0;print_r($result1);
        //$result2 = M('new_erp','erp_','mysql://root:@localhost:3306/test')->query("SELECT count(*) as count FROM `erp_order`");
        //$result2 = M('new_erp','erp_','mysql://user_mblsto:j1f29FK10fOJ9xZ1@166.88.95.42:3306/new_erp')->query("SELECT count(*) as count FROM `erp_order`");
		$result2 = M('new_erp','erp_','mysql://user_mblsto:j1f29FK10fOJ9xZ1@localhost:3306/new_erp')->query("SELECT count(*) as count FROM `erp_order`");
        $get_count2 = isset($result2[0])?$result2[0]['count']:0;

        if($get_count1!=$get_count2){
            $send_content = array(
                'to_addr' => '752979972@qq.com',
                'subject' => '两个数据库数据不同步',
                'content' => '主数据库订单数：'.$get_count1.'. 从数据订单数:'.$get_count2,
                'addCC'   => 'sales@awjvv.com',
            );
            //$send_result = $this->send_mail($send_content);
            print_r($send_content);
        }else{
            echo '两台数据库相同<br />';
            echo $get_count1.'=='.$get_count2;
        }
    }
	public function db_test(){
        $result1 = M('new_erp','erp_','mysql://mbl_erponnew:jfsf29300fOJ99x@localhost:3306/new_erp')->query("SELECT count(*) FROM `erp_order`");
        echo '数据库1最新订单：';
        print_r($result1);
        $result2 = M('new_erp','erp_','mysql://user_mblsto:j1f29FK10fOJ9xZ1@166.88.95.42:3306/new_erp')->query("SELECT count(*) FROM `erp_order`");
        echo '<br />数据库2最新订单：';
        print_r($result2);
        try{
            $con = mysql_connect("166.88.95.42","user_mblsto","j1f29FK10fOJ9xZ1");
            if(!$con){
                echo '<br >42数据库连接不上<br >';
            }
            mysql_select_db("new_erp", $con);
            $result = mysql_query("SELECT count(*) FROM `erp_order`");
            while($row = mysql_fetch_array($result)){
                print_r($row);
            }
            mysql_close($con);
        }catch (\Exception $e){
            echo '<br />';
            echo '<br /><b style="color:red;">';
            print_r($e->getMessage());
            echo '</b>';
        }

        exit();
    }
	public function index() {
	    header('HTTP/1.0 404 Not Found.');
	    exit('<h2>Not Found</h2>');
    }
    public function test_currency()
    {
        echo \Common\Lib\Currency::format(12);
    }
    public function order()
    {
	    
    }

    public function  get_csv_data($csvFile=false){
        $returnArray  = array();
        if(file_exists($csvFile)){
            $csvFile  = fopen($csvFile,'r');
            $i=0;$tempArray=array();
            while ($data = fgetcsv($csvFile)) {
                if($i==0){
                    $tempArray = $data;
                }else{
                    $itemArray = array();
                    if(is_array($data)){
                        foreach($data as $key=>$item){
                            $getKey = trim($tempArray[$key]);
                            $itemArray[$getKey]=$item;
                        }
                    }
                    $returnArray[] = $itemArray;
                }
                $i++;
            }
            fclose($csvFile);
        }
        return $returnArray;
    }
    public function order_restore(){
        $file = './2017-01-18_restore.csv';
        $data = $this->get_csv_data($file);
        if($data){
            $get_all_status = D("Common/OrderStatus")->select();
            $all_status     = array_column($get_all_status,'id_order_status','title');
            $order_model    = D("Common/Order");
            $temp_order_id  = array();
            foreach($data as $item){
                $status = $item['订单状态'];
                $id_increment = $item['订单号'];
                $order = $order_model->where(array('id_increment'=>$id_increment))->find();
                $order_shipping = D("Common/OrderShipping")->where(array('id_order'=>$order['id_order']))->find();
                switch($status){
                    case '配送中':
                    case '待处理':
                    case '未配货':
                        $settlement = D("Common/OrderSettlement")->where(array('id_order'=>$order['id_order']))->find();
                        if($settlement){
                            //更新已经签收的订单
                            //$update = array('id_order_status'=>9);
                            //$order_model->where(array('id_order'=>$order['id_order']))->save($update);
                            //echo $id_increment.'==='.$order['id_order'].'已经签收<br />';
                        }else{
                            if($order['id_order_status']==5){
                                $update = array('id_order_status'=>$all_status[$status]);
                                $order_model->where(array('id_order'=>$order['id_order']))->save($update);
                            }else{
                                $temp_order_id[] = $order['id_order'];
                            }

                        }
                        break;
                    default:
                    if($order_shipping){
                        if($order['id_order_status']==5){
                            $update = array('id_order_status'=>$all_status[$status]);
                            $order_model->where(array('id_order'=>$order['id_order']))->save($update);
                        }else{
                            $temp_order_id[] = $order['id_order'];
                        }
                        //echo $id_increment.'==='.$order['id_order'].'<b style="color:red;">无效单 已经发货</b><br />';
                    }else{
                        //echo $id_increment.'==='.$order['id_order'].'未发货<br />';
                        //$update = array('id_order_status'=>$all_status[$status]);
                        //$order_model->where(array('id_increment'=>$id_increment))->save($update);
                    }
                }

            }
            echo $temp_order_id?implode(',',$temp_order_id):'';
        }
        echo '<br />完成';
    }
}


