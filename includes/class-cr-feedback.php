<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional "why are you deactivating?" survey on the Plugins screen.
 *
 * Nothing is sent unless the person picks a reason and clicks "Submit &
 * Deactivate". "Skip & Deactivate" just deactivates. No email, name or customer
 * data is ever included.
 */
class CartRenew_WC_Feedback {

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_cartrenew_wc_feedback', array( __CLASS__, 'ajax_feedback' ) );
	}

	private static function reasons() {
		return array(
			'setup'         => __( 'Setup was confusing or I could not connect', 'cartrenew-for-woocommerce' ),
			'not_working'   => __( 'Messages were not sent or it did not work', 'cartrenew-for-woocommerce' ),
			'features'      => __( 'It is missing a feature I need', 'cartrenew-for-woocommerce' ),
			'better_plugin' => __( 'I found a better plugin', 'cartrenew-for-woocommerce' ),
			'temporary'     => __( 'Temporary deactivation / debugging', 'cartrenew-for-woocommerce' ),
			'other'         => __( 'Other', 'cartrenew-for-woocommerce' ),
		);
	}

	public static function enqueue( $hook ) {
		if ( 'plugins.php' !== $hook || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		wp_enqueue_script(
			'cartrenew-feedback',
			CARTRENEW_WC_PLUGIN_URL . 'assets/deactivate-feedback.js',
			array( 'jquery' ),
			CARTRENEW_WC_VERSION,
			true
		);
		wp_localize_script(
			'cartrenew-feedback',
			'CartRenewFeedback',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cartrenew_wc_feedback' ),
				'basename' => plugin_basename( CARTRENEW_WC_PLUGIN_FILE ),
				'reasons'  => self::reasons(),
				'i18n'     => array(
					'title'       => __( 'Quick feedback', 'cartrenew-for-woocommerce' ),
					'intro'       => __( 'Sorry to see you go. Why are you deactivating CartRenew? (optional)', 'cartrenew-for-woocommerce' ),
					'placeholder' => __( 'Anything we should know? (optional)', 'cartrenew-for-woocommerce' ),
					'submit'      => __( 'Submit & Deactivate', 'cartrenew-for-woocommerce' ),
					'skip'        => __( 'Skip & Deactivate', 'cartrenew-for-woocommerce' ),
					'cancel'      => __( 'Cancel', 'cartrenew-for-woocommerce' ),
					'privacy'     => __( 'Only your answer, plugin version and WordPress/WooCommerce versions are sent. No personal or customer data.', 'cartrenew-for-woocommerce' ),
				),
			)
		);
	}

	public static function ajax_feedback() {
		check_ajax_referer( 'cartrenew_wc_feedback', 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( null, 403 );
		}

		$reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		if ( ! array_key_exists( $reason, self::reasons() ) ) {
			wp_send_json_error( null, 400 );
		}

		$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
		$comment = mb_substr( $comment, 0, 500 );

		$settings = CartRenew_WC_Settings::get_settings();

		// Fire-and-forget: never delay or block deactivation on this request.
		wp_remote_post(
			trailingslashit( $settings['api_base'] ) . 'feedback',
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode(
					array(
						'reason'         => $reason,
						'comment'        => $comment,
						'plugin_version' => CARTRENEW_WC_VERSION,
						'wp_version'     => get_bloginfo( 'version' ),
						'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : '',
						'connected'      => CartRenew_WC_Settings::is_connected(),
					)
				),
			)
		);

		wp_send_json_success();
	}
}
