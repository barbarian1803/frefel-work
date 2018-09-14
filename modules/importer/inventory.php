<?php

$path_to_root = "../..";
$page_security = 'SA_BACKUP';

include_once("include/Product.php");
include($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");

include_once("include/util.php");
include_once("lib/PHPExcel/IOFactory.php");
include_once("lib/PHPExcel.php");

ini_set('memory_limit', '1024M');

$js = "";

//Page start
page(_($help_context = "Inventory data import"), false, false, $js);

if (isset($_POST["Upload"]) && can_process()) {
  submit_process();
  display_notification(_("All data has been uploaded"));
  end_page();
  exit();
}

div_start('inventory');

start_form(true);

br();

echo "<center><h3>Inventory file uploader</h3></center>";

start_outer_table(TABLESTYLE2);

table_section(1);

table_section_title(_("Item"));

//------------------------------------------------------------------------------------
file_row(_("Customer file") . ":", 'file_in', 'file_in');

$_POST['inactive'] = 0;

stock_categories_list_row(_("Default category:"), 'category_id', null, false, true);

if (list_updated('category_id') || !isset($_POST['units'])) {

  $category_record = get_item_category($_POST['category_id']);

  $_POST['tax_type_id'] = $category_record["dflt_tax_type"];
  $_POST['units'] = $category_record["dflt_units"];
  $_POST['mb_flag'] = $category_record["dflt_mb_flag"];
  $_POST['inventory_account'] = $category_record["dflt_inventory_act"];
  $_POST['cogs_account'] = $category_record["dflt_cogs_act"];
  $_POST['sales_account'] = $category_record["dflt_sales_act"];
  $_POST['adjustment_account'] = $category_record["dflt_adjustment_act"];
  $_POST['assembly_account'] = $category_record["dflt_assembly_act"];
  $_POST['dimension_id'] = $category_record["dflt_dim1"];
  $_POST['dimension2_id'] = $category_record["dflt_dim2"];
  $_POST['no_sale'] = $category_record["dflt_no_sale"];
  $_POST['editable'] = 0;

}
$fresh_item = !isset($_POST['NewStockID']) || $new_item || check_usage($_POST['stock_id'],false);

item_tax_types_list_row(_("Item Tax Type:"), 'tax_type_id', null);

stock_item_types_list_row(_("Item Type:"), 'mb_flag', null, $fresh_item);

stock_units_list_row(_('Units of Measure:'), 'units', null, $fresh_item);

check_row(_("Editable description:"), 'editable');

check_row(_("Exclude from sales:"), 'no_sale');

table_section_title(_("Sales type"));
sales_types_list_row(_("Sales Type:"), 'sales_type_id', null, true);

$dim = get_company_pref('use_dimension');
if ($dim >= 1)
{
  table_section_title(_("Dimensions"));

  dimensions_list_row(_("Dimension")." 1", 'dimension_id', null, true, " ", false, 1);
  if ($dim > 1)
  dimensions_list_row(_("Dimension")." 2", 'dimension2_id', null, true, " ", false, 2);
}
if ($dim < 1)
hidden('dimension_id', 0);
if ($dim < 2)
hidden('dimension2_id', 0);

table_section_title(_("GL Accounts"));

gl_all_accounts_list_row(_("Sales Account:"), 'sales_account', $_POST['sales_account']);

if (!is_service($_POST['mb_flag']))
{
  gl_all_accounts_list_row(_("Inventory Account:"), 'inventory_account', $_POST['inventory_account']);
  gl_all_accounts_list_row(_("C.O.G.S. Account:"), 'cogs_account', $_POST['cogs_account']);
  gl_all_accounts_list_row(_("Inventory Adjustments Account:"), 'adjustment_account', $_POST['adjustment_account']);
}
else
{
  gl_all_accounts_list_row(_("C.O.G.S. Account:"), 'cogs_account', $_POST['cogs_account']);
  hidden('inventory_account', $_POST['inventory_account']);
  hidden('adjustment_account', $_POST['adjustment_account']);
}


if (is_manufactured($_POST['mb_flag']))
gl_all_accounts_list_row(_("Item Assembly Costs Account:"), 'assembly_account', $_POST['assembly_account']);
else
hidden('assembly_account', $_POST['assembly_account']);

end_outer_table(1);

submit_center_first("Upload", "Upload", "Upload", false);

end_form();

div_end();

end_page();

function processFile($uploaded_data){
  foreach($uploaded_data as $idx=>$product){
//      var_dump($product);br();
    add_item($idx, $product->product_name,$product->product_name." ".$product->product_model, 
        $_POST['category_id'], $_POST['tax_type_id'],
        $_POST['units'], $_POST['mb_flag'], $_POST['sales_account'],
        $_POST['inventory_account'], $_POST['cogs_account'],
        $_POST['adjustment_account'], $_POST['assembly_account'],
        $_POST['dimension_id'], $_POST['dimension2_id'],
        check_value('no_sale'), check_value('editable')
    );
    
    add_item_price($idx, $_POST['sales_type_id'],
        get_company_currency(), $product->price
    );
  }
}

function processOrderProduct($orderProductSheet,$rowsData){

  for ($j = 0; $j < 5000; $j++) {
    $row = $j + 1;

    if ($orderProductSheet->getCellByColumnAndRow(0, $row + 1)->getValue() == null) {
      break;
    }

    $data_product = array();

    for ($col = 0; $col < 3; $col++) {
      $cell = $orderProductSheet->getCellByColumnAndRow($col+2, $row + 1);
      $data_product[$col] = trim($cell->getValue());
    }
    $data_product[3] = trim($orderProductSheet->getCellByColumnAndRow(8, $row + 1)->getValue());
    $product = new Product($data_product);
    $rowsData[str_replace(" ", "_", trim($product->product_model))] = $product;
  }
  return $rowsData;
}

function processData($file) {
  set_time_limit(100);

  $objPHPExcel = PHPExcel_IOFactory::load($file);

  $rowsData = array();

  $orderProductSheet = $objPHPExcel->getSheet(3);
  $rowsData = processOrderProduct($orderProductSheet,$rowsData);

  return $rowsData;
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
