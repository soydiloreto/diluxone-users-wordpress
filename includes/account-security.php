<?php
/**
 * What the person can do with their own security.
 *
 * Turning the second factor on, registering their authenticator app, writing
 * down their backup codes and closing sessions. All from the front end, on
 * the page the site has chosen, without going through the WordPress dashboard
 * — which most people on a site like this never see.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** Does the site offer this person a second factor? */
function diluxone_users_2fa_offered( int $user_id ): bool {
	if ( 'off' === (string) diluxone_users_option( 'diluxone_users_2fa_mode' ) || array() === diluxone_users_2fa_methods() ) {
		return false;
	}

	$roles = (array) diluxone_users_option( 'diluxone_users_2fa_roles' );

	if ( array() === $roles ) {
		return true;
	}

	$user = get_userdata( $user_id );

	return $user instanceof WP_User && array() !== array_intersect( $roles, (array) $user->roles );
}

/** Do they have it turned on? With the required mode, always. */
function diluxone_users_2fa_on( int $user_id ): bool {
	if ( ! diluxone_users_2fa_offered( $user_id ) ) {
		return false;
	}

	return 'required' === (string) diluxone_users_option( 'diluxone_users_2fa_mode' )
		|| (bool) get_user_meta( $user_id, 'diluxone_users_2fa_on', true );
}

/** Can they turn it off, or does the site demand it? */
function diluxone_users_2fa_can_turn_off( int $user_id ): bool {
	return 'required' !== (string) diluxone_users_option( 'diluxone_users_2fa_mode' );
}

/** Saves what the person did on their security screen. */
function diluxone_users_security_submit(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( diluxone_users_login_url() );
		exit;
	}

	check_admin_referer( 'diluxone_users_security' );

	$user_id = get_current_user_id();
	$target  = diluxone_users_account_url( 'security' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$action = sanitize_key( wp_unslash( $_POST['diluxone_users_security'] ?? '' ) );

	switch ( $action ) {
		case 'on':
			if ( array() === diluxone_users_2fa_available( $user_id ) ) {
				wp_safe_redirect( add_query_arg( 'diluxone-users', 'nomethod', $target ) );
				exit;
			}

			update_user_meta( $user_id, 'diluxone_users_2fa_on', 1 );
			diluxone_users_notify_security( $user_id, __( 'Two-step verification was turned on.', 'diluxone-users' ) );

				// The backup codes are generated when it is turned on, not later:
				// the moment to write them down is before needing them.
			if ( 0 === diluxone_users_backup_left( $user_id ) ) {
				set_transient( 'diluxone_users_backup_' . $user_id, diluxone_users_backup_generate( $user_id ), 15 * MINUTE_IN_SECONDS );
			}

			wp_safe_redirect( add_query_arg( 'diluxone-users', 'on', $target ) );
			exit;

		case 'off':
			if ( ! diluxone_users_2fa_can_turn_off( $user_id ) ) {
				wp_safe_redirect( add_query_arg( 'diluxone-users', 'required', $target ) );
				exit;
			}

			delete_user_meta( $user_id, 'diluxone_users_2fa_on' );
			diluxone_users_notify_security( $user_id, __( 'Two-step verification was turned off.', 'diluxone-users' ) );
			wp_safe_redirect( add_query_arg( 'diluxone-users', 'off', $target ) );
			exit;

		case 'totp':
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
			$code = sanitize_text_field( wp_unslash( $_POST['diluxone_users_code'] ?? '' ) );

			if ( ! diluxone_users_totp_activate( $user_id, $code ) ) {
				wp_safe_redirect( add_query_arg( 'diluxone-users', 'badcode', $target ) );
				exit;
			}

			update_user_meta( $user_id, 'diluxone_users_2fa_on', 1 );
			diluxone_users_notify_security( $user_id, __( 'An authenticator app was set up.', 'diluxone-users' ) );

			if ( 0 === diluxone_users_backup_left( $user_id ) ) {
				set_transient( 'diluxone_users_backup_' . $user_id, diluxone_users_backup_generate( $user_id ), 15 * MINUTE_IN_SECONDS );
			}

			wp_safe_redirect( add_query_arg( 'diluxone-users', 'totp', $target ) );
			exit;

		case 'totp_off':
			diluxone_users_totp_forget( $user_id );
			diluxone_users_notify_security( $user_id, __( 'The authenticator app was removed.', 'diluxone-users' ) );
			wp_safe_redirect( add_query_arg( 'diluxone-users', 'totpoff', $target ) );
			exit;

		case 'backup':
			set_transient( 'diluxone_users_backup_' . $user_id, diluxone_users_backup_generate( $user_id ), 15 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'diluxone-users', 'backup', $target ) );
			exit;
	}

	wp_safe_redirect( $target );
	exit;
}
add_action( 'admin_post_diluxone_users_security', 'diluxone_users_security_submit' );

/**
 * The freshly generated codes, if they can still be shown.
 *
 * They live in a fifteen-minute transient and not in the user meta: they are
 * stored hashed, so this is the only window for seeing them, and that window
 * has to close on its own.
 *
 * @return array<int, string>
 */
function diluxone_users_backup_fresh( int $user_id ): array {
	$codes = get_transient( 'diluxone_users_backup_' . $user_id );

	// Without a transient, get_transient() returns false, and (array) false is
	// an array with one empty element inside: the list came out with a blank
	// row. It is compared before casting.
	if ( ! is_array( $codes ) || array() === $codes ) {
		return array();
	}

	// And they are deleted on reading. The box says "shown only this once", and
	// it was saying it while they went on appearing on every reload for fifteen
	// minutes: either it is true or it should not be said.
	delete_transient( 'diluxone_users_backup_' . $user_id );

	return $codes;
}
