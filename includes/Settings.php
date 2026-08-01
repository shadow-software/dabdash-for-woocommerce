<?php
/**
 * Settings store.
 *
 * @package DabDashSync
 */

namespace DabDashSync;

defined( 'ABSPATH' ) || exit;

/**
 * Typed accessors for dabdash_woo_settings.
 *
 * The settings option is stored with autoload disabled so the bearer token is
 * not loaded on every front-end request.
 */
final class Settings {

	public const OPTION = 'dabdash_woo_settings';

	/**
	 * Allowed host suffixes for the tenant API base URL.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_HOST_SUFFIXES = array(
		'dabdash.com',
		'dabdash.app',
	);

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
		// Keep secrets out of the autoload options cache.
		update_option( self::OPTION, $next, false );
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

	/**
	 * Whether a proposed API base URL is allowed.
	 *
	 * Production hosts must be under DabDash. Local/dev hosts are allowed when
	 * WP_DEBUG is on, or via the dabdash_woo_allow_api_host filter.
	 *
	 * @param string $url Candidate base URL.
	 * @return bool
	 */
	public static function is_allowed_api_base( $url ) {
		$url = untrailingslashit( (string) $url );

		if ( '' === $url ) {
			return false;
		}

		$host   = wp_parse_url( $url, PHP_URL_HOST );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'https', 'http' ), true ) ) {
			return false;
		}

		$host = strtolower( $host );

		/**
		 * Filter whether an API host is allowed.
		 *
		 * @param bool|null $allowed Null to use the default allowlist.
		 * @param string    $host    Hostname.
		 * @param string    $url     Full base URL.
		 */
		$filtered = apply_filters( 'dabdash_woo_allow_api_host', null, $host, $url );

		if ( null !== $filtered ) {
			return (bool) $filtered;
		}

		foreach ( self::ALLOWED_HOST_SUFFIXES as $suffix ) {
			if ( $host === $suffix || str_ends_with( $host, '.' . $suffix ) ) {
				return true;
			}
		}

		// Local development only when WP_DEBUG is on.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
				return true;
			}
			if ( str_ends_with( $host, '.test' ) || str_ends_with( $host, '.local' ) ) {
				return true;
			}
		}

		return false;
	}
}
