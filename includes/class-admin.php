<?php
/**
 * Admin pages for managing keyword rules.
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
// Rules list table
// ---------------------------------------------------------------------------

class VTAIL_Rules_List_Table extends WP_List_Table {

	/** @var array<string, array<string, mixed>> Cached stats for the current render. */
	private array $stats = [];

	public function __construct() {
		parent::__construct( [
			'singular' => 'rule',
			'plural'   => 'rules',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'id'       => esc_html__( 'ID', 'vt-auto-internal-linker' ),
			'title'    => esc_html__( 'Title', 'vt-auto-internal-linker' ),
			'url'      => esc_html__( 'Target URL', 'vt-auto-internal-linker' ),
			'keywords' => esc_html__( 'Keywords', 'vt-auto-internal-linker' ),
			'max_per_post'  => esc_html__( 'Max / Post', 'vt-auto-internal-linker' ),
			'active'        => esc_html__( 'Active', 'vt-auto-internal-linker' ),
		];
	}

	public function get_sortable_columns(): array {
		return [];
	}

	public function prepare_items(): void {
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		$this->stats           = VTAIL_Rules_DB::get_stats();

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$all_rules    = VTAIL_Rules_DB::get_all_rules();
		$total_items  = count( $all_rules );

		$this->set_pagination_args( [ 'total_items' => $total_items, 'per_page' => $per_page ] );
		$this->items = array_slice( $all_rules, ( $current_page - 1 ) * $per_page, $per_page );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * URL column with row actions.
	 *
	 * @param array<string, mixed> $item
	 */
	public function column_url( array $item ): string {
		$id         = absint( $item['id'] );
		$base_url   = admin_url( 'options-general.php?page=vtail-rules' );
		$edit_url   = add_query_arg( [ 'action' => 'edit', 'id' => $id ], $base_url );
		$delete_url = wp_nonce_url(
			add_query_arg( [ 'action' => 'delete', 'id' => $id ], $base_url ),
			'vtail_delete_rule_' . $id
		);

		$actions = [
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'vt-auto-internal-linker' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this rule and all its keywords?', 'vt-auto-internal-linker' ) ) . '\')">' . esc_html__( 'Delete', 'vt-auto-internal-linker' ) . '</a>',
		];

		$link = '<a href="' . esc_url( (string) $item['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $item['url'] ) . '</a>';
		return $link . $this->row_actions( $actions );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_keywords( array $item ): string {
		$ids_str   = (string) ( $item['keyword_ids'] ?? '' );
		$texts_str = (string) ( $item['keyword_texts'] ?? '' );

		if ( '' === $ids_str ) {
			return '—';
		}

		$ids   = explode( ',', $ids_str );
		$texts = explode( '|||', $texts_str );
		$lines = [];

		foreach ( $ids as $index => $id ) {
			$kw_id  = absint( $id );
			$text   = $texts[ $index ] ?? '';
			$count  = (int) ( $this->stats[ (string) $kw_id ]['count'] ?? 0 );
			$lines[] = esc_html( $text ) . ' <span class="vtail-kw-stat">(' . esc_html( (string) $count ) . ')</span>';
		}

		return implode( '<br />', $lines );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_active( array $item ): string {
		return (int) $item['active']
			? esc_html__( 'Yes', 'vt-auto-internal-linker' )
			: esc_html__( 'No', 'vt-auto-internal-linker' );
	}
}

// ---------------------------------------------------------------------------
// Admin page controller
// ---------------------------------------------------------------------------

class VTAIL_Admin {

	/** Stores a validation error from handle_early_forms() for display in render_form_page(). */
	private ?string $form_error = null;

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

		if ( 'stats' === $action ) {
			$this->render_stats_page();
			return;
		}

		if ( 'add' === $action || 'edit' === $action ) {
			$this->render_form_page( $action );
			return;
		}

		$this->render_list_page();
	}

	// -------------------------------------------------------------------------
	// AJAX keyword handlers (public — registered via add_action in class-plugin.php)
	// -------------------------------------------------------------------------

	public function handle_save_keyword(): void {
		check_ajax_referer( 'vtail_keyword_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'vt-auto-internal-linker' ) ] );
		}

		$keyword_id = absint( $_POST['keyword_id'] ?? 0 );
		$rule_id    = absint( $_POST['rule_id'] ?? 0 );

		if ( ! $rule_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid rule.', 'vt-auto-internal-linker' ) ] );
		}

		$data = $this->extract_keyword_post_values( $rule_id );

		if ( '' === $data['keyword'] ) {
			wp_send_json_error( [ 'message' => __( 'Keyword is required.', 'vt-auto-internal-linker' ) ] );
		}

		if ( $keyword_id > 0 ) {
			VTAIL_Rules_DB::update_keyword( $keyword_id, $data );
			$saved_id = $keyword_id;
		} else {
			$saved_id = VTAIL_Rules_DB::insert_keyword( $data );
			if ( ! $saved_id ) {
				wp_send_json_error( [ 'message' => __( 'Failed to save keyword.', 'vt-auto-internal-linker' ) ] );
			}
		}

		$kw   = VTAIL_Rules_DB::get_keyword_by_id( $saved_id );
		$html = $kw ? $this->build_keyword_row_html( $kw ) : '';

		wp_send_json_success( [ 'keyword_id' => $saved_id, 'html' => $html ] );
	}

	public function handle_scan_stats(): void {
		check_ajax_referer( 'vtail_keyword_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'vt-auto-internal-linker' ) ] );
		}

		$batch    = absint( $_POST['batch'] ?? 0 );
		$keywords = $this->get_all_active_keywords();

		if ( empty( $keywords ) ) {
			wp_send_json_success( [ 'done' => true, 'total_posts' => 0, 'scanned' => 0 ] );
		}

		if ( 0 === $batch ) {
			VTAIL_Rules_DB::update_stats( [] );
		}

		$query = $this->run_posts_batch( $batch );
		$stats = VTAIL_Rules_DB::get_stats();

		foreach ( $query->posts as $post ) {
			$this->scan_post_for_keywords( $post, $keywords, $stats );
		}

		VTAIL_Rules_DB::update_stats( $stats );

		$total_posts = (int) $query->found_posts;
		$scanned     = min( ( $batch + 1 ) * 15, $total_posts );

		wp_send_json_success( [
			'batch'       => $batch,
			'total_posts' => $total_posts,
			'scanned'     => $scanned,
			'done'        => $scanned >= $total_posts,
		] );
	}

	public function handle_delete_keyword(): void {
		check_ajax_referer( 'vtail_keyword_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'vt-auto-internal-linker' ) ] );
		}

		$keyword_id = absint( $_POST['keyword_id'] ?? 0 );

		if ( ! $keyword_id || ! VTAIL_Rules_DB::delete_keyword( $keyword_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete keyword.', 'vt-auto-internal-linker' ) ] );
		}

		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Early form processing — runs on admin_init, before any HTML output
	// -------------------------------------------------------------------------

	/**
	 * Processes form submissions and redirects before WordPress outputs anything.
	 * Called via add_action('admin_init') so wp_safe_redirect() always works.
	 */
	public function handle_early_forms(): void {
		if ( ! isset( $_GET['page'] ) || 'vtail-rules' !== sanitize_key( $_GET['page'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$get_action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		if ( 'delete' === $get_action ) {
			$this->process_delete_rule();
			return;
		}

		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( ! empty( $_POST['vtail_settings_nonce'] ) ) {
			$this->process_settings_save();
			return;
		}

		if ( in_array( $get_action, [ 'add', 'edit' ], true ) ) {
			$this->form_error = $this->process_rule_save();
		}
	}

	private function process_delete_rule(): void {
		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'vtail_delete_rule_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'vt-auto-internal-linker' ) );
		}

		VTAIL_Rules_DB::delete_rule( $id );

		wp_safe_redirect( add_query_arg( [ 'page' => 'vtail-rules', 'deleted' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	private function process_settings_save(): void {
		check_admin_referer( 'vtail_save_settings', 'vtail_settings_nonce' );

		$raw   = sanitize_text_field( wp_unslash( $_POST['vtail_exclude_tags'] ?? '' ) );
		$clean = preg_replace( '/[^a-z0-9,\-]/', '', strtolower( str_replace( ' ', '', $raw ) ) );
		update_option( 'vtail_exclude_tags', $clean );

		wp_safe_redirect( add_query_arg( [ 'page' => 'vtail-rules', 'settings_saved' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	private function process_rule_save(): ?string {
		check_admin_referer( 'vtail_save_rule' );

		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		if ( '' === $url ) {
			return __( 'URL is required.', 'vt-auto-internal-linker' );
		}

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = $this->extract_rule_post_values();
		$id > 0 ? VTAIL_Rules_DB::update_rule( $id, $data ) : $id = (int) VTAIL_Rules_DB::insert_rule( $data );

		wp_safe_redirect( add_query_arg( [ 'page' => 'vtail-rules', 'action' => 'edit', 'id' => $id, 'saved' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// List view
	// -------------------------------------------------------------------------

	private function render_list_page(): void {

		$table   = new VTAIL_Rules_List_Table();
		$table->prepare_items();
		$add_url = add_query_arg( [ 'page' => 'vtail-rules', 'action' => 'add' ], admin_url( 'options-general.php' ) );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<a href="' . esc_url( $add_url ) . '" class="page-title-action">' . esc_html__( 'Add New Rule', 'vt-auto-internal-linker' ) . '</a>';
		$this->render_scan_trigger( 'page-title-action' );
		echo '<hr class="wp-header-end">';
		$this->show_notice();
		$this->render_scan_progress();
		echo '<form method="get"><input type="hidden" name="page" value="vtail-rules" />';
		$table->display();
		echo '</form>';
		$this->render_settings_section();
		echo '</div>';
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

	private function render_settings_section(): void {
		$current  = (string) get_option( 'vtail_exclude_tags', 'h1,h2,h3,h4,h5,h6' );
		$form_url = add_query_arg( [ 'page' => 'vtail-rules' ], admin_url( 'options-general.php' ) );

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Global Settings', 'vt-auto-internal-linker' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( $form_url ) . '">';
		wp_nonce_field( 'vtail_save_settings', 'vtail_settings_nonce' );
		echo '<table class="form-table"><tbody><tr>';
		echo '<th scope="row"><label for="vtail_exclude_tags">' . esc_html__( 'Exclude Tags', 'vt-auto-internal-linker' ) . '</label></th>';
		echo '<td><input type="text" id="vtail_exclude_tags" name="vtail_exclude_tags" value="' . esc_attr( $current ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Comma-separated HTML tags to skip during linking (e.g. h1,h2,h3)', 'vt-auto-internal-linker' ) . '</p></td>';
		echo '</tr></tbody></table>';
		submit_button( __( 'Save Settings', 'vt-auto-internal-linker' ) );
		echo '</form>';
	}

	// -------------------------------------------------------------------------
	// Rule add / edit form
	// -------------------------------------------------------------------------

	private function render_form_page( string $action ): void {
		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$rule  = $id > 0 ? VTAIL_Rules_DB::get_rule_by_id( $id ) : null;
		$error = $this->form_error;

		if ( null !== $error ) {
			$rule = $this->extract_rule_post_values();
		}

		$defaults = [ 'title' => '', 'url' => '', 'max_per_post' => 1, 'active' => 1 ];
		$values   = $rule ?? $defaults;
		$title    = 'edit' === $action
			? __( 'Edit Rule', 'vt-auto-internal-linker' )
			: __( 'Add New Rule', 'vt-auto-internal-linker' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		$this->render_rule_form( $id, $values, $error );
		echo '</div>';
	}

	private function render_rule_form( int $id, array $values, ?string $error ): void {
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
		$this->render_rule_fields( $values );
		echo '</tbody></table>';
		submit_button( $id > 0 ? __( 'Update Rule', 'vt-auto-internal-linker' ) : __( 'Add Rule', 'vt-auto-internal-linker' ) );
		echo '</form>';

		if ( $id > 0 ) {
			$this->render_keywords_section( $id );
		} else {
			echo '<p class="description">' . esc_html__( 'Save the rule first, then you can add keywords.', 'vt-auto-internal-linker' ) . '</p>';
		}
	}

	private function render_rule_fields( array $values ): void {
		?>
		<tr>
			<th scope="row"><label for="title"><?php esc_html_e( 'Title', 'vt-auto-internal-linker' ); ?></label></th>
			<td>
				<input type="text" id="title" name="title" value="<?php echo esc_attr( (string) ( $values['title'] ?? '' ) ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Optional. A label to identify this rule in the list.', 'vt-auto-internal-linker' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="url"><?php esc_html_e( 'Target URL', 'vt-auto-internal-linker' ); ?></label></th>
			<td>
				<input type="url" id="url" name="url" value="<?php echo esc_attr( (string) $values['url'] ); ?>" class="regular-text" dir="ltr" required />
				<p class="description"><?php esc_html_e( 'Full URL including https://', 'vt-auto-internal-linker' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="max_per_post"><?php esc_html_e( 'Max Links / Post', 'vt-auto-internal-linker' ); ?></label></th>
			<td>
				<input type="number" id="max_per_post" name="max_per_post" value="<?php echo esc_attr( (string) $values['max_per_post'] ); ?>" min="1" class="small-text" />
				<p class="description"><?php esc_html_e( 'Maximum total links to this URL in a single post (across all keywords).', 'vt-auto-internal-linker' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="active"><?php esc_html_e( 'Active', 'vt-auto-internal-linker' ); ?></label></th>
			<td><input type="checkbox" id="active" name="active" value="1" <?php checked( 1, (int) ( $values['active'] ?? 1 ) ); ?> /></td>
		</tr>
		<?php
	}

	// -------------------------------------------------------------------------
	// Keywords section (shown on edit page only)
	// -------------------------------------------------------------------------

	private function render_keywords_section( int $rule_id ): void {
		$keywords = VTAIL_Rules_DB::get_keywords_by_rule( $rule_id );
		$stats    = VTAIL_Rules_DB::get_stats();
		?>
		<hr />
		<h2><?php esc_html_e( 'Keywords', 'vt-auto-internal-linker' ); ?></h2>
		<table class="wp-list-table widefat fixed striped vtail-keywords-table" id="vtail-keywords-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Keyword', 'vt-auto-internal-linker' ); ?></th>
					<th><?php esc_html_e( 'Max / Post', 'vt-auto-internal-linker' ); ?></th>
					<th><?php esc_html_e( 'Priority', 'vt-auto-internal-linker' ); ?></th>
					<th><?php esc_html_e( 'Total Limit', 'vt-auto-internal-linker' ); ?></th>
					<th><?php esc_html_e( 'Link Stats', 'vt-auto-internal-linker' ); ?></th>
					<th><?php esc_html_e( 'Anchor (#)', 'vt-auto-internal-linker' ); ?></th>
					<th><?php esc_html_e( 'Settings', 'vt-auto-internal-linker' ); ?></th>
					<th><?php esc_html_e( 'Active', 'vt-auto-internal-linker' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'vt-auto-internal-linker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $keywords ) ) : ?>
					<tr class="vtail-no-keywords"><td colspan="9"><?php esc_html_e( 'No keywords yet. Add the first one below.', 'vt-auto-internal-linker' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $keywords as $kw ) : ?>
						<?php echo $this->build_keyword_row_html( $kw, $stats ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p>
			<button type="button" id="vtail-add-keyword" class="button">
				<?php esc_html_e( '+ Add Keyword', 'vt-auto-internal-linker' ); ?>
			</button>
			<?php $this->render_scan_trigger( 'button' ); ?>
		</p>
		<?php $this->render_scan_progress(); ?>

		<?php $this->render_keyword_inline_form( $rule_id ); ?>
		<?php
	}

	private function render_keyword_inline_form( int $rule_id ): void {
		?>
		<div id="vtail-keyword-form-wrap" style="display:none;" class="vtail-keyword-form-wrap">
			<h3 id="vtail-keyword-form-title"><?php esc_html_e( 'Add Keyword', 'vt-auto-internal-linker' ); ?></h3>
			<form id="vtail-keyword-form">
				<input type="hidden" name="action" value="vtail_save_keyword" />
				<input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $rule_id ); ?>" />
				<input type="hidden" id="vtail-kw-id" name="keyword_id" value="0" />
				<table class="form-table"><tbody>
					<tr>
						<th><label for="vtail-kw-keyword"><?php esc_html_e( 'Keyword', 'vt-auto-internal-linker' ); ?></label></th>
						<td><input type="text" id="vtail-kw-keyword" name="keyword" class="regular-text" required /></td>
					</tr>
					<tr>
						<th><label for="vtail-kw-max-per-post"><?php esc_html_e( 'Max per Post', 'vt-auto-internal-linker' ); ?></label></th>
						<td>
							<input type="number" id="vtail-kw-max-per-post" name="max_per_post" value="1" min="1" class="small-text" />
							<p class="description"><?php esc_html_e( 'Max times this keyword is linked in a single post.', 'vt-auto-internal-linker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="vtail-kw-priority"><?php esc_html_e( 'Priority', 'vt-auto-internal-linker' ); ?></label></th>
						<td>
							<input type="number" id="vtail-kw-priority" name="priority" value="10" min="1" class="small-text" />
							<p class="description"><?php esc_html_e( 'Lower number = higher priority. Used when rule max/post is 1 and multiple keywords match.', 'vt-auto-internal-linker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="vtail-kw-total-limit"><?php esc_html_e( 'Total Limit', 'vt-auto-internal-linker' ); ?></label></th>
						<td>
							<input type="number" id="vtail-kw-total-limit" name="total_limit" value="0" min="0" class="small-text" />
							<p class="description"><?php esc_html_e( '0 = unlimited. Stop linking once this many links exist across the entire site.', 'vt-auto-internal-linker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="vtail-kw-anchor"><?php esc_html_e( 'Anchor Section', 'vt-auto-internal-linker' ); ?></label></th>
						<td>
							<span class="vtail-anchor-group" dir="ltr"><span class="vtail-anchor-prefix">#</span><input type="text" id="vtail-kw-anchor" name="anchor" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. about', 'vt-auto-internal-linker' ); ?>" /></span>
							<p class="description"><?php esc_html_e( 'Optional. Links to a specific section of the target page (e.g. "about" → URL#about).', 'vt-auto-internal-linker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Options', 'vt-auto-internal-linker' ); ?></th>
						<td>
							<label><input type="checkbox" id="vtail-kw-case-sensitive" name="case_sensitive" value="1" /> <?php esc_html_e( 'Case Sensitive', 'vt-auto-internal-linker' ); ?></label><br />
							<label><input type="checkbox" id="vtail-kw-nofollow" name="nofollow" value="1" /> <?php esc_html_e( 'Nofollow', 'vt-auto-internal-linker' ); ?></label><br />
							<label><input type="checkbox" id="vtail-kw-new-tab" name="new_tab" value="1" /> <?php esc_html_e( 'Open in New Tab', 'vt-auto-internal-linker' ); ?></label><br />
							<label><input type="checkbox" id="vtail-kw-active" name="active" value="1" checked /> <?php esc_html_e( 'Active', 'vt-auto-internal-linker' ); ?></label>
						</td>
					</tr>
				</tbody></table>
				<input type="submit" id="vtail-keyword-submit" class="button button-primary" value="<?php esc_attr_e( 'Save Keyword', 'vt-auto-internal-linker' ); ?>" data-original="<?php esc_attr_e( 'Save Keyword', 'vt-auto-internal-linker' ); ?>" data-saving="<?php esc_attr_e( 'Saving...', 'vt-auto-internal-linker' ); ?>" />
				<button type="button" id="vtail-keyword-cancel" class="button"><?php esc_html_e( 'Cancel', 'vt-auto-internal-linker' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Builds the HTML for a single keyword table row.
	 * Used both on page load and in the AJAX response for keyword save.
	 *
	 * @param array<string, mixed> $kw
	 * @param array<string, mixed> $stats
	 */
	private function build_keyword_row_html( array $kw, array $stats = [] ): string {
		if ( empty( $stats ) ) {
			$stats = VTAIL_Rules_DB::get_stats();
		}

		$kw_id      = (int) $kw['id'];
		$kw_stats   = $stats[ (string) $kw_id ] ?? [ 'count' => 0, 'posts' => [] ];
		$count      = (int) $kw_stats['count'];
		$stats_url  = add_query_arg( [ 'page' => 'vtail-rules', 'action' => 'stats', 'keyword_id' => $kw_id ], admin_url( 'options-general.php' ) );
		$total_disp = (int) $kw['total_limit'] === 0 ? esc_html__( '∞', 'vt-auto-internal-linker' ) : esc_html( (string) $kw['total_limit'] );
		$anchor     = '' !== $kw['anchor'] ? '#' . esc_html( $kw['anchor'] ) : '—';
		$settings   = $this->build_settings_badge( $kw );
		$active     = (int) $kw['active'] ? esc_html__( 'Yes', 'vt-auto-internal-linker' ) : esc_html__( 'No', 'vt-auto-internal-linker' );

		$edit_data = sprintf(
			'data-id="%d" data-keyword="%s" data-max-per-post="%d" data-priority="%d" data-total-limit="%d" data-anchor="%s" data-case-sensitive="%d" data-nofollow="%d" data-new-tab="%d" data-active="%d"',
			$kw_id,
			esc_attr( (string) $kw['keyword'] ),
			(int) $kw['max_per_post'],
			(int) $kw['priority'],
			(int) $kw['total_limit'],
			esc_attr( (string) $kw['anchor'] ),
			(int) $kw['case_sensitive'],
			(int) $kw['nofollow'],
			(int) $kw['new_tab'],
			(int) $kw['active']
		);

		return sprintf(
			'<tr id="vtail-kw-row-%d">
				<td><strong>%s</strong></td>
				<td>%d</td>
				<td>%d</td>
				<td>%s</td>
				<td><a href="%s">%s</a></td>
				<td>%s</td>
				<td>%s</td>
				<td>%s</td>
				<td>
					<button type="button" class="button button-small vtail-edit-keyword" %s>%s</button>
					<button type="button" class="button button-small vtail-delete-keyword" data-id="%d">%s</button>
				</td>
			</tr>',
			$kw_id,
			esc_html( (string) $kw['keyword'] ),
			(int) $kw['max_per_post'],
			(int) $kw['priority'],
			$total_disp,
			esc_url( $stats_url ),
			esc_html( (string) $count ),
			$anchor,
			$settings,
			$active,
			$edit_data,
			esc_html__( 'Edit', 'vt-auto-internal-linker' ),
			$kw_id,
			esc_html__( 'Delete', 'vt-auto-internal-linker' )
		);
	}

	/**
	 * Returns compact badges for case_sensitive / nofollow / new_tab flags.
	 *
	 * @param array<string, mixed> $kw
	 */
	private function build_settings_badge( array $kw ): string {
		$parts = [];
		if ( (int) $kw['case_sensitive'] ) {
			$parts[] = '<span class="vtail-badge">' . esc_html__( 'Aa', 'vt-auto-internal-linker' ) . '</span>';
		}
		if ( (int) $kw['nofollow'] ) {
			$parts[] = '<span class="vtail-badge">' . esc_html__( 'NF', 'vt-auto-internal-linker' ) . '</span>';
		}
		if ( (int) $kw['new_tab'] ) {
			$parts[] = '<span class="vtail-badge">' . esc_html__( '↗', 'vt-auto-internal-linker' ) . '</span>';
		}
		return empty( $parts ) ? '—' : implode( ' ', $parts );
	}

	// -------------------------------------------------------------------------
	// Stats detail page
	// -------------------------------------------------------------------------

	private function render_stats_page(): void {
		$keyword_id = isset( $_GET['keyword_id'] ) ? absint( $_GET['keyword_id'] ) : 0;
		$kw         = $keyword_id ? VTAIL_Rules_DB::get_keyword_by_id( $keyword_id ) : null;

		if ( ! $kw ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Link Stats', 'vt-auto-internal-linker' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Keyword not found.', 'vt-auto-internal-linker' ) . '</p></div></div>';
			return;
		}

		$rule      = VTAIL_Rules_DB::get_rule_by_id( (int) $kw['rule_id'] );
		$kw_stats  = VTAIL_Rules_DB::get_keyword_stats( $keyword_id );
		$back_url  = add_query_arg( [ 'page' => 'vtail-rules', 'action' => 'edit', 'id' => $kw['rule_id'] ], admin_url( 'options-general.php' ) );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Link Stats', 'vt-auto-internal-linker' ) . ': <em>' . esc_html( (string) $kw['keyword'] ) . '</em></h1>';
		echo '<p><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to Rule', 'vt-auto-internal-linker' ) . '</a></p>';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Keyword', 'vt-auto-internal-linker' ) . '</th><td>' . esc_html( (string) $kw['keyword'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Target URL', 'vt-auto-internal-linker' ) . '</th><td>' . ( $rule ? '<a href="' . esc_url( $rule['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $rule['url'] ) . '</a>' : '—' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Total Links Found', 'vt-auto-internal-linker' ) . '</th><td><strong>' . esc_html( (string) $kw_stats['count'] ) . '</strong></td></tr>';
		echo '</tbody></table>';

		$this->render_stats_posts_table( $kw_stats['posts'] ?? [] );
		$this->render_scan_button( true );
		echo '</div>';
	}

	/**
	 * @param list<int> $post_ids
	 */
	private function render_stats_posts_table( array $post_ids ): void {
		if ( empty( $post_ids ) ) {
			echo '<p>' . esc_html__( 'No links found yet. Run the stats scan to update.', 'vt-auto-internal-linker' ) . '</p>';
			return;
		}

		echo '<h3>' . esc_html__( 'Posts / Pages with this link', 'vt-auto-internal-linker' ) . '</h3>';
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Title', 'vt-auto-internal-linker' ) . '</th>';
		echo '<th>' . esc_html__( 'URL', 'vt-auto-internal-linker' ) . '</th>';
		echo '<th>' . esc_html__( 'Edit', 'vt-auto-internal-linker' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post ) {
				continue;
			}
			$permalink = get_permalink( $post );
			echo '<tr>';
			echo '<td>' . esc_html( get_the_title( $post ) ) . '</td>';
			echo '<td><a href="' . esc_url( (string) $permalink ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $permalink ) . '</a></td>';
			echo '<td><a href="' . esc_url( get_edit_post_link( $post ) ) . '">' . esc_html__( 'Edit', 'vt-auto-internal-linker' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Renders the scan trigger button only.
	 * $css_class: 'page-title-action' for header placement, 'button' for inline placement.
	 */
	private function render_scan_trigger( string $css_class = 'button', bool $reload_on_done = false ): void {
		$reload_attr = $reload_on_done ? ' data-reload="true"' : '';
		echo '<button type="button" id="vtail-run-scan" class="' . esc_attr( $css_class ) . '"' . $reload_attr . '>';
		echo esc_html__( 'Update Link Stats', 'vt-auto-internal-linker' );
		echo '</button>';
	}

	/**
	 * Renders the scan progress area (status text + bar). Always placed below the trigger.
	 */
	private function render_scan_progress(): void {
		echo '<div id="vtail-scan-wrap" class="vtail-scan-wrap">';
		echo '<span id="vtail-scan-progress" style="display:none;">';
		echo '<span id="vtail-scan-status"></span>';
		echo '<div class="vtail-progress-bar"><div id="vtail-progress-fill" style="width:0%"></div></div>';
		echo '</span>';
		echo '</div>';
	}

	/**
	 * Renders trigger + progress together (used on the stats detail page).
	 * Pass $reload_on_done=true to reload the page after scan completes.
	 */
	private function render_scan_button( bool $reload_on_done = false ): void {
		echo '<div class="vtail-scan-wrap">';
		$this->render_scan_trigger( 'button', $reload_on_done );
		echo '<span id="vtail-scan-progress" style="display:none;">';
		echo ' <span id="vtail-scan-status"></span>';
		echo '<div class="vtail-progress-bar"><div id="vtail-progress-fill" style="width:0%"></div></div>';
		echo '</span>';
		echo '</div>';
	}

	/**
	 * Returns a flat list of all active keywords across all active rules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_all_active_keywords(): array {
		$keywords = [];
		foreach ( VTAIL_Rules_DB::get_active_rules_with_keywords() as $rule ) {
			foreach ( $rule['keywords'] as $kw ) {
				$kw['rule_url'] = $rule['url'];
				$keywords[]     = $kw;
			}
		}
		return $keywords;
	}

	/**
	 * Runs a WP_Query for a batch of 15 published posts across all public post types.
	 */
	private function run_posts_batch( int $batch ): \WP_Query {
		return new \WP_Query( [
			'post_type'              => array_values( get_post_types( [ 'public' => true ] ) ),
			'post_status'            => 'publish',
			'posts_per_page'         => 15,
			'offset'                 => $batch * 15,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );
	}

	/**
	 * Checks a single post's content against all keywords and updates $stats in-place.
	 *
	 * @param array<int, array<string, mixed>> $keywords
	 * @param array<string, array<string, mixed>> $stats
	 */
	private function scan_post_for_keywords( \WP_Post $post, array $keywords, array &$stats ): void {
		$content = $post->post_content;
		if ( '' === trim( $content ) ) {
			return;
		}

		$permalink = (string) get_permalink( $post );

		foreach ( $keywords as $kw ) {
			// Mirror the linker's self-link prevention: skip if post IS the target URL.
			if ( $this->is_target_page( (string) ( $kw['rule_url'] ?? '' ), $permalink ) ) {
				continue;
			}

			$pattern = $this->build_scan_pattern( (string) $kw['keyword'], (bool) $kw['case_sensitive'] );
			if ( ! preg_match( $pattern, $content ) ) {
				continue;
			}

			$key = (string) $kw['id'];
			if ( ! isset( $stats[ $key ] ) ) {
				$stats[ $key ] = [ 'count' => 0, 'posts' => [] ];
			}
			if ( ! in_array( $post->ID, $stats[ $key ]['posts'], true ) ) {
				$stats[ $key ]['posts'][] = $post->ID;
				++$stats[ $key ]['count'];
			}
		}
	}

	/**
	 * Returns true when $rule_url and $post_permalink point to the same page.
	 * Strips #anchor before comparing, matching the linker's is_self_link() logic.
	 */
	private function is_target_page( string $rule_url, string $post_permalink ): bool {
		if ( '' === $rule_url || '' === $post_permalink ) {
			return false;
		}
		$bare = (string) strtok( $rule_url, '#' );
		return untrailingslashit( $bare ) === untrailingslashit( $post_permalink );
	}

	/**
	 * Builds a regex pattern for content scanning — same word-boundary logic as the linker.
	 */
	private function build_scan_pattern( string $keyword, bool $case_sensitive ): string {
		$escaped = preg_quote( $keyword, '/' );
		$flags   = $case_sensitive ? 'u' : 'ui';
		return '/(?<!\w)' . $escaped . '(?!\w)/' . $flags;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extract_rule_post_values(): array {
		return [
			'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'url'          => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'max_per_post' => absint( $_POST['max_per_post'] ?? 1 ),
			'active'       => absint( $_POST['active'] ?? 0 ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extract_keyword_post_values( int $rule_id ): array {
		return [
			'rule_id'        => $rule_id,
			'keyword'        => sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) ),
			'max_per_post'   => absint( $_POST['max_per_post'] ?? 1 ),
			'priority'       => absint( $_POST['priority'] ?? 10 ),
			'total_limit'    => absint( $_POST['total_limit'] ?? 0 ),
			'anchor'         => sanitize_title( wp_unslash( $_POST['anchor'] ?? '' ) ),
			'case_sensitive' => absint( $_POST['case_sensitive'] ?? 0 ),
			'nofollow'       => absint( $_POST['nofollow'] ?? 0 ),
			'new_tab'        => absint( $_POST['new_tab'] ?? 0 ),
			'active'         => absint( $_POST['active'] ?? 0 ),
		];
	}
}
