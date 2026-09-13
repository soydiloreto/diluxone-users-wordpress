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

/** The mark saying the move has already been made on this site. */
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
	$old_names = $wpdb->get_col(
		$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
	);

	if ( array() === (array) $old_names ) {
		return;
	}

	foreach ( (array) $old_names as $old ) {
		delete_option( DILUXONE_USERS_PREFIX . substr( (string) $old, $length ) );
	}

	// SUBSTRING from the prefix length and not REPLACE: REPLACE would also
	// change a `usmw_` appearing in the middle of the key, and what moves here
	// is the prefix, not every occurrence.
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->options}
			    SET option_name = CONCAT( %s, SUBSTRING( option_name, %d ) )
			  WHERE option_name LIKE %s",
			DILUXONE_USERS_PREFIX,
			$from_pos,
			$like
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

/** Runs the move once, from any earlier prefix. */
function diluxone_users_migrate(): void {
	if ( get_option( DILUXONE_USERS_MIGRATED ) ) {
		return;
	}

	foreach ( diluxone_users_old_prefixes() as $old ) {
		diluxone_users_migrate_prefix( $old );
	}

	update_option( DILUXONE_USERS_MIGRATED, 1 );
}
add_action( 'admin_init', 'diluxone_users_migrate', 0 );
add_action( 'wp_initialize_site', 'diluxone_users_migrate' );
