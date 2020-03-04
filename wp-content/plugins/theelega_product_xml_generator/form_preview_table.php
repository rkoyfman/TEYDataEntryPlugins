<?php
class THEELEGA_PXG_form_preview_table
{
    public function html()
    {
        $ready_only = theelega_request_field('THEELEGA_PXG_ready_only');
        $unexported_only = theelega_request_field('THEELEGA_PXG_unexported_only');

        $suppliers = theelega_request_field('THEELEGA_PXG_suppliers');
        $suppliers = stripslashes($suppliers);
        $suppliers = json_decode($suppliers, true);

        $products = THEELEGA_PXG_Product::get_all($ready_only, $unexported_only, $suppliers);
        $display_data = array_map([$this, 'get_display_data'], $products);

        if (empty($display_data))
        {
            echo 'No products to display.';
            return;
        }

        $this->print_js();
        ?>

        <h3>Products for export</h3>
        <style>
            .THEELEGA_PXG_tr_error
            {
                background-color: rgb(255, 128, 128);
            }

            .THEELEGA_PXG_cell
            {
                vertical-align: top;
            }

            .THEELEGA_PXG_cell pre
            {
                margin: 0px;
                white-space: pre-wrap;
            }

            .THEELEGA_PXG_longstring
            {
                height: 20em;
                width: 20em;
                overflow: auto;
            }
        </style>

        <div>
            <div>
                Use this form to review the products before you download them.
            </div>
            <input type='button'
                value='Submit all changes'
                onclick='onSubmitAllProductsForm()'/>
            <div class='THEELEGA_PXG_status'></div>
        </div>
        
        <table border='1'>
        <?php
        $strings = array();
        
        $strings[] = '<tr>';

        foreach ($display_data[0] as $header => $value)
        {
            $strings[] = "<th>$header</th>";
        }
        $strings[] = '</tr>';

        foreach ($display_data as $d)
        {
            $strings[] = trim($d['Errors']['val']) ? "<tr class='THEELEGA_PXG_tr_error'>" : '<tr>';

            foreach ($d as $item)
            {
                $val = $item['val'];
                $cssclass = $item['cssclass'];
                $strings[] = "<td class='THEELEGA_PXG_cell'><div class='THEELEGA_PXG_$cssclass'>$val</div></td>";
            }
            
            $strings[] = '</tr>';
        }

        echo implode('', $strings);
        ?>

        </table>
        <?php
    }
    
    /*
        Takes one product as argument, returns an array of fields to show in the table.
    */
    /**@param THEELEGA_PXG_Product $p*/
    function get_display_data($p)
    {
        $ret = [];

        $ret['ID'] = ['val' => $p->ID, 'cssclass' => 'string'];
        $ret['Product Name'] = ['val' => $p->title, 'cssclass' => 'string'];
        $ret['SKU'] = ['val' => $p->SKU, 'cssclass' => 'string'];
        $ret['Main Image'] = ['val' => $this->get_image($p), 'cssclass' => 'string'];
        
        $xml = new THEELEGA_XMLElement('pre', THEELEGA_PXG_xml::product_to_xml($p) . '');
        $ret['XML'] = ['val' => $xml, 'cssclass' => 'longstring'];

        $errors = implode('<br/>', $p->errors);
        $ret['Errors'] = ['val' => $errors, 'cssclass' => 'longstring'];

        $ret['Actions'] = ['val' => $this->get_form($p), 'cssclass' => 'form'];

        return $ret;
    }

    function print_js()
    {
        ?>
        <script>
            function onSubmitAllProductsForm()
            {
                var url = '<?= admin_url('admin-ajax.php') ?>';
                var nonce = '<?= wp_create_nonce('THEELEGA_PXG_update_products') ?>';

                var changes = {};
                $ = jQuery;
                $('.THEELEGA_PXG_product_form').each(function(i, form)
                {
                    form = $(form);
                    var pid = form.attr('product_id');
                    var ready = form.find('[name=THEELEGA_PXG_ready]');
                    var exported = form.find('[name=THEELEGA_PXG_exported]');

                    var ready = ready.is(":checked") ? 1 : 0;
                    var exported = exported.is(":checked") ? 1 : 0;

                    changes[pid] = {'ready': ready, 'exported': exported};
                });
                
                THEELEGA_common_post(url, nonce, 'THEELEGA_PXG_update_products', changes, $('.THEELEGA_PXG_status'));
            }
        </script>
        <?php
    }

    /**@param THEELEGA_PXG_Product $p*/
    function get_form($p)
    {
        ob_start();
        ?>
        <p>
            <a href='<?= esc_attr(get_edit_post_link($p->ID)) ?>' target='_blank'>Edit in Wordpress editor</a>
        </p>

        <div class='THEELEGA_PXG_product_form' product_id='<?= $p->ID ?>' style='border: 1px solid gray; padding: 1em;'>
            <input type='checkbox'
                name='THEELEGA_PXG_ready'
                <?= $p->ready_for_export ? 'checked' : '' ?>/>
            Ready for export

            <br/>
            <input type='checkbox'
                name='THEELEGA_PXG_exported'
                <?= $p->exported ? 'checked' : '' ?>/>
            Exported
        </div>
        <?php

        return ob_get_clean();
    }

    /**@param THEELEGA_PXG_Product $p*/
    function get_image($p)
    {
        return "<img src='$p->image' height='150px' width='150px'/>";
    }
}
?>