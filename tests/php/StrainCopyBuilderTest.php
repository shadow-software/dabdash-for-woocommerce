<?php
/**
 * StrainCopyBuilder unit tests.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Tests;

use DabDashSync\Catalog\StrainCopyBuilder;
use DabDashSync\Tests\TestCase as BaseTestCase;

/**
 * @covers \DabDashSync\Catalog\StrainCopyBuilder
 */
final class StrainCopyBuilderTest extends BaseTestCase {

	public function test_builds_readable_description_and_excerpt() {
		$copy = StrainCopyBuilder::build(
			array(
				'name'              => 'Blue Dream',
				'type'              => 'hybrid',
				'thc_percentage'    => 18.5,
				'cbd_percentage'    => 0.2,
				'indica_percentage' => 40,
				'sativa_percentage' => 60,
				'short_description' => 'A balanced hybrid known for gentle euphoria and berry notes.',
			),
			'Blue Dream - 3.5G'
		);

		$this->assertStringContainsString( '<p>', $copy['description'] );
		$this->assertStringContainsString( 'Blue Dream', $copy['description'] );
		$this->assertStringContainsString( 'hybrid', $copy['description'] );
		$this->assertStringContainsString( 'berry notes', $copy['description'] );
		$this->assertStringContainsString( 'THC', $copy['description'] );
		$this->assertStringNotContainsString( 'Blue Dream Blue Dream Blue Dream', $copy['description'] );

		$this->assertNotSame( '', $copy['excerpt'] );
		$this->assertLessThanOrEqual( 160, strlen( $copy['excerpt'] ) );
		$this->assertStringContainsString( 'berry', $copy['excerpt'] );
	}

	public function test_works_without_short_description() {
		$copy = StrainCopyBuilder::build(
			array(
				'name' => 'Slurricane',
				'type' => 'indica',
			)
		);

		$this->assertStringContainsString( 'Slurricane', $copy['description'] );
		$this->assertStringContainsString( 'indica', $copy['description'] );
		$this->assertNotSame( '', $copy['excerpt'] );
	}
}
