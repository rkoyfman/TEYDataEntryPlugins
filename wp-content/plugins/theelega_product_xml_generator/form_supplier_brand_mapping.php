<?php
class THEELEGA_PXG_form_supplier_brand_mapping
{
    public static $option_name = 'THEELEGA_PXG_supplier_brand_mapping';

    public function __construct()
    {
        $option = get_option(self::$option_name);
        if (!is_array($option))
        {
            add_option(self::$option_name);
        }
    }

    public function html()
    {
        $get = 'theelega_arr_get';
        $main_db = THEELEGA_PXG_main_server_db::get();
        $option_name = self::$option_name;

        $option = get_option($option_name);
        $suppliers = $main_db->get_suppliers();
        $brands = $get($main_db->get_taxonomies(), 'pa_brand', []);

        $this->print_js();
        
        echo "<table class='{$option_name}_tbl'>";
        echo "<tr><th>Supplier</th><th>Brand</th></tr>";
        foreach ($suppliers as $s)
        {
            $sname = esc_html($s['label']);

            echo "<tr class='{$option_name}_tr' supplier_name='$sname'>";
            echo "<td>$sname</td>";

            echo "<td>";
            echo "<select name='{$option_name}_brand'>";
            foreach ($brands as $b)
            {
                $bslug = esc_attr($b['slug']);
                $bname = esc_html($b['name']);
                $selected = $get($option, $sname, '') == $bslug ? 'selected' : '';
                echo "<option value='$bslug' $selected>$bname</option>";
            }
            echo "</select>";
            
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<input type='button' value='Save mappings' onclick='THEELEGA_PXG_onSubmitSettings()' />";
        echo "<span class='{$option_name}_status'></span>";
    }

    function print_js()
    {
        ?>
        <script>
            function THEELEGA_PXG_onSubmitSettings()
            {
                var prefix = '<?= self::$option_name ?>';
                var url = '<?= admin_url('admin-ajax.php') ?>';
                var nonce = '<?= wp_create_nonce(self::$option_name) ?>';

                var mappings = {};
                $ = jQuery;
                $('.' + prefix + '_tbl .' + prefix + '_tr').each(function(i, tr)
                {
                    tr = $(tr);
                    var s = tr.attr('supplier_name');
                    var b = tr.find('[name=' + prefix + '_brand]').val();

                    mappings[s] = b;
                });
                
                THEELEGA_common_post(url, nonce, prefix, mappings, $('.' + prefix + '_status'));
            }
        </script>
        <?php
    }
}
?>