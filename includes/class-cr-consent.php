<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the WhatsApp opt-in on the cart page (phone number + consent) and on
 * the checkout page (consent checkbox only — the number comes from the billing
 * phone field). Abandonment has to be captured before an order exists, because
 * WooCommerce only fires order hooks on completed purchases.
 * Saves via AJAX into the WC session so it survives until the cron sweep runs.
 */
class CartRenew_WC_Consent {

	public static function init() {
		// Classic (shortcode-based) cart template still fires this hook — keep it
		// for themes that haven't switched to WooCommerce Blocks.
		add_action( 'woocommerce_before_cart_totals', array( __CLASS__, 'render_form' ) );

		// Classic checkout: show the opt-in right under the billing fields.
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'render_checkout_form' ) );

		// WooCommerce Blocks (Cart / Checkout block) render via React and never fire
		// the hooks above, so also render into wp_footer and let JS relocate the box
		// into the block DOM. render_form() guards against double-output.
		add_action( 'wp_footer', array( __CLASS__, 'render_form_footer_fallback' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_cartrenew_save_consent', array( __CLASS__, 'ajax_save_consent' ) );
		add_action( 'wp_ajax_nopriv_cartrenew_save_consent', array( __CLASS__, 'ajax_save_consent' ) );
	}

	private static $rendered = false;

	private static function is_cart_context() {
		return function_exists( 'is_cart' ) && is_cart();
	}

	/**
	 * Checkout page where the shopper is still filling in details (not the
	 * order-received / thank-you page).
	 */
	private static function is_checkout_context() {
		return function_exists( 'is_checkout' ) && is_checkout()
			&& ! ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) );
	}

	public static function enqueue_assets() {
		$is_checkout = self::is_checkout_context();
		if ( ! self::is_cart_context() && ! $is_checkout ) {
			return;
		}

		wp_enqueue_script(
			'cartrenew-consent',
			CARTRENEW_WC_PLUGIN_URL . 'assets/consent.js',
			array( 'jquery' ),
			CARTRENEW_WC_VERSION,
			true
		);
		wp_localize_script(
			'cartrenew-consent',
			'CartRenewConsent',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'cartrenew_consent' ),
				'context'       => $is_checkout ? 'checkout' : 'cart',
				'default_cc'    => self::get_default_calling_code(),
				'calling_codes' => self::get_calling_codes(),
				'i18n'          => array(
					'saved'      => __( 'Saved', 'cartrenew-for-woocommerce' ),
					'need_phone' => __( 'Enter your phone number above to get the reminder.', 'cartrenew-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Calling code (digits only, e.g. "91") of the store's base country, used
	 * to prefix numbers customers type without a country code.
	 */
	private static function get_default_calling_code() {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return '';
		}
		return self::calling_code_digits( WC()->countries->get_base_country() );
	}

	private static function calling_code_digits( $country ) {
		$code = WC()->countries->get_country_calling_code( $country );
		if ( is_array( $code ) ) {
			$code = reset( $code );
		}
		return preg_replace( '/\D/', '', (string) $code );
	}

	/**
	 * ISO country => calling code digits, so the checkout JS can build an E.164
	 * number from the billing country the shopper picked.
	 */
	private static function get_calling_codes() {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return new stdClass();
		}
		$codes = array();
		foreach ( array_keys( WC()->countries->get_countries() ) as $country ) {
			$digits = self::calling_code_digits( $country );
			if ( '' !== $digits ) {
				$codes[ $country ] = $digits;
			}
		}
		return $codes;
	}

	/**
	 * Renders the box hidden in the footer on the cart / checkout page
	 * (block-based templates don't fire the classic hooks).
	 * JS (assets/consent.js) relocates it into the visible DOM.
	 */
	public static function render_form_footer_fallback() {
		if ( self::$rendered || ( ! self::is_cart_context() && ! self::is_checkout_context() ) ) {
			return;
		}
		self::render_form( true );
	}

	/**
	 * Hook wrapper for the classic checkout (that hook passes the WC_Checkout
	 * object, which must not be mistaken for the $hidden flag).
	 */
	public static function render_checkout_form() {
		self::render_form( false );
	}

	public static function render_form( $hidden = false ) {
		if ( self::$rendered ) {
			return;
		}

		$settings = CartRenew_WC_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		self::$rendered = true;

		$is_checkout = self::is_checkout_context();
		$session     = WC()->session ? WC()->session->get( 'cartrenew_phone', '' ) : '';
		$consented   = WC()->session ? WC()->session->get( 'cartrenew_consent', '' ) : '';
		$checked     = ( 'yes' === $consented ) ? 'checked' : '';
		$style       = $hidden
			? 'display:none;margin:16px 0;padding:14px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;width:100%;clear:both;'
			: 'margin:16px 0;padding:14px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;width:100%;clear:both;';
		?>
		<div id="cartrenew-consent-box" style="<?php echo esc_attr( $style ); ?>">
			<?php if ( $is_checkout ) : ?>
				<label style="display:block;margin-bottom:8px;">
					<input type="checkbox" id="cartrenew_consent" <?php echo esc_attr( $checked ); ?> />
					<?php esc_html_e( 'Remind me on WhatsApp (using the phone number above) if I don\'t complete this order.', 'cartrenew-for-woocommerce' ); ?>
				</label>
			<?php else : ?>
				<p style="margin-top:0;font-weight:600;">
					<?php esc_html_e( 'Get a WhatsApp reminder if you don\'t finish checking out', 'cartrenew-for-woocommerce' ); ?>
				</p>
				<p>
					<input type="tel" id="cartrenew_phone" placeholder="<?php esc_attr_e( 'WhatsApp number, e.g. 9876543210', 'cartrenew-for-woocommerce' ); ?>" value="<?php echo esc_attr( $session ); ?>" style="width:100%;max-width:280px;" />
				</p>
				<label style="display:block;margin-bottom:8px;">
					<input type="checkbox" id="cartrenew_consent" <?php echo esc_attr( $checked ); ?> />
					<?php esc_html_e( 'Yes, remind me on WhatsApp if I leave items in my cart.', 'cartrenew-for-woocommerce' ); ?>
				</label>
			<?php endif; ?>
			<span id="cartrenew-consent-status" style="font-size:12px;color:#666;"></span>
		</div>
		<?php
	}

	public static function ajax_save_consent() {
		check_ajax_referer( 'cartrenew_consent', 'nonce' );

		// Phone is optional in the request (checkout sends it only once typed), so a
		// consent toggle never wipes a number saved earlier. Digits only, E.164 length.
		$phone = null;
		if ( isset( $_POST['phone'] ) ) {
			$phone = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ) ) );
			if ( '' !== $phone && ( strlen( $phone ) < 8 || strlen( $phone ) > 15 ) ) {
				$phone = '';
			}
		}

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$consent = isset( $_POST['consent'] ) && 'yes' === $_POST['consent'] ? 'yes' : 'no';

		if ( WC()->session ) {
			if ( null !== $phone ) {
				WC()->session->set( 'cartrenew_phone', $phone );
			}
			if ( '' !== $name ) {
				WC()->session->set( 'cartrenew_name', mb_substr( $name, 0, 100 ) );
			}
			WC()->session->set( 'cartrenew_consent', $consent );
		}

		// Immediately upsert into the tracking table too, so the cron sweep
		// picks it up even if the shopper never triggers another cart hook.
		if ( class_exists( 'CartRenew_WC_Tracker' ) ) {
			CartRenew_WC_Tracker::save_snapshot();
		}

		wp_send_json_success();
	}
}
