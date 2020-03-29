<?php
class THEELEGA_WPL_catalog_visibility
{
    private $wc_product_visibility_options;
    private $column_name = 'theelega_catalog_visibility';
    private $ddl_name = 'theelega_product_list_filter_catalog_visibility';

    public function __construct()
    {
        $this->wc_product_visibility_options = wc_get_product_visibility_options();

        /**
         * Adds column header to the product list.
         * In our case, Catalog Visibility.
         */
        add_filter('manage_product_posts_columns', [$this, 'get_header'], PHP_INT_MAX, 1);

        /**
         * Prints the contents of the column for each product.
         */
        add_action('manage_product_posts_custom_column', [$this, 'echo_cell'], PHP_INT_MAX, 2);

        /**
         * Prints a drop-down for the Catalog Visibility column in the filter area above the table.
         */
        add_action('restrict_manage_posts', [$this, 'echo_filter'], PHP_INT_MAX, 2);

        /**
         * Applies the filter to search results.
         */
        add_action('parse_query', [$this, 'filter_products'], PHP_INT_MAX, 2);
    }

    function get_header($columns)
    {
        $columns[$this->column_name] = 'Catalog Visibility';
        return $columns;
    }

    function echo_cell($column, $post_id)
    {
        global $product;
        $product2 = $product ? $product : wc_get_product($post_id);
        if ($column == $this->column_name)
        {
            $vis = $product2->get_catalog_visibility();
            echo theelega_arr_get($this->wc_product_visibility_options, $vis);
        }
    }

    function echo_filter($post_type, $which)
    {
        if ($post_type == 'product')
        {
            $current_selection = theelega_request_field($this->ddl_name);

            $sel = new THEELEGA_XMLElement('select');
            $sel->addAttr('name', $this->ddl_name);

            $opt = new THEELEGA_XMLElement('option', 'Filter by catalog visibility');
            $opt->addAttr('value', '');
            $sel->addElement($opt);

            foreach ($this->wc_product_visibility_options as $slug => $label)
            {
                $opt = new THEELEGA_XMLElement('option', $label);
                $opt->addAttr('value', $slug);

                if ($slug == $current_selection)
                {
                    $opt->addAttr('selected', 'selected');
                }
                $sel->addElement($opt);
            }

            echo $sel;
        }
    }

    /**
     * Catalog visibility isn't stored directly. Woo uses two terms to do it - exclude-from-catalog and exclude-from-search,
     * but in the user interface, it becomes the product property catalog_visibility.
     * 
     * This method constructs the tax_query to filter the product table by these terms.
     */
    function filter_products($query)
    {
        $catalog_visibility = theelega_request_field($this->ddl_name);
    
        if ($catalog_visibility && $query->is_main_query())
        {
            //Exclude from catalog
            $efc = ['taxonomy' => 'product_visibility', 'terms' => 'exclude-from-catalog', 'field' => 'slug', 'operator' => 'IN'];
            //Exclude from search
            $efs = ['taxonomy' => 'product_visibility', 'terms' => 'exclude-from-search', 'field' => 'slug', 'operator' => 'IN'];
            //Include in catalog
            $iic = ['taxonomy' => 'product_visibility', 'terms' => 'exclude-from-catalog', 'field' => 'slug', 'operator' => 'NOT IN'];
            //Include in search
            $iis = ['taxonomy' => 'product_visibility', 'terms' => 'exclude-from-search', 'field' => 'slug', 'operator' => 'NOT IN'];

            $tq = ['relation' => 'AND'];

            if ($catalog_visibility == 'hidden')
            {
                $tq[] = $efc;
                $tq[] = $efs;
            }
            elseif ($catalog_visibility == 'catalog')
            {
                $tq[] = $efs;
                $tq[] = $iic;
            }
            elseif ($catalog_visibility == 'search')
            {
                $tq[] = $iis;
                $tq[] = $efc;
            }
            elseif ($catalog_visibility == 'visible')
            {
                $tq[] = $iic;
                $tq[] = $iis;
            }

            $query->query_vars['tax_query'][] = $tq;
        }
    }
}
?>