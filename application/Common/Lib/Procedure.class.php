<?php
/**
 * Created by Eva.
 * User: 549251235@qq.com
 * Date: 17-5-22
 * Time: 下午1:44
 */

namespace Common\Lib;
include "data/conf/db.php";
class Procedure {
    /*
     * $procedure_name 存储过程名字
     * $array参数
     */
    public static function call($procedure_name,$array=[]){
        $return = array();
        $conf = file_exists("data/conf/db.php")?include "data/conf/db.php":'';
        if(!$conf){
            $return[0]=0;
            $return['@erromsg']= '数据库连接文件不存在';
            return $return;
        }
        $connect = mysql_connect($conf['DB_HOST'],$conf['DB_USER'],$conf['DB_PWD']) or die("error connecting") ; //连接数据库
        mysql_select_db($conf['DB_NAME']); //打开数据库
        mysql_query('set names utf8');
        //调用ERP_INOUT_SUBMIT
        if($procedure_name=='ERP_INOUT_SUBMIT'||$procedure_name=='ERP_BILLINOUT_SUBMIT'){
            if(count($array) == 4){
                $billid = explode(',',$array['billid']);
                if(count($billid)>1){
                    foreach($billid as $v){
                        $sql = " call $procedure_name('{$v}','{$array['userid']}','{$array['tablename']}','{$array['inor']}',@erromsg)";
                        mysql_query($sql);
                        if(mysql_error()){
                            $return[$v] = mysql_error();
                        }else{
                            $result= mysql_query('select @erromsg;');
                            $return[$v] =mysql_fetch_array($result)['@erromsg'];
                        }
                    }
                }else{
                    $sql = " call $procedure_name('{$array['billid']}','{$array['userid']}','{$array['tablename']}','{$array['inor']}',@erromsg)";
//                  echo $sql;die;
                    mysql_query($sql);
                    if(mysql_error()){
                        $return[$array['billid']] = mysql_error();
                    }else{
                        $result= mysql_query('select @erromsg;');
                        $return[$array['billid']] =mysql_fetch_array($result)['@erromsg'];
                    }
                }
            }else{
                $return[0]=0;
                $return['@erromsg']= '参数个数错误';
            }
            mysql_close();
        }else if($procedure_name=='ERP_ORDERAUTO_DIS'){
            $sql = " call $procedure_name(@erromsg)";
            mysql_query($sql);
            if(mysql_error()){
                $return[] = mysql_error();
            }else{
                $result = mysql_query('select @erromsg;');
                $return[] =mysql_fetch_array($result)['@erromsg'];
            }

        }
        return array_filter($return);
    }
} 
