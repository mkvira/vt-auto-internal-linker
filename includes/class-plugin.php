<?php
/**
 * Core plugin class — registers all hooks.
 *
 * @package VT_Auto_Internal_Linker
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VTAIL_Plugin {

	public function init(): void {
		load_plugin_textdomain( 'vt-auto-internal-linker', false, plugin_basename( VTAIL_PATH ) . '/languages' );

		VTAIL_Rules_DB::maybe_upgrade();

		require_once VTAIL_PATH . 'includes/class-admin.php';
		require_once VTAIL_PATH . 'includes/class-linker.php';

		if ( is_admin() ) {
			$admin = new VTAIL_Admin();
			add_action( 'admin_menu', [ $admin, 'register_menu' ] );
			add_action( 'wp_ajax_vtail_save_keyword', [ $admin, 'handle_save_keyword' ] );
			add_action( 'wp_ajax_vtail_delete_keyword', [ $admin, 'handle_delete_keyword' ] );
			add_action( 'wp_ajax_vtail_scan_stats', [ $admin, 'handle_scan_stats' ] );
			add_action( 'admin_enqueue_scripts', function ( string $hook ) use ( $admin ): void {
				if ( 'settings_page_vtail-rules' !== $hook ) {
					return;
				}
				wp_enqueue_style( 'vtail-admin', VTAIL_URL . 'assets/css/admin.css', [], VTAIL_VERSION );
				wp_enqueue_script( 'vtail-admin', VTAIL_URL . 'assets/js/admin.js', [ 'jquery' ], VTAIL_VERSION, true );
				wp_localize_script( 'vtail-admin', 'vtailAdmin', [
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'vtail_keyword_nonce' ),
					'i18n'    => [
						'addKeyword'    => __( 'Add Keyword', 'vt-auto-internal-linker' ),
						'editKeyword'   => __( 'Edit Keyword', 'vt-auto-internal-linker' ),
						'confirmDelete' => __( 'Delete this keyword?', 'vt-auto-internal-linker' ),
						'noKeywords'    => __( 'No keywords yet. Add the first one below.', 'vt-auto-internal-linker' ),
						'error'         => __( 'An error occurred. Please try again.', 'vt-auto-internal-linker' ),
						'scanStarting'  => __( 'Starting scan…', 'vt-auto-internal-linker' ),
						'scanProgress'  => __( 'Scanning: %1 / %2 posts', 'vt-auto-internal-linker' ),
						'scanDone'      => __( 'Done — %  posts scanned.', 'vt-auto-internal-linker' ),
					],
				] );
			} );
		}

		$linker = new VTAIL_Linker();
		add_filter( 'the_content', [ $linker, 'process_content' ], 10 );
	}
}
