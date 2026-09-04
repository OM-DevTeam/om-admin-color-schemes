<?php
/**
 * Plugin Name: OM Admin Color Schemes
 * Description: Registers "OM System", "OM Light", and "OM Dark" admin color
 *              schemes based on OM Performance Marketers brand colors.
 *              Users pick their preferred mode from Profile > Admin Color
 *              Scheme, same as any built-in WordPress scheme. "OM System"
 *              follows the browser/OS light-dark preference automatically.
 * Version:     1.0.1
 * Author:      OM Performance Marketers
 *
 * Install: this file must end up as a top-level file directly inside
 * wp-content/mu-plugins/ (mu-plugins only auto-loads top-level .php files,
 * not files in subdirectories), with its accompanying "src" folder (the
 * compiled CSS) alongside it in that same directory.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the three OM admin color schemes.
 *
 * wp_admin_css_color() must run before WordPress renders the color scheme
 * picker on the Profile screen, so scheme registration is hooked to
 * admin_init.
 */
class OM_Admin_Color_Schemes {

	/**
	 * Icon-color trios shared by the light-menu schemes (System and Light
	 * both use the same #0D2132 menu background as their base color).
	 */
	const LIGHT_MENU_ICON_COLORS = array(
		'base'    => 'rgba(225, 225, 225, 0.7)',
		'focus'   => '#FFFFFF',
		'current' => '#0D2132',
	);

	/**
	 * Icon-color trio for the Dark scheme's darker menu background.
	 */
	const DARK_MENU_ICON_COLORS = array(
		'base'    => 'rgba(197, 197, 197, 0.7)',
		'focus'   => '#FFFFFF',
		'current' => '#0D2132',
	);

	/**
	 * Swatch-preview colors shared by System and Light (both preview the
	 * same light-menu palette on the Profile screen).
	 */
	const LIGHT_SWATCH_COLORS = array( '#0D2132', '#153652', '#46C3FC', '#DB0B28' );

	/**
	 * Swatch-preview colors for Dark.
	 */
	const DARK_SWATCH_COLORS = array( '#08141E', '#183C5A', '#46C3FC', '#F41F3D' );

	/**
	 * Color scheme slugs whose canvas can (at least potentially) render
	 * dark — used to gate the editor-brightness warning script so it's
	 * never enqueued for users on OM Light or a core scheme.
	 */
	const DARK_CAPABLE_SCHEMES = array( 'om-dark', 'om-system' );

	/**
	 * Wires up the admin_init and admin_enqueue_scripts hooks. Call once,
	 * e.g. OM_Admin_Color_Schemes::init().
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_color_schemes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_warning_script' ) );
	}

	/**
	 * Registers each scheme defined in get_schemes() via wp_admin_css_color().
	 */
	public static function register_color_schemes() {

		$base_url = plugin_dir_url( __FILE__ ) . 'src/';

		foreach ( self::get_schemes() as $slug => $scheme ) {
			wp_admin_css_color(
				$slug,
				$scheme['label'],
				$base_url . $scheme['css'],
				$scheme['colors'],
				$scheme['icon_colors']
			);
		}
	}

	/**
	 * Enqueues the editor-brightness warning script for users on a
	 * dark-capable scheme (om-dark, or om-system — which may or may not
	 * currently be resolving to dark, checked client-side via
	 * prefers-color-scheme since PHP has no way to know that). Never
	 * enqueued at all for om-light or a core scheme, since neither has
	 * anything to warn about. Hooked to admin_enqueue_scripts.
	 */
	public static function enqueue_editor_warning_script() {

		$scheme = get_user_option( 'admin_color' );

		if ( ! in_array( $scheme, self::DARK_CAPABLE_SCHEMES, true ) ) {
			return;
		}

		$file = plugin_dir_path( __FILE__ ) . 'js/editor-brightness-warning.js';

		wp_enqueue_script(
			'om-admin-color-schemes-editor-warning',
			plugin_dir_url( __FILE__ ) . 'js/editor-brightness-warning.js',
			array(),
			file_exists( $file ) ? filemtime( $file ) : false,
			true
		);

		wp_localize_script(
			'om-admin-color-schemes-editor-warning',
			'omEditorWarning',
			array(
				'scheme'  => $scheme,
				'strings' => array(
					'title'         => __( "This screen isn't dark-themed", 'om-admin-color-schemes' ),
					'body'          => __( "The post editor doesn't support Dark Mode, so it always displays with a light background, no matter your OM Dark color scheme. If you'd like, now's a good time to turn down your screen's actual brightness before continuing.", 'om-admin-color-schemes' ),
					'checkbox'      => __( "Don't warn me again", 'om-admin-color-schemes' ),
					'continueLabel' => __( 'Continue to Editor', 'om-admin-color-schemes' ),
					'cancel'        => __( 'Stay Here', 'om-admin-color-schemes' ),
				),
			)
		);
	}

	/**
	 * Single source of truth for the three schemes' registration data, so
	 * register_color_schemes() doesn't repeat the wp_admin_css_color() call
	 * three times over.
	 *
	 * @return array<string, array{label: string, css: string, colors: string[], icon_colors: array}>
	 */
	private static function get_schemes() {
		return array(
			'om-system' => array(
				'label'       => __( 'OM System', 'om-admin-color-schemes' ),
				'css'         => 'om-system.css',
				'colors'      => self::LIGHT_SWATCH_COLORS,
				'icon_colors' => self::LIGHT_MENU_ICON_COLORS,
			),
			'om-light'  => array(
				'label'       => __( 'OM Light', 'om-admin-color-schemes' ),
				'css'         => 'om-light.css',
				'colors'      => self::LIGHT_SWATCH_COLORS,
				'icon_colors' => self::LIGHT_MENU_ICON_COLORS,
			),
			'om-dark'   => array(
				'label'       => __( 'OM Dark', 'om-admin-color-schemes' ),
				'css'         => 'om-dark.css',
				'colors'      => self::DARK_SWATCH_COLORS,
				'icon_colors' => self::DARK_MENU_ICON_COLORS,
			),
		);
	}
}

OM_Admin_Color_Schemes::init();
