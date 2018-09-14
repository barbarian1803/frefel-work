<?php

include_once($path_to_root . "/modules/importer/importer.php");


class hooks_importer extends hooks {

    var $module_name = 'importer';

    /*
      Install additional menu options provided by module
     */

    function install_tabs($app) {
        $app->add_application(new importer_app);
    }

    /*
      Install additonal menu options provided by module
     */

    function install_options($app) {
        global $path_to_root;
        switch ($app->id) {
            case 'importer':
                $app->add_lapp_function(0, _("Import &Customer"), $path_to_root . "/modules/importer/customer.php?", 'SA_BACKUP', MENU_SYSTEM);
                $app->add_lapp_function(0, _("Import &Inventory"), $path_to_root . "/modules/importer/inventory.php?", 'SA_BACKUP', MENU_SYSTEM);
                $app->add_lapp_function(0, _("Import &Order/Invoice"), $path_to_root . "/modules/importer/order_import.php?", 'SA_BACKUP', MENU_SYSTEM);
                //$app->add_lapp_function(0, _("Reformat postfinance data"), $path_to_root . "/modules/importer/postfinance.php?", 'SA_BACKUP', MENU_SYSTEM);
                break;
        }
    }

    function install_access() {

    }

    /* This method is called on extension activation for company. 	 */

    function activate_extension($company,$check_only = true) {

    }

    function deactivate_extension($company,$check_only = true) {


    }

}

?>
