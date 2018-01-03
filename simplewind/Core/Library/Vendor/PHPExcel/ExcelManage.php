<?php

class ExcelManage {

    public function __construct(){
        require_once(dirname(__FILE__).'/PHPExcel.php');
    }

    /**
     * @param $result_list
     * @param $row_map
     * @param string $title
     *
     * row_map示例
     * $row_map = array(
            array('name'=>'部门', 'key'=> 'title'),
            array('name'=>'发货单数', 'key'=> 'count_delivered'),
            array('name'=>'签收单', 'key'=> 'count_signed'),
            array('name'=>'签收率', 'key'=> 'rate_signed')
        );
     */
    public function export($result_list, $row_map, $title="导出数据"){
        $excel = new \PHPExcel();
        $column_count = count($row_map);
        //A的ASCII码65,设置每列的列标号
        for($i = 0; $i < $column_count; $i++){
            $row_map[$i]['column'] = chr(65+$i);
        }

        foreach ($row_map as $column) {
            $excel->getActiveSheet()->setCellValue($column['column'].'1', $column['name']);
        }

        $idx = 2;
        foreach($result_list as $item){
            foreach ($row_map as $row) {
                $excel->getActiveSheet()->setCellValue($row['column'].$idx, $item[$row['key']]);
            }
            ++$idx;
        }
        $excel->getActiveSheet()->setTitle("{$title}.xlsx");
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename={$title}.xlsx");
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');die();
    }
}