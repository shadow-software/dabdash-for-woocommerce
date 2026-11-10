<?php
/**
 * Match WooCommerce product names to DabDash strain library rows.
 *
 * Mirrors estate prior art in ProductController::matchStrains /
 * ProductManageTool::matchStrainsByName / product_image_strain_match:
 * exact case-insensitive name first, then shortest prefix/contains partial.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Pure name-matching helpers — no HTTP, no WordPress writes.
 */
final class StrainMatcher {

	public const CONFIDENCE_EXACT   = 'exact';
	public const CONFIDENCE_PARTIAL = 'partial';
	public const CONFIDENCE_NONE    = 'none';

	/**
	 * Strip common weight/size suffixes so "Blue Dream - 3.5G" can match
	 * the strain "Blue Dream".
	 *
	 * @param string $name Product or strain display name.
	 * @return string
	 */
	public static function normalize_name( $name ) {
		$name = self::plain_text( $name );
		if ( '' === $name ) {
			return '';
		}

		// Strip trailing size: Name - 3.5G / Name — 7g / Name | 1 oz.
		$stripped = preg_replace(
			'/\s*[-–—|]\s*\d+(?:[.,]\d+)?\s*(?:g|gram|grams|oz|ounce|ounces|mg|ml)\b.*$/iu',
			'',
			$name
		);
		if ( is_string( $stripped ) && '' !== trim( $stripped ) ) {
			$name = trim( $stripped );
		}

		// Trailing parenthetical size: "Name (3.5g)".
		$stripped = preg_replace(
			'/\s*\(\s*\d+(?:[.,]\d+)?\s*(?:g|gram|grams|oz|ounce|ounces|mg|ml)\s*\)\s*$/iu',
			'',
			$name
		);
		if ( is_string( $stripped ) && '' !== trim( $stripped ) ) {
			$name = trim( $stripped );
		}

		return $name;
	}

	/**
	 * Pick the best strain from a strain_lookup candidate list.
	 *
	 * @param string                     $product_name Woo product name.
	 * @param list<array<string, mixed>> $candidates Strain rows (id, name, …).
	 * @return array{strain: array<string, mixed>|null, confidence: string, query: string}
	 */
	public static function pick( $product_name, array $candidates ) {
		$raw        = trim( (string) $product_name );
		$normalized = self::normalize_name( $raw );
		$queries    = array_values(
			array_unique(
				array_filter(
					array( $raw, $normalized ),
					static function ( $q ) {
						return is_string( $q ) && '' !== $q;
					}
				)
			)
		);

		foreach ( $queries as $query ) {
			$exact = self::find_exact( $query, $candidates );
			if ( null !== $exact ) {
				return array(
					'strain'     => $exact,
					'confidence' => self::CONFIDENCE_EXACT,
					'query'      => $query,
				);
			}
		}

		foreach ( $queries as $query ) {
			$partial = self::find_partial( $query, $candidates );
			if ( null !== $partial ) {
				return array(
					'strain'     => $partial,
					'confidence' => self::CONFIDENCE_PARTIAL,
					'query'      => $query,
				);
			}
		}

		return array(
			'strain'     => null,
			'confidence' => self::CONFIDENCE_NONE,
			'query'      => '' !== $normalized ? $normalized : $raw,
		);
	}

	/**
	 * Case-insensitive exact name match.
	 *
	 * @param string                     $query      Name to match.
	 * @param list<array<string, mixed>> $candidates Strain rows.
	 * @return array<string, mixed>|null
	 */
	private static function find_exact( $query, array $candidates ) {
		$needle = self::fold( $query );
		if ( '' === $needle ) {
			return null;
		}

		foreach ( $candidates as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? self::fold( (string) $row['name'] ) : '';
			if ( $name === $needle ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Shortest strain name that shares a prefix/contains relationship wins.
	 *
	 * @param string                     $query      Name to match.
	 * @param list<array<string, mixed>> $candidates Strain rows.
	 * @return array<string, mixed>|null
	 */
	private static function find_partial( $query, array $candidates ) {
		$needle = self::fold( $query );
		if ( '' === $needle || strlen( $needle ) < 3 ) {
			return null;
		}

		$best     = null;
		$best_len = PHP_INT_MAX;
		$best_id  = PHP_INT_MAX;

		foreach ( $candidates as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? (string) $row['name'] : '';
			$fold = self::fold( $name );
			if ( '' === $fold ) {
				continue;
			}

			$hit = str_starts_with( $fold, $needle )
				|| str_starts_with( $needle, $fold )
				|| false !== strpos( $fold, $needle )
				|| false !== strpos( $needle, $fold );

			if ( ! $hit ) {
				continue;
			}

			$len = strlen( $fold );
			$id  = isset( $row['id'] ) ? (int) $row['id'] : PHP_INT_MAX;
			if ( $len < $best_len || ( $len === $best_len && $id < $best_id ) ) {
				$best     = $row;
				$best_len = $len;
				$best_id  = $id;
			}
		}

		return $best;
	}

	/**
	 * Case-fold for comparisons (mbstring when available).
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private static function fold( $value ) {
		$value = trim( (string) $value );
		if ( function_exists( 'mb_strtolower' ) ) {
			return (string) mb_strtolower( $value, 'UTF-8' );
		}

		return strtolower( $value );
	}

	/**
	 * Strip tags without requiring WordPress in unit tests.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function plain_text( $value ) {
		$value = (string) $value;
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return trim( wp_strip_all_tags( $value ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- unit-test fallback.
		return trim( strip_tags( $value ) );
	}
}
