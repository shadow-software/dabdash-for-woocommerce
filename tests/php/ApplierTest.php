<?php
/**
 * Applier plan tests — pure FieldMap/Resolver orchestration.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Tests;

use DabDashSync\Sync\Applier;
use DabDashSync\Tests\TestCase as BaseTestCase;

/**
 * @covers \DabDashSync\Sync\Applier
 */
final class ApplierTest extends BaseTestCase {

	/**
	 * Local opt-out must survive a remote opt-in and be proposed upward.
	 *
	 * When WordPress already holds the suppression there is nothing to write
	 * locally — the important part is proposing it to DabDash.
	 */
	public function test_consent_opt_out_is_written_and_proposed() {
		$plan = Applier::plan(
			array(
				'email_opt_out' => false,
			),
			array(
				'email_opt_out' => true,
			)
		);

		$this->assertArrayNotHasKey( 'email_opt_out', $plan['writes'], 'Local already holds the opt-out.' );
		$this->assertTrue( $plan['proposes']['email_opt_out'] );
	}

	/**
	 * A remote opt-out that WordPress lacks must be written locally.
	 */
	public function test_remote_consent_opt_out_is_written_locally() {
		$plan = Applier::plan(
			array(
				'email_opt_out' => true,
			),
			array(
				'email_opt_out' => false,
			)
		);

		$this->assertTrue( $plan['writes']['email_opt_out'] );
		$this->assertArrayNotHasKey( 'email_opt_out', $plan['proposes'] );
	}

	/**
	 * Canonical remote values overwrite local without proposing.
	 */
	public function test_canonical_writes_without_proposing() {
		$plan = Applier::plan(
			array(
				'loyalty_points' => 42,
				'id_verified_at' => '2026-07-01 10:00:00',
			),
			array(
				'loyalty_points' => 0,
				'id_verified_at' => null,
			)
		);

		$this->assertSame( 42, $plan['writes']['loyalty_points'] );
		$this->assertSame( '2026-07-01 10:00:00', $plan['writes']['id_verified_at'] );
		$this->assertArrayNotHasKey( 'loyalty_points', $plan['proposes'] );
		$this->assertArrayNotHasKey( 'id_verified_at', $plan['proposes'] );
	}

	/**
	 * NEVER fields must never appear in writes or proposes.
	 */
	public function test_never_fields_are_ignored_even_if_present() {
		$plan = Applier::plan(
			array(
				'date_of_birth'          => '1990-01-01',
				'medical_patient_number' => 'MP-1',
				'password'               => 'secret',
				'email'                  => 'a@example.test',
			),
			array(
				'email' => 'b@example.test',
			)
		);

		$this->assertArrayNotHasKey( 'date_of_birth', $plan['writes'] );
		$this->assertArrayNotHasKey( 'medical_patient_number', $plan['writes'] );
		$this->assertArrayNotHasKey( 'password', $plan['writes'] );
		$this->assertArrayNotHasKey( 'date_of_birth', $plan['proposes'] );
		$this->assertSame( 'a@example.test', $plan['writes']['email'] );
	}

	/**
	 * Contact proposes only when local is provably newer.
	 *
	 * The local value is already correct on WP, so there is no local write —
	 * only an upward proposal.
	 */
	public function test_contact_proposes_newer_local_edit() {
		$plan = Applier::plan(
			array(
				'name' => 'Remote',
			),
			array(
				'name' => 'Local',
			),
			1000,
			2000
		);

		$this->assertArrayNotHasKey( 'name', $plan['writes'] );
		$this->assertSame( 'Local', $plan['proposes']['name'] );
	}

	/**
	 * Without timestamps, contact defaults to remote and does not propose.
	 */
	public function test_contact_defaults_to_remote_without_timestamps() {
		$plan = Applier::plan(
			array(
				'name' => 'Remote',
			),
			array(
				'name' => 'Local',
			)
		);

		$this->assertSame( 'Remote', $plan['writes']['name'] );
		$this->assertArrayNotHasKey( 'name', $plan['proposes'] );
	}

	/**
	 * Equivalent values produce no writes (anti-flapping).
	 */
	public function test_equivalent_values_produce_no_writes() {
		$plan = Applier::plan(
			array(
				'loyalty_points' => 5,
				'name'           => 'Same',
				'email_opt_out'  => false,
			),
			array(
				'loyalty_points' => '5',
				'name'           => 'Same',
				'email_opt_out'  => false,
			)
		);

		$this->assertSame( array(), $plan['writes'] );
		$this->assertSame( array(), $plan['proposes'] );
	}

	/**
	 * A local deletion request is never cleared by a null remote.
	 */
	public function test_deletion_request_never_clears() {
		$plan = Applier::plan(
			array(
				'deletion_requested_at' => null,
			),
			array(
				'deletion_requested_at' => '2026-07-01 10:00:00',
			)
		);

		$this->assertArrayNotHasKey( 'deletion_requested_at', $plan['writes'] );
	}
}
