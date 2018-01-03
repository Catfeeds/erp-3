<?php
namespace Purchase\Controller;
use Common\Controller\AdminbaseController;

/**
 * 采购编辑产品
 * Class IndexController
 * @package Product\Controller
 */
class ProductController extends AdminbaseController{
	protected $product;
	public function _initialize() {
		parent::_initialize();
		$this->product=D("Common/Product");
	}

    /**
     * 产品列表
     */
    public function index(){
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where = array();
        if(isset($_GET['product_name']) && $_GET['product_name']){
            $where['p.title'] = array('LIKE', '%'.$_GET['product_name'].'%');
        }
        if (IS_POST) {            
            if (I('post.product_id')) {
                $where['p.id_product'] = array('LIKE', '%'.I('post.product_id').'%');
            }
            if (I('post.category_name')) {
                $where['c.title'] = array('LIKE', '%'.I('post.category_name').'%');
            }
            if (I('post.product_name')) {
                $where['p.title'] = array('LIKE', '%'.I('post.product_name').'%');
            }
            if (I('post.product_sku')) {
                $where['p.model'] = array('LIKE', '%'.I('post.product_sku').'%');
            }
            if (I('post.inner_name')) {
                $where['p.inner_name'] = array('LIKE', '%'.I('post.inner_name').'%');
            }
        }
        $where['id_department'] = array('IN',$department_id);
        $M = new \Think\Model;
        $pro_table_mame = D("Common/Product")->getTableName();
        $cat_table_Name = D("Common/Category")->getTableName();
        $count = $M->table($pro_table_mame.' AS p LEFT JOIN '.$cat_table_Name.' AS c ON c.id_category=p.id_category')
            ->where($where)
            ->field('count(p.id_product) as count')
            ->count();

        $page = $this->page($count, 20);
        $proList = $M->table($pro_table_mame.' AS p LEFT JOIN '.$cat_table_Name.' AS c ON c.id_category=p.id_category')
            ->where($where)
            ->field('p.*,c.title AS category_title')
            ->order("p.id_product DESC")->limit($page->firstRow , $page->listRows)
            ->select();
        foreach ($proList as $k=>$v) {
            $proList[$k]['img'] = json_decode($v['thumbs'],true);
        }
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看产品列表');
        $this->assign("proList",$proList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
	}

    /**
     * 编辑 - 添加产品
     */
    public function edit(){
        /* @var $proModel \Product\Model\ProductModel */
        $pro_model = D("Product");
        /** @var  $options \Product\Model\ProductOptionModel */
        $options = D('Product/ProductOption');
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = array('IN',$department_id);
        $product_attr = array();
        $product_id = isset($_GET['id'])?(int)$_GET['id']:0;
        if($product_id){
            $product_data = $pro_model->where($where)->find($product_id);
            $product_attr = $options->get_attr_list_by_id($product_id);
        }
        import("Tree");
        $tree = new \Tree();
        $parent_id = isset($product_data['id_product'])?$product_data['id_category']:0;
        $field = 'id_category as id,parent_id as parentid,sort as listorder,title,status';
        $result = D("Common/Category")->field($field)
            ->order(array("listorder" => "ASC"))->select();
        foreach ($result as $r) {
            $r['selected'] = $r['id'] == $parent_id ? 'selected' : '';
            $array[] = $r;
        }
        $str = "<option value='\$id' \$selected>\$spacer \$title</option>";
        $tree->init($array);
        $select_category = $tree->get_tree(0, $str);
        //$attrList = D("Common/ProductOption")->order('id desc')->select();
        $department_id  = $_SESSION['department_id'];
        $department  = D('Department/Department')->cache(true,3600)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        $product_data['warehouse_ids'] = D('Warehouse/WarehouseProduct')->where(array('id_product'=>$product_id))
            ->group('id_warehouse')->cache(true,3600)->getField('id_warehouse',true);

        $all_warehouse  = D('Warehouse/Warehouse')->cache(true,3600)->select();
        $classify = M('ProductClassify')->cache(true,3600)->select();
        
        $this->assign("warehouse", $all_warehouse);
        $this->assign("product_attr", $product_attr);
        $this->assign("department_id", $department_id);
        $this->assign("department", $department);
        $this->assign("select_category", $select_category);
        $this->assign("product", $product_data);
        $this->assign('classify',$classify);
        $this->assign("smeta",json_decode($product_data['thumbs'],true));
        $this->display();
    }

    /**
     * 保存编辑
     */
    public function save_post(){
        if(isset($_POST['post']['id_department']) && !$_POST['post']['id_department']){
            $this->error('部门不能为空。');
        }
        /** @var \Product\Model\ProductModel $product */
        $product = D('Product/Product');
        $result = $product->write_to_table();
        $actTitle = isset($_GET['id'])?'编辑':'添加';
        if($result['status']){
            $this->success($actTitle."成功！");
        }else{
            $this->error($actTitle."失败！".$result['message']);
        }
        add_system_record(sp_get_current_admin_id(), 1, 2, '添加产品');
    }

    /**
     * 删除属性
     */
    public function remove(){
        $action = I('post.action');
        $id     = I('post.id');
        /** @var  \Product\Model\ProductOptionValueModel $option_value */
        $option_value = D('Product/ProductOptionValue');
        switch($action){
            case 'option_value':
                $option_value->where('id_product_option_value='.$id)->delete();
                break;
            case 'option_category':
                $product_id = I('post.product_id');
                $option_id  = I('post.option_id');
                $value_where = array('id_product'=>$product_id,'id_product_option'=>$option_id);
                $option_value->where($value_where)->delete();
                D('Product/ProductOption')->where(array('id_product'=>$product_id,'id_product_option'=>$option_id))->delete();
                add_system_record($_SESSION['ADMIN_ID'], 3, 2,'删除产品属性'.$product_id.' 属性'.$option_id);
                break;
        }
    }
    
    /*
     * 产品排重
     */
    public function exclude_repeat() {
        
        $category = M('Category')->field('id_category,title')->where(array('parent_id'=>0))->select();
        $secd_cate = M('Category')->field('id_category,title')->where(array('parent_id'=>$_GET['first_cate']))->select();
        $three_cate = M('Category')->field('id_category,title')->where(array('parent_id'=>$_GET['secd_cate']))->select();
        
        if(isset($_GET['first_cate']) && $_GET['first_cate']) {
            $where['id_category'] = array('EQ',$_GET['first_cate']);
        }
        if(isset($_GET['secd_cate']) && $_GET['secd_cate']) {
            $where['id_category'] = array('EQ',$_GET['secd_cate']);
        }
        if(isset($_GET['three_cate']) && $_GET['three_cate']) {
            $where['id_category'] = array('EQ',$_GET['three_cate']);
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
            $where['title'] = array('like','%'.$_GET['pro_title'].'%');
        }
        
        $pro_result_count = M('Product')->field('id_product,id_department,title,id_category,thumbs')->where($where)->order('id_product DESC')->count();
        $page = $this->page($pro_result_count,12);
        
        $pro_result = M('Product')->field('id_product,id_department,title,id_category,thumbs')->where($where)->order('id_department ASC,id_product DESC')->limit($page->firstRow . ',' . $page->listRows)->select();
        foreach ($pro_result as $k=>$v) {
            $pro_result[$k]['img'] = json_decode($v['thumbs'],true);
            $pro_result[$k]['department'] = M('Department')->where(array('id_department'=>$v['id_department']))->getField('title');
        }
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看产品排重');
        $this->assign('product',$pro_result);
        $this->assign('category',$category);
        $this->assign("page",$page->show('Admin'));
        $this->assign('secd_cates',$secd_cate);
        $this->assign('three_cates',$three_cate);
        $this->display();
    }
    
    public function get_category() {
        if (IS_AJAX) {
            $category_id = I('post.id_category');
            if(!empty($category_id)) {
                $category_result = M('Category')->field('id_category,title')->where(array('parent_id' => $category_id))->select();
                $category = array_column($category_result, 'title', 'id_category');
            }
            $html = '<option value="">请选择二级分类</option>';
            if (isset($category)) {                
                foreach ($category as $k => $v) {
                    $html .= '<option value="' . $k . '" '.$selt.'>' . $v . '</option>';
                }
            }
            echo $html;
        }
    }
    
    public function get_three_category() {
        if (IS_AJAX) {
            $category_id = I('post.id_category');
            if(!empty($category_id)) {
                $category_result = M('Category')->field('id_category,title')->where(array('parent_id' => $category_id))->select();
                $category = array_column($category_result, 'title', 'id_category');
            }
            $html = '<option value="">请选择三级分类</option>';
            if (isset($category)) {
                foreach ($category as $k => $v) {
                    $html .= '<option value="' . $k . '" '.$selt.'>' . $v . '</option>';
                }
            }
            echo $html;
        }
    }
    /**
     * 清除缓存
     */
    public function clearcache() {
        sp_clear_cache();
        add_system_record($_SESSION['ADMIN_ID'], 6, 3, '清除缓存');
        $this->display();
    }
}