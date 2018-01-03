<?php
namespace Checkcategory\Controller;
use Common\Controller\AdminbaseController;

/**
 * 分类模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Checkcategory\Controller
 */
class IndexController extends AdminbaseController{

	protected $category;
	public function _initialize() {
		parent::_initialize();
        $this->category = D("Common/CheckCategory");
	}
    /**
     * 获取菜单深度
     * @param $id
     * @param $array
     * @param $i
     */
    protected function _get_level($id, $array = array(), $i = 0) {
        if ($array[$id]['parentid']==0 || empty($array[$array[$id]['parentid']]) || $array[$id]['parentid']==$id){
            return  $i;
        }else{
            $i++;
            return $this->_get_level($array[$id]['parentid'],$array,$i);
        }
    }

    /**
     * 分类列表
     */
    public function index(){
        $result = $this->category->field('id_category as id,parent_id as parentid,sort as listorder,title,status')
            ->order(array("listorder" => "ASC"))->select();
        import("Tree");
        $tree = new \Tree();
        $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';

        $newmenus=array();
        foreach ($result as $m){
            $newmenus[$m['id']]=$m;
        }
        foreach ($result as $n=> $r) {
            $result[$n]['level'] = $this->_get_level($r['id'], $newmenus);
            $result[$n]['parentid_node'] = ($r['parentid']) ? ' class="child-of-node-' . $r['parentid'] . '"' : '';
            $result[$n]['str_manage'] = '<a href="' . U("Checkcategory/index/add", array("parentid" => $r['id'], "menuid" => I("get.menuid")))
                . '">添加子菜单</a> | <a href="' . U("Checkcategory/index/add",
                    array("parentid" => $r['parentid'],"id" => $r['id'], "menuid" => I("get.menuid"))) . '">'.L('EDIT').'</a>
                     | <a class="js-ajax-delete" href="' . U("Checkcategory/index/delete",
                    array("id" => $r['id'], "menuid" => I("get.menuid")) ). '">'.L('DELETE').'</a> ';
            $result[$n]['status'] = $r['status'] ? L('DISPLAY') : L('HIDDEN');
            if(APP_DEBUG){
                $result[$n]['app']=$r['app']."/".$r['model']."/".$r['action'];
            }
        }

        $tree->init($result);
        $str = "<tr id='node-\$id' \$parentid_node>
					<td style='padding-left:20px;'><input name='listorders[\$id]' type='text' size='3' value='\$listorder' class='input input-order'></td>
					<td>\$id</td>
        			<td>\$spacer\$title</td>
				    <td>\$status</td>
					<td>\$str_manage</td>
				</tr>";
        $categorys = $tree->get_tree(0, $str);
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看分类列表');
        $this->assign("categorys", $categorys);
        $this->display();
	}

    /**
     * 更新排序
     */
    function listorders(){
        $status = parent::_listorders($this->category);
        if ($status) {
            $this->success("排序更新成功！");
        } else {
            $this->error("排序更新失败！");
        }
        add_system_record(sp_get_current_admin_id(), 2, 3, '更新排序');
    }

    /**
     * 添加或更新表单
     */
    function add(){
        import("Tree");
        $tree = new \Tree();
        $parentid = intval(I("get.parentid"));
        if($_GET['id']){
            $category = $this->category->find($_GET['id']);
        }else{
            $category = false;
        }
        $result = $this->category->field('id_category as id,parent_id as parentid,sort as listorder,title,status')->order(array("listorder" => "ASC"))->select();
        foreach ($result as $r) {
            $r['selected'] = $r['id'] == $parentid ? 'selected' : '';
            $array[] = $r;
        }
        $str = "<option value='\$id' \$selected>\$spacer \$title</option>";
        $tree->init($array);
        $select_categorys = $tree->get_tree(0, $str);
        $this->assign("select_categorys", $select_categorys);
        $this->assign("category",$category);
        $this->display();
    }
    /**
     * 导入记录
     */
//id_category,parent_id,title
    public function import() {
        $info = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        if (IS_POST) {
            $data = I('post.data');
            $data = $this->getDataRow($data);
            $count = 1;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 3);
                $title1=$row[0];
                $title2=$row[1];
                $title3=$row[2];
//var_dump($title1);var_dump($title2);var_dump($title3);

                $category1 = M('CheckCategory')->where(array('title'=>$title1,'parent_id'=>0))->getField('id_category');
                if($category1) {
                   // $addData['id_check_category']=$category1;
                    //已有一个一级
                    $category2 = M('CheckCategory')->where(array('title'=>$title2,'parent_id'=>$category1))->getField('id_category');
                    if($category2) {
                        $category3 = M('CheckCategory')->where(array('title'=>$title3,'parent_id'=>$category2))->getField('id_category');
                        if($category3) {
                            $info['error'][] = sprintf('第%s行:已经有相同的分类,%s,%s,%s', $count++,$row[0],$row[1],$row[2]);
                        }else{
                            $ret=D("Common/CheckCategory")->data(['title'=>$title3,'parent_id'=>$category2])->add();
                            if($ret){
                                $info['success'][] = sprintf('第%s行:导入查重成功,1,2有,3没有,%s,%s,%s', $count++, $row[0],$row[1],$row[2]);
                            }
                        }
                    }else{
                        $parent_id2=D("Common/CheckCategory")->data(['title'=>$title2,'parent_id'=>$category1])->add();
                        if($parent_id2){
                            $ret=D("Common/CheckCategory")->data(['title'=>$title3,'parent_id'=>$parent_id2])->add();
                            if($ret){
                                $info['success'][] = sprintf('第%s行:导入查重成功,1有，2,3都没有,%s,%s,%s', $count++, $row[0],$row[1],$row[2]);
                            }
                        }
                    }


                } else {
                    //新增一个一级
                    $addData['title']=$title1;
                    $parent_id1=D("Common/CheckCategory")->data($addData)->add();
                    if($parent_id1){
                        $parent_id2=D("Common/CheckCategory")->data(['title'=>$title2,'parent_id'=>$parent_id1])->add();
                        if($parent_id2){
                            $ret=D("Common/CheckCategory")->data(['title'=>$title3,'parent_id'=>$parent_id2])->add();
                            if($ret){
                                $info['success'][] = sprintf('第%s行:导入查重成功,1,2,3都没有,%s,%s,%s', $count++, $row[0],$row[1],$row[2]);
                            }
                        }
                    }

                }

            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 5, 3, '导入查重');
        $this->assign('infor', $info);
        $this->assign('post', $_POST);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }


    public function export(){
        set_time_limit(0);

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();

        $columns = array(
            '一级分类', '二级分类','三级分类'
        );
        $j = 65;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        $idx = 2;
        $where1['status']=1;
        $where1['parent_id']=0;
        $c1=$this->category->where($where1)->select();

        foreach($c1 as $k => $v) {
            $where2['status'] = 1;
            $where2['parent_id'] = $v['id_category'];
            $c2 = $this->category->where($where2)->select();
            if(empty($c2)){
                    $data = array(
                        $v['title'],'',''
                    );
                    $j = 65;
                    foreach ($data as $col) {
                        $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                        ++$j;
                    }
                    ++$idx;
            }else{
                foreach ($c2 as $k2 => $v2) {
                    $where3['status'] = 1;
                    $where3['parent_id'] = $v2['id_category'];
                    $c3 = $this->category->where($where3)->select();
                    if(empty($c3)){
                        $data = array(
                            $v['title'],$v2['title'],''
                        );
                        $j = 65;
                        foreach ($data as $col) {
                            $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                            ++$j;
                        }
                        ++$idx;
                    }else{
                        foreach ($c3 as $k3 => $v3) {
                            $data = array(
                                $v['title'],$v2['title'],$v3['title']
                            );
                            $j = 65;
                            foreach ($data as $col) {
                                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                                ++$j;
                            }
                            ++$idx;
                        }
                    }

                }
            }

        }

        $excel->getActiveSheet()->setTitle(date('Y-m-d').'查重分类表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d').'查重分类表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');exit();
    }
    /**
     * 更新或添加保存操作
     */
    function add_post(){
        if (IS_POST) {
            if ($_POST['id_category']) {
                $categoryId = (int)$_POST['id_category'];
                if($_POST['title']!=$_POST['old_title']){
                    $ret=$this->category->where(array("title" => $_POST['title']))->count();
                    if ($ret > 0) {
                        $this->error("保存失败,已有相同的分类！");
                    }
                }
                $_POST['updated_at'] = date('Y-m-d H:i:s');
                $status = 2;
                $msg = '更新分类';
                $result = $this->category->where("id_category=".$categoryId)->save($_POST);
            } else {
                $ret=$this->category->where(array("title" => $_POST['title']))->count();
                if ($ret > 0) {
                    $this->error("保存失败,已有相同的分类！");
                }
                $_POST['create_at'] = date('Y-m-d H:i:s');
                $status = 1;
                $msg = '添加分类';
                $result =  $this->category->data($_POST)->add();
            }
            add_system_record(sp_get_current_admin_id(), $status, 3, $msg);
            $result?$this->success("保存完成！", U('index/index')):$this->error("保存失败！");
        }
    }

    /**
     * 删除分类
     */
    function delete(){
        $id = intval(I("get.id"));
        $count = $this->category->where(array("parent_id" => $id))->count();
        if ($count > 0) {
            $this->error("该菜单下还有子菜单，无法删除！");
        }
        $where['id_check_category']=$id;
        $where['status']=array('neq',5);
        $productcount=D('Product/ProductCheck')->where($where)->count();
        if ($productcount > 0) {
            $this->error("该分类已经被引用，请先删除查重记录再删除分类！");
        }
        if ($this->category->delete($id)!==false) {
            $this->success("删除菜单成功！");
        } else {
            $this->error("删除失败！");
        }
        add_system_record(sp_get_current_admin_id(), 3, 3, '删除分类');
    }
}