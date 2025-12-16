<?php
/**
 * GitHub Updater for VK Bogo Language Switcher
 *
 * @package VK_Bogo_Language_Switcher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin updater class.
 */
class VK_BLS_Updater {

	/**
	 * GitHub repository owner.
	 *
	 * @var string
	 */
	private $owner = 'vektor-inc';

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private $repo = 'vk-bogo-language-switcher';

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Plugin data.
	 *
	 * @var array
	 */
	private $plugin_data;

	/**
	 * GitHub API result.
	 *
	 * @var object
	 */
	private $github_api_result;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Plugin file path.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;

		// Check for updates.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'set_transient' ), 10, 1 );
		add_filter( 'plugins_api', array( $this, 'set_plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );

		// Add admin notice for debugging.
		if ( isset( $_GET['vkbls_debug'] ) && current_user_can( 'manage_options' ) ) {
			add_action( 'admin_notices', array( $this, 'debug_notice' ) );
		}
	}

	/**
	 * Get information regarding our plugin from WordPress.
	 */
	private function init_plugin_data() {
		$this->plugin_slug = plugin_basename( $this->plugin_file );
		$this->plugin_data = get_plugin_data( $this->plugin_file );
	}

	/**
	 * Get information regarding our plugin from GitHub.
	 */
	private function get_repository_info() {
		if ( ! empty( $this->github_api_result ) ) {
			return;
		}

		$cache_key = 'vkbls_latest_release';

		// Clear cache if needed (for debugging).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $_GET['vkbls_clear_cache'] ) ) {
			delete_transient( $cache_key );
		}

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			$this->github_api_result = $cached;
			return;
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases',
			$this->owner,
			$this->repo
		);

		$args = array(
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'VK BLS Updater: API request failed - ' . $response->get_error_message() );
			}
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$response_body = wp_remote_retrieve_body( $response );
				error_log( sprintf( 'VK BLS Updater: API returned status code %d. URL: %s. Response: %s', $response_code, $url, $response_body ) );
			}
			return;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$releases      = json_decode( $response_body );

		if ( ! is_array( $releases ) || empty( $releases ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'VK BLS Updater: No releases found or invalid response. Response: ' . $response_body );
			}
			return;
		}

		$this->github_api_result = $releases[0];

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'VK BLS Updater: Latest release tag: ' . $this->github_api_result->tag_name );
			error_log( 'VK BLS Updater: Assets count: ' . ( isset( $this->github_api_result->assets ) ? count( $this->github_api_result->assets ) : 0 ) );
		}

		// Cache for 6 hours.
		set_transient( $cache_key, $this->github_api_result, 6 * HOUR_IN_SECONDS );
	}

	/**
	 * Push in plugin version information to get the update notification.
	 *
	 * @param object $transient Plugin update transient.
	 * @return object Updated plugin update transient.
	 */
	public function set_transient( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$this->init_plugin_data();
		$this->get_repository_info();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'VK BLS Updater: Plugin slug: ' . $this->plugin_slug );
			error_log( 'VK BLS Updater: Checked plugins: ' . print_r( array_keys( $transient->checked ), true ) );
		}

		if ( empty( $this->github_api_result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'VK BLS Updater: No GitHub API result' );
			}
			return $transient;
		}

		// Check if this is our plugin.
		if ( ! isset( $transient->checked[ $this->plugin_slug ] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'VK BLS Updater: Plugin not found in checked list. Slug: ' . $this->plugin_slug );
			}
			return $transient;
		}

		$current_version = $transient->checked[ $this->plugin_slug ];
		$latest_version  = ltrim( $this->github_api_result->tag_name, 'v' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'VK BLS Updater: Current version: %s, Latest version: %s', $current_version, $latest_version ) );
		}

		$do_update = version_compare( $latest_version, $current_version );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'VK BLS Updater: Version comparison result: ' . $do_update );
		}

		if ( $do_update > 0 ) {
			if ( ! isset( $this->github_api_result->assets ) || ! is_array( $this->github_api_result->assets ) || empty( $this->github_api_result->assets[0] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'VK BLS Updater: No assets found in release' );
				}
				return $transient;
			}

			$package = $this->github_api_result->assets[0]->browser_download_url;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'VK BLS Updater: Package URL: ' . $package );
			}

			$obj              = new stdClass();
			$obj->slug        = $this->plugin_slug;
			$obj->new_version = $latest_version;
			$obj->url         = isset( $this->plugin_data['PluginURI'] ) && ! empty( $this->plugin_data['PluginURI'] ) ? $this->plugin_data['PluginURI'] : $this->github_api_result->html_url;
			$obj->package     = $package;

			$transient->response[ $this->plugin_slug ] = $obj;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'VK BLS Updater: Update added to response' );
			}
		}

		return $transient;
	}

	/**
	 * Push in plugin version information to display in the details lightbox.
	 *
	 * @param object|bool $false Plugin information.
	 * @param string      $action Action type.
	 * @param object      $response Response object.
	 * @return object|bool Updated plugin information.
	 */
	public function set_plugin_info( $false, $action, $response ) {
		$this->init_plugin_data();
		$this->get_repository_info();

		if ( empty( $response->slug ) || $response->slug !== $this->plugin_slug ) {
			return $false;
		}

		if ( empty( $this->github_api_result ) ) {
			return $false;
		}

		if ( ! isset( $this->github_api_result->assets ) || ! is_array( $this->github_api_result->assets ) || empty( $this->github_api_result->assets[0] ) ) {
			return $false;
		}

		$response->last_updated = $this->github_api_result->published_at;
		$response->slug         = $this->plugin_slug;
		$response->plugin_name  = $this->plugin_data['Name'];
		$response->version      = ltrim( $this->github_api_result->tag_name, 'v' );
		$response->author       = $this->plugin_data['Author'];
		$response->homepage     = $this->plugin_data['PluginURI'];

		$response->sections = array(
			'description' => $this->plugin_data['Description'],
		);

		$response->download_link = $this->github_api_result->assets[0]->browser_download_url;

		return $response;
	}

	/**
	 * Perform additional actions to successfully install our plugin.
	 *
	 * @param bool  $true Installation result.
	 * @param array $hook_extra Hook extra information.
	 * @param array $result Installation result.
	 * @return array Updated installation result.
	 */
	public function post_install( $true, $hook_extra, $result ) {
		global $wp_filesystem;

		$this->init_plugin_data();

		// Avoid deprecated dirname(null) error and only process if plugin_slug is set.
		if ( ! empty( $this->plugin_slug ) ) {
			$plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->plugin_slug );
			$wp_filesystem->move( $result['destination'], $plugin_folder );
			$result['destination'] = $plugin_folder;

			if ( is_plugin_active( $this->plugin_slug ) ) {
				activate_plugin( $this->plugin_slug );
			}
		}

		return $result;
	}

	/**
	 * Display debug information in admin notice.
	 */
	public function debug_notice() {
		$this->init_plugin_data();
		$this->get_repository_info();

		$transient = get_site_transient( 'update_plugins' );
		$checked   = isset( $transient->checked ) ? $transient->checked : array();

		$current_version = isset( $checked[ $this->plugin_slug ] ) ? $checked[ $this->plugin_slug ] : 'Not found';
		$latest_version  = ! empty( $this->github_api_result ) ? ltrim( $this->github_api_result->tag_name, 'v' ) : 'Not found';

		$has_update = isset( $transient->response[ $this->plugin_slug ] );

		?>
		<div class="notice notice-info">
			<h3>VK BLS Updater Debug Info</h3>
			<p><strong>Plugin Slug:</strong> <?php echo esc_html( $this->plugin_slug ); ?></p>
			<p><strong>Current Version:</strong> <?php echo esc_html( $current_version ); ?></p>
			<p><strong>Latest Version:</strong> <?php echo esc_html( $latest_version ); ?></p>
			<p><strong>Has Update:</strong> <?php echo $has_update ? 'Yes' : 'No'; ?></p>
			<?php if ( ! empty( $this->github_api_result ) ) : ?>
				<p><strong>Release Tag:</strong> <?php echo esc_html( $this->github_api_result->tag_name ); ?></p>
				<p><strong>Assets Count:</strong> <?php echo isset( $this->github_api_result->assets ) ? count( $this->github_api_result->assets ) : 0; ?></p>
				<?php if ( isset( $this->github_api_result->assets[0] ) ) : ?>
					<p><strong>Package URL:</strong> <?php echo esc_html( $this->github_api_result->assets[0]->browser_download_url ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p><strong>GitHub API Result:</strong> Not available</p>
			<?php endif; ?>
			<p><strong>Checked Plugins:</strong> <?php echo esc_html( implode( ', ', array_keys( $checked ) ) ); ?></p>
		</div>
		<?php
	}
}

// Initialize updater (will be called from main plugin file with correct path).
