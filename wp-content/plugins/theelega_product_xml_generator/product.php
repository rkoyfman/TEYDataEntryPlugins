<?php
class THEELEGA_PXG_Product
{
    public $ID = '';
    public $SKU = '';
    public $title = '';
    public $brand = '';
    public $brand_for_product_name = '';
    public $supplier = '';
    public $short_desc = '';
    public $long_desc = '';

    public $price = 0.0;
    public $size_attribute_slug = '';
    public $sizes = '';
    public $color_attribute_slug = '';

    //An associative array
    public $other_attributes = [];

    private $categories = [];
    public $tags = [];

    /**@var THEELEGA_PXG_Product_Variation[] $variations */
    public $variations = [];

    //See create_category_chains() below.
    public $category_chains = [];

    //Validation errors.
    public $errors = [];
    public $warnings = [];

    public $ready_for_export = '0';
    public $exported = '1';
    public $export_despite_errors = false;

    public $image = '';
    public $gallery_images = [];

    public $men_shirt_sizes = [];

    /** @return THEELEGA_PXG_Product[] */
    public static function get_all($ready, $exported, $suppliers)
    {
        $db = THEELEGA_PXG_db::get();
        $main_db = THEELEGA_PXG_main_server_db::get();

        $products = [];
        $suppliers_with_products = $main_db->get_suppliers_with_products($ready, $exported);
        if (!$suppliers)
        {
            $suppliers = array_keys($suppliers_with_products);
        }
        foreach ($suppliers as $s)
        {
            $products = array_merge($products, theelega_arr_get($suppliers_with_products, $s, []));
        }

        $pids = array_column($products, 'ID');
        $meta = $db->get_product_meta($pids);
        $taxonomies = $db->get_product_taxonomies($pids);
        $ms_taxonomies = $main_db->get_taxonomies();
        $ms_woo_attributes = $main_db->get_woo_attributes();
        $ms_suppliers = $main_db->get_suppliers();
        $supplier_brand_mapping = get_option(THEELEGA_PXG_form_supplier_brand_mapping::$option_name);
        $brands = theelega_arr_get($main_db->get_taxonomies(), 'pa_brand', []);
        
        $ms_categories = theelega_arr_get($ms_taxonomies, 'product_cat');
        $ms_category_tree = theelega_build_category_tree($ms_categories);
        
        $products = array_map(function($p) use ($meta, $taxonomies, $ms_taxonomies, $ms_woo_attributes, $ms_suppliers, $supplier_brand_mapping, $brands, $ms_category_tree)
        {
            $ret = new self($p);
            $ret->set_postmeta($meta[$ret->ID]);
            $ret->set_taxonomies($taxonomies[$ret->ID]);
            $ret->set_brand($supplier_brand_mapping, $brands);
            $ret->create_category_chains($ms_category_tree);
            $ret->validate($ms_taxonomies, $ms_woo_attributes, $ms_category_tree, $ms_suppliers);

            return $ret;
        },$products);

        return $products;
    }

    /**
     * @param array $array - An element of the array returned by get_products in db.php
     */
    private function __construct($array)
    {
        $get = 'theelega_arr_get';

        $this->ID = $get($array, 'ID');
        $this->title = $get($array, 'product_title');
        $this->short_desc = $get($array, 'short_desc');
        $this->long_desc = $get($array, 'long_desc');
        $this->SKU = $get($array, 'SKU');
        $this->ready_for_export = $get($array, 'ready_for_export');
        $this->exported = $get($array, 'exported');
    }
    
    /**
     * @param array $meta - An element of the array returned by get_product_meta in db.php
     */
    private function set_postmeta($meta)
    {
        $get = 'theelega_arr_get';

        $this->supplier = $get($meta, 'supplier', '');
        $this->price = floatval($get($meta, 'price', 0.0));

        $this->size_attribute_slug = $get($meta, 'size_attribute_slug');
        $this->color_attribute_slug = $get($meta, 'color_attribute_slug');

        $this->sizes = $get($meta, 'sizes', '');

        $this->image = $get($meta, '_thumbnail_id', '0');
        $this->image = wp_get_attachment_url($this->image);

        $this->gallery_images = $get($meta, '_product_image_gallery', '');
        $this->gallery_images = array_map('wp_get_attachment_url', explode(',', $this->gallery_images));
        $this->gallery_images = theelega_remove_falsy($this->gallery_images);
        
        $this->men_shirt_sizes = $get($meta, 'men_shirt_sizes', '');
        $this->men_shirt_sizes = maybe_unserialize($this->men_shirt_sizes);

        $this->export_despite_errors = $get($meta, 'export_despite_errors', '');

        foreach ($meta as $key => $val)
        {
            if (preg_match('/^other_attribute_\d+_name$/', $key))
            {
                $matches = null;
                preg_match('/\d+/', $key, $matches);
                $num = $matches[0];
                
                $val2 = $get($meta, "other_attribute_{$num}_values", '');
                if ($val && $val2)
                {
                    $this->other_attributes[$val] = $val2;
                }
            }

            $this->try_set_variation_property($key, $val);
        }
    }
    
    /**
     * @param array $taxonomies - An element of the array returned by get_product_taxonomies in db.php
     */
    private function set_taxonomies($taxonomies)
    {
        $get = 'theelega_arr_get';
        
        $this->categories = $get($taxonomies, 'product_cat', []);
        $this->tags = $get($taxonomies, 'product_tag', []);
    }
    
    /**
     * @param array $supplier_brand_mapping - The associative array created on form_supplier_brand_mapping.php and stored as WP option. 
     */
    private function set_brand($supplier_brand_mapping, $brands)
    {
        $get = 'theelega_arr_get';
        $b = $get($supplier_brand_mapping, [$this->supplier, 'brand'], '');
        $b = $get($brands, $b, '');
        $this->brand = $get($b, 'name', '');

        $this->brand_for_product_name = $get($supplier_brand_mapping, [$this->supplier, 'brand_for_product_name'], '');
    }
    
    private function try_set_variation_property($meta_key, $value)
    {
        $variation = $this->get_variation($meta_key,  $value);
        if (!$variation)
        {
            return;
        }
        
        if (preg_match('/^sku\d+$/', $meta_key) && $value)
        {
            $variation->_sku = $value;
            $variation->sku_combined = $this->SKU . '-' . $value;
        }
        elseif (preg_match('/^color\d+$/', $meta_key) && $value)
        {
            $variation->color = $value;
        }
        elseif (preg_match('/^images\d+$/', $meta_key) && $value)
        {
            $variation->images_remote = $value;
        }
        elseif (preg_match('/^image\d+\w$/', $meta_key) && $value)
        {
            $variation->images_local[] = $value;
        }
    }
    
    /*
        Meta keys that represent a property of a variation include the index number of that variation, such as color3.
        Find a variation by that number, or create it if necessary.

        Returns null if it's the wrong kind of meta_key.
    */
    private function get_variation($meta_key, $value)
    {
        if (!$value)
        {
            return null;
        }

        $vindex = $this->get_variation_index($meta_key);
        if (!$vindex)
        {
            return null;
        }

        if (!isset($this->variations[$vindex]))
        {
            $this->variations[$vindex] = new THEELEGA_PXG_Product_Variation();
        }

        return $this->variations[$vindex];
    }
    
    /*
        Meta keys that represent a property of a variation include the index number of that variation, such as color3.
        Extract that number, but only for keys with the expected format.

        If not found, return 0.
    */
    private function get_variation_index($meta_key)
    {
        $get = 'theelega_arr_get';
        
        $vindex = 0;
        foreach (['sku', 'color', 'image'] as $str)
        {
            if (theelega_string_startswith($meta_key, $str))
            {
                $matches = [];
                preg_match('/\d/', $meta_key, $matches);
                $vindex = $get($matches, 0, 0);
                return intval($vindex);
            }
        }
    }

    private function validate($ms_taxonomies, $ms_woo_attributes, $ms_category_tree, $ms_suppliers)
    {
        $get = 'theelega_arr_get';
        
        $variation_colors = [];

        foreach ($this->variations as $i => $v)
        {
            if (!$v->color)
            {
                $this->errors[] = "Variation #$i has no color.";
            }
            else
            {
                $variation_colors[] = $v->color;
            }
        }

        if (!$this->category_chains)
        {
            $this->errors[] = "Product has no categories.";
        }

        foreach ($this->categories as $cat)
        {
            $cat = $cat['slug'];
            $c = $get($ms_category_tree['slugs'], $cat);
            if (!$c)
            {
                $this->errors[] = "Category {$cat} does not exist on the main server.";
            }
            elseif ($c->descendents && isset($this->category_chains[$c->slug]))
            {
                $this->errors[] = "Category {$cat} has descendents. Only leaf categories are allowed.";
            }
        }

        if (!$this->color_attribute_slug)
        {
            if (count($variation_colors))
            {
                $this->errors[] = "There are variations with colors, but a color attribute tag is not given.";
            }
            else
            {
                $this->warnings[] = 'Colors have not been set up.';
            }
        }
        elseif (!isset($ms_woo_attributes[$this->color_attribute_slug]))
        {
            $this->errors[] = "Color attribute slug {$this->color_attribute_slug} does not exist on the main server.";
        }
        elseif (!theelega_string_contains($this->color_attribute_slug, 'color'))
        {
            $this->errors[] = "Color attribute slug {$this->color_attribute_slug} does not contain the word 'color'.";
        }
        elseif (!count($variation_colors))
        {
            $this->errors[] = "If color attribute slug is set, there must be colors, and vice versa.";
        }

        if (!$this->size_attribute_slug)
        {
            if ($this->sizes)
            {
                $this->errors[] = "Sizes are given, but a size attribute tag is not.";
            }
            else
            {
                $this->warnings[] = 'Sizes have not been set up.';
            }
        }
        elseif (!isset($ms_woo_attributes[$this->size_attribute_slug]))
        {
            $this->errors[] = "Size attribute slug {$this->size_attribute_slug} does not exist on the main server.";
        }
        elseif (!theelega_string_contains($this->size_attribute_slug, 'size'))
        {
            $this->errors[] = "Size attribute slug {$this->color_attribute_slug} does not contain the word 'size'.";
        }
        elseif (!$this->sizes)
        {
            $this->errors[] = "If size attribute slug is set, sizes must be given, and vice versa.";
        }

        foreach ($this->other_attributes as $attr => $values)
        {
            //Search for $attr in both the keys (slugs) and values (labels)
            if (!isset($ms_woo_attributes[$attr]) && !in_array($attr, $ms_woo_attributes))
            {
                $this->errors[] = "'Other' attribute '$attr' does not exist on the main server.";
            }
            elseif (!$values)
            {
                $this->errors[] = "No values are given for 'other' attribute '$attr'.";
            }
        } 

        if (!$this->supplier)
        {
            $this->errors[] = "Supplier is required.";
        }
        elseif (!in_array($this->supplier, $ms_suppliers))
        {
            $this->errors[] = "Supplier {$this->supplier} does not exist on the main server.";
        }
        elseif (!$this->brand)
        {
            $this->errors[] = "Brand is required. Check the 'Configure mappings between suppliers and brands' tool to see if the mapping is correct.";
        }

        if (!(floatval($this->price) > 0))
        {
            $this->errors[] = "Price must be a positive number.";
        }
    }
    
    /*
        A category chain is a category from $categories above, plus its ancestors.
        Ancestors are listed from top-level to current category, and separated with the characters " > ".
    */
    private function create_category_chains($ms_category_tree)
    {
        $get = 'theelega_arr_get';
        
        $chains = [];
        foreach ($this->categories as $c)
        {
            $c = $get($ms_category_tree['slugs'], $c['slug']);
            if (!$c)
            {
                continue;
            }

            $as = $c->ancestor_slugs;
            $as = array_reverse($as);
            $as[] = $c->slug;
            
            $chains[$c->slug] = implode(' > ', $as);
        }

        //If a chain is the prefix of another chain, get rid of it. We really only need categories that
        //are leaves - that is, have no descendents. (validate() checks that.)
        foreach ($chains as $cc)
        {
            foreach (array_keys($chains) as $key)
            {
                $prefix = $chains[$key];
                if ($cc > $prefix && theelega_string_startswith($cc, $prefix))
                {
                    unset($chains[$key]);
                }
            }
        }

        $this->category_chains = $chains;
    }
}

class THEELEGA_PXG_Product_Variation
{
    //Form variation SKU by combining it with that of the main product.
    //Empty if not provided.
    public $_sku = '';
    public $sku_combined = '';

    //The color slug associated with this variation.
    //The presence of this, not the SKU, determines if the variation is valid.
    public $color = '';

    //The fields that designate remotely hosted imagees are called things like 'images1'.
    //These are textareas that contain a newline-separated list of all images for a variation.
    public $images_remote = '';

    //For each variation, there are several fields that each select one image from the media picker.
    //They have names like 'image3d'
    //The value this field receives is the ID of a post with post_type='attachment'.
    public $images_local = [];

    public function get_image_urls()
    {
        $ret = [$this->images_remote];

        foreach ($this->images_local as $id)
        {
            $url = wp_get_attachment_url($id);
            if ($url)
            {
                $ret[] = $url;
            }
        }

        $ret = implode("\n", $ret);
        //Split by whitespace, comma, or pipe.
        $ret = preg_split('/(\s|,|\|)+/', $ret, -1, PREG_SPLIT_NO_EMPTY);
        
        return $ret;
    }
}
?>