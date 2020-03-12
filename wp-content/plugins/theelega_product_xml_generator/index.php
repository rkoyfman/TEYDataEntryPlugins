<?php
/**
 * Plugin Name: TheElegantYou Product XML Generator For Data Entry
 * Description: For use on the Data Entry site. Allows an administrator to download products as XML.
 * Version:     1.1
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
    require_once 'form_table_base.php';
    require_once 'form_table_preview.php';
    require_once 'form_table_delete.php';
    require_once 'form_supplier_brand_mapping.php';

    new THEELEGA_PXG_form();

    //add_action('admin_print_footer_scripts', 'THEELEGA_PXG_style', PHP_INT_MAX);
});

function THEELEGA_PXG_style()
{
    ?>
    <style>
        .postbox-container .postbox.acf-postbox .acf-fields .acf-field
        {
            padding-top: 2px;
            padding-bottom: 2px;
        }
    </style>
    <?php
}
?>