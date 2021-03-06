<?php
class THEELEGA_PXG_form
{
    //The function that handles the action when this page is opened.
    //Changes according to query string.
    private $page_handler;

    public function __construct()
    {
        $this->page_handler = [$this, 'html'];
        add_action('admin_menu', array($this, 'admin_menu'), 1000);

        if (theelega_request_field('THEELEGA_PXG_download_xml'))
        {
            THEELEGA_PXG_xml::xml();
        }
        elseif (theelega_request_field('THEELEGA_PXG_form_table_preview'))
        {
            $obj = new THEELEGA_PXG_form_table_preview();
            $this->page_handler = [$obj, 'html'];
        }
        elseif (theelega_request_field('THEELEGA_PXG_form_mens_shirt_sizes'))
        {
            $obj = new THEELEGA_PXG_form_mens_shirt_sizes();
            $this->page_handler = [$obj, 'html'];
        }
        elseif (theelega_request_field('THEELEGA_PXG_form_supplier_brand_mapping'))
        {
            $obj = new THEELEGA_PXG_form_supplier_brand_mapping();
            $this->page_handler = [$obj, 'html'];
        }
        elseif (theelega_request_field('THEELEGA_PXG_cleanup'))
        {
            $obj = new THEELEGA_PXG_form_table_delete();
            $this->page_handler = [$obj, 'html'];
        }
        elseif (theelega_request_field('THEELEGA_PXG_upload_categories'))
        {
            $obj = new THEELEGA_PXG_form_upload_categories();
            $this->page_handler = [$obj, 'html'];
        }
    }
    
    public function admin_menu()
    {
        add_submenu_page('tools.php', 'Generate Product XML', 'TEY Product XML Generator For Data Entry', 'administrator', __CLASS__, $this->page_handler);
    }

    public function html()
    {
        ?>
        <h3>Generate Product XML</h3>
        
        <style>
            td.THEELEGA_PXG_form_block
            {
                border: 2px groove #f1f1f1;
                padding: 10px;
            }
        </style>
        <script>
            function THEELEGA_PXG_onSupplierSelectionChanged(sender)
            {
                var $ = jQuery;
                var sel = $(sender);
                var form = sel.closest('form');
                var supp = form.find('[name=THEELEGA_PXG_suppliers]');

                supp.val(JSON.stringify(sel.val()))
            }
        </script>

        <table style='border-spacing: 10px;'>
            <?php
            echo '<tr>';
            $this->create_form_td(1, function()
            {
                ?>
                <div>
                    <?php $this->print_supplier_selector(null, null); ?>
                </div>
                
                <div>
                    <input type='checkbox' name='THEELEGA_PXG_ready_only'/>
                    Only show products that are ready for export.
                </div>
                <div>
                    <input type='checkbox' name='THEELEGA_PXG_unexported_only'/>
                    Only show unexported products.
                </div>

                <input type='submit' name='THEELEGA_PXG_form_table_preview' value='Preview products' />
                <?php
            });
            $this->create_form_td(1, function()
            {
                ?>
                <div>
                    <?php $this->print_supplier_selector(true, false); ?>
                </div>
                
                <input type='submit' name='THEELEGA_PXG_form_mens_shirt_sizes' value="Set up mens' shirt sizes" />
                <?php
            });
            
            echo '</tr>';
            echo '<tr>';
            
            $this->create_form_td(2, function()
            {
                ?>
                <?php $this->print_supplier_selector(true, false); ?>
                <div>
                    <input type='checkbox' name='THEELEGA_PXG_mark_exported'/>
                    Check to mark products as exported. They will be excluded from future downloads.
                </div>
                <input type='submit' name='THEELEGA_PXG_download_xml' value='Download XML' />
                <?php
            });

            echo '</tr>';
            echo '<tr>';

            $this->create_form_td(2, function()
            {
                ?>
                <input type='submit' name='THEELEGA_PXG_form_supplier_brand_mapping' value='Configure mappings between suppliers and brands' />
                <br/>
                <input type='submit' name='THEELEGA_PXG_upload_categories' value='Upload categories' />
                <?php
            });

            echo '</tr>';
            echo '<tr>';
            
            $this->create_form_td(2, function()
            {
                ?>
                <input type='submit' name='THEELEGA_PXG_cleanup' value='Clean up exported products' />
                <?php
            });
            echo '</tr>';            
            ?>
        </table>
        <?php
    }

    private function create_form_td($colspan, $callback)
    {
        ?>
        <td class='THEELEGA_PXG_form_block' colspan='<?= $colspan ?>'>
            <form>
                <input type='hidden' name='page' value='<?= __CLASS__ ?>' />
                <?php $callback(); ?>
            </form>
        </td>
        <?php
    }

    private function print_supplier_selector($ready, $exported)
    {
        $main_db = THEELEGA_PXG_main_server_db::get();
        $suppliers = $main_db->get_suppliers_with_products($ready, $exported);
        $output = [];

        $output[] = "Filter by supplier:
        <br/>
        <select onchange='THEELEGA_PXG_onSupplierSelectionChanged(this)' multiple size='4'>";

        foreach ($suppliers as $s => $prods)
        {
            $value = esc_attr($s);
            $text = esc_html($s . ' (' . count($prods) . ')');
            $output[] = "<option value='$value'>$text</option>";
        }
        $output[] = "</select>
        <input type='hidden' name='THEELEGA_PXG_suppliers'/>";

        $output = implode('', $output);
        echo $output;
    }
}
?>