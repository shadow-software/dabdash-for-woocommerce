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
 * Factory for OpenAPI-generated tenant API clients.
 */
final class SdkFactory {

	/**
	 * Shared SDK configuration for the tenant API.
	 *
	 * @param string|null $access_token Override token; defaults to settings.
	 * @return Configuration
	 */
	public static function configuration( $access_token = null ) {
		$token = null !== $access_token ? (string) $access_token : Settings::access_token();
		$host  = untrailingslashit( Settings::api_base() );

		$config = Configuration::getDefaultConfiguration();

		if ( '' !== $host ) {
			$config->setHost( $host );
		}

		if ( '' !== $token ) {
			$config->setAccessToken( $token );
		}

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
		return '' !== Settings::api_base() && '' !== Settings::access_token();
	}
}
