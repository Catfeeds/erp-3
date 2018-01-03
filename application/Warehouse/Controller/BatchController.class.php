<?php

/**
 * 盘点模块
 * @Author wz
 * Class IndexController
 * @package Warehouse\Controller
 */

namespace Warehouse\Controller;

use Common\Controller\AdminbaseController;
use Common\Lib\Procedure;
use Order\Model\UpdateStatusModel;
use Order\Model\OrderRecordModel;
class BatchController extends AdminbaseController {

    public function _initialize() {
        parent::_initialize();
        $this->page = isset($_SESSION['set_page_row']) && $_SESSION['set_page_row'] ? $_SESSION['set_page_row'] : 20;
    }

    /**
     * 列表 
     */
    public function index() {
        $getData = I('get.', "htmlspecialchars");        
        $cur_page = $getData['p']? : 1; //默认页数
        $cond['ob.type']=2;  
        $orderitem_table=M('orderItem')->getTableName();
        $ship_table=M('shipping')->getTableName();
        $shiplist=M('shipping')->where(array('status'=>1))->getField('id_shipping,title');
        $template = M('WaybillTemplate wt')->join("{$ship_table} s on s.id_shipping=wt.id_shipping")->field("wt.id,concat(s.title,'-',wt.title) as concattitle,wt.id_shipping")->where(array('wt.status'=>1))->order('wt.id_shipping ASC')->select();
        
        if (!empty($getData['keyword'])) {
            $getData['keyword']=  trim($getData['keyword']);
            $cond['ob.wave_number'] = array('like', "%{$getData['keyword']}%");
        }        
        if (!empty($getData['id_shipping'])) {
            $cond['ob.id_shipping'] = $getData['id_shipping'];
        }  
        //默认开始，结束时间
        $start_time=$getData['start_time']?$getData['start_time']:date('Y-m-d',  strtotime('-7days'));  
        $end_time=$getData['end_time']?strtotime($getData['end_time']):time();  
        $cond['ob.created_at']=array('between',array($start_time,date('Y-m-d 23:59:59',$end_time)));
        $count = M('orderWave ob')->where($cond)->order('ob.wave_number')->count();
        $subQuery = M('orderWave sub')->field('count(sub.id_order)')->where("sub.wave_number=ob.wave_number")->buildSql(); //到货时间
        $list=M('orderWave ob')->join("{$orderitem_table} oi on oi.id_order=ob.id_order")
                ->field("sum(oi.quantity) as totalquantity,ob.*,{$subQuery} as ordercnt")->group('ob.wave_number')
                ->page("$cur_page,$this->page")->where($cond)->order('ob.created_at desc')->select();
        $page = $this->page($count, $this->page);
        $this->assign("page", $page->show('Admin'));
        $this->assign("template", $template);
        $this->assign('getData', $getData);
        $this->assign('list', $list);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', date('Y-m-d',$end_time));         
        $this->assign('shiplist', $shiplist);
        $this->display();die();
    }
    
    /**
     *
     */
    public function import(){
        if($_POST){
            $st_table = D("shippingTrack")->getTableName();
            $errors=[];
            $orderShipArr = is_array($_POST['order_ship']) ? $_POST['order_ship'] : array($_POST['order_ship']);
            if($orderShipArr){
                //验证导入数据
                $temp['created_at']=date('Y-m-d H:i:s',time());
                $temp['status']=0;
                $temp['type']=2;
                $temp['wave_number']=$this->createDocno();
                foreach($orderShipArr as $item){
                    $isexit=0;$track_number=0;
                    list($temp['id_order'],$temp['id_order_shipping'],$track_number)=explode(',',$item);
                    $order_status=M('order')->where(array('id_order'=>$temp['id_order']))->getField('id_order_status');
                    if($order_status===NULL){
                        $errors[]='运单号：'.$track_number.'无法匹配到订单信息！'; continue;
                    }
//                    $temp['track_number_id']=M('orderShipping os')->join("{$st_table} st on st.track_number=os.track_number")->where(array('os.id_order_shipping'=>$temp['id_order_shipping']))->getField('st.id_shipping_track');
                    $addRes=M("orderWave")->add($temp);
                    if(!$addRes){
                        $errors[]='运单号：'.$track_number.'添加数据失败！'; continue;
                    }    
                    D("Order/OrderRecord")->addHistory($temp['id_order'],$order_status,5, '导入批次单');
                    M('OrderWave')->where(array('id_order'=>$temp['id_order'],'type'=>1))->delete();//删除波次单记录
                }
                if(count($errors)==0){
                    $this->success('生成批次单成功！', U('Batch/index'));
                    exit();
                }
            }else{
               $errors[] ='生成数据不能为空！';
            }
            $this->assign("errors", $errors);
        }
        
        $this->display();        
    }
    
    
    /**
     * 产生新的单据编码
     */
    protected function createDocno() {
        $prefix = 'BH' . date('ymd', time());
        $cond['type']=2;
        $cond['created_at'] = array('like', '%' . date('Y-m-d', time()) . '%');
        $lastDocno = M('orderWave')->where($cond)->order('id desc')->field('wave_number')->find();
        $lastNum = 0;
        if ($lastDocno['wave_number']) {
            $lastNum = substr($lastDocno['wave_number'], strlen($prefix));
        }
        $cur_num = $lastNum + 1;
        return $prefix . str_pad($cur_num, 7, '0', STR_PAD_LEFT);
    }

    
    /**
     * 
     */
    public function  checkTrackNum(){
        $return=array('status'=>0,'msg'=>'');
        $track_number=trim(I('post.track_number'));
        $order_table_name = D('Order/Order')->getTableName();
        if(empty($track_number)){
            $return['msg']='运单号不能为空！';
            echo json_encode($return);            exit();            
        }
        $trackInfo=M('orderShipping os')->join("{$order_table_name} o on o.id_order=os.id_order")->where(array('track_number'=>$track_number))->field('o.id_increment,os.*')->find();
        if(empty($trackInfo)){
            $return['msg']='无法匹配运单号信息！';
            echo json_encode($return);            exit();              
        }
        $isexit=M('OrderWave')->where(array('id_order'=>$trackInfo['id_order'],'type'=>2))->count();
        if($isexit){
            $return['msg']='该运单号已经生成批次单！';
            echo json_encode($return);            exit();
        }
        $return['trackInfo']=$trackInfo;
        $return['status']=1;
        echo json_encode($return);            exit();  
    }

    
    
    public function item_list(){
        $wave_number = I('get.wave_number'); 
        if($_GET['keyword']){
            $getData['keyword']=  trim($_GET['keyword']);
            $cond['o.id_increment'] = array('like', "%{$getData['keyword']}%");                    
        }
        $cond['ob.type']=2;
        $cond['ob.wave_number']=$wave_number;
        $orderitem_table=M('orderItem')->getTableName();
        $ship_table=M('shipping')->getTableName();
        $orderbatch= M('orderWave')->getTableName();
        $ordershipTable= M('OrderShipping')->getTableName();
        $productTb=M('product')->getTableName();
        $orderStaus=M('OrderStatus')->where(array('type'=>1))->getField('id_order_status,title');
        $shipList=M('shipping')->where(array('status'=>1))->getField('id_shipping,title');
        $zoneList=M('zone')->getField('id_zone,title');
        
        $fields="o.id_increment,o.id_zone,o.id_order_status,o.id_shipping,o.first_name,o.tel,o.price_total,o.province,o.city,o.area,o.address,o.remark,os.track_number,o.id_order,o.created_at,ob.id as id";
        $list=M('order o')->join("{$orderbatch} ob on ob.id_order=o.id_order")
                ->join("{$ordershipTable} os on os.id_order=o.id_order")->where($cond)->field($fields)->select();
        $batchinfo=M('OrderWave')->where(array('wave_number'=>$wave_number,'type'=>2))->find();
        foreach ($list as $key=> $item){
            $titles=M('orderItem oi')->join("{$productTb} p on p.id_product=oi.id_product")
            ->field("oi.product_title,oi.sale_title,p.inner_name,oi.sku_title,oi.quantity")->where(array('oi.id_order'=>$item['id_order']))->select();
            $list[$key]['productTitles']=$titles;
        }
        $wave_info['need_match_shipping'] = empty($batchinfo['id_shipping']) ? true : false;

        if(!$wave_info['need_match_shipping']){
            $shipping_info = M('Shipping')->where(array('id_shipping' => $batchinfo['id_shipping']))->find();
            $wave_info['id_shipping'] = $shipping_info['id_shipping'];
            $wave_info['need_send_order'] = $shipping_info['need_send_order'];
            $wave_info['shipping_tag'] = $shipping_info['tag'];
        }        
        $waybills = M('WaybillTemplate')->field('id,title')->where(array('id_shipping' => $batchinfo['id_shipping']))->select();
        $this->assign('batchinfo', $batchinfo);
         $this->assign('wave_info', $wave_info);
        $this->assign('list', $list);
        $this->assign('shipList', $shipList);
//        var_dump($shipList);die();
        $this->assign('getData', $_GET);
        $this->assign('zoneList', $zoneList);
        $this->assign('orderStaus', $orderStaus);
        $this->assign('is_shipping_id', $batchinfo['id_shipping']);
        $this->assign('is_shipping_name', $shipList[$batchinfo['id_shipping']]);
        $this->assign('waybilled', $waybills);
        $this->assign('attr_id',$batchinfo['attr_id']);
        $this->display();
    }

    

    public function export_order_list(){
        $wave_number = I('get.wave_number'); 
        if($_GET['keyword']){
            $getData['keyword']=  trim($_GET['keyword']);
            $cond['o.id_increment'] = array('like', "%{$getData['keyword']}%");                    
        }
        $cond['ob.type']=2;
        $cond['ob.wave_number']=$wave_number;
        $orderitem_table=M('orderItem')->getTableName();
        $ship_table=M('shipping')->getTableName();
        $orderbatch= M('orderWave')->getTableName();
        $ordershipTable= M('OrderShipping')->getTableName();
        $productTb=M('product')->getTableName();
        $orderStaus=M('OrderStatus')->where(array('type'=>1))->getField('id_order_status,title');
        $shipList=M('shipping')->where(array('status'=>1))->getField('id_shipping,title');
        $zoneList=M('zone')->getField('id_zone,title');
        
        $fields="o.id_increment,o.id_zone,o.id_order_status,o.id_shipping,o.first_name,o.tel,o.price_total,o.province,o.city,o.area,o.address,o.remark,os.track_number,o.id_order,o.created_at";
        $list=M('order o')->join("{$orderbatch} ob on ob.id_order=o.id_order")
                ->join("{$ordershipTable} os on os.id_order=o.id_order")->where($cond)->field($fields)->select();
        $batchinfo=M('OrderWave')->where(array('wave_number'=>$wave_number,'type'=>2))->find();
        
        $str = "订单号,地区,订单状态,物流,姓名,电话,总价,产品名称,内部产品名称,外文产品名,送货地址,留言,下单时间,快递单号\n";
        foreach ($list as $key=> $item){
            $titles=M('orderItem oi')->join("{$productTb} p on p.id_product=oi.id_product")
            ->field("oi.product_title,oi.sale_title,p.inner_name,oi.sku_title,oi.quantity")->where(array('oi.id_order'=>$item['id_order']))->select();
            $product_str='';
            $inner_str='';
            $foregin_str='';
            foreach ($titles as  $title){
                $product_str.=$title['product_title'].'+'.$title['sku_title'].'*'.$title['quantity'].';';
                $inner_str.=$title['inner_name'].'+'.$title['sku_title'].'*'.$title['quantity'].';';
                $foregin_str.=$title['sale_title'].'+'.$title['sku_title'].'*'.$title['quantity'].';';
            }
            $product_str=str_replace(array("\n","\r",",","\"","\'"),array("",""), $product_str);
            $inner_str=str_replace(array("\n","\r",",","\"","\'"),array("",""), $inner_str);
            $foregin_str=str_replace(array("\n","\r",",","\"","\'"),array("",""), $foregin_str);
            $item['address']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $item['address']);
            $item['province']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $item['province']);
            $item['city']=str_replace(array("\n","\r",",","\"","\'"),array("",""), $item['city']);
            
            $product_str=  trim($product_str, ";");
            $inner_str=  trim($inner_str, ";");
            $foregin_str=  trim($foregin_str, ";");
            
            $str.=
                    $item['id_increment']."\t," .
                    $zoneList[$item['id_zone']] . ',' .  
                    $orderStaus[$item['id_order_status']] . ',' .
                    $shipList[$item['id_shipping']] . ',' .
                    $item['first_name'] . "\t," .
                    $item['tel'] . "\t," .
                    $item['price_total'] . "," .
                    $product_str . "," .
                    $inner_str . "," .
                    $foregin_str . "," .
                    $item['province']. $item['city'] . $item['address'] . ',' .
                    $item['remark'] . "\t," .
                    $item['created_at'] . "\t," .
                    $item['track_number'] . "\t\n" ;
                                       
        }    
        $filename = date('Ymd') . '.csv'; //设置文件名
        $this->export_csv($filename, iconv("UTF-8","GBK//IGNORE",$str)); //导出
        exit;        
         
    }


    
    public function  removed(){
        $postdata=$_POST;
        $return=array('status'=>0,'msg'=>'');        
        if(empty($postdata['wave_number']||empty($postdata['id_order']))){
            $return['msg']='字段不能为空！';
            echo json_encode($return);            exit();              
        }
//        $User->where('status=0')->delete();
        $delres=M('orderWave')->where($postdata)->delete();
        $return=array('status'=>1,'msg'=>'删除成功！');
        echo json_encode($return);            exit();
    }
    
    
    public function deleteBatch(){
        $postdata=$_POST;    
        $return=array('status'=>0,'msg'=>'');        
        if(empty($postdata['wave_number'])){
            $return['msg']='字段不能为空！';
            echo json_encode($return);            exit();              
        }
//        $User->where('status=0')->delete();
        $delres=M('orderWave')->where($postdata)->delete();
        $return=array('status'=>1,'msg'=>'删除成功！');
        echo json_encode($return);            exit();        
    }

    protected function export_csv($filename, $data) {
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=" . $filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
    }    



 

}
