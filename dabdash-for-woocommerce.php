<?php
/**
 * Plugin Name:       DabDash Sync for WooCommerce
 * Plugin URI:        https://github.com/shadow-software/dabdash-for-woocommerce
 * Description:       Keeps WooCommerce customers in step with DabDash — verification status, loyalty balance, and marketing consent — with DabDash as the source of truth. Free and open source; requires a DabDash tenant.
 * Version:           1.0.1
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * Author:            Shadow Software LLC
 * Author URI:        https://shadowsoftware.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dabdash-for-woocommerce
 * Domain Path:       /languages
 *
 * WC requires at least: 8.2
 * WC tested up to:      11.0
 *
 * @package DabDashSync
 */

defined( 'ABSPATH' ) || exit;

define( 'DABDASH_WOO_VERSION', '1.0.1' );
define( 'DABDASH_WOO_FILE', __FILE__ );
define( 'DABDASH_WOO_PATH', plugin_dir_path( __FILE__ ) );
define( 'DABDASH_WOO_URL', plugin_dir_url( __FILE__ ) );

/*
 * Runtime Composer dependencies (shadow-software/dabdash-php-sdk) ship in vendor/.
 */
$dabdash_woo_autoload = DABDASH_WOO_PATH . 'vendor/autoload.php';
if ( is_readable( $dabdash_woo_autoload ) ) {
	require_once $dabdash_woo_autoload;
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
		$base      = DABDASH_WOO_PATH . 'includes' . DIRECTORY_SEPARATOR;
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
	DABDASH_WOO_FILE,
	static function () {
		\DabDashSync\Lifecycle::activate();
	}
);

register_deactivation_hook(
	DABDASH_WOO_FILE,
	static function () {
		\DabDashSync\Lifecycle::deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! is_readable( DABDASH_WOO_PATH . 'vendor/autoload.php' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__(
						'DabDash Sync is missing its Composer dependencies. Run composer install in the plugin directory, or reinstall from a release ZIP.',
						'dabdash-for-woocommerce'
					);
					echo '</p></div>';
				}
			);
			return;
		}

		\DabDashSync\Plugin::instance()->init();
	}
);
