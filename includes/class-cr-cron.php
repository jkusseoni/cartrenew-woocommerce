<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs every 5 minutes (registered in the main plugin file). Finds carts
 * that have gone quiet past the configured wait time, with phone + consent
 * present, and hands each one to CartRenew_WC_API. Rate-limited implicitly
 * by "status='tracking'" only matching once per cart (marked 'sent' or
 * 'send_failed' afterwards, never resent).
 */
class CartRenew_WC_Cron {

	public static function init() {
		add_action( 'cartrenew_wc_check_abandoned', array( __CLASS__, 'run' ) );
	}

	public static function run() {
		$settings = CartRenew_WC_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		// Not connected yet: leave carts in "tracking" so they are sent once the
		// merchant saves their Store ID + API key (instead of burning them as failed).
		if ( empty( $settings['store_id'] ) || empty( $settings['api_key'] ) ) {
			return;
		}

		// Free message limit already hit: stop calling the API until it is lifted
		// (the flag expires on its own, so sending resumes after an upgrade).
		if ( get_transient( CartRenew_WC_Settings::LIMIT_FLAG ) ) {
			return;
		}

		$minutes = ! empty( $settings['abandon_minutes'] ) ? absint( $settings['abandon_minutes'] ) : 20;
		$carts   = CartRenew_WC_DB::get_abandoned_carts( $minutes );

		foreach ( $carts as $cart ) {
			// A number that can never receive WhatsApp (e.g. an Indian mobile not
			// starting 6-9) would only come back as Meta error 131026: skip the call.
			if ( ! CartRenew_WC_Consent::is_valid_phone( $cart->phone_number ) ) {
				CartRenew_WC_DB::mark_status( $cart->cart_key, 'send_failed' );
				continue;
			}

			$result = CartRenew_WC_API::send_abandoned_cart( $cart );

			if ( is_wp_error( $result ) && 'cartrenew_trial_limit' === $result->get_error_code() ) {
				// Leave this cart (and the rest) untouched; only pause sending.
				set_transient( CartRenew_WC_Settings::LIMIT_FLAG, 1, 6 * HOUR_IN_SECONDS );
				break;
			}

			if ( is_wp_error( $result ) ) {
				CartRenew_WC_DB::mark_status(
					$cart->cart_key,
					'send_failed',
					array( 'sent_at' => current_time( 'mysql' ) )
				);
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug trail during dev.
				error_log( 'CartRenew WC send failed for cart ' . $cart->cart_key . ': ' . $result->get_error_message() );
				continue;
			}

			CartRenew_WC_DB::mark_status(
				$cart->cart_key,
				'sent',
				array( 'sent_at' => current_time( 'mysql' ) )
			);
		}
	}
}
