<?php
/**
 * What has to be fixed when the plugin changes version.
 *
 * There is only one thing so far, and it is the prefix. The plugin was called
 * "User & Subscription Manager" with `usmw_`, then "Users Plus for WordPress"
 * with `upfw_`, then "Users Plus" with `users_plus_`, then "Users+" with
 * `users_dlx_plus_`, before settling on "DiluxOne Users+" with
 * `diluxone_users_`. Stored data carries the prefix of its era — the site
 * options and each person's user meta — and renaming the code without
 * renaming the data leaves a site that starts up empty: no fields, no
 * passkeys, no second factor and nobody's linked networks.
 *
 * So the data is renamed too, from any of the old prefixes to the current
 * one, and it is recorded as done. It runs on `admin_init` and not on
 * activation because an update over FTP or over git fires no activation.
 *
 * The whole file talks to the database directly and without caching, and it
 * has to: it is a migration that runs once, with nobody's input, and it drops
 * what is its own from the object cache when it finishes. The annotations go
 * up here and not spread over the functions because a `phpcs:enable` inside
 * an early `return` applies from that line onwards — PHPCS reads the file top
 * to bottom, it does not follow the flow — and leaves everything after it
 * uncovered.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The mark saying the move has already been made on this site.
 *
 * It is written once, at the very end, and it is deliberately kept out of the
 * rename below: the old mark is an option like any other, so a blind rename
 * turns `users_dlx_plus_migrated` into `diluxone_users_migrated` in the first
 * step — and from that moment on the site believes it has finished. A run that
 * dies in the middle then never resumes and nobody is told. That is not a
 * hypothesis: it happened, and it left a site with its options and its user
 * meta moved but the shortcodes inside its pages still written the old way.
 */
const DILUXONE_USERS_MIGRATED = 'diluxone_users_migrated';

/** The current prefix, written exactly once. */
const DILUXONE_USERS_PREFIX = 'diluxone_users_';

/**
 * The prefixes this plugin used before, oldest to newest.
 *
 * A site can come from any of them: from the original, or from one of the
 * intermediate rounds. Every case is the same work.
 *
 * @return array<int, string>
 */
function diluxone_users_old_prefixes(): array {
	return array( 'usmw_', 'upfw_', 'users_plus_', 'users_dlx_plus_' );
}

/**
 * Drops from the cache only what the migration touched.
 *
 * `wp_cache_flush()` would be one line and empties the cache of the WHOLE
 * site: the other plugins' transients included. One of them keeps its licence
 * validation in there, and on losing it asked for the key again. A plugin has
 * no business tearing down a whole site's cache to fix its own.
 *
 * @param array<int, string> $options  The old options, under their former names.
 * @param array<int, string> $user_ids The ids whose meta was renamed.
 * @param string             $old      The prefix being moved from.
 */
function diluxone_users_migration_forget_cache( array $options, array $user_ids, string $old ): void {
	// WordPress caches the full options list in a single lump.
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );

	foreach ( $options as $old ) {
		wp_cache_delete( (string) $old, 'options' );
		wp_cache_delete( DILUXONE_USERS_PREFIX . substr( (string) $old, strlen( $old ) ), 'options' );
	}

	foreach ( $user_ids as $user_id ) {
		wp_cache_delete( (int) $user_id, 'user_meta' );
	}
}

/**
 * Renames the data left under an old prefix.
 *
 * It is done in SQL and not through the options and meta APIs for a practical
 * reason: there is one row per person and per key, that is 25,000 people on
 * the site this came from, and reading and rewriting them one by one takes
 * minutes and breaks off halfway.
 */
function diluxone_users_migrate_prefix( string $old ): void {
	global $wpdb;

	$length   = strlen( $old );
	$from_pos = $length + 1;
	$like     = str_replace( '_', '\\_', $old ) . '%';

	// How much room the current prefix takes, so SQL can cut by it. It comes
	// from the constant and not from a hand-written number: the prefix has
	// changed four times and a hardcoded length survives the next one in silence.
	$from_new = strlen( DILUXONE_USERS_PREFIX ) + 1;

	// Options have a unique key. If the plugin already seeded its own — on
	// activation, which happens before this — the UPDATE collides with that row
	// and fails ENTIRELY: it migrates none, and the site starts up empty
	// without saying why. So the freshly seeded one is removed first: the one
	// that counts is the old one, which holds what the site configured.
	// The migration marks of every era stay out of this: see the constant above.
	$marks = array( 'usmw_migrated', 'upfw_migrated', 'users_plus_migrated', 'users_dlx_plus_migrated' );

	$old_names = array_values(
		array_diff(
			(array) $wpdb->get_col(
				$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
			),
			$marks
		)
	);

	// No early return when there are no old options left: the options may have
	// been moved by a run that then died before reaching the user meta, the
	// field keys or the shortcodes. Each block below finds nothing and costs
	// one query when there is nothing to do, which is the price of picking up
	// a half-finished migration instead of leaving it half-finished for good.
	//
	// $old_name and not $old: $old is the prefix this whole function works on,
	// and a foreach that borrows the name overwrites it for everything below.
	foreach ( (array) $old_names as $old_name ) {
		delete_option( DILUXONE_USERS_PREFIX . substr( (string) $old_name, $length ) );
	}

	// SUBSTRING from the prefix length and not REPLACE: REPLACE would also
	// change a `usmw_` appearing in the middle of the key, and what moves here
	// is the prefix, not every occurrence.
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->options}
			    SET option_name = CONCAT( %s, SUBSTRING( option_name, %d ) )
			  WHERE option_name LIKE %s
			    AND option_name NOT IN ( %s, %s, %s, %s )",
			DILUXONE_USERS_PREFIX,
			$from_pos,
			$like,
			$marks[0],
			$marks[1],
			$marks[2],
			$marks[3]
		)
	);

	$affected = $wpdb->get_col(
		$wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $like )
	);

	// User meta has no unique key, but the problem is the same: one person
	// could end up with both the old and the new one, and `get_user_meta()`
	// would return either.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE nueva FROM {$wpdb->usermeta} nueva
			   INNER JOIN {$wpdb->usermeta} vieja
			           ON vieja.user_id = nueva.user_id
			          AND vieja.meta_key = CONCAT( %s, SUBSTRING( nueva.meta_key, %d ) )
			        WHERE nueva.meta_key LIKE %s",
			$old,
			$from_new,
			$wpdb->esc_like( DILUXONE_USERS_PREFIX ) . '%'
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->usermeta}
			    SET meta_key = CONCAT( %s, SUBSTRING( meta_key, %d ) )
			  WHERE meta_key LIKE %s",
			DILUXONE_USERS_PREFIX,
			$from_pos,
			$like
		)
	);

	diluxone_users_migration_forget_cache( (array) $old_names, (array) $affected, $old );

	// The field keys also travel inside the definition, and they are the same
	// ones each person's value was stored under: if both are not moved, the
	// fields end up looking at a meta that no longer exists.
	$fields = get_option( 'diluxone_users_fields', false );

	if ( is_array( $fields ) ) {
		foreach ( $fields as $i => $field ) {
			if ( isset( $field['key'] ) && 0 === strpos( (string) $field['key'], $old ) ) {
				$fields[ $i ]['key'] = DILUXONE_USERS_PREFIX . substr( (string) $field['key'], $length );
			}
		}

		update_option( 'diluxone_users_fields', $fields );
	}

	// And the shortcodes written inside pages.
	diluxone_users_migrate_shortcodes( $old );
}

/**
 * The shortcodes left written in a page's content.
 *
 * Nobody draws `[usmw_account]` after the rename: the text comes out as it
 * is, and whoever looks at their account page sees the bracket.
 */
function diluxone_users_migrate_shortcodes( string $old ): void {
	global $wpdb;

	$ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s", '%[' . $wpdb->esc_like( $old ) . '%' )
	);

	if ( array() === (array) $ids ) {
		return;
	}

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->posts}
			    SET post_content = REPLACE( post_content, %s, %s )
			  WHERE post_content LIKE %s",
			'[' . $old,
			'[' . DILUXONE_USERS_PREFIX,
			'%[' . $wpdb->esc_like( $old ) . '%'
		)
	);

	// Without this WordPress keeps serving the old content from the object
	// cache, and the page shows the written shortcode instead of the account.
	foreach ( (array) $ids as $id ) {
		clean_post_cache( (int) $id );
	}
}

/**
 * Runs the move, once per plugin version.
 *
 * The mark holds the version that did the migrating, not a yes. Every step
 * here is idempotent — it looks for data under an old prefix and finds none
 * the second time — so re-checking costs one cheap query per prefix and buys
 * something worth much more: a site whose migration was cut short half way,
 * by a timeout or a fatal, is picked up and finished on the next release
 * instead of staying broken in silence for good.
 */
function diluxone_users_migrate(): void {
	if ( DILUXONE_USERS_VERSION === get_option( DILUXONE_USERS_MIGRATED ) ) {
		return;
	}

	foreach ( diluxone_users_old_prefixes() as $old ) {
		diluxone_users_migrate_prefix( $old );
	}

	update_option( DILUXONE_USERS_MIGRATED, DILUXONE_USERS_VERSION );
}
add_action( 'admin_init', 'diluxone_users_migrate', 0 );

/**
 * Registration stops being one exclusive answer and becomes a set of doors.
 *
 * What was stored as `register_mode` (login / form / closed) is read once and
 * written back as the two doors it meant; what was stored as
 * `wp_registration` (site / on / off) is written into WordPress's own
 * `users_can_register` when it forced anything, and dropped. Both old options
 * are deleted so nothing is left to read them. It runs once and marks itself.
 */
function diluxone_users_migrate_registration(): void {
	if ( get_option( 'diluxone_users_registration_v2' ) ) {
		return;
	}

	$mode = (string) get_option( 'diluxone_users_register_mode', '' );

	if ( 'form' === $mode ) {
		update_option( 'diluxone_users_register_form', 1 );
		update_option( 'diluxone_users_login_register', 0 );
	} elseif ( 'closed' === $mode ) {
		update_option( 'diluxone_users_register_form', 0 );
		update_option( 'diluxone_users_login_register', 0 );
	} elseif ( 'login' === $mode ) {
		update_option( 'diluxone_users_register_form', 0 );
		update_option( 'diluxone_users_login_register', 1 );
	}

	$forced = (string) get_option( 'diluxone_users_wp_registration', '' );

	if ( 'on' === $forced ) {
		update_option( 'users_can_register', 1 );
	} elseif ( 'off' === $forced ) {
		update_option( 'users_can_register', 0 );
	}

	delete_option( 'diluxone_users_register_mode' );
	delete_option( 'diluxone_users_wp_registration' );
	update_option( 'diluxone_users_registration_v2', 1, false );
}
add_action( 'admin_init', 'diluxone_users_migrate_registration', 1 );

/**
 * "I forgot my password" grows a third answer, and nobody's site moves.
 *
 * It used to have two, and where the new password was actually typed was
 * decided somewhere else entirely — by what happens to wp-login.php. So the
 * old value is read together with that, and written as the answer that keeps
 * the site doing exactly what it did:
 *
 *   'site'  meant "the lost-password link goes to the sign-in page"  → 'link'
 *   'wp'    with wp-login taken over, meant the new password was typed on the
 *           site's own screen                                        → 'site'
 *   'wp'    otherwise                                                → 'wp'
 */
function diluxone_users_migrate_lost_password(): void {
	if ( get_option( 'diluxone_users_lost_password_v2' ) ) {
		return;
	}

	$old = (string) get_option( 'diluxone_users_lost_password', 'wp' );

	if ( 'site' === $old ) {
		update_option( 'diluxone_users_lost_password', 'link' );
	} elseif ( diluxone_users_wp_screens_taken() && (int) get_option( 'diluxone_users_login_page' ) > 0 ) {
		update_option( 'diluxone_users_lost_password', 'site' );
	}

	update_option( 'diluxone_users_lost_password_v2', 1, false );
}
add_action( 'admin_init', 'diluxone_users_migrate_lost_password', 1 );
add_action( 'wp_initialize_site', 'diluxone_users_migrate' );
