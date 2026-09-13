<?php
/**
 * What changes when this runs on a network of sites.
 *
 * On a WordPress multisite the accounts belong to the network and the
 * permissions belong to each site: somebody can exist and not be a member
 * here. A users plugin that ignores that creates accounts that sign in and
 * can do nothing, or leaves out people who already exist on the site next
 * door.
 *
 * The settings, on the other hand, stay per site on purpose: each site of a
 * network usually has its own account page, its own fields and its own
 * design. A network setting would force every site to ask for the same thing.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds somebody to this site if they are not a member yet.
 *
 * The same role as for a new account is used: if the site decided whoever
 * registers is a subscriber, whoever arrives from another site of the network
 * is one too.
 */
function diluxone_users_join_site( int $user_id ): void {
	if ( ! is_multisite() || $user_id <= 0 ) {
		return;
	}

	if ( is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
		return;
	}

	// Without open registration, existing on the network is not enough to get
	// in here: it is the same rule as for a new e-mail, and the same setting
	// decides it.
	if ( ! diluxone_users_option( 'diluxone_users_login_register' ) ) {
		return;
	}

	add_user_to_blog( get_current_blog_id(), $user_id, (string) diluxone_users_option( 'diluxone_users_login_role' ) );
}

/**
 * The starter fields, for the sites of a network.
 *
 * The activation hook runs once when the plugin is activated network-wide, so
 * a new site would be born with no fields at all. They are seeded the first
 * time somebody asks for them, and only if the option does not exist: a
 * deliberately empty list is respected.
 */
function diluxone_users_seed_fields(): void {
	if ( false === get_option( 'diluxone_users_fields', false ) ) {
		update_option( 'diluxone_users_fields', diluxone_users_default_fields() );

		return;
	}

	diluxone_users_seed_native_fields();
}
add_action( 'wp_initialize_site', 'diluxone_users_seed_fields' );
add_action( 'admin_init', 'diluxone_users_seed_fields' );

/**
 * WordPress's own fields, on a site that already had the list assembled.
 *
 * They are added at the beginning and only the missing ones. A site that was
 * already drawing the name on its own is going to see two: that is correct
 * and is fixed by turning its own off, not by hiding the one WordPress
 * already had.
 */
function diluxone_users_seed_native_fields(): void {
	$fields  = (array) get_option( 'diluxone_users_fields', array() );
	$keys    = array_column( $fields, 'key' );
	$missing = array();

	foreach ( diluxone_users_default_fields() as $field ) {
		if ( diluxone_users_field_is_native( $field['key'] ) && ! in_array( $field['key'], $keys, true ) ) {
			$missing[] = $field;
		}
	}

	if ( array() === $missing ) {
		return;
	}

	update_option( 'diluxone_users_fields', array_merge( $missing, $fields ) );
}
