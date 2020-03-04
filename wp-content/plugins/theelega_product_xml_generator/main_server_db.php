<?php

class THEELEGA_PXG_main_server_db extends THEELEGA_db
{
    /**
	 * @return self
	 */
    public static function get()
    {
        static $instance = null;
        if (!$instance)
        {
            $instance = new self();
        }

        return $instance;
    }
    
    private function __construct()
    {
        $msdb = THEELEGA_PXG_main_site_db_parameters;
        $db = new wpdb($msdb['DB_USER'], $msdb['DB_PASSWORD'], $msdb['DB_NAME'], $msdb['DB_HOST']);
        $db->set_prefix($msdb['DB_TABLE_PREFIX']);

        parent::__construct($db);
    }

    public function get_taxonomies()
    {
        $sql = "SELECT t.*, tt.*
        FROM {$this->prefix}terms t
        INNER JOIN {$this->prefix}term_taxonomy tt
            ON t.term_id = tt.term_id
        ORDER BY tt.taxonomy, t.name";

        $res = $this->get_results($sql);

        $ret = [];
        foreach ($res as $row)
        {
            $tax = $row['taxonomy'];
            $slug = $row['slug'];
            $ret[$tax][$slug] = $row;
        }

        return $ret;
    }

    public function get_woo_attributes()
    {
        $sql = "SELECT attribute_name, 'color' AS tag
        FROM {$this->prefix}woocommerce_attribute_taxonomies
        WHERE attribute_name LIKE '%color%'
        UNION
        SELECT attribute_name, 'size' AS tag
        FROM {$this->prefix}woocommerce_attribute_taxonomies
        WHERE attribute_name LIKE '%size%'";

        $res = $this->get_results($sql);

        $ret = [];
        foreach ($res as $row)
        {
            $tag = $row['tag'];
            $an = $row['attribute_name'];
            $ret[$tag][$an] = true;
        }

        return $ret;
    }

    public function get_suppliers()
    {
        $sql = "SELECT p.ID, p.post_name AS slug, p.post_title AS label
        FROM {$this->prefix}posts p
        WHERE p.post_status = 'publish'
            AND p.post_type like 'ft_supplier'
        ORDER BY p.post_title";

        $ret = $this->get_results($sql);

        return $ret;
    }
}
?>