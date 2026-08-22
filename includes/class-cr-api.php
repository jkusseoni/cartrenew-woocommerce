<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends an abandoned-cart payload to the CartRenew backend.
 *
 * NOTE: this posts to {api_base}/abandoned-cart — that endpoint does not
 * exist yet on the CartRenew Next.js backend and needs to be built there.
 * It should: verify store_id + api_key, look up (or auto-create) the
 * WooCommerce store record, then enqueue the same Meta Cloud API send used
 * for Shopify (template "abandoned_cart_reminder", bodyVariables
 * [customer_name, checkout_url]) — same shape as the Shopify webhook path.
 */
class CartRenew_WC_API {

	public static function send_abandoned_cart( $cart_row ) {
		$settings = CartRenew_WC_Settings::get_settings();

		if ( empty( $settings['store_id'] ) || empty( $settings['api_key'] ) ) {
			return new WP_Error( 'cartrenew_not_configured', 'CartRenew store_id / api_key not set.' );
		}

		$endpoint = trailingslashit( $settings['api_base'] ) . 'abandoned-cart';

		$body = array(
			'store_id'      => $settings['store_id'],
			'cart_key'      => $cart_row->cart_key,
			'customer_name' => $cart_row->customer_name,
			'phone_number'  => $cart_row->phone_number,
			'cart_total'    => $cart_row->cart_total,
			'checkout_url'  => $cart_row->checkout_url,
			'cart_contents' => json_decode( $cart_row->cart_contents, true ),
			'site_url'      => home_url(),
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $settings['api_key'],
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'cartrenew_api_error',
				sprintf( 'CartRenew API returned HTTP %d', $code ),
				array( 'body' => wp_remote_retrieve_body( $response ) )
			);
		}

		return true;
	}
}
