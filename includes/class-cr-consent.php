<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a small phone-number + consent form on the cart page (WooCommerce has
 * no separate marketing-consent field, and checkout only fires on completed
 * orders — so abandonment has to be captured earlier, on the cart page).
 * Saves via AJAX into the WC session so it survives until the cron sweep runs.
 */
class CartRenew_WC_Consent {

	public static function init() {
		// Classic (shortcode-based) cart template still fires this hook — keep it
		// for themes that haven't switched to WooCommerce Blocks.
		add_action( 'woocommerce_before_cart_totals', array( __CLASS__, 'render_form' ) );

		// WooCommerce Blocks (Cart block) renders via React and never fires the
		// hook above, so also render into wp_footer and let JS relocate the box
		// into the block DOM. render_form() guards against double-output.
		add_action( 'wp_footer', array( __CLASS__, 'render_form_footer_fallback' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_cartrenew_save_consent', array( __CLASS__, 'ajax_save_consent' ) );
		add_action( 'wp_ajax_nopriv_cartrenew_save_consent', array( __CLASS__, 'ajax_save_consent' ) );
	}

	private static $rendered = false;

	public static function enqueue_assets() {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
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
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cartrenew_consent' ),
			)
		);
	}

	/**
	 * Renders the box hidden in the footer on the cart page (block-based cart
	 * templates don't fire the classic woocommerce_before_cart_totals hook).
	 * JS (assets/consent.js) relocates it into the visible cart DOM.
	 */
	public static function render_form_footer_fallback() {
		if ( self::$rendered || ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		self::render_form( true );
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

		$session   = WC()->session ? WC()->session->get( 'cartrenew_phone', '' ) : '';
		$consented = WC()->session ? WC()->session->get( 'cartrenew_consent', '' ) : '';
		$checked   = ( 'yes' === $consented ) ? 'checked' : '';
		$style     = $hidden
			? 'display:none;margin:16px 0;padding:14px;border:1px solid #ddd;border-radius:6px;'
			: 'margin:16px 0;padding:14px;border:1px solid #ddd;border-radius:6px;';
		?>
		<div id="cartrenew-consent-box" style="<?php echo esc_attr( $style ); ?>">
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
			<span id="cartrenew-consent-status" style="font-size:12px;color:#666;"></span>
		</div>
		<?php
	}

	public static function ajax_save_consent() {
		check_ajax_referer( 'cartrenew_consent', 'nonce' );

		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$consent = isset( $_POST['consent'] ) && 'yes' === $_POST['consent'] ? 'yes' : 'no';

		if ( WC()->session ) {
			WC()->session->set( 'cartrenew_phone', $phone );
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
