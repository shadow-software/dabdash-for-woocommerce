<?php
/**
 * Settings screen under Settings → DabDash Sync.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Admin;

use DabDashSync\Api\SdkFactory;
use DabDashSync\Settings;
use DabDashSync\Sync\FieldMap;

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings UI (scaffold — connect + field map diagnostics).
 */
final class SettingsPage {

	public const PAGE = 'dabdash-woo';

	/**
	 * Hook the settings screen.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'save' ) );
	}

	/**
	 * Register the options page.
	 *
	 * @return void
	 */
	public function menu() {
		add_options_page(
			__( 'DabDash Sync', 'dabdash-for-woocommerce' ),
			__( 'DabDash Sync', 'dabdash-for-woocommerce' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Persist settings from a POST.
	 *
	 * @return void
	 */
	public function save() {
		if ( ! isset( $_POST['dabdash_woo_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'dabdash_woo_settings' );

		$api_base = isset( $_POST['api_base'] ) ? esc_url_raw( wp_unslash( $_POST['api_base'] ) ) : '';
		$token    = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';
		$enabled  = ! empty( $_POST['sync_enabled'] );

		$api_base = untrailingslashit( $api_base );

		if ( '' !== $api_base && ! Settings::is_allowed_api_base( $api_base ) ) {
			add_settings_error(
				'dabdash_woo',
				'bad_host',
				__( 'API base URL must be a DabDash host (*.dabdash.com). Local hosts are only allowed when WP_DEBUG is on.', 'dabdash-for-woocommerce' ),
				'error'
			);
			return;
		}

		if ( $enabled && ( '' === $api_base || ( '' === $token && '' === Settings::access_token() ) ) ) {
			add_settings_error(
				'dabdash_woo',
				'incomplete',
				__( 'Enable sync only after both an API base URL and access token are set. Customer data will be sent to DabDash.', 'dabdash-for-woocommerce' ),
				'error'
			);
			return;
		}

		// Empty token field means "leave unchanged" so the secret is not wiped
		// when saving other settings.
		$patch = array(
			'api_base'     => $api_base,
			'sync_enabled' => $enabled,
		);

		if ( '' !== $token ) {
			$patch['access_token'] = $token;
		}

		Settings::update( $patch );

		add_settings_error(
			'dabdash_woo',
			'saved',
			__( 'Settings saved.', 'dabdash-for-woocommerce' ),
			'success'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::all();
		$map      = FieldMap::describe();

		settings_errors( 'dabdash_woo' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'DabDash Sync', 'dabdash-for-woocommerce' ); ?></h1>
			<p><?php echo esc_html__( 'DabDash is the source of truth for verification, loyalty, and consent. WooCommerce / WordPress mirrors what it is allowed to see. Enabling sync transmits customer fields listed below to your DabDash tenant.', 'dabdash-for-woocommerce' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'dabdash_woo_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="api_base"><?php echo esc_html__( 'Tenant API base URL', 'dabdash-for-woocommerce' ); ?></label></th>
						<td>
							<input name="api_base" id="api_base" type="url" class="regular-text" value="<?php echo esc_attr( $settings['api_base'] ); ?>" placeholder="https://your-store.dabdash.com" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="access_token"><?php echo esc_html__( 'API access token', 'dabdash-for-woocommerce' ); ?></label></th>
						<td>
							<input name="access_token" id="access_token" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $settings['access_token'] ? '••••••••' : '' ); ?>" />
							<p class="description"><?php echo esc_html__( 'Leave blank to keep the current token.', 'dabdash-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Sync', 'dabdash-for-woocommerce' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="sync_enabled" value="1" <?php checked( $settings['sync_enabled'] ); ?> />
								<?php echo esc_html__( 'Enable background sync', 'dabdash-for-woocommerce' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'dabdash-for-woocommerce' ), 'primary', 'dabdash_woo_save' ); ?>
			</form>

			<h2><?php echo esc_html__( 'Connection', 'dabdash-for-woocommerce' ); ?></h2>
			<p>
				<?php
				echo SdkFactory::is_configured()
					? esc_html__( 'API base and token are set. Pull/push jobs will use shadow-software/dabdash-php-sdk.', 'dabdash-for-woocommerce' )
					: esc_html__( 'Not configured yet.', 'dabdash-for-woocommerce' );
				?>
			</p>

			<h2><?php echo esc_html__( 'Field map', 'dabdash-for-woocommerce' ); ?></h2>
			<p><?php echo esc_html__( 'Classification used by the sync resolver (canonical / contact / consent / never).', 'dabdash-for-woocommerce' ); ?></p>
			<?php foreach ( $map as $class => $fields ) : ?>
				<h3><?php echo esc_html( strtoupper( (string) $class ) ); ?></h3>
				<ul>
					<?php foreach ( $fields as $field ) : ?>
						<li><code><?php echo esc_html( (string) $field ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
