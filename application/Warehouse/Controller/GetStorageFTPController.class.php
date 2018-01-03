<?php
namespace Warehouse\Controller;
use Common\Controller\AdminbaseController;

class GetStorageFTPController extends AdminbaseController{
	public function _initialize() {
        parent::_initialize();
        $this->Com_storage_ftp = D("Common/storage_ftp_total");
        $this->storage_ftp = D("Common/storage_ftp");
       }
      /**
      * 查询日进销存  放入一个新的表 Com_storage_ftp   --Lily 2017-11-14
      */
      public function getStorageFTP(){
        if(isset($_GET['yesterday']) && $_GET['yesterday'] == "yesterday"){
          $where['_string'] = "billdate >= '".date("Y-m-d",strtotime("-1 days"))." 00:00:00' AND billdate <='".date("Y-m-d",strtotime("-1 days"))." 23:59:59'";
        }else{
          $billdate = empty($_GET['billdate'])?"2017-09-04":$_GET['billdate'];
          $where["_string"] = " billdate >= '".$billdate." 00:00:00' AND billdate<= '".$billdate." 23:59:59'";
        }
        $where['qtychange'] = array("NEQ",'0');
        $list = $this->storage_ftp->alias("sf")
      			->join("__WAREHOUSE__ AS w ON w.id_warehouse=sf.id_warehose","LEFT")
      			->join("__PRODUCT_SKU__ AS pk ON sf.id_product_sku=pk.id_product_sku","LEFT")
      			->join("__PRODUCT__ AS p ON sf.id_product=p.id_product","LEFT")
      			->join("__USERS__ AS u ON sf.id_users=u.id","LEFT")
      			->join("__DEPARTMENT__ AS dt ON p.id_department=dt.id_department","LEFT")
      			->field("sf.id,sf.docno,sf.billtype,sf.billdate,sf.qtychange,sf.amtchange,sf.qty_alloc,w.title as wtitle,pk.sku,pk.title as sku_title,p.inner_name,p.title,u.user_nicename,dt.id_department,dt.title as dt_title,sf.id_users")
      			->where($where)
      			->select();
                // echo $this->storage_ftp->getLastSql();
      	if($list){
      		$data = [];
      		foreach($list as $k=>$v){
      			$data[$k]['former_storage_ftp_id'] = $v['id'];
      			$data[$k]['docno'] = $v['docno'];
      			$data[$k]['billtype'] = $v['billtype'];
      			$data[$k]['billdate'] = $v['billdate'];
      			$data[$k]['qtychange'] = $v['qtychange'];
      			$data[$k]['amtchange'] = $v['amtchange'];
      			$data[$k]['qty_alloc'] = $v['qty_alloc'];
      			$data[$k]['wtitle'] = $v['wtitle'];
      			$data[$k]['sku'] = $v['sku'];
      			$data[$k]['sku_title'] = $v['sku_title'];
      			$data[$k]['title'] = $v['title'];
      			$data[$k]['inner_name'] = $v['inner_name'];
      			$data[$k]['user_nicename'] = $v['user_nicename'];
      			$data[$k]['id_users'] = $v['id_users'];
      			$data[$k]['create_time'] = date("Y-m-d H:i:s");
      			$data[$k]['id_department'] = $v['id_department'];
      			$ret = $this->Com_storage_ftp->add($data[$k],array(),true);
            if($ret){
      				$message = $v['id']."于".date("Y-m-d H:i:s")."同步记录成功";
      			}else{
      				$message = $v['id']."于".date("Y-m-d H:i:s")."同步记录失败";
      			}
      			$path = write_file('GetStorageFTPController','getStorageFTP',$message);
      		}
      	}
        if(isset($_GET['yesterday']) && $_GET['yesterday'] == "yesterday"){
          echo "昨天的数据已同步结束";
        }else{
          if(strtotime($billdate)<strtotime(date("Y-m-d",strtotime("+1 day")))){
          $billdate = date("Y-m-d",strtotime("+1 day",strtotime($billdate)));
          echo "<script type='text/javascript'>";
          echo " window.location.href='http://erp.msiela.com:90/Warehouse/GetStorageFTP/getStorageFTP?billdate=".$billdate."'";
          echo "</script>";
        }else{
          echo "同步全部数据的脚本执行结束";
        }
        }
        
       }
}
?>