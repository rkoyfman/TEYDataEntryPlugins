<?php
class THEELEGA_WPL_acf_show_field
{
    public static $setting_name = 'theelega_wpl_acf_show_field';
    public $fields;

    public function __construct()
    {
        /**
         * On the Advanced Custom Fields field configuration form, adds a checkbox that determines if the field gets
         * added to the WooCommerce product table.
         */
        add_action('acf/render_field_settings', [$this, 'add_show_field_checkbox'], PHP_INT_MAX, 1);

        /**
         * Adds column headers to the product list.
         */
        add_filter('manage_product_posts_columns', [$this, 'get_header'], PHP_INT_MAX, 1);

        /**
         * Prints the contents of each column for each product.
         */
        add_action('manage_product_posts_custom_column', [$this, 'echo_cell'], PHP_INT_MAX, 2);
    }

    public function add_show_field_checkbox($field)
    {
        acf_render_field_setting($field, array(
            'label'			=> 'Show this field in the WooCommerce product table',
            'instructions'	=> '',
            'type'			=> 'true_false',
            'name'			=> self::$setting_name,
            'class'			=> self::$setting_name
        ), true);
    }

    //In case slugs from custom fields overlap with existing fields, break glass.
    private $replacement_slugs = [];
    function get_header($columns)
    {
        if (!$this->fields)
        {
            $db = THEELEGA_WPL_db::get();
            $this->fields = $db->get_acf_fields_to_show();
        }
        
        foreach ($this->fields as $f)
        {
            //If column with slug exists
            if (isset($columns[$f['slug']]))
            {
                $new_slug = $f['slug'] . '_theelega_wpl_suffix';
                $this->replacement_slugs[$f['slug']] = $new_slug;
                $columns[$new_slug] = $f['label'] . '*';
            }
            //Elsewise
            else
            {
                $columns[$f['slug']] = $f['label'] . '*';
            }
            $columns[$f['slug']] = $f['label'] . '*';
        }
        return $columns;
    }

    function echo_cell($column, $post_id)
    {
        if (!$this->fields)
        {
            $db = THEELEGA_WPL_db::get();
            $this->fields = $db->get_acf_fields_to_show();
        }

        foreach ($this->fields as $f)
        {
            $slug = theelega_arr_get($this->replacement_slugs, $f['slug'], $f['slug']);
            if ($column == $slug)
            {
                $val = get_post_meta($post_id, $f['slug'], true);
                $type = theelega_arr_get($f['settings'], 'type');
                if (in_array($type, ['checkbox', 'true_false']))
                {
                    $val = intval($val) ? 'Yes' : 'No';
                }

                echo $val;
            }
        }
    }
}

?>