<?php
class THEELEGA_PXG_Product
{
    public $ID = '';
    public $SKU = '';
    public $title = '';
    public $brand = '';    
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

    public $ready_for_export = '0';
    public $exported = '1';

    public $image = '';

    public static function get_all($ready_only, $unexported_only, $suppliers)
    {
        $db = THEELEGA_PXG_db::get();
        $main_db = THEELEGA_PXG_main_server_db::get();

        $products = $db->get_products($ready_only, $unexported_only, $suppliers);
        $pids = array_column($products, 'ID');
        $meta = $db->get_product_meta($pids);
        $taxonomies = $db->get_product_taxonomies($pids);
        $ms_taxonomies = $main_db->get_taxonomies();
        $ms_woo_attributes = $main_db->get_woo_attributes();
        $supplier_brand_mapping = get_option(THEELEGA_PXG_form_supplier_brand_mapping::$option_name);
        $brands = theelega_arr_get($main_db->get_taxonomies(), 'pa_brand', []);
        
        $products = array_map(function($p) use ($meta, $taxonomies, $ms_taxonomies, $ms_woo_attributes, $supplier_brand_mapping, $brands)
        {
            $get = 'theelega_arr_get';

            $ret = new self($p);
            $ret->set_postmeta($meta[$ret->ID]);
            $ret->set_taxonomies($taxonomies[$ret->ID]);
            $ret->set_brand($supplier_brand_mapping, $brands);
            $ret->create_category_chains();
            $ret->validate($ms_taxonomies, $ms_woo_attributes);

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
        $b = $get($supplier_brand_mapping, $this->supplier, '');
        $b = $get($brands, $b, '');
        $this->brand = $get($b, 'name', '');
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

    private function validate($ms_taxonomies, $ms_woo_attributes)
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

        $cats = $get($ms_taxonomies,'product_cat', []);
        foreach ($this->categories as $cat)
        {
            if (!$cats[$cat['slug']])
            {
                $this->errors[] = "Category {$cat['label']} does not exist on the main server.";
            }
        }
        
        $tags = $get($ms_taxonomies,'product_tag', []);
        foreach ($this->tags as $tag)
        {
            if (!$tags[$tag['slug']])
            {
                $this->errors[] = "Tag {$tag['label']} does not exist on the main server.";
            }
        }

        if ($this->color_attribute_slug && !isset($ms_woo_attributes['color'][$this->color_attribute_slug]))
        {
            $this->errors[] = "Color attribute slug {$this->color_attribute_slug} does not exist on the main server.";
        }

        if (!!$this->color_attribute_slug !== !!count($variation_colors))
        {
            $this->errors[] = "If variation attribute slug is set, there must be colors, and vice versa.";
        }

        if ($this->size_attribute_slug && !isset($ms_woo_attributes['size'][$this->size_attribute_slug]))
        {
            $this->errors[] = "Size attribute slug {$this->size_attribute_slug} does not exist on the main server.";
        }

        if (!!$this->size_attribute_slug !== !!$this->sizes)
        {
            $this->errors[] = "If size attribute slug is set, sizes must be given, and vice versa.";
        }

        if (!$this->brand)
        {
            $this->errors[] = "Brand is required.";
        }

        if (!$this->supplier)
        {
            $this->errors[] = "Supplier is required.";
        }

        if (!$this->price || $this->price < 0)
        {
            $this->errors[] = "Price must be a positive number.";
        }
    }
    
    /*
        A category chain is a category from $categories above, plus its ancestors.
        Ancestors are listed from top-level to current category, and separated with the characters " > ".
    */
    private function create_category_chains()
    {
        $get = 'theelega_arr_get';
        
        static $category_tree;
        if (!$category_tree)
        {
            $main_db = THEELEGA_PXG_main_server_db::get();
            $taxs = $main_db->get_taxonomies();
            $cats = $get($taxs, 'product_cat');
            $category_tree = theelega_build_category_tree($cats);
        }
        
        $chains = [];
        foreach ($this->categories as $c)
        {
            $c = $get($category_tree['slugs'], $c['slug']);
            if (!$c)
            {
                continue;
            }

            $as = $c->ancestor_slugs;
            $as = array_reverse($as);
            $as[] = $c->slug;
            
            $chains[] = implode(' > ', $as);
        }

        //If a chain is the prefix of another chain, get rid of it. The product will be added to those categories anyway.
        //Meanwhile, they create clutter.
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

        $this->category_chains = array_values($chains);
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
        $ret = preg_split('/(\s|,|\|)+/', $ret);
        
        return $ret;
    }
}
?>