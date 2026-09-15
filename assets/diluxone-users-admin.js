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
				diluxoneUsersChoiceGroups( form );
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
 * A template is a starting point: pressing one fills in the pieces below it.
 *
 * The screen has said so since it was written and it was not true — the
 * pieces stayed where they were and the area changed shape anyway, because
 * the shape was being decided twice: once by the control you pressed and once
 * by a stylesheet reading the template's name. Nothing showed the second one.
 *
 * Now pressing a template moves the controls, in front of you, and they are
 * what decides. Every one of them is still yours to change afterwards, which
 * is what "a starting point" is supposed to mean.
 */
( function () {
	'use strict';

	var templates = document.querySelectorAll( '[data-diluxone-users-template]' );

	if ( ! templates.length ) {
		return;
	}

	templates.forEach( function ( radio ) {
		radio.addEventListener( 'change', function () {
			if ( ! radio.checked ) {
				return;
			}

			document.querySelectorAll( '.diluxone-users-templates__one' ).forEach( function ( one ) {
				one.classList.toggle( 'is-chosen', one.contains( radio ) );
			} );

			var pieces;

			try {
				pieces = JSON.parse( radio.dataset.diluxoneUsersTemplatePieces || '{}' );
			} catch ( error ) {
				return;
			}

			Object.keys( pieces ).forEach( function ( piece ) {
				var fields = document.querySelectorAll( '[data-diluxone-users-piece="' + piece + '"]' );
				var wanted = String( pieces[ piece ] );

				fields.forEach( function ( field ) {
					if ( 'checkbox' === field.type ) {
						field.checked = '1' === wanted;
					} else if ( 'radio' === field.type ) {
						field.checked = field.value === wanted;
					} else {
						field.value = wanted;
					}
				} );
			} );

			// The preview listens on the form, and a value set from here does
			// not announce itself.
			radio.form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	} );
}() );

/**
 * What hangs off an option shows while that option is the one chosen.
 *
 * With the script off every group is open and the server reads whatever was
 * submitted, which is the same answer: a setting that belongs to an option
 * nobody chose changes nothing either way.
 */
function diluxoneUsersChoiceGroups( root ) {
	( root || document ).querySelectorAll( '[data-diluxone-users-group]' ).forEach( function ( group ) {
		var input = group.querySelector( 'input[type="radio"], input[type="checkbox"]' );

		if ( input ) {
			group.classList.toggle( 'is-open', input.checked );
		}
	} );
}

( function () {
	'use strict';

	/*
	 * On the document and not on the groups found at load: the field editor
	 * fetches its form and drops it into a dialog afterwards, and a listener
	 * bound to what was on the page at load never hears from it. The dialog
	 * calls the function above when it lands; this keeps the rest in step.
	 */
	document.addEventListener( 'change', function ( event ) {
		if ( event.target.matches( 'input[type="radio"], input[type="checkbox"]' ) ) {
			diluxoneUsersChoiceGroups( event.target.form || document );
		}
	} );

	diluxoneUsersChoiceGroups( document );
}() );

/**
 * The way back to the default, and the box that turns a colour on.
 *
 * Both exist for the same reason: a field can be put into a state it cannot
 * be got back out of. A number that is empty means "whatever the plugin
 * does", and once a number is typed there is nothing to say the box used to
 * be blank. A colour picker cannot hold "no colour" at all — the moment it is
 * touched it has one.
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-diluxone-users-default]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			document.querySelectorAll( button.dataset.diluxoneUsersDefault ).forEach( function ( field ) {
				field.value = '';
			} );

			// The preview listens on the form and a value cleared from here
			// does not announce itself.
			if ( button.form ) {
				button.form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
	} );

	document.querySelectorAll( '[data-diluxone-users-toggle]' ).forEach( function ( box ) {
		var target = document.querySelector( box.dataset.diluxoneUsersToggle );

		if ( ! target ) {
			return;
		}

		box.addEventListener( 'change', function () {
			target.disabled = ! box.checked;
		} );
	} );
}() );

/**
 * The preview window, and what size of window it is pretending to be.
 *
 * The preview is a document in an iframe, so `50vw` inside it means half of
 * the preview rather than half of the admin — which is the whole reason the
 * sign-in frames used to come out with the photo over everything and the form
 * off the side. Here the page is laid out at its real width and then shrunk
 * to fit the column, which is a picture of the page and not a squeezed one.
 *
 * Two widths and not a slider: the two questions asked of a design are
 * whether it holds together wide and whether it survives a phone.
 */
( function () {
	'use strict';

	var stage = document.querySelector( '[data-diluxone-users-stage]' );

	if ( ! stage ) {
		return;
	}

	var canvas = stage.querySelector( '[data-diluxone-users-stage-canvas]' );
	var frame  = stage.querySelector( '[data-diluxone-users-stage-frame]' );

	if ( ! canvas || ! frame ) {
		return;
	}

	// The second number is a starting height, not a limit: what is taller
	// than it — an account area with every section on — grows past it.
	var sizes = { 1200: 820, 390: 780 };
	var width = 1200;

	/**
	 * How tall the page inside actually is.
	 *
	 * Measured in two passes and never in a loop: the frame is put at the
	 * window's height first, because a cover that asks for 80vh has to be
	 * given a viewport before it can answer, and only then is the content
	 * measured. Growing afterwards cannot shrink anything back.
	 */
	function measure() {
		var base = sizes[ width ];

		frame.style.height = base + 'px';

		try {
			var doc = frame.contentDocument;

			if ( doc && doc.body ) {
				return Math.max( base, Math.min( 3000, doc.body.scrollHeight ) );
			}
		} catch ( error ) {
			// Another origin, which should not happen: the starting height
			// is still a reasonable window.
		}

		return base;
	}

	function fit() {
		var room = canvas.clientWidth;

		if ( ! room ) {
			return;
		}

		// Never blown up past its own size: a phone at 390px stays 390px wide
		// and sits in the middle, rather than being stretched into a lie.
		var scale = Math.min( 1, room / width );
		var tall  = measure();

		frame.style.width     = width + 'px';
		frame.style.height    = tall + 'px';
		frame.style.transform = 'scale(' + scale + ')';
		frame.style.marginLeft = Math.max( 0, Math.round( ( room - ( width * scale ) ) / 2 ) ) + 'px';

		canvas.style.height = Math.round( tall * scale ) + 'px';
	}

	stage.querySelectorAll( '[data-diluxone-users-device]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			width = parseInt( button.dataset.diluxoneUsersDevice, 10 ) || 1200;

			stage.querySelectorAll( '[data-diluxone-users-device]' ).forEach( function ( other ) {
				other.classList.toggle( 'is-on', other === button );
			} );

			stage.classList.toggle( 'is-phone', 1200 !== width );
			fit();
		} );
	} );

	frame.addEventListener( 'load', fit );
	window.addEventListener( 'resize', fit );
	fit();

	/*
	 * The same page, out of the column. What is shown is copied across rather
	 * than the iframe being moved: moving one reloads it, and a preview that
	 * blinks every time it is opened is a worse preview.
	 */
	( function () {
		var open   = stage.querySelector( '[data-diluxone-users-zoom]' );
		var box    = document.querySelector( '[data-diluxone-users-zoom-box]' );

		if ( ! open || ! box || ! box.showModal ) {
			if ( open ) {
				open.hidden = true;
			}

			return;
		}

		var canvas = box.querySelector( '[data-diluxone-users-zoom-canvas]' );
		var big    = box.querySelector( '[data-diluxone-users-zoom-frame]' );
		var close  = box.querySelector( '[data-diluxone-users-zoom-close]' );

		function fill() {
			var room = canvas.clientWidth;
			// Never past its own size: a phone stays a phone.
			var scale = Math.min( 1, room / width );
			var tall  = sizes[ width ];

			big.style.width  = width + 'px';
			big.style.height = tall + 'px';
			big.style.transform = 'scale(' + scale + ')';
			big.style.marginLeft = Math.max( 0, Math.round( ( room - ( width * scale ) ) / 2 ) ) + 'px';

			try {
				var doc = big.contentDocument;

				if ( doc && doc.body ) {
					tall = Math.max( tall, Math.min( 4000, doc.body.scrollHeight ) );
					big.style.height = tall + 'px';
				}
			} catch ( error ) {
				// The starting height is still a window.
			}

			canvas.style.height = Math.round( tall * scale ) + 'px';
		}

		open.addEventListener( 'click', function () {
			if ( frame.getAttribute( 'src' ) ) {
				big.src = frame.getAttribute( 'src' );
			} else {
				big.srcdoc = frame.srcdoc;
			}

			box.showModal();
			fill();
		} );

		big.addEventListener( 'load', fill );
		close.addEventListener( 'click', function () {
			box.close();
		} );
	}() );

	/*
	 * How the live preview hands over a new drawing. It is a whole document,
	 * so it is written as one; `srcdoc` and not `document.write` because the
	 * second one leaves the old document's styles behind.
	 */
	window.diluxoneUsersStage = {
		show: function ( html ) {
			frame.srcdoc = html;
		},
	};
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

				if ( window.diluxoneUsersStage ) {
					window.diluxoneUsersStage.show( answer.data );
				}
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

					// A value set from here does not announce itself, and the
					// preview listens on the form: without this, choosing a
					// cover changed the thumbnail and nothing else until the
					// page was saved.
					field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
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
				field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		}
	} );
}() );
