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

	dialog.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( '[data-diluxone-users-dialog-close]' ) ) {
			dialog.close();
			return;
		}

		// A click on the backdrop lands on the dialog itself, outside its box.
		if ( event.target === dialog ) {
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
 * The account-area preview follows the fields while they are being chosen.
 *
 * What is inside the frame came from the server and is the real account area,
 * so nothing in here draws anything: it changes the same custom properties
 * and the same classes the front end changes, on the markup the site serves.
 * Turning the stylesheet off strips the class that loads it, which is what
 * the theme would see; ticking off a piece of the header hides the piece the
 * template would not have printed.
 */
( function () {
	'use strict';

	var box = document.querySelector( '[data-diluxone-users-preview-box]' );

	if ( ! box ) {
		return;
	}

	var skin    = box.querySelector( '[data-diluxone-users-preview-skin]' );
	var area    = skin ? skin.querySelector( '.diluxone-users-account' ) : null;
	var bare    = box.querySelector( '[data-diluxone-users-preview-bare]' );
	var accent  = document.getElementById( 'diluxone_users_style_accent' );
	var radius  = document.getElementById( 'diluxone_users_style_radius' );
	var styles  = document.querySelector( 'input[name="diluxone_users_styles"]' );
	var cover   = document.getElementById( 'diluxone_users_account_cover' );

	function piece( name ) {
		return document.querySelector( '[data-diluxone-users-piece="' + name + '"]' );
	}

	function chosen( name ) {
		var inputs = document.querySelectorAll( '[data-diluxone-users-piece="' + name + '"]' );
		var value  = '';

		inputs.forEach( function ( input ) {
			if ( input.checked ) {
				value = input.value;
			}
		} );

		return value;
	}

	/** One class out of a set of them, so the old one never lingers. */
	function only( element, prefix, value, all ) {
		all.forEach( function ( one ) {
			element.classList.toggle( prefix + one, one === value );
		} );
	}

	function paint() {
		if ( accent && accent.value ) {
			skin.style.setProperty( '--diluxone-users-accent', accent.value );
			skin.style.setProperty( '--diluxone-users-accent-bg', accent.value );
		}

		if ( radius ) {
			var r = '' === radius.value ? '' : radius.value + 'px';

			skin.style.setProperty( '--diluxone-users-radius', r );
			skin.style.setProperty( '--diluxone-users-radius-sm', r );
		}

		var on = ! styles || styles.checked;

		box.classList.toggle( 'is-bare', ! on );

		if ( bare ) {
			bare.hidden = on;
		}

		shape();
	}

	/** The shape of the area: the template, the menu, the width, the pieces. */
	function shape() {
		if ( ! area ) {
			return;
		}

		var picked   = document.querySelector( '[data-diluxone-users-template]:checked' );
		var template = picked ? picked.value : '';
		var layout   = chosen( 'layout' );
		var width    = chosen( 'width' );

		if ( template ) {
			only( area, 'diluxone-users-account--', template, [ 'plain', 'cover' ] );
		}

		if ( layout ) {
			only( area, 'diluxone-users-account--', layout, [ 'tabs', 'side', 'none' ] );
		}

		if ( width ) {
			only( area, 'diluxone-users-account--', width, [ 'contained', 'full' ] );
		}

		if ( cover && cover.value ) {
			area.style.setProperty( '--diluxone-users-cover', cover.value );
		}

		// The menu is markup the server already sent: moving it between the
		// bar and the body is not something to fake, so what changes is
		// whether each place shows what it holds.
		hide( '.diluxone-users-account__bar', 'tabs' !== layout );
		hide( '.diluxone-users-account__body > .diluxone-users-account__nav', 'side' !== layout );

		var header = piece( 'header' );

		hide( '.diluxone-users-account__header', header && ! header.checked );
		hide( '.diluxone-users-account__avatar', ticked( 'avatar' ) );
		hide( '.diluxone-users-account__since', ticked( 'since' ) );
		hide( '.diluxone-users-account__action', ticked( 'action' ) );
	}

	/** A piece is off when its box exists and is not ticked. */
	function ticked( name ) {
		var input = piece( name );

		return !! input && ! input.checked;
	}

	function hide( selector, off ) {
		var element = area.querySelector( selector );

		if ( element ) {
			element.hidden = !! off;
		}
	}

	[ accent, radius, styles, cover ].forEach( function ( field ) {
		if ( field ) {
			field.addEventListener( 'input', paint );
			field.addEventListener( 'change', paint );
		}
	} );

	document.querySelectorAll( '[data-diluxone-users-piece]' ).forEach( function ( field ) {
		field.addEventListener( 'change', shape );
	} );

	// The presets do not save anything of their own: they fill in the three
	// fields and let the preview and the Save button do the rest.
	document.querySelectorAll( '[data-diluxone-users-preset]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			if ( styles ) {
				styles.checked = '1' === button.dataset.diluxoneUsersPresetStyles;
			}

			if ( accent && button.dataset.diluxoneUsersPresetAccent ) {
				accent.value = button.dataset.diluxoneUsersPresetAccent;
			}

			if ( radius ) {
				radius.value = button.dataset.diluxoneUsersPresetRadius;
			}

			paint();
		} );
	} );

	/**
	 * A template fills in the pieces underneath it, and stops there.
	 *
	 * It is a starting point and not a lid: every piece it just filled in can
	 * be changed straight afterwards, and changing one does not knock the
	 * template back out — the shape chosen is a decision of its own.
	 */
	document.querySelectorAll( '[data-diluxone-users-template]' ).forEach( function ( radioButton ) {
		radioButton.addEventListener( 'change', function () {
			var pieces = {};

			try {
				pieces = JSON.parse( radioButton.dataset.diluxoneUsersTemplatePieces || '{}' );
			} catch ( error ) {
				pieces = {};
			}

			[ 'header', 'avatar', 'since', 'action' ].forEach( function ( name ) {
				var input = piece( name );

				if ( input && undefined !== pieces[ name ] ) {
					input.checked = !! Number( pieces[ name ] );
				}
			} );

			[ 'layout', 'width' ].forEach( function ( name ) {
				if ( undefined === pieces[ name ] ) {
					return;
				}

				var input = document.querySelector( '[data-diluxone-users-piece="' + name + '"][value="' + pieces[ name ] + '"]' );

				if ( input ) {
					input.checked = true;
				}
			} );

			document.querySelectorAll( '.diluxone-users-templates__one' ).forEach( function ( label ) {
				label.classList.toggle( 'is-chosen', label.contains( radioButton ) );
			} );

			paint();
		} );
	} );

	paint();
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
