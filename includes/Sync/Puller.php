<?php
/**
 * DabDash → WordPress customer pull.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

use DabDashSync\Api\SdkFactory;
use ShadowSoftware\DabDash\Api\CustomersApi;
use ShadowSoftware\DabDash\ApiException;
use ShadowSoftware\DabDash\Model\CustomerListRequest;
use ShadowSoftware\DabDash\Model\CustomerListResponseCustomersInner;
use ShadowSoftware\DabDash\ObjectSerializer;

defined( 'ABSPATH' ) || exit;

/**
 * Pages customerList and applies FieldMap/Resolver rules to matching WP users.
 */
final class Puller {

	/**
	 * Option storing the incremental sync watermark (ISO-8601).
	 */
	public const OPTION_UPDATED_SINCE = 'dabdash_woo_updated_since';

	/**
	 * Rows per API page (API max is 100).
	 */
	private const PAGE_SIZE = 50;

	/**
	 * Safety cap on pages per run so a cron tick cannot run forever.
	 */
	private const MAX_PAGES = 40;

	/**
	 * Customers API client.
	 *
	 * @var CustomersApi
	 */
	private $api;

	/**
	 * Outbound pusher for contact/consent proposals.
	 *
	 * @var Pusher
	 */
	private $pusher;

	/**
	 * Construct with optional injected API clients.
	 *
	 * @param CustomersApi|null $api    Injected for tests.
	 * @param Pusher|null       $pusher Injected for tests.
	 */
	public function __construct( $api = null, $pusher = null ) {
		$this->api    = $api instanceof CustomersApi ? $api : SdkFactory::customers();
		$this->pusher = $pusher instanceof Pusher ? $pusher : new Pusher( $this->api );
	}

	/**
	 * Pull customers updated since the last watermark and apply them locally.
	 *
	 * @return int Number of customers processed.
	 * @throws ApiException On API failure.
	 */
	public function pull() {
		if ( ! SdkFactory::is_configured() ) {
			return 0;
		}

		$updated_since = (string) get_option( self::OPTION_UPDATED_SINCE, '' );
		$cursor        = null;
		$processed     = 0;
		$newest        = $updated_since;
		$pages         = 0;

		do {
			$request = new CustomerListRequest();
			$request->setLimit( self::PAGE_SIZE );

			if ( '' !== $updated_since ) {
				$request->setUpdatedSince( $updated_since );
			}

			if ( is_string( $cursor ) ) {
				$request->setCursor( $cursor );
			}

			$response = $this->api->customerList( $request );
			$data     = $response->getData();

			if ( null === $data ) {
				break;
			}

			$customers = $data->getCustomers();
			$customers = is_array( $customers ) ? $customers : array();

			foreach ( $customers as $customer ) {
				if ( ! $customer instanceof CustomerListResponseCustomersInner ) {
					continue;
				}

				$remote = $this->customer_to_array( $customer );
				$this->apply_customer( $remote );
				++$processed;

				if ( ! empty( $remote['updated_at'] ) && is_string( $remote['updated_at'] ) ) {
					if ( '' === $newest || strcmp( $remote['updated_at'], $newest ) > 0 ) {
						$newest = $remote['updated_at'];
					}
				}
			}

			$has_more = (bool) $data->getHasMore();
			$next     = $data->getNextCursor();
			$cursor   = ( is_string( $next ) && '' !== $next ) ? $next : null;
			++$pages;
		} while ( $has_more && null !== $cursor && $pages < self::MAX_PAGES );

		if ( '' !== $newest && $newest !== $updated_since ) {
			update_option( self::OPTION_UPDATED_SINCE, $newest, false );
		}

		return $processed;
	}

	/**
	 * Apply one remote customer payload to a matching WordPress user.
	 *
	 * Matching order: linked `_dabdash_woo_customer_id`, then email. Users are never
	 * created here — an unmatched DabDash customer is skipped.
	 *
	 * @param array<string, mixed> $remote Customer fields from the API.
	 * @return bool Whether a WP user was updated.
	 */
	public function apply_customer( array $remote ) {
		$customer_id = isset( $remote['id'] ) ? (int) $remote['id'] : 0;

		if ( $customer_id <= 0 ) {
			return false;
		}

		$user = $this->find_user( $customer_id, isset( $remote['email'] ) ? (string) $remote['email'] : '' );

		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		$local           = $this->read_local( $user );
		$remote_modified = $this->parse_timestamp( isset( $remote['updated_at'] ) ? $remote['updated_at'] : null );
		$local_modified  = $this->parse_timestamp( get_user_meta( $user->ID, '_dabdash_woo_local_modified', true ) );

		$plan = Applier::plan( $remote, $local, $remote_modified, $local_modified );

		if ( ! empty( $plan['writes'] ) ) {
			$this->write_local( $user, $plan['writes'] );
		}

		LinkMap::link( $user->ID, $customer_id );
		LinkMap::mark_synced( $user->ID );

		if ( ! empty( $plan['proposes'] ) ) {
			try {
				$this->pusher->propose( $customer_id, $plan['proposes'] );
			} catch ( ApiException $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only push failure trail.
					error_log( 'DabDash Sync propose failed for customer ' . $customer_id . ': ' . $e->getMessage() );
				}
			}
		}

		return true;
	}

	/**
	 * Resolve the WP user for a DabDash customer.
	 *
	 * @param int    $customer_id DabDash customer id.
	 * @param string $email       Remote email.
	 * @return \WP_User|null
	 */
	private function find_user( $customer_id, $email ) {
		$linked = LinkMap::get_user_id( $customer_id );

		if ( $linked > 0 ) {
			$user = get_userdata( $linked );

			if ( $user instanceof \WP_User ) {
				return $user;
			}
		}

		if ( '' === $email || ! is_email( $email ) ) {
			return null;
		}

		$user = get_user_by( 'email', $email );

		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * Read local values keyed by DabDash field name.
	 *
	 * @param \WP_User $user WordPress user.
	 * @return array<string, mixed>
	 */
	private function read_local( \WP_User $user ) {
		$local = array();

		foreach ( FieldMap::pullable() as $field ) {
			$destination = FieldMap::destination( $field );

			if ( null === $destination ) {
				continue;
			}

			if ( 'display_name' === $destination ) {
				$local[ $field ] = $user->display_name;
				continue;
			}

			if ( 'user_email' === $destination ) {
				$local[ $field ] = $user->user_email;
				continue;
			}

			$meta = get_user_meta( $user->ID, $destination, true );

			if ( FieldMap::CONSENT === FieldMap::classify( $field )
				|| in_array( $field, array( 'no_loyalty', 'no_coupons' ), true )
			) {
				$local[ $field ] = ( '' !== $meta && '0' !== $meta && false !== $meta && null !== $meta );
				continue;
			}

			$local[ $field ] = ( '' === $meta ) ? null : $meta;
		}

		return $local;
	}

	/**
	 * Persist resolved field values onto the WP user.
	 *
	 * @param \WP_User             $user   WordPress user.
	 * @param array<string, mixed> $writes DabDash field => value.
	 * @return void
	 */
	private function write_local( \WP_User $user, array $writes ) {
		Hooks::begin_remote_apply();

		try {
			$user_updates = array();

			foreach ( $writes as $field => $value ) {
				if ( FieldMap::NEVER === FieldMap::classify( $field ) ) {
					continue;
				}

				$destination = FieldMap::destination( $field );

				if ( null === $destination ) {
					continue;
				}

				if ( 'display_name' === $destination ) {
					$user_updates['display_name'] = null === $value ? '' : (string) $value;
					continue;
				}

				if ( 'user_email' === $destination ) {
					if ( is_string( $value ) && is_email( $value ) ) {
						$user_updates['user_email'] = $value;
					}
					continue;
				}

				if ( is_bool( $value ) ) {
					update_user_meta( $user->ID, $destination, $value ? '1' : '0' );
					continue;
				}

				if ( null === $value || '' === $value ) {
					delete_user_meta( $user->ID, $destination );
					continue;
				}

				update_user_meta( $user->ID, $destination, $value );
			}

			if ( ! empty( $user_updates ) ) {
				$user_updates['ID'] = $user->ID;
				wp_update_user( $user_updates );
			}
		} finally {
			Hooks::end_remote_apply();
		}
	}

	/**
	 * Convert an SDK customer model to an associative array.
	 *
	 * @param CustomerListResponseCustomersInner $customer API model.
	 * @return array<string, mixed>
	 */
	private function customer_to_array( CustomerListResponseCustomersInner $customer ) {
		$sanitized = ObjectSerializer::sanitizeForSerialization( $customer );

		if ( is_object( $sanitized ) ) {
			$sanitized = (array) $sanitized;
		}

		return is_array( $sanitized ) ? $sanitized : array();
	}

	/**
	 * Parse an ISO/mysql timestamp to a Unix epoch, or null.
	 *
	 * @param mixed $value Timestamp string or empty.
	 * @return int|null
	 */
	private function parse_timestamp( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		$ts = strtotime( $value );

		return false === $ts ? null : $ts;
	}
}
