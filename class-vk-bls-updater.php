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
		$cached    = get_transient( $cache_key );
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
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$releases      = json_decode( $response_body );

		if ( ! is_array( $releases ) || empty( $releases ) ) {
			return;
		}

		$this->github_api_result = $releases[0];

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

		if ( empty( $this->github_api_result ) ) {
			return $transient;
		}

		// Check if this is our plugin.
		if ( ! isset( $transient->checked[ $this->plugin_slug ] ) ) {
			return $transient;
		}

		$current_version = $transient->checked[ $this->plugin_slug ];
		$latest_version  = ltrim( $this->github_api_result->tag_name, 'v' );

		$do_update = version_compare( $latest_version, $current_version );

		if ( $do_update > 0 ) {
			if ( ! isset( $this->github_api_result->assets ) || ! is_array( $this->github_api_result->assets ) || empty( $this->github_api_result->assets[0] ) ) {
				return $transient;
			}

			$package = $this->github_api_result->assets[0]->browser_download_url;

			$obj              = new stdClass();
			$obj->slug        = $this->plugin_slug;
			$obj->new_version = $latest_version;
			$obj->url         = isset( $this->plugin_data['PluginURI'] ) && ! empty( $this->plugin_data['PluginURI'] ) ? $this->plugin_data['PluginURI'] : $this->github_api_result->html_url;
			$obj->package     = $package;

			$transient->response[ $this->plugin_slug ] = $obj;
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
}

// Initialize updater (will be called from main plugin file with correct path).
