<?php
/**
 * Activation / deactivation.
 *
 * @package DabDashSync
 */

namespace DabDashSync;

use DabDashSync\Sync\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle hooks — create nothing destructive on uninstall.
 */
final class Lifecycle {

	/**
	 * Seed default settings on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( 'dabdash_woo_settings', false ) ) {
			add_option(
				'dabdash_woo_settings',
				array(
					'api_base'     => '',
					'access_token' => '',
					'sync_enabled' => false,
				),
				'',
				false // Do not autoload — contains the API bearer token.
			);
		}

		update_option( 'dabdash_woo_version', DABDASH_WOO_VERSION, false );
	}

	/**
	 * Cancel work; leave data alone.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Queue::cancel_all();
	}
}
