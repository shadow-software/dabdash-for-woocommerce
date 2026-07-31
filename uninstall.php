<?php
/**
 * Uninstall — remove settings and tokens only. Never delete WP users or DabDash data.
 *
 * @package DabDashSync
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dabdash_sync_settings' );
delete_option( 'dabdash_sync_version' );
delete_option( 'dabdash_sync_updated_since' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'dabdash_sync_pull', null, 'dabdash-sync' );
	as_unschedule_all_actions( 'dabdash_sync_pull', array(), 'dabdash-sync' );
}

wp_clear_scheduled_hook( 'dabdash_sync_pull' );
