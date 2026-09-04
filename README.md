# OM Admin Color Schemes

A WordPress must-use plugin that adds an "OM" admin color scheme, based on
OM Performance Marketers brand colors, to the Profile screen's color
scheme picker.

## What you get

Three new options under **Users → Profile → Admin Color Scheme**,
alongside WordPress's own built-in schemes:

- **OM System** — follows your browser/OS light or dark mode setting
  automatically, and switches live if you change it.
- **OM Light** — always light.
- **OM Dark** — always dark.

If you're on OM Dark (or OM System while your browser/OS is in dark mode),
clicking a link to the post/page editor shows a quick heads-up first — the
editor itself can't be dark-themed (for a few structural reasons, not
just one — see `CLAUDE.md` if you're curious), so it always displays with
a light background no matter your color scheme. The warning gives you a
chance to back out, or just turn down your screen's actual brightness
before continuing, and a "Don't warn me again" checkbox if you'd rather
not see it a second time.

There's nothing else to configure — no settings page, no extra options.
Pick a scheme on your Profile screen the same way you'd pick any of
WordPress's built-in colors (Light, Blue, Coffee, Ectoplasm, Midnight,
Ocean, Sunrise), and the whole wp-admin interface (menus, admin bar,
buttons, tables, notices, the login screen) re-colors to match.

The login screen is a special case — WordPress has no way to know your
color preference before you've logged in, so this remembers it in a
browser cookie instead, updated whenever you save your Profile screen.
If you already picked an OM scheme before this existed, save your
Profile once (even with no changes) to pick up theming on the login
screen too. No cookie yet (first visit, a different browser, etc.)
defaults to following your OS's light/dark setting, same as OM System.
If a site has its own client-branded login screen instead, that piece
alone can be turned off in code without affecting anything else this
plugin does — see `CLAUDE.md` for the one-line filter.

## Installation

### Option A: Manual install

1. Download the latest release zip from the
   [Releases page](../../releases).
2. Unzip it — you'll get an `om-admin-color-schemes` folder containing
   `om-admin-color-schemes.php`, a `src` folder, a `js` folder, and this
   README.
3. Copy that whole folder into your site's `wp-content/mu-plugins/`
   directory, so you end up with:
   ```
   wp-content/mu-plugins/om-admin-color-schemes/om-admin-color-schemes.php
   wp-content/mu-plugins/om-admin-color-schemes/src/om-light.css
   wp-content/mu-plugins/om-admin-color-schemes/src/om-dark.css
   wp-content/mu-plugins/om-admin-color-schemes/src/om-system.css
   wp-content/mu-plugins/om-admin-color-schemes/js/editor-brightness-warning.js
   ```
4. Continue with "Create the loader shim" below.

### Option B: Install via Composer

No private Packagist/Satis instance needed — point Composer at this
GitHub repo directly and require it with a normal version constraint.

1. Add a `"vcs"` repository entry and a `require` line to your site's
   `composer.json`:
   ```json
   {
   	"repositories": [
   		{
   			"type": "vcs",
   			"url": "https://github.com/OM-DevTeam/om-admin-color-schemes"
   		}
   	],
   	"require": {
   		"om-devteam/om-admin-color-schemes": "^1.0"
   	}
   }
   ```
2. Run `composer update om-devteam/om-admin-color-schemes` (or
   `composer install` if you're setting this up as part of a fresh
   `composer.json`).
3. This package declares itself as `"type": "wordpress-muplugin"`, which
   `composer/installers` (if your site already uses it — Bedrock-based
   sites do by default) routes into `wp-content/mu-plugins/{name}/`
   automatically. If your site doesn't already have that installer
   configured, add it and map the path yourself:
   ```json
   {
   	"require": {
   		"composer/installers": "^2.0"
   	},
   	"extra": {
   		"installer-paths": {
   			"wp-content/mu-plugins/{$name}/": ["type:wordpress-muplugin"]
   		}
   	}
   }
   ```
4. Continue with "Create the loader shim" below — Composer places the
   files, but doesn't change how WordPress mu-plugins loads them.

### Create the loader shim

Either way you installed it, **create one more file** directly inside
`wp-content/mu-plugins/` (as a sibling of the `om-admin-color-schemes`
folder, not inside it) — WordPress's mu-plugins loader only picks up
`.php` files that sit directly in `wp-content/mu-plugins/`; it does not
look inside subfolders, so a small loader file is needed to pull this
plugin in. Name it anything you like, e.g.
`wp-content/mu-plugins/load-om-admin-color-schemes.php`, containing:
```php
<?php
require_once __DIR__ . '/om-admin-color-schemes/om-admin-color-schemes.php';
```

That's it — WordPress mu-plugins are always active, so there's no
"Activate" step. Log in to wp-admin and check Profile → Admin Color
Scheme for the three new OM options.

## Known limitations

- Theming the editor page with OM Dark isn't planned. It's a large,
  open-ended undertaking (every block's own editor styles, core and
  third-party alike, would need re-theming individually), and it would
  likely conflict with whatever theme a given site actually has active,
  since the editor deliberately mirrors that theme rather than admin
  colors.
- No dark-mode-specific logo on the login screen — only admin chrome
  colors are themed.

## For contributors

If you're editing this plugin's source (not just installing it), see
`CLAUDE.md` in this repo for the SCSS build setup, release process, and
notes on WordPress core CSS quirks this plugin works around.

## Credits

Built by OM Performance Marketers.
