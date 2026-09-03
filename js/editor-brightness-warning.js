/**
 * OM Admin Color Schemes — editor brightness warning.
 *
 * The block editor (post.php?action=edit, post-new.php) can't be
 * meaningfully dark-themed — its canvas is a WordPress-controlled iframe
 * that stays bright regardless of admin color scheme. Rather than let
 * someone on OM Dark get hit with that flash of brightness with zero
 * warning, this intercepts clicks on links headed there and offers a
 * chance to back out (or lower their screen brightness first) before
 * navigating.
 *
 * Only enqueued (see om-admin-color-schemes.php) for users on a
 * dark-capable scheme (om-dark, or om-system when currently resolving to
 * dark) — see isSchemeCurrentlyDark() below.
 */
( function () {
	'use strict';

	if ( typeof omEditorWarning === 'undefined' ) {
		return;
	}

	// WP admin sets a body class matching the current admin page's
	// filename (dots -> hyphens) — if we're already on one of these
	// screens, every link click is either an in-page anchor or a
	// navigation to ANOTHER already-bright editor screen (e.g. a
	// translations meta box), so there's nothing left to warn about.
	if ( document.body.classList.contains( 'post-php' ) || document.body.classList.contains( 'post-new-php' ) ) {
		return;
	}

	var DISMISS_KEY = 'omAdminColorSchemesEditorWarningDismissed';
	var modal = null;
	var pendingHref = null;
	var triggerElement = null;

	function isDismissedForever() {
		try {
			return Boolean( window.localStorage.getItem( DISMISS_KEY ) );
		} catch ( error ) {
			return false;
		}
	}

	function setDismissedForever() {
		try {
			window.localStorage.setItem( DISMISS_KEY, '1' );
		} catch ( error ) {
			// Private browsing / storage disabled — the checkbox just won't stick this time.
		}
	}

	// om-system has no server-side way to know the OS's current light/dark
	// preference, so this is checked live at click time rather than baked
	// into the localized "scheme" value.
	function isSchemeCurrentlyDark() {
		if ( omEditorWarning.scheme === 'om-dark' ) {
			return true;
		}

		if ( omEditorWarning.scheme === 'om-system' ) {
			return Boolean( window.matchMedia ) && window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
		}

		return false;
	}

	function isEditorLink( url ) {
		if ( url.origin !== window.location.origin ) {
			return false;
		}

		var file = url.pathname.slice( url.pathname.lastIndexOf( '/' ) + 1 );

		if ( file === 'post-new.php' ) {
			return true;
		}

		return file === 'post.php' && url.searchParams.get( 'action' ) === 'edit';
	}

	function getFocusable() {
		return Array.prototype.slice.call( modal.querySelectorAll( 'input, button' ) );
	}

	function trapFocus( event ) {
		var focusable = getFocusable();
		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	function buildModal() {
		var wrap = document.createElement( 'div' );

		wrap.className = 'om-editor-warning-modal';
		wrap.hidden = true;
		wrap.innerHTML =
			'<div class="om-editor-warning-modal__overlay" data-om-action="cancel"></div>' +
			'<div class="om-editor-warning-modal__dialog" role="alertdialog" aria-modal="true" aria-labelledby="om-editor-warning-title" aria-describedby="om-editor-warning-body">' +
				'<h2 id="om-editor-warning-title"></h2>' +
				'<p id="om-editor-warning-body"></p>' +
				'<label class="om-editor-warning-modal__checkbox">' +
					'<input type="checkbox" /><span></span>' +
				'</label>' +
				'<div class="om-editor-warning-modal__actions">' +
					'<button type="button" class="button" data-om-action="cancel"></button>' +
					'<button type="button" class="button button-primary" data-om-action="continue"></button>' +
				'</div>' +
			'</div>';

		// Set text via textContent, not the innerHTML string above, so
		// translated strings can never be interpreted as markup.
		wrap.querySelector( '#om-editor-warning-title' ).textContent = omEditorWarning.strings.title;
		wrap.querySelector( '#om-editor-warning-body' ).textContent = omEditorWarning.strings.body;
		wrap.querySelector( '.om-editor-warning-modal__checkbox span' ).textContent = omEditorWarning.strings.checkbox;
		wrap.querySelector( 'button[data-om-action="cancel"]' ).textContent = omEditorWarning.strings.cancel;
		wrap.querySelector( 'button[data-om-action="continue"]' ).textContent = omEditorWarning.strings.continueLabel;

		document.body.appendChild( wrap );

		wrap.addEventListener( 'click', function ( event ) {
			var action = event.target.getAttribute( 'data-om-action' );

			if ( action === 'cancel' ) {
				closeModal();
			} else if ( action === 'continue' ) {
				proceed();
			}
		} );

		wrap.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				closeModal();
			} else if ( event.key === 'Tab' ) {
				trapFocus( event );
			}
		} );

		return wrap;
	}

	function openModal( href, trigger ) {
		if ( ! modal ) {
			modal = buildModal();
		}

		pendingHref = href;
		triggerElement = trigger;
		modal.hidden = false;
		modal.querySelector( 'button[data-om-action="continue"]' ).focus();
	}

	function closeModal() {
		if ( ! modal ) {
			return;
		}

		modal.hidden = true;
		modal.querySelector( 'input[type="checkbox"]' ).checked = false;
		pendingHref = null;

		if ( triggerElement ) {
			triggerElement.focus();
			triggerElement = null;
		}
	}

	function proceed() {
		var href = pendingHref;

		if ( modal.querySelector( 'input[type="checkbox"]' ).checked ) {
			setDismissedForever();
		}

		closeModal();
		window.location.href = href;
	}

	document.addEventListener( 'click', function ( event ) {
		if ( event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}

		var link = event.target.closest( 'a[href]' );

		if ( ! link || link.target === '_blank' ) {
			return;
		}

		if ( isDismissedForever() || ! isSchemeCurrentlyDark() ) {
			return;
		}

		var url = new URL( link.href, window.location.href );

		if ( ! isEditorLink( url ) ) {
			return;
		}

		event.preventDefault();
		openModal( link.href, link );
	} );
} )();
