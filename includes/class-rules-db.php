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

	/**
	 * Returns all rules ordered by priority then insertion order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all(): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'vtail_rules';
		$results = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY priority ASC, id ASC",
			ARRAY_A
		);

		return $results ?? [];
	}

	/**
	 * Returns a single rule by ID, or null if not found.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_by_id( int $id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'vtail_rules';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ?? null;
	}

	/**
	 * Inserts a new rule. Returns the new row ID, or null on failure.
	 * int|false union return requires PHP 8.0+; ?int is the 7.4-compatible equivalent.
	 */
	public static function insert( array $data ): ?int {
		global $wpdb;

		$clean  = self::sanitize_rule_data( $data );
		$result = $wpdb->insert(
			$wpdb->prefix . 'vtail_rules',
			$clean,
			self::get_column_formats( $clean )
		);

		return false === $result ? null : $wpdb->insert_id;
	}

	/**
	 * Updates an existing rule by ID. Returns true on success, false on failure.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$clean  = self::sanitize_rule_data( $data );
		$result = $wpdb->update(
			$wpdb->prefix . 'vtail_rules',
			$clean,
			[ 'id' => $id ],
			self::get_column_formats( $clean ),
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Deletes a rule by ID. Returns true on success, false on failure.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->prefix . 'vtail_rules',
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Sanitizes incoming rule data. Only processes keys that are present,
	 * so this is safe for both full inserts and partial updates.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_rule_data( array $data ): array {
		$sanitized = [];

		if ( isset( $data['keyword'] ) ) {
			$sanitized['keyword'] = sanitize_text_field( $data['keyword'] );
		}

		if ( isset( $data['url'] ) ) {
			// esc_url_raw() is correct for DB storage; esc_url() is for HTML output only.
			$sanitized['url'] = esc_url_raw( $data['url'] );
		}

		foreach ( [ 'case_sensitive', 'max_per_post', 'nofollow', 'new_tab', 'priority', 'active' ] as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = absint( $data[ $field ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Returns the $wpdb format array matching the keys present in $data.
	 *
	 * @return list<string>
	 */
	private static function get_column_formats( array $data ): array {
		$format_map = [
			'keyword'        => '%s',
			'url'            => '%s',
			'case_sensitive' => '%d',
			'max_per_post'   => '%d',
			'nofollow'       => '%d',
			'new_tab'        => '%d',
			'priority'       => '%d',
			'active'         => '%d',
		];

		return array_values( array_intersect_key( $format_map, $data ) );
	}
}
