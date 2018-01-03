<?php
namespace Domain\Controller;

use Common\Controller\HomebaseController;

class ApiController extends HomebaseController {
    public function index(){
        echo __METHOD__;
    }
    
    public function get_all() {
        try{
            $damain_name = $_GET['name'];
            $data = array();
            if($damain_name) {
                $result = D('Domain/Domain')->where(array('name'=>$damain_name))->find();
                if($result) {
                    $data['domain'] = $result;
                    $data['zone'] = M('Zone')->select();
                    $data['currency'] = M('Currency')->select();
                    $department_users = M('DepartmentUsers')->where(array('id_department'=>$result['id_department']))->select();
                    foreach ($department_users as $k=>$v) {
                        $data['users'][$v['id_users']] = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
                    }
                    $status =true;$message= '';
                } else {
                    $status =false;$message= '该域名不存在';
                }                
            } else {
                $status =false;$message= '该域名不正确';
            }                      
        }catch (\Exception $e){
            $status= false;$message= $e->getMessage();
        }

        echo json_encode(array('status'=>$status,'message'=>$message,'data'=>$data));
        exit();
    }

    /**
     * 获取域名接口
     */
    public function get_doamin() {
        try{
            $damain_name = $_GET['name'];
            if($damain_name) {
                $result = D('Domain/Domain')->where(array('name'=>$damain_name))->find();
                if($result) {
                    $depart_name = M('Department')->where(array('id_department'=>$result['id_department']))->getField('title');
                    $result['depart_name'] = $depart_name ? $depart_name : '';
                    $status =true;
                    $message = '';
                    $data = $result;
                }else {
                    $status =false;
                    $message= '该域名不存在';
                }
            } else {
                $status =false;
                $message= '参数错误';
            }
        }catch (\Exception $e){
            $data='';
            $status= false;
            $message= $e->getMessage();
        }
        echo json_encode(array('status'=>$status,'message'=>$message,'data'=>$data));
        exit();
    }

    /**
     * 获取部门
     */
    public function get_department(){
        $department  = D('Department/Department')
            ->field('id_department,title')
            ->where('type=1')->cache(true,3600)
            ->select();
        echo json_encode($department);exit();
    }
    /**
     * API 添加域名，后台需要传签名才能添加域名。
     */
    public function add(){
        $data = '';$message = '';
        try{
            $id_department = (int)sp_strip_chars($_POST['id_department']);
            $department = D('Department/Department')->find($id_department);
            if($department){
                $name = trim(htmlspecialchars(strip_tags($_POST['name'])));
                $select = D('Domain/Domain')->where(array('name'=>$name))->find();
                if($select){
//                    $status =false;$message= '域名已经存在。';
                    $save_data = array(
                        'id_department' => $id_department,
                        'name'          => $name,
                        'real_address'  => htmlspecialchars(strip_tags($_POST['real_address'])),
                        'ip'            => htmlspecialchars(strip_tags($_POST['ip'])),
                        'smtp_host'     => htmlspecialchars(strip_tags($_POST['smtp_host'])),
                        'smtp_user'     => htmlspecialchars(strip_tags($_POST['smtp_user'])),
                        'smtp_pwd'      => htmlspecialchars(strip_tags($_POST['smtp_pwd'])),
                        'smtp_port'     => htmlspecialchars(strip_tags($_POST['smtp_port'])),
                        'smtp_ssl'      => htmlspecialchars(strip_tags($_POST['smtp_ssl'])),
                        'copy_url'      => htmlspecialchars(strip_tags($_POST['copy_url'])),
                        'updated_at'    => date('Y-m-d H:i:s'),
                    );
                    $data = D('Domain/Domain')->where(array('id_domain'=>$select['id_domain']))->save($save_data);
                    $status = $data?1:0;
                }else{
                    $add_data = array(
                        'id_department' => $id_department,
                        'name'          => $name,
                        'real_address'  => htmlspecialchars(strip_tags($_POST['real_address'])),
                        'ip'            => htmlspecialchars(strip_tags($_POST['ip'])),
                        'smtp_host'     => htmlspecialchars(strip_tags($_POST['smtp_host'])),
                        'smtp_user'     => htmlspecialchars(strip_tags($_POST['smtp_user'])),
                        'smtp_pwd'      => htmlspecialchars(strip_tags($_POST['smtp_pwd'])),
                        'smtp_port'     => htmlspecialchars(strip_tags($_POST['smtp_port'])),
                        'smtp_ssl'      => htmlspecialchars(strip_tags($_POST['smtp_ssl'])),
                        'copy_url'      => htmlspecialchars(strip_tags($_POST['copy_url'])),
                        'status'        => 1,
                        'created_at'    => date('Y-m-d H:i:s'),
                        'updated_at'    => date('Y-m-d H:i:s'),
                    );
                    $data = D('Domain/Domain')->data($add_data)->add();
                    $status = $data?1:0;
                }
            }else{
                $status =false;$message= '部门不存在。';
            }
        }catch ( \Exception $e){
            $status =false;$message= $e->getMessage();
        }
        echo json_encode(array('status'=>$status,'message'=>$message,'data'=>$data));
        exit();
    }
    public function post_data(){
        if (function_exists('curl_init')) {
            $post_data = array(
                'id_department' => 1,//部门ID
                'name'          => 'www.abcde.com',//域名
                'real_address'  => 'http://www.abcde.com',//真实投放地址
                'ip'            => '142.252.38.220',//IP
                'smtp_host'     => 'cvefs.com',//邮箱主机
                'smtp_user'     => 'fashion@wyyua.com',//邮箱用户名
                'smtp_pwd'      => 'Bslf007',//邮箱密码
                'smtp_port'     => '25',//端口
                'smtp_ssl'      => 0,//是否加密
                'copy_url'        => 'http://www.taobao.com/',
            );
            $send_url = 'http://www.newerp.com/Domain/Api/add/';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $send_url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            $response = curl_exec($ch);print_r($response);
            if (!curl_errno($ch)) {
                //$curl_info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $order_info = json_decode($response,true);
                //$order_id = $order_info['order_id'];
            } else {
                $curl_info = curl_error($ch);
            }

        }else{
            echo '不支持CURL';
        }
    }
    public function get_order_count() {
        try{
            $damain_name ="www.".$_GET['name'];
            //$damain_name="www.iztoz.com";
            $data = array();
            if($damain_name) {
                $result = D('Domain/Domain')->where(array('name'=>$damain_name))->getField('id_domain');
                if($result) {
                    //SELECT COUNT(id_order) as ll FROM `erp_order` WHERE `id_domain` = 13018 AND `created_at` >= '2017-05-07 00:00:00' ORDER BY `id_order` DESC
                    $time_3month_before = date('Y-m-d', strtotime('-3 month'));//15天改成7天
                    $data['three_month_order']=M('Order')->where(array('id_domain'=>$result,'created_at'=>array('EGT', $time_3month_before)))->count();
                    $data['all_order']=M('Order')->where(array('id_domain'=>$result))->count();
                    //
                    $status =true;$message= '';
                } else {
                    $status =false;$message= '该域名不存在';
                }
            } else {
                $status =false;$message= '该域名不正确';
            }
        }catch (\Exception $e){
            $status= false;$message= $e->getMessage();
        }

        echo json_encode(array('status'=>$status,'message'=>$message,'data'=>$data));
        exit();
    }

    function get_table_info(){
        $table_name = trim($_GET['table_name']);
        $sql=    "SELECT * FROM ".$table_name;
        var_dump(M()->table($table_name)->find());
    }
}