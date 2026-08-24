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

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
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
