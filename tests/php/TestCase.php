<?php
/**
 * Base test case with Brain Monkey lifecycle.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;

/**
 * Shared PHPUnit base.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
