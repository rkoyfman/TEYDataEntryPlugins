<?php
class THEELEGA_WPL_db extends THEELEGA_db
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

    public function get_acf_fields_to_show()
    {
        $sql = "SELECT post_excerpt AS slug, post_title AS label, post_content AS settings
        FROM {$this->prefix}posts p
        WHERE p.post_type = 'acf-field'
            AND p.post_status = 'publish'";
        
        $res = $this->get_results($sql);

        $ret = [];
        foreach ($res as $row)
        {
            $row['settings'] = maybe_unserialize($row['settings']);
            if (theelega_arr_get($row['settings'], THEELEGA_WPL_acf_show_field::$setting_name))
            {
                $ret[] = $row;
            }
        }

        return $ret;
    }
}
?>