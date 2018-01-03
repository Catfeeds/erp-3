<?php

namespace Advert\Controller;

use Common\Controller\AdminbaseController;
use SystemRecord\Model\SystemRecordModel;

class ImportController extends AdminbaseController {

    protected $AdvertData;

    public function _initialize() {
        parent::_initialize();
        $this->page      = $_SESSION['set_page_row']?(int)$_SESSION['set_page_row']:20;
    }
    /*
     * 导入广告数据
     */
    public function import_advert_data(){
        if (IS_POST) {
            $advertObj = M("Advert");
            $advertDataObj = M("AdvertData");
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('advert', 'import_advert_data', $data);
            $count = 1;
            $total = 0;
            $data = $this->getDataRow($data);
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 4);
                if (count($row) != 4 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
//                $user_name = trim($row[0], '\'" ');
//                $domain_name = trim($row[1], '\'" ');
//                $conversion_at = trim($row[2], '\'" ');
//                $expense = (float)trim($row[3], '\'" ');
//                $zone = trim($row[4]);
                $conversion_at = trim($row[0], '\'" ');
                $type=trim($row[1], '\'" ');
                $url=trim($row[2], '\'" ');
                $expense = (float)trim($row[3], '\'" ');
                $id_zone = 0;
//                if(!$id_zone){
//                    $infor['error'][] = sprintf('第%s行:找不到对应的地区 %s %s  %s  ', $count,$user_name,$domain_name,$zone);
//                    $count++;
//                    continue;
//                }
               //$where_advert['user_nicename'] = $user_name;
                $where_advert['url'] = $url;
                if(empty($url)){
                    $infor['error'][] = sprintf('第%s行:找不到对应的广告 %s  %s  %s %s ', $count,$conversion_at,$type,$url,$expense);
                }else{
                    $getAdvert = $advertObj->where($where_advert)->find();
                    if($getAdvert['advert_id']){
                        $id_users_today=$getAdvert['id_users'] ;
                        $id_advert_data = $advertDataObj->where(array('advert_id'=>$getAdvert['advert_id'],'conversion_at'=>$conversion_at,'type'=>$type))->getField('id_advert_data');
                        if($id_advert_data){
                            $save['expense'] = $expense;
                            $save['add_user'] = $_SESSION['ADMIN_ID'];
                            $save['conversion_at'] = $conversion_at;
                            $save['id_zone'] = $getAdvert['id_zone'];
                            $save['id_advert_data'] = $id_advert_data;
                            $save['update_at'] = date('Y-m-d H:i:s');
                            $save['id_users_today'] = $id_users_today;
                            $advertDataObj->save($save);
                            $infor['success'][] = sprintf('第%s行: %s  %s  %s %s 之前已有广告数据，修改成功', $count, $conversion_at,$type,$url,$expense);
                        }else{
                            $add['type'] = $type;
                            $add['advert_id'] = $getAdvert['advert_id'];
                            $add['id_zone'] = $getAdvert['id_zone'];
                            $add['expense'] = $expense;
                            $add['conversion_at'] = $conversion_at;
                            $add['created_at'] = date('Y-m-d H:i:s');
                            $add['add_user'] = $_SESSION['ADMIN_ID'];
                            $add['id_users_today'] = $id_users_today;
                            $advertDataObj->add($add);
                            $infor['success'][] = sprintf('第%s行: %s  %s  %s %s 添加成功', $count, $conversion_at,$type,$url,$expense);
                        }
                    }
                    else{
                        $infor['error'][] = sprintf('第%s行:找不到对应的广告 %s  %s  %s %s ', $count,$conversion_at,$type,$url,$expense);
                    }
                }

                $count++;
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '导入广告数据',$path);
        }
        $this->assign('infor', $infor);
        $this->assign('data',I('post.data'));
        $this->display();
    }
    /*
        * 导入广告
        */
    public function import_advert(){
        if (IS_POST) {
            $advertObj = M("Advert");
            $advertDataObj = M("AdvertData");
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('advert', 'import_advert', $data);
            $count = 1;
            $total = 0;
            $data = $this->getDataRow($data);
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode("\t", trim($row), 6);
                if (count($row) != 6 || !$row[0]) {
                    $infor['error'][] = sprintf('第%s行: 格式不正确', $count++);
                    continue;
                }
                $user_name = trim($row[0], '\'" ');
                $domain_name = trim($row[1], '\'" ');
                $url= trim($row[2], '\'" ');
                $advert_name = trim($row[3], '\'" ');
                //$post_at = trim($row[3], '\'" ');

                $zone = trim($row[4]);
                $id_zone = M('Zone')->where(array('title'=>$zone))->getField('id_zone');
                if(!$id_zone){
                    $infor['error'][] = sprintf('第%s行:找不到对应的地区 %s %s  %s  ', $count,$user_name,$domain_name,$zone);
                    $count++;
                    continue;
                }
                $advert_status = trim($row[5]);
//                $where_advert['user_nicename'] = $user_name;
                $where_advert['url'] = $url;
                $getAdvert = $advertObj->where($where_advert)->select();
                if($getAdvert){
                    foreach($getAdvert as $v){
                        $user_nicename = M('Users')->where(array('id'=>$v['id_users']))->getField('user_nicename');
                        $infor['error'][] = sprintf('第%s行:%s     已存在这条广告 %s,广告专员是%s', $count,$v['user_nicename'],$url,$user_nicename);
                    }
                }
                else{
                    $id_users = M('Users')->where(array('user_nicename'=>$user_name))->getField('id');
                    $id_domain = M('Domain')->where(array('name'=>$domain_name))->getField('id_domain');
                    if($id_users&&$id_domain){
                        $add['id_users'] = $id_users;
                        $add['id_domain'] = $id_domain;
                        $add['url'] =$url;
                        $add['advert_name'] = $advert_name;
                        $add['created_at'] = date('Y-m-d H:i:s');
                        //$add['post_at'] = $post_at;
                        $add['id_zone'] = $id_zone;
                        $add['advert_status'] = ($advert_status == '启用' || $advert_status == '正常') ? 1 :($advert_status == '停用' ?  0 : '');
                        $add['add_user'] = $_SESSION['ADMIN_ID'];
                        $advertObj->add($add);
                        $infor['success'][] = sprintf('第%s行:添加广告成功 %s  %s  %s %s ', $count,$user_name,$domain_name,$advert_name,$zone,$advert_status);
                    }else{
                        $infor['error'][] = sprintf('第%s行:优化师或者域名有误，添加失败 %s  %s  %s %s ', $count,$user_name,$domain_name,$advert_name,$zone,$advert_status);
                    }

                }
                $count++;
            }
            add_system_record($_SESSION['ADMIN_ID'], 5, 3, '导入广告',$path);
        }
        $this->assign('infor', $infor);
        $this->assign('data',I('post.data'));
        $this->display();
    }

    public function change_arr() {
        $arr = array(
            'old_ad_id' => 187,
            'new_ad_id' => 540
        );
        return $arr;
    }
    /**
     * 更改指定广告专员id
     */
    public function update_aduser() {
        $arr = $this->change_arr();
        $advert = M('Advert')->where(array('id_users'=>$arr['old_ad_id']))->select();
        if($advert) {
            $data = array(
                'id_users'=>$arr['new_ad_id']
            );
            D('Common/Advert')->where(array('id_users'=>$arr['old_ad_id']))->save($data);
            echo $arr['old_ad_id'].'=>'.$arr['new_ad_id'].'成功';
        } else {
            echo '该广告专员对应的广告不存在';
        }
    }
}
