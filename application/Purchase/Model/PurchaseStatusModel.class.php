<?php

namespace Purchase\Model;
use Common\Model\CommonModel;

class PurchaseStatusModel extends CommonModel {

    protected function _before_write(&$data) {
        parent::_before_write($data);
    }

    const IMPORT_PRICE = 1;//导入采购成本
    const IMPORT_WEIGHT = 2;//导入重量

    public function add_pur_history($purId, $statusId, $comment = false) {
        $userId = $_SESSION['ADMIN_ID'] ? $_SESSION['ADMIN_ID'] : 0;
        $comment = $comment ? $comment : '';
        $addData = array(
            'id_purchase' => $purId,
            'status' => $statusId,
            'id_users' => $userId,
            'desc' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
        );
        D("Purchase/PurchaseRecord")->data($addData)->add();
    }

    //保存单据逻辑
    public static function add_post($array) {
        $docno = M(''.$array['table'].'')->field('docno')->order('docno DESC')->find();
        $docno_num = substr($docno['docno'],2)+1;
        $data['docno'] = $docno ? 'DR'.$docno_num : 'DR'.date('ymd').'0000001';
        $data['bill_date'] = I('post.bill_date');
        $data['description'] = I('post.description');
        $data['status'] = 1;
        $data['owner_id'] = $_SESSION['ADMIN_ID'];
        $data['statuser_id'] = 0;
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['type'] = $array['type'];

        $purchase_import_id = D('Common/'.$array['table'].'')->add($data);

        return $purchase_import_id;
    }

    //导入SKU逻辑
    public static function import_sku($array) {

    }

	//根据单据号获取SKU库存
	function get_purchase_initem_quantity($id_purchasein){

		$sql	=	" SELECT wp.id_product_sku,wp.quantity FROM erp_purchase_initem AS pi INNER JOIN erp_warehouse_product AS wp ON pi.id_product_sku =wp.id_product_sku  WHERE  pi.id_purchasein ='".$id_purchasein."' ";	
	
		$result	=	M()->query($sql);
		
		foreach($result as $key=>$row){
			$data[$row['id_product_sku']]	=	$row['quantity'];
		}

		return $data;
	}
	
	//获取采购单信息
	function get_purchase_in($id_purchasein){
			
			$purchase_info = M()->table("erp_purchase_in")->where(" id_purchasein = '".$id_purchasein."' ")->find();
			return $purchase_info;
	}
	

	//检查入库后库存是否更新,配单是否成功
	function check_quantity($id_purchasein,$old_quantity){
		$purchase_info	=	$this->get_purchase_in($id_purchasein);
		$new_quantity	=	$this->get_purchase_initem_quantity($id_purchasein);

		$isErrorQuantity = true;
		//先知处理已入库的
		if($purchase_info['status'] == 2){
			foreach($old_quantity as $id_product_sku=>$quantity){
				if($quantity == $new_quantity[$id_product_sku]){
					$isErrorQuantity	=	false;
					break;
				}
			}

			if($isErrorQuantity == false){
				//表示入库数量没更新,
				//通过erp_purchase_initem 表中的 received_curr 中数量更新库存 
				$purchase_initem = M()->table("erp_purchase_initem")->where(" id_purchasein = '".$id_purchasein."' ")->select();
				//获取现有产品的在途量 ,进行判断,小于入库数量.在途量设置为0 ,
				$id_product_sku = array_column($purchase_initem,"id_product_sku");
				$id_product_sku_road_num	=	M()->table("erp_warehouse_product")->where(" id_product_sku IN ('".$itemrow['id_product']."') ")->select();
				foreach($id_product_sku_road_num as $key=>$roadrow){
					$road_arr[$roadrow['id_product_sku']] = $roadrow['road_num'];
				}
				//循环更新采购单入库数量异常的问题
				foreach($purchase_initem as $key=>$itemrow){
					$update_data['quantity'] = ['exp', 'quantity+' . $itemrow['received_curr']];
					//进行判断,小于入库数量.在途量设置为0 ,
					if($road_arr[$itemrow['id_product_sku']] < $itemrow['received_curr']){
						$road_num = 0;
					}else{
						$road_num = $road_arr[$itemrow['id_product_sku']] - $itemrow['received_curr'];
					}	
					$update_data['road_num'] = $road_num;

					M()->table("erp_warehouse_product")->where(" id_product = '".$itemrow['id_product']."' AND id_product_sku = '".$itemrow['id_product_sku']."'  ")->setField($update_data);
				}

				$update_new_quantity	=	$this->get_purchase_initem_quantity($id_purchasein);
				//记录前后入库的数量
				$quantity_record['id_purchasein'] 	= $id_purchasein;
				$quantity_record['old_quantity'] 	= json_encode($old_quantity);		
				$quantity_record['new_quantity'] 	= json_encode($new_quantity);
				$quantity_record['create_time'] 	= time();			
				M()->table("erp_purchase_in_quantity_recode")->add($quantity_record);
			}
			//入库前库存跟入库后库存相等,则回滚到原来状态重新提交
			/*if($isErrorQuantity == false){
				//更新采购单状态为未入库
				$in_data['status'] = 1;
				$in_data['total'] = 0;
				$in_data['total_received'] = 0;

				M()->table("erp_purchase_in")->where(" id_purchasein = '".$id_purchasein."' ")->save($in_data);

				//更新采购单SKU入库数量
				$item_data['received']	=	0;
				$item_data['received_true']	=	0;
				M()->table("erp_purchase_initem")->where(" id_purchasein = '".$id_purchasein."'  ")->save($item_data);
				
				//删除入库记录
				M()->table("erp_storage_ftp")->where(" docno = '".$id_purchasein."'  AND  billtype = '采购单入库' ")->delete();
				
				return false;
			} */


		}

		return true;

	}

	//更新产品在途量,到多少入多少, jiangqinqing 20171127
	function updateProductRoadNum($purchase_id,$purchase_initem,$id_product_sku_road_num){

		if(empty($purchase_id)){
			return false;
		}
		//获取现有产品的在途量,进行判断,小于入库数量.在途量设置为0,
		foreach($id_product_sku_road_num as $rkey=>$roadrow){
			$id_products[$roadrow['id_product']] = $roadrow['id_product'];
			$road_arr[$roadrow['id_product_sku']] =	($roadrow['road_num'] < 0) ? 0 : $roadrow['road_num'];
		}
					
		//更新在途量数据
		foreach($purchase_initem as $key=>$row){
			//现有在途量减掉入库数量.
			$road_num = $road_arr[$row['id_product_sku']] - $row['received'];
			//避免仓库多入出现负数的现象,
			$road_data['road_num'] = ($road_num < 0) ? 0 : $road_num;
			$road_record[$row['id_product_sku']] 	= $road_num;
			$purchase_record[$row['id_product_sku']] 	= $row['quantity'];	
			$received_num[$row['id_product_sku']] 	= $row['received'];
			//更新在途量
			M()->table("erp_warehouse_product")->where(" id_product_sku = '".$row['id_product_sku']."'  ")->save($road_data);
		}

		//记录在途量更新前后数据
		$roadnum_record['id_purchasein']= $purchase_id;
		$roadnum_record['id_product'] 	= json_encode($id_products);		
		$roadnum_record['old_roadnum'] 	= json_encode($road_arr);		
		$roadnum_record['received_num'] = json_encode($received_num);
		$roadnum_record['quantity_num'] = json_encode($purchase_record);		
		$roadnum_record['new_roadnum'] 	= json_encode($road_record);
		$roadnum_record['create_time'] 	= time();			
		M()->table("erp_purchase_in_roadnum_record")->add($roadnum_record);
	}


}
