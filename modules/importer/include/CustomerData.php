<?php

class CustomerData {
    var $colnames;
    
    var $customer_id;
    var $customer_group_id;
    var $store_id;
    var $first_name;
    var $last_name;
    var $email;
    var $phone;
    var $fax;
    var $password;
    var $salt;
    var $cart;
    var $wishlist;
    var $newsletter;
    var $address_id;
    var $ip;
    var $approved;
    var $safe;
    var $token;
    var $date_added;
    var $status;
    var $password_status;
    var $address;
    var $currency;
    
    function __construct($array_data) {
        $this->colnames = array(
            "customer_id","customer_group_id","store_id","first_name","last_name","email",
            "phone","fax","password","salt","cart","wishlist","newsletter","address_id","ip","approved",
            "safe","token","date_added","status","password_status"
        );
        
        foreach($array_data as $idx=>$data){
            $this->{$this->colnames[$idx]}=$data;
        }
        
        $this->address = array();
    }
}
