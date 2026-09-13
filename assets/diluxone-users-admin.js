/**
 * A field's detail shows only what that type needs.
 *
 * A date field has no options and a country field is not configured: the
 * plugin brings the list. Offering boxes that do nothing is asking whoever is
 * configuring it to guess which ones matter.
 *
 * Without JavaScript they are all visible, which is the worst that can
 * happen: the form still works and every row says which type it is for.
 */
( function () {
	'use strict';

	var type = document.getElementById( 'diluxone-users-type' );

	if ( ! type ) {
		return;
	}

	var rows = document.querySelectorAll( '.diluxone-users-if-type' );

	function review() {
		var chosen = type.value;

		rows.forEach( function ( row ) {
			var forTypes = ( row.getAttribute( 'data-type' ) || '' ).split( ' ' );

			row.hidden = -1 === forTypes.indexOf( chosen );
		} );
	}

	type.addEventListener( 'change', review );
	review();
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
 * It changes the same custom properties the front end changes, on the real
 * markup with the real classes — there is no copy of the design in here to
 * drift from the site. Turning the stylesheet off strips the class that loads
 * it, which is what the theme would see.
 */
( function () {
	'use strict';

	var box = document.querySelector( '[data-diluxone-users-preview-box]' );

	if ( ! box ) {
		return;
	}

	var skin   = box.querySelector( '[data-diluxone-users-preview-skin]' );
	var bare   = box.querySelector( '[data-diluxone-users-preview-bare]' );
	var accent = document.getElementById( 'diluxone_users_style_accent' );
	var radius = document.getElementById( 'diluxone_users_style_radius' );
	var styles = document.querySelector( 'input[name="diluxone_users_styles"]' );

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
	}

	[ accent, radius, styles ].forEach( function ( field ) {
		if ( field ) {
			field.addEventListener( 'input', paint );
			field.addEventListener( 'change', paint );
		}
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

	paint();
}() );
