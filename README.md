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
editor itself can't be dark-themed, so it always displays with a light
background no matter your color scheme. The warning gives you a chance to
back out, or just turn down your screen's actual brightness before
continuing, and a "Don't warn me again" checkbox if you'd rather not see
it a second time.

There's nothing else to configure — no settings page, no extra options.
Pick a scheme on your Profile screen the same way you'd pick any of
WordPress's built-in colors (Light, Blue, Coffee, Ectoplasm, Midnight,
Ocean, Sunrise), and the whole wp-admin interface (menus, admin bar,
buttons, tables, notices, the login screen) re-colors to match.

## Installation

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
4. **Create one more file** directly inside `wp-content/mu-plugins/`
   (as a sibling of the `om-admin-color-schemes` folder, not inside it) —
   WordPress's mu-plugins loader only picks up `.php` files that sit
   directly in `wp-content/mu-plugins/`; it does not look inside
   subfolders, so a small loader file is needed to pull this plugin in.
   Name it anything you like, e.g.
   `wp-content/mu-plugins/load-om-admin-color-schemes.php`, containing:
   ```php
   <?php
   require_once __DIR__ . '/om-admin-color-schemes/om-admin-color-schemes.php';
   ```
5. That's it — WordPress mu-plugins are always active, so there's no
   "Activate" step. Log in to wp-admin and check Profile → Admin Color
   Scheme for the three new OM options.

If your site manages plugins via Composer instead, point a `"package"`-type
repository entry at a specific release's zip asset (no private Packagist/
Satis instance needed) — see the "Consuming the release zip via Composer"
note in this repo's `CLAUDE.md` for a ready-to-paste example. The manual
loader-shim step above is still required either way — Composer installing
the files doesn't change how mu-plugins loads them.

## Known limitations

- OM Dark doesn't yet theme every screen and element — some tabs, help
  panels, and form controls can still show default WordPress colors
  instead of OM's palette. OM Light and OM System (in light mode) are
  more thoroughly covered.
- No dark-mode-specific logo on the login screen — only admin chrome
  colors are themed.

## For contributors

If you're editing this plugin's source (not just installing it), see
`CLAUDE.md` in this repo for the SCSS build setup, release process, and
notes on WordPress core CSS quirks this plugin works around.

## Credits

Built by OM Performance Marketers.
