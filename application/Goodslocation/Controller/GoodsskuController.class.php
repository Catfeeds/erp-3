<?php

namespace Goodslocation\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;

class GoodsskuController extends AdminbaseController {

    public function _initialize() {
        parent::_initialize();
        $this->page = $_SESSION['set_page_row'] ? (int) $_SESSION['set_page_row'] : 20;
    }

    public function index() {
        $where = array();
        if (isset($_GET['inner_name']) && $_GET['inner_name']) {
            $where['p.inner_name'] = array('like', '%' . $_GET['inner_name'] . '%');
        }
        if (isset($_GET['sku']) && $_GET['sku']) {
            $key_where['ps.sku'] = array('LIKE', '%' . $_GET['sku'] . '%');
            $key_where['ps.barcode'] = array('LIKE', '%' . $_GET['sku'] . '%');
            $key_where['_logic'] = 'or';
            $where['_complex'] = $key_where;
        }
        $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : array(0);
        $where['p.id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != '' ? array('EQ', $_GET['id_department']) : array('IN', $department_id);
        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['p.id_department'] = $_GET['id_department'];
        }
        $where['ps.status'] = 1; // 使用的SKU状态
        $M = new \Think\Model;
        $pro_table = D("Common/Product")->getTableName();
        $pro_s_table = D("Common/ProductSku")->getTableName();
        $goods_sku_tab = M('WarehouseGoodsSku')->getTableName();

        if (isset($_GET['goods_name']) && $_GET['goods_name']) {
            $loacal = M('WarehouseGoodsAllocation')->field('id_warehouse_allocation')->where(array('goods_name' => array('like', '%' . $_GET['goods_name'] . '%')))->select();
            $loacal_id = array_column($loacal, 'id_warehouse_allocation');

            $where['gs.id_warehouse_allocation'] = array('IN', $loacal_id);
            $find_count = $M->table($pro_table . ' as p ')->join('LEFT JOIN ' . $pro_s_table . ' as ps ON p.id_product=ps.id_product')
                            ->join('LEFT JOIN ' . $goods_sku_tab . ' as gs ON gs.id_product_sku=ps.id_product_sku')
                            ->field('count(*) as count')
                            ->where($where)->find();
            $count = $find_count['count'];
            $page = $this->page($count, 20);
            $proList = $M->table($pro_table . ' as p ')->join('LEFT JOIN ' . $pro_s_table . ' as ps ON p.id_product=ps.id_product')
                            ->join('LEFT JOIN ' . $goods_sku_tab . ' as gs ON gs.id_product_sku=ps.id_product_sku')
                            ->field('ps.sku,ps.barcode,ps.id_product_sku,ps.title,p.inner_name,p.id_product,p.thumbs,gs.id_warehouse_allocation,gs.id_goods_sku')->where($where)
                            ->order("ps.sku ASC")->limit($page->firstRow . ',' . $page->listRows)->select();
//            dump($proList);die;
        } else {
            $find_count = $M->table($pro_table . ' AS p LEFT JOIN ' . $pro_s_table . ' AS ps ON p.id_product=ps.id_product')
                            ->field('count(*) as count')->where($where)->find();
            $count = $find_count['count'];
            $page = $this->page($count, 20);
            $proList = $M->table($pro_table . ' AS p LEFT JOIN ' . $pro_s_table . ' AS ps ON p.id_product=ps.id_product')
                            ->field('ps.sku,ps.barcode,ps.id_product_sku,ps.title,p.inner_name,p.id_product,p.thumbs')->where($where)
                            ->order("ps.sku ASC")->limit($page->firstRow . ',' . $page->listRows)->select();
        }
        if ($proList && count($proList)) {
            foreach ($proList as $key => $item) {
                $proList[$key]['img'] = json_decode($item['thumbs'], true);
                if (isset($_GET['goods_name']) && $_GET['goods_name']) {
                    $good_local = M('WarehouseGoodsSku')->field('id_warehouse_allocation,id_warehouse')->where(array('id_warehouse_allocation' => $item['id_warehouse_allocation']))->select();
                } else {
                    $good_local = M('WarehouseGoodsSku')->field('id_warehouse_allocation,id_warehouse')->where(array('id_product_sku' => $item['id_product_sku']))->select();
                }
                $warehouse_id = array_column($good_local, 'id_warehouse');
                $good_local_id = array_column($good_local, 'id_warehouse_allocation');
                if ($good_local_id &&$warehouse_id) {                    
                    $warehouse = M('Warehouse')->field('title')->where(array('id_warehouse' => array('IN',$warehouse_id)))->select();
                    $loacal = M('WarehouseGoodsAllocation')->field('goods_name,id_warehouse')->where(array('id_warehouse_allocation' => array('IN', $good_local_id)))->order('id_warehouse ASC,goods_name ASC')->select();
                    $arr = array();
                    foreach ($loacal as $kk=>$vv) {
                        $warehouse = M('Warehouse')->field('title')->where(array('id_warehouse' => $vv['id_warehouse']))->find();
                        $arr[$warehouse['title']][] = $vv['goods_name'];
                    }
                    $proList[$key]['local'] = $arr;
                } else {
                    $proList[$key]['local'] = '';
                }
            }
        }

        add_system_record(sp_get_current_admin_id(), 4, 2, '货位SKU列表');
        $this->assign("department_id", $department_id);
        $this->assign("proList", $proList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /*
     * 添加货位
     */

    public function add() {
        $department = D('Department/Department')->where('type=1')->cache(true, 3600)->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
        if (isset($_GET['id_department']) && $_GET['id_department']) {
            $where['p.id_department'] = array('EQ', $_GET['id_department']);
        }
        $M = new \Think\Model();
        $pro_tab = M('Product')->getTableName();
        $pro_sku_tab = M('ProductSku')->getTableName();
        $where['ps.status'] = 1;
        $product_result_count = $M->table($pro_tab . ' as p')->field('p.inner_name,p.thumbs,ps.sku,ps.title,ps.id_product_sku')
                        ->join('LEFT JOIN ' . $pro_sku_tab . ' as ps ON ps.id_product=p.id_product')
                        ->where($where)->order('ps.sku ASC')->count();

        $page = $this->page($product_result_count, 20);

        $product_result = $M->table($pro_tab . ' as p')->field('p.inner_name,p.thumbs,ps.sku,ps.title,ps.id_product_sku')
                        ->join('LEFT JOIN ' . $pro_sku_tab . ' as ps ON ps.id_product=p.id_product')
                        ->where($where)->order('ps.sku ASC')->limit($page->firstRow, $page->listRows)->select();
        $arr = array();
        foreach ($product_result as $k => $v) {
            $product_result[$k]['img'] = json_decode($v['thumbs'], true);

            $good_result = M('WarehouseGoodsSku')->field('id_goods_sku,id_product_sku,id_warehouse_allocation,id_warehouse')->where(array('id_product_sku' => $v['id_product_sku']))->select();

            $good_local = M('WarehouseGoodsAllocation')->field('id_warehouse_allocation,goods_name')->select();
            $good_local = array_column($good_local, 'goods_name', 'id_warehouse_allocation');
            $product_result[$k]['gloc'] = $good_local;
            $product_result[$k]['good_result'] = $good_result;
        }
        $good_local = M('WarehouseGoodsAllocation')->field('id_warehouse_allocation,goods_name')->select();
        $good_local = array_column($good_local, 'goods_name', 'id_warehouse_allocation');

        $warehouse = M('Warehouse')->field('id_warehouse,title')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');

        add_system_record($_SESSION['ADMIN_ID'], 2, 3, '添加货位区域');
        $this->assign('good_local', $good_local);
        $this->assign('department', $department);
        $this->assign('pro_list', $product_result);
        $this->assign("page", $page->show('Admin'));
        $this->assign('warehouse', $warehouse);
        $this->display();
    }

    public function add_post() {
//        dump($_POST);die;
        if (IS_POST) {
            $good_local_id = $_POST['good_local_id'];
            $goods_id = $_POST['id_goods'];
            $warehouse_id = $_POST['warehouse_id'];

            if (is_array($good_local_id) && is_array($goods_id)) {
                $status = false;
                foreach ($good_local_id as $sku_key => $sku_val) {
                    foreach ($sku_val as $key => $val) {
                        if (!empty($val)) {
                            $local_result = M('WarehouseGoodsSku')->where(array('id_goods_sku' => $goods_id[$sku_key][$key]))->find();
                            $local_res = M('WarehouseGoodsSku')->where(array('id_product_sku' => $sku_key, 'id_warehouse_allocation' => $val, 'id_warehouse' => $warehouse_id[$sku_key]))->find();
                            if ($local_result || $local_res) {
                                $data = array(
                                    'id_warehouse' => $warehouse_id[$sku_key],
                                    'id_warehouse_allocation' => $val
                                );
                                $res = D('Common/WarehouseGoodsSku')->where(array('id_goods_sku' => $goods_id[$sku_key][$key]))->save($data);
                            } else {
                                $data = array(
                                    'id_warehouse' => $warehouse_id[$sku_key],
                                    'id_product_sku' => $sku_key,
                                    'id_warehouse_allocation' => $val
                                );
                                $res = D('Common/WarehouseGoodsSku')->add($data);
                            }
                            if ($res) {
                                $status = true;
                            }
                        }
                    }
                }
            }
            if (!$status) {
                add_system_record($_SESSION['ADMIN_ID'], 2, 3, 'sku指定货位失败');
                $this->error("修改失败！", U('goodssku/add'));
            } else {
                add_system_record($_SESSION['ADMIN_ID'], 2, 3, 'sku指定货位成功');
                $this->success("修改完成！", U('goodssku/add'));
            }
        }
    }

    public function selt_gloc() {
        if (IS_AJAX) {
            $good_local_id = $_POST['id_goods_loc'];
            $sku_id = $_POST['sku_id'];
            $goods_id = $_POST['goods_id'];
            if (is_array($good_local_id) && is_array($sku_id) && is_array($goods_id)) {
                $status = false;
                foreach ($good_local_id as $key => $val) {
                    if (!empty($val)) {
                        $local_result = M('WarehouseGoodsSku')->where(array('id_goods_sku' => $goods_id[$key]))->find();
                        if ($local_result) {
                            $data = array(
                                'id_warehouse_allocation' => $val
                            );
                            $res = D('Common/WarehouseGoodsSku')->where(array('id_goods_sku' => $goods_id[$key]))->save($data);
                        } else {
                            $data = array(
                                'id_product_sku' => $sku_id[$key],
                                'id_warehouse_allocation' => $val
                            );
                            $res = D('Common/WarehouseGoodsSku')->add($data);
                        }
                        if ($res) {
                            $status = true;
                        }
                    }
                }
                if ($status) {
                    $flag = 1;
                    $msg = '添加成功';
                } else {
                    $flag = 0;
                    $msg = '添加失败';
                }
                add_system_record($_SESSION['ADMIN_ID'], 1, 3, 'sku指定货位区域');
                echo json_encode(array('status' => $flag, 'msg' => $msg));
                die();
            }
        }
    }

    /*
     * 根据仓库名称查询对应的货位区域名称
     */

    public function select_by_warehoues() {
        $id_warehouse = $_GET['id_warehouse'];
        $titles = M('WarehouseGoodsArea')->field('id_goods_area,title')->where(array('id_warehouse' => $id_warehouse))->order('title')->select();
        if ($titles) {
            echo json_encode($titles);
        } else {
            $flag = 1;
            $msg = '未查询到仓库对应的货位区域名称，请先添加！';
            echo json_encode(array('flag' => $flag, 'msg' => $msg));
        }
    }

    /*
     * 添加数据前验证数据是否已存在
     */

    public function select_find() {
        $id_warehouse = $_GET['id_warehouse'];
        $id_goods_area = $_GET['id_goods_area'];
        $goods_name = $_GET['goods_name'];
        $find = M('WarehouseGoodsAllocation')->where(array('id_warehouse' => $id_warehouse, 'id_goods_area' => $id_goods_area, 'goods_name' => $goods_name))->find();
        if ($find) {
            $flag = 1;
            $msg = '数据已存在';
            echo json_encode(array('flag' => $flag, 'msg' => $msg));
        } else {
            $flag = 0;
            $msg = '数据不存在，可添加';
            echo json_encode(array('flag' => $flag, 'msg' => $msg));
        }
    }

    /*
     * 编辑货位
     */

    public function edit() {
        $id = $_GET['id_sku'];

        $M = new \Think\Model();
        $pro_tab = M('Product')->getTableName();
        $pro_sku_tab = M('ProductSku')->getTableName();
        $where['ps.id_product_sku'] = array('EQ', $id);
        $pro_result = $M->table($pro_tab . ' as p')->field('p.inner_name,ps.sku,ps.title,ps.id_product_sku')
                        ->join('LEFT JOIN ' . $pro_sku_tab . ' as ps ON ps.id_product=p.id_product')
                        ->where($where)->find();

        $good_result = M('WarehouseGoodsSku')->field('id_goods_sku,id_product_sku,id_warehouse_allocation,id_warehouse')->where(array('id_product_sku' => $id))->select();

        $good_local = M('WarehouseGoodsAllocation')->field('id_warehouse_allocation,goods_name')->select();
        $good_local = array_column($good_local, 'goods_name', 'id_warehouse_allocation');

        $warehouse = M('Warehouse')->field('id_warehouse,title')->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');

        $good_local_id = M('WarehouseGoodsSku')->where(array('id_product_sku' => $id))->getField('id_warehouse_allocation');

        $this->assign('good_result', $good_result);
        $this->assign('list', $pro_result);
        $this->assign('good_local', $good_local);
        $this->assign('good_local_id', $good_local_id);
        $this->assign('warehouse', $warehouse);
        $this->assign('warehouse_id', $good_result[0]['id_warehouse']);
        $this->display();
    }

    public function edit_post() {
        $id = $_POST['id_sku'];
        $warehouse = $_POST['warehouse_id'];

        if (IS_POST) {
            if (!empty($_POST['good_local_id'])) {
                $gid = $_POST['id_goods'];
                foreach ($_POST['good_local_id'] as $k => $v) {
                    if (!empty($v)) {
                        $local_result = M('WarehouseGoodsSku')->where(array('id_goods_sku' => $gid[$k]))->find();
                        $local_res = M('WarehouseGoodsSku')->where(array('id_product_sku' => $id, 'id_warehouse_allocation' => $v, 'id_warehouse' => $warehouse))->find();
                        if ($local_result || $local_res) {
                            $data = array(
                                'id_warehouse' => $warehouse,
                                'id_product_sku' => $id,
                                'id_warehouse_allocation' => $v
                            );
                            $res = D('Common/WarehouseGoodsSku')->where(array('id_goods_sku' => $gid[$k]))->save($data);
                        } else {
                            $data = array(
                                'id_warehouse' => $warehouse,
                                'id_product_sku' => $id,
                                'id_warehouse_allocation' => $v
                            );
                            $res = D('Common/WarehouseGoodsSku')->add($data);
                        }
                    }
                }

                if ($res === false) {
                    add_system_record($_SESSION['ADMIN_ID'], 2, 3, '修改货位失败');
                    $this->error("修改失败！", U('goodssku/index'));
                } else {
                    add_system_record($_SESSION['ADMIN_ID'], 2, 3, '修改货位成功');
                    $this->success("修改完成！", U('goodssku/index'));
                }
            } else {
                $this->error("请选择货位！");
            }
        }
    }

    public function delete_sku() {
        if (IS_AJAX) {
            $gid = $_POST['gid'];
            $result = D('Common/WarehouseGoodsSku')->where(array('id_goods_sku' => $gid))->delete();
            if ($result) {
                $flag = 1;
                $msg = '删除成功';
            } else {
                $flag = 0;
                $msg = '删除失败';
            }
            echo json_encode(array('status' => $flag, 'msg' => $msg));
            exit();
        }
    }

    public function get_goods_location() {
        if (IS_AJAX) {
            $warehouse_id = I('post.warehouse_id');
            $sku_id = I('post.sku_id');

            $warehouse = M('WarehouseGoodsAllocation')->field('id_warehouse_allocation,goods_name')->where(array('id_warehouse' => $warehouse_id))->select();
            $warehouse = array_column($warehouse, 'goods_name', 'id_warehouse_allocation');
            $warehouse_gsku = M('WarehouseGoodsSku')->field('id_warehouse_allocation,id_goods_sku')->where(array('id_warehouse' => $warehouse_id, 'id_product_sku' => $sku_id))->select();

            $html = '<div class="controls local">';
            if ($warehouse_gsku) {
                foreach ($warehouse_gsku as $w_key => $w_val) {                    
                    $html .= '<div class="result">';
                    $html .= '<input type="hidden" name="id_goods[]" value="'.$w_val['id_goods_sku'].'"/>';
                    $html .= '<select name="good_local_id[]" style="width:120px;" class="local'.$w_key.'" >';
                    $html .= '<option value="0">请选择</option>';
                    if ($warehouse) {
                        foreach ($warehouse as $k => $v) {
                            $selt = $k == $w_val['id_warehouse_allocation'] ? 'selected' : '';
                            $html .= '<option value="' . $k . '" ' . $selt . '>' . $v . '</option>';
                        }
                    }
                    $html .= '</select>';
                    if ($w_key == 0) {
                        $html .= '<a href="javaScript:;" class="add_btn"> 添加</a>';
                    } else {
                        $html .= '<a href="javaScript:;" class="re_btn" wg_id="' . $w_val['id_goods_sku'] . '"> 删除</a>';
                    }
                    $html .= '</div>';
                }
            } else {
                $html .= '<select name="good_local_id[]" style="width:120px;" >';
                $html .= '<option value="0">请选择</option>';
                if ($warehouse) {
                    foreach ($warehouse as $k => $v) {
                        $html .= '<option value="' . $k . '" >' . $v . '</option>';
                    }
                }
                $html .= '</select>';
                $html .= '<a href="javaScript:;" class="add_btn"> 添加</a>';
            }
            $html .= '</div>';
            echo $html;
        }
    }

    public function get_goods_alllocaltion() {
        if (IS_AJAX) {
            $warehouse_id = I('post.warehouse_id');
            $sku_id = I('post.sku_id');
            $pro_key = I('post.pro_key');

            $warehouse = M('WarehouseGoodsAllocation')->field('id_warehouse_allocation,goods_name')->where(array('id_warehouse' => $warehouse_id))->select();
            $warehouse = array_column($warehouse, 'goods_name', 'id_warehouse_allocation');
            $warehouse_gsku = M('WarehouseGoodsSku')->field('id_warehouse_allocation,id_goods_sku')->where(array('id_warehouse' => $warehouse_id, 'id_product_sku' => $sku_id))->select();

            $html = '<div class="add_res'.$pro_key.'">';
            if ($warehouse_gsku) {
                foreach ($warehouse_gsku as $w_key => $w_val) {
                    $html .= '<div class="result'.$pro_key.'">';
                    $html .= '<input type="hidden" class="id_goods" name="id_goods['.$sku_id.'][]" value="'.$w_val['id_goods_sku'].'"/>';
                    $html .= '<select name="good_local_id['.$sku_id.'][]" class="sel_id" style="width:120px;">';
                    $html .= '<option value="0">请选择</option>';
                    if ($warehouse) {
                        foreach ($warehouse as $k => $v) {
                            $selt = $k == $w_val['id_warehouse_allocation'] ? 'selected' : '';
                            $html .= '<option value="' . $k . '" ' . $selt . '>' . $v . '</option>';
                        }
                    }
                    $html .= '</select>';
                    $html .= $w_key == 0 ? '' : '<a href="javaScript:;" class="re_btn" wg_id="'.$w_val['id_goods_sku'].'"> 删除</a>';
                    $html .= '</div>';
                }
            } else {
                $html .= '<select name="good_local_id['.$sku_id.'][]" class="sel_id" style="width:120px;">';
                $html .= '<option value="0">请选择</option>';
                if ($warehouse) {
                    foreach ($warehouse as $k => $v) {
                        $html .= '<option value="' . $k . '" >' . $v . '</option>';
                    }
                }
                $html .= '</select>';
            }
            $html .= '</div>';
            echo $html;
        }
    }

    public function get_local_html() {
        if (IS_AJAX) {
            $warehouse_id = I('post.warehouse_id');
            $warehouse_locat = M('WarehouseGoodsAllocation')->field('id_warehouse_allocation,goods_name')->where(array('id_warehouse' => $warehouse_id))->select();
            $warehouse_locat = array_column($warehouse_locat, 'goods_name', 'id_warehouse_allocation');
            $html = '<div class="controls cont local">';
            $html .= '<select name="good_local_id[]" style="width:120px;" >';
            $html .= '<option value="0">请选择</option>';
            if ($warehouse_locat) {
                foreach ($warehouse_locat as $k => $v) {
                    $html .= '<option value="' . $k . '">' . $v . '</option>';
                }
            }
            $html .= '</select>';
            $html .= '<a href="javaScript:;" class="re_btn_other"> 删除</a>';
            $html .= '</div>';
            echo $html;
        }
    }
    
    public function get_alllocal_html() {
        if (IS_AJAX) {
            $warehouse_id = I('post.warehouse_id');
            $key = I('post.k');
            $warehouse_locat = M('WarehouseGoodsAllocation')->field('id_warehouse_allocation,goods_name')->where(array('id_warehouse' => $warehouse_id))->select();
            $warehouse_locat = array_column($warehouse_locat, 'goods_name', 'id_warehouse_allocation');
            $html = '<div class="result">';
            $html .= '<select name="good_local_id['.$key.'][]" style="width:120px;" >';
            $html .= '<option value="0">请选择</option>';
            if ($warehouse_locat) {
                foreach ($warehouse_locat as $k => $v) {
                    $html .= '<option value="' . $k . '">' . $v . '</option>';
                }
            }
            $html .= '</select>';
            $html .= '<a href="javaScript:;" class="re_btn_other"> 删除</a>';
            $html .= '</div>';
            echo $html;
        }
    }

    /*
     * 导入货位
     */

    public function import_position() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $warehouses = M('Warehouse')->field('id_warehouse,title')->select();
        $warehouses = array_column($warehouses, 'id_warehouse', 'title');
        $titles = M('WarehouseGoodsArea')->field('id_warehouse,title,id_goods_area')->select();
        $total = 0;
        if (IS_POST) {
            $user_id = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('goodslocation', 'import_position', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", $row, 3);
                if (count($row) != 3) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
                $warehouse = trim($row[0], '\'" ');
                $area = trim($row[1], '\'" ');
                $goods_name = trim($row[2], '\'" ');

                if ($warehouses[$warehouse] == null) {
                    $infor['error'][] = sprintf('第%s行: 仓库:%s 不存在.', $count++, $warehouse);
                    continue;
                }
                foreach ($titles as $v) {
                    if ($v['id_warehouse'] == $warehouses[$warehouse]) {
                        if ($v['title'] == $area) {
                            $find = M('WarehouseGoodsAllocation')
                                    ->where(array('id_warehouse' => $warehouses[$warehouse], 'id_goods_area' => $v['id_goods_area'], 'goods_name' => $goods_name))
                                    ->find();
                            if ($find) {
                                $infor['error'][] = sprintf('第%s行: 仓库:%s  货位区域：%s  已存在 货位名称：%s.', $count++, $warehouse, $area, $goods_name);
                                break;
                            } else {
                                $data = array(
                                    'id_goods_area' => $v['id_goods_area'],
                                    'id_warehouse' => $v['id_warehouse'],
                                    'goods_name' => $goods_name
                                );
                                $add = M('WarehouseGoodsAllocation')
                                        ->add($data);
                                if ($add) {
                                    $infor['success'][] = sprintf('第%s行: 仓库:%s  货位区域：%s  货位名称：%s 导入成功', $count++, $warehouse, $area, $goods_name);
                                    break;
                                } else {
                                    $infor['error'][] = sprintf('第%s行: 仓库:%s  货位区域：%s  货位名称：%s 导入失败', $count++, $warehouse, $area, $goods_name);
                                    break;
                                }
                            }
                        } else {
                            $infor['error'][] = sprintf('第%s行: 仓库:%s 不存在货位区域:%s.', $count++, $warehouse, $area);
                            break;
                        }
                    } else {
                        $infor['error'][] = sprintf('第%s行: 仓库:%s 不存在货位区域2:%s.', $count++, $warehouse, $area);
                        break;
                    }
                }
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 5, 2, '导入货位', $path);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }

}
