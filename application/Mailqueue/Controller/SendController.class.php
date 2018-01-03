<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace Mailqueue\Controller;
use Common\Controller\HomebaseController; 
/**
 * 首页
 *
 */
class SendController extends HomebaseController {
	private $lock_send_new_order = 'send-mail-type1.lock';
    private $lock_send_delivery = 'send-mail-type2.lock';
    private $lock_send_test = 'send-mail-test.lock';
    //首页
	public function index() {
    	exit('thank you....bbbbb');
    }
    public function send_new_order()
    {
        //TODO: 过滤掉含test的邮箱
        if (file_exists(CACHE_PATH.$this->lock_send_new_order)) {
            exit('下单邮件队列正在运行');
        }
        try {
            
            $queue = D("Common/MailQueue");
            $naver = $queue->where(array(
                'err_count' => array('lt', 3),
                'status' => 1,
                'type' => 1,
                'to_addr' => array('notlike', array('%qq.com%', '%test%'), 'OR')
            ))->order(array(
                'err_count' => 'asc',
                'created_at' => 'asc',
                'updated_at' => 'asc',
            ))->limit(0, 100)->select();
            
            if (!$naver) {
                echo "nothing do;\n";
                if (file_exists(CACHE_PATH.$this->lock_send_new_order)) {
                    unlink(CACHE_PATH.$this->lock_send_new_order);
                }
                exit;
            }
            $this->send_mail($naver, $this->lock_send_new_order);
        } catch (\Exception $e) {
            exit($e->getMessage());
        } finally {
            if (file_exists(CACHE_PATH.$this->lock_send_new_order)) {
                unlink(CACHE_PATH.$this->lock_send_new_order);
            }
        }
        
        exit;
    }
    public function send_delivery()
    {
        if (file_exists(CACHE_PATH.$this->lock_send_delivery)) {
            exit('下单邮件队列正在运行');
        }
        try {
            
            $queue = D("Common/MailQueue");
            $naver = $queue->where(array(
                'err_count' => array('lt', 3),
                'status' => 1,
                'type' => 2
            ))->order(array(
                'err_count' => 'asc',
                'created_at' => 'asc',
                'updated_at' => 'asc',
            ))->limit(0, 10)->select();
            
            if (!$naver) {
                echo "nothing do;\n";
                if (file_exists(CACHE_PATH.$this->lock_send_delivery)) {
                    unlink(CACHE_PATH.$this->lock_send_delivery);
                }
                exit;
            }
            $this->send_mail($naver, $this->lock_send_delivery);
        } catch (\Exception $e) {
            exit($e->getMessage());
            
        } finally {
            if (file_exists(CACHE_PATH.$this->lock_send_delivery)) {
                unlink(CACHE_PATH.$this->lock_send_delivery);
            }
        }
        
        exit;
    }
    private function send_mail($array, $lock_file)
    {
        //TODO: 发邮件策略
        //定时执行一次发一条
        //定时执行一次发N条
        //目前执行一次发10条
        if (file_exists(CACHE_PATH.$lock_file)) {
            exit('下单邮件队列正在运行');
        }
        try {
            file_put_contents(CACHE_PATH.$lock_file, '');
            $queue = D("Common/MailQueue");
            $send_count = 0;
            $err_count = 0;
            $start_time = time();
            //发送下单邮件
            import("PHPMailer");

            foreach ($array as $item) {
                $data = array();
                ++$send_count;
                try {
                    $mail = new \PHPMailer();
                    // 设置PHPMailer使用SMTP服务器发送Email
                    $mail->isSMTP();
                    // 设置邮件的字符编码，若不指定，则为'UTF-8'
                    $mail->CharSet = 'utf-8';
                    $mail->Encoding = 'base64';
                    $mail->isHTML((bool)$item['is_html']);
                    // 添加收件人地址，可以多次使用来添加多个收件人
                    $mail->addAddress($item['to_addr']);
                    $mail->setFrom($item['from_addr'], $item['from_addr']);
                    // 设置邮件头的From字段。
                    //$mail->From = $item['from_addr'];
                    // 设置发件人名字
                    //$mail->FromName = $item['from_name'];
                    // 设置邮件标题
                    $mail->Subject = $item['subject'];
                    // 设置邮件正文
                    $mail->Body = $item['content'];
                    // 设置SMTP服务器。
                    $mail->Host = $item['smtp_host'];
                    $mail->SMTPSecure = (bool)$item['smtp_ssl'] ? 'ssl' : 'tls';
                    // 设置SMTP服务器端口。
                    $mail->Port = $item['smtp_port'];
                    // 设置为"需要验证"
                    $mail->SMTPAuth = true;
                    // 设置用户名和密码。
                    $mail->Username = $item['smtp_user'];
                    $mail->Password = $item['smtp_pwd'];
                    $mail->Timeout = 60;
                
                    $data['id_mail_queue'] = $item['id_mail_queue'];
                    $data['date_send'] = time();
                
                    // 发送邮件。
                    if (!$mail->send()) {
                    
                        $data['err_count'] = $item['err_count'] + 1;
                        $data['err_msg'] = $mail->ErrorInfo;
                        ++$err_count;
                    } else {
                        $data['err_count'] = 0;
                        $data['err_msg'] = '';
                        $data['status'] = 0; //发送完成
                    }
                } catch (\Exception $err) {
                
                } finally {
                    $queue->create($data);
                    $queue->save();
                }
            
            }
            echo sprintf("时间:%s 用时:%s秒 发送:%s 成功:%s 失败:%s\n", date('Y-m-d H:i:s', $start_time), time()-$start_time, $send_count, $send_count - $err_count, $err_count);
        } catch (\Exception $e) {
            exit($e->getMessage());
        
        } finally {
            if (file_exists(CACHE_PATH.$lock_file)) {
                unlink(CACHE_PATH.$lock_file);
            }
        }
    
        exit;
    }
    public function sendTest(){
        $queue = D("Common/MailQueue");
        $naver = $queue->where(array(
            'to_addr' =>'752979972@qq.com',
        ))->limit(0, 2)->select();

        $queue = D("Common/MailQueue");
        $send_count = 0;
        $err_count = 0;
        $start_time = time();
        //发送下单邮件
        import("PHPMailer");

        foreach ($naver as $item) {
            $data = array();
            ++$send_count;
            $mail = new \PHPMailer();
            //$mail->SMTPDebug = 2;
            // 设置PHPMailer使用SMTP服务器发送Email
            $mail->isSMTP();
            // 设置邮件的字符编码，若不指定，则为'UTF-8'
            $mail->CharSet = 'utf-8';
            $mail->Encoding = 'base64';
            $mail->isHTML((bool)$item['is_html']);
            // 添加收件人地址，可以多次使用来添加多个收件人
            $mail->addAddress($item['to_addr']);
            $mail->setFrom($item['from_addr'], $item['from_addr']);
            // 设置邮件头的From字段。
            //$mail->From = $item['from_addr'];
            // 设置发件人名字
            //$mail->FromName = $item['from_name'];
            // 设置邮件标题
            $mail->Subject = $item['subject'];
            // 设置邮件正文
            $mail->Body = $item['content'];
            // 设置SMTP服务器。
            $mail->Host = $item['smtp_host'];
            $mail->SMTPSecure = (bool)$item['smtp_ssl'] ? 'ssl' : 'tls';
            // 设置SMTP服务器端口。
            $mail->Port = $item['smtp_port'];
            // 设置为"需要验证"
            $mail->SMTPAuth = true;
            // 设置用户名和密码。
            $mail->Username = $item['smtp_user'];
            $mail->Password = $item['smtp_pwd'];

            $data['id_mail_queue'] = $item['id_mail_queue'];
            $data['date_send'] = time();

            // 发送邮件。
            if (!$mail->send()) {

                $data['err_count'] = $item['err_count'] + 1;
                $data['err_msg'] = $mail->ErrorInfo;
                ++$err_count;
                file_put_contents(SITE_PATH.'mail-send.txt', sprintf('TO:%s ERROR:%s'."\n", $item['to_addr'], $mail->ErrorInfo), FILE_APPEND);

            } else {
                $data['err_count'] = 0;
                $data['err_msg'] = '';
                $data['status'] = 0; //发送完成
            }
            $queue->data($data)->save();
            print_r($data);
        }
    }
    public function send_test()
    {

        try {
            $id = I('get.id');
            echo $id, "\n";
            $queue = D("Common/MailQueue");
            $naver = $queue->where(array(
                'id_mail_queue' => array('in', $id),
            ))->select();
            
            if (!$naver) {
                echo "nothing do;\n";
                exit;
            }
            //$this->send_mail($naver, $this->lock_send_new_order);
            $lock_file = $this->lock_send_test;
            //TODO: 发邮件策略
            //定时执行一次发一条
            //定时执行一次发N条
            //目前执行一次发10条
            if (file_exists(CACHE_PATH.$lock_file)) {
                exit('下单邮件队列正在运行');
            }
            try {
                file_put_contents(CACHE_PATH.$lock_file, '');
                $queue = D("Common/MailQueue");
                $send_count = 0;
                $err_count = 0;
                $start_time = time();
                //发送下单邮件
                import("PHPMailer");

                foreach ($naver as $item) {
                    $data = array();
                    ++$send_count;
                    try {
                        $mail = new \PHPMailer();
                        $mail->SMTPDebug = 2;
                        // 设置PHPMailer使用SMTP服务器发送Email
                        $mail->isSMTP();
                        // 设置邮件的字符编码，若不指定，则为'UTF-8'
                        $mail->CharSet = 'utf-8';
                        $mail->Encoding = 'base64';
                        $mail->isHTML((bool)$item['is_html']);
                        // 添加收件人地址，可以多次使用来添加多个收件人
                        $mail->addAddress($item['to_addr']);
                        $mail->setFrom($item['from_addr'], $item['from_addr']);
                        // 设置邮件头的From字段。
                        //$mail->From = $item['from_addr'];
                        // 设置发件人名字
                        //$mail->FromName = $item['from_name'];
                        // 设置邮件标题
                        $mail->Subject = $item['subject'];
                        // 设置邮件正文
                        $mail->Body = $item['content'];
                        // 设置SMTP服务器。
                        $mail->Host = $item['smtp_host'];
                        $mail->SMTPSecure = (bool)$item['smtp_ssl'] ? 'ssl' : 'tls';
                        // 设置SMTP服务器端口。
                        $mail->Port = $item['smtp_port'];
                        // 设置为"需要验证"
                        $mail->SMTPAuth = true;
                        // 设置用户名和密码。
                        $mail->Username = $item['smtp_user'];
                        $mail->Password = $item['smtp_pwd'];

                        $data['id_mail_queue'] = $item['id_mail_queue'];
                        $data['date_send'] = time();

                        // 发送邮件。
                        if (!$mail->send()) {

                            $data['err_count'] = $item['err_count'] + 1;
                            $data['err_msg'] = $mail->ErrorInfo;
                            ++$err_count;
                            file_put_contents(SITE_PATH.'mail-send.txt', sprintf('TO:%s ERROR:%s'."\n", $item['to_addr'], $mail->ErrorInfo), FILE_APPEND);

                        } else {
                            $data['err_count'] = 0;
                            $data['err_msg'] = '';
                            $data['status'] = 0; //发送完成
                        }

                    } catch (\Exception $err) {
                        print_r($err->getMessage());
                    } finally {
                        $queue->data($data)->save();
                    }

                }
                echo sprintf("时间:%s 用时:%s秒 发送:%s 成功:%s 失败:%s\n", date('Y-m-d H:i:s', $start_time), time()-$start_time, $send_count, $send_count - $err_count, $err_count);
            } catch (\Exception $e) {
                exit($e->getMessage());
        
            } finally {
                if (file_exists(CACHE_PATH.$lock_file)) {
                    unlink(CACHE_PATH.$lock_file);
                }
            }
            
            
        } catch (\Exception $e) {
            exit($e->getMessage());
            
        } finally {
        }
        
        exit;
    }
}


