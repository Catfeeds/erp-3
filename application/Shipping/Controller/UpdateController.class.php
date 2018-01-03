<?php
namespace Shipping\Controller;
use Common\Controller\AppframeController;

class UpdateController extends AppframeController{

    public function status()
    {
        $shipping_name = strtoupper($_GET['s']);

        $shipping_api_name = "\\Shipping\\Lib\\".$shipping_name . 'ShippingApi';
        if(!$shipping_name || !class_exists($shipping_api_name)){
            $this->returnResult(1, '未知物流');
        }

        $shipping_api_model = new $shipping_api_name();

        $result = $shipping_api_model->update_status();
        if(!$result){
            $this->returnResult(1, $shipping_api_model->get_error(), $shipping_api_model->track_number);
        }
        $this->returnResult(0, 'success', $result);
    }

    private function returnResult($error=0, $message='', $data = array()){
        header("content-type: application/json");
        $result['error'] = $error;
        $result['message'] = $message;
        if(!empty($data)){
            $result['data'] = $data;
        }
        echo json_encode($result);exit;
    }
}
