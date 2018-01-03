<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;
/**
 * 货币模块
 */
class CurrencyController extends AdminbaseController{

    public function index(){
        $currency_data = D('currency')->order("id_currency desc")->select();
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看货币列表');
        $this->assign("data",$currency_data)->display();
    }

    /*编辑货币*/
    public function edit(){
        $id = I("get.id",0,'intval');
        $result = D('currency')->where(array("id_currency" => $id))->find();
        $this->assign("data",$result)->display();
    }

    /*编辑*/
    public function edit_post(){
        if (IS_POST) {
            $data=I("post.");
            $id_currency=$data['id_currency'];
            $data['updated_at']=date("Y-m-d H:i:s");
            $res=D('currency')->where("id_currency=$id_currency")->save($data);
            if ($res!==false){
                add_system_record($_SESSION['ADMIN_ID'], 2, 3, '修改货币成功');
                $this->success("编辑成功！", U("currency/index"));
            } else {
                add_system_record($_SESSION['ADMIN_ID'], 2, 3, '修改货币失败');
                $this->error("编辑失败！");
            }
        }
    }

    /*删除货币*/
    public function delete(){
        $id = I("get.id",0,'intval');
        if (D('currency')->delete($id)!==false) {
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除货币成功');
            $this->success("删除成功！");
        } else {
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除货币成功');
            $this->error("删除失败！");
        }
    }

    /*添加货币*/
    public function add(){

        $this->display();
    }
    /*添加*/
    public function add_post(){
        if (IS_POST) {
            $data=I("post.");
            $data['created_at']=date("Y-m-d H:i:s");
            $data['updated_at']=date("Y-m-d H:i:s");
            if (D('currency')->create($data)!==false) {
                $result=D('currency')->add();
                if ($result!==false) {
                    add_system_record($_SESSION['ADMIN_ID'], 1, 3, '添加货币成功');
                    $this->success("添加成功！", U("currency/index"));
                } else {
                    add_system_record($_SESSION['ADMIN_ID'], 1, 3, '添加货币失败');
                    $this->error("添加失败！");
                }
            } else {
                $this->error(D('currency')->getError());
            }
        }

    }
}