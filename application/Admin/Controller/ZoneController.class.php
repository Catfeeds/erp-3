<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;
/**
 * 国家、地区模块
 */
class ZoneController extends AdminbaseController{

    public function index(){
        $zone = M();
        $sql="select a.*,a.title AS title2,b.* from erp_zone AS a LEFT JOIN  erp_country AS b ON a.id_country = b.id_country ORDER BY id_zone DESC  ";
        $data_zone = $zone->query($sql);
        add_system_record($_SESSION['ADMIN_ID'], 4, 3, '查看地区列表');
        $this->assign("data",$data_zone)->display();
    }

    /*编辑地区*/
    public function edit(){
        $id = I("get.id",0,'intval');
        $result = D('zone')->where(array("id_zone" => $id))->find();
        $country = D("country")->field("id_country,title")->select();
        $this->assign("country",$country)->assign("data",$result)->display();
    }

    /*编辑*/
    public function edit_post(){
        if (IS_POST) {
            $data=I("post.");
            $id_zone=$data['id_zone'];
            $res=D('zone')->where("id_zone=$id_zone")->save($data);
            if ($res!==false){
                add_system_record($_SESSION['ADMIN_ID'], 2, 3, '修改地区成功');
                F('get_web_all_zone',null);
                $this->success("编辑成功！", U("zone/index"));
                } else {
                    add_system_record($_SESSION['ADMIN_ID'], 2, 3, '修改地区失败');
                    $this->error("编辑失败！");
                }
            }
    }

    /*删除地区*/
    public function delete(){
        $id = I("get.id",0,'intval');
        if (D('zone')->delete($id)!==false) {
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除地区成功');
            F('get_web_all_zone',null);
            $this->success("删除成功！");
        } else {
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除地区失败');
            $this->error("删除失败！");
        }
    }

    /*添加地区*/
    public function add(){
        $country = D("country")->field("id_country,title")->select();
        $this->assign("country",$country)->display();
    }
    /*添加*/
    public function add_post(){
        if (IS_POST) {
            $data=I("post.");
            if (D('zone')->create($data)!==false) {
                $result=D('zone')->add();
                if ($result!==false) {
                    add_system_record($_SESSION['ADMIN_ID'], 1, 3, '添加地区成功');
                    F('get_web_all_zone',null);
                    $this->success("添加成功！", U("zone/index"));
                } else {
                    add_system_record($_SESSION['ADMIN_ID'], 1, 3, '添加地区失败');
                    $this->error("添加失败！");
                }
            } else {
                $this->error(D('zone')->getError());
            }
        }

    }



}