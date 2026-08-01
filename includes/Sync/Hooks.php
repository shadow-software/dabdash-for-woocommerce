<?php
/**
 * WordPress / WooCommerce hooks that mark local customer edits for sync.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Stamps `_dabdash_local_modified` when contact or consent fields change locally
 * so the next pull can propose them upward (consent = most-restrictive).
 */
final class Hooks {

	/**
	 * True while Puller is writing remote fields into usermeta.
	 *
	 * @var bool
	 */
	private static $applying_remote = false;

	/**
	 * User-meta keys that count as a local edit for sync purposes.
	 *
	 * @var list<string>
	 */
	private const TRACKED_META = array(
		'billing_phone',
		'first_name',
		'last_name',
		'_dabdash_email_opt_out',
		'_dabdash_sms_marketing_opt_out',
		'_dabdash_sms_notifications_muted',
	);

	/**
	 * Begin a remote-apply window (ignore meta hooks).
	 *
	 * @return void
	 */
	public static function begin_remote_apply() {
		self::$applying_remote = true;
	}

	/**
	 * End a remote-apply window.
	 *
	 * @return void
	 */
	public static function end_remote_apply() {
		self::$applying_remote = false;
	}

	/**
	 * Register listeners.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'profile_update', array( $this, 'on_profile_update' ), 20, 1 );
		add_action( 'user_register', array( $this, 'touch' ), 20, 1 );
		add_action( 'updated_user_meta', array( $this, 'on_meta_updated' ), 20, 4 );
		add_action( 'added_user_meta', array( $this, 'on_meta_updated' ), 20, 4 );

		// WooCommerce account / checkout / admin customer saves.
		add_action( 'woocommerce_save_account_details', array( $this, 'touch' ), 20, 1 );
		add_action( 'woocommerce_checkout_update_user_meta', array( $this, 'touch' ), 20, 1 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'on_order_customer' ), 20, 1 );
		add_action( 'woocommerce_customer_save_address', array( $this, 'touch' ), 20, 1 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 20, 1 );
	}

	/**
	 * Profile save (wp-admin or account form).
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function on_profile_update( $user_id ) {
		$this->touch( (int) $user_id );
	}

	/**
	 * Meta write listener.
	 *
	 * @param int    $meta_id    Meta row id.
	 * @param int    $user_id    User id.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function on_meta_updated( $meta_id, $user_id, $meta_key, $meta_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! in_array( (string) $meta_key, self::TRACKED_META, true ) ) {
			return;
		}

		$this->touch( (int) $user_id );
	}

	/**
	 * When an order is saved in admin, touch the customer if present.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function on_order_customer( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$customer_id = (int) $order->get_customer_id();

		if ( $customer_id > 0 ) {
			$this->touch( $customer_id );
		}
	}

	/**
	 * After checkout, stamp the customer so consent/contact can propose upward.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function on_order_processed( $order_id ) {
		$this->on_order_customer( $order_id );
	}

	/**
	 * Record that WordPress holds a newer contact/consent edit.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function touch( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 || self::$applying_remote ) {
			return;
		}

		update_user_meta( $user_id, '_dabdash_local_modified', gmdate( 'c' ) );
	}
}
