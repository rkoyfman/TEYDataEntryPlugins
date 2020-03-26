<?php
/**
 * Plugin Name: TheElegantYou Product XML Generator For Data Entry
 * Description: For use on the Data Entry site. Allows an administrator to download products as XML.
 * Version:     1.1.1
 * Author:      Roman Koyfman
 */

add_action('theelega_startup', function()
{
    $missingdeps = theelega_missing_dependencies(['acf', 'woocommerce']);
    if (!empty($missingdeps))
    {
        theelega_missing_dependencies_notification('TheElegantYou Product XML Generator For Data Entry', $missingdeps);
        return;
    }
    
    $main_server_config = ABSPATH . 'wp-config-pxg.php';
    include_once $main_server_config;
    if (!defined('THEELEGA_PXG_main_site_db_parameters'))
    {
        theelega_show_notification(['wp-config-pxg.php is missing, or does not define the constant THEELEGA_PXG_main_site_db_parameters.'], 'error');
        return;
    }

    require_once 'db.php';
    require_once 'main_server_db.php';
    
    require_once 'ajax.php';
    require_once 'product.php';
    require_once 'xml.php';
    
    require_once 'form.php';
    require_once 'form_mens_shirt_sizes.php';
    require_once 'form_table_base.php';
    require_once 'form_table_preview.php';
    require_once 'form_table_delete.php';
    require_once 'form_supplier_brand_mapping.php';
    require_once 'form_upload_categories.php';
    require_once 'form_mens_shirt_sizes.php';

    require_once 'product_table_columns.php';
    
    new THEELEGA_PXG_form();
});
?>