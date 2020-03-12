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
        $sql = "SELECT attribute_name, attribute_label
        FROM {$this->prefix}woocommerce_attribute_taxonomies";

        $res = $this->get_results($sql);

        $ret = [];
        foreach ($res as $row)
        {
            $ret[$row['attribute_name']] = $row['attribute_label'];
        }

        return $ret;
    }

    public function get_suppliers()
    {
        $sql = "SELECT p.post_title
        FROM {$this->prefix}posts p
        WHERE p.post_status = 'publish'
            AND p.post_type like 'ft_supplier'
        ORDER BY p.post_title";

        $ret = $this->get_col($sql);
        return $ret;
    }

    public function get_suppliers_with_products($ready, $exported)
    {
        $suppliers = $this->get_suppliers();

        $ret = [];
        $ret['No supplier'] = [];
        $ret['Invalid supplier'] = [];
        foreach ($suppliers as $s)
        {
            $ret[$s] = [];
        }
        
        $db = THEELEGA_PXG_db::get();
        $products = $db->get_products($ready, $exported, null);

        foreach ($products as $p)
        {
            $supplier = $p['supplier'];
            $supplier = trim($supplier);

            if (isset($ret[$supplier]))
            {
                $ret[$supplier][] = $p;
            }
            elseif (!$supplier)
            {
                $ret['No supplier'][] = $p;
            }
            else
            {
                $ret['Invalid supplier'][] = $p;
            }
        }

        $ret = theelega_remove_falsy($ret);
        return $ret;
    }
}
?>