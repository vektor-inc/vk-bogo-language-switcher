<?php
/**
 * Plugin Name: VK Bogo Language Switcher
 * Description: Bogo の言語切替えメニューにスタイルオプションを追加します。
 * Version: 0.0.4
 * Author: Vektor,Inc.
 * Text Domain: vk-bogo-language-switcher
 * Domain Path: /languages
 * Requires Plugins: bogo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/admin/settings-page.php';
require_once plugin_dir_path( __FILE__ ) . 'updater.php';

// Initialize updater.
new VK_BLS_Updater( __FILE__ );

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
 * Enqueue block styles (editor iframe / site editor).
 */
function vkbls_enqueue_block_styles() {
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
add_action( 'enqueue_block_assets', 'vkbls_enqueue_block_styles' );

/**
 * Enqueue block editor-only assets (post/page editor & サイトエディター).
 */
function vkbls_enqueue_editor_styles() {
	$editor_path = plugin_dir_path( __FILE__ ) . 'assets/css/editor.css';
	$editor_url  = plugin_dir_url( __FILE__ ) . 'assets/css/editor.css';
	$script_path = plugin_dir_path( __FILE__ ) . 'assets/js/editor.js';
	$script_url  = plugin_dir_url( __FILE__ ) . 'assets/js/editor.js';

	if ( file_exists( $editor_path ) ) {
		wp_enqueue_style(
			'vkbls-editor',
			$editor_url,
			array(),
			filemtime( $editor_path )
		);
	}

	if ( file_exists( $script_path ) ) {
		wp_enqueue_script(
			'vkbls-editor-js',
			$script_url,
			array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor' ),
			filemtime( $script_path ),
			true
		);

		$class_string = vkbls_get_switcher_classes();
		wp_add_inline_script(
			'vkbls-editor-js',
			sprintf(
				'window.vkblsSwitcherClasses = %s;',
				wp_json_encode( $class_string )
			),
			'before'
		);
	}
}
add_action( 'enqueue_block_editor_assets', 'vkbls_enqueue_editor_styles' );

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
 * Append class string to switcher markup.
 *
 * @param string $output       Switcher HTML.
 * @param string $class_string Space-separated classes to add.
 * @return string
 */
function vkbls_append_switcher_classes_to_markup( $output, $class_string ) {
	if ( '' === trim( $class_string ) ) {
		return $output;
	}

	return preg_replace_callback(
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
}

/**
 * Append inline style to switcher markup.
 *
 * @param string $output Switcher HTML.
 * @param string $style Inline style string (e.g., "font-size: 16px;").
 * @return string
 */
function vkbls_append_switcher_style_to_markup( $output, $style ) {
	if ( '' === trim( $style ) ) {
		return $output;
	}

	return preg_replace_callback(
		'/<ul([^>]*)>/',
		static function ( $matches ) use ( $style ) {
			$attrs = $matches[1];

			// Check if style attribute already exists.
			if ( preg_match( '/style=(["\'])([^"\']*)(\1)/', $attrs, $style_matches ) ) {
				// Remove existing font-size and --vkbls-vertical-padding if present, then add new ones.
				$existing_style = $style_matches[2];
				// Remove font-size and --vkbls-vertical-padding declarations (with or without semicolon).
				$existing_style = preg_replace( '/font-size\s*:\s*[^;]+;?\s*/i', '', $existing_style );
				$existing_style = preg_replace( '/--vkbls-vertical-padding\s*:\s*[^;]+;?\s*/i', '', $existing_style );
				$existing_style = trim( $existing_style );
				// Combine existing style with new styles.
				$new_style = trim( $existing_style . ( $existing_style ? ' ' : '' ) . $style );
				$new_style = esc_attr( $new_style );
				$replacement = 'style=' . $style_matches[1] . $new_style . $style_matches[1];
				$attrs = preg_replace( '/style=(["\'])([^"\']*)(\1)/', $replacement, $attrs );
				return '<ul' . $attrs . '>';
			} else {
				// Add new style attribute.
				$escaped_style = esc_attr( $style );
				return '<ul' . $attrs . ' style="' . $escaped_style . '">';
			}
		},
		$output,
		1
	);
}

/**
 * Add style class and inline style to Bogo language switcher markup (frontend/shortcode).
 *
 * @param string $output Switcher HTML.
 * @param array  $args   Args passed to Bogo.
 * @return string
 */
function vkbls_add_switcher_style_class( $output, $args ) {
	$class_string = vkbls_get_switcher_classes();
	$output       = vkbls_append_switcher_classes_to_markup( $output, $class_string );

	// Add inline styles.
	$settings = function_exists( 'vkbls_get_settings' ) ? vkbls_get_settings() : array();
	$styles   = array();

	// Add font-size if set.
	$font_size = isset( $settings['font-size'] ) && ! empty( $settings['font-size'] ) ? $settings['font-size'] : '';
	if ( ! empty( $font_size ) ) {
		$styles[] = 'font-size: ' . absint( $font_size ) . 'px;';
	}

	// Add vertical-padding CSS variable if text style is selected and value is set.
	$style = isset( $settings['style'] ) ? $settings['style'] : 'flag-text';
	if ( 'text' === $style ) {
		$vertical_padding = isset( $settings['vertical-padding'] ) && ! empty( $settings['vertical-padding'] ) ? $settings['vertical-padding'] : '';
		if ( ! empty( $vertical_padding ) ) {
			$styles[] = '--vkbls-vertical-padding: ' . absint( $vertical_padding ) . 'px;';
		}
	}

	if ( ! empty( $styles ) ) {
		$style_string = implode( ' ', $styles );
		$output       = vkbls_append_switcher_style_to_markup( $output, $style_string );
	}

	return $output;
}
add_filter( 'bogo_language_switcher', 'vkbls_add_switcher_style_class', 10, 2 );

/**
 * Add classes and inline style when rendering the Bogo language switcher block.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Block data.
 * @return string
 */
function vkbls_add_switcher_class_to_block( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'bogo/language-switcher' !== $block['blockName'] ) {
		return $block_content;
	}

	$class_string  = vkbls_get_switcher_classes();
	$block_content = vkbls_append_switcher_classes_to_markup( $block_content, $class_string );

	// Add inline styles.
	$settings = function_exists( 'vkbls_get_settings' ) ? vkbls_get_settings() : array();
	$styles   = array();

	// Add font-size if set.
	$font_size = isset( $settings['font-size'] ) && ! empty( $settings['font-size'] ) ? $settings['font-size'] : '';
	if ( ! empty( $font_size ) ) {
		$styles[] = 'font-size: ' . absint( $font_size ) . 'px;';
	}

	// Add vertical-padding CSS variable if text style is selected and value is set.
	$style = isset( $settings['style'] ) ? $settings['style'] : 'flag-text';
	if ( 'text' === $style ) {
		$vertical_padding = isset( $settings['vertical-padding'] ) && ! empty( $settings['vertical-padding'] ) ? $settings['vertical-padding'] : '';
		if ( ! empty( $vertical_padding ) ) {
			$styles[] = '--vkbls-vertical-padding: ' . absint( $vertical_padding ) . 'px;';
		}
	}

	if ( ! empty( $styles ) ) {
		$style_string  = implode( ' ', $styles );
		$block_content = vkbls_append_switcher_style_to_markup( $block_content, $style_string );
	}

	return $block_content;
}
add_filter( 'render_block', 'vkbls_add_switcher_class_to_block', 10, 2 );

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
