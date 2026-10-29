<?php
// Runs one exit()-ing code path in its own process and prints the resulting state.
require __DIR__ . '/bootstrap.php';
$name = $argv[1];
$args = json_decode( $argv[2] ?? '{}', true ) ?: array();

register_shutdown_function( function () {
	$rows = array_map( function ( $r ) { return (array) $r; }, $GLOBALS['wpdb']->all() );
	echo "\n@@STATE@@" . json_encode( array(
		'redirects' => $GLOBALS['wp']['redirects'],
		'notices'   => $GLOBALS['wp']['notices'],
		'options'   => $GLOBALS['wp']['options'],
		'transients'=> $GLOBALS['wp']['transients'],
		'http_calls'=> $GLOBALS['wp']['http_calls'],
		'cart_added'=> $GLOBALS['wc']->cart->added,
		'cookie'    => $GLOBALS['wc']->session->cookie,
		'session'   => $GLOBALS['wc']->session->data,
		'rows'      => $rows,
	) ) . "\n";
} );

switch ( $name ) {
	case 'restore':
		if ( isset( $args['gmt_offset'] ) ) {
			update_option( 'gmt_offset', $args['gmt_offset'] );
		}
		if ( isset( $args['seed'] ) ) {
			seed_cart( 'orig', $args['seed']['minutes_ago'], $args['seed']['overrides'] ?? array() );
		}
		if ( ! empty( $args['existing_cart'] ) ) {
			WC()->cart->add_to_cart( 5, 1, 0, array() );
			WC()->cart->added = array();
		}
		if ( ! empty( $args['logged_in'] ) ) {
			$GLOBALS['wp']['user']['logged_in'] = true;
		}
		$_GET['cartrenew_restore'] = $args['token'];
		CartRenew_WC_Restore::maybe_restore();
		break;

	case 'connect':
		$GLOBALS['wp']['user']['can'] = $args['can'] ?? true;
		$_POST['cartrenew_email']     = $args['email'] ?? '';
		foreach ( $args['http'] ?? array() as $r ) {
			if ( 'error' === $r ) {
				queue_http( new WP_Error( 'http_request_failed', 'timeout' ) );
			} else {
				queue_http( http_response( $r[0], $r[1] ) );
			}
		}
		CartRenew_WC_Settings::handle_connect();
		break;
}
