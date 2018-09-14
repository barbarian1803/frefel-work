<?php
class CustomerAddress {
    var $colnames;
    var $Address_id;
    var $Customer_id;
    var $Email;
    var $First_Name;
    var $Last_Name;
    var $Company;
    var $Address_1;
    var $Address_2;
    var $City;
    var $Postcode;
    var $Country_id;
    var $Zone_id;
    var $Type;
    
    function __construct($data) {
        $this->colnames = array("Address_id","Customer_id","Email","First_Name",
            "Last_Name","Company","Address_1","Address_2","City","Postcode",
            "Country_id","Zone_id");
        foreach($data as $idx=>$value){
            $this->{$this->colnames[$idx]} = $value;
        }
    }
}
