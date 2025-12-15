<?php
/**
 * Admin settings page for VK Bogo Language Switcher.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register submenu page under Bogo's top-level menu.
 */
function vkbls_register_style_submenu() {
	// Bogo registers a top-level menu with the slug `bogo`.
	// Hook in after Bogo (priority 20) to ensure the parent exists.
	add_submenu_page(
		'bogo',
		__( 'スタイル設定', 'vk-bogo-language-switcher' ),
		__( 'スタイル', 'vk-bogo-language-switcher' ),
		'manage_options',
		'vkbls-style',
		'vkbls_render_style_settings_page'
	);
}
add_action( 'admin_menu', 'vkbls_register_style_submenu', 20 );

/**
 * Render the style settings page.
 */
function vkbls_render_style_settings_page() {
	$settings          = vkbls_get_settings();
	$current_style     = $settings['style'];
	$current_direction = $settings['direction'];
	$hide_current      = (bool) $settings['hide-current'];
	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'スタイル設定', 'vk-bogo-language-switcher' ) ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'vkbls_style_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html( __( '表示スタイル', 'vk-bogo-language-switcher' ) ); ?></th>
					<td>
						<label>
							<input type="radio" name="vk-bogo-setting[style]" value="flag-text" <?php checked( $current_style, 'flag-text' ); ?> />
							<?php echo esc_html( __( '国旗とテキスト', 'vk-bogo-language-switcher' ) ); ?>
						</label>
						<br />
						<label>
							<input type="radio" name="vk-bogo-setting[style]" value="text" <?php checked( $current_style, 'text' ); ?> />
							<?php echo esc_html( __( 'テキストのみ', 'vk-bogo-language-switcher' ) ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( __( '並び方向', 'vk-bogo-language-switcher' ) ); ?></th>
					<td>
						<label>
							<input type="radio" name="vk-bogo-setting[direction]" value="horizontal" <?php checked( $current_direction, 'horizontal' ); ?> />
							<?php echo esc_html( __( '横並び', 'vk-bogo-language-switcher' ) ); ?>
						</label>
						<br />
						<label>
							<input type="radio" name="vk-bogo-setting[direction]" value="vertical" <?php checked( $current_direction, 'vertical' ); ?> />
							<?php echo esc_html( __( '縦積み', 'vk-bogo-language-switcher' ) ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( __( '表示中の言語を非表示', 'vk-bogo-language-switcher' ) ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="vk-bogo-setting[hide-current]" value="1" <?php checked( $hide_current ); ?> />
							<?php echo esc_html( __( '表示中の言語をスイッチャーに表示しない', 'vk-bogo-language-switcher' ) ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Register style-related options.
 */
function vkbls_register_settings() {
	register_setting(
		'vkbls_style_group',
		'vk-bogo-setting',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'vkbls_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'vkbls_register_settings' );

/**
 * Sanitize style option.
 *
 * @param string $value Submitted value.
 * @return string
 */
function vkbls_sanitize_style_option( $value ) {
	$allowed = array( 'text', 'flag-text' );

	if ( in_array( $value, $allowed, true ) ) {
		return $value;
	}

	// 互換対応: 旧値が保存されている場合は国旗とテキストに寄せる.
	if ( 'flag' === $value || 'text-all' === $value ) {
		return 'flag-text';
	}

	return 'flag-text';
}

/**
 * Sanitize direction option.
 *
 * @param string $value Submitted value.
 * @return string
 */
function vkbls_sanitize_direction_option( $value ) {
	$allowed = array( 'horizontal', 'vertical' );
	return in_array( $value, $allowed, true ) ? $value : 'horizontal';
}

/**
 * Sanitize hide-current option.
 *
 * @param mixed $value Submitted value.
 * @return int
 */
function vkbls_sanitize_hide_current( $value ) {
	return ! empty( $value ) ? 1 : 0;
}

/**
 * Sanitize settings array.
 *
 * @param mixed $settings Submitted settings.
 * @return array
 */
function vkbls_sanitize_settings( $settings ) {
	$defaults = array(
		'style'        => 'flag-text',
		'direction'    => 'horizontal',
		'hide-current' => 0,
	);

	$settings = is_array( $settings ) ? $settings : array();

	$settings['style']        = vkbls_sanitize_style_option( isset( $settings['style'] ) ? $settings['style'] : $defaults['style'] );
	$settings['direction']    = vkbls_sanitize_direction_option( isset( $settings['direction'] ) ? $settings['direction'] : $defaults['direction'] );
	$settings['hide-current'] = vkbls_sanitize_hide_current( isset( $settings['hide-current'] ) ? $settings['hide-current'] : $defaults['hide-current'] );

	return wp_parse_args( $settings, $defaults );
}

/**
 * Get merged/sanitized settings with backward compatibility.
 *
 * @return array
 */
function vkbls_get_settings() {
	$defaults = array(
		'style'        => 'flag-text',
		'direction'    => 'horizontal',
		'hide-current' => 0,
	);

	$saved = get_option( 'vk-bogo-setting', array() );

	// Backward compatibility: migrate legacy options if present.
	if ( is_array( $saved ) ) {
		if ( ! isset( $saved['style'] ) ) {
			$legacy = get_option( 'vk-bogo-language-style', null );
			if ( null !== $legacy ) {
				$saved['style'] = $legacy;
			}
		}
		if ( ! isset( $saved['direction'] ) ) {
			$legacy = get_option( 'vk-bogo-language-direction', null );
			if ( null !== $legacy ) {
				$saved['direction'] = $legacy;
			}
		}
		if ( ! isset( $saved['hide-current'] ) ) {
			$legacy = get_option( 'vk-bogo-language-hide-current', null );
			if ( null !== $legacy ) {
				$saved['hide-current'] = $legacy;
			}
		}
	}

	$saved = is_array( $saved ) ? $saved : array();
	$saved = wp_parse_args( $saved, $defaults );

	return vkbls_sanitize_settings( $saved );
}
