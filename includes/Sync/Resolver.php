<?php
/**
 * Conflict resolution.
 *
 * Three rules, one per field class. They are separated from the Puller and
 * Pusher on purpose: resolution is the part of a two-way sync that is easy to
 * get subtly, silently wrong, so it is pure, side-effect free, and unit-tested
 * in isolation.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Decides which value survives when DabDash and WordPress disagree.
 */
final class Resolver {

	/**
	 * Canonical fields: DabDash always wins, unconditionally.
	 *
	 * No timestamp comparison, no dirty-checking. If WordPress somehow holds a
	 * different value it is wrong by definition and is overwritten.
	 *
	 * @param mixed $remote Value from DabDash.
	 * @return mixed The value to store in WordPress.
	 */
	public static function resolveCanonical( $remote ) {
		return $remote;
	}

	/**
	 * Contact fields: DabDash wins ties, but a genuinely newer local edit is
	 * allowed to be *proposed* upward.
	 *
	 * This never decides the final value on its own — it decides whether WP has
	 * something worth proposing. The API's response is what actually lands, which
	 * is what keeps "DabDash is canonical" true at runtime instead of merely on
	 * paper.
	 *
	 * @param mixed    $remote          Value from DabDash.
	 * @param mixed    $local           Value currently in WordPress.
	 * @param int|null $remote_modified Unix timestamp of the DabDash record's last change.
	 * @param int|null $local_modified  Unix timestamp of the WordPress record's last local edit.
	 * @return array{value: mixed, propose: bool}
	 */
	public static function resolveContact( $remote, $local, $remote_modified = null, $local_modified = null ) {
		// Identical — nothing to do, and nothing to propose.
		if ( self::equivalent( $remote, $local ) ) {
			return array(
				'value'   => $remote,
				'propose' => false,
			);
		}

		// A local edit is only worth proposing if we can prove it is newer. Absent
		// timestamps mean we cannot prove it, so DabDash wins — the canonical side
		// is the safe default when the evidence is missing.
		if ( null === $local_modified || null === $remote_modified ) {
			return array(
				'value'   => $remote,
				'propose' => false,
			);
		}

		if ( $local_modified > $remote_modified ) {
			return array(
				'value'   => $local,
				'propose' => true,
			);
		}

		return array(
			'value'   => $remote,
			'propose' => false,
		);
	}

	/**
	 * Consent fields: most restrictive wins, regardless of which side is newer.
	 *
	 * This is the one place where "DabDash is canonical" is deliberately NOT the
	 * rule, and it is the most important rule in the plugin.
	 *
	 * Consider: a customer unsubscribes on the WooCommerce storefront at 10:00.
	 * The DabDash record, last written at 09:00, still says opted-in. Under
	 * last-write-wins — or under a strict canonical rule — the next pull would
	 * overwrite the opt-out and silently re-subscribe someone who asked to be
	 * left alone. That is a CASL/CAN-SPAM violation caused by a sync bug, and the
	 * customer's only remedy is to unsubscribe again and watch it fail again.
	 *
	 * So suppression is a ratchet: true (opted out / muted) always beats false
	 * within a cycle, in both directions. The opt-out is then pushed upward so
	 * DabDash converges on the restrictive value rather than fighting it.
	 *
	 * Un-suppressing is therefore never automatic. A customer who wants back in
	 * must opt in explicitly on the DabDash side, which is exactly the consent
	 * provenance the `sms_marketing_consent_source` column exists to record.
	 *
	 * @param bool $remote Suppression flag from DabDash (true = suppressed).
	 * @param bool $local  Suppression flag from WordPress (true = suppressed).
	 * @return array{value: bool, propose: bool}
	 */
	public static function mergeConsent( $remote, $local ) {
		$merged = ( (bool) $remote || (bool) $local );

		// Propose upward only when WordPress is the side holding the suppression —
		// that is new information DabDash does not have yet.
		$propose = ( $merged && ! (bool) $remote );

		return array(
			'value'   => $merged,
			'propose' => $propose,
		);
	}

	/**
	 * Deletion requests propagate and never clear.
	 *
	 * Once a customer has asked to be deleted, no sync may un-ask it. A cleared
	 * `deletion_requested_at` coming back from the API is treated as absence of
	 * information, not as a retraction.
	 *
	 * @param string|null $remote Timestamp from DabDash, if any.
	 * @param string|null $local  Timestamp already recorded in WordPress, if any.
	 * @return string|null The surviving timestamp.
	 */
	public static function resolveDeletion( $remote, $local ) {
		if ( ! empty( $local ) ) {
			return $local;
		}

		return ! empty( $remote ) ? $remote : null;
	}

	/**
	 * Loose equality that does not treat "" and null and 0 as interchangeable in
	 * the ways PHP normally would, but does treat "5" and 5 as equal — which is
	 * what round-tripping through JSON and wp_usermeta actually produces.
	 *
	 * Without this, every sync cycle would see phantom differences on integer
	 * fields (loyalty_points comes back as int from the API and as a numeric
	 * string from get_user_meta) and push endlessly.
	 *
	 * @param mixed $a First value.
	 * @param mixed $b Second value.
	 * @return bool
	 */
	public static function equivalent( $a, $b ) {
		if ( null === $a || null === $b ) {
			return ( null === $a && null === $b )
				|| ( null === $a && '' === $b )
				|| ( null === $b && '' === $a );
		}

		if ( is_bool( $a ) || is_bool( $b ) ) {
			return (bool) $a === (bool) $b;
		}

		if ( is_numeric( $a ) && is_numeric( $b ) ) {
			return 0 === self::bccomp_fallback( (string) $a, (string) $b );
		}

		return (string) $a === (string) $b;
	}

	/**
	 * Compare two numeric strings without requiring bcmath.
	 *
	 * @param string $a First numeric string.
	 * @param string $b Second numeric string.
	 * @return int
	 */
	private static function bccomp_fallback( $a, $b ) {
		$fa = (float) $a;
		$fb = (float) $b;

		if ( abs( $fa - $fb ) < 0.000001 ) {
			return 0;
		}

		return ( $fa < $fb ) ? -1 : 1;
	}
}
