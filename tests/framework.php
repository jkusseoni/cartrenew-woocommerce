<?php
/**
 * Tiny dependency-free test framework (no composer / PHPUnit needed).
 * Usage: php tests/run.php [filter]
 */

$GLOBALS['__tests']   = array();
$GLOBALS['__results'] = array( 'pass' => 0, 'fail' => 0, 'failures' => array() );
$GLOBALS['__current'] = '';

function test( $name, $fn ) {
	$GLOBALS['__tests'][] = array( $name, $fn );
}

class AssertionFailure extends Exception {}

function fail( $message ) {
	throw new AssertionFailure( $message );
}

function export_val( $v ) {
	return is_scalar( $v ) || null === $v ? var_export( $v, true ) : json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
}

function assert_same( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		fail( ( $message ? $message . ': ' : '' ) . 'expected ' . export_val( $expected ) . ', got ' . export_val( $actual ) );
	}
}

function assert_true( $actual, $message = '' ) {
	if ( true !== $actual ) {
		fail( ( $message ? $message . ': ' : '' ) . 'expected true, got ' . export_val( $actual ) );
	}
}

function assert_false( $actual, $message = '' ) {
	if ( false !== $actual ) {
		fail( ( $message ? $message . ': ' : '' ) . 'expected false, got ' . export_val( $actual ) );
	}
}

function assert_null( $actual, $message = '' ) {
	if ( null !== $actual ) {
		fail( ( $message ? $message . ': ' : '' ) . 'expected null, got ' . export_val( $actual ) );
	}
}

function assert_contains( $needle, $haystack, $message = '' ) {
	if ( false === strpos( (string) $haystack, (string) $needle ) ) {
		fail( ( $message ? $message . ': ' : '' ) . 'expected to contain ' . export_val( $needle ) . ' in ' . substr( (string) $haystack, 0, 200 ) );
	}
}

function assert_not_contains( $needle, $haystack, $message = '' ) {
	if ( false !== strpos( (string) $haystack, (string) $needle ) ) {
		fail( ( $message ? $message . ': ' : '' ) . 'expected NOT to contain ' . export_val( $needle ) );
	}
}

function assert_count( $expected, $arr, $message = '' ) {
	assert_same( $expected, is_countable( $arr ) ? count( $arr ) : -1, $message ?: 'count' );
}

function assert_within( $expected, $actual, $tolerance, $message = '' ) {
	if ( abs( $expected - $actual ) > $tolerance ) {
		fail( ( $message ? $message . ': ' : '' ) . "expected {$expected} +/- {$tolerance}, got {$actual}" );
	}
}

function run_all_tests( $filter = '' ) {
	foreach ( $GLOBALS['__tests'] as $t ) {
		list( $name, $fn ) = $t;
		if ( $filter && false === stripos( $name, $filter ) ) {
			continue;
		}
		$GLOBALS['__current'] = $name;
		wp_reset();
		try {
			$fn();
			++$GLOBALS['__results']['pass'];
			echo "  \033[32m✓\033[0m {$name}\n";
		} catch ( Throwable $e ) {
			++$GLOBALS['__results']['fail'];
			$GLOBALS['__results']['failures'][] = array( $name, get_class( $e ) . ': ' . $e->getMessage() );
			echo "  \033[31m✗ {$name}\033[0m\n      " . get_class( $e ) . ': ' . $e->getMessage() . "\n";
		}
	}
	$r = $GLOBALS['__results'];
	echo "\n" . ( $r['fail'] ? "\033[31m" : "\033[32m" ) . "{$r['pass']} passed, {$r['fail']} failed\033[0m\n";
	return $r['fail'] ? 1 : 0;
}

/**
 * Runs a scenario in a separate PHP process (needed for code paths that call
 * exit()). Returns the decoded JSON state printed by the scenario at shutdown.
 */
function run_scenario( $name, $args = array() ) {
	$cmd  = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/scenario.php' ) . ' ' . escapeshellarg( $name ) . ' ' . escapeshellarg( json_encode( $args ) ) . ' 2>&1';
	$out  = shell_exec( $cmd );
	$line = trim( (string) substr( (string) $out, (int) strrpos( (string) $out, "\n@@STATE@@" ) + 1 ) );
	if ( 0 !== strpos( $line, '@@STATE@@' ) ) {
		fail( "scenario '{$name}' produced no state. Output: " . substr( (string) $out, 0, 500 ) );
	}
	return json_decode( substr( $line, strlen( '@@STATE@@' ) ), true );
}
