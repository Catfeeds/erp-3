<?php
namespace Oms\Model;
use Common\Lib\Currency;
use Common\Model\CommonModel;
use Think\Cache\Driver\Redis;

class OmsModel {

    public function insert_temp_order(){
        try{
            $data = $this->filter_post_html($_POST);

        }catch (\Exception $e){
            $status = false;$message = $e->getMessage();
        }
        $returnData = array('status'=>$status,'message'=>$message,'data'=>$data);
        return json_encode($returnData);
    }
}