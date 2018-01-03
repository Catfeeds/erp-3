<?php

namespace Goodslocation\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;

class CargochannelController extends AdminbaseController {

    /**
     * 货位通道列表
     */
    public function index() {

        $where = array();
        if(isset($_GET['warehouse_id']) && $_GET['warehouse_id']) {
            $where['id_warehouse'] = $_GET['warehouse_id'];
            $lwhere['id_warehouse'] = $_GET['warehouse_id'];
        }
        $warehouse_location = M('WarehouseGoodsArea')->where($lwhere)->getField('id_goods_area,title',true);
        if(isset($_GET['local_id']) && $_GET['local_id']) {
            $where['id_warehouse_area'] = array('EQ',$_GET['local_id']);
        }

        if(isset($_GET['channel_name']) && $_GET['channel_name']) {
            $where['channel_name'] = array('like','%'.$_GET['channel_name'].'%');
        }

        $list_count = M('WarehouseCargochannel')->where($where)->count();
        $page = $this->page($list_count,20);
        $list = M('WarehouseCargochannel')->where($where)->limit($page->firstRow.','.$page->listRows)->select();

        foreach($list as $k=>$v) {
            $list[$k]['area_name'] = M('WarehouseGoodsArea')->where(array('id_goods_area'=>$v['id_warehouse_area']))->getField('title');
            $list[$k]['warehouse_name'] = M('Warehouse')->where(array('id_warehouse'=>$v['id_warehouse']))->getField('title');
        }

        $warehouse = M('Warehouse')->where(array('status'=>1))->cache(true,84600)->getField('id_warehouse,title',true);
        $this->assign('list',$list);
        $this->assign('warehouse',$warehouse);
        $this->assign('page',$page->show('Admin'));
        $this->assign('warehouse_location',$warehouse_location);
        $this->display();
    }

    /**
     * 货位通道添加页面
     */
    public function add() {
        $warehouse = M('Warehouse')->where(array('status'=>1))->cache(true,84600)->getField('id_warehouse,title',true);
        $warehouse_location = M('WarehouseGoodsArea')->where(array('id_warehouse'=>1))->getField('id_goods_area,title',true);
        $this->assign('warehouse_location',$warehouse_location);
        $this->assign('warehouse',$warehouse);
        $this->display();
    }

    /**
     * 添加逻辑
     */
    public function add_post() {
        $post = I('post.');
        if(IS_POST) {
            $warehouse_id = $post['warehouse_id'];
            $area_id = $post['local_id'];
            foreach($post['channel_name'] as $val) {
                $result = M('WarehouseCargochannel')->where(array('channel_name'=>$val,'id_warehouse_area'=>$area_id,'id_warehouse'=>$warehouse_id))->find();
                if($result) {
                    continue;
                } else {
                    $data = array(
                        'id_warehouse_area' => $area_id,
                        'id_warehouse' => $warehouse_id,
                        'channel_name' => $val,
                        'created_at' => date('Y-m-d H:i:s')
                    );
                    D('Common/WarehouseCargochannel')->data($data)->add();
                }
            }
            $this->success('添加成功','index');
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '添加货位通道');
        }
    }

    /**
     * 编辑页面
     */
    public function edit() {
        $id = I('get.id/i');
        $result = M('WarehouseCargochannel')->where(array('id_cargo_channel'=>$id))->find();
        $warehouse = M('Warehouse')->where(array('status'=>1))->cache(true,84600)->getField('id_warehouse,title',true);
        $warehouse_location = M('WarehouseGoodsArea')->where(array('id_warehouse'=>$result['id_warehouse']))->getField('id_goods_area,title',true);
        $this->assign('result',$result);
        $this->assign('warehouse',$warehouse);
        $this->assign('warehouse_location',$warehouse_location);
        $this->display();
    }

    /**
     *  编辑逻辑
     */
    public function edit_post() {
        $post = I('post.');
//        dump($post);die;
        if(IS_POST) {
            $warehouse_id = $post['warehouse_id'];
            $area_id = $post['local_id'];
            $result = M('WarehouseCargochannel')->where(array('channel_name'=>$post['channel_name'],'id_warehouse_area'=>$area_id,'id_warehouse'=>$warehouse_id))->find();
            if(!$result) {
                $data = array(
                    'id_cargo_channel' => $post['id'],
                    'id_warehouse_area' => $area_id,
                    'id_warehouse' => $warehouse_id,
                    'channel_name' => $post['channel_name'],
                    'updated_at' => date('Y-m-d H:i:s')
                );
                D('Common/WarehouseCargochannel')->data($data)->save();
            } else {
                $this->error('通道名称已存在，请重新修改');
            }
            $this->success('修改成功','index');
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, '修改货位通道');
        }
    }

    /**
     * 删除
     */
    public function del() {
        if(IS_AJAX) {
            $id = I('post.id/i');
            $res = D('Common/WarehouseCargochannel')->where(array('id_cargo_channel'=>$id))->delete();
            if($res) {
                $flag = 1;
                $msg = '删除成功';
            } else {
                $flag = 0;
                $msg = '删除失败';
            }
            add_system_record($_SESSION['ADMIN_ID'], 3, 3, '删除货位通道');
            echo json_encode(array('flag'=>$flag,'msg'=>$msg));die;
        }
    }

    /**
     * 获取仓库对应的区域
     */
    public function get_goods_location() {
        if(IS_AJAX) {
            $warehouse_id = I('post.warehouse_id');
            if(!empty($warehouse_id)) $where['id_warehouse'] = $warehouse_id;
            $warehouse_location = M('WarehouseGoodsArea')->where($where)->getField('id_goods_area,title',true);
            $html = '<select name="local_id" class="local_id" required>';
            $html .= '<option value="">请选择</option>';
            if($warehouse_location) {
                foreach($warehouse_location as $key=>$val) {
                    $html.= '<option value="'.$key.'">'.$val.'</option>';
                }
            }
            $html .= '</select>';
            echo $html;
        }
    }
}