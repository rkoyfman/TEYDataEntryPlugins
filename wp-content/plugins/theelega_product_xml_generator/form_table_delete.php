<?php
require_once 'form_table_base.php';

class THEELEGA_PXG_form_table_delete extends THEELEGA_PXG_form_table_base
{
    public function get_products()
    {
        $ready = 1;
        $exported = 1;

        $suppliers = theelega_request_field('THEELEGA_PXG_suppliers');
        $suppliers = stripslashes($suppliers);
        $suppliers = json_decode($suppliers, true);

        return THEELEGA_PXG_Product::get_all($ready, $exported, $suppliers);
    }

    function print_js()
    {
        ?>
        <script>
            function THEELEGA_PXG_onSubmitAll()
            {
                var $ = jQuery;

                var url = '<?= admin_url('admin-ajax.php') ?>';
                var nonce = '<?= wp_create_nonce('THEELEGA_PXG_delete_products') ?>';
                var operations = {'trash': [], 'delete': []};

                var rows_to_remove = $([]);
                $('.THEELEGA_PXG_product_operations').each(function(i, form)
                {
                    form = $(form);
                    var pid = form.attr('product_id');
                    var selection = form.find('input[type=radio]:checked').val();

                    if (selection == 'trash' || selection == 'delete')
                    {
                        operations[selection].push(pid);
                        rows_to_remove = rows_to_remove.add(form.closest('tr'));
                    }
                });

                var confirmed = false;
                if (operations.trash.length || operations.delete.length)
                {
                    confirmed = confirm('Confirm submission?');
                }
                if (confirmed && operations.delete.length)
                {
                    confirmed = confirm('You are about to delete some products completely. Confirm AGAIN.');
                }
                
                function on_success()
                {
                    rows_to_remove.remove();
                }

                if (confirmed)
                {
                    THEELEGA_common_post(url, nonce, 'THEELEGA_PXG_delete_products', operations, $('.THEELEGA_PXG_status'), on_success);
                }
            }

            function THEELEGA_PXG_markAll(selection)
            {
                var $ = jQuery;

                $('.THEELEGA_PXG_product_operations input[type=radio]').each(function (i, r)
                {
                    r.checked = (r.value == selection);
                });
            }
        </script>
        <?php
    }

    protected function print_page_header()
    {
        ?>
            <div>
                Use this form to delete products after they've been exported.
            </div>
            <input type='button'
                value='Delete selected'
                onclick='THEELEGA_PXG_onSubmitAll()'/>
            <input type='button'
                value='Mark all Keep'
                onclick="THEELEGA_PXG_markAll('keep')"/>
            <input type='button'
                value='Mark all Trash'
                onclick="THEELEGA_PXG_markAll('trash')"/>
            <input type='button'
                value='Mark all Delete'
                onclick="THEELEGA_PXG_markAll('delete')"/>
            <div class='THEELEGA_PXG_status'></div>
        <?php
    }

    /**@param THEELEGA_PXG_Product $p*/
    function print_form($p)
    {
        ob_start();
        $button_group_name = "THEELEGA_PXG_product_operations_{$p->ID}";

        ?>
        <p>
            <a href='<?= esc_attr(get_edit_post_link($p->ID)) ?>' target='_blank'>Edit in Wordpress editor</a>
        </p>

        <div class='THEELEGA_PXG_product_operations' product_id='<?= $p->ID ?>' style='border: 1px solid gray; padding: 1em;'>
            <input type='radio' name='<?=$button_group_name?>' value='keep' checked /> Keep
            <br/>
            <input type='radio' name='<?=$button_group_name?>' value='trash' /> Send to trash
            <br/>
            <input type='radio' name='<?=$button_group_name?>' value='delete' /> Delete
        </div>
        <?php

        return ob_get_clean();
    }
}
?>