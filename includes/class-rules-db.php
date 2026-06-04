<?php
/**
 * Database abstraction for the vtail_rules table.
 *
 * @package VT_Auto_Internal_Linker
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VTAIL_Rules_DB {

	/**
	 * Creates the vtail_rules table using dbDelta.
	 * Safe to call on re-activation — dbDelta only applies missing changes.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'vtail_rules';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
  id INT NOT NULL AUTO_INCREMENT,
  keyword VARCHAR(255) NOT NULL,
  url VARCHAR(2083) NOT NULL,
  case_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  max_per_post INT NOT NULL DEFAULT 1,
  nofollow TINYINT(1) NOT NULL DEFAULT 0,
  new_tab TINYINT(1) NOT NULL DEFAULT 0,
  priority INT NOT NULL DEFAULT 10,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
