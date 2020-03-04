<?php
class THEELEGA_PXG_db extends THEELEGA_db
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
        parent::__construct();
    }

    public function get_products($ready_only, $unexported_only, $suppliers)
    {
        $ready_only = $ready_only ? 1 : 0;
        $unexported_only = $unexported_only ? 1 : 0;
        $sup_clause = "";

        if ($suppliers)
        {
            $suppliers = array_map(function($s)
            {
                return "'" . esc_sql($s) . "'";
            }, $suppliers);
            $suppliers = implode(', ', $suppliers);

            $sup_clause = "AND supplier.meta_value IN ($suppliers)";
        }

        $sql = "SELECT DISTINCT
            p.ID,
            p.post_title AS product_title,
            p.post_excerpt AS short_desc,
            p.post_content AS long_desc,
            sku.meta_value AS SKU,
            ready.meta_value AS ready_for_export,
            exported.meta_value AS exported
        FROM {$this->prefix}posts p
        INNER JOIN {$this->prefix}postmeta sku
            ON p.ID = sku.post_id
                AND sku.meta_key = 'sku'
        LEFT OUTER JOIN {$this->prefix}postmeta ready
            ON p.ID = ready.post_id
                AND ready.meta_key = 'ready_for_export'
        LEFT OUTER JOIN {$this->prefix}postmeta exported
            ON p.ID = exported.post_id
                AND exported.meta_key = 'exported'
        LEFT OUTER JOIN {$this->prefix}postmeta supplier
            ON p.ID = supplier.post_id
                AND supplier.meta_key = 'supplier'
        WHERE p.post_type = 'product'
            AND sku.meta_value > ''
            AND ($ready_only = 0 OR IFNULL(ready.meta_value, 0) = '1')
            AND ($unexported_only = 0 OR IFNULL(exported.meta_value, '0') = '0')
            $sup_clause
        ORDER BY p.post_title";
        
        return $this->get_results($sql);
    }

    public function get_product_meta($products, $meta_keys = null)
    {
        $products = empty($products) ? [0] : $products;
        $products = array_map('intval', $products);
        $products = implode(',', $products);

        if (is_array($meta_keys))
        {
            $meta_keys = "'" . implode("', '", $meta_keys) . "'";
            $meta_keys = "AND pm.meta_key IN ($meta_keys)";
        }
        else
        {
            $meta_keys = '';
        }

        $sql = "SELECT pm.post_id, pm.meta_key, pm.meta_value
        FROM {$this->prefix}posts p
        INNER JOIN {$this->prefix}postmeta pm
            ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
            AND p.ID IN ($products)
            $meta_keys";
        
        $res = $this->get_results($sql);

        $ret = [];
        foreach ($res as $row)
        {
            $pid = $row['post_id'];
            $key = trim($row['meta_key'], ':'); //Remove trailing colon that some of them have.
            $ret[$pid][$key] = trim($row['meta_value']);
        }

        return $ret;
    }

    public function get_product_taxonomies($products)
    {
        $products = empty($products) ? [0] : $products;
        $products = array_map('intval', $products);
        $products = implode(',', $products);

        $sql = "SELECT
            p.ID, tt.taxonomy, t.slug, t.name AS label
        FROM {$this->prefix}posts p
        INNER JOIN {$this->prefix}term_relationships tr
            ON p.ID = tr.object_id
        INNER JOIN {$this->prefix}term_taxonomy tt
            ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$this->prefix}terms t
            ON t.term_id = tt.term_id
        WHERE p.post_type = 'product'
            AND p.ID IN ($products)";

        $res = $this->get_results($sql);

        $ret = [];
        foreach ($res as $row)
        {
            $pid = $row['ID'];
            $tax = $row['taxonomy'];
            $slug = $row['slug'];
            $ret[$pid][$tax][$slug] = $row;
        }

        return $ret;
    }

    public function update_posts($updates)
    {
        $products = array_keys($updates);
        $meta_keys = ['ready_for_export', 'exported'];
        $existing = $this->get_product_meta($products, $meta_keys);
        
        foreach ($updates as $pid => $data)
        {
            if ($data['ready'] != $existing[$pid]['ready_for_export'])
            {
                update_post_meta($pid, 'ready_for_export', $data['ready']);
            }
            if ($data['exported'] != $existing[$pid]['exported'])
            {
                update_post_meta($pid, 'exported', $data['exported']);
            }
        }
    }

    public function mark_products($products)
    {
        if (empty($products))
        {
            return;
        }
        $ids = wp_list_pluck($products, 'ID');
        $ids = array_map('intval', $ids);
        $ids = implode(', ', $ids);

        $sql = "UPDATE {$this->prefix}postmeta pm
        SET pm.meta_value = 1
        WHERE pm.meta_key = 'exported'
            AND pm.post_id IN ($ids)";

        $this->query($sql);
    }
}
?>