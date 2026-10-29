<?php
/**
 * WordPress / WooCommerce stubs + an SQLite-backed $wpdb, so the plugin's real
 * classes (and real SQL) run without a WordPress install.
 */

define( 'ABSPATH', __DIR__ . '/fake-wp/' );
define( 'WPINC', 'wp-includes' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WC_VERSION', '9.4.0' );

$GLOBALS['wp'] = array();
$GLOBALS['wp_hooks'] = array();
$GLOBALS['wp_dbdelta_sql'] = array();

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}

/** Thrown by stubs of functions that end the request in WordPress. */
class WpJsonResponse extends Exception {
	public $success;
	public $data;
	public $status;
	public function __construct( $success, $data = null, $status = 200 ) {
		parent::__construct( 'json response' );
		$this->success = $success;
		$this->data    = $data;
		$this->status  = $status;
	}
}
class WpDie extends Exception {}

// --------------------------------------------------------------------------
// Fake $wpdb on SQLite
// --------------------------------------------------------------------------
class FakeWpdb {
	public $prefix = 'wp_';
	public $insert_id = 0;
	/** @var PDO */
	public $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->exec(
			'CREATE TABLE wp_cartrenew_carts (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				cart_key TEXT NOT NULL,
				customer_name TEXT NULL,
				phone_number TEXT NULL,
				consent INTEGER NOT NULL DEFAULT 0,
				cart_contents TEXT NULL,
				cart_total REAL NULL,
				checkout_url TEXT NULL,
				status TEXT NOT NULL DEFAULT \'tracking\',
				order_id INTEGER NULL,
				last_activity TEXT NOT NULL,
				created_at TEXT NOT NULL,
				sent_at TEXT NULL,
				restore_token TEXT NULL
			)'
		);
	}

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$i = 0;
		return preg_replace_callback(
			'/%[sd]/',
			function ( $m ) use ( &$args, &$i ) {
				$v = $args[ $i++ ];
				return '%d' === $m[0] ? (string) (int) $v : $this->pdo->quote( (string) $v );
			},
			$sql
		);
	}

	public function get_row( $sql ) {
		$row = $this->pdo->query( $sql )->fetch( PDO::FETCH_OBJ );
		return $row ? $row : null;
	}

	public function get_results( $sql ) {
		return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_OBJ );
	}

	public function insert( $table, $data ) {
		$cols = implode( ',', array_keys( $data ) );
		$ph   = implode( ',', array_fill( 0, count( $data ), '?' ) );
		$st   = $this->pdo->prepare( "INSERT INTO {$table} ({$cols}) VALUES ({$ph})" );
		$st->execute( array_values( $data ) );
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return 1;
	}

	public function update( $table, $data, $where ) {
		$set  = implode( ',', array_map( function ( $c ) { return "{$c} = ?"; }, array_keys( $data ) ) );
		$cond = implode( ' AND ', array_map( function ( $c ) { return "{$c} = ?"; }, array_keys( $where ) ) );
		$st   = $this->pdo->prepare( "UPDATE {$table} SET {$set} WHERE {$cond}" );
		$st->execute( array_merge( array_values( $data ), array_values( $where ) ) );
		return $st->rowCount();
	}

	public function query( $sql ) {
		return $this->pdo->exec( $sql );
	}

	/** Test helper: all rows. */
	public function all() {
		return $this->pdo->query( 'SELECT * FROM wp_cartrenew_carts ORDER BY id' )->fetchAll( PDO::FETCH_OBJ );
	}
}

// --------------------------------------------------------------------------
// WordPress function stubs
// --------------------------------------------------------------------------
function wp_reset() {
	$GLOBALS['wp'] = array(
		'options'    => array( 'gmt_offset' => 0, 'admin_email' => 'owner@shop.example' ),
		'transients' => array(),
		'transient_ttl' => array(),
		'http_queue' => array(),
		'http_calls' => array(),
		'redirects'  => array(),
		'notices'    => array(),
		'localized'  => array(),
		'scripts'    => array(),
		'user'       => array( 'id' => 1, 'logged_in' => false, 'display_name' => 'adminuser', 'can' => true ),
		'get_page'   => 'cartrenew-settings',
	);
	$_GET  = array();
	$_POST = array();
	$GLOBALS['wpdb'] = new FakeWpdb();
	$GLOBALS['wc']   = wc_make();
	// Reset the consent box "already rendered" guard.
	if ( class_exists( 'CartRenew_WC_Consent' ) ) {
		$p = new ReflectionProperty( 'CartRenew_WC_Consent', 'rendered' );
		$p->setAccessible( true );
		$p->setValue( null, false );
	}
}

function wp_state() {
	return $GLOBALS['wp'];
}

function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['wp_hooks'][ $hook ][] = $cb;
}
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['wp_hooks'][ $hook ][] = $cb;
}
function register_activation_hook( $file, $cb ) {
	$GLOBALS['wp_hooks']['activate'][] = $cb;
}
function register_deactivation_hook( $file, $cb ) {
	$GLOBALS['wp_hooks']['deactivate'][] = $cb;
}
function plugin_dir_path( $f ) {
	return dirname( $f ) . '/';
}
function plugin_dir_url( $f ) {
	return 'https://shop.example/wp-content/plugins/cartrenew-woocommerce/';
}
function plugin_basename( $f ) {
	return 'cartrenew-woocommerce/cartrenew-woocommerce.php';
}

function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['wp']['options'] ) ? $GLOBALS['wp']['options'][ $k ] : $d;
}
function update_option( $k, $v ) {
	$GLOBALS['wp']['options'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['wp']['options'][ $k ] );
	return true;
}
function get_transient( $k ) {
	return array_key_exists( $k, $GLOBALS['wp']['transients'] ) ? $GLOBALS['wp']['transients'][ $k ] : false;
}
function set_transient( $k, $v, $ttl = 0 ) {
	$GLOBALS['wp']['transients'][ $k ]    = $v;
	$GLOBALS['wp']['transient_ttl'][ $k ] = $ttl;
	return true;
}
function delete_transient( $k ) {
	unset( $GLOBALS['wp']['transients'][ $k ] );
	return true;
}
function wp_clear_scheduled_hook( $h ) {
	$GLOBALS['wp']['cleared_cron'][] = $h;
}
function wp_next_scheduled( $h ) {
	return $GLOBALS['wp']['scheduled'][ $h ] ?? false;
}
function wp_schedule_event( $t, $recur, $h ) {
	$GLOBALS['wp']['scheduled'][ $h ] = $recur;
}

function wp_parse_args( $a, $d = array() ) {
	return array_merge( $d, (array) $a );
}
function wp_unslash( $v ) {
	return $v;
}
function sanitize_text_field( $s ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function sanitize_textarea_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function sanitize_key( $k ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) );
}
function sanitize_email( $e ) {
	return trim( (string) $e );
}
function is_email( $e ) {
	return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL );
}
function esc_url_raw( $u ) {
	return preg_match( '#^https?://#i', (string) $u ) ? (string) $u : '';
}
function absint( $n ) {
	return abs( (int) $n );
}
function __( $s, $d = null ) {
	return $s;
}
function esc_html__( $s, $d = null ) {
	return htmlspecialchars( $s, ENT_QUOTES );
}
function esc_attr__( $s, $d = null ) {
	return htmlspecialchars( $s, ENT_QUOTES );
}
function esc_html_e( $s, $d = null ) {
	echo htmlspecialchars( $s, ENT_QUOTES );
}
function esc_attr_e( $s, $d = null ) {
	echo htmlspecialchars( $s, ENT_QUOTES );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function checked( $a, $b = true, $echo = true ) {
	$r = (string) $a === (string) $b ? "checked='checked'" : '';
	if ( $echo ) {
		echo $r;
	}
	return $r;
}
function admin_url( $p = '' ) {
	return 'https://shop.example/wp-admin/' . $p;
}
function home_url( $p = '' ) {
	return 'https://shop.example' . $p;
}
function get_bloginfo( $k = '' ) {
	return 'version' === $k ? '6.9' : 'Shop Name';
}
function wp_json_encode( $v ) {
	return json_encode( $v );
}
function trailingslashit( $s ) {
	return rtrim( $s, '/\\' ) . '/';
}
function add_query_arg( $k, $v, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
}
function current_time( $type ) {
	$ts = time() + (int) round( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s', $ts ) : $ts;
}
function wp_generate_password( $len = 12, $special = true ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$out   = '';
	for ( $i = 0; $i < $len; $i++ ) {
		$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}
	return $out;
}
function dbDelta( $sql ) {
	$GLOBALS['wp_dbdelta_sql'][] = $sql;
}

function is_wp_error( $r ) {
	return $r instanceof WP_Error;
}
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['wp']['http_calls'][] = array( 'url' => $url, 'args' => $args );
	$next = array_shift( $GLOBALS['wp']['http_queue'] );
	return null === $next ? array( 'response' => array( 'code' => 200 ), 'body' => '{}' ) : $next;
}
function http_response( $code, $body = '{}' ) {
	return array( 'response' => array( 'code' => $code ), 'body' => is_string( $body ) ? $body : json_encode( $body ) );
}
function queue_http( ...$responses ) {
	foreach ( $responses as $r ) {
		$GLOBALS['wp']['http_queue'][] = $r;
	}
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['response']['code'];
}
function wp_remote_retrieve_body( $r ) {
	return $r['body'];
}
function wp_safe_redirect( $url ) {
	$GLOBALS['wp']['redirects'][] = $url;
	return true;
}

function current_user_can( $cap ) {
	return $GLOBALS['wp']['user']['can'];
}
function is_user_logged_in() {
	return $GLOBALS['wp']['user']['logged_in'];
}
function get_current_user_id() {
	return $GLOBALS['wp']['user']['id'];
}
function wp_get_current_user() {
	return (object) array( 'display_name' => $GLOBALS['wp']['user']['display_name'] );
}
function is_admin() {
	return true;
}
function is_network_admin() {
	return false;
}
function check_admin_referer( $a = '' ) {
	return true;
}
function check_ajax_referer( $a = '', $b = '' ) {
	return true;
}
function wp_nonce_field( $a = '' ) {
	echo '<input type="hidden" name="_wpnonce" value="nonce" />';
}
function wp_create_nonce( $a = '' ) {
	return 'nonce123';
}
function wp_send_json_success( $data = null ) {
	throw new WpJsonResponse( true, $data );
}
function wp_send_json_error( $data = null, $status = 200 ) {
	throw new WpJsonResponse( false, $data, $status );
}
function wp_die( $m = '', $t = '', $args = array() ) {
	throw new WpDie( is_string( $m ) ? $m : 'die' );
}
function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true ) {
	echo '<button class="button button-' . $type . '">' . htmlspecialchars( $text ) . '</button>';
}
function settings_fields( $g ) {
	echo '<input type="hidden" name="option_page" value="' . $g . '" />';
}
function register_setting( $g, $o, $args = array() ) {
	$GLOBALS['wp']['registered_settings'][ $o ] = $args;
}
function add_submenu_page( ...$a ) {
	$GLOBALS['wp']['submenu'] = $a;
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['wp']['scripts'][ $handle ] = array( 'src' => $src, 'ver' => $ver );
}
function wp_localize_script( $handle, $name, $data ) {
	$GLOBALS['wp']['localized'][ $handle ][ $name ] = $data;
}

// WooCommerce functions
function wc_get_cart_url() {
	return 'https://shop.example/cart/';
}
function wc_get_checkout_url() {
	return 'https://shop.example/checkout/';
}
function wc_add_notice( $m, $t = 'success' ) {
	$GLOBALS['wp']['notices'][] = array( $t, $m );
}
function is_cart() {
	return ! empty( $GLOBALS['wp']['is_cart'] );
}
function is_checkout() {
	return ! empty( $GLOBALS['wp']['is_checkout'] );
}
function is_wc_endpoint_url( $e = '' ) {
	return ! empty( $GLOBALS['wp']['is_order_received'] );
}
function set_page( $page ) {
	$GLOBALS['wp']['is_cart']          = 'cart' === $page;
	$GLOBALS['wp']['is_checkout']      = in_array( $page, array( 'checkout', 'order-received' ), true );
	$GLOBALS['wp']['is_order_received'] = 'order-received' === $page;
}

class WC_Stub_Product {
	private $name;
	private $price;
	public function __construct( $name, $price ) {
		$this->name  = $name;
		$this->price = $price;
	}
	public function get_name() {
		return $this->name;
	}
	public function get_price() {
		return $this->price;
	}
}
class WC_Stub_Cart {
	public $items = array();
	public $total = '0.00';
	public $added = array();
	public function is_empty() {
		return empty( $this->items );
	}
	public function get_cart() {
		return $this->items;
	}
	public function get_total( $ctx = 'view' ) {
		return $this->total;
	}
	public function add_to_cart( $product_id, $qty, $variation_id, $variation ) {
		if ( 999 === (int) $product_id ) {
			return false; // unavailable product
		}
		$this->added[]  = array( $product_id, $qty, $variation_id, $variation );
		$this->items[] = array( 'data' => new WC_Stub_Product( 'P' . $product_id, '1.00' ), 'quantity' => $qty, 'product_id' => $product_id, 'variation_id' => $variation_id, 'variation' => $variation );
		return 'cartkey';
	}
}
class WC_Stub_Session {
	public $data = array();
	public $cookie = false;
	public $customer_id = 'guest123';
	public function get( $k, $d = '' ) {
		return array_key_exists( $k, $this->data ) ? $this->data[ $k ] : $d;
	}
	public function set( $k, $v ) {
		$this->data[ $k ] = $v;
	}
	public function get_customer_id() {
		return $this->customer_id;
	}
	public function set_customer_session_cookie( $b ) {
		$this->cookie = $b;
	}
}
class WC_Stub_Countries {
	private $codes = array(
		'IN' => '+91',
		'GB' => '+44',
		'US' => '+1',
		'DO' => array( '+1809', '+1829' ),
		'AQ' => '',
	);
	public $base = 'IN';
	public function get_base_country() {
		return $this->base;
	}
	public function get_countries() {
		return array_fill_keys( array_keys( $this->codes ), 'x' );
	}
	public function get_country_calling_code( $c ) {
		return $this->codes[ $c ] ?? '';
	}
}
class WC_Stub {
	public $cart;
	public $session;
	public $countries;
}
function wc_make() {
	$wc            = new WC_Stub();
	$wc->cart      = new WC_Stub_Cart();
	$wc->session   = new WC_Stub_Session();
	$wc->countries = new WC_Stub_Countries();
	return $wc;
}
function WC() {
	return $GLOBALS['wc'];
}
class WooCommerce {}

// --------------------------------------------------------------------------
// Load the plugin (real code)
// --------------------------------------------------------------------------
define( 'PLUGIN_ROOT', dirname( __DIR__ ) );
wp_reset();
require PLUGIN_ROOT . '/cartrenew-woocommerce.php';
cartrenew_wc_init(); // requires every class file and registers hooks
wp_reset();

// --------------------------------------------------------------------------
// Test data helpers
// --------------------------------------------------------------------------
/** Insert a cart row; last_activity is given as minutes ago in SITE-LOCAL time (how the plugin stores it). */
function seed_cart( $key, $minutes_ago, $overrides = array() ) {
	$offset = (int) round( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	$local  = gmdate( 'Y-m-d H:i:s', time() + $offset - (int) ( $minutes_ago * 60 ) );
	$row    = array_merge(
		array(
			'cart_key'      => $key,
			'customer_name' => 'Asha',
			'phone_number'  => '919876543210',
			'consent'       => 1,
			'cart_contents' => json_encode( array( array( 'name' => 'Tee', 'quantity' => 2, 'price' => '10', 'product_id' => 11, 'variation_id' => 12, 'variation' => array( 'attribute_size' => 'M' ) ) ) ),
			'cart_total'    => 25.0,
			'checkout_url'  => 'https://shop.example/cart/',
			'status'        => 'tracking',
			'last_activity' => $local,
			'created_at'    => $local,
			'restore_token' => 'tok' . $key . str_repeat( 'x', 20 ),
		),
		$overrides
	);
	$GLOBALS['wpdb']->insert( 'wp_cartrenew_carts', $row );
	return $row;
}

function configure_store() {
	update_option( 'cartrenew_wc_settings', array(
		'store_id'        => 'STORE-1',
		'api_key'         => 'KEY-1',
		'api_base'        => 'https://api.example/api/woocommerce',
		'abandon_minutes' => 20,
		'enabled'         => 1,
	) );
}

function row_by_key( $key ) {
	foreach ( $GLOBALS['wpdb']->all() as $r ) {
		if ( $r->cart_key === $key ) {
			return $r;
		}
	}
	return null;
}
