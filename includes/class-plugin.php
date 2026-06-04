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
		require_once VTAIL_PATH . 'includes/class-admin.php';

		if ( is_admin() ) {
			$admin = new VTAIL_Admin();
			add_action( 'admin_menu', [ $admin, 'register_menu' ] );
		}
	}
}
