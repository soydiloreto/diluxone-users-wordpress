<?php
/**
 * The screen for looking, next to the screens for deciding.
 *
 * A report is not a setting, and under a Save button it reads as one. The
 * list of everybody with a session open used to sit below the two boxes that
 * say how long a session lasts, on a tab called Sessions, and the two were
 * read as one thing: somebody who came to change a number left with a table
 * of names in front of them, and somebody who came for the table had to
 * scroll past a form they had no business pressing. It was asked for more
 * than once and it stayed there, because there was nowhere else to put it.
 *
 * Now there is. What the site is doing right now is here; what the site is
 * told to do is on the settings screens, and nothing on those two kinds of
 * screen is on the other one.
 *
 * Its tabs come from the same registry every other screen uses, so a feature
 * — the plugin's own or an add-on's — puts its report here from its own file,
 * and this one never learns that the feature exists.
 *
 * There is one settings tab on this screen and it is the exception that says
 * what the rule is for. The activity log decides whether the tab beside it has
 * a single row in it, and a switch two screens away from the empty table it
 * explains is a switch nobody finds: the first thing anybody does after opening
 * a log that shows nothing is look for where it is turned on. So it is here,
 * last in the strip, saying in its own name that it is settings — and it is the
 * only one. A second one would not be an exception any more, it would be this
 * screen going back to being both kinds of screen at once.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

const DILUXONE_USERS_REPORTS = 'diluxone-users-reports';

/** The reports screen. */
function diluxone_users_screen_reports(): void {
	diluxone_users_screen_panels( DILUXONE_USERS_REPORTS, (string) diluxone_users_screens()[ DILUXONE_USERS_REPORTS ] );
}
