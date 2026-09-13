<?php
/**
 * The "e-mail only" mode: closing the password door.
 *
 * How people get in is the site's choice, and there are three possible
 * answers: the e-mail link only, username and password only, or both. This
 * file deals with nothing but the first, which is the only one that needs to
 * close anything: designing a screen without a password is not enough,
 * because `/wp-login.php` is still open with its form and with the native
 * registration. With either of the other two, nothing happens here.
 *
 * One deliberate escape hatch remains:
 * `/wp-login.php?diluxone-users-admin=1` shows the native form. It is no
 * secret — the password still provides the security — but it avoids being
 * locked out of the site if the mail or the social provider fails.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The wp-login.php actions that HAVE to keep working.
 *
 * `logout` and `postpass` are ordinary flows; `rp` / `resetpass` are the end
 * of a reset that only an admin can start from the dashboard; and
 * `confirmaction` is the one confirming the GDPR personal-data requests,
 * which WordPress sends by e-mail and which has no other URL.
 */
const DILUXONE_USERS_LOGIN_ALLOWED = array( 'logout', 'postpass', 'rp', 'resetpass', 'confirmaction' );

/**
 * Does this request have to be taken out of wp-login.php?
 *
 * A pure function on purpose: it decides from the action and the query alone,
 * touching neither globals nor the database, so it can be tested without
 * booting WordPress.
 *
 * @param string               $action Value of `action` (empty string = login).
 * @param array<string, mixed> $query  Equivalent to $_GET.
 */
function diluxone_users_should_redirect( string $action, array $query = array() ): bool {
	// Escape hatch for administrators.
	if ( isset( $query['diluxone-users-admin'] ) ) {
		return false;
	}

	// The interstitial login is the modal appearing inside the dashboard when
	// the session expires. Taking it out of there would break the screen that
	// opened it.
	if ( isset( $query['interim-login'] ) ) {
		return false;
	}

	$action = '' === $action ? 'login' : $action;

	if ( in_array( $action, DILUXONE_USERS_LOGIN_ALLOWED, true ) ) {
		return false;
	}

	// login, register, lostpassword and any unknown action go to the site
	// sign-in page.
	return true;
}

/** Sends wp-login.php to the sign-in page. */
function diluxone_users_block_wp_login(): void {
	if ( ! diluxone_users_login_only_link() ) {
		return;
	}

	// With no other door, wp-login.php is the only one there is: closing it
	// would leave the site with no way in. It is compared against the resolved
	// URL and not against the setting, because a site can supply it through a
	// filter instead of an option.
	if ( diluxone_users_login_url() === wp_login_url() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- it is only read to decide the destination.
	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! diluxone_users_should_redirect( $action, $_GET ) ) {
		return;
	}

		// POST to the native form: do not redirect in silence, stop.
	if ( 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
		wp_die(
			esc_html__( 'This site signs you in without a password: with your email or with a social account.', 'diluxone-users' ),
			esc_html__( 'Sign in', 'diluxone-users' ),
			array( 'response' => 403 )
		);
	}

	wp_safe_redirect( diluxone_users_login_url() );
	exit;
}
add_action( 'login_init', 'diluxone_users_block_wp_login' );

/**
 * Turns off WordPress's own registration for as long as "e-mail only" lasts.
 *
 * Without this, with `users_can_register` on, `wp-signup.php` would go on
 * registering people with a password by e-mail.
 *
 * @param mixed $value What came from the option.
 * @return mixed
 */
function diluxone_users_block_registration( $value ) {
	$forced = (string) diluxone_users_option( 'diluxone_users_wp_registration' );

	if ( 'on' === $forced ) {
		return 1;
	}

	if ( 'off' === $forced ) {
		return 0;
	}

	// With nothing forced, "link only" mode turns it off all the same: leaving
	// it on would register people with a password through a door the site closed.
	return diluxone_users_login_only_link() ? 0 : $value;
}
add_filter( 'option_users_can_register', 'diluxone_users_block_registration' );

/* ── The dashboard profile ─────────────────────────────────────────── */

/**
 * What happens when somebody opens `wp-admin/profile.php`.
 *
 * A site that built its account area on the front end does not want half the
 * data edited on another screen, with another look and other rules — the edit
 * limits and required fields configured here do not apply there. People can
 * be sent to the account area, or have the door closed.
 *
 * Whoever administers is always left out of this: they are the person who has
 * to be able to fix what broke, and the dashboard profile is where it gets
 * fixed.
 */
function diluxone_users_wp_profile_guard(): void {
	$mode = (string) diluxone_users_option( 'diluxone_users_wp_profile' );

	if ( 'allow' === $mode || current_user_can( 'edit_users' ) ) {
		return;
	}

	$screen = basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ?? '' ) ) );

	if ( 'profile.php' !== $screen ) {
		return;
	}

	if ( 'redirect' === $mode ) {
		$target = diluxone_users_account_url( 'details' );

		wp_safe_redirect( '' !== $target ? $target : home_url( '/' ) );
		exit;
	}

	wp_die(
		esc_html__( 'Your details are edited from your account on the site, not from here.', 'diluxone-users' ),
		esc_html__( 'Not from here', 'diluxone-users' ),
		array(
			'response'  => 403,
			'back_link' => true,
		)
	);
}
add_action( 'admin_init', 'diluxone_users_wp_profile_guard' );
