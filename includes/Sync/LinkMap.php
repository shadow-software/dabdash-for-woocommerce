<?php
/**
 * WordPress user ↔ DabDash customer link.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the DabDash customer id on a WP user via usermeta.
 */
final class LinkMap {

	/**
	 * Usermeta key for the remote customer id.
	 */
	public const META_CUSTOMER_ID = '_dabdash_woo_customer_id';

	/**
	 * Usermeta key for the last successful sync timestamp (UTC mysql).
	 */
	public const META_SYNCED_AT = '_dabdash_woo_synced_at';

	/**
	 * DabDash customer id linked to a WP user, or 0.
	 *
	 * @param int $user_id WordPress user id.
	 * @return int
	 */
	public static function get_customer_id( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return 0;
		}

		$id = (int) get_user_meta( $user_id, self::META_CUSTOMER_ID, true );
		if ( $id > 0 ) {
			return $id;
		}

		// Pre-1.0.1 installs used `_dabdash_customer_id`.
		return (int) get_user_meta( $user_id, '_dabdash_customer_id', true );
	}

	/**
	 * WordPress user id linked to a DabDash customer, or 0.
	 *
	 * @param int $customer_id DabDash customer id.
	 * @return int
	 */
	public static function get_user_id( $customer_id ) {
		$customer_id = (int) $customer_id;

		if ( $customer_id <= 0 ) {
			return 0;
		}

		$users = get_users(
			array(
				'meta_key'   => self::META_CUSTOMER_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed lookup by unique customer id.
				'meta_value' => (string) $customer_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact match on a single id.
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		if ( empty( $users ) ) {
			return 0;
		}

		return (int) $users[0];
	}

	/**
	 * Persist the bidirectional link on the WP user.
	 *
	 * @param int $user_id     WordPress user id.
	 * @param int $customer_id DabDash customer id.
	 * @return void
	 */
	public static function link( $user_id, $customer_id ) {
		$user_id     = (int) $user_id;
		$customer_id = (int) $customer_id;

		if ( $user_id <= 0 || $customer_id <= 0 ) {
			return;
		}

		update_user_meta( $user_id, self::META_CUSTOMER_ID, $customer_id );
	}

	/**
	 * Record that this user was synced successfully.
	 *
	 * @param int $user_id WordPress user id.
	 * @return void
	 */
	public static function mark_synced( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return;
		}

		update_user_meta( $user_id, self::META_SYNCED_AT, current_time( 'mysql', true ) );
	}

	/**
	 * Drop the DabDash link meta from a WP user.
	 *
	 * @param int $user_id WordPress user id.
	 * @return void
	 */
	public static function unlink( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return;
		}

		delete_user_meta( $user_id, self::META_CUSTOMER_ID );
		delete_user_meta( $user_id, self::META_SYNCED_AT );
	}
}
