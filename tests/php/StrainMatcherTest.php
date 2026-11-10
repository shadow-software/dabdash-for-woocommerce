<?php
/**
 * StrainMatcher unit tests.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Tests;

use DabDashSync\Catalog\StrainMatcher;
use DabDashSync\Tests\TestCase as BaseTestCase;

/**
 * @covers \DabDashSync\Catalog\StrainMatcher
 */
final class StrainMatcherTest extends BaseTestCase {

	public function test_normalize_strips_weight_suffix() {
		$this->assertSame( 'Blue Dream', StrainMatcher::normalize_name( 'Blue Dream - 3.5G' ) );
		$this->assertSame( 'Blue Dream', StrainMatcher::normalize_name( 'Blue Dream — 7g' ) );
		$this->assertSame( 'Slurricane', StrainMatcher::normalize_name( 'Slurricane (1 oz)' ) );
		$this->assertSame( 'Candy Cake', StrainMatcher::normalize_name( 'Candy Cake' ) );
	}

	public function test_exact_match_wins() {
		$candidates = array(
			array( 'id' => 2, 'name' => 'Blue Dream OG' ),
			array( 'id' => 1, 'name' => 'Blue Dream' ),
		);

		$pick = StrainMatcher::pick( 'Blue Dream', $candidates );

		$this->assertSame( StrainMatcher::CONFIDENCE_EXACT, $pick['confidence'] );
		$this->assertSame( 1, (int) $pick['strain']['id'] );
	}

	public function test_size_suffix_still_exact_matches_normalized() {
		$candidates = array(
			array( 'id' => 9, 'name' => 'Grape Stomper' ),
		);

		$pick = StrainMatcher::pick( 'Grape Stomper - 3.5G', $candidates );

		$this->assertSame( StrainMatcher::CONFIDENCE_EXACT, $pick['confidence'] );
		$this->assertSame( 9, (int) $pick['strain']['id'] );
		$this->assertSame( 'Grape Stomper', $pick['query'] );
	}

	public function test_partial_prefers_shortest_name() {
		$candidates = array(
			array( 'id' => 3, 'name' => 'Blue Dream Special Cut' ),
			array( 'id' => 4, 'name' => 'Blue' ),
		);

		$pick = StrainMatcher::pick( 'Blue Dream Cherry', $candidates );

		$this->assertSame( StrainMatcher::CONFIDENCE_PARTIAL, $pick['confidence'] );
		$this->assertSame( 4, (int) $pick['strain']['id'] );
	}

	public function test_no_match() {
		$pick = StrainMatcher::pick( 'Totally Fake Strain XYZ', array() );

		$this->assertSame( StrainMatcher::CONFIDENCE_NONE, $pick['confidence'] );
		$this->assertNull( $pick['strain'] );
	}
}
