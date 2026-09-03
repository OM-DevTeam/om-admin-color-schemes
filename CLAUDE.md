# CLAUDE.md

Context for Claude Code (or any future contributor) working on this repo.

## What this is

**om-admin-color-schemes** is a WordPress **mu-plugin** that registers a custom admin
color scheme called "OM", based on OM Performance Marketers brand colors.
It ships as three selectable schemes on the Profile screen
(Users → Profile → Admin Color Scheme):

- **OM System** — follows the browser/OS `prefers-color-scheme` setting live.
- **OM Light** — always light.
- **OM Dark** — always dark.

Mode switching itself is handled entirely by WordPress's native
color-scheme picker (`wp_admin_css_color()`) plus a CSS media query for the
"System" variant — no user-meta toggle of its own. See the earlier
conversation with the person who commissioned this for why that
architecture was chosen over a custom profile field (they explicitly asked
for three native swatches instead). There **is** now a small amount of JS
(`js/editor-brightness-warning.js`) — see "Editor brightness warning"
below — but it's narrowly scoped to one specific, structural problem, not
a general dark-mode toggle mechanism.

Dark mode was briefly dropped and then restored — it needed enough one-off
fixes for readability (dropdown arrows, hover text, the color-scheme
picker's own hover state, etc.) that it looked like more trouble than it
was worth, but the actual plan going forward is to keep it and instead
flag to whoever's using it when they land on a screen that isn't fully
themed yet, rather than abandon dark mode outright. That general flagging
mechanism (for screens that are merely *not themed yet*, as opposed to
*can't be themed*) still doesn't exist — see "Known limitations" below. One
specific, unfixable case of it — the post editor, which structurally can't
be dark-themed at all — now has its own targeted warning; see "Editor
brightness warning" below.

## File layout

```
om-admin-color-schemes.php             Loader mu-plugin. Defines the
                                        OM_Admin_Color_Schemes class, which
                                        registers the 3 schemes via
                                        wp_admin_css_color() on admin_init,
                                        and conditionally enqueues
                                        js/editor-brightness-warning.js on
                                        admin_enqueue_scripts. Must end up
                                        as a top-level file directly inside
                                        wp-content/mu-plugins/ (mu-plugins
                                        only auto-loads top-level .php
                                        files, not files in subdirectories).

js/
└── editor-brightness-warning.js       Vanilla JS, no build step. Warns
                                        before navigating to the post
                                        editor — see "Editor brightness
                                        warning" below. Only enqueued for
                                        om-dark/om-system users.

README.md                              User-facing install/usage doc —
                                        ships inside the release zip (see
                                        "Releases" below). Keep it about
                                        using the plugin, not building it.

package.json                           SCSS build + lint tooling only —
.stylelintrc.cjs                       nothing here runs in WordPress.

composer.json                          Package metadata only (name, type,
                                        php requirement) — for a consuming
                                        site's installer, not an autoloader;
                                        nothing here requires Composer to
                                        run PHP code from this repo. See
                                        "Consuming the release zip via
                                        Composer" below.

dev/
└── create-release-files.sh            Assembles releases/mu-plugins/ (the
                                        loader + compiled src/*.css as
                                        siblings) and zips it. Called by
                                        .github/workflows/draft-release.yml
                                        — see "Releases" below.

scss/                                  SOURCE. Hand-edit these, never src/*.css.
├── _tokens.scss                       $light and $dark maps — the single
│                                       source of truth for every --om-*
│                                       value. Add/remove a token here and
│                                       it's automatically picked up by
│                                       om-root-vars() below; nothing else
│                                       needs updating.
├── _mixins.scss                       om-root-vars($tokens) turns one of
│                                       those maps into a `:root { --om-*: ...; }`
│                                       block, generically over its keys.
├── _shared.scss                       styles() mixin — every actual
│                                       selector (#adminmenu, #wpadminbar,
│                                       .postbox, buttons, tables, notices,
│                                       login screen...). Contains NO
│                                       literal colors — every rule reads a
│                                       var(--om-*) custom property. Edit
│                                       selectors/layout here.
├── om-light.scss                      Entry point: om-root-vars($light) +
│                                       styles().
├── om-dark.scss                       Entry point: om-root-vars($dark) +
│                                       styles().
└── om-system.scss                     Entry point: om-root-vars($light) +
                                        styles(), then om-root-vars($dark)
                                        again inside
                                        @media (prefers-color-scheme: dark).

src/                                   COMPILED OUTPUT — gitignored, never
├── om-light.css                       committed, never hand-edit. Exists
├── om-dark.css                        locally only after `npm run build`
└── om-system.css                      (or transiently during a release
                                        build). No compiled om-shared.css:
                                        _shared.scss is a partial inlined
                                        into each of the three files above
                                        at build time, so there's no extra
                                        runtime CSS @import / HTTP request
                                        the way the old hand-written CSS had.
```

This repo folder (`om-admin-color-schemes/`, containing the loader, `scss/`, `src/`,
and this CLAUDE.md) is the source of truth for the plugin, kept together for
version control. In this local dev environment specifically, the site's
`wp-content/mu-plugins/` also has a symlink — `om-admin-color-schemes.php` →
`om-admin-color-schemes/om-admin-color-schemes.php` — so mu-plugins picks it up without
duplicating files. That symlink is a local convenience, not part of the
plugin itself — production sites install from a release zip instead; see
"Releases" below for how that's built and shipped.

## Build (SCSS)

```
npm install     # once, to pull in sass/stylelint/etc (devDependencies only)
npm run build   # compiles scss/*.scss -> src/*.css (what WordPress serves)
npm run watch   # same, recompiling on save while you work
npm run lint    # stylelint against scss/**/*.scss
npm run lint:fix
```

**To add or change a selector:** edit `_shared.scss`'s `styles()` mixin
once; it applies to all three modes automatically, then `npm run build`.

**To change a color:** edit the one value in `_tokens.scss`'s `$light` or
`$dark` map, then `npm run build`. Because `om-system.scss` pulls from the
same two maps as `om-light.scss`/`om-dark.scss`, all three compiled files
stay in sync automatically — there's no more hand-copying values between
files.

Stylelint config (`.stylelintrc.cjs`) extends `stylelint-config-standard-scss`
plus the stylistic/order/defensive-css/high-performance-animation plugins,
with a handful of rules turned off — each with an inline comment explaining
why (short version: this file is a WordPress admin color-scheme override,
so several "defensive CSS" assumptions that fit a normal stylesheet don't
apply — see `require-pure-selectors`, `require-focus-visible`, and
`no-accidental-hover` in that file before re-enabling any of them).

**`src/*.css` is never committed** — it's gitignored (see `.gitignore`).
Run `npm run build`/`npm run watch` locally to produce it for local
testing, but there's nothing to stage or commit; the compiled CSS only
ever gets generated fresh, either on your machine or inside the release
workflow below.

## Releases

Cutting a release means running the **"Draft new release"** workflow from
the Actions tab (`workflow_dispatch`, takes a `version` input like
`1.2.0`). It mirrors the same pattern used in
[crstauf/query-monitor-extend](https://github.com/crstauf/query-monitor-extend)
(`draft-release.yml` + `dev/create-release-files.sh`):

1. `npm ci && npm run lint && npm run build` — fresh `src/*.css` from
   current `scss/`.
2. `dev/create-release-files.sh` assembles `releases/om-admin-color-schemes/`
   — the plugin's own folder, matching normal WordPress plugin zip
   conventions rather than pre-wrapping it in a `mu-plugins/` folder —
   containing `om-admin-color-schemes.php`, `src/*.css` and
   `js/editor-brightness-warning.js` as its siblings (the exact relative
   relationships the PHP already expects via
   `plugin_dir_url( __FILE__ ) . 'src/'` / `'js/'`), and `README.md`. That
   folder is then zipped as-is into `releases/om-admin-color-schemes.zip`.
3. `softprops/action-gh-release` publishes a **draft** GitHub Release
   named after the version input, with that zip attached. Review the
   draft and hit "Publish" manually — nothing goes live automatically.

The zip is a normal single-plugin-folder zip; it does **not** unzip
directly into a working state inside `wp-content/mu-plugins/`. mu-plugins
only auto-loads top-level `.php` files, not files inside subdirectories,
and this zip's `om-admin-color-schemes.php` is one level down (inside the
`om-admin-color-schemes/` folder). Whoever installs it needs to also
hand-create a small loader file directly in `wp-content/mu-plugins/`
(sibling of the `om-admin-color-schemes/` folder) that does
`require_once __DIR__ . '/om-admin-color-schemes/om-admin-color-schemes.php';`
— see README.md's Installation section, which spells this out for
end users. This mirrors the local dev symlink's purpose (getting a
nested loader to actually execute), just via a `require` instead of a
symlink on the destination site.

**Consuming the release zip via Composer — decided.** No private
Packagist/Satis instance; a consuming site points a `"package"`-type
repository entry directly at a specific release's zip asset instead. This
repo's own `composer.json` (added alongside this decision) just declares
`"name": "om-devteam/om-admin-color-schemes"` and `"type":
"wordpress-muplugin"` — that's metadata for whichever
`composer/installers`-based install-path config the consuming site already
uses, not something Composer resolves against automatically, since a
`"package"` repository has no version-discovery mechanism of its own. The
consuming site's own `composer.json` needs an entry shaped like this
(bump the version/URL by hand for every new release — that manual edit is
the actual cost of skipping a private Packagist/Satis instance):

```json
{
	"repositories": [
		{
			"type": "package",
			"package": {
				"name": "om-devteam/om-admin-color-schemes",
				"version": "1.1.0",
				"type": "wordpress-muplugin",
				"dist": {
					"url": "https://github.com/OM-DevTeam/om-admin-color-schemes/releases/download/1.1.0/om-admin-color-schemes.zip",
					"type": "zip"
				}
			}
		}
	],
	"require": {
		"om-devteam/om-admin-color-schemes": "1.1.0"
	}
}
```

The version string in that `dist.url` must exactly match the release's git
tag — see the `tag_name` fix in `draft-release.yml` below; before that fix
the workflow only set the release's display *name* from the `version`
input, not its actual tag, so every draft release's tag silently fell back
to the branch it was run from (`init`) instead of the version typed in.
Also note this only solves *fetching* the zip — see the loader-shim gap
noted just above (mu-plugins doesn't auto-load subdirectory `.php` files
regardless of how they got there), which the consuming site still needs
its own answer for, Composer-installed or not.

The `om-devteam` vendor name is inferred from the `OM-DevTeam` GitHub org,
not confirmed against any existing internal Composer-package naming
convention — rename it here (and in the consuming site's `require`/
`repositories` entries) if CSSLLC already has a different one.

## PHP registration details

`OM_Admin_Color_Schemes` is a static-only class (no instances are created; the
file just calls `OM_Admin_Color_Schemes::init()` at the bottom). Structure:

- `init()` — hooks `register_color_schemes()` to `admin_init`.
  `wp_admin_css_color()` must run before WordPress renders the color-scheme
  picker on the Profile screen, hence `admin_init` rather than something
  later.
- `get_schemes()` — the single source of truth: an associative array keyed
  by scheme slug (`om-system`, `om-light`, `om-dark`), each entry holding
  its label, CSS filename, swatch-preview colors, and icon colors.
- `register_color_schemes()` — loops over `get_schemes()` and calls
  `wp_admin_css_color()` once per entry. This is what keeps the three
  registrations DRY; adding a fourth scheme means adding one array entry,
  not a fourth near-identical `wp_admin_css_color()` call.
- Class constants (`LIGHT_MENU_ICON_COLORS`, `DARK_MENU_ICON_COLORS`,
  `LIGHT_SWATCH_COLORS`, `DARK_SWATCH_COLORS`) hold the values shared
  between schemes, since `om-system` and `om-light` use identical
  swatch/icon colors (both preview the light-menu palette) and `om-dark`
  uses its own. These are independent of the `--om-*` CSS custom
  properties in `src/` and must be kept in rough visual sync by hand if the
  real theme colors change.
- Text domain for the `__()` calls is the literal string `'om-admin-color-schemes'`
  (kept as a literal, not a constant, since WP's i18n string-extraction
  tooling requires a literal in that argument position).

## Editor brightness warning

**The problem this solves is structural, not a theming gap**: the block
editor's canvas (post.php?action=edit, post-new.php) is a
WordPress-controlled iframe that always displays with a light background,
regardless of admin color scheme — there's no realistic way to dark-theme
it from an admin color-scheme plugin. Rather than let an OM Dark user get
hit with that light-background flash with zero warning,
`js/editor-brightness-warning.js` intercepts clicks on links headed there
and offers a chance to back out — or just turn down their screen's actual
brightness — before navigating. Worth being precise about the two senses
of "brightness" in play here: the editor's own light color scheme (a fixed
fact about the editor, not adjustable) versus the user's screen brightness
setting (a device setting the warning suggests adjusting) — the modal copy
keeps these two distinct rather than using "brightness" for both.

**Why click-interception instead of a notice on the editor screen itself:**
the whole point is to let the user act *before* the bright screen ever
renders (specifically, to give them a moment to turn down their screen
brightness) — a notice on the destination screen can't do that, since by
the time it's visible the flash has already happened. The trade-off is
coverage: this only catches actual `<a href>` clicks, not bookmarks, typed
URLs, or browser back/forward — considered an acceptable gap, since those
are rarer entry points and the alternative (a destination-screen notice)
gives up the one thing this feature is for.

**Gating — enqueued only when there's something to warn about:**
`enqueue_editor_warning_script()` (hooked to `admin_enqueue_scripts`) skips
entirely unless the current user's `admin_color` is `om-dark` or
`om-system`. `om-system` is included because it *might* currently be
dark, but PHP has no way to know the browser's live
`prefers-color-scheme` state — that check happens client-side, in
`isSchemeCurrentlyDark()`, via `window.matchMedia`, evaluated fresh on
every click rather than baked into a value computed at page load (so it
stays correct if someone flips their OS theme mid-session without
reloading).

**Matching editor links:** `isEditorLink()` resolves the clicked anchor's
`href` to an absolute URL and checks the pathname's filename against
`post-new.php`, or `post.php` with `?action=edit` — this catches "Add New"
buttons, list-table row titles/Edit actions, the admin bar's "+ New"
dropdown, Dashboard "At a Glance"/Quick Draft links, comment "Edit post"
links, etc., since they all resolve to one of those two URLs regardless of
which screen they're clicked from. Skips cross-origin links, `target=
"_blank"` links, and any click with a modifier key or non-primary mouse
button held (so Cmd/Ctrl/middle-click-to-open-in-a-new-tab is left alone,
un-intercepted, the way a user expects).

**Already-on-the-editor guard:** if `document.body` already carries WP's
own `post-php`/`post-new-php` admin-screen body class, the script returns
immediately and never attaches its click listener at all — this isn't
just a minor optimization, it's what stops in-page hash-anchor links (or a
translations/related-post meta box linking to another edit screen) from
re-triggering the warning while already on a bright screen, where there's
nothing left to warn about.

**"Don't warn me again" — `localStorage`, not user meta.** Deliberately
per-browser rather than per-account: no REST round trip, no nonce, and the
checkbox is meant as a quick fatigue-avoidance escape hatch, not a durable
cross-device preference. If this needs to follow the user across devices
later, that's a `wp_ajax_`/REST endpoint plus a user-meta read in
`enqueue_editor_warning_script()` to also skip enqueueing entirely once
dismissed — not built, since the added complexity didn't seem justified
for what's meant to be a lightweight nag. **This checkbox existing at all
is the main defense against the annoyance risk**: editing content is the
single most frequent action in wp-admin, so a warning that fired on every
single click without a durable way to turn it off would very quickly
become a net-negative, resented feature rather than a helpful one.

**Modal markup/styling** lives in `_shared.scss`'s own dedicated section
(`.om-editor-warning-modal` and its children) — this is the one place in
the whole file that's the plugin's OWN markup rather than a core-selector
override, built via `document.createElement`/`innerHTML` in JS rather than
output from PHP, so there's no server-rendered template to keep in sync.
Text content is set via `.textContent`, not interpolated into the
`innerHTML` string, specifically so a translated string can never be
interpreted as markup. The modal's two buttons deliberately use plain
`.button`/`.button-primary` (no extra wrapper class) so they pick up the
`.wp-core-ui .button`/`.button-primary` rules this file already themes —
every wp-admin `<body>` always carries the `wp-core-ui` class, so this
works for free without a separate button treatment. A minimal focus trap
(Tab/Shift+Tab cycles within the dialog, Escape cancels, focus returns to
the original trigger link on close) covers keyboard/screen-reader use
without pulling in a dialog library for three focusable elements.

## Color system

All colors are OM Performance Marketers brand colors, sourced from the
internal OM brand guide (Dark Navy `#0D2132`, Light Blue `#46C3FC`, plus
accent reds/greens/oranges/yellows/magenta and a grey scale). A few brand
hexes were algorithmically adjusted for WCAG AA text contrast rather than
used raw, because some brand colors (e.g. the raw Light Blue on white, or
the raw Red on navy) fall short of 4.5:1 for body text:

| Role | Light mode | Dark mode | Why |
|---|---|---|---|
| Link/accent text | `#037DB5` (darkened Light Blue) | `#46C3FC` (full brand Light Blue) | Raw `#46C3FC` on white is only ~2:1; darkening it hits ~4.56:1. On dark navy backgrounds the full-strength brand blue already clears 8:1, so no adjustment needed there. |
| Error text | `#DB0B28` (darkened Red) | `#F86D81` (lightened Red) | Raw `#F41F3D` is borderline against both white (~4.1:1) and navy (~4:1) — each mode needed the opposite adjustment to clear 4.5:1. |
| Warning text | `#B25900` (darkened Orange) | `#FF850D` (full brand Orange) | Same logic as link color. |

If you need to re-derive or double check any of these, the method used was:
convert the brand hex to HLS, walk lightness up/down in the same hue until
`(L1+0.05)/(L2+0.05)` (relative luminance contrast formula, WCAG 2.x) hits
≥4.5 against the relevant background. Non-text elements (borders, hairline
dividers) were allowed to fall a bit under the stricter 3:1 UI-component
guideline where WordPress core itself does the same (e.g. subtle table
borders) — don't over-rotate on those, but do keep body text, links,
buttons, and menu items at proper AA.

**Dark mode's design philosophy — matching core Default's "feel," not its
own navy-brand identity:** an earlier version of `$dark` leaned into OM's
Dark Navy brand color for the page background, cards, and chrome alike,
which read as moody/saturated rather than the soft, airy, neutral feel
WordPress core's own "Default" scheme has (the scheme labeled "Default" on
the Profile screen — confirmed by inspecting `wp-admin/css/colors/modern/
colors.css`, since in current WP core "Default" is actually the `modern`
scheme, not the unstyled `fresh` one). Core Default achieves that feel
with: near-black *neutral* (not navy) chrome, a soft mid-light neutral
page background, white cards sitting a shade lighter than the page for
subtle elevation, muted low-contrast borders, soft off-black body text
(never pure `#000`), and exactly one accent color (`--wp-admin-theme-color`)
carrying links, buttons, and the current-menu-item highlight — it doesn't
tint the neutral surfaces with that accent at all. `$dark` now mirrors
that structure inverted (neutral near-black chrome, soft neutral dark page
background, a slightly-lighter neutral card tone, soft muted borders,
off-white rather than pure-white text) while keeping OM's Light Blue as
the single accent color in that same limited role — same job Default's
`#3858e9` does, just OM's actual brand blue instead of WP's. Status colors
(success/warning/error/info) were deliberately left alone — those are
OM's actual brand accent colors per the brand guide, not something to
invent softer variants of just to chase a feel.

**Secondary buttons are filled, not outlined**, in both Light and Dark —
`button-secondary-bg` matches `--om-box-bg` (white in Light, the dark
"elevated card" tone in Dark) with `--om-box-border` for the border, the
same neutral treatment every other card/box surface in this file gets.
This matches what core's actual Default secondary button looks like (a
pale filled box with a hairline border, not a transparent outline) —
primary still reads as clearly distinct since it's the one button on the
page filled with the accent color rather than a neutral. An earlier
iteration tried outlined/transparent secondary buttons instead (accent
color for border + text, no fill); that's no longer the design here.
Hover/focus deliberately does **not** reuse `--om-menu-current-bg`/
`-text` (the strong accent-blue pairing reserved for the active nav item
and `.button-primary`'s own resting state) — doing so made a
hovered/focused secondary button (a common lingering state right after a
click, e.g. after "Save Draft") visually indistinguishable from an
unpressed primary button. Hover/focus uses the subtler
`--om-table-row-hover-bg` background, `--om-focus-color` border, and
`--om-heading-color` text instead — the same family of hover treatments
already used elsewhere in this file (e.g. `.color-option:hover`).

A first pass at Dark only fixed the elements this file already had
selectors for, styled with the *old* navy palette — it didn't add
coverage for elements that were never themed at all, so they stayed
core's literal hardcoded white regardless of scheme (not just in Dark —
Light happened to already look right by coincidence, since its own
`--om-box-bg` is white). Once seen against Dark's new soft neutral
background, these stood out as un-themed white patches:

- **Text inputs and `<textarea>`** — forms.css's base rule for every
  `input[type=text/password/.../week]` plus `textarea` hardcodes
  `background-color: #fff; color: #1e1e1e;` in one shared selector; there
  was no override for any of it. Now reads `--om-box-bg`/`--om-field-border`/
  `--om-body-text`, same "elevated card" tone as postboxes and `<select>`.
  `--om-field-border` (not `--om-box-border`) specifically: core itself
  draws a visibly stronger outline around interactive form controls
  (~3:1 on box-bg) than it does around purely structural dividers like
  postbox/table borders (~1.6:1) — see `field-border` vs `box-border` in
  `_tokens.scss`. The override selector is built from a Sass string
  (`$text-inputs`) so the 14-type list isn't repeated twice for the
  `body.wp-admin :is(...)` specificity-boost variant.
- **Screen Options / Help tabs and their panel** (`#contextual-help-link`,
  `#screen-options-link`, generically `#screen-meta-links .show-settings`,
  and the `#screen-meta` panel that drops down under them) — common.css
  hardcodes these to `#fff` background / `#646970` text / `#2c3338` on
  hover, all unthemed. Same token treatment as the text-input fix above.
- **List table rows** — `table.wp-list-table`/`tbody`/`td`/`th` had
  `border-color` and `color` overrides but no `background`, so ordinary
  (non-header, non-hover) rows stayed common.css's hardcoded `.widefat`
  white. First attempt added `background: var(--om-box-bg)` directly to
  the `td`/`th` selectors alongside `border-color`/`color` — this
  regressed the alternating-row stripe (`.striped > tbody > :nth-child(odd)`
  / `.alternate`), since an explicit background on a cell paints over
  whatever background its row/row-group has, hiding the stripe entirely.
  Fixed by splitting `background` onto `table.wp-list-table`/`tbody` only
  (row-group level, cells stay transparent so striping shows through) and
  adding a themed override for the stripe itself (`--om-table-row-alt-bg`,
  a new token distinct from the hover highlight so a hovered striped row
  still reads differently from an unhovered one) instead of leaving core's
  hardcoded `#f6f7f7` in place. **Lesson: never put `background` on a
  table cell selector when the row/row-group beneath it might have its
  own conditional background (striping, JS-added state classes, etc.) —
  set it on the row/row-group instead and let cells stay transparent.**
  Separately, common.css's `.widefat thead tr th`/`tr td` and `.widefat
  tfoot tr th`/`tr td` (note the extra `tr` — one more type selector than
  `.wp-list-table thead th` etc.) out-specify the plain header/footer
  `color` rule; fixed the same tie-breaking way as elsewhere in this file,
  using `:is(thead, tfoot)` / `:is(th, td)` to avoid writing out all four
  literal combinations twice for the `body.wp-admin`-prefixed variant.

Five more turned up on the Dashboard screen specifically — all in
`dashboard.css`, all plain (no specificity fight; checked and found no
competing higher-specificity rule for any of these properties):

- `.welcome-panel .welcome-panel-column-container` — hardcoded white card
  background inside the Welcome panel. Now `--om-box-bg`/`--om-box-border`,
  same as postboxes.
- `#future-posts li:nth-child(odd)` / `#published-posts li:nth-child(odd)`
  (the Activity widget's Publishing Soon / Recently Published lists) —
  hardcoded `#f6f7f7`, the exact same value core uses for list-table
  striping, so this reuses `--om-table-row-alt-bg` rather than inventing a
  near-duplicate token. The non-`:nth-child` base `li` rule's `color` was
  also unthemed.
- `#activity-widget #the-comment-list .comment-item` (Recent Comments) —
  same `#f6f7f7`, same `--om-table-row-alt-bg` reuse.
- `.community-events .activity-block.last` and `.community-events-footer`
  (Community Events widget) — hardcoded border colors (and, for the
  footer, text color) using core's generic light-grey/muted-text values.
- `.activity-block` (the hairline divider *between* dashboard-widget
  entries, distinct from `.activity-block.last` above) and
  `#dashboard_activity .subsubsub` — same hardcoded light-grey border.
- `#dashboard-widgets h3`, `#dashboard-widgets h4`, and Quick Draft's
  `.drafts h2` — an **ID beats any number of classes**, so these
  out-specify the generic `h1, h2, h3 { color: var(--om-heading-color) }`
  rule this file already has; widget titles were silently falling back to
  core's hardcoded dark grey. `h4` isn't covered by the generic rule at
  all regardless of context (only h1–h3 are listed there), so it needed
  its own rule either way.

A handful more turned up outside the Dashboard screen. Each was checked
for competing higher-or-equal-specificity core rules the same way as
above before writing the override:

- **`.ac_match, .subsubsub a.current`** (common.css) — the "current"
  status-filter link above a list table (All/Published/Drafts, etc.)
  hardcodes `color: #000`. Separately, edit.css's tag-suggest autocomplete
  dropdown reuses the literal class `.ac_match` for a *different* purpose
  — the highlighted suggestion row — via the more specific `.ac_over
  .ac_match` / `.ac_results .ac_over`, with its own hardcoded accent fill.
  Both needed overriding; the highlighted-row state uses the same
  `--om-menu-current-bg`/`-text` "current/selected" pairing used elsewhere
  (e.g. `.wp-core-ui .button:hover`).
- **Sortable list-table column arrows** (`.sorting-indicator::before` and
  friends, list-tables.css) — a dashicon glyph colored via plain `color`,
  with *four* increasingly specific states: unsorted/inactive (muted
  grey), active-sort-direction (dark), then two hover/focus states that
  swap which of the two sort directions reads as muted vs. dark. Overriding
  only the base and "active" states (specificity (0,1,1) and (0,2,2))
  isn't enough — the hover/focus compound selectors (~(0,4,1)) are more
  specific still and would re-apply core's hardcoded greys the moment you
  interact with the column header. All four needed matching overrides.
  The unsorted/inactive state uses the dedicated `--om-chrome-faint` token
  (see the "List table chrome tokens" note below) rather than a border
  token — it's one of the ratio-matched "chrome" tier, not a border reuse.
- **`.wp-core-ui select:focus`** — the same gap as the `:hover` fix above,
  just for `:focus`: `border-color`/`box-shadow` already read
  `var(--wp-admin-theme-color)` (already remapped to `--om-link-color`),
  so `color: #1e1e1e` was the only hardcoded leftover.
- **Media Library** — `.attachments-browser .media-toolbar` (the grid-view
  toolbar) and `.wp-filter` (the search/filter bar reused across list
  tables and the media grid) both hardcode white backgrounds. The
  grid-view toolbar element carries *both* classes at once, and
  `.attachments-browser .media-toolbar` (0,2,0) is more specific than
  plain `.wp-filter` (0,1,0), so both needed theming for that shared
  element to come out fully covered.
- **Health Check / Privacy Settings screens** — `.health-check-header`/
  `.privacy-settings-header` and the accordion trigger buttons
  (`.health-check-accordion-trigger`/`.privacy-settings-accordion-trigger`,
  base + `:hover`/`:active` + `:focus` states) all hardcode white
  backgrounds and/or dark text, unthemed.

Also added `'selector-class-pattern': null` and `'selector-id-pattern':
null` to `.stylelintrc.cjs` alongside `require-pure-selectors` — WP core
itself isn't consistently kebab-case in its own markup (`#dashboard_activity`,
`#dashboard_quick_press`, `.ac_match`), and this file has to match those
verbatim, not rename them to a convention core doesn't use.

A further five, all in `common.css`/`list-tables.css`:

- **`.subsubsub a .count, .subsubsub a.current .count`** — the
  parenthetical item count next to a status filter link (e.g. "Published
  (12)"), a separate, more muted color from the filter link's own text
  handled just above.
- **`.wrap .page-title-action`** (the "Add New" button next to a screen's
  title; `.add-new-h2` is the same thing under its deprecated pre-4.4
  class name) — an outlined button using, in core's own code comment,
  "the standard color used for buttons," i.e. the link/theme color, not a
  button-specific one. Reused `--om-link-color`/`--om-link-hover` rather
  than the button tokens for that reason. Core splits hover/focus across
  two selectors of different specificity (a low-specificity bare
  `.page-title-action:focus` for `color`, a `.wrap`-qualified one for
  `border-color`/`box-shadow`) — one combined rule here covers both; the
  focus ring itself already comes from the generic `a:focus` rule
  elsewhere in this file, which uses `!important` and wins regardless of
  specificity, so it didn't need repeating.
- **`.plugins tr` / `.plugins td`** — the Plugins screen's list table
  carries both `.plugins` and `.wp-list-table`/`.widefat` classes on the
  same markup, so `.plugins tr`/`.plugins td` sit at **exactly the same
  specificity** as this file's existing `.wp-list-table tr`/`td` rules —
  not a case of core being more specific, just an equal-specificity rule
  this file hadn't matched yet. `.plugins td { color: #000 }` in
  particular is a much harsher pure black than anything else this file
  uses, so it was worth catching even though it's "only" a tie.
- **`.widefat ol, .widefat p, .widefat ul`** — block content rendered
  inside list-table cells (e.g. plugin descriptions), unthemed text color.
- **`th .comment-grey-bubble::before`** — the small speech-bubble dashicon
  in a list table's Comments column header, colored via plain `color`
  like the sorting-indicator icons above.

A generic "card" background+border group, plus notice text/dismiss and a
deliberate submenu design change:

- **`table.widefat, .stuffbox, p.popular-tags, .widgets-holder-wrap,
  .wp-editor-container, .popular-tags, .feature-filter, .comment-ays`** —
  common.css themes all of these together in one shared rule (the same
  "generic bordered white card" treatment as postboxes). `.stuffbox` is
  already covered by the postbox rule above; it's repeated here too
  because it's part of core's own grouping and the duplication is
  harmless (same value both places).
- **`.notice p`, `div.updated p`, `div.error p`, `.form-table td .notice
  p`** — notice/error paragraph text, hardcoded separately from the
  `.notice`/`div.error`/`div.updated` container color this file already
  themes. **`.notice-title`** gets its own, slightly darker core value
  (`#1d2327` vs plain `.notice p`'s `#1e1e1e`) — mapped to
  `--om-heading-color` instead of `--om-body-text` to preserve that same
  subtle distinction.
- **`.notice-dismiss`** (the notice's "X" button) — common.css keeps this
  icon's color constant across every state (hover/active/focus only add
  an opacity dim, not a color change), so one rule covers the button, the
  base `::before` glyph, and all three interactive `::before` states.

**Sidebar submenu background is deliberately darker than the top-level
menu, not lighter like core's own submenu** (recall from earlier research:
Fresh's own submenu flyout, `#2c3338`, is *lighter* than its `#1d2327`
menu background). This was a specific design ask, not a core-override fix
— the goal is a submenu that reads as a clearly distinct surface from the
top-level items, rather than blending into them the way `--om-menu-bg`
alone (previously applied to `#adminmenu .wp-submenu` too) did. New token
`--om-menu-submenu-bg` applies everywhere a submenu flyout background is
set: the normal expanded state, the hover-revealed flyout
(`li.opensub .wp-submenu` / `a.wp-has-submenu:hover + .wp-submenu`), and
the folded/collapsed menu's flyout (`.folded #adminmenu .wp-submenu`,
which previously — probably by oversight rather than intent — just reused
plain `--om-menu-bg`, the same color as the top-level menu it flies out
from).

**Real bug, not a core-override gap: primary and secondary buttons briefly
rendered identically.** `.wp-core-ui .button-primary` and `.wp-core-ui
.button`/`.button-secondary` have IDENTICAL CSS specificity — both are
`.wp-core-ui` plus exactly one more class, (0,2,0) — and WordPress markup
gives a primary button *both* classes at once (`class="button
button-primary"`). For any property both rules set, whichever rule is
declared **later in the stylesheet** wins the tie, completely independent
of which selector "sounds" more specific. The primary-button rule used to
be declared *before* the secondary/generic rule, so the secondary rule's
`background`/`color` won on every primary button, and they rendered with
secondary's styling. Fixed by moving the generic `.button`/`.button-secondary`
block (base + hover/focus) before `.button-primary` (base + hover/focus +
its own `:focus` box-shadow) — same fix for the same reason on both the
base and hover/focus pairs, since `.button-primary:hover` ties
`.button:hover` the identical way. **General lesson, distinct from every
other issue logged in this file: this is the one case so far where
*selector order in the stylesheet*, not selector specificity, decided the
outcome — worth checking whenever two rules in this file could plausibly
match the exact same element via different classes.**

Two more Health Check / Privacy Settings gaps, once the accordion pieces
above made the surrounding chrome dark:

- **`.health-check-accordion-trigger .badge, .privacy-settings-accordion-trigger
  .badge`** — the small status pill inside each trigger (e.g. "Critical",
  "Recommended") hardcodes dark-grey text, unreadable once the trigger's
  own background went dark. Its `.badge.blue/orange/red/green/purple/gray`
  border-color variants are deliberately left alone — same reasoning as
  the notice status colors: they're meant to stay their semantic color
  regardless of scheme.
- **`.health-check-accordion-panel, .privacy-settings-accordion-panel`** —
  the expanded panel content under a trigger, same hardcoded white
  background the trigger itself had.
- **`.widefat td, .widefat th { color: #50575e }`** — a *different*
  selector from `.wp-list-table th/td` above, not just a duplicate report:
  not every `.widefat` table is also a `.wp-list-table` (some
  settings/data tables use `.widefat` on its own), so tables that only
  carry `.widefat` fell through to this hardcoded color entirely
  unopposed — same shape as the earlier `.plugins td` tie, just a gap
  rather than a tie this time.
- **`#templateside > ul`** (Theme/Plugin File Editor's file-list sidebar)
  — hardcoded light-grey background. Themed via `border-color` (not the
  `border`/`border-left` shorthands core uses) specifically so it also
  covers core's `@media (max-width: 782px)` variant, which re-sets
  `border-left` with the same hardcoded color at that breakpoint.

**`--wp-admin-theme-color--rgb` — a systemic gap, not a single selector.**
Several core rules (e.g. `.highlight { background-color: rgba(var(
--wp-admin-theme-color--rgb), 0.08); }`, a button-group active state) read
this comma-separated-RGB-channel form of the theme color directly, to
build a translucent tint. WordPress's own *named* schemes (blue, midnight,
etc.) populate this variable automatically as part of their built-in
registration; a custom scheme registered via `wp_admin_css_color()` does
not get it for free. Without it, every core rule using that `rgba(var(...),
alpha)` pattern was silently invalid, which generally reads as an
unthemed white/transparent patch wherever it's used — this is likely the
actual explanation for at least one report that didn't trace to any
literal selector at all (see "Known limitations" below). Fixed by adding
a `link-color-rgb` token (the same value as `link-color`, just as
comma-separated channels, since Sass can't derive one from the other
without extra tooling) to both palettes, and remapping
`--wp-admin-theme-color--rgb` to it alongside the existing
`--wp-admin-theme-color`/`-darker-10`/`-darker-20` remapping.

`_shared.scss` also sets `--wp-admin-theme-color` and its `-darker-10` /
`-darker-20` variants from the same tokens, since WordPress core and the
block editor read those for a handful of native UI accents (focus rings on
some newer components, etc). Worth spot-checking in Gutenberg if you touch
the link/focus colors.

**List table "chrome" tokens (`--om-chrome-*`) — a whole tier of row-level
UI (row actions, sort arrows, view-switch icons, the Comments column
header icon, plugin-card compatibility icons) that isn't text, isn't a
border, and isn't a status color, but was still hardcoded by core to a
specific grey.** Rather than pick one flat "muted grey" for all of it (which
would either wash out the elements core makes bold, or over-darken the ones
core keeps subtle), each token is solved to reproduce the *same contrast
ratio* core's own Default scheme has for that exact element against its own
background — not a literal hex recolor, and not forced into OM's brand
hues. Named by ratio tier + the background it's solved against (`-box` =
against `--om-box-bg`, `-page` = against `--om-body-bg`):

| Token | Ratio | Used for |
|---|---|---|
| `chrome-muted` | ~4.85:1 on box-bg | `.row-actions` text |
| `chrome-muted-strong` | ~6.4:1 on box-bg | Comments "submitted on" timestamp |
| `chrome-muted-page` | ~4.85:1 on body-bg | `.subsubsub` filters, "no items" empty states |
| `chrome-faint` | ~1.8:1 on body-bg | unsorted sort arrow, view-switch base icon |
| `chrome-soft` | ~3.7:1 on body-bg | view-switch hover icon |
| `chrome-soft-box` | ~2.85:1 on box-bg | "locked by another user" icon |
| `chrome-strong-box` | ~9.4:1 on box-bg | mobile row-toggle icon, plugin-card compatibility icon |
| `chrome-strong-page` | ~8.8:1 on body-bg | Comments column header icon |
| `chrome-accent-soft` | ~4.5:1 on body-bg | view-switch "current" icon — ACCENT, dimmed from `link-color` |

This is what keeps the list table screen's soft, low-key feel consistent
across every scheme instead of drifting harsher in Dark or washier in
Light. If you add a new list-table element with a hardcoded core color,
check whether it matches one of these ratios (against the right
background) before inventing a new token — most row-level chrome turns
out to fall into one of these nine tiers.

**Specificity gotcha (`#adminmenu` current-item arrow):** WP core's
`ul#adminmenu a.wp-has-current-submenu::after` and
`ul#adminmenu > li.current > a.current::after` (the little triangle that
points into the page content for the active menu item) are unusually
specific. A plain `#adminmenu a.wp-has-current-submenu:after` /
`#adminmenu li.current a.menu-top:after` override loses to them on
specificity alone — core wins regardless of CSS load order, so the arrow
stays whatever color core set instead of `var(--om-body-bg)`. The fix used
in `_shared.scss` is to also add `body.wp-admin`-prefixed selectors that
mirror core's exact structure, which pushes specificity above core's. If
you add more overrides targeting `#adminmenu`'s current/active states,
check the matching core selector's specificity first rather than assuming
an ID selector is automatically enough.

This turned out not to be a one-off: an audit of the whole file against
WP core's actual admin CSS (`wp-admin/css/{common,admin-menu,forms,
list-tables,login}.css`, `wp-includes/css/admin-bar.css`) found several
more of the same shape — mostly on `:hover`/`:focus`/`:current` *compound*
states (a hover rule alone was usually fine; the bug was almost always a
more specific `:focus`, `.current`, or `li a.wp-has-current-submenu`
variant of the same element that core styles separately). Each is now
fixed in `_shared.scss` with an inline comment naming the exact core
selector it's beating — see the comments above the admin-bar
`.quicklinks .menupop ul li a:hover/:focus` rule, the `#wpadminbar.nojq`
focus rule, the `li.wp-has-current-submenu a:focus` icon rule, the
`li.current`/`li a.wp-has-current-submenu` bubble-counter rule, the
`.stuffbox .hndle` border rule, the `.wp-list-table thead/tfoot` border
rule, the `.color-option` rule, the login `:focus` rule, and the
`abbr.required`/`span.required` rule. **The general lesson:** when this
file overrides a color on a `:hover`, `:focus`, or "current/active" state,
don't assume the default selector is enough — check whether WP core has a
*more specific* variant of that same state (it very often does, since
core itself frequently layers a plain hover rule with a separate, more
qualified focus/current rule) and make sure this file's override matches
or exceeds it.

One related case was deliberately left alone: `forms.css`'s
`.form-invalid.form-required input:focus`/`select:focus` sets a hardcoded
red `border-color` on invalid required fields, and out-specifies this
file's generic focus-ring rule. That's arguably correct behavior (an
error-state border probably *should* stay red regardless of admin color
scheme, not switch to `--om-focus-color`), so it wasn't changed — but flag
it if OM ever wants the focus color to win there too.

Some of these turned out to be plain *ties* rather than core being more
specific: `forms.css`'s `.form-table th, .form-wrap label`, edit.css's
`p.description, .form-wrap p`, and `forms.css`'s `.color-option:hover,
.color-option.selected` (the Profile screen's own color-scheme picker)
all match this file's selector specificity-for-specificity, so which one
wins is purely a function of stylesheet load order — evidently not
reliably in our favor in practice. Fixed the same way, with
`body.wp-admin`-prefixed variants added alongside. `.form-wrap p`/
`p.description` had no override at all before this — worth remembering
that "core ties us" and "core was never challenged in the first place"
look identical in the rendered page, so a plain visual check against the
WP core color-scheme partial (`wp-admin/css/colors/_admin.scss`) doesn't
catch *everything* this file should be covering — only what it already
attempts to cover.

A few more bugs surfaced by actually looking at the schemes in a browser,
not just by comparing selectors to core — none of these three were
specificity problems:

- **Checked checkbox/radio looked the same as unchecked in OM Light.**
  forms.css's own `input[type=checkbox]:checked`/`input[type=radio]:checked`
  already read `var(--wp-admin-theme-color)` for both fill and border, and
  the `:root` block at the top of `_shared.scss` already points that
  variable at `--om-link-color` — so core's *unmodified* rule was already
  correct. An earlier override in this file re-set the checked
  `background` to `--om-button-secondary-bg`, which happens to equal
  `--om-box-bg`'s white in Light mode — the same as the *unchecked*
  background — making the two states nearly indistinguishable. Fix was to
  delete the override entirely and let core's rule (reading our remapped
  variable) do the right thing. Lesson: before adding an override, check
  whether core already reads a `--wp-admin-theme-color`-family variable
  this file redefines — sometimes the correct fix is *removing* CSS, not
  adding more.
- **OM Dark's `<select>` arrow was unreadably dark.** forms.css sets the
  box fill color AND the dropdown-arrow SVG in one `background` shorthand,
  with the arrow's fill hardcoded inside the SVG data URI. Overriding only
  `background-color` (a longhand) leaves that hardcoded dark arrow in
  place — invisible against a dark `--om-box-bg`. Since a data URI can't
  reference a CSS custom property, `_tokens.scss` now carries a
  `select-arrow-icon` token per scheme (the same SVG with `fill` swapped
  to that scheme's `body-text` color) that `_shared.scss` applies via
  `background-image`.
- **Hovering the dropdown made its text unreadable in OM Dark.**
  forms.css's `.wp-core-ui select:hover` hardcodes `color: #1e1e1e`
  regardless of scheme — fine against core's own always-white select
  background, unreadable against `--om-box-bg`'s dark navy. Added a
  `.wp-core-ui select:hover` override restoring `--om-body-text` and using
  `--om-focus-color` for the border, so — like WP core's own hover state —
  the border color is the primary visible change.

**Media Library modal (`#wp-media-modal`) — a full pass, not spot fixes.**
This got a comprehensive sweep of `wp-includes/css/media-views.css` and
`wp-admin/css/media.css` rather than one-off reports, since the whole
modal turned out to be almost entirely unthemed. The approach: treat the
whole modal as "just another card surface" and reuse the existing
postbox/card tokens (`--om-box-bg`, `--om-box-border`, `--om-body-text`,
`--om-heading-color`, `--om-table-row-hover-bg`, `--om-success`,
`--om-error`, `--om-warning`, `--om-focus-color`) rather than inventing
modal-specific ones — covers the modal frame, the left menu rail and top
router tabs, the attachment grid (including the selected/details rings,
filename captions, and checkmark badges), the attachment-details sidebar
(popup modal, Image Details view, and Edit Attachment screen — three
different selectors for the same "sidebar card" role), the bottom
selection toolbar, the drag-and-drop uploader, the image editor/crop
tool, and modal-wide placeholder text.

Two new tokens were needed, both following the `link-color-rgb` pattern
already established for `--wp-admin-theme-color--rgb`: **`box-bg-rgb`**
and **`error-rgb`** — comma-separated RGB channels of `box-bg`/`error`,
for the couple of places core composites `rgba(var(--x--rgb), alpha)`
translucent overlays (attachment caption strips, the drag-and-drop
overlay, an upload-error tint) instead of a flat color. Note for anyone
extending these: `rgba(var(--token), alpha)` (the legacy comma 4-argument
form) is what core's own consumers expect and what these tokens are
comma-formatted for — `stylelint --fix` will rename `rgba` to `rgb`
(they're aliases in modern CSS) but do **not** change these to
space+slash syntax by hand, since a comma-separated custom property value
can't mix with slash-alpha notation.

Two specificity findings worth remembering if you touch attachment tiles
again:
- **`.wp-core-ui .selected.attachment` and `.wp-core-ui .attachment.details`
  are exactly the same specificity (0,3,0)**, and WP's own JS puts both
  classes on a tile at once (checked *and* open in the details view).
  Core declares `.details` second so its accent ring wins the tie; this
  file's own two rules are declared in that same order for the same
  reason — same category of bug as the primary/secondary button fix
  above, caught proactively this time instead of after a report. The
  grid-page variant (`.media-frame.mode-grid .selected/.details`, media.css)
  has the identical ordering requirement.
- **Cross-file tie**: `.wp-core-ui .selected.attachment` (media-views.css)
  and `.media-frame.mode-grid .selected.attachment` (media.css) are also
  an exact tie with each other. Both are covered here as separate rules
  using different tokens on purpose (`--om-box-bg` vs `--om-body-bg` — the
  grid page's ring sits on the admin page background, not a card, since
  the grid renders directly on the page there, not inside a modal).

One deliberate design override, not just a hardcoded-value swap: the
full-page drag-and-drop overlay (`.uploader-window`) reads
`--wp-admin-theme-color--rgb` as a **90%-opaque fill** with white text
drawn on top. That variable is already remapped to this scheme's accent
elsewhere, which is correct for the many places core uses it for *text*
or thin *borders* — but the accent color is tuned for text contrast, not
for standing in as a near-solid fill behind white overlay text, and using
it that way risked looking washed out. Overridden to use `--om-box-bg`
instead, keeping it consistent with every other surface in the modal.

Fixed pre-emptively, not from a report: `.media-frame a.button`/
`a.button-primary` (media-views.css) out-specify this file's generic
`.wp-core-ui .button`/`.button-primary` rules by one extra class. No
core-rendered anchor-styled button was found inside the modal today (WP's
own toolbar controls are all real `<button>` elements), but third-party
code sometimes renders one, so this closes the gap defensively rather
than waiting for it to surface as a bug report.

## Known limitations / things worth testing in Claude Code

- **OM Dark doesn't theme every screen/element yet**, and there's no
  general mechanism to tell the user when they've landed on one that
  merely *isn't themed yet* (as opposed to structurally can't be — see
  "Editor brightness warning" above for the one case that got its own
  targeted fix). Text inputs/textarea, the Screen Options/Help tabs, and
  list table rows were fixed (see "Color system" above); still open: a
  `<select>`'s text can go grey/unreadable after focusing then blurring it
  without choosing an option (not yet root-caused — likely another core
  state variant like the `:focus`/`:hover` ones already fixed, this time
  on blur). Rather than keep chasing every individual case one report at a
  time, the plan is to build a way to flag un-themed screens/elements to
  whoever's using OM Dark — not yet designed or built. If you pick this
  up: decide whether "flag" means a visual indicator, a console warning,
  an admin notice, or something else before writing code.
- **Two open reports that couldn't be traced to a literal core selector**
  despite an exhaustive search of every relevant core CSS file (common.css,
  edit.css, dashboard.css, list-tables.css, site-health.css, all their
  `@media` blocks, and a search for JS-injected class usage):
  - `.health-check-accordion-heading` reported showing a white background
    in some state. Core only ever declares two rules for this exact class
    (`margin`/`border-top`/`font`/`color: inherit` — no background — and
    a `:first-child` variant that only touches `border-top`), and neither
    the `<h3>`/`<h4>` element it's on nor any of its ancestors up through
    `.health-check-body`/`.health-check-accordion` have a background rule
    in core CSS at all. If you pick this up, check the browser's inspector
    for the actual matched/computed rule and its source file:line rather
    than re-deriving from source — it may be something JS-injected, a
    different WP version's CSS, or a rule this search genuinely missed.
  - `.notification-dialog` reported similarly. This class itself has *no*
    dedicated CSS rule anywhere in core (confirmed by grepping every
    non-minified, non-RTL core CSS file) — it's a reusable wrapper class
    referenced from JS (`post.js`'s post-lock dialog,
    `updates.js`'s filesystem-credentials modal) whose actual chrome
    comes from context-specific ID selectors instead (e.g.
    `#wp-auth-check-wrap #wp-auth-check { background-color: #f0f0f1; }`
    for the session-expired re-login popup, which was **not** touched by
    this pass). Need to know which specific dialog was being looked at
    (session-expired? post-lock? filesystem credentials? a media-modal
    confirmation?) to find the real selector to theme.
- Only tested by inspection/contrast math, not against a live wp-admin —
  worth loading this on a real (or local) WordPress install and eyeballing:
  menu hover/current states, submenu flyouts, the admin bar's "My Account"
  dropdown, list table hover, notice colors (success/warning/error), and
  the button states (primary/secondary, hover, focus).
- No dark-mode-specific favicon/login-logo swap is included — only admin
  chrome colors are themed. If OM wants a dark-mode logo variant on the
  login screen, that's an additional `login_enqueue_scripts` hook, not yet
  built.
- Accessibility numbers above are computed against *solid* backgrounds
  only; anywhere a color sits on a gradient, image, or semi-transparent
  overlay wasn't re-verified.
- No automated tests. If you add any (e.g. a PHP unit test asserting all
  three `wp_admin_css_color()` keys register, or a visual regression tool
  against wp-admin), this is the natural place to wire them in.
- WordPress reads `src/*.css` directly at runtime, but that folder is
  never in this repo's git history — it only exists locally (via
  `npm run build`) or transiently inside the release workflow (see
  "Releases"). `node_modules/`, `src/`, and `releases/` are all
  gitignored; everything else in the repo is tracked normally.

## Conventions

- All custom properties are prefixed `--om-` to avoid collisding with
  WordPress core's own custom properties or those from other plugins.
- Indentation in `scss/` is tabs, matching WordPress core coding standards
  for CSS (enforced by `.stylistic/indentation` in `.stylelintrc.cjs`).
  Compiled `src/*.css` is generated output from `sass`'s `expanded` style
  (2-space indent) — don't hand-reformat it to tabs; it'll just get
  overwritten by the next `npm run build`.
- Hex colors are uppercase, full-length (`#F7F7F7`, not `#f7f7f7` or
  `#FFF`) — the convention already established by the original hand-written
  CSS before the SCSS migration; configured via `@stylistic/color-hex-case`
  and `color-hex-length` rather than left to stylelint's lowercase/shorthand
  defaults.
- PHP follows WordPress PHP coding standards. The one existing conditional
  (`enqueue_editor_warning_script()`'s scheme check) uses `in_array()`
  rather than a direct `==`/`===` comparison, so yoda ordering doesn't
  apply there — keep that in mind (flip to `'value' === $var` order) if
  you add a direct equality comparison.
