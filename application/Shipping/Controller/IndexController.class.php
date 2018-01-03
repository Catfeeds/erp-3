<?php
namespace Shipping\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;

class IndexController extends AdminbaseController {

    protected $shipping;

    public function _initialize() {
        parent::_initialize();
        $this->shipping = D("Common/Shipping");
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }

    /*
     * 物流列表
     */

    public function index() {
        $findCount = D("Common/Shipping")->field('count(*) as count')->find();
        $count = $findCount['count'];
        $page = $this->page($count, 20);
        $proList = D("Common/Shipping")->order("id_shipping DESC")
                        ->limit($page->firstRow . ',' . $page->listRows)->select();
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看物流列表');
        $this->assign("proList", $proList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    /*
     * 物流添加页面
     */

    public function create() {
        $id = I('get.id');
        $data = array();
        if($id){
            $data = $this->shipping->find($id);
        }
        $this->assign("shipping",$data);
        $this->display();
    }

    /*
     * 物流添加逻辑
     */

    public function save_post() {
        if (IS_POST) {
            $data = I('post.');
            if (isset($data['id']) && $data['id']) {
                $data['updated_at'] = date('Y-m-d H:i:s');
                $result = $this->shipping->where('id_shipping=' . $data['id'])->save($data);
                $msg = $result ? '物流'.$data['id'].'修改成功' : '物流'.$data['id'].'修改失败';
                $status = 2;
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                if($this->shipping->create($data)) {
                    $result = $this->shipping->add($data);
                    $msg = $result ? '物流添加成功' : '物流添加失败';
                    $status = 1;
                } else {
                    $this->error($this->shipping->getError());
                }
            }
            if ($result) {
                add_system_record(sp_get_current_admin_id(), $status, 1, $msg);
                $this->success($msg, U("index/index"));
            } else {
                add_system_record(sp_get_current_admin_id(), $status, 1, $msg);
                $this->error($msg, U("index/index"));
            }
        }
    }

    /*
     * 物流删除
     */
    public function delete() {
        $id = intval(I("get.id"));
        $status = $this->shipping->delete($id);   
        if ($status) {
            add_system_record(sp_get_current_admin_id(), 3, 1, '删除物流'.$id.'成功');
            $this->success("删除成功！", U('index/index'));
        } else {
            add_system_record(sp_get_current_admin_id(), 3, 1, '删除物流'.$id.'失败');
            $this->error("删除失败！");
        }
    }
    
    public function status_statistics(){
        if(isset($_GET['shipping_id']) && $_GET['shipping_id']){
            $where[]= array('o.id_shipping'=>$_GET['shipping_id']);
            $total_where[] = array('id_shipping'=>$_GET['shipping_id']);
        }
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['id_department']= $_GET['id_department'];
            $total_where['id_department'] = $_GET['id_department'];
        }else{
            $total_where['id_department'] = array('IN',$_SESSION['department_id']);
        }
        /*创建日期初始化*/
        $created_at_array = array();
        if ($_GET['start_time'] or $_GET['end_time'])
        {
            if ($_GET['start_time'])
            {
                $created_at_array[] = array('EGT', $_GET['start_time']);
            }
            if ($_GET['end_time'])
            {
                $created_at_array[] = array('LT', $_GET['end_time']);
            }
        }
        else
        {
            if (!$_GET['start_time'] && !$_GET['end_time'])
            {
                $get_data['start_time'] = $_GET['start_time'] = date('Y-m-d H:i',time()-86400*7);
                $get_data['end_time'] = $_GET['end_time'] = date('Y-m-d H:i',time());
                $created_at_array[] = array('EGT', $get_data['start_time']);
                $created_at_array[] = array('LT', $get_data['end_time']);
            }
        }
        $where['o.created_at'] = $created_at_array;
        $total_where = array('created_at'=>$created_at_array);
        $this->assign("start_time",$get_data['start_time']);
        $this->assign("end_time",$get_data['end_time']);
//        if (isset($_GET['start_time']) && $_GET['start_time']) {//搜索物流运单号表的订单
//            $createAtArray = array();
//            if ($_GET['start_time']) $createAtArray[] = array('EGT', $_GET['start_time']);
//            if ($_GET['end_time']) $createAtArray[] = array('LT', $_GET['end_time']);
//            $where[]= array('o.created_at'=>$createAtArray);
//            $total_where = array('created_at'=>$createAtArray);
//        }
        //$where[] = array('(os.track_number !="" AND os.track_number != null)');
        //$where[] = array('(o.payment_method = 0 OR o.payment_method = "")');
        $where['_string'] = '(os.track_number !="" or os.track_number != null) and (o.payment_method = 0 OR o.payment_method = "") and o.`id_order_status`!=14';
        /* @var $ordModel \Common\Model\OrderModel */
        $ordModel = D("Order/Order");
        $M = new \Think\Model;
        $ordName = $ordModel->getTableName();
        /** @var  $ordShipping \Common\Model\OrderShippingModel */
        $ordShipping = D("Order/OrderShipping");
        $ordShiName = $ordShipping->getTableName();
        $statusList = $ordShipping->group('summary_status_label')->select();
        $tempStatus = array();
        $setStaList = array();
        $temp_string = array();
        foreach($statusList as $key=>$status){
            if(!in_array($status['summary_status_label'],$temp_string)){
                $temp_string[] = $status['summary_status_label'];
                $tempStatus[] = "SUM(IF(os.`summary_status_label`='".$status['summary_status_label']."',1,0)) AS status".$key;
                $setStaList['status'.$key] = !empty($status['summary_status_label']) ? $status['summary_status_label'] : '空';
            }
        }
        $tempStatus = count($tempStatus)?','.implode(',',$tempStatus):'';
        $fieldStr   = "SUBSTRING(o.created_at,1,10) AS set_date,count(os.id_order) as count_all".$tempStatus;

        $count = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->select();
        $page = $this->page(count($count), 20);
        $selectOrder = $M->table($ordName . ' AS o LEFT JOIN ' . $ordShiName . ' AS os ON o.id_order=os.id_order')
            ->field($fieldStr)->where($where)
            ->group('set_date')->order('set_date desc')->limit($page->firstRow, $page->listRows)->select();
        if($selectOrder){
            foreach($selectOrder as $s_key=>$s_item){
                $set_date = $s_item['set_date'];
                $total_where['SUBSTRING(created_at,1,10)'] = $set_date;
                $total_where['_string'] = "(payment_method is NULL OR payment_method='' or payment_method='0')";

                $effective_status = OrderStatus::get_effective_status();
                $effective = $ordModel->field('SUM(IF(`id_order_status` IN('.implode(',', $effective_status).'),1,0)) as effective')
                    ->where($total_where)->find();
                $selectOrder[$s_key]['effective'] = $effective?$effective['effective']:0;
            }
        }
        $shipping = D("Common/Shipping")->where('status=1')->cache(true,6000)->select();
        $shipItem = array();
        if($shipping){
            foreach($shipping as $item){
                $shipItem[$item['id_shipping']] = $item['title'];
            }
        }
        $department_id  = $_SESSION['department_id'];
        $department = D('Common/Department')->where('type=1')->cache(true, 6000)->select();
        $department = $department ? array_column($department, 'title', 'id_department') : array();
        add_system_record(sp_get_current_admin_id(), 4, 4, '查看物流状态统计');
        $this->assign("department_id", $department_id);
        $this->assign('department', $department);
        $this->assign("shipping", $shipItem);
        $this->assign("list", $selectOrder);
        $this->assign("status_list", $setStaList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }
    /*
     * 物流列表
     * 新增搜索功能--搜索条件：物流名称$_GET['title']、状态(开启/关闭)$_GET['status']     liuruibin   20171018
     */
    public function shipping_list()
    {
        if(isset($_GET['title']) && $_GET['title']){
            $title = $_GET['title'];
            $where['title'] = array('like',"%{$title}%");
        }
        if(isset($_GET['status'])&&$_GET['status']){
            $status = $_GET['status'] == 1 ? $_GET['status']:0;
            $where['status'] = array('EQ',$status);
        }
        $count =  $this->shipping->where($where)->order('id_shipping DESC')->count();
        $page = $this->page($count, 12);
        $shipping_list = $this->shipping->where($where)->order('status DESC,id_shipping DESC')->limit($page->firstRow . ',' . $page->listRows)->select();
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看物流列表');
        $this->assign("Page", $page->show('Admin'));
        $this->assign('shipping_list',$shipping_list);
        $this->display();
    }
    /*
     * 运单号库列表
     */

    public function track_list()
    {
        $id_shipping = $_GET['id_shipping'];
        if(isset($id_shipping)&&$id_shipping) {
            $where['st.id_shipping'] = $id_shipping;
            $twhere['id_shipping'] = $id_shipping;
        }
        //增加运单号搜索   --Lily  2017-10-18
        if(isset($_GET['track_number']) && $_GET['track_number']){
            $where['st.track_number'] = trim($_GET['track_number']);
        }
        $count =M('ShippingTrack')->alias('st')->field('s.title,st.*')
            ->join('__SHIPPING__ as s on st.id_shipping = s.id_shipping')->where($where)->count();
        $page = $this->page($count, 20);
        $datas =  M('ShippingTrack')->alias('st')->field('s.title,s.title,st.*')
            ->join('__SHIPPING__ as s on st.id_shipping = s.id_shipping')->where($where)
            ->limit($page->firstRow, $page->listRows)->select();
        $shippings = $this->shipping->field('id_shipping,title')->where(array('status'=>1))->cache(true,7200)->select();
        
        $twhere['track_status'] = 0;        
        $not_use = M('ShippingTrack')->field('count(*) as count')->where($twhere)->find();
        $shipping_track_count = M('ShippingTrack')->alias('st')->field('count(*) as count')->where($where)->find();
        add_system_record(sp_get_current_admin_id(), 4, 3, '查看运单号列表');
        $this->assign("Page", $page->show('Admin'));
        $this->assign("current_page", $page->GetCurrentPage());
        $this->assign('datas',$datas);
        $this->assign('shippings',$shippings);
        $this->assign('not_use',$not_use['count']);
        $this->assign('shipping_track_count',$shipping_track_count['count']);
        $this->display();
    }
    /*
     * 删除运单号
     */
    public function delete_track()
    {
        $id_shipping_track = $_GET['id_shipping_track'];
        $res = M('ShippingTrack')->delete($id_shipping_track);
        if ($res == false) {
            add_system_record(sp_get_current_admin_id(), 3, 1, '删除运单号'.$id_shipping_track.'失败');
            $this->error("删除失败！", U('index/delete_track', array('id_shipping' =>$_GET['id_shipping'])));
        }
        else{
            add_system_record(sp_get_current_admin_id(), 3, 1, '删除运单号'.$id_shipping_track.'成功');
            $this->success("删除完成！", U('index/track_list',array('id_shipping' =>$_GET['id_shipping'])));
        }

    }
    /*
     * 编辑物流
     */
    public function edit_shipping()
    {
        if(IS_POST)
        {
            $id_shipping = $_GET['id_shipping'];
            $_POST['updated_at'] = date('Y-m-d H:i:s');
            $res = $this->shipping->save($_POST);
            if ($res == false) {
                add_system_record(sp_get_current_admin_id(), 2, 1, '编辑物流失败');
                $this->error("修改失败！", U('index/edit_shipping', array('id_shipping' =>$id_shipping)));
            }
            else
            {
                add_system_record(sp_get_current_admin_id(), 2, 1, '编辑物流成功');
                $this->success("修改完成！", U('index/shipping_list'));
            }
        }
        $id_shipping = $_GET['id_shipping'];
        $shipping = $this->shipping->where(array('id_shipping'=>$id_shipping))->find();
        $this->assign('shipping',$shipping);
        $this->display();
    }
    /*
     * 删除物流
     */
    public function delete_shipping(){
        $id_shipping = $_GET['id_shipping'];
        $res = $this->shipping->delete($id_shipping);
        if ($res == false) {
            add_system_record(sp_get_current_admin_id(), 3, 1, '删除物流'.$id_shipping.'失败');
            $this->error("删除失败！", U('index/delete_shipping', array('id_shipping' =>$id_shipping)));
        }
        else{
            add_system_record(sp_get_current_admin_id(), 3, 1, '删除物流'.$id_shipping.'成功');
            $this->success("删除完成！", U('index/shipping_list'));
        }

    }

    /*
     * 添加物流add
     */
    public function add_shipping(){
        if(IS_POST)
        {
            $_POST['created_at'] = date('Y-m-d H:i:s');
            $res = $this->shipping->add($_POST);
            if ($res == false) {
                add_system_record(sp_get_current_admin_id(), 1, 1, '添加物流失败');
                $this->error("添加失败！", U('index/add_shipping'));
            }
            else{
                add_system_record(sp_get_current_admin_id(), 1, 1, '添加物流成功');
                $this->success("添加完成！", U('index/shipping_list'));
            }

        }
        $this->display();
    }

}
