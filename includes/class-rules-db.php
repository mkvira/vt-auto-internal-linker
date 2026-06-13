<?php
/**
 * Database abstraction for vtail_rules and vtail_keywords tables.
 *
 * @package VT_Auto_Internal_Linker
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VTAIL_Rules_DB {

	// -------------------------------------------------------------------------
	// Table creation & schema upgrade
	// -------------------------------------------------------------------------

	/**
	 * Creates the vtail_rules table using dbDelta.
	 * Safe to call on re-activation — dbDelta only applies missing changes.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'vtail_rules';
		$charset_collate = $wpdb->get_charset_collate();

		// Legacy columns (keyword, case_sensitive, nofollow, new_tab, priority) are kept
		// intentionally — dbDelta cannot drop columns. They are ignored in all queries.
		$sql = "CREATE TABLE {$table_name} (
  id INT NOT NULL AUTO_INCREMENT,
  keyword VARCHAR(255) NOT NULL DEFAULT '',
  url VARCHAR(2083) NOT NULL,
  title VARCHAR(255) NOT NULL DEFAULT '',
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

		self::create_keywords_table();
	}

	/**
	 * Creates the vtail_keywords table using dbDelta.
	 */
	public static function create_keywords_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'vtail_keywords';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
  id INT NOT NULL AUTO_INCREMENT,
  rule_id INT NOT NULL,
  keyword VARCHAR(255) NOT NULL,
  max_per_post INT NOT NULL DEFAULT 1,
  priority INT NOT NULL DEFAULT 10,
  total_limit INT NOT NULL DEFAULT 0,
  case_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  nofollow TINYINT(1) NOT NULL DEFAULT 0,
  new_tab TINYINT(1) NOT NULL DEFAULT 0,
  anchor VARCHAR(255) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY rule_id (rule_id)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Runs on plugins_loaded. Upgrades the DB schema when VTAIL_DB_VERSION increases.
	 */
	public static function maybe_upgrade(): void {
		$installed = (int) get_option( 'vtail_db_version', 1 );

		if ( $installed >= VTAIL_DB_VERSION ) {
			return;
		}

		if ( $installed < 2 ) {
			self::upgrade_to_v2();
		}

		if ( $installed < 3 ) {
			self::upgrade_to_v3();
		}

		if ( $installed < 4 ) {
			self::upgrade_to_v4();
		}

		update_option( 'vtail_db_version', VTAIL_DB_VERSION, false );
	}

	/**
	 * v1 → v2: create vtail_keywords and migrate existing rules.
	 */
	private static function upgrade_to_v2(): void {
		global $wpdb;

		self::create_keywords_table();

		$rules = $wpdb->get_results(
			"SELECT id, keyword, case_sensitive, max_per_post, nofollow, new_tab, priority
			 FROM {$wpdb->prefix}vtail_rules
			 WHERE keyword != ''",
			ARRAY_A
		);

		if ( empty( $rules ) ) {
			return;
		}

		$keywords_table = $wpdb->prefix . 'vtail_keywords';

		foreach ( $rules as $rule ) {
			$already_migrated = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$keywords_table} WHERE rule_id = %d",
					$rule['id']
				)
			);

			if ( $already_migrated > 0 ) {
				continue;
			}

			$wpdb->insert(
				$keywords_table,
				[
					'rule_id'        => (int) $rule['id'],
					'keyword'        => $rule['keyword'],
					'max_per_post'   => (int) $rule['max_per_post'],
					'priority'       => (int) $rule['priority'],
					'total_limit'    => 0,
					'case_sensitive' => (int) $rule['case_sensitive'],
					'nofollow'       => (int) $rule['nofollow'],
					'new_tab'        => (int) $rule['new_tab'],
					'anchor'         => '',
					'active'         => 1,
				],
				[ '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d' ]
			);
		}
	}

	/**
	 * v3 → v4: add title column to vtail_rules.
	 */
	private static function upgrade_to_v4(): void {
		self::create_table();
	}

	/**
	 * v2 → v3: fix anchor = '0' caused by a format-string ordering bug in v1.1.0.
	 * get_keyword_formats() used $map key order instead of $data key order, so
	 * VARCHAR anchor was formatted as %d, converting '' to 0 on every insert/update.
	 */
	private static function upgrade_to_v3(): void {
		global $wpdb;

		$wpdb->query(
			"UPDATE {$wpdb->prefix}vtail_keywords SET anchor = '' WHERE anchor = '0'"
		);
	}

	// -------------------------------------------------------------------------
	// Rule CRUD (new schema — used by new admin UI after Task 3)
	// -------------------------------------------------------------------------

	/**
	 * Returns all rules with keyword count, ordered by insertion order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all_rules(): array {
		global $wpdb;

		$rules_table    = $wpdb->prefix . 'vtail_rules';
		$keywords_table = $wpdb->prefix . 'vtail_keywords';

		return $wpdb->get_results(
			"SELECT r.id, r.title, r.url, r.max_per_post, r.active, r.created_at,
			        GROUP_CONCAT(k.id ORDER BY k.priority ASC, k.id ASC SEPARATOR ',') AS keyword_ids,
			        GROUP_CONCAT(k.keyword ORDER BY k.priority ASC, k.id ASC SEPARATOR '|||') AS keyword_texts
			 FROM {$rules_table} r
			 LEFT JOIN {$keywords_table} k ON k.rule_id = r.id
			 GROUP BY r.id
			 ORDER BY r.id ASC",
			ARRAY_A
		) ?? [];
	}

	/**
	 * Returns a single rule by ID, or null if not found.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_rule_by_id( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, title, url, max_per_post, active
				 FROM {$wpdb->prefix}vtail_rules
				 WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $row ?? null;
	}

	/**
	 * Inserts a new rule. Returns the new row ID, or null on failure.
	 */
	public static function insert_rule( array $data ): ?int {
		global $wpdb;

		$clean = self::sanitize_rule_fields( $data );

		if ( empty( $clean['url'] ) ) {
			return null;
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'vtail_rules',
			$clean,
			self::get_rule_formats( $clean )
		);

		return false === $result ? null : $wpdb->insert_id;
	}

	/**
	 * Updates a rule by ID. Returns true on success.
	 */
	public static function update_rule( int $id, array $data ): bool {
		global $wpdb;

		$clean  = self::sanitize_rule_fields( $data );
		$result = $wpdb->update(
			$wpdb->prefix . 'vtail_rules',
			$clean,
			[ 'id' => $id ],
			self::get_rule_formats( $clean ),
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Deletes a rule and cascades to its keywords and their stats.
	 */
	public static function delete_rule( int $id ): bool {
		global $wpdb;

		$keywords = self::get_keywords_by_rule( $id );
		foreach ( $keywords as $kw ) {
			self::delete_keyword_stats( (int) $kw['id'] );
		}

		$wpdb->delete( $wpdb->prefix . 'vtail_keywords', [ 'rule_id' => $id ], [ '%d' ] );

		$result = $wpdb->delete( $wpdb->prefix . 'vtail_rules', [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	// -------------------------------------------------------------------------
	// Keyword CRUD
	// -------------------------------------------------------------------------

	/**
	 * Returns a single keyword by ID, or null if not found.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_keyword_by_id( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vtail_keywords WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $row ?? null;
	}

	/**
	 * Returns all keywords for a rule, ordered by priority then insertion order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_keywords_by_rule( int $rule_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vtail_keywords
				 WHERE rule_id = %d
				 ORDER BY priority ASC, id ASC",
				$rule_id
			),
			ARRAY_A
		) ?? [];
	}

	/**
	 * Inserts a new keyword. Returns the new row ID, or null on failure.
	 */
	public static function insert_keyword( array $data ): ?int {
		global $wpdb;

		$clean = self::sanitize_keyword_fields( $data );

		if ( empty( $clean['keyword'] ) || empty( $clean['rule_id'] ) ) {
			return null;
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'vtail_keywords',
			$clean,
			self::get_keyword_formats( $clean )
		);

		return false === $result ? null : $wpdb->insert_id;
	}

	/**
	 * Updates a keyword by ID. Returns true on success.
	 */
	public static function update_keyword( int $id, array $data ): bool {
		global $wpdb;

		$clean  = self::sanitize_keyword_fields( $data );
		$result = $wpdb->update(
			$wpdb->prefix . 'vtail_keywords',
			$clean,
			[ 'id' => $id ],
			self::get_keyword_formats( $clean ),
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Deletes a keyword and its stats.
	 */
	public static function delete_keyword( int $id ): bool {
		global $wpdb;

		self::delete_keyword_stats( $id );

		$result = $wpdb->delete( $wpdb->prefix . 'vtail_keywords', [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	// -------------------------------------------------------------------------
	// Combined query for the linker — single JOIN, static-cached per request
	// -------------------------------------------------------------------------

	/**
	 * Returns all active rules with their active keywords in a single query.
	 * Result is static-cached so the_content firing multiple times costs one DB hit.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_active_rules_with_keywords(): array {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			self::build_active_rules_query( $wpdb ),
			ARRAY_A
		);

		$cache = self::group_rules_with_keywords( $rows ?? [] );

		return $cache;
	}

	/**
	 * Builds the JOIN query string for get_active_rules_with_keywords().
	 */
	private static function build_active_rules_query( \wpdb $wpdb ): string {
		$r = $wpdb->prefix . 'vtail_rules';
		$k = $wpdb->prefix . 'vtail_keywords';

		return "SELECT r.id AS rule_id, r.url, r.max_per_post AS rule_max_per_post,
		               k.id AS keyword_id, k.keyword, k.max_per_post, k.priority,
		               k.total_limit, k.case_sensitive, k.nofollow, k.new_tab, k.anchor
		        FROM {$r} r
		        INNER JOIN {$k} k ON k.rule_id = r.id
		        WHERE r.active = 1 AND k.active = 1
		        ORDER BY r.id ASC, k.priority ASC, k.id ASC";
	}

	/**
	 * Groups flat JOIN rows into a rule_id-keyed array with nested keywords.
	 *
	 * @param  array<int, array<string, mixed>> $rows
	 * @return array<int, array<string, mixed>>
	 */
	private static function group_rules_with_keywords( array $rows ): array {
		$grouped = [];

		foreach ( $rows as $row ) {
			$rule_id = (int) $row['rule_id'];

			if ( ! isset( $grouped[ $rule_id ] ) ) {
				$grouped[ $rule_id ] = [
					'id'           => $rule_id,
					'url'          => $row['url'],
					'max_per_post' => (int) $row['rule_max_per_post'],
					'keywords'     => [],
				];
			}

			$grouped[ $rule_id ]['keywords'][] = [
				'id'             => (int) $row['keyword_id'],
				'keyword'        => $row['keyword'],
				'max_per_post'   => (int) $row['max_per_post'],
				'priority'       => (int) $row['priority'],
				'total_limit'    => (int) $row['total_limit'],
				'case_sensitive' => (int) $row['case_sensitive'],
				'nofollow'       => (int) $row['nofollow'],
				'new_tab'        => (int) $row['new_tab'],
				'anchor'         => $row['anchor'],
			];
		}

		return $grouped;
	}

	// -------------------------------------------------------------------------
	// Stats (wp_options, autoload=no)
	// -------------------------------------------------------------------------

	/**
	 * Returns the full stats array: [ keyword_id => [ count, posts[] ] ]
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_stats(): array {
		$stats = get_option( 'vtail_stats', [] );
		return is_array( $stats ) ? $stats : [];
	}

	/**
	 * Persists the full stats array. Always saves with autoload=no.
	 *
	 * @param array<string, array<string, mixed>> $stats
	 */
	public static function update_stats( array $stats ): void {
		update_option( 'vtail_stats', $stats, false );
	}

	/**
	 * Returns stats for a single keyword. Defaults to count=0, posts=[].
	 *
	 * @return array{ count: int, posts: list<int> }
	 */
	public static function get_keyword_stats( int $keyword_id ): array {
		$stats = self::get_stats();
		$key   = (string) $keyword_id;

		return $stats[ $key ] ?? [ 'count' => 0, 'posts' => [] ];
	}

	/**
	 * Removes stats for a keyword. Called on keyword/rule deletion.
	 */
	public static function delete_keyword_stats( int $keyword_id ): void {
		$stats = self::get_stats();
		unset( $stats[ (string) $keyword_id ] );
		self::update_stats( $stats );
	}

	// -------------------------------------------------------------------------
	// LEGACY methods — kept for backward compatibility with current admin UI.
	// Will be removed in Task 3 (admin refactor).
	// -------------------------------------------------------------------------

	/** @deprecated Use get_all_rules() after Task 3 admin refactor. */
	public static function get_all(): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'vtail_rules';
		$results = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY priority ASC, id ASC",
			ARRAY_A
		);

		return $results ?? [];
	}

	/** @deprecated Use get_rule_by_id() after Task 3 admin refactor. */
	public static function get_by_id( int $id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'vtail_rules';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ?? null;
	}

	/** @deprecated Use insert_rule() + insert_keyword() after Task 3 admin refactor. */
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

	/** @deprecated Use update_rule() + update_keyword() after Task 3 admin refactor. */
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

	/** @deprecated Use delete_rule() after Task 3 admin refactor. */
	public static function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->prefix . 'vtail_rules',
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Sanitizes rule-level fields (url, max_per_post, active).
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_rule_fields( array $data ): array {
		$sanitized = [];

		if ( isset( $data['title'] ) ) {
			$sanitized['title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['url'] ) ) {
			$sanitized['url'] = esc_url_raw( $data['url'] );
		}
		if ( isset( $data['max_per_post'] ) ) {
			$sanitized['max_per_post'] = max( 1, absint( $data['max_per_post'] ) );
		}
		if ( isset( $data['active'] ) ) {
			$sanitized['active'] = absint( $data['active'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitizes keyword-level fields.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_keyword_fields( array $data ): array {
		$sanitized = [];

		if ( isset( $data['rule_id'] ) ) {
			$sanitized['rule_id'] = absint( $data['rule_id'] );
		}
		if ( isset( $data['keyword'] ) ) {
			$sanitized['keyword'] = sanitize_text_field( $data['keyword'] );
		}
		// anchor: stored without #; only slug-safe characters allowed
		if ( isset( $data['anchor'] ) ) {
			$sanitized['anchor'] = sanitize_title( $data['anchor'] );
		}

		$int_fields = [ 'max_per_post', 'priority', 'total_limit', 'case_sensitive', 'nofollow', 'new_tab', 'active' ];
		foreach ( $int_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = absint( $data[ $field ] );
			}
		}

		if ( isset( $sanitized['max_per_post'] ) ) {
			$sanitized['max_per_post'] = max( 1, $sanitized['max_per_post'] );
		}
		if ( isset( $sanitized['priority'] ) ) {
			$sanitized['priority'] = max( 1, $sanitized['priority'] );
		}

		return $sanitized;
	}

	/**
	 * Returns formats in the same key order as $data, not $map.
	 * array_intersect_key preserves $map order which can diverge from $data order;
	 * $wpdb->insert() consumes formats positionally, so order must match $data.
	 *
	 * @return list<string>
	 */
	private static function get_rule_formats( array $data ): array {
		$map     = [ 'title' => '%s', 'url' => '%s', 'max_per_post' => '%d', 'active' => '%d' ];
		$formats = [];
		foreach ( array_keys( $data ) as $key ) {
			if ( isset( $map[ $key ] ) ) {
				$formats[] = $map[ $key ];
			}
		}
		return $formats;
	}

	/**
	 * @return list<string>
	 */
	private static function get_keyword_formats( array $data ): array {
		$map = [
			'rule_id'        => '%d',
			'keyword'        => '%s',
			'max_per_post'   => '%d',
			'priority'       => '%d',
			'total_limit'    => '%d',
			'case_sensitive' => '%d',
			'nofollow'       => '%d',
			'new_tab'        => '%d',
			'anchor'         => '%s',
			'active'         => '%d',
		];
		$formats = [];
		foreach ( array_keys( $data ) as $key ) {
			if ( isset( $map[ $key ] ) ) {
				$formats[] = $map[ $key ];
			}
		}
		return $formats;
	}

	// Legacy private helpers — used by deprecated public methods above.

	/** @deprecated */
	private static function sanitize_rule_data( array $data ): array {
		$sanitized = [];

		if ( isset( $data['keyword'] ) ) {
			$sanitized['keyword'] = sanitize_text_field( $data['keyword'] );
		}
		if ( isset( $data['url'] ) ) {
			$sanitized['url'] = esc_url_raw( $data['url'] );
		}

		foreach ( [ 'case_sensitive', 'max_per_post', 'nofollow', 'new_tab', 'priority', 'active' ] as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = absint( $data[ $field ] );
			}
		}

		return $sanitized;
	}

	/** @deprecated */
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
