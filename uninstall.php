<?php
/**
 * Uninstall — remove settings, tokens, cron, and mirrored DabDash usermeta.
 *
 * Does not delete WordPress users or anything on DabDash.
 *
 * @package DabDashSync
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dabdash_woo_settings' );
delete_option( 'dabdash_woo_version' );
delete_option( 'dabdash_woo_updated_since' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'dabdash_woo_pull', null, 'dabdash-woo' );
	as_unschedule_all_actions( 'dabdash_woo_pull', array(), 'dabdash-woo' );
}

wp_clear_scheduled_hook( 'dabdash_woo_pull' );

global $wpdb;

$dabdash_woo_meta_keys = array(
	'_dabdash_customer_id',
	'_dabdash_synced_at',
	'_dabdash_local_modified',
	'_dabdash_id_verified_at',
	'_dabdash_medical_verified_at',
	'_dabdash_email_verified_at',
	'_dabdash_phone_verified_at',
	'_dabdash_phone_validation_status',
	'_dabdash_loyalty_points',
	'_dabdash_no_loyalty',
	'_dabdash_no_coupons',
	'_dabdash_deletion_requested_at',
	'_dabdash_last_login_at',
	'_dabdash_email_opt_out',
	'_dabdash_sms_marketing_opt_out',
	'_dabdash_sms_notifications_muted',
);

$dabdash_woo_in = implode( ',', array_fill( 0, count( $dabdash_woo_meta_keys ), '%s' ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ($dabdash_woo_in)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN list from fixed keys.
		$dabdash_woo_meta_keys
	)
);
