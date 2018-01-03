<?php
namespace Warehouse\Controller;
use Common\Controller\AdminbaseController;
use Common\Lib\Procedure;
use Order\Model\UpdateStatusModel;

class TransferController extends AdminbaseController {

		private $status	= array(
			'producting'=>array(	/*未提交/已提交*/
				0=>array('t.status'=>array('IN',array(1,2))),
				1=>array('t.status'=>array('EQ',1)),
				2=>array('t.status'=>array('EQ',2)),
				'cnt'=>'`qty`'
			),	
			'output'=>array(	/*未出库/已出库*/
				0=>array('t.status'=>2,'t.outstatus'=>array('IN',array(1,2))),
				1=>array('t.status'=>2,'t.outstatus'=>array('EQ',1)),
				2=>array('t.status'=>2,'t.outstatus'=>array('EQ',2)),
				'cnt'=>'`qty_out`'
			),
			'input'=>array(	/*未入库/已入库*/
				0=>array('t.status'=>2,'t.instatus'=>array('IN',array(1,2))),
				1=>array('t.status'=>2,'t.instatus'=>array('EQ',1)),
				2=>array('t.status'=>2,'t.instatus'=>array('EQ',2)),
				'cnt'=>'`qty_in`'
			)
		);

    public function _initialize() {
    	
    	parent::_initialize();
    }    

    /*
     * 调拨单列表
     */
    protected function transfer_list($status, $transfer_id=0) {
    	
    	$where 	= $transfer_id>0?array():$this->status[$status][1];
    	$pcount	=	20;			/*默认每页显示*/
    	if (IS_GET) 
    	{
    		
    			('0'==I('get.status2')||I('get.status2')) 					&& $where 												= $this->status[$status][I('get.status2')];		/*单据状态*/
    			I('get.docno') 						&& $where['t.docno'] 							= array('LIKE', '%'.I('get.docno').'%');			/*单据编号*/
    			I('get.c_orig_id') 				&& $where['t.c_orig_id'] 					= array('EQ', I('get.c_orig_id'));						/*发货仓库*/
    			I('get.c_dest_id') 				&& $where['t.c_dest_id'] 					= array('EQ', I('get.c_dest_id'));						/*收货仓库*/
    			I('get.logistics') 				&& $where['t.logistics'] 					= array('LIKE', '%'.I('get.logistics').'%');	/*物流公司*/
    			I('get.logistics_docno') 	&& $where['t.logistics_docno'] 		= array('EQ', I('get.logistics_docno'));			/*物流单号*/    			
    			
    			$pcount2 = intval(I('get.pcount'));
    			if($pcount2>0)$pcount = $pcount2;
    	}
    	
    	$model = new \Think\Model();
    	$transfer_table_name 			= M('Transfer')->getTableName();
    	$transferitem_table_name 	= M('Transferitem')->getTableName();
    	
    	if($transfer_id>0)
    	{
    		$where['t.id'] = $transfer_id;
    		$count = $model->table($transfer_table_name . " t")
      									->join("{$transferitem_table_name} ti ON (t.id = ti.transfer_id)", 'RIGHT')
      									->where($where)
      									->count();
	      $page = $this->page($count, $pcount);
	      $list = $model->table($transfer_table_name . " t")
	      										->join("{$transferitem_table_name} ti ON (t.id = ti.transfer_id)", 'RIGHT')
	      										->where($where)
	                					->order("t.id DESC")
	                					->limit($page->firstRow, $page->listRows)
	                					->select();	
    	}
    	else
    	{
    		$count = $model->table($transfer_table_name . " t")->where($where)->count();
	      $page = $this->page($count, $pcount);
	      $list = $model->table($transfer_table_name . " t")->where($where)
	                					->order("t.id DESC")
	                					->limit($page->firstRow, $page->listRows)
	                					->select();		
    	}
      
              					
      if(!empty($list))    
      {
      	
      	foreach ($list as $k => &$v) 
      	{
      		/**产品信息**/
      		$pro_info	=	($transfer_id>0)?$this->get_pro_info($v['id_product'], $v['id_product_sku']):array();
      		$v				=	array_merge($v, $pro_info);
      		
      		/**制单/提交/出库/入库人**/
      		$nicenames_id = array();
      		!empty($v['owner_id']) 		&& $nicenames_id[] = $v['owner_id'];
      		!empty($v['statuser_id']) && $nicenames_id[] = $v['statuser_id'];
      		!empty($v['outer_id']) 		&& $nicenames_id[] = $v['outer_id'];
      		!empty($v['iner_id']) 		&& $nicenames_id[] = $v['iner_id'];
      		$nicenames	=M('Users')->field('id,user_nicename')->where(array('id' => array('IN',$nicenames_id)))->select();
      		$nicenames 	= array_column($nicenames, 'user_nicename', 'id');
      		$v['owner_id_nicename'] 		= isset($nicenames[$v['owner_id']]) ? $nicenames[$v['owner_id']] : '';
      		$v['statuser_id_nicename'] 	= isset($nicenames[$v['statuser_id']]) ? $nicenames[$v['statuser_id']] : '';
      		$v['outer_id_nicename'] 		= isset($nicenames[$v['outer_id']]) ? $nicenames[$v['outer_id']] : '';
      		$v['iner_id_nicename'] 			= isset($nicenames[$v['iner_id']]) ? $nicenames[$v['iner_id']] : '';
      		
      		/**发货/收货仓库**/      		
      		$v['c_orig_id_ware_name'] = !empty($v['c_orig_id']) ? M('Warehouse')->where(array('id_warehouse' => $v['c_orig_id']))->getField('title') : '';
      		$v['c_dest_id_ware_name'] = !empty($v['c_dest_id']) ? M('Warehouse')->where(array('id_warehouse' => $v['c_dest_id']))->getField('title') : '';
      		
      		/*总量统计*/
      		if(!$transfer_id)
      		{
      			$status_cnt = array();
      			$cnt	= M('Transferitem')->where(array('transfer_id'=>$v['id']))->getField("SUM({$this->status[$status]['cnt']}) AS qty");
      			$v['cnt']	= empty($cnt)?0:$cnt;
      		}
        }
      }
    	
    	/*非转寄仓库*/
    	$warehouses  = D('Warehouse/Warehouse')->field('id_warehouse, title')->where(array('status'=>1,'forward'=>0))->cache(true,3600)->select();
    	
    	
    	$this->assign('warehouses', $warehouses);
    	$this->assign('list', 			$list);
    	$this->assign("page", 			$page->show('Admin'));
    	$this->assign("pcount", 			$pcount);

    }
    
    /**
     * 未提交/已提交调拨单
     */
    public function producting() {
        
        $this->transfer_list('producting');
        $this->display();
    }
    
    /**
     * 未出库/已出库调拨单
     */
    public function output() {
        
        $this->transfer_list('output');
        $this->display();
    }
    
    /**
     * 未入库/已入库调拨单
     */
    public function input() {
        
        $this->transfer_list('input');
        $this->display();
    }    
    
    /**
     * 提交调拨单
     */
    public function submit2() {
        if(IS_AJAX) {
            try {
            	
            	$transfer_ids = I('post.transfer_id');
              $message = array();
              
            	if(!empty($transfer_ids))
            	{
            		$model = new \Think\Model;
            		$transfer_table_name 			= M('Transfer')->getTableName();
    						$transferitem_table_name 	= M('Transferitem')->getTableName();
    						$transfer_submit 					= array();
    						
    						$transfer_list = M('Transfer')->where(array('id'=>array('IN',$transfer_ids)))->select();
    						$transfer_list 	= array_column($transfer_list, 'docno', 'id');
    						
            		foreach($transfer_ids as $transfer_id)
            		{
            			$transfers = $model->table($transfer_table_name . ' as t RIGHT JOIN ' . $transferitem_table_name . ' as ti ON (t.id = ti.transfer_id)')->field('t.docno,t.c_orig_id,ti.id_product,ti.id_product_sku,ti.qty,ti.id,ti.transfer_id')->where(array('ti.transfer_id'=>array('EQ',$transfer_id)))->select();
            			
            			if(!empty($transfers))
            			{
            				
            				/*$erromsg = $model->query('call ERP_BILLINOUT_SUBMIT('.$transfer_id.', '.sp_get_current_admin_id().', "erp_transfer", "O", @erromsg)', true);
            				var_dump($erromsg);exit();*/
            				$erromsg = Procedure::call('ERP_BILLINOUT_SUBMIT', array(
            										'billid'=>$transfer_id,
            										'userid'=>sp_get_current_admin_id(),
            										'tablename'=>'erp_transfer',
            										'inor'=>'O',
            									));
            				if(!empty($erromsg[$transfer_id]))
            					$message[] = '单据编号:'.$transfer_list[$transfer_id].'  '.$erromsg[$transfer_id];
            				else
            					$message[] = '单据编号:'.$transfer_list[$transfer_id].'  提交成功';
            					
            				continue;
            				
            				
            				
            				foreach($transfers as $transfer)
            				{
            					/*仓库库存*/
	            				$wp = M('WarehouseProduct')->where(array('id_warehouse'=>array('EQ',$transfer['c_orig_id']),'id_product'=>array('EQ',$transfer['id_product']),'id_product_sku'=>array('EQ',$transfer['id_product_sku'])))->find();
	            				
	            				$sku_info 	= M('ProductSku')->where(array('id_product_sku'=>array('EQ',$transfer['id_product_sku'])))->getField('sku');	
	            				
	            				if(empty($wp))
	            				{
	            					$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：无产品信息');	
	            					continue;
	            				}
	            				else
	            				{
	            						/*是否有可用库存*/
	            						if($wp['quantity']-$wp['qty_preout']+$wp['road_num']<$transfer['qty'])
	            						{
	            							$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：无可用库存');	
	            							continue;	
	            						}
	            						
	            						$transfer_submit[] = array(
	            							'docno' => $transfer['docno'],
	            							'id_warehouse_product' => $wp['id_warehouse_product'],
	            							'qty' => $transfer['qty'],
	            							'transfer_id' => $transfer['transfer_id'],
	            							'sku' => $sku_info,
	            							'qty_preout' => $wp['qty_preout'],
	            						);
	            				}
            				}
            			}
            			else
            			{
            				$message[] 	= ('单据编号:'.$transfer_list[$transfer_id].'  无调拨明细');	
	            			continue;		
            			}
            		}
            		
            		if(empty($message)&&!empty($transfer_submit))
            		{
            			foreach($transfer_submit as $v)	
            			{
            				/*锁定库存*/
	            						$lock_q = M('WarehouseProduct')->where(array('id_warehouse_product'=>array('EQ',$v['id_warehouse_product'])))->save(array('qty_preout'=>$v['qty_preout']+$v['qty']));
	            						if($lock_q !== false)
	            						{
	            								if (M('Transfer')->where(array('id'=>array('EQ',$v['transfer_id'])))->save(array('status'=>2,'statuser_id'=>sp_get_current_admin_id(),'status_time'=>date('Y-m-d H:i:s'))) !== false)
	            								{
	            									$message[] = ('单据编号:'.$v['docno'].' SKU:'.$v['sku'].'：提交成功');
	            								}
	            								else
	            								{
	            									M('WarehouseProduct')->where(array('id_warehouse_product'=>array('EQ',$v['id_warehouse_product'])))->setDec('qty_preout',$v['qty']);	
	            									$message[] = ('单据编号:'.$v['docno'].' SKU:'.$v['sku'].'：提交失败');	
	            								}
	            						}	
            			}
            		}
            		
            	}
            } catch (\Exception $e) {
                
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('调拨单提交:'.is_array($message)?implode("\n",$message):$message));
            $return = array('message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }
    
    /**
     * 调拨单出库
     */
    public function submit2_output() {
        if(IS_AJAX) {
            try {
            	
            	$transfer_ids = I('post.transfer_id');
              $message = array();
              $status	 = 1;
              
            	if(!empty($transfer_ids))
            	{
            		$model = new \Think\Model;
            		$transfer_table_name 			= M('Transfer')->getTableName();
    						$transferitem_table_name 	= M('Transferitem')->getTableName();
    						$transfer_list = M('Transfer')->where(array('id'=>array('IN',$transfer_ids)))->select();
    						$transfer_list 	= array_column($transfer_list, 'docno', 'id');
            		foreach($transfer_ids as $transfer_id)
            		{
            			$transfers = $model->table($transfer_table_name . ' as t RIGHT JOIN ' . $transferitem_table_name . ' as ti ON (t.id = ti.transfer_id)')->field('t.docno,t.c_orig_id,t.c_dest_id,ti.id_product,ti.id_product_sku,ti.qty,ti.transfer_id,ti.id')->where(array('ti.transfer_id'=>array('EQ',$transfer_id)))->select();
            			
            			if(!empty($transfers))
            			{
            				
            				
            				$erromsg = Procedure::call('ERP_INOUT_SUBMIT', array(
            										'billid'=>$transfer_id,
            										'userid'=>sp_get_current_admin_id(),
            										'tablename'=>'erp_transfer',
            										'inor'=>'O',
            									));
            				if(!empty($erromsg[$transfer_id]))
            					$message[] = '单据编号:'.$transfer_list[$transfer_id].'  '.$erromsg[$transfer_id];
            				else
            					$message[] = '单据编号:'.$transfer_list[$transfer_id].'  出库成功';
            					
            				continue;
            				
            				
            				
            				foreach($transfers as $transfer)
            				{
            					/*仓库库存*/
	            				$wp = M('WarehouseProduct')->where(array('id_warehouse'=>array('EQ',$transfer['c_orig_id']),'id_product'=>array('EQ',$transfer['id_product']),'id_product_sku'=>array('EQ',$transfer['id_product_sku'])))->find();	
	            				
	            				$sku_info 	= M('ProductSku')->where(array('id_product_sku'=>array('EQ',$transfer['id_product_sku'])))->getField('sku');
	            				
	            				if(empty($wp))
	            				{
	            					$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：无产品信息');	
	            					$status	 		= 0;
	            					continue;
	            				}
	            				else
	            				{
	            						/*发货仓库减在单减库存*/
	            						$des_q = M('WarehouseProduct')->where(array('id_warehouse_product'=>array('EQ',$wp['id_warehouse_product'])))->save(array('quantity'=>($wp['quantity']-$transfer['qty']), 'qty_preout'=>($wp['qty_preout']-$transfer['qty'])));
	            						if($des_q !== false)
	            						{
	            							/*收货仓库加在途*/
	            							$des_q = M('WarehouseProduct')->where(array('id_warehouse'=>array('EQ',$transfer['c_dest_id']),'id_product'=>array('EQ',$transfer['id_product']),'id_product_sku'=>array('EQ',$transfer['id_product_sku'])))->setInc('road_num',$transfer['qty']);
	            							if($des_q !== false)
	            							{
	            								/*发货仓库记录库存进销存*/	
	            								$q = array(
	            									'id_warehose'=>$transfer['c_orig_id'],
	            									'id_product'=>$transfer['id_product'],
	            									'docno'=>$transfer['docno'],
	            									'id_product_sku'=>$transfer['id_product_sku'],
	            									'billtype'=>'调拨',
	            									'id_users'=>sp_get_current_admin_id(),
	            									'billdate'=>date('Y-m-d H:i:s'),
	            									'qtychange'=>-$transfer['qty'],
	            								);
	            								$des_q = M('StorageFtp')->data($q)->add();
	            								if($des_q !== false)
	            								{
	            									
	            									M('Transfer')->where(array('id'=>array('EQ',$transfer['transfer_id'])))->save(array('outstatus'=>2,'outer_id'=>sp_get_current_admin_id(),'out_time'=>date('Y-m-d H:i:s')));
	            									M('Transferitem')->where(array('id'=>array('EQ',$transfer['id'])))->save(array('qty_out'=>$transfer['qty']));
	            									
	            									$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：出库成功');
					            					$status	 		= 1;		       										
					            					continue;
	            								}
	            								else
	            								{
	            									M('WarehouseProduct')->where(array('id_warehouse'=>array('EQ',$transfer['c_dest_id']),'id_product'=>array('EQ',$transfer['id_product']),'id_product_sku'=>array('EQ',$transfer['id_product_sku'])))->setDec('road_num',$transfer['qty']);	
	            									M('WarehouseProduct')->where(array('id_warehouse_product'=>array('EQ',$wp['id_warehouse_product'])))->setInc('qty_preout',$transfer['qty']);
	            									$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：发货仓库记录库存进销存失败');	
					            					$status	 		= 0;
					            					continue;
	            								}
	            							}
	            							else
		            						{
		            							M('WarehouseProduct')->where(array('id_warehouse_product'=>array('EQ',$wp['id_warehouse_product'])))->setInc('qty_preout',$transfer['qty']);
		            							$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：收货仓库加在途失败');	
						            			$status	 		= 0;
						            			continue;	
		            						}
	            						}
	            						else
	            						{
	            							$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：发货仓库减在单失败');	
						            		$status	 		= 0;
						            		continue;	
	            						}
	            				}	
            				}
            			}
            		}
            		
            	}
            } catch (\Exception $e) {
                
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('调拨单出库:'.is_array($message)?implode("\n",$message):$message));
            $return = array('status'=>$status,'message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }
    
    /**
     * 调拨单入库
     */
    public function submit2_input() {
        if(IS_AJAX) {
            try {
            	
            	$transfer_ids = I('post.transfer_id');
              $message = array();
              $status	 = 1;
              
            	if(!empty($transfer_ids))
            	{
            		$model = new \Think\Model;
            		$transfer_table_name 			= M('Transfer')->getTableName();
    						$transferitem_table_name 	= M('Transferitem')->getTableName();
    						$transfer_list = M('Transfer')->where(array('id'=>array('IN',$transfer_ids)))->select();
    						$transfer_list 	= array_column($transfer_list, 'docno', 'id');
            		foreach($transfer_ids as $transfer_id)
            		{
            			$transfers = $model->table($transfer_table_name . ' as t RIGHT JOIN ' . $transferitem_table_name . ' as ti ON (t.id = ti.transfer_id)')->field('t.docno,t.c_dest_id,ti.id_product,ti.id_product_sku,ti.qty,ti.transfer_id,ti.id')->where(array('ti.transfer_id'=>array('EQ',$transfer_id)))->select();
            			
            			if(!empty($transfers))
            			{

							$sku_id = array_column($transfers,'id_product_sku');
            				$erromsg = Procedure::call('ERP_INOUT_SUBMIT', array(
            										'billid'=>$transfer_id,
            										'userid'=>sp_get_current_admin_id(),
            										'tablename'=>'erp_transfer',
            										'inor'=>'I',
            									));
            				if(!empty($erromsg[$transfer_id]))
            					$message[] = '单据编号:'.$transfer_list[$transfer_id].'  '.$erromsg[$transfer_id];
            				else
								UpdateStatusModel::get_short_order($sku_id);//匹配缺货订单
            					$message[] = '单据编号:'.$transfer_list[$transfer_id].'  入库成功';
            					
            				continue;
            				
            				
            				
            				foreach($transfers as $transfer)
            				{
            					/*仓库库存*/
	            				$wp = M('WarehouseProduct')->where(array('id_warehouse'=>array('EQ',$transfer['c_dest_id']),'id_product'=>array('EQ',$transfer['id_product']),'id_product_sku'=>array('EQ',$transfer['id_product_sku'])))->find();	
	            				
	            				$sku_info 	= M('ProductSku')->where(array('id_product_sku'=>array('EQ',$transfer['id_product_sku'])))->getField('sku');

	            				if(empty($wp))
	            				{
	            					$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：无产品信息');	
	            					$status	 		= 0;
	            					continue;
	            				}
	            				else
	            				{
	            						/*收货仓库加库存减在途*/
	            						$des_q = M('WarehouseProduct')->where(array('id_warehouse_product'=>array('EQ',$wp['id_warehouse_product'])))->save(array('quantity'=>$wp['quantity']+$transfer['qty'],'road_num'=>$wp['road_num']-$transfer['qty']));
	            						if($des_q !== false)
	            						{
	            							/*收货仓库记录库存进销存*/	
	            								$q = array(
	            									'id_warehose'=>$transfer['c_dest_id'],
	            									'id_product'=>$transfer['id_product'],
	            									'docno'=>$transfer['docno'],
	            									'id_product_sku'=>$transfer['id_product_sku'],
	            									'billtype'=>'调拨',
	            									'id_users'=>sp_get_current_admin_id(),
	            									'billdate'=>date('Y-m-d H:i:s'),
	            									'qtychange'=>$transfer['qty'],
	            								);
	            								$des_q = M('StorageFtp')->data($q)->add();
	            								if($des_q !== false)
	            								{
	            									
	            									M('Transfer')->where(array('id'=>array('EQ',$transfer['transfer_id'])))->save(array('instatus'=>2,'iner_id'=>sp_get_current_admin_id(),'in_time'=>date('Y-m-d H:i:s')));
	            									M('Transferitem')->where(array('id'=>array('EQ',$transfer['id'])))->save(array('qty_in'=>$transfer['qty']));
	            									
	            									$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：入库成功');	
					            					$status	 		= 1;         										
					            					continue;
	            								}
	            								else
	            								{
	            									M('WarehouseProduct')->where(array('id_warehouse_product'=>array('EQ',$wp['id_warehouse_product'])))->save(array('quantity'=>$wp['quantity']-$transfer['qty'],'road_num'=>$wp['road_num']+$transfer['qty']));
	            									
	            									$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：收货仓库记录库存进销存失败');
					            					$status	 		= 0;
					            					continue;
	            								}
	            						}
	            						else
	            						{
	            							$message[] 	= ('单据编号:'.$transfer['docno'].' SKU:'.$sku_info.'：收货仓库加库存减在途失败');
						            		$status	 		= 0;
						            		continue;	
	            						}
	            				}	
            				}
            			}
            		}
            		
            	}
            } catch (\Exception $e) {
                
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('调拨单出库:'.is_array($message)?implode("\n",$message):$message));
            $return = array('status'=>$status,'message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }
    
    /**
     * 查看调拨单页面
     */
    public function look() {
        
      $id 		= I('get.id');
    	$transfer = array();
    	if(!empty($id))
    	{
    		$this->transfer_list('producting', $id);
    		$transfer = M('Transfer')->where(array('id'=>$id))->find();
			}
      
      /*非转寄仓库*/
    	$warehouses  = D('Warehouse/Warehouse')->field('id_warehouse, title')->where(array('status'=>1,'forward'=>0))->cache(true,3600)->select();
    	
    	
    	$this->assign('warehouses', $warehouses);
    	$this->assign('id', $id);
    	$this->assign('transfer', $transfer);
      
    	$this->display();  
    }

    /**
     * 添加调拨单页面
     */
    public function add() 
    {
    	
    	$id 		= I('get.id');
    	$transfer = array();
    	if(!empty($id))
    	{
    		$this->transfer_list('producting', $id);
    		$transfer = M('Transfer')->where(array('id'=>$id))->find();
			}
      
      /*非转寄仓库*/
    	$warehouses  = D('Warehouse/Warehouse')->field('id_warehouse, title')->where(array('status'=>1,'forward'=>0))->cache(true,3600)->select();
    	
    	
    	$this->assign('warehouses', $warehouses);
    	$this->assign('id', $id);
    	$this->assign('transfer', $transfer);
      
    	$this->display();
    }
    
    /**
     * 搜索提示产品名称
     */
    public function get_product_title() {
        $keyword 		= I('post.value');
        $c_orig_id 	= I('post.c_orig_id');
        if (!empty($keyword) && !empty($c_orig_id)) {
        		$where = array();
            $where['p.inner_name'] = array('like', '%'.$keyword . '%');
            $where['wp.id_warehouse'] = array('EQ', $c_orig_id);
            $where['ps.status'] = 1;
            $product = M('WarehouseProduct')->alias('wp')->join('__PRODUCT__ p ON wp.id_product=p.id_product')->join('__PRODUCT_SKU__ ps ON wp.id_product_sku=ps.id_product_sku')->field('p.id_product,p.inner_name')->where($where)->select();
            if ($product) {
                $data = '<ul>';
                $product_ids = array();
                foreach ($product as $value) {
                	if(isset($product_ids[$value['id_product']]))continue;
                	$product_ids[$value['id_product']] = true;
                    $data .= '<li><a class="pro' . $value['id_product'] . '" href="javascript:;" onclick="get_pro_param(' . $value['id_product'] . ')" >' . $value['inner_name'] . '</a></li>';
                }
                unset($product_ids);
                $data .= '</ul>';
            } else {
                $data = 0;
            }
        } else {
            $data = 0;
        }
        echo $data;
    }
    
    /**
     * 生成产品属性
     */
    public function get_attr() {
        /** @var  $product \Common\Model\ProductModel */
        $id = I('post.product_id'); /*产品Id*/
        $transfer_id = I('post.transfer_id');/*调拨主表ID*/
        $warehouse_id = I('post.warehouse_id');/*发货仓库ID*/
        $pro_table_name = D("Common/Product")->getTableName();

        $model = new \Think\Model;

        $load_product = D("Common/Product")->find($id);
        $sku_where = array('id_product' => $id, 'status' => 1);
        $all_child_sku = D("Common/ProductSku")->where($sku_where)->select();
        
        $product_row = '<tr class="productBox' . $id . '"><td colspan="10" style="background-color: #f5f5f5;">' . $load_product['title'] . '</td></tr>
        <tr class="headings productBox' . $id . '"><th>SKU</th><th>属性</th><th>调拨数量</th><th>可用库存</th></tr>';
        
        /*调拨明细*/
        $transfer_items	=M('Transferitem')->where(array('transfer_id' => array('EQ',$transfer_id)))->getField('id_product_sku', true);

        foreach ($all_child_sku as $c_key => $c_item) {
        	
            		if(in_array($c_item['id_product_sku'], $transfer_items))continue;

                $set_qty = '0';
                
                $where['id_product'] = $id;
                $where['id_product_sku'] = $c_item['id_product_sku'];
                $wp_where['id_warehouse'] = $warehouse_id;
                /*可用库存*/
                $warehouse_pro = M('WarehouseProduct')->field('quantity,road_num,qty_preout')->where($where)->where($wp_where)->find();
                           
                $product_row .= '<tr class="productBox' . $id . '" data-sku-id="'.$c_item['sku'].'"><input type="hidden" value="' . $c_item['title'] . '" name="attr_name[' . $id . '][' . $c_item['id_product_sku'] . ']"/>' .
                        '<td>' . $c_item['sku'] . '</td> ' .
                        '<td>' . $c_item['title'] . '</td>' .
                        '<td><input type="text" class="sqt sqt' . $c_key . '" value="' . $set_qty . '" name="set_qty[' . $id . '][' . $c_item['id_product_sku'] . ']" onchange="qty_change(this)"/></td>' .
                        '<td>' . ($warehouse_pro['quantity']-$warehouse_pro['qty_preout']) . '</td>' .
                        '</tr>';
        }
        echo json_encode(array('status' => 1, 'row' => $product_row));
        exit();
    }
    
    /**
     * 添加调拨单
     */
    public function add_post() {
    	
			if (IS_POST) {
				$message = '';
				try
				{       
        $add_data['c_orig_id'] 							= I('post.c_orig_id');
        $add_data['c_dest_id'] 							= I('post.c_dest_id');
        $add_data['create_time'] 						= date('Y-m-d H:i:s');
        $add_data['docno'] 									= $this->get_docno();
        $add_data['logistics'] 							= I('post.logistics');
        $add_data['logistics_docno'] 				= I('post.logistics_docno');
        $add_data['description'] 						= I('post.description');
        $add_data['description_shipping'] 	= I('post.description_shipping');
        $add_data['owner_id'] 							= sp_get_current_admin_id();
        
        /*调拨主表*/
        $transfer_id = M("Transfer")->data($add_data)->add();
        $status = 1;
        } catch (\Exception $e) {
        	$status = 0;
        	$message = $e->getMessage();
        }
        if($status)
        {
        	add_system_record(sp_get_current_admin_id(), 1, 2, '添加调拨单主表成功');
        	$this->redirect(U('Transfer/add',array('id'=>$transfer_id)));	
        }
        else
        {
        	$this->error("保存失败");	
        }
      }
    }
    
    /**
     * 添加调拨单明细
     */
    /*public function add_transfer_item() {
    	
			if (IS_POST) {
				$message = '';
				try
				{
				$transfer_id = I('post.transfer_id');				
				$set_qty 		= array_filter($_POST['set_qty']);
        $product_id = I('post.product_id');
        $attr_name = I('post.attr_name');
        
        if (!empty($set_qty)) {
        	
        	$set_qty = $set_qty[$product_id];
        	$set_qty = array_filter($set_qty);
        	if (empty($set_qty)) {
            add_system_record(sp_get_current_admin_id(), 1, 2, '调拨单生成(保存行)失败');
            $this->error("保存失败,调拨数量不能都为空");
            exit;
          }
          
        	foreach($set_qty as $sku_id => $qty)
        	{
        		$array_data = array(
              'transfer_id' => $transfer_id,
              'id_product' => $product_id,
              'id_product_sku' => $sku_id,
              'option_value' => $attr_name[$product_id][$sku_id],
              'qty' => $qty
            );
            M("Transferitem")->data($array_data)->add();	
        	}
        }
        $status = 1;
      	} catch (\Exception $e) {
        	$status = 0;
        	$message = $e->getMessage();
        }
        
        if($status)
        {
        	add_system_record(sp_get_current_admin_id(), 1, 2, '调拨单生成(保存行)成功');
        	$this->redirect(U('Transfer/add',array('id'=>$transfer_id)));	
        }
        else
        {
        	$this->error("保存失败");	
        }
        
      }
    }*/
    public function add_transfer_item() {
    	
			if (IS_POST) {
				$message = '';
				try
				{
					
					$transfer_id 	= I('post.transfer_id');
					$warehouse_id 	= I('post.warehouse_id');	
					$sku_name 		= I('post.sku_name');
					$sku_qty 			= I('post.sku_qty');
					if($transfer_id>0&&!empty($sku_name)&&$sku_qty>0&&$warehouse_id>0)
					{
						$product_sku = M('ProductSku')->where(array('sku' => $sku_name, 'status' => 1))->find();
            if ($product_sku) {

							/*判断可用库存*/
							$ware_pro = M('WarehouseProduct')->field('id_product,quantity,qty_preout')->where(array('id_product_sku'=>$product_sku['id_product_sku'],'id_warehouse'=>$warehouse_id))->find();
							if(empty($ware_pro))
							{
								$message 	= "该SKU:{$sku_name} 在仓库不存在";
		            $status 	= 0;	
							}
							else
							{
								/*可用库存不足*/
			          if($sku_qty>($ware_pro['quantity']-$ware_pro['qty_preout'])) 
			          {
			            $message 	= "SKU:{$sku_name} 仓库可用库存不足";
			            $status 	= 0;
			          }
			          else
			          {
			          	$tmp_re = M('Transferitem')->where(array('transfer_id'=>$transfer_id,'id_product_sku'=>$product_sku['id_product_sku']))->find();
									if(!empty($tmp_re))
									{
										$message 	= "SKU:{$sku_name} 该记录已存在";
										$status 	= 0;
									}
			          	else
			          	{
			          		$array_data = array(
					              'transfer_id' => $transfer_id,
					              'id_product' => $product_sku['id_product'],
					              'id_product_sku' => $product_sku['id_product_sku'],
					              'option_value' => $product_sku['title'],
					              'qty' => $sku_qty
					           );
					           M("Transferitem")->data($array_data)->add();
					           $status = 1;		
			          	}
			          }	
							}							
            }
            else
            {
            	$status = 0;	
            	$message = "SKU:{$sku_name}不存在或者无效";
            }
					}
					
      	} catch (\Exception $e) {
        	$status = 0;
        	$message = $e->getMessage();
        }
        
        if($status)
        {
        	add_system_record(sp_get_current_admin_id(), 1, 2, '调拨单生成(保存行)成功');
        	$this->redirect(U('Transfer/add',array('id'=>$transfer_id)));	
        }
        else
        {
        	$this->error("保存失败 ({$message})", U('Transfer/add',array('id'=>$transfer_id)));	
        }
        
      }
    }
    
    /**
     * 编辑调拨单页面
     */
    public function edit() 
    {
        
      $id = I('get.id');
      /*调拨单主表*/
      $transfer = M('Transfer')->where(array('id'=>$id))->find();	
      /*调拨单详细表*/
      $transferitem = M('Transferitem')->where(array('id'=>$id))->select();
      $this->assign('transfer',$transfer);
      $this->assign('transferitem',$transferitem);
      $this->assign('id',$id);
      $this->display();
    }
    
    /**
     * 编辑调拨单
     */
    public function edit_post() 
    {
    	try{
       $transfer_id = I('post.transfer_id');	
       if($transfer_id>0)
       {
       	$add_data['c_orig_id'] 							= I('post.c_orig_id');
        $add_data['c_dest_id'] 							= I('post.c_dest_id');
        $add_data['logistics'] 							= I('post.logistics');
        $add_data['logistics_docno'] 				= I('post.logistics_docno');
        $add_data['description'] 						= I('post.description');
        $add_data['description_shipping'] 	= I('post.description_shipping');
                
        /*调拨单主表*/
        M('Transfer')->where(array('id'=>$transfer_id))->save($add_data);
        
        /*调拨单明细*/
        $set_qty 		= array_filter($_POST['set_qty']);
        if(!empty($set_qty))
        {
        	foreach($set_qty as $id=>$qty)
        	{
        		M('Transferitem')->where(array('id'=>$id))->save(array('qty'=>$qty));	
        	}	
        }
      }
        $status = 1;
      } catch (\Exception $e) {
        	$status = 0;
        	$message = $e->getMessage();
        }
        
        if($status)
        {
        	add_system_record(sp_get_current_admin_id(), 1, 2, '调拨单生产(修改明细)成功');
        	$this->redirect(U('Transfer/add',array('id'=>$transfer_id)));	
        }
        else
        {
        	$this->error("保存失败");	
        }    
    }

    /*
     * 删除调拨单
     */
    public function delete() {
        
      if(IS_AJAX) {
            try {
            	
            	$transfer_ids = I('post.transfer_id');
              $message = array();
              
            	if(!empty($transfer_ids))
            	{
            		
            		foreach($transfer_ids as $transfer_id)
            		{
            			M("Transfer")->where(array("id" => $transfer_id))->delete();
      						M("Transferitem")->where(array("transfer_id" => $transfer_id))->delete();
            		}
            		
            		$message = '作废成功';            		
            	}
            } catch (\Exception $e) {
                
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('调拨单作废:'.is_array($message)?implode("\n",$message):$message));
            $return = array('message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }
    
    /*
     * 提交调拨单
     */
    public function product2() {
        
        
    }
    
    /*
     * 获取产品、sku信息
     * @param int $pro_id 产品ID
     * @param int $pro_id 产品 sku ID
     * @param array $return
     */
    protected function get_pro_info($pro_id, $pro_sku_id) 
    {
    	
    	$return = array('s_inner_name'=>'', 's_sku'=>'');
    	$sku		=	M('ProductSku')->where(array('id_product_sku' => $pro_sku_id))->getField('sku');
    	if(!empty($sku))
    	{
    		$return['s_sku'] = $sku;	
    	}
    	$inner_name		=	M('Product')->where(array('id_product' => $pro_id))->getField('inner_name');
    	if(!empty($inner_name))
    	{
    		$return['s_inner_name'] = $inner_name;	
    	}
    	
    	return $return;
    }
    
    /*
     * 生成调拨单单据编号
     * @param array $return
     */
    protected function get_docno() 
    {
    	$Transfer = M('Transfer')->order(array('id' => 'desc'))
    						->field('docno')
                ->limit(1)
                ->find();	
      if(empty($Transfer))
      	return 'TF'.substr(date('Ymd'), 2).'0000001';
      else
      {
      	$Transfer = substr($Transfer['docno'],8);	
      	return 'TF'.substr(date('Ymd'), 2).str_pad((intval($Transfer)+1),7,'0',STR_PAD_LEFT);
      }
    }
    
    /**
     * 删除明细
     */
    public function del_transfer_item() {
        if(IS_AJAX) {
            try {
            	
            	$ids = I('post.ids');              
              
              if(!empty($ids))
              {
              	M('Transferitem')->where(array('id'=>array('IN',$ids)))->delete();	
              	$status	 = 1;
              	$message	 = '删除成功';
              }
              
            } catch (\Exception $e) {
                $status	 = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('调拨单生产修改明细(删除选中行):'.is_array($message)?implode("\n",$message):$message));
            $return = array('status'=>$status,'message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }
    
    /**
     * 导入sku
     */
    public function import() 
    {
    	
    	if(IS_AJAX) {
            try {
            	
            	$data 				= I('post.data');
            	$warehouse_id = I('post.warehouse_id');   
            	$transfer_id	= I('post.transfer_id'); 
            	$message			= array();        
              
              if($warehouse_id>0&&!empty($data))
              {
              	/*导入记录到文件*/
		            $path = write_file('transfer', 'import', $data);
		            $data = $this->getDataRow($data);
		            $count = 1;
		            /*调拨明细*/
        				$transfer_items	=M('Transferitem')->where(array('transfer_id' => array('EQ',$transfer_id)))->getField('id_product_sku', true);
		            foreach ($data as $row) {
		                $row = trim($row);
		                if (empty($row))
		                    continue;
		                    
		                $row = explode("\t", trim($row), 2);
		                if (count($row) != 2 || !$row[0]) {
		                    $message[] = sprintf('第%s行: 格式不正确', $count++);
		                    continue;
		                }
		                $sku = $row[0];/*sku*/
		                $stock = $row[1];/*调拨数量*/
		                $sku_result = M('ProductSku')->where(array('sku'=>$sku,'status'=>1))->find();
		                if($sku_result) {
		                	
		                		if(in_array($sku_result['id_product_sku'], $transfer_items))
		                		{
		                			$message[] = sprintf('第%s行: SKU:%s 明细中已存在', $count++, $sku);
		                      continue;	
		                		}
		                	
		                    $ware_pro = M('WarehouseProduct')->field('id_product,quantity,road_num,qty_preout')->where(array('id_product_sku'=>$sku_result['id_product_sku'],'id_warehouse'=>$warehouse_id))->find();
		                    if(empty($ware_pro)) {
		                      $message[] = sprintf('第%s行: SKU:%s 仓库不存在', $count++, $sku);
		                      continue;
		                    }
		                    if($stock>0) {
		                        
		                      /*可用库存不足*/
		                      if($stock>($ware_pro['quantity']-$ware_pro['qty_preout'])) 
		                      {
		                      	$message[] = sprintf('第%s行: SKU:%s 仓库可用库存不足', $count++, $sku);
		                      	continue;	
		                      } 
		                      /*录入调拨明细*/
		                      $data = array(
		                      	'transfer_id' 		=> $transfer_id,
		                      	'id_product' 			=> $ware_pro['id_product'],
		                      	'id_product_sku' 	=> $sku_result['id_product_sku'],
		                      	'option_value' 		=> $sku_result['title'],
		                      	'qty' 						=> $stock,
		                      );
		                      if(M("Transferitem")->data($data)->add())
		                      {
		                      	$message[] = sprintf('第%s行: SKU:%s 导入成功', $count++, $sku);	
		                      }
		                    }
		                } else {
		                    $message[] = sprintf('第%s行: SKU:%s 不存在或者隐藏了', $count++, $sku);
		                }
		            }
              }
              
            } catch (\Exception $e) {
                $status	 = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('调拨单生产修改明细(导入):'.is_array($message)?implode("\n",$message):$message));
            $return = array('message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }	
    }
    
    /*搜索sku页面*/
    public function search_sku() {
        $where =array();
        if(isset($_GET['inner_name'])&& $_GET['inner_name']){
            $where['p.inner_name'] = array('like','%'.$_GET['inner_name'].'%');
        }
        if(isset($_GET['sku'])&& $_GET['sku']){
            $where['ps.sku|ps.barcode'] = array('like','%'.$_GET['sku'].'%');
        }
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['p.id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['p.id_department']= $_GET['id_department'];
        }
        $where['ps.status'] = 1;// 使用的SKU状态
        $M = new \Think\Model;
        $pro_table = D("Common/Product")->getTableName();
        $pro_s_table = D("Common/ProductSku")->getTableName();
        $find_count = $M->table($pro_table.' AS p LEFT JOIN '.$pro_s_table.' AS ps ON p.id_product=ps.id_product')
            ->field('count(*) as count')->where($where)->find();
        $count= $find_count['count'];
        $page = $this->page($count,15);

        $proList = $M->table($pro_table.' AS p LEFT JOIN '.$pro_s_table.' AS ps ON p.id_product=ps.id_product')
            ->field('ps.sku,ps.barcode,ps.model,ps.option_value,ps.purchase_price,ps.weight,p.inner_name,p.id_product,p.thumbs,ps.id_product_sku')->where($where)
            ->order("p.id_product DESC")->limit($page->firstRow . ',' . $page->listRows)->select();

        $value_model  = D("Common/ProductOptionValue");
        if($proList && count($proList)){
            foreach($proList as $key=>$item){
                $option_value = $item['option_value'];
                if($option_value){
                    $get_value = $value_model->where('id_product_option_value in('.$option_value.')')->getField('title',true);
                    $proList[$key]['value'] = $get_value?implode('-',$get_value):'';
                    $proList[$key]['img'] = json_decode($item['thumbs'],true);
                }
            }
        }
        $department_id  = $_SESSION['department_id'];
        $department = D('Common/Department')->where('type=1')->cache(true,6000)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看SKU列表');
        $this->assign("department_id", $department_id);
        $this->assign('department',$department);
        $this->assign("proList",$proList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
    }
    
    /*ajax搜索sku*/
    public function ajax_get_sku() {
        if(IS_AJAX){
            $id = $_POST['id'];
            $sku = M('ProductSku')->where(array('id_product_sku'=>$id))->find();
            if($sku){
                $sku_name = $sku['sku'];
            } else {
                $sku_name = '';
            }
            echo json_encode(array($sku_name));die;
        }
    }
}
