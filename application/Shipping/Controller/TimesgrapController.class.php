<?php
namespace Shipping\Controller;
use Common\Controller\AppframeController;
header("Content-type:text/html;charset=utf-8");
class TimesgrapController extends AppframeController{
    /*
        * times物流抓取
        */
    public  function webhook() {
        $webhook_secret = 'I98LNynQ7BbRFt37odt6auo2bfSZWQxS3Yl4fk0YNi49GGm4vl2gjqmQHkXp';
        try {
            $token = $this->getBearerToken();
            if (empty($token)) {
                throw new Exception("Authentication Error.");
            }
            if ($token != $webhook_secret) {
                throw new Exception("Authentication Error.");
            }

            /*
             * Get own reference number and their related timestamp
             */

            $content = array();
            parse_str(urldecode(file_get_contents("php://input")), $content);
            $tracking_number = $content['tracking_number'];
            $reference_number = $content['reference_number'];
            $sort_in = $content['sort_in'];
            $sort_out = $content['sort_out'];
            $close_box = $content['close_box'];
            $handover_linehaul = $content['handover_linehaul'];
            $reject = $content['reject'];
            $return = $content['return'];
            $receive = $content['receive'];
            $data = array(
                'refuse'=>$reject,
                'return_finish'=>$return,
                'received'=>$receive
            );
            $data = array_filter($data);
            $data = implode('',array_keys($data));
            /*
             * Check and update your database if authenticate successs
             */
            $shipping_api_name = "\\Shipping\\Lib\\ShippingApi";
            $shipping_api_model = new $shipping_api_name();
            $shipping_api_model->track_number = $reference_number;
            switch($data){
                case 'refuse':
                    $shipping_api_model->status_type = '拒收';
                    $shipping_api_model->status_name = '拒收';
                    break;
                case 'refusereturn_finish':
                    $shipping_api_model->status_type = '退貨完成';
                    $shipping_api_model->status_name = '退貨完成';
                    break;
                case 'received':
                    $shipping_api_model->status_type = '順利送達';
                    $shipping_api_model->status_name = '順利送達';
                    break;
                case 'handover_lastmile':
                    $shipping_api_model->status_type = '派送中';
                    $shipping_api_model->status_name = '派送中';
                    break;
               default:
                    $shipping_api_model->status_type = '';
                    $shipping_api_model->status_name = '';
                    break;
            }
            if($data&&$reference_number){
                $result = $shipping_api_model->update_status();
                $return = array(
                    'tracking_number'=>$tracking_number,
                    'reference_number'=>$reference_number
                );
                if(!$result){
                    $this->returnResult(1, $shipping_api_model->get_error(),$return);
                }
                $this->returnResult(0, 'success',$return);
            }


        } catch (Exception $e) {
            echo json_encode(
                array(
                    'message' => $e->getMessage(),
                )
            );
        }
    }
    private function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    private function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
    private function returnResult($error=0, $message='', $data = array()){
        header("content-type: application/json");
        $result['error'] = $error;
        $result['message'] = $message;
        if(!empty($data)){
            $result['tracking_number'] = $data['tracking_number'];
            $result['reference_number'] = $data['reference_number'];
        }
        echo json_encode($result);exit;
    }
}
