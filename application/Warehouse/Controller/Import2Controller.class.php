<?php
namespace Warehouse\Controller;
use Common\Controller\AdminbaseController;
use Order\Lib\OrderStatus;

class Import2Controller extends AdminbaseController {

		private $status	= array(
		
				0=>array('t.status'=>array('IN',array(1,2))),
				1=>array('t.status'=>array('EQ',1)),
				2=>array('t.status'=>array('EQ',2))
		);
		
		private $type	= array(
		
				1=>array('name'=>'update_packaged', 'cols'=>array('track_number')),											/*运单号*/
				2=>array('name'=>'update_delivering', 'cols'=>array('track_number')),										/*运单号*/
				3=>array('name'=>'update_weight', 'cols'=>array('track_number','weight')),							/*运单号，重量*/
				4=>array('name'=>'update_out_stock', 'cols'=>array('track_number')),										/*运单号或者订单号，数据统一放运单号里*/
				5=>array('name'=>'update_track_number', 'cols'=>array('order_number','track_number')),	/*订单号,运单号*/
                6=>array('name'=>'update_shipping', 'cols'=>array('order_number')),	/*订单号*/
                7=>array('name'=>'update_to_warehouse', 'cols'=>array('track_number')),	/*订单号*/
                8=>array('name'=>'update_to_forward', 'cols'=>array('order_number')),	/*订单号*/

        );
		
		private $order_action = array(
				0=>'无',
    		6=>'缺货',
    		4=>'未配货',
    		5=>'配货中',
    		7=>'已配货',
    		8=>'配送中',
    	);

    public function _initialize() {
    	
    	parent::_initialize();
    }    

    /*
     * 列表
     */
    protected function import_list($type_id, $id=0) {
    	
    	$where 	= $id>0?array():$this->status[1];
    	
    	$pcount	=	20;			/*默认每页显示*/
    	if (IS_GET) 
    	{
    			if(!$id)
    			{
    				('0'==I('get.status2')||I('get.status2')) && $where 									= $this->status[I('get.status2')];		/*状态*/
    				$where['t.type'] 	= array('EQ', $this->type[$type_id]['name']);
	    			
	    			I('get.id_shipping') 											&& $where['t.id_shipping'] 	= array('EQ', I('get.id_shipping'));		/*物流ID*/
	    			I('get.order_number') 										&& $where['ti.order_number'] = array('EQ', I('get.order_number'));	/*订单号*/
	    			I('get.track_number') 										&& $where['ti.track_number'] = array('EQ', I('get.track_number'));	/*运单号*/
	    			('0'==I('get.order_action')||I('get.order_action')) 										&& $where['t.order_action'] = array('EQ', I('get.order_action'));		/*订单状态*/	
	    			I('get.docno') 														&& $where['t.docno'] = array('EQ', I('get.docno'));	/*单据编号*/
    			}
    			
    			$pcount2 = intval(I('get.pcount'));
    			if($pcount2>0)$pcount = $pcount2;
    	}
    	
    	$model = new \Think\Model();
    	$warehouse_import_table_name 			= M('WarehouseImport')->getTableName();
    	$warehouse_import_data_table_name 	= M('WarehouseImportData')->getTableName();
    	
    	if($id>0)
    	{
    		
    		$where['id_warehouse_import'] 	= array('EQ', $id);
    		$count = $model->table($warehouse_import_data_table_name)->where($where)->count();
	      $page = $this->page($count, $pcount);
	      $list = $model->table($warehouse_import_data_table_name)->where($where)
	                					->order("id DESC")
	                					->limit($page->firstRow, $page->listRows)
	                					->select();	
    	}
    	else
    	{
    			
	      $count = $model->table($warehouse_import_table_name . " t")
      									->join("{$warehouse_import_data_table_name} ti ON (t.id = ti.id_warehouse_import)", 'LEFT')
      									->field('t.*, t.id as id_warehouse_import2,ti.*,count(ti.id) as sum')
      									->where($where)
      									->order("t.id DESC")      									
      									->group('t.id')
      									->select();
	      $page = $this->page(count($count), $pcount);
	      $list = $model->table($warehouse_import_table_name . " t")
      									->join("{$warehouse_import_data_table_name} ti ON (t.id = ti.id_warehouse_import)", 'LEFT')
      									->field('t.*, t.id as id_warehouse_import2,ti.*,count(ti.id) as sum')
      									->where($where)
      									->order("t.id DESC")
      									->group('t.id')
	                			->limit($page->firstRow, $page->listRows)
	                			->select();	
    	}
              					
      if(!empty($list)&&!$id)    
      {
      	
      	foreach ($list as $k => &$v) 
      	{
      
      		$v['owner_id_nicename'] 		= !empty($v['owner_id']) ? M('Users')->where(array('id' => array('EQ',$v['owner_id'])))->getField('user_nicename') : '';
      		$v['statuser_id_nicename'] 	= !empty($v['statuser_id']) ? M('Users')->where(array('id' => array('EQ',$v['owner_id'])))->getField('user_nicename') : '';
      		if(5==$type_id)
      		{
      			$v['id_shipping_name'] = D('Common/Shipping')->where('id_shipping='.$v['id_shipping'])->getField('title');
      		}
      		elseif(4==$type_id)
      		{
      			$v['order_action_name'] = empty($v['order_action'])	? '' : $this->order_action[$v['order_action']];
      		}
            elseif(6==$type_id)
            {
                $v['id_shipping_name'] = D('Common/Shipping')->where('id_shipping='.$v['id_shipping'])->getField('title');
            }
            elseif(7==$type_id)
            {
//                $v['id_shipping_name'] = D('Common/Shipping')->where('id_shipping='.$v['id_shipping'])->getField('title');
            }
        }
      }
    	
    	$this->assign('list', 			$list);
    	$this->assign("page", 			$page->show('Admin'));
    	$this->assign("pcount", 			$pcount);
    	$this->assign("type_id", $type_id);

    }
    
    /**
     * 更新已打包
     */
    public function update_packaged() {
        
        $this->import_list(1);
        $this->display();
    }
    
    /**
     * 更新配送中
     */
    public function update_delivering() {
        
        $this->import_list(2);
        $this->display();
    }
    
    /**
     * 更新重量
     */
    public function update_weight() {
        
        $this->import_list(3);
        $this->display();
    }
    
    /**
     * 更新缺货
     */
    public function update_out_stock() {
        
        $this->import_list(4);
        
        $this->assign('order_action', $this->order_action);
        
        $this->display();
    }
    
    /**
     * 更新运单号
     */
    public function update_track_number() {
        
        $this->import_list(5);    
        
        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();	
      	$this->assign('shipping', $shipping);
            
        $this->display();
    }

    /**
     * 更新运单号
     */
    public function update_shipping() {

        $this->import_list(6);

        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
        $this->assign('shipping', $shipping);
        $this->display();
    }

    /**
     * 更新运单号
     */
    public function update_to_warehouse() {

        $this->import_list(7);
//        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
//        $this->assign('shipping', $shipping);
        $this->display();
    }


    /*
     * 更新匹配转寄
     */
    public function update_to_forward() {

        $this->import_list(8);
//        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
//        $this->assign('shipping', $shipping);
        $this->display();
    }
    /**
     * 明细
     */
    private function detail($type_id) {
        
      $id 				= I('get.id');
      
    	$import = array();
    	if(!empty($id))
    	{
    		$this->import_list($type_id, $id);
    		$import = M('WarehouseImport')->where(array('id'=>$id))->find();
			}
      
    	$this->assign('id', $id);
    	$this->assign('type_id', $type_id);
    	$this->assign('import', $import);
    	
    }
    
    /**
     * 明细
     */
    public function look() {
        
      $type_id 		= I('get.type_id');
      $this->detail($type_id);
      
      if(5==$type_id)
      {
      	$shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();	
      	$this->assign('shipping', $shipping);
      }
      elseif(4==$type_id)
      {
      	$this->assign('order_action', $this->order_action);	
      }
      
      $this->display($this->type[$type_id]['name'].'_look');
    }
    
    /**
     * 添加
     */
    public function add() {
        
      $type_id 		= I('get.type_id');
      $this->detail($type_id);
      if(6==$type_id)
      {
        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
        $this->assign('shipping', $shipping);
      }
      if(5==$type_id)
      {
      	$shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();	
      	$this->assign('shipping', $shipping);
      }
      elseif(4==$type_id)
      {
      	$this->assign('order_action', $this->order_action);	
      }
        elseif(7==$type_id)
        {
            $where['status'] = 1;
            if(count($belong_ware_id) != 1 || (count($belong_ware_id) == 1 && $belong_ware_id[0] != 1)) {
                //$where['id_warehouse'] = array('IN',$belong_ware_id);
            }
            $where['forward'] = 1;
            $warehouse = M('Warehouse')->field('id_warehouse,title')->where($where)->select();
            $warehouse = array_column($warehouse, 'title', 'id_warehouse');
            $this->assign('warehouse', $warehouse);
        }
    	$this->display($this->type[$type_id]['name'].'_add');
    }
    
    /**
     * 添加
     */
    public function add_post() {
//        echo json_encode($_POST);die;
			if (IS_AJAX) {
				$message = array();
				try
				{
	        $type_id							= I('post.type_id');    
	        $isimport 						= I('post.isimport');
	        $data 								= I('post.data');
	        $data_tmp							= array();
	        $id_warehouse_import	= I('post.id_warehouse_import');
	        if(!empty($data))
	        	{
	        		$data 	= $this->getDataRow($data);	
	        		$count 	= 1;
	        		foreach ($data as $k=>$row) 
	        		{
	        			$row = trim($row);
			          if (empty($row))continue;
			          
			          $row = explode("\t", trim($row));
			          
			          if (count($row) != count($this->type[$type_id]['cols']) || !$row[0]) {
			            $message[] = sprintf('第%s行: 格式不正确', $count++);
			            continue;
			          }          
			          $data_tmp[$k] = array();

			          foreach($this->type[$type_id]['cols'] as $k2=>$v)$data_tmp[$k][$v] = $row[$k2]; 
	        		}
	        	}
	        unset($data);
	        if($id_warehouse_import>0)
	        {
	        	/*重复处理*/
						foreach($data_tmp as $v2)
						{
							$where = array('id_warehouse_import'=>array('EQ',$id_warehouse_import));
							$kmsg  = '';
							foreach($v2 as $k=>$i)	
							{
								$where[$k] = array('EQ',$i);
								$kmsg .= ($i.' ');
							}
							$tmp_re = M('WarehouseImportData')->where($where)->find();
							if(!empty($tmp_re))
							{
								$message[] = ('记录已存在:'.$kmsg);	
							}
						}
	        }
	        
	        if(!empty($message))
	        {
	        	$return = array('status' => 0, 'id' => $id_warehouse_import, 'message' => (is_array($message)?implode("\n",$message):$message));
	          echo json_encode($return);exit();	
	        }
	        	        
	        $save_data = array();
	        if(!$isimport)
	        {
	        	I('post.description') 	&& $save_data['description'] 	= I('post.description');
		        I('post.id_shipping') 	&& $save_data['id_shipping'] 	= I('post.id_shipping');
		        I('post.order_action') 	&& $save_data['order_action'] = I('post.order_action');
                I('post.warehouse_id') 	&& $save_data['warehouse_id'] = I('post.warehouse_id');
                I('post.description') 	&& $save_data['description'] = I('post.description');
            }
	        else
	        {
	        	if(5==$type_id)
	        		I('post.id_shipping') 		&& $save_data['id_shipping'] 	= I('post.id_shipping');
	        	elseif(4==$type_id)
	        		I('post.order_action') 	&& $save_data['order_action'] 	= I('post.order_action');
                elseif(6==$type_id)
                {
                    I('post.id_shipping') 	&& $save_data['id_shipping'] 	= I('post.id_shipping');
                    I('post.description') 	&& $save_data['description'] 	= I('post.description');
                }
                elseif(7==$type_id)
                {
                    I('post.warehouse_id') 	&& $save_data['warehouse_id'] 	= I('post.warehouse_id');
                    I('post.description') 	&& $save_data['description'] 	= I('post.description');
                }
                elseif(8==$type_id)
                    I('post.description') 	&& $save_data['description'] 	= I('post.description');
            }

	        if($id_warehouse_import>0)
	        {
	        	M('WarehouseImport')->where(array('id'=>array('EQ',$id_warehouse_import)))->save($save_data);	
	        }
	        else
	        {
		        $save_data['type'] 				= $this->type[$type_id]['name'];
		        $save_data['owner_id'] 		= sp_get_current_admin_id();
		        $save_data['create_time'] = date('Y-m-d H:i:s');	
		        $save_data['docno'] 			= $this->get_docno();
		        $id_warehouse_import = M('WarehouseImport')->data($save_data)->add();
	        }
	        if(!empty($data_tmp))
	        {
	        	foreach($data_tmp as $v)
	        	{
	        			        		
	        		$v['id_warehouse_import'] = $id_warehouse_import;
	        		M('WarehouseImportData')->data($v)->add();	
	        	}	
	        }
	        
	        $message 	= $isimport?'导入成功':'保存成功';
	        $status 	= 1;
        
        } catch (\Exception $e) {
        	$status 	= 0;
        	$message = $e->getMessage();
        }
        add_system_record($_SESSION['ADMIN_ID'], 2, 3, ("出库管理导入保存({$this->type[$type_id]['name']}):".is_array($message)?implode("\n",$message):$message));
        $return = array('status' => $status, 'id' => $id_warehouse_import, 'message' => (is_array($message)?implode("\n",$message):$message));
        echo json_encode($return);exit();
      }
    } 
    
    /**
     * 删除明细
     */
    public function del_item() {
        if(IS_AJAX) {
        		
            try {
            	
            	$ids 			= I('post.ids');       
            	$type_id	= I('post.type_id');       
              
              if(!empty($ids))
              {
              	M('WarehouseImportData')->where(array('id'=>array('IN',$ids)))->delete();	
              	$status	 = 1;
              	$message	 = '删除成功';
              }
              
            } catch (\Exception $e) {
                $status	 = 0;
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ("出库管理导入删除明细({$this->type[$type_id]['name']}):".is_array($message)?implode("\n",$message):$message));
            $return = array('status'=>$status,'message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }
    
    /*
     * 删除
     */
    public function delete() {
        
      if(IS_AJAX) {
            try {
            	
            	$id_warehouse_import = I('post.id_warehouse_import');
              $message = array();
              
            	if(!empty($id_warehouse_import))
            	{
            		
            		foreach($id_warehouse_import as $id)
            		{
            			M("WarehouseImport")->where(array("id" => $id))->delete();
      						M("WarehouseImportData")->where(array("id_warehouse_import" => $id))->delete();
            		}
            		
            		$message = '作废成功';            		
            	}
            } catch (\Exception $e) {
                
                $message = $e->getMessage();
            }
            add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('出库导入作废:'.is_array($message)?implode("\n",$message):$message));
            $return = array('message' => (is_array($message)?implode("\n",$message):$message));
            echo json_encode($return);exit();
        }
    }
    
    /**
     * 提交
     */
    public function submit()
    {
        
        if (IS_AJAX) {
        	
        	$message = array();
        	try {
            
            	$id_warehouse_import = I('post.id_warehouse_import');
              
            	if(!empty($id_warehouse_import))
            	{
            		
            		foreach($id_warehouse_import as $id)
            		{
            			$import 			= M('WarehouseImport')->where(array('id'=>$id))->find();
      						$import_data 	= M("WarehouseImportData")->where(array("id_warehouse_import" => $id))->select();
      						if(!empty($import))
      						{
      							$type_id = $this->get_type_id($import['type']);
      							$submit	 = $import['type'].'_submit';
      							$import_data_tmp = array();
      							if(!empty($import_data))
      							{
      								foreach($import_data as $k=>$v)
      								{
      									$import_data_tmp[$k] = array();
      									foreach($this->type[$type_id]['cols'] as $col)
      									{
      										if(isset($v[$col]))$import_data_tmp[$k][] = $v[$col];	
      									}	
      								}
      								
      								$info = $this->$submit($import,$import_data_tmp);
      								if($info['status']==1)
      								{
      									M('WarehouseImport')->where(array('id'=>array('EQ',$id)))->save(array('status'=>2,'statuser_id'=>sp_get_current_admin_id(),'status_time'=>date('Y-m-d H:i:s')));
      									$message[] = "单据编号：{$import['docno']}提交成功";	
      								}
      								else
      								{
      									$message[] = $info['message'];	
      								}
      							}
      							else
      							{
      								$message[] = "单据编号：{$import['docno']}明细为空";	
      							}
      							
      						}
      						
            		}  		
            	}
            	
          } catch (\Exception $e) {
                
            $message = $e->getMessage();
          }
          
          add_system_record($_SESSION['ADMIN_ID'], 2, 3, ('更新已打包提交:'.is_array($message)?implode("\n",$message):$message));
          $return = array('message' => (is_array($message)?implode("\n",$message):$message));
          echo json_encode($return);exit();
        }
    }
    
    /**
     * 提交（更新已打包）
     */
    private function update_packaged_submit($import,$import_data_tmp)
    {
    	
    	/*检查*/
    	$info = array(
            'status' => 0,
            'message' => ''
        );
        
        //所属仓库只能看到所属仓库的订单
        $belong_ware_id = $_SESSION['belong_ware_id'];
        $warehouse = M('Warehouse')->getField('id_warehouse','title',true);
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Order/OrderShipping');
        $data = $import_data_tmp;
        $order_id_data = array();

            $count = 1;
            foreach ($data as $row) {

                if ($row[0]) {
                    $selectShip = $ordShip->where(array('track_number' => trim($row[0])))->find();//运单号信息
                    $selectOrder = M('Order')->field('id_order,id_order_status,id_warehouse')->where(array('id_increment'=>$row[0]))->find();//订单号信息
                    if ($selectShip &&  $selectShip['id_order']) {
                        $order_id = $selectShip['id_order'];
                        $get_order = D("Order/Order")->field('id_order_status,id_warehouse')->where(array('id_order' => $order_id))->find();
                        if(in_array($get_order['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1)) {
                            if ($get_order['id_order_status'] == OrderStatus::PICKED) {
                                $order_id_data[] = array('type'=>'ship', 'order_id'=>$order_id, 'n'=>$row[0]);
                            } else {
                                $show_text = $statusLabel[$get_order['id_order_status']];
                                $info['message'] .= sprintf('单据编号:%s 运单号:%s 订单状态是' . $show_text . '，不能更新为已打包'."\n", $import['docno'], $row[0]);
                            }
                        } else {
                            $info['message'] .= sprintf('单据编号:%s 运单号:%s 更新状态失败，该订单属于%s仓库'."\n", $import['docno'], $row[0],$warehouse[$get_order['id_warehouse']]);
                        }
                    } elseif ($selectOrder &&  $selectOrder['id_order']) {
                        $order_id = $selectOrder['id_order'];
                        if(in_array($selectOrder['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1)) {
                            if ($selectOrder['id_order_status'] == OrderStatus::PICKED) {
                                $order_id_data[] = array('type'=>'order', 'order_id'=>$order_id);
                            } else {
                                $show_text = $statusLabel[$selectOrder['id_order_status']];
                                $info['message'] .= sprintf('单据编号:%s 订单号:%s 订单状态是' . $show_text . '，不能更新为已打包'."\n", $import['docno'], $row[0]);
                            }
                        } else {
                            $info['message'] .= sprintf('单据编号:%s 订单号:%s 更新状态失败，该订单属于%s仓库'."\n", $import['docno'], $row[0],$warehouse[$selectOrder['id_warehouse']]);
                        }
                    }else {
                        $info['message'] .= sprintf('单据编号:%s 单号:%s 更新状态失败，没有找到订单'."\n", $import['docno'], $row[0]);
                    }
                }
            }
            
            if($info['message'])
            {
            	return $info;	
            }
            if(!empty($order_id_data))
            {
            	foreach($order_id_data as $v)
	            {
	            	if($v['type']=='ship')
	            	{
	            		$updateData = array('id_order_status' => OrderStatus::PACKAGED);
	                D("Order/Order")->where('id_order=' . $v['order_id'])->save($updateData);
	                $id_increment = D('Order/Order')->where('id_order=' . $v['order_id'])->getField('id_increment');
	                D("Order/OrderRecord")->addHistory($order_id, OrderStatus::PACKAGED, 4, '更新已打包：' . $row[0]);
	                $info['message'] .= sprintf('单据编号:%s 订单号:%s 运单号:%s 更新状态: %s'."\n", $import['docno'], $id_increment,$v['n'], '已打包');
	            	}
	            	else
	            	{
	            		$updateData = array('id_order_status' => OrderStatus::PACKAGED);
	                D("Order/Order")->where('id_order=' . $v['order_id'])->save($updateData);
	                $track_number = D('Order/OrderShipping')->where('id_order=' . $order_id)->getField('track_number');
	                D("Order/OrderRecord")->addHistory($v['order_id'], OrderStatus::PACKAGED, 4, '更新已打包：' . $row[0]);
	                $info['message'] .= sprintf('单据编号:%s 订单号:%s 运单号:%s 更新状态: %s'."\n", $import['docno'], $v['n'], $track_number, '已打包');
	            	}	
	            }
	            $info['status'] = 1;	
            }
            else
            {
            	$info['message'] = '提交失败';	
            }
            
            return $info;
            
    }
    
    /**
     * 提交（更新配送中）
     */
    private function update_delivering_submit($import,$import_data_tmp)
    {
    	$infor = array(
            'status' => 0,
            'message' => ''
        );
        
        $status = array(
            7 => '已配货',
            6 => '缺货'
        );
        //所属仓库只能看到所属仓库的订单
        $belong_ware_id = $_SESSION['belong_ware_id'];
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $warehouse = M('Warehouse')->getField('id_warehouse','title',true);
        $total = 0;
        $ordShip = D('Order/OrderShipping');
    		$data = $import_data_tmp;
    		$order_id_data = array();

            foreach ($data as $row) {

                ++$total;
                $selectShip = $ordShip->where(array(
                            'track_number' => trim($row[0])
                        ))
                        ->find();
                if ($selectShip && $selectShip['id_order']) {
                    $order_id = $selectShip['id_order'];
                    $get_order = D("Order/Order")->field('id_order_status,id_warehouse')->where(array('id_order' => $order_id))->find();
                    if(in_array($get_order['id_warehouse'],$belong_ware_id) || (count($belong_ware_id)==1&&$belong_ware_id[0]==1)) {
                        if (($get_order['id_order_status'] > 7 || $get_order['id_order_status'] == 4 || $get_order['id_order_status'] == 5) && $get_order['id_order_status'] != 18) {
                            $show_text = $statusLabel[$get_order['id_order_status']];
                            $infor['message'] .= sprintf('单据编号:%s 运单号:%s 订单已经是' . $show_text . '了,不能更新为配送中'."\n", $import['docno'], $row[0]);
                        } else {
                            $order_id_data[] = array('order_id'=>$order_id, 'n'=>$row[0]);
                        }
                    } else {
                        $infor['message'] .= sprintf('单据编号:%s 更新状态失败，%s运单号属于%s仓库'."\n", $import['docno'], $row[0],$warehouse[$get_order['id_warehouse']]);
                    }
                } else {
                    $infor['message'] .= sprintf('单据编号:%s  没有找到订单:%s'."\n", $import['docno'],$row[0]);
                }
            }
            
            if($infor['message'])
            {
            	return $infor;	
            }
            if(!empty($order_id_data))
            {
            	foreach($order_id_data as $v)
	            {
	            	$today = date('Y-m-d H:i:s');
	                D("Order/Order")->where('id_order=' . $v['order_id'])->save(array('id_order_status' => 8, 'date_delivery' => $today));
	                $id_increment = D('Order/Order')->where('id_order=' . $v['order_id'])->getField('id_increment');
	                D("Order/OrderRecord")->addHistory($v['order_id'], 8, 4, '批量导入配送中');
	                $infor['message'] .= sprintf('单据编号:%s 订单号:%s 运单号:%s 更新状态: %s'."\n", $import['docno'], $id_increment, $row[0], '已打包');
	            }
	            $infor['status'] = 1;	
            }
            else
            {
            	$infor['message'] = '提交失败';	
            }
            
            return $infor;
    }
    
    /**
     * 提交（更新重量）
     */
    private function update_weight_submit($import,$import_data_tmp)
    {
    	$info = array(
            'status' => 0,
            'message' => ''
        );
        $total = 0;
        
        $data = $import_data_tmp;
        $order_id_data = array();

            foreach ($data as $row) {

                $track_number = $row[0];
                $track_number = trim($track_number);
                $weight = trim($row[1]);/*重量*/
                /*查找全局是否有重复运单号*/
                $finded = D('Order/OrderShipping')
                    ->field('id_order, track_number')
                    ->where(array(
                        'track_number' => $track_number
                    ))
                    ->find();
                if (!$finded) {
                    $infor['message'] .= sprintf('单据编号:%s 运单号:%s 运单号不存在.' ."\n", $import['docno'], $track_number);
                    continue;
                }else{
                    $order_id_data[] = array('weight'=>$weight, 'track_number'=>$track_number);
                }
            }
            
            if($infor['message'])
            {
            	return $infor;	
            }
            
            foreach($order_id_data as $v)
            {
            	$v['weight'] = (float)$v['weight'];
            	$updateData = array('weight' => $v['weight']);
                    $update = D("Order/OrderShipping")->where('track_number=' . $v['track_number'])->save($updateData);
                    if($update)$infor['message'] .= sprintf('单据编号:%s 运单号: %s   重量:%s'."\n", $import['docno'], $v['track_number'],$v['weight']);
                    else $infor['error'][] = sprintf('单据编号:%s 运单号:%s 更新状态失败', $import['docno'], $v['track_number']);
            }
            $infor['status'] = 1;
            return $infor;	
    }
    
    /**
     * 提交（更新缺货）
     */
    private function update_out_stock_submit($import,$import_data_tmp)
    {
    	if('0'==$import['order_action'])	
    	{
    		return $this->update_out_stock_by_num($import,$import_data_tmp);	
    	}
    	return $this->update_out_stock_by_status($import,$import_data_tmp);
    }
    
    /**
     * 提交（更新缺货）:按运单号或者订单号更新
     */
    private function update_out_stock_by_num($import,$import_data_tmp)
    {
    	$info = array(
            'status' => 0,
            'message' => ''
        );
        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Order/OrderShipping');
        $statusLabel = D("Order/OrderStatus")->get_status_label();
        $data = $import_data_tmp;
        $order_id_data = array();
            $update_qty_message = '';
            foreach ($data as $row) {

                if ($row[0]) {
                    $actionID = 6;
                    $selectShip = $ordShip->where(array('track_number' => trim($row[0])))->find();//获取运单信息
                    $select_order = M('Order')->where(array('id_increment'=>trim($row[0])))->find();//获取订单信息
                    if (($selectShip && $actionID) ||( $select_order && $actionID)) {
                        $order_id = $selectShip['id_order']? $selectShip['id_order']:$select_order['id_order'];
                        $get_order = D("Order/Order")->where(array('id_order' => $order_id))->find();

                        $get_wave_order = M('OrderWave')->where(array('id_order' => $order_id))->find();
                        $msg = $select_order ? '订单号' : '运单号';
                        if ($get_order['id_order_status'] == $actionID or $get_order['id_order_status'] > 7) {
                            $show_text = $statusLabel[$get_order['id_order_status']];
                            $message_text = $get_order['id_order_status'] > 7 ? '订单已经是' . $show_text . '状态了' : '订单已经是此状态了';                            
                            $info['message'] .= sprintf('单据编号:%s '.$msg.':%s ' . $message_text."\n", $import['docno'], $row[0]);
                        } else{
                            $order_id_data[] = array('order_id'=>$order_id, 'get_order'=>$get_order, 'n'=>$row[0]);
                        }
                    }
                    else {
                        $info['message'] .= sprintf('单据编号:%s 运单号:%s 更新状态失败，没有找到订单'."\n", $import['docno'], $row[0]);
                    }
                }
            }
            
            if($info['message'])
            {
            	return $info;	
            }
            
            foreach($order_id_data as $v)
            {
            	$updateData['id_order_status'] = 6;
                            $updateData['id_shipping']= 0;

                            D('Common/OrderWave')->where(array('id_order'=>$v['order_id']))->delete();
                            M('OrderShipping')->where('id_order=' . $v['order_id'])->delete();

                            /*当导入缺货时，查询本仓库和其他仓库是否有库存， 如有扣减库存*/
                            if($get_order){
                                $results = \Order\Model\UpdateStatusModel::lessInventory($v['get_order']['id_order'],$v['get_order'],false);
                                if($results['status']){
                                    D("Order/OrderRecord")->addHistory($v['order_id'], $v['get_order']['id_order_status'], 4, $v['n'].' 更新缺货，匹配其他仓库库存。' );
                                    $unpicking_status = \Order\Lib\OrderStatus::UNPICKING;
                                    $id_warehouse     = end($results['id_warehouse']);
                                    D("Order/Order")->where(array('id_order' => $v['order_id']))
                                        ->save(array(
                                            'id_order_status' => $unpicking_status,
                                            'id_warehouse'    => $id_warehouse,
                                            'id_shipping'     => 0
                                        ));
                                    $info['message'] .= sprintf('单据编号:%s 订单号:%s 更新为: %s'."\n", $import['docno'], $v['get_order']['id_increment'], '其他仓库');
                                    continue;
                                }
                            }
                            if($get_wave_order){
                                $note_text = $update_qty_message.' 更新缺货状态同时清除波次单信息' . $get_wave_order['wave_number'];
                            }else{
                                $note_text = $update_qty_message.' '.$row[0].' 更新缺货状态同时清除物流信息与运单号';
                            }
                            D("Order/Order")->where('id_order=' . $v['order_id'])->save($updateData);
                            D("Order/OrderRecord")->addHistory($v['order_id'], $actionID, 4, $note_text);
                            $info['message'] .= sprintf('单据编号:%s '.$msg.':%s 更新状态: %s'."\n", $import['docno'], $v['n'], '缺货');
            }
            $info['status'] = 1;
            return $info;
    }
    
    /**
     * 提交（更新缺货）:状态更新
     */
    private function update_out_stock_by_status($import,$import_data_tmp)
    {
    	$info = array(
            'status' => 0,
            'message' => ''
        );

        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordShip = D('Order/OrderShipping');

            $data = $import_data_tmp;
        		$order_id_data = array();
            
            $OUT_STOCK = \Order\Lib\OrderStatus::OUT_STOCK;
            foreach ($data as $row) {

                if ($row[0]) {
                    $actionID = (int) $import['order_action'];
                    $status_name = D('Order/OrderStatus')->where(array('id_order_status' => $actionID))->getField('title');
                    $selectShip = $ordShip->where(array(
                                'track_number' => trim($row[0])
                            ))
                            ->find();
                    if ($selectShip && $actionID && $selectShip['id_order']) {

                        $order_id_data[] = array('ship'=>$selectShip, 'action_id'=>$actionID, 'n'=>$row[0], 'status_name'=>$status_name);
                        
                    } else {
                        $info['message'] .= sprintf('单据编号:%s 运单号:%s 更新状态失败，没有找到订单'."\n", $import['docno'], $row[0]);
                    }
                }
            }
            
            if($info['message'])
            {
            	return $info;	
            }
            
            foreach($order_id_data as $v)
            {
            	$order_id = $v['ship']['id_order'];
                        $get_order = D('Order/Order')->where(array('id_order'=>$order_id))->find();
                        $today = date('Y-m-d H:i:s');
                        $updateData = array('id_order_status' => $v['action_id']);
                        D("Order/Order")->where('id_order=' . $order_id)->save($updateData);
                        $update_qty_message = '';
                        
                        $id_increment = $get_order['id_increment'];
                        D("Order/OrderRecord")->addHistory($order_id, $v['action_id'], 4, $update_qty_message.' 根据运单号('. $v['n'].')更新订单状态');
                        $info['message'] .= sprintf('单据编号:%s 订单号:%s 更新状态: %s'."\n", $import['docno'], $id_increment, $v['status_name']);
            }
            $info['status'] = 1;
            return $info;
    }
    
    /**
     * 提交（更新运单号）
     */
    private function update_track_number_submit($import,$import_data_tmp)
    {
    	$info = array(
            'status' => 0,
            'message' => ''
        );
        $data = $import_data_tmp;
        $order_id_data = array();
            foreach ($data as $row) {
                $order_id = trim($row[0]);
                $track_number = $row[1];
                /*查找全局是否有重复运单号*/
                $finded = D('Order/OrderShipping')
                        ->field('id_order, track_number')
                        ->where(array(
                            'track_number' => $track_number
                        ))
                        ->find();
                if ($finded) {
                    $info['message'] .= sprintf('单据编号:%s 订单号:%s 运单号:%s 运单号已经存在.'."\n", $import['docno'], $order_id, $track_number);
                    continue;
                }
                /*TODO: 可以从OrderShpping复制一条记录*/
                $order = D('Order/Order')
                        ->field('id_order, id_increment, first_name, tel, id_shipping, date_delivery, id_order_status')
                        ->where(array('id_increment' => $order_id))
                        ->find();
                if (!$order) {
                    $info['message'] .= sprintf('单据编号:%s 订单号:%s 不存在.'."\n", $import['docno'], $order_id);
                    continue;
                }
                if (in_array($order['id_order_status'], array(11, 12, 13, 14, 15))) {
                    $info['message'] .= sprintf('单据编号:%s 订单号:%s 订单已经取消.'."\n", $import['docno'], $order_id);
                    continue;
                }
                $shipping_name = D('Common/Shipping')
                        ->where(array(
                            'id_shipping' => $import['id_shipping']
                        ))
                        ->find();

                /*TODO: 没有运单号时添加一条记录, 但是在分配物流时已经加了一条记录.冗余代码*/
                /*TODO: 如果一个订单有多个运单号时, 必须在这里添加一条新记录*/
                $shipping_info = D('Order/OrderShipping')
                        ->field('id_order_shipping, track_number, id_order')
                        ->where(array(
                            'id_order' => $order['id_order']
                        ))
                        ->select();
                
                $shipping_track = M('ShippingTrack')->where(array('id_shipping'=>$import['id_shipping'],'track_number'=>$track_number))->find();
                $order_id_data[] = array('shipping_track'=>$shipping_track, 'track_number'=>$track_number, 'shipping_info'=>$shipping_info, 'order'=>$order, 'shipping_name'=>$shipping_name, 'order_id'=>$order_id);
            }
            
            if($info['message'])
            {
            	return $info;	
            }
            
            foreach($order_id_data as $v)
            {
            	/*1.如果运单号库表存在，并且未使用，则更新为已使用*/
                /*2.如果运单号库表不存在，则新增该运单号到库里，并标记为已使用*/
                if(!$v['shipping_track']) {
                    $track_array = array(
                        'id_shipping'=>$import['id_shipping'],
                        'track_number'=>$v['track_number'],
                        'track_status'=>1
                    );
                    $track_number_id = D('Common/ShippingTrack')->add($track_array);
                } else {
                    if($v['shipping_track']['track_status'] == 0) {
                        D('Common/ShippingTrack')->where(array('id_shipping'=>$import['id_shipping'],'track_number'=>$v['track_number']))->save(array('track_status'=>1));
                    }
                    $track_number_id = $v['shipping_track']['id_shipping_track'];
                } 

                /*TODO: 修改OrderShipping的逻辑, 只有在更新运单号时直接写入运单号信息即可,不用在分配物流时写入*/
                $updated = false;
                foreach ($v['shipping_info'] as $ship) {

                        /*更新一个后退出*/
                        D('Order/OrderShipping')->where(array('id_order_shipping' => $ship['id_order_shipping']))
                                ->save(array(
                                    'track_number' => $v['track_number'],
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'id_shipping' => $import['id_shipping'],
                        ));
                        M('OrderWave')->where(array('id_order' => $ship['id_order']))
                                ->save(array(
                                    'track_number_id' => $track_number_id,
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'id_shipping' => $import['id_shipping'],
                                ));
                        $updated = true;
                        /*TODO: 导入运单号后更新订单状态为已配货(20)*/
                        /*当订单状态为匹配转寄中和已匹配转寄状态时，不改变状态，只添加物流信息*/
                        if($v['order']['id_order_status'] == OrderStatus::MATCH_FORWARDING || $v['order']['id_order_status'] == OrderStatus::MATCH_FORWARDED) {
                            D('Order/Order')->where(array('id_order' => $v['order']['id_order']))->save(array(
                                'id_shipping' => $import['id_shipping'],
                            ));
                            D("Order/OrderRecord")->addHistory($v['order']['id_order'], $v['order']['id_order_status'], 4, '更新运单号 ' . $v['track_number']);
                        } else {
                            D('Order/Order')->where(array('id_order' => $v['order']['id_order']))->save(array(
                                'id_shipping' => $import['id_shipping'],
                                'id_order_status' => 7
                            ));
                            D("Order/OrderRecord")->addHistory($v['order']['id_order'], 7, 4, '更新运单号 ' . $v['track_number']);
                        }
                        break;

                }
                if (!$updated) {

                    //新的运单号
                    D('Order/OrderShipping')
                            ->add(array(
                                'id_order' => $v['order']['id_order'],
                                'id_shipping' => $import['id_shipping'],
                                'shipping_name' => $v['shipping_name']['title'], /*TODO: 加入物流名称*/
                                'track_number' => $v['track_number'],
                                'fetch_count' => 0,
                                'is_email' => 0,
                                'status_label' => '',
                                'date_delivery' => $v['order']['date_delivery'],
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s'),
                    ));
                    $updated = true;
                    /*TODO: 导入运单号后更新订单状态为已配货(3)*/
                    /*当订单状态为匹配转寄中和已匹配转寄状态时，不改变状态，只添加物流信息*/
                    if($v['order']['id_order_status'] == OrderStatus::MATCH_FORWARDING || $v['order']['id_order_status'] == OrderStatus::MATCH_FORWARDED) {
                        D('Order/Order')->save(array(
                            'id_order' => $v['order']['id_order'],
                            'id_shipping' => $import['id_shipping'],
                        ));
                        D("Order/OrderRecord")
                            ->addHistory($v['order']['id_order'], $v['order']['id_order_status'], 4, '更新运单号 ' . $v['track_number']);
                    } else {
                        D('Order/Order')->save(array(
                            'id_order' => $v['order']['id_order'],
                            'id_shipping' => $import['id_shipping'],
                            'id_order_status' => 7
                        ));
                        D("Order/OrderRecord")
                            ->addHistory($v['order']['id_order'], 7, 4, '更新运单号 ' . $v['track_number']);
                    }
                }
                $info['message'] .= sprintf('单据编号:%s 订单号:%s 更新运单号: %s '."\n", $import['docno'], $order_id, $v['track_number']);
            }
            $info['status'] = 1;
            return $info;	
    }
    
    /**
     * type id
     */
    private function get_type_id($type)
    {
    	$type_id = 0;
    	foreach($this->type as $k=>$v)
    	{
    		if($v['name']==$type)
    		{
    			$type_id = $k;	
    		}
    	}	
    	return $type_id;
    }
    
    /*
     * 生成调拨单单据编号
     * @param array $return
     */
    protected function get_docno() 
    {
    	$Transfer = M('WarehouseImport')->order(array('id' => 'desc'))
    						->field('docno')
                ->limit(1)
                ->find();	
      if(empty($Transfer))
      	return 'IF'.substr(date('Ymd'), 2).'0000001';
      else
      {
      	$Transfer = substr($Transfer['docno'],8);	
      	return 'IF'.substr(date('Ymd'), 2).str_pad((intval($Transfer)+1),7,'0',STR_PAD_LEFT);
      }
    }

    /*
     * 更新物流
     */
    public function update_shipping_submit($import,$import_data_tmp){
        /*检查*/
        $info = array(
            'status' => 0,
            'message' => ''
        );
        $data = $import_data_tmp;
        $shipping = D('Common/Shipping')->field('id_shipping,title')->where('status=1')->select();
        $shipping_selected = array();
        foreach ($shipping as $item) {
            if ((int) $import['id_shipping'] === (int) $item['id_shipping']) {
                $shipping_selected = $item;
            }
        }

        $total = 0;
        /* @var $ordShip \Common\Model\OrderShippingModel */
        $ordObj = D("Order/Order");
        //导入记录到文件
        $path = write_file('warehouse', 'update_shipping', $data);
        $count = 1;
        foreach ($data as $row) {
            $row = trim($row[0]);
            if (empty($row))
                continue;
            ++$total;
            $order_id = $row;
            if ($order_id) {
                $orderObj = $ordObj->where(array(
                    'id_increment' => $order_id
                ))->find();
                if ($orderObj) {
                    $ordObj->where('id_order=' . $orderObj['id_order'])->save(array('id_shipping' => $import['id_shipping']));
                    D('Order/OrderShipping')->where('id_order=' . $orderObj['id_order'])->save(array('id_shipping' => $import['id_shipping'], 'shipping_name' => $shipping_selected['title']));
                    D("Order/OrderRecord")->addHistory($order_id, $orderObj['id_order_status'], 4, '更新物流' . $row[0]);
                    $info['message'] .= sprintf('第%s行: 订单号:%s 物流名称: %s 更新成功', $count++, $order_id, $shipping_selected['title'])."\n";
                    $info['status'] =1;
                } else {
                    $info['message'] .= sprintf('第%s行: 订单号:%s 没有找到订单', $count++,$order_id)."\n";
                }
            } else {
                $info['message'] .= sprintf('第%s行:订单号:%s 没有找到订单', $count++,$order_id)."\n";
            }
        }
//        echo json_encode($info);die;
        add_system_record($_SESSION['ADMIN_ID'], 2, 3, '更新物流', $path);
        return $info;
    }
    /*
     * 更新转寄入库提交
     */
    public function update_to_warehouse_submit($import,$import_data_tmp){
        $info = array(
            'status' => 0,
            'message' => ''
        );
        $model = new \Think\Model;
        $order_table_name = D('Order/Order')->getTableName();
        $order_item_table_name = D('Order/OrderItem')->getTableName();
        $belong_ware_id = $_SESSION['belong_ware_id'];
//        dump($belong_ware_id);die;
        $where['status'] = 1;
        if(count($belong_ware_id) != 1 || (count($belong_ware_id) == 1 && $belong_ware_id[0] != 1)) {
            //$where['id_warehouse'] = array('IN',$belong_ware_id);
        }
        $where['forward'] = 1;
        $warehouse = M('Warehouse')->field('id_warehouse,title')->where($where)->select();
        $warehouse = array_column($warehouse, 'title', 'id_warehouse');
        $total = 0;
        $data = $import_data_tmp;
        //导入记录到文件
        $path = write_file('warehouse', 'forward', $data);
        $count = 1;
        foreach ($data as $key=>$row) {
            $row = $row[0];
            if (empty($row))
                continue;
            ++$total;
            $track_number = $row;
//            $track_number = str_replace(array('"',' ',' ','　'),'', $track_number);
//            $track_number = trim($track_number);

            $finded = M('OrderShipping')->field('id_order, track_number')->where(array('track_number' => $track_number))->find();
            if($finded) {
                $id_order = $finded['id_order'];//订单id
                $order = M('Order')->field('id_order_status,id_increment,id_warehouse,id_department,id_order')->where(array('id_order'=>$id_order))->find();
                if($order['id_order_status']==OrderStatus::DELIVERING || $order['id_order_status']==OrderStatus::RETURNED || $order['id_order_status']==OrderStatus::REJECTION || $order['id_order_status']==OrderStatus::CLAIMS) {
                    D('Order/Order')->where(array('id_order'=>$id_order))->save(array('id_order_status'=>OrderStatus::FORWARD));
                    D("Order/OrderRecord")->addHistory($id_order, OrderStatus::FORWARD, 4, '更新转寄状态，运单号' . $track_number.'，仓库' .$warehouse[$import['warehouse_id']]);
                    $ProductData=D("Common/OrderItem")->where(array('id_order'=>$id_order))->field('sku_title,sku,id_product,id_product_sku,product_title,quantity')->select();
                    if(!empty($ProductData)){
                        $Prodate['track_number']=$track_number;
                        $Prodate['id_increment']=$order['id_increment'];
                        $Prodate['created_at']=date('Y-m-d H:i:s');
                        $Prodate['updated_at']=date('Y-m-d H:i:s');
                        $Prodate['id_warehouse']= $import['warehouse_id'];
                        $Prodate['id_order']=$order['id_order'];
                        foreach($ProductData as $k=>$v){
                            $Prodate['sku']=$v['sku'];
                            $Prodate['id_product']=$v['id_product'];
                            $Prodate['id_department']=M('Product')->where(["id_product"=>$Prodate['id_product']])->getField('id_department');
                            $Prodate['inner_name']=M('Product')->where(["id_product"=>$Prodate['id_product']])->getField('inner_name');
                            $Prodate['id_product_sku']=$v['id_product_sku'];
                            $Prodate['title']=$v['product_title'];
                            $Prodate['code']=M('ProductSku')->where(["id_product_sku"=>$Prodate['id_product_sku']])->getField('barcode');
                            $Prodate['total']=$v['quantity'];
                            $id=M('Forward')->add($Prodate);
                        }

                        $order_forward_id = array();
                        $warehouse_id = array();
                        $tracking_number = array();
                        $new_order_id = array();
                        $order_arr = array();
                        foreach($ProductData as $key=>$value) {
                            $where = 'oi.id_product_sku ='.$value['id_product_sku'].' and o.id_order_status=6';
                            $order_data = $model->table($order_table_name . ' as o LEFT JOIN ' . $order_item_table_name . ' as oi ON o.id_order=oi.id_order')
                                ->field('o.id_zone,oi.id_order,oi.id_product,oi.id_product_sku,oi.quantity,o.id_order_status')
                                ->where($where)
                                ->order('o.created_at asc')
                                ->select();

                            foreach($order_data as $k=>$val) {
                                $order_arr[] = array(
                                    $val['id_product_sku'] => $val['quantity']
                                );
                                $fwhere = array();
                                $fwhere['id_product'] = $val['id_product'];
                                $fwhere['id_product_sku'] = $val['id_product_sku'];
                                $fwhere['total'] = $val['quantity'];
                                $fwhere['status'] = 0;
                                $forward = M('Forward')->where($fwhere)->find();//转寄仓库
                                if($forward) {
                                    $id_zone = M('Warehouse')->where(array('id_warehouse'=>$forward['id_warehouse']))->getField('id_zone');
                                    if($val['id_zone'] == $id_zone) {
                                        $new_order_id = $val['id_order'];
                                        $order_forward_id = $forward['id_order'];
                                        $warehouse_id = $forward['id_warehouse'];
                                        $tracking_number[] = $forward['track_number'];
                                    }
                                }
                            }
                        }

                        if(!empty($order_forward_id)){
                            $forwards = M('Forward')->where(array('id_order'=>$order_forward_id))->select();
                            $for_arr = array();
                            foreach($forwards as $k=>$v) {
                                $for_arr[] = array(
                                    $v['id_product_sku']=>$v['total']
                                );
                            }
                        }

                        if(!empty($tracking_number) && count(array_unique($tracking_number)) == 1 && count($forwards)==count($order_data) && $for_arr==$order_arr) {
                            D('Common/Forward')->where(array('id_order'=>$order_forward_id))->save(array('status'=>OrderStatus::HAS_MATCH));
                            $forward_order = M('OrderForward')->where(array('tracking_number'=>$tracking_number[0]))->find();
                            if(!$forward_order) {
                                $data = array(
                                    'new_order_id' => $new_order_id,
                                    'old_order_id' => $order_forward_id,
                                    'warehouse_id' => $warehouse_id,
                                    'tracking_number' => $tracking_number[0],
                                    'created_time' => date('Y-m-d H:i:s')
                                );
                                D('Common/OrderForward')->add($data);
                                $update_data = array(
                                    'id_order'=>$new_order_id,
                                    'status_id'=>OrderStatus::MATCH_FORWARDING,
                                );
                                UpdateStatusModel::minus_stock($update_data);
                            }
                        }
                    }
                    $info['message'] .= sprintf('第%s行: 订单号:%s 运单号:%s 仓库名称: %s操作成功', $count++, $order['id_increment'], $track_number, $warehouse[$import['warehouse_id']])."\n";
                    $info['status'] =1;
                } else {
                    $info['message'] .= sprintf('第%s行: 订单号:%s 运单号:%s 该运单号不能进行更新转寄状态操作', $count++, $order['id_increment'], $track_number)."\n";
                }
            } else {
                $info['message'] .= sprintf('第%s行: 运单号:%s 没有该运单号', $count++, $track_number)."\n";
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 2, 4, '更新转寄状态', $path);
        return $info;
    }
    /*
     * 更新匹配转寄提交
     */
     public function update_to_forward_submit($import,$import_data_tmp){
         $info = array(
             'status' => 0,
             'message' => ''
         );
         $total = 0;
         /* @var $ordShip \Common\Model\OrderShippingModel */
         $ordShip = D('Order/OrderShipping');
         $statusLabel = D("Order/OrderStatus")->get_status_label();
         if (IS_POST) {
             $data = $import_data_tmp;
             //导入记录到文件
             $path = write_file('warehouse', 'update_match_forward', $data);
             $count = 1;
             foreach ($data as $row) {
                 $row = trim($row[0]);
//                 echo $row;die;
                 if (empty($row))
                     continue;
                 ++$total;
                 $id_increment = $row;
                 if ($id_increment) {
                     $select_order = M('Order')->where(array('id_increment'=>trim($id_increment)))->find();//获取订单信息
                     if ($select_order) {
                         $order_id = $select_order['id_order'];
                         if($select_order['id_order_status'] == OrderStatus::MATCH_FORWARDING) {
                             $updateData['id_order_status'] = OrderStatus::MATCH_FORWARDED;
                             $res = D("Order/Order")->where('id_order=' . $order_id)->save($updateData);
                             D("Order/OrderRecord")->addHistory($order_id, OrderStatus::MATCH_FORWARDED, 4,' 更新为已匹配转寄');
                             if($res){
                                 $info['status'] = 1;
                                 $info['message'] .= sprintf('第%s行: 订单号:%s 更新状态: %s', $count++, $row[0], '已匹配转寄状态成功')."\n";
                             }
                             else
                                 $info['message'] .= sprintf('第%s行: 订单号:%s 更新状态: %s', $count++, $row[0], '已匹配转寄状态失败')."\n";
                         } else {
                             $info['message'] .= sprintf('第%s行: 订单号:%s 更新状态失败，状态不是匹配转寄中，不能进行更新', $count++, $id_increment)."\n";
                         }
                     }
                     else {
                         $info['message'] .= sprintf('第%s行: 订单号:%s 更新状态失败，没有找到订单', $count++, $id_increment)."\n";
                     }
                 } else {
                     $info['message'] .= sprintf('第%s行: 格式不正确', $count++)."\n";
                 }
             }
             add_system_record($_SESSION['ADMIN_ID'], 2, 4, '更新匹配转寄', $path);
         }
        return $info;
     }
}
