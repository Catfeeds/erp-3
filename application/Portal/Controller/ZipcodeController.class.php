<?php
/*
 *      _______ _     _       _     _____ __  __ ______
 *     |__   __| |   (_)     | |   / ____|  \/  |  ____|
 *        | |  | |__  _ _ __ | | _| |    | \  / | |__
 *        | |  | '_ \| | '_ \| |/ / |    | |\/| |  __|
 *        | |  | | | | | | | |   <| |____| |  | | |
 *        |_|  |_| |_|_|_| |_|_|\_\\_____|_|  |_|_|
 */
/*
 *     _________  ___  ___  ___  ________   ___  __    ________  _____ ______   ________
 *    |\___   ___\\  \|\  \|\  \|\   ___  \|\  \|\  \ |\   ____\|\   _ \  _   \|\  _____\
 *    \|___ \  \_\ \  \\\  \ \  \ \  \\ \  \ \  \/  /|\ \  \___|\ \  \\\__\ \  \ \  \__/
 *         \ \  \ \ \   __  \ \  \ \  \\ \  \ \   ___  \ \  \    \ \  \\|__| \  \ \   __\
 *          \ \  \ \ \  \ \  \ \  \ \  \\ \  \ \  \\ \  \ \  \____\ \  \    \ \  \ \  \_|
 *           \ \__\ \ \__\ \__\ \__\ \__\\ \__\ \__\\ \__\ \_______\ \__\    \ \__\ \__\
 *            \|__|  \|__|\|__|\|__|\|__| \|__|\|__| \|__|\|_______|\|__|     \|__|\|__|
 */
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace Portal\Controller;
use Common\Controller\HomebaseController;
use Common\Lib\Currency;
class ZipcodeController extends HomebaseController {
	public function index() {
	    header('HTTP/1.0 404 Not Found.');
        echo $this->not_found;
	    exit;
    }
    public function  get_csv_data($csvFile=false){
        $returnArray  = array();
        if(file_exists($csvFile)){
            $csvFile  = fopen($csvFile,'r');
            $i=0;$tempArray=array();
            while ($data = fgetcsv($csvFile)) {
                if($i==0){
                    $tempArray = $data;
                }else{
                    $itemArray = array();
                    if(is_array($data)){
                        foreach($data as $key=>$item){
                            $getKey = trim($tempArray[$key]);
                            $itemArray[$getKey]=$item;
                        }
                    }
                    $returnArray[] = $itemArray;
                }
                $i++;
            }
            fclose($csvFile);
        }
        return $returnArray;
    }
    public function write(){
        set_time_limit(0);
        $array = array(
            'gaoxs.csv','lianhs.csv','jils.csv','jiayis.csv','miaolx.csv','nantx.csv','penghx.csv',
            'pingdx.csv','taibs.csv','taids.csv','tains.csv','taizs.csv','taoys.csv','xinbs.csv',
            'xinzx.csv','yilx.csv','yunlx.csv','zhanghx.csv',
        );
        $file = './zipcode/1.csv';
        $area_zipcode = $this->get_csv_data($file);
        $allZipCode = array_column($area_zipcode,'code','zipcode');
        foreach($array as $name){
            $getData = $this->get_csv_data('./zipcode/'.$name);echo $getData.'<br />';
            foreach($getData as $key=>$item){
                $getCode = $allZipCode[$item['郵碼']];
                if($getCode){
                    $add_data = array(
                        'area_code' => $getCode,
                        'zipcode' => $item['郵碼'],
                        'province' => $item['縣市'],
                        'area' => $item['鄉鎮市區'],
                        'village' => $item['村里名'],
                        'street' => $item['路街名'],
                        'segment' => $item['段名'],
                        'lane' => $item['巷名'],
                        'lane_number' => $item['巷號'],
                        'alley' => $item['弄名'],
                        'alley_number' => $item['弄號'],
                        'neighbor_start' => $item['鄰起始'],
                        'neighbor_end' => $item['鄰結束'],
                        'number_start' => $item['號起始'],
                        'number_start_number' => $item['號起始之號'],
                        'number_end' => $item['號結束'],
                        'number_end_number' => $item['號結束之號'],
                        'neighbor_regx' => $item['鄰/號規則'],
                    );
                    D('Common/ZipcodeArea')->data($add_data)->add();
                }
            }
        }
        echo 'ok';
    }
}


