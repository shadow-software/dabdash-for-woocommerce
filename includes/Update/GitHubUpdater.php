<?php
/**
 * Self-update from GitHub Releases.
 *
 * The plugin is distributed as a versioned ZIP attached to a GitHub Release (see
 * .github/workflows/release.yml). This class teaches WordPress to treat that
 * release feed as an update channel, so a site running 1.0.0 sees 1.0.1 in
 * Dashboard → Updates within the hour and can one-click it — or take it
 * automatically, with no wp.org round trip.
 *
 * Design notes, each of which is a bug avoided:
 *
 * - **Never hammer the API.** GitHub allows 60 unauthenticated requests per hour
 *   per IP, and that IP is shared by every site on the host. The check is cached
 *   for six hours, and a *failed* check is cached too (briefly) so an outage or a
 *   rate-limit does not turn into a request on every single admin page load.
 *
 * - **Fail silently and stay installed.** Every failure path leaves the
 *   transient WordPress handed us untouched. A network blip must never remove
 *   the plugin from the updates list, and must never produce a fatal or a
 *   warning on someone's dashboard.
 *
 * - **Only ever upgrade.** version_compare with '>' — a yanked release or a
 *   malformed tag can never trigger a downgrade.
 *
 * - **Take the built asset, not the source tarball.** GitHub's automatic
 *   `zipball_url` contains the whole repo (tests, CI, composer) with a
 *   commit-hash directory name, which would install as a broken, differently-named
 *   plugin. We look for the release asset the workflow built and skip the release
 *   entirely if it is missing.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Update;

defined( 'ABSPATH' ) || exit;

/**
 * Wires GitHub Releases into the WordPress update system.
 */
final class GitHubUpdater {

	/**
	 * How long a successful check is cached.
	 */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * How long a failed check is cached. Shorter than success — we want to
	 * recover reasonably quickly — but long enough that an outage cannot become
	 * a request storm.
	 */
	private const FAILURE_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * The GitHub repository, "owner/name".
	 *
	 * @var string
	 */
	private $repo;

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * The plugin's currently installed version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin basename, e.g. "dabdash-sync/dabdash-sync.php".
	 *
	 * @var string
	 */
	private $basename;

	/**
	 * Cache key for this plugin's update payload.
	 *
	 * @var string
	 */
	private $cache_key;

	/**
	 * @param string $repo    GitHub "owner/name".
	 * @param string $file    Absolute path to the main plugin file.
	 * @param string $version Installed version.
	 */
	public function __construct( $repo, $file, $version ) {
		$this->repo      = $repo;
		$this->file      = $file;
		$this->version   = $version;
		$this->basename  = plugin_basename( $file );
		$this->cache_key = 'dabdash_sync_update_' . md5( $repo );
	}

	/**
	 * Register the update hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject' ) );
		add_filter( 'plugins_api', array( $this, 'details' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'normalise_directory' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'flush' ), 10, 2 );
	}

	/**
	 * Add our release to the set of available plugin updates.
	 *
	 * @param mixed $transient The update_plugins transient.
	 * @return mixed
	 */
	public function inject( $transient ) {
		// WordPress occasionally passes a non-object here on a cold cache. Handing
		// back something malformed breaks updates for EVERY plugin, so bail on
		// anything unexpected rather than trying to repair it.
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->latest_release();

		if ( null === $release ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		// Strictly greater — never downgrade.
		if ( version_compare( $release['version'], $this->version, '>' ) ) {
			$transient->response[ $this->basename ] = (object) array(
				'slug'        => dirname( $this->basename ),
				'plugin'      => $this->basename,
				'new_version' => $release['version'],
				'package'     => $release['package'],
				'url'         => $release['url'],
				'tested'      => $release['tested'],
				'icons'       => $release['icons'],
			);

			return $transient;
		}

		// Up to date. Record it in no_update so the "Enable auto-updates" control
		// renders on the Plugins screen — without this the plugin looks to
		// WordPress like it has no update source at all and the link is hidden.
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		$transient->no_update[ $this->basename ] = (object) array(
			'slug'        => dirname( $this->basename ),
			'plugin'      => $this->basename,
			'new_version' => $this->version,
			'package'     => '',
			'url'         => $release['url'],
		);

		return $transient;
	}

	/**
	 * Populate the "View details" modal.
	 *
	 * @param mixed  $result The result object or array.
	 * @param string $action The API action being performed.
	 * @param object $args   Arguments to the API call.
	 * @return mixed
	 */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || dirname( $this->basename ) !== $args->slug ) {
			return $result;
		}

		$release = $this->latest_release();

		if ( null === $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'DabDash Sync for WordPress',
			'slug'          => dirname( $this->basename ),
			'version'       => $release['version'],
			'author'        => '<a href="https://shadowsoftware.com/">Shadow Software LLC</a>',
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'trunk'         => $release['package'],
			'requires'      => '6.4',
			'requires_php'  => '8.0',
			'tested'        => $release['tested'],
			'last_updated'  => $release['published'],
			'sections'      => array(
				'description' => esc_html__(
					'Keeps WordPress customers in step with DabDash — verification status, loyalty balance and marketing consent — with DabDash as the source of truth.',
					'dabdash-sync-for-wordpress'
				),
				'changelog'   => $release['notes'],
			),
		);
	}

	/**
	 * Ensure the unpacked directory is named after the plugin.
	 *
	 * A GitHub asset can unpack to any folder name. If that name is not the
	 * plugin's existing directory, WordPress installs it *alongside* the current
	 * copy instead of upgrading it — leaving two plugins, one active and stale.
	 * Renaming the source before install is what makes the upgrade an upgrade.
	 *
	 * @param string $source        Path to the unpacked source.
	 * @param string $remote_source Path to the downloaded archive's root.
	 * @param object $upgrader      The upgrader instance.
	 * @param array  $args          Extra arguments, including the target plugin.
	 * @return string|\WP_Error
	 */
	public function normalise_directory( $source, $remote_source, $upgrader, $args = array() ) {
		// Only touch our own upgrade.
		if ( ! isset( $args['plugin'] ) || $args['plugin'] !== $this->basename ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . dirname( $this->basename );

		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}

		if ( ! $wp_filesystem->move( $source, $desired, true ) ) {
			return new \WP_Error(
				'dabdash_sync_rename_failed',
				esc_html__( 'Could not prepare the downloaded update for installation.', 'dabdash-sync-for-wordpress' )
			);
		}

		return trailingslashit( $desired );
	}

	/**
	 * Drop the cached release after any plugin update runs.
	 *
	 * @param object $upgrader The upgrader instance.
	 * @param array  $data     Information about the process that ran.
	 * @return void
	 */
	public function flush( $upgrader, $data ) {
		if ( isset( $data['action'], $data['type'] ) && 'update' === $data['action'] && 'plugin' === $data['type'] ) {
			delete_transient( $this->cache_key );
		}
	}

	/**
	 * Fetch (and cache) the latest release from GitHub.
	 *
	 * @return array<string, mixed>|null Release data, or null when unavailable.
	 */
	private function latest_release() {
		$cached = get_transient( $this->cache_key );

		// A cached failure is stored as the string 'none' so we can tell it apart
		// from "nothing cached", which is what false already means.
		if ( 'none' === $cached ) {
			return null;
		}

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->repo ),
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'DabDashSync/' . $this->version . '; ' . home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $this->cache_key, 'none', self::FAILURE_TTL );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_transient( $this->cache_key, 'none', self::FAILURE_TTL );
			return null;
		}

		// Never offer a draft or a pre-release as an update.
		if ( ! empty( $body['draft'] ) || ! empty( $body['prerelease'] ) ) {
			set_transient( $this->cache_key, 'none', self::FAILURE_TTL );
			return null;
		}

		$package = $this->find_asset( isset( $body['assets'] ) ? $body['assets'] : array() );

		// No built asset means no installable update. Deliberately NOT falling back
		// to zipball_url: that would install the repo, not the plugin.
		if ( null === $package ) {
			set_transient( $this->cache_key, 'none', self::FAILURE_TTL );
			return null;
		}

		$release = array(
			'version'   => ltrim( (string) $body['tag_name'], 'v' ),
			'package'   => $package,
			'url'       => isset( $body['html_url'] ) ? $body['html_url'] : '',
			'notes'     => isset( $body['body'] ) ? wp_kses_post( $body['body'] ) : '',
			'published' => isset( $body['published_at'] ) ? $body['published_at'] : '',
			'tested'    => $this->tested_up_to(),
			'icons'     => array(),
		);

		set_transient( $this->cache_key, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Pick the installable ZIP from a release's assets.
	 *
	 * @param array $assets Release assets from the API.
	 * @return string|null Download URL, or null if there is no suitable asset.
	 */
	private function find_asset( $assets ) {
		if ( ! is_array( $assets ) ) {
			return null;
		}

		foreach ( $assets as $asset ) {
			if ( ! isset( $asset['name'], $asset['browser_download_url'] ) ) {
				continue;
			}

			if ( '.zip' === strtolower( substr( $asset['name'], -4 ) ) ) {
				return $asset['browser_download_url'];
			}
		}

		return null;
	}

	/**
	 * The WordPress version this plugin is tested against, read from the
	 * readme so it has exactly one home.
	 *
	 * @return string
	 */
	private function tested_up_to() {
		$readme = plugin_dir_path( $this->file ) . 'readme.txt';

		if ( ! is_readable( $readme ) ) {
			return '';
		}

		$handle = fopen( $readme, 'r' );

		if ( ! $handle ) {
			return '';
		}

		$tested = '';

		// The header is in the first few lines; do not read a whole file for it.
		for ( $i = 0; $i < 20; $i++ ) {
			$line = fgets( $handle );

			if ( false === $line ) {
				break;
			}

			if ( preg_match( '/^Tested up to:\s*(.+)$/i', trim( $line ), $m ) ) {
				$tested = trim( $m[1] );
				break;
			}
		}

		fclose( $handle );

		return $tested;
	}
}
