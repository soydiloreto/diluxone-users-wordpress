<?php
/**
 * Overridable templates.
 *
 * The plugin ships its own markup so it lands anywhere and works, but a site
 * with a design of its own has to be able to replace it without touching the
 * plugin. The lookup order is:
 *
 *   1. The `diluxone_users_template` filter, which always wins.
 *   2. wp-content/themes/<child-theme>/diluxone-users/<file>
 *   3. wp-content/themes/<theme>/diluxone-users/<file>
 *   4. The plugin's template.
 *
 * It is the same mechanism bbPress and LifterLMS use, for two reasons: it is
 * the one people who install plugins already know, and it forces nobody to
 * copy files inside the plugin, where they are lost on the next update.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** Path of the template file to use. */
function diluxone_users_template( string $file ): string {
	// It is accepted with or without the extension: whoever asks for a template
	// thinks of "account/home", not of a file. And the comparison is with
	// is_file() and not file_exists(), which is also true for a directory — and
	// "account" is a directory of templates.
	$file = '.php' === substr( $file, -4 ) ? $file : $file . '.php';

	$candidates = array(
		get_stylesheet_directory() . '/diluxone-users/' . $file,
		get_template_directory() . '/diluxone-users/' . $file,
		DILUXONE_USERS_DIR . 'templates/' . $file,
	);

	$path = '';

	foreach ( $candidates as $candidate ) {
		if ( is_file( $candidate ) ) {
			$path = $candidate;
			break;
		}
	}

	/**
	 * Filters which file is used to draw something of the plugin.
	 *
	 * @param string $path Path found.
	 * @param string $file Name asked for, with its extension: "account/home.php".
	 */
	return (string) apply_filters( 'diluxone_users_template', $path, $file );
}

/**
 * Draws a template and returns what it printed.
 *
 * The variables arrive as loose variables, which is what whoever writes a
 * template and not a class expects.
 *
 * @param array<string, mixed> $data
 */
function diluxone_users_render( string $file, array $data = array() ): string {
	$path = diluxone_users_template( $file );

	if ( '' === $path ) {
		return '';
	}

	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- it is a template's contract.
	extract( $data, EXTR_SKIP );

	ob_start();
	include $path;

	return (string) ob_get_clean();
}

/**
 * The version a plugin file is requested with.
 *
 * The file date, not the plugin version: while work is going on the version
 * does not change and the browser keeps the old stylesheet. That cost an
 * afternoon arguing about a change that was made and could not be seen.
 *
 * @param string $file Relative path inside the plugin, e.g. "assets/diluxone-users.css".
 */
function diluxone_users_asset_version( string $file ): string {
	$path = DILUXONE_USERS_DIR . $file;
	$time = is_file( $path ) ? (int) filemtime( $path ) : 0;

	return $time > 0 ? DILUXONE_USERS_VERSION . '.' . $time : DILUXONE_USERS_VERSION;
}

/** The accent colour currently in use. */
function diluxone_users_style_accent(): string {
	$accent = trim( (string) diluxone_users_option( 'diluxone_users_style_accent' ) );

	return '' !== $accent ? $accent : '#2b59d6';
}

/**
 * Everything the admin decided, as custom properties.
 *
 * One function and not two. The front end and the live preview both need this
 * string and they had a copy each: the preview set `--accent-bg` and the front
 * end did not, which is the kind of difference only ever found by somebody
 * wondering why the preview and the site disagree.
 *
 * Properties and not rules, because the sheet is already written against
 * them. A button that is an outline is not a class that has to reach every
 * template with a button in it: it is three values, and the button rule was
 * already reading all three.
 */
function diluxone_users_style_tokens(): string {
	$tokens = '';

	if ( '' !== trim( (string) diluxone_users_option( 'diluxone_users_style_accent' ) ) ) {
		$accent  = (string) sanitize_hex_color( diluxone_users_style_accent() );
		$tokens .= '--diluxone-users-accent:' . $accent . ';';
		$tokens .= '--diluxone-users-accent-bg:' . $accent . ';';
	}

	$radius = (string) diluxone_users_option( 'diluxone_users_style_radius' );

	if ( '' !== trim( $radius ) ) {
		$tokens .= '--diluxone-users-radius:' . (int) $radius . 'px;';
		$tokens .= '--diluxone-users-radius-sm:' . max( 0, (int) $radius - 4 ) . 'px;';
	}

	$border = (string) diluxone_users_option( 'diluxone_users_style_border' );

	if ( '' !== trim( $border ) ) {
		// A half pixel is a real answer here: 1.5px is what a site with a
		// heavier hand actually uses, and rounding it to 2 is another design.
		$tokens .= '--diluxone-users-border-w:' . (float) $border . 'px;';
	}

	$control = (string) diluxone_users_option( 'diluxone_users_style_control' );

	if ( '' !== trim( $control ) ) {
		$tokens .= '--diluxone-users-control-h:' . (int) $control . 'px;';
	}

	$tokens .= diluxone_users_button_tokens();

	return $tokens;
}

/**
 * The whole of what the admin decided, as CSS.
 *
 * Almost all of it is properties on `:root`, which is the point of the token
 * system. The notice is the exception and it has to be: the soft shape wants
 * the background to be the notice's own colour washed down, and which colour
 * that is — green, red, the accent — is decided by a class on the notice
 * itself. A `:root` that says `--notice-bg: var(--notice-tint)` is asking
 * `:root` for a value that only exists further down, and a custom property
 * that cannot be resolved where it is declared resolves to nothing at all.
 * That is not a case for giving up on tokens; it is a case for declaring this
 * one where its ingredients are.
 */
function diluxone_users_style_css(): string {
	$tokens = diluxone_users_style_tokens();
	$css    = '' === $tokens ? '' : ':root{' . $tokens . '}';

	return $css . diluxone_users_notice_css() . diluxone_users_account_css();
}

/**
 * Where the account area's rows line up, if the site said.
 *
 * Printed only when there is an answer, and that is the whole point. There
 * was a rule in the stylesheet for a day that read these as properties with
 * neutral defaults — `padding-inline: var(--pad, 0px)` — and it took the
 * alignment away from a site that had been doing it itself. The plugin's
 * sheet loads after the site's, so `0` won; a value that means "nothing"
 * still beats a value that means something. The only declaration that cannot
 * overrule anybody is the one that is not printed.
 */
function diluxone_users_account_css(): string {
	$width = (string) diluxone_users_option( 'diluxone_users_account_row_w' );
	$pad   = (string) diluxone_users_option( 'diluxone_users_account_row_pad' );
	$body  = (string) diluxone_users_option( 'diluxone_users_account_body_pad' );
	$rows  = '';

	if ( '' !== trim( $width ) ) {
		$rows .= 'max-width:' . (int) $width . 'px;margin-inline:auto;';
	}

	if ( '' !== trim( $pad ) ) {
		$rows .= 'padding-inline:' . (int) $pad . 'px;';
	}

	$css = '' === $rows ? '' : '.diluxone-users-account__header-inner,'
		. '.diluxone-users-account__nav--row,'
		. '.diluxone-users-account__body{' . $rows . '}';

	if ( '' !== trim( $body ) ) {
		// As a property and not as a rule of its own: the sheet already gives
		// a cover its air at the end, and two rules for one measurement is
		// two rules to keep in step.
		$css .= ':root{--diluxone-users-body-end:' . (int) $body . 'px;}'
			. '.diluxone-users-account__body{padding-top:' . (int) $body . 'px;}';
	}

	return $css;
}

/** A notice with a bar down its left, or a soft filled box. */
function diluxone_users_notice_css(): string {
	if ( 'soft' !== (string) diluxone_users_option( 'diluxone_users_notice_style' ) ) {
		return '';
	}

	return '.diluxone-users-notice{'
		. '--diluxone-users-notice-edge:0;'
		. '--diluxone-users-notice-pad:14px 18px;'
		. '--diluxone-users-notice-radius:var(--diluxone-users-radius-sm);'
		. '--diluxone-users-notice-bg:var(--diluxone-users-notice-tint);'
		. '}';
}

/**
 * The three looks a button comes in.
 *
 * The filled one is the sheet's own, so it says nothing: a site that never
 * touched this gets exactly what it had.
 */
function diluxone_users_button_tokens(): string {
	$style = (string) diluxone_users_option( 'diluxone_users_button_style' );

	if ( 'outline' === $style ) {
		return '--diluxone-users-btn-bg:transparent;'
			. '--diluxone-users-btn-ink:var(--diluxone-users-accent);'
			. '--diluxone-users-btn-edge:var(--diluxone-users-accent);';
	}

	if ( 'soft' === $style ) {
		return '--diluxone-users-btn-bg:var(--diluxone-users-accent-soft);'
			. '--diluxone-users-btn-ink:var(--diluxone-users-accent);'
			. '--diluxone-users-btn-edge:transparent;';
	}

	return '';
}

/**
 * The plugin stylesheet, only if the site wants it.
 *
 * It is registered and not enqueued: each shortcode enqueues it as it draws,
 * so a page showing nothing of the plugin loads none of its CSS.
 *
 * A site with a design of its own has two roads, and the first usually
 * suffices: redefine the `--diluxone-users-*` properties so the components
 * take their colours from them, or turn the whole sheet off and style the
 * `diluxone-users-*` classes on its own.
 */
function diluxone_users_styles(): void {
	if ( ! diluxone_users_option( 'diluxone_users_styles' ) ) {
		return;
	}

	wp_register_style( 'diluxone-users', DILUXONE_USERS_URL . 'assets/diluxone-users.css', array(), diluxone_users_asset_version( 'assets/diluxone-users.css' ) );

	// The values chosen from the admin travel as properties, not as rules:
	// there is no file to generate or to invalidate, and what is touched is
	// exactly what the rest of the sheet was already reading.
	$css = diluxone_users_style_css();

	if ( '' !== $css ) {
		wp_add_inline_style( 'diluxone-users', $css );
	}

	// And if the page being requested already carries one of our shortcodes, it
	// is enqueued here. Enqueueing it only when the shortcode draws arrives too
	// late in block themes, which assemble the template at another moment: in
	// Twenty Twenty-Five the sheet did not come out and everything looked raw.
	if ( diluxone_users_page_has_shortcode() ) {
		wp_enqueue_style( 'diluxone-users' );
	}
}
add_action( 'wp_enqueue_scripts', 'diluxone_users_styles', 5 );

/** Does what is about to be drawn carry any of the plugin's shortcodes? */
function diluxone_users_page_has_shortcode(): bool {
	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	foreach ( array( 'diluxone_users_account', 'diluxone_users_account_nav', 'diluxone_users_login', 'diluxone_users_register', 'diluxone_users_fields', 'diluxone_users_accounts', 'diluxone_users_sessions', 'diluxone_users_handle', 'diluxone_users_avatar', 'diluxone_users_notifications' ) as $shortcode ) {
		if ( has_shortcode( $post->post_content, $shortcode ) ) {
			return true;
		}
	}

	/**
	 * Filters whether this page needs the plugin stylesheet.
	 *
	 * A site drawing the shortcodes from a template — and not from the content
	 * — turns it on through here.
	 *
	 * @param bool    $needs
	 * @param WP_Post $post
	 */
	return (bool) apply_filters( 'diluxone_users_needs_styles', false, $post );
}

/** Enqueues the sheet, if it is registered. The shortcodes call it as they draw. */
function diluxone_users_enqueue_styles(): void {
	if ( wp_style_is( 'diluxone-users', 'registered' ) ) {
		wp_enqueue_style( 'diluxone-users' );
	}
}

/**
 * A box that opens and closes.
 *
 * It is a `<details>` and not a div with JavaScript: the browser already
 * knows how to open it, close it, focus it with the keyboard, search inside
 * it with Ctrl+F even when closed, and open it when printing. All of that
 * would have to be rewritten — badly — to arrive at the same place.
 *
 * @param string $title   The box heading.
 * @param bool   $open    Whether it starts open.
 * @param string $classes Extra classes.
 */
function diluxone_users_panel_open( string $title, bool $open = false, string $classes = '' ): void {
	printf(
		'<details class="diluxone-users-panel %1$s"%2$s>'
			. '<summary class="diluxone-users-panel__head">'
			. '<span class="diluxone-users-panel__title">%3$s</span>'
			// The arrow is an element and not a pseudo: that way it can be drawn
			// with two borders, the only thing that comes out crisp on any
			// screen, and turned as it opens.
			. '<span class="diluxone-users-panel__arrow" aria-hidden="true"></span>'
			. '</summary><div class="diluxone-users-panel__body">',
		esc_attr( $classes ),
		$open ? ' open' : '',
		esc_html( $title )
	);
}

/**
 * Closes the box opened with diluxone_users_panel_open().
 *
 * With `$save`, the box ends with a button of its own. Since every box on a
 * screen lives inside the same form, any of those buttons submits the whole
 * page: there is no need to remember which one to press or to go back up to
 * find it.
 */
function diluxone_users_panel_close( string $save = '' ): void {
	if ( '' !== $save ) {
		printf(
			'<p class="diluxone-users-panel__save"><button type="submit" class="diluxone-users-button">%s</button></p>',
			esc_html( $save )
		);
	}

	echo '</div></details>';
}
