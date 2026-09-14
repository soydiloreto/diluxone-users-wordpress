<?php
/**
 * Everything the plugin draws, in one place.
 *
 * The look used to live wherever the setting it belonged to lived: the shape
 * of the sign-in page on the sign-in screen, the account template on the
 * account screen, the social buttons on the social screen, the colours on
 * whichever of them you happened to open first. Seven places for one
 * question, and no way to see the result as a whole — which is how a site
 * ends up with a blue sign-in page and a green account area.
 *
 * So the screens keep the rules and this one keeps the look. A designer opens
 * one screen; an administrator setting up who can register never comes here
 * at all.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** The design screen, with its tabs. */
function diluxone_users_screen_design(): void {
	$tabs = array(
		'brand'   => __( 'Your brand', 'diluxone-users' ),
		'login'   => __( 'Sign in and registration', 'diluxone-users' ),
		'account' => __( 'The account area', 'diluxone-users' ),
		'social'  => __( 'Social buttons', 'diluxone-users' ),
		'photo'   => __( 'Profile photo', 'diluxone-users' ),
		'wp'      => __( 'The WordPress screen', 'diluxone-users' ),
	);

	$current = diluxone_users_tab( $tabs );

	if ( isset( $_POST['diluxone_users_design_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_design_nonce'] ) ), 'diluxone_users_design' ) ) {
		diluxone_users_screen_design_save( $current );
		diluxone_users_notice( __( 'Saved.', 'diluxone-users' ) );
	}

	diluxone_users_screen_open( diluxone_users_screens()['diluxone-users-design'], 'diluxone-users-design', $tabs, $current );

	echo '<form method="post">';
	wp_nonce_field( 'diluxone_users_design', 'diluxone_users_design_nonce' );

	switch ( $current ) {
		case 'login':
			diluxone_users_screen_login_shape();
			diluxone_users_screen_login_words();
			break;

		case 'account':
			diluxone_users_screen_appearance_template();
			break;

		case 'social':
			diluxone_users_screen_social_buttons( diluxone_users_sso_providers() );
			break;

		case 'photo':
			diluxone_users_screen_appearance_photo();
			break;

		case 'wp':
			diluxone_users_screen_login_wp();
			break;

		default:
			diluxone_users_screen_design_brand();
	}

	// The buttons tab carries its own submit, inside its left-hand column: it
	// is a two-column layout and a button under the whole thing would sit
	// under the preview.
	if ( 'social' !== $current ) {
		submit_button();
	}

	echo '</form>';

	/*
	 * The previews go after the form and not inside it: the sign-in form and
	 * the account area are forms of their own, and a form inside a form is
	 * thrown away by the browser.
	 */
	if ( 'login' === $current ) {
		diluxone_users_login_preview();
	}

	if ( in_array( $current, array( 'brand', 'account' ), true ) ) {
		diluxone_users_style_preview();
	}

	diluxone_users_screen_close();
}

/** Saves whatever the tab being looked at submits. */
function diluxone_users_screen_design_save( string $tab ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the caller verifies it.
	if ( 'login' === $tab ) {
		diluxone_users_save_options(
			array(
				'diluxone_users_login_template' => sanitize_key( wp_unslash( $_POST['diluxone_users_login_template'] ?? 'plain' ) ),
				'diluxone_users_login_side'     => 'right' === sanitize_key( wp_unslash( $_POST['diluxone_users_login_side'] ?? 'left' ) ) ? 'right' : 'left',
				'diluxone_users_login_image'    => absint( wp_unslash( $_POST['diluxone_users_login_image'] ?? 0 ) ),
				'diluxone_users_login_title'    => sanitize_text_field( wp_unslash( $_POST['diluxone_users_login_title'] ?? '' ) ),
				'diluxone_users_login_intro'    => sanitize_text_field( wp_unslash( $_POST['diluxone_users_login_intro'] ?? '' ) ),
				// Links allowed, and only links: the terms and the privacy
				// policy are pages, and a legal line that cannot point at them
				// is not one. wp_kses_post() is the same filter a post goes
				// through, so nothing gets in here that could not be published.
				'diluxone_users_login_legal'    => wp_kses_post( wp_unslash( $_POST['diluxone_users_login_legal'] ?? '' ) ),
				'diluxone_users_sent_title'     => sanitize_text_field( wp_unslash( $_POST['diluxone_users_sent_title'] ?? '' ) ),
				'diluxone_users_sent_note'      => sanitize_text_field( wp_unslash( $_POST['diluxone_users_sent_note'] ?? '' ) ),
			)
		);

		return;
	}

	if ( 'wp' === $tab ) {
		diluxone_users_save_options(
			array(
				'diluxone_users_wp_login_brand' => isset( $_POST['diluxone_users_wp_login_brand'] ) ? 1 : 0,
				'diluxone_users_wp_login_logo'  => absint( wp_unslash( $_POST['diluxone_users_wp_login_logo'] ?? 0 ) ),
				'diluxone_users_wp_login_bg'    => sanitize_hex_color( wp_unslash( $_POST['diluxone_users_wp_login_bg'] ?? '' ) ) ?? '',
			)
		);

		return;
	}

	if ( 'social' === $tab ) {
		diluxone_users_save_options( diluxone_users_sso_buttons_posted() );

		return;
	}

	if ( 'photo' === $tab ) {
		diluxone_users_save_options(
			array(
				'diluxone_users_avatar_upload'   => isset( $_POST['diluxone_users_avatar_upload'] ) ? 1 : 0,
				'diluxone_users_avatar_gravatar' => isset( $_POST['diluxone_users_avatar_gravatar'] ) ? 1 : 0,
				'diluxone_users_avatar_initials' => isset( $_POST['diluxone_users_avatar_initials'] ) ? 1 : 0,
				'diluxone_users_avatar_max_kb'   => absint( wp_unslash( $_POST['diluxone_users_avatar_max_kb'] ?? 2048 ) ),
			)
		);

		return;
	}

	if ( 'account' === $tab ) {
		diluxone_users_save_options(
			array(
				'diluxone_users_account_template' => 'cover' === sanitize_key( wp_unslash( $_POST['diluxone_users_account_template'] ?? '' ) ) ? 'cover' : 'plain',
				'diluxone_users_account_layout'   => sanitize_key( wp_unslash( $_POST['diluxone_users_account_layout'] ?? 'tabs' ) ),
				'diluxone_users_account_width'    => 'full' === sanitize_key( wp_unslash( $_POST['diluxone_users_account_width'] ?? '' ) ) ? 'full' : 'contained',
				'diluxone_users_account_header'   => isset( $_POST['diluxone_users_account_header'] ) ? 1 : 0,
				'diluxone_users_account_avatar'   => isset( $_POST['diluxone_users_account_avatar'] ) ? 1 : 0,
				'diluxone_users_account_since'    => isset( $_POST['diluxone_users_account_since'] ) ? 1 : 0,
				'diluxone_users_account_action'   => isset( $_POST['diluxone_users_account_action'] ) ? 1 : 0,
				'diluxone_users_account_cover'    => sanitize_hex_color( wp_unslash( $_POST['diluxone_users_account_cover'] ?? '' ) ) ?? '',
			)
		);

		return;
	}

	// The brand: what every screen reads.
	diluxone_users_save_options(
		array(
			'diluxone_users_styles'       => isset( $_POST['diluxone_users_styles'] ) ? 1 : 0,
			'diluxone_users_style_accent' => sanitize_hex_color( wp_unslash( $_POST['diluxone_users_style_accent'] ?? '' ) ) ?? '',
			'diluxone_users_style_radius' => sanitize_text_field( wp_unslash( $_POST['diluxone_users_style_radius'] ?? '' ) ),
			'diluxone_users_login_logo'   => absint( wp_unslash( $_POST['diluxone_users_login_logo'] ?? 0 ) ),
		)
	);
	// phpcs:enable
}
