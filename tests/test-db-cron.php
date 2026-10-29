<?php
// ---- DB ----
test( 'db: schema has restore_token and dbDelta-safe PRIMARY KEY spacing', function () {
	$GLOBALS['wp_dbdelta_sql'] = array();
	CartRenew_WC_DB::create_table();
	$sql = $GLOBALS['wp_dbdelta_sql'][0];
	assert_contains( 'restore_token VARCHAR(64) NULL', $sql );
	assert_contains( 'PRIMARY KEY  (id)', $sql, 'dbDelta needs two spaces' );
	assert_contains( 'KEY restore_token (restore_token)', $sql );
} );

test( 'db: upsert inserts with 32-char token and tracking status', function () {
	CartRenew_WC_DB::upsert_cart( 'k1', array( 'consent' => 1, 'phone_number' => '919876543210' ) );
	$r = row_by_key( 'k1' );
	assert_same( 'tracking', $r->status );
	assert_same( 1, preg_match( '/^[A-Za-z0-9]{32}$/', $r->restore_token ) );
} );

test( 'db: upsert update keeps token and refreshes last_activity', function () {
	seed_cart( 'k2', 300 );
	$before = row_by_key( 'k2' );
	CartRenew_WC_DB::upsert_cart( 'k2', array( 'cart_total' => 99 ) );
	$after = row_by_key( 'k2' );
	assert_same( $before->restore_token, $after->restore_token );
	assert_true( $after->last_activity > $before->last_activity );
	assert_count( 1, $GLOBALS['wpdb']->all() );
} );

test( 'db: upsert never reverts sent/recovered/opted_out to tracking', function () {
	foreach ( array( 'sent', 'recovered', 'opted_out' ) as $st ) {
		seed_cart( "p_$st", 100, array( 'status' => $st ) );
		CartRenew_WC_DB::upsert_cart( "p_$st", array( 'status' => '', 'cart_total' => 5 ) );
		assert_same( $st, row_by_key( "p_$st" )->status, $st );
	}
} );

test( 'db: ensure_token backfills legacy rows', function () {
	seed_cart( 'old', 100, array( 'restore_token' => null ) );
	$row = row_by_key( 'old' );
	$tok = CartRenew_WC_DB::ensure_token( $row );
	assert_same( 32, strlen( $tok ) );
	assert_same( $tok, row_by_key( 'old' )->restore_token );
	assert_same( 'tokx', CartRenew_WC_DB::ensure_token( (object) array( 'restore_token' => 'tokx', 'id' => 1 ) ) );
} );

test( 'db: get_by_token', function () {
	seed_cart( 'a', 5, array( 'restore_token' => 'AAAAAAAAAAAAAAAAAAAA' ) );
	assert_same( 'a', CartRenew_WC_DB::get_by_token( 'AAAAAAAAAAAAAAAAAAAA' )->cart_key );
	assert_null( CartRenew_WC_DB::get_by_token( 'nope' ) );
} );

// ---- Timezone regression (original bug: offset was subtracted) ----
foreach ( array( 5.5, -5, 0, 13, -11 ) as $off ) {
	test( "timezone: cart idle 30 min is abandoned, 5 min is not (gmt_offset $off)", function () use ( $off ) {
		update_option( 'gmt_offset', $off );
		seed_cart( 'idle30', 30 );
		seed_cart( 'idle5', 5 );
		$keys = array_map( function ( $c ) { return $c->cart_key; }, CartRenew_WC_DB::get_abandoned_carts( 20 ) );
		assert_same( array( 'idle30' ), $keys );
	} );

	test( "timezone: carts older than 2 days are skipped (gmt_offset $off)", function () use ( $off ) {
		update_option( 'gmt_offset', $off );
		seed_cart( 'ancient', 60 * 49 );
		seed_cart( 'recent', 60 * 47 );
		$keys = array_map( function ( $c ) { return $c->cart_key; }, CartRenew_WC_DB::get_abandoned_carts( 20 ) );
		assert_same( array( 'recent' ), $keys );
	} );
}

test( 'timezone: local_cutoff adds the offset', function () {
	update_option( 'gmt_offset', 5.5 );
	$expected = gmdate( 'Y-m-d H:i:s', time() + 19800 - 600 );
	assert_within( strtotime( $expected ), strtotime( CartRenew_WC_DB::local_cutoff( 600 ) ), 2 );
} );

test( 'db: only tracking + consent + phone carts are eligible', function () {
	seed_cart( 'ok', 40 );
	seed_cart( 'noconsent', 40, array( 'consent' => 0 ) );
	seed_cart( 'nophone', 40, array( 'phone_number' => null ) );
	seed_cart( 'emptyphone', 40, array( 'phone_number' => '' ) );
	seed_cart( 'sent', 40, array( 'status' => 'sent' ) );
	seed_cart( 'failed', 40, array( 'status' => 'send_failed' ) );
	seed_cart( 'rec', 40, array( 'status' => 'recovered' ) );
	$keys = array_map( function ( $c ) { return $c->cart_key; }, CartRenew_WC_DB::get_abandoned_carts( 20 ) );
	assert_same( array( 'ok' ), $keys );
} );

test( 'db: abandoned carts ordered oldest first and limited', function () {
	seed_cart( 'b', 60 );
	seed_cart( 'a', 120 );
	seed_cart( 'c', 30 );
	$keys = array_map( function ( $c ) { return $c->cart_key; }, CartRenew_WC_DB::get_abandoned_carts( 20, 2 ) );
	assert_same( array( 'a', 'b' ), $keys );
} );

// ---- Cron ----
test( 'cron: disabled -> nothing sent', function () {
	configure_store();
	$s = get_option( 'cartrenew_wc_settings' ); $s['enabled'] = 0; update_option( 'cartrenew_wc_settings', $s );
	seed_cart( 'x', 60 );
	CartRenew_WC_Cron::run();
	assert_count( 0, $GLOBALS['wp']['http_calls'] );
} );

test( 'cron: not connected -> cart stays tracking (not burned as failed)', function () {
	seed_cart( 'x', 60 );
	CartRenew_WC_Cron::run();
	assert_count( 0, $GLOBALS['wp']['http_calls'] );
	assert_same( 'tracking', row_by_key( 'x' )->status );
} );

test( 'cron: success marks sent with sent_at', function () {
	configure_store();
	seed_cart( 'x', 60 );
	queue_http( http_response( 200 ) );
	CartRenew_WC_Cron::run();
	$r = row_by_key( 'x' );
	assert_same( 'sent', $r->status );
	assert_true( null !== $r->sent_at );
} );

test( 'cron: API error marks send_failed and continues with next cart', function () {
	configure_store();
	seed_cart( 'x', 90 );
	seed_cart( 'y', 60 );
	queue_http( http_response( 500 ), http_response( 200 ) );
	CartRenew_WC_Cron::run();
	assert_same( 'send_failed', row_by_key( 'x' )->status );
	assert_same( 'sent', row_by_key( 'y' )->status );
} );

test( 'cron: network WP_Error marks send_failed', function () {
	configure_store();
	seed_cart( 'x', 60 );
	queue_http( new WP_Error( 'http_request_failed', 'timeout' ) );
	CartRenew_WC_Cron::run();
	assert_same( 'send_failed', row_by_key( 'x' )->status );
} );

test( 'cron: 402 sets limit flag (6h), leaves carts untouched, stops loop', function () {
	configure_store();
	seed_cart( 'x', 90 );
	seed_cart( 'y', 60 );
	queue_http( http_response( 402 ) );
	CartRenew_WC_Cron::run();
	assert_count( 1, $GLOBALS['wp']['http_calls'] );
	assert_same( 'tracking', row_by_key( 'x' )->status );
	assert_same( 'tracking', row_by_key( 'y' )->status );
	assert_same( 1, get_transient( CartRenew_WC_Settings::LIMIT_FLAG ) );
	assert_same( 6 * HOUR_IN_SECONDS, $GLOBALS['wp']['transient_ttl'][ CartRenew_WC_Settings::LIMIT_FLAG ] );
} );

test( 'cron: limit flag set -> no API calls until lifted', function () {
	configure_store();
	seed_cart( 'x', 60 );
	set_transient( CartRenew_WC_Settings::LIMIT_FLAG, 1, 100 );
	CartRenew_WC_Cron::run();
	assert_count( 0, $GLOBALS['wp']['http_calls'] );
	delete_transient( CartRenew_WC_Settings::LIMIT_FLAG );
	queue_http( http_response( 200 ) );
	CartRenew_WC_Cron::run();
	assert_same( 'sent', row_by_key( 'x' )->status );
} );

test( 'cron: a sent cart is never sent twice', function () {
	configure_store();
	seed_cart( 'x', 60 );
	CartRenew_WC_Cron::run();
	CartRenew_WC_Cron::run();
	assert_count( 1, $GLOBALS['wp']['http_calls'] );
} );

test( 'cron: respects configured wait minutes', function () {
	configure_store();
	$s = get_option( 'cartrenew_wc_settings' ); $s['abandon_minutes'] = 60; update_option( 'cartrenew_wc_settings', $s );
	seed_cart( 'x', 45 );
	CartRenew_WC_Cron::run();
	assert_count( 0, $GLOBALS['wp']['http_calls'] );
} );
