<?php
class THEELEGA_PXG_form_table_preview extends THEELEGA_PXG_form_table_base
{
    protected function get_products()
    {
        $ready = theelega_request_field('THEELEGA_PXG_ready_only') ? 1 : null;
        $exported = theelega_request_field('THEELEGA_PXG_unexported_only') ? 0 : null;

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
                var url = '<?= admin_url('admin-ajax.php') ?>';
                var nonce = '<?= wp_create_nonce('THEELEGA_PXG_update_products') ?>';

                var changes = {};
                var $ = jQuery;
                $('.THEELEGA_PXG_product_form').each(function(i, form)
                {
                    form = $(form);
                    var pid = form.attr('product_id');

                    changes[pid] = {
                        'ready_for_export': form.find('[name=THEELEGA_PXG_ready_for_export]:checked').length ? 1 : 0,
                         'exported': form.find('[name=THEELEGA_PXG_exported]:checked').length ? 1 : 0,
                         'export_despite_errors': form.find('[name=THEELEGA_PXG_export_despite_errors]:checked').length ? 1 : 0,
                        };
                });

                THEELEGA_common_post(url, nonce, 'THEELEGA_PXG_update_products', changes, $('.THEELEGA_PXG_status'));
            }
        </script>
        <?php
    }

    protected function print_page_header()
    {
        ?>
        <div>
            Use this form to review the products before you download them.
            </div>
            <input type='button'
                value='Submit all changes'
                onclick='THEELEGA_PXG_onSubmitAll()'/>
            <div class='THEELEGA_PXG_status'></div>
        <?php
    }

    /**@param THEELEGA_PXG_Product $p*/
    protected function print_form($p)
    {
        ob_start();
        ?>
        <p>
            <a href='<?= esc_attr(get_edit_post_link($p->ID)) ?>' target='_blank'>Edit in Wordpress editor</a>
        </p>

        <div class='THEELEGA_PXG_product_form' product_id='<?= $p->ID ?>' style='border: 1px solid gray; padding: 1em;'>
            <input type='checkbox' name='THEELEGA_PXG_ready_for_export'
                <?= $p->ready_for_export ? 'checked' : '' ?>/>
            Ready for export

        <br/>
        <input type='checkbox' name='THEELEGA_PXG_exported'
            <?= $p->exported ? 'checked' : '' ?>/>
        Exported

        <br/>
        <input type='checkbox' name='THEELEGA_PXG_export_despite_errors'
            <?= $p->export_despite_errors ? 'checked' : '' ?>/>
        Export despite errors
        </div>
        <?php

        return ob_get_clean();
    }
}
?>