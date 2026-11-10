<?php
/**
 * Push WooCommerce orders to DabDash order_upsert.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

use DabDashSync\Api\SdkFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

defined( 'ABSPATH' ) || exit;

/**
 * POSTs mapped order rows to /api/v1/tools/order_upsert on the tenant host.
 * Uses raw HTTP until dabdash-php-sdk regenerates OrdersApi::orderUpsert.
 */
final class OrderPusher {

	/**
	 * HTTP client.
	 *
	 * @var Client
	 */
	private $http;

	/**
	 * @param Client|null $http Injected for tests.
	 */
	public function __construct( $http = null ) {
		$this->http = $http instanceof Client ? $http : new Client( array( 'timeout' => 30 ) );
	}

	/**
	 * Upsert one Woo order (apply).
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, mixed> Tool response body.
	 * @throws \RuntimeException On HTTP/API failure.
	 */
	public function push( $order ) {
		$row = ( new OrderMapper() )->to_row( $order );

		return $this->upsert_rows( array( $row ), false );
	}

	/**
	 * @param list<array<string, mixed>> $rows    Mapped rows.
	 * @param bool                       $dry_run Preview when true.
	 * @return array<string, mixed>
	 * @throws \RuntimeException On failure.
	 */
	public function upsert_rows( array $rows, $dry_run = false ) {
		if ( ! SdkFactory::is_configured() ) {
			throw new \RuntimeException( 'DabDash Sync is not configured.' );
		}

		$config = SdkFactory::configuration();
		$host   = untrailingslashit( $config->getHost() );
		$token  = (string) $config->getAccessToken();
		$url    = $host . '/api/v1/tools/order_upsert';

		$body = array(
			'rows'    => array_values( $rows ),
			'dry_run' => (bool) $dry_run,
		);
		if ( ! $dry_run ) {
			$body['confirm'] = true;
		}

		try {
			$response = $this->http->post(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/json',
						'Content-Type'  => 'application/json',
						'User-Agent'    => $config->getUserAgent(),
					),
					'json'    => $body,
				)
			);
		} catch ( GuzzleException $e ) {
			throw new \RuntimeException( 'order_upsert HTTP failed: ' . $e->getMessage(), 0, $e );
		}

		$decoded = json_decode( (string) $response->getBody(), true );
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException( 'order_upsert returned non-JSON.' );
		}
		if ( isset( $decoded['error'] ) ) {
			$message = is_string( $decoded['message'] ?? null ) ? $decoded['message'] : (string) $decoded['error'];
			throw new \RuntimeException( 'order_upsert error: ' . $message );
		}

		return $decoded;
	}
}
