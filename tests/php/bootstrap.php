<?php
/**
 * PHPUnit bootstrap — Composer autoload + plugin constants.
 *
 * @package DabDashSync
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DABDASH_SYNC_VERSION' ) ) {
	define( 'DABDASH_SYNC_VERSION', '1.0.0' );
}
if ( ! defined( 'DABDASH_SYNC_FILE' ) ) {
	define( 'DABDASH_SYNC_FILE', dirname( __DIR__, 2 ) . '/dabdash-sync-for-wordpress.php' );
}
if ( ! defined( 'DABDASH_SYNC_PATH' ) ) {
	define( 'DABDASH_SYNC_PATH', dirname( __DIR__, 2 ) . '/' );
}

spl_autoload_register(
	static function ( $classname ) {
		$prefix = 'DabDashSync\\';
		if ( 0 !== strpos( $classname, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $classname, strlen( $prefix ) ) );
		$file     = DABDASH_SYNC_PATH . 'includes/' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
