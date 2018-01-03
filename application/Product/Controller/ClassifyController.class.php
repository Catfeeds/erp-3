<?php
namespace Product\Controller;
use Common\Controller\AdminbaseController;

class ClassifyController extends AdminbaseController{
    
    protected $product_classify;

    public function _initialize() {
        parent::_initialize();
        $this->product_classify = D("Common/ProductClassify");
    }
    
    public function index() {
        
        $list = M('ProductClassify')->select();
        
        $this->assign('list',$list);
        $this->display();        
    }
    
    public function add() {
        $this->display();
    }
    
    public function add_post() {
        if (IS_POST) {
            $data = I('post.');
            $data['created_at'] = date('Y-m-d H:i:s', time());
            if ($this->product_classify->create($data)) {
                if ($this->product_classify->add($data)) {
                    add_system_record(sp_get_current_admin_id(), 1, 2, '添加产品分类成功');
                    $this->success("添加产品分类成功", U("classify/index"));
                } else {
                    add_system_record(sp_get_current_admin_id(), 1, 2, '添加产品分类失败');
                    $this->error('添加产品分类失败');
                }
            } else {
                $this->error($this->product_classify->getError());
            }
        }
    }
    
    public function edit() {
        $id = I('get.id');
        $list = M('ProductClassify')->where(array('id_classify'=>$id))->find();
        
        $this->assign('list',$list);
        $this->display();
    }
    
    public function edit_post() {
        $id = I('post.id_classify');
        if (IS_POST) {
            $data = I('post.');
            $data['updated_at'] = date('Y-m-d H:i:s', time());
            if ($this->product_classify->create($data)) {
                if ($this->product_classify->save($data)) {
                    add_system_record(sp_get_current_admin_id(), 2, 2, '修改产品分类'.$data['id_classify'].'成功');
                    $this->success("修改成功！", U('classify/index'));
                } else {
                    add_system_record(sp_get_current_admin_id(), 2, 2, '修改产品分类'.$data['id_classify'].'失败');
                    $this->error("修改失败！");
                }
            } else {
                $this->error($this->product_classify->getError());
            }
        }
    }
}