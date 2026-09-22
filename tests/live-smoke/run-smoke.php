<?php
/**
 * Thin live smoke — runs inside WordPress via `wp eval-file`.
 *
 * Proves SdkFactory + customerList against the configured tenant API.
 * Does not create customers (read-only ping + one pull page when sync enabled).
 *
 * @package DabDashSync
 */

declare(strict_types=1);

use DabDashSync\Api\SdkFactory;
use DabDashSync\Settings;
use DabDashSync\Sync\Puller;
use ShadowSoftware\DabDash\Model\CustomerListRequest;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Must run inside WordPress (wp eval-file).\n" );
	exit( 2 );
}

$fail = static function ( string $msg ): void {
	fwrite( STDERR, "SMOKE FAIL: {$msg}\n" );
	exit( 1 );
};

if ( ! is_plugin_active( 'dabdash-for-woocommerce/dabdash-for-woocommerce.php' ) ) {
	$fail( 'dabdash-for-woocommerce is not active' );
}

if ( ! SdkFactory::is_configured() ) {
	$fail( 'plugin API base + token not configured' );
}

$base = Settings::api_base();
if ( ! Settings::is_allowed_api_base( $base ) ) {
	$fail( 'API base URL is not an allowed DabDash host: ' . $base );
}

echo "API base: {$base}\n";

try {
	$api  = SdkFactory::customers();
	$req  = new CustomerListRequest();
	$req->setLimit( 1 );
	$resp = $api->customerList( $req );
	$rows = $resp->getCustomers();
	$count = is_array( $rows ) ? count( $rows ) : 0;
	echo "✓ customerList OK ({$count} row(s))\n";
} catch ( Throwable $e ) {
	$fail( 'customerList: ' . $e->getMessage() );
}

if ( Settings::sync_enabled() && class_exists( Puller::class ) ) {
	try {
		( new Puller() )->pull();
		echo "✓ pull completed\n";
	} catch ( Throwable $e ) {
		$fail( 'pull: ' . $e->getMessage() );
	}
} else {
	echo "ℹ sync disabled — skipped pull run\n";
}

echo "SMOKE PASS\n";
exit( 0 );
