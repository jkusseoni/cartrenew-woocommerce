<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart restore links.
 *
 * The WhatsApp message links to {cart}?cartrenew_restore=<token>. Opening it in
 * any browser or device rebuilds the shopper's cart from the saved snapshot and
 * sends them to checkout, so the link works even when it is opened somewhere
 * other than the original session.
 */
class CartRenew_WC_Restore {

	const QUERY_ARG = 'cartrenew_restore';
	const MAX_AGE   = 7 * DAY_IN_SECONDS;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_restore' ), 5 );
	}

	public static function url_for( $token ) {
		return add_query_arg( self::QUERY_ARG, rawurlencode( $token ), wc_get_cart_url() );
	}

	public static function maybe_restore() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public link; the unguessable token is the credential.
		if ( empty( $_GET[ self::QUERY_ARG ] ) ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ) );
		$row   = preg_match( '/^[A-Za-z0-9]{16,64}$/', $token ) ? CartRenew_WC_DB::get_by_token( $token ) : null;

		// Unknown, already-purchased or stale links just land on the normal cart.
		if ( ! $row || 'recovered' === $row->status || $row->last_activity < CartRenew_WC_DB::local_cutoff( self::MAX_AGE ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		// Don't touch a cart the shopper already rebuilt themselves.
		if ( WC()->cart->is_empty() ) {
			if ( WC()->session && ! is_user_logged_in() ) {
				WC()->session->set_customer_session_cookie( true );
			}

			$added = self::restore_items( $row->cart_contents );
			if ( 0 === $added ) {
				wc_add_notice( __( 'Sorry, the items in your saved cart are no longer available.', 'cartrenew-for-woocommerce' ), 'notice' );
				wp_safe_redirect( wc_get_cart_url() );
				exit;
			}

			// Lets the original (sent) cart be marked recovered once an order is placed.
			if ( WC()->session ) {
				WC()->session->set( 'cartrenew_restored_key', $row->cart_key );
			}
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Re-add the saved items. Returns how many were added.
	 */
	private static function restore_items( $cart_contents_json ) {
		$items = json_decode( (string) $cart_contents_json, true );
		if ( ! is_array( $items ) ) {
			return 0;
		}

		$added = 0;
		foreach ( $items as $item ) {
			if ( empty( $item['product_id'] ) ) {
				continue;
			}
			$variation = ( isset( $item['variation'] ) && is_array( $item['variation'] ) ) ? $item['variation'] : array();
			$key       = WC()->cart->add_to_cart(
				absint( $item['product_id'] ),
				max( 1, absint( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) ),
				absint( isset( $item['variation_id'] ) ? $item['variation_id'] : 0 ),
				$variation
			);
			if ( $key ) {
				++$added;
			}
		}
		return $added;
	}
}
