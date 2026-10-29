<?php
// Integration run: real plugin code <-> real backend route handlers over HTTP.
// Use run.sh (boots the backend harness first).
putenv( 'CARTRENEW_IT=1' );
ini_set( 'error_log', '/dev/null' );
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../framework.php';
require __DIR__ . '/test-flow.php';
exit( run_all_tests( $argv[1] ?? '' ) );
