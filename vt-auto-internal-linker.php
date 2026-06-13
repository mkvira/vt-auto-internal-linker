<?php
/**
 * Plugin Name:       VT Auto Internal Linker
 * Plugin URI:        https://vira-team.com
 * Description:       Automatically inserts internal links into post/page content based on user-defined keyword→URL rules.
 * Version:           1.1.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            Mahmoud Kazemi
 * Author URI:        https://mahmoudkazemi.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vt-auto-internal-linker
 * Domain Path:       /languages
 *
 * @package VT_Auto_Internal_Linker
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VTAIL_VERSION', '1.1.0' );
define( 'VTAIL_DB_VERSION', 3 );
define( 'VTAIL_PATH', plugin_dir_path( __FILE__ ) );
define( 'VTAIL_URL', plugin_dir_url( __FILE__ ) );

require_once VTAIL_PATH . 'includes/class-plugin.php';
require_once VTAIL_PATH . 'includes/class-rules-db.php';

register_activation_hook( __FILE__, [ 'VTAIL_Rules_DB', 'create_table' ] );
register_deactivation_hook( __FILE__, function (): void {} );

add_action( 'plugins_loaded', function (): void {
	$plugin = new VTAIL_Plugin();
	$plugin->init();
} );
