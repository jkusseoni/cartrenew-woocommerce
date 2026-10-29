<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings > CartRenew page — stores the merchant's CartRenew store_id + api_key
 * so the backend can identify which store a webhook is coming from.
 */
class CartRenew_WC_Settings {

	const OPTION_KEY = 'cartrenew_wc_settings';

	// Set by the cron when CartRenew reports the free message limit is used up.
	const LIMIT_FLAG = 'cartrenew_wc_limit_reached';

	const UPGRADE_URL = 'https://www.cartrenew.com/en#pricing';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_after_activation' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_connect_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_limit_notice' ) );
		add_action( 'admin_post_cartrenew_wc_connect', array( __CLASS__, 'handle_connect' ) );
	}

	/**
	 * Short-lived admin message (per user) shown on the settings page after the
	 * one-click connect redirect.
	 */
	private static function flash( $type, $message ) {
		set_transient(
			'cartrenew_wc_flash_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	private static function save_credentials( $store_id, $api_key = null ) {
		$settings             = self::get_settings();
		$settings['store_id'] = sanitize_text_field( $store_id );
		if ( null !== $api_key ) {
			$settings['api_key'] = sanitize_text_field( $api_key );
		}
		update_option( self::OPTION_KEY, $settings );
		delete_transient( self::LIMIT_FLAG );
	}

	/**
	 * "Connect with one click": registers this site with CartRenew and stores the
	 * returned Store ID + API Key, so the merchant never has to copy them by hand.
	 */
	public static function handle_connect() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'cartrenew-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cartrenew_wc_connect' );

		$redirect = self::settings_url();
		$email    = isset( $_POST['cartrenew_email'] ) ? sanitize_email( wp_unslash( $_POST['cartrenew_email'] ) ) : '';

		if ( ! is_email( $email ) ) {
			self::flash( 'error', __( 'Please enter a valid email address.', 'cartrenew-for-woocommerce' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		$settings = self::get_settings();
		$response = wp_remote_post(
			trailingslashit( $settings['api_base'] ) . 'register-store',
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'email'      => $email,
						'site_url'   => home_url(),
						'store_name' => get_bloginfo( 'name' ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::flash(
				'error',
				sprintf(
					/* translators: %s: error message from WordPress HTTP API */
					__( 'Could not reach CartRenew: %s. You can still enter your credentials manually below.', 'cartrenew-for-woocommerce' ),
					$response->get_error_message()
				)
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : array();

		if ( 200 === $code && ! empty( $data['store_id'] ) && ! empty( $data['api_key'] ) ) {
			self::save_credentials( $data['store_id'], $data['api_key'] );
			self::flash( 'success', __( 'Connected! Your Store ID and API Key were saved automatically.', 'cartrenew-for-woocommerce' ) );
		} elseif ( 409 === $code && ! empty( $data['store_id'] ) ) {
			// Site already registered: the API key is never returned a second time.
			self::save_credentials( $data['store_id'] );
			self::flash( 'warning', __( 'This site is already registered with CartRenew. Your Store ID was filled in; copy your API Key from your CartRenew dashboard and paste it below.', 'cartrenew-for-woocommerce' ) );
		} else {
			self::flash(
				'error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'CartRenew could not register this store (HTTP %d). Please enter your Store ID and API Key manually below.', 'cartrenew-for-woocommerce' ),
					$code
				)
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function settings_url() {
		return admin_url( 'admin.php?page=cartrenew-settings' );
	}

	public static function is_connected() {
		$s = self::get_settings();
		return ! empty( $s['store_id'] ) && ! empty( $s['api_key'] );
	}

	/**
	 * One-time redirect to the settings page right after activation.
	 */
	public static function maybe_redirect_after_activation() {
		if ( ! get_transient( 'cartrenew_wc_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'cartrenew_wc_activation_redirect' );

		// Skip bulk activations, network admin and users who can't configure it.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by WordPress core.
		if ( isset( $_GET['activate-multi'] ) || is_network_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_safe_redirect( self::settings_url() );
		exit;
	}

	/**
	 * Shown while sending is paused because the free message limit is used up.
	 */
	public static function maybe_show_limit_notice() {
		if ( ! get_transient( self::LIMIT_FLAG ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'CartRenew free message limit reached.', 'cartrenew-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'WhatsApp recovery messages are paused until you upgrade your plan.', 'cartrenew-for-woocommerce' ); ?>
				<a href="<?php echo esc_url( self::UPGRADE_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade now', 'cartrenew-for-woocommerce' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Reminder on admin screens until the Store ID + API key are saved.
	 */
	public static function maybe_show_connect_notice() {
		if ( self::is_connected() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'cartrenew-settings' === $page ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'CartRenew is not connected yet.', 'cartrenew-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Abandoned-cart WhatsApp messages will not be sent until you add your Store ID and API Key.', 'cartrenew-for-woocommerce' ); ?>
				<a href="<?php echo esc_url( self::settings_url() ); ?>"><?php esc_html_e( 'Connect now', 'cartrenew-for-woocommerce' ); ?></a>
			</p>
		</div>
		<?php
	}

	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'CartRenew', 'cartrenew-for-woocommerce' ),
			__( 'CartRenew', 'cartrenew-for-woocommerce' ),
			'manage_woocommerce',
			'cartrenew-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_KEY,
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	public static function sanitize_settings( $input ) {
		$sanitized = array();
		if ( isset( $input['store_id'] ) ) {
			$sanitized['store_id'] = sanitize_text_field( $input['store_id'] );
		}
		if ( isset( $input['api_key'] ) ) {
			$sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
		}
		if ( isset( $input['api_base'] ) ) {
			$sanitized['api_base'] = esc_url_raw( $input['api_base'] );
		}
		if ( isset( $input['abandon_minutes'] ) ) {
			$sanitized['abandon_minutes'] = absint( $input['abandon_minutes'] );
			if ( $sanitized['abandon_minutes'] < 5 ) {
				$sanitized['abandon_minutes'] = 5;
			}
			if ( $sanitized['abandon_minutes'] > 180 ) {
				$sanitized['abandon_minutes'] = 180;
			}
		}
		$sanitized['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
		return $sanitized;
	}

	public static function get_settings() {
		$defaults = array(
			'store_id'          => '',
			'api_key'           => '',
			'api_base'          => CARTRENEW_WC_DEFAULT_API_BASE,
			'abandon_minutes'   => 20,
			'consent_default'   => 'unchecked', // 'checked' or 'unchecked'
			'enabled'           => 1,
		);
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $saved, $defaults );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = self::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CartRenew Settings', 'cartrenew-for-woocommerce' ); ?></h1>
			<p><?php esc_html_e( 'Connect this store to your CartRenew account to enable WhatsApp abandoned-cart recovery.', 'cartrenew-for-woocommerce' ); ?></p>
			<?php
			$flash_key = 'cartrenew_wc_flash_' . get_current_user_id();
			$flash     = get_transient( $flash_key );
			if ( $flash ) {
				delete_transient( $flash_key );
				$allowed = array( 'success', 'warning', 'error' );
				$type    = in_array( $flash['type'], $allowed, true ) ? $flash['type'] : 'info';
				?>
				<div class="notice notice-<?php echo esc_attr( $type ); ?> inline"><p><?php echo esc_html( $flash['message'] ); ?></p></div>
			<?php } ?>
			<?php if ( ! self::is_connected() ) : ?>
				<div class="card" style="max-width:640px;margin:16px 0;padding:8px 20px 16px;">
					<h2><?php esc_html_e( 'Connect in one click', 'cartrenew-for-woocommerce' ); ?></h2>
					<p><?php esc_html_e( 'Enter your email and we will create your CartRenew store and fill in your credentials automatically.', 'cartrenew-for-woocommerce' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cartrenew_wc_connect" />
						<?php wp_nonce_field( 'cartrenew_wc_connect' ); ?>
						<p>
							<label for="cartrenew_email" class="screen-reader-text"><?php esc_html_e( 'Email address', 'cartrenew-for-woocommerce' ); ?></label>
							<input type="email" id="cartrenew_email" name="cartrenew_email" class="regular-text" required value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
						</p>
						<?php submit_button( __( 'Connect with one click', 'cartrenew-for-woocommerce' ), 'primary', 'submit', false ); ?>
					</form>
					<p class="description" style="margin-top:12px;">
						<?php esc_html_e( 'Already have an account? Enter your Store ID and API Key manually below.', 'cartrenew-for-woocommerce' ); ?>
						<a href="https://www.cartrenew.com/en/woocommerce/connect" target="_blank" rel="noopener"><?php esc_html_e( 'Find my credentials', 'cartrenew-for-woocommerce' ); ?></a>
					</p>
				</div>
			<?php else : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Connected. Carts with a phone number and WhatsApp consent are tracked and recovered automatically.', 'cartrenew-for-woocommerce' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_KEY ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cr_store_id"><?php esc_html_e( 'CartRenew Store ID', 'cartrenew-for-woocommerce' ); ?></label></th>
						<td><input type="text" id="cr_store_id" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[store_id]" value="<?php echo esc_attr( $s['store_id'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cr_api_key"><?php esc_html_e( 'API Key', 'cartrenew-for-woocommerce' ); ?></label></th>
						<td><input type="password" id="cr_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cr_api_base"><?php esc_html_e( 'API Base URL', 'cartrenew-for-woocommerce' ); ?></label></th>
						<td>
							<input type="url" id="cr_api_base" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_base]" value="<?php echo esc_attr( $s['api_base'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Leave default unless CartRenew support tells you otherwise.', 'cartrenew-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cr_abandon_minutes"><?php esc_html_e( 'Abandonment wait time (minutes)', 'cartrenew-for-woocommerce' ); ?></label></th>
						<td><input type="number" min="5" max="180" id="cr_abandon_minutes" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[abandon_minutes]" value="<?php echo esc_attr( $s['abandon_minutes'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled', 'cartrenew-for-woocommerce' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?> />
								<?php esc_html_e( 'Track carts and send recovery messages', 'cartrenew-for-woocommerce' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
