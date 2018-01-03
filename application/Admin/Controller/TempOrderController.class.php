<?php 
namespace Admin\Controller;

use Common\Controller\AdminbaseController;
/**
 * 国家、地区模块
 */
class TempOrderController extends AdminbaseController{

    public function index(){
        $sql = " select * from erp_temp_order_n  order by id desc  ";
        $data_list =    M()->query($sql);
 
        $this->assign("data_list",$data_list);
        $this->display();
    }
 
    /*添加地区*/
    public function add(){
        $order_id = $_REQUEST['order_id'];

        if($_POST){

            $data['order_no'] = $_POST['order_no'];
            $data['domain'] = $_POST['domain'];
            $data['spu_no'] = $_POST['spu_no'];
            $data['attr'] = $_POST['attr'];
            $data['zs_spu_no'] = $_POST['spu_no'];
            $data['zs_attr'] = $_POST['attr'];
            $data['name'] = $_POST['name'];
            $data['telphone'] = $_POST['telphone'];
            $data['address'] = $_POST['address'];
            $data['order_status'] = $_POST['order_status'];
      
            if($order_id){
                $query =M()->table("erp_temp_order_n")->where("id='".$order_id."'")->save($data);   
            }else{
                $query =M()->table("erp_temp_order_n")->add($data);       
            }

            $this->success("操作成功", U("Admin/TempOrder/index"));
        }

        if($order_id){
            $sql = " SELECT * FROM  erp_temp_order_n where id = '".$order_id."'";
   
            $info = M()->query($sql);

            $this->assign("info",$info[0]);
        }

       $this->display();
    }

    //添加采购
    function add_purchase(){
        $order_id = $_REQUEST['order_id'];

        if($_POST){
            $id = $_POST['order_id'];
            $data['purchase_name']  = $_POST['purchase_name'];
            $data['purchase_no']    = $_POST['purchase_no'];    
            $data['purchase_status']   = $_POST['purchase_status'];

            $sql = " UPDATE  erp_temp_order_n SET purchase_name = '".$purchase_name."',purchase_no = '".$purchase_no."',purchase_status = '".$purchase_status."'  WHERE  id = '".$order_id."' ";
            //$query = M()->query($sql);
             $query = M()->table("erp_temp_order_n")->where(array("id"=>$order_id))->save($data);  

            $this->success("操作成功", U("Admin/TempOrder/index"));           
        }
        if($order_id){
            $sql = " SELECT * FROM  erp_temp_order_n where id = ".$order_id;

            $info = M()->query($sql);
            $this->assign("info",$info[0]);
        }

       $this->display();
    }

    //添加发货
    function add_deli(){
        $order_id = $_REQUEST['order_id'];

        if($_POST){
            $id = $_POST['order_id'];
            $data['deli_name']      = $_POST['deli_name'];
            $data['deli_no']        = $_POST['deli_no'];    
            $data['deli_status']    = $_POST['deli_status'];  
            $sql = " UPDATE  erp_temp_order_n SET deli_name = '".$deli_name."',deli_no = '".$deli_no."',deli_status = '".$deli_status."'  WHERE  id = '".$order_id."' ";
             //$query = M()->query($sql);
           $query =M()->table("erp_temp_order_n")->where("id = '".$order_id."' ")->save($data);       
            $this->success("操作成功", U("Admin/TempOrder/index"));       
        }

        if($order_id){
            $sql = " SELECT * FROM  erp_temp_order_n where id = ".$order_id;

            $info = M()->query($sql);
            $this->assign("info",$info[0]);
        }

       $this->display();
    }
 

}