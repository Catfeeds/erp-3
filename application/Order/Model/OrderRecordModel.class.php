<?php
namespace Order\Model;
use Common\Model\CommonModel;
class OrderRecordModel extends CommonModel {
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        //array('id_department', 'require', '部门不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        //array('title', 'require', '标题不能为空！', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    );
	protected function _before_write(&$data) {
		parent::_before_write($data);
	}
    public function addHistory($orderId, $statusId, $type=1,$comment = false){
        $userId = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
        $userName = $_SESSION['name'] ? $_SESSION['name'] : '';
        $comment = $comment ? $comment : '';
        $addData = array(
            'id_order'        => $orderId,
            'id_order_status' => $statusId,
            'id_users'         => $userId,
            'user_name'       => $userName,
            'desc'         => $comment,
            'created_at' => date('Y-m-d H:i:s'),
            'type' => $type
        );
        return D("Order/OrderRecord")->data($addData)->add();
    }

    /**
     * 添加订单记录
     * @param $parameter
     */
    public function addOrderHistory($parameter){
        if($parameter['id_order'] && $parameter['id_order_status']){
            if(isset($parameter['user_id']) && $parameter['user_id']){
                $userId = $parameter['user_id'];
                $userName = $parameter['user_name']?$parameter['user_name']:'系统';
            }else{
                $userId = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
                $userName = $_SESSION['name'] ? $_SESSION['name'] : '';
            }
            $comment = $parameter['comment'] ? $parameter['comment'] : '';
            $addData = array(
                'id_order'        => $parameter['id_order'],
                'id_order_status' => $parameter['id_order_status'],
                'id_users'         => $userId,
                'user_name'       => $userName,
                'desc'         => $comment,
                'created_at' => date('Y-m-d H:i:s'),
                'type' => $parameter['type']?$parameter['type']:1,
            );
            D("Order/OrderRecord")->data($addData)->add();
        }
    }
}