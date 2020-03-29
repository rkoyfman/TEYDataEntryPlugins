<?php
/**
 * Obsolete.
 */


add_filter('manage_product_posts_columns', function($columns)
{
    $columns['sku_cf'] = 'SKU (Custom Field)';
    $columns['brand_cf'] = 'Brand (Custom Field)';
    $columns['ready_cf'] = 'Ready for Export (Custom Field)';
    $columns['exported_cf'] = 'Exported (Custom Field)';

    return $columns;
}, PHP_INT_MAX, 1);

add_action('manage_product_posts_custom_column', function($column, $post_id)
{
    static $products = null;
    $products = $products ? $products : theelega_arr_group_by(THEELEGA_PXG_Product::get_all(null, null, null), 'ID');
    
    $p = theelega_arr_get($products, [$post_id, 0]);

    switch ($column)
    {
        case 'sku_cf':
            echo theelega_arr_get($p, 'SKU', '');
            break;
        case 'brand_cf':
            echo theelega_arr_get($p, 'brand', '');
            break;
        case 'ready_cf':
            echo theelega_arr_get($p, 'ready_for_export', 0) == 1 ? 'Yes' : 'No';
            break;
        case 'exported_cf':
            echo theelega_arr_get($p, 'exported', 0) == 1 ? 'Yes' : 'No';
            break;
    }
}, PHP_INT_MAX, 2);
?>