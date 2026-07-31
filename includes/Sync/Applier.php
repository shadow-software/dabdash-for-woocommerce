<?php
/**
 * Pure field-resolution plan for one customer.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Turns remote + local field maps into WP writes and upward proposals.
 *
 * Side-effect free so it can be unit-tested without WordPress or HTTP.
 */
final class Applier {

	/**
	 * Build the write/propose plan for one customer sync cycle.
	 *
	 * NEVER fields are ignored even if present on `$remote`. Canonical values
	 * always come from DabDash. Contact uses Resolver::resolveContact(). Consent
	 * uses Resolver::mergeConsent(). Deletion requests never clear.
	 *
	 * @param array<string, mixed> $remote          DabDash customer fields.
	 * @param array<string, mixed> $local           Local values keyed by DabDash field.
	 * @param int|null             $remote_modified Unix timestamp of remote update.
	 * @param int|null             $local_modified  Unix timestamp of local contact edit.
	 * @return array{writes: array<string, mixed>, proposes: array<string, mixed>}
	 */
	public static function plan( array $remote, array $local, $remote_modified = null, $local_modified = null ) {
		$writes   = array();
		$proposes = array();

		foreach ( FieldMap::pullable() as $field ) {
			// Only fields the API actually returned — absent keys are not "null".
			if ( ! array_key_exists( $field, $remote ) ) {
				continue;
			}

			$class = FieldMap::classify( $field );

			// Fail closed — NEVER must never reach a write destination.
			if ( FieldMap::NEVER === $class ) {
				continue;
			}

			$remote_value = $remote[ $field ];
			$local_value  = array_key_exists( $field, $local ) ? $local[ $field ] : null;

			if ( 'deletion_requested_at' === $field ) {
				$merged = Resolver::resolveDeletion(
					is_string( $remote_value ) || null === $remote_value ? $remote_value : (string) $remote_value,
					is_string( $local_value ) || null === $local_value ? $local_value : (string) $local_value
				);

				if ( ! Resolver::equivalent( $merged, $local_value ) ) {
					$writes[ $field ] = $merged;
				}

				continue;
			}

			if ( FieldMap::CANONICAL === $class ) {
				$value = Resolver::resolveCanonical( $remote_value );

				if ( ! Resolver::equivalent( $value, $local_value ) ) {
					$writes[ $field ] = $value;
				}

				continue;
			}

			if ( FieldMap::CONTACT === $class ) {
				$result = Resolver::resolveContact( $remote_value, $local_value, $remote_modified, $local_modified );

				if ( ! Resolver::equivalent( $result['value'], $local_value ) ) {
					$writes[ $field ] = $result['value'];
				}

				if ( ! empty( $result['propose'] ) ) {
					$proposes[ $field ] = $result['value'];
				}

				continue;
			}

			if ( FieldMap::CONSENT === $class ) {
				$result = Resolver::mergeConsent( (bool) $remote_value, (bool) $local_value );

				if ( ! Resolver::equivalent( $result['value'], $local_value ) ) {
					$writes[ $field ] = $result['value'];
				}

				if ( ! empty( $result['propose'] ) ) {
					$proposes[ $field ] = $result['value'];
				}
			}
		}

		return array(
			'writes'   => $writes,
			'proposes' => $proposes,
		);
	}
}
