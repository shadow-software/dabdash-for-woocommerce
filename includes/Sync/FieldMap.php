<?php
/**
 * The field classification — the single source of truth for what syncs, in which
 * direction, and who wins a disagreement.
 *
 * DabDash is canonical. That is not a preference, it is a property of the data:
 * a DabDash customer carries ID-verification state, medical-record verification,
 * loyalty balances and SMS consent provenance. WordPress is not an authority on
 * any of those and must never be able to assert them.
 *
 * Every field therefore falls into exactly one class:
 *
 *   CANONICAL — DabDash → WP only. Overwritten on every pull, never pushed.
 *               A WooCommerce profile form cannot forge ID verification.
 *
 *   CONTACT   — Both ways, DabDash wins ties. WP may *propose* a change; the
 *               API's response is the truth and is applied verbatim.
 *
 *   CONSENT   — Both ways, but resolved by MOST RESTRICTIVE, never by recency.
 *               See Resolver::mergeConsent() for why last-write-wins is wrong
 *               (and unlawful) here.
 *
 *   NEVER     — Not mirrored into WordPress at all. Medical record numbers,
 *               ID document paths and date of birth stay in DabDash; WP gets a
 *               derived boolean flag instead, so a storefront can gate on
 *               "is verified" without the site becoming a second store of
 *               medical records subject to every WP backup and plugin.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Classifies every DabDash customer field for sync purposes.
 */
final class FieldMap {

	public const CANONICAL = 'canonical';
	public const CONTACT   = 'contact';
	public const CONSENT   = 'consent';
	public const NEVER     = 'never';

	/**
	 * DabDash → WordPress. Authoritative facts the storefront may read but never
	 * write. Each entry maps the API field to the wp_usermeta key it lands in.
	 *
	 * Note these are deliberately *derived* for the verification fields: DabDash
	 * sends a timestamp, we store a boolean plus the timestamp, so that theme code
	 * can ask the cheap question ("verified?") without parsing dates.
	 *
	 * @var array<string, string>
	 */
	private const CANONICAL_FIELDS = array(
		'id_verified_at'              => '_dabdash_id_verified_at',
		'medical_record_verified_at'  => '_dabdash_medical_verified_at',
		'email_verified_at'           => '_dabdash_email_verified_at',
		'phone_verified_at'           => '_dabdash_phone_verified_at',
		'phone_validation_status'     => '_dabdash_phone_validation_status',
		'loyalty_points'              => '_dabdash_loyalty_points',
		'no_loyalty'                  => '_dabdash_no_loyalty',
		'no_coupons'                  => '_dabdash_no_coupons',
		'deletion_requested_at'       => '_dabdash_deletion_requested_at',
		'last_login_at'               => '_dabdash_last_login_at',
	);

	/**
	 * Two-way, DabDash wins a tie. These are the only fields a WordPress user can
	 * meaningfully own — the ones they type into a checkout or account form.
	 *
	 * @var array<string, string>
	 */
	private const CONTACT_FIELDS = array(
		'name'  => 'display_name',
		'email' => 'user_email',
		'phone' => 'billing_phone',
	);

	/**
	 * Two-way, most-restrictive wins. Suppression is a one-way ratchet within a
	 * sync cycle: an opt-out from either side always survives.
	 *
	 * @var array<string, string>
	 */
	private const CONSENT_FIELDS = array(
		'email_opt_out'           => '_dabdash_email_opt_out',
		'sms_marketing_opt_out'   => '_dabdash_sms_marketing_opt_out',
		'sms_notifications_muted' => '_dabdash_sms_notifications_muted',
	);

	/**
	 * Never mirrored into WordPress under any setting.
	 *
	 * `date_of_birth` is here rather than in CANONICAL on purpose. Age gating is
	 * satisfied by the derived `_dabdash_is_of_age` flag the API returns; storing
	 * the raw DOB would put a regulated identifier in wp_usermeta for no gain.
	 *
	 * @var list<string>
	 */
	private const NEVER_FIELDS = array(
		'id_image_path',
		'medical_record_path',
		'medical_record_byte_size',
		'medical_patient_number',
		'medical_caregiver_number',
		'date_of_birth',
		'password',
		'remember_token',
	);

	/**
	 * Classify a DabDash field name.
	 *
	 * Unknown fields are NEVER by default — fail closed. When DabDash adds a
	 * column, it does not silently start landing in WordPress just because the
	 * API began returning it; someone has to classify it here first.
	 *
	 * @param string $field DabDash customer field name.
	 * @return string One of the class constants.
	 */
	public static function classify( $field ) {
		if ( isset( self::CANONICAL_FIELDS[ $field ] ) ) {
			return self::CANONICAL;
		}

		if ( isset( self::CONTACT_FIELDS[ $field ] ) ) {
			return self::CONTACT;
		}

		if ( isset( self::CONSENT_FIELDS[ $field ] ) ) {
			return self::CONSENT;
		}

		return self::NEVER;
	}

	/**
	 * The WordPress destination for a field, or null if it is never mirrored.
	 *
	 * @param string $field DabDash customer field name.
	 * @return string|null wp_usermeta key or WP_User property.
	 */
	public static function destination( $field ) {
		$maps = array( self::CANONICAL_FIELDS, self::CONTACT_FIELDS, self::CONSENT_FIELDS );

		foreach ( $maps as $map ) {
			if ( isset( $map[ $field ] ) ) {
				return $map[ $field ];
			}
		}

		return null;
	}

	/**
	 * Fields the plugin is permitted to push to DabDash.
	 *
	 * Deliberately derived from the CONTACT and CONSENT maps rather than written
	 * out again: it is impossible for this list to drift from the classification
	 * and start pushing a canonical field.
	 *
	 * @return list<string>
	 */
	public static function pushable() {
		return array_merge(
			array_keys( self::CONTACT_FIELDS ),
			array_keys( self::CONSENT_FIELDS )
		);
	}

	/**
	 * Fields mirrored from DabDash on a pull.
	 *
	 * @return list<string>
	 */
	public static function pullable() {
		return array_merge(
			array_keys( self::CANONICAL_FIELDS ),
			array_keys( self::CONTACT_FIELDS ),
			array_keys( self::CONSENT_FIELDS )
		);
	}

	/**
	 * Whether a field is one of the consent fields, which resolve by
	 * most-restrictive rather than by precedence.
	 *
	 * @param string $field DabDash customer field name.
	 * @return bool
	 */
	public static function isConsent( $field ) {
		return isset( self::CONSENT_FIELDS[ $field ] );
	}

	/**
	 * Assert that a payload bound for DabDash contains nothing it should not.
	 *
	 * This is the last line of defence before a push. Called by Pusher on every
	 * outbound request; a canonical or never-synced key reaching this point is a
	 * programming error, so it is dropped and logged rather than sent.
	 *
	 * @param array<string, mixed> $payload Proposed outbound payload.
	 * @return array{0: array<string, mixed>, 1: list<string>} Filtered payload, rejected keys.
	 */
	public static function filterOutbound( array $payload ) {
		$allowed  = self::pushable();
		$filtered = array();
		$rejected = array();

		foreach ( $payload as $key => $value ) {
			if ( in_array( $key, $allowed, true ) ) {
				$filtered[ $key ] = $value;
			} else {
				$rejected[] = $key;
			}
		}

		return array( $filtered, $rejected );
	}

	/**
	 * The full classification, for the admin diagnostics screen.
	 *
	 * @return array<string, list<string>>
	 */
	public static function describe() {
		return array(
			self::CANONICAL => array_keys( self::CANONICAL_FIELDS ),
			self::CONTACT   => array_keys( self::CONTACT_FIELDS ),
			self::CONSENT   => array_keys( self::CONSENT_FIELDS ),
			self::NEVER     => self::NEVER_FIELDS,
		);
	}
}
