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
		if ( false === get_option( 'dabdash_sync_settings', false ) ) {
			add_option(
				'dabdash_sync_settings',
				array(
					'api_base'     => '',
					'access_token' => '',
					'sync_enabled' => false,
				)
			);
		}

		update_option( 'dabdash_sync_version', DABDASH_SYNC_VERSION );
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
