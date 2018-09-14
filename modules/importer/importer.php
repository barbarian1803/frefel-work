<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
class importer_app extends application
{

	function  __construct()
	{
            parent::__construct("importer",_($this->help_context = "Data importer"));

            $this->add_module(_("Transactions"));

            $this->add_module(_("Inquiries and Reports"));

            $this->add_module(_("Maintenance"));

            $this->add_extensions();

	}

}
