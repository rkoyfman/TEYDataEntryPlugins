<?php
class THEELEGA_PXG_form_upload_categories
{
    public function html()
    {
        ?>
        <script>
            function THEELEGA_PXG_form_upload_categories_submit(sender)
            {
                var elmStatus = document.getElementById('THEELEGA_PXG_form_upload_categories_status');
                var elmFile = document.getElementById('THEELEGA_PXG_form_upload_categories_file');
                if (!elmFile.files.length)
                {
                    alert('No file selected!');
                    return;
                }

                var file = {xml: elmFile.files[0]};

                var url = '<?= admin_url('admin-ajax.php') ?>';
                var nonce = '<?= wp_create_nonce(__CLASS__) ?>';

                function onSucess(resp)
                {
                    if (resp.messages_html)
                    {
                        elmStatus.innerHTML = resp.messages_html;
                    }
                }

                function onError(resp)
                {
                    if (resp.errors_html)
                    {
                        elmStatus.innerHTML = resp.errors_html;
                    }
                }

                THEELEGA_common_post(url, nonce, 'THEELEGA_PXG_form_upload_categories', null, elmStatus, onSucess, onError, file);
            }
        </script>
        <h3>Upload categories</h3>
        <div>
            Use this form to upload product categories that are missing from the data entry server. This tool only adds categories - it does not delete, modify, or move them.
            <br/><br/>

            The categories are expected to be specified by an XML file consisting of elements named ProductCategoriesIndexed.
            The name of the element that wraps these is unrestricted.
            <br/><br/>

            The element has the following subelements:
            <hr/>

            ID: ID of the term. Used only to report errors, say, if a category has no slug.
            (Optional.)
            <br/>

            CatName: Category name. The name may be prefixed with asterisks (*), indicating that it's a child of a category above it. Number of asterisks indicates nesting level. If a name already exists on the server, but with a different slug, the category will still be processed, but a warning will be printed.
            <br/>

            CatSlag: The slug. Slugs must be unique. If a slug exists, the category is not created. However, it may be used to find the parent of a category further down, so don't remove it.
            <br/>

            CatSelect: A value of 0 or 1 indicating whether the category is included in the import. If a category has ancestors that are not selected, these ancestors will be included anyway.
            <hr/>
        </div>
        <br/>

        <div><input type="file" id="THEELEGA_PXG_form_upload_categories_file" accept=".xml"></div>
        <div><input type="button" value="Submit" onclick="THEELEGA_PXG_form_upload_categories_submit(this);"></div>
        <div id='THEELEGA_PXG_form_upload_categories_status'></div>
        <?php
    }

    /**
     * Takes the XML strings and calls other functions to create the categories.
     * 
     * $errors, $warnings, and $messages are arrays that this method fills as needed.
     * If $errors gets anything in it, the program stops.
     */
    public static function processXML($xml, &$errors, &$warnings, &$messages)
    {
        $db = THEELEGA_PXG_db::get();
        $categories = self::parseXML($xml);


        self::findAncestors($categories, $errors);
        self::selectAncestors($categories);

        $categories_to_create = array_filter($categories, function($c) { return $c['selected']; });

        $categories_current = $db->get_categories();
        self::validate($categories_to_create, $categories_current, $errors, $warnings);

        if ($errors)
        {
            $errors = array_unique($errors);
            return;
        }

        $cc_by_slug = theelega_arr_group_by($categories_current, 'slug');
        array_walk($categories_to_create, function(&$c) use ($cc_by_slug)
        {
            //For categories that already exist, set their term ID.
            $c['term_taxonomy_id'] = theelega_arr_get($cc_by_slug, [$c['slug'], 0, 'term_taxonomy_id']);
        });

        $categories_to_create = theelega_arr_group_by($categories_to_create, 'slug');
        foreach ($categories_to_create as $c)
        {
            $c = $c[0];

            if ($c['term_taxonomy_id'])
            {
                continue;
            }

            $n = ltrim($c['name'], '*');
            $s = $c['slug'];

            //parent_slug was set in findAncestors.
            //Use it to find out parent's term_taxonomy_id.
            $pslug = $c['parent_slug'];
            $pid = theelega_arr_get($categories_to_create, [$pslug, 0, 'term_taxonomy_id']);
            $res = wp_insert_term($n, 'product_cat',
            [
                'slug' => $s,
                'parent' => $pid
            ]);

            if (is_wp_error($res))
            {
                throw new Exception($res->get_error_message());
            }
            else
            {
                //Set term_taxonomy_id for newly created category.
                $categories_to_create[$s]['term_taxonomy_id'] = $res['term_taxonomy_id'];
            }
        }

        $messages[] = "All categories successfully uploaded.";
    }

    /**
     * Takes the XML string and extracts the categories. The returned object is an array of arrays.
     */
    private static function parseXML($xml)
    {
        $doc = theelega_load_xml($xml);
        $pcis = $doc->getElementsByTagName('ProductCategoriesIndexed');
        $ret = [];

        /**@var DOMNode $pci */
        for ($i = 0; $i < $pcis->length; $i++)
        {
            $pci = $pcis->item($i);

            $cat = [];
            /**@var DOMNode $cn */
            foreach ($pci->childNodes as $cn)
            {
                $key = $cn->nodeName;
                $val = $cn->textContent;
                $cat[$key] = $val;
            }

            $ret[] =
            [
                'id' => theelega_arr_get($cat, 'ID'),
                'name' => theelega_arr_get($cat, 'CatName'),
                'slug' => sanitize_title(theelega_arr_get($cat, 'CatSlag')),
                'selected' => theelega_arr_get($cat, 'CatSelect') == '1',
            ];
        }

        return $ret;
    }

    /**
     * For each category in $categories, set two values:
     * 'ancestors': An array containing all of its ancestors, in the form of their indexes in $categories.
     *      Used by selectAncestors() below.
     * 'parent_slug': The slug of the direct parent.
     */
    private static function findAncestors(&$categories, &$errors)
    {
        $ancestors = [];
        $previous_category_level = -1;
        foreach ($categories as $i => &$c)
        {
            $current_level = strlen($c['name']) - strlen(ltrim($c['name'], '*'));
            $level_change = $current_level - $previous_category_level;
            if ($level_change > 1)
            {
                $errors[] = "Category {$c['slug']} has a nesting level of $current_level, $level_change greater than the category before it, which doesn't make sense.";
            }

            $ancestors[$current_level] = $i;
            $c['ancestors'] = array_slice($ancestors, 0, $current_level);

            $last = theelega_arr_get(array_reverse($c['ancestors']), 0);
            $c['parent_slug'] = theelega_arr_get($categories, [$last, 'slug']);

            $previous_category_level = $current_level;

            unset($c);
        }
    }

    /**
     * For each category, ensure that all of its ancestor will get imported.
     */
    private static function selectAncestors(&$categories)
    {
        foreach ($categories as $c)
        {
            if ($c['selected'])
            {
                foreach ($c['ancestors'] as $a)
                {
                    $categories[$a]['selected'] = true;
                }
            }
        }
    }

    /**
     * Check the input for brokenness.
     */
    private static function validate($categories, $categories_current, &$errors, &$warnings)
    {
        $cc_by_name = theelega_arr_group_by($categories_current, 'name', 'slug');
        $slugs = [];

        foreach ($categories as $c)
        {
            $n = ltrim($c['name'], '*');
            $s = $c['slug'];
            $id = $c['id'];

            if (!$n)
            {
                $errors[] = "Category with ID $id has no name.";
            }

            if (!$s)
            {
                $errors[] = "Category with ID $id has no slug.";
            }

            if (!$n || !$s)
            {
                continue;
            }

            if (isset($slugs[$s]))
            {
                $errors[] = "Category slug '$s' appears multiple times.";
            }
            $slugs[$s] = true;

            $s2 = theelega_arr_get($cc_by_name, [$n, 0]);
            if ($s2 && $s != $s2)
            {
                $warnings[] = "Category with the name '$n' already exists, but has a different slug. Your slug: $s. Existing slug: $s2. The new slug will still be created.";
            }
        }

        return $errors;
    }
}
?>