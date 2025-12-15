<?php
/**
 * Plugin Name: VK Bogo Language Switcher
 * Description: Bogo の言語切替えメニューにスタイルオプションを追加します。
 * Version: 0.1.0
 * Author: Vektor,Inc.
 * Text Domain: vk-bogo-language-switcher
 * Domain Path: /languages
 * Requires Plugins: bogo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/admin/settings-page.php';

/**
 * Enqueue frontend styles for FSE themes.
 */
function vkbls_enqueue_frontend_styles() {
	$style_path = plugin_dir_path( __FILE__ ) . 'assets/css/style.css';
	$style_url  = plugin_dir_url( __FILE__ ) . 'assets/css/style.css';

	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'vkbls-frontend',
			$style_url,
			array(),
			filemtime( $style_path )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'vkbls_enqueue_frontend_styles' );

/**
 * Enqueue block editor styles (post/page editor & サイトエディター).
 */
function vkbls_enqueue_editor_styles() {
	// ensure only in block editors (post/page editor or site editor).
	if ( ! is_admin() || ! wp_should_load_block_editor_scripts_and_styles() ) {
		return;
	}

	$style_path = plugin_dir_path( __FILE__ ) . 'assets/css/style.css';
	$style_url  = plugin_dir_url( __FILE__ ) . 'assets/css/style.css';
	$editor_path = plugin_dir_path( __FILE__ ) . 'assets/css/editor.css';
	$editor_url  = plugin_dir_url( __FILE__ ) . 'assets/css/editor.css';

	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'vkbls-frontend',
			$style_url,
			array(),
			filemtime( $style_path )
		);
	}

	if ( file_exists( $editor_path ) ) {
		wp_enqueue_style(
			'vkbls-editor',
			$editor_url,
			array(),
			filemtime( $editor_path )
		);
	}
}
add_action( 'enqueue_block_assets', 'vkbls_enqueue_editor_styles' );

/**
 * Get classes to append to the language switcher <ul>.
 *
 * @return string Class attribute value (space-separated).
 */
function vkbls_get_switcher_classes() {
	$settings  = function_exists( 'vkbls_get_settings' ) ? vkbls_get_settings() : array();
	$style     = isset( $settings['style'] ) ? $settings['style'] : 'flag-text';
	$direction = isset( $settings['direction'] ) ? $settings['direction'] : 'horizontal';
	$hide      = ! empty( $settings['hide-current'] );
	$classes   = array(
		'switcher--' . $direction,
	);
	if ( 'text' === $style ) {
		$classes[] = 'switcher--text';
	}
	if ( $hide ) {
		$classes[] = 'switcher--current-hidden';
	}

	$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

	return implode( ' ', $classes );
}

/**
 * Add style class to Bogo language switcher markup.
 *
 * @param string $output Switcher HTML.
 * @param array  $args   Args passed to Bogo.
 * @return string
 */
function vkbls_add_switcher_style_class( $output, $args ) {
	$class_string = vkbls_get_switcher_classes();

	if ( '' === trim( $class_string ) ) {
		return $output;
	}

	// Append our class string to the existing <ul> class attribute.
	$output = preg_replace_callback(
		'/<ul([^>]*)class=(["\'])([^"\']*)(\2)/',
		static function ( $matches ) use ( $class_string ) {
			$existing = trim( $matches[3] );
			$new      = trim( $existing . ' ' . $class_string );
			$new      = esc_attr( $new );
			return sprintf( '<ul%1$sclass=%2$s%3$s%2$s', $matches[1], $matches[2], $new );
		},
		$output,
		1
	);

	return $output;
}
add_filter( 'bogo_language_switcher', 'vkbls_add_switcher_style_class', 10, 2 );

/**
 * Optionally hide current language from switcher links.
 *
 * @param array $links Switcher links.
 * @param array $args  Args.
 * @return array
 */
function vkbls_hide_current_language( $links, $args ) {
	$settings = function_exists( 'vkbls_get_settings' ) ? vkbls_get_settings() : array();

	if ( empty( $settings['hide-current'] ) ) {
		return $links;
	}

	$current_locale = get_locale();

	$filtered = array_filter(
		$links,
		static function ( $link ) use ( $current_locale ) {
			return isset( $link['locale'] ) && $link['locale'] !== $current_locale;
		}
	);

	return array_values( $filtered );
}
add_filter( 'bogo_language_switcher_links', 'vkbls_hide_current_language', 10, 2 );
