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

		require_once VTAIL_PATH . 'includes/class-admin.php';
		require_once VTAIL_PATH . 'includes/class-linker.php';

		if ( is_admin() ) {
			$admin = new VTAIL_Admin();
			add_action( 'admin_menu', [ $admin, 'register_menu' ] );
			add_action( 'admin_enqueue_scripts', function ( string $hook ): void {
				if ( 'settings_page_vtail-rules' !== $hook ) {
					return;
				}
				wp_enqueue_style( 'vtail-admin', VTAIL_URL . 'assets/css/admin.css', [], VTAIL_VERSION );
			} );
		}

		$linker = new VTAIL_Linker();
		add_filter( 'the_content', [ $linker, 'process_content' ], 10 );
	}
}
