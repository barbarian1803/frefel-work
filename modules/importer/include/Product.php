<?php

class Product{
  var $id;
  var $product_name;
  var $product_model;
  var $price;
  function __construct($array){
    $this->id = $array[0];
    $this->product_name = $array[1];
    $this->product_model = $array[2];
    $this->price = $array[3];
  }
}
