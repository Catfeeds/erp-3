<?php

namespace Goodslocation\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;

class AreaController extends AdminbaseController {
    public function _initialize() {
        parent::_initialize();
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }
    public function add_area()
    {
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
       if(IS_POST)
       {
           $_POST['coding'] = strtoupper($_POST['coding']);
           $_POST['title'] = strtoupper($_POST['title']);
               $res = M('WarehouseGoodsArea')->add($_POST);
               if ($res == false) {
                   $this->error("添加失败！", U('Area/add_area'));
               }else{
                   $this->success("添加完成！", U('Area/list_area'));
               }
       }
        add_system_record($_SESSION['ADMIN_ID'], 2, 3,'添加货位区域');
        $this->assign('warehouse',$warehouse);
        $this->display();
    }
    public function select_find()
    {
        $coding = $_GET['coding'];
        $title = $_GET['title'];
        $id_warehouse = $_GET['id_warehouse'];
        $find1  = M('WarehouseGoodsArea')->where(array('id_warehouse'=>$id_warehouse,'title'=>$title))->find();
        $find2  = M('WarehouseGoodsArea')->where(array('id_warehouse'=>$id_warehouse,'coding'=>$coding))->find();
        if($find1 or $find2)
        {
            $flag = 1;
            $msg = '数据已存在';
            echo json_encode(array('flag'=>$flag,'msg'=>$msg));
        }else{
            $flag = 0;
            $msg = '数据不存在，可添加';
            echo json_encode(array('flag'=>$flag,'msg'=>$msg));
        }

    }

    public function edit_area()
    {
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        $id = $_REQUEST['id_goods_area'];
        $list = M('WarehouseGoodsArea')->where(array('id_goods_area'=>$id))->find();

        if($_POST){
            $_POST['coding'] = strtoupper($_POST['coding']);
            $_POST['title'] = strtoupper($_POST['title']);
            $find1  = M('WarehouseGoodsArea')->where(array('id_warehouse'=>$list['id_warehouse'],'title'=>$_POST['title'],'id_goods_area'=>array('neq',$id)))->find();
            $find2  = M('WarehouseGoodsArea')->where(array('id_warehouse'=>$list['id_warehouse'],'coding'=>$_POST['coding'],'id_goods_area'=>array('neq',$id)))->find();
            if($find1 or $find2){
                $this->error("修改失败！编码或者名称重复");
            }else{
                $res = M('WarehouseGoodsArea')->save($_POST);

                if ($res == false) {
                    $this->error("修改失败！", U('Area/edit_area',array('id_goods_area'=>$id)));
                }else{
                    $this->success("修改完成！", U('Area/list_area'));
                }
            }

        }
        add_system_record($_SESSION['ADMIN_ID'], 2, 3,'修改货位区域');
        $this->assign('list',$list);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }
    public function list_area()
    {
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where('status=1')->select();
        $warehouse = array_column($warehouse,'title','id_warehouse');
        if(IS_GET){
            if(isset($_GET['id_warehouse'])&&$_GET['id_warehouse'])
                $where['id_warehouse'] = $_GET['id_warehouse'];
            if(isset($_GET['coding'])&&$_GET['coding'])
                $where['coding'] = $_GET['coding'];
            if(isset($_GET['title'])&&$_GET['title'])
                $where['title'] = $_GET['title'];
        }
//        $goods_area_coding =M('WarehouseGoodsArea')->field('DISTINCT coding')->where($where)->order('coding')->select();
        $list_area = M('WarehouseGoodsArea')->order('coding,title')->where($where)->order('id_warehouse')->select();
        $this->assign('areas',$list_area);
//        $this->assign('goods_area_coding',$goods_area_coding);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }
    public function select_by_goods_area_coding()
    {
        if(IS_AJAX){
           $coding = $_GET['coding'];
           $goods_area_name =  M('WarehouseGoodsArea')->field('DISTINCT title')->where(array('coding'=>$coding))->order('title')->select();
           echo json_encode($goods_area_name);
        }
    }
    public function select_by_warehouse()
    {
        if(IS_AJAX){
            $id_warehouse = $_GET['id_warehouse'];
            $coding =  M('WarehouseGoodsArea')->field('DISTINCT coding')->where(array('id_warehouse'=>$id_warehouse))->order('coding')->select();
            echo json_encode($coding);
        }
    }

    public function delete_area()
    {
        $id_goods_area = $_GET['id_goods_area'];
        //检查是否有关联
        $where['wga.id_goods_area']=$id_goods_area;
        $list =  M('WarehouseAllocationStock')->alias('was')
            ->join('__WAREHOUSE_GOODS_ALLOCATION__ as wga on wga.id_warehouse_allocation = was.id_warehouse_allocation','LEFT')
            ->field('was.id')
            ->where($where)->find();
        if(!empty($list)){
            add_system_record($_SESSION['ADMIN_ID'], 2, 3,'删除货位区域失败');
            $this->error("删除失败！已有产品关联此货位区域", U('Area/list_area'));
        }else{
            $res = M('WarehouseGoodsArea')->delete($id_goods_area);
            if ($res == false) {
                add_system_record($_SESSION['ADMIN_ID'], 2, 3,'删除货位区域失败');
                $this->error("删除失败！", U('Area/list_area'));
            }else{
                add_system_record($_SESSION['ADMIN_ID'], 2, 3,'删除货位区域成功');
                $this->success("删除完成！", U('Area/list_area'));
            }
        }

    }



}
