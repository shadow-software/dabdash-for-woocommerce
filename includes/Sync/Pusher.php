<?php
/**
 * WordPress → DabDash contact/consent proposals.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

use DabDashSync\Api\SdkFactory;
use ShadowSoftware\DabDash\Api\CustomersApi;
use ShadowSoftware\DabDash\ApiException;
use ShadowSoftware\DabDash\Model\CustomerUpdateRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Pushes only pushable (contact/consent) fields via customerUpdate.
 */
final class Pusher {

	/**
	 * Customers API client.
	 *
	 * @var CustomersApi
	 */
	private $api;

	/**
	 * Construct with an optional injected Customers API client.
	 *
	 * @param CustomersApi|null $api Injected for tests.
	 */
	public function __construct( $api = null ) {
		$this->api = $api instanceof CustomersApi ? $api : SdkFactory::customers();
	}

	/**
	 * Propose contact/consent changes for one DabDash customer.
	 *
	 * Canonical and NEVER keys are stripped by FieldMap::filterOutbound() before
	 * the request is built — a programming error cannot forge verification state.
	 *
	 * @param int                  $customer_id DabDash customer id.
	 * @param array<string, mixed> $payload     Proposed field values.
	 * @return array<string, mixed> Filtered payload that was sent (empty if nothing).
	 * @throws ApiException On API failure.
	 */
	public function propose( $customer_id, array $payload ) {
		$customer_id = (int) $customer_id;

		if ( $customer_id <= 0 || empty( $payload ) ) {
			return array();
		}

		list( $filtered, $rejected ) = FieldMap::filterOutbound( $payload );

		if ( ! empty( $rejected ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only guardrail for rejected keys.
			error_log( 'DabDash Sync rejected outbound keys: ' . implode( ', ', $rejected ) );
		}

		if ( empty( $filtered ) ) {
			return array();
		}

		$request = new CustomerUpdateRequest();
		$request->setCustomerId( $customer_id );

		if ( array_key_exists( 'name', $filtered ) ) {
			$request->setName( null === $filtered['name'] ? null : (string) $filtered['name'] );
		}
		if ( array_key_exists( 'email', $filtered ) ) {
			$request->setEmail( null === $filtered['email'] ? null : (string) $filtered['email'] );
		}
		if ( array_key_exists( 'phone', $filtered ) ) {
			$request->setPhone( null === $filtered['phone'] ? null : (string) $filtered['phone'] );
		}
		if ( array_key_exists( 'email_opt_out', $filtered ) && true === (bool) $filtered['email_opt_out'] ) {
			$request->setEmailOptOut( true );
		}
		if ( array_key_exists( 'sms_marketing_opt_out', $filtered ) && true === (bool) $filtered['sms_marketing_opt_out'] ) {
			$request->setSmsMarketingOptOut( true );
		}
		if ( array_key_exists( 'sms_notifications_muted', $filtered ) && true === (bool) $filtered['sms_notifications_muted'] ) {
			$request->setSmsNotificationsMuted( true );
		}

		$this->api->customerUpdate( $request );

		return $filtered;
	}
}
