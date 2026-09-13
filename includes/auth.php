<?php
/**
 * The heart of authentication: which doors exist and what each one asks for.
 *
 * A site does not choose "one" way in: it chooses a set. It can have a
 * password and an e-mail link at the same time, it can add social networks on
 * top, and it can ask for a second factor on all of them, on some, or on
 * none. Those combinations cannot be resolved with a chain of `if`s: what is
 * needed is one place where they are enumerated and a single path everybody
 * goes through.
 *
 * That path is `diluxone_users_complete_login()`. However they get in —
 * password, link, social network — everyone ends up there, and there it is
 * decided whether the session opens or whether something else has to be
 * proved first. Without it, adding a second factor would mean remembering to
 * add it at every door, and the one that gets forgotten stays open.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** How long a half-finished second-factor attempt lives. */
const DILUXONE_USERS_2FA_WINDOW = 10 * MINUTE_IN_SECONDS;

/* ── Los segundos factores disponibles ─────────────────────────────── */

/*
 * * A passkey is not on this list, and that is not an oversight: it is not a
 * * second factor but a way in that already carries both inside — something
 * * you have, the device, and something you are or know, the fingerprint or
 * * the PIN. Putting it here would mean asking three things of someone who
 * * already gave two.
 */

/**
 * The second-factor methods the plugin knows how to handle.
 *
 * It is a registry and not a fixed list: an add-on adds its own without
 * touching this, the same as the account-area sections do.
 *
 * Each one declares:
 *   label     What it is called for people.
 *   help      What it is, in one line.
 *   ready     Function that says whether THAT person already has it set up.
 *   send      Optional: what to do when the challenge starts (send the e-mail).
 *   verify    Function that validates what the person typed.
 *   position  Preference order when there is more than one.
 *
 * @return array<string, array<string, mixed>>
 */
function diluxone_users_2fa_methods(): array {
	$methods = array(
		'email' => array(
			// Which channel the second step arrives through. It is what makes it
			// possible to decide whether it adds anything when someone already
			// came in through that same channel.
			'channel'  => 'email',
			'label'    => __( 'A code by email', 'diluxone-users' ),
			'help'     => __( 'We send a six-digit code to the address on the account. Nothing to install.', 'diluxone-users' ),
			'ready'    => static fn( int $user_id ): bool => true,
			'send'     => 'diluxone_users_2fa_email_send',
			'verify'   => 'diluxone_users_2fa_email_verify',
			'position' => 20,
		),
		'totp'  => array(
			'channel'  => 'device',
			'label'    => __( 'An authenticator app', 'diluxone-users' ),
			'help'     => __( 'The six-digit code that changes every thirty seconds, from Google Authenticator, 1Password, Aegis or whichever one you use.', 'diluxone-users' ),
			'ready'    => 'diluxone_users_totp_ready',
			'verify'   => 'diluxone_users_totp_verify',
			'position' => 10,
		),
	);

	/**
	 * Filters the second-factor methods.
	 *
	 * @param array<string, array<string, mixed>> $methods
	 */
	$methods = (array) apply_filters( 'diluxone_users_2fa_methods', $methods );

	// The ones the site turned off do not exist for anybody.
	$enabled = (array) diluxone_users_option( 'diluxone_users_2fa_methods' );

	$methods = array_filter(
		$methods,
		static fn( string $id ): bool => in_array( $id, $enabled, true ),
		ARRAY_FILTER_USE_KEY
	);

	uasort( $methods, static fn( array $a, array $b ): int => $a['position'] <=> $b['position'] );

	return $methods;
}

/**
 * The methods THIS person has ready to use right now.
 *
 * @return array<string, array<string, mixed>>
 */
function diluxone_users_2fa_available( int $user_id ): array {
	return array_filter(
		diluxone_users_2fa_methods(),
		static fn( array $m ): bool => is_callable( $m['ready'] ) && call_user_func( $m['ready'], $user_id )
	);
}

/* ── The policy: who gets asked ────────────────────────────────────── */

/**
 * Does this person have to go through a second factor?
 *
 * Three things decide, in this order:
 *
 *   1. The site mode. Off is never asked; optional is asked only of whoever
 *      turned it on; required is asked of everybody.
 *   2. The chosen roles, when the list is not empty. Useful for asking it of
 *      whoever administers the site and not of the 25,000 who only watch a
 *      course.
 *   3. How they got in. A single-use link sent to their e-mail already proves
 *      whoever is coming in has that e-mail; asking them on top for a code
 *      sent to the same e-mail is asking the same thing twice. That is why it
 *      is a separate setting and comes turned off: a site that wants the
 *      second factor anyway turns it on.
 *
 * @param string $via 'password', 'link' or 'sso'.
 */
function diluxone_users_2fa_required( int $user_id, string $via ): bool {
	$mode = (string) diluxone_users_option( 'diluxone_users_2fa_mode' );

	if ( 'off' === $mode ) {
		return false;
	}

	if ( array() === diluxone_users_2fa_available( $user_id ) ) {
		return false;
	}

	if ( 'link' === $via && ! diluxone_users_2fa_worth_it_on_link( $user_id ) ) {
		return false;
	}

	if ( ! diluxone_users_scope_includes( $user_id, 'diluxone_users_2fa' ) ) {
		return false;
	}

	if ( 'required' === $mode ) {
		return true;
	}

	// Optional: only for whoever turned it on.
	return (bool) get_user_meta( $user_id, 'diluxone_users_2fa_on', true );
}

/**
 * Is it worth asking the second step of someone who came in by e-mail link?
 *
 * On automatic, yes only when there is a method that does NOT arrive by
 * e-mail. A code sent to the same inbox the person just opened to follow the
 * link proves nothing the link has not proved already; an authenticator app
 * or a key does.
 *
 * The site can force both answers, but automatic is the one that avoids both
 * the open door and the errand that serves no purpose.
 */
function diluxone_users_2fa_worth_it_on_link( int $user_id ): bool {
	$mode = (string) diluxone_users_option( 'diluxone_users_2fa_link' );

	if ( 'always' === $mode ) {
		return true;
	}

	if ( 'never' === $mode ) {
		return false;
	}

	foreach ( diluxone_users_2fa_available( $user_id ) as $method ) {
		if ( 'email' !== ( $method['channel'] ?? 'email' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The ways in the site offers, and whether this person is asked for the
 * second step on each of them.
 *
 * It exists so the security screen tells the truth. "You are not being asked"
 * is false as soon as one social network is on: the e-mail-link exception
 * belongs to the e-mail link alone, because there the second code would go to
 * the same inbox the person just opened.
 *
 * Passkeys are not on the list because they do not come through here: a
 * passkey is already two factors in one step, and that is accounted for in
 * its own box.
 *
 * @return array<string, array{label: string, asked: bool}>
 */
function diluxone_users_2fa_ways( int $user_id ): array {
	$ways = array();

	if ( diluxone_users_login_has_link() ) {
		$ways['link'] = __( 'the link we email you', 'diluxone-users' );
	}

	if ( diluxone_users_login_has_password() ) {
		$ways['password'] = __( 'your password', 'diluxone-users' );
	}

	if ( function_exists( 'diluxone_users_sso_available' ) && array() !== diluxone_users_sso_available() ) {
		$ways['sso'] = __( 'a social account', 'diluxone-users' );
	}

	$out = array();

	foreach ( $ways as $via => $label ) {
		$out[ $via ] = array(
			'label' => $label,
			'asked' => diluxone_users_2fa_required( $user_id, $via ),
		);
	}

	return $out;
}

/**
 * The ways in where the second step is actually asked for.
 *
 * @return array<int, string>
 */
function diluxone_users_2fa_ways_asked( int $user_id ): array {
	return array_values(
		wp_list_pluck(
			array_filter( diluxone_users_2fa_ways( $user_id ), static fn( array $w ): bool => $w['asked'] ),
			'label'
		)
	);
}

/**
 * Has this browser passed the second factor recently?
 *
 * The cookie grants no access: it only avoids repeating the challenge in the
 * same browser for as many days as the setting says. It is signed with the
 * site salts, so it cannot be forged, and it carries the id of whoever asked
 * for it.
 */
function diluxone_users_2fa_trusted( int $user_id ): bool {
	$days = (int) diluxone_users_option( 'diluxone_users_2fa_remember_days' );

	if ( $days <= 0 ) {
		return false;
	}

	$cookie = isset( $_COOKIE[ 'diluxone_users_2fa_' . COOKIEHASH ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ 'diluxone_users_2fa_' . COOKIEHASH ] ) ) : '';

	if ( '' === $cookie ) {
		return false;
	}

	[ $stored_id, $expires, $hash ] = array_pad( explode( '|', $cookie, 3 ), 3, '' );

	if ( (int) $stored_id !== $user_id || (int) $expires < time() ) {
		return false;
	}

	return hash_equals( wp_hash( $user_id . '|' . $expires, 'secure_auth' ), $hash );
}

/** Marks this browser so it is not asked again for a few days. */
function diluxone_users_2fa_trust( int $user_id ): void {
	$days = (int) diluxone_users_option( 'diluxone_users_2fa_remember_days' );

	if ( $days <= 0 ) {
		return;
	}

	$expires = time() + $days * DAY_IN_SECONDS;
	$value   = $user_id . '|' . $expires . '|' . wp_hash( $user_id . '|' . $expires, 'secure_auth' );

	setcookie(
		'diluxone_users_2fa_' . COOKIEHASH,
		$value,
		$expires,
		defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
		defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
		is_ssl(),
		true
	);
}

/* ── The single way in ─────────────────────────────────────────────── */

/**
 * Closes somebody's sign-in: either opens the session, or asks for the second
 * factor.
 *
 * @param int    $user_id  Who is coming in.
 * @param string $via      Through which door: 'password', 'link' or 'sso'.
 * @param bool   $remember Long session.
 * @param string $redirect Where they go afterwards.
 */
function diluxone_users_complete_login( int $user_id, string $via, bool $remember = true, string $redirect = '' ): void {
	$redirect = '' !== $redirect ? $redirect : (string) apply_filters( 'diluxone_users_login_redirect', home_url( '/' ), $user_id );

	// A passkey goes straight in: it already proved both things.
	if ( 'passkey' === $via || ! diluxone_users_2fa_required( $user_id, $via ) || diluxone_users_2fa_trusted( $user_id ) ) {
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, $remember );

		/**
		 * Somebody came in, with everything that was needed already done.
		 *
		 * @param int    $user_id
		 * @param string $via
		 */
		do_action( 'diluxone_users_logged_in', $user_id, $via );

		wp_safe_redirect( $redirect );
		exit;
	}

	diluxone_users_2fa_challenge( $user_id, $via, $remember, $redirect );
}

/**
 * Stores the half-done sign-in and sends them to the second-factor screen.
 *
 * What is pending lives in a user meta and not in the session: the attempt
 * has to survive the person opening the screen in another tab, and it cannot
 * depend on a session cookie that does not exist yet.
 */
function diluxone_users_2fa_challenge( int $user_id, string $via, bool $remember, string $redirect ): void {
	// Just in case: if some door left the cookie set, it is taken away. A
	// session opened before the second factor is having no second factor.
	wp_clear_auth_cookie();

	$nonce = wp_generate_password( 32, false );

	update_user_meta(
		$user_id,
		'diluxone_users_2fa_pending',
		array(
			'nonce'    => wp_hash( $nonce ),
			'expires'  => time() + DILUXONE_USERS_2FA_WINDOW,
			'via'      => $via,
			'remember' => $remember ? 1 : 0,
			'redirect' => $redirect,
		)
	);

	$methods = diluxone_users_2fa_available( $user_id );

		// Coming in by e-mail link, it starts with a method that is not another
		// e-mail: sending a code to the inbox they just opened would be making
		// them repeat the same step.
	if ( 'link' === $via ) {
		foreach ( $methods as $id => $m ) {
			if ( 'email' !== ( $m['channel'] ?? 'email' ) ) {
				$methods = array( $id => $m ) + $methods;
				break;
			}
		}
	}

	$method = (string) array_key_first( $methods );

	diluxone_users_2fa_send( $user_id, $method );

	wp_safe_redirect(
		add_query_arg(
			array(
				'diluxone_users_2fa'    => $user_id,
				'diluxone_users_key'    => $nonce,
				'diluxone_users_method' => $method,
			),
			diluxone_users_login_url()
		)
	);
	exit;
}

/**
 * Somebody's pending attempt, if it is still alive and the nonce is theirs.
 *
 * @return array<string, mixed>
 */
function diluxone_users_2fa_pending( int $user_id, string $nonce ): array {
	$pending = (array) get_user_meta( $user_id, 'diluxone_users_2fa_pending', true );

	if ( array() === $pending || (int) ( $pending['expires'] ?? 0 ) < time() ) {
		return array();
	}

	return hash_equals( (string) ( $pending['nonce'] ?? '' ), wp_hash( $nonce ) ) ? $pending : array();
}

/** Fires whatever that method needs in order to start (send the e-mail). */
function diluxone_users_2fa_send( int $user_id, string $method ): void {
	$methods = diluxone_users_2fa_available( $user_id );

	if ( isset( $methods[ $method ]['send'] ) && is_callable( $methods[ $method ]['send'] ) ) {
		call_user_func( $methods[ $method ]['send'], $user_id );
	}
}

/**
 * Validates what the person typed and, if it is right, lets them in.
 *
 * Backup codes are always tried, whichever method was chosen: they are
 * precisely for when the method is not at hand.
 */
function diluxone_users_2fa_verify( int $user_id, string $method, string $code ): bool {
	if ( diluxone_users_backup_use( $user_id, $code ) ) {
		return true;
	}

	$methods = diluxone_users_2fa_available( $user_id );

	if ( ! isset( $methods[ $method ] ) || ! is_callable( $methods[ $method ]['verify'] ) ) {
		return false;
	}

	return (bool) call_user_func( $methods[ $method ]['verify'], $user_id, $code );
}

/**
 * The second-factor screen and its submission.
 *
 * It lives on `init` like the rest of the plugin's doors, so that the sign-in
 * page is a page of the site and not wp-login.php.
 */
function diluxone_users_2fa_handle(): void {
	// phpcs:disable WordPress.Security.NonceVerification -- our own nonce IS the credential.
	if ( ! isset( $_POST['diluxone_users_2fa_user'], $_POST['diluxone_users_2fa_key'] ) ) {
		return;
	}

	$user_id = absint( $_POST['diluxone_users_2fa_user'] );
	$key     = sanitize_text_field( wp_unslash( $_POST['diluxone_users_2fa_key'] ) );
	$method  = sanitize_key( wp_unslash( $_POST['diluxone_users_2fa_method'] ?? '' ) );
	$code    = sanitize_text_field( wp_unslash( $_POST['diluxone_users_2fa_code'] ?? '' ) );
	$trust   = isset( $_POST['diluxone_users_2fa_trust'] );
	$resend  = isset( $_POST['diluxone_users_2fa_resend'] );
	// phpcs:enable

	$pending = diluxone_users_2fa_pending( $user_id, $key );

	if ( array() === $pending ) {
		wp_safe_redirect( add_query_arg( 'diluxone-users', 'expired', diluxone_users_login_url() ) );
		exit;
	}

	$back = add_query_arg(
		array(
			'diluxone_users_2fa'    => $user_id,
			'diluxone_users_key'    => $key,
			'diluxone_users_method' => $method,
		),
		diluxone_users_login_url()
	);

	if ( $resend ) {
		diluxone_users_2fa_send( $user_id, $method );
		wp_safe_redirect( add_query_arg( 'diluxone-users', 'sent', $back ) );
		exit;
	}

	if ( '' === $code || ! diluxone_users_2fa_verify( $user_id, $method, $code ) ) {
		wp_safe_redirect( add_query_arg( 'diluxone-users', 'code', $back ) );
		exit;
	}

	delete_user_meta( $user_id, 'diluxone_users_2fa_pending' );

	if ( $trust ) {
		diluxone_users_2fa_trust( $user_id );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, ! empty( $pending['remember'] ) );

	do_action( 'diluxone_users_logged_in', $user_id, (string) $pending['via'] );

	wp_safe_redirect( (string) $pending['redirect'] );
	exit;
}
add_action( 'init', 'diluxone_users_2fa_handle', 5 );

/**
 * The password goes through here too.
 *
 * WordPress opens the session before firing `wp_login`, so what is done is to
 * close it again straight away and send them to the challenge. It is ugly and
 * it is what there is: there is no hook between "the password was right" and
 * "the cookie is set".
 */
function diluxone_users_2fa_after_password( string $login, WP_User $user ): void {
	if ( ! diluxone_users_2fa_required( (int) $user->ID, 'password' ) || diluxone_users_2fa_trusted( (int) $user->ID ) ) {
		return;
	}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress verified it while authenticating.
	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
	$redirect = '' !== $redirect ? $redirect : (string) apply_filters( 'diluxone_users_login_redirect', home_url( '/' ), (int) $user->ID );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$remember = ! empty( $_POST['rememberme'] );

	diluxone_users_2fa_challenge( (int) $user->ID, 'password', $remember, $redirect );
}
add_action( 'wp_login', 'diluxone_users_2fa_after_password', 10, 2 );

/* ── Backup codes ──────────────────────────────────────────────────── */

/**
 * Generates a fresh set of backup codes.
 *
 * They are stored hashed, like a password: if somebody walks off with the
 * database, they walk off with hashes. They are returned in the clear exactly
 * once, which is when the person has to write them down.
 *
 * @return array<int, string>
 */
function diluxone_users_backup_generate( int $user_id, int $many = 8 ): array {
	$plain  = array();
	$hashes = array();

	for ( $i = 0; $i < $many; $i++ ) {
		$code     = strtolower( wp_generate_password( 10, false, false ) );
		$plain[]  = $code;
		$hashes[] = wp_hash_password( $code );
	}

	update_user_meta( $user_id, 'diluxone_users_backup_codes', $hashes );

	return $plain;
}

/** How many backup codes they have left unused. */
function diluxone_users_backup_left( int $user_id ): int {
	return count( (array) get_user_meta( $user_id, 'diluxone_users_backup_codes', true ) );
}

/**
 * Uses a backup code, if what they typed is one.
 *
 * It is deleted on use: a single-use code that can be used twice is not a
 * single-use code.
 */
function diluxone_users_backup_use( int $user_id, string $code ): bool {
	$code   = strtolower( trim( str_replace( array( ' ', '-' ), '', $code ) ) );
	$hashes = (array) get_user_meta( $user_id, 'diluxone_users_backup_codes', true );

	foreach ( $hashes as $i => $hash ) {
		if ( wp_check_password( $code, (string) $hash, $user_id ) ) {
			unset( $hashes[ $i ] );
			update_user_meta( $user_id, 'diluxone_users_backup_codes', array_values( $hashes ) );

			return true;
		}
	}

	return false;
}
