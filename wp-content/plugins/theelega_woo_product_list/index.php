<?php
/**
 * Plugin Name: TheElegantYou WooCommerce product list tweaks
 * Author:      Roman Koyfman
 * Description: <CODE>The Elegant You</CODE> Property.
 * Version: 1
 */
add_action('theelega_startup', function()
{
    $missingdeps = theelega_missing_dependencies(['woocommerce', 'acf']);
    if (!empty($missingdeps))
    {
        theelega_missing_dependencies_notification('TheElegantYou Product XML Generator For Data Entry', $missingdeps);
        return;
    }
    
    if (!is_admin())
    {
        return;
    }
    
    require_once 'db.php';

    require_once 'acf_show_field.php';
    new THEELEGA_WPL_acf_show_field();

    require_once 'catalog_visibility.php';
    new THEELEGA_WPL_catalog_visibility();
});

?>