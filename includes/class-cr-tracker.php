<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Watches the WooCommerce cart and keeps a snapshot row up to date in the
 * cartrenew_carts table. Marks the cart "recovered" the moment an order is
 * placed, so the cron sweep never sends a message for a completed purchase.
 */
class CartRenew_WC_Tracker {

	public static function init() {
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'save_snapshot' ) );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'save_snapshot' ) );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'save_snapshot' ) );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'save_snapshot' ) );

		// Order placed → this cart is recovered, stop any pending send.
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'mark_recovered' ), 10, 1 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'mark_recovered' ), 10, 1 );
	}

	/**
	 * Stable identifier for the current shopper's cart, guest or logged-in.
	 */
	private static function get_cart_key() {
		if ( ! WC()->session ) {
			return null;
		}

		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}

		$customer_id = WC()->session->get_customer_id();
		return 'session_' . $customer_id;
	}

	public static function save_snapshot() {
		$settings = CartRenew_WC_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$cart_key = self::get_cart_key();
		if ( ! $cart_key ) {
			return;
		}

		$items = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'];
			if ( ! $product ) {
				continue;
			}
			$items[] = array(
				'name'     => $product->get_name(),
				'quantity' => $item['quantity'],
				'price'    => $product->get_price(),
			);
		}

		$phone   = WC()->session->get( 'cartrenew_phone', '' );
		$consent = WC()->session->get( 'cartrenew_consent', '' );

		$customer_name = '';
		if ( is_user_logged_in() ) {
			$user          = wp_get_current_user();
			$customer_name = $user->display_name;
		}

		$data = array(
			'customer_name' => $customer_name,
			'phone_number'  => $phone ? $phone : null,
			'consent'       => ( 'yes' === $consent ) ? 1 : 0,
			'cart_contents' => wp_json_encode( $items ),
			'cart_total'    => WC()->cart->get_total( 'edit' ),
			'checkout_url'  => wc_get_cart_url(),
		);

		CartRenew_WC_DB::upsert_cart( $cart_key, $data );
	}

	public static function mark_recovered( $order_id ) {
		$cart_key = self::get_cart_key();
		if ( ! $cart_key ) {
			return;
		}

		CartRenew_WC_DB::mark_status( $cart_key, 'recovered', array( 'order_id' => absint( $order_id ) ) );
	}
}
