<?php
/**
 * PHPStan bootstrap constants and Action Scheduler stubs.
 *
 * @package DabDashSync
 */

define( 'DABDASH_WOO_VERSION', '1.0.0' );
define( 'DABDASH_WOO_FILE', '' );
define( 'DABDASH_WOO_PATH', '' );
define( 'DABDASH_WOO_URL', '' );

if ( ! function_exists( 'as_has_scheduled_action' ) ) {
	/**
	 * @param string               $hook  Hook name.
	 * @param array<string, mixed> $args  Args.
	 * @param string               $group Group.
	 * @return bool
	 */
	function as_has_scheduled_action( $hook, $args = array(), $group = '' ) {
		return false;
	}
}

if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
	/**
	 * @param int                  $timestamp      When.
	 * @param int                  $interval_in_seconds Interval.
	 * @param string               $hook           Hook name.
	 * @param array<string, mixed> $args           Args.
	 * @param string               $group          Group.
	 * @return int
	 */
	function as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args = array(), $group = '' ) {
		return 0;
	}
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	/**
	 * @param string                    $hook  Hook name.
	 * @param array<string, mixed>|null $args  Args.
	 * @param string                    $group Group.
	 * @return void
	 */
	function as_unschedule_all_actions( $hook, $args = null, $group = '' ) {}
}
