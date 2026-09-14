<?php
/**
 * Everything about keeping an account from being taken over.
 *
 * Its tabs come from the features that are installed: the second factor
 * registers its own, passkeys registers its own, sessions registers its own.
 * The screen itself knows about none of them, which is what lets any one of
 * the three live in its own files and leave with them.
 *
 * If nothing registers anything, the screen does not appear in the menu at
 * all — an empty screen is worse than no screen.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

const DILUXONE_USERS_SECURITY = 'diluxone-users-security';

/** The security screen. */
function diluxone_users_screen_security(): void {
	diluxone_users_screen_panels( DILUXONE_USERS_SECURITY, diluxone_users_screens()[ DILUXONE_USERS_SECURITY ] );
}
