<?php

$path_to_root = "../..";
$page_security = 'SA_BACKUP';

include_once($path_to_root . "/includes/ui/allocation_cart.inc");
include_once($path_to_root . "/sales/includes/cart_class.inc");
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/banking.inc");
include_once($path_to_root . "/sales/includes/db/customers_db.inc");
include_once($path_to_root . "/sales/includes/ui/sales_order_ui.inc");
include_once($path_to_root . "/admin/db/shipping_db.inc");
include_once($path_to_root . "/gl/includes/db/gl_db_rates.inc");
include_once("include/util.php");
include_once("include/parsecsv.lib.php");

ini_set('max_execution_time', 300);
ini_set('memory_limit', '1024M');
ini_set('upload_max_filesize','20M');

$js = "";

if(isset($_GET["debug"])){
    $_POST["bank_account"] = 4;
    $payment = processCSVPayment("files/paypal 2016.CSV");
    processData("files/fs-orders-2016.xml", $payment);
    exit();
}

//Page start
page(_($help_context = "Order data import"), false, false, $js);

if (isset($_POST["Upload"]) && can_process()) {
    submit_process();
    display_notification(_("Upload successfull"));
    end_page();
    exit();
}

div_start('inventory');

start_form(true);

br();

echo "<center><h3>Inventory file uploader</h3></center>";

start_outer_table(TABLESTYLE2);

table_section(1);

table_section_title(_("Order"));

//------------------------------------------------------------------------------------
file_row(_("Order XML file") . ":", 'file_in', 'file_in');

file_row(_("Paypal payment") . ":", 'file_csv', 'file_csv');

bank_accounts_list_row(_("Paypal bank acccount:"), 'bank_account', null, true);

end_outer_table(1);

submit_center_first("Upload", "Upload", "Upload", false);

end_form();

div_end();

end_page();

function extract_item($items,$sub_total){
    $data = array();
    $idx=0;
    if(count($items)==0){
        $data[0]["stock_id"] = "T0";
        $data[0]["description"] = "Sales order item (tax 0%)";
        $data[0]['price'] = $sub_total;
        $data[0]['tax'] = 0;
        $data[0]['qty'] = 1;
    }
    foreach($items as $tax_name=>$value){
        $value = floatval($value);
        if(contains("2,5%",$tax_name)){
            $data[$idx]["stock_id"] = "T25";
            $data[$idx]["description"] = "Sales order item (tax 2,5%)";
            $data[$idx]['price'] = ($value*100)/2.5;
            $data[$idx]['tax'] = $value;
            $data[$idx]['qty'] = 1;
        }elseif (contains("8%",$tax_name)){
            $data[$idx]["stock_id"] = "T8";
            $data[$idx]["description"] = "Sales order item (tax 8%)";
            $data[$idx]['price'] = ($value*100)/8;
            $data[$idx]['tax'] = $value;
            $data[$idx]['qty'] = 1;
        }
        $idx++;
    }
    return $data;
}

function trim_object($order){
    $order->firstname = trim($order->firstname);
    $order->lastname = trim($order->lastname);
    
    $order->payment_firstname = trim($order->payment_firstname);
    $order->payment_lastname = trim($order->payment_lastname);
    
    $order->shipping_firstname = trim($order->shipping_firstname);
    $order->shipping_lastname = trim($order->shipping_lastname);
    
    return $order;
}

function processData($upload_result,$payment_paypal) {
    $feed = html_entity_decode(file_get_contents($upload_result));
    $feed = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $feed);
    $file = fopen("files/test.xml", "w");
    fwrite($file, $feed);
    fclose($file);
    $orders = simplexml_load_file("files/test.xml");

    $i=0;
    foreach ($orders->ORDER as $order) {
        $order = trim_object($order);
        // get customer info
        $debtor_ref = $order->lastname." ".$order->firstname."_".$order->customer_id;
        
        $customer = get_customer_by_ref($debtor_ref);
        if($customer["debtor_no"] == null){
            $customer = get_customer_by_fuzzy_ref($order->customer_id);
        }
        
        $branch = get_cust_branch_by_ref($debtor_ref);
        if($branch["branch_code"] == null){
            $branch = get_cust_branch_by_ref($order->customer_id,false);
        }

        $order_name = $order->payment_firstname." ".$order->payment_lastname;
        $order_ref = $order->payment_lastname." ".$order->payment_firstname;
        
        // setup branch address
        $address = "";
        if($order->payment_company != ""){
            $address .= $order->payment_company."\n";
        }
        $address .= trim($order->payment_address_1." ".$order->payment_address_2)."\n";
        $address .= trim($order->payment_postcode." ".$order->payment_city)."\n";
        if($order->shipping_country != "Switzerland" && $order->shipping_country != ""){
            $address .= $order->payment_country;
        }
        $address = rtrim($address);
        
        if($branch["br_name"] != $order_name && $order_name != "- -" && $order->customer_id == 2){
            
            add_customer($order_name, $order_ref."_".$order->customer_id, $address, "", get_company_currency(), 
                $customer['dimension_id'], $customer['dimension2_id'], $customer['credit_status'], 
                $customer['payment_terms'], $customer['discount'], $customer['pymt_discount'], 
                $customer['credit_limit'], $customer["payment_terms"], "");
            $customer = get_customer_by_ref($order_ref."_".$order->customer_id);
        }
        
        if($branch["br_name"] != $order_name){
            add_branch(
                $customer["debtor_no"], $order_name, $order_ref."_".$order->customer_id, $address, 
                $branch["salesman"], $branch["area"], $branch['tax_group_id'], '',
                get_company_pref('default_sales_discount_act'), 
                get_company_pref('debtors_act'), get_company_pref('default_prompt_payment_act'), 
                $branch['default_location'], $address, 0, $branch['default_ship_via'], "", ""
            );
            $branch = get_branch(db_insert_id());
        }
        
        $shipper = get_shipper_id($order->shipping_method);
        
        if($shipper==null){
            $shipper = get_shipper(1);
        }
        
        if($customer["debtor_no"]==null || $branch["branch_code"]==null || $shipper["shipper_id"]==null){
            continue;
        }       
        
        $order->date_added = sql2date(explode(" ", $order->date_added)[0]);
        
        $totals = $order->ORDERTOTALS->ORDERTOTAL;
        $sub_total = 0;
        $shipping = 0;
        $total = 0;
        
        $items = array();
        
        $order->currency_code[0] = get_company_currency();
        
        foreach($totals as $total_item){
            if($total_item->code=="tax"){
                $items["v_".$total_item->title] = floatval($total_item->value);
            }else{
                ${$total_item->code} = floatval($total_item->value);  // initiate sub_total, shipping, and total
            }
        }
                
        $data = extract_item($items,$sub_total);
        
        $cart = null;
        $cart = new Cart(ST_SALESINVOICE, 0);
        
        // setup delivery address
        $cart->deliver_to = $order->shipping_firstname." ".$order->shipping_lastname;
        
        $cart->delivery_address = "";
        if($order->shipping_company != "")
            $cart->delivery_address .= $order->shipping_company."\n";
        $cart->delivery_address .= trim($order->shipping_address_1." ".$order->shipping_address_2)."\n";
        $cart->delivery_address .= trim($order->shipping_postcode." ".$order->shipping_city)."\n";
        if($order->shipping_country != "Switzerland" && $order->shipping_country != "")
            $cart->delivery_address .= $order->shipping_country;
               
        $cart->delivery_address = trim(str_replace("\n\n", "\n", $cart->delivery_address));
        
        $cart->reference = "sales order ".$order->order_id;
        $cart->document_date = $order->date_added;
        $cart->due_date = $order->date_added;
        
        // save original order as comment
        $item_list = $order->ORDERPRODUCTS->ORDERPRODUCT;
        $item_data = "";
        foreach($item_list as $item){
            $item_data .= $item->name." ".$item->model." @".$order->currency_code[0]." ". number_format(floatval($item->price),4)." x ".$item->quantity."\n\n";
        }
        $cart->Comments = "Invoice for sales order ".$order->order_id."\n".$item_data;
                
        $cart->payment = 1;
        $cart->payment_terms = get_payment_terms(1);
        $cart->ship_via = $shipper["shipper_id"];
        $cart->freight_cost = $shipping;
        $cart->customer_id = $customer["debtor_no"];
        $cart->Branch = $branch["branch_code"];
        $cart->sales_type = 1;
        $cart->Location = "DEF";
        
        foreach ($data as $item) {
            add_to_order($cart, $item['stock_id'], $item['qty'], $item['price'], 0, $item['description']);
        }
        $order_id = $cart->write(1);
        
        // start paypal payment import
        
        $paypal_code = $order->order_id." - ".$order->payment_firstname." ".$order->payment_lastname;
        
        if(isset($payment_paypal[$paypal_code])){
            $bruto = 0;
            $fee = 0;
            $nett = 0;
            

            foreach($payment_paypal[$paypal_code] as $paid){
                $bruto += floatval($paid["Brutto"]);
                $fee += floatval($paid["Gebühr"]);
                $nett += floatval($paid["Netto"]);
            }

            if($payment_paypal[$paypal_code][0]["Währung"]!= get_company_currency()){
                $exrate = floatval($order->currency_value);
                $bruto = round($bruto/$exrate,4);
                $fee = round($fee/$exrate,4);
                $nett = round($nett/$exrate,4);
            }
            $diff = round($total-$bruto,1);
            if( $diff == 0 ){
                $date =  sql2date($payment_paypal[$paypal_code][0]["Datum"]);
                $AllocCart = new allocation(ST_CUSTPAYMENT, 0, $cart->customer_id, PT_CUSTOMER);
                
                $type = ST_SALESINVOICE;
                $type_no = 1;
                $date_ = $date;
                $due_date = $date;
                $amount = $bruto;
                $amount_allocated = 0;
                $current_allocated = $nett;
                $ref = $paypal_code;
                $AllocCart->allocs[0] = new allocation_item($type,$type_no,$date,$due_date,$amount,$amount_allocated,$current_allocated,$ref);
               
                $payment_no = write_customer_payment(
                    $AllocCart->trans_no, $cart->customer_id, $cart->Branch,
                    $_POST['bank_account'], $date, $paypal_code,
                    $bruto, 0, $paypal_code, 0, -$fee, $nett-$fee);
                add_cust_allocation($bruto,ST_CUSTPAYMENT, $payment_no,ST_SALESINVOICE, $order_id, $date);
                update_debtor_trans_allocation(ST_SALESINVOICE, $order_id);
            }
        }
    }
}

function processCSVPayment($file){
    $csv = new parseCSV();
    $csv->delimiter = ",";
    $csv->parse($file);
    $retval = array();
    foreach($csv->data as $entry){
        $objdate = date_create_from_format ( "m/d/Y" , $entry["Datum"]);
        $entry["Datum"] =  date_format($objdate,"Y-m-d");
        $retval[$entry["Rechnungsnummer"]][] = $entry;
    }
    return $retval;
}

function submit_process() {
    $payment_csv = process_upload("file_csv");
    $upload_result = process_upload("file_in");
    
    if (!$upload_result || !$payment_csv) {
        display_error(_("Error upload file"));
        return;
    }
    
    $paymentCSV = processCSVPayment($payment_csv);
    processData($upload_result, $paymentCSV);
}
