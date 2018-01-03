<?php
namespace Category\Controller;
use Common\Controller\AdminbaseController;

/**
 * 分类模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Category\Controller
 */
class IndexController extends AdminbaseController{
	protected $category;
	public function _initialize() {
		parent::_initialize();
        $this->category = D("Common/Category");
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
            $result[$n]['str_manage'] = '<a href="' . U("Category/index/add", array("parentid" => $r['id'], "menuid" => I("get.menuid")))
                . '">添加子菜单</a> | <a href="' . U("Category/index/add",
                    array("parentid" => $r['parentid'],"id" => $r['id'], "menuid" => I("get.menuid"))) . '">'.L('EDIT').'</a>
                     | <a class="js-ajax-delete" href="' . U("Category/index/delete",
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
     * 更新或添加保存操作
     */
    function add_post(){
        if (IS_POST) {
            if ($_POST['id_category']) {
                $categoryId = (int)$_POST['id_category'];
                $_POST['updated_at'] = date('Y-m-d H:i:s');
                $status = 2;
                $msg = '更新分类';
                $result = $this->category->where("id_category=".$categoryId)->save($_POST);
            } else {
                $_POST['create_at'] = date('Y-m-d H:i:s');
                $status = 1;
                $msg = '添加分类';
                $result =  $this->category->data($_POST)->add();
            }
            add_system_record(sp_get_current_admin_id(), $status, 3, $msg);
            $result?$this->success("保存成功！"):$this->error("保存失败！");
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
        if ($this->category->delete($id)!==false) {
            $this->success("删除菜单成功！");
        } else {
            $this->error("删除失败！");
        }
        add_system_record(sp_get_current_admin_id(), 3, 3, '删除分类');
    }
}