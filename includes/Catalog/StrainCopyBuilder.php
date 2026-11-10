<?php
/**
 * Build readable WooCommerce long description + excerpt from strain_lookup rows.
 *
 * Uses only fields the tenant Catalog API returns today (name, type,
 * cannabinoids, short_description). Prose stays useful for SEO/AEO — no
 * keyword stuffing.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Pure copy builder — no HTTP, no WordPress writes.
 */
final class StrainCopyBuilder {

	/**
	 * Build post_content (HTML) and post_excerpt (plain) for a matched strain.
	 *
	 * @param array<string, mixed> $strain Strain row from strain_lookup / matcher.
	 * @param string               $product_name Woo product title (for address).
	 * @return array{description: string, excerpt: string}
	 */
	public static function build( array $strain, $product_name = '' ) {
		$strain_name = self::text( $strain['name'] ?? '' );
		$type        = self::text( $strain['type'] ?? '' );
		$short       = self::text( $strain['short_description'] ?? '' );
		$product     = self::text( $product_name );

		$display = '' !== $strain_name ? $strain_name : $product;
		if ( '' === $display ) {
			$display = 'This strain';
		}

		$paras = array();

		$lead = self::lead_sentence( $display, $type, $strain );
		if ( '' !== $lead ) {
			$paras[] = $lead;
		}

		if ( '' !== $short && ! self::same_sentence( $short, $lead ) ) {
			$paras[] = $short;
		}

		$profile = self::profile_sentence( $strain );
		if ( '' !== $profile ) {
			$paras[] = $profile;
		}

		if ( array() === $paras ) {
			$paras[] = sprintf(
				'%s is listed in the DabDash strain library. Ask the shop for the latest lab results and availability.',
				$display
			);
		}

		$html_parts = array();
		foreach ( $paras as $para ) {
			$html_parts[] = '<p>' . self::esc_html_compat( $para ) . '</p>';
		}

		$description = implode( "\n", $html_parts );
		$excerpt     = self::excerpt_from( '' !== $short ? $short : $paras[0], $display, $type );

		return array(
			'description' => $description,
			'excerpt'     => $excerpt,
		);
	}

	/**
	 * Opening sentence (name + type + indica/sativa split).
	 *
	 * @param string               $display Strain or product name.
	 * @param string               $type    Strain type.
	 * @param array<string, mixed> $strain  Strain row.
	 * @return string
	 */
	private static function lead_sentence( $display, $type, array $strain ) {
		$type_label = self::type_label( $type );
		$split      = self::split_clause( $strain );

		if ( '' !== $type_label && '' !== $split ) {
			return sprintf( '%s is a %s cannabis strain (%s).', $display, $type_label, $split );
		}
		if ( '' !== $type_label ) {
			return sprintf( '%s is a %s cannabis strain.', $display, $type_label );
		}
		if ( '' !== $split ) {
			return sprintf( '%s is a cannabis strain (%s).', $display, $split );
		}

		return sprintf( '%s is a cannabis strain from the DabDash library.', $display );
	}

	/**
	 * Cannabinoid range sentence when THC/CBD are present.
	 *
	 * @param array<string, mixed> $strain Strain row.
	 * @return string
	 */
	private static function profile_sentence( array $strain ) {
		$bits = array();

		$thc = self::percent( $strain['thc_percentage'] ?? null );
		$cbd = self::percent( $strain['cbd_percentage'] ?? null );

		if ( null !== $thc ) {
			$bits[] = sprintf( 'about %s%% THC', self::format_number( $thc ) );
		}
		if ( null !== $cbd ) {
			$bits[] = sprintf( 'about %s%% CBD', self::format_number( $cbd ) );
		}

		if ( array() === $bits ) {
			return '';
		}

		return 'Typical lab ranges report ' . self::join_and( $bits ) . '. Always check the batch COA for this product.';
	}

	/**
	 * Indica/sativa percentage clause for the lead sentence.
	 *
	 * @param array<string, mixed> $strain Strain row.
	 * @return string
	 */
	private static function split_clause( array $strain ) {
		$indica = self::percent( $strain['indica_percentage'] ?? null );
		$sativa = self::percent( $strain['sativa_percentage'] ?? null );
		if ( null === $indica && null === $sativa ) {
			return '';
		}

		$parts = array();
		if ( null !== $indica ) {
			$parts[] = self::format_number( $indica ) . '% indica';
		}
		if ( null !== $sativa ) {
			$parts[] = self::format_number( $sativa ) . '% sativa';
		}

		return implode( ' / ', $parts );
	}

	/**
	 * Plain-text excerpt capped near a typical meta description length.
	 *
	 * @param string $body    Preferred body text.
	 * @param string $display Name.
	 * @param string $type    Type.
	 * @return string
	 */
	private static function excerpt_from( $body, $display, $type ) {
		$body = trim( preg_replace( '/\s+/u', ' ', $body ) ?? $body );
		if ( '' === $body ) {
			$type_label = self::type_label( $type );
			$body       = '' !== $type_label
				? sprintf( '%s — %s cannabis strain.', $display, $type_label )
				: sprintf( '%s cannabis strain.', $display );
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $body ) <= 155 ) {
				return $body;
			}
			$cut = mb_substr( $body, 0, 152 );
			$sp  = mb_strrpos( $cut, ' ' );
			if ( false !== $sp && $sp > 80 ) {
				$cut = mb_substr( $cut, 0, $sp );
			}
			return rtrim( $cut, '.,;: ' ) . '…';
		}

		if ( strlen( $body ) <= 155 ) {
			return $body;
		}
		$cut = substr( $body, 0, 152 );
		$sp  = strrpos( $cut, ' ' );
		if ( false !== $sp && $sp > 80 ) {
			$cut = substr( $cut, 0, $sp );
		}
		return rtrim( $cut, '.,;: ' ) . '…';
	}

	/**
	 * Human label for a strain type slug.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	private static function type_label( $type ) {
		$type = strtolower( trim( $type ) );
		$map  = array(
			'indica' => 'indica',
			'sativa' => 'sativa',
			'hybrid' => 'hybrid',
			'cbd'    => 'CBD-forward',
		);

		return $map[ $type ] ?? ( '' !== $type ? $type : '' );
	}

	/**
	 * Parse a non-negative percentage or return null.
	 *
	 * @param mixed $value Candidate percent.
	 * @return float|null
	 */
	private static function percent( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$n = (float) $value;
		if ( $n < 0 ) {
			return null;
		}

		return $n;
	}

	/**
	 * Format a percentage for prose (drop trailing .0).
	 *
	 * @param float $n Number.
	 * @return string
	 */
	private static function format_number( $n ) {
		if ( abs( $n - round( $n ) ) < 0.05 ) {
			return (string) (int) round( $n );
		}

		return rtrim( rtrim( number_format( $n, 1, '.', '' ), '0' ), '.' );
	}

	/**
	 * Join phrases with commas and a final “and”.
	 *
	 * @param string[] $bits Phrases.
	 * @return string
	 */
	private static function join_and( array $bits ) {
		$count = count( $bits );
		if ( 0 === $count ) {
			return '';
		}
		if ( 1 === $count ) {
			return $bits[0];
		}
		if ( 2 === $count ) {
			return $bits[0] . ' and ' . $bits[1];
		}
		$last = array_pop( $bits );

		return implode( ', ', $bits ) . ', and ' . $last;
	}

	/**
	 * Plain text from a mixed field value.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	private static function text( $value ) {
		$value = (string) $value;
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return trim( wp_strip_all_tags( $value ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- unit-test fallback.
		return trim( strip_tags( $value ) );
	}

	/**
	 * Whether two sentences are effectively the same after normalize.
	 *
	 * @param string $a First.
	 * @param string $b Second.
	 * @return bool
	 */
	private static function same_sentence( $a, $b ) {
		$norm = static function ( $s ) {
			$s = strtolower( trim( $s ) );
			$s = preg_replace( '/\s+/u', ' ', $s ) ?? $s;
			return rtrim( $s, '. ' );
		};

		return $norm( $a ) === $norm( $b );
	}

	/**
	 * Escape for HTML without requiring WordPress in unit tests.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function esc_html_compat( $text ) {
		if ( function_exists( 'esc_html' ) ) {
			return esc_html( $text );
		}

		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
