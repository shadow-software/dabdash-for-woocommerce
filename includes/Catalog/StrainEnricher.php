<?php
/**
 * One-shot WooCommerce product ← DabDash strain enrich job.
 *
 * Not a recurring Action Scheduler sync. Admin triggers a dry-run or apply;
 * matched products get long description + excerpt written from strain_lookup.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Catalog;

use DabDashSync\Api\SdkFactory;
use ShadowSoftware\DabDash\Api\CatalogApi;
use ShadowSoftware\DabDash\ApiException;
use ShadowSoftware\DabDash\Model\StrainLookupRequest;
use ShadowSoftware\DabDash\Model\StrainLookupResponseStrainsInner;

defined( 'ABSPATH' ) || exit;

/**
 * Scans Woo products, matches strains via CatalogApi::strainLookup, writes copy.
 */
final class StrainEnricher {

	public const META_STRAIN_ID      = '_dabdash_woo_strain_id';
	public const META_STRAIN_NAME    = '_dabdash_woo_strain_name';
	public const META_CONFIDENCE     = '_dabdash_woo_strain_confidence';
	public const META_ENRICHED_AT    = '_dabdash_woo_strain_enriched_at';
	public const META_ENRICH_VERSION = '_dabdash_woo_strain_enrich_version';

	/**
	 * Catalog API (null until resolved from SdkFactory).
	 *
	 * @var CatalogApi|null
	 */
	private $api;

	/**
	 * Whether an API client was injected (tests) — skip settings gate.
	 *
	 * @var bool
	 */
	private $api_injected;

	/**
	 * In-run lookup cache keyed by query string.
	 *
	 * @var array<string, list<array<string, mixed>>>
	 */
	private $lookup_cache = array();

	/**
	 * Optional CatalogApi for tests; production resolves via SdkFactory.
	 *
	 * @param CatalogApi|null $api Injected for tests.
	 */
	public function __construct( $api = null ) {
		$this->api_injected = $api instanceof CatalogApi;
		$this->api          = $this->api_injected ? $api : null;
	}

	/**
	 * Run the one-shot job.
	 *
	 * @param array<string, mixed> $args Job options: dry_run, overwrite, limit, exact_only, product_ids.
	 * @return array<string, mixed> Summary with scanned/matched/updated/skipped/errors/rows.
	 */
	public function run( array $args = array() ) {
		$dry_run    = ! empty( $args['dry_run'] );
		$overwrite  = ! empty( $args['overwrite'] );
		$exact_only = ! empty( $args['exact_only'] );
		$limit      = isset( $args['limit'] ) ? max( 0, (int) $args['limit'] ) : 0;
		$ids        = isset( $args['product_ids'] ) && is_array( $args['product_ids'] )
			? array_values( array_filter( array_map( 'intval', $args['product_ids'] ) ) )
			: array();

		$summary = array(
			'dry_run' => $dry_run,
			'scanned' => 0,
			'matched' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => 0,
			'rows'    => array(),
		);

		if ( ! $this->api_injected && ! SdkFactory::is_configured() ) {
			++$summary['errors'];
			$summary['rows'][] = array(
				'status'  => 'error',
				'message' => 'DabDash API is not configured.',
			);
			return $summary;
		}

		if ( ! function_exists( 'wc_get_products' ) ) {
			++$summary['errors'];
			$summary['rows'][] = array(
				'status'  => 'error',
				'message' => 'WooCommerce is required for strain enrich.',
			);
			return $summary;
		}

		$query = array(
			'status'  => array( 'publish', 'private' ),
			'limit'   => $limit > 0 ? $limit : -1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'return'  => 'objects',
		);
		if ( array() !== $ids ) {
			$query['include'] = $ids;
			$query['limit']   = count( $ids );
		}

		$products = wc_get_products( $query );
		if ( ! is_array( $products ) ) {
			$products = array();
		}

		foreach ( $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}

			++$summary['scanned'];
			$row               = $this->process_product( $product, $dry_run, $overwrite, $exact_only );
			$summary['rows'][] = $row;

			$status = isset( $row['status'] ) ? (string) $row['status'] : '';
			if ( 'updated' === $status || 'would_update' === $status ) {
				++$summary['matched'];
				++$summary['updated'];
			} elseif ( 'matched_skip' === $status ) {
				++$summary['matched'];
				++$summary['skipped'];
			} elseif ( 'skipped' === $status || 'no_match' === $status ) {
				++$summary['skipped'];
			} elseif ( 'error' === $status ) {
				++$summary['errors'];
			}
		}

		return $summary;
	}

	/**
	 * Match and optionally write copy for one Woo product.
	 *
	 * @param object $product    WC_Product-like.
	 * @param bool   $dry_run    Preview only.
	 * @param bool   $overwrite  Replace existing enrich / non-empty copy.
	 * @param bool   $exact_only Require exact confidence.
	 * @return array<string, mixed>
	 */
	private function process_product( $product, $dry_run, $overwrite, $exact_only ) {
		$id   = (int) $product->get_id();
		$name = method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';

		$base = array(
			'product_id'   => $id,
			'product_name' => $name,
			'status'       => 'skipped',
		);

		if ( '' === trim( $name ) ) {
			$base['message'] = 'Empty product name.';
			return $base;
		}

		$existing_strain = (int) $product->get_meta( self::META_STRAIN_ID, true );
		if ( $existing_strain > 0 && ! $overwrite ) {
			$base['status']    = 'skipped';
			$base['message']   = 'Already enriched (pass overwrite to replace).';
			$base['strain_id'] = $existing_strain;
			return $base;
		}

		try {
			$candidates = $this->lookup_candidates( $name );
		} catch ( ApiException $e ) {
			$base['status']  = 'error';
			$base['message'] = 'strain_lookup failed: ' . $e->getMessage();
			return $base;
		} catch ( \Throwable $e ) {
			$base['status']  = 'error';
			$base['message'] = $e->getMessage();
			return $base;
		}

		$pick = StrainMatcher::pick( $name, $candidates );
		if ( null === $pick['strain'] || StrainMatcher::CONFIDENCE_NONE === $pick['confidence'] ) {
			$base['status']  = 'no_match';
			$base['message'] = 'No DabDash strain matched.';
			$base['query']   = $pick['query'];
			return $base;
		}

		if ( $exact_only && StrainMatcher::CONFIDENCE_EXACT !== $pick['confidence'] ) {
			$base['status']     = 'matched_skip';
			$base['message']    = 'Partial match skipped (exact-only mode).';
			$base['confidence'] = $pick['confidence'];
			$base['strain']     = $this->public_strain( $pick['strain'] );
			$base['query']      = $pick['query'];
			return $base;
		}

		$copy                = StrainCopyBuilder::build( $pick['strain'], $name );
		$base['confidence']  = $pick['confidence'];
		$base['strain']      = $this->public_strain( $pick['strain'] );
		$base['query']       = $pick['query'];
		$base['excerpt']     = $copy['excerpt'];
		$base['description'] = $copy['description'];

		if ( $dry_run ) {
			$base['status']  = 'would_update';
			$base['message'] = 'Dry-run: would write description + excerpt.';
			return $base;
		}

		try {
			$product->set_description( $copy['description'] );
			$product->set_short_description( $copy['excerpt'] );
			$product->update_meta_data( self::META_STRAIN_ID, (int) ( $pick['strain']['id'] ?? 0 ) );
			$product->update_meta_data( self::META_STRAIN_NAME, (string) ( $pick['strain']['name'] ?? '' ) );
			$product->update_meta_data( self::META_CONFIDENCE, (string) $pick['confidence'] );
			$product->update_meta_data( self::META_ENRICHED_AT, gmdate( 'c' ) );
			$product->update_meta_data( self::META_ENRICH_VERSION, DABDASH_WOO_VERSION );
			$product->save();
		} catch ( \Throwable $e ) {
			$base['status']  = 'error';
			$base['message'] = 'Failed to save product: ' . $e->getMessage();
			return $base;
		}

		$base['status']  = 'updated';
		$base['message'] = 'Updated description + excerpt.';
		return $base;
	}

	/**
	 * Lookup candidates for the raw title and size-stripped base name.
	 *
	 * @param string $product_name Product title.
	 * @return list<array<string, mixed>>
	 * @throws ApiException On API failure.
	 */
	private function lookup_candidates( $product_name ) {
		$queries = array_values(
			array_unique(
				array_filter(
					array(
						trim( $product_name ),
						StrainMatcher::normalize_name( $product_name ),
					)
				)
			)
		);

		$merged = array();
		$seen   = array();

		foreach ( $queries as $query ) {
			foreach ( $this->lookup( $query ) as $row ) {
				$id  = isset( $row['id'] ) ? (int) $row['id'] : 0;
				$key = $id > 0 ? 'id:' . $id : 'name:' . strtolower( (string) ( $row['name'] ?? '' ) );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$merged[]     = $row;
			}
		}

		return $merged;
	}

	/**
	 * Call CatalogApi::strainLookup with an in-run cache.
	 *
	 * @param string $query Lookup query.
	 * @return list<array<string, mixed>>
	 * @throws ApiException On API failure.
	 */
	private function lookup( $query ) {
		$query = trim( $query );
		if ( '' === $query ) {
			return array();
		}

		if ( isset( $this->lookup_cache[ $query ] ) ) {
			return $this->lookup_cache[ $query ];
		}

		$request = new StrainLookupRequest();
		$request->setQuery( $query );

		$response = $this->client()->strainLookup( $request );
		$data     = $response->getData();
		$strains  = null !== $data ? $data->getStrains() : null;
		$rows     = array();

		if ( is_array( $strains ) ) {
			foreach ( $strains as $strain ) {
				$row = $this->strain_to_array( $strain );
				if ( null !== $row ) {
					$rows[] = $row;
				}
			}
		}

		$this->lookup_cache[ $query ] = $rows;
		return $rows;
	}

	/**
	 * Resolve the Catalog API client.
	 *
	 * @return CatalogApi
	 */
	private function client() {
		if ( ! ( $this->api instanceof CatalogApi ) ) {
			$this->api = SdkFactory::catalog();
		}

		return $this->api;
	}

	/**
	 * Normalize an SDK strain model (or array) into a plain row.
	 *
	 * @param mixed $strain SDK model or array.
	 * @return array<string, mixed>|null
	 */
	private function strain_to_array( $strain ) {
		if ( $strain instanceof StrainLookupResponseStrainsInner ) {
			return array(
				'id'                => $strain->getId(),
				'name'              => $strain->getName(),
				'slug'              => $strain->getSlug(),
				'type'              => $strain->getType(),
				'thc_percentage'    => $strain->getThcPercentage(),
				'cbd_percentage'    => $strain->getCbdPercentage(),
				'indica_percentage' => $strain->getIndicaPercentage(),
				'sativa_percentage' => $strain->getSativaPercentage(),
				'short_description' => $strain->getShortDescription(),
				'has_hosted_image'  => $strain->getHasHostedImage(),
			);
		}

		if ( is_array( $strain ) ) {
			return $strain;
		}

		return null;
	}

	/**
	 * Public summary fields for the admin result table.
	 *
	 * @param array<string, mixed> $strain Full row.
	 * @return array<string, mixed>
	 */
	private function public_strain( array $strain ) {
		return array(
			'id'   => isset( $strain['id'] ) ? (int) $strain['id'] : 0,
			'name' => isset( $strain['name'] ) ? (string) $strain['name'] : '',
			'type' => isset( $strain['type'] ) ? (string) $strain['type'] : '',
			'slug' => isset( $strain['slug'] ) ? (string) $strain['slug'] : '',
		);
	}
}
