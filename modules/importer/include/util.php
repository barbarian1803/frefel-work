<?php

function process_upload($file) {
    global $path_to_root;
    $upload_file = "";

    if (isset($_FILES[$file]) && $_FILES[$file]['name'] != '') {
        $result = $_FILES[$file]['error'];
        $upload_file = 'Yes'; //Assume all is well to start off with
        $filename = $path_to_root . '/modules/importer/files/' . $_FILES[$file]['name'];

        if ($_FILES[$file]['error'] == UPLOAD_ERR_INI_SIZE) {
            display_error(_('The file size is over the maximum allowed.'));
            $upload_file = 'No';
        } elseif ($_FILES[$file]['error'] > 0) {
            display_error(_('Error uploading file.'));
            $upload_file = 'No';
        }

        if ($upload_file == 'Yes') {
            $result = move_uploaded_file($_FILES[$file]['tmp_name'], $filename);
            return $filename;
        } else {
            return false;
        }
    }
}

function process_upload_xml($file) {
    global $path_to_root;
    $upload_file = "";

    if (isset($_FILES[$file]) && $_FILES[$file]['name'] != '') {
        $result = $_FILES[$file]['error'];
        $upload_file = 'Yes'; //Assume all is well to start off with
        $filename = $path_to_root . '/modules/importer/files/' . $_FILES[$file]['name'];

        if ($_FILES[$file]['error'] == UPLOAD_ERR_INI_SIZE) {
            display_error(_('The file size is over the maximum allowed.'));
            $upload_file = 'No';
        } elseif ($_FILES[$file]['error'] > 0) {
            display_error(_('Error uploading file.'));
            $upload_file = 'No';
        }

        if ($upload_file == 'Yes') {
            $result = move_uploaded_file($_FILES[$file]['tmp_name'], $filename);
            return $filename;
        } else {
            return false;
        }
    }
}

function can_process() {
  if (!isset($_FILES["file_in"])) {
    display_error(_("File is needed"));
    return false;
  }
  return true;
}

function contains($needle, $haystack){
    return strpos($haystack, $needle) !== false;
}

function get_customer_by_fuzzy_ref($reference)
{
	$sql = "SELECT * FROM ".TB_PREF."debtors_master WHERE debtor_ref LIKE ".db_escape("%".$reference."%");

	$result = db_query($sql, "could not get customer");

	return db_fetch($result);
}

function get_cust_branch_by_ref($branch_ref,$match=true)
{
    if($match){
	$sql = "SELECT * FROM ".TB_PREF."cust_branch WHERE branch_ref=".db_escape($branch_ref);
    }else {
        $sql = "SELECT * FROM ".TB_PREF."cust_branch WHERE branch_ref LIKE ".db_escape('%'.$branch_ref.'%');
    }
	$result = db_query($sql,"check failed");
	return db_fetch($result);
}

function get_shipper_id($method){
    $sql = "SELECT * FROM ".TB_PREF."shippers WHERE shipper_name=".db_escape($method);

    $result = db_query($sql, "could not get shipper");
    return db_fetch($result);
}

function update_customer_curr($customer_id, $curr_code)
{
	$sql = "UPDATE ".TB_PREF."debtors_master SET curr_code=".db_escape($curr_code) . "WHERE debtor_no = ".db_escape($customer_id);
	db_query($sql,"The customer could not be updated");
}

function get_debtor_trans_by_ref($ref){
    $sql = "SELECT * FROM ".TB_PREF."debtor_trans WHERE reference=".db_escape($ref);

    $result = db_query($sql, "could not get shipper");
    return db_fetch_assoc($result);
}