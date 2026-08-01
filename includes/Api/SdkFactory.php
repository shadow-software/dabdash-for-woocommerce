<?php
/**
 * Builds configured DabDash PHP SDK clients.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Api;

use DabDashSync\Settings;
use ShadowSoftware\DabDash\Api\CustomersApi;
use ShadowSoftware\DabDash\Configuration;

defined( 'ABSPATH' ) || exit;

/**
 * Factory for OpenAPI-generated tenant API clients (Packagist:
 * shadow-software/dabdash-php-sdk).
 */
final class SdkFactory {

	/**
	 * Shared SDK configuration for the tenant API.
	 *
	 * @param string|null $access_token Override token; defaults to settings.
	 * @return Configuration
	 * @throws \InvalidArgumentException When the configured host is not allowed.
	 */
	public static function configuration( $access_token = null ) {
		$token = null !== $access_token ? (string) $access_token : Settings::access_token();
		$host  = untrailingslashit( Settings::api_base() );

		if ( '' === $host || ! Settings::is_allowed_api_base( $host ) ) {
			throw new \InvalidArgumentException( 'DabDash API base URL is missing or not an allowed DabDash host.' );
		}

		$config = Configuration::getDefaultConfiguration()
			->setHost( $host );

		if ( '' !== $token ) {
			$config->setAccessToken( $token );
		}

		$config->setUserAgent( 'dabdash-for-woocommerce/' . DABDASH_WOO_VERSION . '; WordPress/' . get_bloginfo( 'version' ) );

		return $config;
	}

	/**
	 * Customers API client.
	 *
	 * @param string|null $access_token Override token.
	 * @return CustomersApi
	 */
	public static function customers( $access_token = null ) {
		return new CustomersApi( null, self::configuration( $access_token ) );
	}

	/**
	 * Whether settings look ready for an API call.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$base = Settings::api_base();

		return '' !== $base
			&& Settings::is_allowed_api_base( $base )
			&& '' !== Settings::access_token();
	}
}
