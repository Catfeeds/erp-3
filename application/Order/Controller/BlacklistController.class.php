<?php
/*
* 黑名单
 * */

namespace Order\Controller;

use Common\Controller\AdminbaseController;

class BlacklistController extends AdminbaseController
{

    public function index()
    {
        $M = new \Think\Model;
        
        $where = array();
        if(isset($_GET['level']) && $_GET['level']) {
            $where['a.level'] = array('EQ',$_GET['level']);
        }
        if(isset($_GET['keyword']) && $_GET['keyword']) {
            $where['a.title'] = array('LIKE','%'.$_GET['keyword'].'%');
        }
        if(isset($_GET['ptype']) && $_GET['ptype']) {
            $where['a.field'] = array('LIKE','%'.$_GET['ptype'].'%');
        }
         
        $blacklist_tab_name = M('Blacklist')->getTableName();
        $user_tab_name = M('Users')->getTableName();
        $find_count = $M->table($blacklist_tab_name . ' AS a LEFT JOIN ' . $user_tab_name . ' AS b ON a.id_user=b.id')
                        ->field('a.*,b.user_nicename')->where($where)->order('id_blacklist DESC')->select();
        $page = $this->page(count($find_count), 20);
        $black_data = $M->table($blacklist_tab_name . ' AS a LEFT JOIN ' . $user_tab_name . ' AS b ON a.id_user=b.id')
                        ->field('a.*,b.user_nicename,b.user_login')->where($where)->order('id_blacklist DESC')->limit($page->firstRow, $page->listRows)->select();

        $level = M('Blacklist')->field('level')->group('level')->order('level ASC')->select();
        $arr_level = array();
        foreach ($level as $k=>$v) {
            if($v['level'] == 1) {
                $arr_level[$v['level']] = '警告';
            }
            if($v['level'] == 10) {
                $arr_level[$v['level']] = '黑名单';
            }
        }

        add_system_record(sp_get_current_admin_id(), 4, 3, '查看黑名单列表');
        $this->assign("data",$black_data);
        $this->assign('levels',$arr_level);
        $this->assign("page", $page->show('Admin'));
        $this->display();
   }

    /*添加黑名单*/
    public function add(){
        if (IS_POST) {
            $data = I('post.data');
//            var_dump($data);die;
            $data = $this->getDataRow($data);
            $blacklist = D('Blacklist');
            $total = 0;
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 5);
                $row = array_filter($row);
                $addData = array(
                    'field'=>trim($row[0]),
                    'title'=>trim($row[1]),
                    'level'=>trim($row[2]),
                );
                $result = $blacklist->where($addData)->find();
                if($result){
                    //$this->error("黑名单已经存在！");
                }elseif(empty($result)){
                    $ass="have";
                    $addData['id_user']=$_SESSION['ADMIN_ID'];
                    $blacklist->data($addData)->add();
                }
            }
            add_system_record($_SESSION['ADMIN_ID'], 1, 3,'添加黑名单');
        }
        $this->assign("ass",$ass)->display();
    }

    /*删除黑名单*/
    public function delete(){
        $id = I("get.id",0,'intval');
        if (D('Blacklist')->delete($id)!==false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
        add_system_record($_SESSION['ADMIN_ID'], 3, 3,'删除黑名单');
    }


    /*订单匹配黑名单类型*/

    public function api($param){
        if (IS_POST) {
        /*黑名单查询内容*/

        }

    }

    protected function getDataRow($data)
    {
        if (empty($data))
            return array();
        $data = preg_split("~[\r\n]~", $data, -1, PREG_SPLIT_NO_EMPTY);
        return $data;
    }
}