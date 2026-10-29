<?php
function ajax( $fn ) {
	try {
		$fn();
	} catch ( WpJsonResponse $r ) {
		return $r;
	}
	fail( 'no JSON response' );
}

// ---- Consent AJAX ----
test( 'consent ajax: stores digits-only phone, name, consent and snapshots the cart', function () {
	configure_store();
	WC()->cart->add_to_cart( 11, 2, 12, array( 'attribute_size' => 'M' ) );
	$_POST = array( 'phone' => '+91 98765-43210', 'name' => 'Asha', 'consent' => 'yes' );
	$r = ajax( array( 'CartRenew_WC_Consent', 'ajax_save_consent' ) );
	assert_true( $r->success );
	$s = WC()->session->data;
	assert_same( '919876543210', $s['cartrenew_phone'] );
	assert_same( 'yes', $s['cartrenew_consent'] );
	$row = row_by_key( 'session_guest123' );
	assert_same( 1, (int) $row->consent );
	assert_same( '919876543210', $row->phone_number );
	assert_same( 'Asha', $row->customer_name );
} );

test( 'consent ajax: invalid phone length is discarded', function () {
	foreach ( array( '1234567', str_repeat( '9', 16 ) ) as $p ) {
		$_POST = array( 'phone' => $p, 'consent' => 'yes' );
		ajax( array( 'CartRenew_WC_Consent', 'ajax_save_consent' ) );
		assert_same( '', WC()->session->data['cartrenew_phone'], $p );
	}
} );

test( 'consent ajax: toggling consent without phone keeps earlier number', function () {
	WC()->session->set( 'cartrenew_phone', '919876543210' );
	$_POST = array( 'consent' => 'no' );
	ajax( array( 'CartRenew_WC_Consent', 'ajax_save_consent' ) );
	assert_same( '919876543210', WC()->session->data['cartrenew_phone'] );
	assert_same( 'no', WC()->session->data['cartrenew_consent'] );
} );

test( 'consent ajax: anything but "yes" means no consent', function () {
	foreach ( array( 'true', '1', 'YES', '' ) as $v ) {
		$_POST = array( 'consent' => $v );
		ajax( array( 'CartRenew_WC_Consent', 'ajax_save_consent' ) );
		assert_same( 'no', WC()->session->data['cartrenew_consent'], $v );
	}
} );

test( 'consent ajax: name is capped at 100 chars', function () {
	$_POST = array( 'name' => str_repeat( 'a', 300 ), 'consent' => 'yes' );
	ajax( array( 'CartRenew_WC_Consent', 'ajax_save_consent' ) );
	assert_same( 100, strlen( WC()->session->data['cartrenew_name'] ) );
} );

test( 'consent form: checkbox is never pre-ticked', function () {
	ob_start();
	CartRenew_WC_Consent::render_form();
	$html = ob_get_clean();
	assert_contains( 'cartrenew_consent', $html );
	assert_not_contains( 'checked', $html );
} );

// ---- Tracker ----
test( 'tracker: snapshot stores product_id/variation for restore', function () {
	configure_store();
	WC()->cart->add_to_cart( 11, 2, 12, array( 'attribute_size' => 'M' ) );
	WC()->cart->total = '25.00';
	CartRenew_WC_Tracker::save_snapshot();
	$row   = row_by_key( 'session_guest123' );
	$items = json_decode( $row->cart_contents, true );
	assert_same( 11, $items[0]['product_id'] );
	assert_same( 12, $items[0]['variation_id'] );
	assert_same( array( 'attribute_size' => 'M' ), $items[0]['variation'] );
	assert_same( 0, (int) $row->consent );
} );

test( 'tracker: empty cart or disabled plugin writes nothing', function () {
	configure_store();
	CartRenew_WC_Tracker::save_snapshot();
	assert_count( 0, $GLOBALS['wpdb']->all() );
	WC()->cart->add_to_cart( 11, 1, 0, array() );
	$s = get_option( 'cartrenew_wc_settings' ); $s['enabled'] = 0; update_option( 'cartrenew_wc_settings', $s );
	CartRenew_WC_Tracker::save_snapshot();
	assert_count( 0, $GLOBALS['wpdb']->all() );
} );

test( 'tracker: logged-in user keyed by user id, name falls back to display name', function () {
	configure_store();
	$GLOBALS['wp']['user']['logged_in'] = true;
	WC()->cart->add_to_cart( 11, 1, 0, array() );
	CartRenew_WC_Tracker::save_snapshot();
	assert_same( 'adminuser', row_by_key( 'user_1' )->customer_name );
} );

test( 'tracker: checkout first name wins over account name', function () {
	configure_store();
	$GLOBALS['wp']['user']['logged_in'] = true;
	WC()->session->set( 'cartrenew_name', 'Riya' );
	WC()->cart->add_to_cart( 11, 1, 0, array() );
	CartRenew_WC_Tracker::save_snapshot();
	assert_same( 'Riya', row_by_key( 'user_1' )->customer_name );
} );

test( 'tracker: order placed marks cart recovered, and restored original too', function () {
	configure_store();
	seed_cart( 'orig', 120, array( 'status' => 'sent' ) );
	seed_cart( 'session_guest123', 5 );
	WC()->session->set( 'cartrenew_restored_key', 'orig' );
	CartRenew_WC_Tracker::mark_recovered( 77 );
	assert_same( 'recovered', row_by_key( 'session_guest123' )->status );
	assert_same( 'recovered', row_by_key( 'orig' )->status );
	assert_same( 77, (int) row_by_key( 'orig' )->order_id );
} );

// ---- Settings ----
test( 'settings: defaults', function () {
	$s = CartRenew_WC_Settings::get_settings();
	assert_same( 20, $s['abandon_minutes'] );
	assert_same( 1, $s['enabled'] );
	assert_same( 'unchecked', $s['consent_default'] );
	assert_false( CartRenew_WC_Settings::is_connected() );
} );

test( 'settings: abandon_minutes clamped to 5..180', function () {
	assert_same( 5, CartRenew_WC_Settings::sanitize_settings( array( 'abandon_minutes' => 1 ) )['abandon_minutes'] );
	assert_same( 180, CartRenew_WC_Settings::sanitize_settings( array( 'abandon_minutes' => 9999 ) )['abandon_minutes'] );
	assert_same( 45, CartRenew_WC_Settings::sanitize_settings( array( 'abandon_minutes' => '45' ) )['abandon_minutes'] );
} );

test( 'settings: sanitize strips tags, rejects non-http api_base, enabled flag', function () {
	$s = CartRenew_WC_Settings::sanitize_settings( array( 'store_id' => '<b>abc</b>', 'api_base' => 'javascript:alert(1)' ) );
	assert_same( 'abc', $s['store_id'] );
	assert_same( '', $s['api_base'] );
	assert_same( 0, $s['enabled'] );
	assert_same( 1, CartRenew_WC_Settings::sanitize_settings( array( 'enabled' => '1' ) )['enabled'] );
} );

test( 'settings: is_connected needs both id and key', function () {
	update_option( 'cartrenew_wc_settings', array( 'store_id' => 'S' ) );
	assert_false( CartRenew_WC_Settings::is_connected() );
	configure_store();
	assert_true( CartRenew_WC_Settings::is_connected() );
} );

test( 'settings: activation redirect is one-shot and skips bulk activation', function () {
	set_transient( 'cartrenew_wc_activation_redirect', 1 );
	$_GET['activate-multi'] = '1';
	CartRenew_WC_Settings::maybe_redirect_after_activation();
	assert_count( 0, $GLOBALS['wp']['redirects'] );
	assert_false( get_transient( 'cartrenew_wc_activation_redirect' ) );
} );

test( 'settings: render_page works connected and disconnected', function () {
	ob_start(); CartRenew_WC_Settings::render_page(); $a = ob_get_clean();
	assert_contains( 'cartrenew_email', $a, 'one-click form shown when not connected' );
	configure_store();
	ob_start(); CartRenew_WC_Settings::render_page(); $b = ob_get_clean();
	assert_contains( 'STORE-1', $b );
} );

// ---- Feedback ----
test( 'feedback: valid reason posts non-blocking payload and succeeds', function () {
	configure_store();
	$_POST = array( 'reason' => 'setup', 'comment' => str_repeat( 'x', 800 ) );
	$r = ajax( array( 'CartRenew_WC_Feedback', 'ajax_feedback' ) );
	assert_true( $r->success );
	$call = $GLOBALS['wp']['http_calls'][0];
	assert_same( 'https://api.example/api/woocommerce/feedback', $call['url'] );
	assert_false( $call['args']['blocking'] );
	$b = json_decode( $call['args']['body'], true );
	assert_same( 'setup', $b['reason'] );
	assert_same( 500, strlen( $b['comment'] ) );
	assert_same( CARTRENEW_WC_VERSION, $b['plugin_version'] );
	assert_true( $b['connected'] );
} );

test( 'feedback: unknown reason -> 400, no request', function () {
	$_POST = array( 'reason' => 'hacked' );
	$r = ajax( array( 'CartRenew_WC_Feedback', 'ajax_feedback' ) );
	assert_false( $r->success );
	assert_same( 400, $r->status );
	assert_count( 0, $GLOBALS['wp']['http_calls'] );
} );

test( 'feedback: user without activate_plugins -> 403', function () {
	$GLOBALS['wp']['user']['can'] = false;
	$_POST = array( 'reason' => 'setup' );
	$r = ajax( array( 'CartRenew_WC_Feedback', 'ajax_feedback' ) );
	assert_same( 403, $r->status );
	assert_count( 0, $GLOBALS['wp']['http_calls'] );
} );

// ---- Uninstall ----
test( 'uninstall: drops table, options, transients and cron hook', function () {
	configure_store();
	update_option( 'cartrenew_wc_db_version', '0.1.5' );
	set_transient( 'cartrenew_wc_limit_reached', 1 );
	set_transient( 'cartrenew_wc_activation_redirect', 1 );
	seed_cart( 'x', 10 );
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', 'cartrenew-woocommerce/cartrenew-woocommerce.php' );
	}
	include PLUGIN_ROOT . '/uninstall.php';
	assert_false( get_option( 'cartrenew_wc_settings' ) );
	assert_false( get_option( 'cartrenew_wc_db_version' ) );
	assert_false( get_transient( 'cartrenew_wc_limit_reached' ) );
	assert_false( get_transient( 'cartrenew_wc_activation_redirect' ) );
	assert_same( array( 'cartrenew_wc_check_abandoned' ), $GLOBALS['wp']['cleared_cron'] );
	$threw = false;
	try { $GLOBALS['wpdb']->all(); } catch ( PDOException $e ) { $threw = true; }
	assert_true( $threw, 'table dropped' );
} );

// ---- Release hygiene ----
test( 'release: header version == constant == readme Stable tag == changelog top', function () {
	$main   = file_get_contents( PLUGIN_ROOT . '/cartrenew-woocommerce.php' );
	$readme = file_get_contents( PLUGIN_ROOT . '/readme.txt' );
	preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $h );
	preg_match( '/^Stable tag:\s*(\S+)/m', $readme, $s );
	preg_match( '/== Changelog ==\s*= (\S+) =/', $readme, $c );
	assert_same( CARTRENEW_WC_VERSION, $h[1], 'header vs constant' );
	assert_same( CARTRENEW_WC_VERSION, $s[1], 'readme stable tag' );
	assert_same( CARTRENEW_WC_VERSION, $c[1], 'changelog' );
} );

test( 'release: DB upgrade runs once when stored version differs', function () {
	$GLOBALS['wp_dbdelta_sql'] = array();
	update_option( 'cartrenew_wc_db_version', '0.1.4' );
	cartrenew_wc_init();
	assert_count( 1, $GLOBALS['wp_dbdelta_sql'] );
	assert_same( CARTRENEW_WC_VERSION, get_option( 'cartrenew_wc_db_version' ) );
	cartrenew_wc_init();
	assert_count( 1, $GLOBALS['wp_dbdelta_sql'], 'not re-run' );
} );

test( 'release: every PHP file lints and has the ABSPATH guard', function () {
	$files = array_merge( glob( PLUGIN_ROOT . '/*.php' ), glob( PLUGIN_ROOT . '/includes/*.php' ) );
	foreach ( $files as $f ) {
		$out = shell_exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $f ) . ' 2>&1' );
		assert_contains( 'No syntax errors', $out, basename( $f ) );
		if ( 'uninstall.php' !== basename( $f ) ) {
			assert_true( false !== strpos( file_get_contents( $f ), "defined( 'ABSPATH' )" ), basename( $f ) . ' guard' );
		}
	}
} );
