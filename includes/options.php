<?php
/**
 * The plugin settings, with their default values in a single place.
 *
 * Everything that in other plugins is a constant or a number written into the
 * code lives here and is edited from the admin.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default values. The key is the option name, with its prefix.
 *
 * @return array<string, mixed>
 */
function diluxone_users_option_defaults(): array {
	return array(
		// ── Passwordless sign-in ──────────────────────────────────────
		// How people get into this site:
		// 'link'     the e-mail link only; the username-and-password form of
		// wp-login.php is closed.
		// 'password' username and password only, the usual WordPress one.
		// 'both'     both of them, one below the other.
		'diluxone_users_login_method'       => 'both',
		// Page holding the sign-in form (the [diluxone_users_login] shortcode,
		// or whichever one the site puts there). At 0, wp-login.php is used.
		'diluxone_users_login_page'         => 0,
		// Minutes the e-mail link is good for.
		'diluxone_users_login_expiry'       => 15,
		// Seconds between two requests for the same e-mail address.
		'diluxone_users_login_throttle'     => 60,
		// Create the account when the e-mail does not exist. Turned off, the
		// link only works for someone already registered.
		'diluxone_users_login_register'     => 1,
		// Role of the accounts created that way.
		'diluxone_users_login_role'         => 'subscriber',
		'diluxone_users_login_subject'      => '',
		'diluxone_users_login_body'         => '',

		// ── Session length ────────────────────────────────────────────
		'diluxone_users_session_long_days'  => 30,  // With "remember me".
		'diluxone_users_session_short_days' => 2,   // Without "remember me".

		// ── Social login ──────────────────────────────────────────────
		// If the e-mail the network returns already exists on the site, that
		// account belongs to the same person and they are linked. It is what
		// makes signing in with Google today and GitHub tomorrow one account.
		'diluxone_users_sso_link_by_email'  => 1,
		// Create a new account when the e-mail does not exist.
		'diluxone_users_sso_register'       => 1,
		// Require the provider to say the e-mail is verified.
		'diluxone_users_sso_verified_only'  => 0,
		// Roles that cannot sign in with a social account. It goes as a list
		// because there are several; the saving below treats it separately.
		'diluxone_users_sso_blocked_roles'  => array(),

		/* How the network buttons look. */
		'diluxone_users_sso_button_skin'    => 'brand',
		'diluxone_users_sso_button_shape'   => 'rounded',
		'diluxone_users_sso_button_show'    => 'icon-text',
		// Empty means the default text, which also translates itself.
		'diluxone_users_sso_button_text'    => '',
		'diluxone_users_sso_button_columns' => 2,

		// ── The account area ──────────────────────────────────────────
		// The page holding the [diluxone_users_account] shortcode. With that
		// declared in a single place, everyone who needs to send somebody to
		// "my account" — LifterLMS, bbPress, a certificate — points right.
		'diluxone_users_account_page'       => 0,
		// Where the navigation goes: on top, down the side, or nowhere at all
		// because the site places it with [diluxone_users_account_nav].
		'diluxone_users_account_layout'     => 'tabs',
		// The front page with avatar, name and member-since date.
		'diluxone_users_account_header'     => 1,
		// The configuration of each section: whether it is on, what it is
		// called, what order it goes in, and the site's own added sections. It
		// is a list because the saving treats it separately.
		'diluxone_users_account_sections'   => array(),

		// ── The public name ───────────────────────────────────────────
		// The e-mail is the identity and is not chosen; this is the short name
		// the person appears under and that goes in their profile URL.
		'diluxone_users_handle_enabled'     => 0,
		// Besides the e-mail, the sign-in link can be requested by typing the
		// public name. The link still goes to the account's e-mail address.
		'diluxone_users_handle_login'       => 0,
		'diluxone_users_handle_min'         => 3,
		'diluxone_users_handle_max'         => 30,
		// 'strict' = a-z 0-9 . _ - ; 'unicode' accepts accents and ñ.
		'diluxone_users_handle_charset'     => 'strict',
		// What to do with spaces: 'dash' turns them into hyphens — an address
		// cannot contain spaces — or 'reject' refuses them and says so.
		'diluxone_users_handle_spaces'      => 'dash',
		// Days to wait between one change and the next. 0 = no wait; a name
		// that changes every day identifies nobody.
		'diluxone_users_handle_cooldown'    => 30,
		'diluxone_users_handle_reserved'    => '',

		// ── The profile picture ───────────────────────────────────────
		// Let the person upload their own.
		'diluxone_users_avatar_upload'      => 1,
		// If they uploaded none, go and fetch it from Gravatar. Turned off, no
		// request is made to a third party with anybody's e-mail hash.
		'diluxone_users_avatar_gravatar'    => 1,
		// And if there is neither: the initials over the accent colour.
		'diluxone_users_avatar_initials'    => 1,
		'diluxone_users_avatar_max_kb'      => 2048,

		// ── Appearance ────────────────────────────────────────────────
		// The plugin stylesheet. Off, the site styles the diluxone-users-* classes.
		'diluxone_users_styles'             => 1,
		// The two values that change everything else, because the rest of the
		// sheet derives from them. Empty = the ones the sheet ships with.
		'diluxone_users_style_accent'       => '',
		'diluxone_users_style_radius'       => '',

		// ── Second factor ─────────────────────────────────────────────
		// 'off' is never asked; 'optional' only of whoever turned it on;
		// 'required' of everybody who can use it.
		'diluxone_users_2fa_mode'           => 'optional',
		// The methods this site offers. Empty is equivalent to off.
		'diluxone_users_2fa_methods'        => array( 'totp', 'email' ),
		// Roles it is asked of. Empty = everybody.
		'diluxone_users_2fa_scope'          => 'all',
		'diluxone_users_2fa_roles'          => array(),
		// What to do when somebody comes in by e-mail link:
		// 'auto'   ask only if the second step is NOT another e-mail. A code
		// to the same inbox that was just opened proves nothing
		// new; an authenticator app does.
		// 'always' always ask.
		// 'never'  never ask.
		'diluxone_users_2fa_link'           => 'auto',
		// Days a browser that already passed the challenge is remembered. 0 = never.
		'diluxone_users_2fa_remember_days'  => 30,

		// ── Passkeys ──────────────────────────────────────────────────
		// ── Privacy ─────────────────────────────────────────────────
		// What somebody can do with their own data without asking anyone.
		// Both come turned on: it is the right thing, and a site that would
		// rather handle those requests by hand turns them off.
		'diluxone_users_privacy_export'     => 1,
		'diluxone_users_privacy_delete'     => 1,

		// ── Sessions ────────────────────────────────────────────────
		// Whether each person sees where their sessions are open and can close them.
		'diluxone_users_sessions_show'      => 1,

		'diluxone_users_passkey_enabled'    => 0,
		// 'device' only the key of the device in use; 'any' also the ones from
		// outside — a USB key, or the phone scanning a QR code.
		'diluxone_users_passkey_where'      => 'any',
		// Require verifying who they are on top: fingerprint, face or PIN.
		'diluxone_users_passkey_verify'     => 1,

		// ── WordPress's own registration ──────────────────────────────
		// 'site' respects whatever Settings → General says; 'on' and 'off'
		// force it from here, which is where accounts are administered.
		'diluxone_users_wp_registration'    => 'site',

		// What happens when somebody opens the WordPress dashboard profile:
		// 'allow' nothing, 'redirect' sends them to the site account area,
		// 'block' tells them no. It never reaches whoever administers.
		'diluxone_users_wp_profile'         => 'allow',
		'diluxone_users_wp_profile_scope'   => 'all',
		'diluxone_users_wp_profile_roles'   => array(),
	);
}

/**
 * Whether a setting applies to everybody or only to some roles.
 *
 * The answer used to be inferred from an empty list of ticked roles, which is
 * the kind of thing only the person who wrote it knows: a screen full of
 * unticked boxes reads as "nobody", and it meant "everybody".
 *
 * Sites configured before the choice existed have no answer stored, so it is
 * derived from what they ticked: roles ticked means they meant some.
 *
 * @param string $prefix Option prefix, e.g. 'diluxone_users_2fa'.
 * @return string 'all' or 'some'
 */
function diluxone_users_scope( string $prefix ): string {
	$stored = get_option( $prefix . '_scope', '' );

	if ( 'all' === $stored || 'some' === $stored ) {
		return $stored;
	}

	return array() === (array) diluxone_users_option( $prefix . '_roles' ) ? 'all' : 'some';
}

/**
 * Does this setting reach this person?
 *
 * @param int    $user_id Who is being checked.
 * @param string $prefix  Option prefix, e.g. 'diluxone_users_2fa'.
 */
function diluxone_users_scope_includes( int $user_id, string $prefix ): bool {
	if ( 'all' === diluxone_users_scope( $prefix ) ) {
		return true;
	}

	$roles = (array) diluxone_users_option( $prefix . '_roles' );

	if ( array() === $roles ) {
		// "Some" with nothing chosen reaches nobody. That is the literal
		// reading, and the screen says as much rather than quietly meaning
		// everybody like the old empty list did.
		return false;
	}

	$user = get_userdata( $user_id );

	return $user instanceof WP_User && array() !== array_intersect( $roles, (array) $user->roles );
}

/**
 * One setting, with its default value.
 *
 * @param string $key      Option name, with prefix.
 * @param mixed  $fallback Value when there is neither option nor default.
 * @return mixed
 */
function diluxone_users_option( string $key, $fallback = null ) {
	$defaults = diluxone_users_option_defaults();
	$value    = get_option( $key, null );

	if ( null === $value ) {
		$value = $defaults[ $key ] ?? $fallback;
	}

	/**
	 * Filters one plugin setting.
	 *
	 * @param mixed  $value Resolved value.
	 * @param string $key   Option name.
	 */
	return apply_filters( 'diluxone_users_option', $value, $key );
}

/**
 * Is this setting being forced from code by the site?
 *
 * A site's own plugin can pin a value through the `diluxone_users_option`
 * filter — because there it is not an option but how things work. When that happens, the admin control saves and changes
 * nothing, which is exactly the kind of lie to avoid on a settings screen.
 * With this it can be shown next to the control.
 */
function diluxone_users_option_forced( string $key ): bool {
	$defaults = diluxone_users_option_defaults();
	$stored   = get_option( $key, null );
	$stored   = null === $stored ? ( $defaults[ $key ] ?? null ) : $stored;

	return diluxone_users_option( $key ) !== $stored;
}

/**
 * Who is pinning a setting from code.
 *
 * "Something on the site decided this" helps nobody: whoever reads that wants
 * to go and remove it, and does not know where. Here the file and the
 * function come out, which is what it takes to find it. Everything hooked to
 * the filter is listed because any of them could be the one that wins; which
 * one it is, opening the file will say.
 *
 * @return array<int, string>
 */
function diluxone_users_option_forced_by(): array {
	global $wp_filter;

	if ( ! isset( $wp_filter['diluxone_users_option'] ) ) {
		return array();
	}

	$who = array();

	foreach ( $wp_filter['diluxone_users_option']->callbacks as $hooked ) {
		foreach ( $hooked as $hook ) {
			$fn = $hook['function'];

			if ( ! is_string( $fn ) || ! function_exists( $fn ) ) {
				continue;
			}

			try {
				$file = (string) ( new ReflectionFunction( $fn ) )->getFileName();
			} catch ( ReflectionException $e ) {
				continue;
			}

			$who[] = sprintf(
				'%s() — %s',
				$fn,
				ltrim( str_replace( wp_normalize_path( WP_PLUGIN_DIR ), '', wp_normalize_path( $file ) ), '/' )
			);
		}
	}

	return $who;
}

/** The link lifetime in minutes, clamped to something reasonable. */
function diluxone_users_login_expiry(): int {
	return max( 1, min( 1440, (int) diluxone_users_option( 'diluxone_users_login_expiry' ) ) );
}

/**
 * The URL of the site's sign-in screen.
 *
 * With no page configured it falls back to wp-login.php, which is where
 * WordPress expects to send somebody who is not signed in: a plugin cannot
 * leave a site with no door.
 */
function diluxone_users_login_url(): string {
	$id  = (int) diluxone_users_option( 'diluxone_users_login_page' );
	$url = $id > 0 ? (string) get_permalink( $id ) : '';

	if ( '' === $url ) {
		$url = wp_login_url();
	}

	/**
	 * Filters the URL of the sign-in screen.
	 *
	 * @param string $url
	 */
	return apply_filters( 'diluxone_users_login_url', $url );
}

/**
 * Saves the settings arriving from an admin screen.
 *
 * @param array<string, mixed> $input
 */
function diluxone_users_save_options( array $input ): void {
	$defaults = diluxone_users_option_defaults();

	foreach ( $input as $key => $value ) {
		if ( ! array_key_exists( $key, $defaults ) ) {
			continue;
		}

		$default = $defaults[ $key ];

		if ( is_int( $default ) ) {
			update_option( $key, (int) $value );
			continue;
		}

		// A list of keys — the blocked roles are the only one so far. It is
		// stored sanitised element by element and with no stray indexes.
		if ( is_array( $default ) ) {
			update_option( $key, array_values( array_unique( array_map( 'sanitize_key', (array) $value ) ) ) );
			continue;
		}

		update_option( $key, sanitize_textarea_field( (string) $value ) );
	}
}
