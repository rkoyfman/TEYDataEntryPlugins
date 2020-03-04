<?php
add_action('wp_ajax_THEELEGA_PXG_update_products', function()
{
    $db = THEELEGA_PXG_db::get();
    $updates = theelega_get_ajax_request('THEELEGA_PXG_update_products');
    $db->update_posts($updates);

    die(json_encode(['success' => true]));
});

add_action('wp_ajax_THEELEGA_PXG_supplier_brand_mapping', function()
{
    $option = theelega_get_ajax_request('THEELEGA_PXG_supplier_brand_mapping');
    update_option('THEELEGA_PXG_supplier_brand_mapping', $option);

    die(json_encode(['success' => true]));
});
?>