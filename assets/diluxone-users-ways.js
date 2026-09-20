/**
 * The ways into the site, turned from a stack into tabs.
 *
 * The server sends the stack: every way in on the page, one under the other,
 * separated by "or". That is the arrangement that needs nothing to work, and
 * it is what somebody with JavaScript switched off gets — the whole screen,
 * with nothing hidden behind a control that cannot be pressed.
 *
 * This script is what makes it tabs, and it does the two jobs the server
 * cannot: it takes the `hidden` off the strip it was sent, and it puts the
 * ARIA on. The roles arrive with the behaviour and not before it — a
 * `role="tab"` that opens nothing, announced to somebody who cannot see that
 * all four panels are showing anyway, is a worse lie than a plain button.
 *
 * It also has the last word on WHICH tab opens. The server reads the cookie
 * and marks the tab, which is what stops the right one arriving a frame late;
 * but a sign-in page is exactly the kind of page a cache hands out already
 * built, and then the mark in the markup is whoever was served first. So the
 * cookie is read again here, in the browser it belongs to, and it wins.
 */
( function () {
	'use strict';

	var ARROWS = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 };

	/**
	 * What this browser remembers, or nothing.
	 *
	 * One id and no more ever goes in here — see includes/login-ways.php — so
	 * reading it is reading the name of a door, never anything about a person.
	 */
	function remembered( name ) {
		var found = document.cookie.split( '; ' ).find( function ( pair ) {
			return pair.indexOf( name + '=' ) === 0;
		} );

		return found ? decodeURIComponent( found.slice( name.length + 1 ) ) : '';
	}

	function remember( name, path, id ) {
		// A year, because the point of it is the person who comes back in six
		// months and finds the tab they always use already open. SameSite=Lax
		// so it does not travel with a request some other site made.
		document.cookie =
			name +
			'=' +
			encodeURIComponent( id ) +
			'; path=' +
			( path || '/' ) +
			'; max-age=31536000; SameSite=Lax' +
			( 'https:' === window.location.protocol ? '; Secure' : '' );
	}

	document.querySelectorAll( '[data-diluxone-users-ways]' ).forEach( function ( block ) {
		if ( 'tabs' !== block.getAttribute( 'data-diluxone-users-ways-mode' ) ) {
			return;
		}

		var strip = block.querySelector( '[data-diluxone-users-ways-strip]' );

		if ( ! strip ) {
			return;
		}

		var tabs = Array.prototype.slice.call( strip.querySelectorAll( '[data-diluxone-users-way-tab]' ) );

		// A panel per tab, in the strip's order. A tab whose panel is missing
		// is dropped rather than left pointing at nothing.
		// Created without a prototype so a cookie saying `constructor` is a tab
		// that does not exist rather than a function pretending to be a panel.
		var panels = Object.create( null );

		tabs = tabs.filter( function ( tab ) {
			var id    = tab.getAttribute( 'data-diluxone-users-way-tab' );
			var panel = block.querySelector( '[data-diluxone-users-way="' + id + '"]' );

			if ( ! panel ) {
				tab.remove();

				return false;
			}

			panels[ id ] = panel;

			return true;
		} );

		if ( tabs.length < 2 ) {
			return;
		}

		var cookie = block.getAttribute( 'data-diluxone-users-ways-cookie' ) || 'diluxone_users_way';
		var path   = block.getAttribute( 'data-diluxone-users-ways-path' ) || '/';

		strip.setAttribute( 'role', 'tablist' );

		tabs.forEach( function ( tab ) {
			var id = tab.getAttribute( 'data-diluxone-users-way-tab' );

			tab.setAttribute( 'role', 'tab' );
			tab.setAttribute( 'aria-controls', panels[ id ].id );

			panels[ id ].setAttribute( 'role', 'tabpanel' );
			panels[ id ].setAttribute( 'aria-labelledby', tab.id );
			// Focusable, because a panel holding nothing focusable — a row of
			// network buttons is links, not fields — would otherwise be a
			// region the keyboard cannot reach at all.
			panels[ id ].setAttribute( 'tabindex', '0' );
		} );

		// The "or" between two stacked ways says nothing between a strip and
		// the one panel under it.
		block.querySelectorAll( '[data-diluxone-users-ways-or]' ).forEach( function ( or ) {
			or.hidden = true;
		} );

		/**
		 * Opens one.
		 *
		 * Only one tab is in the tab order: inside a tab strip the arrows move
		 * between them, and Tab is what leaves for the panel. A strip of four
		 * stops on four times on the way past.
		 */
		function open( id, moveFocus ) {
			tabs.forEach( function ( tab ) {
				var mine = tab.getAttribute( 'data-diluxone-users-way-tab' ) === id;

				tab.setAttribute( 'aria-selected', String( mine ) );
				tab.setAttribute( 'tabindex', mine ? '0' : '-1' );
				panels[ tab.getAttribute( 'data-diluxone-users-way-tab' ) ].hidden = ! mine;

				if ( mine && moveFocus ) {
					tab.focus();
				}
			} );
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				var id = tab.getAttribute( 'data-diluxone-users-way-tab' );

				open( id, false );
				remember( cookie, path, id );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var step = ARROWS[ event.key ];
				var to   = null;

				if ( step ) {
					to = tabs[ ( index + step + tabs.length ) % tabs.length ];
				} else if ( 'Home' === event.key ) {
					to = tabs[ 0 ];
				} else if ( 'End' === event.key ) {
					to = tabs[ tabs.length - 1 ];
				}

				if ( ! to ) {
					return;
				}

				event.preventDefault();

				var id = to.getAttribute( 'data-diluxone-users-way-tab' );

				open( id, true );
				remember( cookie, path, id );
			} );
		} );

		var asked = block.getAttribute( 'data-diluxone-users-ways-open' );
		var last  = remembered( cookie );

		/*
		 * The cookie wins over the mark in the markup, unless the server is
		 * answering something that just happened: a page that comes back
		 * saying an address was refused has to show the address form,
		 * whichever tab this person prefers.
		 */
		open( block.hasAttribute( 'data-diluxone-users-ways-state' ) || ! panels[ last ] ? asked : last, false );

		strip.hidden = false;
	} );
}() );
