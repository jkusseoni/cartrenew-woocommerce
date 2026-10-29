<?php
ini_set( "error_log", "/dev/null" );
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/framework.php';
foreach ( glob( __DIR__ . '/test-*.php' ) as $f ) {
	require $f;
}
exit( run_all_tests( $argv[1] ?? '' ) );
