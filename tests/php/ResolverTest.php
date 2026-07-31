<?php
/**
 * Conflict-resolution tests.
 *
 * These are the tests that matter most in the plugin. A sync bug here does not
 * throw an error — it silently re-subscribes someone who unsubscribed, or lets a
 * checkout form forge an ID verification. Both are invisible until they are a
 * legal problem, so the rules are pinned down here explicitly.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Tests;

use DabDashSync\Sync\FieldMap;
use DabDashSync\Sync\Resolver;
use DabDashSync\Tests\TestCase as BaseTestCase;

/**
 * @covers \DabDashSync\Sync\Resolver
 * @covers \DabDashSync\Sync\FieldMap
 */
final class ResolverTest extends BaseTestCase {

	// ---------------------------------------------------------------------
	// Consent — the ratchet.
	// ---------------------------------------------------------------------

	/**
	 * The core scenario: customer unsubscribes in WooCommerce while DabDash still
	 * says opted-in. The opt-out must survive, and must be proposed upward.
	 */
	public function test_local_opt_out_beats_stale_remote_opt_in() {
		$result = Resolver::mergeConsent( false, true );

		$this->assertTrue( $result['value'], 'A local opt-out must never be overwritten by a remote opt-in.' );
		$this->assertTrue( $result['propose'], 'The opt-out is new information for DabDash and must be pushed.' );
	}

	/**
	 * The mirror image: DabDash holds the opt-out, WordPress does not. The
	 * opt-out still wins, but there is nothing to propose — DabDash already knows.
	 */
	public function test_remote_opt_out_beats_local_opt_in_without_proposing() {
		$result = Resolver::mergeConsent( true, false );

		$this->assertTrue( $result['value'] );
		$this->assertFalse( $result['propose'], 'DabDash already holds this; pushing it back is a wasted write.' );
	}

	/**
	 * Un-suppressing is never automatic. Both sides opted-in stays opted-in, but
	 * that is the ONLY way a false survives.
	 */
	public function test_both_opted_in_stays_opted_in() {
		$result = Resolver::mergeConsent( false, false );

		$this->assertFalse( $result['value'] );
		$this->assertFalse( $result['propose'] );
	}

	public function test_both_opted_out_stays_opted_out() {
		$result = Resolver::mergeConsent( true, true );

		$this->assertTrue( $result['value'] );
		$this->assertFalse( $result['propose'] );
	}

	/**
	 * Recency must be irrelevant to consent. mergeConsent takes no timestamps at
	 * all, which is the structural guarantee that nobody can later "fix" it into
	 * last-write-wins.
	 */
	public function test_consent_merge_is_not_time_dependent() {
		$reflection = new \ReflectionMethod( Resolver::class, 'mergeConsent' );

		$this->assertSame(
			2,
			$reflection->getNumberOfParameters(),
			'mergeConsent must take only the two flags — adding a timestamp would reintroduce last-write-wins.'
		);
	}

	// ---------------------------------------------------------------------
	// Canonical — DabDash is the authority.
	// ---------------------------------------------------------------------

	public function test_canonical_always_takes_the_remote_value() {
		$this->assertSame( '2026-07-01 10:00:00', Resolver::resolveCanonical( '2026-07-01 10:00:00' ) );
		$this->assertNull( Resolver::resolveCanonical( null ), 'Clearing a verification remotely must clear it locally.' );
	}

	/**
	 * The security property: no canonical field may ever appear in an outbound
	 * payload, no matter what a caller passes in.
	 */
	public function test_canonical_fields_can_never_be_pushed() {
		$forged = array(
			'id_verified_at'         => '2026-07-01 10:00:00',
			'loyalty_points'         => 999999,
			'medical_record_path'    => '/etc/passwd',
			'medical_patient_number' => 'MP-1',
			'date_of_birth'          => '1990-01-01',
			'email'                  => 'real@example.test',
		);

		list( $filtered, $rejected ) = FieldMap::filterOutbound( $forged );

		$this->assertSame( array( 'email' => 'real@example.test' ), $filtered );
		$this->assertContains( 'id_verified_at', $rejected );
		$this->assertContains( 'loyalty_points', $rejected );
		$this->assertContains( 'medical_record_path', $rejected );
		$this->assertContains( 'date_of_birth', $rejected );
	}

	/**
	 * Unknown fields fail closed. When DabDash adds a column, it must not start
	 * flowing into WordPress just because the API returned it.
	 */
	public function test_unknown_fields_are_never_synced() {
		$this->assertSame( FieldMap::NEVER, FieldMap::classify( 'some_new_column_added_next_year' ) );
		$this->assertNull( FieldMap::destination( 'some_new_column_added_next_year' ) );
	}

	public function test_sensitive_fields_have_no_wordpress_destination() {
		foreach ( array( 'medical_record_path', 'medical_patient_number', 'id_image_path', 'date_of_birth', 'password' ) as $field ) {
			$this->assertSame( FieldMap::NEVER, FieldMap::classify( $field ), $field . ' must never be mirrored.' );
			$this->assertNull( FieldMap::destination( $field ), $field . ' must have no WP destination.' );
		}
	}

	// ---------------------------------------------------------------------
	// Contact — DabDash wins ties.
	// ---------------------------------------------------------------------

	public function test_contact_prefers_remote_when_timestamps_are_missing() {
		$result = Resolver::resolveContact( 'Remote Name', 'Local Name' );

		$this->assertSame( 'Remote Name', $result['value'], 'Without proof the local edit is newer, canonical wins.' );
		$this->assertFalse( $result['propose'] );
	}

	public function test_contact_proposes_a_provably_newer_local_edit() {
		$result = Resolver::resolveContact( 'Old Name', 'New Name', 1000, 2000 );

		$this->assertSame( 'New Name', $result['value'] );
		$this->assertTrue( $result['propose'] );
	}

	public function test_contact_remote_wins_a_tie() {
		$result = Resolver::resolveContact( 'Remote', 'Local', 1500, 1500 );

		$this->assertSame( 'Remote', $result['value'], 'Equal timestamps must resolve to the canonical side.' );
		$this->assertFalse( $result['propose'] );
	}

	public function test_identical_values_propose_nothing() {
		$result = Resolver::resolveContact( 'Same', 'Same', 1000, 2000 );

		$this->assertFalse( $result['propose'], 'An unchanged value must not generate a write.' );
	}

	// ---------------------------------------------------------------------
	// Deletion — a one-way door.
	// ---------------------------------------------------------------------

	public function test_a_deletion_request_never_clears() {
		$this->assertSame(
			'2026-07-01 10:00:00',
			Resolver::resolveDeletion( null, '2026-07-01 10:00:00' ),
			'A cleared remote value is absence of information, not a retraction.'
		);
	}

	public function test_a_deletion_request_propagates_inward() {
		$this->assertSame( '2026-07-02 09:00:00', Resolver::resolveDeletion( '2026-07-02 09:00:00', null ) );
	}

	// ---------------------------------------------------------------------
	// Equivalence — the anti-flapping guarantee.
	// ---------------------------------------------------------------------

	/**
	 * loyalty_points arrives as int from JSON and as a numeric string from
	 * get_user_meta. If those compared as different, every cycle would push.
	 */
	public function test_numeric_string_and_int_are_equivalent() {
		$this->assertTrue( Resolver::equivalent( 5, '5' ) );
		$this->assertTrue( Resolver::equivalent( 0, '0' ) );
		$this->assertFalse( Resolver::equivalent( 5, '6' ) );
	}

	public function test_booleans_round_trip_through_meta_storage() {
		$this->assertTrue( Resolver::equivalent( true, '1' ) );
		$this->assertTrue( Resolver::equivalent( false, '' ) );
		$this->assertFalse( Resolver::equivalent( true, '' ) );
	}

	public function test_null_and_empty_string_are_equivalent() {
		$this->assertTrue( Resolver::equivalent( null, '' ), 'get_user_meta returns "" where the API sends null.' );
		$this->assertTrue( Resolver::equivalent( null, null ) );
	}

	public function test_distinct_strings_are_not_equivalent() {
		$this->assertFalse( Resolver::equivalent( 'a@example.test', 'b@example.test' ) );
	}
}
