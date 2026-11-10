<?php
/**
 * One-shot strain / SEO enrich admin screen.
 *
 * Settings → DabDash Strain Enrich — not a recurring sync job.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Admin;

use DabDashSync\Api\SdkFactory;
use DabDashSync\Catalog\StrainEnricher;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI to preview or apply DabDash strain copy onto Woo products.
 */
final class StrainEnrichPage {

	public const PAGE = 'dabdash-woo-strain-enrich';

	/**
	 * Last run summary for the request (set by handle_post).
	 *
	 * @var array<string, mixed>|null
	 */
	private $last_summary = null;

	/**
	 * Hook the screen.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
	}

	/**
	 * Register under Settings.
	 *
	 * @return void
	 */
	public function menu() {
		add_options_page(
			__( 'DabDash Strain Enrich', 'dabdash-for-woocommerce' ),
			__( 'DabDash Strain Enrich', 'dabdash-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Run dry-run or apply from POST.
	 *
	 * @return void
	 */
	public function handle_post() {
		if ( ! isset( $_POST['dabdash_woo_strain_enrich'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'dabdash_woo_strain_enrich' );

		$mode       = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'dry_run';
		$overwrite  = ! empty( $_POST['overwrite'] );
		$exact_only = ! empty( $_POST['exact_only'] );
		$limit      = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 0;

		$dry_run = ( 'apply' !== $mode );

		if ( 'apply' === $mode && empty( $_POST['confirm_apply'] ) ) {
			add_settings_error(
				'dabdash_woo_strain',
				'confirm',
				__( 'Check “I understand this will overwrite product descriptions” before applying.', 'dabdash-for-woocommerce' ),
				'error'
			);
			return;
		}

		if ( ! SdkFactory::is_configured() ) {
			add_settings_error(
				'dabdash_woo_strain',
				'config',
				__( 'Configure the DabDash API base URL and token under Settings → DabDash Sync first.', 'dabdash-for-woocommerce' ),
				'error'
			);
			return;
		}

		if ( ! function_exists( 'wc_get_products' ) ) {
			add_settings_error(
				'dabdash_woo_strain',
				'woo',
				__( 'WooCommerce must be active to enrich products.', 'dabdash-for-woocommerce' ),
				'error'
			);
			return;
		}

		// Large catalogs: allow the request to finish.
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged,WordPress.PHP.NoSilencedErrors.Discouraged -- one-shot admin job.
			@set_time_limit( 300 );
		}

		$enricher = new StrainEnricher();
		$summary  = $enricher->run(
			array(
				'dry_run'    => $dry_run,
				'overwrite'  => $overwrite,
				'exact_only' => $exact_only,
				'limit'      => $limit,
			)
		);

		$this->last_summary = $summary;

		$message = sprintf(
			/* translators: 1: scanned, 2: matched, 3: updated, 4: skipped, 5: errors */
			__( 'Strain enrich %1$s — scanned %2$d, matched %3$d, updated %4$d, skipped %5$d, errors %6$d.', 'dabdash-for-woocommerce' ),
			$dry_run ? __( 'dry-run', 'dabdash-for-woocommerce' ) : __( 'apply', 'dabdash-for-woocommerce' ),
			(int) $summary['scanned'],
			(int) $summary['matched'],
			(int) $summary['updated'],
			(int) $summary['skipped'],
			(int) $summary['errors']
		);

		add_settings_error(
			'dabdash_woo_strain',
			'done',
			$message,
			$summary['errors'] > 0 ? 'warning' : 'success'
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		settings_errors( 'dabdash_woo_strain' );
		$configured   = SdkFactory::is_configured();
		$summary      = $this->last_summary;
		$settings_url = admin_url( 'options-general.php?page=' . SettingsPage::PAGE );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'DabDash Strain Enrich', 'dabdash-for-woocommerce' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'One-time job: match WooCommerce products to the DabDash strain library and write readable long descriptions and excerpts for SEO/AEO. This is not the hourly customer sync.',
					'dabdash-for-woocommerce'
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( '← DabDash Sync settings', 'dabdash-for-woocommerce' ); ?></a>
			</p>

			<?php if ( ! $configured ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					echo esc_html__(
						'API base URL and access token are required. Save them on the DabDash Sync settings screen first.',
						'dabdash-for-woocommerce'
					);
					?>
				</p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'dabdash_woo_strain_enrich' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Mode', 'dabdash-for-woocommerce' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:0.5em;">
								<input type="radio" name="mode" value="dry_run" checked="checked" />
								<?php echo esc_html__( 'Dry-run (preview matches and copy — no writes)', 'dabdash-for-woocommerce' ); ?>
							</label>
							<label style="display:block;">
								<input type="radio" name="mode" value="apply" />
								<?php echo esc_html__( 'Apply (write description + excerpt on matched products)', 'dabdash-for-woocommerce' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Options', 'dabdash-for-woocommerce' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:0.5em;">
								<input type="checkbox" name="exact_only" value="1" />
								<?php echo esc_html__( 'Exact name matches only (skip partial / contains matches)', 'dabdash-for-woocommerce' ); ?>
							</label>
							<label style="display:block;margin-bottom:0.5em;">
								<input type="checkbox" name="overwrite" value="1" />
								<?php echo esc_html__( 'Overwrite products already enriched by this job', 'dabdash-for-woocommerce' ); ?>
							</label>
							<label style="display:block;margin-bottom:0.5em;">
								<input type="checkbox" name="confirm_apply" value="1" />
								<?php echo esc_html__( 'I understand Apply will overwrite product long description and short description (excerpt)', 'dabdash-for-woocommerce' ); ?>
							</label>
							<p>
								<label for="dabdash_strain_limit"><?php echo esc_html__( 'Limit (0 = all published/private products)', 'dabdash-for-woocommerce' ); ?></label><br />
								<input name="limit" id="dabdash_strain_limit" type="number" min="0" step="1" value="0" class="small-text" />
							</p>
						</td>
					</tr>
				</table>
				<?php
				submit_button(
					__( 'Run strain enrich', 'dabdash-for-woocommerce' ),
					'primary',
					'dabdash_woo_strain_enrich',
					false,
					$configured ? array() : array( 'disabled' => 'disabled' )
				);
				?>
			</form>

			<h2><?php echo esc_html__( 'How matching works', 'dabdash-for-woocommerce' ); ?></h2>
			<ol>
				<li><?php echo esc_html__( 'Calls the tenant Catalog API strain_lookup (same library as DabDash product create).', 'dabdash-for-woocommerce' ); ?></li>
				<li><?php echo esc_html__( 'Tries the product title, then a size-stripped base name (e.g. “Blue Dream - 3.5G” → “Blue Dream”).', 'dabdash-for-woocommerce' ); ?></li>
				<li><?php echo esc_html__( 'Prefers case-insensitive exact name, then shortest prefix/contains partial — same order as DabDash vendor bulk enrich.', 'dabdash-for-woocommerce' ); ?></li>
				<li><?php echo esc_html__( 'Builds a short readable long description + excerpt from type, cannabinoids, and short_description (no keyword stuffing).', 'dabdash-for-woocommerce' ); ?></li>
			</ol>
			<p class="description">
				<?php
				echo esc_html__(
					'PHP note: this plugin line currently requires PHP 8.5 (composer.json / plugin header). Hosts still on PHP 8.3 (e.g. WDK 1.1.0) need a PHP upgrade before installing current releases, or an 8.3-capable build.',
					'dabdash-for-woocommerce'
				);
				?>
			</p>

			<?php if ( is_array( $summary ) ) : ?>
				<h2><?php echo esc_html__( 'Last run summary', 'dabdash-for-woocommerce' ); ?></h2>
				<ul>
					<li><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Scanned: %d', 'dabdash-for-woocommerce' ), (int) $summary['scanned'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Matched: %d', 'dabdash-for-woocommerce' ), (int) $summary['matched'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Updated / would update: %d', 'dabdash-for-woocommerce' ), (int) $summary['updated'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Skipped: %d', 'dabdash-for-woocommerce' ), (int) $summary['skipped'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Errors: %d', 'dabdash-for-woocommerce' ), (int) $summary['errors'] ) ); ?></li>
				</ul>

				<?php if ( ! empty( $summary['rows'] ) && is_array( $summary['rows'] ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Product', 'dabdash-for-woocommerce' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'dabdash-for-woocommerce' ); ?></th>
								<th><?php echo esc_html__( 'Strain', 'dabdash-for-woocommerce' ); ?></th>
								<th><?php echo esc_html__( 'Confidence', 'dabdash-for-woocommerce' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'dabdash-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$rows = array_slice( $summary['rows'], 0, 200 );
							foreach ( $rows as $row ) :
								if ( ! is_array( $row ) ) {
									continue;
								}
								$strain_label = '';
								if ( isset( $row['strain'] ) && is_array( $row['strain'] ) ) {
									$strain_label = (string) ( $row['strain']['name'] ?? '' );
									if ( ! empty( $row['strain']['id'] ) ) {
										$strain_label .= ' (#' . (int) $row['strain']['id'] . ')';
									}
								}
								?>
								<tr>
									<td>
										<?php
										echo esc_html( (string) ( $row['product_name'] ?? '' ) );
										if ( ! empty( $row['product_id'] ) ) {
											echo ' <code>#' . esc_html( (string) (int) $row['product_id'] ) . '</code>';
										}
										?>
									</td>
									<td><code><?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?></code></td>
									<td><?php echo esc_html( $strain_label ); ?></td>
									<td><?php echo esc_html( (string) ( $row['confidence'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $row['message'] ?? '' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( count( $summary['rows'] ) > 200 ) : ?>
						<p class="description"><?php echo esc_html__( 'Showing the first 200 rows.', 'dabdash-for-woocommerce' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
