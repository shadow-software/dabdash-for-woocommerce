<?php
/**
 * Plugin Name:       DabDash Sync for WordPress
 * Plugin URI:        https://github.com/shadow-software/dabdash-sync-for-wordpress
 * Description:       Keeps WordPress customers in step with DabDash — verification status, loyalty balance, and marketing consent — with DabDash as the source of truth. Free and open source; requires a DabDash tenant.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Shadow Software LLC
 * Author URI:        https://shadowsoftware.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dabdash-sync-for-wordpress
 * Domain Path:       /languages
 *
 * @package DabDashSync
 */

defined( 'ABSPATH' ) || exit;

define( 'DABDASH_SYNC_VERSION', '1.0.0' );
define( 'DABDASH_SYNC_FILE', __FILE__ );
define( 'DABDASH_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'DABDASH_SYNC_URL', plugin_dir_url( __FILE__ ) );

/*
 * Runtime Composer dependencies (shadow-software/dabdash-php-sdk) ship in vendor/.
 */
$dabdash_sync_autoload = DABDASH_SYNC_PATH . 'vendor/autoload.php';
if ( is_readable( $dabdash_sync_autoload ) ) {
	require_once $dabdash_sync_autoload;
}

/**
 * PSR-4-ish autoloader for DabDashSync\* classes under includes/.
 *
 * @param string $classname Fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	static function ( $classname ) {
		$prefix = 'DabDashSync\\';

		if ( 0 !== strpos( $classname, $prefix ) ) {
			return;
		}

		$relative = substr( $classname, strlen( $prefix ) );

		if ( 1 !== preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative ) ) {
			return;
		}

		$relative  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$base      = DABDASH_SYNC_PATH . 'includes' . DIRECTORY_SEPARATOR;
		$file      = $base . $relative . '.php';
		$real_base = realpath( $base );
		$real_file = realpath( $file );

		if ( false === $real_base || false === $real_file || 0 !== strpos( $real_file, $real_base ) ) {
			return;
		}

		require $real_file;
	}
);

register_activation_hook(
	DABDASH_SYNC_FILE,
	static function () {
		\DabDashSync\Lifecycle::activate();
	}
);

register_deactivation_hook(
	DABDASH_SYNC_FILE,
	static function () {
		\DabDashSync\Lifecycle::deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! is_readable( DABDASH_SYNC_PATH . 'vendor/autoload.php' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__(
						'DabDash Sync is missing its Composer dependencies. Run composer install in the plugin directory, or reinstall from a release ZIP.',
						'dabdash-sync-for-wordpress'
					);
					echo '</p></div>';
				}
			);
			return;
		}

		\DabDashSync\Plugin::instance()->init();
	}
);
