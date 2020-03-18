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

add_action('wp_ajax_THEELEGA_PXG_delete_products', function()
{
    $db = THEELEGA_PXG_db::get();
    $operations = theelega_get_ajax_request('THEELEGA_PXG_delete_products');
    $db->trash_products($operations['trash']);
    $db->delete_products($operations['delete']);

    die(json_encode(['success' => true]));
});

add_action('wp_ajax_THEELEGA_PXG_form_upload_categories', function()
{
    try
    {
        check_ajax_referer('THEELEGA_PXG_form_upload_categories');
        $xml = theelega_arr_get($_FILES, ['xml', 'tmp_name']);
        if (!$xml)
        {
            die('No data sent!');
        }
        
        $xml = file_get_contents($xml);

        $errors = [];
        $warnings = [];
        $messages = [];
        THEELEGA_PXG_form_upload_categories::processXML($xml, $errors, $warnings, $messages);

        $ul = new THEELEGA_XMLElement('ul');

        if ($errors)
        {
            $lis = array_map(function($e) { return new THEELEGA_XMLElement('li', $e); }, $errors);
            $ul->addElements(...$lis);
            $ul->addAttr('style', 'color: red;');

            die(json_encode(['success' => false, 'errors_html' => strval($ul)]));
        }

        foreach ($warnings as $w)
        {
            $li = new THEELEGA_XMLElement('li', $w);
            $li->addAttr('style', 'color: darksalmon;');
            $ul->addElement($li);
        }

        foreach ($messages as $m)
        {
            $li = new THEELEGA_XMLElement('li', $m);
            $li->addAttr('style', 'color: green;');
            $ul->addElement($li);
        }

        die(json_encode(['success' => true, 'messages_html' => strval($ul)]));
        

        die(json_encode(['success' => true]));
    }
    catch (Exception $e)
    {
        die($e->getMessage());
    }
});
?>