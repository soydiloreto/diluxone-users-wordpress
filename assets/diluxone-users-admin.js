/**
 * A field's detail shows only what that type needs.
 *
 * A date field has no options and a country field is not configured: the
 * plugin brings the list. Offering boxes that do nothing is asking whoever is
 * configuring it to guess which ones matter.
 *
 * Without JavaScript they are all visible, which is the worst that can
 * happen: the form still works and every row says which type it is for.
 *
 * It takes the piece of the page to work on, because the form is not always
 * on the page from the start: the dialog drops it in later and has to be able
 * to wire up the copy it just dropped.
 */
function diluxoneUsersFieldTypes( root ) {
	'use strict';

	var type = root.querySelector( '#diluxone-users-type' );

	if ( ! type ) {
		return;
	}

	var rows = root.querySelectorAll( '.diluxone-users-if-type' );

	function review() {
		var chosen = type.value;

		rows.forEach( function ( row ) {
			var forTypes = ( row.getAttribute( 'data-type' ) || '' ).split( ' ' );

			row.hidden = -1 === forTypes.indexOf( chosen );
		} );
	}

	type.addEventListener( 'change', review );
	review();
}

diluxoneUsersFieldTypes( document );

/**
 * A field is edited on top of its list, not on another screen.
 *
 * The form is not written twice: it is fetched from the screen that already
 * draws it — the same address the link points at — and lifted out of the
 * answer. So the link keeps working with JavaScript off or when the fetch
 * fails, and there is no second copy of thirty settings to keep in step.
 *
 * Saving is a plain submit, the same one that screen does: the browser posts,
 * WordPress saves and redirects back to the list with its notice. No parallel
 * save path that validates a little differently from the real one.
 */
( function () {
	'use strict';

	var dialog = document.querySelector( '[data-diluxone-users-dialog]' );

	// Without <dialog> the links are left alone and go to their screen.
	if ( ! dialog || 'function' !== typeof dialog.showModal ) {
		return;
	}

	var body    = dialog.querySelector( '[data-diluxone-users-dialog-body]' );
	var heading = dialog.querySelector( '[data-diluxone-users-dialog-heading]' );
	var loading = body.innerHTML;

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '[data-diluxone-users-field-dialog]' );

		// A modified click is somebody asking for a new tab. Let them have it.
		if ( ! link || event.metaKey || event.ctrlKey || event.shiftKey || 0 !== event.button ) {
			return;
		}

		event.preventDefault();
		open( link );
	} );

	/*
	 * Only the close button and Cancel close it. A click on the greyed-out
	 * page does not: this dialog holds a form somebody is halfway through
	 * filling in, and a stray click outside it should not throw that away.
	 * Escape still works — it is what a keyboard expects, and it is deliberate
	 * in a way a misplaced click is not.
	 */
	dialog.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( '[data-diluxone-users-dialog-close]' ) ) {
			dialog.close();
		}
	} );

	function open( link ) {
		heading.textContent = link.getAttribute( 'data-diluxone-users-dialog-title' ) || '';
		body.innerHTML      = loading;

		dialog.showModal();

		window.fetch( link.href, { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.ok ? response.text() : Promise.reject( response.status );
			} )
			.then( function ( html ) {
				var page = new window.DOMParser().parseFromString( html, 'text/html' );
				var form = page.querySelector( 'form.diluxone-users-form-admin' );

				if ( ! form ) {
					return Promise.reject( 'no form' );
				}

				body.innerHTML = '';
				body.appendChild( document.adoptNode( form ) );

				diluxoneUsersFieldTypes( form );
				addCancel( form );

				var first = form.querySelector( 'input:not([type="hidden"]), select, textarea' );

				if ( first ) {
					first.focus();
				}
			} )
			.catch( function () {
				// Whatever went wrong, the screen behind the dialog still works.
				dialog.close();
				window.location.href = link.href;
			} );
	}

	/** The way out that is not saving, next to the way out that is. */
	function addCancel( form ) {
		var submit = form.querySelector( 'p.submit' );

		if ( ! submit || submit.querySelector( '[data-diluxone-users-dialog-close]' ) ) {
			return;
		}

		var cancel = document.createElement( 'button' );

		cancel.type      = 'button';
		cancel.className = 'button';
		cancel.textContent = dialog.getAttribute( 'data-diluxone-users-cancel' ) || 'Cancel';
		cancel.setAttribute( 'data-diluxone-users-dialog-close', '' );

		submit.appendChild( document.createTextNode( ' ' ) );
		submit.appendChild( cancel );
	}
}() );

/**
 * The live test opens in a small window, not in a tab.
 *
 * The link already works without this — it has a target — so all that happens
 * here is giving it a size. If the provider blocks the pop-up, the click is
 * left alone and the test opens in a tab anyway.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '[data-diluxone-users-popup]' );

		if ( ! link ) {
			return;
		}

		var size   = ( link.getAttribute( 'data-diluxone-users-popup' ) || '' ).split( 'x' );
		var width  = parseInt( size[ 0 ], 10 ) || 600;
		var height = parseInt( size[ 1 ], 10 ) || 740;

		var popup = window.open(
			link.href,
			link.target || 'diluxone-users-popup',
			'width=' + width + ',height=' + height + ',scrollbars=yes,resizable=yes'
		);

		if ( popup ) {
			event.preventDefault();
			popup.focus();
		}
	} );
}() );

/**
 * The button preview follows the selectors while they are being chosen.
 *
 * It only changes classes on the real markup: there is no copy of the design
 * in here that could drift from the site. The text is not previewed live —
 * saving is needed to see it — because the name of each network comes from
 * the server and rebuilding it here would be exactly that copy.
 */
( function () {
	'use strict';

	var canvas = document.querySelector( '.diluxone-users-buttons__canvas .diluxone-users-socials' );

	if ( ! canvas ) {
		return;
	}

	var map = {
		skin:  [ 'brand', 'light', 'dark' ],
		shape: [ 'rounded', 'pill', 'square' ],
		show:  [ 'icon-text', 'icon' ],
		cols:  [ 'cols-0', 'cols-1', 'cols-2' ]
	};

	function apply( group, value ) {
		var classes = map[ group ];
		var wanted  = 'cols' === group ? 'cols-' + value : value;

		classes.forEach( function ( name ) {
			canvas.classList.toggle( 'diluxone-users-socials--' + name, name === wanted );
		} );
	}

	document.querySelectorAll( '[data-diluxone-users-preview]' ).forEach( function ( field ) {
		field.addEventListener( 'change', function () {
			apply( field.getAttribute( 'data-diluxone-users-preview' ), field.value );
		} );
	} );

	// The canvas background. A dark finish on white looks great and vanishes
	// against the site's dark background; both have to be viewable.
	var canvasBox = document.querySelector( '.diluxone-users-buttons__canvas' );

	document.querySelectorAll( '[data-diluxone-users-background]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var dark = 'dark' === button.getAttribute( 'data-diluxone-users-background' );

			canvasBox.classList.toggle( 'diluxone-users-buttons__canvas--dark', dark );

			document.querySelectorAll( '[data-diluxone-users-background]' ).forEach( function ( other ) {
				other.setAttribute( 'aria-pressed', String( other === button ) );
			} );
		} );
	} );
}() );

/**
 * Dragging to order the account sections.
 *
 * The order travels in the <input hidden> of each row, so moving them in the
 * DOM already leaves the form ready: on drop it submits itself and the page
 * comes back with the new order. Without JavaScript there is no dragging and
 * the save button remains, which is what makes this usable with the keyboard
 * too.
 *
 * It is native HTML5 and not jQuery UI: it is thirty lines and it does not
 * drag in 90 KB of dependency to move five rows.
 */
( function () {
	'use strict';

	var list = document.querySelector( '[data-diluxone-users-sortable]' );

	if ( ! list ) {
		return;
	}

	var dragged = null;

	list.querySelectorAll( 'li' ).forEach( function ( row ) {
		row.draggable = true;

		row.addEventListener( 'dragstart', function ( e ) {
			dragged = row;
			row.classList.add( 'is-dragging' );
			e.dataTransfer.effectAllowed = 'move';
			// Firefox does not start the drag without this.
			e.dataTransfer.setData( 'text/plain', '' );
		} );

		row.addEventListener( 'dragend', function () {
			row.classList.remove( 'is-dragging' );

			if ( dragged ) {
				dragged = null;
				list.closest( 'form' ).submit();
			}
		} );

		row.addEventListener( 'dragover', function ( e ) {
			if ( ! dragged || dragged === row ) {
				return;
			}

			e.preventDefault();

			var box   = row.getBoundingClientRect();
			var above = e.clientY < box.top + box.height / 2;

			list.insertBefore( dragged, above ? row : row.nextSibling );
		} );
	} );
}() );

/**
 * "To everybody" hides the list of roles; "only to some" shows it.
 *
 * Without JavaScript the list is simply always there, and the radio above is
 * what the server reads: the screen is usable either way, which is the same
 * rule the rest of this admin follows.
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-diluxone-users-scope]' ).forEach( function ( box ) {
		var roles  = box.querySelector( '[data-diluxone-users-scope-roles]' );
		var radios = box.querySelectorAll( 'input[type="radio"]' );

		if ( ! roles || ! radios.length ) {
			return;
		}

		function sync() {
			var some = box.querySelector( 'input[type="radio"][value="some"]' );

			roles.hidden = ! ( some && some.checked );
		}

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', sync );
		} );

		sync();
	} );
}() );

/**
 * The preview follows the fields, and the server draws it.
 *
 * It used to be done here: move a class, hide an element. That works for a
 * colour and lies about everything else — asking for the menu at the side did
 * nothing, because the markup for a menu at the side is not on the page when
 * the saved setting says tabs. There is nothing for JavaScript to move.
 *
 * So the form is sent as it stands and the answer is the real thing rendered
 * with it. Nothing is saved; the values only live for that one request.
 */
( function () {
	'use strict';

	var box = document.querySelector( '[data-diluxone-users-live]' );

	if ( ! box || ! window.ajaxurl ) {
		return;
	}

	var form = document.querySelector( '.diluxone-users-studio__fields form' );

	if ( ! form ) {
		return;
	}

	var panel   = box.getAttribute( 'data-diluxone-users-live' );
	var screen  = box.getAttribute( 'data-diluxone-users-live-screen' );
	var nonce   = box.getAttribute( 'data-diluxone-users-live-nonce' );
	var waiting = null;
	var current = 0;

	/**
	 * What the form says right now.
	 *
	 * Unticked boxes are sent as 0 rather than left out: leaving them out is
	 * how a preview ends up showing something that was just turned off.
	 */
	function values() {
		var out = {};

		form.querySelectorAll( 'input, select, textarea' ).forEach( function ( field ) {
			var name = field.name;

			if ( ! name || 0 !== name.indexOf( 'diluxone_users_' ) ) {
				return;
			}

			if ( 'checkbox' === field.type ) {
				out[ name ] = field.checked ? field.value || '1' : '0';

				return;
			}

			if ( 'radio' === field.type ) {
				if ( field.checked ) {
					out[ name ] = field.value;
				}

				return;
			}

			out[ name ] = field.value;
		} );

		return out;
	}

	function draw() {
		var body = new window.URLSearchParams();
		var mine = ++current;

		body.set( 'action', 'diluxone_users_preview' );
		body.set( 'nonce', nonce );
		body.set( 'screen', screen );
		body.set( 'panel', panel );

		var all = values();

		Object.keys( all ).forEach( function ( key ) {
			body.set( 'values[' + key + ']', all[ key ] );
		} );

		box.classList.add( 'is-working' );

		window.fetch( window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( answer ) {
				// A slower answer from an older keystroke must not land on top
				// of a newer one.
				if ( mine !== current || ! answer || ! answer.success ) {
					return;
				}

				box.innerHTML = answer.data;
			} )
			.catch( function () {
				// The preview stays as it was, which is the last true thing
				// it showed.
			} )
			.finally( function () {
				if ( mine === current ) {
					box.classList.remove( 'is-working' );
				}
			} );
	}

	form.addEventListener( 'input', function () {
		window.clearTimeout( waiting );
		waiting = window.setTimeout( draw, 350 );
	} );

	form.addEventListener( 'change', function () {
		window.clearTimeout( waiting );
		waiting = window.setTimeout( draw, 120 );
	} );

	/*
	 * A preset is not a setting of its own: it fills in the fields below it
	 * and gets out of the way. It lives here because it has to redraw
	 * afterwards, and because the fields it fills in are in this form.
	 */
	form.querySelectorAll( '[data-diluxone-users-preset]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var styles = form.querySelector( 'input[name="diluxone_users_styles"]' );
			var accent = form.querySelector( '#diluxone_users_style_accent' );
			var radius = form.querySelector( '#diluxone_users_style_radius' );

			if ( styles ) {
				styles.checked = '1' === button.dataset.diluxoneUsersPresetStyles;
			}

			if ( accent && button.dataset.diluxoneUsersPresetAccent ) {
				accent.value = button.dataset.diluxoneUsersPresetAccent;
			}

			if ( radius ) {
				radius.value = button.dataset.diluxoneUsersPresetRadius;
			}

			draw();
		} );
	} );
}() );

/**
 * Choosing a picture opens WordPress's own media library.
 *
 * Nothing is uploaded from here and no URL is typed: whoever is setting this
 * up already has the picture in the library, and an address written by hand
 * is an address that breaks the day the site changes domain. What is stored
 * is the attachment id, so the picture keeps working after that move.
 */
( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.media ) {
		return;
	}

	document.querySelectorAll( '[data-diluxone-users-image]' ).forEach( function ( box ) {
		var field   = box.querySelector( '[data-diluxone-users-image-id]' );
		var preview = box.querySelector( '[data-diluxone-users-image-preview]' );
		var pick    = box.querySelector( '[data-diluxone-users-image-pick]' );
		var clear   = box.querySelector( '[data-diluxone-users-image-clear]' );
		var frame;

		if ( ! field || ! pick ) {
			return;
		}

		pick.addEventListener( 'click', function () {
			// One frame per field, opened again rather than built again:
			// rebuilding it forgets what was chosen last time.
			if ( ! frame ) {
				frame = window.wp.media( {
					title: pick.textContent,
					library: { type: 'image' },
					multiple: false,
				} );

				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					var sizes = image.sizes || {};
					var shown = ( sizes.medium || sizes.full || image ).url;

					field.value = image.id;

					if ( preview ) {
						preview.querySelector( 'img' ).src = shown;
						preview.hidden = false;
					}

					if ( clear ) {
						clear.hidden = false;
					}
				} );
			}

			frame.open();
		} );

		if ( clear ) {
			clear.addEventListener( 'click', function () {
				field.value = '0';

				if ( preview ) {
					preview.hidden = true;
				}

				clear.hidden = true;
			} );
		}
	} );
}() );
