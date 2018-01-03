<?php
namespace Waybill\Model;
use Common\Model\CommonModel;
class WaybillModel{
    /**
     * 读取台湾的县市
     * @return array|mixed
     */
    public function get_tw_province($field='province',$where= array('province'=>array('NEQ','')),$group='province') {
        $md5 = md5(json_encode($where));
        $key = 'getWaybill'.$field.$md5;
        $cache = 0;F($key);
        if ($cache) {
            $list = unserialize($cache);
        } else {
            $list = array();
            if($group){
                $zipCode =  D("Common/ZipcodeArea")->field('id,area_code,zipcode,'.$field.' as read_field')
                    ->where($where)->group($group)->select();
            }else{
                $zipCode =  D("Common/ZipcodeArea")->field('id,area_code,zipcode,'.$field.' as read_field')
                    ->where($where)->select();
            }
            if ($zipCode) {
                foreach ($zipCode as $item) {
                    $list[$item['id']] = $item;
                }
            }
            F($key, serialize($list));
        }
        return $list;
    }
    public function string_replace($string){
        $key  = 'getZipcodeTransferData';
        $cache = F($key);
        if ($cache) {
            $list = unserialize($cache);
        } else {
            $list = D("Common/ZipcodeTransfer")->select();
            F($key, serialize($list));
        }
        if($list){
            foreach($list as $item){
                $string = str_replace($item['word_in'],$item['word_out'],$string);
            }
        }
        return $string;
    }
    public function get_tw_zip_code($address){
        //$address             = '嘉義市國城二城10號';
        $address             = $this->string_replace(trim($address));
        $ini_address         = $address;
        $area_code           = '';
        $zipcode             = '';
        /*$province_exp        = array('市','縣');
        $area_exp            = array('鄉','鎮','市','區');
        $village_exp         = array('村');
        $street_exp          = array('路','街');
        $segment_exp         = array('段');
        $lane_exp            = array('巷');
        $alley_exp           = array('弄');
        $alley_number_exp    = array('號');

        $exp_province = $this->explode_string($province_exp,$address);
        $address      = $exp_province?str_replace($exp_province,'',$address):$address;
        $exp_area     = $this->explode_string($area_exp,$address);
        if($exp_area){
            $address           = $exp_area?str_replace($exp_area,'',$address):$address;
            $exp_village       = $this->explode_string($village_exp,$address);
            $address           = $exp_village?str_replace($exp_village,'',$address):$address;
            $exp_street        = $this->explode_string($street_exp,$address);
            $address           = $exp_street?str_replace($exp_street,'',$address):$address;
            $exp_segment       = $this->explode_string($segment_exp,$address);
            $address           = $exp_segment?str_replace($exp_segment,'',$address):$address;
            $exp_lane          = $this->explode_string($lane_exp,$address);
            $exp_alley         = $this->explode_string($alley_exp,$address);
            //$exp_alley       = $this->explode_string($alley_exp,$address);
            $where             = array();
            $where['village']  = $exp_village;
            $where['street']   = $exp_street;
            $where['segment']  = $exp_segment;
            $where['lane']     = $exp_lane;
            $where['alley']    = $exp_alley;
            $where             = array_filter($where);
            if($where){
                $where['_logic']   = 'or';
                $map['_complex']   = $where;
            }
            $map['province']   = $exp_province;
            $map['area']       = $exp_area;
            $get_data = D("Common/ZipcodeArea")->where($map)->find();
            if(!$get_data){
                $get_data = $this->switch_address($ini_address);
            }
            $area_code = $get_data['area_code'];
            $zipcode   = substr($get_data['zipcode'],0,3).'-'.substr($get_data['zipcode'],3,5);
        }*/

        $get_data = $this->switch_address($ini_address);
        if($get_data){
            $area_code = $get_data['area_code'];
            $zipcode   = substr($get_data['zipcode'],0,3).'-'.substr($get_data['zipcode'],3,5);
        }
        return array('area'=>$area_code,'zip_code'=>$zipcode);
    }
    public function switch_address($address){
        $province_exp        = array('市','縣');
        $area_exp            = array('鄉','鎮','市','區');
        $village_exp         = array('村');
        $street_exp          = array('路','街');
        $segment_exp         = array('段');
        $lane_exp            = array('巷');
        $alley_exp           = array('弄');
        $alley_number_exp    = array('號');
        $pro_array           = $this->get_tw_province();
        $province            = $this->preg_match_string($pro_array,$province_exp,str_replace('臺','台',$address));
        $province            = $province?$province:$this->preg_match_province($pro_array,$province_exp,$address);
        $get_result          = $province;
        if($province){
            $address         = str_replace(array($province['read_field'],$province['match_string']),'',$address);

            $area_array      = $this->get_tw_province('area',array('province'=>$province['read_field']),'area');
            $area            = $this->preg_match_string($area_array,$area_exp,$address);
            $address         = $area?str_replace(array($area['read_field'],$province['match_string']),'',$address):$address;
            $get_result      = $area?$area:$get_result;
            $where           = $area?array('province'=>$province['read_field'],'area'=>$area['read_field']):array('province'=>$province['read_field']);

            $village_array   = $this->get_tw_province('village',$where,'village');
            $village         = $this->preg_match_string($village_array,$village_exp,$address);
            $get_result      = $village?$village:$get_result;
            if($village){
                $where['village'] = $village['read_field'];
            }

            //$where           = array('province'=>$province['read_field']);
            $street_array    = $this->get_tw_province('street',$where,'street');
            $street          = $this->preg_match_string($street_array,$street_exp,$address);
            $get_result      = $street?$street:$get_result;
            if($street){
                $where['street'] = $street['read_field'];
            }

            //$where           = array('province'=>$province['read_field']);
            $segment_array   = $this->get_tw_province('segment',$where,'segment');
            $segment         = $this->preg_match_string($segment_array,$segment_exp,$address);
            $get_result      = $segment?$segment:$get_result;
            if($segment){
                $where['segment'] = $segment['read_field'];
            }

            //$where           = array('province'=>$province['read_field']);
            $lane_array      = $this->get_tw_province('lane',$where,'lane');
            $lane            = $this->preg_match_string($lane_array,$lane_exp,$address);
            $get_result      = $lane?$lane:$get_result;
            if($lane){
                $where['lane'] = $lane['read_field'];
            }

            //$where           = array('province'=>$province['read_field']);
            $alley_array     = $this->get_tw_province('alley',$where,'alley');
            $alley           = $this->preg_match_string($alley_array,$alley_exp,$address);
            $get_result      = $alley?$alley:$get_result;
            //$reg='/(\d+-)*,*(\d+-?)+號/';//匹配数字的正则表达式
            //preg_match_all($reg,$address,$result);
            //print_r($where);print_r($get_result);exit();
        }
        return $get_result;
    }
    public function preg_match_string($array,$replace_key,$address){
        $return              = false;
        foreach($array as $pro){
//            $pro['read_field'] = str_replace($replace_key,'',$pro['read_field']);
//            $pattern = '/'.$pro_title.'/';
//            preg_match($pattern, $address, $matches, PREG_OFFSET_CAPTURE);
//            if($matches[0] && $matches[0][0]){
//                $pro['match_string'] = $matches[0][0];
//                $return = $pro;
//            }
            //echo $pro_title.'==='.$address.'<br />';
            if(strpos($address, $pro['read_field'])!== false){
                $pro['match_string'] = $pro['read_field'];
                $return = $pro;
            }
        }
        return $return;
    }
    public function preg_match_province($array,$replace_key,$address){
        $return              = false;
        foreach($array as $pro){
            if(strpos($address, $pro['read_field'])!== false){
                $pro['match_string'] = $pro['read_field'];
                $return = $pro;break;
            }else{
                $read_field  = str_replace($replace_key,'',$pro['read_field']);
                if(strpos($address, $read_field)!== false){
                    $pro['match_string'] = $pro['read_field'];
                    $return = $pro;
                }
            }
        }
        return $return;
    }
    public function explode_string($delimiter=array(),$string){
        $return = '';
        if(is_array($delimiter)){
            foreach($delimiter as $item){
                $exp_address  = explode($item,$string);
                if(count($exp_address)==2){
                    $return   = $exp_address[0].$item;
                    break;
                }
            }
        }
        return $return;
    }
}

