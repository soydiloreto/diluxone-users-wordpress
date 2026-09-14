<?php
/**
 * The tabs of a screen, registered instead of written down.
 *
 * A screen used to carry its tabs in an array in its own file, which meant
 * the file had to know about every feature that might appear on it. Passkeys
 * lived inside the sign-in screen; the social buttons lived inside the social
 * screen; and taking one out meant editing a file that had nothing to do with
 * it.
 *
 * Now a feature registers its own panel from its own file. The screen asks
 * what there is and draws that. Two things follow, and the second is the
 * reason for the first:
 *
 *   - A feature that is not installed leaves no gap. Its file is not there,
 *     so it never registered, so the tab does not exist — rather than a tab
 *     that exists and apologises.
 *   - Anything can add one from outside: an add-on, a site's own plugin, a
 *     theme. The registry is the seam, and it is the same call the plugin
 *     uses for its own.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The registry itself.
 *
 * A static array and not an option: panels are code, and code that is not
 * loaded has no panel. Kept private — everything else goes through the two
 * functions below.
 *
 * @param string                    $screen Screen slug, or '' to read everything.
 * @param string                    $id     Panel id when writing.
 * @param array<string, mixed>|null $panel  The panel when writing, null when reading.
 * @return array<string, array<string, mixed>>
 */
function diluxone_users_panel_registry( string $screen = '', string $id = '', ?array $panel = null ): array {
	static $panels = array();

	if ( null !== $panel ) {
		$panels[ $screen ][ $id ] = $panel;

		return array();
	}

	return $panels[ $screen ] ?? array();
}

/**
 * Adds one tab to one screen.
 *
 * Call it on `admin_menu` at an early priority, which is what
 * `diluxone_users_panels_ready()` is for. Not at load time: the plugin loads
 * its files with a glob, so load time means alphabetical order, and a file
 * that sorts before this one would be calling a function that does not exist
 * yet. It did, and it took the whole site down with it.
 *
 * @param string                                                                                                                                        $screen Which screen, e.g. 'diluxone-users-login'.
 * @param string                                                                                                                                        $id     The tab's slug, which ends up in the URL.
 * @param array{label: string, render: callable, save?: callable, preview?: callable, preview_src?: string, note?: string, position?: int, form?: bool} $panel
 */
function diluxone_users_register_panel( string $screen, string $id, array $panel ): void {
	diluxone_users_panel_registry(
		$screen,
		$id,
		wp_parse_args(
			$panel,
			array(
				'label'       => $id,
				'render'      => '',
				// A panel with nothing to save — a summary, a preview — says
				// so, and the screen leaves out the form and the button.
				'save'        => '',
				// What goes in the column beside the fields. A panel without
				// one runs the full width.
				'preview'     => '',
				// A whole page of its own to show instead — wp-login.php is
				// one. It is a URL, so nothing is redrawn as the fields move:
				// the page is what it is until it is saved.
				'preview_src' => '',
				// A line under the preview, in the admin's voice. It lives out
				// here and not inside the preview because inside the preview
				// it would be part of what is being previewed.
				'note'        => '',
				'form'        => true,
				'position'    => 50,
			)
		)
	);
}

/**
 * Every panel on a screen, in order.
 *
 * @param string $screen
 * @return array<string, array<string, mixed>>
 */
function diluxone_users_panels( string $screen ): array {
	$panels = diluxone_users_panel_registry( $screen );

	uasort( $panels, static fn( array $a, array $b ): int => $a['position'] <=> $b['position'] );

	/**
	 * Filters the panels of one screen, once they are in order.
	 *
	 * Registering is the way in; this is for taking one out or moving it.
	 *
	 * @param array<string, array<string, mixed>> $panels
	 * @param string                              $screen
	 */
	return (array) apply_filters( 'diluxone_users_panels', $panels, $screen );
}

/**
 * Draws a whole screen out of its panels.
 *
 * Every screen built this way behaves the same: the tabs, the current one,
 * its form, its own saving, its notice. A screen with one panel shows no
 * tabs — one tab is not navigation, it is furniture.
 *
 * @param string $screen Screen slug.
 * @param string $title  What the heading says.
 */
function diluxone_users_screen_panels( string $screen, string $title ): void {
	$panels = diluxone_users_panels( $screen );

	if ( array() === $panels ) {
		return;
	}

	$labels  = wp_list_pluck( $panels, 'label' );
	$current = diluxone_users_tab( $labels );
	$panel   = $panels[ $current ];

	if (
		is_callable( $panel['save'] )
		&& isset( $_POST['diluxone_users_panel_nonce'] )
		&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_panel_nonce'] ) ), 'diluxone_users_panel_' . $screen )
	) {
		call_user_func( $panel['save'] );
		diluxone_users_notice( __( 'Saved.', 'diluxone-users' ) );

		// Read again: what is on screen has to be what was just written.
		$panels = diluxone_users_panels( $screen );
		$panel  = $panels[ $current ];
	}

	diluxone_users_screen_open( $title, $screen, count( $labels ) > 1 ? $labels : array(), $current );

	$form    = ! empty( $panel['form'] ) && is_callable( $panel['save'] );
	$preview = is_callable( $panel['preview'] ) || '' !== $panel['preview_src'];

	/*
	 * With a preview, the two columns wrap the form rather than the other way
	 * round: the form lives inside the left-hand column and the preview sits
	 * outside it. That is what lets a preview of the account area — which is
	 * a form itself — sit beside the settings instead of underneath them. A
	 * form inside a form is thrown away by the browser; two siblings are not.
	 */
	if ( $preview ) {
		echo '<div class="diluxone-users-studio"><div class="diluxone-users-studio__fields">';
	}

	if ( $form ) {
		echo '<form method="post">';
		wp_nonce_field( 'diluxone_users_panel_' . $screen, 'diluxone_users_panel_nonce' );
	}

	if ( is_callable( $panel['render'] ) ) {
		call_user_func( $panel['render'] );
	}

	if ( $form ) {
		submit_button();
		echo '</form>';
	}

	if ( $preview ) {
		printf(
			'</div><div class="diluxone-users-studio__preview" data-diluxone-users-live="%1$s" data-diluxone-users-live-screen="%2$s" data-diluxone-users-live-nonce="%3$s">',
			esc_attr( $current ),
			esc_attr( $screen ),
			esc_attr( wp_create_nonce( 'diluxone_users_preview' ) )
		);
		diluxone_users_preview_stage( $panel );
		echo '</div></div>';
	}

	/**
	 * Fires after a panel has been drawn, inside the screen's wrapper.
	 *
	 * @param string $screen
	 */
	do_action( 'diluxone_users_after_panel_' . $current, $screen );

	diluxone_users_screen_close();
}

/**
 * The moment to register a panel.
 *
 * Every panel — the plugin's own and an add-on's — is registered here, on
 * `admin_init`. By then every plugin has loaded, so nothing depends on which
 * file came first.
 *
 * `admin_init` and not `admin_menu`, which was the first attempt: admin_menu
 * does not run on an AJAX request, so the preview endpoint asked for the
 * panels, found none, and answered 404 to every keystroke. admin_init runs
 * for both, and still before the menu is built.
 */
function diluxone_users_panels_ready(): void {
	/**
	 * Fires when panels can be registered.
	 *
	 * @since 1.0.0
	 */
	do_action( 'diluxone_users_register_panels' );
}
add_action( 'admin_init', 'diluxone_users_panels_ready', 1 );

/**
 * A preview, as a page of its own.
 *
 * This is the whole reason the previews are in an iframe. The sign-in frames
 * break out to the full width of the window the way any cover does, with
 * `calc(50% - 50vw)` — and a preview drawn inside the admin column reads the
 * *admin's* window: half of 1900px of photo inside a 460px box, with the form
 * pushed off the side and clipped. Nothing was wrong with the markup; it was
 * being shown a window that was not its own.
 *
 * In here `50vw` is half of the preview, because the preview is the window.
 *
 * @param string $body The preview's markup.
 * @return string A complete document.
 */
function diluxone_users_preview_document( string $body ): string {
	$sheets = array( DILUXONE_USERS_URL . 'assets/diluxone-users.css' => 'assets/diluxone-users.css' );

	// The social buttons come with their own sheet on the front end too.
	$sheets[ DILUXONE_USERS_URL . 'assets/diluxone-users-social.css' ] = 'assets/diluxone-users-social.css';

	$links = '';

	foreach ( $sheets as $url => $file ) {
		$links .= sprintf(
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- a document of its own, with no queue to enqueue into.
			'<link rel="stylesheet" href="%s">',
			esc_url( add_query_arg( 'ver', diluxone_users_asset_version( $file ), $url ) )
		);
	}

	/*
	 * The padding is the page's margin and it is what the breakout undoes: a
	 * cover reaches the edge of this document exactly as it reaches the edge
	 * of the browser on the site.
	 *
	 * The rest is the same CSS the front end is given, built in the same
	 * place. Here it cannot be added to the sheet — the sheet is a file
	 * inside this document and a colour chosen a second ago would show as the
	 * colour saved a week ago — so it rides with the markup instead.
	 */
	$style = 'html{background:#fff}'
		. 'body{margin:0;padding:40px 24px;background:#fff;color:#1e1e1e;'
		. 'font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}'
		. diluxone_users_style_css();

	return '<!DOCTYPE html><html ' . get_language_attributes( 'html' ) . '><head><meta charset="'
		. esc_attr( get_bloginfo( 'charset' ) ) . '">'
		. $links
		. '<style>' . $style . '</style></head><body>'
		. $body
		. '</body></html>';
}

/**
 * The preview column: a window with the page inside it, to scale.
 *
 * Two sizes and not a slider, because there are two questions being asked of
 * a design — does it hold together wide, and does it survive a phone — and a
 * slider makes you find the answer instead of handing it over. The scaling
 * itself is the browser's, from the script: the column's width divided by the
 * width being pretended at.
 *
 * @param array<string, mixed> $panel
 */
function diluxone_users_preview_stage( array $panel ): void {
	$src = (string) $panel['preview_src'];

	if ( '' === $src ) {
		ob_start();
		call_user_func( $panel['preview'] );
		$document = diluxone_users_preview_document( (string) ob_get_clean() );
	}
	?>
	<div class="diluxone-users-stage" data-diluxone-users-stage>
		<div class="diluxone-users-stage__bar">
			<?php
			// With a context, because "Phone" is also the name of a field
			// type: one is a width and the other is a box somebody types a
			// number into, and a translator seeing the bare word gets it
			// wrong exactly half the time.
			?>
			<button type="button" class="diluxone-users-stage__device is-on" data-diluxone-users-device="1200">
				<?php echo esc_html_x( 'Desktop', 'the preview at desktop width', 'diluxone-users' ); ?>
			</button>
			<button type="button" class="diluxone-users-stage__device" data-diluxone-users-device="390">
				<?php echo esc_html_x( 'Phone', 'the preview at phone width', 'diluxone-users' ); ?>
			</button>

			<?php
			// Beside the column the page is a thumbnail, which answers "does
			// it hold together" and not "is that line too long". This is the
			// same page, big.
			?>
			<button type="button" class="diluxone-users-stage__zoom" data-diluxone-users-zoom>
				<?php esc_html_e( 'See it big', 'diluxone-users' ); ?>
			</button>
		</div>

		<div class="diluxone-users-stage__canvas" data-diluxone-users-stage-canvas>
			<?php
			/*
			 * Not clickable and not reachable by the keyboard: it is the real
			 * page, with the real forms in it, and it is here to be looked at.
			 */
			?>
			<iframe
				class="diluxone-users-stage__frame"
				data-diluxone-users-stage-frame
				title="<?php esc_attr_e( 'Preview', 'diluxone-users' ); ?>"
				tabindex="-1"
				scrolling="no"
				<?php if ( '' !== $src ) : ?>
					src="<?php echo esc_url( $src ); ?>"
				<?php else : ?>
					srcdoc="<?php echo esc_attr( $document ); ?>"
				<?php endif; ?>
				></iframe>
		</div>
	</div>

	<?php if ( '' !== (string) $panel['note'] ) : ?>
		<p class="description diluxone-users-stage__note"><?php echo esc_html( (string) $panel['note'] ); ?></p>
	<?php endif; ?>

	<?php
	// Empty on purpose: what is in it is whatever the preview is showing at
	// the moment it is opened, copied across by the script. Two iframes and
	// not one moved around, because moving an iframe in the DOM reloads it
	// and a preview that flickers every time it is opened is a worse preview.
	?>
	<dialog class="diluxone-users-zoom" data-diluxone-users-zoom-box>
		<div class="diluxone-users-zoom__bar">
			<span class="diluxone-users-zoom__what"><?php echo esc_html( (string) $panel['label'] ); ?></span>
			<button type="button" class="button" data-diluxone-users-zoom-close><?php esc_html_e( 'Close', 'diluxone-users' ); ?></button>
		</div>
		<div class="diluxone-users-zoom__canvas" data-diluxone-users-zoom-canvas>
			<iframe class="diluxone-users-zoom__frame" data-diluxone-users-zoom-frame title="<?php esc_attr_e( 'Preview', 'diluxone-users' ); ?>" tabindex="-1" scrolling="no"></iframe>
		</div>
	</dialog>
	<?php
}

/**
 * Redraws a preview with settings that have not been saved.
 *
 * The previews used to be updated in the browser: JavaScript moved a class
 * around and hid an element or two. That works for a colour and lies about
 * everything else — asking for the menu at the side did nothing at all,
 * because the markup for a menu at the side is not on the page when the saved
 * setting says tabs. There is nothing to move.
 *
 * So the server draws it again. The values from the form are pushed in front
 * of `diluxone_users_option`, the panel's own preview runs against them, and
 * what comes back is the real thing rendered with what is on screen — not a
 * guess at what it would look like. Nothing is written: the filter is added,
 * used and left behind when the request ends.
 */
function diluxone_users_preview_request(): void {
	check_ajax_referer( 'diluxone_users_preview', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( '', 403 );
	}

	$screen = isset( $_POST['screen'] ) ? sanitize_key( wp_unslash( $_POST['screen'] ) ) : '';
	$id     = isset( $_POST['panel'] ) ? sanitize_key( wp_unslash( $_POST['panel'] ) ) : '';
	$panels = diluxone_users_panels( $screen );

	if ( ! isset( $panels[ $id ] ) || ! is_callable( $panels[ $id ]['preview'] ) ) {
		wp_send_json_error( '', 404 );
	}

	// Only the plugin's own settings, and only as strings and integers: this
	// decides what a preview looks like, never what is stored.
	$values = array();

	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the nonce is checked above and every key and value is sanitised one at a time inside the loop.
	foreach ( (array) wp_unslash( $_POST['values'] ?? array() ) as $key => $value ) {
		$key = sanitize_key( (string) $key );

		if ( 0 !== strpos( $key, 'diluxone_users_' ) ) {
			continue;
		}

		$values[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( (string) $value );
	}

	$override = static function ( $value, string $key ) use ( $values ) {
		return array_key_exists( $key, $values ) ? $values[ $key ] : $value;
	};

	add_filter( 'diluxone_users_option', $override, 999, 2 );

	ob_start();
	call_user_func( $panels[ $id ]['preview'] );
	$html = (string) ob_get_clean();

	// The document is built while the override is still in place: the colour
	// and the corners are read from the form too.
	$document = diluxone_users_preview_document( $html );

	remove_filter( 'diluxone_users_option', $override, 999 );

	wp_send_json_success( $document );
}
add_action( 'wp_ajax_diluxone_users_preview', 'diluxone_users_preview_request' );
