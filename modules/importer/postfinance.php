<?php
$path_to_root = "../..";
include_once($path_to_root . "/includes/ui.inc");
include_once("include/util.php");
include_once("lib/PHPExcel/IOFactory.php");
include_once("lib/PHPExcel.php");


reformat("files/Year2016.xlsx");


function reformat($file){
    set_time_limit(100);

    $objPHPExcel = PHPExcel_IOFactory::load($file);
    $output = new PHPExcel();
    $idx = 0;

    foreach ($objPHPExcel->getAllSheets() as $sheet){
        $output = processSheet($sheet,$output,$idx);
        $idx++;
    }
    $output->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="PostfinanceReformat.xlsx"');
    header('Cache-Control: max-age=0');

    $objWriter = PHPExcel_IOFactory::createWriter($output, 'Excel2007');
    ob_get_clean();
    $objWriter->save('php://output');
}

function processSheet($sheet,$output,$idx){
    $retval = new PHPExcel_Worksheet($output,$sheet->getTitle());
    $output->addSheet($retval, $idx);
    
    $entry = array();
    $curr_date = "";
    $row = 1;
    while(1){
        $data_row = array();
        for($col=0;$col<4;$col++){
            $data_row[] = $sheet->getCellByColumnAndRow($col,$row)->getValue();
        }
        if($sheet->getCellByColumnAndRow(1,$row)->getValue()==""){
            break;
        }
        $row++;

        if($data_row[0]!=""){
            $curr_date = $data_row[0];
        }
        
        if($data_row[2]!="" ||$data_row[3]!=""){          
            //create new object to work
            $obj = new PosftFinanceEntry();
            $obj->date = $curr_date;
            $obj->desc = $data_row[1];
            $obj->credit = $data_row[2];
            $obj->debit = $data_row[3];
            $entry[] = $obj;
        }else{
            // append desc to last item in array
            $entry[count($entry)-1]->desc .= "\r\n".$data_row[1];
        }
    }
    
    $retval->setCellValueExplicitByColumnAndRow(0, 1, "Datum");
    $retval->setCellValueExplicitByColumnAndRow(1, 1, "Text");
    $retval->setCellValueExplicitByColumnAndRow(2, 1, "Gutschrift");
    $retval->setCellValueExplicitByColumnAndRow(3, 1, "Lastschrift");
    $retval->setCellValueExplicitByColumnAndRow(4, 1, "MITTEILUNGEN");
    
    foreach($entry as $row=>$data){
        $cur_row = $row+2;

        $retval->setCellValueExplicitByColumnAndRow(0, $cur_row, $data->date);
        $retval->setCellValueExplicitByColumnAndRow(1, $cur_row, $data->desc);
        $retval->setCellValueExplicitByColumnAndRow(2, $cur_row, $data->credit, PHPExcel_Cell_DataType::TYPE_NUMERIC);
        $retval->setCellValueExplicitByColumnAndRow(3, $cur_row, $data->debit, PHPExcel_Cell_DataType::TYPE_NUMERIC);
        
        $order_msg = explode("MITTEILUNGEN", strtoupper($data->desc));
        if(count($order_msg)>1){
            $retval->setCellValueExplicitByColumnAndRow(4, $cur_row, $order_msg[1]);
        }
        
        $retval->getStyleByColumnAndRow(1, $cur_row)->getAlignment()->setWrapText(true);
        $retval->getStyleByColumnAndRow(2, $cur_row)->getNumberFormat()->setFormatCode(0);
        $retval->getStyleByColumnAndRow(3, $cur_row)->getNumberFormat()->setFormatCode(0);
        $retval->getStyleByColumnAndRow(4, $cur_row)->getAlignment()->setWrapText(true);
        
        $no_lines = calculateLines($data->desc);
        $retval->getRowDimension($cur_row)->setRowHeight($no_lines*15);
        
        $retval->getColumnDimensionByColumn(1)->setAutoSize(true);
        $retval->getColumnDimensionByColumn(2)->setAutoSize(true);
        $retval->getColumnDimensionByColumn(3)->setAutoSize(true);
        $retval->getColumnDimensionByColumn(4)->setAutoSize(true);
        $retval->getColumnDimensionByColumn(5)->setAutoSize(true);
        
        if (strpos(strtoupper($data->desc),"EINZAHLUNGSSCHEIN")===false){
            
        }else{
            $retval->getStyleByColumnAndRow(0, $cur_row,4, $cur_row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('FF0000');
        }
    }
    return $output;
}

function calculateLines($str){
    return count(explode("\r\n", $str));
}

class PosftFinanceEntry{
    var $date;
    var $desc;
    var $credit;
    var $debit;
    
}