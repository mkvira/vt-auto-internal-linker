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
				'<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Delete this rule?', 'vt-auto-internal-linker' ) ),
				esc_html__( 'Delete', 'vt-auto-internal-linker' )
			),
		];

		return esc_html( $item['keyword'] ) . $this->row_actions( $actions );
	}

	/**
	 * URL column — renders as a clickable link truncated for readability.
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

		$this->handle_delete();

		$table = new VTAIL_Rules_List_Table();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		$this->show_notice();
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="vtail-rules" />';
		$table->display();
		echo '</form>';
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
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Rule deleted successfully.', 'vt-auto-internal-linker' )
				. '</p></div>';
		}
	}
}
