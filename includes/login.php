<?php
/**
 * Single-use link sign-in ("magic link").
 *
 * Signing in and registering can be the same step: the person types their
 * e-mail, receives a link and gets in with it. If the account did not exist,
 * it is created right there.
 *
 * How it works:
 *   1. A random token is generated.
 *   2. Only its hash is stored in the database, never the token: if somebody
 *      walks off with the database, they cannot get in with what is there.
 *   3. On opening the link it is validated, the session starts and the token
 *      is deleted — it works exactly once.
 *
 * Decisions that matter:
 *   - The answer is identical whether the account exists or not. Saying "we
 *     could not find that e-mail" turns the form into a detector of who is
 *     registered.
 *   - Constant-time comparison, so the token does not leak through how long
 *     it takes to fail.
 *   - A per-e-mail request limit, so it cannot be used as a battering ram or
 *     as a machine for mailing third parties.
 *
 * This lives alongside WordPress's ordinary registration: it is one more
 * door, not a replacement. The "e-mail only" setting is what closes the others.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The shape of the sign-in page.
 *
 * Four answers and only one of them leaves the page alone. A site with a
 * designed page wants 'plain' — the form where the theme put it. A site that
 * has not designed one wants the page taken over, which is what the other
 * three do, and is the reason a second plugin usually gets installed for it.
 */
function diluxone_users_login_template(): string {
	$template = (string) diluxone_users_option( 'diluxone_users_login_template' );

	return in_array( $template, array( 'card', 'split', 'backdrop' ), true ) ? $template : 'plain';
}

/**
 * A picture chosen in the admin, at a size worth serving.
 *
 * Full width because it is a background: an 'image' thumbnail stretched over
 * half a screen is the kind of detail that makes a good design look cheap.
 */
function diluxone_users_login_image( string $key ): string {
	$id = (int) diluxone_users_option( $key );

	if ( $id <= 0 ) {
		return '';
	}

	return (string) wp_get_attachment_image_url( $id, 'full' );
}

/**
 * The frame around the sign-in form, opened.
 *
 * It lives here and not in the template because both steps of signing in go
 * inside it — the form, and the screen asking for the second-step code — and
 * a frame written twice is a frame that will be changed once. A theme that
 * replaces login.php still gets the frame around whatever it draws, which is
 * what a theme replacing the form actually wants.
 *
 * Prints nothing at all for the template that leaves the page alone.
 */
function diluxone_users_login_frame_open(): void {
	$template = diluxone_users_login_template();

	if ( 'plain' === $template ) {
		return;
	}

	$image = diluxone_users_login_image( 'diluxone_users_login_image' );
	$side  = 'right' === (string) diluxone_users_option( 'diluxone_users_login_side' ) ? 'right' : 'left';

	// The picture travels as a custom property because it is a background in
	// every layout that has one, and a property can be repointed from a
	// stylesheet without touching any of this.
	$style = '' !== $image && in_array( $template, array( 'split', 'backdrop' ), true )
		? sprintf( ' style="--diluxone-users-login-image: url(%s)"', esc_url( $image ) )
		: '';

	/*
	 * The wrapper is not decoration: it is what makes the frame a grandchild.
	 * A block theme constrains its content with
	 * `:where(.is-layout-constrained) > :where(…)` — direct children only, and
	 * with !important on both the margin and the max-width, so nothing a child
	 * declares can break out of the column. The wrapper takes that treatment
	 * and the frame inside it is free, which is the same thing the account
	 * cover does one level down.
	 */
	echo '<div class="diluxone-users-login-page">';

	printf(
		'<div class="diluxone-users-login-frame diluxone-users-login-frame--%1$s diluxone-users-login-frame--%2$s"%3$s>',
		esc_attr( $template ),
		esc_attr( $side ),
		$style // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped just above.
	);

	if ( 'split' === $template ) {
		echo '<div class="diluxone-users-login-frame__picture" aria-hidden="true"></div>';
	}

	echo '<div class="diluxone-users-login-frame__box">';
}

/** And closed. */
function diluxone_users_login_frame_close(): void {
	if ( 'plain' === diluxone_users_login_template() ) {
		return;
	}

	echo '</div></div></div>';
}

/**
 * A small drawing, for the two places on this screen that read better with one.
 *
 * Inline and not a font or a sprite: two shapes do not justify a request, and
 * inline is the only kind that follows the surrounding colour. They carry
 * their size as attributes so they stay the right size with the plugin's
 * stylesheet turned off, and they are hidden from screen readers — each one
 * sits next to a sentence that already says the same thing.
 *
 * @param string $name mail | info
 */
function diluxone_users_icon( string $name ): string {
	$paths = array(
		'mail' => '<rect x="2.5" y="4.5" width="19" height="15" rx="2"></rect><path d="m3 6 9 6.5L21 6"></path>',
		'info' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 16v-5M12 8h.01"></path>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	$size = 'mail' === $name ? 40 : 20;

	return sprintf(
		'<svg class="diluxone-users-icon diluxone-users-icon--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		$size,
		$paths[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup from the table above.
	);
}

/** The site's mark above the form, when there is one. */
function diluxone_users_login_logo(): void {
	$logo = diluxone_users_login_image( 'diluxone_users_login_logo' );

	if ( '' === $logo ) {
		return;
	}

	printf(
		'<p class="diluxone-users-login__logo"><img src="%1$s" alt="%2$s"></p>',
		esc_url( $logo ),
		esc_attr( get_bloginfo( 'name' ) )
	);
}

/**
 * What a screen says, in the site's words or in the plugin's.
 *
 * Every visible sentence the plugin writes has a default that works out of
 * the box and an option that is empty until somebody fills it in. Empty is
 * not "say nothing": it is "say what the plugin says", which is the same rule
 * every other setting here follows — a site that changes nothing gets a
 * screen that reads correctly, and a site that has its own voice does not
 * have to copy a template to use it.
 *
 * @param string $key      Option name.
 * @param string $fallback  What the plugin says when nothing was written.
 */
function diluxone_users_text( string $key, string $fallback = '' ): string {
	$own = trim( (string) diluxone_users_option( $key ) );

	return '' !== $own ? $own : $fallback;
}

const DILUXONE_USERS_META_HASH    = '_diluxone_users_acceso_hash';
const DILUXONE_USERS_META_EXPIRES = '_diluxone_users_acceso_vence';

/**
 * Generates a token, stores its hash and returns the token in the clear.
 *
 * What is persisted is the hash: the token in the clear exists only in the e-mail.
 */
function diluxone_users_token_create( int $user_id ): string {
	$token = wp_generate_password( 40, false, false );

	update_user_meta( $user_id, DILUXONE_USERS_META_HASH, wp_hash( $token ) );
	update_user_meta( $user_id, DILUXONE_USERS_META_EXPIRES, time() + ( diluxone_users_login_expiry() * MINUTE_IN_SECONDS ) );

	return $token;
}

/**
 * Is this token valid for this user?
 *
 * It is compared with hash_equals so that the response time does not depend
 * on how many characters matched.
 */
function diluxone_users_token_valid( int $user_id, string $token ): bool {
	$hash    = (string) get_user_meta( $user_id, DILUXONE_USERS_META_HASH, true );
	$expires = (int) get_user_meta( $user_id, DILUXONE_USERS_META_EXPIRES, true );

	if ( '' === $hash || $expires <= 0 || time() > $expires ) {
		return false;
	}

	return hash_equals( $hash, wp_hash( $token ) );
}

/** Burns the token: a link works exactly once. */
function diluxone_users_token_burn( int $user_id ): void {
	delete_user_meta( $user_id, DILUXONE_USERS_META_HASH );
	delete_user_meta( $user_id, DILUXONE_USERS_META_EXPIRES );
}

/** URL of the sign-in link. */
function diluxone_users_login_link( int $user_id, string $token ): string {
	return add_query_arg(
		array(
			'diluxone_users_login' => $user_id,
			'diluxone_users_token' => $token,
		),
		home_url( '/' )
	);
}

/**
 * Finds the account for the e-mail, or creates it if the site allows it.
 *
 * This is where "registration" and "sign-in" stop being two things: the
 * account is created in the same step, with no separate form and no password
 * to choose.
 *
 * @return int User ID, or 0 when it does not exist and cannot be created.
 */
function diluxone_users_user_for( string $email ): int {
	$user = get_user_by( 'email', $email );

	if ( $user ) {
		// On a network, existing is not being a member of this site: somebody
		// coming from the site next door gets in, but with no role here they
		// can do nothing.
		diluxone_users_join_site( (int) $user->ID );

		return (int) $user->ID;
	}

	if ( ! diluxone_users_option( 'diluxone_users_login_register' ) ) {
		return 0;
	}

	// The display name and the one in the profile URL are NOT allowed to be
	// derived from user_login, because user_login is the e-mail: WordPress
	// would build a display_name of "somebody@gmail.com" that later shows up in
	// the forums, and a user_nicename of "somebodygmail-com" that reads plainly
	// in the profile URL. The account is identified by the e-mail; publishing
	// it is another matter.
	$visible = diluxone_users_name_from_email( $email );

	// The password is generated at random and nobody knows it, not even the
	// person registering: WordPress needs the field, the site does not use it.
	$id = wp_insert_user(
		array(
			'user_login'    => $email,
			'user_email'    => $email,
			'user_pass'     => wp_generate_password( 64, true, true ),
			'display_name'  => $visible,
			// wp_insert_user() adds a suffix if it is already taken.
			'user_nicename' => sanitize_title( $visible ),
			'role'          => (string) diluxone_users_option( 'diluxone_users_login_role' ),
		)
	);

	if ( is_wp_error( $id ) ) {
		return 0;
	}

	diluxone_users_join_site( (int) $id );

	return (int) $id;
}

/** How people get in: 'link', 'password' or 'both'. */
function diluxone_users_login_method(): string {
	$method = (string) diluxone_users_option( 'diluxone_users_login_method' );

	return in_array( $method, array( 'link', 'password', 'both' ), true ) ? $method : 'both';
}

/** Is the e-mail link offered? */
function diluxone_users_login_has_link(): bool {
	return 'password' !== diluxone_users_login_method();
}

/** Is the username-and-password form offered? */
function diluxone_users_login_has_password(): bool {
	return 'link' !== diluxone_users_login_method();
}

/** Is the e-mail link the only door? */
function diluxone_users_login_only_link(): bool {
	return 'link' === diluxone_users_login_method();
}

/**
 * A presentable name out of an e-mail address.
 *
 * It is what is shown until the person writes their own. It keeps what comes
 * before the at sign — the domain tells nobody anything — and strips the
 * separators so that "juan.perez88" reads as "juan perez88".
 */
function diluxone_users_name_from_email( string $email ): string {
	$local = (string) strstr( $email, '@', true );
	$local = '' === $local ? $email : $local;
	$local = trim( (string) preg_replace( '/[._\-]+/', ' ', $local ) );

	return '' === $local ? __( 'Someone', 'diluxone-users' ) : $local;
}

/** The e-mail subject, with the site's own as a fallback. */
function diluxone_users_login_subject(): string {
	$subject = trim( (string) diluxone_users_option( 'diluxone_users_login_subject' ) );

	if ( '' === $subject ) {
		/* translators: %s: site name */
		$subject = sprintf( __( 'Your sign-in link for %s', 'diluxone-users' ), get_bloginfo( 'name' ) );
	}

	return $subject;
}

/**
 * The e-mail body. `{link}` and `{minutes}` are replaced.
 */
function diluxone_users_login_body( string $url ): string {
	$body = trim( (string) diluxone_users_option( 'diluxone_users_login_body' ) );

	if ( '' === $body ) {
		$body = __(
			"Click here to sign in:\n\n{link}\n\nThe link expires in {minutes} minutes and works once.\n\nIf you did not ask for it, ignore this message: nobody can get into your account without it.",
			'diluxone-users'
		);
	}

	return strtr(
		$body,
		array(
			'{link}'    => $url,
			'{minutes}' => (string) diluxone_users_login_expiry(),
		)
	);
}

/** Sends the e-mail with the link. */
function diluxone_users_login_send( int $user_id, string $email, string $token ): bool {
	$url = diluxone_users_login_link( $user_id, $token );

	// In development there is usually no mail server. Leaving the link in the
	// log is what makes the flow testable end to end.
	if ( 'production' !== wp_get_environment_type() ) {
		error_log( '[diluxone-users] sign-in link for ' . $email . ': ' . $url ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Filters the e-mail before sending it.
	 *
	 * @param array{subject: string, body: string} $message
	 * @param string                               $email
	 * @param string                               $url
	 */
	$message = apply_filters(
		'diluxone_users_login_email',
		array(
			'subject' => diluxone_users_login_subject(),
			'body'    => diluxone_users_login_body( $url ),
		),
		$email,
		$url
	);

	return wp_mail( $email, $message['subject'], $message['body'] );
}

/**
 * Handles the "send me the link" form.
 *
 * It always answers the same thing, whatever happened.
 */
function diluxone_users_login_request(): void {
	$redirect = diluxone_users_login_url();

	if ( ! isset( $_POST['diluxone_users_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_nonce'] ) ), 'diluxone_users_login' ) ) {
		wp_safe_redirect( add_query_arg( 'diluxone-users', 'error', $redirect ) );
		exit;
	}

	// What was typed can be an e-mail or, if the site allows it, somebody's
	// public name. In the second case it goes on with that account's e-mail:
	// the link never goes out to an address typed on the spot.
	$typed = sanitize_text_field( wp_unslash( $_POST['diluxone_users_email'] ?? '' ) );
	$email = sanitize_email( diluxone_users_handle_login_email( $typed ) );

	if ( '' === $email || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'diluxone-users', 'email', $redirect ) );
		exit;
	}

	// Everything that follows ends on the same screen, account or no account.
	$done     = add_query_arg(
		array(
			'diluxone-users' => 'sent',
			'email'          => rawurlencode( $email ),
		),
		$redirect
	);
	$throttle = 'diluxone_users_throttle_' . md5( $email );

	if ( get_transient( $throttle ) ) {
		wp_safe_redirect( $done );
		exit;
	}

	set_transient( $throttle, 1, max( 1, (int) diluxone_users_option( 'diluxone_users_login_throttle' ) ) );

	$user_id = diluxone_users_user_for( $email );

	if ( $user_id > 0 ) {
		diluxone_users_login_send( $user_id, $email, diluxone_users_token_create( $user_id ) );
	}

	wp_safe_redirect( $done );
	exit;
}
add_action( 'admin_post_nopriv_diluxone_users_acceso', 'diluxone_users_login_request' );
add_action( 'admin_post_diluxone_users_acceso', 'diluxone_users_login_request' );

/**
 * Consumes the link: validates, signs in and burns the token.
 */
function diluxone_users_login_consume(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token IS the credential.
	if ( ! isset( $_GET['diluxone_users_login'], $_GET['diluxone_users_token'] ) ) {
		return;
	}

	$user_id = absint( $_GET['diluxone_users_login'] );
	$token   = sanitize_text_field( wp_unslash( $_GET['diluxone_users_token'] ) );
	// phpcs:enable

	if ( $user_id <= 0 || '' === $token || ! diluxone_users_token_valid( $user_id, $token ) ) {
		wp_safe_redirect( add_query_arg( 'diluxone-users', 'expired', diluxone_users_login_url() ) );
		exit;
	}

	diluxone_users_token_burn( $user_id );

	/**
	 * Where the person goes after coming in through the link.
	 *
	 * This is the point where a site sends them to fill in their profile the
	 * first time.
	 *
	 * @param string $redirect
	 * @param int    $user_id
	 */
	$redirect = (string) apply_filters( 'diluxone_users_login_redirect', home_url( '/' ), $user_id );

	// The session is not opened here: diluxone_users_complete_login() opens it,
	// and also decides whether a second factor has to be asked for first. Every
	// door ends in the same function on purpose.
	diluxone_users_complete_login( $user_id, 'link', true, $redirect );
}
add_action( 'init', 'diluxone_users_login_consume' );
