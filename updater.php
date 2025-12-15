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
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Plugin file path.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_slug     = dirname( plugin_basename( $plugin_file ) );
		$this->current_version = $this->get_plugin_version();

		// Check for updates.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ), 10, 1 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'maybe_shortcircuit_download' ), 10, 3 );
	}

	/**
	 * Get plugin version from main plugin file.
	 *
	 * @return string Plugin version.
	 */
	private function get_plugin_version() {
		$plugin_data = get_file_data( $this->plugin_file, array( 'Version' => 'Version' ), 'plugin' );
		return isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0.0.0';
	}

	/**
	 * Get latest release from GitHub.
	 *
	 * @return object|false Release object or false on failure.
	 */
	private function get_latest_release() {
		$cache_key = 'vkbls_latest_release';
		$release   = get_transient( $cache_key );

		if ( false === $release ) {
			$api_url = sprintf(
				'https://api.github.com/repos/%s/%s/releases/latest',
				$this->owner,
				$this->repo
			);

			$response = wp_remote_get(
				$api_url,
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/vnd.github.v3+json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$body = wp_remote_retrieve_body( $response );
			$code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $code ) {
				return false;
			}

			$release = json_decode( $body );

			if ( ! $release || ! isset( $release->tag_name ) ) {
				return false;
			}

			// Cache for 6 hours (shorter cache for faster updates).
			set_transient( $cache_key, $release, 6 * HOUR_IN_SECONDS );
		}

		return $release;
	}

	/**
	 * Get download URL from release assets.
	 *
	 * @param object $release Release object.
	 * @return string|false Download URL or false.
	 */
	private function get_download_url( $release ) {
		if ( ! isset( $release->assets ) || ! is_array( $release->assets ) ) {
			return false;
		}

		foreach ( $release->assets as $asset ) {
			if ( isset( $asset->browser_download_url ) && 'zip' === substr( $asset->name, -3 ) ) {
				return $asset->browser_download_url;
			}
		}

		return false;
	}

	/**
	 * Compare version strings.
	 *
	 * @param string $version1 Version 1.
	 * @param string $version2 Version 2.
	 * @return int -1 if version1 < version2, 0 if equal, 1 if version1 > version2.
	 */
	private function version_compare( $version1, $version2 ) {
		return version_compare( $version1, $version2 );
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient Update transient.
	 * @return object Modified transient.
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$plugin_file = plugin_basename( $this->plugin_file );

		// Get current version from transient or plugin file.
		$current_version = isset( $transient->checked[ $plugin_file ] )
			? $transient->checked[ $plugin_file ]
			: $this->current_version;

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$latest_version = ltrim( $release->tag_name, 'v' );
		$download_url  = $this->get_download_url( $release );

		if ( ! $download_url ) {
			return $transient;
		}

		// Check if update is available.
		if ( $this->version_compare( $current_version, $latest_version ) < 0 ) {
			$transient->response[ $plugin_file ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $plugin_file,
				'new_version' => $latest_version,
				'url'         => $release->html_url,
				'package'     => $download_url,
			);
		}

		return $transient;
	}

	/**
	 * Get plugin information for update screen.
	 *
	 * @param false|object|array $result Result object or array.
	 * @param string             $action Action type.
	 * @param object             $args   Arguments.
	 * @return false|object Plugin information or false.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$plugin_file = plugin_basename( $this->plugin_file );

		// Check if this is our plugin by slug or plugin file.
		if ( ! isset( $args->slug ) || ( $this->plugin_slug !== $args->slug && $plugin_file !== $args->slug ) ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$latest_version = ltrim( $release->tag_name, 'v' );
		$download_url  = $this->get_download_url( $release );

		if ( ! $download_url ) {
			return $result;
		}

		$info                 = new stdClass();
		$info->name           = 'VK Bogo Language Switcher';
		$info->slug           = $this->plugin_slug;
		$info->version        = $latest_version;
		$info->download_link  = $download_url;
		$info->requires       = '6.6';
		$info->tested         = '6.9';
		$info->requires_php    = '7.4';
		$info->author         = '<a href="https://github.com/vektor-inc">Vektor,Inc.</a>';
		$info->homepage       = $release->html_url;
		$info->sections       = array(
			'description' => $release->body ? wp_kses_post( $release->body ) : '',
		);

		return $info;
	}

	/**
	 * Short-circuit download if needed.
	 *
	 * @param bool   $reply   Whether to bail without returning the package.
	 * @param string $package Package URL.
	 * @param object $upgrader WP_Upgrader instance.
	 * @return bool|WP_Error True to bail, false to continue, WP_Error on error.
	 */
	public function maybe_shortcircuit_download( $reply, $package, $upgrader ) {
		// Only handle our plugin.
		if ( ! isset( $upgrader->skin->plugin ) ) {
			return $reply;
		}

		$plugin_file = plugin_basename( $this->plugin_file );
		if ( $plugin_file !== $upgrader->skin->plugin ) {
			return $reply;
		}

		// Allow WordPress to handle the download.
		return $reply;
	}
}

// Initialize updater (will be called from main plugin file with correct path).
