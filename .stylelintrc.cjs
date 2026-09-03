module.exports = {
	extends: [
		'stylelint-config-standard-scss',
		'@stylistic/stylelint-config',
		'stylelint-plugin-defensive-css/configs/recommended',
	],
	plugins: [
		'stylelint-order',
		'stylelint-high-performance-animation',
	],
	rules: {
		'@stylistic/indentation': 'tab',

		// Every pre-existing hand-written color in this project (om-light.css,
		// om-dark.css before the SCSS migration) used uppercase, full-length
		// hex — matching that established convention rather than the tool's
		// lowercase/shorthand defaults.
		'@stylistic/color-hex-case': 'upper',
		'color-hex-length': 'long',

		'order/properties-alphabetical-order': true,
		'plugin/no-low-performance-animation-properties': true,

		// This plugin's rules assume a general-purpose stylesheet where the
		// author controls the markup. This project is the opposite: it's an
		// admin color-scheme override, so every selector MUST target WP
		// core's actual DOM (#adminmenu, #wpadminbar, bare h1/h2/h3, input
		// [type=...], etc) — that's not a "leaky" pattern here, it's the job.
		'defensive-css/require-pure-selectors': null,

		// Same reasoning as require-pure-selectors above, one level more
		// specific: WP core itself isn't consistently kebab-case in its own
		// markup (e.g. `#dashboard_activity`, `#dashboard_quick_press`,
		// `.ac_match`) — this file has to match those verbatim, underscores
		// and all, not rename them to a convention core doesn't use.
		'selector-class-pattern': null,
		'selector-id-pattern': null,

		// WP core's own admin CSS styles :focus directly (not :focus-visible)
		// everywhere — see wp-admin/css/colors/_admin.scss's own `a:focus`
		// rules. Matching core's convention keeps focus-ring behavior
		// consistent across themed and non-themed admin screens; switching
		// only this plugin to :focus-visible would make OM-themed focus
		// rings behave differently from every other focus ring in wp-admin.
		'defensive-css/require-focus-visible': null,

		// WP core's own hover rules for these exact elements (adminmenu,
		// adminbar, buttons) aren't wrapped in `(hover: hover)` either —
		// WP's own JS already manages the touch-vs-mouse distinction here
		// (see the `.opensub`/`.hover` classes toggled in common.js/
		// admin-menu behavior). Wrapping only OM's color overrides in a
		// hover-media-query would desync them from core's unwrapped
		// defaults on touch devices, producing partially-themed UI instead
		// of fixing anything.
		'defensive-css/no-accidental-hover': null,

		// Cosmetic-only; not worth enforcing a blank-line policy between the
		// two token maps in _tokens.scss.
		'scss/dollar-variable-empty-line-before': null,
	},
};
