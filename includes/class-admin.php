<?php
/**
 * Admin list table and page for managing keyword rules.
 *
 * @package VT_Auto_Internal_Linker
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// ---------------------------------------------------------------------------
// List table
// ---------------------------------------------------------------------------

class VTAIL_Rules_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'rule',
			'plural'   => 'rules',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'keyword'      => esc_html__( 'Keyword', 'vt-auto-internal-linker' ),
			'url'          => esc_html__( 'URL', 'vt-auto-internal-linker' ),
			'max_per_post' => esc_html__( 'Max / Post', 'vt-auto-internal-linker' ),
			'active'       => esc_html__( 'Active', 'vt-auto-internal-linker' ),
		];
	}

	public function get_sortable_columns(): array {
		return [];
	}

	public function prepare_items(): void {
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$all_rules    = VTAIL_Rules_DB::get_all();
		$total_items  = count( $all_rules );

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
		] );

		$this->items = array_slice( $all_rules, ( $current_page - 1 ) * $per_page, $per_page );
	}

	/**
	 * Fallback renderer for columns without a dedicated column_ method.
	 *
	 * @param array<string, mixed> $item
	 */
	public function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * Keyword column — carries row actions (edit / delete).
	 *
	 * @param array<string, mixed> $item
	 */
	public function column_keyword( array $item ): string {
		$id         = absint( $item['id'] );
		$base_url   = admin_url( 'options-general.php?page=vtail-rules' );
		$edit_url   = add_query_arg( [ 'action' => 'edit', 'id' => $id ], $base_url );
		$delete_url = wp_nonce_url(
			add_query_arg( [ 'action' => 'delete', 'id' => $id ], $base_url ),
			'vtail_delete_rule_' . $id
		);

		$actions = [
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'vt-auto-internal-linker' )
			),
			'delete' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $delete_url ),
				esc_html__( 'Delete', 'vt-auto-internal-linker' )
			),
		];

		return esc_html( $item['keyword'] ) . $this->row_actions( $actions );
	}

	/**
	 * URL column — renders as a clickable link.
	 *
	 * @param array<string, mixed> $item
	 */
	public function column_url( array $item ): string {
		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $item['url'] ),
			esc_html( $item['url'] )
		);
	}

	/**
	 * Active column — human-readable Yes / No.
	 *
	 * @param array<string, mixed> $item
	 */
	public function column_active( array $item ): string {
		return $item['active']
			? esc_html__( 'Yes', 'vt-auto-internal-linker' )
			: esc_html__( 'No', 'vt-auto-internal-linker' );
	}
}

// ---------------------------------------------------------------------------
// Admin page controller
// ---------------------------------------------------------------------------

class VTAIL_Admin {

	public function register_menu(): void {
		add_options_page(
			__( 'Auto Internal Linker', 'vt-auto-internal-linker' ),
			__( 'Auto Internal Linker', 'vt-auto-internal-linker' ),
			'manage_options',
			'vtail-rules',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		if ( 'add' === $action || 'edit' === $action ) {
			$this->render_form_page( $action );
			return;
		}

		$this->render_list_page();
	}

	// -------------------------------------------------------------------------
	// List view
	// -------------------------------------------------------------------------

	private function render_list_page(): void {
		$this->handle_delete();
		$this->handle_settings_save();

		$table   = new VTAIL_Rules_List_Table();
		$table->prepare_items();
		$add_url = add_query_arg( [ 'page' => 'vtail-rules', 'action' => 'add' ], admin_url( 'options-general.php' ) );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<a href="' . esc_url( $add_url ) . '" class="page-title-action">' . esc_html__( 'Add New Rule', 'vt-auto-internal-linker' ) . '</a>';
		echo '<hr class="wp-header-end">';
		$this->show_notice();
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="vtail-rules" />';
		$table->display();
		echo '</form>';
		$this->render_settings_section();
		echo '</div>';
	}

	private function handle_delete(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		if ( 'delete' !== $action ) {
			return;
		}

		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'vtail_delete_rule_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'vt-auto-internal-linker' ) );
		}

		VTAIL_Rules_DB::delete( $id );

		wp_safe_redirect( add_query_arg( [ 'page' => 'vtail-rules', 'deleted' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	private function show_notice(): void {
		if ( ! empty( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule deleted successfully.', 'vt-auto-internal-linker' ) . '</p></div>';
		}
		if ( ! empty( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule saved successfully.', 'vt-auto-internal-linker' ) . '</p></div>';
		}
		if ( ! empty( $_GET['settings_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'vt-auto-internal-linker' ) . '</p></div>';
		}
	}

	private function handle_settings_save(): void {
		if ( empty( $_POST['vtail_settings_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'vtail_save_settings', 'vtail_settings_nonce' );

		$raw   = sanitize_text_field( wp_unslash( $_POST['vtail_exclude_tags'] ?? '' ) );
		$clean = preg_replace( '/[^a-z0-9,\-]/', '', strtolower( str_replace( ' ', '', $raw ) ) );
		update_option( 'vtail_exclude_tags', $clean );

		wp_safe_redirect( add_query_arg( [ 'page' => 'vtail-rules', 'settings_saved' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	private function render_settings_section(): void {
		$current  = (string) get_option( 'vtail_exclude_tags', 'h1,h2,h3,h4,h5,h6' );
		$form_url = add_query_arg( [ 'page' => 'vtail-rules' ], admin_url( 'options-general.php' ) );

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Global Settings', 'vt-auto-internal-linker' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( $form_url ) . '">';
		wp_nonce_field( 'vtail_save_settings', 'vtail_settings_nonce' );
		echo '<table class="form-table"><tbody><tr>';
		echo '<th scope="row"><label for="vtail_exclude_tags">' . esc_html__( 'Exclude Tags', 'vt-auto-internal-linker' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" id="vtail_exclude_tags" name="vtail_exclude_tags" value="' . esc_attr( $current ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Comma-separated HTML tags to skip during linking (e.g. h1,h2,h3)', 'vt-auto-internal-linker' ) . '</p>';
		echo '</td>';
		echo '</tr></tbody></table>';
		submit_button( __( 'Save Settings', 'vt-auto-internal-linker' ) );
		echo '</form>';
	}

	// -------------------------------------------------------------------------
	// Add / Edit form view
	// -------------------------------------------------------------------------

	private function render_form_page( string $action ): void {
		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$rule  = $id > 0 ? VTAIL_Rules_DB::get_by_id( $id ) : null;
		$error = $this->handle_save(); // redirects on success; returns null or error string

		// On validation failure, repopulate from submitted values so the user's input isn't lost.
		if ( null !== $error ) {
			$rule = $this->extract_post_values();
		}

		$defaults = [ 'keyword' => '', 'url' => '', 'case_sensitive' => 0,
		              'max_per_post' => 1, 'nofollow' => 0, 'new_tab' => 0,
		              'priority' => 10, 'active' => 1 ];
		$values = $rule ?? $defaults;
		$title  = 'edit' === $action
			? __( 'Edit Rule', 'vt-auto-internal-linker' )
			: __( 'Add New Rule', 'vt-auto-internal-linker' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		$this->render_form( $id, $values, $error );
		echo '</div>';
	}

	private function handle_save(): ?string {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return null;
		}

		// Dies on nonce failure — intentional, no recovery needed.
		check_admin_referer( 'vtail_save_rule' );

		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$keyword = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
		$url     = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );

		if ( '' === $keyword ) {
			return __( 'Keyword is required.', 'vt-auto-internal-linker' );
		}
		if ( '' === $url ) {
			return __( 'URL is required.', 'vt-auto-internal-linker' );
		}

		$data = $this->build_rule_data( $keyword, $url );
		$id > 0 ? VTAIL_Rules_DB::update( $id, $data ) : VTAIL_Rules_DB::insert( $data );

		wp_safe_redirect( add_query_arg( [ 'page' => 'vtail-rules', 'saved' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Builds the data array from $_POST for DB insert/update.
	 * keyword and url are pre-sanitized by the caller.
	 *
	 * @return array<string, mixed>
	 */
	private function build_rule_data( string $keyword, string $url ): array {
		return [
			'keyword'        => $keyword,
			'url'            => $url,
			'case_sensitive' => absint( $_POST['case_sensitive'] ?? 0 ),
			'max_per_post'   => absint( $_POST['max_per_post'] ?? 1 ),
			'nofollow'       => absint( $_POST['nofollow'] ?? 0 ),
			'new_tab'        => absint( $_POST['new_tab'] ?? 0 ),
			'priority'       => absint( $_POST['priority'] ?? 10 ),
			'active'         => absint( $_POST['active'] ?? 0 ),
		];
	}

	/**
	 * Re-sanitizes $_POST values for form repopulation after a validation error.
	 *
	 * @return array<string, mixed>
	 */
	private function extract_post_values(): array {
		return [
			'keyword'        => sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) ),
			'url'            => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'case_sensitive' => absint( $_POST['case_sensitive'] ?? 0 ),
			'max_per_post'   => absint( $_POST['max_per_post'] ?? 1 ),
			'nofollow'       => absint( $_POST['nofollow'] ?? 0 ),
			'new_tab'        => absint( $_POST['new_tab'] ?? 0 ),
			'priority'       => absint( $_POST['priority'] ?? 10 ),
			'active'         => absint( $_POST['active'] ?? 0 ),
		];
	}

	private function render_form( int $id, array $values, ?string $error ): void {
		$back_url = add_query_arg( [ 'page' => 'vtail-rules' ], admin_url( 'options-general.php' ) );
		$args     = array_filter( [ 'page' => 'vtail-rules', 'action' => $id > 0 ? 'edit' : 'add', 'id' => $id ?: null ] );
		$form_url = add_query_arg( $args, admin_url( 'options-general.php' ) );

		if ( $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}

		echo '<p><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to Rules', 'vt-auto-internal-linker' ) . '</a></p>';
		echo '<form method="post" action="' . esc_url( $form_url ) . '">';
		wp_nonce_field( 'vtail_save_rule' );
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
		echo '<table class="form-table"><tbody>';
		$this->render_form_fields( $values );
		$this->render_form_toggles( $values );
		echo '</tbody></table>';
		submit_button( $id > 0 ? __( 'Update Rule', 'vt-auto-internal-linker' ) : __( 'Add Rule', 'vt-auto-internal-linker' ) );
		echo '</form>';
	}

	private function render_form_fields( array $values ): void {
		?>
		<tr>
			<th scope="row"><label for="keyword"><?php esc_html_e( 'Keyword', 'vt-auto-internal-linker' ); ?></label></th>
			<td><input type="text" id="keyword" name="keyword" value="<?php echo esc_attr( (string) $values['keyword'] ); ?>" class="regular-text" required /></td>
		</tr>
		<tr>
			<th scope="row"><label for="url"><?php esc_html_e( 'URL', 'vt-auto-internal-linker' ); ?></label></th>
			<td><input type="text" id="url" name="url" value="<?php echo esc_attr( (string) $values['url'] ); ?>" class="regular-text" required /></td>
		</tr>
		<tr>
			<th scope="row"><label for="max_per_post"><?php esc_html_e( 'Max per Post', 'vt-auto-internal-linker' ); ?></label></th>
			<td><input type="number" id="max_per_post" name="max_per_post" value="<?php echo esc_attr( (string) $values['max_per_post'] ); ?>" min="1" class="small-text" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="priority"><?php esc_html_e( 'Priority', 'vt-auto-internal-linker' ); ?></label></th>
			<td><input type="number" id="priority" name="priority" value="<?php echo esc_attr( (string) $values['priority'] ); ?>" min="0" class="small-text" /></td>
		</tr>
		<?php
	}

	private function render_form_toggles( array $values ): void {
		$checkboxes = [
			'case_sensitive' => __( 'Case Sensitive', 'vt-auto-internal-linker' ),
			'nofollow'       => __( 'Nofollow', 'vt-auto-internal-linker' ),
			'new_tab'        => __( 'Open in New Tab', 'vt-auto-internal-linker' ),
			'active'         => __( 'Active', 'vt-auto-internal-linker' ),
		];

		foreach ( $checkboxes as $name => $label ) {
			?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td><input type="checkbox" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( 1, (int) ( $values[ $name ] ?? 0 ) ); ?> /></td>
			</tr>
			<?php
		}
	}
}
