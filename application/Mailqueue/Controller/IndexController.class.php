<?php

/* * 
 * ERP邮件队列管理
 */

namespace Mailqueue\Controller;

use Common\Controller\AdminbaseController;

class IndexController extends AdminbaseController {

    private $lock_send_new_order = 'send-mail-type1.lock';
    private $lock_send_delivery = 'send-mail-type2.lock';
    /** @var  \Common\Model\RoleModel $role_model */
    protected $role_model;

    /** @var  \Think\Model */
    protected $auth_access_model;

    /** @var  \Common\Model\DomainModel */
    protected $queue;
    private $types = array(
        1 => '下单邮件',
        2 => '发货通知邮件'
    );

    function _initialize() {
        parent::_initialize();
        $this->role_model = D("Common/Role");
        $this->queue = D("Common/MailQueue");
    }

    /**
     * 有add添加，edit编辑，delete删除
     */
    public function index() {
        $where = array();
        if (IS_POST) {
            if (I('post.name')) {
                $where['name'] = array('LIKE', '%' . I('post.name') . '%');
            }
            if (I('post.from_addr')) {
                $where['from_addr'] = array('LIKE', '%' . I('post.from_addr') . '%');
            }
            if (I('post.subject')) {
                $where['subject'] = array('LIKE', '%' . I('post.subject') . '%');
            }
        }
        $count = $this->queue->where($where)->count();
        $page = $this->page($count, 20);

        $data = $this->queue->where($where)->order(array("id_mail_queue" => "desc"))
                ->limit($page->firstRow, $page->listRows)
                ->select();

        foreach ($data as $key => $v) {
            $data[$key]['type'] = $this->get_type($v['type']);
        }
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看邮件列表');
        $this->assign("queues", $data);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->display();
    }

    public function sendtest() {
        //TODO: 测试邮件发送
        exit('fff thank you');
    }

    /**
     * 添加
     */
    public function add() {
        $this->display();
    }

    /**
     * 添加
     */
    public function add_post() {
        if (IS_POST) {
            $data = I('post.');
            $data['created_at'] = date('Y-m-d H:i:s');
            if ($this->queue->create($data)) {
                if ($this->queue->add($data) !== false) {
                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加邮箱成功');
                    $this->success("添加邮件成功", U("Index/index"));
                } else {
                    add_system_record(sp_get_current_admin_id(), 1, 3, '添加邮箱失败');
                    $this->error("添加失败！");
                }
            } else {
                $this->error($this->queue->getError());
            }
        }
    }

    /**
     * 删除
     */
    public function delete() {
        $id = intval(I("get.id"));

        $status = $this->queue->delete($id);
        if ($status !== false) {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除邮箱成功');
            $this->success("删除成功！", U('Index/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 3, 3, '删除邮箱失败');
            $this->error("删除失败！");
        }
    }

    /**
     * 编辑
     */
    public function edit() {
        $id = intval(I("get.id"));
        if ($id == 0) {
            $id = intval(I("post.id"));
        }
        $data = $this->queue->where(array("id_mail_queue" => $id))->find();
        if (!$data) {
            $this->error("该邮件不存在！");
        }
        $this->assign("data", $data);
        $this->display();
    }

    /**
     * 编辑
     */
    public function edit_post() {
        $id = intval(I("get.id_mail_queue"));
        if ($id == 0) {
            $id = intval(I("post.id_mail_queue"));
        }

        if (IS_POST) {
            $data = I('post.');
            $data['updated_at'] = date('Y-m-d H:i:s');
            if ($this->queue->create($data)) {
                if ($this->queue->save($data) !== false) {
                    add_system_record(sp_get_current_admin_id(), 2, 3, '修改邮箱成功');
                    $this->success("修改成功！", U('Index/index'));
                } else {
                    add_system_record(sp_get_current_admin_id(), 2, 3, '修改邮箱失败');
                    $this->error("修改失败！");
                }
            } else {
                $this->error($this->queue->getError());
            }
        }
    }

    /*
     * 手动发送邮件
     */

    public function manual_send() {
        //TODO: 过滤掉含test的邮箱
        if (file_exists(CACHE_PATH . $this->lock_send_new_order)) {
            exit('下单邮件队列正在运行');
        }
        
        $id = intval(I("get.id"));
        if ($id == 0) {
            $id = intval(I("post.id"));
        }

        try {

            $queue = D("Common/MailQueue");
            $naver = $queue->where(array(
                        'err_count' => array('lt', 3),
                        'id_mail_queue' => $id                
                    ))->order(array(
                        'err_count' => 'asc',
                        'created_at' => 'asc',
                        'date_send' => 'asc',
                    ))->limit(0, 1)->select();

            if (!$naver) {
//                echo "nothing do;\n";
                if (file_exists(CACHE_PATH . $this->lock_send_new_order)) {
                    unlink(CACHE_PATH . $this->lock_send_new_order);
                }
                $this->error('该条数据不存在',U("Index/index"));
//                exit;
            }
            $this->send_mail($naver, $this->lock_send_new_order);
        } catch (\Exception $e) {
            exit($e->getMessage());
        } finally {
            if (file_exists(CACHE_PATH . $this->lock_send_new_order)) {
                unlink(CACHE_PATH . $this->lock_send_new_order);
            }
        }

        exit;
    }
    
    /*
     * 发送邮箱方法
     */
    private function send_mail($array, $lock_file)
    {
        //TODO: 发邮件策略
        //定时执行一次发一条
        //定时执行一次发N条
        //目前执行一次发1条
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
            add_system_record(sp_get_current_admin_id(), 6, 3, '手动发送邮箱');            
            $msg =  sprintf("时间:%s 用时:%s秒 发送:%s 成功:%s 失败:%s\n", date('Y-m-d H:i:s', $start_time), time()-$start_time, $send_count, $send_count - $err_count, $err_count);
            $this->success($msg,U("Index/index"));
        } catch (\Exception $e) {
            exit($e->getMessage());
        
        } finally {
            if (file_exists(CACHE_PATH.$lock_file)) {
                unlink(CACHE_PATH.$lock_file);
            }
        }
    
        exit;
    }

    /**
     * 角色授权
     */
    public function authorize() {
        $this->auth_access_model = D("Common/AuthAccess");
        //角色ID
        $roleid = intval(I("get.id"));
        if (!$roleid) {
            $this->error("参数错误！");
        }
        import("Tree");
        $menu = new \Tree();
        $menu->icon = array('│ ', '├─ ', '└─ ');
        $menu->nbsp = '&nbsp;&nbsp;&nbsp;';
        $result = $this->initMenu();
        $newmenus = array();
        $priv_data = $this->auth_access_model->where(array("role_id" => $roleid))->getField("rule_name", true); //获取权限表数据
        foreach ($result as $m) {
            $newmenus[$m['id']] = $m;
        }

        foreach ($result as $n => $t) {
            $result[$n]['checked'] = ($this->_is_checked($t, $roleid, $priv_data)) ? ' checked' : '';
            $result[$n]['level'] = $this->_get_level($t['id'], $newmenus);
            $result[$n]['parentid_node'] = ($t['parentid']) ? ' class="child-of-node-' . $t['parentid'] . '"' : '';
        }
        $str = "<tr id='node-\$id' \$parentid_node>
                       <td style='padding-left:30px;'>\$spacer<input type='checkbox' name='menuid[]' value='\$id' level='\$level' \$checked onclick='checknode(this);'> \$name</td>
	    			</tr>";
        $menu->init($result);
        $categorys = $menu->get_tree(0, $str);

        $this->assign("categorys", $categorys);
        $this->assign("roleid", $roleid);
        $this->display();
    }

    /**
     * 角色授权
     */
    public function authorize_post() {
        $this->auth_access_model = D("Common/AuthAccess");
        if (IS_POST) {
            $roleid = intval(I("post.roleid"));
            if (!$roleid) {
                $this->error("需要授权的角色不存在！");
            }
            if (is_array($_POST['menuid']) && count($_POST['menuid']) > 0) {

                $menu_model = M("Menu");
                $auth_rule_model = M("AuthRule");
                $this->auth_access_model->where(array("role_id" => $roleid, 'type' => 'admin_url'))->delete();
                foreach ($_POST['menuid'] as $menuid) {
                    $menu = $menu_model->where(array("id" => $menuid))->field("app,model,action")->find();
                    if ($menu) {
                        $app = $menu['app'];
                        $model = $menu['model'];
                        $action = $menu['action'];
                        $name = strtolower("$app/$model/$action");
                        $this->auth_access_model->add(array("role_id" => $roleid, "rule_name" => $name, 'type' => 'admin_url'));
                    }
                }

                $this->success("授权成功！", U("Rbac/index"));
            } else {
                //当没有数据时，清除当前角色授权
                $this->auth_access_model->where(array("role_id" => $roleid))->delete();
                $this->error("没有接收到数据，执行清除授权成功！");
            }
        }
    }

    /**
     *  检查指定菜单是否有权限
     * @param array $menu menu表中数组
     * @param int $roleid 需要检查的角色ID
     */
    private function _is_checked($menu, $roleid, $priv_data) {

        $app = $menu['app'];
        $model = $menu['model'];
        $action = $menu['action'];
        $name = strtolower("$app/$model/$action");
        if ($priv_data) {
            if (in_array($name, $priv_data)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取菜单深度
     * @param $id
     * @param $array
     * @param $i
     */
    protected function _get_level($id, $array = array(), $i = 0) {

        if ($array[$id]['parentid'] == 0 || empty($array[$array[$id]['parentid']]) || $array[$id]['parentid'] == $id) {
            return $i;
        } else {
            $i++;
            return $this->_get_level($array[$id]['parentid'], $array, $i);
        }
    }

    public function member() {
        //TODO 添加角色成员管理
    }

    private function get_type($type) {
        $str = '下单邮件';
        if (isset($this->types[$type]))
            $str = $this->types[$type];
        return $str;
    }

}
