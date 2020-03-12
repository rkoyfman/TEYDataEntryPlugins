<?php
class THEELEGA_PXG_xml
{
    public static function xml()
    {
        $db = THEELEGA_PXG_db::get();
        
        $suppliers = theelega_request_field('THEELEGA_PXG_suppliers');
        $suppliers = stripslashes($suppliers);
        $suppliers = json_decode($suppliers, true);

        $products = THEELEGA_PXG_Product::get_all(true, false, $suppliers);
        $products = array_filter($products, function($p)
        {
            return empty($p->errors) || $p->export_despite_errors;
        });

        $txe = new THEELEGA_XMLElement('Products');
        $txe->children = array_map([self::class, 'product_to_xml'], $products);
        
        $mark = theelega_request_field('THEELEGA_PXG_mark_exported');
        if ($mark)
        {
            $db->mark_products_exported($products);
        } 

        self::print_headers('Products.xml');
        die($txe);
    }

    private static function print_headers($filename)
    {
        header("Content-type: text/xml");
        header("Content-Disposition: attachment; filename=$filename");
        header("Pragma: no-cache");
        header("Expires: 0");
    }

    /**
     * @param THEELEGA_PXG_Product $p
     * @return THEELEGA_XMLElement
    */
    public static function product_to_xml($p)
    {
        $ret = new THEELEGA_XMLElement('Product');

        $ret->addElement('SKU', $p->SKU);
        $ret->addElement('Product_Name', $p->title);
        $ret->addElement('Short_Description', $p->short_desc);
        $ret->addElement('Brand_Name', $p->brand_for_product_name);
        $ret->addElement('Price', $p->price);
        $ret->addElement(self::supplier_xml($p));
        $ret->addElement(self::categories_xml($p));
        $ret->addElement(self::tags_xml($p));
        $ret->addElements(...self::images_xml($p));
        $ret->addElement(self::attributes_xml($p));
        $ret->addElement(self::variations_xml($p));
        $ret->addElement('Description', $p->long_desc);

        return $ret;
    }

    /**
     * @param THEELEGA_PXG_Product $p
     * @return THEELEGA_XMLElement
    */
    private static function supplier_xml($p)
    {
        $ret = new THEELEGA_XMLElement('Supplier');
        $ret->addElements(['Name', $p->supplier], ['Price', '']);
        return $ret;
    }

    /**
     * @param THEELEGA_PXG_Product $p
     * @return THEELEGA_XMLElement
    */
    private static function categories_xml($p)
    {
        $ret = new THEELEGA_XMLElement('Categories');
        foreach ($p->category_chains as $cc)
        {
            $ret->addElement('Category', $cc);
        }

        return $ret;
    }

    /**
     * @param THEELEGA_PXG_Product $p
     * @return THEELEGA_XMLElement
    */
    private static function tags_xml($p)
    {
        $ret = new THEELEGA_XMLElement('Keywords');
        $ret->addElement('Keyword', implode(', ', array_column($p->tags, 'slug')));
        return $ret;
    }

    /**
     * @param THEELEGA_PXG_Product $p
     * @return THEELEGA_XMLElement
    */
    private static function images_xml($p)
    {
        $gi = new THEELEGA_XMLElement('Gallery_Images');
        $it = new THEELEGA_XMLElement('Image_Titles');

        $img_name = strtoupper($p->brand) . ' - ' . $p->title . ' - Main';
        $gi->addElement('Gallery_Image', $p->image);
        $it->addElement('Image_Title', $img_name);

        foreach ($p->gallery_images as $gi2)
        {
            $img_name = strtoupper($p->brand) . ' - ' . $p->title . ' - Gallery Image';
            $gi->addElement('Gallery_Image', $gi2);
            $it->addElement('Image_Title', $img_name);
        }

        foreach ($p->variations as $v)
        {
            $img_name = strtoupper($p->brand) . ' ' . $p->title . ' - ' . $v->color;
            foreach ($v->get_image_urls() as $u)
            {
                $gi->addElement('Gallery_Image', $u);
                $it->addElement('Image_Title', $img_name);
            }
        }

        return [$gi, $it];
    }

    /**
     * @param THEELEGA_PXG_Product $p
     * @return THEELEGA_XMLElement
    */
    private static function attributes_xml($p)
    {
        $attrs = [];
        
        $attrs['Brand'] = $p->brand;
        $attrs[$p->size_attribute_slug] = $p->sizes;
        $attrs[$p->color_attribute_slug] = implode(', ', wp_list_pluck($p->variations, 'color'));
        $attrs = array_merge($attrs, $p->other_attributes);

        $ret = new THEELEGA_XMLElement('Attributes');
        foreach ($attrs as $key => $val)
        {
            if ($key && $val)
            {
                $ret->addElement('Attribute', $key);
                $ret->addElement('Attr_values', $val);
            }
        }

        return $ret;
    }

    /**
     * @param THEELEGA_PXG_Product $p
     * @return THEELEGA_XMLElement
    */
    private static function variations_xml($p)
    {
        $ret = new THEELEGA_XMLElement('Variations');

        foreach ($p->variations as $v)
        {
            foreach ($v->get_image_urls() as $u)
            {
                $ret->addElement(self::variant_xml($p, $v, $u));
            }
        }

        return $ret;
    }

    /**
     * @param THEELEGA_PXG_Product $p
     * @param THEELEGA_PXG_Product_Variation $p
     * @param string $u
     * @return THEELEGA_XMLElement
    */
    private static function variant_xml($p, $v, $u)
    {
        $get = 'theelega_arr_get';
        $ret = new THEELEGA_XMLElement('variant');
        $attrs = array_slice(range(0, 10), 1, null, true);
        
        $attrs[1] = ['Attr_SKU', $v->sku_combined];
        $attrs[2] = ['Brand', $p->brand];
        $attrs[3] = [$p->color_attribute_slug, $v->color];
        $attrs[7] = ['Attr_RegPrice', $p->price];
        $attrs[10] = ['Attr_Image', $u];
        
        foreach ($attrs as $a)
        {
            $ret->addElement('Attr_Name', $get($a, 0, ''));
            $ret->addElement('Attr_Value', $get($a, 1, ''));
        }

        return $ret;
    }
}
?>