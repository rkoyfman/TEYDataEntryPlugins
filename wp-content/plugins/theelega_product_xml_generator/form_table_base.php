<?php
abstract class THEELEGA_PXG_form_table_base
{
    public function html()
    {
        $products = $this->get_products();
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
            .THEELEGA_PXG_tr_warning
            {
                background-color: rgb(255, 255, 128);
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
        <?php $this->print_page_header();?>
        </div>
        
        <table border='1'>
        <?php
        
        $row0 = (array) $display_data[0];
        unset($row0['css_class']);

        $strings = array();
        $strings[] = '<tr>';

        foreach (array_keys($row0) as $header)
        {
            $strings[] = "<th>$header</th>";
        }
        $strings[] = '</tr>';

        foreach ($display_data as $d)
        {
            $row_css_class = $d['css_class'];
            $strings[] = "<tr class='$row_css_class'>";

            foreach (array_keys($row0) as $header)
            {
                $item = $d[$header];
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
        $ret['Main Image'] = ['val' => $this->print_image($p), 'cssclass' => 'string'];
        
        $xml = new THEELEGA_XMLElement('pre', THEELEGA_PXG_xml::product_to_xml($p) . '');
        $ret['XML'] = ['val' => $xml, 'cssclass' => 'longstring'];

        $errors = implode('<br/>', $p->errors);
        $errors = $errors ? '<h3>Errors:</h3>' . $errors : '';
        $warnings = implode('<br/>', $p->warnings);
        $warnings = $warnings ? '<h3>Warnings:</h3>' . $warnings : '';

        $messages = implode('<hr/>', theelega_remove_falsy([$errors, $warnings]));

        $ret['Errors'] = ['val' => $messages, 'cssclass' => 'longstring'];

        $ret['Actions'] = ['val' => $this->print_form($p), 'cssclass' => 'form'];

        $ret['css_class'] = '';
        if ($warnings)
        {
            $ret['css_class'] = 'THEELEGA_PXG_tr_warning';
        }
        if ($errors)
        {
            $ret['css_class'] = 'THEELEGA_PXG_tr_error';
        }

        return $ret;
    }

    /**@param THEELEGA_PXG_Product $p*/
    function print_image($p)
    {
        return "<img src='$p->image' height='150px' width='150px'/>";
    }

    /** @return THEELEGA_PXG_Product[] */
    protected abstract function get_products();
    
    protected abstract function print_js();
    protected abstract function print_page_header();
    
    /**@param THEELEGA_PXG_Product $p*/
    protected abstract function print_form($p);
}
?>