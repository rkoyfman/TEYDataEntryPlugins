<?php
/**
 * Parameters for connecting to the database of the main website.
 * Add real parameters to the file wp-config-pgx.php
 * This file exists so it can be stored on Github without uploading a DB password.
*/
define('THEELEGA_PXG_main_site_db_parameters', array(
	'DB_HOST' => 'host',
	'DB_NAME' => 'db',
	'DB_USER' => 'user',
	'DB_PASSWORD' => 'password',
	'DB_TABLE_PREFIX' => 'prefix',
));
?>