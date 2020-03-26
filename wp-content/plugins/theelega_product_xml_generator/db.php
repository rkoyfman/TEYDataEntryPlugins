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

    /**
     * @param bool $ready Filter by ready_for_export value; null to ignore.
     * @param bool $exported Filter by exported value; null to ignore.
     * @param array $suppliers Filter by supplier name; null or empty to ignore.
     */
    public function get_products($ready, $exported, $suppliers)
    {
        $ready_clause = '';
        if ($ready !== null)
        {
            $ready = $ready ? 1 : 0;
            $ready_clause = "AND IFNULL(ready.meta_value, '0') = '$ready'";
        }

        $exported_clause = '';
        if ($exported !== null)
        {
            $exported = $exported ? 1 : 0;
            $exported_clause = "AND IFNULL(exported.meta_value, '0') = '$exported'";
        }
        
        $supplier_clause = "";
        if ($suppliers)
        {
            $suppliers = array_map(function($s)
            {
                return "'" . esc_sql($s) . "'";
            }, $suppliers);
            $suppliers = implode(', ', $suppliers);

            $supplier_clause = "AND supplier.meta_value IN ($suppliers)";
        }

        $sql = "SELECT DISTINCT
            p.ID,
            p.post_title AS product_title,
            p.post_excerpt AS short_desc,
            p.post_content AS long_desc,
            sku.meta_value AS SKU,
            ready.meta_value AS ready_for_export,
            exported.meta_value AS exported,
            supplier.meta_value AS supplier
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
            AND p.post_status <> 'trash'
            AND sku.meta_value > ''
            $ready_clause
            $exported_clause
            $supplier_clause
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

        $sql = "SELECT p.ID, tt.taxonomy, t.slug, t.name AS label
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

    public function get_categories()
    {
        $sql = "SELECT t.*, tt.*
        FROM {$this->prefix}terms t
        INNER JOIN {$this->prefix}term_taxonomy tt
            ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'product_cat'";

        $ret = $this->get_results($sql);
        return $ret;
    }

    public function update_posts($updates)
    {
        $products = array_keys($updates);
        $meta_keys = ['ready_for_export', 'exported', 'export_despite_errors'];
        $existing = $this->get_product_meta($products, $meta_keys);
        
        foreach ($updates as $pid => $data)
        {
            foreach ($data as $key => $value)
            {
                if ($value != theelega_arr_get($existing, [$pid, $key]))
                {
                    update_post_meta($pid, $key, $value);
                }
            }
        }
    }

    public function mark_products_exported($products)
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

    public function trash_products($product_ids)
    {
        if (empty($product_ids))
        {
            return;
        }
        $product_ids = array_map('intval', $product_ids);
        $product_ids = implode(', ', $product_ids);

        $sql = "UPDATE {$this->prefix}posts p
        INNER JOIN {$this->prefix}postmeta pm
            ON p.ID = pm.post_id
        SET post_status = 'trash'
        WHERE pm.meta_value = '1'
            AND p.post_type = 'product'
            AND p.ID IN ($product_ids)";

        $this->query($sql);
    }
    
    public function delete_products($product_ids)
    {
        if (empty($product_ids))
        {
            return;
        }
        $product_ids = array_map('intval', $product_ids);

        //Check that the products are exported.
        $sql = "SELECT p.ID
        FROM {$this->prefix}posts p
        INNER JOIN {$this->prefix}postmeta exported
            ON p.ID = exported.post_id
                AND exported.meta_key = 'exported'
        WHERE post_id IN (". implode(', ', $product_ids) .")";

        $product_ids = $this->get_col($sql);
        
        if (empty($product_ids))
        {
            return;
        }

        //Get the IDs of images to delete.
        $image_ids = $this->find_images_to_delete($product_ids);
        $image_metas = [];
        if (!empty($image_ids))
        {
            $image_ids = implode(',', $image_ids);
            
            //Get the image metadata with image paths.
            $sql = "SELECT meta_value
            FROM {$this->prefix}postmeta
            WHERE post_id IN ($image_ids)
                AND meta_key = '_wp_attachment_metadata'";
            
            $image_metas = $this->get_col($sql);
        }

        $this->delete_posts($product_ids);
        $this->delete_files($image_metas);
    }

    private function find_images_to_delete($product_ids)
    {
        //Make product_ids associative.
        $product_ids = array_flip($product_ids);

        //Get images for all products
        $sql = "SELECT *
        FROM {$this->prefix}postmeta
        WHERE meta_key IN ('_thumbnail_id', '_product_image_gallery')";
        
        $res = $this->get_results($sql);

        //Create a map from images to products. Images are actually the IDs of posts of type 'attachment'.
        $attachments_with_products = [];
        foreach ($res as $row)
        {
            $pid = $row['post_id'];
            $aids = [];
            
            //Extract IDs from meta_value, which is a comma-separated list for _product_image_gallery.
            //I could use explode(), but this avoids a slew of edge cases.
            preg_match('/\d+/',$row['meta_value'], $aids);
            foreach ($aids as $aid)
            {
                //Add the product to the 'true' or 'false' array, based on whether it's to be deleted.
                $in_product_ids = isset($product_ids[$pid]);
                $attachments_with_products[$aid][$in_product_ids] = $pid;
            }
        }


        //Now, collect the attachments that 
        //1) belong to a product in the list of products to delete;
        //2) do not belong to any product outside the list.
        $attachment_ids = [];
        foreach ($attachments_with_products as $aid => $arr)
        {
            if (!empty($arr[true]) && empty($arr[false]))
            {
                $attachment_ids[] = $aid;
            }
        }

        return $attachment_ids;
    }

    private function delete_posts($product_ids)
    {
        if (empty($product_ids))
        {
            return;
        }
        $product_ids = array_map('intval', $product_ids);
        $product_ids = implode(', ', $product_ids);

        $sql = "DELETE p, pm, tr, c, cm
        FROM {$this->prefix}posts p
        LEFT OUTER JOIN {$this->prefix}postmeta pm
            ON p.ID = pm.post_id
        LEFT OUTER JOIN {$this->prefix}term_relationships tr
            ON p.ID = tr.object_id
        LEFT OUTER JOIN {$this->prefix}comments c
            ON p.ID = c.comment_post_ID
        LEFT OUTER JOIN {$this->prefix}commentmeta cm
            ON c.comment_ID = cm.comment_id
        WHERE object_id IN ($product_ids)";

        //$this->query($sql);
    }

    private function delete_files($image_metas)
    {
        $paths = [];
        $basedir = wp_upload_dir()['basedir'];

        foreach ($image_metas as $im)
        {
            $im = maybe_unserialize($im);
            if (!isset($im['file']) || !isset($im['sizes']))
            {
                continue;
            }
            $file = $im['file'];
            $paths[] = $basedir . '/' . $file;
            $dir = $basedir . '/' . dirname($file);

            foreach ($im['sizes'] as $s)
            {
                $paths[] = $dir . '/' . $s['file'];
            }
        }

        $paths = array_unique($paths);
        array_map('wp_delete_file', $paths);
    }

    public function set_mens_shirt_sizes($pids, $selectedSizes)
    {
        foreach ($pids as $p)
        {
            update_post_meta($p, 'men_shirt_sizes', $selectedSizes);
        }
    }
}
?>