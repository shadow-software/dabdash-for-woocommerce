<?php
/**
 * Settings store.
 *
 * @package DabDashSync
 */

namespace DabDashSync;

defined( 'ABSPATH' ) || exit;

/**
 * Typed accessors for dabdash_sync_settings.
 */
final class Settings {

	public const OPTION = 'dabdash_sync_settings';

	/**
	 * All settings with defaults applied.
	 *
	 * @return array{api_base: string, access_token: string, sync_enabled: bool}
	 */
	public static function all() {
		$defaults = array(
			'api_base'     => '',
			'access_token' => '',
			'sync_enabled' => false,
		);
		$stored   = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $stored );
	}

	/**
	 * Merge and persist a settings patch.
	 *
	 * @param array<string, mixed> $patch Partial settings.
	 * @return void
	 */
	public static function update( array $patch ) {
		$next = array_merge( self::all(), $patch );
		update_option( self::OPTION, $next );
	}

	/**
	 * Tenant API base URL.
	 *
	 * @return string
	 */
	public static function api_base() {
		return (string) self::all()['api_base'];
	}

	/**
	 * API bearer token.
	 *
	 * @return string
	 */
	public static function access_token() {
		return (string) self::all()['access_token'];
	}

	/**
	 * Whether background sync is on.
	 *
	 * @return bool
	 */
	public static function sync_enabled() {
		return (bool) self::all()['sync_enabled'];
	}
}
