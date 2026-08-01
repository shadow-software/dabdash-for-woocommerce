<?php
/**
 * Plugin bootstrap.
 *
 * @package DabDashSync
 */

namespace DabDashSync;

use DabDashSync\Admin\SettingsPage;
use DabDashSync\Sync\Hooks;
use DabDashSync\Sync\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Wires settings, sync hooks, and storefront/user meta listeners.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Singleton accessor.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks once plugins are loaded.
	 *
	 * @return void
	 */
	public function init() {
		if ( is_admin() ) {
			( new SettingsPage() )->register();
		}

		( new Queue() )->register();
		( new Hooks() )->register();

		add_filter(
			'plugin_action_links_' . plugin_basename( DABDASH_WOO_FILE ),
			array( $this, 'plugin_action_links' )
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array<string, string> $links Existing links.
	 * @return array<string, string>
	 */
	public function plugin_action_links( $links ) {
		$url = admin_url( 'options-general.php?page=dabdash-woo' );

		return array_merge(
			array(
				'settings' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'dabdash-for-woocommerce' ) . '</a>',
			),
			$links
		);
	}
}
