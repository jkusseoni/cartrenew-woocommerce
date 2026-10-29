<?php
$TOK = 'RESTORETOKEN1234567890abcdef12';
$cart = 'https://shop.example/cart/';
$checkout = 'https://shop.example/checkout/';

test( 'restore: url_for builds cart link', function () {
	assert_same( 'https://shop.example/cart/?cartrenew_restore=abc', CartRenew_WC_Restore::url_for( 'abc' ) );
} );

test( 'restore: no query arg -> nothing happens (in-process)', function () {
	CartRenew_WC_Restore::maybe_restore();
	assert_count( 0, $GLOBALS['wp']['redirects'] );
} );

test( 'restore: empty cart is rebuilt with variations and sent to checkout', function () use ( $TOK, $checkout ) {
	$s = run_scenario( 'restore', array( 'token' => $TOK, 'seed' => array( 'minutes_ago' => 60, 'overrides' => array( 'restore_token' => $TOK, 'status' => 'sent' ) ) ) );
	assert_same( array( $checkout ), $s['redirects'] );
	assert_same( array( array( 11, 2, 12, array( 'attribute_size' => 'M' ) ) ), $s['cart_added'] );
	assert_same( 'orig', $s['session']['cartrenew_restored_key'] );
	assert_true( $s['cookie'], 'guest session cookie set' );
} );

test( 'restore: shopper who already has items is not overwritten', function () use ( $TOK, $checkout ) {
	$s = run_scenario( 'restore', array( 'token' => $TOK, 'existing_cart' => true, 'seed' => array( 'minutes_ago' => 60, 'overrides' => array( 'restore_token' => $TOK ) ) ) );
	assert_same( array( $checkout ), $s['redirects'] );
	assert_count( 0, $s['cart_added'] );
} );

test( 'restore: unknown token -> cart page, nothing added', function () use ( $cart ) {
	$s = run_scenario( 'restore', array( 'token' => 'UNKNOWNTOKEN1234567890' ) );
	assert_same( array( $cart ), $s['redirects'] );
	assert_count( 0, $s['cart_added'] );
} );

test( 'restore: malformed token (SQL-ish / too short) -> cart page', function () use ( $cart ) {
	foreach ( array( "x' OR '1'='1", 'short', str_repeat( 'a', 65 ) ) as $t ) {
		$s = run_scenario( 'restore', array( 'token' => $t ) );
		assert_same( array( $cart ), $s['redirects'], $t );
	}
} );

test( 'restore: already recovered order is never restored', function () use ( $TOK, $cart ) {
	$s = run_scenario( 'restore', array( 'token' => $TOK, 'seed' => array( 'minutes_ago' => 60, 'overrides' => array( 'restore_token' => $TOK, 'status' => 'recovered' ) ) ) );
	assert_same( array( $cart ), $s['redirects'] );
	assert_count( 0, $s['cart_added'] );
} );

test( 'restore: link expires after 7 days (and works just before)', function () use ( $TOK, $cart, $checkout ) {
	$old = run_scenario( 'restore', array( 'token' => $TOK, 'seed' => array( 'minutes_ago' => 60 * 24 * 8, 'overrides' => array( 'restore_token' => $TOK ) ) ) );
	assert_same( array( $cart ), $old['redirects'] );
	$ok = run_scenario( 'restore', array( 'token' => $TOK, 'seed' => array( 'minutes_ago' => 60 * 24 * 6, 'overrides' => array( 'restore_token' => $TOK ) ) ) );
	assert_same( array( $checkout ), $ok['redirects'] );
} );

test( 'restore: expiry is timezone-correct (gmt_offset +5.5 and -8)', function () use ( $TOK, $cart, $checkout ) {
	foreach ( array( 5.5, -8 ) as $off ) {
		$ok = run_scenario( 'restore', array( 'gmt_offset' => $off, 'token' => $TOK, 'seed' => array( 'minutes_ago' => 60 * 24 * 6, 'overrides' => array( 'restore_token' => $TOK ) ) ) );
		assert_same( array( $checkout ), $ok['redirects'], "fresh $off" );
		$old = run_scenario( 'restore', array( 'gmt_offset' => $off, 'token' => $TOK, 'seed' => array( 'minutes_ago' => 60 * 24 * 8, 'overrides' => array( 'restore_token' => $TOK ) ) ) );
		assert_same( array( $cart ), $old['redirects'], "stale $off" );
	}
} );

test( 'restore: all items unavailable -> notice + cart page', function () use ( $TOK, $cart ) {
	$items = json_encode( array( array( 'name' => 'Gone', 'quantity' => 1, 'price' => '1', 'product_id' => 999 ) ) );
	$s = run_scenario( 'restore', array( 'token' => $TOK, 'seed' => array( 'minutes_ago' => 60, 'overrides' => array( 'restore_token' => $TOK, 'cart_contents' => $items ) ) ) );
	assert_same( array( $cart ), $s['redirects'] );
	assert_count( 1, $s['notices'] );
	assert_false( isset( $s['session']['cartrenew_restored_key'] ) );
} );

test( 'restore: corrupt cart_contents -> notice + cart page, no crash', function () use ( $TOK, $cart ) {
	$s = run_scenario( 'restore', array( 'token' => $TOK, 'seed' => array( 'minutes_ago' => 60, 'overrides' => array( 'restore_token' => $TOK, 'cart_contents' => 'not json' ) ) ) );
	assert_same( array( $cart ), $s['redirects'] );
	assert_count( 1, $s['notices'] );
} );

test( 'restore: logged-in shopper gets no guest cookie call', function () use ( $TOK ) {
	$s = run_scenario( 'restore', array( 'token' => $TOK, 'logged_in' => true, 'seed' => array( 'minutes_ago' => 60, 'overrides' => array( 'restore_token' => $TOK ) ) ) );
	assert_false( $s['cookie'] );
} );

// ---- One-click connect ----
test( 'connect: 200 saves store_id + api_key, clears limit flag, success flash', function () {
	$s = run_scenario( 'connect', array( 'email' => 'me@shop.com', 'http' => array( array( 200, array( 'store_id' => 'S-9', 'api_key' => 'K-9' ) ) ) ) );
	$set = $s['options']['cartrenew_wc_settings'];
	assert_same( 'S-9', $set['store_id'] );
	assert_same( 'K-9', $set['api_key'] );
	assert_same( 'success', $s['transients']['cartrenew_wc_flash_1']['type'] );
	$body = json_decode( $s['http_calls'][0]['args']['body'], true );
	assert_same( 'me@shop.com', $body['email'] );
	assert_same( 'https://shop.example', $body['site_url'] );
	assert_contains( '/register-store', $s['http_calls'][0]['url'] );
} );

test( 'connect: 409 saves only store_id with warning', function () {
	$s = run_scenario( 'connect', array( 'email' => 'me@shop.com', 'http' => array( array( 409, array( 'store_id' => 'S-1' ) ) ) ) );
	$set = $s['options']['cartrenew_wc_settings'];
	assert_same( 'S-1', $set['store_id'] );
	assert_same( '', $set['api_key'] ?? '' );
	assert_same( 'warning', $s['transients']['cartrenew_wc_flash_1']['type'] );
} );

test( 'connect: 500 / network error -> error flash, nothing saved', function () {
	foreach ( array( array( array( 500, '{}' ) ), array( 'error' ) ) as $http ) {
		$s = run_scenario( 'connect', array( 'email' => 'me@shop.com', 'http' => $http ) );
		assert_same( 'error', $s['transients']['cartrenew_wc_flash_1']['type'] );
		assert_false( isset( $s['options']['cartrenew_wc_settings'] ) );
	}
} );

test( 'connect: 200 without key is treated as failure', function () {
	$s = run_scenario( 'connect', array( 'email' => 'me@shop.com', 'http' => array( array( 200, array( 'store_id' => 'S' ) ) ) ) );
	assert_same( 'error', $s['transients']['cartrenew_wc_flash_1']['type'] );
	assert_false( isset( $s['options']['cartrenew_wc_settings'] ) );
} );

test( 'connect: invalid email -> no HTTP call', function () {
	$s = run_scenario( 'connect', array( 'email' => 'not-an-email' ) );
	assert_count( 0, $s['http_calls'] );
	assert_same( 'error', $s['transients']['cartrenew_wc_flash_1']['type'] );
} );

test( 'connect: user without manage_woocommerce is refused before any call', function () {
	$s = run_scenario( 'connect', array( 'email' => 'me@shop.com', 'can' => false ) );
	assert_count( 0, $s['http_calls'] );
	assert_false( isset( $s['options']['cartrenew_wc_settings'] ) );
} );
