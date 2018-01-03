<?php
namespace Product\Controller;
use Common\Controller\HomebaseController;

class CrontabController extends HomebaseController {

    /**
     * 每日0：01执行
     * 将不符合规定的备案记录设置为消档
     */
    public function cancel_record(){

        //分页处理
        $per_page = 1000;
        $time_15days_before = date('Y-m-d', strtotime('-7 days'));//15天改成7天
        $effected_status = \Order\Lib\OrderStatus::get_effective_status();
        $product_check_model = M('ProductCheck');
        $order_model = M('Order');

        //总记录数
        $count = M('ProductCheck')->alias('pc')
            ->join("__DOMAIN__ as d ON d.id_domain=pc.id_domain", "LEFT")
            ->where(array('pc.status'=>1))
            ->where(array('pc.id_domain'=>array('GT', 0)))
            ->where(array('pc.record_time'=> array('LT', $time_15days_before)))   //根据备案时间判断
            //->where(array('d.created_at'=> array('LT', $time_15days_before)))          //根据域名创建时间判断
            ->count();

        $delete_count = 0;

        echo "=====start, count:{$count}=========\n";

        for($i=0; $i<$count; $i+=$per_page){
            //待处理记录
            $list = $product_check_model->alias('pc')
                ->join("__DOMAIN__ as d ON d.id_domain=pc.id_domain", "LEFT")
                ->where(array('pc.status'=>1))
                ->where(array('pc.id_domain'=>array('GT', 0)))
               ->where(array('pc.record_time'=> array('LT', $time_15days_before)))
               // ->where(array('d.created_at'=> array('LT', $time_15days_before)))
                ->limit($i, $per_page)
                ->select();

            foreach($list as $record){
                //找出15天内该产品的订单
                $effected_order_count = $order_model->alias('o')
                    ->join('__ORDER_ITEM__ as oi ON oi.id_order = o.id_order', 'left')
                    ->where(array('o.created_at'=> array('EGT', $time_15days_before)))
                    ->where(array('o.id_order_status' => array('IN', $effected_status)))
                    ->where(array('oi.id_product'=>$record['id_product']))
                    //->where(array('o.id_domain' => $record['id_domain']))
                    ->count();
                if($effected_order_count <= 0){
                    M('ProductCheck')
                        ->where(array('id_checked'=>$record['id_checked']))
                        ->save(array('status'=>0));
                    $delete_count++;
                    echo "=====id_checked[{$record['id_checked']}]deleted=========\n";
                }
            }
        }

        echo "=====over, delete_count:{$delete_count}=========\n";
        $this->check_review();
    }

    //检查查重是否过期，过期就删除
    public function check_review(){
        //$where['domain'] = array('exp', 'IS NULL');
        $where['id_domain|id_product'] = 0;
        $where['status'] = 1;
        $result=D('Product/ProductCheck')->field("id_checked,check_time,end_time,pid")->where($where)->select();
        $del=array();
        if(!empty($result)){
            foreach($result as $k =>$v){
                if(empty($v['end_time'])){
                    // $endtime=strtotime($v['check_time'])+3*24*3600;
                    $endtime= $this->get_end_time(strtotime($v['check_time']));
                }else{
                    $endtime=$v['end_time'];
                }
                $now=time();
                if($now >=$endtime){
                    $del[$k]= D('Product/ProductCheck')->where(array('id_checked' => $v['id_checked']))->save(['status'=>-1]);
                    // $del[$k]= D('Product/ProductCheck')->where(array('id_checked' => $v['id_checked']))->delete();
                    if($v['pid'] > 0){
                        $result=D('Product/ProductCheck')->where('id_checked=' . $v['pid'])->save(['status'=>0]);
                    }
                }
            }
        }
        //$this->syn_inner_name();
        var_dump($del);die;
    }
    public function get_end_time($t){
        //$t=time()+24*3600*6;
        $start_time_week = date('w',$t);//开始时间的星期数
        switch ($start_time_week)
        {
            case 3://加5天
                $t2=$t+24*3600*5;
                break;
            case 4://加5天
                $t2=$t+24*3600*5;
                break;
            case 5://加5天
                $t2=$t+24*3600*5;
                break;
            case 6://加4天
                $t2=$t+24*3600*4;
                break;
            default://加3天
                $t2=$t+24*3600*3;
                break;
        }
        return $t2;
    }
    public function syn_inner_name(){
        $data=D('Product/ProductCheck')->where(array('id_domain'=>array('GT',0)))->field('id_domain,id_checked')->select();
        foreach($data as $k => $v){
            $order = D('Order')->field('id_order,id_department,id_users')->order('id_order DESC')->where(array('id_domain'=>$v['id_domain']))->find();
            if(!empty($order)) {
                $order2 = D('OrderItem')->alias('oi')->field('p.inner_name')
                    ->join('__PRODUCT__ as p ON p.id_product = oi.id_product', 'left')
                    ->where(['id_order' => $order['id_order']])->group('oi.id_product')->select();
                $inner_name='';
                $inner_name_array=array();
                foreach($order2 as $v2){
                    array_push($inner_name_array,$v2['inner_name']);
                }
                $inner_name=implode(',',$inner_name_array);
                $update=array();
                $update['inner_name']=$inner_name;
//            $update['id_department']=$order['id_department'];
//            $update['id_users']=$order['id_users'];
                $result[$k]=D('Product/ProductCheck')->where('id_checked='.$v['id_checked'])->save($update);
            }

        }
        //var_dump($update);var_dump($result);die;
    }
    public function syn_inner_name2(){
        $data=D('Product/ProductCheck')->where(array('id_domain'=>array('GT',0)))->field('id_domain,id_checked')->select();
        foreach($data as $k => $v){
            $order = D('Order')->field('id_order,id_department,id_users')->order('id_order DESC')->where(array('id_domain'=>$v['id_domain']))->find();
            if(!empty($order)) {
                $order2 = D('OrderItem')->alias('oi')->field('p.inner_name')
                    ->join('__PRODUCT__ as p ON p.id_product = oi.id_product', 'left')
                    ->where(['id_order' => $order['id_order']])->group('oi.id_product')->select();
                $inner_name='';
                $inner_name_array=array();
                foreach($order2 as $v2){
                    array_push($inner_name_array,$v2['inner_name']);
                }
                $inner_name=implode(',',$inner_name_array);
                  $update=array();
            $update['inner_name']=$inner_name;
            $update['id_department']=$order['id_department'];
            $update['id_users']=$order['id_users'];
            $result[$k]=D('Product/ProductCheck')->where('id_checked='.$v['id_checked'])->save($update);
            }
          
        }
        var_dump($update);var_dump($result);die;
    }
}
