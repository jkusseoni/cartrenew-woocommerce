<?php
function sent_body() {
	return json_decode( $GLOBALS['wp']['http_calls'][0]['args']['body'], true );
}

test( 'api: not configured -> cartrenew_not_configured, no request', function () {
	seed_cart( 'x', 60 );
	$r = CartRenew_WC_API::send_abandoned_cart( row_by_key( 'x' ) );
	assert_same( 'cartrenew_not_configured', $r->get_error_code() );
	assert_count( 0, $GLOBALS['wp']['http_calls'] );
} );

test( 'api: posts to {base}/abandoned-cart with Bearer key', function () {
	configure_store();
	seed_cart( 'x', 60 );
	assert_true( CartRenew_WC_API::send_abandoned_cart( row_by_key( 'x' ) ) );
	$call = $GLOBALS['wp']['http_calls'][0];
	assert_same( 'https://api.example/api/woocommerce/abandoned-cart', $call['url'] );
	assert_same( 'Bearer KEY-1', $call['args']['headers']['Authorization'] );
} );

test( 'api: payload items stripped to name/quantity/price (no product ids)', function () {
	configure_store();
	seed_cart( 'x', 60 );
	CartRenew_WC_API::send_abandoned_cart( row_by_key( 'x' ) );
	$b = sent_body();
	assert_same( array( array( 'name' => 'Tee', 'quantity' => 2, 'price' => '10' ) ), $b['cart_contents'] );
	assert_same( 'STORE-1', $b['store_id'] );
	assert_same( 'x', $b['cart_key'] );
	assert_same( '919876543210', $b['phone_number'] );
} );

test( 'api: checkout_url is the restore link with the row token', function () {
	configure_store();
	seed_cart( 'x', 60, array( 'restore_token' => 'TOKENTOKENTOKENTOKEN12' ) );
	CartRenew_WC_API::send_abandoned_cart( row_by_key( 'x' ) );
	assert_same( 'https://shop.example/cart/?cartrenew_restore=TOKENTOKENTOKENTOKEN12', sent_body()['checkout_url'] );
} );

test( 'api: legacy row without token gets one generated and used', function () {
	configure_store();
	seed_cart( 'x', 60, array( 'restore_token' => null ) );
	CartRenew_WC_API::send_abandoned_cart( row_by_key( 'x' ) );
	$tok = row_by_key( 'x' )->restore_token;
	assert_same( 32, strlen( $tok ) );
	assert_contains( $tok, sent_body()['checkout_url'] );
} );

test( 'api: HTTP code mapping (402 limit, 4xx/5xx error, 2xx ok)', function () {
	configure_store();
	seed_cart( 'x', 60 );
	$row = row_by_key( 'x' );
	foreach ( array( 402 => 'cartrenew_trial_limit', 401 => 'cartrenew_api_error', 404 => 'cartrenew_api_error', 500 => 'cartrenew_api_error', 422 => 'cartrenew_api_error' ) as $code => $err ) {
		queue_http( http_response( $code ) );
		assert_same( $err, CartRenew_WC_API::send_abandoned_cart( $row )->get_error_code(), (string) $code );
	}
	foreach ( array( 200, 202 ) as $code ) {
		queue_http( http_response( $code ) );
		assert_true( CartRenew_WC_API::send_abandoned_cart( $row ), (string) $code );
	}
} );

test( 'api: transport error is passed through', function () {
	configure_store();
	seed_cart( 'x', 60 );
	queue_http( new WP_Error( 'http_request_failed', 'dns' ) );
	assert_same( 'http_request_failed', CartRenew_WC_API::send_abandoned_cart( row_by_key( 'x' ) )->get_error_code() );
} );
