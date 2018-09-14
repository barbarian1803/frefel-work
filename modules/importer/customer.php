<?php

$path_to_root = "../..";
$page_security = 'SA_BACKUP';

include_once("include/CustomerData.php");
include_once("include/CustomerAddress.php");
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

ini_set('memory_limit', '1024M');

$country_array = array();
$shipping_method = array();

$js = "";
//Page start
page(_($help_context = "Customer data import"), false, false, $js);

if (isset($_POST["Upload"]) && can_process()) {
    submit_process();
    display_notification(_("All data has been uploaded"));
    end_page();
    exit();
}


div_start('customer');

start_form(true);

br();

echo "<center><h3>Customer file uploader</h3></center>";
start_table(TABLESTYLE2);

table_section_title(_("File to upload"));
file_row(_("Customer file") . ":", 'file_in', 'file_in');

table_section_title(_("Default customer data"));
sales_types_list_row(_("Sales Type/Price List:"), 'sales_type');

table_section_title(_("Default customer sales option"));
percent_row(_("Discount Percent:"), 'discount');
percent_row(_("Prompt Payment Discount Percent:"), 'pymt_discount');
amount_row(_("Credit Limit:"), 'credit_limit');
payment_terms_list_row(_("Payment Terms:"), 'payment_terms');
credit_status_list_row(_("Credit Status:"), 'credit_status');
$dim = get_company_pref('use_dimension');
if ($dim >= 1) {
    dimensions_list_row(_("Dimension") . " 1:", 'dimension_id', null, true, " ", false, 1);
}
if ($dim > 1) {
    dimensions_list_row(_("Dimension") . " 2:", 'dimension2_id', null, true, " ", false, 2);
}
if ($dim < 1) {
    hidden('dimension_id', 0);
}
if ($dim < 2) {
    hidden('dimension2_id', 0);
}

//if (isset($SysPrefs->auto_create_branch) && $SysPrefs->auto_create_branch == 1) {
table_section_title(_("Default branch data"));
sales_persons_list_row(_("Sales Person:"), 'salesman', null);
locations_list_row(_("Default Inventory Location:"), 'location');
shippers_list_row(_("Default Shipping Company:"), 'ship_via');
sales_areas_list_row(_("Sales Area:"), 'area', null);
tax_groups_list_row(_("Tax Group:"), 'tax_group_id', null);
//}

table_section_title(_("Default additional info"));
textarea_row(_("General Notes:"), 'notes', null, 35, 5);

end_table(1);


submit_center_first("Upload", "Upload", "Upload", false);

end_form();

div_end();

end_page();

function processData($file) {
    set_time_limit(100);

    $objPHPExcel = PHPExcel_IOFactory::load($file);

    $rowsData = array();

    $customerSheet = $objPHPExcel->getSheet(0);
    $rowsData = processCustomer($customerSheet, $rowsData);

    $addressSheet = $objPHPExcel->getSheet(1);
    $rowsData = processAddress($addressSheet, $rowsData);

    $orderSheet = $objPHPExcel->getSheet(2);
    $rowsData = processOrder($orderSheet, $rowsData);

    $countrySheet = $objPHPExcel->getSheet(5);
    processCountry($countrySheet);

    return $rowsData;
}

function processCountry($countrySheet) {
    global $country_array;
    for ($j = 0; $j < 5000; $j++) {
        $row = $j + 1;

        if ($countrySheet->getCellByColumnAndRow(0, $row + 1)->getValue() == null) {
            break;
        }
        $id = $countrySheet->getCellByColumnAndRow(0, $row + 1)->getValue();
        $country = $countrySheet->getCellByColumnAndRow(1, $row + 1)->getValue();
        $country_array[$id] = $country;
    }
}

function processCustomer($customerSheet, $rowsData) {
    for ($j = 0; $j < 5000; $j++) {
        $row = $j + 1;

        if ($customerSheet->getCellByColumnAndRow(0, $row + 1)->getValue() == null) {
            break;
        }

        $data_array = array();

        for ($col = 0; $col < 21; $col++) {
            $cell = $customerSheet->getCellByColumnAndRow($col, $row + 1);
            $data_array[$col] = trim($cell->getValue());
        }
        $customer = new CustomerData($data_array);
        $rowsData[$customer->customer_id] = $customer;
    }
    return $rowsData;
}

function processAddress($addressSheet, $rowsData) {
    for ($j = 0; $j < 5000; $j++) {
        $row = $j + 1;

        if ($addressSheet->getCellByColumnAndRow(0, $row + 1)->getValue() == null) {
            break;
        }

        $data_array = array();

        for ($col = 0; $col < 12; $col++) {
            $cell = $addressSheet->getCellByColumnAndRow($col, $row + 1);
            $data_array[$col] = trim($cell->getValue());
        }
        $address = new CustomerAddress($data_array);
        $address->Type = "general";
        if (isset($rowsData[$address->Customer_id])) {
            $rowsData[$address->Customer_id]->address[$address->Type][] = $address;
        }
    }
    return $rowsData;
}

function processOrder($orderSheet, $rowsData) {
    global $shipping_method;

    for ($j = 0; $j < 5000; $j++) {
        $row = $j + 1;

        if ($orderSheet->getCellByColumnAndRow(0, $row + 1)->getValue() == null) {
            break;
        }

        $data_order = array();

        for ($col = 0; $col < 60; $col++) {
            $cell = $orderSheet->getCellByColumnAndRow($col, $row + 1);
            $data_order[$col] = trim($cell->getValue());
        }
        //update customer currency
        if (isset($rowsData[$data_order[6]])) {
            $rowsData[$data_order[6]]->currency = $data_order[52];
        }

        //Customer address payment
        $data_array = array();
        $data_array[0] = 1;    //dummy data
        $data_array[1] = $data_order[6];   //customer id
        $data_array[2] = $data_order[10];  //email
        $data_array[3] = $data_order[14];  //first name
        $data_array[4] = $data_order[15];  //last name
        $data_array[5] = $data_order[16];  //company
        $data_array[6] = $data_order[17];  //addr 1
        $data_array[7] = $data_order[18];  //addr 2
        $data_array[8] = $data_order[19];  //city
        $data_array[9] = $data_order[20];  //post code
        $data_array[10] = $data_order[22];  //country id
        $data_array[11] = $data_order[24];  //zone id

        $address = new CustomerAddress($data_array);

        $address->Type = "invoice";
        if (isset($rowsData[$address->Customer_id])) {
            $rowsData[$address->Customer_id]->address[$address->Type][] = $address;
        }
        //Customer address shipment
        $data_array = array();
        $data_array[0] = 1;  //dummy data  
        $data_array[1] = $data_order[6];   //customer id
        $data_array[2] = $data_order[10];  //email
        $data_array[3] = $data_order[28];  //first name
        $data_array[4] = $data_order[29];  //last name
        $data_array[5] = $data_order[30];  //company
        $data_array[6] = $data_order[31];  //addr 1
        $data_array[7] = $data_order[32];  //addr 2
        $data_array[8] = $data_order[33];  //city
        $data_array[9] = $data_order[34];  //post code
        $data_array[10] = $data_order[36];  //country id
        $data_array[11] = $data_order[37];  //zone id

        if ($data_order[40] != "") {
            $shipping_method[$data_order[40]] = $data_order[40];
        }


        $address = new CustomerAddress($data_array);
        $address->Type = "delivery";
        if (isset($rowsData[$address->Customer_id])) {
            $rowsData[$address->Customer_id]->address[$address->Type][] = $address;
        }
    }
    return $rowsData;
}

function processFile($uploaded_data) {
    global $country_array;
    global $shipping_method;

    begin_transaction();

    foreach ($shipping_method as $key => $shipper) {
        add_shipper($shipper, "", "", "", "");
    }

    foreach ($uploaded_data as $customer) {

        $cust_name = $customer->first_name . " " . $customer->last_name;
        $cust_ref = $customer->last_name . " " . $customer->first_name . "_" . $customer->customer_id;
        if (strlen($cust_ref) > 30) {
            $cust_ref = $customer->last_name . "_" . $customer->customer_id;
        }

        $customer->currency = get_company_pref('curr_default');
        
        if (isset($customer->address['general'])) {
            $contact = $customer->address["general"][0];
        } else {
            $contact = new CustomerAddress(array());
        }
        $country = (isset($country_array[$contact->Country_id]) && ($contact->Country_id != 204)) ? $country_array[$contact->Country_id] : "";
        
        $address = "";
        if($contact->Company != ""){
            $address .= $contact->Company."\n";
        }
        $address .= trim(trim($contact->Address_1) . " " . trim($contact->Address_2)) . "\n";
        $address .= trim(trim($contact->Postcode) . " " . trim($contact->City)) . "\n";
        $address = rtrim($address);
        $address .= trim($country) . "\n";
        $address = rtrim($address);
        
        add_customer($cust_name, $cust_ref, $address, "", $customer->currency, 
            $_POST['dimension_id'], $_POST['dimension2_id'], $_POST['credit_status'], 
            $_POST['payment_terms'], input_num('discount') / 100, 
            input_num('pymt_discount') / 100, input_num('credit_limit'), 
            $_POST['sales_type'], $_POST['notes']
        );
        
        $selected_id = $_POST['customer_id'] = db_insert_id();

        add_branch(
            $selected_id, $cust_name, $cust_ref, $address, $_POST['salesman'], 
            $_POST['area'], $_POST['tax_group_id'], '', 
            get_company_pref('default_sales_discount_act'), get_company_pref('debtors_act'), 
            get_company_pref('default_prompt_payment_act'), $_POST['location'], 
            $address, 0, $_POST['ship_via'], $_POST['notes'], ""
        );

        $selected_branch = db_insert_id();
//        foreach ($customer->address as $contact) {
//
//            if (is_array($contact)) {
//                foreach ($contact as $c) {
//                    insert_contact($c, $cust_ref,$customer,$selected_id,$selected_branch);
//                }
//            }else{
//                insert_contact($contact, $cust_ref,$customer,$selected_id,$selected_branch);
//            }
//        }
    }
    commit_transaction();
}

function insert_contact($contact,$cust_ref,$customer,$selected_id,$selected_branch) {
    global $country_array;
    
    $cust_name = $contact->First_Name . " " . $contact->Last_Name;
    $country = (isset($country_array[$contact->Country_id]) && ($contact->Country_id != 204)) ? $country_array[$contact->Country_id] : "";
    $address = trim($contact->Company . "\n" . $contact->Address_1 . " " . $contact->Address_2) . "\n"
            . trim($contact->Postcode . " " . $contact->City) . "\n"
            . trim($country) . "\n";
    add_crm_person($cust_ref, $cust_name, '', $address, $customer->phone, "", $customer->fax, $contact->Email, '', '');
    $pers_id = db_insert_id();
    add_crm_contact('cust_branch', $contact->Type, $selected_branch, $pers_id);
    add_crm_contact('customer', $contact->Type, $selected_id, $pers_id);
}

function submit_process() {
    $upload_result = process_upload("file_in");
    if (!$upload_result) {
        display_error(_("Error upload file"));
        return;
    }
    $uploaded_data = processData($upload_result);
    processFile($uploaded_data);
}
