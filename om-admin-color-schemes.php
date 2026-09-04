<?php
/**
 * Plugin Name: OM Admin Color Schemes
 * Description: Registers "OM System", "OM Light", and "OM Dark" admin color
 *              schemes based on OM Performance Marketers brand colors.
 *              Users pick their preferred mode from Profile > Admin Color
 *              Scheme, same as any built-in WordPress scheme. "OM System"
 *              follows the browser/OS light-dark preference automatically.
 * Version:     1.0.2
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
	 * Cookie that remembers which OM scheme a logged-in user has active,
	 * so the login screen (which has no user context to read a
	 * preference from directly — see enqueue_login_scheme_style()) can
	 * still theme itself. Kept in sync by sync_login_scheme_cookie().
	 */
	const LOGIN_SCHEME_COOKIE = 'om_admin_color_scheme';

	/**
	 * Wires up the admin_init, admin_enqueue_scripts, profile_update, and
	 * login_enqueue_scripts hooks. Call once, e.g.
	 * OM_Admin_Color_Schemes::init().
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_color_schemes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_warning_script' ) );
		add_action( 'profile_update', array( __CLASS__, 'sync_login_scheme_cookie' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_login_scheme_style' ) );
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
	 * Keeps LOGIN_SCHEME_COOKIE in sync with the current user's own
	 * admin_color, so enqueue_login_scheme_style() below can theme the
	 * login screen without any user context to read a preference from —
	 * there's no logged-in user yet at that point. Hooked to
	 * profile_update, which fires after a save completes, not on every
	 * admin page load: an existing OM-scheme user who never revisits
	 * their Profile screen again simply won't get this cookie set until
	 * they do. Accepted trade-off, not a bug — see CLAUDE.md.
	 *
	 * Guarded to the user's own profile save specifically. profile_update
	 * fires for ANY user's save, including an admin editing someone
	 * ELSE's profile — without this guard, that admin's own browser would
	 * get a cookie based on a different user's scheme change.
	 *
	 * @param int $user_id ID of the user whose profile was just saved.
	 */
	public static function sync_login_scheme_cookie( $user_id ) {

		if ( headers_sent() || get_current_user_id() !== (int) $user_id ) {
			return;
		}

		$scheme = get_user_option( 'admin_color', $user_id );
		$path   = defined( 'SITECOOKIEPATH' ) ? SITECOOKIEPATH : COOKIEPATH;
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

		if ( array_key_exists( $scheme, self::get_schemes() ) ) {
			// 400 days is the practical maximum browsers honor regardless
			// of what's requested (Chrome enforces this cap; others
			// follow) — since this cookie is rewritten on every profile
			// save, an active user effectively never sees it expire.
			setcookie( self::LOGIN_SCHEME_COOKIE, $scheme, time() + 400 * DAY_IN_SECONDS, $path, $domain, is_ssl(), true );
		} else {
			// Switched to a core scheme (or something not ours) — clear
			// it, so a stale OM preference doesn't keep theming the login
			// screen for someone who's since moved off OM schemes.
			setcookie( self::LOGIN_SCHEME_COOKIE, '', time() - DAY_IN_SECONDS, $path, $domain, is_ssl(), true );
		}
	}

	/**
	 * Themes the login screen from LOGIN_SCHEME_COOKIE. WordPress's own
	 * admin color scheme system never reaches wp-login.php — core's
	 * `colors` stylesheet (the one wp_admin_css_color() plugs into) is
	 * only ever enqueued from wp-admin/admin-header.php and two legacy
	 * media-iframe contexts, confirmed by reading core directly — so this
	 * loads its own stylesheet independently rather than relying on that
	 * mechanism. Falls back to om-system (follows the browser/OS
	 * light-dark preference) for a visitor with no cookie at all: first
	 * visit, cleared cookies, a different browser/device, etc.
	 */
	public static function enqueue_login_scheme_style() {

		$schemes = self::get_schemes();
		$scheme  = isset( $_COOKIE[ self::LOGIN_SCHEME_COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::LOGIN_SCHEME_COOKIE ] ) ) : '';

		if ( ! array_key_exists( $scheme, $schemes ) ) {
			$scheme = 'om-system';
		}

		$file = plugin_dir_path( __FILE__ ) . 'src/' . $schemes[ $scheme ]['css'];

		wp_enqueue_style(
			'om-admin-color-schemes-login',
			plugin_dir_url( __FILE__ ) . 'src/' . $schemes[ $scheme ]['css'],
			array( 'login' ),
			file_exists( $file ) ? filemtime( $file ) : false
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
