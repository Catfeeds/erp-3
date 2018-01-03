<?php
namespace Product\Controller;
use Common\Controller\AdminbaseController;
header("Content-Type:text/html;charset=utf-8;");
/**
 * 产品模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Product\Controller
 */
class IndexController extends AdminbaseController{
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
        if (IS_GET) {
            if (I('get.product_id')) {
                $where['p.id_product'] = array('LIKE', '%'.I('get.product_id').'%');
            }
            if (I('get.category_name')&& I('get.category_name')!='空') {
                $where['c.title'] = array('LIKE', '%'.I('get.category_name').'%');
            }
            if (I('get.category_name')=='空') {
                $where['p.id_category'] = 0;
            }
            if (I('get.product_name')) {
                $where['p.title'] = array('LIKE', '%'.I('get.product_name').'%');
            }
            if (I('get.id_department')) { //部门筛选
                $where['p.id_department'] = array('EQ', I('get.id_department'));
            }
            if (I('get.product_sku')) {
                $where['p.model'] = array('LIKE', '%'.I('get.product_sku').'%');
            }
            if (I('get.inner_name')) {
                $where['p.inner_name'] = array('LIKE', '%'.I('get.inner_name').'%');
            }
            if($_GET['status']==-1){
                $where['p.status'] = 0;
            }elseif($_GET['status']==1){
                $where['p.status'] = 1;
            }
            if($_GET['thumbs']==-1){
                $where['p.thumbs'] = '{"thumb":""}';
            }elseif($_GET['thumbs']==1){
                $where['p.thumbs'] = array('NEQ','{"thumb":""}');
            }
        }
        if (I('get.id_department')){
            $where['p.id_department'] = I('get.id_department'); //产品筛选
        }else{
            $where['p.id_department'] = array('IN',$department_id);
        }
        $where2['id_department'] = array('IN',$department_id); //部门筛选
        //$where['id_department'] = array('IN',$department_id);
        $M = new \Think\Model;
        $pro_table_mame = D("Common/Product")->getTableName();
        $cat_table_Name = D("Common/Category")->getTableName();
        $count = $M->table($pro_table_mame.' AS p LEFT JOIN '.$cat_table_Name.' AS c ON c.id_category=p.id_category')
            ->where($where)
            ->field('count(*) as count')
            ->count();

        $page = $this->page($count, 20);
        $proList = $M->table(($pro_table_mame.' AS p LEFT JOIN '.$cat_table_Name.' AS c ON c.id_category=p.id_category').' LEFT JOIN erp_department AS e ON p.id_department=e.id_department')
            ->where($where)
            ->field('p.*,c.title AS category_title,e.title AS department_title')
            ->order("p.id_product DESC")->limit($page->firstRow , $page->listRows)
            ->select();
        foreach ($proList as $k=>$v) {
            $proList[$k]['img'] = json_decode($v['thumbs'],true);
        }
        //dump($proList);exit;
        $department = D("Common/Department")->where(array('id_department'=>$where2['id_department']))->order('sort asc')->select();
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看产品列表');
        $this->assign("department", $department);
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
            /** @var \Order\Model\OrderModel $order_model */
            $order_model = D("Order/Order");

            $order_status = array(1,2,3,4,5,6,7,8,9,10,16,17,18,19,21,22,23,24,25,26,27);
            $order_where = array('o.id_order_status'=>array('IN',$order_status),'oi.id_product'=>$product_id);
            $product_data['effective_order'] = $order_model->alias('o')->field('oi.id_order')
                ->join('__ORDER_ITEM__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->where($order_where)->order("id_order DESC")->find();
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
        if(isset($_POST['post']['id'])){
            if($_POST['post']['status']==0){
                $this->error('该产品状态为关闭，不能编辑。');
            }
            $bef_data = D('Product/Product')->find($_POST['post']['id']);
            $bef_data['attr'] = D('Product/ProductOption')->get_attr_list_by_id($_POST['post']['id']);
        }else{
            $bef_data = [];
            $bef_data['attr'] = [];
        }


        /** @var \Product\Model\ProductModel $product */
        $product = D('Product/Product');
        $result = $product->write_to_table();
        $actTitle = isset($_POST['post']['id'])?'编辑':'添加';

        if(isset($_POST['post']['id'])){
            $this->add_product_record(sp_get_current_admin_id(),$des='编辑产品',$bef_data,$_POST,$_POST['post']['id']);
        }else{
            $this->add_product_record(sp_get_current_admin_id(),$des='添加产品',$bef_data,$_POST,0);
        }
        add_system_record(sp_get_current_admin_id(), 1, 2, '添加产品');
        if($result['status']){
            $this->success($actTitle."成功！");
        }else{
            $this->error($actTitle."失败！".$result['message']);
        }
    }
    //add a record
    public function add_product_record($id_users,$des,$bef_data,$data,$id_product){
        $data=json_encode($data);
        $bef_data=json_encode($bef_data);
        $adddata['id_users']=$id_users;
        $adddata['data']=$data;
        $adddata['des']=$des;
        $adddata['bef_data']=$bef_data;
        $adddata['id_product']=$id_product;
        $adddata['created_at'] = date('Y-m-d H:i:s');
        $record=M('ProductRecord');
        $result=$record->data($adddata)->add();
        if($result){
            return true;
        }else{
            return fasle;
        }
    }
    public function list_product_record(){
        $where = array();
        if(isset($_GET['id_users'])&&$_GET['id_users']){
            $where['pr.id_users'] = trim($_GET['id_users']);
        }
        if(isset($_GET['id_product'])&&$_GET['id_product']){
            $where['pr.id_product'] = trim($_GET['id_product']);
        }
        if(isset($_GET['des'])&&$_GET['des']){
            $where['pr.des'] =array('LIKE','%'.trim($_GET['des']).'%');
        }
        if(isset($_GET['pro_title']) && $_GET['pro_title']) {
            $_GET['pro_title']=trim($_GET['pro_title']);
            $where['p.title|p.inner_name'] = array('like','%'.$_GET['pro_title'].'%');
        }
//        if(isset($_GET['data'])&&$_GET['data']){
//            $data=json_encode($_GET['data']);
//            $where['data'] =array('LIKE','%'.$data.'%');
//        }

        if (!empty($_GET['start_time']) || !empty($_GET['end_time'])) {
            $created_at_array = array();
            if ($_GET['start_time'])
                $created_at_array[] = array('EGT', $_GET['start_time']);
            if ($_GET['end_time'])
                $created_at_array[] = array('LT', $_GET['end_time']);
            $where['pr.created_at'] = $created_at_array;
        }
        $users = M('Users')->field('id,user_nicename')->where(array('user_status' => 1))->select();
        $users = array_column($users, 'user_nicename', 'id');
        $record=M('productRecord');
//        if(isset($_REQUEST['act']) && $_REQUEST['act']=='export'){
//            vendor("PHPExcel.PHPExcel");
//            vendor("PHPExcel.PHPExcel.IOFactory");
//            vendor("PHPExcel.PHPExcel.Writer.CSV");
//            $excel = new \PHPExcel();
//            $idx = 2;
//            $column = array(
//                //'id','物流','部门','扫码人员','订单号','快递单号','建立日期'
//            );
//            $j = 65;
//            foreach ($column as $col) {
//                $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
//                ++$j;
//            }
//
//            add_system_record(sp_get_current_admin_id(), 7, 2, '导出列表');
//            $excel->getActiveSheet()->setTitle(date('Y-m-d') . '列表.xlsx');
//            $excel->setActiveSheetIndex(0);
//            header('Content-Type: application/vnd.ms-excel');
//            header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '列表.xlsx"');
//            header('Cache-Control: max-age=0');
//            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
//            $writer->save('php://output');
//            exit();
       // }else{

            $count = $record->alias('pr')->join('__PRODUCT__ AS p ON p.id_product=pr.id_product')->where($where)->count();
            $page = $this->page($count, 20);
            $list = $record->alias('pr')->join('__PRODUCT__ AS p ON p.id_product=pr.id_product')->field('pr.*,p.title,p.inner_name')->where($where)->limit($page->firstRow, $page->listRows)->select();
            if(!empty($list)){
                foreach($list as $k =>$v){
                    $list[$k]['data']=json_decode($v['data'],true);
                    $list[$k]['bef_data']=json_decode($v['bef_data'],true);
                }
          //  }
        }

        $this->assign('users',$users);
        $this->assign('list',$list);
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign("getData",$_GET);
        add_system_record($_SESSION['ADMIN_ID'], 4, 3,'查看产品记录跟踪列表');
        $this->display();

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
    
    
    public function close_product(){
        if(!in_array($_SESSION['ADMIN_ID'], array(1,117,526,321))){
            $this->error('没有权限操作！', U('/Product/Index/index'));
        }
        $order_item_table = D('Order/OrderItem')->getTableName();
        if($_POST){
            $info=[];
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('product', 'close_product', $data); 
            $data = $this->getDataRow($data);
            $total=count($data);
            //验证产品的正确性
            if(empty($data)){
                $info['error'][] = sprintf('导入数据不能为空！');
            }
            foreach ($data as $key=> &$item){
                $line=$key+1;
                $item=  trim($item);
                if(!preg_match('/^\d+$/', $item)){
                    $info['error'][] = sprintf('第%s行: '.$msg.':%s  产品不存在！', $line, $item);
                }
                $isexit=M('Product')->where(array('id_product'=>$item))->count();
                if($isexit==0){
                    $info['error'][] = sprintf('第%s行: '.$msg.':%s  产品不存在！', $line, $item);
                }               
            }
            if($info['error']){
                $this->assign('infor', $info);
                $this->assign('total', $total);
                $this->display();
                exit;
            }
            $updateItem=M('ProductSku')->where(array('id_product'=>array('in',$data)))->save(array('status'=>0));
            $updateP=M('Product')->where(array('id_product'=>array('in',$data)))->save(array('status'=>0));
            $this->success('成功关闭产品！',U('/Product/Index/index'));
            exit();
            
        }
        
        $this->display();
    }

    /**
     * 清除缓存
     */
    public function clearcache() {
        sp_clear_cache();
        add_system_record($_SESSION['ADMIN_ID'], 6, 3, '清除缓存');
        $this->display();
    }
    /*
     *修改商品名
     *
     */
    public function updateName()
    {
             if(IS_POST){
                 $id = I('post.id');
                 //接受产品名
                 $inner_name = I('post.inner_name');
                 if($id == '' || $inner_name == ''){
                     $this->error('数据不能为空');
                 }
                 //获取数据模型
                 $model = D("Product");
                 $result = $model->find($id);
                 if($result == 0){
                     $this->error('没有你要修改的数据');
                 }
                 //准备好修改的字段
                 $save['inner_name']=$inner_name;
                 //执行修改
                 $res = $model->where(['id_product'=> $id])->save($save);
                 //判断结果
                 if(!$res > 0){
                     $this->error($res."失败！");
                 }else{
                     $this->success("成功！");
                 }
             }
              $this->display ();
    }
}