<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;
/**
 *消息通知
 */
class MessageController extends AdminbaseController{

    /*接收的消息*/
    public function index(){
        $model=D();
        $user_id=$_SESSION['ADMIN_ID'];
        $sql="select a.*,b.*,d.user_nicename from erp_message_users AS a LEFT JOIN erp_message AS b ON a.id_message=b.id_message
              LEFT JOIN erp_users AS d ON b.id_users=d.id WHERE a.id_users=$user_id ORDER BY a.id_message_users DESC ";
        $message_list=$model->query($sql);
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看接收的消息');
        $this->assign("data",$message_list)->display();
    }

    /*我发送的消息*/
    public function mysend(){
        $model=D();
        $my_id=$_SESSION['ADMIN_ID'];
        $sql="select a.*,b.*,b.id_users AS id_receive, d.user_nicename from erp_message AS a LEFT JOIN erp_message_users AS b ON a.id_message=b.id_message
              LEFT JOIN erp_users AS d ON b.id_users=d.id WHERE a.id_users=$my_id ORDER BY a.id_message DESC ";
        $message_list=$model->query($sql);
        $message_seeion=D("message")->where("id_users=$my_id")->select();
//        var_dump($message_seeion);die;
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看我发送的消息');
        $this->assign("data",$message_list)->assign("data1",$message_seeion)->display();
    }

    /*发送消息动作*/
    public function send(){
        $data_user=D("users")->field("id,user_nicename")->select();
        $data_part=D("department")->field("id_department,title")->select();
        $this->assign("data_user",$data_user)->assign("data_part",$data_part)->display();
    }
    /*发送*/
    public function send_post(){
        $my_id=$_SESSION['ADMIN_ID'];
        if (IS_POST) {
            $data_message=I("post.");
            $data_message['id_users']=$my_id;
            $data_message['created_at']=date("Y-m-d H:i:s");
            if (D('message')->create($data_message)!==false) {
                //添加消息
                $result=D('message')->add();
                if ($result!==false) {
                    if($data_message["send_users"][0]!=""){
                        //发给多个人
                        $id_users=$data_message['send_users'];//所有人
                        for($i=0;$i<count($id_users);$i++){
                            $user_message['id_message']=$result;
                            $user_message['id_users']=$id_users[$i]; //添加多个收信人
                            $result1[$i]=D('message_users')->add($user_message);
                        }
                        if(count($id_users)==count($result1) and count($id_users)!= 0){
                            add_system_record($_SESSION['ADMIN_ID'], 1, 3, '发送个人消息成功');
                            $this->success("消息发送成功！", U("message/send"));
                        }else{
                            add_system_record($_SESSION['ADMIN_ID'], 1, 3, '发送个人消息失败');
                            $this->error("发送失败！");
                        }
                    }
                    if($data_message["send_parts"][0]!=""){
                        //发给多部门
                        $id_departments=$data_message['send_parts'];//所有部门
                        for($i=0;$i<count($id_departments);$i++){   //某部门中的人员
                            $data=D("department_users")->field("id_department,id_users")->where("id_department=$id_departments[$i]")->select();
                            for($j=0;$j<count($data);$j++){
                                $p_user_message['id_message']=$result;
                                $p_user_message['id_users']=$data[$j]["id_users"]; //添加多个收信人
                                D('message_users')->add($p_user_message);
                            }
                            $result3[]=$i;
                        }
                        if(count($id_departments)==count($result3)){
                            add_system_record($_SESSION['ADMIN_ID'], 1, 3, '发送部门消息成功');
                            $this->success("消息发送成功！", U("message/send"));
                        }else{
                            add_system_record($_SESSION['ADMIN_ID'], 1, 3, '发送部门消息失败');
                            $this->error("发送失败！");
                        }
                    }
                    if($data_message["send_users"][0]=='' && $data_message["send_parts"][0]==''){
                        $users = M('Users')->where(array('user_status'=>1))->select();
                        foreach($users as $user) {
                            $all_user_message['id_message']=$result;
                            $all_user_message['id_users']=$user['id'];
                            D('Common/MessageUsers')->add($all_user_message);
                        }
                        add_system_record($_SESSION['ADMIN_ID'], 1, 3, '发送全公司消息成功');
                        $this->success("消息发送成功！", U("message/send"));
                    }
                } else {
                    add_system_record($_SESSION['ADMIN_ID'], 1, 3, '发送消息失败');
                    $this->error("发送失败！");
                }
            } else {
                $this->error(D('message')->getError());
            }
        }

    }



}