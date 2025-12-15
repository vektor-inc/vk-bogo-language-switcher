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
	$font_size         = isset( $settings['font-size'] ) ? $settings['font-size'] : '';
	$vertical_padding  = isset( $settings['vertical-padding'] ) ? $settings['vertical-padding'] : '';
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
							<?php echo esc_html( __( 'テキストボタン', 'vk-bogo-language-switcher' ) ); ?>
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
				<tr>
					<th scope="row"><?php echo esc_html( __( '文字サイズ', 'vk-bogo-language-switcher' ) ); ?></th>
					<td>
						<input type="number" name="vk-bogo-setting[font-size]" value="<?php echo esc_attr( $font_size ); ?>" min="0" step="1" class="small-text" />
						<span>px</span>
						<p class="description"><?php echo esc_html( __( '文字サイズをpx単位で指定します。空欄の場合はデフォルトサイズが適用されます。', 'vk-bogo-language-switcher' ) ); ?></p>
					</td>
				</tr>
				<tr class="vkbls-vertical-padding-row" style="<?php echo 'text' === $current_style ? '' : 'display: none;'; ?>">
					<th scope="row"><?php echo esc_html( __( 'テキストボタン内の上下余白', 'vk-bogo-language-switcher' ) ); ?></th>
					<td>
						<input type="number" name="vk-bogo-setting[vertical-padding]" value="<?php echo esc_attr( $vertical_padding ); ?>" min="0" step="1" class="small-text" />
						<span>px</span>
						<p class="description"><?php echo esc_html( __( 'テキストボタン内の上下余白をpx単位で指定します。空欄の場合はデフォルト値（2px）が適用されます。', 'vk-bogo-language-switcher' ) ); ?></p>
					</td>
				</tr>
			</table>
			<script>
			(function() {
				var styleRadios = document.querySelectorAll('input[name="vk-bogo-setting[style]"]');
				var paddingRow = document.querySelector('.vkbls-vertical-padding-row');
				function togglePaddingRow() {
					var textSelected = document.querySelector('input[name="vk-bogo-setting[style]"][value="text"]').checked;
					paddingRow.style.display = textSelected ? '' : 'none';
				}
				styleRadios.forEach(function(radio) {
					radio.addEventListener('change', togglePaddingRow);
				});
			})();
			</script>
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
 * Sanitize font-size option.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function vkbls_sanitize_font_size( $value ) {
	if ( empty( $value ) ) {
		return '';
	}

	$value = absint( $value );
	return $value > 0 ? (string) $value : '';
}

/**
 * Sanitize vertical-padding option.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function vkbls_sanitize_vertical_padding( $value ) {
	if ( empty( $value ) ) {
		return '';
	}

	$value = absint( $value );
	return $value >= 0 ? (string) $value : '';
}

/**
 * Sanitize settings array.
 *
 * @param mixed $settings Submitted settings.
 * @return array
 */
function vkbls_sanitize_settings( $settings ) {
	$defaults = array(
		'style'           => 'flag-text',
		'direction'       => 'horizontal',
		'hide-current'    => 0,
		'font-size'       => '',
		'vertical-padding' => '',
	);

	$settings = is_array( $settings ) ? $settings : array();

	$settings['style']           = vkbls_sanitize_style_option( isset( $settings['style'] ) ? $settings['style'] : $defaults['style'] );
	$settings['direction']       = vkbls_sanitize_direction_option( isset( $settings['direction'] ) ? $settings['direction'] : $defaults['direction'] );
	$settings['hide-current']    = vkbls_sanitize_hide_current( isset( $settings['hide-current'] ) ? $settings['hide-current'] : $defaults['hide-current'] );
	$settings['font-size']       = vkbls_sanitize_font_size( isset( $settings['font-size'] ) ? $settings['font-size'] : $defaults['font-size'] );
	$settings['vertical-padding'] = vkbls_sanitize_vertical_padding( isset( $settings['vertical-padding'] ) ? $settings['vertical-padding'] : $defaults['vertical-padding'] );

	return wp_parse_args( $settings, $defaults );
}

/**
 * Get merged/sanitized settings with backward compatibility.
 *
 * @return array
 */
function vkbls_get_settings() {
	$defaults = array(
		'style'           => 'flag-text',
		'direction'       => 'horizontal',
		'hide-current'    => 0,
		'font-size'       => '',
		'vertical-padding' => '',
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
