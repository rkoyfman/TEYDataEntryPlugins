<?php
    class THEELEGA_PXG_form_mens_shirt_sizes
    {
        private static $collar_sizes = 
        [
            14.5 => ['checked' => false, 'sleeve_sizes' => [33.5]],
            15 => ['checked' => true, 'sleeve_sizes' => [33, 34]],
            15.5 => ['checked' => true, 'sleeve_sizes' => [33, 34.5, 36]],
            16 => ['checked' => true, 'sleeve_sizes' => [33, 35, 36, 38]],
            16.5 => ['checked' => true, 'sleeve_sizes' => [33, 34.5, 36, 38]],
            17 => ['checked' => true, 'sleeve_sizes' => [34.5, 36, 38]],
            17.5 => ['checked' => true, 'sleeve_sizes' => [34.5, 36, 38]],
            18 => ['checked' => true, 'sleeve_sizes' => [36, 38]],
            19 => ['checked' => false, 'sleeve_sizes' => [38]],
            20 => ['checked' => false, 'sleeve_sizes' => [38]],
        ];

        public function html()
        {
            $products = $this->get_products();
    
            if (empty($products))
            {
                echo 'No products to display.';
                return;
            }
    
            $this->print_css();
            $this->print_js();
            ?>
    
            <h3>Set up sizes for mens' shirts</h3>
    
            <?php
            $this->print_grid();
            ?>
            
            <input type='button' value='Save' onclick='THEELEGA_PXG_submit_sizes()' />
            <div id='THEELEGA_PXG_status'></div>

            <table border='1' id='THEELEGA_PXG_products'>
            <tr>
                <th>ID</th>
                <th>SKU</th>
                <th>Name</th>
                <th>Supplier</th>
                <th>Current Selection</th>
                <th></th>
            </tr>

            <?php
            foreach ($products as $p)
            {
                $ID = $p->ID;
                $link = esc_attr(get_edit_post_link($p->ID));
                $link = "<a href='$link' target='_blank'>$p->ID</a>";
                $SKU = esc_html($p->SKU);
                $Name = esc_html($p->title);
                $Supplier = esc_html($p->supplier);
                $Selection = $this->sizes_html($p->men_shirt_sizes);

                echo 
                "<tr>
                    <td>$link</th>
                    <td>$SKU</th>
                    <td>$Name</th>
                    <td>$Supplier</th>
                    <td>$Selection</th>
                    <td>
                        <input type='checkbox' class='THEELEGA_PXG_product_selected' pid='$ID'/>
                        Apply selections to this product
                    </td>
                </tr>";
            }
            ?>
    
            </table>
            <?php
        }

        protected function get_products()
        {    
            $suppliers = theelega_request_field('THEELEGA_PXG_suppliers');
            $suppliers = stripslashes($suppliers);
            $suppliers = json_decode($suppliers, true);
    
            return THEELEGA_PXG_Product::get_all(true, false, $suppliers);
        }

        public static function print_grid()
        {
            self::print_css();
            echo "<table border id='THEELEGA_PXG_shirt_size_grid'>";
            foreach (self::$collar_sizes as $cs => $arr)
            {
                $checked = $arr['checked'];
                echo '<tr>';
                echo '<td>' . self::print_size_cell($cs, 'collar') . '</td>';

                foreach ($arr['sleeve_sizes'] as $ss)
                {
                    $value = ['collar_size' => $cs, 'sleeve_size' => $ss];
                    echo '<td>' . self::print_checkbox_cell($ss, 'sleeve', $value, $checked) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
        }
        
        private static function print_checkbox_cell($inches, $type, $value, $checked = true)
        {
            $value = esc_attr(json_encode($value));
            ob_start();
            ?>
            <table class='THEELEGA_PXG_shirt_size_cell'>
                <tr>
                    <th>
                        <?= self::print_size_cell($inches, $type)?>
                    </th>
                    <th>
                        <input type='checkbox' <?= $checked ? 'checked' : ''?>
                        value='<?= $value ?>'  class='THEELEGA_PXG_shirt_size_cell_checkbox' />
                    </th>
            </table>

            <?php
            return ob_get_clean();
        }

        private static function print_size_cell($inches, $type)
        {
            $cm = round($inches * 2.54);
            
            ob_start();
            ?>
            <table class='THEELEGA_PXG_shirt_size_cell_<?= $type ?>' >
                <tr><th><?= $inches ?>"</th></tr>
                <tr><th><hr/></th></tr>
                <tr><th><?= $cm ?> cm</th></tr>
            </table>
            <?php
            return ob_get_clean();
        }

        private static function print_css()
        {
            ?>
            <style>
                #THEELEGA_PXG_shirt_size_grid
                {
                    border-collapse: collapse;
                }

                .THEELEGA_PXG_shirt_size_cell hr
                {
                    margin: 0px 5px;
                    border: 1px solid rgb(150, 150, 150);
                }

                .THEELEGA_PXG_shirt_size_cell_collar
                {
                    width: 60px;
                    height: 60px;
                    border: 1px solid rgb(128, 85, 95);
                    background-color: rgb(247, 203, 213);
                }

                .THEELEGA_PXG_shirt_size_cell_sleeve
                {
                    width: 60px;
                    height: 60px;
                    border: 1px solid rgb(85, 128, 95);
                    background-color: rgb(203, 247, 213);
                }

            </style>
            <?php
        }

        

        function print_js()
        {
            ?>
            <script>
                function THEELEGA_PXG_submit_sizes()
                {
                    var url = '<?= admin_url('admin-ajax.php') ?>';
                    var nonce = '<?= wp_create_nonce('THEELEGA_PXG_set_mens_shirt_sizes') ?>';
                    var $ = jQuery;

                    var selectedSizes = [];

                    $('#THEELEGA_PXG_shirt_size_grid .THEELEGA_PXG_shirt_size_cell_checkbox:checked').each(function(i, cb)
                    {
                        value = JSON.parse(cb.value);
                        selectedSizes.push(value);
                    });

                    var pids = [];

                    $('#THEELEGA_PXG_products .THEELEGA_PXG_product_selected:checked').each(function(i, cb)
                    {
                        var pid = cb.getAttribute('pid');
                        pids.push(pid);
                    });

                    var data =
                    {
                        pids: pids,
                        selectedSizes: selectedSizes
                    };

                    THEELEGA_common_post(url, nonce, 'THEELEGA_PXG_set_mens_shirt_sizes',
                        data, document.getElementById('THEELEGA_PXG_status'));
                }
            </script>
            <?php
        }

        private function sizes_html($sizes_array)
        {
            $sa = theelega_arr_group_by($sizes_array, 'collar_size', 'sleeve_size');

            $strings = [];
            foreach ($sa as $cs => $ss)
            {
                $strings[] = "<b>$cs:</b> " . implode(', ', $ss);
            }

            return implode('<br/>', $strings);
        }
    }
?>