<?php

$path_to_root = "../..";
$page_security = 'SA_BACKUP';

include_once($path_to_root . "/includes/ui/allocation_cart.inc");
include_once($path_to_root . "/sales/includes/cart_class.inc");
include($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/banking.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/ui/contacts_view.inc");
include($path_to_root . "/admin/db/shipping_db.inc");

include_once("include/util.php");
include_once("lib/PHPExcel/IOFactory.php");
include_once("lib/PHPExcel.php");

//debugger
include 'ChromePhp.php';
function logger($msg){
    ChromePhp::log($msg);
}


ini_set('memory_limit', '1024M');

$js = "";
//Page start
page(_($help_context = "Postfinance data import"), false, false, $js);

if (isset($_POST["Upload"]) && can_process()) {
    submit_process();
    display_notification(_("All data has been uploaded"));
    end_page();
    exit();
}


div_start('postfinance');

start_form(true);
br();
echo "<center><h3>Postfinance file uploader</h3></center>";
start_table(TABLESTYLE2);
bank_accounts_list_row(_("Postfinance bank acccount:"), 'bank_account', null, true);
file_row(_("Postfinance file") . ":", 'file_in', 'file_in');
end_table(1);
submit_center_first("Upload", "Upload", "Upload", false);
end_form();

div_end();

end_page();

function submit_process() {
    $upload_result = process_upload("file_in");
    if (!$upload_result) {
        display_error(_("Error upload file"));
        return;
    }
    processData($upload_result);
}

function processData($upload_result){
    $objPHPExcel = PHPExcel_IOFactory::load($upload_result);
    $sheet_no = $objPHPExcel->getSheetCount();
    for($n=0;$n<$sheet_no;$n++){
        processSheetPerMonth($objPHPExcel->getSheet($n));
    }
}

function processSheetPerMonth($sheet){
    // column start from 0, row start from 1
    
    for ($j = 2; $j < 5000; $j++) {
        $datum = $sheet->getCellByColumnAndRow(0, $j)->getValue();
        $amount = $sheet->getCellByColumnAndRow(2, $j)->getValue();
        $order_no = $sheet->getCellByColumnAndRow(5, $j)->getValue();
        
        if($datum==null){
            break;
        }
        if($order_no==null){
            continue;
        }
        
        if(is_a($datum,"PHPExcel_RichText")){
            $datum = $datum->getPlainText();
        }
        insertTransaction($datum,$amount,$order_no);
    }
}

function insertTransaction($datum,$amount_in,$order_no){
    
    $bruto = 0;
    $fee = 0;
    $nett = 0;
    
    $ref = "sales order ".$order_no;
    $invoice = get_debtor_trans_by_ref($ref);
    if(!$invoice){
        return;
    }
    
    logger($invoice);
    
    $datum = explode(".", $datum);
    $datum[2] = "20".trim($datum[2]);
    
    $new_datum = array();
    $new_datum[0] = $datum[2];
    $new_datum[1] = $datum[1];
    $new_datum[2] = $datum[0];
    
    $new_datum = implode("-", $new_datum);
    
    $date =  sql2date($new_datum);
    
    $AllocCart = new allocation(ST_CUSTPAYMENT, 0, $invoice["debtor_no"], PT_CUSTOMER);

    $type = ST_SALESINVOICE;
    $type_no = 1;
    $date_ = $date;
    $due_date = $date;
    $amount = $amount_in;
    $amount_allocated = 0;
    $current_allocated = $amount_in;
    $ref = "postfinance ".$order_no;
    $memo = "imported from postfinance ".$order_no;
    
    $AllocCart->allocs[0] = new allocation_item($type,$type_no,$date,$due_date,$amount,$amount_allocated,$current_allocated,$ref);
    
    $payment_no = write_customer_payment($AllocCart->trans_no, $invoice["debtor_no"], $invoice["branch_code"],$_POST['bank_account'], $date, $ref, $amount, 0, $memo);
    
    add_cust_allocation($amount,ST_CUSTPAYMENT, $payment_no,ST_SALESINVOICE, $invoice["trans_no"], $invoice["debtor_no"], $date);
    
    update_debtor_trans_allocation(ST_SALESINVOICE, $invoice["trans_no"], $invoice["debtor_no"]);
}