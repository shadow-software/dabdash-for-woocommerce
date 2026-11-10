<?php
/**
 * Background sync schedule.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

use DabDashSync\Api\SdkFactory;
use DabDashSync\Settings;
use ShadowSoftware\DabDash\ApiException;

defined( 'ABSPATH' ) || exit;

/**
 * Hourly pull via Action Scheduler (when WooCommerce is present) or WP-Cron.
 */
final class Queue {

	/**
	 * Hook name for the pull job.
	 */
	public const HOOK_PULL = 'dabdash_woo_pull';

	/**
	 * Hook name for a single order push.
	 */
	public const HOOK_PUSH_ORDER = 'dabdash_woo_push_order';

	/**
	 * Action Scheduler group name.
	 */
	private const GROUP = 'dabdash-woo';

	/**
	 * Register handlers and ensure the recurring schedule.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK_PULL, array( $this, 'run_pull' ) );
		add_action( self::HOOK_PUSH_ORDER, array( $this, 'run_push_order' ), 10, 1 );
		add_action( 'init', array( $this, 'ensure_recurring' ) );
	}

	/**
	 * Queue an order upsert (Action Scheduler when available, else immediate).
	 *
	 * @param int $order_id Woo order id.
	 * @return void
	 */
	public static function enqueue_order_push( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 || ! Settings::sync_enabled() || ! SdkFactory::is_configured() ) {
			return;
		}

		if ( self::has_action_scheduler() && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_PUSH_ORDER, array( $order_id ), self::GROUP );

			return;
		}

		( new self() )->run_push_order( $order_id );
	}

	/**
	 * Schedule (or cancel) the hourly pull based on settings.
	 *
	 * @return void
	 */
	public function ensure_recurring() {
		if ( ! Settings::sync_enabled() || ! SdkFactory::is_configured() ) {
			$this->cancel_recurring();

			return;
		}

		if ( self::has_action_scheduler() ) {
			wp_clear_scheduled_hook( self::HOOK_PULL );

			if ( ! as_has_scheduled_action( self::HOOK_PULL, array(), self::GROUP ) ) {
				as_schedule_recurring_action(
					time() + MINUTE_IN_SECONDS,
					HOUR_IN_SECONDS,
					self::HOOK_PULL,
					array(),
					self::GROUP
				);
			}

			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_PULL ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK_PULL );
		}
	}

	/**
	 * Cancel the recurring pull only.
	 *
	 * @return void
	 */
	public function cancel_recurring() {
		wp_clear_scheduled_hook( self::HOOK_PULL );

		if ( self::has_action_scheduler() && function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_PULL, array(), self::GROUP );
		}
	}

	/**
	 * Cancel every scheduled pull — used on deactivate/uninstall.
	 *
	 * @return void
	 */
	public static function cancel_all() {
		wp_clear_scheduled_hook( self::HOOK_PULL );

		if ( self::has_action_scheduler() && function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_PULL, null, self::GROUP );
			as_unschedule_all_actions( self::HOOK_PULL, array(), self::GROUP );
		}
	}

	/**
	 * Run one pull cycle.
	 *
	 * @return void
	 */
	public function run_pull() {
		if ( ! Settings::sync_enabled() || ! SdkFactory::is_configured() ) {
			return;
		}

		try {
			( new Puller() )->pull();
		} catch ( ApiException $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only pull failure trail.
				error_log( 'DabDash Sync pull failed: ' . $e->getMessage() );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only unexpected failure trail.
				error_log( 'DabDash Sync pull error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Push one Woo order to DabDash order_upsert.
	 *
	 * @param int $order_id Woo order id.
	 * @return void
	 */
	public function run_push_order( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 || ! Settings::sync_enabled() || ! SdkFactory::is_configured() ) {
			return;
		}
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		try {
			( new OrderPusher() )->push( $order );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only order push failure trail.
				error_log( 'DabDash Sync order push failed: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Whether Action Scheduler is available (typically via WooCommerce).
	 *
	 * @return bool
	 */
	private static function has_action_scheduler() {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_has_scheduled_action' )
			&& function_exists( 'as_unschedule_all_actions' );
	}
}
