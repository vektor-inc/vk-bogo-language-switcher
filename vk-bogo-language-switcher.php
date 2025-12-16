<?php
/**
 * Plugin Name: VK Bogo Language Switcher
 * Description: Bogo の言語切替えメニューにスタイルオプションを追加します。
 * Version: 0.0.16
 * Author: Vektor,Inc.
 * Text Domain: vk-bogo-language-switcher
 * Domain Path: /languages
 * Requires Plugins: bogo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/admin/settings-page.php';
require_once plugin_dir_path( __FILE__ ) . 'class-vk-bls-updater.php';

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

		// Add dynamic inline styles.
		vkbls_add_dynamic_inline_styles( 'vkbls-frontend' );
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

		// Add dynamic inline styles.
		vkbls_add_dynamic_inline_styles( 'vkbls-frontend' );
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
 * Add dynamic inline styles based on settings.
 *
 * @param string $handle Style handle.
 */
function vkbls_add_dynamic_inline_styles( $handle ) {
	$settings = function_exists( 'vkbls_get_settings' ) ? vkbls_get_settings() : array();
	$css_rules = array();

	// Add font-size if set.
	$font_size = isset( $settings['font-size'] ) && ! empty( $settings['font-size'] ) ? $settings['font-size'] : '';
	if ( ! empty( $font_size ) ) {
		$css_rules[] = 'ul.bogo-language-switcher { font-size: ' . absint( $font_size ) . 'px; }';
	}

	// Add gap variables (applies to all styles).
	$gap_decls = array();
	$row_gap   = isset( $settings['row-gap'] ) && '' !== $settings['row-gap'] ? $settings['row-gap'] : '';
	if ( '' !== $row_gap ) {
		$gap_decls[] = '--vkbls-row-gap: ' . absint( $row_gap ) . 'px;';
	}
	$column_gap = isset( $settings['column-gap'] ) && '' !== $settings['column-gap'] ? $settings['column-gap'] : '';
	if ( '' !== $column_gap ) {
		$gap_decls[] = '--vkbls-column-gap: ' . absint( $column_gap ) . 'px;';
	}
	if ( ! empty( $gap_decls ) ) {
		$css_rules[] = 'ul.bogo-language-switcher { ' . implode( ' ', $gap_decls ) . ' }';
	}

	// Add vertical-padding CSS variable if text style is selected and value is set.
	$style = isset( $settings['style'] ) ? $settings['style'] : 'flag-text';
	if ( 'text' === $style ) {
		$text_decls = array();

		$vertical_padding = isset( $settings['vertical-padding'] ) && ! empty( $settings['vertical-padding'] ) ? $settings['vertical-padding'] : '';
		if ( '' !== $vertical_padding ) {
			$text_decls[] = '--vkbls-vertical-padding: ' . absint( $vertical_padding ) . 'px;';
		}

		$btn_min_width = isset( $settings['btn-min-width'] ) && '' !== $settings['btn-min-width'] ? $settings['btn-min-width'] : '';
		if ( '' !== $btn_min_width ) {
			$text_decls[] = '--vkbls-btn-min-width: ' . absint( $btn_min_width ) . 'px;';
		}

		if ( ! empty( $text_decls ) ) {
			$css_rules[] = 'ul.bogo-language-switcher.switcher--text { ' . implode( ' ', $text_decls ) . ' }';
		}
	}

	if ( ! empty( $css_rules ) ) {
		$inline_css = implode( "\n", $css_rules );
		wp_add_inline_style( $handle, $inline_css );
	}
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
 * Add style class to Bogo language switcher markup (frontend/shortcode).
 *
 * @param string $output Switcher HTML.
 * @param array  $args   Args passed to Bogo.
 * @return string
 */
function vkbls_add_switcher_style_class( $output, $args ) {
	$class_string = vkbls_get_switcher_classes();
	return vkbls_append_switcher_classes_to_markup( $output, $class_string );
}
add_filter( 'bogo_language_switcher', 'vkbls_add_switcher_style_class', 10, 2 );

/**
 * Add classes when rendering the Bogo language switcher block.
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
	return vkbls_append_switcher_classes_to_markup( $block_content, $class_string );
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
