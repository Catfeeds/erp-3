<?php
/**
 * 运单模板
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Report\Controller
 */
namespace Product\Controller;
use Common\Controller\HomebaseController;

class PdfController extends HomebaseController{
    protected $Waybill;
    public function _initialize() {
        parent::_initialize();
        $this->Waybill=D("Common/WaybillTemplate");
    }
    function mb_str_split($str,$split_length=1,$charset="UTF-8",$total_len=false){
        if(func_num_args()==1){
            return preg_split('/(?<!^)(?!$)/u', $str);
        }
        if($split_length<1)return false;
        if($total_len){
            $len = $total_len;
        }else{
            $len = mb_strlen($str, $charset);
        }
        $arr = array();
        for($i=0;$i<$len;$i+=$split_length){
            $s = mb_substr($str, $i, $split_length, $charset);
            $arr[] = $s;
        }
        return $arr;
    }
    protected function subpart($cont,$l,$utf){
        $len = mb_strlen($cont, $utf);
        for($i=0; $i<$len; $i+=$l)
            $arr[] = mb_substr($cont, $i, $l, $utf);
        return $arr;
    }
    function utf8_str_split($str, $split_len = 1){
        if (!preg_match('/^[0-9]+$/', $split_len) || $split_len < 1)
            return FALSE;
        $len = mb_strlen($str, 'UTF-8');
        if ($len <= $split_len)
            return array($str);
        preg_match_all('/.{'.$split_len.'}|[^\x00]{1,'.$split_len.'}$/us', $str, $ar);
        return $ar[0];
    }
    /**
     * 打印
     */
    public function page_print(){
        header("Content-type: text/html; charset=utf-8");
        $setPath = './'.C("UPLOADPATH").'qrcode'."/".date('Ym')."/";
        if(!is_dir($setPath)){
            mkdir($setPath,0777,TRUE);
        }
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $print_data     = array();
        $skuid=$_REQUEST['skuid'];
        $pdfnum=I('get.pdfnum'); //获取打印数量
        $skunum=I('get.skunum'); //获取每个产品的数量
        if(!empty($skunum)) $skunumArr = explode(',',$skunum);
        if(strpos($skuid,',')){ //有多个值
            $where['ps.id_product_sku'] = array("IN",$_REQUEST['skuid']) ;
        }else{
            $where['ps.id_product_sku'] = $skuid;
        }
        $template=array(
            "font_size"=>12,
            "height"=>75,
            "width"=>112,
        );
        $M = new \Think\Model;
        // 查询所有分类 zx 11/16 
        $category = M("Category")->getField('id_category,title');
        $pro_table = D("Common/Product")->getTableName();
        $pro_s_table = D("Common/ProductSku")->getTableName();
        /* 查询所有分类 zx 11/16 */
        $category = M("Category")->getField('id_category,title');
        $list = $M->table($pro_table.' AS p LEFT JOIN '.$pro_s_table.' AS ps ON p.id_product=ps.id_product')
            ->field('ps.barcode,p.title,p.inner_name,ps.title as skutitle,ps.id_product_sku,p.id_category,ps.sku')->where($where)->order(" find_in_set(id_product_sku,'$skuid') ")->select();
//        生成二维码
//        vendor('QRcode.QRcodeManager');
//        $qrcode = new \QRcodeManager();
        if($list){
            foreach($list as $ord_key=>$item){
                $cat_current = M('Category')->where(array('id_category'=>$item['id_category']))->find();
                if($cat_current['parent_id']!=0){
                    $cat_top = M('Category')->where(array('id_category'=>$cat_current['parent_id']))->find();
                }
                $field=array(
                    "inner_name"=>"2,12",
                    "skutitle"=>"5,32",//SKU属性名居中显示    liuruibin   20171202
                    "barcodeImg"=>"10,43",
                    "barcode"=>"40,73"
                );
                $font_size=12;
                $titleislong=false;
                foreach($field as $key=>$value){
                    $is_image=false;
                    $subpart=false;
                    $width=89;
                    $position = $value?explode(',',$value):array();
                    if($titleislong){
                        $position[1]=$position[1]+10;
                    }
                    switch($key){
                        // 去除(注释)内部名 属性名 ，增加分类名称显示 zx 11/16 
                        case 'inner_name':
                            //$label = '内部名:'.$item['inner_name'];
                            $label = $category[$item['id_category']].':'.trim($item['inner_name']);
                            $total_width = 95;
                            $line_len = ceil($total_width/($font_size));
                            $total_len  = mb_strlen($label,"UTF-8");
                            if($total_len>$line_len){
                                $line_len = ceil($total_width/(7));
                                $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                $font_size=11;
                            }
                            $label    = $subpart?implode('<br/>',$subpart):$label;

                            break;
                        case 'skutitle':
                            $item['skutitle'] = $item['skutitle']?$item['skutitle']:'无';
                            $label = $item['skutitle'];
                            $total_width = $width;
                            $line_len = ceil($total_width/($font_size));
                            $total_len  = mb_strlen($label,"UTF-8");
                            if($total_len>$line_len){
                                $line_len = ceil($total_width/(8));
                                $subpart  = $this->mb_str_split($label, $line_len, "utf8",$total_len);
                                $font_size=11;
                            }
                            $label    = $subpart?implode('<br/>',$subpart):$label;
                            break;
                        case 'barcode':
                            $label = $item['barcode'];
                            $font_size=10;
                            break;
                        case 'barcodeImg':
                            $label = $item['barcode'];
                            $barcode = $this->barcode($label,0);
                            $label = realpath('./'.$barcode);
                            $is_image = true;
                            $width=90;
                            $height=20;
                            break;

                    }
                    $field_array[$key]= array(
                        'left' => $position[0],
                        'top' => $position[1],
                        'width' => $width,
                        'height' => $height,
                        'label' => $label,
                        'font_size' => $font_size,
                        'is_image'  => $is_image,
                    );
                }
                $print_data[] = $field_array;
            }
        }


        $pdf_data = array();

        if(!empty($skunumArr)){ //批量性导入
            foreach($skunumArr as $key=>$value){
                for($n=0;$n<$value;$n++){
                    $newData[$key][]=$print_data[$key];
                }
                foreach($newData[$key] as $item_data){
                    $pdf_data[] = array(
                        'template' => $template,
                        'data' => $item_data,
                    );
                }
            
            }
        }else{ //非批量性导入
            foreach($print_data as $item_data){
                $pdf_data[] = array(
                    'template' => $template,
                    'data' => $item_data,
                );
            }
        }

        $page_show_number =1;
        $this->out_pdf($pdf_data,$template['width'],$template['height'],$page_show_number);
    }

    public function out_pdf($data=array(),$width,$height,$number=1){
        import("tFPDF");
        $page_width = $width;
        $page_height = $height*$number;
        $orientation = $number>1?'p':'l';
        $pdf = new \tFPDF($orientation,'pt',array($page_width,$page_height));
        if(is_array($data)){
            $i = 0;
            foreach($data as $d_key=>$item){
                $page_flag = $d_key%$number;
                if($number==1 or $d_key==0){
                    $pdf->AddPage();
                }elseif($number>1 && $page_flag==0){
                    $pdf->AddPage();$i = 0;
                }
                $pdf->AddFont('kaiu','','kaiu.ttf',true);
                $pdf->SetFont('kaiu','',$item['template']['font_size']);
                if($item['template']['waybill_image']){
                    $background_height = $number>1?$i*$height:0;
                    $pdf->Image($item['template']['waybill_image'], 0,$background_height, $width, $height);
                }
                if(is_array($item['data'])){
                    foreach($item['data'] as $item_data){
                        $item_top = $number>1?$item_data['top']+$height*$i:$item_data['top'];
                        if($item_data['is_image']){
                            $item_data['height'] = isset($item_data['height'])?$item_data['height']:'';
                            $pdf->Image($item_data['label'],$item_data['left'], $item_top, $item_data['width'], $item_data['height']);
                        }else{
                            $pdf->AddFont('kaiu','','kaiu.ttf',true);
                            $pdf->SetFont('kaiu','',$item_data['font_size']);
                            if(strpos($item_data['label'],'<br')!=false){
                                $get_label = explode('<br/>',$item_data['label']);
                                $height_top = ceil($item_data['font_size']/3);
                                $height_top = $height_top>16?$height_top:9;
                                foreach($get_label as $key=> $label){
                                    $top  = $key*$height_top;
                                    $pdf->Text($item_data['left'], $item_top+$top, $label);
                                }
                            }else{
                                $pdf->Text($item_data['left'], $item_top, $item_data['label']);
                            }
                        }
                    }
                }
                $i++;
            }


        }
        $pdf->Output();
        exit();
    }
    private function barcode($barcode_code='empty',$is_number=12){
        $setPath = './'.C("UPLOADPATH").'barcode'."/".date('Ym')."/";
        if(!is_dir($setPath)){
            unlink($setPath);
            mkdir($setPath,0777,TRUE);
        }
        $file = $setPath.$barcode_code.'.png';
        if(file_exists($file)){
            return str_replace('./','/',$file);
        }
        import('BCGFontFile');
        import('BCGColor');
        import('BCGDrawing');
        import('BCGcode128');
        $Arial = 'Arial.ttf';
        $font = new \BCGFontFile($Arial,18);
        $color_black = new \BCGColor(0, 0, 0);
        $color_white = new \BCGColor(255, 255, 255);
        $drawException = null;
        try {
            $code = new \BCGcode128();
            $code->setScale(2); // Resolution
            $code->setThickness(30); // Thickness
            $code->setForegroundColor($color_black); // Color of bars
            $code->setBackgroundColor($color_white); // Color of spaces
            $code->setFont($is_number); // Font (or 0)
            $code->parse($barcode_code); // Text            $code->parse($text);
        } catch(\Exception $exception) {
            $drawException = $exception;
        }

        /* Here is the list of the arguments
        1 - Filename (empty : display on screen)
        2 - Background color */

        $drawing = new \BCGDrawing($file, $color_white);
        if($drawException) {
            $drawing->drawException($drawException);
        } else {
            $drawing->setBarcode($code);
            $drawing->draw();
        }
//        header('Content-Type: image/png');
//        header('Content-Disposition: inline; filename="barcode.png"');
        $drawing->finish(\BCGDrawing::IMG_FORMAT_PNG);
        return str_replace('./','/',$file);
    }

    /*
     * 打印货架条码
     * */
    public function code_page_print(){
        header("Content-type: text/html; charset=utf-8");
        $setPath = './'.C("UPLOADPATH").'qrcode'."/".date('Ym')."/";
        if(!is_dir($setPath)){
            mkdir($setPath,0777,TRUE);
        }
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $print_data     = array();
        $bar_code_area=$_REQUEST['bar_code_area'];
        $storage_rack_n=$_REQUEST['storage_rack_n'];
        $storage_rack_nc=$_REQUEST['storage_rack_nc'];
        $location_n=$_REQUEST['location_n'];
        $bar_code = $bar_code_area.$storage_rack_n."-".$storage_rack_nc."-".$location_n;
        $template=array(
            "font_size"=>12,
            "height"=>85,
            "width"=>184,
        );
        $field=array(
            "barcodeImg"=>"40,35",
            "barcode"=>"40,27"
        );
        $font_size=8;
        $titleislong=false;
        foreach($field as $key=>$value){
            $position = $value?explode(',',$value):array();
            if($titleislong){
                $position[1]=$position[1]+10;
            }
            switch($key){
                case 'barcode':
                    $label = $bar_code;
                    $font_size=28;
                    $is_image = false;
                    break;
                case 'barcodeImg':
                    $label = $bar_code;
                    $barcode = $this->barcode($label,0);
                    $label = realpath('./'.$barcode);
                    $is_image = true;
                    $width=100;
                    $height=40;
                    break;
            }
            $field_array[$key]= array(
                'left' => $position[0],
                'top' => $position[1],
                'width' => $width,
                'height' => $height,
                'label' => $label,
                'font_size' => $font_size,
                'is_image'  => $is_image,
            );
        }
        $print_data[] = $field_array;
        $pdf_data = array();
        foreach($print_data as $item_data){
            $pdf_data[] = array(
                'template' => $template,
                'data' => $item_data,
            );
        }
        $page_show_number =1;
        $this->out_pdf($pdf_data,$template['width'],$template['height'],$page_show_number);

    }
}